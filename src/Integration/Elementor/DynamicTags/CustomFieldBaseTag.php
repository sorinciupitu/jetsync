<?php
declare( strict_types=1 );

namespace JetSync\Integration\Elementor\DynamicTags;

use JetSync\Core\JetSync;
use JetSync\Registry\MetaBoxRegistry;
use JetSync\Registry\Model\MetaBoxDefinition;
use JetSync\Registry\Model\MetaFieldDefinition;

abstract class CustomFieldBaseTag extends \Elementor\Core\DynamicTags\Data_Tag {
    public function get_group() : string {
        return 'jetsync';
    }

    public function get_panel_template_setting_key() : string {
        return 'field';
    }

    protected function register_controls() : void {
        $this->add_control(
            'field',
            [
                'label'       => \esc_html__( 'Field', 'jetsync' ),
                'type'        => \Elementor\Controls_Manager::SELECT2,
                'options'     => $this->get_field_options(),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'context',
            [
                'label'   => \esc_html__( 'Context', 'jetsync' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'default_object',
                'options' => [
                    'default_object' => \esc_html__( 'Default Object', 'jetsync' ),
                ],
            ]
        );
    }

    /**
     * @return array<string,string>
     */
    protected function get_field_options() : array {
        $registry = $this->get_metabox_registry();
        if ( ! $registry ) {
            return [];
        }

        $allowed_types = $this->get_allowed_field_types();
        $out = [];

        foreach ( $registry->get_all() as $metabox ) {
            if ( ! $metabox instanceof MetaBoxDefinition ) {
                continue;
            }

            foreach ( $metabox->get_fields() as $field ) {
                if ( ! $field instanceof MetaFieldDefinition ) {
                    continue;
                }
                if ( ! empty( $allowed_types ) && ! in_array( $field->get_type(), $allowed_types, true ) ) {
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

    /**
     * @return array<int,string>
     */
    protected function get_allowed_field_types() : array {
        return [];
    }

    protected function get_context_post_id( array $options = [] ) : int {
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

        return $post_id > 0 ? $post_id : 0;
    }

    protected function get_selected_field_name() : string {
        return \sanitize_key( (string) $this->get_settings( 'field' ) );
    }

    protected function get_raw_meta_value( array $options = [] ) : mixed {
        $field = $this->get_selected_field_name();
        $post_id = $this->get_context_post_id( $options );
        if ( '' === $field || $post_id <= 0 ) {
            return null;
        }

        return \get_post_meta( $post_id, $field, true );
    }

    protected function get_metabox_registry() : ?MetaBoxRegistry {
        $container = JetSync::get_instance();
        $registry = $container->get( 'metabox_registry' );
        return $registry instanceof MetaBoxRegistry ? $registry : null;
    }
}
