<?php
declare( strict_types=1 );

namespace JetSync\Admin;

use JetSync\Core\JetSync;
use JetSync\Core\Capabilities;
use JetSync\Compatibility\ImportManager;
use JetSync\Migration\MigrationPlan;
use JetSync\Migration\MigrationRunner;
use JetSync\Migration\ImportConfigManager;
use JetSync\Migration\IntegrityChecker;
use JetSync\Migration\RepairManager;

/**
 * Handles security check verification and routing for WP AJAX requests.
 */
class AjaxController {

    /**
     * Constructor. Binds AJAX endpoints.
     */
    public function __construct() {
        \add_action( 'wp_ajax_jetsync_run_diagnostics',        [ $this, 'run_diagnostics' ] );
        \add_action( 'wp_ajax_jetsync_execute_import',         [ $this, 'execute_import' ] );
        \add_action( 'wp_ajax_jetsync_create_migration_plan',  [ $this, 'create_migration_plan' ] );
        \add_action( 'wp_ajax_jetsync_run_migration_step',     [ $this, 'run_migration_step' ] );
        \add_action( 'wp_ajax_jetsync_rollback_migration',     [ $this, 'rollback_migration' ] );
        \add_action( 'wp_ajax_jetsync_reset_migration_plan',   [ $this, 'reset_migration_plan' ] );
        \add_action( 'wp_ajax_jetsync_import_config',          [ $this, 'import_config' ] );
        \add_action( 'wp_ajax_jetsync_validate_integrity',     [ $this, 'validate_integrity' ] );
        \add_action( 'wp_ajax_jetsync_repair_integrity',       [ $this, 'repair_integrity' ] );
        \add_action( 'wp_ajax_jetsync_resync_jetengine',       [ $this, 'resync_jetengine' ] );
        \add_action( 'wp_ajax_jetsync_full_reset',            [ $this, 'full_reset' ] );
        \add_action( 'wp_ajax_jetsync_get_listing_fields',     [ $this, 'get_listing_fields' ] );
    }

    /**
     * Diagnostic scan endpoint. Non-destructive dry-run.
     *
     * @return void
     */
    public function run_diagnostics() : void {
        \check_ajax_referer( 'jetsync_wizard_nonce', 'nonce' );

        if ( ! \current_user_can( Capabilities::MANAGE ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Insufficient permissions.', 'jet-sync' ) ], 403 );
        }

        /** @var \JetSync\Registry\PostTypeRegistry $cpt_registry */
        $cpt_registry = JetSync::get_instance()->get( 'cpt_registry' );
        /** @var \JetSync\Registry\TaxonomyRegistry $taxonomy_registry */
        $taxonomy_registry = JetSync::get_instance()->get( 'taxonomy_registry' );
        /** @var \JetSync\Registry\MetaBoxRegistry $metabox_registry */
        $metabox_registry = JetSync::get_instance()->get( 'metabox_registry' );
        /** @var \JetSync\Registry\RelationRegistry $relation_registry */
        $relation_registry = JetSync::get_instance()->get( 'relation_registry' );
        /** @var \JetSync\Core\Logger $logger */
        $logger = JetSync::get_instance()->get( 'logger' );

        $import_manager = new ImportManager(
            $cpt_registry,
            $taxonomy_registry,
            $metabox_registry,
            $relation_registry,
            $logger
        );
        $report = $import_manager->analyze_scan();

        \update_option( 'jetsync_last_scan_report', $report, false );

        \wp_send_json_success( $report );
    }

    /**
     * Sync execution endpoint. Creates registry items.
     *
     * @return void
     */
    public function execute_import() : void {
        \check_ajax_referer( 'jetsync_wizard_nonce', 'nonce' );

        if ( ! \current_user_can( Capabilities::MANAGE ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Insufficient permissions.', 'jet-sync' ) ], 403 );
        }

        /** @var \JetSync\Registry\PostTypeRegistry $cpt_registry */
        $cpt_registry = JetSync::get_instance()->get( 'cpt_registry' );
        /** @var \JetSync\Registry\TaxonomyRegistry $taxonomy_registry */
        $taxonomy_registry = JetSync::get_instance()->get( 'taxonomy_registry' );
        /** @var \JetSync\Registry\MetaBoxRegistry $metabox_registry */
        $metabox_registry = JetSync::get_instance()->get( 'metabox_registry' );
        /** @var \JetSync\Registry\RelationRegistry $relation_registry */
        $relation_registry = JetSync::get_instance()->get( 'relation_registry' );
        /** @var \JetSync\Core\Logger $logger */
        $logger = JetSync::get_instance()->get( 'logger' );

        $import_manager = new ImportManager(
            $cpt_registry,
            $taxonomy_registry,
            $metabox_registry,
            $relation_registry,
            $logger
        );
        $selected_cpts = null;
        if ( isset( $_POST['selected_cpts'] ) && is_array( $_POST['selected_cpts'] ) ) {
            $selected_cpts = array_values(
                array_filter(
                    array_map( '\sanitize_key', array_map( 'strval', $_POST['selected_cpts'] ) )
                )
            );
        }

        $selected_taxonomies = null;
        if ( isset( $_POST['selected_taxonomies'] ) && is_array( $_POST['selected_taxonomies'] ) ) {
            $selected_taxonomies = array_values(
                array_filter(
                    array_map( '\sanitize_key', array_map( 'strval', $_POST['selected_taxonomies'] ) )
                )
            );
        }

        $results = $import_manager->execute_import( $selected_cpts, $selected_taxonomies );

        \wp_send_json_success( $results );
    }

    public function get_listing_fields() : void {
        \check_ajax_referer( 'jetsync_wizard_nonce', 'nonce' );

        if ( ! \current_user_can( Capabilities::MANAGE ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Insufficient permissions.', 'jet-sync' ) ], 403 );
        }

        $post_type = isset( $_POST['post_type'] ) ? \sanitize_key( (string) $_POST['post_type'] ) : '';
        if ( '' === $post_type ) {
            \wp_send_json_success( [ 'meta_fields' => [] ] );
        }

        /** @var \JetSync\Registry\MetaBoxRegistry $metabox_registry */
        $metabox_registry = JetSync::get_instance()->get( 'metabox_registry' );
        $metaboxes = $metabox_registry ? $metabox_registry->get_all() : [];

        $out = [];
        foreach ( $metaboxes as $mb ) {
            if ( ! $mb instanceof \JetSync\Registry\Model\MetaBoxDefinition ) {
                continue;
            }
            if ( ! $mb->is_active() ) {
                continue;
            }
            $mb_types = $mb->get_object_types();
            if ( ! empty( $mb_types ) && ! in_array( $post_type, $mb_types, true ) ) {
                continue;
            }

            foreach ( $mb->get_fields() as $field ) {
                if ( ! $field instanceof \JetSync\Registry\Model\MetaFieldDefinition ) {
                    continue;
                }
                $key = $field->get_name();
                if ( '' === $key ) {
                    continue;
                }
                if ( isset( $out[ $key ] ) ) {
                    continue;
                }
                $out[ $key ] = [
                    'key'   => $key,
                    'label' => $field->get_title(),
                    'type'  => $field->get_type(),
                ];
            }
        }

        \wp_send_json_success( [ 'meta_fields' => array_values( $out ) ] );
    }

    // -------------------------------------------------------------------------
    // Migration AJAX endpoints
    // -------------------------------------------------------------------------

    /**
     * Create a new migration plan.
     *
     * @return void
     */
    public function create_migration_plan() : void {
        \check_ajax_referer( 'jetsync_ajax', '_wpnonce' );

        if ( ! \current_user_can( Capabilities::MIGRATE ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Insufficient permissions.', 'jet-sync' ) ], 403 );
        }

        /** @var MigrationRunner|null $runner */
        $runner = JetSync::get_instance()->get( 'migration_runner' );
        if ( ! $runner ) {
            \wp_send_json_error( [ 'message' => \__( 'Migration runner not available.', 'jet-sync' ) ] );
        }

        $include_deactivate = ! empty( $_POST['include_deactivate'] ) && '1' === $_POST['include_deactivate'];
        $runner->create_plan( $include_deactivate );

        \wp_send_json_success( [ 'message' => \__( 'Migration plan created. Click "Run Next Step" to begin.', 'jet-sync' ) ] );
    }

    /**
     * Execute the next pending step in the current migration plan.
     *
     * @return void
     */
    public function run_migration_step() : void {
        \check_ajax_referer( 'jetsync_ajax', '_wpnonce' );

        if ( ! \current_user_can( Capabilities::MIGRATE ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Insufficient permissions.', 'jet-sync' ) ], 403 );
        }

        /** @var MigrationRunner|null $runner */
        $runner = JetSync::get_instance()->get( 'migration_runner' );
        if ( ! $runner ) {
            \wp_send_json_error( [ 'message' => \__( 'Migration runner not available.', 'jet-sync' ) ] );
        }

        $result = $runner->run_next_step();

        if ( in_array( $result['status'], [ MigrationPlan::STATUS_FAILED, 'error' ], true ) ) {
            \wp_send_json_error( $result );
        }

        \wp_send_json_success( $result );
    }

    /**
     * Roll back all completed reversible steps.
     *
     * @return void
     */
    public function rollback_migration() : void {
        \check_ajax_referer( 'jetsync_ajax', '_wpnonce' );

        if ( ! \current_user_can( Capabilities::MIGRATE ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Insufficient permissions.', 'jet-sync' ) ], 403 );
        }

        /** @var MigrationRunner|null $runner */
        $runner = JetSync::get_instance()->get( 'migration_runner' );
        if ( ! $runner ) {
            \wp_send_json_error( [ 'message' => \__( 'Migration runner not available.', 'jet-sync' ) ] );
        }

        $result = $runner->rollback();
        \wp_send_json_success( $result );
    }

    /**
     * Clear the current migration plan from the database.
     *
     * @return void
     */
    public function reset_migration_plan() : void {
        \check_ajax_referer( 'jetsync_ajax', '_wpnonce' );

        if ( ! \current_user_can( Capabilities::MIGRATE ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Insufficient permissions.', 'jet-sync' ) ], 403 );
        }

        MigrationPlan::clear();
        \wp_send_json_success( [ 'message' => \__( 'Migration plan cleared.', 'jet-sync' ) ] );
    }

    /**
     * Import a JSON configuration file into the registries.
     *
     * @return void
     */
    public function import_config() : void {
        \check_ajax_referer( 'jetsync_ajax', '_wpnonce' );

        if ( ! \current_user_can( Capabilities::MANAGE ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Insufficient permissions.', 'jet-sync' ) ], 403 );
        }

        if ( empty( $_FILES['jetsync_import_file'] ) || ! is_array( $_FILES['jetsync_import_file'] ) ) {
            \wp_send_json_error( [ 'message' => \__( 'No file uploaded.', 'jet-sync' ) ] );
        }

        $file = $_FILES['jetsync_import_file'];

        if ( ! empty( $file['error'] ) ) {
            \wp_send_json_error( [ 'message' => \__( 'File upload error.', 'jet-sync' ) ] );
        }

        $max_bytes = 2 * 1024 * 1024;
        if ( isset( $file['size'] ) && (int) $file['size'] > $max_bytes ) {
            \wp_send_json_error( [ 'message' => \__( 'File is too large.', 'jet-sync' ) ] );
        }

        $tmp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
        $name = isset( $file['name'] ) ? (string) $file['name'] : '';

        if ( '' === $tmp ) {
            \wp_send_json_error( [ 'message' => \__( 'No file uploaded.', 'jet-sync' ) ] );
        }

        $check = \wp_check_filetype_and_ext( $tmp, $name );
        $ext = is_array( $check ) ? (string) ( $check['ext'] ?? '' ) : '';
        if ( 'json' !== strtolower( $ext ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Invalid file type. Please upload a JSON export.', 'jet-sync' ) ] );
        }

        if ( ! \is_uploaded_file( $tmp ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Invalid file upload.', 'jet-sync' ) ] );
        }

        $json = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( false === $json ) {
            \wp_send_json_error( [ 'message' => \__( 'Could not read the uploaded file.', 'jet-sync' ) ] );
        }

        $manager = new ImportConfigManager();
        $result  = $manager->import_from_json( $json );

        if ( \is_wp_error( $result ) ) {
            \wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        \wp_send_json_success( [
            'message' => sprintf(
                /* translators: counts */
                \__( 'Import successful: %1$d CPTs, %2$d Taxonomies, %3$d Meta Boxes, %4$d Relations, %5$d Listings.', 'jet-sync' ),
                (int) $result['post_types'],
                (int) $result['taxonomies'],
                (int) $result['meta_boxes'],
                (int) $result['relations'],
                (int) ( $result['listings'] ?? 0 )
            ),
            'counts'  => $result,
        ] );
    }

    public function validate_integrity() : void {
        \check_ajax_referer( 'jetsync_ajax', '_wpnonce' );

        if ( ! \current_user_can( Capabilities::MIGRATE ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Insufficient permissions.', 'jet-sync' ) ], 403 );
        }

        $for_deactivation = ! empty( $_POST['for_deactivation'] ) && '1' === (string) $_POST['for_deactivation'];

        $checker = new IntegrityChecker();
        $report  = $checker->run( $for_deactivation );

        \wp_send_json_success( [
            'message' => \__( 'Validation completed.', 'jet-sync' ),
            'report'  => $report,
        ] );
    }

    public function repair_integrity() : void {
        \check_ajax_referer( 'jetsync_ajax', '_wpnonce' );

        if ( ! \current_user_can( Capabilities::MIGRATE ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Insufficient permissions.', 'jet-sync' ) ], 403 );
        }

        $cleanup_orphan_connections = ! empty( $_POST['cleanup_orphan_connections'] ) && '1' === (string) $_POST['cleanup_orphan_connections'];
        $cleanup_orphan_meta        = ! empty( $_POST['cleanup_orphan_meta'] ) && '1' === (string) $_POST['cleanup_orphan_meta'];

        $manager = new RepairManager();
        $report  = $manager->repair( $cleanup_orphan_connections, $cleanup_orphan_meta );

        \wp_send_json_success( [
            'message' => \__( 'Repair completed.', 'jet-sync' ),
            'report'  => $report,
        ] );
    }

    public function resync_jetengine() : void {
        \check_ajax_referer( 'jetsync_ajax', '_wpnonce' );

        if ( ! \current_user_can( Capabilities::MIGRATE ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Insufficient permissions.', 'jet-sync' ) ], 403 );
        }

        if ( ! defined( 'JET_ENGINE_VERSION' ) ) {
            \wp_send_json_error( [ 'message' => \__( 'JetEngine not detected. Re-sync is unavailable.', 'jet-sync' ) ] );
        }

        /** @var \JetSync\Registry\PostTypeRegistry $cpt_registry */
        $cpt_registry = JetSync::get_instance()->get( 'cpt_registry' );
        /** @var \JetSync\Registry\TaxonomyRegistry $taxonomy_registry */
        $taxonomy_registry = JetSync::get_instance()->get( 'taxonomy_registry' );
        /** @var \JetSync\Registry\MetaBoxRegistry $metabox_registry */
        $metabox_registry = JetSync::get_instance()->get( 'metabox_registry' );
        /** @var \JetSync\Registry\RelationRegistry $relation_registry */
        $relation_registry = JetSync::get_instance()->get( 'relation_registry' );
        /** @var \JetSync\Core\Logger $logger */
        $logger = JetSync::get_instance()->get( 'logger' );

        $import_manager = new ImportManager(
            $cpt_registry,
            $taxonomy_registry,
            $metabox_registry,
            $relation_registry,
            $logger
        );
        $results = $import_manager->execute_import();

        \wp_send_json_success( [
            'message' => \__( 'Re-sync completed.', 'jet-sync' ),
            'results' => $results,
        ] );
    }

    /**
     * Full reset: wipe all JetSync registries and relation data to start import from zero.
     * Requires typing RESET as confirmation.
     */
    public function full_reset() : void {
        \check_ajax_referer( 'jetsync_ajax', '_wpnonce' );

        if ( ! \current_user_can( Capabilities::MANAGE ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Insufficient permissions.', 'jet-sync' ) ], 403 );
        }

        $confirm = isset( $_POST['confirm'] ) ? trim( (string) $_POST['confirm'] ) : '';
        if ( 'RESET' !== $confirm ) {
            \wp_send_json_error( [ 'message' => \__( 'Type RESET to confirm.', 'jet-sync' ) ] );
        }

        global $wpdb;

        // Options to wipe (keep jetsync_settings and installed_version for UX)
        $options = [
            'jetsync_cpt_registry',
            'jetsync_taxonomy_registry',
            'jetsync_meta_boxes',
            'jetsync_relations',
            'jetsync_listings',
            'jetsync_queries',
            'jetsync_migration_plan',
            'jetsync_last_scan_report',
            'jetsync_last_integrity_report',
            'jetsync_last_repair_report',
            'jetsync_model_fields_repaired_v2',
            'jetsync_last_scan_report',
        ];

        foreach ( array_unique( $options ) as $opt ) {
            \delete_option( $opt );
        }

        // Truncate relation tables (preserve structure)
        $tables = [ $wpdb->prefix . 'jetsync_relations', $wpdb->prefix . 'jetsync_relation_meta' ];
        foreach ( $tables as $table ) {
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $exists === $table ) {
                $wpdb->query( "TRUNCATE TABLE {$table}" );
            }
        }

        // Also clear any leftover jetsync_options via Installer re-init
        \delete_option( 'jetsync_last_scan_report' );

        \flush_rewrite_rules( false );

        /** @var \JetSync\Core\Logger|null $logger */
        $logger = JetSync::get_instance()->get( 'logger' );
        if ( $logger ) {
            $logger->info( 'Full reset executed by user ' . get_current_user_id() );
        }

        \wp_send_json_success( [
            'message' => \__( 'JetSync a fost resetat complet. Poți porni un nou import de la zero.', 'jet-sync' ),
        ] );
    }
}
