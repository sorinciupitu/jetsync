<?php
declare( strict_types=1 );

namespace JetSync\Admin\Pages;

use JetSync\Core\JetSync;
use JetSync\Registry\Model\MetaBoxDefinition;
use JetSync\Registry\Model\MetaFieldDefinition;

/**
 * Custom Meta Boxes Registry list page.
 */
class MetaBoxesPage extends BasePage {

    /**
     * {@inheritdoc}
     */
    public static function get_slug() : string {
        return 'jet-sync-meta-boxes';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_title() : string {
        return __( 'Meta Fields', 'jet-sync' );
    }

    /**
     * Render Meta Box list screen.
     *
     * @return void
     */
    public function render() : void {
        /** @var \JetSync\Registry\MetaBoxRegistry|null $metabox_registry */
        $metabox_registry = JetSync::get_instance()->get( 'metabox_registry' );
        $metaboxes = $metabox_registry ? $metabox_registry->get_all() : [];
        $action = isset( $_GET['action'] ) ? sanitize_key( (string) $_GET['action'] ) : '';
        $edit_id = isset( $_GET['id'] ) ? sanitize_key( (string) $_GET['id'] ) : '';
        $current_metabox = ( 'edit' === $action && '' !== $edit_id && $metabox_registry ) ? $metabox_registry->get( $edit_id ) : null;
        $is_add = 'add' === $action;
        $is_edit = $current_metabox instanceof MetaBoxDefinition;
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
                <h2><?php esc_html_e( 'Custom Meta Fields Registry', 'jet-sync' ); ?></h2>
                <a class="jetsync-btn primary-btn add-new-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-meta-boxes&action=add' ) ); ?>">
                    <span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add New Meta Box', 'jet-sync' ); ?>
                </a>
            </div>

            <div class="jetsync-workspace">
                <?php if ( 0 === $created ) : ?>
                    <div class="scan-status-alert warning" style="margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-warning"></span>
                        <div><strong><?php esc_html_e( 'Meta box was not created.', 'jet-sync' ); ?></strong> <?php esc_html_e( 'Please check required fields and try again.', 'jet-sync' ); ?></div>
                    </div>
                <?php elseif ( 1 === $created ) : ?>
                    <div class="scan-status-alert info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-yes-alt" style="color:var(--success);"></span>
                        <div><strong><?php esc_html_e( 'Meta box created.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ( 0 === $updated ) : ?>
                    <div class="scan-status-alert warning" style="margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-warning"></span>
                        <div><strong><?php esc_html_e( 'Meta box was not updated.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php elseif ( 1 === $updated ) : ?>
                    <div class="scan-status-alert info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-yes-alt" style="color:var(--success);"></span>
                        <div><strong><?php esc_html_e( 'Meta box updated.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ( 0 === $deleted ) : ?>
                    <div class="scan-status-alert warning" style="margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-warning"></span>
                        <div><strong><?php esc_html_e( 'Meta box was not deleted.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php elseif ( 1 === $deleted ) : ?>
                    <div class="scan-status-alert info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; margin-bottom:1.5rem;">
                        <span class="dashicons dashicons-yes-alt" style="color:var(--success);"></span>
                        <div><strong><?php esc_html_e( 'Meta box deleted.', 'jet-sync' ); ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ( $is_form_mode ) : ?>
                    <?php
                    $definition = $is_edit ? $current_metabox : null;
                    $fields_value = $definition ? $this->stringify_fields( $definition->get_fields() ) : '';
                    $field_rows = $definition ? $this->get_field_rows_data( $definition->get_fields() ) : [];
                    if ( empty( $field_rows ) ) {
                        $field_rows[] = $this->get_empty_field_row();
                    }
                    ?>
                    <div style="max-width: 900px;">
                        <h3 style="margin-top:0;"><?php echo esc_html( $is_edit ? __( 'Edit Meta Box', 'jet-sync' ) : __( 'Add New Meta Box', 'jet-sync' ) ); ?></h3>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php if ( $is_edit ) : ?>
                                <?php wp_nonce_field( 'jetsync_update_metabox' ); ?>
                                <input type="hidden" name="action" value="jetsync_update_metabox">
                                <input type="hidden" name="current_id" value="<?php echo esc_attr( $definition->get_id() ); ?>">
                            <?php else : ?>
                                <?php wp_nonce_field( 'jetsync_add_metabox' ); ?>
                                <input type="hidden" name="action" value="jetsync_add_metabox">
                            <?php endif; ?>

                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><label for="jetsync_mb_title"><?php esc_html_e( 'Title', 'jet-sync' ); ?></label></th>
                                    <td><input name="title" type="text" id="jetsync_mb_title" value="<?php echo esc_attr( $definition ? $definition->get_title() : '' ); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_mb_id"><?php esc_html_e( 'ID (slug)', 'jet-sync' ); ?></label></th>
                                    <td><input name="id" type="text" id="jetsync_mb_id" value="<?php echo esc_attr( $definition ? $definition->get_id() : '' ); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_mb_object_types"><?php esc_html_e( 'Post Types (comma separated)', 'jet-sync' ); ?></label></th>
                                    <td><input name="object_types" type="text" id="jetsync_mb_object_types" value="<?php echo esc_attr( $definition ? implode( ',', $definition->get_object_types() ) : '' ); ?>" class="regular-text" placeholder="news,women,men"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_mb_context"><?php esc_html_e( 'Context', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <select name="context" id="jetsync_mb_context">
                                            <option value="normal" <?php selected( $definition ? $definition->get_context() : 'normal', 'normal' ); ?>><?php esc_html_e( 'Normal', 'jet-sync' ); ?></option>
                                            <option value="advanced" <?php selected( $definition ? $definition->get_context() : 'normal', 'advanced' ); ?>><?php esc_html_e( 'Advanced', 'jet-sync' ); ?></option>
                                            <option value="side" <?php selected( $definition ? $definition->get_context() : 'normal', 'side' ); ?>><?php esc_html_e( 'Side', 'jet-sync' ); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_mb_priority"><?php esc_html_e( 'Priority', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <select name="priority" id="jetsync_mb_priority">
                                            <option value="high" <?php selected( $definition ? $definition->get_priority() : 'default', 'high' ); ?>><?php esc_html_e( 'High', 'jet-sync' ); ?></option>
                                            <option value="core" <?php selected( $definition ? $definition->get_priority() : 'default', 'core' ); ?>><?php esc_html_e( 'Core', 'jet-sync' ); ?></option>
                                            <option value="default" <?php selected( $definition ? $definition->get_priority() : 'default', 'default' ); ?>><?php esc_html_e( 'Default', 'jet-sync' ); ?></option>
                                            <option value="low" <?php selected( $definition ? $definition->get_priority() : 'default', 'low' ); ?>><?php esc_html_e( 'Low', 'jet-sync' ); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="jetsync_mb_fields"><?php esc_html_e( 'Fields', 'jet-sync' ); ?></label></th>
                                    <td>
                                        <div class="jetsync-fields-builder" data-next-index="<?php echo esc_attr( (string) count( $field_rows ) ); ?>">
                                            <div class="jetsync-fields-builder-toolbar">
                                                <button type="button" class="jetsync-btn secondary-btn jetsync-add-field-row"><?php esc_html_e( 'Add Field', 'jet-sync' ); ?></button>
                                                <button type="button" class="jetsync-btn secondary-btn jetsync-add-switcher-row"><?php esc_html_e( 'Add Yes/No Switcher', 'jet-sync' ); ?></button>
                                            </div>
                                            <div class="jetsync-fields-builder-list">
                                                <?php foreach ( $field_rows as $index => $row ) : ?>
                                                    <?php $this->render_field_builder_row( $row, (int) $index ); ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <textarea name="fields" id="jetsync_mb_fields" rows="10" class="large-text jetsync-fields-serialized" hidden><?php echo esc_textarea( $fields_value ); ?></textarea>

                                        <p class="description"><?php esc_html_e( 'Folosește editorul vizual pentru fiecare câmp. Pentru switcher-ul "Updated" poți folosi butonul Add Yes/No Switcher.', 'jet-sync' ); ?></p>
                                        <p class="description"><?php esc_html_e( 'Tipuri suportate: text, textarea, select, radio, checkbox, switcher, wysiwyg, date, url, media, gallery.', 'jet-sync' ); ?></p>

                                        <details class="jetsync-fields-advanced">
                                            <summary><?php esc_html_e( 'Advanced raw format', 'jet-sync' ); ?></summary>
                                            <textarea id="jetsync_mb_fields_raw" rows="8" class="large-text jetsync-fields-raw" placeholder="meta_key|Label|type|Description|Default|value1:Option 1,value2:Option 2"><?php echo esc_textarea( $fields_value ); ?></textarea>
                                            <div class="jetsync-fields-advanced-actions">
                                                <button type="button" class="jetsync-btn secondary-btn jetsync-import-raw-fields"><?php esc_html_e( 'Apply Text To Builder', 'jet-sync' ); ?></button>
                                            </div>
                                            <p class="description"><?php esc_html_e( 'Format per line: meta_key|Label|type|Description|Default|value1:Option 1,value2:Option 2', 'jet-sync' ); ?></p>
                                        </details>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Active', 'jet-sync' ); ?></th>
                                    <td><label><input name="active" type="checkbox" value="1" <?php checked( $definition ? $definition->is_active() : true ); ?>> <?php esc_html_e( 'Enabled', 'jet-sync' ); ?></label></td>
                                </tr>
                            </table>

                            <p class="submit">
                                <button type="submit" class="jetsync-btn primary-btn"><?php echo esc_html( $is_edit ? __( 'Save Changes', 'jet-sync' ) : __( 'Create Meta Box', 'jet-sync' ) ); ?></button>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-meta-boxes' ) ); ?>" class="jetsync-btn secondary-btn" style="margin-left:0.5rem;"><?php esc_html_e( 'Cancel', 'jet-sync' ); ?></a>
                            </p>
                        </form>
                    </div>
                <?php else : ?>
                <?php if ( empty( $metaboxes ) ) : ?>
                    <div class="registry-empty-state">
                        <span class="dashicons dashicons-edit-page"></span>
                        <h3><?php esc_html_e( 'No Meta Boxes Defined', 'jet-sync' ); ?></h3>
                        <p><?php esc_html_e( 'You can define custom meta fields groups inside JetSync or scan/import them from JetEngine using the wizard.', 'jet-sync' ); ?></p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-dashboard' ) ); ?>" class="jetsync-btn primary-btn">
                            <?php esc_html_e( 'Go to Dashboard', 'jet-sync' ); ?>
                        </a>
                    </div>
                <?php else : ?>
                    <table class="jetsync-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Name / ID', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Targeted Post Types', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Fields Count', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Fields List', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'jet-sync' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'jet-sync' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $metaboxes as $mb ) :
                                $id = $mb->get_id();
                                $title = $mb->get_title();
                                $object_types = $mb->get_object_types();
                                $fields = $mb->get_fields();
                                $is_active = $mb->is_active();
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $title ); ?></strong><br>
                                        <code><?php echo esc_html( $id ); ?></code>
                                    </td>
                                    <td>
                                        <div class="tags-list">
                                            <?php if ( empty( $object_types ) ) : ?>
                                                <span class="tag tag-empty"><?php esc_html_e( 'All Post Types', 'jet-sync' ); ?></span>
                                            <?php else : ?>
                                                <?php foreach ( $object_types as $obj_type ) : ?>
                                                    <span class="tag object-tag"><?php echo esc_html( $obj_type ); ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo count( $fields ); ?>
                                    </td>
                                    <td>
                                        <div style="max-height: 120px; overflow-y: auto; padding-right: 0.5rem;">
                                            <?php foreach ( $fields as $f ) : ?>
                                                <div style="font-size: 0.85rem; margin-bottom: 0.35rem; line-height: 1.4;">
                                                    <code><?php echo esc_html( $f->get_name() ); ?></code>
                                                    <span style="color: var(--text-muted); font-size: 0.8rem;">(<?php echo esc_html( $f->get_type() ); ?>)</span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
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
                                        <a class="jetsync-btn secondary-btn edit-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=jet-sync-meta-boxes&action=edit&id=' . $id ) ); ?>">
                                            <?php esc_html_e( 'Edit', 'jet-sync' ); ?>
                                        </a>
                                        <a class="jetsync-btn danger-btn-outline delete-btn" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jetsync_delete_metabox&id=' . $id ), 'jetsync_delete_metabox_' . $id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this meta box from the JetSync registry?', 'jet-sync' ) ); ?>');">
                                            <?php esc_html_e( 'Delete', 'jet-sync' ); ?>
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

    /**
     * @param array<MetaFieldDefinition> $fields
     */
    private function stringify_fields( array $fields ) : string {
        $lines = [];

        foreach ( $fields as $field ) {
            if ( ! $field instanceof MetaFieldDefinition ) {
                continue;
            }

            $options = [];
            foreach ( $field->get_options() as $option ) {
                if ( ! is_array( $option ) ) {
                    continue;
                }

                $value = isset( $option['value'] ) ? (string) $option['value'] : '';
                if ( '' === $value ) {
                    continue;
                }

                $label = isset( $option['label'] ) ? (string) $option['label'] : $value;
                $options[] = $value . ':' . $label;
            }

            $lines[] = implode(
                '|',
                [
                    $field->get_name(),
                    $field->get_title(),
                    $field->get_type(),
                    $field->get_description(),
                    is_scalar( $field->get_default_value() ) ? (string) $field->get_default_value() : '',
                    implode( ',', $options ),
                ]
            );
        }

        return implode( "\n", $lines );
    }

    /**
     * @param array<MetaFieldDefinition> $fields
     * @return array<int,array<string,string>>
     */
    private function get_field_rows_data( array $fields ) : array {
        $rows = [];

        foreach ( $fields as $field ) {
            if ( ! $field instanceof MetaFieldDefinition ) {
                continue;
            }

            $options = [];
            foreach ( $field->get_options() as $option ) {
                if ( ! is_array( $option ) ) {
                    continue;
                }

                $value = isset( $option['value'] ) ? (string) $option['value'] : '';
                if ( '' === $value ) {
                    continue;
                }

                $label = isset( $option['label'] ) ? (string) $option['label'] : $value;
                $options[] = $value . ':' . $label;
            }

            $rows[] = [
                'name'          => $field->get_name(),
                'title'         => $field->get_title(),
                'type'          => $field->get_type(),
                'description'   => $field->get_description(),
                'default_value' => is_scalar( $field->get_default_value() ) ? (string) $field->get_default_value() : '',
                'options'       => implode( ',', $options ),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string,string>
     */
    private function get_empty_field_row() : array {
        return [
            'name'          => '',
            'title'         => '',
            'type'          => 'text',
            'description'   => '',
            'default_value' => '',
            'options'       => '',
        ];
    }

    /**
     * @param array<string,string> $row
     */
    private function render_field_builder_row( array $row, int $index ) : void {
        $row = array_merge( $this->get_empty_field_row(), $row );
        $types = [
            'text'     => __( 'Text', 'jet-sync' ),
            'textarea' => __( 'Textarea', 'jet-sync' ),
            'select'   => __( 'Select', 'jet-sync' ),
            'radio'    => __( 'Radio', 'jet-sync' ),
            'checkbox' => __( 'Checkbox', 'jet-sync' ),
            'switcher' => __( 'Switcher', 'jet-sync' ),
            'wysiwyg'  => __( 'WYSIWYG', 'jet-sync' ),
            'date'     => __( 'Date', 'jet-sync' ),
            'url'      => __( 'URL', 'jet-sync' ),
            'media'    => __( 'Media', 'jet-sync' ),
            'gallery'  => __( 'Gallery', 'jet-sync' ),
        ];
        ?>
        <div class="jetsync-field-builder-row" data-row-index="<?php echo esc_attr( (string) $index ); ?>">
            <div class="jetsync-field-builder-head">
                <strong><?php esc_html_e( 'Field', 'jet-sync' ); ?></strong>
                <button type="button" class="jetsync-btn danger-btn-outline jetsync-remove-field-row"><?php esc_html_e( 'Remove', 'jet-sync' ); ?></button>
            </div>

            <div class="jetsync-field-builder-grid">
                <div class="jetsync-field-builder-cell">
                    <label><?php esc_html_e( 'Meta key', 'jet-sync' ); ?></label>
                    <input type="text" class="regular-text jetsync-field-input jetsync-field-name" value="<?php echo esc_attr( $row['name'] ); ?>" placeholder="updated">
                </div>

                <div class="jetsync-field-builder-cell">
                    <label><?php esc_html_e( 'Label', 'jet-sync' ); ?></label>
                    <input type="text" class="regular-text jetsync-field-input jetsync-field-title" value="<?php echo esc_attr( $row['title'] ); ?>" placeholder="Updated">
                </div>

                <div class="jetsync-field-builder-cell">
                    <label><?php esc_html_e( 'Type', 'jet-sync' ); ?></label>
                    <select class="jetsync-field-input jetsync-field-type">
                        <?php foreach ( $types as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $row['type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="jetsync-field-builder-cell">
                    <label><?php esc_html_e( 'Default', 'jet-sync' ); ?></label>
                    <input type="text" class="regular-text jetsync-field-input jetsync-field-default" value="<?php echo esc_attr( $row['default_value'] ); ?>" placeholder="false">
                </div>

                <div class="jetsync-field-builder-cell jetsync-field-builder-cell-wide">
                    <label><?php esc_html_e( 'Options', 'jet-sync' ); ?></label>
                    <input type="text" class="regular-text jetsync-field-input jetsync-field-options" value="<?php echo esc_attr( $row['options'] ); ?>" placeholder="0:Nu,1:Da">
                    <p class="description"><?php esc_html_e( 'Pentru switcher recomandat: 0:Nu,1:Da. Pentru select/radio/checkbox folosește value:Label.', 'jet-sync' ); ?></p>
                </div>

                <div class="jetsync-field-builder-cell jetsync-field-builder-cell-wide">
                    <label><?php esc_html_e( 'Description', 'jet-sync' ); ?></label>
                    <input type="text" class="regular-text jetsync-field-input jetsync-field-description" value="<?php echo esc_attr( $row['description'] ); ?>" placeholder="<?php esc_attr_e( 'Short help text shown under the field', 'jet-sync' ); ?>">
                </div>
            </div>
        </div>
        <?php
    }
}
