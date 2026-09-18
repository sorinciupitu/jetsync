<?php
declare( strict_types=1 );

namespace JetSync\Compatibility\Adapter;

use JetSync\Registry\Model\MetaBoxDefinition;
use JetSync\Registry\Model\MetaFieldDefinition;
use JetSync\Registry\Model\DefinitionInterface;

/**
 * Adapter to translate JetEngine Custom Meta Box definitions to JetSync models.
 */
class MetaBoxAdapter implements ImportAdapterInterface {

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

        // Map associated object types (CPT slugs)
        $post_types = $args['allowed_post_type']
            ?? $args['allowed_post_types']
            ?? $args['object_type']
            ?? $args['object_types']
            ?? $raw_data['post_type']
            ?? $raw_data['allowed_post_type']
            ?? $raw_data['allowed_post_types']
            ?? $raw_data['object_type']
            ?? $raw_data['object_types']
            ?? [];
        if ( is_string( $post_types ) ) {
            $post_types = array_filter( array_map( 'trim', explode( ',', $post_types ) ) );
        }
        if ( is_array( $post_types ) ) {
            $normalized = [];
            foreach ( $post_types as $pt ) {
                if ( is_string( $pt ) ) {
                    $normalized[] = $pt;
                    continue;
                }
                if ( is_array( $pt ) ) {
                    $candidate = $pt['value'] ?? $pt['slug'] ?? $pt['name'] ?? '';
                    if ( is_string( $candidate ) && '' !== $candidate ) {
                        $normalized[] = $candidate;
                    }
                    continue;
                }
                if ( is_object( $pt ) ) {
                    $candidate = '';
                    if ( isset( $pt->value ) && is_string( $pt->value ) ) {
                        $candidate = $pt->value;
                    } elseif ( isset( $pt->slug ) && is_string( $pt->slug ) ) {
                        $candidate = $pt->slug;
                    } elseif ( isset( $pt->name ) && is_string( $pt->name ) ) {
                        $candidate = $pt->name;
                    }
                    if ( '' !== $candidate ) {
                        $normalized[] = $candidate;
                    }
                }
            }

            $post_types = array_values(
                array_filter(
                    array_map(
                        static function( $v ) {
                            $v = trim( (string) $v );
                            if ( '' === $v ) {
                                return '';
                            }

                            if ( str_contains( $v, '::' ) ) {
                                $parts = explode( '::', $v );
                                $v = (string) end( $parts );
                            } elseif ( str_contains( $v, ':' ) ) {
                                $parts = explode( ':', $v );
                                $v = (string) end( $parts );
                            }

                            return \sanitize_key( $v );
                        },
                        $normalized
                    )
                )
            );
        }

        $context  = $args['context'] ?? 'normal';
        $priority = $args['priority'] ?? 'default';

        // Translate fields
        $fields = [];
        $raw_fields = $args['meta_fields'] ?? $raw_data['meta_fields'] ?? $args['fields'] ?? $raw_data['fields'] ?? [];
        if ( is_array( $raw_fields ) ) {
            foreach ( $raw_fields as $raw_field ) {
                $field_name = $raw_field['name'] ?? '';
                $field_title = $raw_field['title'] ?? '';
                if ( empty( $field_name ) || empty( $field_title ) ) {
                    continue;
                }

                $type        = $raw_field['type'] ?? 'text';
                $description = $raw_field['description'] ?? '';
                $required    = (bool) ( $raw_field['required'] ?? false );
                $default_val = $raw_field['default_val'] ?? '';

                // Normalize options list (for select, radio, checkbox inputs)
                $options = [];
                $raw_options = $raw_field['options'] ?? [];
                if ( is_array( $raw_options ) ) {
                    foreach ( $raw_options as $opt ) {
                        $val = $opt['value'] ?? $opt['key'] ?? '';
                        $lbl = $opt['label'] ?? $opt['value'] ?? $val;
                        if ( $val !== '' ) {
                            $options[] = [ 'value' => (string) $val, 'label' => (string) $lbl ];
                        }
                    }
                }

                $fields[] = new MetaFieldDefinition(
                    name: $field_name,
                    title: $field_title,
                    type: $type,
                    description: $description,
                    required: $required,
                    default_value: $default_val,
                    options: $options
                );
            }
        }

        return new MetaBoxDefinition(
            id: $id,
            title: $title,
            object_types: $post_types,
            context: $context,
            priority: $priority,
            fields: $fields,
            active: true
        );
    }
}
