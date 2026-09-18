<?php
declare( strict_types=1 );

namespace JetSync\Integration\Elementor\DynamicTags;

class RelatedItemImageTag extends RelatedBaseTag {
    public function get_name() : string {
        return 'jetsync-related-item-image';
    }

    public function get_title() : string {
        return \esc_html__( 'Related Item Image', 'jetsync' );
    }

    public function get_categories() : array {
        if ( \class_exists( '\Elementor\Modules\DynamicTags\Module' ) ) {
            return [ \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY ];
        }

        return [ 'image' ];
    }

    public function get_value( array $options = [] ) : array {
        $related_id = $this->get_related_post_id( $options );
        if ( $related_id <= 0 ) {
            return [
                'id'  => 0,
                'url' => '',
            ];
        }

        $thumb_id = (int) \get_post_thumbnail_id( $related_id );
        $url = '';
        if ( $thumb_id > 0 ) {
            $u = \wp_get_attachment_image_url( $thumb_id, 'full' );
            if ( is_string( $u ) ) {
                $url = $u;
            }
        }

        if ( '' === $url ) {
            $u = \get_the_post_thumbnail_url( $related_id, 'full' );
            if ( is_string( $u ) ) {
                $url = $u;
            }
        }

        return [
            'id'  => $thumb_id,
            'url' => $url,
        ];
    }

    public function render() : void {
        $val = $this->get_value();
        echo \esc_url( (string) ( $val['url'] ?? '' ) );
    }
}
