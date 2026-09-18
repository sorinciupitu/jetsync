<?php
declare( strict_types=1 );

namespace JetSync\Registry;

use JetSync\Registry\Model\RelationDefinition;

/**
 * Registry to store and query Custom Relationship schema configurations.
 */
class RelationRegistry extends BaseRegistry {

    /**
     * Constructor. Specifies the Relations registry option name.
     */
    public function __construct() {
        parent::__construct( 'jetsync_relations' );
    }

    /**
     * {@inheritdoc}
     */
    protected function get_model_class() : string {
        return RelationDefinition::class;
    }
}
