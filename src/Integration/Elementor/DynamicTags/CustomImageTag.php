<?php
declare( strict_types=1 );

namespace JetSync\Integration\Elementor\DynamicTags;

class CustomImageTag extends CustomFieldBaseTag {
    public function get_name() : string {
        return 'jetsync-custom-image';
    }

    public function get_title() : string {
        return \esc_html__( 'Custom Image', 'jetsync' );
    }

    public function get_categories() : array {
        if ( \class_exists( '\Elementor\Modules\DynamicTags\Module' ) ) {
            return [ \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY ];
        }

        return [ 'image' ];
    }

    protected function get_allowed_field_types() : array {
        return [ 'media', 'text', 'gallery' ];
    }

    public function get_value( array $options = [] ) : array {
        $value = $this->get_raw_meta_value( $options );
        $attachment_id = 0;
        $url = '';

        if ( is_numeric( $value ) ) {
            $attachment_id = (int) \absint( $value );
        } elseif ( is_string( $value ) ) {
            $trimmed = trim( $value );
            if ( preg_match( '/^\\d+(,\\d+)*$/', $trimmed ) ) {
                $parts = array_values( array_filter( array_map( '\absint', explode( ',', $trimmed ) ) ) );
                $attachment_id = (int) ( $parts[0] ?? 0 );
            } elseif ( \filter_var( $trimmed, FILTER_VALIDATE_URL ) ) {
                $url = $trimmed;
            }
        }

        if ( $attachment_id > 0 ) {
            $maybe = \wp_get_attachment_image_url( $attachment_id, 'full' );
            if ( is_string( $maybe ) ) {
                $url = $maybe;
            }
        }

        return [
            'id'  => $attachment_id,
            'url' => $url,
        ];
    }

    public function render() : void {
        $value = $this->get_value();
        echo \esc_url( (string) ( $value['url'] ?? '' ) );
    }
}
