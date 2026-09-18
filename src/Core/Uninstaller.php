<?php
declare( strict_types=1 );

namespace JetSync\Core;

class Uninstaller {

    public static function uninstall() : void {
        self::drop_tables();
        self::delete_options();
        Capabilities::remove();
    }

    private static function drop_tables() : void {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'jetsync_relations',
            $wpdb->prefix . 'jetsync_relation_meta',
        ];

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS $table" );
        }
    }

    private static function delete_options() : void {
        $options = [
            'jetsync_cpt_registry',
            'jetsync_taxonomy_registry',
            'jetsync_meta_boxes',
            'jetsync_relations',
            'jetsync_listings',
            'jetsync_queries',
            'jetsync_settings',
            'jetsync_installed_version',
            'jetsync_migration_plan',
            'jetsync_last_integrity_report',
            'jetsync_last_repair_report',
        ];

        foreach ( $options as $opt ) {
            delete_option( $opt );
        }
    }
}
