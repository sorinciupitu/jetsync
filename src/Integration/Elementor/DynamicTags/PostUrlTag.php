<?php
declare( strict_types=1 );

namespace JetSync\Integration\Elementor\DynamicTags;

class PostUrlTag extends \Elementor\Core\DynamicTags\Tag {
    public function get_name() : string {
        return 'jetsync-post-url';
    }

    public function get_title() : string {
        return \esc_html__( 'Current Post URL', 'jetsync' );
    }

    public function get_group() : string {
        return 'jetsync';
    }

    public function get_categories() : array {
        if ( \class_exists( '\Elementor\Modules\DynamicTags\Module' ) ) {
            return [ \Elementor\Modules\DynamicTags\Module::URL_CATEGORY ];
        }

        return [ 'url' ];
    }

    public function get_value( array $options = [] ) : string {
        $post_id = 0;

        if ( isset( $options['post_id'] ) ) {
            $post_id = (int) \absint( $options['post_id'] );
        } elseif ( isset( $options['id'] ) ) {
            $post_id = (int) \absint( $options['id'] );
        } elseif ( isset( $options['object_id'] ) ) {
            $post_id = (int) \absint( $options['object_id'] );
        }

        if ( $post_id <= 0 ) {
            $post_id = (int) \get_the_ID();
        }

        if ( $post_id <= 0 && isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ) {
            $post_id = (int) $GLOBALS['post']->ID;
        }

        return $post_id > 0 ? (string) \get_permalink( $post_id ) : '';
    }

    public function render() : void {
        echo \esc_url( $this->get_value() );
    }
}
