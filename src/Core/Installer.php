<?php
declare( strict_types=1 );

namespace JetSync\Core;

/**
 * Handles plugin activation, deactivation, and verification steps.
 */
class Installer {

    /**
     * Minimum PHP version required by the plugin.
     */
    private const MINIMUM_PHP_VERSION = '8.1.0';

    /**
     * Run activation logic.
     *
     * @return void
     */
    public static function activate() : void {
        // Verify PHP version
        if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
            deactivate_plugins( plugin_basename( JETSYNC_PATH . 'jet-sync.php' ) );
            wp_die(
                sprintf(
                    /* translators: %s: Minimum PHP version required */
                    esc_html__( 'JetSync requires PHP %s or higher. Your current PHP version is %s. The plugin has been deactivated.', 'jet-sync' ),
                    self::MINIMUM_PHP_VERSION,
                    PHP_VERSION
                ),
                esc_html__( 'Plugin Activation Failed', 'jet-sync' ),
                [ 'back_link' => true ]
            );
        }

        // Create Custom Database Tables
        self::create_custom_tables();

        // Initialize default options
        self::initialize_options();

        Capabilities::install();

        // Flush rewrite rules to ensure registered routes work
        flush_rewrite_rules();
    }

    /**
     * Run deactivation logic.
     *
     * @return void
     */
    public static function deactivate() : void {
        // Flush rewrite rules to clean up CPTs and taxonomies
        flush_rewrite_rules();
    }

    /**
     * Creates custom DB tables for relationships.
     *
     * @return void
     */
    public static function create_custom_tables() : void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_relations = $wpdb->prefix . 'jetsync_relations';
        $sql_relations = "CREATE TABLE $table_relations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rel_id varchar(64) NOT NULL,
            parent_id bigint(20) unsigned NOT NULL,
            child_id bigint(20) unsigned NOT NULL,
            parent_type varchar(64) DEFAULT 'post',
            child_type varchar(64) DEFAULT 'post',
            PRIMARY KEY  (id),
            KEY rel_key (rel_id),
            KEY parent_key (parent_id),
            KEY child_key (child_id),
            UNIQUE KEY connection_key (rel_id, parent_id, child_id)
        ) $charset_collate;";

        $table_meta = $wpdb->prefix . 'jetsync_relation_meta';
        $sql_meta = "CREATE TABLE $table_meta (
            meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            connection_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) DEFAULT NULL,
            meta_value longtext,
            PRIMARY KEY  (meta_id),
            KEY connection_id (connection_id),
            KEY meta_key (meta_key(191))
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_relations );
        dbDelta( $sql_meta );
    }

    /**
     * Set up default options if they do not exist.
     *
     * @return void
     */
    private static function initialize_options() : void {
        if ( false === get_option( 'jetsync_installed_version' ) ) {
            update_option( 'jetsync_installed_version', JETSYNC_VERSION );
        }

        if ( false === get_option( 'jetsync_settings' ) ) {
            update_option( 'jetsync_settings', [
                'compatibility_mode' => true,
                'debug_mode'         => false,
            ] );
        }
    }
}
