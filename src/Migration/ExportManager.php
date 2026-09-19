<?php
declare( strict_types=1 );

namespace JetSync\Migration;

use JetSync\Core\JetSync;

/**
 * Exports the complete JetSync configuration as a portable JSON package.
 *
 * The exported JSON contains CPTs, Taxonomies, MetaBoxes, Relations,
 * Listings, Queries and relation link data — enough to fully reconstruct
 * a JetSync installation including items created after initial migration
 * (e.g. Elementor Query Builder queries).
 */
class ExportManager {

	/**
	 * Export version tag embedded in the JSON.
	 */
	const EXPORT_VERSION = '1.1';

	/**
	 * Build and return the full configuration as an array.
	 *
	 * @return array<string, mixed>
	 */
	public function build_export() : array {
		$plugin = JetSync::get_instance();

		/** @var \JetSync\Registry\PostTypeRegistry|null $cpt_reg */
		$cpt_reg = $plugin->get( 'cpt_registry' );
		/** @var \JetSync\Registry\TaxonomyRegistry|null $tax_reg */
		$tax_reg = $plugin->get( 'taxonomy_registry' );
		/** @var \JetSync\Registry\MetaBoxRegistry|null $meta_reg */
		$meta_reg = $plugin->get( 'metabox_registry' );
		/** @var \JetSync\Registry\RelationRegistry|null $rel_reg */
		$rel_reg = $plugin->get( 'relation_registry' );
		/** @var \JetSync\Registry\ListingRegistry|null $listing_reg */
		$listing_reg = $plugin->get( 'listing_registry' );
		/** @var \JetSync\Registry\QueryRegistry|null $query_reg */
		$query_reg = $plugin->get( 'query_registry' );

		$cpts       = $cpt_reg  ? array_map( static fn( $d ) => $d->to_array(), $cpt_reg->get_all() )  : [];
		$taxonomies = $tax_reg  ? array_map( static fn( $d ) => $d->to_array(), $tax_reg->get_all() )  : [];
		$metaboxes  = $meta_reg ? array_map( static fn( $d ) => $d->to_array(), $meta_reg->get_all() ) : [];
		$relations  = $rel_reg  ? array_map( static fn( $d ) => $d->to_array(), $rel_reg->get_all() )  : [];
		$listings   = $listing_reg ? array_map( static fn( $d ) => $d->to_array(), $listing_reg->get_all() ) : [];
		$queries    = $query_reg ? array_map( static fn( $d ) => $d->to_array(), $query_reg->get_all() ) : [];

		// Relations link data (actual connections created after migration)
		$relations_data = $this->collect_relations_data();

		return [
			'_meta' => [
				'export_version' => self::EXPORT_VERSION,
				'jetsync_version' => defined( 'JETSYNC_VERSION' ) ? JETSYNC_VERSION : 'unknown',
				'site_url'       => get_site_url(),
				'exported_at'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
			],
			'post_types'     => array_values( $cpts ),
			'taxonomies'     => array_values( $taxonomies ),
			'meta_boxes'     => array_values( $metaboxes ),
			'relations'      => array_values( $relations ),
			'listings'       => array_values( $listings ),
			'queries'        => array_values( $queries ),
			'relations_data' => $relations_data,
		];
	}

	/**
	 * Collect all rows from jetsync_relations for full data export.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_relations_data() : array {
		global $wpdb;
		$table = $wpdb->prefix . 'jetsync_relations';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return [];
		}
		$rows = $wpdb->get_results( "SELECT rel_id, parent_id, child_id, parent_type, child_type FROM {$table} ORDER BY rel_id, parent_id, child_id LIMIT 50000", ARRAY_A );
		return is_array( $rows ) ? array_values( $rows ) : [];
	}

	/**
	 * Serve the export as a downloadable JSON file (call from admin context).
	 *
	 * @return void
	 */
	public function serve_download() : void {
		$data     = $this->build_export();
		$json     = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		$filename = 'jetsync-export-' . gmdate( 'Y-m-d' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $json ) );

		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Return the JSON string without serving it as a download.
	 *
	 * @return string
	 */
	public function get_json() : string {
		return (string) wp_json_encode( $this->build_export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}
}
