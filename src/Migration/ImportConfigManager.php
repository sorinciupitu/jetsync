<?php
declare( strict_types=1 );

namespace JetSync\Migration;

use JetSync\Core\JetSync;
use JetSync\Registry\Model\PostTypeDefinition;
use JetSync\Registry\Model\TaxonomyDefinition;
use JetSync\Registry\Model\MetaBoxDefinition;
use JetSync\Registry\Model\RelationDefinition;
use JetSync\Registry\Model\ListingDefinition;
use JetSync\Registry\Model\QueryDefinition;

/**
 * Imports a JetSync JSON configuration package back into the registries.
 *
 * Works in merge mode: existing entries with the same slug are overwritten,
 * entries not present in the JSON are left untouched.
 */
class ImportConfigManager {

	/**
	 * Parse and validate a JSON string, returning the data array or WP_Error.
	 *
	 * @param string $json Raw JSON content.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function parse( string $json ) : array|\WP_Error {
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'jetsync_json_parse_error',
				/* translators: %s: json error message */
				sprintf( __( 'Invalid JSON: %s', 'jet-sync' ), json_last_error_msg() )
			);
		}

		if ( ! is_array( $data ) || ! isset( $data['_meta'] ) ) {
			return new \WP_Error(
				'jetsync_invalid_export',
				__( 'The file does not appear to be a valid JetSync export.', 'jet-sync' )
			);
		}

		return $data;
	}

	/**
	 * Import a full configuration array into the active registries.
	 *
	 * @param array<string, mixed> $data Parsed export array.
	 * @return array<string, int>  Counts of imported items per type.
	 */
	public function import( array $data ) : array {
		$plugin  = JetSync::get_instance();
		$counts  = [
			'post_types'     => 0,
			'taxonomies'     => 0,
			'meta_boxes'     => 0,
			'relations'      => 0,
			'listings'       => 0,
			'queries'        => 0,
			'relations_data' => 0,
		];

		// --- CPTs ---
		/** @var \JetSync\Registry\PostTypeRegistry|null $cpt_reg */
		$cpt_reg = $plugin->get( 'cpt_registry' );
		if ( $cpt_reg && ! empty( $data['post_types'] ) ) {
			foreach ( (array) $data['post_types'] as $row ) {
				if ( is_array( $row ) && ! empty( $row['slug'] ) ) {
					$cpt_reg->add( PostTypeDefinition::from_array( $row ) );
					$counts['post_types']++;
				}
			}
		}

		// --- Taxonomies ---
		/** @var \JetSync\Registry\TaxonomyRegistry|null $tax_reg */
		$tax_reg = $plugin->get( 'taxonomy_registry' );
		if ( $tax_reg && ! empty( $data['taxonomies'] ) ) {
			foreach ( (array) $data['taxonomies'] as $row ) {
				if ( is_array( $row ) && ! empty( $row['slug'] ) ) {
					$tax_reg->add( TaxonomyDefinition::from_array( $row ) );
					$counts['taxonomies']++;
				}
			}
		}

		// --- MetaBoxes ---
		/** @var \JetSync\Registry\MetaBoxRegistry|null $meta_reg */
		$meta_reg = $plugin->get( 'metabox_registry' );
		if ( $meta_reg && ! empty( $data['meta_boxes'] ) ) {
			foreach ( (array) $data['meta_boxes'] as $row ) {
				if ( is_array( $row ) && ! empty( $row['id'] ) ) {
					$meta_reg->add( MetaBoxDefinition::from_array( $row ) );
					$counts['meta_boxes']++;
				}
			}
		}

		// --- Relations ---
		/** @var \JetSync\Registry\RelationRegistry|null $rel_reg */
		$rel_reg = $plugin->get( 'relation_registry' );
		if ( $rel_reg && ! empty( $data['relations'] ) ) {
			foreach ( (array) $data['relations'] as $row ) {
				if ( is_array( $row ) && ! empty( $row['id'] ) ) {
					$rel_reg->add( RelationDefinition::from_array( $row ) );
					$counts['relations']++;
				}
			}
		}

		// --- Listings ---
		/** @var \JetSync\Registry\ListingRegistry|null $listing_reg */
		$listing_reg = $plugin->get( 'listing_registry' );
		if ( $listing_reg && ! empty( $data['listings'] ) ) {
			foreach ( (array) $data['listings'] as $row ) {
				if ( is_array( $row ) && ! empty( $row['id'] ) ) {
					$listing_reg->add( ListingDefinition::from_array( $row ) );
					$counts['listings']++;
				}
			}
		}

		// --- Queries (Elementor Query Builder etc., created after migration) ---
		/** @var \JetSync\Registry\QueryRegistry|null $query_reg */
		$query_reg = $plugin->get( 'query_registry' );
		if ( $query_reg && ! empty( $data['queries'] ) ) {
			foreach ( (array) $data['queries'] as $row ) {
				if ( is_array( $row ) && ! empty( $row['id'] ) ) {
					$query_reg->add( QueryDefinition::from_array( $row ) );
					$counts['queries']++;
				}
			}
		}

		// --- Relations data (actual links, e.g. Women<->Video/News created after migration) ---
		if ( ! empty( $data['relations_data'] ) && is_array( $data['relations_data'] ) ) {
			$counts['relations_data'] = $this->import_relations_data( $data['relations_data'] );
		}

		return $counts;
	}

	/**
	 * Import relation link rows into jetsync_relations table.
	 *
	 * @param array<int, mixed> $rows
	 * @return int
	 */
	private function import_relations_data( array $rows ) : int {
		global $wpdb;
		$table = $wpdb->prefix . 'jetsync_relations';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return 0;
		}
		$count = 0;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) continue;
			$rel_id = isset( $row['rel_id'] ) ? sanitize_key( (string) $row['rel_id'] ) : '';
			$parent_id = isset( $row['parent_id'] ) ? (int) $row['parent_id'] : 0;
			$child_id = isset( $row['child_id'] ) ? (int) $row['child_id'] : 0;
			if ( '' === $rel_id || $parent_id <= 0 || $child_id <= 0 ) continue;
			$parent_type = isset( $row['parent_type'] ) && is_string( $row['parent_type'] ) ? sanitize_key( $row['parent_type'] ) : ( get_post_type( $parent_id ) ?: 'post' );
			$child_type  = isset( $row['child_type'] ) && is_string( $row['child_type'] ) ? sanitize_key( $row['child_type'] ) : ( get_post_type( $child_id ) ?: 'post' );
			$res = $wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO {$table} (rel_id, parent_id, child_id, parent_type, child_type) VALUES (%s,%d,%d,%s,%s)",
				$rel_id, $parent_id, $child_id, $parent_type, $child_type
			) );
			if ( $res ) $count += (int) $res;
		}
		return $count;
	}

	/**
	 * Parse and immediately import a JSON string.
	 *
	 * @param string $json Raw JSON content.
	 * @return array<string, mixed>|\WP_Error Counts array or WP_Error.
	 */
	public function import_from_json( string $json ) : array|\WP_Error {
		$data = $this->parse( $json );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return $this->import( $data );
	}
}
