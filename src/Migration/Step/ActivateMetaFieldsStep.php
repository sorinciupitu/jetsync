<?php
declare( strict_types=1 );

namespace JetSync\Migration\Step;

use JetSync\Core\JetSync;

/**
 * Migration Step: Activate all registered Meta Fields / Meta Boxes.
 */
class ActivateMetaFieldsStep implements MigrationStepInterface {

	/** {@inheritdoc} */
	public function get_id() : string {
		return 'activate_meta_fields';
	}

	/** {@inheritdoc} */
	public function get_label() : string {
		return __( 'Activate Meta Fields', 'jet-sync' );
	}

	/** {@inheritdoc} */
	public function get_description() : string {
		return __( 'Marks all JetSync-registered meta boxes and their fields as active, enabling native WordPress meta rendering.', 'jet-sync' );
	}

	/** {@inheritdoc} */
	public function execute() : bool|\WP_Error {
		/** @var \JetSync\Registry\MetaBoxRegistry|null $registry */
		$registry = JetSync::get_instance()->get( 'metabox_registry' );
		if ( ! $registry ) {
			return new \WP_Error( 'jetsync_no_metabox_registry', __( 'MetaBox Registry not found.', 'jet-sync' ) );
		}

		foreach ( $registry->get_all() as $box ) {
			$data           = $box->to_array();
			$data['active'] = true;
			$updated = \JetSync\Registry\Model\MetaBoxDefinition::from_array( $data );
			$registry->add( $updated );
		}

		return true;
	}

	/** {@inheritdoc} */
	public function rollback() : bool|\WP_Error {
		/** @var \JetSync\Registry\MetaBoxRegistry|null $registry */
		$registry = JetSync::get_instance()->get( 'metabox_registry' );
		if ( ! $registry ) {
			return true;
		}
		foreach ( $registry->get_all() as $box ) {
			$data           = $box->to_array();
			$data['active'] = false;
			$updated = \JetSync\Registry\Model\MetaBoxDefinition::from_array( $data );
			$registry->add( $updated );
		}
		return true;
	}

	/** {@inheritdoc} */
	public function is_reversible() : bool {
		return true;
	}
}
