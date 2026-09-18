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
        echo '<div class="jetsync-rel-box">';
        echo '<select name="' . \esc_attr( $field_name ) . '[]" multiple="multiple" size="8" style="width:100%; max-width:100%;">';

        foreach ( $options as $id => $label ) {
            $selected = in_array( (int) $id, $selected_ids, true ) ? ' selected="selected"' : '';
            $display = '' !== $label ? $label : ( '#' . (int) $id );
            echo '<option value="' . \esc_attr( (string) $id ) . '"' . $selected . '>' . \esc_html( $display ) . '</option>';
        }

        echo '</select>';

        if ( ! empty( $selected_ids ) ) {
            echo '<div style="margin-top:0.75rem; display:flex; flex-wrap:wrap; gap:0.5rem;">';
            foreach ( $selected_ids as $id ) {
                $title = isset( $options[ $id ] ) ? $options[ $id ] : ( '#' . $id );
                $edit = \get_edit_post_link( (int) $id, '' );
                if ( is_string( $edit ) && '' !== $edit ) {
                    echo '<a class="button button-small" href="' . \esc_url( $edit ) . '">' . \esc_html( $title ) . '</a>';
                } else {
                    echo '<span class="button button-small" style="pointer-events:none;">' . \esc_html( $title ) . '</span>';
                }
            }
            echo '</div>';
        }

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

