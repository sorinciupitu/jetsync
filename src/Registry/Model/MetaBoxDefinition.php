<?php
declare( strict_types=1 );

namespace JetSync\Registry\Model;

/**
 * Model representing a Custom Meta Box configuration.
 */
class MetaBoxDefinition implements DefinitionInterface {

    /**
     * Cache of field objects.
     *
     * @var array<MetaFieldDefinition>
     */
    private array $field_objects = [];

    /**
     * Constructor.
     */
    public function __construct(
        private string $id,
        private string $title,
        private array $object_types = [],
        private string $context = 'normal',
        private string $priority = 'default',
        private array $fields = [],
        private bool $active = true
    ) {
        $this->id = \sanitize_key( $id );
        
        // Build field objects
        foreach ( $this->fields as $field ) {
            if ( $field instanceof MetaFieldDefinition ) {
                $this->field_objects[] = $field;
            } elseif ( is_array( $field ) ) {
                $this->field_objects[] = MetaFieldDefinition::from_array( $field );
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get_slug() : string {
        return $this->id;
    }

    /**
     * Get unique id.
     */
    public function get_id() : string {
        return $this->id;
    }

    /**
     * Get title.
     */
    public function get_title() : string {
        return $this->title;
    }

    /**
     * Get targeted post types.
     *
     * @return array<string>
     */
    public function get_object_types() : array {
        $out = [];
        foreach ( $this->object_types as $t ) {
            if ( is_string( $t ) ) {
                $t = \sanitize_key( $t );
            } else {
                $t = '';
            }
            if ( '' === $t ) {
                continue;
            }
            $out[ $t ] = true;
        }

        return array_keys( $out );
    }

    /**
     * Get panel rendering context.
     */
    public function get_context() : string {
        return in_array( $this->context, [ 'normal', 'advanced', 'side' ], true ) ? $this->context : 'normal';
    }

    /**
     * Get rendering priority.
     */
    public function get_priority() : string {
        return in_array( $this->priority, [ 'high', 'core', 'default', 'low' ], true ) ? $this->priority : 'default';
    }

    /**
     * Get meta field objects.
     *
     * @return array<MetaFieldDefinition>
     */
    public function get_fields() : array {
        return $this->field_objects;
    }

    /**
     * {@inheritdoc}
     */
    public function is_active() : bool {
        return $this->active;
    }

    /**
     * Return formatted args for registration if needed.
     *
     * @return array<string,mixed>
     */
    public function get_args() : array {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'object_types' => $this->get_object_types(),
            'context'      => $this->get_context(),
            'priority'     => $this->get_priority(),
        ];
    }

    /**
     * Serialize to array.
     *
     * @return array<string,mixed>
     */
    public function to_array() : array {
        $serialized_fields = [];
        foreach ( $this->field_objects as $field ) {
            $serialized_fields[] = $field->to_array();
        }

        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'object_types' => $this->object_types,
            'context'      => $this->context,
            'priority'     => $this->priority,
            'fields'       => $serialized_fields,
            'active'       => $this->active,
        ];
    }

    /**
     * Deserialize from array.
     */
    public static function from_array( array $data ) : self {
        return new self(
            id: (string) ( $data['id'] ?? '' ),
            title: (string) ( $data['title'] ?? '' ),
            object_types: (array) ( $data['object_types'] ?? [] ),
            context: (string) ( $data['context'] ?? 'normal' ),
            priority: (string) ( $data['priority'] ?? 'default' ),
            fields: (array) ( $data['fields'] ?? [] ),
            active: (bool) ( $data['active'] ?? true )
        );
    }
}
