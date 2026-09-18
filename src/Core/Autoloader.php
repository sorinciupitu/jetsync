<?php
declare( strict_types=1 );

namespace JetSync\Core;

/**
 * PSR-4 compliant autoloader for JetSync classes.
 */
class Autoloader {

    /**
     * Namespace prefix.
     *
     * @var string
     */
    private const PREFIX = 'JetSync\\';

    /**
     * Directory containing source files.
     *
     * @var string
     */
    private $base_dir;

    /**
     * Constructor.
     *
     * @param string $base_dir Base directory to resolve classes from.
     */
    public function __construct( string $base_dir ) {
        $this->base_dir = rtrim( $base_dir, '/\\' ) . '/';
    }

    /**
     * Register autoloader with SPL.
     *
     * @return void
     */
    public function register() : void {
        spl_autoload_register( [ $this, 'autoload' ] );
    }

    /**
     * Load class file if it belongs to the JetSync namespace.
     *
     * @param string $class Fully qualified class name.
     * @return void
     */
    public function autoload( string $class ) : void {
        // Does the class use our namespace prefix?
        $len = strlen( self::PREFIX );
        if ( strncmp( self::PREFIX, $class, $len ) !== 0 ) {
            return;
        }

        // Get the relative class name
        $relative_class = substr( $class, $len );

        // Replace namespace separators with directory separators, append .php
        $file = $this->base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

        // If the file exists, require it
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
