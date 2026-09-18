<?php
declare( strict_types=1 );

namespace JetSync\Admin\Pages;

use JetSync\Core\JetSync;
use JetSync\Registry\Model\TaxonomyDefinition;

/**
 * Custom Taxonomies Registry list page.
 */
class TaxonomiesPage extends BasePage {

    /**
     * {@inheritdoc}
     */
    public static function get_slug() : string {
        return 'jet-sync-taxonomies';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_title() : string {
        return __( 'Custom Taxonomies', 'jet-sync' );
    }

    /**
     * Render Taxonomy list screen.
     *
     * @return void
     */
    public function render() : void {
        /** @var \JetSync\Registry\TaxonomyRegistry|null $taxonomy_registry */
        $taxonomy_registry = JetSync::get_instance()->get( 'taxonomy_registry' );
        $taxonomies = $taxonomy_registry ? $taxonomy_registry->get_all() : [];
        $action = isset( $_GET['action'] ) ? sanitize_key( (string) $_GET['action'] ) : '';
        $edit_slug = isset( $_GET['slug'] ) ? sanitize_key( (string) $_GET['slug'] ) : '';
        $current_taxonomy = ( 'edit' === $action && '' !== $edit_slug && $taxonomy_registry ) ? $taxonomy_registry->get( $edit_slug ) : null;
        $is_add = 'add' === $action;
        $is_edit = $current_taxonomy instanceof TaxonomyDefinition;
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
                <h2><?php esc_html_e( 'Custom Taxonomies Registry', 'jet-sync' ); ?></h2>
                <a class="jetsync-btn primary-btn add-new-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-taxonomies&action=add' ) ); ?>">
                    <span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add New Taxonomy', 'jet-sync' ); ?>
                </a>
            </div>

            <div class="jetsync-workspace">
                <?php if ( 0 === $created ) : ?>
                    <div class="scan-status-alert warning" style="margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-warning"></span>
                        <div><strong><?php esc_html_e( 'Taxonomy was not created.', 'jet-sync' ); ?></strong> <?php esc_html_e( 'Please complete the required fields and use a unique slug.', 'jet-sync' ); ?></div>
                    </div>
                <?php elseif ( 1 === $created ) : ?>
                    <div class="scan-status-alert info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-yes-alt" style="color:var(--success);"></span>
                        <div><strong><?php esc_html_e( 'Taxonomy created.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ( 0 === $updated ) : ?>
                    <div class="scan-status-alert warning" style="margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-warning"></span>
                        <div><strong><?php esc_html_e( 'Taxonomy was not updated.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php elseif ( 1 === $updated ) : ?>
                    <div class="scan-status-alert info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-yes-alt" style="color:var(--success);"></span>
                        <div><strong><?php esc_html_e( 'Taxonomy updated.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ( 0 === $deleted ) : ?>
                    <div class="scan-status-alert warning" style="margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-warning"></span>
                        <div><strong><?php esc_html_e( 'Taxonomy was not deleted.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php elseif ( 1 === $deleted ) : ?>
                    <div class="scan-status-alert info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-yes-alt" style="color:var(--success);"></span>
                        <div><strong><?php esc_html_e( 'Taxonomy deleted.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ( $is_form_mode ) : ?>
                    <?php
                    $definition = $is_edit ? $current_taxonomy : null;
                    $labels = $definition ? $definition->get_labels() : [];
                    $args = $definition ? $definition->get_args() : [];
                    $rewrite = isset( $args['rewrite'] ) && is_array( $args['rewrite'] ) ? $args['rewrite'] : [ 'slug' => '', 'with_front' => true, 'hierarchical' => false ];
                    ?>
                    <div style="max-width: 920px;">
                        <h3 style="margin-top:0;"><?php echo esc_html( $is_edit ? __( 'Edit Taxonomy', 'jet-sync' ) : __( 'Add New Taxonomy', 'jet-sync' ) ); ?></h3>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php if ( $is_edit ) : ?>
                                <?php wp_nonce_field( 'jetsync_update_taxonomy' ); ?>
                                <input type="hidden" name="action" value="jetsync_update_taxonomy">
                                <input type="hidden" name="current_slug" value="<?php echo esc_attr( $definition->get_slug() ); ?>">
                            <?php else : ?>
                                <?php wp_nonce_field( 'jetsync_add_taxonomy' ); ?>
                                <input type="hidden" name="action" value="jetsync_add_taxonomy">
                            <?php endif; ?>

                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><label for="jetsync_tax_plural_label"><?php esc_html_e( 'Plural label', 'jet-sync' ); ?></label></th>
                                    <td><input name="plural_label" type="text" id="jetsync_tax_plural_label" value="<?php echo esc_attr( (string) ( $labels['name'] ?? '' ) ); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_tax_singular_label"><?php esc_html_e( 'Singular label', 'jet-sync' ); ?></label></th>
                                    <td><input name="singular_label" type="text" id="jetsync_tax_singular_label" value="<?php echo esc_attr( (string) ( $labels['singular_name'] ?? '' ) ); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_tax_slug"><?php esc_html_e( 'Slug', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <input name="slug" type="text" id="jetsync_tax_slug" value="<?php echo esc_attr( $definition ? $definition->get_slug() : '' ); ?>" class="regular-text" <?php echo $is_edit ? 'readonly' : ''; ?> required>
                                        <?php if ( $is_edit ) : ?>
                                            <p class="description"><?php esc_html_e( 'Slug is locked on edit to keep existing assignments stable.', 'jet-sync' ); ?></p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_tax_object_types"><?php esc_html_e( 'Associated post types', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <input name="object_types" type="text" id="jetsync_tax_object_types" value="<?php echo esc_attr( $definition ? implode( ',', $definition->get_object_types() ) : '' ); ?>" class="regular-text" placeholder="post,page,news">
                                        <p class="description"><?php esc_html_e( 'Comma separated post type slugs. Leave empty to register without bindings for now.', 'jet-sync' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_tax_rewrite_slug"><?php esc_html_e( 'Rewrite slug', 'jet-sync' ); ?></label></th>
                                    <td><input name="rewrite_slug" type="text" id="jetsync_tax_rewrite_slug" value="<?php echo esc_attr( (string) ( $rewrite['slug'] ?? '' ) ); ?>" class="regular-text" placeholder="taxonomy-slug"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Settings', 'jet-sync' ); ?></th>
                                    <td>
                                        <label style="display:block; margin-bottom:0.4rem;"><input name="hierarchical" type="checkbox" value="1" <?php checked( (bool) ( $args['hierarchical'] ?? true ) ); ?>> <?php esc_html_e( 'Hierarchical taxonomy', 'jet-sync' ); ?></label>
                                        <label style="display:block; margin-bottom:0.4rem;"><input name="public" type="checkbox" value="1" <?php checked( (bool) ( $args['public'] ?? true ) ); ?>> <?php esc_html_e( 'Public', 'jet-sync' ); ?></label>
                                        <label style="display:block; margin-bottom:0.4rem;"><input name="show_ui" type="checkbox" value="1" <?php checked( (bool) ( $args['show_ui'] ?? true ) ); ?>> <?php esc_html_e( 'Show UI', 'jet-sync' ); ?></label>
                                        <label style="display:block; margin-bottom:0.4rem;"><input name="show_admin_column" type="checkbox" value="1" <?php checked( (bool) ( $args['show_admin_column'] ?? true ) ); ?>> <?php esc_html_e( 'Show admin column', 'jet-sync' ); ?></label>
                                        <label style="display:block; margin-bottom:0.4rem;"><input name="show_in_rest" type="checkbox" value="1" <?php checked( (bool) ( $args['show_in_rest'] ?? true ) ); ?>> <?php esc_html_e( 'Expose in REST API', 'jet-sync' ); ?></label>
                                        <label style="display:block; margin-bottom:0.4rem;"><input name="with_front" type="checkbox" value="1" <?php checked( (bool) ( $rewrite['with_front'] ?? true ) ); ?>> <?php esc_html_e( 'Use permalink front base', 'jet-sync' ); ?></label>
                                        <label style="display:block; margin-bottom:0.4rem;"><input name="rewrite_hierarchical" type="checkbox" value="1" <?php checked( (bool) ( $rewrite['hierarchical'] ?? false ) ); ?>> <?php esc_html_e( 'Hierarchical rewrite URLs', 'jet-sync' ); ?></label>
                                        <label style="display:block;"><input name="active" type="checkbox" value="1" <?php checked( $definition ? $definition->is_active() : true ); ?>> <?php esc_html_e( 'Active', 'jet-sync' ); ?></label>
                                    </td>
                                </tr>
                            </table>

                            <p class="submit">
                                <button type="submit" class="jetsync-btn primary-btn"><?php echo esc_html( $is_edit ? __( 'Save Changes', 'jet-sync' ) : __( 'Create Taxonomy', 'jet-sync' ) ); ?></button>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-taxonomies' ) ); ?>" class="jetsync-btn secondary-btn" style="margin-left:0.5rem;"><?php esc_html_e( 'Cancel', 'jet-sync' ); ?></a>
                            </p>
                        </form>
                    </div>
                <?php elseif ( empty( $taxonomies ) ) : ?>
                    <div class="registry-empty-state">
                        <span class="dashicons dashicons-category"></span>
                        <h3><?php esc_html_e( 'No Custom Taxonomies Defined', 'jet-sync' ); ?></h3>
                        <p><?php esc_html_e( 'You can define custom taxonomies inside JetSync or scan/import them from JetEngine using the wizard.', 'jet-sync' ); ?></p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-dashboard' ) ); ?>" class="jetsync-btn primary-btn">
                            <?php esc_html_e( 'Go to Dashboard', 'jet-sync' ); ?>
                        </a>
                    </div>
                <?php else : ?>
                    <table class="jetsync-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Name / Slug', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Associated Types', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Hierarchical', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'REST API', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'jet-sync' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $taxonomies as $taxonomy ) :
                                $slug = $taxonomy->get_slug();
                                $labels = $taxonomy->get_labels();
                                $args = $taxonomy->get_args();
                                $object_types = $taxonomy->get_object_types();
                                $hierarchical = $args['hierarchical'] ?? true;
                                $show_in_rest = $args['show_in_rest'] ?? true;
                                $is_active = $taxonomy->is_active();
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $labels['name'] ); ?></strong><br>
                                        <code><?php echo esc_html( $slug ); ?></code>
                                    </td>
                                    <td>
                                        <div class="tags-list">
                                            <?php if ( empty( $object_types ) ) : ?>
                                                <span class="tag tag-empty"><?php esc_html_e( 'None', 'jet-sync' ); ?></span>
                                            <?php else : ?>
                                                <?php foreach ( $object_types as $obj_type ) : ?>
                                                    <span class="tag object-tag"><?php echo esc_html( $obj_type ); ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo $hierarchical ? esc_html__( 'Yes (Hierarchical)', 'jet-sync' ) : esc_html__( 'No (Flat / Tags)', 'jet-sync' ); ?>
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
                                        <a class="jetsync-btn secondary-btn edit-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-taxonomies&action=edit&slug=' . $slug ) ); ?>">
                                            <?php esc_html_e( 'Edit', 'jet-sync' ); ?>
                                        </a>
                                        <a class="jetsync-btn danger-btn-outline delete-btn" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jetsync_delete_taxonomy&slug=' . $slug ), 'jetsync_delete_taxonomy_' . $slug ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this taxonomy from the JetSync registry?', 'jet-sync' ) ); ?>');">
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
