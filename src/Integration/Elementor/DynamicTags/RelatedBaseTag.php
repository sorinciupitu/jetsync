<?php
declare( strict_types=1 );

namespace JetSync\Integration\Elementor\DynamicTags;

use JetSync\Core\JetSync;
use JetSync\Registry\RelationRegistry;
use JetSync\Registry\Model\RelationDefinition;
use JetSync\Runtime\RelationsEngine;

abstract class RelatedBaseTag extends \Elementor\Core\DynamicTags\Data_Tag {
    public function get_group() : string {
        return 'jetsync';
    }

    public function get_panel_template_setting_key() : string {
        return 'relations';
    }

    protected function register_controls() : void {
        $this->add_control(
            'relations',
            [
                'label'       => \esc_html__( 'Relations', 'jetsync' ),
                'type'        => \Elementor\Controls_Manager::SELECT2,
                'multiple'    => true,
                'options'     => $this->get_relation_options(),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'index',
            [
                'label'   => \esc_html__( 'Index', 'jetsync' ),
                'type'    => \Elementor\Controls_Manager::NUMBER,
                'default' => 0,
                'min'     => 0,
                'step'    => 1,
            ]
        );
    }

    protected function get_relation_options() : array {
        $registry = $this->get_relation_registry();
        if ( ! $registry ) {
            return [];
        }

        $out = [];
        foreach ( $registry->get_all() as $slug => $def ) {
            if ( ! $def instanceof RelationDefinition ) {
                continue;
            }
            $out[ (string) $slug ] = $def->get_title() !== '' ? $def->get_title() : (string) $slug;
        }

        return $out;
    }

    protected function get_related_post_id( array $options = [] ) : int {
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

        if ( $post_id <= 0 ) {
            return 0;
        }

        $relations = $this->get_settings( 'relations' );
        if ( is_string( $relations ) ) {
            $relations = array_filter( array_map( 'trim', explode( ',', $relations ) ) );
        }
        if ( ! is_array( $relations ) || empty( $relations ) ) {
            return 0;
        }

        $index = (int) $this->get_settings( 'index' );
        if ( $index < 0 ) {
            $index = 0;
        }

        $engine = $this->get_relations_engine();
        if ( ! $engine ) {
            return 0;
        }

        $registry = $this->get_relation_registry();
        $post_type = (string) \get_post_type( $post_id );

        foreach ( $relations as $rel_id ) {
            $rel_id = \sanitize_key( (string) $rel_id );
            if ( '' === $rel_id ) {
                continue;
            }

            $role = 'parent';
            if ( $registry ) {
                $def = $registry->get( $rel_id );
                if ( $def instanceof RelationDefinition ) {
                    $role = $this->resolve_role( $def, $post_type );
                }
            }

            $ids = $engine->get_related_items( $rel_id, $post_id, $role );
            if ( empty( $ids ) ) {
                continue;
            }

            $picked = $ids[ $index ] ?? $ids[0] ?? 0;
            $picked = (int) $picked;
            if ( $picked > 0 ) {
                return $picked;
            }
        }

        return 0;
    }

    protected function resolve_role( RelationDefinition $def, string $post_type ) : string {
        $parent = $def->get_parent_object();
        $child  = $def->get_child_object();

        if ( $post_type !== '' ) {
            if ( $parent !== 'post' && $post_type === $parent ) {
                return 'parent';
            }
            if ( $child !== 'post' && $post_type === $child ) {
                return 'child';
            }
            if ( $post_type === $parent ) {
                return 'parent';
            }
            if ( $post_type === $child ) {
                return 'child';
            }
        }

        if ( $parent === 'post' && $child !== 'post' ) {
            return 'parent';
        }

        if ( $child === 'post' && $parent !== 'post' ) {
            return 'child';
        }

        return 'parent';
    }

    protected function get_relation_registry() : ?RelationRegistry {
        $container = JetSync::get_instance();
        $registry = $container->get( 'relation_registry' );
        return $registry instanceof RelationRegistry ? $registry : null;
    }

    protected function get_relations_engine() : ?RelationsEngine {
        $container = JetSync::get_instance();
        $engine = $container->get( 'relations_engine' );
        return $engine instanceof RelationsEngine ? $engine : null;
    }
}
