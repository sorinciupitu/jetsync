<?php
declare( strict_types=1 );

namespace JetSync\Admin\Pages;

use JetSync\Core\JetSync;
use JetSync\Registry\Model\PostTypeDefinition;

/**
 * Custom Post Types Registry list page.
 */
class CptsPage extends BasePage {

    /**
     * {@inheritdoc}
     */
    public static function get_slug() : string {
        return 'jet-sync-cpts';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_title() : string {
        return __( 'Custom Post Types', 'jet-sync' );
    }

    /**
     * Render CPT list screen.
     *
     * @return void
     */
    public function render() : void {
        /** @var \JetSync\Registry\PostTypeRegistry|null $cpt_registry */
        $cpt_registry = JetSync::get_instance()->get( 'cpt_registry' );
        $cpts = $cpt_registry ? $cpt_registry->get_all() : [];
        $action = isset( $_GET['action'] ) ? sanitize_key( (string) $_GET['action'] ) : '';
        $edit_slug = isset( $_GET['slug'] ) ? sanitize_key( (string) $_GET['slug'] ) : '';
        $current_cpt = ( 'edit' === $action && '' !== $edit_slug && $cpt_registry ) ? $cpt_registry->get( $edit_slug ) : null;
        $is_add = 'add' === $action;
        $is_edit = $current_cpt instanceof PostTypeDefinition;
        $is_form_mode = $is_add || $is_edit;
        $created = isset( $_GET['created'] ) ? (int) $_GET['created'] : -1;
        $updated = isset( $_GET['updated'] ) ? (int) $_GET['updated'] : -1;
        $deleted = isset( $_GET['deleted'] ) ? (int) $_GET['deleted'] : -1;

        ?>
        <div class="wrap jetsync-wrap">
            <header class="jetsync-header">
                <div class="jetsync-header-logo">
                    <span class="jetsync-badge-version">v<?php echo esc_html( JETSYNC_VERSION ); ?></span>
                    <h1>JetSync</h1>
                </div>
            </header>

            <div class="jetsync-section-title">
                <h2><?php esc_html_e( 'Custom Post Types Registry', 'jet-sync' ); ?></h2>
                <a class="jetsync-btn primary-btn add-new-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-cpts&action=add' ) ); ?>">
                    <span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add New Post Type', 'jet-sync' ); ?>
                </a>
            </div>

            <div class="jetsync-workspace">
                <?php if ( 0 === $created ) : ?>
                    <div class="scan-status-alert warning" style="margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-warning"></span>
                        <div><strong><?php esc_html_e( 'Post type was not created.', 'jet-sync' ); ?></strong> <?php esc_html_e( 'Please complete the required fields and use a unique slug.', 'jet-sync' ); ?></div>
                    </div>
                <?php elseif ( 1 === $created ) : ?>
                    <div class="scan-status-alert info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-yes-alt" style="color:var(--success);"></span>
                        <div><strong><?php esc_html_e( 'Post type created.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ( 0 === $updated ) : ?>
                    <div class="scan-status-alert warning" style="margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-warning"></span>
                        <div><strong><?php esc_html_e( 'Post type was not updated.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php elseif ( 1 === $updated ) : ?>
                    <div class="scan-status-alert info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-yes-alt" style="color:var(--success);"></span>
                        <div><strong><?php esc_html_e( 'Post type updated.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ( 0 === $deleted ) : ?>
                    <div class="scan-status-alert warning" style="margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-warning"></span>
                        <div><strong><?php esc_html_e( 'Post type was not deleted.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php elseif ( 1 === $deleted ) : ?>
                    <div class="scan-status-alert info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-yes-alt" style="color:var(--success);"></span>
                        <div><strong><?php esc_html_e( 'Post type deleted.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ( $is_form_mode ) : ?>
                    <?php
                    $definition = $is_edit ? $current_cpt : null;
                    $labels = $definition ? $definition->get_labels() : [];
                    $args = $definition ? $definition->get_args() : [];
                    $supports = isset( $args['supports'] ) && is_array( $args['supports'] ) ? array_map( 'strval', $args['supports'] ) : [ 'title', 'editor', 'thumbnail' ];
                    $rewrite = isset( $args['rewrite'] ) && is_array( $args['rewrite'] ) ? $args['rewrite'] : [ 'slug' => '', 'with_front' => true ];
                    ?>
                    <div style="max-width: 920px;">
                        <h3 style="margin-top:0;"><?php echo esc_html( $is_edit ? __( 'Edit Post Type', 'jet-sync' ) : __( 'Add New Post Type', 'jet-sync' ) ); ?></h3>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php if ( $is_edit ) : ?>
                                <?php wp_nonce_field( 'jetsync_update_cpt' ); ?>
                                <input type="hidden" name="action" value="jetsync_update_cpt">
                                <input type="hidden" name="current_slug" value="<?php echo esc_attr( $definition->get_slug() ); ?>">
                            <?php else : ?>
                                <?php wp_nonce_field( 'jetsync_add_cpt' ); ?>
                                <input type="hidden" name="action" value="jetsync_add_cpt">
                            <?php endif; ?>

                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><label for="jetsync_cpt_plural_label"><?php esc_html_e( 'Plural label', 'jet-sync' ); ?></label></th>
                                    <td><input name="plural_label" type="text" id="jetsync_cpt_plural_label" value="<?php echo esc_attr( (string) ( $labels['name'] ?? '' ) ); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_cpt_singular_label"><?php esc_html_e( 'Singular label', 'jet-sync' ); ?></label></th>
                                    <td><input name="singular_label" type="text" id="jetsync_cpt_singular_label" value="<?php echo esc_attr( (string) ( $labels['singular_name'] ?? '' ) ); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_cpt_slug"><?php esc_html_e( 'Slug', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <input name="slug" type="text" id="jetsync_cpt_slug" value="<?php echo esc_attr( $definition ? $definition->get_slug() : '' ); ?>" class="regular-text" <?php echo $is_edit ? 'readonly' : ''; ?> required>
                                        <?php if ( $is_edit ) : ?>
                                            <p class="description"><?php esc_html_e( 'Slug is locked on edit to avoid breaking existing content mappings.', 'jet-sync' ); ?></p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_cpt_supports"><?php esc_html_e( 'Supports', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <input name="supports" type="text" id="jetsync_cpt_supports" value="<?php echo esc_attr( implode( ',', $supports ) ); ?>" class="regular-text" placeholder="title,editor,thumbnail,excerpt,custom-fields">
                                        <p class="description"><?php esc_html_e( 'Comma separated WordPress supports list.', 'jet-sync' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_cpt_menu_icon"><?php esc_html_e( 'Menu icon', 'jet-sync' ); ?></label></th>
                                    <td><input name="menu_icon" type="text" id="jetsync_cpt_menu_icon" value="<?php echo esc_attr( (string) ( $args['menu_icon'] ?? 'dashicons-admin-post' ) ); ?>" class="regular-text" placeholder="dashicons-admin-post"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_cpt_menu_position"><?php esc_html_e( 'Menu position', 'jet-sync' ); ?></label></th>
                                    <td><input name="menu_position" type="number" id="jetsync_cpt_menu_position" value="<?php echo esc_attr( isset( $args['menu_position'] ) ? (string) $args['menu_position'] : '' ); ?>" class="small-text" min="1"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_cpt_rewrite_slug"><?php esc_html_e( 'Rewrite slug', 'jet-sync' ); ?></label></th>
                                    <td><input name="rewrite_slug" type="text" id="jetsync_cpt_rewrite_slug" value="<?php echo esc_attr( (string) ( $rewrite['slug'] ?? '' ) ); ?>" class="regular-text" placeholder="custom-archive-slug"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Settings', 'jet-sync' ); ?></th>
                                    <td>
                                        <label style="display:block; margin-bottom:0.4rem;"><input name="public" type="checkbox" value="1" <?php checked( (bool) ( $args['public'] ?? true ) ); ?>> <?php esc_html_e( 'Public', 'jet-sync' ); ?></label>
                                        <label style="display:block; margin-bottom:0.4rem;"><input name="show_ui" type="checkbox" value="1" <?php checked( (bool) ( $args['show_ui'] ?? true ) ); ?>> <?php esc_html_e( 'Show UI', 'jet-sync' ); ?></label>
                                        <label style="display:block; margin-bottom:0.4rem;"><input name="show_in_menu" type="checkbox" value="1" <?php checked( (bool) ( $args['show_in_menu'] ?? true ) ); ?>> <?php esc_html_e( 'Show in admin menu', 'jet-sync' ); ?></label>
                                        <label style="display:block; margin-bottom:0.4rem;"><input name="has_archive" type="checkbox" value="1" <?php checked( (bool) ( $args['has_archive'] ?? true ) ); ?>> <?php esc_html_e( 'Has archive', 'jet-sync' ); ?></label>
                                        <label style="display:block; margin-bottom:0.4rem;"><input name="show_in_rest" type="checkbox" value="1" <?php checked( (bool) ( $args['show_in_rest'] ?? true ) ); ?>> <?php esc_html_e( 'Expose in REST API', 'jet-sync' ); ?></label>
                                        <label style="display:block; margin-bottom:0.4rem;"><input name="with_front" type="checkbox" value="1" <?php checked( (bool) ( $rewrite['with_front'] ?? true ) ); ?>> <?php esc_html_e( 'Use permalink front base', 'jet-sync' ); ?></label>
                                        <label style="display:block;"><input name="active" type="checkbox" value="1" <?php checked( $definition ? $definition->is_active() : true ); ?>> <?php esc_html_e( 'Active', 'jet-sync' ); ?></label>
                                    </td>
                                </tr>
                            </table>

                            <p class="submit">
                                <button type="submit" class="jetsync-btn primary-btn"><?php echo esc_html( $is_edit ? __( 'Save Changes', 'jet-sync' ) : __( 'Create Post Type', 'jet-sync' ) ); ?></button>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-cpts' ) ); ?>" class="jetsync-btn secondary-btn" style="margin-left:0.5rem;"><?php esc_html_e( 'Cancel', 'jet-sync' ); ?></a>
                            </p>
                        </form>
                    </div>
                <?php elseif ( empty( $cpts ) ) : ?>
                    <div class="registry-empty-state">
                        <span class="dashicons dashicons-admin-post"></span>
                        <h3><?php esc_html_e( 'No Custom Post Types Defined', 'jet-sync' ); ?></h3>
                        <p><?php esc_html_e( 'You can define post types inside JetSync or scan/import them from JetEngine using the wizard.', 'jet-sync' ); ?></p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-dashboard' ) ); ?>" class="jetsync-btn primary-btn">
                            <?php esc_html_e( 'Go to Dashboard', 'jet-sync' ); ?>
                        </a>
                    </div>
                <?php else : ?>
                    <table class="jetsync-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Name / Slug', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Labels', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Supports', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'REST API', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'jet-sync' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $cpts as $cpt ) :
                                $slug = $cpt->get_slug();
                                $labels = $cpt->get_labels();
                                $args = $cpt->get_args();
                                $supports = $args['supports'] ?? [];
                                $show_in_rest = $args['show_in_rest'] ?? true;
                                $is_active = $cpt->is_active();
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $labels['name'] ); ?></strong><br>
                                        <code><?php echo esc_html( $slug ); ?></code>
                                    </td>
                                    <td>
                                        <span class="meta-label"><?php esc_html_e( 'Singular:', 'jet-sync' ); ?></span> <?php echo esc_html( $labels['singular_name'] ); ?>
                                    </td>
                                    <td>
                                        <div class="tags-list">
                                            <?php foreach ( $supports as $support ) : ?>
                                                <span class="tag"><?php echo esc_html( $support ); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ( $show_in_rest ) : ?>
                                            <span class="status-indicator active"><?php esc_html_e( 'Enabled', 'jet-sync' ); ?></span>
                                        <?php else : ?>
                                            <span class="status-indicator inactive"><?php esc_html_e( 'Disabled', 'jet-sync' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( $is_active ) : ?>
                                            <span class="jetsync-status-badge active">
                                                <span class="pulse-dot"></span>
                                                <?php esc_html_e( 'Active', 'jet-sync' ); ?>
                                            </span>
                                        <?php else : ?>
                                            <span class="jetsync-status-badge inactive">
                                                <?php esc_html_e( 'Inactive', 'jet-sync' ); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="table-actions">
                                        <a class="jetsync-btn secondary-btn edit-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-cpts&action=edit&slug=' . $slug ) ); ?>">
                                            <?php esc_html_e( 'Edit', 'jet-sync' ); ?>
                                        </a>
                                        <a class="jetsync-btn danger-btn-outline delete-btn" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jetsync_delete_cpt&slug=' . $slug ), 'jetsync_delete_cpt_' . $slug ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this post type from the JetSync registry?', 'jet-sync' ) ); ?>');">
                                            <?php esc_html_e( 'Delete', 'jet-sync' ); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
