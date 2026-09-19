<?php
declare( strict_types=1 );

namespace JetSync\Compatibility;

use JetSync\Registry\Model\MetaFieldDefinition;
use JetSync\Registry\Model\MetaBoxDefinition;

/**
 * Runtime snapshot: captures WordPress live registrations as source of truth.
 * This bypasses fragile JetEngine internal storage formats and guarantees
 * 100% capture of what is actually registered in WP.
 */
class SnapshotService {

    /**
     * @return array{cpts: array<int, array{slug:string,name:string,object:object}>, taxonomies: array<int, array{slug:string,name:string,object:object}>}
     */
    public static function snapshot_post_types_and_taxonomies() : array {
        $ignored_post_types = [
            'jet-engine',
            'jet-engine-cpt',
            'jet-engine-tax',
            'jet-cct',
            'jet-cct-',
            'elementor_library',
            'elementor_font',
            'elementor_icons',
            'wp_block',
            'wp_template',
            'wp_template_part',
            'wp_navigation',
            'wp_global_styles',
            'custom_css',
            'customize_changeset',
            'oembed_cache',
            'user_request',
            'revision',
            'nav_menu_item',
            'attachment',
        ];

        $ignored_taxonomies = [ 'post_format', 'wp_theme', 'wp_template_part_area' ];

        $cpts = [];
        $post_types = \get_post_types( [ '_builtin' => false ], 'objects' );
        if ( is_array( $post_types ) ) {
            foreach ( $post_types as $slug => $obj ) {
                $slug = (string) $slug;
                if ( '' === $slug ) continue;
                // Allow jetsync's own but filter core jet-engine storage
                if ( in_array( $slug, $ignored_post_types, true ) ) continue;
                if ( str_starts_with( $slug, 'jet-' ) && in_array( $slug, ['jet-engine','jet-cct'], true) ) continue;
                // ignore elementor/metform/e- prefixes handled elsewhere but keep for snapshot
                $label = '';
                if ( is_object( $obj ) && isset( $obj->labels->name ) ) $label = (string) $obj->labels->name;
                if ( '' === $label && is_object( $obj ) && isset( $obj->label ) ) $label = (string) $obj->label;
                $cpts[] = [ 'slug' => $slug, 'name' => $label !== '' ? $label : $slug, 'object' => $obj ];
            }
        }

        $taxs = [];
        $taxonomies = \get_taxonomies( [ '_builtin' => false ], 'objects' );
        if ( is_array( $taxonomies ) ) {
            foreach ( $taxonomies as $slug => $obj ) {
                $slug = (string) $slug;
                if ( '' === $slug ) continue;
                if ( in_array( $slug, $ignored_taxonomies, true ) ) continue;
                $label = '';
                if ( is_object( $obj ) && isset( $obj->labels->name ) ) $label = (string) $obj->labels->name;
                if ( '' === $label && is_object( $obj ) && isset( $obj->label ) ) $label = (string) $obj->label;
                $taxs[] = [ 'slug' => $slug, 'name' => $label !== '' ? $label : $slug, 'object' => $obj ];
            }
        }

        return [ 'cpts' => $cpts, 'taxonomies' => $taxs ];
    }

    /**
     * Snapshot meta fields via DISTINCT meta_key from DB (more reliable than sampling 30 posts).
     * @param array<string> $post_types
     * @return array<MetaBoxDefinition>
     */
    public static function snapshot_meta_boxes_from_db( array $post_types ) : array {
        global $wpdb;
        $post_types = array_values( array_filter( array_map( '\sanitize_key', $post_types ) ) );
        if ( empty( $post_types ) ) return [];

        $blacklist = [ '_edit_lock','_edit_last','_wp_old_slug','_thumbnail_id','_wp_page_template','_wp_trash_meta_status','_wp_trash_meta_time' ];
        $blocked_prefixes = [ '_wp_','_edit_','_elementor','_oembed','_yoast','_rank_math','_listing_','_menu_item','jet_engine','jetengine' ];

        $out = [];

        foreach ( $post_types as $pt ) {
            // Use DISTINCT to get all keys, not just sample 30 posts
            $keys = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = %s AND p.post_status != 'trash' LIMIT 200",
                $pt
            ));
            if ( ! is_array( $keys ) ) continue;

            // Fallback to sampling if table is huge and distinct missed due to LIMIT
            if ( count( $keys ) >= 190 ) {
                $sample = $wpdb->get_col( $wpdb->prepare(
                    "SELECT pm.meta_key FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = %s LIMIT 5000",
                    $pt
                ));
                if ( is_array( $sample ) ) $keys = array_values( array_unique( $sample ) );
            }

            $filtered = [];
            foreach ( $keys as $k ) {
                $k = (string) $k;
                if ( '' === $k ) continue;
                if ( in_array( $k, $blacklist, true ) ) continue;
                $blocked = false;
                foreach ( $blocked_prefixes as $p ) { if ( str_starts_with( $k, $p ) ) { $blocked = true; break; } }
                if ( $blocked ) continue;
                // Also include our own model keys: height, bust etc need to survive
                $filtered[] = $k;
            }

            if ( empty( $filtered ) ) continue;

            // Sample IDs for type inference (6 posts)
            $sample_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'trash' ORDER BY ID DESC LIMIT 6",
                $pt
            ));
            $sample_ids = is_array( $sample_ids ) ? array_map('intval',$sample_ids) : [];

            $field_objects = [];
            foreach ( array_slice( $filtered, 0, 40 ) as $k ) {
                $type = self::infer_type_from_samples( $sample_ids, $k );
                $field_objects[] = new MetaFieldDefinition(
                    name: sanitize_key($k),
                    title: ucwords( str_replace(['_','-'],' ', $k) ),
                    type: $type
                );
            }

            if ( empty( $field_objects ) ) continue;

            $out[] = new MetaBoxDefinition(
                id: 'jetsync_auto_' . $pt,
                title: 'Settings',
                object_types: [$pt],
                context: 'normal',
                priority: 'default',
                fields: $field_objects,
                active: true
            );
        }

        return $out;
    }

    private static function infer_type_from_samples( array $sample_ids, string $meta_key ) : string {
        $k = strtolower($meta_key);
        // reuse registry logic but simplified
        if ( str_contains($k,'gallery')) return 'gallery';
        if ( str_contains($k,'cover') || str_contains($k,'thumbnail') || str_contains($k,'image')) return 'media';
        if ( str_contains($k,'instagram') || str_contains($k,'uri') || str_contains($k,'url') || str_contains($k,'link')) return 'url';
        if ( str_contains($k,'model-section') || str_contains($k,'model_section')) return 'checkbox';
        if ( $k === 'updated' || str_ends_with($k,'-updated') || str_ends_with($k,'_updated')) return 'switcher';

        // sample DB values
        foreach ( $sample_ids as $pid ) {
            $raw = \get_post_meta($pid, $meta_key, true);
            if ( '' === $raw || null === $raw ) continue;
            if ( is_array($raw) ) {
                $ids = array_values(array_filter(array_map('\absint',$raw)));
                if ( !empty($ids)) return 'gallery';
                continue;
            }
            $val = trim((string)$raw);
            if ( preg_match('/^\d+(,\d+)*$/',$val)) {
                $ids = array_values(array_filter(array_map('\absint', explode(',',$val))));
                if ( count($ids) > 1 ) return 'gallery';
                if ( 1 === count($ids) ) {
                    $pt = \get_post_type($ids[0]);
                    if ( 'attachment' === $pt ) return 'media';
                }
            }
            if ( strlen($val) > 180 || str_contains($val,"\n")) {
                if ( str_contains($val,'<')) return 'wysiwyg';
                return 'textarea';
            }
            if ( filter_var($val, FILTER_VALIDATE_URL)) return 'url';
        }
        return 'text';
    }

    /**
     * Snapshot relations from DB tables + option scan (exhaustive).
     * @return array<int, array{id:string,title:string,parent_object:string,child_object:string,type:string}>
     */
    public static function snapshot_relations() : array {
        global $wpdb;
        $tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}jet%'");
        if ( !is_array($tables) ) $tables = [];
        $tables = array_values(array_filter(array_map('strval',$tables)));
        $tables = array_values(array_filter($tables, fn($t)=>!str_starts_with($t,$wpdb->prefix.'jetsync_') ));

        $out = [];
        // 1) Try to read jet_engine_relations option and jet_engine tables if still present
        $candidates = [];
        $opt_names = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%relation%' AND option_name LIKE '%jet%' LIMIT 20");
        if ( is_array($opt_names) ) {
            foreach ( $opt_names as $on ) {
                $val = get_option($on, null);
                if ( is_array($val) && !empty($val) ) {
                    // try to find definitions inside
                    $flat = self::flatten_jetengine_payload($val);
                    foreach ( $flat as $cand ) {
                        if ( isset($cand['id']) || isset($cand['slug'])) {
                            $candidates[] = $cand;
                        }
                    }
                }
            }
        }

        // 2) Inspect relation definition tables (e.g. jet_engine_relations, jet_relations)
        foreach ( $tables as $table ) {
            $cols = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
            if ( !is_array($cols) ) continue;
            $colNames = array_column($cols,'Field');
            $has_id = in_array('id',$colNames,true) || in_array('rel_id',$colNames,true);
            $has_parent = in_array('parent_object',$colNames,true) || in_array('parent',$colNames,true);
            $has_child = in_array('child_object',$colNames,true) || in_array('child',$colNames,true);
            $args_col = null;
            foreach (['args','settings','config','data'] as $c) if (in_array($c,$colNames,true)) {$args_col=$c;break;}
            if ( !$has_id && !$args_col) continue;
            if ( !$has_id || (!$has_parent && !$args_col)) continue;

            $rows = $wpdb->get_results("SELECT * FROM {$table} LIMIT 200", ARRAY_A);
            if (!is_array($rows)) continue;
            foreach ( $rows as $row ) {
                $id = (string)($row['id'] ?? $row['rel_id'] ?? '');
                if ( '' === $id) continue;
                $args = [];
                if ( $args_col && isset($row[$args_col])) {
                    $decoded = self::decode_payload($row[$args_col]);
                    if ( is_array($decoded) ) $args = $decoded['args'] ?? $decoded;
                }
                $name = (string)($row['name'] ?? $row['title'] ?? $args['name'] ?? $args['title'] ?? $id);
                $parent = (string)($row['parent_object'] ?? $args['parent_object'] ?? '');
                $child = (string)($row['child_object'] ?? $args['child_object'] ?? '');
                if ( '' === $parent || '' === $child) continue;
                $out[$id] = ['id'=>$id,'title'=>$name,'parent_object'=>$parent,'child_object'=>$child,'type'=> $args['type'] ?? 'many_to_many'];
            }
        }

        // 3) Fallback: derive from connection tables (rel_id present)
        $connTables = array_filter($tables, fn($t)=> preg_match('/jet_rel/i',$t));
        $relIdsFromConn = [];
        foreach ( $connTables as $ct ) {
            $cols = $wpdb->get_results("SHOW COLUMNS FROM {$ct}", ARRAY_A);
            $colNames = is_array($cols) ? array_column($cols,'Field') : [];
            if ( !in_array('rel_id',$colNames,true)) continue;
            $ids = $wpdb->get_col("SELECT DISTINCT rel_id FROM {$ct} LIMIT 100");
            if ( is_array($ids) ) foreach ( $ids as $rid ) { $rid = sanitize_key((string)$rid); if (''!==$rid) $relIdsFromConn[$rid]=true; }
        }
        foreach ( array_keys($relIdsFromConn) as $rid ) {
            if ( isset($out[$rid])) continue;
            // Infer parent/child from sample rows
            $sampleTable = null;
            foreach ( $connTables as $ct ) {
                $cnt = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$ct} WHERE rel_id = %s",$rid));
                if ( $cnt ) { $sampleTable=$ct; break; }
            }
            if ( !$sampleTable) continue;
            $sample = $wpdb->get_row($wpdb->prepare("SELECT parent_object_id, child_object_id FROM {$sampleTable} WHERE rel_id = %s LIMIT 1", $rid), ARRAY_A);
            if (!is_array($sample)) continue;
            $pt_parent = get_post_type((int)($sample['parent_object_id'] ?? 0)) ?: 'post';
            $pt_child = get_post_type((int)($sample['child_object_id'] ?? 0)) ?: 'post';
            $out[$rid] = ['id'=>$rid,'title'=>ucwords(str_replace(['_','-'],' ',$rid)),'parent_object'=>$pt_parent,'child_object'=>$pt_child,'type'=>'many_to_many'];
        }

        // Merge candidates found via options scan
        foreach ( $candidates as $c ) {
            $id = (string)($c['id'] ?? $c['slug'] ?? '');
            if ( ''=== $id) continue;
            if ( isset($out[$id])) continue;
            $args = $c['args'] ?? $c;
            $out[$id] = [
                'id'=>$id,
                'title'=> (string)($args['name'] ?? $args['title'] ?? $c['title'] ?? $id),
                'parent_object'=> (string)($args['parent_object'] ?? ''),
                'child_object'=> (string)($args['child_object'] ?? ''),
                'type'=> (string)($args['type'] ?? 'many_to_many')
            ];
        }

        return array_values($out);
    }

    private static function decode_payload( mixed $blob ) : ?array {
        if ( is_array($blob)) return $blob;
        if ( !is_string($blob) || '' === trim($blob)) return null;
        $trim = trim($blob);
        // maybe serialized
        $maybe = @unserialize($trim);
        if ( is_array($maybe)) return $maybe;
        $json = json_decode($trim,true);
        if ( is_array($json)) return $json;
        return null;
    }

    private static function flatten_jetengine_payload( mixed $val ) : array {
        $out = [];
        $stack = [$val];
        $seen = 0;
        while (!empty($stack) && $seen < 200) {
            $seen++;
            $node = array_shift($stack);
            if ( is_array($node) ) {
                // check if looks like definition
                if ( isset($node['slug']) || isset($node['id'])) {
                    $out[] = $node;
                }
                foreach ( $node as $v ) if (is_array($v)||is_object($v)) $stack[] = (array)$v;
            } elseif (is_object($node)) {
                $arr = (array)$node;
                if ( isset($arr['slug']) || isset($arr['id'])) $out[] = $arr;
                foreach ($arr as $v) if (is_array($v)||is_object($v)) $stack[] = (array)$v;
            }
        }
        return $out;
    }
}
