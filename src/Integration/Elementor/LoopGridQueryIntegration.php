<?php
declare( strict_types=1 );

namespace JetSync\Integration\Elementor;

use JetSync\Core\JetSync;
use JetSync\Registry\Model\QueryDefinition;
use JetSync\Registry\Model\RelationDefinition;
use JetSync\Registry\QueryRegistry;
use JetSync\Registry\RelationRegistry;
use JetSync\Runtime\RelationsEngine;

class LoopGridQueryIntegration {

    public function __construct() {
        if ( ! \class_exists( '\Elementor\Plugin' ) ) {
            return;
        }

        \add_action( 'init', [ $this, 'register_query_hooks' ], 20 );
    }

    public function register_query_hooks() : void {
        $query_registry = $this->get_query_registry();
        if ( ! $query_registry ) {
            return;
        }

        foreach ( $query_registry->get_all() as $query ) {
            if ( ! $query instanceof QueryDefinition || ! $query->is_active() ) {
                continue;
            }

            $query_id = $query->get_id();
            if ( '' === $query_id ) {
                continue;
            }

            \add_action(
                'elementor/query/' . $query_id,
                function( $wp_query ) use ( $query ) : void {
                    $this->apply_query_definition( $wp_query, $query );
                }
            );

            \add_action(
                'elementor_pro/posts/query/' . $query_id,
                function( $wp_query ) use ( $query ) : void {
                    $this->apply_query_definition( $wp_query, $query );
                }
            );
        }
    }

    public function apply_query_definition( $wp_query, QueryDefinition $definition ) : void {
        if ( ! $wp_query instanceof \WP_Query ) {
            return;
        }

        $current_post_id = $this->resolve_current_post_id();
        if ( $current_post_id <= 0 ) {
            $this->force_empty_query( $wp_query, $definition );
            return;
        }

        $current_post_type = (string) \get_post_type( $current_post_id );
        if ( ! $definition->applies_to_post_type( $current_post_type ) ) {
            $this->force_empty_query( $wp_query, $definition );
            return;
        }

        $engine = $this->get_relations_engine();
        $relation_registry = $this->get_relation_registry();
        if ( ! $engine || ! $relation_registry ) {
            $this->force_empty_query( $wp_query, $definition );
            return;
        }

        $relation = $relation_registry->get( $definition->get_relation_id() );
        if ( ! $relation instanceof RelationDefinition || ! $relation->is_active() ) {
            $this->force_empty_query( $wp_query, $definition );
            return;
        }

        $role = $this->resolve_role( $relation, $current_post_type );
        $related_ids = $engine->get_related_items( $relation->get_id(), $current_post_id, $role );
        $related_ids = array_values( array_filter( array_map( 'intval', $related_ids ) ) );

        $this->reset_to_collection_query( $wp_query );
        $wp_query->set( 'post_type', $definition->get_target_post_type() );
        $wp_query->set( 'post_status', 'publish' );
        $wp_query->set( 'ignore_sticky_posts', true );
        $wp_query->set( 'posts_per_page', $definition->get_posts_per_page() );
        $wp_query->set( 'post__in', ! empty( $related_ids ) ? $related_ids : [ 0 ] );

        if ( 'post__in' === $definition->get_orderby() ) {
            $wp_query->set( 'orderby', 'post__in' );
            $wp_query->set( 'order', 'ASC' );
            return;
        }

        $wp_query->set( 'orderby', $definition->get_orderby() );
        $wp_query->set( 'order', $definition->get_order() );
    }

    private function resolve_current_post_id() : int {
        $post_id = $this->normalize_runtime_post_id( (int) \get_queried_object_id() );
        if ( $post_id > 0 ) {
            return $post_id;
        }

        $post_id = $this->normalize_runtime_post_id( (int) \get_the_ID() );
        if ( $post_id > 0 ) {
            return $post_id;
        }

        if ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ) {
            $post_id = $this->normalize_runtime_post_id( (int) $GLOBALS['post']->ID );
            if ( $post_id > 0 ) {
                return $post_id;
            }
        }

        foreach ( [ 'preview_id', 'preview', 'object_id', 'post_id', 'id', 'p' ] as $key ) {
            if ( empty( $_GET[ $key ] ) ) {
                continue;
            }

            $post_id = $this->normalize_runtime_post_id( (int) \absint( $_GET[ $key ] ) );
            if ( $post_id > 0 ) {
                return $post_id;
            }
        }

        return 0;
    }

    private function resolve_role( RelationDefinition $relation, string $current_post_type ) : string {
        $parent = $relation->get_parent_object();
        $child = $relation->get_child_object();

        if ( '' !== $current_post_type ) {
            if ( $current_post_type === $parent ) {
                return 'parent';
            }

            if ( $current_post_type === $child ) {
                return 'child';
            }
        }

        if ( 'post' === $parent && 'post' !== $child ) {
            return 'parent';
        }

        if ( 'post' === $child && 'post' !== $parent ) {
            return 'child';
        }

        return 'parent';
    }

    private function force_empty_query( \WP_Query $wp_query, QueryDefinition $definition ) : void {
        $this->reset_to_collection_query( $wp_query );
        $wp_query->set( 'post_type', $definition->get_target_post_type() );
        $wp_query->set( 'post__in', [ 0 ] );
        $wp_query->set( 'posts_per_page', $definition->get_posts_per_page() );
    }

    private function reset_to_collection_query( \WP_Query $wp_query ) : void {
        foreach ( [
            'p',
            'page_id',
            'attachment_id',
            'name',
            'pagename',
            'attachment',
            'page',
            'preview',
            'error',
            'post_parent',
        ] as $query_var ) {
            $wp_query->set( $query_var, '' );
        }

        $wp_query->set( 'post_name__in', [] );
        $wp_query->set( 'post_parent__in', [] );
        $wp_query->set( 'post_parent__not_in', [] );
    }

    private function normalize_runtime_post_id( int $post_id ) : int {
        if ( $post_id <= 0 ) {
            return 0;
        }

        $post_type = (string) \get_post_type( $post_id );
        if ( 'elementor_library' === $post_type ) {
            return 0;
        }

        return $post_id;
    }

    private function get_query_registry() : ?QueryRegistry {
        $container = JetSync::get_instance();
        $registry = $container->get( 'query_registry' );
        return $registry instanceof QueryRegistry ? $registry : null;
    }

    private function get_relation_registry() : ?RelationRegistry {
        $container = JetSync::get_instance();
        $registry = $container->get( 'relation_registry' );
        return $registry instanceof RelationRegistry ? $registry : null;
    }

    private function get_relations_engine() : ?RelationsEngine {
        $container = JetSync::get_instance();
        $engine = $container->get( 'relations_engine' );
        return $engine instanceof RelationsEngine ? $engine : null;
    }
}
