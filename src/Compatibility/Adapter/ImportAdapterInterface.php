<?php
declare( strict_types=1 );

namespace JetSync\Compatibility\Adapter;

use JetSync\Registry\Model\DefinitionInterface;

/**
 * Interface for adapters converting third-party schema configurations.
 */
interface ImportAdapterInterface {

    /**
     * Translate raw third-party configuration into a JetSync definition model.
     *
     * @param array<string,mixed> $raw_data Raw database array.
     * @return DefinitionInterface|null Translated definition, or null if invalid.
     */
    public function translate( array $raw_data ) : ?DefinitionInterface;
}
