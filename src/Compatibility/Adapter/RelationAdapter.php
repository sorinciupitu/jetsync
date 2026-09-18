<?php
declare( strict_types=1 );

namespace JetSync\Compatibility\Adapter;

use JetSync\Registry\Model\RelationDefinition;
use JetSync\Registry\Model\DefinitionInterface;

/**
 * Adapter to translate JetEngine Relationship definitions to JetSync models.
 */
class RelationAdapter implements ImportAdapterInterface {

    /**
     * {@inheritdoc}
     */
    public function translate( array $raw_data ) : ?DefinitionInterface {
        $id = isset( $raw_data['id'] ) ? (string) $raw_data['id'] : '';
        $args = $raw_data['args'] ?? [];

        $title = $args['name'] ?? $args['title'] ?? $raw_data['title'] ?? $raw_data['name'] ?? '';
        if ( empty( $id ) || empty( $title ) ) {
            return null;
        }

        $parent = $args['parent_object'] ?? $raw_data['parent_object'] ?? 'post';
        $child  = $args['child_object'] ?? $raw_data['child_object'] ?? 'post';
        $type   = $args['type'] ?? $raw_data['type'] ?? 'many_to_many';

        return new RelationDefinition(
            id: $id,
            title: $title,
            parent_object: $parent,
            child_object: $child,
            type: $type,
            active: true
        );
    }
}
