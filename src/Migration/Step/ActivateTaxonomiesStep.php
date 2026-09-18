<?php
declare( strict_types=1 );

namespace JetSync\Migration\Step;

use JetSync\Core\JetSync;

/**
 * Migration Step: Activate all registered Taxonomies.
 */
class ActivateTaxonomiesStep implements MigrationStepInterface {

	/** {@inheritdoc} */
	public function get_id() : string {
		return 'activate_taxonomies';
	}

	/** {@inheritdoc} */
	public function get_label() : string {
		return __( 'Activate Custom Taxonomies', 'jet-sync' );
	}

	/** {@inheritdoc} */
	public function get_description() : string {
		return __( 'Marks all JetSync-registered taxonomies as active and regenerates WordPress rewrite rules.', 'jet-sync' );
	}

	/** {@inheritdoc} */
	public function execute() : bool|\WP_Error {
		/** @var \JetSync\Registry\TaxonomyRegistry|null $registry */
		$registry = JetSync::get_instance()->get( 'taxonomy_registry' );
		if ( ! $registry ) {
			return new \WP_Error( 'jetsync_no_tax_registry', __( 'Taxonomy Registry not found.', 'jet-sync' ) );
		}

		foreach ( $registry->get_all() as $definition ) {
			$data           = $definition->to_array();
			$data['active'] = true;
			$updated = \JetSync\Registry\Model\TaxonomyDefinition::from_array( $data );
			$registry->add( $updated );
		}

		flush_rewrite_rules( false );
		return true;
	}

	/** {@inheritdoc} */
	public function rollback() : bool|\WP_Error {
		/** @var \JetSync\Registry\TaxonomyRegistry|null $registry */
		$registry = JetSync::get_instance()->get( 'taxonomy_registry' );
		if ( ! $registry ) {
			return true;
		}
		foreach ( $registry->get_all() as $definition ) {
			$data           = $definition->to_array();
			$data['active'] = false;
			$updated = \JetSync\Registry\Model\TaxonomyDefinition::from_array( $data );
			$registry->add( $updated );
		}
		flush_rewrite_rules( false );
		return true;
	}

	/** {@inheritdoc} */
	public function is_reversible() : bool {
		return true;
	}
}
