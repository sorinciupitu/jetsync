<?php
declare( strict_types=1 );

namespace JetSync\Migration\Step;

use JetSync\Migration\IntegrityChecker;

class ReadinessCheckStep implements MigrationStepInterface {

	public function get_id() : string {
		return 'readiness_check';
	}

	public function get_label() : string {
		return __( 'Readiness Check', 'jet-sync' );
	}

	public function get_description() : string {
		return __( 'Runs validations before finalizing migration and (optionally) deactivating JetEngine.', 'jet-sync' );
	}

	public function execute() : bool|\WP_Error {
		$checker = new IntegrityChecker();
		$report  = $checker->run( true );

		$errors = (int) ( $report['summary']['errors'] ?? 0 );
		if ( $errors > 0 ) {
			$messages = [];
			foreach ( (array) ( $report['checks'] ?? [] ) as $check ) {
				if ( isset( $check['status'], $check['message'] ) && 'error' === $check['status'] ) {
					$messages[] = (string) $check['message'];
				}
			}

			return new \WP_Error(
				'jetsync_readiness_failed',
				sprintf(
					/* translators: 1: error count, 2: messages */
					__( 'Readiness check failed with %1$d error(s): %2$s', 'jet-sync' ),
					$errors,
					implode( ' | ', array_slice( $messages, 0, 5 ) )
				)
			);
		}

		return true;
	}

	public function rollback() : bool|\WP_Error {
		return true;
	}

	public function is_reversible() : bool {
		return true;
	}
}

