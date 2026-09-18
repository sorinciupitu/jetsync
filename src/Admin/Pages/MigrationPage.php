<?php
declare( strict_types=1 );

namespace JetSync\Admin\Pages;

use JetSync\Core\JetSync;
use JetSync\Migration\MigrationPlan;
use JetSync\Migration\MigrationRunner;
use JetSync\Migration\IntegrityChecker;
use JetSync\Migration\RepairManager;

/**
 * Migration Center admin page — multi-step wizard for finalising migration.
 */
class MigrationPage extends BasePage {

	/** {@inheritdoc} */
	public static function get_slug() : string {
		return 'jet-sync-migration';
	}

	/** {@inheritdoc} */
	public static function get_title() : string {
		return __( 'Migration Wizard', 'jet-sync' );
	}

	/** {@inheritdoc} */
	public function render() : void {
		/** @var MigrationRunner|null $runner */
		$runner = JetSync::get_instance()->get( 'migration_runner' );

		$plan          = MigrationPlan::load();
		$plan_status   = $plan ? $plan->get_status() : null;
		$plan_log      = $plan ? $plan->get_log()    : [];
		$plan_steps    = $plan ? $plan->get_steps()  : [];
		$plan_current  = $plan ? $plan->get_current_step_index() : 0;
		$all_steps     = $runner ? $runner->get_all_steps() : [];
		$je_active     = defined( 'JET_ENGINE_VERSION' );
		$last_integrity = IntegrityChecker::get_last_report();
		$last_repair    = RepairManager::get_last_report();

		// Audit: count items in each registry
		$plugin   = JetSync::get_instance();
		$cpt_count  = $plugin->get( 'cpt_registry' )      ? count( $plugin->get( 'cpt_registry' )->get_all() )      : 0;
		$tax_count  = $plugin->get( 'taxonomy_registry' )  ? count( $plugin->get( 'taxonomy_registry' )->get_all() )  : 0;
		$meta_count = $plugin->get( 'metabox_registry' )   ? count( $plugin->get( 'metabox_registry' )->get_all() )   : 0;
		$rel_count  = $plugin->get( 'relation_registry' )  ? count( $plugin->get( 'relation_registry' )->get_all() )  : 0;
		?>
		<div class="wrap jetsync-wrap">

			<!-- Header -->
			<header class="jetsync-header">
				<div class="jetsync-header-logo">
					<span class="jetsync-badge-version">v<?php echo esc_html( JETSYNC_VERSION ); ?></span>
					<h1>JetSync</h1>
				</div>
			</header>

			<!-- Page title -->
			<div class="jetsync-section-title">
				<h2><?php esc_html_e( 'Migration Center', 'jet-sync' ); ?></h2>
				<span class="jetsync-section-subtitle"><?php esc_html_e( 'Audit, migrate, export and restore your JetSync configuration', 'jet-sync' ); ?></span>
			</div>

			<div class="jetsync-workspace jetsync-migration-grid">

				<!-- ============================================================
				     COLUMN 1 — Audit & Status
				     ============================================================ -->
				<section class="migration-card" id="jetsync-audit-card">
					<div class="migration-card-header">
						<span class="dashicons dashicons-search"></span>
						<h3><?php esc_html_e( 'Pre-Migration Audit', 'jet-sync' ); ?></h3>
					</div>

					<ul class="audit-list">
						<li>
							<span class="audit-icon <?php echo $je_active ? 'audit-ok' : 'audit-warn'; ?>">
								<span class="dashicons <?php echo $je_active ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
							</span>
							<span class="audit-label">
								<?php if ( $je_active ) : ?>
									<?php esc_html_e( 'JetEngine is active — import wizard available', 'jet-sync' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'JetEngine not detected — JetSync running standalone', 'jet-sync' ); ?>
								<?php endif; ?>
							</span>
						</li>

						<li>
							<span class="audit-icon <?php echo $cpt_count > 0 ? 'audit-ok' : 'audit-neutral'; ?>">
								<span class="dashicons dashicons-admin-post"></span>
							</span>
							<span class="audit-label">
								<?php printf(
									/* translators: %d: count */
									esc_html( _n( '%d Custom Post Type registered', '%d Custom Post Types registered', $cpt_count, 'jet-sync' ) ),
									(int) $cpt_count
								); ?>
							</span>
						</li>

						<li>
							<span class="audit-icon <?php echo $tax_count > 0 ? 'audit-ok' : 'audit-neutral'; ?>">
								<span class="dashicons dashicons-tag"></span>
							</span>
							<span class="audit-label">
								<?php printf(
									esc_html( _n( '%d Taxonomy registered', '%d Taxonomies registered', $tax_count, 'jet-sync' ) ),
									(int) $tax_count
								); ?>
							</span>
						</li>

						<li>
							<span class="audit-icon <?php echo $meta_count > 0 ? 'audit-ok' : 'audit-neutral'; ?>">
								<span class="dashicons dashicons-forms"></span>
							</span>
							<span class="audit-label">
								<?php printf(
									esc_html( _n( '%d Meta Box registered', '%d Meta Boxes registered', $meta_count, 'jet-sync' ) ),
									(int) $meta_count
								); ?>
							</span>
						</li>

						<li>
							<span class="audit-icon <?php echo $rel_count > 0 ? 'audit-ok' : 'audit-neutral'; ?>">
								<span class="dashicons dashicons-admin-links"></span>
							</span>
							<span class="audit-label">
								<?php printf(
									esc_html( _n( '%d Relation schema registered', '%d Relation schemas registered', $rel_count, 'jet-sync' ) ),
									(int) $rel_count
								); ?>
							</span>
						</li>
					</ul>

					<?php if ( 0 === $cpt_count && 0 === $tax_count && 0 === $meta_count ) : ?>
						<div class="migration-notice notice-warning">
							<span class="dashicons dashicons-info"></span>
							<?php esc_html_e( 'No JetSync definitions found. Go to Dashboard and run the Import Wizard first.', 'jet-sync' ); ?>
						</div>
					<?php endif; ?>
				</section>

				<section class="migration-card" id="jetsync-tools-card">
					<div class="migration-card-header">
						<span class="dashicons dashicons-admin-tools"></span>
						<h3><?php esc_html_e( 'Tools', 'jet-sync' ); ?></h3>
					</div>

					<div class="migration-tools">
						<p><?php esc_html_e( 'Validate, repair and re-sync to keep JetSync consistent during migration.', 'jet-sync' ); ?></p>

						<div class="migration-tool-row">
							<button id="jetsync-validate-integrity" class="jetsync-btn secondary-btn">
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'Validate', 'jet-sync' ); ?>
							</button>
						</div>

						<?php if ( $last_integrity && isset( $last_integrity['summary'] ) ) : ?>
							<div class="migration-notice <?php echo ( (int) ( $last_integrity['summary']['errors'] ?? 0 ) ) > 0 ? 'notice-error' : 'notice-success'; ?>">
								<span class="dashicons <?php echo ( (int) ( $last_integrity['summary']['errors'] ?? 0 ) ) > 0 ? 'dashicons-dismiss' : 'dashicons-yes-alt'; ?>"></span>
								<?php
								printf(
									/* translators: 1: date, 2: errors, 3: warnings */
									esc_html__( 'Last validation: %1$s — %2$d error(s), %3$d warning(s).', 'jet-sync' ),
									esc_html( date_i18n( 'Y-m-d H:i:s', (int) ( $last_integrity['timestamp'] ?? time() ) ) ),
									(int) ( $last_integrity['summary']['errors'] ?? 0 ),
									(int) ( $last_integrity['summary']['warnings'] ?? 0 )
								);
								?>
							</div>
						<?php endif; ?>

						<hr />

						<div class="migration-tool-row">
							<label class="migration-checkbox">
								<input type="checkbox" id="jetsync-repair-cleanup-orphans">
								<?php esc_html_e( 'Clean orphan relation connections', 'jet-sync' ); ?>
							</label>
							<label class="migration-checkbox">
								<input type="checkbox" id="jetsync-repair-cleanup-meta">
								<?php esc_html_e( 'Clean orphan relation meta', 'jet-sync' ); ?>
							</label>
							<button id="jetsync-repair-integrity" class="jetsync-btn secondary-btn">
								<span class="dashicons dashicons-hammer"></span>
								<?php esc_html_e( 'Repair', 'jet-sync' ); ?>
							</button>
						</div>

						<?php if ( $last_repair ) : ?>
							<div class="migration-notice notice-info">
								<span class="dashicons dashicons-info"></span>
								<?php
								printf(
									/* translators: 1: date, 2: connections, 3: meta */
									esc_html__( 'Last repair: %1$s — deleted %2$d connections, %3$d meta rows.', 'jet-sync' ),
									esc_html( date_i18n( 'Y-m-d H:i:s', (int) ( $last_repair['timestamp'] ?? time() ) ) ),
									(int) ( $last_repair['deleted']['connections'] ?? 0 ),
									(int) ( $last_repair['deleted']['meta'] ?? 0 )
								);
								?>
							</div>
						<?php endif; ?>

						<hr />

						<div class="migration-tool-row">
							<button id="jetsync-resync-jetengine" class="jetsync-btn secondary-btn" <?php echo $je_active ? '' : 'disabled'; ?>>
								<span class="dashicons dashicons-update"></span>
								<?php esc_html_e( 'Re-sync from JetEngine', 'jet-sync' ); ?>
							</button>
							<?php if ( ! $je_active ) : ?>
								<small><?php esc_html_e( 'JetEngine not detected.', 'jet-sync' ); ?></small>
							<?php endif; ?>
						</div>

						<div id="jetsync-tools-log" class="migration-ajax-log" style="display:none;"></div>
					</div>
				</section>

				<!-- ============================================================
				     COLUMN 2 — Migration Wizard
				     ============================================================ -->
				<section class="migration-card" id="jetsync-wizard-card">
					<div class="migration-card-header">
						<span class="dashicons dashicons-migrate"></span>
						<h3><?php esc_html_e( 'Migration Wizard', 'jet-sync' ); ?></h3>
					</div>

					<!-- Steps overview -->
					<div class="wizard-steps-overview">
						<?php
						$step_ids_to_show = $plan && ! empty( $plan_steps ) ? $plan_steps : array_keys( $all_steps );
						foreach ( $step_ids_to_show as $sid ) :
							$step = $all_steps[ $sid ] ?? null;
							if ( ! $step ) {
								continue;
							}
							$step_log    = $plan_log[ $sid ] ?? null;
							$is_done     = ( $step_log && $step_log['status'] === 'success' );
							$is_failed   = ( $step_log && $step_log['status'] === 'failed' );
							$is_current  = ( $plan && $plan->get_current_step_id() === $sid && ! $plan->is_complete() );
							$step_class  = $is_done ? 'step-done' : ( $is_failed ? 'step-failed' : ( $is_current ? 'step-current' : 'step-pending' ) );
						?>
						<div class="wizard-step <?php echo esc_attr( $step_class ); ?>">
							<div class="step-indicator">
								<?php if ( $is_done ) : ?>
									<span class="dashicons dashicons-yes-alt"></span>
								<?php elseif ( $is_failed ) : ?>
									<span class="dashicons dashicons-dismiss"></span>
								<?php elseif ( $is_current ) : ?>
									<span class="dashicons dashicons-update spin-icon"></span>
								<?php else : ?>
									<span class="dashicons dashicons-marker"></span>
								<?php endif; ?>
							</div>
							<div class="step-content">
								<strong><?php echo esc_html( $step->get_label() ); ?></strong>
								<small><?php echo esc_html( $step->get_description() ); ?></small>
								<?php if ( $step_log ) : ?>
									<em class="step-message"><?php echo esc_html( $step_log['message'] ); ?></em>
								<?php endif; ?>
								<?php if ( ! $step->is_reversible() ) : ?>
									<span class="tag step-irreversible"><?php esc_html_e( 'Irreversible', 'jet-sync' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<?php endforeach; ?>
					</div>

					<!-- Progress bar -->
					<?php if ( $plan && ! empty( $plan_steps ) ) :
						$pct = (int) round( ( $plan_current / count( $plan_steps ) ) * 100 );
					?>
					<div class="wizard-progress-wrap">
						<div class="wizard-progress-bar">
							<div class="wizard-progress-fill" style="width:<?php echo esc_attr( $pct ); ?>%"></div>
						</div>
						<span class="wizard-progress-label"><?php echo esc_html( $plan_current . ' / ' . count( $plan_steps ) ); ?></span>
					</div>
					<?php endif; ?>

					<!-- Status badge -->
					<?php if ( $plan_status ) : ?>
					<div class="wizard-status-row">
						<span class="jetsync-status-badge <?php echo esc_attr( $plan_status ); ?>">
							<?php echo esc_html( ucfirst( $plan_status ) ); ?>
						</span>
					</div>
					<?php endif; ?>

					<!-- Actions -->
					<div class="wizard-actions" id="jetsync-wizard-actions">
						<?php if ( ! $plan || $plan->get_status() === MigrationPlan::STATUS_PENDING ) : ?>
							<!-- Configure options before starting -->
							<label class="migration-checkbox">
								<input type="checkbox" id="jetsync-include-deactivate" checked>
								<?php esc_html_e( 'Deactivate JetEngine after migration (recommended)', 'jet-sync' ); ?>
							</label>
							<button id="jetsync-start-migration" class="jetsync-btn primary-btn">
								<span class="dashicons dashicons-controls-play"></span>
								<?php esc_html_e( 'Start Migration', 'jet-sync' ); ?>
							</button>

						<?php elseif ( $plan->get_status() === MigrationPlan::STATUS_RUNNING || $plan->get_status() === MigrationPlan::STATUS_PENDING ) : ?>
							<button id="jetsync-next-step" class="jetsync-btn primary-btn">
								<span class="dashicons dashicons-controls-skipforward"></span>
								<?php esc_html_e( 'Run Next Step', 'jet-sync' ); ?>
							</button>

						<?php elseif ( $plan->get_status() === MigrationPlan::STATUS_COMPLETED ) : ?>
							<div class="migration-notice notice-success">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Migration completed successfully! JetSync is now running independently.', 'jet-sync' ); ?>
							</div>
							<button id="jetsync-reset-plan" class="jetsync-btn secondary-btn">
								<?php esc_html_e( 'Reset Plan', 'jet-sync' ); ?>
							</button>

						<?php elseif ( $plan->get_status() === MigrationPlan::STATUS_FAILED ) : ?>
							<div class="migration-notice notice-error">
								<span class="dashicons dashicons-dismiss"></span>
								<?php esc_html_e( 'A step failed. Review the log above and roll back if necessary.', 'jet-sync' ); ?>
							</div>
							<button id="jetsync-rollback" class="jetsync-btn danger-btn-outline">
								<span class="dashicons dashicons-undo"></span>
								<?php esc_html_e( 'Roll Back', 'jet-sync' ); ?>
							</button>
							<button id="jetsync-reset-plan" class="jetsync-btn secondary-btn">
								<?php esc_html_e( 'Reset Plan', 'jet-sync' ); ?>
							</button>

						<?php elseif ( $plan->get_status() === MigrationPlan::STATUS_ROLLED_BACK ) : ?>
							<div class="migration-notice notice-warning">
								<span class="dashicons dashicons-undo"></span>
								<?php esc_html_e( 'Plan rolled back. You can start a new migration at any time.', 'jet-sync' ); ?>
							</div>
							<button id="jetsync-reset-plan" class="jetsync-btn secondary-btn">
								<?php esc_html_e( 'Start New Plan', 'jet-sync' ); ?>
							</button>
						<?php endif; ?>
					</div>

					<!-- AJAX log output -->
					<div id="jetsync-migration-log" class="migration-ajax-log" style="display:none;"></div>
				</section>

				<!-- ============================================================
				     COLUMN 3 — Export / Import
				     ============================================================ -->
				<section class="migration-card migration-card-full" id="jetsync-export-card">
					<div class="migration-card-header">
						<span class="dashicons dashicons-migrate"></span>
						<h3><?php esc_html_e( 'Export / Import Configuration', 'jet-sync' ); ?></h3>
					</div>

					<div class="export-import-cols">
						<!-- Export -->
						<div class="export-col">
							<h4><?php esc_html_e( 'Export', 'jet-sync' ); ?></h4>
							<p><?php esc_html_e( 'Download the complete JetSync configuration as a portable JSON file. Use this to back up or move configuration between sites.', 'jet-sync' ); ?></p>
							<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=jetsync_export_config&_wpnonce=' . wp_create_nonce( 'jetsync_export' ) ) ); ?>"
							   class="jetsync-btn primary-btn">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Download JSON Export', 'jet-sync' ); ?>
							</a>
						</div>

						<!-- Import -->
						<div class="import-col">
							<h4><?php esc_html_e( 'Import', 'jet-sync' ); ?></h4>
							<p><?php esc_html_e( 'Upload a previously exported JetSync JSON file to restore or merge configurations.', 'jet-sync' ); ?></p>
							<form id="jetsync-import-form" method="post" enctype="multipart/form-data">
								<?php wp_nonce_field( 'jetsync_import_config', 'jetsync_import_nonce' ); ?>
								<input type="hidden" name="action" value="jetsync_import_config">
								<label class="file-upload-label" for="jetsync-import-file">
									<span class="dashicons dashicons-upload"></span>
									<span id="jetsync-import-filename"><?php esc_html_e( 'Choose JSON file…', 'jet-sync' ); ?></span>
								</label>
								<input type="file" id="jetsync-import-file" name="jetsync_import_file" accept=".json" class="hidden-file-input">
								<button type="submit" id="jetsync-import-submit" class="jetsync-btn secondary-btn" disabled>
									<span class="dashicons dashicons-upload"></span>
									<?php esc_html_e( 'Import Configuration', 'jet-sync' ); ?>
								</button>
							</form>
							<div id="jetsync-import-result" class="migration-ajax-log" style="display:none;"></div>
						</div>
					</div>
				</section>

			</div><!-- .jetsync-migration-grid -->
		</div><!-- .wrap -->

		<script>
		(function($) {
			'use strict';

			var nonce     = '<?php echo esc_js( wp_create_nonce( 'jetsync_ajax' ) ); ?>';
			var ajaxUrl   = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
			var $log      = $('#jetsync-migration-log');
			var $toolsLog = $('#jetsync-tools-log');

			function appendLog( message, type ) {
				var cls = type === 'error' ? 'log-error' : ( type === 'success' ? 'log-success' : 'log-info' );
				$log.show().append( '<div class="log-entry ' + cls + '">' + $('<span>').text(message).html() + '</div>' );
				$log.scrollTop( $log[0].scrollHeight );
			}

			function appendToolsLog( message, type ) {
				var cls = type === 'error' ? 'log-error' : ( type === 'success' ? 'log-success' : 'log-info' );
				$toolsLog.show().append( '<div class="log-entry ' + cls + '">' + $('<span>').text(message).html() + '</div>' );
				$toolsLog.scrollTop( $toolsLog[0].scrollHeight );
			}

			$('#jetsync-validate-integrity').on('click', function() {
				var $btn = $(this);
				$btn.prop('disabled', true);
				$toolsLog.show().html('');
				appendToolsLog('<?php echo esc_js( __( 'Running validation…', 'jet-sync' ) ); ?>', 'info');

				$.post( ajaxUrl, {
					action  : 'jetsync_validate_integrity',
					_wpnonce: nonce,
					for_deactivation: 1
				}, function( res ) {
					if ( res.success && res.data && res.data.report ) {
						var r = res.data.report;
						appendToolsLog( res.data.message || '<?php echo esc_js( __( 'Validation completed.', 'jet-sync' ) ); ?>', 'success' );
						appendToolsLog( 'Errors: ' + (r.summary ? r.summary.errors : 0) + ', Warnings: ' + (r.summary ? r.summary.warnings : 0), (r.summary && r.summary.errors > 0) ? 'error' : 'success' );
						setTimeout( function() { location.reload(); }, 900 );
					} else {
						appendToolsLog( ( res.data && res.data.message ) || '<?php echo esc_js( __( 'Validation failed.', 'jet-sync' ) ); ?>', 'error' );
						$btn.prop('disabled', false);
					}
				});
			});

			$('#jetsync-repair-integrity').on('click', function() {
				var $btn = $(this);
				var cleanupOrphans = $('#jetsync-repair-cleanup-orphans').is(':checked') ? 1 : 0;
				var cleanupMeta    = $('#jetsync-repair-cleanup-meta').is(':checked') ? 1 : 0;

				if ( cleanupOrphans || cleanupMeta ) {
					if ( ! confirm('<?php echo esc_js( __( 'This will delete rows from the database. Continue?', 'jet-sync' ) ); ?>') ) {
						return;
					}
				}

				$btn.prop('disabled', true);
				$toolsLog.show().html('');
				appendToolsLog('<?php echo esc_js( __( 'Running repair…', 'jet-sync' ) ); ?>', 'info');

				$.post( ajaxUrl, {
					action  : 'jetsync_repair_integrity',
					_wpnonce: nonce,
					cleanup_orphan_connections: cleanupOrphans,
					cleanup_orphan_meta: cleanupMeta
				}, function( res ) {
					if ( res.success && res.data && res.data.report ) {
						var r = res.data.report;
						appendToolsLog( res.data.message || '<?php echo esc_js( __( 'Repair completed.', 'jet-sync' ) ); ?>', 'success' );
						appendToolsLog( 'Deleted connections: ' + (r.deleted ? r.deleted.connections : 0) + ', meta: ' + (r.deleted ? r.deleted.meta : 0), 'info' );
						setTimeout( function() { location.reload(); }, 900 );
					} else {
						appendToolsLog( ( res.data && res.data.message ) || '<?php echo esc_js( __( 'Repair failed.', 'jet-sync' ) ); ?>', 'error' );
						$btn.prop('disabled', false);
					}
				});
			});

			$('#jetsync-resync-jetengine').on('click', function() {
				var $btn = $(this);
				$btn.prop('disabled', true);
				$toolsLog.show().html('');
				appendToolsLog('<?php echo esc_js( __( 'Re-syncing from JetEngine…', 'jet-sync' ) ); ?>', 'info');

				$.post( ajaxUrl, {
					action  : 'jetsync_resync_jetengine',
					_wpnonce: nonce
				}, function( res ) {
					if ( res.success ) {
						appendToolsLog( res.data.message || '<?php echo esc_js( __( 'Re-sync completed.', 'jet-sync' ) ); ?>', 'success' );
						setTimeout( function() { location.reload(); }, 900 );
					} else {
						appendToolsLog( ( res.data && res.data.message ) || '<?php echo esc_js( __( 'Re-sync failed.', 'jet-sync' ) ); ?>', 'error' );
						$btn.prop('disabled', false);
					}
				});
			});

			// --- Start migration plan ---
			$('#jetsync-start-migration').on('click', function() {
				var $btn = $(this);
				var includeDeactivate = $('#jetsync-include-deactivate').is(':checked') ? 1 : 0;
				$btn.prop('disabled', true);
				$log.show();
				appendLog('<?php echo esc_js( __( 'Creating migration plan…', 'jet-sync' ) ); ?>', 'info');

				$.post( ajaxUrl, {
					action  : 'jetsync_create_migration_plan',
					_wpnonce: nonce,
					include_deactivate: includeDeactivate
				}, function( res ) {
					if ( res.success ) {
						appendLog( res.data.message || '<?php echo esc_js( __( 'Plan created.', 'jet-sync' ) ); ?>', 'success' );
						setTimeout( function() { location.reload(); }, 800 );
					} else {
						appendLog( ( res.data && res.data.message ) || '<?php echo esc_js( __( 'Failed to create plan.', 'jet-sync' ) ); ?>', 'error' );
						$btn.prop('disabled', false);
					}
				});
			});

			// --- Run next step ---
			$('#jetsync-next-step').on('click', function() {
				var $btn = $(this);
				$btn.prop('disabled', true);
				appendLog('<?php echo esc_js( __( 'Running next step…', 'jet-sync' ) ); ?>', 'info');

				$.post( ajaxUrl, {
					action  : 'jetsync_run_migration_step',
					_wpnonce: nonce
				}, function( res ) {
					if ( res.success ) {
						var d = res.data;
						appendLog( d.message, d.step_result === 'failed' ? 'error' : 'success' );
						setTimeout( function() { location.reload(); }, 900 );
					} else {
						appendLog( ( res.data && res.data.message ) || '<?php echo esc_js( __( 'Step failed.', 'jet-sync' ) ); ?>', 'error' );
						setTimeout( function() { location.reload(); }, 1200 );
					}
				});
			});

			// --- Rollback ---
			$('#jetsync-rollback').on('click', function() {
				if ( ! confirm('<?php echo esc_js( __( 'Roll back all completed reversible steps? This cannot be undone automatically.', 'jet-sync' ) ); ?>') ) {
					return;
				}
				$(this).prop('disabled', true);
				appendLog('<?php echo esc_js( __( 'Rolling back…', 'jet-sync' ) ); ?>', 'info');

				$.post( ajaxUrl, {
					action  : 'jetsync_rollback_migration',
					_wpnonce: nonce
				}, function( res ) {
					if ( res.success ) {
						appendLog( res.data.message, 'success' );
					} else {
						appendLog( ( res.data && res.data.message ) || '<?php echo esc_js( __( 'Rollback failed.', 'jet-sync' ) ); ?>', 'error' );
					}
					setTimeout( function() { location.reload(); }, 1000 );
				});
			});

			// --- Reset plan ---
			$('#jetsync-reset-plan').on('click', function() {
				if ( ! confirm('<?php echo esc_js( __( 'Clear the current migration plan and start fresh?', 'jet-sync' ) ); ?>') ) {
					return;
				}
				$.post( ajaxUrl, {
					action  : 'jetsync_reset_migration_plan',
					_wpnonce: nonce
				}, function() { location.reload(); });
			});

			// --- File input label ---
			$('#jetsync-import-file').on('change', function() {
				var name = this.files[0] ? this.files[0].name : '<?php echo esc_js( __( 'Choose JSON file…', 'jet-sync' ) ); ?>';
				$('#jetsync-import-filename').text( name );
				$('#jetsync-import-submit').prop('disabled', ! this.files[0]);
			});

			// --- Import via AJAX ---
			$('#jetsync-import-form').on('submit', function(e) {
				e.preventDefault();
				var $result = $('#jetsync-import-result');
				var formData = new FormData( this );
				formData.set('_wpnonce', nonce);
				$result.show().html('');

				$.ajax({
					url        : ajaxUrl,
					type       : 'POST',
					data       : formData,
					processData: false,
					contentType: false,
					success    : function( res ) {
						if ( res.success ) {
							$result.html('<div class="log-entry log-success">' + $('<span>').text(res.data.message).html() + '</div>');
						} else {
							$result.html('<div class="log-entry log-error">' + $('<span>').text((res.data && res.data.message) || '<?php echo esc_js( __( 'Import failed.', 'jet-sync' ) ); ?>').html() + '</div>');
						}
					},
					error: function() {
						$result.html('<div class="log-entry log-error"><?php echo esc_js( __( 'Server error during import.', 'jet-sync' ) ); ?></div>');
					}
				});
			});

		})(jQuery);
		</script>
		<?php
	}
}
