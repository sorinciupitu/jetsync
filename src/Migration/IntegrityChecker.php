<?php
declare( strict_types=1 );

namespace JetSync\Migration;

use JetSync\Core\JetSync;

class IntegrityChecker {

	private const OPTION_LAST_REPORT = 'jetsync_last_integrity_report';

	public static function get_last_report() : ?array {
		$report = get_option( self::OPTION_LAST_REPORT );
		return is_array( $report ) ? $report : null;
	}

	public function run( bool $for_deactivation = false ) : array {
		global $wpdb;

		$plugin = JetSync::get_instance();

		/** @var \JetSync\Registry\PostTypeRegistry|null $cpt_registry */
		$cpt_registry = $plugin->get( 'cpt_registry' );
		/** @var \JetSync\Registry\TaxonomyRegistry|null $taxonomy_registry */
		$taxonomy_registry = $plugin->get( 'taxonomy_registry' );
		/** @var \JetSync\Registry\MetaBoxRegistry|null $metabox_registry */
		$metabox_registry = $plugin->get( 'metabox_registry' );
		/** @var \JetSync\Registry\RelationRegistry|null $relation_registry */
		$relation_registry = $plugin->get( 'relation_registry' );

		$checks   = [];
		$errors   = 0;
		$warnings = 0;

		$cpt_defs  = $cpt_registry ? $cpt_registry->get_all() : [];
		$tax_defs  = $taxonomy_registry ? $taxonomy_registry->get_all() : [];
		$meta_defs = $metabox_registry ? $metabox_registry->get_all() : [];
		$rel_defs  = $relation_registry ? $relation_registry->get_all() : [];

		$checks[] = [
			'id'      => 'registry_counts',
			'status'  => 'ok',
			'message' => sprintf(
				/* translators: 1: cpt count, 2: taxonomy count, 3: meta boxes count, 4: relations count */
				__( 'Registries: %1$d CPTs, %2$d taxonomies, %3$d meta boxes, %4$d relations.', 'jet-sync' ),
				count( $cpt_defs ),
				count( $tax_defs ),
				count( $meta_defs ),
				count( $rel_defs )
			),
			'data'    => [
				'cpts'       => count( $cpt_defs ),
				'taxonomies' => count( $tax_defs ),
				'meta_boxes' => count( $meta_defs ),
				'relations'  => count( $rel_defs ),
			],
		];

		$table_relations = $wpdb->prefix . 'jetsync_relations';
		$table_meta      = $wpdb->prefix . 'jetsync_relation_meta';
		$tables          = [
			'table_relations' => $table_relations,
			'table_meta'      => $table_meta,
		];

		$tables_ok = true;
		foreach ( $tables as $key => $table_name ) {
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
			if ( $found !== $table_name ) {
				$tables_ok = false;
				$errors++;
				$checks[] = [
					'id'      => $key,
					'status'  => 'error',
					'message' => sprintf(
						/* translators: %s: db table name */
						__( 'Missing database table: %s', 'jet-sync' ),
						$table_name
					),
				];
			}
		}

		if ( $tables_ok ) {
			$checks[] = [
				'id'      => 'tables_exist',
				'status'  => 'ok',
				'message' => __( 'Relations tables exist.', 'jet-sync' ),
			];
		}

		$reserved_post_types = [
			'post',
			'page',
			'attachment',
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_navigation',
			'wp_font_family',
			'wp_font_face',
		];
		$reserved_taxonomies = [
			'category',
			'post_tag',
			'nav_menu',
			'link_category',
			'post_format',
		];

		$reserved_hits = [
			'post_types' => [],
			'taxonomies' => [],
		];

		if ( $for_deactivation ) {
			foreach ( $cpt_defs as $def ) {
				$slug = $def->get_slug();
				if ( in_array( $slug, $reserved_post_types, true ) ) {
					$reserved_hits['post_types'][] = $slug;
				}
			}
			foreach ( $tax_defs as $def ) {
				$slug = $def->get_slug();
				if ( in_array( $slug, $reserved_taxonomies, true ) ) {
					$reserved_hits['taxonomies'][] = $slug;
				}
			}

			if ( ! empty( $reserved_hits['post_types'] ) || ! empty( $reserved_hits['taxonomies'] ) ) {
				$errors++;
				$checks[] = [
					'id'      => 'reserved_slugs',
					'status'  => 'error',
					'message' => __( 'Reserved WordPress slugs detected in registries.', 'jet-sync' ),
					'data'    => $reserved_hits,
				];
			} else {
				$checks[] = [
					'id'      => 'reserved_slugs',
					'status'  => 'ok',
					'message' => __( 'No reserved WordPress slugs found in registries.', 'jet-sync' ),
				];
			}
		}

		$jetengine_active = defined( 'JET_ENGINE_VERSION' );
		$collisions       = [
			'post_types' => [],
			'taxonomies' => [],
		];

		foreach ( $cpt_defs as $def ) {
			if ( ! $def->is_active() ) {
				continue;
			}
			$slug = $def->get_slug();
			if ( post_type_exists( $slug ) ) {
				if ( ! $jetengine_active ) {
					$collisions['post_types'][] = $slug;
				} elseif ( ! $for_deactivation ) {
					$collisions['post_types'][] = $slug;
				}
			}
		}

		foreach ( $tax_defs as $def ) {
			if ( ! $def->is_active() ) {
				continue;
			}
			$slug = $def->get_slug();
			if ( taxonomy_exists( $slug ) ) {
				if ( ! $jetengine_active ) {
					$collisions['taxonomies'][] = $slug;
				} elseif ( ! $for_deactivation ) {
					$collisions['taxonomies'][] = $slug;
				}
			}
		}

		if ( ! empty( $collisions['post_types'] ) || ! empty( $collisions['taxonomies'] ) ) {
			if ( $jetengine_active && $for_deactivation ) {
				$warnings++;
				$checks[] = [
					'id'      => 'collisions',
					'status'  => 'warning',
					'message' => __( 'Some slugs are currently registered (likely by JetEngine). This is expected before deactivation, but review for other conflicts.', 'jet-sync' ),
					'data'    => $collisions,
				];
			} else {
				$errors++;
				$checks[] = [
					'id'      => 'collisions',
					'status'  => 'error',
					'message' => __( 'Slug collisions detected with already-registered post types or taxonomies.', 'jet-sync' ),
					'data'    => $collisions,
				];
			}
		} else {
			$checks[] = [
				'id'      => 'collisions',
				'status'  => 'ok',
				'message' => __( 'No slug collisions detected for active definitions.', 'jet-sync' ),
			];
		}

		if ( $tables_ok ) {
			$known_rel_ids = array_keys( $rel_defs );

			if ( empty( $known_rel_ids ) ) {
				$orphans = $wpdb->get_col( "SELECT DISTINCT rel_id FROM {$table_relations} LIMIT 50" );
				if ( ! empty( $orphans ) ) {
					$warnings++;
					$checks[] = [
						'id'      => 'orphan_relations',
						'status'  => 'warning',
						'message' => __( 'Relations table contains connections, but no relation schemas are registered in JetSync.', 'jet-sync' ),
						'data'    => [ 'rel_ids' => array_values( $orphans ) ],
					];
				} else {
					$checks[] = [
						'id'      => 'orphan_relations',
						'status'  => 'ok',
						'message' => __( 'No orphaned relation connections detected.', 'jet-sync' ),
					];
				}
			} else {
				$placeholders = implode( ',', array_fill( 0, count( $known_rel_ids ), '%s' ) );
				$sql          = "SELECT DISTINCT rel_id FROM {$table_relations} WHERE rel_id NOT IN ({$placeholders}) LIMIT 50";
				$orphans      = $wpdb->get_col( $wpdb->prepare( $sql, ...$known_rel_ids ) );

				if ( ! empty( $orphans ) ) {
					$warnings++;
					$checks[] = [
						'id'      => 'orphan_relations',
						'status'  => 'warning',
						'message' => __( 'Orphaned relation connections detected (connections referencing unknown relation IDs).', 'jet-sync' ),
						'data'    => [ 'rel_ids' => array_values( $orphans ) ],
					];
				} else {
					$checks[] = [
						'id'      => 'orphan_relations',
						'status'  => 'ok',
						'message' => __( 'No orphaned relation connections detected.', 'jet-sync' ),
					];
				}
			}
		}

		$report = [
			'timestamp'       => time(),
			'for_deactivation'=> $for_deactivation,
			'jetengine_active'=> $jetengine_active,
			'summary'         => [
				'errors'   => $errors,
				'warnings' => $warnings,
			],
			'checks'          => $checks,
		];

		update_option( self::OPTION_LAST_REPORT, $report );

		return $report;
	}
}

