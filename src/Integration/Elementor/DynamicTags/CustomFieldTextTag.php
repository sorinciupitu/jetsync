<?php
declare( strict_types=1 );

namespace JetSync\Integration\Elementor\DynamicTags;

class CustomFieldTextTag extends CustomFieldBaseTag {
    public function get_name() : string {
        return 'jetsync-custom-field';
    }

    public function get_title() : string {
        return \esc_html__( 'Custom Field', 'jetsync' );
    }

    public function get_categories() : array {
        if ( \class_exists( '\Elementor\Modules\DynamicTags\Module' ) ) {
            return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
        }

        return [ 'text' ];
    }

    protected function get_allowed_field_types() : array {
        return [ 'text', 'textarea', 'wysiwyg', 'date', 'select', 'radio', 'switcher' ];
    }

    protected function register_controls() : void {
        parent::register_controls();

        $this->add_control(
            'before_text',
            [
                'label'       => \esc_html__( 'Before', 'jetsync' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '',
                'label_block' => true,
            ]
        );

        $this->add_control(
            'after_text',
            [
                'label'       => \esc_html__( 'After', 'jetsync' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '',
                'label_block' => true,
            ]
        );
    }

    private function format_value( string $value ) : string {
        if ( '' === $value ) {
            return '';
        }

        $before = (string) $this->get_settings( 'before_text' );
        $after = (string) $this->get_settings( 'after_text' );

        return $before . $value . $after;
    }

    public function get_value( array $options = [] ) : string {
        $value = $this->get_raw_meta_value( $options );
        if ( is_array( $value ) ) {
            $value = implode( ', ', array_map( 'strval', $value ) );
        }

        return is_scalar( $value ) ? $this->format_value( (string) $value ) : '';
    }

    public function render() : void {
        echo \wp_kses_post( $this->get_value() );
    }
}
