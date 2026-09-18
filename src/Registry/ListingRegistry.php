<?php
declare( strict_types=1 );

namespace JetSync\Registry;

use JetSync\Registry\Model\ListingDefinition;

class ListingRegistry extends BaseRegistry {

    public function __construct() {
        parent::__construct( 'jetsync_listings' );
    }

    protected function get_model_class() : string {
        return ListingDefinition::class;
    }
}

