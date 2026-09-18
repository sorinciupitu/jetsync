<?php
declare( strict_types=1 );

namespace JetSync\Admin\Pages;

use JetSync\Core\JetSync;

class ListingsPage extends BasePage {

    public static function get_slug() : string {
        return 'jet-sync-listings';
    }

    public static function get_title() : string {
        return __( 'Listings', 'jet-sync' );
    }

    public function render() : void {
        /** @var \JetSync\Registry\ListingRegistry|null $listing_registry */
        $listing_registry = JetSync::get_instance()->get( 'listing_registry' );
        $listings = $listing_registry ? $listing_registry->get_all() : [];

        $is_add = isset( $_GET['action'] ) && 'add' === sanitize_key( (string) $_GET['action'] );
        $created = isset( $_GET['created'] ) ? (int) $_GET['created'] : -1;
        $deleted = isset( $_GET['deleted'] ) ? (int) $_GET['deleted'] : -1;

        $wizard_nonce = wp_create_nonce( 'jetsync_wizard_nonce' );

        $post_types = \get_post_types( [ '_builtin' => false ], 'objects' );
        if ( ! is_array( $post_types ) ) {
            $post_types = [];
        }

        ?>
        <script type="text/javascript">
            window.jetSyncConfig = window.jetSyncConfig || {
                ajaxUrl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
                nonce: '<?php echo esc_js( $wizard_nonce ); ?>',
                adminPageUrl: '<?php echo esc_url( admin_url( 'admin.php' ) ); ?>'
            };
        </script>
        <div class="wrap jetsync-wrap">
            <header class="jetsync-header">
                <div class="jetsync-header-logo">
                    <span class="jetsync-badge-version">v<?php echo esc_html( JETSYNC_VERSION ); ?></span>
                    <h1>JetSync</h1>
                </div>
            </header>

            <div class="jetsync-section-title">
                <h2><?php esc_html_e( 'Listings Builder', 'jet-sync' ); ?></h2>
                <a class="jetsync-btn primary-btn add-new-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-listings&action=add' ) ); ?>">
                    <span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add New Listing', 'jet-sync' ); ?>
                </a>
            </div>

            <div class="jetsync-workspace">
                <?php if ( 0 === $created ) : ?>
                    <div class="scan-status-alert warning" style="margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-warning"></span>
                        <div><strong><?php esc_html_e( 'Listing was not created.', 'jet-sync' ); ?></strong> <?php esc_html_e( 'Please check required fields and try again.', 'jet-sync' ); ?></div>
                    </div>
                <?php elseif ( 1 === $created ) : ?>
                    <div class="scan-status-alert info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-yes-alt" style="color:var(--success);"></span>
                        <div><strong><?php esc_html_e( 'Listing created.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ( 0 === $deleted ) : ?>
                    <div class="scan-status-alert warning" style="margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-warning"></span>
                        <div><strong><?php esc_html_e( 'Listing was not deleted.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php elseif ( 1 === $deleted ) : ?>
                    <div class="scan-status-alert info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-yes-alt" style="color:var(--success);"></span>
                        <div><strong><?php esc_html_e( 'Listing deleted.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ( $is_add ) : ?>
                    <div style="max-width: 980px;">
                        <h3 style="margin-top:0;"><?php esc_html_e( 'Add New Listing', 'jet-sync' ); ?></h3>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php wp_nonce_field( 'jetsync_add_listing' ); ?>
                            <input type="hidden" name="action" value="jetsync_add_listing">

                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><label for="jetsync_listing_title"><?php esc_html_e( 'Title', 'jet-sync' ); ?></label></th>
                                    <td><input name="title" type="text" id="jetsync_listing_title" value="" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_listing_id"><?php esc_html_e( 'ID (slug)', 'jet-sync' ); ?></label></th>
                                    <td><input name="id" type="text" id="jetsync_listing_id" value="" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_listing_post_type"><?php esc_html_e( 'Post Type', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <select name="post_type" id="jetsync_listing_post_type">
                                            <?php foreach ( $post_types as $slug => $obj ) :
                                                $label = is_object( $obj ) && isset( $obj->labels->name ) ? (string) $obj->labels->name : (string) $slug;
                                                ?>
                                                <option value="<?php echo esc_attr( (string) $slug ); ?>"><?php echo esc_html( $label ); ?> (<?php echo esc_html( (string) $slug ); ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_listing_ppp"><?php esc_html_e( 'Posts per page', 'jet-sync' ); ?></label></th>
                                    <td><input name="posts_per_page" type="number" id="jetsync_listing_ppp" value="10" class="small-text" min="1" max="100"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_listing_orderby"><?php esc_html_e( 'Order by', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <select name="orderby" id="jetsync_listing_orderby">
                                            <option value="date"><?php esc_html_e( 'Date', 'jet-sync' ); ?></option>
                                            <option value="title"><?php esc_html_e( 'Title', 'jet-sync' ); ?></option>
                                            <option value="menu_order"><?php esc_html_e( 'Menu Order', 'jet-sync' ); ?></option>
                                            <option value="rand"><?php esc_html_e( 'Random', 'jet-sync' ); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_listing_order"><?php esc_html_e( 'Order', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <select name="order" id="jetsync_listing_order">
                                            <option value="DESC"><?php esc_html_e( 'DESC', 'jet-sync' ); ?></option>
                                            <option value="ASC"><?php esc_html_e( 'ASC', 'jet-sync' ); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Fields', 'jet-sync' ); ?></th>
                                    <td>
                                        <div class="jetsync-card" style="padding: 1rem; background: rgba(255,255,255,0.02);">
                                            <div id="jetsync-listing-fields" class="jetsync-field-list">
                                                <div class="jetsync-draggable-field jetsync-core-field" draggable="true">
                                                    <span class="dashicons dashicons-menu"></span>
                                                    <label><input type="checkbox" name="selected_fields[]" value="title" checked> <?php esc_html_e( 'Title', 'jet-sync' ); ?></label>
                                                </div>
                                                <div class="jetsync-draggable-field jetsync-core-field" draggable="true">
                                                    <span class="dashicons dashicons-menu"></span>
                                                    <label><input type="checkbox" name="selected_fields[]" value="permalink" checked> <?php esc_html_e( 'Permalink', 'jet-sync' ); ?></label>
                                                </div>
                                                <div class="jetsync-draggable-field jetsync-core-field" draggable="true">
                                                    <span class="dashicons dashicons-menu"></span>
                                                    <label><input type="checkbox" name="selected_fields[]" value="excerpt"> <?php esc_html_e( 'Excerpt', 'jet-sync' ); ?></label>
                                                </div>
                                            </div>
                                            <p class="description" style="margin-top:0.75rem;"><?php esc_html_e( 'Meta fields are loaded from JetSync Meta Boxes for the selected post type.', 'jet-sync' ); ?></p>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_listing_template"><?php esc_html_e( 'Item template (HTML)', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <div style="display:flex; gap:0.5rem; align-items:center; margin-bottom:0.5rem;">
                                            <button type="button" class="jetsync-btn secondary-btn jetsync-generate-listing-template"><?php esc_html_e( 'Generate Template', 'jet-sync' ); ?></button>
                                            <span class="description"><?php esc_html_e( 'Uses selected fields to generate HTML. You can edit afterwards.', 'jet-sync' ); ?></span>
                                        </div>
                                        <textarea name="template" id="jetsync_listing_template" rows="10" class="large-text" data-autogen="1" placeholder="<div class=&quot;jetsync-listing-item&quot;>..."></textarea>
                                        <p class="description"><?php esc_html_e( 'Tokens: {{title}}, {{permalink}}, {{excerpt}}, {{field:meta_key}}', 'jet-sync' ); ?></p>
                                        <div class="jetsync-card jetsync-listing-preview">
                                            <h4 style="margin:0 0 0.75rem 0;"><?php esc_html_e( 'Live preview', 'jet-sync' ); ?></h4>
                                            <div id="jetsync-listing-preview-inner"></div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Active', 'jet-sync' ); ?></th>
                                    <td><label><input name="active" type="checkbox" value="1" checked> <?php esc_html_e( 'Enabled', 'jet-sync' ); ?></label></td>
                                </tr>
                            </table>

                            <p class="submit">
                                <button type="submit" class="jetsync-btn primary-btn"><?php esc_html_e( 'Create Listing', 'jet-sync' ); ?></button>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-listings' ) ); ?>" class="jetsync-btn secondary-btn" style="margin-left:0.5rem;"><?php esc_html_e( 'Cancel', 'jet-sync' ); ?></a>
                            </p>
                        </form>
                    </div>
                <?php else : ?>
                    <?php if ( empty( $listings ) ) : ?>
                        <div class="registry-empty-state">
                            <span class="dashicons dashicons-feedback"></span>
                            <h3><?php esc_html_e( 'No Listings Defined', 'jet-sync' ); ?></h3>
                            <p><?php esc_html_e( 'Create a listing and render it with shortcode [jetsync_listing id="your_id"].', 'jet-sync' ); ?></p>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-listings&action=add' ) ); ?>" class="jetsync-btn primary-btn">
                                <?php esc_html_e( 'Add New Listing', 'jet-sync' ); ?>
                            </a>
                        </div>
                    <?php else : ?>
                        <table class="jetsync-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Title / ID', 'jet-sync' ); ?></th>
                                    <th><?php esc_html_e( 'Post Type', 'jet-sync' ); ?></th>
                                    <th><?php esc_html_e( 'Shortcode', 'jet-sync' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'jet-sync' ); ?></th>
                                    <th><?php esc_html_e( 'Actions', 'jet-sync' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $listings as $listing ) :
                                    if ( ! $listing instanceof \JetSync\Registry\Model\ListingDefinition ) {
                                        continue;
                                    }
                                    $id = $listing->get_id();
                                    $title = $listing->get_title();
                                    $pt = $listing->get_post_type();
                                    $shortcode = '[jetsync_listing id="' . $id . '"]';
                                    $delete_url = wp_nonce_url(
                                        admin_url( 'admin-post.php?action=jetsync_delete_listing&id=' . rawurlencode( $id ) ),
                                        'jetsync_delete_listing_' . $id
                                    );
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html( $title ); ?></strong><br>
                                            <code><?php echo esc_html( $id ); ?></code>
                                        </td>
                                        <td><code><?php echo esc_html( $pt ); ?></code></td>
                                        <td><code><?php echo esc_html( $shortcode ); ?></code></td>
                                        <td>
                                            <?php if ( $listing->is_active() ) : ?>
                                                <span class="jetsync-status-badge active"><span class="pulse-dot"></span><?php esc_html_e( 'Active', 'jet-sync' ); ?></span>
                                            <?php else : ?>
                                                <span class="jetsync-status-badge inactive"><?php esc_html_e( 'Inactive', 'jet-sync' ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="table-actions">
                                            <a class="jetsync-btn danger-btn-outline" href="<?php echo esc_url( $delete_url ); ?>"><?php esc_html_e( 'Delete', 'jet-sync' ); ?></a>
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
