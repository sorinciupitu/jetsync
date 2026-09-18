<?php
declare( strict_types=1 );

namespace JetSync\Admin\Pages;

use JetSync\Core\JetSync;
use JetSync\Registry\Model\QueryDefinition;
use JetSync\Registry\Model\RelationDefinition;
use function __;
use function admin_url;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function esc_url;
use function get_post_types;
use function implode;
use function is_array;
use function is_object;
use function rawurlencode;
use function sanitize_key;
use function wp_nonce_field;
use function wp_nonce_url;

class QueriesPage extends BasePage {

    public static function get_slug() : string {
        return 'jet-sync-queries';
    }

    public static function get_title() : string {
        return __( 'Queries', 'jet-sync' );
    }

    public function render() : void {
        /** @var \JetSync\Registry\QueryRegistry|null $query_registry */
        $query_registry = JetSync::get_instance()->get( 'query_registry' );
        /** @var \JetSync\Registry\RelationRegistry|null $relation_registry */
        $relation_registry = JetSync::get_instance()->get( 'relation_registry' );

        $queries = $query_registry ? $query_registry->get_all() : [];
        $relations = $relation_registry ? $relation_registry->get_all() : [];

        $is_add = isset( $_GET['action'] ) && 'add' === sanitize_key( (string) $_GET['action'] );
        $created = isset( $_GET['created'] ) ? (int) $_GET['created'] : -1;
        $deleted = isset( $_GET['deleted'] ) ? (int) $_GET['deleted'] : -1;

        $post_types = \get_post_types( [ 'public' => true ], 'objects' );
        if ( ! is_array( $post_types ) ) {
            $post_types = [];
        }
        ?>
        <div class="wrap jetsync-wrap">
            <header class="jetsync-header">
                <div class="jetsync-header-logo">
                    <span class="jetsync-badge-version">v<?php echo esc_html( JETSYNC_VERSION ); ?></span>
                    <h1>JetSync</h1>
                </div>
            </header>

            <div class="jetsync-section-title">
                <h2><?php esc_html_e( 'Elementor Queries', 'jet-sync' ); ?></h2>
                <a class="jetsync-btn primary-btn add-new-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-queries&action=add' ) ); ?>">
                    <span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add New Query', 'jet-sync' ); ?>
                </a>
            </div>

            <div class="jetsync-workspace">
                <?php if ( 0 === $created ) : ?>
                    <div class="scan-status-alert warning" style="margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-warning"></span>
                        <div><strong><?php esc_html_e( 'Query was not created.', 'jet-sync' ); ?></strong> <?php esc_html_e( 'Please check required fields and try again.', 'jet-sync' ); ?></div>
                    </div>
                <?php elseif ( 1 === $created ) : ?>
                    <div class="scan-status-alert info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-yes-alt" style="color:var(--success);"></span>
                        <div><strong><?php esc_html_e( 'Query created.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ( 0 === $deleted ) : ?>
                    <div class="scan-status-alert warning" style="margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-warning"></span>
                        <div><strong><?php esc_html_e( 'Query was not deleted.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php elseif ( 1 === $deleted ) : ?>
                    <div class="scan-status-alert info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-yes-alt" style="color:var(--success);"></span>
                        <div><strong><?php esc_html_e( 'Query deleted.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ( $is_add ) : ?>
                    <div style="max-width: 980px;">
                        <h3 style="margin-top:0;"><?php esc_html_e( 'Add New Query', 'jet-sync' ); ?></h3>
                        <p class="description" style="margin-bottom:1rem;">
                            <?php esc_html_e( 'Create reusable Query IDs for Elementor Loop Grid. Each query shows posts related to the current post through a JetSync relation.', 'jet-sync' ); ?>
                        </p>

                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php wp_nonce_field( 'jetsync_add_query' ); ?>
                            <input type="hidden" name="action" value="jetsync_add_query">

                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><label for="jetsync_query_title"><?php esc_html_e( 'Title', 'jet-sync' ); ?></label></th>
                                    <td><input name="title" type="text" id="jetsync_query_title" value="" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_query_id"><?php esc_html_e( 'Query ID', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <input name="id" type="text" id="jetsync_query_id" value="" class="regular-text" required>
                                        <p class="description"><?php esc_html_e( 'Use this exact ID in Elementor Loop Grid > Query ID.', 'jet-sync' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_query_relation"><?php esc_html_e( 'Relation', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <select name="relation_id" id="jetsync_query_relation" required>
                                            <option value=""><?php esc_html_e( 'Select relation', 'jet-sync' ); ?></option>
                                            <?php foreach ( $relations as $relation ) : ?>
                                                <?php if ( ! $relation instanceof RelationDefinition ) { continue; } ?>
                                                <option value="<?php echo esc_attr( $relation->get_id() ); ?>">
                                                    <?php echo esc_html( $relation->get_title() . ' (' . $relation->get_id() . ')' ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_query_target_post_type"><?php esc_html_e( 'Target Post Type', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <select name="target_post_type" id="jetsync_query_target_post_type" required>
                                            <option value=""><?php esc_html_e( 'Select post type', 'jet-sync' ); ?></option>
                                            <?php foreach ( $post_types as $slug => $obj ) :
                                                $label = is_object( $obj ) && isset( $obj->labels->name ) ? (string) $obj->labels->name : (string) $slug;
                                                ?>
                                                <option value="<?php echo esc_attr( (string) $slug ); ?>"><?php echo esc_html( $label ); ?> (<?php echo esc_html( (string) $slug ); ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_query_source_post_types"><?php esc_html_e( 'Allowed source post types', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <input name="source_post_types" type="text" id="jetsync_query_source_post_types" value="" class="regular-text">
                                        <p class="description"><?php esc_html_e( 'Optional. Comma-separated slugs of the current post types where this query should run. Leave empty for all.', 'jet-sync' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_query_ppp"><?php esc_html_e( 'Posts per page', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <input name="posts_per_page" type="number" id="jetsync_query_ppp" value="10" class="small-text" min="-1" max="100">
                                        <p class="description"><?php esc_html_e( 'Use -1 to load all related posts.', 'jet-sync' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_query_orderby"><?php esc_html_e( 'Order by', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <select name="orderby" id="jetsync_query_orderby">
                                            <option value="date"><?php esc_html_e( 'Date', 'jet-sync' ); ?></option>
                                            <option value="title"><?php esc_html_e( 'Title', 'jet-sync' ); ?></option>
                                            <option value="menu_order"><?php esc_html_e( 'Menu Order', 'jet-sync' ); ?></option>
                                            <option value="modified"><?php esc_html_e( 'Modified', 'jet-sync' ); ?></option>
                                            <option value="rand"><?php esc_html_e( 'Random', 'jet-sync' ); ?></option>
                                            <option value="post__in"><?php esc_html_e( 'Relation order', 'jet-sync' ); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_query_order"><?php esc_html_e( 'Order', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <select name="order" id="jetsync_query_order">
                                            <option value="DESC"><?php esc_html_e( 'DESC', 'jet-sync' ); ?></option>
                                            <option value="ASC"><?php esc_html_e( 'ASC', 'jet-sync' ); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Active', 'jet-sync' ); ?></th>
                                    <td><label><input name="active" type="checkbox" value="1" checked> <?php esc_html_e( 'Enabled', 'jet-sync' ); ?></label></td>
                                </tr>
                            </table>

                            <p class="submit">
                                <button type="submit" class="jetsync-btn primary-btn"><?php esc_html_e( 'Create Query', 'jet-sync' ); ?></button>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-queries' ) ); ?>" class="jetsync-btn secondary-btn" style="margin-left:0.5rem;"><?php esc_html_e( 'Cancel', 'jet-sync' ); ?></a>
                            </p>
                        </form>
                    </div>
                <?php else : ?>
                    <?php if ( empty( $queries ) ) : ?>
                        <div class="registry-empty-state">
                            <span class="dashicons dashicons-filter"></span>
                            <h3><?php esc_html_e( 'No Queries Defined', 'jet-sync' ); ?></h3>
                            <p><?php esc_html_e( 'Create reusable Elementor Query IDs that fetch posts related to the current post through JetSync relations.', 'jet-sync' ); ?></p>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-queries&action=add' ) ); ?>" class="jetsync-btn primary-btn">
                                <?php esc_html_e( 'Add New Query', 'jet-sync' ); ?>
                            </a>
                        </div>
                    <?php else : ?>
                        <table class="jetsync-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Title / Query ID', 'jet-sync' ); ?></th>
                                    <th><?php esc_html_e( 'Relation', 'jet-sync' ); ?></th>
                                    <th><?php esc_html_e( 'Target Post Type', 'jet-sync' ); ?></th>
                                    <th><?php esc_html_e( 'Allowed Sources', 'jet-sync' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'jet-sync' ); ?></th>
                                    <th><?php esc_html_e( 'Actions', 'jet-sync' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $queries as $query ) :
                                    if ( ! $query instanceof QueryDefinition ) {
                                        continue;
                                    }

                                    $source_post_types = $query->get_source_post_types();
                                    $source_label = empty( $source_post_types ) ? __( 'All post types', 'jet-sync' ) : implode( ', ', $source_post_types );
                                    $delete_url = wp_nonce_url(
                                        admin_url( 'admin-post.php?action=jetsync_delete_query&id=' . rawurlencode( $query->get_id() ) ),
                                        'jetsync_delete_query_' . $query->get_id()
                                    );
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html( $query->get_title() ); ?></strong><br>
                                            <code><?php echo esc_html( $query->get_id() ); ?></code>
                                        </td>
                                        <td><code><?php echo esc_html( $query->get_relation_id() ); ?></code></td>
                                        <td><code><?php echo esc_html( $query->get_target_post_type() ); ?></code></td>
                                        <td><?php echo esc_html( $source_label ); ?></td>
                                        <td>
                                            <?php if ( $query->is_active() ) : ?>
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
