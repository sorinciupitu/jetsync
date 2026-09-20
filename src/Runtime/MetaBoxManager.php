<?php
declare( strict_types=1 );

namespace JetSync\Runtime;

use JetSync\Registry\MetaBoxRegistry;
use JetSync\Registry\Model\MetaBoxDefinition;
use JetSync\Registry\Model\MetaFieldDefinition;
use JetSync\Core\Logger;

/**
 * Handles rendering metabox fields in WP-Admin editor screens and saving postmeta fields safely.
 */
class MetaBoxManager {
    private array $registered_for = [];

    /**
     * Constructor. Registers WordPress hooks.
     *
     * @param MetaBoxRegistry $registry Option registry storing fields.
     * @param Logger|null $logger Logger instance.
     */
    public function __construct(
        private MetaBoxRegistry $registry,
        private ?Logger $logger = null
    ) {
        \add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
        \add_action( 'save_post', [ $this, 'save_meta_boxes_data' ], 10, 2 );
        \add_action( 'current_screen', [ $this, 'maybe_register_meta_boxes_for_screen' ] );
        \add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets() : void {
        $screen = \function_exists( 'get_current_screen' ) ? \get_current_screen() : null;
        if ( ! $screen instanceof \WP_Screen ) {
            return;
        }
        if ( ! in_array( (string) $screen->base, [ 'post', 'post-new' ], true ) ) {
            return;
        }
        if ( ! isset( $screen->post_type ) || ! is_string( $screen->post_type ) || '' === $screen->post_type ) {
            return;
        }

        $has_box   = false;
        $needs_media = false;
        $meta_boxes = $this->registry->get_all();
        foreach ( $meta_boxes as $metabox ) {
            if ( ! $metabox instanceof MetaBoxDefinition ) {
                continue;
            }
            if ( ! $metabox->is_active() ) {
                continue;
            }
            $types = $metabox->get_object_types();
            if ( ! empty( $types ) && ! in_array( $screen->post_type, $types, true ) ) {
                continue;
            }
            $has_box = true;
            foreach ( $metabox->get_fields() as $field ) {
                $t = $field->get_type();
                if ( 'media' === $t || 'gallery' === $t ) {
                    $needs_media = true;
                }
            }
        }

        if ( ! $has_box ) {
            return;
        }

        \wp_enqueue_style(
            'jet-sync-metabox',
            JETSYNC_URL . 'assets/css/admin-metabox.css',
            [],
            JETSYNC_VERSION
        );

        $needs_sortable = false;
        if ( $needs_media ) {
            // Check if any gallery exists to need sortable
            foreach ( $meta_boxes as $mb ) {
                foreach ( $mb->get_fields() as $f ) {
                    if ( 'gallery' === $f->get_type() ) { $needs_sortable = true; break 2; }
                }
            }
        }

        if ( $needs_media ) {
            \wp_enqueue_media();
            if ( $needs_sortable ) {
                \wp_enqueue_script( 'jquery-ui-sortable' );
            }
            \wp_enqueue_script(
                'jet-sync-metabox-media',
                JETSYNC_URL . 'assets/js/metabox-media.js',
                $needs_sortable ? [ 'jquery', 'jquery-ui-sortable' ] : [ 'jquery' ],
                JETSYNC_VERSION,
                true
            );
        }

        \wp_enqueue_script(
            'jet-sync-metabox-ui',
            JETSYNC_URL . 'assets/js/metabox-ui.js',
            [ 'jquery', 'jquery-ui-sortable' ],
            JETSYNC_VERSION,
            true
        );
    }

    public function sync_featured_images_for_registered_media_fields() : int {
        $updated = 0;

        foreach ( $this->registry->get_all() as $metabox ) {
            if ( ! $metabox instanceof MetaBoxDefinition ) {
                continue;
            }
            if ( ! $metabox->is_active() ) {
                continue;
            }

            $object_types = $metabox->get_object_types();
            if ( empty( $object_types ) ) {
                continue;
            }

            foreach ( $metabox->get_fields() as $field ) {
                if ( ! $field instanceof MetaFieldDefinition ) {
                    continue;
                }
                if ( ! $this->is_primary_image_field( $field->get_name() ) ) {
                    continue;
                }

                foreach ( $object_types as $post_type ) {
                    $query = new \WP_Query(
                        [
                            'post_type'      => (string) $post_type,
                            'post_status'    => 'any',
                            'posts_per_page' => -1,
                            'fields'         => 'ids',
                            'no_found_rows'  => true,
                        ]
                    );

                    if ( ! is_array( $query->posts ) ) {
                        continue;
                    }

                    foreach ( $query->posts as $post_id ) {
                        $post_id = (int) $post_id;
                        if ( $post_id <= 0 ) {
                            continue;
                        }
                        $attachment_id = $this->extract_attachment_id( \get_post_meta( $post_id, $field->get_name(), true ) );
                        if ( $attachment_id <= 0 ) {
                            continue;
                        }
                        $current_thumb = (int) \absint( \get_post_thumbnail_id( $post_id ) );
                        if ( $current_thumb === $attachment_id ) {
                            continue;
                        }
                        \update_post_meta( $post_id, '_thumbnail_id', $attachment_id );
                        $updated++;
                    }
                }
            }
        }

        return $updated;
    }

    private function is_primary_image_field( string $field_name ) : bool {
        $field_name = strtolower( $field_name );
        return str_contains( $field_name, 'cover_image' )
            || str_contains( $field_name, 'cover-model' )
            || str_contains( $field_name, 'cover_model' )
            || str_contains( $field_name, 'cover' )
            || str_contains( $field_name, 'featured_image' )
            || str_contains( $field_name, 'thumbnail' )
            || 'image' === $field_name;
    }

    private function extract_attachment_id( $value ) : int {
        if ( is_numeric( $value ) ) {
            return (int) \absint( $value );
        }

        if ( is_array( $value ) ) {
            foreach ( $value as $item ) {
                $attachment_id = $this->extract_attachment_id( $item );
                if ( $attachment_id > 0 ) {
                    return $attachment_id;
                }
            }
            return 0;
        }

        if ( ! is_string( $value ) ) {
            return 0;
        }

        $value = trim( $value );
        if ( '' === $value ) {
            return 0;
        }

        if ( preg_match( '/^\d+(,\d+)*$/', $value ) ) {
            $parts = array_values( array_filter( array_map( '\absint', preg_split( '/\s*,\s*/', $value ) ?: [] ) ) );
            return (int) ( $parts[0] ?? 0 );
        }

        if ( \filter_var( $value, FILTER_VALIDATE_URL ) ) {
            return (int) \absint( \attachment_url_to_postid( $value ) );
        }

        return 0;
    }

    private function sync_featured_image_from_field_value( int $post_id, string $field_name, $raw_value ) : void {
        if ( ! $this->is_primary_image_field( $field_name ) ) {
            return;
        }

        $attachment_id = $this->extract_attachment_id( $raw_value );
        if ( $attachment_id > 0 ) {
            \update_post_meta( $post_id, '_thumbnail_id', $attachment_id );
            return;
        }

        \delete_post_meta( $post_id, '_thumbnail_id' );
    }

    public function maybe_register_meta_boxes_for_screen( $screen ) : void {
        if ( ! $screen instanceof \WP_Screen ) {
            return;
        }
        if ( ! isset( $screen->base ) || ! is_string( $screen->base ) ) {
            return;
        }
        if ( ! in_array( $screen->base, [ 'post', 'post-new' ], true ) ) {
            return;
        }
        if ( ! isset( $screen->post_type ) || ! is_string( $screen->post_type ) || '' === $screen->post_type ) {
            return;
        }

        $this->register_meta_boxes_for_post_type( $screen->post_type );
    }

    /**
     * Callback for 'add_meta_boxes'. Registers active metabox containers with WordPress.
     *
     * @return void
     */
    public function register_meta_boxes() : void {
        $meta_boxes = $this->registry->get_all();

        foreach ( $meta_boxes as $metabox ) {
            if ( ! $metabox->is_active() ) {
                continue;
            }

            $post_types = $metabox->get_object_types();
            if ( empty( $post_types ) ) {
                $post_types = \get_post_types( [ 'show_ui' => true ], 'names' );
                if ( is_array( $post_types ) ) {
                    $post_types = array_values( array_map( 'strval', $post_types ) );
                } else {
                    $post_types = [];
                }
            }
            foreach ( $post_types as $post_type ) {
                $this->register_single_metabox( $metabox, $post_type );
            }
        }
    }

    /**
     * Convert arbitrary stored meta into a safe admin text representation.
     */
    private function stringify_field_value( mixed $value ) : string {
        if ( is_scalar( $value ) || null === $value ) {
            return (string) $value;
        }

        if ( is_array( $value ) ) {
            $items = [];
            foreach ( $value as $item ) {
                if ( is_scalar( $item ) || null === $item ) {
                    $item = trim( (string) $item );
                    if ( '' !== $item ) {
                        $items[] = $item;
                    }
                }
            }

            return implode( ', ', $items );
        }

        return '';
    }

    private function register_meta_boxes_for_post_type( string $post_type ) : void {
        $post_type = \sanitize_key( $post_type );
        if ( '' === $post_type ) {
            return;
        }
        if ( isset( $this->registered_for[ $post_type ] ) ) {
            return;
        }
        $this->registered_for[ $post_type ] = true;

        $meta_boxes = $this->registry->get_all();
        foreach ( $meta_boxes as $metabox ) {
            if ( ! $metabox instanceof MetaBoxDefinition ) {
                continue;
            }
            if ( ! $metabox->is_active() ) {
                continue;
            }

            $types = $metabox->get_object_types();
            if ( ! empty( $types ) && ! in_array( $post_type, $types, true ) ) {
                continue;
            }

            $this->register_single_metabox( $metabox, $post_type );
        }
    }

    private function register_single_metabox( MetaBoxDefinition $metabox, string $post_type ) : void {
        $post_type = \sanitize_key( $post_type );
        if ( '' === $post_type ) {
            return;
        }

        \add_meta_box(
            $metabox->get_id(),
            $metabox->get_title(),
            [ $this, 'render_meta_box_content' ],
            $post_type,
            $metabox->get_context(),
            $metabox->get_priority(),
            [ 'definition' => $metabox ]
        );
    }

    private function get_field_grid_class( string $field_name, string $field_type ) : string {
        $k = strtolower( $field_name );
        // Known model fields mapping to match screenshot model.jpeg
        if ( str_contains( $k, 'model-name' ) || $k === 'model_name' ) {
            return 'jetsync-col-4';
        }
        if ( str_contains( $k, 'model-section' ) || str_contains( $k, 'model_section' ) ) {
            return 'jetsync-col-5';
        }
        if ( $k === 'updated' || $k === 'updated?' ) {
            return 'jetsync-col-3';
        }
        if ( str_contains( $k, 'cover' ) ) {
            return 'jetsync-col-12 jetsync-media-row';
        }
        if ( str_contains( $k, 'height' ) || str_contains( $k, 'bust' ) || str_contains( $k, 'waist' ) || str_contains( $k, 'hips' ) ) {
            return 'jetsync-col-3';
        }
        if ( str_contains( $k, 'shoes' ) || str_contains( $k, 'shoe' ) || str_contains( $k, 'hair' ) || str_contains( $k, 'eyes' ) || str_contains( $k, 'eye' ) ) {
            return 'jetsync-col-4';
        }
        if ( 'gallery' === $field_type || str_contains( $k, 'gallery' ) ) {
            return 'jetsync-col-12';
        }
        if ( 'media' === $field_type ) {
            return 'jetsync-col-12';
        }
        if ( str_contains( $k, 'instagram' ) || str_contains( $k, 'profil' ) || str_contains( $k, 'uri' ) || str_contains( $k, 'url' ) ) {
            return 'jetsync-col-12';
        }
        if ( 'checkbox' === $field_type ) {
            return 'jetsync-col-6';
        }
        if ( 'switcher' === $field_type ) {
            return 'jetsync-col-3';
        }
        if ( 'textarea' === $field_type || 'wysiwyg' === $field_type ) {
            return 'jetsync-col-12';
        }
        // default: fallback to full width to preserve compatibility for generic metaboxes
        return 'jetsync-col-12';
    }

    /**
     * Sort fields to match model.jpeg expected order.
     * Only applies to model-like metaboxes (contains model-* fields); otherwise preserves original order.
     * @param array<MetaFieldDefinition> $fields
     * @return array<MetaFieldDefinition>
     */
    private function sort_fields_for_display( array $fields ) : array {
        // Check if this is a model metabox (has at least one model-* field)
        $is_model = false;
        foreach ($fields as $f) { if (str_contains(strtolower($f->get_name()),'model') || strtolower($f->get_name())==='updated') { $is_model=true; break; } }
        if (!$is_model) return $fields;

        $order_map = [
            'model-name' => 0, 'model_name' => 0,
            'model-section' => 1, 'model_section' => 1,
            'updated' => 2,
            'cover-model' => 3, 'cover_model' => 3, 'cover' => 3,
            'height' => 4, 'height-model' => 4,
            'bust' => 5, 'bust-model' => 5,
            'waist' => 6, 'waist-model' => 6,
            'hips' => 7, 'hips-model' => 7,
            'shoes' => 8, 'shoe' => 8, 'shoes-model' => 8,
            'hair' => 9, 'hair-model' => 9,
            'eyes' => 10, 'eye' => 10, 'eyes-model' => 10,
            'model-gallery' => 11, 'gallery' => 11,
            'uri' => 12, 'url-model' => 12, 'uri-model' => 12,
            'profil-instagram' => 13, 'instagram' => 13, 'profil' => 13,
        ];
        usort($fields, function($a,$b) use ($order_map) {
            $ka = strtolower($a->get_name());
            $kb = strtolower($b->get_name());
            $pa = 99; $pb = 99;
            foreach ($order_map as $key=>$prio) { if (str_contains($ka,$key)) { $pa=$prio; break; } }
            foreach ($order_map as $key=>$prio) { if (str_contains($kb,$key)) { $pb=$prio; break; } }
            if ($pa=== $pb) return 0;
            return $pa < $pb ? -1 : 1;
        });
        return $fields;
    }

    /**
     * Rendering callback. Renders custom form input controls.
     *
     * @param \WP_Post $post Current post object.
     * @param array{args:array{definition:MetaBoxDefinition}} $callback_args
     * @return void
     */
    public function render_meta_box_content( \WP_Post $post, array $callback_args ) : void {
        $metabox = $callback_args['args']['definition'] ?? null;
        if ( ! $metabox ) {
            return;
        }

        $nonce_name = 'jetsync_metabox_nonce_' . $metabox->get_id();
        $nonce_action = 'jetsync_save_metabox_' . $metabox->get_id();
        \wp_nonce_field( $nonce_action, $nonce_name );

        $fields = $metabox->get_fields();
        // Reorder to match model.jpeg expected layout (Model Name -> Section -> Updated -> Cover -> measurements -> gallery ...)
        $fields = $this->sort_fields_for_display( $fields );

        echo '<div class="jetsync-meta-box-container jetsync-model-layout">';

        foreach ( $fields as $field ) {
            $field_name  = $field->get_name();
            $field_title = $field->get_title();
            $field_type  = $field->get_type();
            $field_desc  = $field->get_description();
            $default     = $field->get_default_value();

            $stored_val = \get_post_meta( $post->ID, $field_name, true );
            // Fix legacy broken checkbox that was stored as string "true, false" instead of array
            if ( 'checkbox' === $field_type && is_string( $stored_val ) && str_contains( $stored_val, 'true' ) && empty( $field->get_options() ) === false ) {
                // Try to detect broken stringified booleans -> treat as empty and let user reselect
                if ( preg_match( '/\btrue\b|\bfalse\b/', $stored_val ) && ! str_contains( $stored_val, 'Special Booking' ) ) {
                    $stored_val = [];
                }
            }
            $value = ( $stored_val !== '' && $stored_val !== [] ) ? $stored_val : $default;
            // For checkbox, ensure array; if stored as comma string of valid option values, convert to array
            if ( 'checkbox' === $field_type && is_string( $value ) && '' !== $value ) {
                // Only convert if it looks like option values list, not legacy booleans
                if ( ! str_contains( $value, 'true' ) || str_contains( $value, 'Special' ) ) {
                    $value = array_map( 'trim', explode( ',', $value ) );
                } else {
                    $value = [];
                }
            }

            $grid_class = $this->get_field_grid_class( $field_name, $field_type );

            echo '<div class="jetsync-meta-field ' . \esc_attr( $grid_class ) . ' jetsync-type-' . \esc_attr( $field_type ) . '">';

            // Label + tiny Name hint like model.jpeg shows "Name: cover-model"
            echo '<div class="jetsync-field-header">';
            echo '<label class="jetsync-field-label" for="' . \esc_attr( $field_name ) . '">' . \esc_html( $field_title ) . '</label>';
            echo '<span class="jetsync-field-name-hint">Name: ' . \esc_html( $field_name ) . '</span>';
            echo '</div>';

            // Field input
            switch ( $field_type ) {
                case 'textarea':
                    echo '<textarea id="' . \esc_attr( $field_name ) . '" name="' . \esc_attr( $field_name ) . '" class="large-text" rows="3">' . \esc_textarea( $this->stringify_field_value( $value ) ) . '</textarea>';
                    break;

                case 'select':
                    echo '<select id="' . \esc_attr( $field_name ) . '" name="' . \esc_attr( $field_name ) . '" class="jetsync-select">';
                    foreach ( $field->get_options() as $opt ) {
                        $selected = ( (string) $value === (string) $opt['value'] ) ? ' selected="selected"' : '';
                        echo '<option value="' . \esc_attr( $opt['value'] ) . '"' . $selected . '>' . \esc_html( $opt['label'] ) . '</option>';
                    }
                    echo '</select>';
                    break;

                case 'radio':
                    echo '<div class="jetsync-radio-group">';
                    foreach ( $field->get_options() as $opt ) {
                        $checked = ( (string) $value === (string) $opt['value'] ) ? ' checked="checked"' : '';
                        echo '<label class="jetsync-radio-label"><input type="radio" name="' . \esc_attr( $field_name ) . '" value="' . \esc_attr( $opt['value'] ) . '"' . $checked . '> ' . \esc_html( $opt['label'] ) . '</label>';
                    }
                    echo '</div>';
                    break;

                case 'checkbox':
                    // Normalize legacy storage forms: associative true/false, indexed booleans, etc.
                    if ( is_array( $value ) && ! empty( $value ) ) {
                        $keys = array_keys( $value );
                        $is_assoc = $keys !== range( 0, count( $value ) - 1 );
                        if ( $is_assoc ) {
                            $normalized = [];
                            foreach ( $value as $k => $v ) {
                                $vs = strtolower( trim( (string) $v ) );
                                if ( in_array( $vs, [ 'true', '1', 'yes', 'on' ], true ) ) {
                                    $normalized[] = (string) $k;
                                }
                            }
                            $value = $normalized;
                        } elseif ( count( $value ) === count( $field->get_options() ) && count( array_filter( $value, static fn( $v ) => in_array( strtolower( trim( (string) $v ) ), [ 'true', 'false', '1', '0' ], true ) ) ) === count( $value ) ) {
                            $normalized = [];
                            $opts = $field->get_options();
                            foreach ( $value as $idx => $boolStr ) {
                                if ( in_array( strtolower( trim( (string) $boolStr ) ), [ 'true', '1', 'yes', 'on' ], true ) && isset( $opts[ $idx ]['value'] ) ) {
                                    $normalized[] = (string) $opts[ $idx ]['value'];
                                }
                            }
                            $value = $normalized;
                        }
                    }
                    $checkbox_values = is_array( $value ) ? $value : ( ! empty( $value ) ? (array) $value : [] );
                    // Normalize to strings for comparison
                    $checkbox_values_str = array_map( 'strval', $checkbox_values );
                    // Filter out legacy boolean strings
                    $checkbox_values_str = array_filter( $checkbox_values_str, static function( $v ) {
                        return ! in_array( strtolower( trim( $v ) ), [ 'true', 'false', '1', '0' ], true ) || in_array( $v, [ 'Special Booking', 'Main board', 'Development', 'Commercial', 'Runway', 'General' ], true );
                    } );
                    // If values are like ["true","false"] leftover, treat as empty
                    if ( count( $checkbox_values_str ) > 0 && count( array_filter( $checkbox_values_str, fn($v) => in_array($v, ['Special Booking','Main board','Development','Commercial','Runway','General'], true) ) ) === 0 && count($checkbox_values_str) <= 6 ) {
                        // Check if it's all booleans => clear
                        $all_bool = true;
                        foreach ( $checkbox_values_str as $cv ) {
                            if ( ! in_array( strtolower($cv), ['true','false'], true ) ) { $all_bool = false; break; }
                        }
                        if ( $all_bool ) { $checkbox_values_str = []; }
                    }

                    echo '<div class="jetsync-checkbox-field" data-field="' . \esc_attr( $field_name ) . '">';
                    // Select all / Deselect all like model
                    echo '<div class="jetsync-checkbox-actions">';
                    echo '<button type="button" class="button button-small jetsync-select-all">Select all</button>';
                    echo '<button type="button" class="button button-small jetsync-deselect-all">Deselect all</button>';
                    echo '</div>';
                    echo '<div class="jetsync-checkbox-options">';
                    foreach ( $field->get_options() as $opt ) {
                        $checked = in_array( (string) $opt['value'], $checkbox_values_str, true ) ? ' checked="checked"' : '';
                        echo '<label class="jetsync-checkbox-label"><input type="checkbox" name="' . \esc_attr( $field_name ) . '[]" value="' . \esc_attr( $opt['value'] ) . '"' . $checked . '> <span>' . \esc_html( $opt['label'] ) . '</span></label>';
                    }
                    if ( empty( $field->get_options() ) ) {
                        echo '<p class="description">No options configured.</p>';
                    }
                    echo '</div>';
                    echo '</div>';
                    break;

                case 'switcher':
                    $switcher_labels = $this->get_switcher_labels( $field );
                    $is_checked = ( $value === 'true' || $value === '1' || $value === true || $value === 1 || $value === 'on' ) ? ' checked="checked"' : '';
                    echo '<label class="jetsync-switcher">';
                    echo '<input type="checkbox" name="' . \esc_attr( $field_name ) . '" value="1"' . $is_checked . '>';
                    echo '<span class="jetsync-switcher-slider"></span>';
                    echo '<span class="jetsync-switcher-labels"><span class="jetsync-switcher-off">' . \esc_html( $switcher_labels['off'] ) . '</span><span class="jetsync-switcher-on">' . \esc_html( $switcher_labels['on'] ) . '</span></span>';
                    echo '</label>';
                    break;

                case 'wysiwyg':
                    \wp_editor( $this->stringify_field_value( $value ), $field_name, [
                        'textarea_name' => $field_name,
                        'textarea_rows' => 5,
                        'media_buttons' => true,
                        'quicktags'     => true,
                    ] );
                    break;

                case 'media':
                    $attachment_id = \absint( is_array( $value ) ? 0 : $value );
                    // Also handle legacy string "123" or url
                    if ( 0 === $attachment_id && is_string( $value ) && '' !== trim( $value ) ) {
                        $attachment_id = $this->extract_attachment_id( $value );
                    }
                    $thumb = $attachment_id > 0 ? \wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
                    $full = $attachment_id > 0 ? \wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
                    echo '<div class="jetsync-media-field jetsync-media-single">';
                    echo '<input class="jetsync-media-value" type="hidden" id="' . \esc_attr( $field_name ) . '" name="' . \esc_attr( $field_name ) . '" value="' . \esc_attr( (string) $attachment_id ) . '">';
                    echo '<div class="jetsync-media-preview' . ( '' !== $thumb ? ' has-image' : '' ) . '">';
                    if ( is_string( $thumb ) && '' !== $thumb ) {
                        echo '<img src="' . \esc_url( $full ?: $thumb ) . '" alt="" />';
                    } else {
                        echo '<div class="jetsync-media-placeholder"><span class="dashicons dashicons-format-image"></span></div>';
                    }
                    echo '</div>';
                    echo '<div class="jetsync-media-actions">';
                    echo '<button type="button" class="button jetsync-media-select jetsync-btn-dark" data-multiple="0">CHOOSE MEDIA</button>';
                    echo '<button type="button" class="button jetsync-media-clear">Clear</button>';
                    echo '</div>';
                    echo '</div>';
                    break;

                case 'gallery':
                    $ids = [];
                    if ( is_array( $value ) ) {
                        $ids = array_values( array_filter( array_map( '\absint', $value ) ) );
                    } else {
                        $raw = trim( (string) $value );
                        if ( '' !== $raw ) {
                            // handle both "1,2,3" and array stored as serialized?
                            $ids = array_values( array_filter( array_map( '\absint', preg_split( '/\s*,\s*/', $raw ) ?: [] ) ) );
                        }
                    }
                    $ids = array_slice( $ids, 0, 80 );
                    $raw_value = implode( ',', $ids );
                    echo '<div class="jetsync-media-field jetsync-media-gallery">';
                    echo '<input class="jetsync-media-value" type="hidden" id="' . \esc_attr( $field_name ) . '" name="' . \esc_attr( $field_name ) . '" value="' . \esc_attr( $raw_value ) . '">';
                    echo '<div class="jetsync-gallery-grid">';
                    foreach ( $ids as $attachment_id ) {
                        $thumb = \wp_get_attachment_image_url( (int) $attachment_id, 'thumbnail' );
                        if ( is_string( $thumb ) && '' !== $thumb ) {
                            echo '<div class="jetsync-gallery-item" data-id="' . \esc_attr( (string) $attachment_id ) . '"><img src="' . \esc_url( $thumb ) . '" alt="" /><span class="jetsync-gallery-remove" title="Remove">&times;</span></div>';
                        }
                    }
                    if ( empty( $ids ) ) {
                        echo '<div class="jetsync-gallery-empty">No images selected.</div>';
                    }
                    echo '</div>';
                    echo '<div class="jetsync-media-actions">';
                    echo '<button type="button" class="button jetsync-media-select jetsync-btn-dark" data-multiple="1">CHOOSE MEDIA</button>';
                    echo '<button type="button" class="button jetsync-media-clear">Clear</button>';
                    echo '</div>';
                    echo '</div>';
                    break;

                case 'date':
                    echo '<input type="date" id="' . \esc_attr( $field_name ) . '" name="' . \esc_attr( $field_name ) . '" value="' . \esc_attr( $this->stringify_field_value( $value ) ) . '" class="regular-text">';
                    break;

                case 'url':
                    echo '<input type="url" id="' . \esc_attr( $field_name ) . '" name="' . \esc_attr( $field_name ) . '" value="' . \esc_attr( $this->stringify_field_value( $value ) ) . '" class="large-text jetsync-url-input" placeholder="https://">';
                    break;

                case 'text':
                default:
                    echo '<input type="text" id="' . \esc_attr( $field_name ) . '" name="' . \esc_attr( $field_name ) . '" value="' . \esc_attr( $this->stringify_field_value( $value ) ) . '" class="large-text">';
                    break;
            }

            if ( ! empty( $field_desc ) ) {
                echo '<p class="description jetsync-field-desc">' . \esc_html( $field_desc ) . '</p>';
            }
            echo '</div>';
        }

        echo '</div>';
        // clearfix for WP postbox
        echo '<div style="clear:both;"></div>';
    }

    /**
     * Callback for 'save_post' action. Sanitizes and persists metadata to DB.
     *
     * @param int $post_id Current post identifier.
     * @param \WP_Post $post Current post object.
     * @return void
     */
    public function save_meta_boxes_data( int $post_id, \WP_Post $post ) : void {
        // Disables saves during autosaves, revisions, or bulk updates
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( \wp_is_post_revision( $post_id ) ) {
            return;
        }

        $meta_boxes = $this->registry->get_all();

        foreach ( $meta_boxes as $metabox ) {
            if ( ! $metabox->is_active() ) {
                continue;
            }

            // Verify nonce
            $nonce_name = 'jetsync_metabox_nonce_' . $metabox->get_id();
            $nonce_action = 'jetsync_save_metabox_' . $metabox->get_id();

            if ( ! isset( $_POST[ $nonce_name ] ) || ! \wp_verify_nonce( $_POST[ $nonce_name ], $nonce_action ) ) {
                continue;
            }

            // Capability checks
            if ( ! \current_user_can( 'edit_post', $post_id ) ) {
                continue;
            }

            $fields = $metabox->get_fields();
            foreach ( $fields as $field ) {
                $field_name = $field->get_name();
                $field_type = $field->get_type();

                if ( 'checkbox' === $field_type ) {
                    // Checkboxes send arrays
                    $posted_val = isset( $_POST[ $field_name ] ) ? (array) $_POST[ $field_name ] : [];
                    $sanitized = array_map( '\sanitize_text_field', $posted_val );
                    \update_post_meta( $post_id, $field_name, $sanitized );
                } elseif ( 'switcher' === $field_type ) {
                    // Switchers send '1' if active, else they are missing in $_POST
                    $val = isset( $_POST[ $field_name ] ) ? 'true' : 'false';
                    \update_post_meta( $post_id, $field_name, $val );
                } elseif ( 'media' === $field_type ) {
                    $id = isset( $_POST[ $field_name ] ) ? \absint( $_POST[ $field_name ] ) : 0;
                    if ( $id > 0 ) {
                        \update_post_meta( $post_id, $field_name, (string) $id );
                        $this->sync_featured_image_from_field_value( $post_id, $field_name, $id );
                    } else {
                        \delete_post_meta( $post_id, $field_name );
                        $this->sync_featured_image_from_field_value( $post_id, $field_name, '' );
                    }
                } elseif ( 'gallery' === $field_type ) {
                    $raw = isset( $_POST[ $field_name ] ) ? (string) $_POST[ $field_name ] : '';
                    $raw = \sanitize_text_field( $raw );
                    $ids = [];
                    if ( '' !== $raw ) {
                        $ids = array_values( array_filter( array_map( '\absint', preg_split( '/\\s*,\\s*/', $raw ) ?: [] ) ) );
                    }
                    $raw_value = implode( ',', $ids );
                    if ( '' !== $raw_value ) {
                        \update_post_meta( $post_id, $field_name, $raw_value );
                    } else {
                        \delete_post_meta( $post_id, $field_name );
                    }
                    $this->sync_featured_image_from_field_value( $post_id, $field_name, $raw_value );
                } else {
                    if ( isset( $_POST[ $field_name ] ) ) {
                        $raw_val = $_POST[ $field_name ];
                        
                        if ( 'wysiwyg' === $field_type ) {
                            $sanitized = \wp_kses_post( $raw_val );
                        } elseif ( 'textarea' === $field_type ) {
                            $sanitized = \sanitize_textarea_field( $raw_val );
                        } elseif ( 'url' === $field_type ) {
                            $sanitized = \esc_url_raw( (string) $raw_val );
                        } else {
                            $sanitized = \sanitize_text_field( $raw_val );
                        }

                        \update_post_meta( $post_id, $field_name, $sanitized );
                        $this->sync_featured_image_from_field_value( $post_id, $field_name, $sanitized );
                    } else {
                        // Delete if empty text/select was unset
                        \delete_post_meta( $post_id, $field_name );
                        $this->sync_featured_image_from_field_value( $post_id, $field_name, '' );
                    }
                }
            }
        }
    }

    /**
     * @return array{off:string,on:string}
     */
    private function get_switcher_labels( MetaFieldDefinition $field ) : array {
        $labels = [
            'off' => 'No',
            'on'  => 'Yes',
        ];

        foreach ( $field->get_options() as $option ) {
            if ( ! is_array( $option ) ) {
                continue;
            }

            $value = isset( $option['value'] ) ? strtolower( trim( (string) $option['value'] ) ) : '';
            $label = isset( $option['label'] ) ? trim( (string) $option['label'] ) : '';
            if ( '' === $value || '' === $label ) {
                continue;
            }

            if ( in_array( $value, [ '1', 'true', 'yes', 'on' ], true ) ) {
                $labels['on'] = $label;
            }

            if ( in_array( $value, [ '0', 'false', 'no', 'off' ], true ) ) {
                $labels['off'] = $label;
            }
        }

        return $labels;
    }
}
