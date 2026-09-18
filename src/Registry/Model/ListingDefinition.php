<?php
declare( strict_types=1 );

namespace JetSync\Registry\Model;

class ListingDefinition implements DefinitionInterface {

    public function __construct(
        private string $id,
        private string $title,
        private string $post_type,
        private int $posts_per_page = 10,
        private string $orderby = 'date',
        private string $order = 'DESC',
        private array $selected_fields = [ 'title', 'permalink' ],
        private string $template = '',
        private bool $active = true
    ) {
        $this->id = $this->clean_key( $id );
        $this->post_type = $this->clean_key( $post_type );
        $this->selected_fields = $this->sanitize_selected_fields( $selected_fields );
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

    public function get_post_type() : string {
        return $this->post_type;
    }

    public function get_posts_per_page() : int {
        return max( 1, min( 100, (int) $this->posts_per_page ) );
    }

    public function get_orderby() : string {
        return $this->orderby !== '' ? $this->orderby : 'date';
    }

    public function get_order() : string {
        return strtoupper( $this->order ) === 'ASC' ? 'ASC' : 'DESC';
    }

    /**
     * @return array<string>
     */
    public function get_selected_fields() : array {
        return $this->selected_fields;
    }

    public function get_template() : string {
        return $this->template;
    }

    public function is_active() : bool {
        return $this->active;
    }

    public function get_args() : array {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'post_type'      => $this->post_type,
            'posts_per_page' => $this->get_posts_per_page(),
            'orderby'        => $this->get_orderby(),
            'order'          => $this->get_order(),
            'selected_fields'=> $this->get_selected_fields(),
            'template'       => $this->get_template(),
        ];
    }

    public function to_array() : array {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'post_type'      => $this->post_type,
            'posts_per_page' => $this->posts_per_page,
            'orderby'        => $this->orderby,
            'order'          => $this->order,
            'selected_fields'=> $this->selected_fields,
            'template'       => $this->template,
            'active'         => $this->active,
        ];
    }

    public static function from_array( array $data ) : self {
        return new self(
            id: (string) ( $data['id'] ?? '' ),
            title: (string) ( $data['title'] ?? '' ),
            post_type: (string) ( $data['post_type'] ?? 'post' ),
            posts_per_page: (int) ( $data['posts_per_page'] ?? 10 ),
            orderby: (string) ( $data['orderby'] ?? 'date' ),
            order: (string) ( $data['order'] ?? 'DESC' ),
            selected_fields: (array) ( $data['selected_fields'] ?? [ 'title', 'permalink' ] ),
            template: (string) ( $data['template'] ?? '' ),
            active: (bool) ( $data['active'] ?? true )
        );
    }

    /**
     * @param array<mixed> $selected_fields
     * @return array<string>
     */
    private function sanitize_selected_fields( array $selected_fields ) : array {
        $out = [];
        foreach ( $selected_fields as $f ) {
            $f = (string) $f;
            $f = trim( $f );
            if ( '' === $f ) {
                continue;
            }

            if ( in_array( $f, [ 'title', 'permalink', 'excerpt' ], true ) ) {
                $out[] = $f;
                continue;
            }

            if ( str_starts_with( $f, 'field:' ) ) {
                $key = substr( $f, 6 );
                $key = $this->clean_key( $key );
                if ( '' !== $key ) {
                    $out[] = 'field:' . $key;
                }
            }
        }

        $out = array_values( array_unique( $out ) );
        if ( empty( $out ) ) {
            return [ 'title', 'permalink' ];
        }

        return $out;
    }

    private function clean_key( string $key ) : string {
        $key = strtolower( $key );
        $key = preg_replace( '/[^a-z0-9_\-]/', '', $key );
        return is_string( $key ) ? $key : '';
    }
}
