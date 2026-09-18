<?php
declare( strict_types=1 );

namespace JetSync\Registry\Model;

/**
 * Interface representing a definition (CPT, Taxonomy, relation, etc.)
 */
interface DefinitionInterface {

    /**
     * Get the item's unique slug identifier.
     *
     * @return string
     */
    public function get_slug() : string;

    /**
     * Get arguments formatted for WordPress registration functions.
     *
     * @return array<string,mixed>
     */
    public function get_args() : array;

    /**
     * Serialize model into standard associative array.
     *
     * @return array<string,mixed>
     */
    public function to_array() : array;

    /**
     * Instantiate model from data array.
     *
     * @param array<string,mixed> $data Data payload.
     * @return self
     */
    public static function from_array( array $data ) : self;

    /**
     * Checks if the definition is marked active.
     *
     * @return bool
     */
    public function is_active() : bool;
}
