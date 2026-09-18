<?php
declare( strict_types=1 );

namespace JetSync\Registry;

use JetSync\Registry\Model\PostTypeDefinition;

/**
 * Registry to store and query Custom Post Type definitions.
 */
class PostTypeRegistry extends BaseRegistry {

    /**
     * Constructor. Specifies the CPT registry option name.
     */
    public function __construct() {
        parent::__construct( 'jetsync_cpt_registry' );
    }

    /**
     * {@inheritdoc}
     */
    protected function get_model_class() : string {
        return PostTypeDefinition::class;
    }
}
