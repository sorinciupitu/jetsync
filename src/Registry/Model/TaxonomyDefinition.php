<?php
declare( strict_types=1 );

namespace JetSync\Registry\Model;

/**
 * Model representing a Custom Taxonomy definition.
 */
class TaxonomyDefinition implements DefinitionInterface {

    /**
     * Constructor.
     */
    public function __construct(
        private string $slug,
        private array $object_types = [],
        private array $labels = [],
        private bool $hierarchical = true,
        private bool $public = true,
        private bool $show_ui = true,
        private bool $show_admin_column = true,
        private bool $show_in_rest = true,
        private array $rewrite = [ 'slug' => '', 'with_front' => true, 'hierarchical' => false ],
        private bool $active = true
    ) {
        $this->slug = sanitize_key( $slug );
    }

    /**
     * {@inheritdoc}
     */
    public function get_slug() : string {
        return $this->slug;
    }

    /**
     * Get associated post type slugs.
     *
     * @return array<string>
     */
    public function get_object_types() : array {
        return array_map( 'sanitize_key', $this->object_types );
    }

    /**
     * Check if active.
     */
    public function is_active() : bool {
        return $this->active;
    }

    /**
     * Get label values.
     *
     * @return array<string,string>
     */
    public function get_labels() : array {
        $defaults = [
            'name'          => ucfirst( $this->slug ) . 's',
            'singular_name' => ucfirst( $this->slug ),
            'menu_name'     => ucfirst( $this->slug ) . 's',
        ];
        return array_merge( $defaults, $this->labels );
    }

    /**
     * Return formatted args for register_taxonomy().
     *
     * @return array<string,mixed>
     */
    public function get_args() : array {
        $labels = $this->get_labels();

        $args = [
            'labels'            => $labels,
            'hierarchical'      => $this->hierarchical,
            'public'            => $this->public,
            'show_ui'           => $this->show_ui,
            'show_admin_column' => $this->show_admin_column,
            'show_in_rest'      => $this->show_in_rest,
            'query_var'         => true,
        ];

        // Handle rewrite slug mapping
        $rewrite_slug = ! empty( $this->rewrite['slug'] ) ? $this->rewrite['slug'] : $this->slug;
        $args['rewrite'] = [
            'slug'         => sanitize_title( $rewrite_slug ),
            'with_front'   => (bool) ( $this->rewrite['with_front'] ?? true ),
            'hierarchical' => (bool) ( $this->rewrite['hierarchical'] ?? false ),
        ];

        return $args;
    }

    /**
     * Serialize to array.
     *
     * @return array<string,mixed>
     */
    public function to_array() : array {
        return [
            'slug'              => $this->slug,
            'object_types'      => $this->object_types,
            'labels'            => $this->labels,
            'hierarchical'      => $this->hierarchical,
            'public'            => $this->public,
            'show_ui'           => $this->show_ui,
            'show_admin_column' => $this->show_admin_column,
            'show_in_rest'      => $this->show_in_rest,
            'rewrite'           => $this->rewrite,
            'active'            => $this->active,
        ];
    }

    /**
     * Deserialize from array.
     */
    public static function from_array( array $data ) : self {
        return new self(
            slug: (string) ( $data['slug'] ?? '' ),
            object_types: (array) ( $data['object_types'] ?? [] ),
            labels: (array) ( $data['labels'] ?? [] ),
            hierarchical: (bool) ( $data['hierarchical'] ?? true ),
            public: (bool) ( $data['public'] ?? true ),
            show_ui: (bool) ( $data['show_ui'] ?? true ),
            show_admin_column: (bool) ( $data['show_admin_column'] ?? true ),
            show_in_rest: (bool) ( $data['show_in_rest'] ?? true ),
            rewrite: (array) ( $data['rewrite'] ?? [ 'slug' => '', 'with_front' => true, 'hierarchical' => false ] ),
            active: (bool) ( $data['active'] ?? true )
        );
    }
}
