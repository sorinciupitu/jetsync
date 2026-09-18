<?php
declare( strict_types=1 );

namespace JetSync\Runtime;

use JetSync\Registry\PostTypeRegistry;
use JetSync\Registry\TaxonomyRegistry;
use JetSync\Registry\Model\PostTypeDefinition;
use JetSync\Registry\Model\TaxonomyDefinition;
use JetSync\Core\Logger;
use JetSync\Core\Capabilities;

/**
 * Handles WordPress runtime registration of post types and taxonomies.
 */
class RegistrationManager {
    private bool $caps_installed = false;

    /**
     * Constructor. Set up registries and hook into WordPress.
     *
     * @param PostTypeRegistry $cpt_registry Registry for CPTs.
     * @param TaxonomyRegistry $taxonomy_registry Registry for Taxonomies.
     * @param Logger|null $logger File logger instance.
     */
    public function __construct(
        private PostTypeRegistry $cpt_registry,
        private TaxonomyRegistry $taxonomy_registry,
        private ?Logger $logger = null
    ) {
        \add_action( 'init', [ $this, 'ensure_admin_caps' ], 99 );
        \add_action( 'admin_init', [ $this, 'ensure_admin_caps' ], 1 );
        \add_action( 'admin_init', [ $this, 'force_standard_caps_for_managed_post_types' ], 2 );
        \add_action( 'init', [ $this, 'register_structures' ], 11 );
        \add_action( 'registered_post_type', [ $this, 'maybe_patch_runtime_post_type_caps' ], 100, 2 );
        \add_filter( 'map_meta_cap', [ $this, 'map_meta_caps_for_managed_post_types' ], 1, 4 );
    }

    /**
     * Callback for 'init' action. Registers taxonomies first, then CPTs.
     *
     * @return void
     */
    public function register_structures() : void {
        $this->register_taxonomies();
        $this->register_post_types();
        $this->ensure_admin_caps();
    }

    public function ensure_admin_caps() : void {
        if ( $this->caps_installed ) {
            return;
        }

        if ( ! \function_exists( 'get_role' ) ) {
            return;
        }

        $role = \get_role( 'administrator' );
        if ( ! $role ) {
            return;
        }

        $did_add = false;

        $cpts = $this->cpt_registry->get_all();
        foreach ( $cpts as $cpt ) {
            if ( ! $cpt->is_active() ) {
                continue;
            }
            $args = $cpt->get_args();
            if ( empty( $args['capabilities'] ) || ! is_array( $args['capabilities'] ) ) {
                continue;
            }

            foreach ( $args['capabilities'] as $cap ) {
                if ( is_string( $cap ) && '' !== $cap ) {
                    if ( ! $role->has_cap( $cap ) ) {
                        $role->add_cap( $cap );
                        $did_add = true;
                    }
                }
            }
        }

        $runtime = \get_post_types( [ '_builtin' => false ], 'objects' );
        if ( is_array( $runtime ) ) {
            foreach ( $runtime as $obj ) {
                if ( ! is_object( $obj ) || ! isset( $obj->cap ) || ! is_object( $obj->cap ) ) {
                    continue;
                }
                foreach ( get_object_vars( $obj->cap ) as $cap ) {
                    if ( is_string( $cap ) && '' !== $cap ) {
                        if ( ! $role->has_cap( $cap ) ) {
                            $role->add_cap( $cap );
                            $did_add = true;
                        }
                    }
                }
            }
        }

        if ( is_array( $runtime ) && ! empty( $runtime ) ) {
            $this->caps_installed = true;
        }

        if ( $did_add ) {
            $this->caps_installed = true;
            if ( \function_exists( 'wp_get_current_user' ) ) {
                \wp_get_current_user()->get_role_caps();
            }
        }
    }

    /**
     * Registers all active Custom Post Types.
     *
     * @return void
     */
    private function register_post_types() : void {
        $cpts = $this->cpt_registry->get_all();

        foreach ( $cpts as $cpt ) {
            if ( ! $cpt instanceof PostTypeDefinition ) {
                continue;
            }

            if ( ! $cpt->is_active() ) {
                continue;
            }

            $slug = $cpt->get_slug();

            if ( \post_type_exists( $slug ) ) {
                $this->patch_existing_post_type( $slug, $cpt->get_args() );
                continue;
            }

            $args = $cpt->get_args();
            if ( ! \class_exists( 'Jet_Engine' ) && isset( $args['capabilities'] ) ) {
                unset( $args['capabilities'] );
                $args['capability_type'] = 'post';
                $args['map_meta_cap'] = true;
            }
            $result = \register_post_type( $slug, $args );

            if ( \is_wp_error( $result ) ) {
                if ( $this->logger ) {
                    $this->logger->error(
                        sprintf( 'Failed to register post type "%s": %s', $slug, $result->get_error_message() )
                    );
                }
            }
        }
    }

    public function maybe_patch_runtime_post_type_caps( string $post_type, $args ) : void {
        if ( \class_exists( 'Jet_Engine' ) ) {
            return;
        }

        if ( ! $this->is_managed_post_type( $post_type ) ) {
            return;
        }

        global $wp_post_types;
        if ( ! is_array( $wp_post_types ) || ! isset( $wp_post_types[ $post_type ] ) ) {
            return;
        }

        $post_obj = $wp_post_types[ $post_type ];
        $base = \get_post_type_object( 'post' );
        if ( ! $base || ! isset( $base->cap ) ) {
            return;
        }

        $post_obj->capability_type = 'post';
        $post_obj->cap = $base->cap;
        $post_obj->map_meta_cap = true;
        $wp_post_types[ $post_type ] = $post_obj;

        if ( \function_exists( 'wp_get_current_user' ) ) {
            \wp_get_current_user()->get_role_caps();
        }
    }

    public function force_standard_caps_for_managed_post_types() : void {
        if ( \class_exists( 'Jet_Engine' ) ) {
            return;
        }

        global $wp_post_types;
        if ( ! is_array( $wp_post_types ) ) {
            return;
        }

        $base = \get_post_type_object( 'post' );
        if ( ! $base || ! isset( $base->cap ) ) {
            return;
        }

        $cpts = $this->cpt_registry->get_all();
        foreach ( $cpts as $cpt ) {
            if ( ! $cpt instanceof PostTypeDefinition ) {
                continue;
            }

            if ( ! $cpt->is_active() ) {
                continue;
            }

            $slug = $cpt->get_slug();
            if ( '' === $slug || ! isset( $wp_post_types[ $slug ] ) ) {
                continue;
            }

            $obj = $wp_post_types[ $slug ];
            $obj->capability_type = 'post';
            $obj->cap = $base->cap;
            $obj->map_meta_cap = true;
            $wp_post_types[ $slug ] = $obj;
        }

        if ( \function_exists( 'wp_get_current_user' ) ) {
            \wp_get_current_user()->get_role_caps();
        }
    }

    public function map_meta_caps_for_managed_post_types( array $caps, string $cap, int $user_id, array $args ) : array {
        if ( \class_exists( 'Jet_Engine' ) ) {
            return $caps;
        }

        if ( empty( $args[0] ) ) {
            return $caps;
        }

        $post_id = (int) $args[0];
        if ( $post_id <= 0 ) {
            return $caps;
        }

        $post_type = \get_post_type( $post_id );
        if ( ! is_string( $post_type ) || '' === $post_type ) {
            return $caps;
        }

        if ( ! $this->is_managed_post_type( $post_type ) ) {
            return $caps;
        }

        if ( ! \user_can( $user_id, Capabilities::MANAGE ) ) {
            return $caps;
        }

        if ( in_array( $cap, [ 'edit_post', 'delete_post', 'read_post' ], true ) ) {
            return [ Capabilities::MANAGE ];
        }

        return $caps;
    }

    private function is_managed_post_type( string $post_type ) : bool {
        $post_type = \sanitize_key( $post_type );
        if ( '' === $post_type ) {
            return false;
        }

        $cpts = $this->cpt_registry->get_all();
        foreach ( $cpts as $cpt ) {
            if ( ! $cpt instanceof PostTypeDefinition ) {
                continue;
            }

            if ( $cpt->is_active() && $cpt->get_slug() === $post_type ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Registers all active Custom Taxonomies.
     *
     * @return void
     */
    private function register_taxonomies() : void {
        $taxonomies = $this->taxonomy_registry->get_all();

        foreach ( $taxonomies as $taxonomy ) {
            if ( ! $taxonomy instanceof TaxonomyDefinition ) {
                continue;
            }

            if ( ! $taxonomy->is_active() ) {
                continue;
            }

            $slug = $taxonomy->get_slug();

            if ( \taxonomy_exists( $slug ) ) {
                $this->patch_existing_taxonomy( $slug, $taxonomy->get_object_types(), $taxonomy->get_args() );
                continue;
            }

            $args = $taxonomy->get_args();
            $object_types = $taxonomy->get_object_types();

            $result = \register_taxonomy( $slug, $object_types, $args );

            if ( \is_wp_error( $result ) ) {
                if ( $this->logger ) {
                    $this->logger->error(
                        sprintf( 'Failed to register taxonomy "%s": %s', $slug, $result->get_error_message() )
                    );
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $args
     */
    private function patch_existing_post_type( string $slug, array $args ) : void {
        global $wp_post_types;

        if ( ! is_array( $wp_post_types ) || ! isset( $wp_post_types[ $slug ] ) || ! is_object( $wp_post_types[ $slug ] ) ) {
            return;
        }

        $post_type = $wp_post_types[ $slug ];

        if ( isset( $args['labels'] ) && is_array( $args['labels'] ) ) {
            $post_type->labels = (object) array_merge( (array) ( $post_type->labels ?? [] ), $args['labels'] );
            if ( isset( $args['labels']['name'] ) ) {
                $post_type->label = (string) $args['labels']['name'];
            }
        }

        foreach ( [ 'public', 'publicly_queryable', 'show_ui', 'show_in_menu', 'has_archive', 'show_in_rest', 'query_var', 'menu_icon', 'menu_position', 'rewrite', 'supports' ] as $key ) {
            if ( array_key_exists( $key, $args ) ) {
                $post_type->{$key} = $args[ $key ];
            }
        }

        if ( ! empty( $args['supports'] ) && is_array( $args['supports'] ) ) {
            foreach ( array_keys( \get_all_post_type_supports( $slug ) ) as $feature ) {
                \remove_post_type_support( $slug, (string) $feature );
            }

            foreach ( $args['supports'] as $feature ) {
                if ( is_string( $feature ) && '' !== $feature ) {
                    \add_post_type_support( $slug, $feature );
                }
            }
        }

        $wp_post_types[ $slug ] = $post_type;

        if ( $this->logger ) {
            $this->logger->info( sprintf( 'Patched existing post type "%s" from JetSync registry.', $slug ) );
        }
    }

    /**
     * @param array<string>      $object_types
     * @param array<string,mixed> $args
     */
    private function patch_existing_taxonomy( string $slug, array $object_types, array $args ) : void {
        global $wp_taxonomies;

        if ( ! is_array( $wp_taxonomies ) || ! isset( $wp_taxonomies[ $slug ] ) || ! is_object( $wp_taxonomies[ $slug ] ) ) {
            return;
        }

        $taxonomy = $wp_taxonomies[ $slug ];

        if ( isset( $args['labels'] ) && is_array( $args['labels'] ) ) {
            $taxonomy->labels = (object) array_merge( (array) ( $taxonomy->labels ?? [] ), $args['labels'] );
            if ( isset( $args['labels']['name'] ) ) {
                $taxonomy->label = (string) $args['labels']['name'];
            }
        }

        foreach ( [ 'public', 'show_ui', 'show_admin_column', 'show_in_rest', 'query_var', 'rewrite', 'hierarchical' ] as $key ) {
            if ( array_key_exists( $key, $args ) ) {
                $taxonomy->{$key} = $args[ $key ];
            }
        }

        if ( ! empty( $object_types ) ) {
            $taxonomy->object_type = array_values( array_unique( array_map( 'sanitize_key', $object_types ) ) );
            foreach ( $taxonomy->object_type as $object_type ) {
                if ( '' !== $object_type ) {
                    \register_taxonomy_for_object_type( $slug, $object_type );
                }
            }
        }

        $wp_taxonomies[ $slug ] = $taxonomy;

        if ( $this->logger ) {
            $this->logger->info( sprintf( 'Patched existing taxonomy "%s" from JetSync registry.', $slug ) );
        }
    }
}
