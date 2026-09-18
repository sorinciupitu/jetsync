<?php
declare( strict_types=1 );

namespace JetSync\Registry\Model;

/**
 * Model representing a Custom Post Type definition.
 */
class PostTypeDefinition implements DefinitionInterface {

    /**
     * Constructor.
     */
    public function __construct(
        private string $slug,
        private array $labels = [],
        private bool $public = true,
        private bool $show_ui = true,
        private bool $show_in_menu = true,
        private ?int $menu_position = null,
        private string $menu_icon = 'dashicons-admin-post',
        private array $supports = [ 'title', 'editor', 'thumbnail' ],
        private bool $has_archive = true,
        private array $rewrite = [ 'slug' => '', 'with_front' => true ],
        private bool $show_in_rest = true,
        private array $capabilities = [],
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
     * Return formatted args for register_post_type().
     *
     * @return array<string,mixed>
     */
    public function get_args() : array {
        $labels = $this->get_labels();
        
        $args = [
            'labels'             => $labels,
            'public'             => $this->public,
            'publicly_queryable' => $this->public,
            'show_ui'            => $this->show_ui,
            'show_in_menu'       => $this->show_in_menu,
            'query_var'          => true,
            'capability_type'    => 'post',
            'has_archive'        => $this->has_archive,
            'hierarchical'       => false,
            'supports'           => $this->supports,
            'show_in_rest'       => $this->show_in_rest,
        ];

        if ( ! empty( $this->menu_icon ) ) {
            $args['menu_icon'] = $this->menu_icon;
        }

        if ( null !== $this->menu_position ) {
            $args['menu_position'] = $this->menu_position;
        }

        // Handle rewrite slug mapping
        $rewrite_slug = ! empty( $this->rewrite['slug'] ) ? $this->rewrite['slug'] : $this->slug;
        $args['rewrite'] = [
            'slug'       => sanitize_title( $rewrite_slug ),
            'with_front' => (bool) ( $this->rewrite['with_front'] ?? true ),
        ];

        if ( ! empty( $this->capabilities ) ) {
            $args['capabilities'] = $this->capabilities;
        }

        return $args;
    }

    /**
     * Serialize to array.
     *
     * @return array<string,mixed>
     */
    public function to_array() : array {
        return [
            'slug'          => $this->slug,
            'labels'        => $this->labels,
            'public'        => $this->public,
            'show_ui'       => $this->show_ui,
            'show_in_menu'  => $this->show_in_menu,
            'menu_position' => $this->menu_position,
            'menu_icon'     => $this->menu_icon,
            'supports'      => $this->supports,
            'has_archive'   => $this->has_archive,
            'rewrite'       => $this->rewrite,
            'show_in_rest'  => $this->show_in_rest,
            'capabilities'  => $this->capabilities,
            'active'        => $this->active,
        ];
    }

    /**
     * Deserialize from array.
     */
    public static function from_array( array $data ) : self {
        return new self(
            slug: (string) ( $data['slug'] ?? '' ),
            labels: (array) ( $data['labels'] ?? [] ),
            public: (bool) ( $data['public'] ?? true ),
            show_ui: (bool) ( $data['show_ui'] ?? true ),
            show_in_menu: (bool) ( $data['show_in_menu'] ?? true ),
            menu_position: isset( $data['menu_position'] ) ? (int) $data['menu_position'] : null,
            menu_icon: (string) ( $data['menu_icon'] ?? 'dashicons-admin-post' ),
            supports: (array) ( $data['supports'] ?? [ 'title', 'editor', 'thumbnail' ] ),
            has_archive: (bool) ( $data['has_archive'] ?? true ),
            rewrite: (array) ( $data['rewrite'] ?? [ 'slug' => '', 'with_front' => true ] ),
            show_in_rest: (bool) ( $data['show_in_rest'] ?? true ),
            capabilities: (array) ( $data['capabilities'] ?? [] ),
            active: (bool) ( $data['active'] ?? true )
        );
    }
}
