<?php
declare( strict_types=1 );

namespace JetSync\Runtime;

use JetSync\Core\Logger;
use JetSync\Registry\RelationRegistry;
use JetSync\Registry\Model\RelationDefinition;

class RelationsMetaBoxManager {
    public function __construct(
        private RelationRegistry $relation_registry,
        private RelationsEngine $relations_engine,
        private ?Logger $logger = null
    ) {
        \add_action( 'add_meta_boxes', [ $this, 'register_relation_meta_boxes' ], 10, 2 );
        \add_action( 'save_post', [ $this, 'save_relation_meta_boxes' ], 10, 2 );
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
        // Check if any relation applies to this post type
        $has_rel = false;
        foreach ( $this->relation_registry->get_all() as $rel ) {
            if ( ! $rel instanceof RelationDefinition ) {
                continue;
            }
            if ( ! $rel->is_active() ) {
                continue;
            }
            $parent = \sanitize_key( $rel->get_parent_object() );
            $child  = \sanitize_key( $rel->get_child_object() );
            if ( $screen->post_type === $parent || $screen->post_type === $child ) {
                $has_rel = true;
                break;
            }
        }
        if ( ! $has_rel ) {
            return;
        }
        \wp_enqueue_style(
            'jet-sync-metabox',
            JETSYNC_URL . 'assets/css/admin-metabox.css',
            [],
            JETSYNC_VERSION
        );
        \wp_enqueue_script(
            'jet-sync-metabox-ui',
            JETSYNC_URL . 'assets/js/metabox-ui.js',
            [ 'jquery' ],
            JETSYNC_VERSION,
            true
        );
    }

    public function register_relation_meta_boxes( string $post_type, \WP_Post $post ) : void {
        $post_type = \sanitize_key( $post_type );
        if ( '' === $post_type ) {
            return;
        }

        foreach ( $this->relation_registry->get_all() as $rel ) {
            if ( ! $rel instanceof RelationDefinition ) {
                continue;
            }
            if ( ! $rel->is_active() ) {
                continue;
            }

            $parent_type = \sanitize_key( $rel->get_parent_object() );
            $child_type  = \sanitize_key( $rel->get_child_object() );
            if ( '' === $parent_type || '' === $child_type ) {
                continue;
            }

            if ( $post_type !== $parent_type && $post_type !== $child_type ) {
                continue;
            }

            $is_parent_screen = $post_type === $parent_type;
            $other_type = $is_parent_screen ? $child_type : $parent_type;

            $other_label = $other_type;
            $pto = \get_post_type_object( $other_type );
            if ( is_object( $pto ) && isset( $pto->labels->name ) ) {
                $other_label = (string) $pto->labels->name;
            }

            $title = $is_parent_screen ? ( 'Children ' . $other_label ) : ( 'Parent ' . $other_label );
            $mode = $is_parent_screen ? 'children' : 'parents';

            \add_meta_box(
                'jetsync_rel_' . $rel->get_id() . '_' . $mode,
                $title,
                [ $this, 'render_relation_meta_box' ],
                $post_type,
                'normal',
                'default',
                [
                    'rel_id'       => $rel->get_id(),
                    'mode'         => $mode,
                    'parent_type'  => $parent_type,
                    'child_type'   => $child_type,
                    'other_type'   => $other_type,
                    'other_label'  => $other_label,
                ]
            );
        }
    }

    /**
     * @param array{args:array{rel_id:string,mode:string,parent_type:string,child_type:string,other_type:string,other_label:string}} $callback_args
     */
    public function render_relation_meta_box( \WP_Post $post, array $callback_args ) : void {
        $args = $callback_args['args'] ?? [];
        $rel_id = isset( $args['rel_id'] ) ? (string) $args['rel_id'] : '';
        $mode = isset( $args['mode'] ) ? (string) $args['mode'] : '';
        $other_type = isset( $args['other_type'] ) ? (string) $args['other_type'] : '';
        $other_label = isset( $args['other_label'] ) ? (string) $args['other_label'] : $other_type;

        if ( '' === $rel_id || ( 'parents' !== $mode && 'children' !== $mode ) || '' === $other_type ) {
            return;
        }

        $nonce_action = 'jetsync_save_relation_' . $rel_id . '_' . $mode;
        $nonce_name = 'jetsync_relation_nonce_' . $rel_id . '_' . $mode;
        \wp_nonce_field( $nonce_action, $nonce_name );

        $selected_ids = [];
        if ( 'parents' === $mode ) {
            $selected_ids = $this->relations_engine->get_related_items( $rel_id, (int) $post->ID, 'child' );
        } else {
            $selected_ids = $this->relations_engine->get_related_items( $rel_id, (int) $post->ID, 'parent' );
        }

        $selected_ids = array_values( array_filter( array_map( 'intval', $selected_ids ) ) );

        $q = new \WP_Query(
            [
                'post_type'      => $other_type,
                'posts_per_page' => 200,
                'post_status'    => 'any',
                'orderby'        => 'title',
                'order'          => 'ASC',
                'no_found_rows'  => true,
            ]
        );

        $options = [];
        if ( is_array( $q->posts ) ) {
            foreach ( $q->posts as $p ) {
                if ( $p instanceof \WP_Post ) {
                    $options[ (int) $p->ID ] = (string) $p->post_title;
                }
            }
        }

        foreach ( $selected_ids as $id ) {
            if ( isset( $options[ $id ] ) ) {
                continue;
            }
            $p = \get_post( (int) $id );
            if ( $p instanceof \WP_Post ) {
                $options[ (int) $p->ID ] = (string) $p->post_title;
            }
        }

        $field_name = 'jetsync_rel_' . $rel_id . '_' . $mode;
        $is_children = 'children' === $mode;
        $add_new_url = \admin_url( 'post-new.php?post_type=' . $other_type );
        $connect_label = $is_children ? ( 'Connect ' . $other_label ) : ( 'Connect ' . $other_label );
        $add_new_label = 'Add New ' . $other_label;

        // Use JS-synced hidden select + visible table like JetEngine model
        echo '<div class="jetsync-rel-box jetsync-rel-' . \esc_attr( $mode ) . '" data-rel-id="' . \esc_attr( $rel_id ) . '" data-mode="' . \esc_attr( $mode ) . '" data-field="' . \esc_attr( $field_name ) . '">';

        // Top actions like model.jpeg: Add New + Connect
        echo '<div class="jetsync-rel-actions">';
        if ( $is_children ) {
            echo '<a href="' . \esc_url( $add_new_url ) . '" class="button jetsync-rel-add-new" target="_blank" rel="noopener">' . \esc_html( $add_new_label ) . '</a>';
        }
        echo '<button type="button" class="button jetsync-rel-connect-toggle">' . \esc_html( $connect_label ) . '</button>';
        echo '</div>';

        // Table like model: Title | Actions with Edit/View/Disconnect
        echo '<table class="jetsync-rel-table widefat">';
        echo '<thead><tr><th>Title</th><th style="width:230px; text-align:center;">Actions</th></tr></thead>';
        echo '<tbody class="jetsync-rel-tbody">';

        if ( empty( $selected_ids ) ) {
            echo '<tr class="jetsync-rel-empty-row"><td colspan="2" style="text-align:center; color:#64748b; padding:0.75rem;">--</td></tr>';
        } else {
            foreach ( $selected_ids as $id ) {
                $title = isset( $options[ $id ] ) ? $options[ $id ] : ( '#' . $id );
                $display = '' !== $title ? $title : ( '#' . $id );
                $edit_url = \get_edit_post_link( (int) $id, '' );
                $view_url = \get_permalink( (int) $id );
                echo '<tr data-id="' . \esc_attr( (string) $id ) . '">';
                echo '<td class="jetsync-rel-title">' . \esc_html( $display ) . '</td>';
                echo '<td class="jetsync-rel-row-actions" style="text-align:center;">';
                if ( is_string( $edit_url ) && '' !== $edit_url ) {
                    echo '<a href="' . \esc_url( $edit_url ) . '" class="button button-small jetsync-btn-edit" target="_blank" rel="noopener"><span class="dashicons dashicons-edit" style="font-size:14px; line-height:1;"></span> Edit</a> ';
                }
                if ( is_string( $view_url ) && '' !== $view_url ) {
                    echo '<a href="' . \esc_url( $view_url ) . '" class="button button-small jetsync-btn-view" target="_blank" rel="noopener"><span class="dashicons dashicons-visibility" style="font-size:14px; line-height:1;"></span> View</a> ';
                }
                echo '<button type="button" class="button button-small jetsync-btn-disconnect" data-id="' . \esc_attr( (string) $id ) . '"><span class="dashicons dashicons-dismiss" style="font-size:14px; line-height:1;"></span> Disconnect</button>';
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '<tfoot><tr><th>Title</th><th style="text-align:center;">Actions</th></tr></tfoot>';
        echo '</table>';

        // Connect panel (hidden by default) with search + select
        echo '<div class="jetsync-rel-connect-panel" style="display:none; margin-top:1rem; padding:1rem; border:1px solid #e5e7eb; border-radius:6px; background:#f9fafb;">';
        echo '<p class="description" style="margin:0 0 0.5rem;">Select one or more ' . \esc_html( $other_label ) . ' to connect. Hold Ctrl/Cmd for multiple.</p>';
        echo '<input type="text" class="jetsync-rel-search large-text" placeholder="Search..." style="margin-bottom:0.5rem;" />';
        echo '<select class="jetsync-rel-connect-select" multiple="multiple" size="8" style="width:100%;">';
        foreach ( $options as $id => $label ) {
            if ( in_array( (int) $id, $selected_ids, true ) ) {
                continue;
            }
            $display = '' !== $label ? $label : ( '#' . (int) $id );
            echo '<option value="' . \esc_attr( (string) $id ) . '">' . \esc_html( $display ) . '</option>';
        }
        echo '</select>';
        echo '<div style="margin-top:0.5rem; display:flex; gap:0.5rem;">';
        echo '<button type="button" class="button button-primary jetsync-rel-do-connect">Add selected</button>';
        echo '<button type="button" class="button jetsync-rel-cancel-connect">Cancel</button>';
        echo '</div>';
        echo '</div>';

        // Hidden actual submission select (kept for form submit, synced via JS)
        echo '<select name="' . \esc_attr( $field_name ) . '[]" multiple="multiple" class="jetsync-rel-hidden-select" style="display:none;">';
        foreach ( $options as $id => $label ) {
            $selected = in_array( (int) $id, $selected_ids, true ) ? ' selected="selected"' : '';
            $display = '' !== $label ? $label : ( '#' . (int) $id );
            echo '<option value="' . \esc_attr( (string) $id ) . '"' . $selected . '>' . \esc_html( $display ) . '</option>';
        }
        // Ensure missing selected ids still exist as option
        foreach ( $selected_ids as $id ) {
            if ( ! isset( $options[ $id ] ) ) {
                $p = \get_post( (int) $id );
                $label = $p instanceof \WP_Post ? $p->post_title : ( '#' . $id );
                echo '<option value="' . \esc_attr( (string) $id ) . '" selected="selected">' . \esc_html( $label ) . '</option>';
            }
        }
        echo '</select>';

        echo '</div>';
    }

    public function save_relation_meta_boxes( int $post_id, \WP_Post $post ) : void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( \wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( ! \current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $post_type = \sanitize_key( (string) $post->post_type );
        if ( '' === $post_type ) {
            return;
        }

        foreach ( $this->relation_registry->get_all() as $rel ) {
            if ( ! $rel instanceof RelationDefinition ) {
                continue;
            }
            if ( ! $rel->is_active() ) {
                continue;
            }

            $parent_type = \sanitize_key( $rel->get_parent_object() );
            $child_type  = \sanitize_key( $rel->get_child_object() );
            if ( '' === $parent_type || '' === $child_type ) {
                continue;
            }

            $mode = null;
            if ( $post_type === $parent_type ) {
                $mode = 'children';
            } elseif ( $post_type === $child_type ) {
                $mode = 'parents';
            } else {
                continue;
            }

            $nonce_action = 'jetsync_save_relation_' . $rel->get_id() . '_' . $mode;
            $nonce_name = 'jetsync_relation_nonce_' . $rel->get_id() . '_' . $mode;
            if ( ! isset( $_POST[ $nonce_name ] ) || ! \wp_verify_nonce( $_POST[ $nonce_name ], $nonce_action ) ) {
                continue;
            }

            $field_name = 'jetsync_rel_' . $rel->get_id() . '_' . $mode;
            $posted = isset( $_POST[ $field_name ] ) ? (array) $_POST[ $field_name ] : [];
            $ids = array_values( array_filter( array_map( '\absint', $posted ) ) );

            if ( 'children' === $mode ) {
                $this->relations_engine->set_related_items_typed(
                    $rel->get_id(),
                    (int) $post_id,
                    $ids,
                    $parent_type,
                    $child_type
                );
            } else {
                $this->relations_engine->set_parent_items_typed(
                    $rel->get_id(),
                    (int) $post_id,
                    $ids,
                    $parent_type,
                    $child_type
                );
            }
        }
    }
}

