<?php
declare( strict_types=1 );

namespace JetSync\Runtime;

use JetSync\Core\Logger;

/**
 * PHP API Engine to query and manage relationship links in custom DB tables.
 */
class RelationsEngine {

    /**
     * Cache table name for relations.
     *
     * @var string
     */
    private string $table_relations;

    /**
     * Cache table name for relation meta.
     *
     * @var string
     */
    private string $table_meta;

    /**
     * Constructor. Resolves table names.
     *
     * @param Logger|null $logger Logger instance.
     */
    public function __construct( private ?Logger $logger = null ) {
        global $wpdb;
        $this->table_relations = $wpdb->prefix . 'jetsync_relations';
        $this->table_meta      = $wpdb->prefix . 'jetsync_relation_meta';
    }

    /**
     * Fetch all related object IDs.
     *
     * @param string $rel_id The relationship identifier key.
     * @param int $object_id The source object ID to filter.
     * @param string $role The role of the source object ('parent' or 'child').
     * @return array<int> Array of linked object IDs.
     */
    public function get_related_items( string $rel_id, int $object_id, string $role = 'parent' ) : array {
        global $wpdb;

        if ( 'child' === $role ) {
            $select_col = 'parent_id';
            $where_col  = 'child_id';
        } else {
            $select_col = 'child_id';
            $where_col  = 'parent_id';
        }

        $query = $wpdb->prepare(
            "SELECT $select_col FROM {$this->table_relations} WHERE rel_id = %s AND $where_col = %d",
            $rel_id,
            $object_id
        );

        $results = $wpdb->get_col( $query );

        return array_map( 'intval', $results );
    }

    /**
     * Establish a new connection linkage between a parent and child.
     *
     * @param string $rel_id The relationship identifier key.
     * @param int $parent_id The parent object ID.
     * @param int $child_id The child object ID.
     * @param string $parent_type
     * @param string $child_type
     * @return int|bool Connection ID insert index, or false on failure/duplicate.
     */
    public function add_connection(
        string $rel_id,
        int $parent_id,
        int $child_id,
        string $parent_type = 'post',
        string $child_type = 'post'
    ) : int|bool {
        global $wpdb;

        $inserted = $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$this->table_relations} 
                 (rel_id, parent_id, child_id, parent_type, child_type) 
                 VALUES (%s, %d, %d, %s, %s)",
                $rel_id,
                $parent_id,
                $child_id,
                $parent_type,
                $child_type
            )
        );

        if ( $inserted ) {
            return (int) $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Deletes a specific connection linkage.
     *
     * @param string $rel_id The relationship identifier key.
     * @param int $parent_id The parent object ID.
     * @param int $child_id The child object ID.
     * @return bool True if deleted successfully, false otherwise.
     */
    public function delete_connection( string $rel_id, int $parent_id, int $child_id ) : bool {
        global $wpdb;

        $deleted = $wpdb->delete(
            $this->table_relations,
            [
                'rel_id'    => $rel_id,
                'parent_id' => $parent_id,
                'child_id'  => $child_id,
            ],
            [ '%s', '%d', '%d' ]
        );

        return false !== $deleted;
    }

    /**
     * Overwrites connection lists for a specific parent item.
     *
     * @param string $rel_id The relationship identifier key.
     * @param int $parent_id The parent object ID.
     * @param array<int> $child_ids New array of child object IDs.
     * @return void
     */
    public function set_related_items( string $rel_id, int $parent_id, array $child_ids ) : void {
        $this->set_related_items_typed( $rel_id, $parent_id, $child_ids, 'post', 'post' );
    }

    public function set_related_items_typed(
        string $rel_id,
        int $parent_id,
        array $child_ids,
        string $parent_type,
        string $child_type
    ) : void {
        global $wpdb;

        $wpdb->delete(
            $this->table_relations,
            [
                'rel_id'    => $rel_id,
                'parent_id' => $parent_id,
            ],
            [ '%s', '%d' ]
        );

        foreach ( $child_ids as $child_id ) {
            $child_id = (int) $child_id;
            if ( $child_id <= 0 ) {
                continue;
            }
            $this->add_connection( $rel_id, $parent_id, $child_id, $parent_type, $child_type );
        }
    }

    public function set_parent_items_typed(
        string $rel_id,
        int $child_id,
        array $parent_ids,
        string $parent_type,
        string $child_type
    ) : void {
        global $wpdb;

        $wpdb->delete(
            $this->table_relations,
            [
                'rel_id'   => $rel_id,
                'child_id' => $child_id,
            ],
            [ '%s', '%d' ]
        );

        foreach ( $parent_ids as $parent_id ) {
            $parent_id = (int) $parent_id;
            if ( $parent_id <= 0 ) {
                continue;
            }
            $this->add_connection( $rel_id, $parent_id, $child_id, $parent_type, $child_type );
        }
    }

    /**
     * Fetch connection metadata.
     *
     * @param int $connection_id The unique connection index ID.
     * @param string $meta_key The metadata key name.
     * @param bool $single Return a single value or an array.
     * @return mixed Meta values.
     */
    public function get_connection_meta( int $connection_id, string $meta_key, bool $single = true ) : mixed {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT meta_value FROM {$this->table_meta} WHERE connection_id = %d AND meta_key = %s",
            $connection_id,
            $meta_key
        );

        if ( $single ) {
            $val = $wpdb->get_var( $query );
            return maybe_unserialize( $val );
        }

        $results = $wpdb->get_col( $query );
        return array_map( 'maybe_unserialize', $results );
    }

    /**
     * Add or update connection metadata.
     *
     * @param int $connection_id The unique connection index ID.
     * @param string $meta_key The metadata key name.
     * @param mixed $meta_value The metadata value payload.
     * @return bool True on success, false on failure.
     */
    public function update_connection_meta( int $connection_id, string $meta_key, mixed $meta_value ) : bool {
        global $wpdb;

        $serialized_val = maybe_serialize( $meta_value );

        // Check if exists
        $meta_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_id FROM {$this->table_meta} WHERE connection_id = %d AND meta_key = %s",
                $connection_id,
                $meta_key
            )
        );

        if ( $meta_id ) {
            $updated = $wpdb->update(
                $this->table_meta,
                [ 'meta_value' => $serialized_val ],
                [ 'meta_id' => $meta_id ],
                [ '%s' ],
                [ '%d' ]
            );
            return false !== $updated;
        }

        $inserted = $wpdb->insert(
            $this->table_meta,
            [
                'connection_id' => $connection_id,
                'meta_key'      => $meta_key,
                'meta_value'    => $serialized_val,
            ],
            [ '%d', '%s', '%s' ]
        );

        return false !== $inserted;
    }
}
