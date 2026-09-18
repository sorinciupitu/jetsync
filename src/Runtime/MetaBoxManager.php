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
            foreach ( $metabox->get_fields() as $field ) {
                $t = $field->get_type();
                if ( 'media' === $t || 'gallery' === $t ) {
                    $needs_media = true;
                    break 2;
                }
            }
        }

        if ( ! $needs_media ) {
            return;
        }

        \wp_enqueue_media();
        \wp_enqueue_script(
            'jet-sync-metabox-media',
            JETSYNC_URL . 'assets/js/metabox-media.js',
            [ 'jquery' ],
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

        // Output nonce for security verification
        $nonce_name = 'jetsync_metabox_nonce_' . $metabox->get_id();
        $nonce_action = 'jetsync_save_metabox_' . $metabox->get_id();
        \wp_nonce_field( $nonce_action, $nonce_name );

        $fields = $metabox->get_fields();

        echo '<div class="jetsync-meta-box-container">';

        foreach ( $fields as $field ) {
            $field_name = $field->get_name();
            $field_title = $field->get_title();
            $field_type = $field->get_type();
            $field_desc = $field->get_description();
            $default = $field->get_default_value();

            // Fetch stored meta value (fallback to default)
            $stored_val = \get_post_meta( $post->ID, $field_name, true );
            $value = ( $stored_val !== '' ) ? $stored_val : $default;

            echo '<div class="jetsync-meta-row" style="margin-bottom:1.5rem; padding-bottom:1.5rem; border-bottom:1px solid #f0f0f0;">';
            echo '<label style="display:block; font-weight:600; margin-bottom:0.5rem; color:#2c3338;" for="' . \esc_attr( $field_name ) . '">' . \esc_html( $field_title ) . '</label>';

            switch ( $field_type ) {
                case 'textarea':
                    echo '<textarea id="' . \esc_attr( $field_name ) . '" name="' . \esc_attr( $field_name ) . '" class="large-text" rows="4">' . \esc_textarea( $this->stringify_field_value( $value ) ) . '</textarea>';
                    break;

                case 'select':
                    echo '<select id="' . \esc_attr( $field_name ) . '" name="' . \esc_attr( $field_name ) . '" class="postform">';
                    foreach ( $field->get_options() as $opt ) {
                        $selected = ( (string) $value === (string) $opt['value'] ) ? ' selected="selected"' : '';
                        echo '<option value="' . \esc_attr( $opt['value'] ) . '"' . $selected . '>' . \esc_html( $opt['label'] ) . '</option>';
                    }
                    echo '</select>';
                    break;

                case 'radio':
                    foreach ( $field->get_options() as $opt ) {
                        $checked = ( (string) $value === (string) $opt['value'] ) ? ' checked="checked"' : '';
                        echo '<label style="margin-right:1.5rem; font-weight:500;"><input type="radio" name="' . \esc_attr( $field_name ) . '" value="' . \esc_attr( $opt['value'] ) . '"' . $checked . '> ' . \esc_html( $opt['label'] ) . '</label>';
                    }
                    break;

                case 'checkbox':
                    // Checkboxes values are typically stored as arrays
                    $checkbox_values = is_array( $value ) ? $value : ( ( ! empty( $value ) ) ? (array) $value : [] );
                    foreach ( $field->get_options() as $opt ) {
                        $checked = in_array( (string) $opt['value'], array_map( 'strval', $checkbox_values ), true ) ? ' checked="checked"' : '';
                        echo '<label style="display:block; margin-bottom:0.25rem; font-weight:500;"><input type="checkbox" name="' . \esc_attr( $field_name ) . '[]" value="' . \esc_attr( $opt['value'] ) . '"' . $checked . '> ' . \esc_html( $opt['label'] ) . '</label>';
                    }
                    break;

                case 'switcher':
                    $switcher_labels = $this->get_switcher_labels( $field );
                    $is_checked = ( $value === 'true' || $value === '1' || $value === true || $value === 1 ) ? ' checked="checked"' : '';
                    echo '<label class="jetsync-switch-container" style="display:inline-flex; align-items:center; gap:0.75rem; cursor:pointer; font-weight:600;">';
                    echo '<span style="color:#64748b;">' . \esc_html( $switcher_labels['off'] ) . '</span>';
                    echo '<input type="checkbox" name="' . \esc_attr( $field_name ) . '" value="1"' . $is_checked . ' style="transform:scale(1.1);">';
                    echo '<span style="color:#16a34a;">' . \esc_html( $switcher_labels['on'] ) . '</span>';
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
                    $thumb = $attachment_id > 0 ? \wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
                    echo '<div class="jetsync-media-field">';
                    echo '<input class="jetsync-media-value" type="hidden" id="' . \esc_attr( $field_name ) . '" name="' . \esc_attr( $field_name ) . '" value="' . \esc_attr( (string) $attachment_id ) . '">';
                    echo '<div style="display:flex; gap:0.5rem; align-items:center; margin-bottom:0.75rem;">';
                    echo '<button type="button" class="button jetsync-media-select" data-multiple="0">Choose media</button>';
                    echo '<button type="button" class="button jetsync-media-clear">Clear</button>';
                    echo '</div>';
                    echo '<div class="jetsync-media-preview">';
                    if ( is_string( $thumb ) && '' !== $thumb ) {
                        echo '<img src="' . \esc_url( $thumb ) . '" style="width:120px; height:120px; object-fit:cover; border-radius:8px; border:1px solid #e5e7eb;" />';
                    }
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
                            $ids = array_values( array_filter( array_map( '\absint', explode( ',', $raw ) ) ) );
                        }
                    }
                    $ids = array_slice( $ids, 0, 80 );
                    $raw_value = implode( ',', $ids );
                    echo '<div class="jetsync-media-field">';
                    echo '<input class="jetsync-media-value" type="hidden" id="' . \esc_attr( $field_name ) . '" name="' . \esc_attr( $field_name ) . '" value="' . \esc_attr( $raw_value ) . '">';
                    echo '<div style="display:flex; gap:0.5rem; align-items:center; margin-bottom:0.75rem;">';
                    echo '<button type="button" class="button jetsync-media-select" data-multiple="1">Choose images</button>';
                    echo '<button type="button" class="button jetsync-media-clear">Clear</button>';
                    echo '</div>';
                    echo '<div class="jetsync-media-preview" style="display:flex; flex-wrap:wrap;">';
                    foreach ( $ids as $attachment_id ) {
                        $thumb = \wp_get_attachment_image_url( (int) $attachment_id, 'thumbnail' );
                        if ( is_string( $thumb ) && '' !== $thumb ) {
                            echo '<img src="' . \esc_url( $thumb ) . '" style="width:72px; height:72px; object-fit:cover; margin-right:8px; margin-bottom:8px; border-radius:6px; border:1px solid #e5e7eb;" />';
                        }
                    }
                    echo '</div>';
                    echo '</div>';
                    break;

                case 'date':
                    echo '<input type="date" id="' . \esc_attr( $field_name ) . '" name="' . \esc_attr( $field_name ) . '" value="' . \esc_attr( $this->stringify_field_value( $value ) ) . '" class="regular-text">';
                    break;

                case 'url':
                    echo '<input type="url" id="' . \esc_attr( $field_name ) . '" name="' . \esc_attr( $field_name ) . '" value="' . \esc_attr( $this->stringify_field_value( $value ) ) . '" class="large-text">';
                    break;

                case 'text':
                default:
                    echo '<input type="text" id="' . \esc_attr( $field_name ) . '" name="' . \esc_attr( $field_name ) . '" value="' . \esc_attr( $this->stringify_field_value( $value ) ) . '" class="large-text">';
                    break;
            }

            if ( ! empty( $field_desc ) ) {
                echo '<p class="description" style="margin-top:0.35rem; color:#64748b; font-style:italic;">' . \esc_html( $field_desc ) . '</p>';
            }
            echo '</div>';
        }

        echo '</div>';
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
