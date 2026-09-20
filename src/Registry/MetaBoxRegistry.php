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

                // Ensure known model fields have correct options even after type fix
                $opts = $field->get_options();
                $expected_opts = $this->get_expected_options_for_key( strtolower( $field_name ) );
                if ( null !== $expected_opts && empty( $opts ) ) {
                    $opts = $expected_opts;
                }
                if ( 'checkbox' === $type && empty( $opts ) && str_contains( strtolower( $field_name ), 'model-section' ) ) {
                    $opts = $this->get_expected_options_for_key( 'model-section' ) ?? [];
                }
                if ( 'switcher' === $type && empty( $opts ) ) {
                    $opts = [ [ 'value' => '0', 'label' => 'Nu' ], [ 'value' => '1', 'label' => 'Da' ] ];
                }

                $fields[] = new MetaFieldDefinition(
                    name: $field_name,
                    title: $field->get_title(),
                    type: $type,
                    description: $field->get_description(),
                    required: $field->is_required(),
                    default_value: $field->get_default_value(),
                    options: $opts
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

     public function repair_known_model_fields() : bool {
        $changed = false;
        foreach ( $this->definitions as $slug => $definition ) {
            if ( ! $definition instanceof MetaBoxDefinition ) {
                continue;
            }
            $fields = $definition->get_fields();
            $new_fields = [];
            $local_changed = false;
            foreach ( $fields as $field ) {
                if ( ! $field instanceof MetaFieldDefinition ) {
                    continue;
                }
                $name = strtolower( $field->get_name() );
                $type = $field->get_type();
                $options = $field->get_options();
                $expected_type = $this->get_expected_type_for_key( $name );
                $expected_options = $this->get_expected_options_for_key( $name );

                if ( null !== $expected_type && $type !== $expected_type ) {
                    $type = $expected_type;
                    $local_changed = true;
                }
                if ( null !== $expected_options && empty( $options ) ) {
                    $options = $expected_options;
                    $local_changed = true;
                }
                // Fix checkbox with empty options but expected to have options
                if ( 'checkbox' === $type && empty( $options ) && str_contains( $name, 'model-section' ) ) {
                    $options = $this->get_expected_options_for_key( 'model-section' ) ?? [];
                    $local_changed = true;
                }
                // Fix switcher with empty options
                if ( 'switcher' === $type && empty( $options ) ) {
                    $options = [ [ 'value' => '0', 'label' => 'Nu' ], [ 'value' => '1', 'label' => 'Da' ] ];
                    $local_changed = true;
                }
                // Fix measurement/model fields that were incorrectly inferred as media/gallery -> should be text
                $forced_text_keys = ['bust','waist','hips','shoe','shoes','height','hair','eyes','eye','size','weight','profile'];
                foreach ($forced_text_keys as $kw) {
                    if ( str_contains($name, $kw) && in_array($type, ['media','gallery'], true) ) {
                        $type = 'text';
                        $options = [];
                        $local_changed = true;
                        break;
                    }
                }
                // Also handle generic 'image' mis-fire for bust: ensure image only for cover/thumbnail
                if ( in_array($type, ['media','gallery'], true) && str_contains($name,'bust') ) {
                    $type = 'text';
                    $options = [];
                    $local_changed = true;
                }

                if ( $local_changed ) {
                    $new_fields[] = new MetaFieldDefinition(
                        name: $field->get_name(),
                        title: $field->get_title(),
                        type: $type,
                        description: $field->get_description(),
                        required: $field->is_required(),
                        default_value: $field->get_default_value(),
                        options: $options
                    );
                } else {
                    $new_fields[] = $field;
                }
            }
            if ( $local_changed ) {
                $this->definitions[ $slug ] = new MetaBoxDefinition(
                    id: $definition->get_id(),
                    title: $definition->get_title(),
                    object_types: $definition->get_object_types(),
                    context: $definition->get_context(),
                    priority: $definition->get_priority(),
                    fields: $new_fields,
                    active: $definition->is_active()
                );
                $changed = true;
            }
        }
        if ( $changed ) {
            $this->save();
        }
        return $changed;
    }

    private function get_expected_type_for_key( string $key ) : ?string {
        if ( str_contains( $key, 'model-section' ) || str_contains( $key, 'model_section' ) ) {
            return 'checkbox';
        }
        if ( 'updated' === $key || str_contains( $key, 'updated' ) ) {
            // exact 'updated' or 'updated?' should be switcher
            if ( $key === 'updated' || $key === 'updated?' || str_ends_with( $key, '-updated' ) || str_ends_with( $key, '_updated' ) ) {
                return 'switcher';
            }
        }
        if ( str_contains( $key, 'cover' ) && ( str_contains( $key, 'model' ) || str_contains( $key, 'image' ) ) ) {
            return 'media';
        }
        if ( str_contains( $key, 'gallery' ) ) {
            return 'gallery';
        }
        if ( str_contains( $key, 'profil-instagram' ) || str_contains( $key, 'profil_instagram' ) || ( str_contains( $key, 'instagram' ) && ! str_contains( $key, 'section' ) ) ) {
            return 'url';
        }
        if ( str_contains( $key, 'uri-model' ) || str_contains( $key, 'uri_model' ) || $key === 'uri' ) {
            return 'url';
        }
        return null;
    }

    private function get_expected_options_for_key( string $key ) : ?array {
        if ( str_contains( $key, 'model-section' ) || str_contains( $key, 'model_section' ) ) {
            return [
                [ 'value' => 'Special Booking', 'label' => 'Special Booking' ],
                [ 'value' => 'Main board', 'label' => 'Main board' ],
                [ 'value' => 'Development', 'label' => 'Development' ],
                [ 'value' => 'Commercial', 'label' => 'Commercial' ],
                [ 'value' => 'Runway', 'label' => 'Runway' ],
                [ 'value' => 'General', 'label' => 'General' ],
            ];
        }
        if ( str_contains( $key, 'updated' ) ) {
            return [ [ 'value' => '0', 'label' => 'Nu' ], [ 'value' => '1', 'label' => 'Da' ] ];
        }
        return null;
    }

    /**
     * @param array<int> $sample_post_ids
     */
    private function infer_meta_field_type( array $sample_post_ids, string $meta_key ) : string {
        $meta_key = (string) $meta_key;
        $k = strtolower( $meta_key );

        // Known keys have priority over forced text
        $expected = $this->get_expected_type_for_key( $k );
        if ( null !== $expected ) {
            return $expected;
        }

        if ( $this->should_force_text_type( $k ) ) {
            return 'text';
        }
        if ( str_contains( $k, 'gallery' ) ) {
            return 'gallery';
        }
        if ( str_contains( $k, 'image' ) || str_contains( $k, 'cover' ) || str_contains( $k, 'thumbnail' ) ) {
            return 'media';
        }
        if ( str_contains( $k, 'url' ) || str_contains( $k, 'link' ) || str_contains( $k, 'website' ) || str_contains( $k, 'instagram' ) ) {
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
            'size',
            'weight',
        ];
    }
}
