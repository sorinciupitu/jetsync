<?php
declare( strict_types=1 );

namespace JetSync\Compatibility;

use JetSync\Registry\PostTypeRegistry;
use JetSync\Registry\TaxonomyRegistry;
use JetSync\Registry\MetaBoxRegistry;
use JetSync\Registry\RelationRegistry;
use JetSync\Compatibility\Adapter\PostTypeAdapter;
use JetSync\Compatibility\Adapter\TaxonomyAdapter;
use JetSync\Compatibility\Adapter\MetaBoxAdapter;
use JetSync\Compatibility\Adapter\RelationAdapter;
use JetSync\Core\Logger;
use JetSync\Registry\Model\MetaBoxDefinition;
use JetSync\Registry\Model\MetaFieldDefinition;

/**
 * Manages scanning JetEngine options, analyzing schema differences, checking collisions, and running imports.
 */
class ImportManager {

    /**
     * Detector instance.
     *
     * @var Detector
     */
    private Detector $detector;

    /**
     * CPT translator adapter.
     *
     * @var PostTypeAdapter
     */
    private PostTypeAdapter $cpt_adapter;

    /**
     * Taxonomy translator adapter.
     *
     * @var TaxonomyAdapter
     */
    private TaxonomyAdapter $taxonomy_adapter;

    /**
     * Meta Box translator adapter.
     *
     * @var MetaBoxAdapter
     */
    private MetaBoxAdapter $metabox_adapter;

    /**
     * Relations schema translator adapter.
     *
     * @var RelationAdapter
     */
    private RelationAdapter $relation_adapter;

    /**
     * Constructor.
     */
    public function __construct(
        private PostTypeRegistry $cpt_registry,
        private TaxonomyRegistry $taxonomy_registry,
        private MetaBoxRegistry $metabox_registry,
        private RelationRegistry $relation_registry,
        private ?Logger $logger = null
    ) {
        $this->detector         = new Detector();
        $this->cpt_adapter      = new PostTypeAdapter();
        $this->taxonomy_adapter = new TaxonomyAdapter();
        $this->metabox_adapter  = new MetaBoxAdapter();
        $this->relation_adapter = new RelationAdapter();
    }

    /**
     * Parses JetEngine configurations to detect CPTs, taxonomies, meta boxes, relations, and collisions.
     *
     * @return array<string,mixed> Report mapping findings.
     */
    public function analyze_scan() : array {
        global $wpdb;
        
        $report = [
            'jet_engine_active' => $this->detector->is_jet_engine_active(),
            'cpts'              => [],
            'taxonomies'        => [],
            'meta_boxes'        => [],
            'relations'         => [],
            'conflicts_count'   => 0,
            'storage'           => [
                'cpts_source'       => null,
                'taxonomies_source' => null,
                'options'           => [],
                'post_types'        => [],
                'runtime'           => [
                    'cpts_count'       => 0,
                    'taxonomies_count' => 0,
                ],
            ],
        ];

        // 1. Analyze Post Types
        [ $cpts_source, $raw_cpts ] = $this->get_jet_engine_collection(
            preferred_option: 'jet_engine_post_types',
            like_patterns: [ 'jet_engine%post%type%', 'jetengine%post%type%' ]
        );
        $report['storage']['cpts_source'] = $cpts_source;
        if ( is_array( $raw_cpts ) ) {
            foreach ( $raw_cpts as $raw_cpt ) {
                $slug = $raw_cpt['slug'] ?? '';
                if ( empty( $slug ) ) {
                    continue;
                }

                $name = $raw_cpt['name'] ?? $slug;
                $has_conflict = false;
                $conflict_message = '';

                // Check for third-party collisions
                if ( \post_type_exists( $slug ) ) {
                    $is_conflict = false;
                    
                    if ( ! $this->detector->is_jet_engine_active() ) {
                        $is_conflict = true;
                        $conflict_message = sprintf( 'Slug "%s" is already registered by another plugin or theme.', $slug );
                    } else {
                        $built_in_types = [ 'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' ];
                        if ( in_array( $slug, $built_in_types, true ) ) {
                            $is_conflict = true;
                            $conflict_message = sprintf( 'Slug "%s" is a WordPress built-in post type and cannot be used.', $slug );
                        }
                    }

                    if ( $is_conflict ) {
                        $has_conflict = true;
                        $report['conflicts_count']++;
                    }
                }

                $report['cpts'][] = [
                    'slug'             => $slug,
                    'name'             => $name,
                    'has_conflict'     => $has_conflict,
                    'conflict_message' => $conflict_message,
                    'status'           => $has_conflict ? 'conflict' : 'ready',
                ];
            }
        }

        // 2. Analyze Taxonomies
        [ $tax_source, $raw_taxs ] = $this->get_jet_engine_collection(
            preferred_option: 'jet_engine_taxonomies',
            like_patterns: [ 'jet_engine%tax%', 'jetengine%tax%' ]
        );
        $report['storage']['taxonomies_source'] = $tax_source;
        if ( is_array( $raw_taxs ) ) {
            foreach ( $raw_taxs as $raw_tax ) {
                $slug = $raw_tax['slug'] ?? '';
                if ( empty( $slug ) ) {
                    continue;
                }

                $name = $raw_tax['name'] ?? $slug;
                $has_conflict = false;
                $conflict_message = '';

                // Check for third-party collisions
                if ( \taxonomy_exists( $slug ) ) {
                    $is_conflict = false;
                    
                    if ( ! $this->detector->is_jet_engine_active() ) {
                        $is_conflict = true;
                        $conflict_message = sprintf( 'Slug "%s" is already registered by another plugin or theme.', $slug );
                    } else {
                        $built_in_taxonomies = [ 'category', 'post_tag', 'nav_menu', 'link_category', 'post_format' ];
                        if ( in_array( $slug, $built_in_taxonomies, true ) ) {
                            $is_conflict = true;
                            $conflict_message = sprintf( 'Slug "%s" is a WordPress built-in taxonomy and cannot be used.', $slug );
                        }
                    }

                    if ( $is_conflict ) {
                        $has_conflict = true;
                        $report['conflicts_count']++;
                    }
                }

                $report['taxonomies'][] = [
                    'slug'             => $slug,
                    'name'             => $name,
                    'has_conflict'     => $has_conflict,
                    'conflict_message' => $conflict_message,
                    'status'           => $has_conflict ? 'conflict' : 'ready',
                ];
            }
        }

        // 3. Analyze Meta Boxes
        [ $mb_source, $raw_mbs ] = $this->get_jet_engine_collection(
            preferred_option: 'jet_engine_meta_boxes',
            like_patterns: [
                'jet_engine%meta%box%',
                'jetengine%meta%box%',
                'jet_engine%metabox%',
                'jetengine%metabox%',
                'jet_engine%meta%field%',
                'jetengine%meta%field%',
                'jet_engine%custom%field%',
                'jetengine%custom%field%',
            ]
        );
        $report['storage']['meta_boxes_source'] = $mb_source;
        if ( is_array( $raw_mbs ) ) {
            foreach ( $raw_mbs as $raw_mb ) {
                $args  = $raw_mb['args'] ?? [];
                $id    = (string) ( $raw_mb['id'] ?? '' );
                $title = $args['name'] ?? $args['title'] ?? $raw_mb['title'] ?? $raw_mb['name'] ?? '';
                if ( empty( $id ) || empty( $title ) ) {
                    continue;
                }

                $raw_fields = $args['meta_fields'] ?? $raw_mb['meta_fields'] ?? $args['fields'] ?? $raw_mb['fields'] ?? null;
                $fields_count = is_array( $raw_fields ) ? count( $raw_fields ) : 0;

                $report['meta_boxes'][] = [
                    'id'           => $id,
                    'title'        => $title,
                    'fields_count' => $fields_count,
                    'status'       => 'ready',
                ];
            }
        }
        if ( empty( $report['meta_boxes'] ) ) {
            $cpt_slugs = [];
            foreach ( $report['cpts'] as $cpt ) {
                if ( ! is_array( $cpt ) ) {
                    continue;
                }
                $slug = isset( $cpt['slug'] ) ? \sanitize_key( (string) $cpt['slug'] ) : '';
                if ( '' !== $slug ) {
                    $cpt_slugs[] = $slug;
                }
            }
            if ( empty( $cpt_slugs ) ) {
                $runtime = $this->get_runtime_structures();
                if ( is_array( $runtime['cpts'] ?? null ) ) {
                    foreach ( $runtime['cpts'] as $item ) {
                        if ( is_array( $item ) && isset( $item['slug'] ) ) {
                            $slug = \sanitize_key( (string) $item['slug'] );
                            if ( '' !== $slug ) {
                                $cpt_slugs[] = $slug;
                            }
                        }
                    }
                }
            }
            $cpt_slugs = array_slice( array_values( array_unique( $cpt_slugs ) ), 0, 12 );

            if ( ! empty( $cpt_slugs ) ) {
                // New: use SnapshotService (DISTINCT DB query) - 100% capture vs sampling 30 posts
                $auto_mbs = \JetSync\Compatibility\SnapshotService::snapshot_meta_boxes_from_db( $cpt_slugs );
                // Fallback to old sampler if DISTINCT returns empty (e.g. no postmeta yet)
                if ( empty( $auto_mbs ) ) {
                    $auto_mbs = $this->generate_metaboxes_from_postmeta( $cpt_slugs );
                }
                foreach ( $auto_mbs as $mb ) {
                    if ( ! $mb instanceof MetaBoxDefinition ) {
                        continue;
                    }
                    $report['meta_boxes'][] = [
                        'id'           => $mb->get_id(),
                        'title'        => $mb->get_title(),
                        'fields_count' => count( $mb->get_fields() ),
                        'status'       => 'auto',
                    ];
                }
                if ( ! empty( $report['meta_boxes'] ) && empty( $report['storage']['meta_boxes_source'] ) ) {
                    $report['storage']['meta_boxes_source'] = 'auto_postmeta_snapshot';
                }
            }
        }

        // 4. Analyze Relationships - with snapshot fallback for JetEngine 3.x DB layout
        [ $rel_source, $raw_rels ] = $this->get_jet_engine_collection(
            preferred_option: 'jet_engine_relations',
            like_patterns: [ 'jet_engine%relat%', 'jetengine%relat%' ]
        );
        $report['storage']['relations_source'] = $rel_source;
        if ( is_array( $raw_rels ) ) {
            $je_table = $wpdb->prefix . 'jet_rel_connections';
            $has_table = ( $wpdb->get_var( "SHOW TABLES LIKE '$je_table'" ) === $je_table );
            $connection_tables = [];

            if ( ! $has_table ) {
                $tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'jet_rel_%' ) );
                $tables = is_array( $tables ) ? array_values( array_unique( array_filter( array_map( 'strval', $tables ) ) ) ) : [];
                $tables = array_values(
                    array_filter(
                        $tables,
                        static fn( string $t ) => ! str_starts_with( $t, $wpdb->prefix . 'jetsync_' )
                    )
                );
                $tables = array_values(
                    array_filter(
                        $tables,
                        static fn( string $t ) => $t !== $wpdb->prefix . 'jet_rel_connections' && $t !== $wpdb->prefix . 'jet_relations'
                    )
                );

                foreach ( $tables as $t ) {
                    $cols_rows = $wpdb->get_results( "SHOW COLUMNS FROM {$t}", \ARRAY_A );
                    if ( ! is_array( $cols_rows ) || empty( $cols_rows ) ) {
                        continue;
                    }
                    $cols = [];
                    foreach ( $cols_rows as $r ) {
                        if ( isset( $r['Field'] ) ) {
                            $cols[] = (string) $r['Field'];
                        }
                    }
                    if ( in_array( 'rel_id', $cols, true ) && in_array( 'parent_object_id', $cols, true ) && in_array( 'child_object_id', $cols, true ) ) {
                        $connection_tables[] = $t;
                    }
                }
            }

            foreach ( $raw_rels as $raw_rel ) {
                $args  = $raw_rel['args'] ?? [];
                $id    = (string) ( $raw_rel['id'] ?? '' );
                $title = $args['name'] ?? $args['title'] ?? $raw_rel['title'] ?? $raw_rel['name'] ?? '';
                if ( empty( $id ) || empty( $title ) ) {
                    continue;
                }

                // Query raw connection count in JetEngine if table exists
                $conn_count = 0;
                if ( $has_table ) {
                    $conn_count = (int) $wpdb->get_var(
                        $wpdb->prepare( "SELECT COUNT(*) FROM $je_table WHERE rel_id = %s", $id )
                    );
                } elseif ( ! empty( $connection_tables ) ) {
                    foreach ( $connection_tables as $ct ) {
                        $conn_count += (int) $wpdb->get_var(
                            $wpdb->prepare( "SELECT COUNT(*) FROM {$ct} WHERE rel_id = %s", $id )
                        );
                    }
                }

                $report['relations'][] = [
                    'id'               => $id,
                    'title'            => $title,
                    'connection_count' => $conn_count,
                    'status'           => 'ready',
                ];
            }
        }
        // Snapshot fallback: when JetEngine 3.x stores relations only in DB tables / no option, use live snapshot
        if ( empty( $report['relations'] ) ) {
            $snap = \JetSync\Compatibility\SnapshotService::snapshot_relations();
            if ( !empty($snap) ) {
                // Determine connection counts for snapshot relations
                $je_table = $wpdb->prefix . 'jet_rel_connections';
                $has_table = ( $wpdb->get_var( "SHOW TABLES LIKE '$je_table'" ) === $je_table );
                foreach ( $snap as $rel ) {
                    $id = (string)($rel['id'] ?? '');
                    $title = (string)($rel['title'] ?? $id);
                    $conn_count = 0;
                    if ( $has_table ) {
                        $conn_count = (int)$wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $je_table WHERE rel_id = %s",$id) );
                    } else {
                        // scan all jet_rel tables
                        $tables = $wpdb->get_col( $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix.'jet_rel_%'));
                        if ( is_array($tables) ) {
                            foreach ( $tables as $ct ) {
                                if ( str_starts_with($ct,$wpdb->prefix.'jetsync_')) continue;
                                $exists = $wpdb->get_var("SHOW TABLES LIKE '$ct'");
                                if ( $exists !== $ct) continue;
                                $cols = $wpdb->get_results("SHOW COLUMNS FROM {$ct}", ARRAY_A);
                                $colNames = is_array($cols) ? array_column($cols,'Field') : [];
                                if ( !in_array('rel_id',$colNames,true)) continue;
                                $conn_count += (int)$wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$ct} WHERE rel_id = %s", $id));
                            }
                        }
                    }
                    $report['relations'][] = [
                        'id' => $id,
                        'title' => $title,
                        'connection_count' => $conn_count,
                        'status' => 'snapshot',
                    ];
                }
                if ( !empty($report['relations']) && empty($report['storage']['relations_source']) ) {
                    $report['storage']['relations_source'] = 'snapshot_db';
                }
            }
        }

        $report['storage']['options'] = $this->get_jetengine_options_debug();
        $report['storage']['post_types'] = $this->get_jetengine_post_types_debug();
        $report['storage']['jet_engine_posts'] = $this->get_jetengine_storage_posts_debug();
        $report['storage']['relations_tables'] = $this->get_jetengine_relations_tables_debug();

        if ( empty( $report['cpts'] ) && empty( $report['taxonomies'] ) ) {
            $runtime = $this->get_runtime_structures();
            $report['storage']['runtime']['cpts_count'] = count( $runtime['cpts'] );
            $report['storage']['runtime']['taxonomies_count'] = count( $runtime['taxonomies'] );

            foreach ( $runtime['cpts'] as $item ) {
                $report['cpts'][] = [
                    'slug'             => $item['slug'],
                    'name'             => $item['name'],
                    'has_conflict'     => false,
                    'conflict_message' => '',
                    'status'           => 'runtime',
                ];
            }

            foreach ( $runtime['taxonomies'] as $item ) {
                $report['taxonomies'][] = [
                    'slug'             => $item['slug'],
                    'name'             => $item['name'],
                    'has_conflict'     => false,
                    'conflict_message' => '',
                    'status'           => 'runtime',
                ];
            }

            if ( ! empty( $report['cpts'] ) ) {
                $report['storage']['cpts_source'] = $report['storage']['cpts_source'] ?: 'runtime';
            }
            if ( ! empty( $report['taxonomies'] ) ) {
                $report['storage']['taxonomies_source'] = $report['storage']['taxonomies_source'] ?: 'runtime';
            }
        }

        return $report;
    }

    /**
     * Syncs schemas and relationship entries from JetEngine to JetSync.
     *
     * @return array<string,mixed> Import performance summary.
     */
    public function execute_import( ?array $only_cpts = null, ?array $only_taxonomies = null ) : array {
        global $wpdb;

        $results = [
            'imported_cpts'        => 0,
            'imported_taxonomies'  => 0,
            'imported_metaboxes'   => 0,
            'imported_relations'   => 0,
            'imported_connections' => 0,
            'errors'               => [],
        ];
        $imported_cpt_slugs = [];

        // 1. Import Post Types
        [ , $raw_cpts ] = $this->get_jet_engine_collection(
            preferred_option: 'jet_engine_post_types',
            like_patterns: [ 'jet_engine%post%type%', 'jetengine%post%type%' ]
        );
        if ( empty( $raw_cpts ) ) {
            $runtime = $this->get_runtime_structures();
            foreach ( $runtime['cpts'] as $item ) {
                if ( is_array( $only_cpts ) && ! in_array( (string) $item['slug'], $only_cpts, true ) ) {
                    continue;
                }
                $definition = $this->translate_runtime_post_type( $item['object'] );
                if ( $definition ) {
                    $this->cpt_registry->add( $definition );
                    $results['imported_cpts']++;
                    $imported_cpt_slugs[] = $definition->get_slug();
                }
            }
        }
        if ( is_array( $raw_cpts ) ) {
            foreach ( $raw_cpts as $raw_cpt ) {
                $slug = (string) ( $raw_cpt['slug'] ?? '' );
                if ( is_array( $only_cpts ) && '' !== $slug && ! in_array( $slug, $only_cpts, true ) ) {
                    continue;
                }
                $definition = $this->cpt_adapter->translate( $raw_cpt );
                if ( $definition ) {
                    $this->cpt_registry->add( $definition );
                    $results['imported_cpts']++;
                    $imported_cpt_slugs[] = $definition->get_slug();
                } else {
                    $results['errors'][] = sprintf( 'Failed to translate CPT array: %s', print_r( $raw_cpt, true ) );
                }
            }
        }

        // 2. Import Taxonomies
        [ , $raw_taxs ] = $this->get_jet_engine_collection(
            preferred_option: 'jet_engine_taxonomies',
            like_patterns: [ 'jet_engine%tax%', 'jetengine%tax%' ]
        );
        if ( empty( $raw_taxs ) ) {
            $runtime = $this->get_runtime_structures();
            foreach ( $runtime['taxonomies'] as $item ) {
                if ( is_array( $only_taxonomies ) && ! in_array( (string) $item['slug'], $only_taxonomies, true ) ) {
                    continue;
                }
                $definition = $this->translate_runtime_taxonomy( $item['object'] );
                if ( $definition ) {
                    $this->taxonomy_registry->add( $definition );
                    $results['imported_taxonomies']++;
                }
            }
        }
        if ( is_array( $raw_taxs ) ) {
            foreach ( $raw_taxs as $raw_tax ) {
                $slug = (string) ( $raw_tax['slug'] ?? '' );
                if ( is_array( $only_taxonomies ) && '' !== $slug && ! in_array( $slug, $only_taxonomies, true ) ) {
                    continue;
                }
                $definition = $this->taxonomy_adapter->translate( $raw_tax );
                if ( $definition ) {
                    $this->taxonomy_registry->add( $definition );
                    $results['imported_taxonomies']++;
                } else {
                    $results['errors'][] = sprintf( 'Failed to translate Taxonomy array: %s', print_r( $raw_tax, true ) );
                }
            }
        }

        // 3. Import Meta Boxes
        [ , $raw_mbs ] = $this->get_jet_engine_collection(
            preferred_option: 'jet_engine_meta_boxes',
            like_patterns: [ 'jet_engine%meta%box%', 'jetengine%meta%box%' ]
        );
        if ( is_array( $raw_mbs ) ) {
            foreach ( $raw_mbs as $raw_mb ) {
                $definition = $this->metabox_adapter->translate( $raw_mb );
                if ( $definition ) {
                    $this->metabox_registry->add( $definition );
                    $results['imported_metaboxes']++;
                } else {
                    $results['errors'][] = sprintf( 'Failed to translate MetaBox array: %s', print_r( $raw_mb, true ) );
                }
            }
        }
        if ( 0 === (int) $results['imported_metaboxes'] ) {
            $cpts_for_auto = array_values( array_unique( $imported_cpt_slugs ) );
            if ( empty( $cpts_for_auto ) ) {
                if ( is_array( $only_cpts ) && ! empty( $only_cpts ) ) {
                    $cpts_for_auto = $only_cpts;
                } else {
                    $runtime = $this->get_runtime_structures();
                    $cpts_for_auto = array_values(
                        array_unique(
                            array_filter(
                                array_map(
                                    static fn( $it ) => isset( $it['slug'] ) ? \sanitize_key( (string) $it['slug'] ) : '',
                                    is_array( $runtime['cpts'] ?? null ) ? $runtime['cpts'] : []
                                )
                            )
                        )
                    );
                }
            }
            $cpts_for_auto = array_slice( $cpts_for_auto, 0, 12 );

            $auto_mbs = \JetSync\Compatibility\SnapshotService::snapshot_meta_boxes_from_db( $cpts_for_auto );
            if ( empty($auto_mbs) ) {
                $auto_mbs = $this->generate_metaboxes_from_postmeta( $cpts_for_auto );
            }
            foreach ( $auto_mbs as $mb ) {
                $this->metabox_registry->add( $mb );
                $results['imported_metaboxes']++;
            }
        }

        // 4. Import Relationships Configurations - with snapshot fallback
        [ , $raw_rels ] = $this->get_jet_engine_collection(
            preferred_option: 'jet_engine_relations',
            like_patterns: [ 'jet_engine%relat%', 'jetengine%relat%' ]
        );
        if ( is_array( $raw_rels ) ) {
            foreach ( $raw_rels as $raw_rel ) {
                $definition = $this->relation_adapter->translate( $raw_rel );
                if ( $definition ) {
                    $this->relation_registry->add( $definition );
                    $results['imported_relations']++;
                } else {
                    $results['errors'][] = sprintf( 'Failed to translate Relation array: %s', print_r( $raw_rel, true ) );
                }
            }
        }
        // Snapshot fallback for JetEngine 3.x DB-only relations
        if ( 0 === (int)$results['imported_relations'] ) {
            $snapRels = \JetSync\Compatibility\SnapshotService::snapshot_relations();
            foreach ( $snapRels as $relArr ) {
                $definition = $this->relation_adapter->translate( ['id'=>$relArr['id'],'args'=>$relArr] );
                if ( !$definition ) {
                    // build directly if adapter fails on snapshot format
                    $definition = new \JetSync\Registry\Model\RelationDefinition(
                        id: $relArr['id'],
                        title: $relArr['title'] ?? $relArr['id'],
                        parent_object: $relArr['parent_object'] ?? 'post',
                        child_object: $relArr['child_object'] ?? 'post',
                        type: $relArr['type'] ?? 'many_to_many',
                        active: true
                    );
                }
                $this->relation_registry->add( $definition );
                $results['imported_relations']++;
            }
        }

        // 5. Migrate Relationship Connections
        $je_table = $wpdb->prefix . 'jet_rel_connections';
        $js_table = $wpdb->prefix . 'jetsync_relations';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$je_table'" ) === $je_table ) {
            $rows = $wpdb->get_results( "SELECT * FROM $je_table", \ARRAY_A );
            if ( is_array( $rows ) ) {
                foreach ( $rows as $row ) {
                    $rel_id    = (string) ( $row['rel_id'] ?? '' );
                    $parent_id = (int) ( $row['parent_object_id'] ?? $row['parent_id'] ?? 0 );
                    $child_id  = (int) ( $row['child_object_id'] ?? $row['child_id'] ?? 0 );

                    if ( ! empty( $rel_id ) && $parent_id > 0 && $child_id > 0 ) {
                        $parent_type = \get_post_type( $parent_id );
                        $child_type  = \get_post_type( $child_id );
                        $parent_type = is_string( $parent_type ) && '' !== $parent_type ? $parent_type : 'post';
                        $child_type  = is_string( $child_type ) && '' !== $child_type ? $child_type : 'post';

                        $wpdb->query(
                            $wpdb->prepare(
                                "INSERT IGNORE INTO $js_table (rel_id, parent_id, child_id, parent_type, child_type) 
                                 VALUES (%s, %d, %d, %s, %s)",
                                $rel_id,
                                $parent_id,
                                $child_id,
                                $parent_type,
                                $child_type
                            )
                        );
                        $results['imported_connections']++;
                    }
                }
            }
        }

        $results['imported_connections'] += $this->migrate_relation_connections_from_tables( $js_table );

        if ( $this->logger ) {
            $this->logger->info(
                sprintf(
                    'Migration sync completed: %d CPTs, %d Taxonomies, %d Meta Boxes, %d Relations schemas, and %d link connections migrated.',
                    $results['imported_cpts'],
                    $results['imported_taxonomies'],
                    $results['imported_metaboxes'],
                    $results['imported_relations'],
                    $results['imported_connections']
                )
            );
        }

        \do_action( 'jetsync_import_completed', $results, $only_cpts, $only_taxonomies );

        return $results;
    }

    private function migrate_relation_connections_from_tables( string $js_table ) : int {
        global $wpdb;

        $patterns = [
            $wpdb->prefix . 'jet_rel_%',
            $wpdb->prefix . 'jet_rel%',
        ];

        $tables = [];
        foreach ( $patterns as $pattern ) {
            $rows = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );
            if ( is_array( $rows ) ) {
                $tables = array_merge( $tables, $rows );
            }
        }

        $tables = array_values( array_unique( array_filter( array_map( 'strval', $tables ) ) ) );
        $tables = array_values(
            array_filter(
                $tables,
                static fn( string $t ) => ! str_starts_with( $t, $wpdb->prefix . 'jetsync_' )
            )
        );
        $tables = array_values(
            array_filter(
                $tables,
                static fn( string $t ) => $t !== $wpdb->prefix . 'jet_rel_connections' && $t !== $wpdb->prefix . 'jet_relations'
            )
        );

        if ( empty( $tables ) ) {
            return 0;
        }

        $count = 0;
        $type_cache = [];

        foreach ( $tables as $table ) {
            $cols_rows = $wpdb->get_results( "SHOW COLUMNS FROM {$table}", \ARRAY_A );
            if ( ! is_array( $cols_rows ) || empty( $cols_rows ) ) {
                continue;
            }

            $cols = [];
            foreach ( $cols_rows as $r ) {
                if ( isset( $r['Field'] ) ) {
                    $cols[] = (string) $r['Field'];
                }
            }

            if ( ! in_array( 'rel_id', $cols, true ) || ! in_array( 'parent_object_id', $cols, true ) || ! in_array( 'child_object_id', $cols, true ) ) {
                continue;
            }

            $rows = $wpdb->get_results( "SELECT rel_id, parent_object_id, child_object_id FROM {$table} LIMIT 15000", \ARRAY_A );
            if ( ! is_array( $rows ) || empty( $rows ) ) {
                continue;
            }

            foreach ( $rows as $row ) {
                $rel_id    = sanitize_key( (string) ( $row['rel_id'] ?? '' ) );
                $parent_id = (int) ( $row['parent_object_id'] ?? 0 );
                $child_id  = (int) ( $row['child_object_id'] ?? 0 );

                if ( '' === $rel_id || $parent_id <= 0 || $child_id <= 0 ) {
                    continue;
                }

                if ( isset( $type_cache[ $parent_id ] ) ) {
                    $parent_type = $type_cache[ $parent_id ];
                } else {
                    $pt = \get_post_type( $parent_id );
                    $parent_type = is_string( $pt ) && '' !== $pt ? $pt : 'post';
                    $type_cache[ $parent_id ] = $parent_type;
                }

                if ( isset( $type_cache[ $child_id ] ) ) {
                    $child_type = $type_cache[ $child_id ];
                } else {
                    $pt = \get_post_type( $child_id );
                    $child_type = is_string( $pt ) && '' !== $pt ? $pt : 'post';
                    $type_cache[ $child_id ] = $child_type;
                }

                $ok = $wpdb->query(
                    $wpdb->prepare(
                        "INSERT IGNORE INTO {$js_table} (rel_id, parent_id, child_id, parent_type, child_type)
                         VALUES (%s, %d, %d, %s, %s)",
                        $rel_id,
                        $parent_id,
                        $child_id,
                        $parent_type,
                        $child_type
                    )
                );

                if ( false !== $ok && $ok > 0 ) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @param array<string> $post_types
     * @return array<MetaBoxDefinition>
     */
    private function generate_metaboxes_from_postmeta( array $post_types ) : array {
        $blacklist = [
            '_edit_lock',
            '_edit_last',
            '_wp_old_slug',
            '_thumbnail_id',
            '_wp_page_template',
        ];
        $blocked_prefixes = [
            '_wp_',
            '_edit_',
            '_elementor',
            '_oembed',
            '_yoast',
            '_rank_math',
            '_listing_',
        ];

        $out = [];

        foreach ( $post_types as $post_type ) {
            $post_type = sanitize_key( (string) $post_type );
            if ( '' === $post_type ) {
                continue;
            }

            $q = new \WP_Query(
                [
                    'post_type'      => $post_type,
                    'posts_per_page' => 30,
                    'post_status'    => 'any',
                    'fields'         => 'ids',
                    'no_found_rows'  => true,
                ]
            );

            $keys = [];
            if ( is_array( $q->posts ) ) {
                foreach ( $q->posts as $post_id ) {
                    $custom_keys = get_post_custom_keys( (int) $post_id );
                    if ( ! is_array( $custom_keys ) ) {
                        continue;
                    }
                    foreach ( $custom_keys as $k ) {
                        $k = (string) $k;
                        if ( '' === $k ) {
                            continue;
                        }
                        if ( in_array( $k, $blacklist, true ) ) {
                            continue;
                        }
                        foreach ( $blocked_prefixes as $p ) {
                            if ( str_starts_with( $k, $p ) ) {
                                continue 2;
                            }
                        }
                        $keys[ $k ] = true;
                    }
                }
            }

            $field_objects = [];
            $max_fields = 40;
            $sample_post_ids = is_array( $q->posts ) ? array_slice( array_map( 'intval', $q->posts ), 0, 6 ) : [];
            foreach ( array_keys( $keys ) as $k ) {
                if ( count( $field_objects ) >= $max_fields ) {
                    break;
                }
                $type = $this->infer_meta_field_type( $sample_post_ids, (string) $k );
                $field_objects[] = new MetaFieldDefinition(
                    name: sanitize_key( $k ),
                    title: ucwords( str_replace( [ '_', '-' ], ' ', (string) $k ) ),
                    type: $type
                );
            }

            if ( empty( $field_objects ) ) {
                continue;
            }

            $out[] = new MetaBoxDefinition(
                id: 'jetsync_auto_' . $post_type,
                title: 'Settings',
                object_types: [ $post_type ],
                context: 'normal',
                priority: 'default',
                fields: $field_objects,
                active: true
            );
        }

        return $out;
    }

    /**
     * @param array<int> $sample_post_ids
     */
    private function infer_meta_field_type( array $sample_post_ids, string $meta_key ) : string {
        $meta_key = (string) $meta_key;
        $k = strtolower( $meta_key );

        if ( $this->should_force_text_type( $k ) ) {
            return 'text';
        }
        if ( str_contains( $k, 'gallery' ) ) {
            return 'gallery';
        }
        if ( str_contains( $k, 'image' ) || str_contains( $k, 'cover' ) || str_contains( $k, 'thumbnail' ) ) {
            return 'media';
        }
        if ( str_contains( $k, 'url' ) || str_contains( $k, 'link' ) || str_contains( $k, 'website' ) ) {
            return 'url';
        }
        if ( str_contains( $k, 'description' ) || str_contains( $k, 'content' ) || str_contains( $k, 'text' ) ) {
            return 'wysiwyg';
        }

        foreach ( $sample_post_ids as $post_id ) {
            $raw = \get_post_meta( (int) $post_id, $meta_key, true );
            if ( '' === $raw || null === $raw ) {
                continue;
            }
            if ( is_array( $raw ) ) {
                $ids = array_values( array_filter( array_map( '\absint', $raw ) ) );
                if ( ! empty( $ids ) ) {
                    return 'gallery';
                }
                continue;
            }

            $val = trim( (string) $raw );
            if ( '' === $val ) {
                continue;
            }

            if ( preg_match( '/^\\d+(,\\d+)*$/', $val ) ) {
                $ids = array_values( array_filter( array_map( '\absint', explode( ',', $val ) ) ) );
                if ( count( $ids ) > 1 ) {
                    return 'gallery';
                }
                if ( 1 === count( $ids ) && $this->is_probably_media_key( $k ) ) {
                    $maybe_attachment = \get_post_type( $ids[0] );
                    if ( 'attachment' === $maybe_attachment ) {
                        return 'media';
                    }
                }
            }

            if ( strlen( $val ) > 180 || str_contains( $val, "\n" ) ) {
                if ( str_contains( $val, '<' ) ) {
                    return 'wysiwyg';
                }
                return 'textarea';
            }

            if ( \filter_var( $val, FILTER_VALIDATE_URL ) ) {
                return 'url';
            }
        }

        return 'text';
    }

    private function should_force_text_type( string $meta_key ) : bool {
        foreach ( $this->get_text_field_keywords() as $keyword ) {
            if ( str_contains( $meta_key, $keyword ) ) {
                return true;
            }
        }

        return false;
    }

    private function is_probably_media_key( string $meta_key ) : bool {
        foreach ( [ 'image', 'cover', 'thumbnail', 'photo', 'avatar', 'picture', 'icon', 'logo', 'banner', 'file' ] as $keyword ) {
            if ( str_contains( $meta_key, $keyword ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string>
     */
    private function get_text_field_keywords() : array {
        return [
            'bust',
            'waist',
            'hips',
            'shoe',
            'shoes',
            'height',
            'hair',
            'eyes',
            'eye',
            'section',
            'size',
            'weight',
            'profile',
            'instagram',
        ];
    }

    private function get_runtime_structures() : array {
        $ignored_post_types = [
            'jet-engine',
            'elementor_library',
        ];

        $ignored_taxonomies = [
            'post_format',
        ];

        $cpts = [];
        $taxs = [];

        $post_types = \get_post_types( [ '_builtin' => false ], 'objects' );
        if ( is_array( $post_types ) ) {
            foreach ( $post_types as $slug => $obj ) {
                if ( in_array( (string) $slug, $ignored_post_types, true ) ) {
                    continue;
                }
                $label = '';
                if ( is_object( $obj ) && isset( $obj->labels->name ) ) {
                    $label = (string) $obj->labels->name;
                }
                $cpts[] = [
                    'slug'   => (string) $slug,
                    'name'   => $label !== '' ? $label : (string) $slug,
                    'object' => $obj,
                ];
            }
        }

        $taxonomies = \get_taxonomies( [ '_builtin' => false ], 'objects' );
        if ( is_array( $taxonomies ) ) {
            foreach ( $taxonomies as $slug => $obj ) {
                if ( in_array( (string) $slug, $ignored_taxonomies, true ) ) {
                    continue;
                }
                $label = '';
                if ( is_object( $obj ) && isset( $obj->labels->name ) ) {
                    $label = (string) $obj->labels->name;
                }
                $taxs[] = [
                    'slug'   => (string) $slug,
                    'name'   => $label !== '' ? $label : (string) $slug,
                    'object' => $obj,
                ];
            }
        }

        return [
            'cpts'       => $cpts,
            'taxonomies' => $taxs,
        ];
    }

    private function translate_runtime_post_type( mixed $obj ) : ?\JetSync\Registry\Model\DefinitionInterface {
        if ( ! is_object( $obj ) || ! isset( $obj->name ) ) {
            return null;
        }

        $slug = (string) $obj->name;
        if ( '' === $slug ) {
            return null;
        }

        $supports = array_keys( \get_all_post_type_supports( $slug ) );

        $labels = [];
        if ( isset( $obj->labels ) ) {
            $labels = json_decode( \wp_json_encode( $obj->labels ), true );
            if ( ! is_array( $labels ) ) {
                $labels = [];
            }
        }

        $rewrite = [];
        if ( isset( $obj->rewrite ) && is_array( $obj->rewrite ) ) {
            $rewrite = $obj->rewrite;
        }

        return new \JetSync\Registry\Model\PostTypeDefinition(
            slug: $slug,
            labels: $labels,
            public: (bool) ( $obj->public ?? true ),
            show_ui: (bool) ( $obj->show_ui ?? true ),
            show_in_menu: (bool) ( $obj->show_in_menu ?? true ),
            menu_position: isset( $obj->menu_position ) ? (int) $obj->menu_position : null,
            menu_icon: (string) ( $obj->menu_icon ?? 'dashicons-admin-post' ),
            supports: $supports,
            has_archive: (bool) ( $obj->has_archive ?? false ),
            rewrite: $rewrite,
            show_in_rest: (bool) ( $obj->show_in_rest ?? false ),
            capabilities: isset( $obj->cap ) ? json_decode( \wp_json_encode( $obj->cap ), true ) : [],
            active: true
        );
    }

    private function translate_runtime_taxonomy( mixed $obj ) : ?\JetSync\Registry\Model\DefinitionInterface {
        if ( ! is_object( $obj ) || ! isset( $obj->name ) ) {
            return null;
        }

        $slug = (string) $obj->name;
        if ( '' === $slug ) {
            return null;
        }

        $labels = [];
        if ( isset( $obj->labels ) ) {
            $labels = json_decode( \wp_json_encode( $obj->labels ), true );
            if ( ! is_array( $labels ) ) {
                $labels = [];
            }
        }

        $rewrite = [];
        if ( isset( $obj->rewrite ) && is_array( $obj->rewrite ) ) {
            $rewrite = $obj->rewrite;
        }

        $object_types = [];
        if ( isset( $obj->object_type ) && is_array( $obj->object_type ) ) {
            $object_types = array_map( 'strval', $obj->object_type );
        }

        return new \JetSync\Registry\Model\TaxonomyDefinition(
            slug: $slug,
            object_types: $object_types,
            labels: $labels,
            hierarchical: (bool) ( $obj->hierarchical ?? false ),
            public: (bool) ( $obj->public ?? true ),
            show_ui: (bool) ( $obj->show_ui ?? true ),
            show_admin_column: (bool) ( $obj->show_admin_column ?? true ),
            show_in_rest: (bool) ( $obj->show_in_rest ?? false ),
            rewrite: $rewrite,
            active: true
        );
    }

    private function normalize_collection( mixed $raw ) : array {
        if ( is_array( $raw ) && isset( $raw['items'] ) && is_array( $raw['items'] ) ) {
            return $raw['items'];
        }

        if ( is_array( $raw ) ) {
            return $raw;
        }

        if ( is_string( $raw ) ) {
            $trim = trim( $raw );
            if ( ( str_starts_with( $trim, '{' ) && str_ends_with( $trim, '}' ) ) || ( str_starts_with( $trim, '[' ) && str_ends_with( $trim, ']' ) ) ) {
                $json = json_decode( $trim, true );
                if ( is_array( $json ) ) {
                    if ( isset( $json['items'] ) && is_array( $json['items'] ) ) {
                        return $json['items'];
                    }
                    return $json;
                }
            }
        }

        return [];
    }

    private function get_jet_engine_collection( string $preferred_option, array $like_patterns ) : array {
        global $wpdb;

        $raw = $this->normalize_collection( \get_option( $preferred_option, [] ) );
        if ( ! empty( $raw ) ) {
            return [ $preferred_option, $raw ];
        }

        $options_table = $wpdb->options;
        $candidates = [];
        foreach ( $like_patterns as $like ) {
            $rows = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT option_name FROM {$options_table} WHERE option_name LIKE %s LIMIT 25",
                    $like
                )
            );
            if ( is_array( $rows ) ) {
                $candidates = array_merge( $candidates, $rows );
            }
        }
        $candidates = array_values( array_unique( array_filter( $candidates ) ) );

        foreach ( $candidates as $option_name ) {
            if ( $option_name === $preferred_option ) {
                continue;
            }
            $candidate_raw = $this->normalize_collection( \get_option( $option_name, [] ) );
            if ( empty( $candidate_raw ) ) {
                continue;
            }

            $first = $candidate_raw[ array_key_first( $candidate_raw ) ] ?? null;
            if ( is_array( $first ) && ( isset( $first['slug'] ) || isset( $first['id'] ) ) ) {
                return [ $option_name, $candidate_raw ];
            }
        }

        $kind = match ( $preferred_option ) {
            'jet_engine_post_types' => 'cpt',
            'jet_engine_taxonomies' => 'taxonomy',
            'jet_engine_meta_boxes' => 'metabox',
            'jet_engine_relations'  => 'relation',
            default => null,
        };

        if ( $kind ) {
            $from_posts = $this->get_jet_engine_collection_from_posts( $kind );
            if ( ! empty( $from_posts ) ) {
                return [ 'post_type:jet-engine', $from_posts ];
            }
        }

        if ( 'metabox' === $kind ) {
            $from_runtime = $this->get_jet_engine_collection_from_runtime( $kind );
            if ( ! empty( $from_runtime ) ) {
                return [ 'runtime:jetengine', $from_runtime ];
            }
        }

        if ( 'relation' === $kind ) {
            [ $source, $from_tables ] = $this->get_jet_engine_relations_from_tables();
            if ( ! empty( $from_tables ) ) {
                return [ $source, $from_tables ];
            }

            [ $source, $from_connection_tables ] = $this->get_jet_engine_relations_from_connection_tables();
            if ( ! empty( $from_connection_tables ) ) {
                return [ $source, $from_connection_tables ];
            }
        }

        return [ null, [] ];
    }

    /**
     * @return array{0: string|null, 1: array<int,array<string,mixed>>}
     */
    private function get_jet_engine_relations_from_tables() : array {
        global $wpdb;

        $patterns = [
            $wpdb->prefix . 'jet_relations%',
            $wpdb->prefix . 'jet_rel_%',
            $wpdb->prefix . 'jet_rel%',
            $wpdb->prefix . 'jet_engine_rel%',
            $wpdb->prefix . 'jet_%rel%',
        ];

        $tables = [];
        foreach ( $patterns as $pattern ) {
            $rows = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );
            if ( is_array( $rows ) ) {
                $tables = array_merge( $tables, $rows );
            }
        }
        $tables = array_values( array_unique( array_filter( array_map( 'strval', $tables ) ) ) );
        $tables = array_values(
            array_filter(
                $tables,
                static fn( string $t ) => ! str_starts_with( $t, $wpdb->prefix . 'jetsync_' )
            )
        );

        if ( empty( $tables ) ) {
            return [ null, [] ];
        }

        foreach ( $tables as $table ) {
            $cols_rows = $wpdb->get_results( "SHOW COLUMNS FROM {$table}", \ARRAY_A );
            if ( ! is_array( $cols_rows ) || empty( $cols_rows ) ) {
                continue;
            }

            $cols = [];
            foreach ( $cols_rows as $r ) {
                if ( isset( $r['Field'] ) ) {
                    $cols[] = (string) $r['Field'];
                }
            }

            $has_name = in_array( 'name', $cols, true ) || in_array( 'title', $cols, true );
            $has_parent = in_array( 'parent_object', $cols, true ) || in_array( 'parent', $cols, true ) || in_array( 'parent_object_type', $cols, true ) || in_array( 'parent_type', $cols, true );
            $has_child  = in_array( 'child_object', $cols, true ) || in_array( 'child', $cols, true ) || in_array( 'child_object_type', $cols, true ) || in_array( 'child_type', $cols, true );
            $has_id = in_array( 'id', $cols, true ) || in_array( 'rel_id', $cols, true ) || in_array( 'relation_id', $cols, true );
            $args_col = null;
            foreach ( [ 'args', 'settings', 'config', 'data', 'payload' ] as $c ) {
                if ( in_array( $c, $cols, true ) ) {
                    $args_col = $c;
                    break;
                }
            }

            if ( ! $has_id || ( ! $args_col && ( ! $has_name || ! $has_parent || ! $has_child ) ) ) {
                continue;
            }

            $rows = $wpdb->get_results( "SELECT * FROM {$table} LIMIT 200", \ARRAY_A );
            if ( ! is_array( $rows ) || empty( $rows ) ) {
                continue;
            }

            $out = [];
            foreach ( $rows as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }

                $id = (string) ( $row['id'] ?? $row['rel_id'] ?? $row['relation_id'] ?? '' );
                $args = [];
                if ( $args_col && isset( $row[ $args_col ] ) ) {
                    $payloads = $this->decode_any_payloads( $row[ $args_col ] );
                    $first = $payloads[0] ?? null;
                    if ( is_array( $first ) ) {
                        $args = isset( $first['args'] ) && is_array( $first['args'] ) ? $first['args'] : $first;
                    }
                }

                $name = (string) ( $row['name'] ?? $row['title'] ?? ( $args['name'] ?? $args['title'] ?? '' ) );
                if ( '' === $id || '' === $name ) {
                    continue;
                }

                $parent_object = (string) ( $row['parent_object'] ?? $row['parent'] ?? ( $args['parent_object'] ?? $args['parent'] ?? '' ) );
                $parent_sub = (string) ( $row['parent_object_type'] ?? $row['parent_type'] ?? ( $args['parent_object_type'] ?? $args['parent_type'] ?? '' ) );
                $child_object = (string) ( $row['child_object'] ?? $row['child'] ?? ( $args['child_object'] ?? $args['child'] ?? '' ) );
                $child_sub = (string) ( $row['child_object_type'] ?? $row['child_type'] ?? ( $args['child_object_type'] ?? $args['child_type'] ?? '' ) );

                $type_raw = (string) ( $row['type'] ?? $row['relation_type'] ?? $row['cardinality'] ?? ( $args['type'] ?? $args['relation_type'] ?? $args['cardinality'] ?? '' ) );

                if ( '' === $parent_object || '' === $child_object ) {
                    continue;
                }

                $out[] = [
                    'id'   => $id,
                    'args' => [
                        'name'          => $name,
                        'parent_object' => $this->normalize_jetengine_object( $parent_object, $parent_sub ),
                        'child_object'  => $this->normalize_jetengine_object( $child_object, $child_sub ),
                        'type'          => $this->normalize_jetengine_relation_type( $type_raw ),
                    ],
                ];
            }

            if ( empty( $out ) ) {
                continue;
            }

            return [ 'db_table:' . $table, $out ];
        }

        return [ null, [] ];
    }

    /**
     * @return array{0: string|null, 1: array<int,array<string,mixed>>}
     */
    private function get_jet_engine_relations_from_connection_tables() : array {
        global $wpdb;

        $patterns = [
            $wpdb->prefix . 'jet_rel_%',
            $wpdb->prefix . 'jet_rel%',
        ];

        $tables = [];
        foreach ( $patterns as $pattern ) {
            $rows = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );
            if ( is_array( $rows ) ) {
                $tables = array_merge( $tables, $rows );
            }
        }

        $tables = array_values( array_unique( array_filter( array_map( 'strval', $tables ) ) ) );
        $tables = array_values(
            array_filter(
                $tables,
                static fn( string $t ) => ! str_starts_with( $t, $wpdb->prefix . 'jetsync_' )
            )
        );

        $tables = array_values(
            array_filter(
                $tables,
                static fn( string $t ) => $t !== $wpdb->prefix . 'jet_rel_connections'
            )
        );

        if ( empty( $tables ) ) {
            return [ null, [] ];
        }

        $out_by_id = [];
        $source_tables = [];

        foreach ( $tables as $table ) {
            $cols_rows = $wpdb->get_results( "SHOW COLUMNS FROM {$table}", \ARRAY_A );
            if ( ! is_array( $cols_rows ) || empty( $cols_rows ) ) {
                continue;
            }

            $cols = [];
            foreach ( $cols_rows as $r ) {
                if ( isset( $r['Field'] ) ) {
                    $cols[] = (string) $r['Field'];
                }
            }

            if ( ! in_array( 'rel_id', $cols, true ) ) {
                continue;
            }
            if ( ! in_array( 'parent_object_id', $cols, true ) || ! in_array( 'child_object_id', $cols, true ) ) {
                continue;
            }

            $rel_id = (string) $wpdb->get_var( "SELECT rel_id FROM {$table} WHERE rel_id <> '' LIMIT 1" );
            $rel_id = sanitize_key( $rel_id );

            if ( '' === $rel_id ) {
                $suffix = str_replace( $wpdb->prefix, '', $table );
                $suffix = preg_replace( '/^jet_rel_?/', '', (string) $suffix );
                $rel_id = sanitize_key( (string) $suffix );
            }

            if ( '' === $rel_id ) {
                continue;
            }

            $parent_counts = [];
            $child_counts  = [];

            $pairs = $wpdb->get_results(
                "SELECT parent_object_id, child_object_id FROM {$table} WHERE parent_object_id > 0 AND child_object_id > 0 LIMIT 80",
                \ARRAY_A
            );
            if ( is_array( $pairs ) ) {
                foreach ( $pairs as $p ) {
                    $parent_id = (int) ( $p['parent_object_id'] ?? 0 );
                    $child_id  = (int) ( $p['child_object_id'] ?? 0 );
                    if ( $parent_id > 0 ) {
                        $pt = \get_post_type( $parent_id );
                        if ( is_string( $pt ) && '' !== $pt ) {
                            $parent_counts[ $pt ] = ( $parent_counts[ $pt ] ?? 0 ) + 1;
                        }
                    }
                    if ( $child_id > 0 ) {
                        $pt = \get_post_type( $child_id );
                        if ( is_string( $pt ) && '' !== $pt ) {
                            $child_counts[ $pt ] = ( $child_counts[ $pt ] ?? 0 ) + 1;
                        }
                    }
                }
            }

            arsort( $parent_counts );
            arsort( $child_counts );

            $parent_object = (string) ( array_key_first( $parent_counts ) ?: 'post' );
            $child_object  = (string) ( array_key_first( $child_counts ) ?: 'post' );

            if ( ! isset( $out_by_id[ $rel_id ] ) ) {
                $out_by_id[ $rel_id ] = [
                    'id'   => $rel_id,
                    'args' => [
                        'name'          => sprintf( 'JetEngine Relation %s', $rel_id ),
                        'parent_object' => $parent_object,
                        'child_object'  => $child_object,
                        'type'          => 'many_to_many',
                    ],
                ];
            }

            $source_tables[] = $table;
        }

        $out = array_values( $out_by_id );
        if ( empty( $out ) ) {
            return [ null, [] ];
        }

        $src = 'db_connections:' . implode( ',', array_slice( $source_tables, 0, 6 ) );
        return [ $src, $out ];
    }

    private function normalize_jetengine_object( string $object, string $subtype = '' ) : string {
        $subtype = trim( $subtype );
        if ( '' !== $subtype ) {
            return sanitize_key( $subtype );
        }

        $object = trim( $object );
        if ( '' === $object ) {
            return 'post';
        }

        $object = strtolower( $object );
        $object = str_replace( [ ' ', "\t", "\n", "\r" ], '', $object );

        if ( str_contains( $object, '::' ) ) {
            $parts = explode( '::', $object );
            $last = (string) end( $parts );
            $last = trim( $last );
            if ( '' !== $last ) {
                return sanitize_key( $last );
            }
        }

        if ( str_contains( $object, ':' ) ) {
            $parts = explode( ':', $object );
            $last = (string) end( $parts );
            $last = trim( $last );
            if ( '' !== $last ) {
                return sanitize_key( $last );
            }
        }

        if ( in_array( $object, [ 'posts', 'post', 'post_type' ], true ) ) {
            return 'post';
        }

        if ( in_array( $object, [ 'users', 'user' ], true ) ) {
            return 'user';
        }

        return sanitize_key( $object );
    }

    private function normalize_jetengine_relation_type( string $type ) : string {
        $type = strtolower( trim( $type ) );
        $type = str_replace( [ ' ', '-' ], '_', $type );

        if ( in_array( $type, [ 'one_to_one', 'one_to_many', 'many_to_many' ], true ) ) {
            return $type;
        }

        if ( in_array( $type, [ 'one2one', 'one_to1', 'one_to_one_relation' ], true ) ) {
            return 'one_to_one';
        }

        if ( in_array( $type, [ 'one2many', 'one_to_many_relation', 'one_to_multi' ], true ) ) {
            return 'one_to_many';
        }

        if ( in_array( $type, [ 'many2many', 'many_to_many_relation' ], true ) ) {
            return 'many_to_many';
        }

        return 'many_to_many';
    }

    private function get_jet_engine_collection_from_posts( string $kind ) : array {
        // JetEngine 3.x stores in multiple CPTs: jet-engine, jet-engine-cpt, cct, jet-cct, etc.
        $jet_post_types = ['jet-engine','jet-engine-cpt','jet-cpt','jet-engine-tax','cct','jet-cct','jet-engine-relation','jet-engine-meta'];
        // Only query valid registered post types to avoid WP error
        $jet_post_types = array_values(array_filter($jet_post_types, fn($pt) => post_type_exists($pt) || $pt === 'jet-engine'));
        // Ensure at least jet-engine
        if ( !in_array('jet-engine',$jet_post_types,true)) $jet_post_types[] = 'jet-engine';
        $posts = \get_posts( [
            'post_type'      => $jet_post_types,
            'post_status'    => 'any',
            'numberposts'    => 300,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'cache_results'  => false,
            'suppress_filters' => true,
        ] );

        if ( ! is_array( $posts ) || empty( $posts ) ) {
            return [];
        }

        $out = [];

        foreach ( $posts as $post_id ) {
            $post_id = (int) $post_id;
            if ( $post_id <= 0 ) {
                continue;
            }

            $post = \get_post( $post_id );
            if ( $post instanceof \WP_Post ) {
                foreach ( [ (string) $post->post_content, (string) $post->post_excerpt ] as $blob ) {
                    $payloads = $this->decode_any_payloads( $blob );
                    if ( empty( $payloads ) ) {
                        continue;
                    }

                    foreach ( $payloads as $payload ) {
                        foreach ( $this->find_definitions_in_array( $payload, 0 ) as $candidate ) {
                            $classified = $this->classify_jetengine_payload( $candidate );
                            if ( $classified !== $kind ) {
                                continue;
                            }

                            if ( 'cpt' === $kind || 'taxonomy' === $kind ) {
                                $slug = (string) ( $candidate['slug'] ?? '' );
                                if ( '' === $slug ) {
                                    continue;
                                }
                                $out[ $slug ] = $candidate;
                            } else {
                                $id = (string) ( $candidate['id'] ?? '' );
                                if ( '' === $id ) {
                                    $id = (string) ( $candidate['slug'] ?? '' );
                                }
                                if ( '' === $id ) {
                                    continue;
                                }
                                $out[ $id ] = $candidate;
                            }
                        }
                    }
                }
            }

            $meta = \get_post_meta( $post_id );
            if ( ! is_array( $meta ) || empty( $meta ) ) {
                continue;
            }

            foreach ( $meta as $meta_key => $values ) {
                if ( ! is_array( $values ) ) {
                    continue;
                }

                foreach ( $values as $value ) {
                    $payloads = $this->decode_any_payloads( $value );
                    if ( empty( $payloads ) ) {
                        continue;
                    }

                    foreach ( $payloads as $payload ) {
                        foreach ( $this->find_definitions_in_array( $payload, 0 ) as $candidate ) {
                            $classified = $this->classify_jetengine_payload( $candidate );
                            if ( $classified !== $kind ) {
                                continue;
                            }

                            if ( 'cpt' === $kind || 'taxonomy' === $kind ) {
                                $slug = (string) ( $candidate['slug'] ?? '' );
                                if ( '' === $slug ) {
                                    continue;
                                }
                                $out[ $slug ] = $candidate;
                            } else {
                                $id = (string) ( $candidate['id'] ?? '' );
                                if ( '' === $id ) {
                                    $id = (string) ( $candidate['slug'] ?? '' );
                                }
                                if ( '' === $id ) {
                                    continue;
                                }
                                $out[ $id ] = $candidate;
                            }
                        }
                    }
                }
            }
        }

        return array_values( $out );
    }

    private function get_jet_engine_collection_from_runtime( string $kind ) : array {
        if ( ! \function_exists( 'jet_engine' ) ) {
            return [];
        }

        $je = \jet_engine();
        if ( ! is_object( $je ) ) {
            return [];
        }

        $out = [];
        $queue = [
            [ $je, 0 ],
        ];
        $visited = 0;

        while ( ! empty( $queue ) && $visited < 120 ) {
            $visited++;
            [ $node, $depth ] = array_shift( $queue );
            $depth = (int) $depth;

            if ( $depth > 4 ) {
                continue;
            }

            if ( is_array( $node ) ) {
                foreach ( $this->find_definitions_in_array( $node, 0 ) as $candidate ) {
                    $classified = $this->classify_jetengine_payload( $candidate );
                    if ( $classified !== $kind ) {
                        continue;
                    }

                    $id = (string) ( $candidate['id'] ?? '' );
                    if ( '' === $id ) {
                        $id = (string) ( $candidate['slug'] ?? '' );
                    }
                    if ( '' !== $id ) {
                        $out[ $id ] = $candidate;
                    }
                }

                foreach ( $node as $v ) {
                    if ( is_array( $v ) || is_object( $v ) ) {
                        $queue[] = [ $v, $depth + 1 ];
                    }
                }
                continue;
            }

            if ( is_object( $node ) ) {
                if ( $node instanceof \WP_Post || $node instanceof \WP_Term || $node instanceof \WP_User ) {
                    continue;
                }

                if ( $node instanceof \Traversable ) {
                    foreach ( $node as $v ) {
                        if ( is_array( $v ) || is_object( $v ) ) {
                            $queue[] = [ $v, $depth + 1 ];
                        }
                    }
                    continue;
                }

                $vars = \get_object_vars( $node );
                if ( is_array( $vars ) && ! empty( $vars ) ) {
                    $queue[] = [ $vars, $depth + 1 ];
                }
            }
        }

        return array_values( $out );
    }

    private function classify_jetengine_payload( array $candidate ) : ?string {
        if ( isset( $candidate['args'] ) && is_array( $candidate['args'] ) ) {
            $args = $candidate['args'];
            if ( isset( $args['meta_fields'] ) || isset( $candidate['meta_fields'] ) || isset( $args['fields'] ) || isset( $candidate['fields'] ) ) {
                return 'metabox';
            }
            if ( isset( $args['parent_object'] ) || isset( $args['child_object'] ) || isset( $args['parent'] ) || isset( $args['child'] ) ) {
                return 'relation';
            }
            if ( isset( $args['object_type'] ) || isset( $args['object_types'] ) || isset( $args['hierarchical'] ) ) {
                return 'taxonomy';
            }
            if ( isset( $candidate['slug'] ) && ( isset( $args['supports'] ) || isset( $args['has_archive'] ) || isset( $args['rewrite'] ) || isset( $args['show_in_rest'] ) ) ) {
                return 'cpt';
            }
        }

        if ( isset( $candidate['meta_fields'] ) ) {
            return 'metabox';
        }

        if ( isset( $candidate['hierarchical'] ) || isset( $candidate['object_type'] ) || isset( $candidate['object_types'] ) ) {
            return 'taxonomy';
        }

        if ( isset( $candidate['slug'] ) ) {
            return 'cpt';
        }

        return null;
    }

    private function decode_any_payloads( mixed $value ) : array {
        $candidate = \maybe_unserialize( $value );

        if ( is_array( $candidate ) ) {
            return [ $candidate ];
        }

        if ( is_string( $candidate ) ) {
            $trim = trim( $candidate );
            if ( '' === $trim ) {
                return [];
            }

            if ( ( str_starts_with( $trim, '{' ) && str_ends_with( $trim, '}' ) ) || ( str_starts_with( $trim, '[' ) && str_ends_with( $trim, ']' ) ) ) {
                $json = json_decode( $trim, true );
                if ( is_array( $json ) ) {
                    return [ $json ];
                }
            }
        }

        return [];
    }

    private function find_definitions_in_array( array $data, int $depth ) : array {
        if ( $depth > 6 ) {
            return [];
        }

        $found = [];

        if ( isset( $data['slug'] ) || isset( $data['id'] ) ) {
            $found[] = $data;
        }

        foreach ( $data as $val ) {
            if ( is_array( $val ) ) {
                $found = array_merge( $found, $this->find_definitions_in_array( $val, $depth + 1 ) );
            }
        }

        return $found;
    }

    private function get_jetengine_options_debug() : array {
        global $wpdb;

        $options_table = $wpdb->options;
        $names = $wpdb->get_col(
            "SELECT option_name FROM {$options_table}
             WHERE option_name LIKE 'jet_engine%' OR option_name LIKE 'jetengine%'
             ORDER BY option_name ASC
             LIMIT 50"
        );

        $out = [];
        if ( ! is_array( $names ) ) {
            return $out;
        }

        foreach ( $names as $name ) {
            $val = \get_option( $name, null );
            $count = null;
            if ( is_array( $val ) ) {
                $count = count( $val );
            }
            $out[] = [
                'name'  => (string) $name,
                'type'  => gettype( $val ),
                'count' => $count,
            ];
        }

        return $out;
    }

    private function get_jetengine_post_types_debug() : array {
        global $wpdb;

        $posts_table = $wpdb->posts;
        $rows = $wpdb->get_results(
            "SELECT post_type, COUNT(*) AS cnt
             FROM {$posts_table}
             WHERE post_type LIKE '%jet%engine%'
             GROUP BY post_type
             ORDER BY cnt DESC
             LIMIT 20",
            \ARRAY_A
        );

        if ( ! is_array( $rows ) ) {
            return [];
        }

        return array_map(
            static fn( $r ) => [
                'post_type' => (string) ( $r['post_type'] ?? '' ),
                'count'     => (int) ( $r['cnt'] ?? 0 ),
            ],
            $rows
        );
    }

    private function get_jetengine_relations_tables_debug() : array {
        global $wpdb;

        $patterns = [
            $wpdb->prefix . 'jet_relations%',
            $wpdb->prefix . 'jet_rel_%',
            $wpdb->prefix . 'jet_rel%',
            $wpdb->prefix . 'jet_engine_rel%',
            $wpdb->prefix . 'jet_%rel%',
        ];

        $tables = [];
        foreach ( $patterns as $pattern ) {
            $rows = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );
            if ( is_array( $rows ) ) {
                $tables = array_merge( $tables, $rows );
            }
        }
        $tables = array_values( array_unique( array_filter( array_map( 'strval', $tables ) ) ) );
        $tables = array_values(
            array_filter(
                $tables,
                static fn( string $t ) => ! str_starts_with( $t, $wpdb->prefix . 'jetsync_' )
            )
        );
        if ( empty( $tables ) ) {
            return [];
        }

        $tables = array_slice( $tables, 0, 15 );

        $out = [];
        foreach ( $tables as $table ) {
            $cols_rows = $wpdb->get_results( "SHOW COLUMNS FROM {$table}", \ARRAY_A );
            if ( ! is_array( $cols_rows ) || empty( $cols_rows ) ) {
                continue;
            }

            $cols = [];
            foreach ( $cols_rows as $r ) {
                if ( isset( $r['Field'] ) ) {
                    $cols[] = (string) $r['Field'];
                }
            }
            $cols = array_slice( $cols, 0, 40 );

            $args_col = null;
            foreach ( [ 'args', 'settings', 'config', 'data', 'payload' ] as $c ) {
                if ( in_array( $c, $cols, true ) ) {
                    $args_col = $c;
                    break;
                }
            }

            $out[] = [
                'table'    => (string) $table,
                'args_col' => $args_col,
                'columns'  => $cols,
            ];
        }

        return $out;
    }

    private function get_jetengine_storage_posts_debug() : array {
        $ids = \get_posts( [
            'post_type'        => 'jet-engine',
            'post_status'      => 'any',
            'numberposts'      => 10,
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'fields'           => 'ids',
            'no_found_rows'    => true,
            'cache_results'    => false,
            'suppress_filters' => true,
        ] );

        if ( ! is_array( $ids ) || empty( $ids ) ) {
            return [];
        }

        $out = [];

        foreach ( $ids as $id ) {
            $id = (int) $id;
            if ( $id <= 0 ) {
                continue;
            }

            $post = \get_post( $id );
            $title = \get_the_title( $id );
            $meta  = \get_post_meta( $id );
            $keys  = is_array( $meta ) ? array_keys( $meta ) : [];
            $keys  = array_slice( array_values( array_map( 'strval', $keys ) ), 0, 15 );
            $content_preview = '';
            $post_name = '';
            if ( $post instanceof \WP_Post ) {
                $post_name = (string) $post->post_name;
                $blob = trim( (string) $post->post_content );
                if ( '' !== $blob ) {
                    $content_preview = \wp_strip_all_tags( $blob );
                    $content_preview = preg_replace( '/\s+/', ' ', $content_preview );
                    $content_preview = substr( (string) $content_preview, 0, 160 );
                }
            }

            $out[] = [
                'id'       => $id,
                'title'    => is_string( $title ) ? $title : '',
                'slug'     => $post_name,
                'metaKeys' => $keys,
                'content'  => $content_preview,
            ];
        }

        return $out;
    }
}
