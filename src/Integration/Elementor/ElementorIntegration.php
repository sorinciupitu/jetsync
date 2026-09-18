<?php
declare( strict_types=1 );

namespace JetSync\Integration\Elementor;

use JetSync\Core\JetSync;
use JetSync\Integration\Elementor\DynamicTags\RelatedItemImageTag;
use JetSync\Integration\Elementor\DynamicTags\RelatedItemTitleTag;
use JetSync\Integration\Elementor\DynamicTags\RelatedItemUrlTag;
use JetSync\Integration\Elementor\DynamicTags\PostUrlTag;
use JetSync\Integration\Elementor\DynamicTags\CustomFieldTextTag;
use JetSync\Integration\Elementor\DynamicTags\CustomFieldUrlTag;
use JetSync\Integration\Elementor\DynamicTags\CustomImageTag;
use JetSync\Registry\MetaBoxRegistry;
use JetSync\Registry\Model\MetaBoxDefinition;
use JetSync\Registry\Model\MetaFieldDefinition;

class ElementorIntegration {
    public function __construct() {
        if ( ! \class_exists( '\Elementor\Plugin' ) ) {
            return;
        }

        \add_action( 'elementor/dynamic_tags/register', [ $this, 'register_dynamic_tags' ] );
    }

    private function require_tag_file( string $relative_path ) : void {
        if ( ! defined( 'JETSYNC_PATH' ) ) {
            return;
        }
        $file = rtrim( (string) JETSYNC_PATH, '/\\' ) . '/' . ltrim( $relative_path, '/\\' );
        if ( is_string( $file ) && '' !== $file && file_exists( $file ) ) {
            require_once $file;
        }
    }

    public function register_dynamic_tags( $dynamic_tags_manager ) : void {
        if ( ! is_object( $dynamic_tags_manager ) ) {
            return;
        }

        $this->require_tag_file( 'src/Integration/Elementor/DynamicTags/CustomFieldBaseTag.php' );
        $this->require_tag_file( 'src/Integration/Elementor/DynamicTags/CustomFieldTextTag.php' );
        $this->require_tag_file( 'src/Integration/Elementor/DynamicTags/CustomFieldUrlTag.php' );
        $this->require_tag_file( 'src/Integration/Elementor/DynamicTags/CustomImageTag.php' );
        $this->require_tag_file( 'src/Integration/Elementor/DynamicTags/PostUrlTag.php' );

        if ( method_exists( $dynamic_tags_manager, 'register_group' ) ) {
            // #region debug-point A:register-group
            $this->debug_report(
                'A',
                'ElementorIntegration::register_group',
                '[DEBUG] Registering dynamic tag group',
                [
                    'group_id'    => 'jetsync',
                    'group_title' => 'JetSync',
                ]
            );
            // #endregion
            $dynamic_tags_manager->register_group(
                'jetsync',
                [
                    'title' => \esc_html__( 'JetSync', 'jetsync' ),
                ]
            );
        }

        if ( method_exists( $dynamic_tags_manager, 'register' ) ) {
            if ( \class_exists( CustomFieldTextTag::class ) ) {
                $tag = new CustomFieldTextTag();
                $this->debug_register_tag( $dynamic_tags_manager, $tag, 'B', false );
            } else {
                $fallback = $this->build_fallback_custom_field_tag();
                if ( $fallback ) {
                    $this->debug_register_tag( $dynamic_tags_manager, $fallback, 'B', true );
                }
            }
            if ( \class_exists( CustomFieldUrlTag::class ) ) {
                $tag = new CustomFieldUrlTag();
                $this->debug_register_tag( $dynamic_tags_manager, $tag, 'BU', false );
            }
            if ( \class_exists( CustomImageTag::class ) ) {
                $tag = new CustomImageTag();
                $this->debug_register_tag( $dynamic_tags_manager, $tag, 'C', false );
            } else {
                $fallback = $this->build_fallback_custom_image_tag();
                if ( $fallback ) {
                    $this->debug_register_tag( $dynamic_tags_manager, $fallback, 'C', true );
                }
            }
            if ( \class_exists( PostUrlTag::class ) ) {
                $tag = new PostUrlTag();
                $this->debug_register_tag( $dynamic_tags_manager, $tag, 'D', false );
            }
            if ( \class_exists( RelatedItemTitleTag::class ) ) {
                $tag = new RelatedItemTitleTag();
                $this->debug_register_tag( $dynamic_tags_manager, $tag, 'E', false );
            }
            if ( \class_exists( RelatedItemUrlTag::class ) ) {
                $tag = new RelatedItemUrlTag();
                $this->debug_register_tag( $dynamic_tags_manager, $tag, 'F', false );
            }
            if ( \class_exists( RelatedItemImageTag::class ) ) {
                $tag = new RelatedItemImageTag();
                $this->debug_register_tag( $dynamic_tags_manager, $tag, 'G', false );
            }
        }
    }

    // #region debug-point H:helpers
    private function debug_register_tag( object $dynamic_tags_manager, object $tag, string $hypothesis_id, bool $is_fallback ) : void {
        $payload = [
            'class'                    => get_class( $tag ),
            'is_fallback'              => $is_fallback,
            'name'                     => method_exists( $tag, 'get_name' ) ? $tag->get_name() : null,
            'title'                    => method_exists( $tag, 'get_title' ) ? $tag->get_title() : null,
            'title_type'               => method_exists( $tag, 'get_title' ) ? gettype( $tag->get_title() ) : null,
            'group'                    => method_exists( $tag, 'get_group' ) ? $tag->get_group() : null,
            'group_type'               => method_exists( $tag, 'get_group' ) ? gettype( $tag->get_group() ) : null,
            'categories'               => method_exists( $tag, 'get_categories' ) ? $tag->get_categories() : null,
            'categories_type'          => method_exists( $tag, 'get_categories' ) ? gettype( $tag->get_categories() ) : null,
            'panel_template_setting'   => method_exists( $tag, 'get_panel_template_setting_key' ) ? $tag->get_panel_template_setting_key() : null,
        ];

        $this->debug_report(
            $hypothesis_id,
            'ElementorIntegration::debug_register_tag',
            '[DEBUG] About to register Elementor dynamic tag',
            $payload
        );

        $dynamic_tags_manager->register( $tag );
    }

    private function debug_report( string $hypothesis_id, string $location, string $message, array $data = [] ) : void {
        if ( ! function_exists( 'wp_json_encode' ) || ! function_exists( 'wp_remote_post' ) ) {
            return;
        }

        $env_file = \trailingslashit( dirname( __DIR__, 4 ) ) . '.dbg/elementor-tag-warning.env';
        $server_url = 'http://127.0.0.1:7777/event';
        $session_id = 'elementor-tag-warning';

        if ( file_exists( $env_file ) && is_readable( $env_file ) ) {
            $lines = file( $env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
            if ( is_array( $lines ) ) {
                foreach ( $lines as $line ) {
                    if ( ! is_string( $line ) || ! str_contains( $line, '=' ) ) {
                        continue;
                    }

                    [ $key, $value ] = array_map( 'trim', explode( '=', $line, 2 ) );
                    if ( 'DEBUG_SERVER_URL' === $key && '' !== $value ) {
                        $server_url = $value;
                    }
                    if ( 'DEBUG_SESSION_ID' === $key && '' !== $value ) {
                        $session_id = $value;
                    }
                }
            }
        }

        \wp_remote_post(
            $server_url,
            [
                'timeout' => 0.25,
                'blocking' => false,
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body' => \wp_json_encode(
                    [
                        'sessionId'    => $session_id,
                        'runId'        => 'pre-fix',
                        'hypothesisId' => $hypothesis_id,
                        'location'     => $location,
                        'msg'          => $message,
                        'data'         => $data,
                        'ts'           => round( microtime( true ) * 1000 ),
                    ]
                ),
            ]
        );
    }
    // #endregion

    private function build_fallback_custom_field_tag() : ?object {
        if ( ! \class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) {
            return null;
        }

        return new class() extends \Elementor\Core\DynamicTags\Tag {
            public function get_name() : string {
                return 'jetsync-custom-field';
            }

            public function get_title() : string {
                return \esc_html__( 'Custom Field', 'jetsync' );
            }

            public function get_group() : string {
                return 'jetsync';
            }

            public function get_categories() : array {
                if ( \class_exists( '\Elementor\Modules\DynamicTags\Module' ) ) {
                    return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
                }

                return [ 'text' ];
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

            private function get_registry() : ?\JetSync\Registry\MetaBoxRegistry {
                $registry = \JetSync\Core\JetSync::get_instance()->get( 'metabox_registry' );
                return $registry instanceof \JetSync\Registry\MetaBoxRegistry ? $registry : null;
            }

            private function get_field_options() : array {
                $registry = $this->get_registry();
                if ( ! $registry ) {
                    return [];
                }

                $out = [];
                foreach ( $registry->get_all() as $metabox ) {
                    if ( ! $metabox instanceof MetaBoxDefinition ) {
                        continue;
                    }
                    foreach ( $metabox->get_fields() as $field ) {
                        if ( ! $field instanceof MetaFieldDefinition ) {
                            continue;
                        }
                        if ( in_array( $field->get_type(), [ 'media', 'gallery', 'checkbox' ], true ) ) {
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

            private function resolve_post_id( array $options = [] ) : int {
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

            public function get_value( array $options = [] ) : string {
                $field = \sanitize_key( (string) $this->get_settings( 'field' ) );
                $post_id = $this->resolve_post_id( $options );
                if ( '' === $field || $post_id <= 0 ) {
                    return '';
                }
                $value = \get_post_meta( $post_id, $field, true );
                if ( is_array( $value ) ) {
                    $value = implode( ', ', array_map( 'strval', $value ) );
                }
                if ( ! is_scalar( $value ) ) {
                    return '';
                }

                $value = (string) $value;
                if ( '' === $value ) {
                    return '';
                }

                $before = (string) $this->get_settings( 'before_text' );
                $after = (string) $this->get_settings( 'after_text' );

                return $before . $value . $after;
            }

            public function render() : void {
                echo \wp_kses_post( $this->get_value() );
            }
        };
    }

    private function build_fallback_custom_image_tag() : ?object {
        if ( ! \class_exists( '\Elementor\Core\DynamicTags\Data_Tag' ) ) {
            return null;
        }

        return new class() extends \Elementor\Core\DynamicTags\Data_Tag {
            public function get_name() : string {
                return 'jetsync-custom-image';
            }

            public function get_title() : string {
                return \esc_html__( 'Custom Image', 'jetsync' );
            }

            public function get_group() : string {
                return 'jetsync';
            }

            public function get_panel_template_setting_key() : string {
                return 'field';
            }

            public function get_categories() : array {
                if ( \class_exists( '\Elementor\Modules\DynamicTags\Module' ) ) {
                    return [ \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY ];
                }

                return [ 'image' ];
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
            }

            private function get_registry() : ?\JetSync\Registry\MetaBoxRegistry {
                $registry = \JetSync\Core\JetSync::get_instance()->get( 'metabox_registry' );
                return $registry instanceof \JetSync\Registry\MetaBoxRegistry ? $registry : null;
            }

            private function get_field_options() : array {
                $registry = $this->get_registry();
                if ( ! $registry ) {
                    return [];
                }

                $out = [];
                foreach ( $registry->get_all() as $metabox ) {
                    if ( ! $metabox instanceof MetaBoxDefinition ) {
                        continue;
                    }
                    foreach ( $metabox->get_fields() as $field ) {
                        if ( ! $field instanceof MetaFieldDefinition ) {
                            continue;
                        }
                        if ( ! in_array( $field->get_type(), [ 'media', 'gallery', 'text' ], true ) ) {
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

            private function resolve_post_id( array $options = [] ) : int {
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

            public function get_value( array $options = [] ) : array {
                $field = \sanitize_key( (string) $this->get_settings( 'field' ) );
                $post_id = $this->resolve_post_id( $options );
                if ( '' === $field || $post_id <= 0 ) {
                    return [ 'id' => 0, 'url' => '' ];
                }

                $value = \get_post_meta( $post_id, $field, true );
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
        };
    }
}
