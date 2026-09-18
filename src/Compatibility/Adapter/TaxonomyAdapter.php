<?php
declare( strict_types=1 );

namespace JetSync\Compatibility\Adapter;

use JetSync\Registry\Model\TaxonomyDefinition;
use JetSync\Registry\Model\DefinitionInterface;

/**
 * Adapter to translate JetEngine Custom Taxonomy definitions to JetSync models.
 */
class TaxonomyAdapter implements ImportAdapterInterface {

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
        $object_types = $raw_data['object_type'] ?? $raw_data['object_types'] ?? [];
        if ( is_string( $object_types ) ) {
            $object_types = [ $object_types ];
        }

        // Normalize rewrite rules
        $rewrite = $args['rewrite'] ?? $raw_data['rewrite'] ?? [];
        $rewrite_args = [ 'slug' => '', 'with_front' => true, 'hierarchical' => false ];
        if ( is_array( $rewrite ) ) {
            $rewrite_args['slug'] = $rewrite['slug'] ?? '';
            $rewrite_args['with_front'] = (bool) ( $rewrite['with_front'] ?? true );
            $rewrite_args['hierarchical'] = (bool) ( $rewrite['hierarchical'] ?? false );
        }

        return new TaxonomyDefinition(
            slug: $slug,
            object_types: $object_types,
            labels: $labels,
            hierarchical: (bool) ( $args['hierarchical'] ?? true ),
            public: (bool) ( $args['public'] ?? true ),
            show_ui: (bool) ( $args['show_ui'] ?? true ),
            show_admin_column: (bool) ( $args['show_admin_column'] ?? true ),
            show_in_rest: (bool) ( $args['show_in_rest'] ?? true ),
            rewrite: $rewrite_args,
            active: true
        );
    }
}
