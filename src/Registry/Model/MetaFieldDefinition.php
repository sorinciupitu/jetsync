<?php
declare( strict_types=1 );

namespace JetSync\Registry\Model;

/**
 * Model representing an individual custom meta field definition.
 */
class MetaFieldDefinition {

    /**
     * Constructor.
     */
    public function __construct(
        private string $name,
        private string $title,
        private string $type = 'text',
        private string $description = '',
        private bool $required = false,
        private mixed $default_value = '',
        private array $options = []
    ) {
        $this->name = sanitize_key( $name );
    }

    /**
     * Get the field's meta key name.
     */
    public function get_name() : string {
        return $this->name;
    }

    /**
     * Get the field label.
     */
    public function get_title() : string {
        return $this->title;
    }

    /**
     * Get the field input type.
     */
    public function get_type() : string {
        return $this->type;
    }

    /**
     * Get the field description instructions.
     */
    public function get_description() : string {
        return $this->description;
    }

    /**
     * Is the field required?
     */
    public function is_required() : bool {
        return $this->required;
    }

    /**
     * Get default value.
     */
    public function get_default_value() : mixed {
        return $this->default_value;
    }

    /**
     * Get options list (for select, checkbox, radio).
     *
     * @return array<array{value:string,label:string}>
     */
    public function get_options() : array {
        return $this->options;
    }

    /**
     * Serialize to array.
     *
     * @return array<string,mixed>
     */
    public function to_array() : array {
        return [
            'name'          => $this->name,
            'title'         => $this->title,
            'type'          => $this->type,
            'description'   => $this->description,
            'required'      => $this->required,
            'default_value' => $this->default_value,
            'options'       => $this->options,
        ];
    }

    /**
     * Deserialize from array.
     */
    public static function from_array( array $data ) : self {
        return new self(
            name: (string) ( $data['name'] ?? '' ),
            title: (string) ( $data['title'] ?? '' ),
            type: (string) ( $data['type'] ?? 'text' ),
            description: (string) ( $data['description'] ?? '' ),
            required: (bool) ( $data['required'] ?? false ),
            default_value: $data['default_value'] ?? '',
            options: (array) ( $data['options'] ?? [] )
        );
    }
}
