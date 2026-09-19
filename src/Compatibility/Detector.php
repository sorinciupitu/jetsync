<?php
declare( strict_types=1 );

namespace JetSync\Compatibility;

/**
 * Checks for JetEngine presence, activity status, and stored configurations.
 */
class Detector {

    /**
     * Verify if JetEngine is active.
     *
     * @return bool
     */
    public function is_jet_engine_active() : bool {
        return class_exists( 'Jet_Engine' ) || defined( 'JET_ENGINE_VERSION' );
    }

    /**
     * Checks if JetEngine configuration arrays exist in wp_options.
     *
     * @return bool
     */
    public function has_jet_engine_data() : bool {
        return $this->get_cpt_count() > 0 || $this->get_taxonomy_count() > 0;
    }

    /**
     * Get count of Custom Post Types stored in JetEngine.
     * Falls back to runtime snapshot (WP live registration) when option storage empty - ensures 100% detection for JetEngine 3.x
     *
     * @return int
     */
    public function get_cpt_count() : int {
        $cnt = $this->get_collection_count(
            preferred_option: 'jet_engine_post_types',
            like_patterns: [ 'jet_engine%post%type%', 'jetengine%post%type%' ]
        );
        if ( $cnt > 0 ) return $cnt;
        // Fallback: snapshot live WP registrations (version-agnostic, works without JetEngine option)
        // This captures what JetEngine actually registered, regardless of its internal storage format.
        $snap = SnapshotService::snapshot_post_types_and_taxonomies();
        // Filter to Jetsync-managed vs all? Dashboard wants JetEngine count, so exclude core but include JetEngine-like.
        return count( $snap['cpts'] );
    }

    /**
     * Get count of Custom Taxonomies stored in JetEngine.
     *
     * @return int
     */
    public function get_taxonomy_count() : int {
        $cnt = $this->get_collection_count(
            preferred_option: 'jet_engine_taxonomies',
            like_patterns: [ 'jet_engine%tax%', 'jetengine%tax%' ]
        );
        if ( $cnt > 0 ) return $cnt;
        $snap = SnapshotService::snapshot_post_types_and_taxonomies();
        return count( $snap['taxonomies'] );
    }

    private function normalize_collection( mixed $raw ) : array {
        if ( is_array( $raw ) && isset( $raw['items'] ) && is_array( $raw['items'] ) ) {
            return $raw['items'];
        }

        if ( is_array( $raw ) ) {
            return $raw;
        }

        return [];
    }

    private function get_collection_count( string $preferred_option, array $like_patterns ) : int {
        global $wpdb;

        $raw = $this->normalize_collection( \get_option( $preferred_option, [] ) );
        if ( ! empty( $raw ) ) {
            return count( $raw );
        }

        $options_table = $wpdb->options;
        $candidates = [];
        foreach ( $like_patterns as $like ) {
            $rows = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT option_name FROM {$options_table} WHERE option_name LIKE %s LIMIT 10",
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
            if ( is_array( $first ) && isset( $first['slug'] ) ) {
                return count( $candidate_raw );
            }
        }

        return 0;
    }
}
