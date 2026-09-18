<?php
declare( strict_types=1 );

namespace JetSync\Admin;

use JetSync\Core\JetSync;
use JetSync\Core\Capabilities;
use JetSync\Admin\Pages\DashboardPage;
use JetSync\Admin\Pages\CptsPage;
use JetSync\Admin\Pages\TaxonomiesPage;
use JetSync\Admin\Pages\MetaBoxesPage;
use JetSync\Admin\Pages\RelationsPage;
use JetSync\Admin\Pages\ListingsPage;
use JetSync\Admin\Pages\QueriesPage;
use JetSync\Admin\Pages\MigrationPage;
use JetSync\Registry\Model\PostTypeDefinition;
use JetSync\Registry\Model\TaxonomyDefinition;
use JetSync\Registry\Model\MetaBoxDefinition;
use JetSync\Registry\Model\MetaFieldDefinition;
use JetSync\Registry\Model\RelationDefinition;
use JetSync\Registry\Model\ListingDefinition;
use JetSync\Registry\Model\QueryDefinition;

/**
 * Main Controller for WP Admin screens and menus.
 */
class AdminController {

    /**
     * JetSync main instance.
     *
     * @var JetSync
     */
    private $plugin;

    /**
     * Constructor. Registers admin hooks.
     *
     * @param JetSync $plugin The main JetSync singleton instance.
     */
    public function __construct( JetSync $plugin ) {
        $this->plugin = $plugin;

        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_post_jetsync_export_config', [ $this, 'handle_export_download' ] );
        add_action( 'admin_post_jetsync_add_cpt', [ $this, 'handle_add_cpt' ] );
        add_action( 'admin_post_jetsync_update_cpt', [ $this, 'handle_update_cpt' ] );
        add_action( 'admin_post_jetsync_delete_cpt', [ $this, 'handle_delete_cpt' ] );
        add_action( 'admin_post_jetsync_add_taxonomy', [ $this, 'handle_add_taxonomy' ] );
        add_action( 'admin_post_jetsync_update_taxonomy', [ $this, 'handle_update_taxonomy' ] );
        add_action( 'admin_post_jetsync_delete_taxonomy', [ $this, 'handle_delete_taxonomy' ] );
        add_action( 'admin_post_jetsync_add_relation', [ $this, 'handle_add_relation' ] );
        add_action( 'admin_post_jetsync_update_relation', [ $this, 'handle_update_relation' ] );
        add_action( 'admin_post_jetsync_add_metabox', [ $this, 'handle_add_metabox' ] );
        add_action( 'admin_post_jetsync_update_metabox', [ $this, 'handle_update_metabox' ] );
        add_action( 'admin_post_jetsync_delete_metabox', [ $this, 'handle_delete_metabox' ] );
        add_action( 'admin_post_jetsync_add_listing', [ $this, 'handle_add_listing' ] );
        add_action( 'admin_post_jetsync_delete_listing', [ $this, 'handle_delete_listing' ] );
        add_action( 'admin_post_jetsync_add_query', [ $this, 'handle_add_query' ] );
        add_action( 'admin_post_jetsync_delete_query', [ $this, 'handle_delete_query' ] );
    }

    /**
     * Register JetSync menu and its submenus in WordPress.
     *
     * @return void
     */
    public function register_menu() : void {
        // Main JetSync page
        add_menu_page(
            __( 'JetSync', 'jet-sync' ),
            __( 'JetSync', 'jet-sync' ),
            Capabilities::MANAGE,
            'jet-sync-dashboard',
            [ $this, 'render_dashboard' ],
            'dashicons-randomize',
            25
        );

        // Standardise submenus
        add_submenu_page(
            'jet-sync-dashboard',
            __( 'Dashboard', 'jet-sync' ),
            __( 'Dashboard', 'jet-sync' ),
            Capabilities::MANAGE,
            'jet-sync-dashboard',
            [ $this, 'render_dashboard' ]
        );

        // Active screens for Stage 2 & 4
        add_submenu_page(
            'jet-sync-dashboard',
            __( 'Custom Post Types', 'jet-sync' ),
            __( 'Custom Post Types', 'jet-sync' ),
            Capabilities::MANAGE,
            CptsPage::get_slug(),
            [ $this, 'render_cpts_page' ]
        );

        add_submenu_page(
            'jet-sync-dashboard',
            __( 'Taxonomies', 'jet-sync' ),
            __( 'Taxonomies', 'jet-sync' ),
            Capabilities::MANAGE,
            TaxonomiesPage::get_slug(),
            [ $this, 'render_taxonomies_page' ]
        );

        add_submenu_page(
            'jet-sync-dashboard',
            __( 'Meta Fields', 'jet-sync' ),
            __( 'Meta Fields', 'jet-sync' ),
            Capabilities::MANAGE,
            MetaBoxesPage::get_slug(),
            [ $this, 'render_metaboxes_page' ]
        );

        // Stage 5: Relations Registry
        add_submenu_page(
            'jet-sync-dashboard',
            __( 'Relations', 'jet-sync' ),
            __( 'Relations', 'jet-sync' ),
            Capabilities::MANAGE,
            RelationsPage::get_slug(),
            [ $this, 'render_relations_page' ]
        );

        // Stage 6: Migration Center
        add_submenu_page(
            'jet-sync-dashboard',
            __( 'Migration Wizard', 'jet-sync' ),
            __( 'Migration Wizard', 'jet-sync' ),
            Capabilities::MIGRATE,
            MigrationPage::get_slug(),
            [ $this, 'render_migration_page' ]
        );

        add_submenu_page(
            'jet-sync-dashboard',
            __( 'Listings', 'jet-sync' ),
            __( 'Listings', 'jet-sync' ),
            Capabilities::MANAGE,
            ListingsPage::get_slug(),
            [ $this, 'render_listings_page' ]
        );

        add_submenu_page(
            'jet-sync-dashboard',
            __( 'Queries', 'jet-sync' ),
            __( 'Queries', 'jet-sync' ),
            Capabilities::MANAGE,
            QueriesPage::get_slug(),
            [ $this, 'render_queries_page' ]
        );
    }

    /**
     * Render the Dashboard page.
     *
     * @return void
     */
    public function render_dashboard() : void {
        $page = new DashboardPage();
        $page->render();
    }

    /**
     * Render the CPT list page.
     *
     * @return void
     */
    public function render_cpts_page() : void {
        $page = new CptsPage();
        $page->render();
    }

    /**
     * Render the Taxonomies list page.
     *
     * @return void
     */
    public function render_taxonomies_page() : void {
        $page = new TaxonomiesPage();
        $page->render();
    }

    /**
     * Render the Meta Boxes list page.
     *
     * @return void
     */
    public function render_metaboxes_page() : void {
        $page = new MetaBoxesPage();
        $page->render();
    }

    /**
     * Render the Relations Registry page.
     *
     * @return void
     */
    public function render_relations_page() : void {
        $page = new RelationsPage();
        $page->render();
    }

    /**
     * Render the Migration Center page.
     *
     * @return void
     */
    public function render_migration_page() : void {
        $page = new MigrationPage();
        $page->render();
    }

    public function render_listings_page() : void {
        $page = new ListingsPage();
        $page->render();
    }

    public function render_queries_page() : void {
        $page = new QueriesPage();
        $page->render();
    }

    /**
     * Handle config export download via admin-post.php.
     *
     * @return void
     */
    public function handle_export_download() : void {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jet-sync' ), 403 );
        }

        check_admin_referer( 'jetsync_export' );

        $export = new \JetSync\Migration\ExportManager();
        $export->serve_download();
    }

    public function handle_add_cpt() : void {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jet-sync' ), 403 );
        }

        check_admin_referer( 'jetsync_add_cpt' );

        /** @var \JetSync\Registry\PostTypeRegistry|null $cpt_registry */
        $cpt_registry = $this->plugin->get( 'cpt_registry' );
        if ( ! $cpt_registry ) {
            $this->redirect_admin_page( 'jet-sync-cpts', [ 'created' => 0 ] );
        }

        $definition = $this->build_cpt_definition_from_request();
        if ( ! $definition ) {
            $this->redirect_admin_page( 'jet-sync-cpts', [ 'created' => 0, 'action' => 'add' ] );
        }

        if ( $cpt_registry->get( $definition->get_slug() ) ) {
            $this->redirect_admin_page( 'jet-sync-cpts', [ 'created' => 0, 'action' => 'add' ] );
        }

        $cpt_registry->add( $definition );
        flush_rewrite_rules( false );

        $this->redirect_admin_page( 'jet-sync-cpts', [ 'created' => 1 ] );
    }

    public function handle_update_cpt() : void {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jet-sync' ), 403 );
        }

        check_admin_referer( 'jetsync_update_cpt' );

        $current_slug = isset( $_POST['current_slug'] ) ? sanitize_key( (string) $_POST['current_slug'] ) : '';
        if ( '' === $current_slug ) {
            $this->redirect_admin_page( 'jet-sync-cpts', [ 'updated' => 0 ] );
        }

        /** @var \JetSync\Registry\PostTypeRegistry|null $cpt_registry */
        $cpt_registry = $this->plugin->get( 'cpt_registry' );
        if ( ! $cpt_registry || ! $cpt_registry->get( $current_slug ) ) {
            $this->redirect_admin_page( 'jet-sync-cpts', [ 'updated' => 0 ] );
        }

        $definition = $this->build_cpt_definition_from_request( $current_slug );
        if ( ! $definition ) {
            $this->redirect_admin_page( 'jet-sync-cpts', [ 'updated' => 0, 'action' => 'edit', 'slug' => $current_slug ] );
        }

        $cpt_registry->add( $definition );
        flush_rewrite_rules( false );

        $this->redirect_admin_page( 'jet-sync-cpts', [ 'updated' => 1 ] );
    }

    public function handle_delete_cpt() : void {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jet-sync' ), 403 );
        }

        $slug = isset( $_GET['slug'] ) ? sanitize_key( (string) $_GET['slug'] ) : '';
        if ( '' === $slug ) {
            $this->redirect_admin_page( 'jet-sync-cpts', [ 'deleted' => 0 ] );
        }

        check_admin_referer( 'jetsync_delete_cpt_' . $slug );

        /** @var \JetSync\Registry\PostTypeRegistry|null $cpt_registry */
        $cpt_registry = $this->plugin->get( 'cpt_registry' );
        if ( ! $cpt_registry || ! $cpt_registry->get( $slug ) ) {
            $this->redirect_admin_page( 'jet-sync-cpts', [ 'deleted' => 0 ] );
        }

        $cpt_registry->delete( $slug );
        flush_rewrite_rules( false );

        $this->redirect_admin_page( 'jet-sync-cpts', [ 'deleted' => 1 ] );
    }

    public function handle_add_taxonomy() : void {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jet-sync' ), 403 );
        }

        check_admin_referer( 'jetsync_add_taxonomy' );

        /** @var \JetSync\Registry\TaxonomyRegistry|null $taxonomy_registry */
        $taxonomy_registry = $this->plugin->get( 'taxonomy_registry' );
        if ( ! $taxonomy_registry ) {
            $this->redirect_admin_page( 'jet-sync-taxonomies', [ 'created' => 0 ] );
        }

        $definition = $this->build_taxonomy_definition_from_request();
        if ( ! $definition ) {
            $this->redirect_admin_page( 'jet-sync-taxonomies', [ 'created' => 0, 'action' => 'add' ] );
        }

        if ( $taxonomy_registry->get( $definition->get_slug() ) ) {
            $this->redirect_admin_page( 'jet-sync-taxonomies', [ 'created' => 0, 'action' => 'add' ] );
        }

        $taxonomy_registry->add( $definition );
        flush_rewrite_rules( false );

        $this->redirect_admin_page( 'jet-sync-taxonomies', [ 'created' => 1 ] );
    }

    public function handle_update_taxonomy() : void {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jet-sync' ), 403 );
        }

        check_admin_referer( 'jetsync_update_taxonomy' );

        $current_slug = isset( $_POST['current_slug'] ) ? sanitize_key( (string) $_POST['current_slug'] ) : '';
        if ( '' === $current_slug ) {
            $this->redirect_admin_page( 'jet-sync-taxonomies', [ 'updated' => 0 ] );
        }

        /** @var \JetSync\Registry\TaxonomyRegistry|null $taxonomy_registry */
        $taxonomy_registry = $this->plugin->get( 'taxonomy_registry' );
        if ( ! $taxonomy_registry || ! $taxonomy_registry->get( $current_slug ) ) {
            $this->redirect_admin_page( 'jet-sync-taxonomies', [ 'updated' => 0 ] );
        }

        $definition = $this->build_taxonomy_definition_from_request( $current_slug );
        if ( ! $definition ) {
            $this->redirect_admin_page( 'jet-sync-taxonomies', [ 'updated' => 0, 'action' => 'edit', 'slug' => $current_slug ] );
        }

        $taxonomy_registry->add( $definition );
        flush_rewrite_rules( false );

        $this->redirect_admin_page( 'jet-sync-taxonomies', [ 'updated' => 1 ] );
    }

    public function handle_delete_taxonomy() : void {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jet-sync' ), 403 );
        }

        $slug = isset( $_GET['slug'] ) ? sanitize_key( (string) $_GET['slug'] ) : '';
        if ( '' === $slug ) {
            $this->redirect_admin_page( 'jet-sync-taxonomies', [ 'deleted' => 0 ] );
        }

        check_admin_referer( 'jetsync_delete_taxonomy_' . $slug );

        /** @var \JetSync\Registry\TaxonomyRegistry|null $taxonomy_registry */
        $taxonomy_registry = $this->plugin->get( 'taxonomy_registry' );
        if ( ! $taxonomy_registry || ! $taxonomy_registry->get( $slug ) ) {
            $this->redirect_admin_page( 'jet-sync-taxonomies', [ 'deleted' => 0 ] );
        }

        $taxonomy_registry->delete( $slug );
        flush_rewrite_rules( false );

        $this->redirect_admin_page( 'jet-sync-taxonomies', [ 'deleted' => 1 ] );
    }

    public function handle_add_relation() : void {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jet-sync' ), 403 );
        }

        check_admin_referer( 'jetsync_add_relation' );

        $id = isset( $_POST['id'] ) ? sanitize_key( (string) $_POST['id'] ) : '';
        $title = isset( $_POST['title'] ) ? sanitize_text_field( (string) $_POST['title'] ) : '';
        $parent_object = isset( $_POST['parent_object'] ) ? sanitize_key( (string) $_POST['parent_object'] ) : 'post';
        $child_object = isset( $_POST['child_object'] ) ? sanitize_key( (string) $_POST['child_object'] ) : 'post';
        $type = isset( $_POST['type'] ) ? sanitize_key( (string) $_POST['type'] ) : 'many_to_many';
        $active = isset( $_POST['active'] ) ? (bool) $_POST['active'] : true;

        if ( '' === $id || '' === $title ) {
            wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-relations&created=0' ) );
            exit;
        }

        /** @var \JetSync\Registry\RelationRegistry|null $relation_registry */
        $relation_registry = $this->plugin->get( 'relation_registry' );
        if ( ! $relation_registry ) {
            wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-relations&created=0' ) );
            exit;
        }

        $relation_registry->add(
            new RelationDefinition(
                id: $id,
                title: $title,
                parent_object: $parent_object,
                child_object: $child_object,
                type: $type,
                active: $active
            )
        );
        $relation_registry->save();

        wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-relations&created=1' ) );
        exit;
    }

    public function handle_update_relation() : void {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jet-sync' ), 403 );
        }

        check_admin_referer( 'jetsync_update_relation' );

        $id = isset( $_POST['id'] ) ? sanitize_key( (string) $_POST['id'] ) : '';
        $title = isset( $_POST['title'] ) ? sanitize_text_field( (string) $_POST['title'] ) : '';
        $parent_object = isset( $_POST['parent_object'] ) ? sanitize_key( (string) $_POST['parent_object'] ) : 'post';
        $child_object = isset( $_POST['child_object'] ) ? sanitize_key( (string) $_POST['child_object'] ) : 'post';
        $type = isset( $_POST['type'] ) ? sanitize_key( (string) $_POST['type'] ) : 'many_to_many';
        $active = isset( $_POST['active'] ) ? (bool) $_POST['active'] : false;

        if ( '' === $id || '' === $title ) {
            wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-relations&updated=0' ) );
            exit;
        }

        /** @var \JetSync\Registry\RelationRegistry|null $relation_registry */
        $relation_registry = $this->plugin->get( 'relation_registry' );
        if ( ! $relation_registry ) {
            wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-relations&updated=0' ) );
            exit;
        }

        $existing = $relation_registry->get( $id );
        if ( ! $existing ) {
            wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-relations&updated=0' ) );
            exit;
        }

        $relation_registry->add(
            new RelationDefinition(
                id: $id,
                title: $title,
                parent_object: $parent_object,
                child_object: $child_object,
                type: $type,
                active: $active
            )
        );
        $relation_registry->save();

        wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-relations&updated=1' ) );
        exit;
    }

    public function handle_add_metabox() : void {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jet-sync' ), 403 );
        }

        check_admin_referer( 'jetsync_add_metabox' );

        $id = isset( $_POST['id'] ) ? sanitize_key( (string) $_POST['id'] ) : '';
        $title = isset( $_POST['title'] ) ? sanitize_text_field( (string) $_POST['title'] ) : '';
        $object_types_raw = isset( $_POST['object_types'] ) ? sanitize_text_field( (string) $_POST['object_types'] ) : '';
        $fields_raw = isset( $_POST['fields'] ) ? (string) $_POST['fields'] : '';
        $context = isset( $_POST['context'] ) ? sanitize_key( (string) $_POST['context'] ) : 'normal';
        $priority = isset( $_POST['priority'] ) ? sanitize_key( (string) $_POST['priority'] ) : 'default';
        $active = isset( $_POST['active'] ) ? (bool) $_POST['active'] : true;

        if ( '' === $id || '' === $title ) {
            wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-meta-boxes&created=0' ) );
            exit;
        }

        /** @var \JetSync\Registry\MetaBoxRegistry|null $metabox_registry */
        $metabox_registry = $this->plugin->get( 'metabox_registry' );
        if ( ! $metabox_registry ) {
            wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-meta-boxes&created=0' ) );
            exit;
        }
        if ( $metabox_registry->get( $id ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-meta-boxes&created=0&action=add' ) );
            exit;
        }

        $allowed_contexts = [ 'normal', 'advanced', 'side' ];
        if ( ! in_array( $context, $allowed_contexts, true ) ) {
            $context = 'normal';
        }

        $allowed_priorities = [ 'high', 'core', 'default', 'low' ];
        if ( ! in_array( $priority, $allowed_priorities, true ) ) {
            $priority = 'default';
        }

        $object_types = $this->parse_slug_list( $object_types_raw );
        $field_objects = $this->parse_metabox_fields( $fields_raw );

        $metabox_registry->add(
            new MetaBoxDefinition(
                id: $id,
                title: $title,
                object_types: $object_types,
                context: $context,
                priority: $priority,
                fields: $field_objects,
                active: $active
            )
        );
        $metabox_registry->save();

        wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-meta-boxes&created=1' ) );
        exit;
    }

    public function handle_update_metabox() : void {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jet-sync' ), 403 );
        }

        check_admin_referer( 'jetsync_update_metabox' );

        $current_id = isset( $_POST['current_id'] ) ? sanitize_key( (string) $_POST['current_id'] ) : '';
        $id = isset( $_POST['id'] ) ? sanitize_key( (string) $_POST['id'] ) : '';
        $title = isset( $_POST['title'] ) ? sanitize_text_field( (string) $_POST['title'] ) : '';
        $object_types_raw = isset( $_POST['object_types'] ) ? sanitize_text_field( (string) $_POST['object_types'] ) : '';
        $fields_raw = isset( $_POST['fields'] ) ? (string) $_POST['fields'] : '';
        $context = isset( $_POST['context'] ) ? sanitize_key( (string) $_POST['context'] ) : 'normal';
        $priority = isset( $_POST['priority'] ) ? sanitize_key( (string) $_POST['priority'] ) : 'default';
        $active = isset( $_POST['active'] ) ? (bool) $_POST['active'] : false;

        if ( '' === $current_id || '' === $id || '' === $title ) {
            $this->redirect_admin_page( 'jet-sync-meta-boxes', [ 'updated' => 0 ] );
        }

        /** @var \JetSync\Registry\MetaBoxRegistry|null $metabox_registry */
        $metabox_registry = $this->plugin->get( 'metabox_registry' );
        if ( ! $metabox_registry || ! $metabox_registry->get( $current_id ) ) {
            $this->redirect_admin_page( 'jet-sync-meta-boxes', [ 'updated' => 0 ] );
        }

        $allowed_contexts = [ 'normal', 'advanced', 'side' ];
        if ( ! in_array( $context, $allowed_contexts, true ) ) {
            $context = 'normal';
        }

        $allowed_priorities = [ 'high', 'core', 'default', 'low' ];
        if ( ! in_array( $priority, $allowed_priorities, true ) ) {
            $priority = 'default';
        }

        $object_types = $this->parse_slug_list( $object_types_raw );
        $field_objects = $this->parse_metabox_fields( $fields_raw );

        $definition = new MetaBoxDefinition(
            id: $id,
            title: $title,
            object_types: $object_types,
            context: $context,
            priority: $priority,
            fields: $field_objects,
            active: $active
        );

        if ( $current_id !== $id ) {
            $metabox_registry->delete( $current_id );
        }

        $metabox_registry->add( $definition );

        $this->redirect_admin_page( 'jet-sync-meta-boxes', [ 'updated' => 1 ] );
    }

    public function handle_delete_metabox() : void {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jet-sync' ), 403 );
        }

        $id = isset( $_GET['id'] ) ? sanitize_key( (string) $_GET['id'] ) : '';
        if ( '' === $id ) {
            $this->redirect_admin_page( 'jet-sync-meta-boxes', [ 'deleted' => 0 ] );
        }

        check_admin_referer( 'jetsync_delete_metabox_' . $id );

        /** @var \JetSync\Registry\MetaBoxRegistry|null $metabox_registry */
        $metabox_registry = $this->plugin->get( 'metabox_registry' );
        if ( ! $metabox_registry || ! $metabox_registry->get( $id ) ) {
            $this->redirect_admin_page( 'jet-sync-meta-boxes', [ 'deleted' => 0 ] );
        }

        $metabox_registry->delete( $id );
        $this->redirect_admin_page( 'jet-sync-meta-boxes', [ 'deleted' => 1 ] );
    }

    public function handle_add_listing() : void {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jet-sync' ), 403 );
        }

        check_admin_referer( 'jetsync_add_listing' );

        $id = isset( $_POST['id'] ) ? sanitize_key( (string) $_POST['id'] ) : '';
        $title = isset( $_POST['title'] ) ? sanitize_text_field( (string) $_POST['title'] ) : '';
        $post_type = isset( $_POST['post_type'] ) ? sanitize_key( (string) $_POST['post_type'] ) : '';
        $ppp = isset( $_POST['posts_per_page'] ) ? (int) $_POST['posts_per_page'] : 10;
        $orderby = isset( $_POST['orderby'] ) ? sanitize_key( (string) $_POST['orderby'] ) : 'date';
        $order = isset( $_POST['order'] ) ? sanitize_key( (string) $_POST['order'] ) : 'DESC';
        $selected_fields = [];
        if ( isset( $_POST['selected_fields'] ) && is_array( $_POST['selected_fields'] ) ) {
            $selected_fields = array_values( array_map( 'sanitize_text_field', array_map( 'strval', $_POST['selected_fields'] ) ) );
        }
        $template = isset( $_POST['template'] ) ? wp_kses_post( (string) $_POST['template'] ) : '';
        $active = isset( $_POST['active'] ) ? (bool) $_POST['active'] : true;

        if ( '' === $id || '' === $title || '' === $post_type ) {
            wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-listings&created=0' ) );
            exit;
        }

        /** @var \JetSync\Registry\ListingRegistry|null $listing_registry */
        $listing_registry = $this->plugin->get( 'listing_registry' );
        if ( ! $listing_registry ) {
            wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-listings&created=0' ) );
            exit;
        }

        $listing_registry->add(
            new ListingDefinition(
                id: $id,
                title: $title,
                post_type: $post_type,
                posts_per_page: $ppp,
                orderby: $orderby,
                order: $order,
                selected_fields: $selected_fields,
                template: $template,
                active: $active
            )
        );
        $listing_registry->save();

        wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-listings&created=1' ) );
        exit;
    }

    public function handle_delete_listing() : void {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jet-sync' ), 403 );
        }

        $id = isset( $_GET['id'] ) ? sanitize_key( (string) $_GET['id'] ) : '';
        if ( '' === $id ) {
            wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-listings&deleted=0' ) );
            exit;
        }

        check_admin_referer( 'jetsync_delete_listing_' . $id );

        /** @var \JetSync\Registry\ListingRegistry|null $listing_registry */
        $listing_registry = $this->plugin->get( 'listing_registry' );
        if ( ! $listing_registry ) {
            wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-listings&deleted=0' ) );
            exit;
        }

        $listing_registry->delete( $id );
        wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-listings&deleted=1' ) );
        exit;
    }

    public function handle_add_query() : void {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jet-sync' ), 403 );
        }

        check_admin_referer( 'jetsync_add_query' );

        $id = isset( $_POST['id'] ) ? sanitize_key( (string) $_POST['id'] ) : '';
        $title = isset( $_POST['title'] ) ? sanitize_text_field( (string) $_POST['title'] ) : '';
        $relation_id = isset( $_POST['relation_id'] ) ? sanitize_key( (string) $_POST['relation_id'] ) : '';
        $target_post_type = isset( $_POST['target_post_type'] ) ? sanitize_key( (string) $_POST['target_post_type'] ) : '';
        $source_post_types_raw = isset( $_POST['source_post_types'] ) ? sanitize_text_field( (string) $_POST['source_post_types'] ) : '';
        $posts_per_page = isset( $_POST['posts_per_page'] ) ? (int) $_POST['posts_per_page'] : 10;
        $orderby = isset( $_POST['orderby'] ) ? sanitize_key( (string) $_POST['orderby'] ) : 'date';
        $order = isset( $_POST['order'] ) ? sanitize_key( (string) $_POST['order'] ) : 'DESC';
        $active = isset( $_POST['active'] ) ? (bool) $_POST['active'] : true;

        if ( '' === $id || '' === $title || '' === $relation_id || '' === $target_post_type ) {
            wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-queries&created=0' ) );
            exit;
        }

        $source_post_types = [];
        if ( '' !== $source_post_types_raw ) {
            foreach ( preg_split( '/\s*,\s*/', $source_post_types_raw ) as $post_type ) {
                $post_type = sanitize_key( (string) $post_type );
                if ( '' !== $post_type ) {
                    $source_post_types[] = $post_type;
                }
            }
        }

        /** @var \JetSync\Registry\QueryRegistry|null $query_registry */
        $query_registry = $this->plugin->get( 'query_registry' );
        if ( ! $query_registry ) {
            wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-queries&created=0' ) );
            exit;
        }

        $query_registry->add(
            new QueryDefinition(
                id: $id,
                title: $title,
                relation_id: $relation_id,
                target_post_type: $target_post_type,
                source_post_types: $source_post_types,
                posts_per_page: $posts_per_page,
                orderby: $orderby,
                order: $order,
                active: $active
            )
        );
        $query_registry->save();

        wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-queries&created=1' ) );
        exit;
    }

    public function handle_delete_query() : void {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jet-sync' ), 403 );
        }

        $id = isset( $_GET['id'] ) ? sanitize_key( (string) $_GET['id'] ) : '';
        if ( '' === $id ) {
            wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-queries&deleted=0' ) );
            exit;
        }

        check_admin_referer( 'jetsync_delete_query_' . $id );

        /** @var \JetSync\Registry\QueryRegistry|null $query_registry */
        $query_registry = $this->plugin->get( 'query_registry' );
        if ( ! $query_registry ) {
            wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-queries&deleted=0' ) );
            exit;
        }

        $query_registry->delete( $id );
        wp_safe_redirect( admin_url( 'admin.php?page=jet-sync-queries&deleted=1' ) );
        exit;
    }

    /**
     * Render placeholder page for future modules.
     *
     * @return void
     */
    public function render_placeholder_page() : void {
        $page_title = __( 'JetSync Page', 'jet-sync' );
        if ( isset( $_GET['page'] ) ) {
            $slug = sanitize_text_field( $_GET['page'] );
            switch ( $slug ) {
                case 'jet-sync-relations':
                    $page_title = __( 'Relations Registry', 'jet-sync' );
                    break;
                case 'jet-sync-listings':
                    $page_title = __( 'Listings Builder', 'jet-sync' );
                    break;
                case 'jet-sync-queries':
                    $page_title = __( 'Elementor Queries', 'jet-sync' );
                    break;
                case 'jet-sync-migration':
                    $page_title = __( 'Migration Wizard', 'jet-sync' );
                    break;
            }
        }
        ?>
        <div class="wrap jetsync-wrap">
            <header class="jetsync-header">
                <div class="jetsync-header-logo">
                    <span class="jetsync-badge-version">v<?php echo esc_html( JETSYNC_VERSION ); ?></span>
                    <h1>JetSync</h1>
                </div>
            </header>
            <div class="jetsync-workspace" style="padding: 3.5rem 2rem; text-align: center; max-width: 800px; margin: 2rem auto; border-radius: 12px; background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1);">
                <span class="dashicons dashicons-lock" style="font-size: 4rem; width: 4rem; height: 4rem; color: #64748b; margin-bottom: 1.5rem;"></span>
                <h2 style="font-size: 1.75rem; font-weight: 600; margin-bottom: 0.75rem; color: #f8fafc;"><?php echo esc_html( $page_title ); ?></h2>
                <p style="font-size: 1.05rem; line-height: 1.6; color: #94a3b8; max-width: 500px; margin: 0 auto 2rem;">
                    <?php esc_html_e( 'This section is currently locked. It will be enabled in the upcoming stage of the JetSync installation roadmap.', 'jet-sync' ); ?>
                </p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-dashboard' ) ); ?>" class="jetsync-btn primary-btn">
                    <?php esc_html_e( 'Back to Dashboard', 'jet-sync' ); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue CSS/JS only on JetSync specific pages.
     *
     * @param string $hook The current admin page hook.
     * @return void
     */
    public function enqueue_assets( string $hook ) : void {
        if ( false === strpos( $hook, 'jet-sync' ) ) {
            return;
        }

        wp_enqueue_style(
            'jet-sync-admin-css',
            JETSYNC_URL . 'assets/css/admin.css',
            [],
            JETSYNC_VERSION
        );

        wp_enqueue_script(
            'jet-sync-admin-js',
            JETSYNC_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            JETSYNC_VERSION,
            true
        );
    }

    private function build_cpt_definition_from_request( string $fallback_slug = '' ) : ?PostTypeDefinition {
        $slug = isset( $_POST['slug'] ) ? sanitize_key( (string) $_POST['slug'] ) : $fallback_slug;
        $plural = isset( $_POST['plural_label'] ) ? sanitize_text_field( (string) $_POST['plural_label'] ) : '';
        $singular = isset( $_POST['singular_label'] ) ? sanitize_text_field( (string) $_POST['singular_label'] ) : '';
        $supports_raw = isset( $_POST['supports'] ) ? sanitize_text_field( (string) $_POST['supports'] ) : '';
        $rewrite_slug = isset( $_POST['rewrite_slug'] ) ? sanitize_title( (string) $_POST['rewrite_slug'] ) : '';
        $menu_icon = isset( $_POST['menu_icon'] ) ? sanitize_text_field( (string) $_POST['menu_icon'] ) : 'dashicons-admin-post';
        $menu_position = isset( $_POST['menu_position'] ) && '' !== (string) $_POST['menu_position'] ? (int) $_POST['menu_position'] : null;

        if ( '' === $slug || '' === $plural || '' === $singular ) {
            return null;
        }

        $supports = $this->parse_slug_list( $supports_raw );
        if ( empty( $supports ) ) {
            $supports = [ 'title', 'editor', 'thumbnail' ];
        }

        return new PostTypeDefinition(
            slug: $slug,
            labels: [
                'name'          => $plural,
                'singular_name' => $singular,
                'menu_name'     => $plural,
            ],
            public: isset( $_POST['public'] ),
            show_ui: isset( $_POST['show_ui'] ),
            show_in_menu: isset( $_POST['show_in_menu'] ),
            menu_position: $menu_position,
            menu_icon: '' !== $menu_icon ? $menu_icon : 'dashicons-admin-post',
            supports: $supports,
            has_archive: isset( $_POST['has_archive'] ),
            rewrite: [
                'slug'       => '' !== $rewrite_slug ? $rewrite_slug : $slug,
                'with_front' => isset( $_POST['with_front'] ),
            ],
            show_in_rest: isset( $_POST['show_in_rest'] ),
            capabilities: [],
            active: isset( $_POST['active'] )
        );
    }

    private function build_taxonomy_definition_from_request( string $fallback_slug = '' ) : ?TaxonomyDefinition {
        $slug = isset( $_POST['slug'] ) ? sanitize_key( (string) $_POST['slug'] ) : $fallback_slug;
        $plural = isset( $_POST['plural_label'] ) ? sanitize_text_field( (string) $_POST['plural_label'] ) : '';
        $singular = isset( $_POST['singular_label'] ) ? sanitize_text_field( (string) $_POST['singular_label'] ) : '';
        $object_types_raw = isset( $_POST['object_types'] ) ? sanitize_text_field( (string) $_POST['object_types'] ) : '';
        $rewrite_slug = isset( $_POST['rewrite_slug'] ) ? sanitize_title( (string) $_POST['rewrite_slug'] ) : '';

        if ( '' === $slug || '' === $plural || '' === $singular ) {
            return null;
        }

        return new TaxonomyDefinition(
            slug: $slug,
            object_types: $this->parse_slug_list( $object_types_raw ),
            labels: [
                'name'          => $plural,
                'singular_name' => $singular,
                'menu_name'     => $plural,
            ],
            hierarchical: isset( $_POST['hierarchical'] ),
            public: isset( $_POST['public'] ),
            show_ui: isset( $_POST['show_ui'] ),
            show_admin_column: isset( $_POST['show_admin_column'] ),
            show_in_rest: isset( $_POST['show_in_rest'] ),
            rewrite: [
                'slug'         => '' !== $rewrite_slug ? $rewrite_slug : $slug,
                'with_front'   => isset( $_POST['with_front'] ),
                'hierarchical' => isset( $_POST['rewrite_hierarchical'] ),
            ],
            active: isset( $_POST['active'] )
        );
    }

    /**
     * @return array<string>
     */
    private function parse_slug_list( string $raw ) : array {
        $values = [];

        foreach ( preg_split( '/\s*,\s*/', $raw ) ?: [] as $value ) {
            $value = sanitize_key( (string) $value );
            if ( '' !== $value ) {
                $values[ $value ] = true;
            }
        }

        return array_keys( $values );
    }

    /**
     * @return array<MetaFieldDefinition>
     */
    private function parse_metabox_fields( string $fields_raw ) : array {
        $allowed_field_types = [
            'text',
            'textarea',
            'select',
            'radio',
            'checkbox',
            'switcher',
            'wysiwyg',
            'date',
            'url',
            'media',
            'gallery',
        ];

        $field_objects = [];
        foreach ( preg_split( "/\r\n|\n|\r/", $fields_raw ) as $line ) {
            $line = trim( (string) $line );
            if ( '' === $line ) {
                continue;
            }

            $parts = array_map( 'trim', explode( '|', $line ) );
            $name = isset( $parts[0] ) ? sanitize_key( (string) $parts[0] ) : '';
            if ( '' === $name ) {
                continue;
            }

            $field_title = isset( $parts[1] ) && '' !== $parts[1] ? sanitize_text_field( (string) $parts[1] ) : ucwords( str_replace( [ '_', '-' ], ' ', $name ) );
            $type = isset( $parts[2] ) ? sanitize_key( (string) $parts[2] ) : 'text';
            if ( ! in_array( $type, $allowed_field_types, true ) ) {
                $type = 'text';
            }

            $description = isset( $parts[3] ) ? sanitize_text_field( (string) $parts[3] ) : '';
            $default_value = isset( $parts[4] ) ? sanitize_text_field( (string) $parts[4] ) : '';
            $options = [];

            if ( isset( $parts[5] ) && '' !== $parts[5] ) {
                foreach ( preg_split( '/\s*,\s*/', (string) $parts[5] ) ?: [] as $raw_option ) {
                    $raw_option = trim( (string) $raw_option );
                    if ( '' === $raw_option ) {
                        continue;
                    }

                    $pair = array_map( 'trim', explode( ':', $raw_option, 2 ) );
                    $value = sanitize_text_field( (string) ( $pair[0] ?? '' ) );
                    if ( '' === $value ) {
                        continue;
                    }

                    $options[] = [
                        'value' => $value,
                        'label' => sanitize_text_field( (string) ( $pair[1] ?? $value ) ),
                    ];
                }
            }

            $field_objects[] = new MetaFieldDefinition(
                name: $name,
                title: $field_title,
                type: $type,
                description: $description,
                required: false,
                default_value: $default_value,
                options: $options
            );
        }

        return $field_objects;
    }

    /**
     * @param array<string,scalar> $args
     */
    private function redirect_admin_page( string $page, array $args = [] ) : void {
        $args = array_merge( [ 'page' => $page ], $args );
        wp_safe_redirect( admin_url( 'admin.php?' . http_build_query( $args ) ) );
        exit;
    }
}
