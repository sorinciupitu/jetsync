<?php
declare( strict_types=1 );

namespace JetSync\Migration\Step;

use JetSync\Core\JetSync;

/**
 * Migration Step: Activate all registered Relations.
 */
class ActivateRelationsStep implements MigrationStepInterface {

	/** {@inheritdoc} */
	public function get_id() : string {
		return 'activate_relations';
	}

	/** {@inheritdoc} */
	public function get_label() : string {
		return __( 'Activate Relations Engine', 'jet-sync' );
	}

	/** {@inheritdoc} */
	public function get_description() : string {
		return __( 'Marks all JetSync-registered relation schemas as active, enabling the native Relations Engine.', 'jet-sync' );
	}

	/** {@inheritdoc} */
	public function execute() : bool|\WP_Error {
		/** @var \JetSync\Registry\RelationRegistry|null $registry */
		$registry = JetSync::get_instance()->get( 'relation_registry' );
		if ( ! $registry ) {
			return new \WP_Error( 'jetsync_no_relation_registry', __( 'Relation Registry not found.', 'jet-sync' ) );
		}

		foreach ( $registry->get_all() as $definition ) {
			$data           = $definition->to_array();
			$data['active'] = true;
			$updated = \JetSync\Registry\Model\RelationDefinition::from_array( $data );
			$registry->add( $updated );
		}

		return true;
	}

	/** {@inheritdoc} */
	public function rollback() : bool|\WP_Error {
		/** @var \JetSync\Registry\RelationRegistry|null $registry */
		$registry = JetSync::get_instance()->get( 'relation_registry' );
		if ( ! $registry ) {
			return true;
		}
		foreach ( $registry->get_all() as $definition ) {
			$data           = $definition->to_array();
			$data['active'] = false;
			$updated = \JetSync\Registry\Model\RelationDefinition::from_array( $data );
			$registry->add( $updated );
		}
		return true;
	}

	/** {@inheritdoc} */
	public function is_reversible() : bool {
		return true;
	}
}
