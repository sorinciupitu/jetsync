<?php
declare( strict_types=1 );

namespace JetSync\Registry;

use JetSync\Registry\Model\TaxonomyDefinition;

/**
 * Registry to store and query Custom Taxonomy definitions.
 */
class TaxonomyRegistry extends BaseRegistry {

    /**
     * Constructor. Specifies the Taxonomy registry option name.
     */
    public function __construct() {
        parent::__construct( 'jetsync_taxonomy_registry' );
    }

    /**
     * {@inheritdoc}
     */
    protected function get_model_class() : string {
        return TaxonomyDefinition::class;
    }
}
