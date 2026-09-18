<?php
declare( strict_types=1 );

namespace JetSync\Admin\Pages;

use JetSync\Core\JetSync;

/**
 * Custom Relationships Registry list page.
 */
class RelationsPage extends BasePage {

    /**
     * {@inheritdoc}
     */
    public static function get_slug() : string {
        return 'jet-sync-relations';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_title() : string {
        return __( 'Relations', 'jet-sync' );
    }

    /**
     * Render Relations list screen.
     *
     * @return void
     */
    public function render() : void {
        global $wpdb;

        /** @var \JetSync\Registry\RelationRegistry|null $relation_registry */
        $relation_registry = JetSync::get_instance()->get( 'relation_registry' );
        $relations = $relation_registry ? $relation_registry->get_all() : [];
        $is_add = isset( $_GET['action'] ) && 'add' === sanitize_key( (string) $_GET['action'] );
        $is_edit = isset( $_GET['action'] ) && 'edit' === sanitize_key( (string) $_GET['action'] );
        $edit_id = isset( $_GET['id'] ) ? sanitize_key( (string) $_GET['id'] ) : '';
        $edit_rel = ( $is_edit && $relation_registry && '' !== $edit_id ) ? $relation_registry->get( $edit_id ) : null;
        $created = isset( $_GET['created'] ) ? (int) $_GET['created'] : -1;
        $updated = isset( $_GET['updated'] ) ? (int) $_GET['updated'] : -1;

        $table_name = $wpdb->prefix . 'jetsync_relations';
        $has_table  = ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name );

        ?>
        <div class="wrap jetsync-wrap">
            <!-- Header Section -->
            <header class="jetsync-header">
                <div class="jetsync-header-logo">
                    <span class="jetsync-badge-version">v<?php echo esc_html( JETSYNC_VERSION ); ?></span>
                    <h1>JetSync</h1>
                </div>
            </header>

            <!-- Title & Actions bar -->
            <div class="jetsync-section-title">
                <h2><?php esc_html_e( 'Custom Relations Registry', 'jet-sync' ); ?></h2>
                <a class="jetsync-btn primary-btn add-new-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-relations&action=add' ) ); ?>">
                    <span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add New Relation', 'jet-sync' ); ?>
                </a>
            </div>

            <!-- Table Container -->
            <div class="jetsync-workspace">
                <?php if ( 0 === $created ) : ?>
                    <div class="scan-status-alert warning" style="margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-warning"></span>
                        <div><strong><?php esc_html_e( 'Relation was not created.', 'jet-sync' ); ?></strong> <?php esc_html_e( 'Please check required fields and try again.', 'jet-sync' ); ?></div>
                    </div>
                <?php elseif ( 1 === $created ) : ?>
                    <div class="scan-status-alert info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-yes-alt" style="color:var(--success);"></span>
                        <div><strong><?php esc_html_e( 'Relation created.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ( 0 === $updated ) : ?>
                    <div class="scan-status-alert warning" style="margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-warning"></span>
                        <div><strong><?php esc_html_e( 'Relation was not updated.', 'jet-sync' ); ?></strong> <?php esc_html_e( 'Please check required fields and try again.', 'jet-sync' ); ?></div>
                    </div>
                <?php elseif ( 1 === $updated ) : ?>
                    <div class="scan-status-alert info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-yes-alt" style="color:var(--success);"></span>
                        <div><strong><?php esc_html_e( 'Relation updated.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ( $is_add ) : ?>
                    <div style="max-width: 860px;">
                        <h3 style="margin-top:0;"><?php esc_html_e( 'Add New Relation', 'jet-sync' ); ?></h3>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php wp_nonce_field( 'jetsync_add_relation' ); ?>
                            <input type="hidden" name="action" value="jetsync_add_relation">

                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><label for="jetsync_rel_title"><?php esc_html_e( 'Title', 'jet-sync' ); ?></label></th>
                                    <td><input name="title" type="text" id="jetsync_rel_title" value="" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_rel_id"><?php esc_html_e( 'ID (slug)', 'jet-sync' ); ?></label></th>
                                    <td><input name="id" type="text" id="jetsync_rel_id" value="" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_rel_parent"><?php esc_html_e( 'Parent Object', 'jet-sync' ); ?></label></th>
                                    <td><input name="parent_object" type="text" id="jetsync_rel_parent" value="post" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_rel_child"><?php esc_html_e( 'Child Object', 'jet-sync' ); ?></label></th>
                                    <td><input name="child_object" type="text" id="jetsync_rel_child" value="post" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_rel_type"><?php esc_html_e( 'Type', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <select name="type" id="jetsync_rel_type">
                                            <option value="many_to_many"><?php esc_html_e( 'Many to Many', 'jet-sync' ); ?></option>
                                            <option value="one_to_many"><?php esc_html_e( 'One to Many', 'jet-sync' ); ?></option>
                                            <option value="one_to_one"><?php esc_html_e( 'One to One', 'jet-sync' ); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Active', 'jet-sync' ); ?></th>
                                    <td><label><input name="active" type="checkbox" value="1" checked> <?php esc_html_e( 'Enabled', 'jet-sync' ); ?></label></td>
                                </tr>
                            </table>

                            <p class="submit">
                                <button type="submit" class="jetsync-btn primary-btn"><?php esc_html_e( 'Create Relation', 'jet-sync' ); ?></button>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-relations' ) ); ?>" class="jetsync-btn secondary-btn" style="margin-left:0.5rem;"><?php esc_html_e( 'Cancel', 'jet-sync' ); ?></a>
                            </p>
                        </form>
                    </div>
                <?php elseif ( $is_edit ) : ?>
                    <?php if ( ! $edit_rel || ! $edit_rel instanceof \JetSync\Registry\Model\RelationDefinition ) : ?>
                        <div class="registry-empty-state">
                            <span class="dashicons dashicons-warning"></span>
                            <h3><?php esc_html_e( 'Relation not found', 'jet-sync' ); ?></h3>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-relations' ) ); ?>" class="jetsync-btn primary-btn">
                                <?php esc_html_e( 'Back to Relations', 'jet-sync' ); ?>
                            </a>
                        </div>
                    <?php else : ?>
                        <div style="max-width: 860px;">
                            <h3 style="margin-top:0;"><?php esc_html_e( 'Edit Relation', 'jet-sync' ); ?></h3>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <?php wp_nonce_field( 'jetsync_update_relation' ); ?>
                                <input type="hidden" name="action" value="jetsync_update_relation">

                                <table class="form-table" role="presentation">
                                    <tr>
                                        <th scope="row"><label for="jetsync_rel_title"><?php esc_html_e( 'Title', 'jet-sync' ); ?></label></th>
                                        <td><input name="title" type="text" id="jetsync_rel_title" value="<?php echo esc_attr( $edit_rel->get_title() ); ?>" class="regular-text" required></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="jetsync_rel_id"><?php esc_html_e( 'ID (slug)', 'jet-sync' ); ?></label></th>
                                        <td><input name="id" type="text" id="jetsync_rel_id" value="<?php echo esc_attr( $edit_rel->get_id() ); ?>" class="regular-text" readonly></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="jetsync_rel_parent"><?php esc_html_e( 'Parent Object', 'jet-sync' ); ?></label></th>
                                        <td><input name="parent_object" type="text" id="jetsync_rel_parent" value="<?php echo esc_attr( $edit_rel->get_parent_object() ); ?>" class="regular-text"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="jetsync_rel_child"><?php esc_html_e( 'Child Object', 'jet-sync' ); ?></label></th>
                                        <td><input name="child_object" type="text" id="jetsync_rel_child" value="<?php echo esc_attr( $edit_rel->get_child_object() ); ?>" class="regular-text"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="jetsync_rel_type"><?php esc_html_e( 'Type', 'jet-sync' ); ?></label></th>
                                        <td>
                                            <select name="type" id="jetsync_rel_type">
                                                <option value="many_to_many" <?php selected( $edit_rel->get_type(), 'many_to_many' ); ?>><?php esc_html_e( 'Many to Many', 'jet-sync' ); ?></option>
                                                <option value="one_to_many" <?php selected( $edit_rel->get_type(), 'one_to_many' ); ?>><?php esc_html_e( 'One to Many', 'jet-sync' ); ?></option>
                                                <option value="one_to_one" <?php selected( $edit_rel->get_type(), 'one_to_one' ); ?>><?php esc_html_e( 'One to One', 'jet-sync' ); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Active', 'jet-sync' ); ?></th>
                                        <td><label><input name="active" type="checkbox" value="1" <?php checked( $edit_rel->is_active() ); ?>> <?php esc_html_e( 'Enabled', 'jet-sync' ); ?></label></td>
                                    </tr>
                                </table>

                                <p class="submit">
                                    <button type="submit" class="jetsync-btn primary-btn"><?php esc_html_e( 'Save Changes', 'jet-sync' ); ?></button>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-relations' ) ); ?>" class="jetsync-btn secondary-btn" style="margin-left:0.5rem;"><?php esc_html_e( 'Cancel', 'jet-sync' ); ?></a>
                                </p>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                <?php if ( empty( $relations ) ) : ?>
                    <div class="registry-empty-state">
                        <span class="dashicons dashicons-admin-links"></span>
                        <h3><?php esc_html_e( 'No Relations Defined', 'jet-sync' ); ?></h3>
                        <p><?php esc_html_e( 'You can define custom relationship connections inside JetSync or scan/import them from JetEngine using the wizard.', 'jet-sync' ); ?></p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-dashboard' ) ); ?>" class="jetsync-btn primary-btn">
                            <?php esc_html_e( 'Go to Dashboard', 'jet-sync' ); ?>
                        </a>
                    </div>
                <?php else : ?>
                    <table class="jetsync-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Name / ID', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Parent Object', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Child Object', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Type', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Active Connections', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'jet-sync' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $relations as $rel ) :
                                $id = $rel->get_id();
                                $title = $rel->get_title();
                                $parent = $rel->get_parent_object();
                                $child = $rel->get_child_object();
                                $type = $rel->get_type();
                                $is_active = $rel->is_active();

                                // Query active connection rows dynamically
                                $conn_count = 0;
                                if ( $has_table ) {
                                    $conn_count = (int) $wpdb->get_var(
                                        $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE rel_id = %s", $id )
                                    );
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $title ); ?></strong><br>
                                        <code><?php echo esc_html( $id ); ?></code>
                                    </td>
                                    <td>
                                        <span class="tag object-tag"><?php echo esc_html( $parent ); ?></span>
                                    </td>
                                    <td>
                                        <span class="tag" style="background: rgba(99, 102, 241, 0.1); color: #818cf8; border-color: rgba(99, 102, 241, 0.2);"><?php echo esc_html( $child ); ?></span>
                                    </td>
                                    <td>
                                        <code><?php echo esc_html( $type ); ?></code>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( (string) $conn_count ); ?></strong>
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
                                        <a class="jetsync-btn secondary-btn edit-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-relations&action=edit&id=' . rawurlencode( $id ) ) ); ?>">
                                            <?php esc_html_e( 'Edit', 'jet-sync' ); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
