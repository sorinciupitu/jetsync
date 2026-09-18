/**
 * JetSync Admin Script
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Tab switching logic
        $('.jetsync-tabs').on('click', '.jetsync-tab-link', function(e) {
            e.preventDefault();
            
            var $this = $(this);
            var targetTab = $this.data('tab');
            
            // Toggle active class on tab buttons
            $('.jetsync-tab-link').removeClass('active');
            $this.addClass('active');
            
            // Toggle active class on tab panels
            $('.jetsync-tab-panel').removeClass('active');
            $('#' + targetTab).addClass('active');
        });

        // "Go to Migration Wizard" deep link button trigger
        $('.jetsync-wrap').on('click', '.trigger-migration-tab', function(e) {
            e.preventDefault();
            $('.jetsync-tab-link[data-tab="migration"]').trigger('click');
        });

        // Start Diagnostics Scan AJAX
        $('.jetsync-wrap').on('click', '.start-scan-btn', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var $resultsContainer = $('#jetsync-scan-results');
            
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Diagnostics scanning in progress...');
            $resultsContainer.fadeOut('fast').empty();
            
            $.post(window.jetSyncConfig.ajaxUrl, {
                action: 'jetsync_run_diagnostics',
                nonce: window.jetSyncConfig.nonce
            }, function(response) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Run Diagnostics Scan Again');
                
                if (response.success) {
                    var data = response.data;
                    var html = '';

                    function isAuxCptSlug(slug) {
                        slug = String(slug || '');
                        return slug.indexOf('elementor_') === 0 || slug.indexOf('metform-') === 0 || slug.indexOf('e-') === 0;
                    }

                    function isAuxTaxSlug(slug) {
                        slug = String(slug || '');
                        return slug.indexOf('elementor_') === 0;
                    }
                    
                    html += '<h3 class="scan-results-title">Scan Results Diagnostics Report</h3>';
                    html += '<p style="margin-bottom:1.5rem; color:var(--text-secondary);">Review the detected custom structural definitions below before committing to the registry import.</p>';
                    
                    // Display Post Types
                    html += '<div class="jetsync-card" style="background:rgba(255,255,255,0.01);">';
                    html += '<h4>Detected Custom Post Types (' + data.cpts.length + ')</h4>';
                    if (data.cpts.length === 0) {
                        html += '<p style="color:var(--text-muted); margin-top:0.5rem;">No post types found.</p>';
                    } else {
                        html += '<table class="jetsync-table" style="margin-top:1rem;">';
                        html += '<thead><tr><th style="width:34px;"><input type="checkbox" class="jetsync-select-all-cpts"></th><th>Name</th><th>Slug</th><th>Collision Status</th></tr></thead><tbody>';
                        $.each(data.cpts, function(i, item) {
                            var badgeClass = item.has_conflict ? 'inactive' : 'active';
                            var badgeText = item.has_conflict ? 'Collision Blocked' : 'Ready to Sync';
                            var checkedAttr = (!item.has_conflict && !isAuxCptSlug(item.slug)) ? ' checked' : '';
                            html += '<tr>';
                            html += '<td><input type="checkbox" class="jetsync-select-cpt" data-slug="' + item.slug + '"' + (item.has_conflict ? ' disabled' : checkedAttr) + '></td>';
                            html += '<td><strong>' + item.name + '</strong></td>';
                            html += '<td><code>' + item.slug + '</code></td>';
                            html += '<td>';
                            html += '<span class="jetsync-status-badge ' + badgeClass + '">' + badgeText + '</span>';
                            if (item.has_conflict) {
                                html += '<div style="color:var(--danger); font-size:0.8rem; margin-top:0.25rem;">' + item.conflict_message + '</div>';
                            }
                            html += '</td>';
                            html += '</tr>';
                        });
                        html += '</tbody></table>';
                    }
                    html += '</div>';
                    
                    // Display Taxonomies
                    html += '<div class="jetsync-card" style="background:rgba(255,255,255,0.01);">';
                    html += '<h4>Detected Custom Taxonomies (' + data.taxonomies.length + ')</h4>';
                    if (data.taxonomies.length === 0) {
                        html += '<p style="color:var(--text-muted); margin-top:0.5rem;">No taxonomies found.</p>';
                    } else {
                        html += '<table class="jetsync-table" style="margin-top:1rem;">';
                        html += '<thead><tr><th style="width:34px;"><input type="checkbox" class="jetsync-select-all-taxonomies"></th><th>Name</th><th>Slug</th><th>Collision Status</th></tr></thead><tbody>';
                        $.each(data.taxonomies, function(i, item) {
                            var badgeClass = item.has_conflict ? 'inactive' : 'active';
                            var badgeText = item.has_conflict ? 'Collision Blocked' : 'Ready to Sync';
                            var checkedAttr = (!item.has_conflict && !isAuxTaxSlug(item.slug)) ? ' checked' : '';
                            html += '<tr>';
                            html += '<td><input type="checkbox" class="jetsync-select-taxonomy" data-slug="' + item.slug + '"' + (item.has_conflict ? ' disabled' : checkedAttr) + '></td>';
                            html += '<td><strong>' + item.name + '</strong></td>';
                            html += '<td><code>' + item.slug + '</code></td>';
                            html += '<td>';
                            html += '<span class="jetsync-status-badge ' + badgeClass + '">' + badgeText + '</span>';
                            if (item.has_conflict) {
                                html += '<div style="color:var(--danger); font-size:0.8rem; margin-top:0.25rem;">' + item.conflict_message + '</div>';
                            }
                            html += '</td>';
                            html += '</tr>';
                        });
                        html += '</tbody></table>';
                    }
                    html += '</div>';

                    // Display Meta Boxes
                    html += '<div class="jetsync-card" style="background:rgba(255,255,255,0.01);">';
                    html += '<h4>Detected Meta Boxes (' + (data.meta_boxes ? data.meta_boxes.length : 0) + ')</h4>';
                    if (!data.meta_boxes || data.meta_boxes.length === 0) {
                        html += '<p style="color:var(--text-muted); margin-top:0.5rem;">No meta boxes found.</p>';
                    } else {
                        html += '<table class="jetsync-table" style="margin-top:1rem;">';
                        html += '<thead><tr><th>Title</th><th>ID</th><th>Fields</th></tr></thead><tbody>';
                        $.each(data.meta_boxes, function(i, item) {
                            html += '<tr>';
                            html += '<td><strong>' + item.title + '</strong></td>';
                            html += '<td><code>' + item.id + '</code></td>';
                            html += '<td>' + (item.fields_count || 0) + '</td>';
                            html += '</tr>';
                        });
                        html += '</tbody></table>';
                    }
                    html += '</div>';

                    // Display Relations
                    html += '<div class="jetsync-card" style="background:rgba(255,255,255,0.01);">';
                    html += '<h4>Detected Object Relations (' + (data.relations ? data.relations.length : 0) + ')</h4>';
                    if (!data.relations || data.relations.length === 0) {
                        html += '<p style="color:var(--text-muted); margin-top:0.5rem;">No relations found.</p>';
                    } else {
                        html += '<table class="jetsync-table" style="margin-top:1rem;">';
                        html += '<thead><tr><th>Title</th><th>ID</th><th>Connections</th><th>Status</th></tr></thead><tbody>';
                        $.each(data.relations, function(i, item) {
                            html += '<tr>';
                            html += '<td><strong>' + item.title + '</strong></td>';
                            html += '<td><code>' + item.id + '</code></td>';
                            html += '<td><strong>' + (item.connection_count || 0) + '</strong></td>';
                            html += '<td><span class="jetsync-status-badge active">Ready to Sync</span></td>';
                            html += '</tr>';
                        });
                        html += '</tbody></table>';
                    }
                    html += '</div>';

                    // Import Button Group
                    if (data.conflicts_count === 0 && (data.cpts.length > 0 || data.taxonomies.length > 0 || (data.meta_boxes && data.meta_boxes.length > 0) || (data.relations && data.relations.length > 0))) {
                        html += '<div class="scan-status-alert info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; margin-top:2rem;">';
                        html += '<span class="dashicons dashicons-yes-alt" style="color:var(--success);"></span>';
                        html += '<div><strong>No conflicts detected!</strong> You are safe to import these configurations into JetSync.</div>';
                        html += '</div>';
                        html += '<div class="action-buttons" style="margin-top:1.5rem;">';
                        html += '<button class="jetsync-btn secondary-btn select-content-only-btn" style="margin-right:0.5rem;">Select content only</button>';
                        html += '<button class="jetsync-btn secondary-btn select-none-btn" style="margin-right:0.5rem;">Select none</button>';
                        html += '<button class="jetsync-btn primary-btn run-import-btn">';
                        html += '<span class="dashicons dashicons-migrate"></span> Import Schemas into Registry';
                        html += '</button>';
                        html += '</div>';
                    } else if (data.conflicts_count > 0) {
                        html += '<div class="scan-status-alert info" style="background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.2); color: #fecaca; margin-top:2rem;">';
                        html += '<span class="dashicons dashicons-warning" style="color:var(--danger);"></span>';
                        html += '<div><strong>Registry Conflicts Detected!</strong> Some CPT/Taxonomy slugs are registered by third-party plugins or themes. Please resolve these to continue.</div>';
                        html += '</div>';
                    } else {
                        html += '<div class="scan-status-alert info" style="margin-top:2rem;">';
                        html += '<span class="dashicons dashicons-info"></span>';
                        html += '<div><strong>Empty JetEngine configuration.</strong> No custom post types or taxonomies were found in JetEngine database storage.</div>';
                        html += '</div>';
                    }

                    if (data.jet_engine_active === false) {
                        html += '<div class="scan-status-alert warning" style="margin-top:1rem;">';
                        html += '<span class="dashicons dashicons-warning"></span>';
                        html += '<div><strong>JetEngine is not detected as active.</strong> The scan will rely on runtime fallbacks and may not detect JetEngine meta boxes or relations.</div>';
                        html += '</div>';
                    }

                    if (data.storage) {
                        html += '<details style="margin-top:1rem;">';
                        html += '<summary style="cursor:pointer; color:var(--text-secondary);">Show storage debug</summary>';
                        html += '<div class="jetsync-card" style="margin-top:1rem; background:rgba(255,255,255,0.01);">';

                        html += '<p style="margin:0 0 0.75rem 0; color:var(--text-secondary);">Sources used: CPTs=' + (data.storage.cpts_source || 'none') + ', Taxonomies=' + (data.storage.taxonomies_source || 'none') + ', Meta Boxes=' + (data.storage.meta_boxes_source || 'none') + ', Relations=' + (data.storage.relations_source || 'none') + '</p>';
                        html += '<p style="margin:0 0 0.75rem 0; color:var(--text-secondary);">JetEngine active: ' + (data.jet_engine_active ? 'yes' : 'no') + '</p>';

                        if (data.storage.runtime) {
                            html += '<p style="margin:0 0 0.75rem 0; color:var(--text-secondary);">Runtime fallback: CPTs=' + (data.storage.runtime.cpts_count || 0) + ', Taxonomies=' + (data.storage.runtime.taxonomies_count || 0) + '</p>';
                        }

                        if (data.storage.options && data.storage.options.length) {
                            html += '<h4 style="margin-top:0;">JetEngine-related options (first 50)</h4>';
                            html += '<table class="jetsync-table" style="margin-top:0.75rem;">';
                            html += '<thead><tr><th>Option</th><th>Type</th><th>Count</th></tr></thead><tbody>';
                            $.each(data.storage.options, function(i, opt) {
                                html += '<tr>';
                                html += '<td><code>' + opt.name + '</code></td>';
                                html += '<td>' + opt.type + '</td>';
                                html += '<td>' + (opt.count === null ? '' : opt.count) + '</td>';
                                html += '</tr>';
                            });
                            html += '</tbody></table>';
                        }

                        if (data.storage.post_types && data.storage.post_types.length) {
                            html += '<h4>JetEngine-related post types</h4>';
                            html += '<table class="jetsync-table" style="margin-top:0.75rem;">';
                            html += '<thead><tr><th>post_type</th><th>count</th></tr></thead><tbody>';
                            $.each(data.storage.post_types, function(i, pt) {
                                html += '<tr><td><code>' + pt.post_type + '</code></td><td>' + pt.count + '</td></tr>';
                            });
                            html += '</tbody></table>';
                        }

                        if (data.storage.jet_engine_posts && data.storage.jet_engine_posts.length) {
                            html += '<h4>jet-engine posts (sample)</h4>';
                            html += '<table class="jetsync-table" style="margin-top:0.75rem;">';
                            html += '<thead><tr><th>ID</th><th>Title</th><th>Slug</th><th>Content</th><th>Meta keys (first 15)</th></tr></thead><tbody>';
                            $.each(data.storage.jet_engine_posts, function(i, p) {
                                var keys = (p.metaKeys && p.metaKeys.length) ? p.metaKeys.join(', ') : '';
                                html += '<tr>';
                                html += '<td><code>' + p.id + '</code></td>';
                                html += '<td>' + (p.title || '') + '</td>';
                                html += '<td><code>' + (p.slug || '') + '</code></td>';
                                html += '<td style="max-width:420px; white-space:normal; word-break:break-word;">' + (p.content || '') + '</td>';
                                html += '<td><code>' + keys + '</code></td>';
                                html += '</tr>';
                            });
                            html += '</tbody></table>';
                        }

                        if (data.storage.relations_tables && data.storage.relations_tables.length) {
                            html += '<h4>JetEngine relations tables (debug)</h4>';
                            html += '<table class="jetsync-table" style="margin-top:0.75rem;">';
                            html += '<thead><tr><th>Table</th><th>Args col</th><th>Columns</th></tr></thead><tbody>';
                            $.each(data.storage.relations_tables, function(i, t) {
                                var cols = (t.columns && t.columns.length) ? t.columns.join(', ') : '';
                                html += '<tr>';
                                html += '<td><code>' + t.table + '</code></td>';
                                html += '<td><code>' + (t.args_col || '') + '</code></td>';
                                html += '<td style="max-width:520px; white-space:normal; word-break:break-word;"><code>' + cols + '</code></td>';
                                html += '</tr>';
                            });
                            html += '</tbody></table>';
                        }

                        html += '</div>';
                        html += '</details>';
                    }
                    
                    // Mark checklist item as scanned
                    $('.indicator-scan').removeClass('pending').addClass('checked').find('.dashicons').removeClass('dashicons-clock').addClass('dashicons-yes-alt');
                    
                    $resultsContainer.html(html).fadeIn('fast');

                    $('.jetsync-select-all-cpts').prop('checked', $('.jetsync-select-cpt:not(:disabled)').length > 0 && $('.jetsync-select-cpt:not(:disabled)').length === $('.jetsync-select-cpt:not(:disabled):checked').length);
                    $('.jetsync-select-all-taxonomies').prop('checked', $('.jetsync-select-taxonomy:not(:disabled)').length > 0 && $('.jetsync-select-taxonomy:not(:disabled)').length === $('.jetsync-select-taxonomy:not(:disabled):checked').length);
                } else {
                    alert('Error running diagnostics: ' + (response.data.message || 'Unknown error.'));
                }
            }, 'json').fail(function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Run Diagnostics Scan Again');
                alert('Connection error while executing diagnostics scan.');
            });
        });

        // Run Import Registry Schema AJAX
        $('.jetsync-wrap').on('click', '.run-import-btn', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var selectedCpts = [];
            var selectedTax = [];

            $('.jetsync-select-cpt:checked').each(function() {
                selectedCpts.push($(this).data('slug'));
            });
            $('.jetsync-select-taxonomy:checked').each(function() {
                selectedTax.push($(this).data('slug'));
            });

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Executing schema registry import...');
            
            $.post(window.jetSyncConfig.ajaxUrl, {
                action: 'jetsync_execute_import',
                nonce: window.jetSyncConfig.nonce,
                selected_cpts: selectedCpts,
                selected_taxonomies: selectedTax
            }, function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    // Update stats counters
                    $('.cpt-icon').closest('.jetsync-stat-card').find('.js-count').text(data.imported_cpts);
                    $('.taxonomy-icon').closest('.jetsync-stat-card').find('.js-count').text(data.imported_taxonomies);
                    $('.relation-icon').closest('.jetsync-stat-card').find('.js-count').text(data.imported_relations);
                    
                    // Update wizard step indicator active state
                    $('.wizard-step[data-step="1"]').removeClass('active').addClass('completed');
                    $('.wizard-step[data-step="2"]').addClass('active');
                    
                    // Switch wizard contents
                    $('#step-content-1').hide();
                    $('#step-content-2').fadeIn('fast');
                    
                    // Update checklist
                    $('.indicator-import').removeClass('pending').addClass('checked').find('.dashicons').removeClass('dashicons-clock').addClass('dashicons-yes-alt');
                } else {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-migrate"></span> Import Schemas into Registry');
                    alert('Error executing schema import: ' + (response.data.message || 'Unknown error.'));
                }
            }, 'json').fail(function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-migrate"></span> Import Schemas into Registry');
                alert('Connection error while importing schemas.');
            });
        });

        $('.jetsync-wrap').on('change', '.jetsync-select-all-cpts', function() {
            var checked = $(this).is(':checked');
            $('.jetsync-select-cpt:not(:disabled)').prop('checked', checked);
        });

        $('.jetsync-wrap').on('change', '.jetsync-select-all-taxonomies', function() {
            var checked = $(this).is(':checked');
            $('.jetsync-select-taxonomy:not(:disabled)').prop('checked', checked);
        });

        $('.jetsync-wrap').on('click', '.select-none-btn', function(e) {
            e.preventDefault();
            $('.jetsync-select-cpt:not(:disabled)').prop('checked', false);
            $('.jetsync-select-taxonomy:not(:disabled)').prop('checked', false);
            $('.jetsync-select-all-cpts').prop('checked', false);
            $('.jetsync-select-all-taxonomies').prop('checked', false);
        });

        $('.jetsync-wrap').on('click', '.select-content-only-btn', function(e) {
            e.preventDefault();
            var cptPrefixes = [ 'elementor_', 'metform-', 'e-' ];
            var taxPrefixes = [ 'elementor_' ];

            $('.jetsync-select-cpt:not(:disabled)').each(function() {
                var slug = String($(this).data('slug') || '');
                var isAux = false;
                $.each(cptPrefixes, function(i, p) {
                    if (slug.indexOf(p) === 0) {
                        isAux = true;
                    }
                });
                $(this).prop('checked', !isAux);
            });

            $('.jetsync-select-taxonomy:not(:disabled)').each(function() {
                var slug = String($(this).data('slug') || '');
                var isAux = false;
                $.each(taxPrefixes, function(i, p) {
                    if (slug.indexOf(p) === 0) {
                        isAux = true;
                    }
                });
                $(this).prop('checked', !isAux);
            });
        });

        // Placeholder button step actions
        $('.jetsync-wrap').on('click', '.continue-to-step3-btn', function(e) {
            e.preventDefault();
            if (window.jetSyncConfig && window.jetSyncConfig.adminPageUrl) {
                window.location.href = window.jetSyncConfig.adminPageUrl + '?page=jet-sync-relations';
                return;
            }
            alert('Cannot navigate to Relations page (adminPageUrl missing).');
        });

        function buildListingTemplateFromSelected($form) {
            var selected = [];
            $form.find('#jetsync-listing-fields input[name="selected_fields[]"]:checked').each(function() {
                selected.push(String($(this).val() || ''));
            });

            var hasTitle = selected.indexOf('title') !== -1;
            var hasPermalink = selected.indexOf('permalink') !== -1;

            var parts = [];
            $.each(selected, function(i, f) {
                if (f === 'title') {
                    if (hasPermalink) {
                        return;
                    }
                    parts.push('<div class="jetsync-field jetsync-field-title">{{title}}</div>');
                    return;
                }
                if (f === 'permalink') {
                    if (hasTitle) {
                        parts.push('<div class="jetsync-field jetsync-field-title"><a href="{{permalink}}">{{title}}</a></div>');
                    } else {
                        parts.push('<div class="jetsync-field jetsync-field-permalink"><a href="{{permalink}}">{{permalink}}</a></div>');
                    }
                    return;
                }
                if (f === 'excerpt') {
                    parts.push('<div class="jetsync-field jetsync-field-excerpt">{{excerpt}}</div>');
                    return;
                }
                if (f.indexOf('field:') === 0) {
                    var key = f.substring(6);
                    parts.push('<div class="jetsync-field jetsync-field-' + key + '">{{field:' + key + '}}</div>');
                }
            });

            if (parts.length === 0) {
                parts.push('<div class="jetsync-field jetsync-field-title"><a href="{{permalink}}">{{title}}</a></div>');
            }

            return '<div class="jetsync-listing-item">' + parts.join('') + '</div>';
        }

        function loadListingMetaFields(postType) {
            var $container = $('#jetsync-listing-fields');
            if ($container.length === 0) {
                return;
            }
            $container.find('.jetsync-meta-field-item').remove();

            if (!window.jetSyncConfig || !window.jetSyncConfig.ajaxUrl || !window.jetSyncConfig.nonce) {
                return;
            }

            $.post(window.jetSyncConfig.ajaxUrl, {
                action: 'jetsync_get_listing_fields',
                nonce: window.jetSyncConfig.nonce,
                post_type: postType
            }, function(response) {
                if (!response || !response.success || !response.data) {
                    return;
                }
                var fields = response.data.meta_fields || [];
                if (!fields.length) {
                    return;
                }
                $.each(fields, function(i, f) {
                    if (!f || !f.key) {
                        return;
                    }
                    var key = String(f.key);
                    var label = String(f.label || key);
                    var $item = $('<div class="jetsync-draggable-field jetsync-meta-field-item" draggable="true"></div>');
                    $item.append($('<span class="dashicons dashicons-menu"></span>'));
                    var $label = $('<label />');
                    var $cb = $('<input type="checkbox" />');
                    $cb.attr('name', 'selected_fields[]');
                    $cb.attr('value', 'field:' + key);
                    $label.append($cb).append(' ' + label + ' ').append($('<code />').text(key));
                    $item.append($label);
                    $container.append($item);
                });
            }, 'json');
        }

        function enableDragSort($container) {
            var dragEl = null;

            $container.on('dragstart', '.jetsync-draggable-field', function(e) {
                dragEl = this;
                try {
                    e.originalEvent.dataTransfer.setData('text/plain', '');
                } catch (err) {}
                e.originalEvent.dataTransfer.effectAllowed = 'move';
                $(this).addClass('jetsync-dragging');
            });

            $container.on('dragend', '.jetsync-draggable-field', function() {
                $(this).removeClass('jetsync-dragging');
                dragEl = null;
            });

            $container.on('dragover', '.jetsync-draggable-field', function(e) {
                e.preventDefault();
            });

            $container.on('drop', '.jetsync-draggable-field', function(e) {
                e.preventDefault();
                if (!dragEl || dragEl === this) {
                    return;
                }
                var $target = $(this);
                var $drag = $(dragEl);
                if ($target.index() < $drag.index()) {
                    $target.before($drag);
                } else {
                    $target.after($drag);
                }
                $drag.removeClass('jetsync-dragging');
            });
        }

        function updateListingPreview($form) {
            var $preview = $('#jetsync-listing-preview-inner');
            var $template = $('#jetsync_listing_template');
            if ($preview.length === 0 || $template.length === 0) {
                return;
            }

            var tpl = String($template.val() || '');
            if (tpl.trim() === '') {
                tpl = buildListingTemplateFromSelected($form);
            }

            var html = tpl;
            html = html.split('{{title}}').join('Sample Title');
            html = html.split('{{permalink}}').join('#');
            html = html.split('{{excerpt}}').join('Sample excerpt text...');
            html = html.replace(/\{\{\s*field:([a-zA-Z0-9_\-]+)\s*\}\}/g, function() {
                return 'Sample';
            });

            $preview.html(html);
        }

        function getMetaFieldTypeOptions(selectedType) {
            var options = [
                { value: 'text', label: 'Text' },
                { value: 'textarea', label: 'Textarea' },
                { value: 'select', label: 'Select' },
                { value: 'radio', label: 'Radio' },
                { value: 'checkbox', label: 'Checkbox' },
                { value: 'switcher', label: 'Switcher' },
                { value: 'wysiwyg', label: 'WYSIWYG' },
                { value: 'date', label: 'Date' },
                { value: 'url', label: 'URL' },
                { value: 'media', label: 'Media' },
                { value: 'gallery', label: 'Gallery' }
            ];

            var html = '';
            $.each(options, function(i, option) {
                var selectedAttr = option.value === selectedType ? ' selected="selected"' : '';
                html += '<option value="' + option.value + '"' + selectedAttr + '>' + option.label + '</option>';
            });

            return html;
        }

        function normalizeMetaFieldRow(row) {
            row = row || {};
            return {
                name: String(row.name || ''),
                title: String(row.title || ''),
                type: String(row.type || 'text'),
                description: String(row.description || ''),
                default_value: String(row.default_value || ''),
                options: String(row.options || '')
            };
        }

        function createMetaFieldRow(row, index) {
            row = normalizeMetaFieldRow(row);
            var html = '';

            html += '<div class="jetsync-field-builder-row" data-row-index="' + index + '">';
            html += '<div class="jetsync-field-builder-head">';
            html += '<strong>Field</strong>';
            html += '<button type="button" class="jetsync-btn danger-btn-outline jetsync-remove-field-row">Remove</button>';
            html += '</div>';
            html += '<div class="jetsync-field-builder-grid">';
            html += '<div class="jetsync-field-builder-cell">';
            html += '<label>Meta key</label>';
            html += '<input type="text" class="regular-text jetsync-field-input jetsync-field-name" value="' + $('<div>').text(row.name).html() + '" placeholder="updated">';
            html += '</div>';
            html += '<div class="jetsync-field-builder-cell">';
            html += '<label>Label</label>';
            html += '<input type="text" class="regular-text jetsync-field-input jetsync-field-title" value="' + $('<div>').text(row.title).html() + '" placeholder="Updated">';
            html += '</div>';
            html += '<div class="jetsync-field-builder-cell">';
            html += '<label>Type</label>';
            html += '<select class="jetsync-field-input jetsync-field-type">' + getMetaFieldTypeOptions(row.type) + '</select>';
            html += '</div>';
            html += '<div class="jetsync-field-builder-cell">';
            html += '<label>Default</label>';
            html += '<input type="text" class="regular-text jetsync-field-input jetsync-field-default" value="' + $('<div>').text(row.default_value).html() + '" placeholder="false">';
            html += '</div>';
            html += '<div class="jetsync-field-builder-cell jetsync-field-builder-cell-wide">';
            html += '<label>Options</label>';
            html += '<input type="text" class="regular-text jetsync-field-input jetsync-field-options" value="' + $('<div>').text(row.options).html() + '" placeholder="0:Nu,1:Da">';
            html += '<p class="description">Pentru switcher recomandat: 0:Nu,1:Da. Pentru select/radio/checkbox folosește value:Label.</p>';
            html += '</div>';
            html += '<div class="jetsync-field-builder-cell jetsync-field-builder-cell-wide">';
            html += '<label>Description</label>';
            html += '<input type="text" class="regular-text jetsync-field-input jetsync-field-description" value="' + $('<div>').text(row.description).html() + '" placeholder="Short help text shown under the field">';
            html += '</div>';
            html += '</div>';
            html += '</div>';

            return $(html);
        }

        function parseMetaFieldRaw(raw) {
            var rows = [];
            $.each(String(raw || '').split(/\r?\n/), function(i, line) {
                line = $.trim(line);
                if (!line) {
                    return;
                }

                var parts = $.map(line.split('|'), function(item) {
                    return $.trim(String(item || ''));
                });

                rows.push(normalizeMetaFieldRow({
                    name: parts[0] || '',
                    title: parts[1] || '',
                    type: parts[2] || 'text',
                    description: parts[3] || '',
                    default_value: parts[4] || '',
                    options: parts[5] || ''
                }));
            });

            return rows;
        }

        function ensureSwitcherPreset($row) {
            var type = String($row.find('.jetsync-field-type').val() || '');
            if (type !== 'switcher') {
                return;
            }

            var $options = $row.find('.jetsync-field-options');
            var $default = $row.find('.jetsync-field-default');

            if ($.trim(String($options.val() || '')) === '') {
                $options.val('0:Nu,1:Da');
            }

            if ($.trim(String($default.val() || '')) === '') {
                $default.val('false');
            }
        }

        function serializeMetaFieldBuilder($builder) {
            var lines = [];

            $builder.find('.jetsync-field-builder-row').each(function() {
                var $row = $(this);
                var name = $.trim(String($row.find('.jetsync-field-name').val() || ''));
                var title = $.trim(String($row.find('.jetsync-field-title').val() || ''));
                var type = $.trim(String($row.find('.jetsync-field-type').val() || 'text'));
                var description = $.trim(String($row.find('.jetsync-field-description').val() || ''));
                var defaultValue = $.trim(String($row.find('.jetsync-field-default').val() || ''));
                var options = $.trim(String($row.find('.jetsync-field-options').val() || ''));

                if (!name) {
                    return;
                }

                lines.push([name, title, type, description, defaultValue, options].join('|'));
            });

            var serialized = lines.join('\n');
            $builder.find('.jetsync-fields-serialized').val(serialized);
            $builder.find('.jetsync-fields-raw').val(serialized);
        }

        function addMetaFieldBuilderRow($scope, row) {
            var $builder = $scope.find('.jetsync-fields-builder').first();
            var nextIndex = parseInt(String($builder.attr('data-next-index') || '0'), 10);
            if (isNaN(nextIndex)) {
                nextIndex = 0;
            }

            var $row = createMetaFieldRow(row, nextIndex);
            $builder.attr('data-next-index', String(nextIndex + 1));
            $builder.find('.jetsync-fields-builder-list').append($row);
            ensureSwitcherPreset($row);
            serializeMetaFieldBuilder($scope);
        }

        function setupMetaFieldBuilder() {
            $('.jetsync-fields-builder').each(function() {
                var $visualBuilder = $(this);
                var $scope = $visualBuilder.closest('td');
                serializeMetaFieldBuilder($scope);

                $scope.on('click', '.jetsync-add-field-row', function(e) {
                    e.preventDefault();
                    addMetaFieldBuilderRow($scope, {});
                });

                $scope.on('click', '.jetsync-add-switcher-row', function(e) {
                    e.preventDefault();
                    addMetaFieldBuilderRow($scope, {
                        name: 'updated',
                        title: 'Updated',
                        type: 'switcher',
                        default_value: 'false',
                        options: '0:Nu,1:Da'
                    });
                });

                $scope.on('click', '.jetsync-remove-field-row', function(e) {
                    e.preventDefault();
                    $(this).closest('.jetsync-field-builder-row').remove();
                    if ($scope.find('.jetsync-field-builder-row').length === 0) {
                        addMetaFieldBuilderRow($scope, {});
                        return;
                    }
                    serializeMetaFieldBuilder($scope);
                });

                $scope.on('change', '.jetsync-field-type', function() {
                    ensureSwitcherPreset($(this).closest('.jetsync-field-builder-row'));
                    serializeMetaFieldBuilder($scope);
                });

                $scope.on('input change', '.jetsync-field-input', function() {
                    serializeMetaFieldBuilder($scope);
                });

                $scope.on('click', '.jetsync-import-raw-fields', function(e) {
                    e.preventDefault();
                    var rows = parseMetaFieldRaw($scope.find('.jetsync-fields-raw').val());
                    var $list = $scope.find('.jetsync-fields-builder-list');
                    $list.empty();
                    $visualBuilder.attr('data-next-index', '0');

                    if (!rows.length) {
                        rows.push({});
                    }

                    $.each(rows, function(i, row) {
                        addMetaFieldBuilderRow($scope, row);
                    });
                });

                $scope.closest('form').on('submit', function() {
                    serializeMetaFieldBuilder($scope);
                });
            });
        }

        function setupListingsBuilder() {
            var $pt = $('#jetsync_listing_post_type');
            var $template = $('#jetsync_listing_template');
            if ($pt.length === 0 || $template.length === 0) {
                return;
            }

            var $form = $pt.closest('form');
            var $fields = $('#jetsync-listing-fields');
            if ($fields.length) {
                enableDragSort($fields);
            }

            loadListingMetaFields($pt.val());

            if ($template.val() === '') {
                $template.val(buildListingTemplateFromSelected($form));
            }
            updateListingPreview($form);

            $pt.on('change', function() {
                loadListingMetaFields($(this).val());
                updateListingPreview($form);
            });

            $template.on('input', function() {
                $template.attr('data-autogen', '0');
                updateListingPreview($form);
            });

            $form.on('click', '.jetsync-generate-listing-template', function(e) {
                e.preventDefault();
                $template.val(buildListingTemplateFromSelected($form));
                $template.attr('data-autogen', '1');
                updateListingPreview($form);
            });

            $form.on('change', 'input[name="selected_fields[]"]', function() {
                if ($template.attr('data-autogen') === '1' && ($template.val() === '' || $template.val().indexOf('jetsync-listing-item') !== -1)) {
                    $template.val(buildListingTemplateFromSelected($form));
                }
                updateListingPreview($form);
            });
        }

        setupMetaFieldBuilder();
        setupListingsBuilder();

    });

})(jQuery);
