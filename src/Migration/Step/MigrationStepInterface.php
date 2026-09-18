<?php
declare( strict_types=1 );

namespace JetSync\Migration\Step;

/**
 * Contract every migration step must satisfy.
 */
interface MigrationStepInterface {

	/**
	 * Unique machine-readable identifier for this step.
	 *
	 * @return string
	 */
	public function get_id() : string;

	/**
	 * Human-readable label shown in the wizard UI.
	 *
	 * @return string
	 */
	public function get_label() : string;

	/**
	 * Human-readable description of what this step does.
	 *
	 * @return string
	 */
	public function get_description() : string;

	/**
	 * Execute the migration step.
	 *
	 * Should return a WP_Error on failure or true on success.
	 *
	 * @return true|\WP_Error
	 */
	public function execute() : bool|\WP_Error;

	/**
	 * Roll back the actions performed by this step if possible.
	 *
	 * @return true|\WP_Error
	 */
	public function rollback() : bool|\WP_Error;

	/**
	 * Whether this step can be safely rolled back.
	 *
	 * @return bool
	 */
	public function is_reversible() : bool;
}
