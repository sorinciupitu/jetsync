<?php
declare( strict_types=1 );

namespace JetSync\Registry;

use JetSync\Registry\Model\DefinitionInterface;

/**
 * Base abstract class for registries storing definitions in wp_options.
 */
abstract class BaseRegistry {

    /**
     * Cache of definitions.
     *
     * @var array<string,DefinitionInterface>
     */
    protected array $definitions = [];

    /**
     * Constructor. Loads definitions from DB.
     *
     * @param string $option_name WordPress option name to use.
     */
    public function __construct( protected string $option_name ) {
        $this->load();
    }

    /**
     * Returns the model class string (e.g. PostTypeDefinition::class).
     *
     * @return string
     */
    abstract protected function get_model_class() : string;

    /**
     * Get all registered definitions.
     *
     * @return array<string,DefinitionInterface>
     */
    public function get_all() : array {
        return $this->definitions;
    }

    /**
     * Get a definition by slug.
     *
     * @param string $slug Unique definition key.
     * @return DefinitionInterface|null
     */
    public function get( string $slug ) : ?DefinitionInterface {
        return $this->definitions[ $slug ] ?? null;
    }

    /**
     * Add or update a definition in the registry.
     *
     * @param DefinitionInterface $definition
     * @return void
     */
    public function add( DefinitionInterface $definition ) : void {
        $this->definitions[ $definition->get_slug() ] = $definition;
        $this->save();
    }

    /**
     * Remove a definition by slug.
     *
     * @param string $slug Unique definition key.
     * @return void
     */
    public function delete( string $slug ) : void {
        if ( isset( $this->definitions[ $slug ] ) ) {
            unset( $this->definitions[ $slug ] );
            $this->save();
        }
    }

    /**
     * Load definitions from option.
     *
     * @return void
     */
    public function load() : void {
        $data = get_option( $this->option_name, [] );
        if ( ! is_array( $data ) ) {
            $data = [];
        }

        $model_class = $this->get_model_class();
        $this->definitions = [];

        foreach ( $data as $slug => $item_data ) {
            if ( is_array( $item_data ) && class_exists( $model_class ) ) {
                $this->definitions[ $slug ] = $model_class::from_array( $item_data );
            }
        }
    }

    /**
     * Save definitions back to option.
     *
     * @return void
     */
    public function save() : void {
        $data = [];
        foreach ( $this->definitions as $slug => $definition ) {
            $data[ $slug ] = $definition->to_array();
        }
        update_option( $this->option_name, $data );
    }
}
