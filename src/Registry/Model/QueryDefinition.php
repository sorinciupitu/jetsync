<?php
declare( strict_types=1 );

namespace JetSync\Registry\Model;

class QueryDefinition implements DefinitionInterface {

    /**
     * @param array<string> $source_post_types
     */
    public function __construct(
        private string $id,
        private string $title,
        private string $relation_id,
        private string $target_post_type,
        private array $source_post_types = [],
        private int $posts_per_page = 10,
        private string $orderby = 'date',
        private string $order = 'DESC',
        private bool $active = true
    ) {
        $this->id = $this->clean_key( $id );
        $this->relation_id = $this->clean_key( $relation_id );
        $this->target_post_type = $this->clean_key( $target_post_type );
        $this->source_post_types = $this->sanitize_post_types( $source_post_types );
    }

    public function get_slug() : string {
        return $this->id;
    }

    public function get_id() : string {
        return $this->id;
    }

    public function get_title() : string {
        return $this->title;
    }

    public function get_relation_id() : string {
        return $this->relation_id;
    }

    public function get_target_post_type() : string {
        return $this->target_post_type;
    }

    /**
     * @return array<string>
     */
    public function get_source_post_types() : array {
        return $this->source_post_types;
    }

    public function get_posts_per_page() : int {
        $value = (int) $this->posts_per_page;
        if ( -1 === $value ) {
            return -1;
        }

        return max( 1, min( 100, $value ) );
    }

    public function get_orderby() : string {
        $allowed = [ 'date', 'title', 'menu_order', 'rand', 'modified', 'post__in' ];
        return in_array( $this->orderby, $allowed, true ) ? $this->orderby : 'date';
    }

    public function get_order() : string {
        return 'ASC' === strtoupper( $this->order ) ? 'ASC' : 'DESC';
    }

    public function is_active() : bool {
        return $this->active;
    }

    public function applies_to_post_type( string $post_type ) : bool {
        $post_type = $this->clean_key( $post_type );
        if ( '' === $post_type ) {
            return false;
        }

        if ( empty( $this->source_post_types ) ) {
            return true;
        }

        return in_array( $post_type, $this->source_post_types, true );
    }

    public function get_args() : array {
        return [
            'id'                => $this->id,
            'title'             => $this->title,
            'relation_id'       => $this->relation_id,
            'target_post_type'  => $this->target_post_type,
            'source_post_types' => $this->source_post_types,
            'posts_per_page'    => $this->get_posts_per_page(),
            'orderby'           => $this->get_orderby(),
            'order'             => $this->get_order(),
            'active'            => $this->active,
        ];
    }

    public function to_array() : array {
        return [
            'id'                => $this->id,
            'title'             => $this->title,
            'relation_id'       => $this->relation_id,
            'target_post_type'  => $this->target_post_type,
            'source_post_types' => $this->source_post_types,
            'posts_per_page'    => $this->posts_per_page,
            'orderby'           => $this->orderby,
            'order'             => $this->order,
            'active'            => $this->active,
        ];
    }

    public static function from_array( array $data ) : self {
        return new self(
            id: (string) ( $data['id'] ?? '' ),
            title: (string) ( $data['title'] ?? '' ),
            relation_id: (string) ( $data['relation_id'] ?? '' ),
            target_post_type: (string) ( $data['target_post_type'] ?? 'post' ),
            source_post_types: (array) ( $data['source_post_types'] ?? [] ),
            posts_per_page: (int) ( $data['posts_per_page'] ?? 10 ),
            orderby: (string) ( $data['orderby'] ?? 'date' ),
            order: (string) ( $data['order'] ?? 'DESC' ),
            active: (bool) ( $data['active'] ?? true )
        );
    }

    /**
     * @param array<mixed> $post_types
     * @return array<string>
     */
    private function sanitize_post_types( array $post_types ) : array {
        $clean = [];
        foreach ( $post_types as $post_type ) {
            $post_type = $this->clean_key( (string) $post_type );
            if ( '' !== $post_type ) {
                $clean[] = $post_type;
            }
        }

        return array_values( array_unique( $clean ) );
    }

    private function clean_key( string $key ) : string {
        $key = strtolower( $key );
        $key = preg_replace( '/[^a-z0-9_\-]/', '', $key );
        return is_string( $key ) ? $key : '';
    }
}
