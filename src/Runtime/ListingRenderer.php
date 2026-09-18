<?php
declare( strict_types=1 );

namespace JetSync\Runtime;

use JetSync\Core\Logger;
use JetSync\Registry\ListingRegistry;
use JetSync\Registry\Model\ListingDefinition;

class ListingRenderer {

    public function __construct(
        private ListingRegistry $registry,
        private ?Logger $logger = null
    ) {
        \add_shortcode( 'jetsync_listing', [ $this, 'render_shortcode' ] );
        \add_action( 'init', [ $this, 'register_block' ] );
    }

    public function register_block() : void {
        if ( ! \function_exists( 'register_block_type' ) ) {
            return;
        }

        $handle = 'jetsync-listing-block';
        \wp_register_script(
            $handle,
            \JETSYNC_URL . 'assets/js/listing-block.js',
            [ 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor' ],
            \JETSYNC_VERSION,
            true
        );

        $options = [];
        foreach ( $this->registry->get_all() as $listing ) {
            if ( ! $listing instanceof ListingDefinition ) {
                continue;
            }
            $options[] = [
                'id'    => $listing->get_id(),
                'title' => $listing->get_title(),
            ];
        }

        \wp_add_inline_script(
            $handle,
            'window.JetSyncListingOptions=' . \wp_json_encode( $options ) . ';',
            'before'
        );

        \register_block_type(
            'jetsync/listing',
            [
                'editor_script'   => $handle,
                'render_callback' => [ $this, 'render_block' ],
                'attributes'      => [
                    'id' => [
                        'type'    => 'string',
                        'default' => '',
                    ],
                ],
            ]
        );
    }

    public function render_block( array $attributes ) : string {
        $id = isset( $attributes['id'] ) ? (string) $attributes['id'] : '';
        $id = \sanitize_key( $id );
        if ( '' === $id ) {
            return '';
        }

        return $this->render_shortcode( [ 'id' => $id ] );
    }

    public function render_shortcode( array $atts = [] ) : string {
        $atts = \shortcode_atts(
            [
                'id' => '',
            ],
            $atts,
            'jetsync_listing'
        );

        $id = \sanitize_key( (string) ( $atts['id'] ?? '' ) );
        if ( '' === $id ) {
            return '';
        }

        $def = $this->registry->get( $id );
        if ( ! $def instanceof ListingDefinition ) {
            return '';
        }

        if ( ! $def->is_active() ) {
            return '';
        }

        $post_type = $def->get_post_type();
        if ( '' === $post_type ) {
            return '';
        }

        $args = [
            'post_type'      => $post_type,
            'posts_per_page' => $def->get_posts_per_page(),
            'orderby'        => $def->get_orderby(),
            'order'          => $def->get_order(),
            'post_status'    => 'publish',
            'no_found_rows'  => true,
        ];

        $args = \apply_filters( 'jetsync_listing_query_args', $args, $def );

        $q = new \WP_Query( $args );

        if ( ! $q->have_posts() ) {
            return '';
        }

        $template = $def->get_template();
        if ( '' === trim( $template ) ) {
            $template = $this->build_template_from_selected_fields( $def->get_selected_fields() );
        }
        $template = \apply_filters( 'jetsync_listing_template', $template, $def );
        $template = \wp_kses_post( $template );

        $items = '';
        foreach ( $q->posts as $post ) {
            if ( ! $post instanceof \WP_Post ) {
                continue;
            }
            $item = $this->render_item( $template, $post->ID );
            $item = \apply_filters( 'jetsync_listing_item_html', $item, $def, $post );
            $items .= $item;
        }

        $html = '<div class="jetsync-listing jetsync-listing-' . \esc_attr( $id ) . '">' . $items . '</div>';
        return \apply_filters( 'jetsync_listing_html', $html, $def, $q );
    }

    private function render_item( string $template, int $post_id ) : string {
        $title = \get_the_title( $post_id );
        $permalink = \get_permalink( $post_id );
        $excerpt = \get_the_excerpt( $post_id );

        $out = $template;

        $out = str_replace( '{{title}}', \esc_html( (string) $title ), $out );
        $out = str_replace( '{{permalink}}', \esc_url( (string) $permalink ), $out );
        $out = str_replace( '{{excerpt}}', \esc_html( (string) $excerpt ), $out );

        $out = preg_replace_callback(
            '/\{\{\s*field:([a-zA-Z0-9_\-]+)\s*\}\}/',
            static function ( array $m ) use ( $post_id ) : string {
                $key = \sanitize_key( (string) ( $m[1] ?? '' ) );
                if ( '' === $key ) {
                    return '';
                }
                $val = \get_post_meta( $post_id, $key, true );
                if ( is_array( $val ) ) {
                    $val = implode( ', ', array_map( 'strval', $val ) );
                }
                return \esc_html( (string) $val );
            },
            $out
        );

        return $out;
    }

    /**
     * @param array<string> $selected
     */
    private function build_template_from_selected_fields( array $selected ) : string {
        $selected = array_values( array_unique( array_filter( array_map( 'strval', $selected ) ) ) );

        $parts = [];

        $has_title = in_array( 'title', $selected, true );
        $has_permalink = in_array( 'permalink', $selected, true );

        foreach ( $selected as $f ) {
            if ( 'title' === $f ) {
                if ( $has_permalink ) {
                    continue;
                }
                $parts[] = '<div class="jetsync-field jetsync-field-title">{{title}}</div>';
                continue;
            }

            if ( 'permalink' === $f ) {
                if ( $has_title ) {
                    $parts[] = '<div class="jetsync-field jetsync-field-title"><a href="{{permalink}}">{{title}}</a></div>';
                } else {
                    $parts[] = '<div class="jetsync-field jetsync-field-permalink"><a href="{{permalink}}">{{permalink}}</a></div>';
                }
                continue;
            }

            if ( 'excerpt' === $f ) {
                $parts[] = '<div class="jetsync-field jetsync-field-excerpt">{{excerpt}}</div>';
                continue;
            }

            if ( str_starts_with( $f, 'field:' ) ) {
                $key = substr( $f, 6 );
                $key = \sanitize_key( (string) $key );
                if ( '' !== $key ) {
                    $parts[] = '<div class="jetsync-field jetsync-field-' . $key . '">{{field:' . $key . '}}</div>';
                }
            }
        }

        if ( empty( $parts ) ) {
            $parts[] = '<div class="jetsync-field jetsync-field-title"><a href="{{permalink}}">{{title}}</a></div>';
        }

        return '<div class="jetsync-listing-item">' . implode( '', $parts ) . '</div>';
    }
}
