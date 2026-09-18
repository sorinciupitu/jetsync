<?php
declare( strict_types=1 );

namespace JetSync\Registry;

use JetSync\Registry\Model\MetaBoxDefinition;
use JetSync\Registry\Model\MetaFieldDefinition;

/**
 * Registry to store and query Custom Meta Box definitions.
 */
class MetaBoxRegistry extends BaseRegistry {

    /**
     * Constructor. Specifies the Meta Box registry option name.
     */
    public function __construct() {
        parent::__construct( 'jetsync_meta_boxes' );
    }

    /**
     * {@inheritdoc}
     */
    protected function get_model_class() : string {
        return MetaBoxDefinition::class;
    }

    public function upgrade_auto_metaboxes() : void {
        $changed = false;

        foreach ( $this->definitions as $slug => $definition ) {
            if ( ! $definition instanceof MetaBoxDefinition ) {
                continue;
            }
            $id = $definition->get_id();
            if ( ! str_starts_with( $id, 'jetsync_auto_' ) ) {
                continue;
            }

            $post_type = substr( $id, strlen( 'jetsync_auto_' ) );
            $post_type = is_string( $post_type ) ? \sanitize_key( $post_type ) : '';
            if ( '' === $post_type ) {
                continue;
            }

            $q = new \WP_Query(
                [
                    'post_type'      => $post_type,
                    'posts_per_page' => 6,
                    'post_status'    => 'any',
                    'fields'         => 'ids',
                    'no_found_rows'  => true,
                ]
            );
            $sample_post_ids = is_array( $q->posts ) ? array_slice( array_map( 'intval', $q->posts ), 0, 6 ) : [];

            $fields = [];
            foreach ( $definition->get_fields() as $field ) {
                if ( ! $field instanceof MetaFieldDefinition ) {
                    continue;
                }
                $existing_type = $field->get_type();
                $type = $existing_type;
                $field_name = $field->get_name();
                if ( ! in_array( $existing_type, [ 'wysiwyg', 'media', 'gallery', 'textarea', 'select', 'radio', 'checkbox', 'switcher', 'date' ], true ) ) {
                    $type = 'text';
                }
                if ( $this->should_force_text_type( strtolower( $field_name ) ) ) {
                    $type = 'text';
                } elseif ( 'text' === $type ) {
                    $type = $this->infer_meta_field_type( $sample_post_ids, $field_name );
                }

                $fields[] = new MetaFieldDefinition(
                    name: $field_name,
                    title: $field->get_title(),
                    type: $type,
                    description: $field->get_description(),
                    required: $field->is_required(),
                    default_value: $field->get_default_value(),
                    options: $field->get_options()
                );
            }

            if ( empty( $fields ) ) {
                continue;
            }

            $this->definitions[ $slug ] = new MetaBoxDefinition(
                id: $definition->get_id(),
                title: 'Settings',
                object_types: $definition->get_object_types(),
                context: $definition->get_context(),
                priority: $definition->get_priority(),
                fields: $fields,
                active: $definition->is_active()
            );
            $changed = true;
        }

        if ( $changed ) {
            $this->save();
        }
    }

    /**
     * @param array<int> $sample_post_ids
     */
    private function infer_meta_field_type( array $sample_post_ids, string $meta_key ) : string {
        $meta_key = (string) $meta_key;
        $k = strtolower( $meta_key );

        if ( $this->should_force_text_type( $k ) ) {
            return 'text';
        }
        if ( str_contains( $k, 'gallery' ) ) {
            return 'gallery';
        }
        if ( str_contains( $k, 'image' ) || str_contains( $k, 'cover' ) || str_contains( $k, 'thumbnail' ) ) {
            return 'media';
        }
        if ( str_contains( $k, 'url' ) || str_contains( $k, 'link' ) || str_contains( $k, 'website' ) ) {
            return 'url';
        }
        if ( str_contains( $k, 'description' ) || str_contains( $k, 'content' ) || str_contains( $k, 'text' ) ) {
            return 'wysiwyg';
        }

        foreach ( $sample_post_ids as $post_id ) {
            $raw = \get_post_meta( (int) $post_id, $meta_key, true );
            if ( '' === $raw || null === $raw ) {
                continue;
            }
            if ( is_array( $raw ) ) {
                $ids = array_values( array_filter( array_map( '\absint', $raw ) ) );
                if ( ! empty( $ids ) ) {
                    return 'gallery';
                }
                continue;
            }

            $val = trim( (string) $raw );
            if ( '' === $val ) {
                continue;
            }

            if ( preg_match( '/^\\d+(,\\d+)*$/', $val ) ) {
                $ids = array_values( array_filter( array_map( '\absint', explode( ',', $val ) ) ) );
                if ( count( $ids ) > 1 ) {
                    return 'gallery';
                }
                if ( 1 === count( $ids ) && $this->is_probably_media_key( $k ) ) {
                    $maybe_attachment = \get_post_type( $ids[0] );
                    if ( 'attachment' === $maybe_attachment ) {
                        return 'media';
                    }
                }
            }

            if ( strlen( $val ) > 180 || str_contains( $val, "\n" ) ) {
                if ( str_contains( $val, '<' ) ) {
                    return 'wysiwyg';
                }
                return 'textarea';
            }

            if ( \filter_var( $val, FILTER_VALIDATE_URL ) ) {
                return 'url';
            }
        }

        return 'text';
    }

    private function should_force_text_type( string $meta_key ) : bool {
        foreach ( $this->get_text_field_keywords() as $keyword ) {
            if ( str_contains( $meta_key, $keyword ) ) {
                return true;
            }
        }

        return false;
    }

    private function is_probably_media_key( string $meta_key ) : bool {
        foreach ( [ 'image', 'cover', 'thumbnail', 'photo', 'avatar', 'picture', 'icon', 'logo', 'banner', 'file' ] as $keyword ) {
            if ( str_contains( $meta_key, $keyword ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string>
     */
    private function get_text_field_keywords() : array {
        return [
            'bust',
            'waist',
            'hips',
            'shoe',
            'shoes',
            'height',
            'hair',
            'eyes',
            'eye',
            'section',
            'size',
            'weight',
            'profile',
            'instagram',
        ];
    }
}
