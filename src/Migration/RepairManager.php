<?php
declare( strict_types=1 );

namespace JetSync\Migration;

use JetSync\Core\Installer;
use JetSync\Core\JetSync;

class RepairManager {

	private const OPTION_LAST_REPAIR = 'jetsync_last_repair_report';

	public static function get_last_report() : ?array {
		$report = get_option( self::OPTION_LAST_REPAIR );
		return is_array( $report ) ? $report : null;
	}

	public function repair( bool $cleanup_orphan_connections = false, bool $cleanup_orphan_meta = false ) : array {
		global $wpdb;

		Installer::create_custom_tables();

		$table_relations = $wpdb->prefix . 'jetsync_relations';
		$table_meta      = $wpdb->prefix . 'jetsync_relation_meta';

		$deleted_connections = 0;
		$deleted_meta        = 0;

		if ( $cleanup_orphan_connections ) {
			$plugin = JetSync::get_instance();
			/** @var \JetSync\Registry\RelationRegistry|null $relation_registry */
			$relation_registry = $plugin->get( 'relation_registry' );
			$known_rel_ids     = $relation_registry ? array_keys( $relation_registry->get_all() ) : [];

			if ( empty( $known_rel_ids ) ) {
				$deleted_connections = (int) $wpdb->query( "DELETE FROM {$table_relations}" );
			} else {
				$placeholders = implode( ',', array_fill( 0, count( $known_rel_ids ), '%s' ) );
				$sql          = "DELETE FROM {$table_relations} WHERE rel_id NOT IN ({$placeholders})";
				$deleted_connections = (int) $wpdb->query( $wpdb->prepare( $sql, ...$known_rel_ids ) );
			}
		}

		if ( $cleanup_orphan_meta ) {
			$deleted_meta = (int) $wpdb->query(
				"DELETE m FROM {$table_meta} m LEFT JOIN {$table_relations} r ON m.connection_id = r.id WHERE r.id IS NULL"
			);
		}

		$report = [
			'timestamp'           => time(),
			'repaired_tables'     => true,
			'cleanup_connections' => $cleanup_orphan_connections,
			'cleanup_meta'        => $cleanup_orphan_meta,
			'deleted'             => [
				'connections' => $deleted_connections,
				'meta'        => $deleted_meta,
			],
		];

		update_option( self::OPTION_LAST_REPAIR, $report );

		return $report;
	}
}

