<?php
declare( strict_types=1 );

namespace JetSync\Runtime;

/**
 * Defines the streamlined admin experience for the agency's model content.
 *
 * Existing meta and relation data remains untouched; this policy only changes
 * which controls are presented while editing the affected CPTs.
 */
final class CptEditorPolicy {
    /**
     * Returns the input control to use for a field in its CPT-specific context.
     */
    public static function get_effective_field_type( string $post_type, string $field_name, string $configured_type ) : string {
        $post_type  = self::normalize_key( $post_type );
        $field_name = self::normalize_key( $field_name );

        if ( 'news' !== $post_type ) {
            return $configured_type;
        }

        if ( 'model' === $field_name ) {
            return 'select';
        }

        if ( 'apare-pe-homepage' === $field_name ) {
            return 'switcher';
        }

        return $configured_type;
    }

    /**
     * The relations remain in storage and are still available to integrations,
     * but are not part of the focused editorial screens.
     */
    public static function should_hide_relation_boxes( string $post_type ) : bool {
        return in_array( self::normalize_key( $post_type ), [ 'news', 'women', 'men' ], true );
    }

    public static function is_news_model_field( string $post_type, string $field_name ) : bool {
        return 'news' === self::normalize_key( $post_type ) && 'model' === self::normalize_key( $field_name );
    }

    /**
     * @return array{off:string,on:string}|null
     */
    public static function get_switcher_labels( string $post_type, string $field_name ) : ?array {
        if ( 'news' === self::normalize_key( $post_type ) && 'apare-pe-homepage' === self::normalize_key( $field_name ) ) {
            return [ 'off' => 'Nu', 'on' => 'Da' ];
        }

        return null;
    }

    /**
     * @param array<int, array{slug:string,title:string,type_label:string}> $models
     * @return array<int, array{value:string,label:string}>
     */
    public static function build_model_options( array $models ) : array {
        $options = [ [ 'value' => '', 'label' => '— Select Model —' ] ];
        $used_slugs = [];

        foreach ( $models as $model ) {
            $slug = isset( $model['slug'] ) ? trim( (string) $model['slug'] ) : '';
            if ( '' === $slug || isset( $used_slugs[ $slug ] ) ) {
                continue;
            }

            $title = isset( $model['title'] ) ? trim( (string) $model['title'] ) : '';
            $type_label = isset( $model['type_label'] ) ? trim( (string) $model['type_label'] ) : '';
            $used_slugs[ $slug ] = true;
            $options[] = [
                'value' => $slug,
                'label' => '' !== $type_label ? ( ( '' !== $title ? $title : $slug ) . ' — ' . $type_label ) : ( '' !== $title ? $title : $slug ),
            ];
        }

        return $options;
    }

    /**
     * Converts a legacy model title to its selector slug when the title matches
     * a current Men/Women profile. Unknown values are deliberately preserved.
     *
     * @param array<int, array{value:string,label:string}> $options
     */
    public static function resolve_model_value( string $value, array $options ) : string {
        $value = trim( $value );
        if ( '' === $value ) {
            return '';
        }

        foreach ( $options as $option ) {
            $option_value = isset( $option['value'] ) ? (string) $option['value'] : '';
            if ( $value === $option_value ) {
                return $option_value;
            }

            $label = isset( $option['label'] ) ? (string) $option['label'] : '';
            $title = trim( explode( ' — ', $label, 2 )[0] );
            if ( '' !== $title && 0 === strcasecmp( $value, $title ) ) {
                return $option_value;
            }
        }

        return $value;
    }

    private static function normalize_key( string $value ) : string {
        return str_replace( '_', '-', strtolower( trim( $value ) ) );
    }
}
