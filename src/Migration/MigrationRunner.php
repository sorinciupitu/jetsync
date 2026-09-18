<?php
declare( strict_types=1 );

namespace JetSync\Migration;

use JetSync\Migration\Step\MigrationStepInterface;
use JetSync\Migration\Step\ActivateCptsStep;
use JetSync\Migration\Step\ActivateTaxonomiesStep;
use JetSync\Migration\Step\ActivateMetaFieldsStep;
use JetSync\Migration\Step\ActivateRelationsStep;
use JetSync\Migration\Step\ReadinessCheckStep;
use JetSync\Migration\Step\DeactivateJetEngineStep;
use JetSync\Core\Logger;

/**
 * Orchestrates the sequential execution of a MigrationPlan.
 *
 * Each call to run_next_step() executes exactly one step and persists the
 * plan state, making it safe to run via AJAX in a paged fashion.
 */
class MigrationRunner {

	/**
	 * Step registry: id => instance.
	 *
	 * @var array<string, MigrationStepInterface>
	 */
	private array $step_registry = [];

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor. Registers all available steps.
	 *
	 * @param Logger $logger JetSync logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
		$this->register_default_steps();
	}

	// -------------------------------------------------------------------------
	// Step Registry
	// -------------------------------------------------------------------------

	/**
	 * Register the built-in steps in execution order.
	 *
	 * @return void
	 */
	private function register_default_steps() : void {
		foreach ( [
			new ActivateCptsStep(),
			new ActivateTaxonomiesStep(),
			new ActivateMetaFieldsStep(),
			new ActivateRelationsStep(),
			new ReadinessCheckStep(),
			new DeactivateJetEngineStep(),
		] as $step ) {
			$this->step_registry[ $step->get_id() ] = $step;
		}
	}

	/**
	 * Register a custom step (for extensibility).
	 *
	 * @param MigrationStepInterface $step
	 * @return void
	 */
	public function register_step( MigrationStepInterface $step ) : void {
		$this->step_registry[ $step->get_id() ] = $step;
	}

	/**
	 * Get all registered step objects indexed by ID.
	 *
	 * @return array<string, MigrationStepInterface>
	 */
	public function get_all_steps() : array {
		return $this->step_registry;
	}

	// -------------------------------------------------------------------------
	// Plan Factory
	// -------------------------------------------------------------------------

	/**
	 * Create and persist a new MigrationPlan from all registered steps.
	 *
	 * Passing $include_deactivate=false omits the JetEngine deactivation step
	 * (user can choose to keep JetEngine active).
	 *
	 * @param bool $include_deactivate Whether to include the DeactivateJetEngineStep.
	 * @return MigrationPlan
	 */
	public function create_plan( bool $include_deactivate = true ) : MigrationPlan {
		$step_ids = array_keys( $this->step_registry );

		if ( ! $include_deactivate ) {
			$step_ids = array_filter( $step_ids, static fn( $id ) => $id !== 'deactivate_jet_engine' );
			$step_ids = array_values( $step_ids );
		}

		$plan = new MigrationPlan( $step_ids );
		$plan->set_status( MigrationPlan::STATUS_PENDING );
		$plan->save();

		$this->logger->info( 'Migration plan created with steps: ' . implode( ', ', $step_ids ) );

		return $plan;
	}

	// -------------------------------------------------------------------------
	// Execution
	// -------------------------------------------------------------------------

	/**
	 * Load the saved plan and execute the next pending step.
	 *
	 * Returns an associative array with:
	 *   - 'status'       => overall plan status after this call
	 *   - 'step_id'      => the step that was executed
	 *   - 'step_result'  => 'success' | 'failed'
	 *   - 'message'      => human-readable result message
	 *   - 'progress'     => ['current' => int, 'total' => int]
	 *
	 * @return array<string, mixed>
	 */
	public function run_next_step() : array {
		$plan = MigrationPlan::load();

		if ( ! $plan ) {
			return [
				'status'      => 'error',
				'step_id'     => null,
				'step_result' => 'failed',
				'message'     => __( 'No migration plan found. Please start the wizard again.', 'jet-sync' ),
				'progress'    => [ 'current' => 0, 'total' => 0 ],
			];
		}

		if ( $plan->is_complete() ) {
			return [
				'status'      => $plan->get_status(),
				'step_id'     => null,
				'step_result' => 'success',
				'message'     => __( 'Migration already complete.', 'jet-sync' ),
				'progress'    => [
					'current' => count( $plan->get_steps() ),
					'total'   => count( $plan->get_steps() ),
				],
			];
		}

		$step_id = $plan->get_current_step_id();
		$step    = $this->step_registry[ $step_id ] ?? null;

		if ( ! $step ) {
			$msg = sprintf(
				/* translators: %s: step identifier */
				__( 'Unknown migration step: %s', 'jet-sync' ),
				$step_id
			);
			$plan->mark_step_failed( $step_id, $msg );
			$plan->save();
			$this->logger->error( $msg );

			return [
				'status'      => MigrationPlan::STATUS_FAILED,
				'step_id'     => $step_id,
				'step_result' => 'failed',
				'message'     => $msg,
				'progress'    => [
					'current' => $plan->get_current_step_index(),
					'total'   => count( $plan->get_steps() ),
				],
			];
		}

		$plan->set_status( MigrationPlan::STATUS_RUNNING );
		$plan->save();

		$this->logger->info( 'Running migration step: ' . $step_id );
		$result = $step->execute();

		if ( is_wp_error( $result ) ) {
			$msg = $result->get_error_message();
			$plan->mark_step_failed( $step_id, $msg );
			$plan->save();
			$this->logger->error( 'Step failed (' . $step_id . '): ' . $msg );

			return [
				'status'      => MigrationPlan::STATUS_FAILED,
				'step_id'     => $step_id,
				'step_result' => 'failed',
				'message'     => $msg,
				'progress'    => [
					'current' => $plan->get_current_step_index(),
					'total'   => count( $plan->get_steps() ),
				],
			];
		}

		$success_msg = sprintf(
			/* translators: %s: step label */
			__( 'Step "%s" completed successfully.', 'jet-sync' ),
			$step->get_label()
		);
		$plan->mark_step_success( $step_id, $success_msg );
		$plan->save();
		$this->logger->info( 'Step succeeded: ' . $step_id );

		return [
			'status'      => $plan->get_status(),
			'step_id'     => $step_id,
			'step_result' => 'success',
			'message'     => $success_msg,
			'progress'    => [
				'current' => $plan->get_current_step_index(),
				'total'   => count( $plan->get_steps() ),
			],
		];
	}

	// -------------------------------------------------------------------------
	// Rollback
	// -------------------------------------------------------------------------

	/**
	 * Roll back all completed reversible steps in reverse order.
	 *
	 * @return array<string, mixed>
	 */
	public function rollback() : array {
		$plan = MigrationPlan::load();
		if ( ! $plan ) {
			return [
				'status'  => 'error',
				'message' => __( 'No migration plan found.', 'jet-sync' ),
			];
		}

		$log     = $plan->get_log();
		$errors  = [];
		$rolled  = [];

		// Iterate completed steps in reverse
		$completed = array_filter( $log, static fn( $entry ) => $entry['status'] === 'success' );
		$step_ids  = array_reverse( array_keys( $completed ) );

		foreach ( $step_ids as $step_id ) {
			$step = $this->step_registry[ $step_id ] ?? null;
			if ( ! $step || ! $step->is_reversible() ) {
				continue;
			}

			$result = $step->rollback();
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
				$this->logger->error( 'Rollback failed for step (' . $step_id . '): ' . $result->get_error_message() );
			} else {
				$rolled[] = $step_id;
				$this->logger->info( 'Rolled back step: ' . $step_id );
			}
		}

		$plan->set_status( MigrationPlan::STATUS_ROLLED_BACK );
		$plan->save();

		return [
			'status'         => MigrationPlan::STATUS_ROLLED_BACK,
			'rolled_back'    => $rolled,
			'errors'         => $errors,
			'message'        => empty( $errors )
				? __( 'Rollback completed successfully.', 'jet-sync' )
				: __( 'Rollback completed with some errors. Check the log.', 'jet-sync' ),
		];
	}
}
