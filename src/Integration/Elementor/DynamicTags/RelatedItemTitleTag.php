<?php
declare( strict_types=1 );

namespace JetSync\Integration\Elementor\DynamicTags;

class RelatedItemTitleTag extends RelatedBaseTag {
    public function get_name() : string {
        return 'jetsync-related-item-title';
    }

    public function get_title() : string {
        return \esc_html__( 'Related Item Title', 'jetsync' );
    }

    public function get_categories() : array {
        if ( \class_exists( '\Elementor\Modules\DynamicTags\Module' ) ) {
            return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
        }

        return [ 'text' ];
    }

    public function get_value( array $options = [] ) : string {
        $related_id = $this->get_related_post_id( $options );
        if ( $related_id <= 0 ) {
            return '';
        }

        return (string) \get_the_title( $related_id );
    }

    public function render() : void {
        echo \esc_html( $this->get_value() );
    }
}
