<?php
declare( strict_types=1 );

namespace JetSync\Integration\Elementor\DynamicTags;

use JetSync\Registry\Model\MetaBoxDefinition;
use JetSync\Registry\Model\MetaFieldDefinition;

class CustomFieldUrlTag extends CustomFieldBaseTag {
    public function get_name() : string {
        return 'jetsync-custom-field-url';
    }

    public function get_title() : string {
        return \esc_html__( 'URL Field', 'jetsync' );
    }

    public function get_categories() : array {
        if ( \class_exists( '\Elementor\Modules\DynamicTags\Module' ) ) {
            return [ \Elementor\Modules\DynamicTags\Module::URL_CATEGORY ];
        }

        return [ 'url' ];
    }

    /**
     * @return array<string,string>
     */
    protected function get_field_options() : array {
        $registry = $this->get_metabox_registry();
        if ( ! $registry ) {
            return [];
        }

        $out = [];

        foreach ( $registry->get_all() as $metabox ) {
            if ( ! $metabox instanceof MetaBoxDefinition ) {
                continue;
            }

            foreach ( $metabox->get_fields() as $field ) {
                if ( ! $field instanceof MetaFieldDefinition || ! $this->is_url_like_field( $field ) ) {
                    continue;
                }

                $key = $field->get_name();
                if ( '' === $key ) {
                    continue;
                }

                $label = $field->get_title() !== '' ? $field->get_title() : $key;
                $out[ $key ] = $label . ' (' . $key . ')';
            }
        }

        \asort( $out );
        return $out;
    }

    private function is_url_like_field( MetaFieldDefinition $field ) : bool {
        $type = \sanitize_key( $field->get_type() );
        if ( in_array( $type, [ 'url', 'link', 'page_link', 'post_object', 'relationship', 'file' ], true ) ) {
            return true;
        }

        if ( 'text' !== $type && 'textarea' !== $type ) {
            return false;
        }

        $haystack = strtolower( $field->get_name() . ' ' . $field->get_title() );

        return str_contains( $haystack, 'url' )
            || str_contains( $haystack, 'link' )
            || str_contains( $haystack, 'website' )
            || str_contains( $haystack, 'web' )
            || str_contains( $haystack, 'site' );
    }

    public function get_value( array $options = [] ) : string {
        return $this->normalize_value_to_url( $this->get_raw_meta_value( $options ) );
    }

    private function normalize_value_to_url( mixed $value ) : string {
        if ( is_array( $value ) ) {
            if ( isset( $value['url'] ) && is_string( $value['url'] ) && \filter_var( trim( $value['url'] ), FILTER_VALIDATE_URL ) ) {
                return trim( $value['url'] );
            }

            if ( isset( $value['link'] ) && is_string( $value['link'] ) && \filter_var( trim( $value['link'] ), FILTER_VALIDATE_URL ) ) {
                return trim( $value['link'] );
            }

            foreach ( [ 'ID', 'id' ] as $id_key ) {
                if ( isset( $value[ $id_key ] ) && is_numeric( $value[ $id_key ] ) ) {
                    $resolved = $this->resolve_id_to_url( (int) \absint( $value[ $id_key ] ) );
                    if ( '' !== $resolved ) {
                        return $resolved;
                    }
                }
            }

            foreach ( $value as $item ) {
                $resolved = $this->normalize_value_to_url( $item );
                if ( '' !== $resolved ) {
                    return $resolved;
                }
            }

            return '';
        }

        if ( is_numeric( $value ) ) {
            return $this->resolve_id_to_url( (int) \absint( $value ) );
        }

        if ( ! is_string( $value ) ) {
            return '';
        }

        $trimmed = trim( $value );
        if ( '' === $trimmed ) {
            return '';
        }

        if ( \filter_var( $trimmed, FILTER_VALIDATE_URL ) ) {
            return $trimmed;
        }

        if ( preg_match( '/^\d+(,\d+)*$/', $trimmed ) ) {
            $parts = array_values( array_filter( array_map( '\absint', explode( ',', $trimmed ) ) ) );
            $first_id = (int) ( $parts[0] ?? 0 );
            if ( $first_id > 0 ) {
                return $this->resolve_id_to_url( $first_id );
            }
        }

        return '';
    }

    private function resolve_id_to_url( int $object_id ) : string {
        if ( $object_id <= 0 ) {
            return '';
        }

        $post_type = \get_post_type( $object_id );
        if ( 'attachment' === $post_type ) {
            $attachment_url = \wp_get_attachment_url( $object_id );
            return is_string( $attachment_url ) ? $attachment_url : '';
        }

        $permalink = \get_permalink( $object_id );
        return is_string( $permalink ) ? $permalink : '';
    }

    public function render() : void {
        echo \esc_url( $this->get_value() );
    }
}
