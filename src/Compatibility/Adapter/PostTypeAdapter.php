<?php
declare( strict_types=1 );

namespace JetSync\Compatibility\Adapter;

use JetSync\Registry\Model\PostTypeDefinition;
use JetSync\Registry\Model\DefinitionInterface;

/**
 * Adapter to translate JetEngine CPT definitions to JetSync models.
 */
class PostTypeAdapter implements ImportAdapterInterface {

    /**
     * {@inheritdoc}
     */
    public function translate( array $raw_data ) : ?DefinitionInterface {
        $slug = $raw_data['slug'] ?? '';
        if ( empty( $slug ) ) {
            return null;
        }

        // Parse labels (with top-level fallbacks if empty)
        $labels = $raw_data['labels'] ?? [];
        if ( empty( $labels ) ) {
            $name = $raw_data['name'] ?? ucfirst( $slug );
            $labels = [
                'name'          => $name . 's',
                'singular_name' => $name,
            ];
        }

        $args = $raw_data['args'] ?? [];

        // Normalize CPT supports options (handle list or associative formats)
        $supports = $args['supports'] ?? $raw_data['supports'] ?? [ 'title', 'editor', 'thumbnail' ];
        if ( is_array( $supports ) ) {
            $clean_supports = [];
            foreach ( $supports as $key => $val ) {
                if ( is_string( $key ) && ( true === $val || 'true' === $val || '1' === $val || 1 === $val ) ) {
                    $clean_supports[] = $key;
                } elseif ( is_int( $key ) && is_string( $val ) ) {
                    $clean_supports[] = $val;
                }
            }
            if ( ! empty( $clean_supports ) ) {
                $supports = $clean_supports;
            }
        }

        // Normalize rewrite rules
        $rewrite = $args['rewrite'] ?? $raw_data['rewrite'] ?? [];
        $rewrite_args = [ 'slug' => '', 'with_front' => true ];
        if ( is_array( $rewrite ) ) {
            $rewrite_args['slug'] = $rewrite['slug'] ?? '';
            $rewrite_args['with_front'] = (bool) ( $rewrite['with_front'] ?? true );
        } elseif ( is_string( $rewrite ) ) {
            $rewrite_args['slug'] = $rewrite;
        }

        $menu_icon = $args['menu_icon'] ?? $raw_data['menu_icon'] ?? 'dashicons-admin-post';
        $menu_position = isset( $args['menu_position'] ) ? (int) $args['menu_position'] : null;

        return new PostTypeDefinition(
            slug: $slug,
            labels: $labels,
            public: (bool) ( $args['public'] ?? true ),
            show_ui: (bool) ( $args['show_ui'] ?? true ),
            show_in_menu: (bool) ( $args['show_in_menu'] ?? true ),
            menu_position: $menu_position,
            menu_icon: $menu_icon,
            supports: $supports,
            has_archive: (bool) ( $args['has_archive'] ?? true ),
            rewrite: $rewrite_args,
            show_in_rest: (bool) ( $args['show_in_rest'] ?? true ),
            capabilities: $args['capabilities'] ?? [],
            active: true
        );
    }
}
