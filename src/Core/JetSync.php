<?php
declare( strict_types=1 );

namespace JetSync\Core;

use JetSync\Core\Logger;
use JetSync\Core\Capabilities;
use JetSync\Core\ErrorHandler;
use JetSync\Core\Installer;
use JetSync\Registry\PostTypeRegistry;
use JetSync\Registry\TaxonomyRegistry;
use JetSync\Registry\MetaBoxRegistry;
use JetSync\Registry\RelationRegistry;
use JetSync\Registry\ListingRegistry;
use JetSync\Registry\QueryRegistry;
use JetSync\Runtime\RegistrationManager;
use JetSync\Runtime\MetaBoxManager;
use JetSync\Runtime\RelationsEngine;
use JetSync\Runtime\RelationsMetaBoxManager;
use JetSync\Runtime\ListingRenderer;
use JetSync\Integration\Elementor\LoopGridQueryIntegration;
use JetSync\Migration\MigrationRunner;
use JetSync\Admin\AdminController;
use JetSync\Admin\AjaxController;
use JetSync\Integration\Elementor\ElementorIntegration;

/**
 * Main plugin bootstrap and service container class.
 */
class JetSync {

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Service Registry.
     *
     * @var array<string,object>
     */
    private $services = [];

    /**
     * Get the singleton instance.
     *
     * @return self
     */
    public static function get_instance() : self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor. Initialise services and hook into WordPress.
     */
    private function __construct() {
        // Boot Core Services first.
        $this->set( 'logger', new Logger() );
        $this->set( 'capabilities', new Capabilities() );

        // Initialize Error Handler early.
        ErrorHandler::register();

        // Boot Registries.
        $this->set( 'cpt_registry', new PostTypeRegistry() );
        $this->set( 'taxonomy_registry', new TaxonomyRegistry() );
        $this->set( 'metabox_registry', new MetaBoxRegistry() );
        $this->set( 'relation_registry', new RelationRegistry() );
        $this->set( 'listing_registry', new ListingRegistry() );
        $this->set( 'query_registry', new QueryRegistry() );

        // Boot Registration & Fields Managers.
        $this->set( 'registration_manager', new RegistrationManager(
            $this->get( 'cpt_registry' ),
            $this->get( 'taxonomy_registry' ),
            $this->get( 'logger' )
        ) );
        $this->set( 'metabox_manager', new MetaBoxManager(
            $this->get( 'metabox_registry' ),
            $this->get( 'logger' )
        ) );
        $this->set( 'relations_engine', new RelationsEngine(
            $this->get( 'logger' )
        ) );
        $this->set( 'relations_metabox_manager', new RelationsMetaBoxManager(
            $this->get( 'relation_registry' ),
            $this->get( 'relations_engine' ),
            $this->get( 'logger' )
        ) );
        $this->set( 'listing_renderer', new ListingRenderer(
            $this->get( 'listing_registry' ),
            $this->get( 'logger' )
        ) );

        if ( class_exists( '\Elementor\Plugin' ) ) {
            $this->set( 'elementor_integration', new ElementorIntegration() );
            $this->set( 'elementor_loop_grid_query_integration', new LoopGridQueryIntegration() );
        }

        // Boot Migration Runner.
        $this->set( 'migration_runner', new MigrationRunner(
            $this->get( 'logger' )
        ) );

        // Boot Admin & AJAX Controllers.
        if ( \is_admin() ) {
            $this->set( 'admin', new AdminController( $this ) );
            $this->set( 'ajax', new AjaxController() );
        }

        $this->init_hooks();
    }

    /**
     * Retrieve a registered service.
     *
     * @param string $id Service identifier.
     * @return object|null Service instance or null if not registered.
     */
    public function get( string $id ) : ?object {
        return $this->services[ $id ] ?? null;
    }

    /**
     * Register a service.
     *
     * @param string $id Service identifier.
     * @param object $service Service instance.
     * @return void
     */
    public function set( string $id, object $service ) : void {
        $this->services[ $id ] = $service;
    }

    /**
     * Activation Callback.
     */
    public static function activate() : void {
        $instance = self::get_instance();
        /** @var Logger $logger */
        $logger = $instance->get( 'logger' );
        if ( $logger ) {
            $logger->info( 'JetSync activation process started.' );
        }

        Installer::activate();

        if ( $logger ) {
            $logger->info( 'JetSync activated successfully.' );
        }
    }

    /**
     * Deactivation Callback.
     */
    public static function deactivate() : void {
        $instance = self::get_instance();
        /** @var Logger $logger */
        $logger = $instance->get( 'logger' );
        if ( $logger ) {
            $logger->info( 'JetSync deactivation process started.' );
        }

        Installer::deactivate();

        if ( $logger ) {
            $logger->info( 'JetSync deactivated successfully.' );
        }
    }

    /**
     * Hook into WordPress initialization processes.
     */
    private function init_hooks() : void {
        \add_action( 'init', [ $this, 'on_init' ] );
    }

    /**
     * General WP Init Hook.
     */
    public function on_init() : void {
        Capabilities::install();

        $installed = \get_option( 'jetsync_installed_version', '' );
        if ( ! is_string( $installed ) ) {
            $installed = '';
        }

        if ( $installed !== JETSYNC_VERSION ) {
            if ( '' !== $installed && version_compare( $installed, '1.3.25', '<' ) ) {
                $mb_registry = $this->get( 'metabox_registry' );
                if ( $mb_registry instanceof \JetSync\Registry\MetaBoxRegistry ) {
                    $mb_registry->upgrade_auto_metaboxes();
                }
            }
            if ( '' !== $installed && version_compare( $installed, '1.3.23', '<' ) ) {
                $mb_manager = $this->get( 'metabox_manager' );
                if ( $mb_manager instanceof \JetSync\Runtime\MetaBoxManager ) {
                    $mb_manager->sync_featured_images_for_registered_media_fields();
                }
            }
            \update_option( 'jetsync_installed_version', JETSYNC_VERSION );
        }

        // Self-healing for model field types (checkbox/switcher) introduced in 1.4.x — runs once.
        $repaired_flag = \get_option( 'jetsync_model_fields_repaired_v2', false );
        if ( ! $repaired_flag ) {
            $mb_registry = $this->get( 'metabox_registry' );
            if ( $mb_registry instanceof \JetSync\Registry\MetaBoxRegistry ) {
                $mb_registry->repair_known_model_fields();
                \update_option( 'jetsync_model_fields_repaired_v2', true );
            }
        }
    }
}
