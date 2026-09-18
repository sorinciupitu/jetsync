<?php
declare( strict_types=1 );

namespace JetSync\Migration;

/**
 * Represents a serialisable migration plan stored in wp_options.
 *
 * A plan consists of an ordered list of step IDs, a current index pointer,
 * an overall status, and per-step result log entries.
 */
class MigrationPlan {

	const STATUS_PENDING    = 'pending';
	const STATUS_RUNNING    = 'running';
	const STATUS_COMPLETED  = 'completed';
	const STATUS_FAILED     = 'failed';
	const STATUS_ROLLED_BACK = 'rolled_back';

	const OPTION_KEY = 'jetsync_migration_plan';

	/**
	 * Ordered list of step IDs to execute.
	 *
	 * @var string[]
	 */
	private array $steps = [];

	/**
	 * Index of the currently executing step (0-based).
	 *
	 * @var int
	 */
	private int $current_step = 0;

	/**
	 * Overall plan status.
	 *
	 * @var string
	 */
	private string $status = self::STATUS_PENDING;

	/**
	 * Per-step log: step_id => ['status' => ..., 'message' => ..., 'time' => ...].
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $log = [];

	/**
	 * Timestamp (Unix) of when the plan was created.
	 *
	 * @var int
	 */
	private int $created_at;

	/**
	 * Constructor.
	 *
	 * @param string[] $steps Ordered step identifiers.
	 */
	public function __construct( array $steps = [] ) {
		$this->steps      = $steps;
		$this->created_at = time();
	}

	// -------------------------------------------------------------------------
	// Getters
	// -------------------------------------------------------------------------

	/** @return string[] */
	public function get_steps() : array {
		return $this->steps;
	}

	public function get_current_step_index() : int {
		return $this->current_step;
	}

	public function get_current_step_id() : ?string {
		return $this->steps[ $this->current_step ] ?? null;
	}

	public function get_status() : string {
		return $this->status;
	}

	/** @return array<string, array<string, mixed>> */
	public function get_log() : array {
		return $this->log;
	}

	public function get_created_at() : int {
		return $this->created_at;
	}

	public function is_complete() : bool {
		return in_array( $this->status, [ self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_ROLLED_BACK ], true );
	}

	// -------------------------------------------------------------------------
	// Mutators
	// -------------------------------------------------------------------------

	/**
	 * Mark a step as successful and advance the pointer.
	 *
	 * @param string $step_id  The step that succeeded.
	 * @param string $message  Human-readable success message.
	 * @return void
	 */
	public function mark_step_success( string $step_id, string $message = '' ) : void {
		$this->log[ $step_id ] = [
			'status'  => 'success',
			'message' => $message,
			'time'    => time(),
		];
		$this->current_step++;

		if ( $this->current_step >= count( $this->steps ) ) {
			$this->status = self::STATUS_COMPLETED;
		}
	}

	/**
	 * Mark a step as failed and set overall plan status to failed.
	 *
	 * @param string $step_id  The step that failed.
	 * @param string $message  Error message.
	 * @return void
	 */
	public function mark_step_failed( string $step_id, string $message = '' ) : void {
		$this->log[ $step_id ] = [
			'status'  => 'failed',
			'message' => $message,
			'time'    => time(),
		];
		$this->status = self::STATUS_FAILED;
	}

	/**
	 * Set overall status.
	 *
	 * @param string $status One of the STATUS_* constants.
	 * @return void
	 */
	public function set_status( string $status ) : void {
		$this->status = $status;
	}

	// -------------------------------------------------------------------------
	// Persistence
	// -------------------------------------------------------------------------

	/**
	 * Persist the plan to wp_options.
	 *
	 * @return void
	 */
	public function save() : void {
		update_option( self::OPTION_KEY, $this->to_array(), false );
	}

	/**
	 * Load a saved plan from wp_options, or null if none exists.
	 *
	 * @return self|null
	 */
	public static function load() : ?self {
		$data = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $data ) ) {
			return null;
		}
		return self::from_array( $data );
	}

	/**
	 * Delete the saved plan from wp_options.
	 *
	 * @return void
	 */
	public static function clear() : void {
		delete_option( self::OPTION_KEY );
	}

	// -------------------------------------------------------------------------
	// Serialisation helpers
	// -------------------------------------------------------------------------

	/** @return array<string, mixed> */
	public function to_array() : array {
		return [
			'steps'        => $this->steps,
			'current_step' => $this->current_step,
			'status'       => $this->status,
			'log'          => $this->log,
			'created_at'   => $this->created_at,
		];
	}

	/**
	 * @param array<string, mixed> $data
	 * @return self
	 */
	public static function from_array( array $data ) : self {
		$plan               = new self( $data['steps'] ?? [] );
		$plan->current_step = (int) ( $data['current_step'] ?? 0 );
		$plan->status       = (string) ( $data['status'] ?? self::STATUS_PENDING );
		$plan->log          = (array) ( $data['log'] ?? [] );
		$plan->created_at   = (int) ( $data['created_at'] ?? time() );
		return $plan;
	}
}
