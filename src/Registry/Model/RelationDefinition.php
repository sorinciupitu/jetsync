<?php
declare( strict_types=1 );

namespace JetSync\Registry\Model;

/**
 * Model representing a Custom Object Relationship definition.
 */
class RelationDefinition implements DefinitionInterface {

    /**
     * Constructor.
     */
    public function __construct(
        private string $id,
        private string $title,
        private string $parent_object = 'post',
        private string $child_object = 'post',
        private string $type = 'many_to_many',
        private bool $active = true
    ) {
        $this->id = sanitize_key( $id );
    }

    /**
     * {@inheritdoc}
     */
    public function get_slug() : string {
        return $this->id;
    }

    /**
     * Get the relationship ID.
     */
    public function get_id() : string {
        return $this->id;
    }

    /**
     * Get the relationship Title.
     */
    public function get_title() : string {
        return $this->title;
    }

    /**
     * Get parent object type (e.g. CPT name, 'user', or taxonomy name).
     */
    public function get_parent_object() : string {
        return $this->parent_object;
    }

    /**
     * Get child object type.
     */
    public function get_child_object() : string {
        return $this->child_object;
    }

    /**
     * Get relationship cardinality type (one_to_one, one_to_many, many_to_many).
     */
    public function get_type() : string {
        return in_array( $this->type, [ 'one_to_one', 'one_to_many', 'many_to_many' ], true ) ? $this->type : 'many_to_many';
    }

    /**
     * {@inheritdoc}
     */
    public function is_active() : bool {
        return $this->active;
    }

    /**
     * Get arguments representation.
     *
     * @return array<string,mixed>
     */
    public function get_args() : array {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'parent_object' => $this->parent_object,
            'child_object'  => $this->child_object,
            'type'          => $this->type,
        ];
    }

    /**
     * Serialize to array.
     *
     * @return array<string,mixed>
     */
    public function to_array() : array {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'parent_object' => $this->parent_object,
            'child_object'  => $this->child_object,
            'type'          => $this->type,
            'active'        => $this->active,
        ];
    }

    /**
     * Deserialize from array.
     */
    public static function from_array( array $data ) : self {
        return new self(
            id: (string) ( $data['id'] ?? '' ),
            title: (string) ( $data['title'] ?? '' ),
            parent_object: (string) ( $data['parent_object'] ?? 'post' ),
            child_object: (string) ( $data['child_object'] ?? 'post' ),
            type: (string) ( $data['type'] ?? 'many_to_many' ),
            active: (bool) ( $data['active'] ?? true )
        );
    }
}
