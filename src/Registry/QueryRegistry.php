<?php
declare( strict_types=1 );

namespace JetSync\Registry;

use JetSync\Registry\Model\QueryDefinition;

class QueryRegistry extends BaseRegistry {

    public function __construct() {
        parent::__construct( 'jetsync_queries' );
    }

    protected function get_model_class() : string {
        return QueryDefinition::class;
    }
}
