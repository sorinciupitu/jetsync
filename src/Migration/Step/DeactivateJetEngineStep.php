<?php
declare( strict_types=1 );

namespace JetSync\Migration\Step;

/**
 * Migration Step: Deactivate JetEngine plugin.
 *
 * IMPORTANT: This step is NON-reversible. Once JetEngine is deactivated,
 * its CPTs, Taxonomies and Meta Fields registered natively by JetEngine
 * will cease to exist on that request cycle. The user must have confirmed
 * this action explicitly before the plan runs this step.
 *
 * The step only deactivates — it does NOT delete any data.
 */
class DeactivateJetEngineStep implements MigrationStepInterface {

	/**
	 * Slug of the JetEngine main plugin file.
	 *
	 * @var string
	 */
	private string $je_plugin = 'jet-engine/jet-engine.php';

	/** {@inheritdoc} */
	public function get_id() : string {
		return 'deactivate_jet_engine';
	}

	/** {@inheritdoc} */
	public function get_label() : string {
		return __( 'Deactivate JetEngine', 'jet-sync' );
	}

	/** {@inheritdoc} */
	public function get_description() : string {
		return __( 'Safely deactivates the JetEngine plugin. JetSync will then serve all CPTs, Taxonomies, Meta Fields and Relations natively. No data is deleted.', 'jet-sync' );
	}

	/** {@inheritdoc} */
	public function execute() : bool|\WP_Error {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( $this->je_plugin ) ) {
			// JetEngine already inactive — consider step successful.
			return true;
		}

		deactivate_plugins( $this->je_plugin, true );

		if ( is_plugin_active( $this->je_plugin ) ) {
			return new \WP_Error(
				'jetsync_deactivation_failed',
				__( 'JetEngine could not be deactivated. Please deactivate it manually from the Plugins screen.', 'jet-sync' )
			);
		}

		return true;
	}

	/** {@inheritdoc} */
	public function rollback() : bool|\WP_Error {
		// Re-activating JetEngine would restore it but could conflict with
		// already-active JetSync registrations. Leave this to the user.
		return new \WP_Error(
			'jetsync_not_reversible',
			__( 'Deactivating JetEngine cannot be automatically rolled back. Please re-activate JetEngine manually from the Plugins screen if needed.', 'jet-sync' )
		);
	}

	/** {@inheritdoc} */
	public function is_reversible() : bool {
		return false;
	}
}
