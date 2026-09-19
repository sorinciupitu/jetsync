<?php
declare( strict_types=1 );

namespace JetSync\Admin\Pages;

use JetSync\Core\JetSync;
use JetSync\Compatibility\Detector;

/**
 * Renders the main JetSync Dashboard Page.
 */
class DashboardPage extends BasePage {

    /**
     * Get the unique slug of the page.
     *
     * @return string
     */
    public static function get_slug() : string {
        return 'jet-sync-dashboard';
    }

    /**
     * Get the page title.
     *
     * @return string
     */
    public static function get_title() : string {
        return __( 'Dashboard', 'jet-sync' );
    }

    /**
     * Render the page content.
     *
     * @return void
     */
    public function render() : void {
        // Instantiate compatibility detector
        $detector = new Detector();
        $jet_engine_active = $detector->is_jet_engine_active();
        $compatibility_mode = get_option( 'jetsync_settings' )['compatibility_mode'] ?? true;

        // Fetch counts
        $je_cpt_count = $detector->get_cpt_count();
        $je_tax_count = $detector->get_taxonomy_count();

        /** @var \JetSync\Registry\PostTypeRegistry|null $cpt_registry */
        $cpt_registry = JetSync::get_instance()->get( 'cpt_registry' );
        $js_cpt_count = $cpt_registry ? count( $cpt_registry->get_all() ) : 0;

        /** @var \JetSync\Registry\TaxonomyRegistry|null $taxonomy_registry */
        $taxonomy_registry = JetSync::get_instance()->get( 'taxonomy_registry' );
        $js_tax_count = $taxonomy_registry ? count( $taxonomy_registry->get_all() ) : 0;

        /** @var \JetSync\Registry\MetaBoxRegistry|null $metabox_registry */
        $metabox_registry = JetSync::get_instance()->get( 'metabox_registry' );
        $js_meta_fields_count = 0;
        if ( $metabox_registry ) {
            foreach ( $metabox_registry->get_all() as $mb ) {
                if ( $mb instanceof \JetSync\Registry\Model\MetaBoxDefinition ) {
                    $js_meta_fields_count += count( $mb->get_fields() );
                }
            }
        }

        /** @var \JetSync\Registry\RelationRegistry|null $relation_registry */
        $relation_registry = JetSync::get_instance()->get( 'relation_registry' );
        $js_relations_count = $relation_registry ? count( $relation_registry->get_all() ) : 0;

        $last_scan = get_option( 'jetsync_last_scan_report', [] );
        if ( ! is_array( $last_scan ) ) {
            $last_scan = [];
        }

        $je_meta_fields_count = 0;
        if ( isset( $last_scan['meta_boxes'] ) && is_array( $last_scan['meta_boxes'] ) && !empty($last_scan['meta_boxes']) ) {
            foreach ( $last_scan['meta_boxes'] as $mb ) {
                if ( is_array( $mb ) && isset( $mb['fields_count'] ) ) {
                    $je_meta_fields_count += (int) $mb['fields_count'];
                }
            }
        }
        // Fallback: live snapshot when no scan yet - guarantees dashboard is not 0
        if ( 0 === $je_meta_fields_count ) {
            $snap = \JetSync\Compatibility\SnapshotService::snapshot_post_types_and_taxonomies();
            $ptSlugs = array_column($snap['cpts'],'slug');
            if ( !empty($ptSlugs) ) {
                $autoMbs = \JetSync\Compatibility\SnapshotService::snapshot_meta_boxes_from_db(array_slice($ptSlugs,0,12));
                foreach ( $autoMbs as $mb ) $je_meta_fields_count += count($mb->get_fields());
            }
        }

        $je_relations_count = 0;
        if ( isset( $last_scan['relations'] ) && is_array( $last_scan['relations'] ) && !empty($last_scan['relations']) ) {
            $je_relations_count = count( $last_scan['relations'] );
        }
        if ( 0 === $je_relations_count && $detector->is_jet_engine_active() ) {
            $snapRels = \JetSync\Compatibility\SnapshotService::snapshot_relations();
            $je_relations_count = count($snapRels);
        }

        // Generate wizard security nonce
        $wizard_nonce = wp_create_nonce( 'jetsync_wizard_nonce' );

        ?>
        <!-- Localized Script Config -->
        <script type="text/javascript">
            window.jetSyncConfig = {
                ajaxUrl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
                nonce: '<?php echo esc_js( $wizard_nonce ); ?>',
                adminPageUrl: '<?php echo esc_url( admin_url( 'admin.php' ) ); ?>'
            };
        </script>

        <div class="wrap jetsync-wrap">
            <!-- Header Section -->
            <header class="jetsync-header">
                <div class="jetsync-header-logo">
                    <span class="jetsync-badge-version">v<?php echo esc_html( JETSYNC_VERSION ); ?></span>
                    <h1>JetSync</h1>
                </div>
                <div class="jetsync-header-status">
                    <?php if ( $jet_engine_active ) : ?>
                        <span class="jetsync-status-badge active">
                            <span class="pulse-dot"></span>
                            <?php esc_html_e( 'JetEngine Connected', 'jet-sync' ); ?>
                        </span>
                    <?php else : ?>
                        <span class="jetsync-status-badge inactive">
                            <?php esc_html_e( 'JetEngine Missing', 'jet-sync' ); ?>
                        </span>
                    <?php endif; ?>

                    <?php if ( $compatibility_mode && $jet_engine_active ) : ?>
                        <span class="jetsync-status-badge info">
                            <?php esc_html_e( 'Compatibility Mode: ON', 'jet-sync' ); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </header>

            <!-- Cards / Statistics Row -->
            <div class="jetsync-stats-grid">
                <div class="jetsync-stat-card">
                    <div class="jetsync-stat-icon cpt-icon">
                        <span class="dashicons dashicons-admin-post"></span>
                    </div>
                    <div class="jetsync-stat-content">
                        <h3>Custom Post Types</h3>
                        <p class="stat-number">
                            <span class="js-count"><?php echo esc_html( (string) $js_cpt_count ); ?></span>
                            <span class="separator">/</span>
                            <span class="je-count"><?php echo esc_html( (string) $je_cpt_count ); ?></span>
                        </p>
                        <span class="stat-label"><?php esc_html_e( 'JetSync / JetEngine', 'jet-sync' ); ?></span>
                    </div>
                </div>
                <div class="jetsync-stat-card">
                    <div class="jetsync-stat-icon taxonomy-icon">
                        <span class="dashicons dashicons-category"></span>
                    </div>
                    <div class="jetsync-stat-content">
                        <h3>Custom Taxonomies</h3>
                        <p class="stat-number">
                            <span class="js-count"><?php echo esc_html( (string) $js_tax_count ); ?></span>
                            <span class="separator">/</span>
                            <span class="je-count"><?php echo esc_html( (string) $je_tax_count ); ?></span>
                        </p>
                        <span class="stat-label"><?php esc_html_e( 'JetSync / JetEngine', 'jet-sync' ); ?></span>
                    </div>
                </div>
                <div class="jetsync-stat-card">
                    <div class="jetsync-stat-icon field-icon">
                        <span class="dashicons dashicons-edit-page"></span>
                    </div>
                    <div class="jetsync-stat-content">
                        <h3>Meta Fields</h3>
                        <p class="stat-number">
                            <span class="js-count"><?php echo esc_html( (string) $js_meta_fields_count ); ?></span>
                            <span class="separator">/</span>
                            <span class="je-count"><?php echo esc_html( (string) $je_meta_fields_count ); ?></span>
                        </p>
                        <span class="stat-label"><?php esc_html_e( 'JetSync / JetEngine', 'jet-sync' ); ?></span>
                    </div>
                </div>
                <div class="jetsync-stat-card">
                    <div class="jetsync-stat-icon relation-icon">
                        <span class="dashicons dashicons-admin-links"></span>
                    </div>
                    <div class="jetsync-stat-content">
                        <h3>Object Relations</h3>
                        <p class="stat-number">
                            <span class="js-count"><?php echo esc_html( (string) $js_relations_count ); ?></span>
                            <span class="separator">/</span>
                            <span class="je-count"><?php echo esc_html( (string) $je_relations_count ); ?></span>
                        </p>
                        <span class="stat-label"><?php esc_html_e( 'JetSync / JetEngine', 'jet-sync' ); ?></span>
                    </div>
                </div>
            </div>

            <!-- Main Workspace Area -->
            <div class="jetsync-workspace">
                <!-- Navigation Tabs -->
                <nav class="jetsync-tabs" aria-label="Dashboard Navigation">
                    <button class="jetsync-tab-link active" data-tab="overview">
                        <span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Overview', 'jet-sync' ); ?>
                    </button>
                    <button class="jetsync-tab-link" data-tab="migration">
                        <span class="dashicons dashicons-migrate"></span> <?php esc_html_e( 'Migration Wizard', 'jet-sync' ); ?>
                    </button>
                    <button class="jetsync-tab-link" data-tab="registry">
                        <span class="dashicons dashicons-database"></span> <?php esc_html_e( 'Registry Explorer', 'jet-sync' ); ?>
                    </button>
                    <button class="jetsync-tab-link" data-tab="tools">
                        <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Status & Tools', 'jet-sync' ); ?>
                    </button>
                </nav>

                <!-- Tab Panels -->
                <div class="jetsync-tab-content">
                    <!-- Overview Tab -->
                    <div id="overview" class="jetsync-tab-panel active">
                        <div class="jetsync-panel-layout">
                            <div class="main-column">
                                <h2><?php esc_html_e( 'Welcome to JetSync', 'jet-sync' ); ?></h2>
                                <p class="lead-text">
                                    <?php esc_html_e( 'JetSync is built to migrate, host, and register your database structures and configurations created with JetEngine without disrupting active production data.', 'jet-sync' ); ?>
                                </p>

                                <div class="jetsync-features-list">
                                    <div class="feature-item">
                                        <div class="feature-marker">1</div>
                                        <div>
                                            <h4><?php esc_html_e( 'Parallel Coexistence', 'jet-sync' ); ?></h4>
                                            <p><?php esc_html_e( 'Scan and review JetEngine structures in real-time. Keep compatibility mode enabled to safety-test queries before disabling JetEngine.', 'jet-sync' ); ?></p>
                                        </div>
                                    </div>
                                    <div class="feature-item">
                                        <div class="feature-marker">2</div>
                                        <div>
                                            <h4><?php esc_html_e( 'Incremental Synchronization', 'jet-sync' ); ?></h4>
                                            <p><?php esc_html_e( 'Sync schema edits from JetEngine as you tweak styling. We copy configurations, metadata keys, and relation mapping without duplicating database records.', 'jet-sync' ); ?></p>
                                        </div>
                                    </div>
                                    <div class="feature-item">
                                        <div class="feature-marker">3</div>
                                        <div>
                                            <h4><?php esc_html_e( 'Decoupled Autonomy', 'jet-sync' ); ?></h4>
                                            <p><?php esc_html_e( 'Once ready, toggle native controls. Deactivate JetEngine and let JetSync handle low-overhead PHP custom post type and metadata registrations.', 'jet-sync' ); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <aside class="sidebar-column">
                                <div class="jetsync-card highlight-card">
                                    <h3><?php esc_html_e( 'Migration Checklist', 'jet-sync' ); ?></h3>
                                    <ul class="checklist">
                                        <li class="checked">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <?php esc_html_e( 'Plugin Core Bootstrapped', 'jet-sync' ); ?>
                                        </li>
                                        <li class="pending indicator-scan">
                                            <span class="dashicons dashicons-clock"></span>
                                            <?php esc_html_e( 'Scan JetEngine structures', 'jet-sync' ); ?>
                                        </li>
                                        <li class="pending indicator-import">
                                            <span class="dashicons dashicons-clock"></span>
                                            <?php esc_html_e( 'Import registry schemas', 'jet-sync' ); ?>
                                        </li>
                                    </ul>
                                    <button class="jetsync-btn primary-btn btn-full trigger-migration-tab">
                                        <?php esc_html_e( 'Go to Migration Wizard', 'jet-sync' ); ?>
                                    </button>
                                </div>
                            </aside>
                        </div>
                    </div>

                    <!-- Migration Wizard Tab -->
                    <div id="migration" class="jetsync-tab-panel">
                        <h2><?php esc_html_e( 'JetEngine Migration Wizard', 'jet-sync' ); ?></h2>
                        <p class="lead-text"><?php esc_html_e( 'Run a complete, non-destructive sync of all Custom Post Types, Taxonomies, Meta Boxes, and Relations from JetEngine.', 'jet-sync' ); ?></p>

                        <!-- Wizard Steps Progress Bar -->
                        <div class="wizard-steps-container">
                            <div class="wizard-step active" data-step="1">
                                <div class="step-num">1</div>
                                <div class="step-label">Analysis Scan</div>
                            </div>
                            <div class="wizard-line"></div>
                            <div class="wizard-step" data-step="2">
                                <div class="step-num">2</div>
                                <div class="step-label">Import Registry</div>
                            </div>
                            <div class="wizard-line"></div>
                            <div class="wizard-step" data-step="3">
                                <div class="step-num">3</div>
                                <div class="step-label">Sync Relations</div>
                            </div>
                            <div class="wizard-line"></div>
                            <div class="wizard-step" data-step="4">
                                <div class="step-num">4</div>
                                <div class="step-label">Decouple Engine</div>
                            </div>
                        </div>

                        <!-- Step Content Boxes -->
                        <div class="wizard-step-content current" id="step-content-1">
                            <div class="wizard-card-body">
                                <h3><?php esc_html_e( 'Step 1: Scan JetEngine Configurations', 'jet-sync' ); ?></h3>
                                <p><?php esc_html_e( 'Click the button below to parse JetEngine\'s database definitions stored in options and active tables. This will do a dry-run sync without saving any modifications.', 'jet-sync' ); ?></p>
                                
                                <div class="scan-status-alert info">
                                    <span class="dashicons dashicons-info"></span>
                                    <div>
                                        <strong><?php esc_html_e( 'Safety Guarantee:', 'jet-sync' ); ?></strong>
                                        <?php esc_html_e( 'This operation only reads options and configuration schemas. No post content, terms, metadata values or listings are deleted or modified.', 'jet-sync' ); ?>
                                    </div>
                                </div>

                                <div class="scan-actions-group">
                                    <button class="jetsync-btn success-btn start-scan-btn">
                                        <span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Start Diagnostics Scan', 'jet-sync' ); ?>
                                    </button>
                                </div>

                                <!-- AJAX Results Placeholder -->
                                <div id="jetsync-scan-results" style="display:none; margin-top:2rem; padding-top:2rem; border-top:1px solid var(--border-color);">
                                    <!-- Populated via jQuery -->
                                </div>
                            </div>
                        </div>

                        <div class="wizard-step-content" id="step-content-2">
                            <div class="wizard-card-body">
                                <h3><?php esc_html_e( 'Step 2: Schema Configuration Sync Success', 'jet-sync' ); ?></h3>
                                <p><?php esc_html_e( 'All detected Custom Post Types and Custom Taxonomies have been imported into the JetSync local registry.', 'jet-sync' ); ?></p>

                                <div class="scan-status-alert info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0;">
                                    <span class="dashicons dashicons-yes-alt" style="color:var(--success);"></span>
                                    <div>
                                        <strong><?php esc_html_e( 'Schemas Imported successfully!', 'jet-sync' ); ?></strong>
                                        <?php esc_html_e( 'Configurations are now stored in JetSync options tables. In the next stage (Stage 5), we will synchronize object relationship links.', 'jet-sync' ); ?>
                                    </div>
                                </div>

                                <div class="button-group">
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-cpts' ) ); ?>" class="jetsync-btn secondary-btn">
                                        <?php esc_html_e( 'View Post Types', 'jet-sync' ); ?>
                                    </a>
                                    <button class="jetsync-btn primary-btn continue-to-step3-btn">
                                        <?php esc_html_e( 'Continue to Relations (Stage 5)', 'jet-sync' ); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Registry Explorer Tab -->
                    <div id="registry" class="jetsync-tab-panel">
                        <h2><?php esc_html_e( 'Registry Explorer', 'jet-sync' ); ?></h2>
                        <p class="lead-text"><?php esc_html_e( 'Browse schemas and properties saved inside JetSync\'s local registry.', 'jet-sync' ); ?></p>
                        
                        <?php if ( $js_cpt_count === 0 && $js_tax_count === 0 ) : ?>
                            <div class="registry-empty-state">
                                <span class="dashicons dashicons-open-folder"></span>
                                <h3><?php esc_html_e( 'Registry is Empty', 'jet-sync' ); ?></h3>
                                <p><?php esc_html_e( 'Run a diagnostic migration scan to copy definitions from JetEngine or add custom elements.', 'jet-sync' ); ?></p>
                                <button class="jetsync-btn primary-btn trigger-migration-tab">
                                    <?php esc_html_e( 'Run Initial Scan', 'jet-sync' ); ?>
                                </button>
                            </div>
                        <?php else : ?>
                            <div class="tools-grid">
                                <div class="jetsync-card" style="margin-bottom:0;">
                                    <h3><?php esc_html_e( 'Post Types in JetSync', 'jet-sync' ); ?></h3>
                                    <p style="font-size:2rem; font-weight:700; color:var(--text-primary); margin: 0.5rem 0;"><?php echo esc_html( (string) $js_cpt_count ); ?></p>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-cpts' ) ); ?>" class="jetsync-btn secondary-btn" style="margin-top:1rem;">
                                        <?php esc_html_e( 'Open CPT Registry', 'jet-sync' ); ?>
                                    </a>
                                </div>

                                <div class="jetsync-card" style="margin-bottom:0;">
                                    <h3><?php esc_html_e( 'Taxonomies in JetSync', 'jet-sync' ); ?></h3>
                                    <p style="font-size:2rem; font-weight:700; color:var(--text-primary); margin: 0.5rem 0;"><?php echo esc_html( (string) $js_tax_count ); ?></p>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-taxonomies' ) ); ?>" class="jetsync-btn secondary-btn" style="margin-top:1rem;">
                                        <?php esc_html_e( 'Open Taxonomy Registry', 'jet-sync' ); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tools & Status Tab -->
                    <div id="tools" class="jetsync-tab-panel">
                        <h2><?php esc_html_e( 'Status & Utilities', 'jet-sync' ); ?></h2>
                        
                        <div class="tools-grid">
                            <div class="jetsync-card">
                                <h3><?php esc_html_e( 'System Report', 'jet-sync' ); ?></h3>
                                <table class="system-status-table">
                                    <tr>
                                        <td><strong><?php esc_html_e( 'WordPress Version', 'jet-sync' ); ?></strong></td>
                                        <td><?php echo esc_html( $GLOBALS['wp_version'] ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'PHP Version', 'jet-sync' ); ?></strong></td>
                                        <td><?php echo esc_html( PHP_VERSION ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'JetSync Version', 'jet-sync' ); ?></strong></td>
                                        <td><?php echo esc_html( JETSYNC_VERSION ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'Active Database Prefix', 'jet-sync' ); ?></strong></td>
                                        <td><code><?php echo esc_html( $GLOBALS['wpdb']->prefix ); ?></code></td>
                                    </tr>
                                </table>
                            </div>

                            <div class="jetsync-card">
                                <h3><?php esc_html_e( 'Developer Commands', 'jet-sync' ); ?></h3>
                                <p><?php esc_html_e( 'Maintenance tasks to rebuild indexes, clear caches, or wipe test registries.', 'jet-sync' ); ?></p>
                                <div class="button-group">
                                    <button class="jetsync-btn secondary-btn" disabled>
                                        <?php esc_html_e( 'Clear Registry Cache', 'jet-sync' ); ?>
                                    </button>
                                    <button class="jetsync-btn danger-btn-outline" disabled>
                                        <?php esc_html_e( 'Purge All JetSync Settings', 'jet-sync' ); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
