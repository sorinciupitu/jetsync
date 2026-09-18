(function(blocks, element, i18n, components, blockEditor) {
    var el = element.createElement;
    var __ = i18n.__;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var SelectControl = components.SelectControl;

    function buildOptions() {
        var raw = window.JetSyncListingOptions || [];
        var opts = raw.map(function(o) {
            var id = String((o && o.id) || '');
            var title = String((o && o.title) || '');
            var label = title ? (title + ' (' + id + ')') : id;
            return { label: label, value: id };
        });
        opts.unshift({ label: __('Select a listing', 'jet-sync'), value: '' });
        return opts;
    }

    blocks.registerBlockType('jetsync/listing', {
        title: __('JetSync Listing', 'jet-sync'),
        icon: 'feedback',
        category: 'widgets',
        attributes: {
            id: { type: 'string', default: '' }
        },
        edit: function(props) {
            var id = String((props.attributes && props.attributes.id) || '');
            var options = buildOptions();

            return [
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: __('Listing', 'jet-sync'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Listing', 'jet-sync'),
                            value: id,
                            options: options,
                            onChange: function(next) {
                                props.setAttributes({ id: next });
                            }
                        })
                    )
                ),
                el('div', { key: 'view', className: 'jetsync-block-preview' },
                    id ? el('code', null, '[jetsync_listing id="' + id + '"]') : el('span', null, __('Select a listing from the sidebar.', 'jet-sync'))
                )
            ];
        },
        save: function() {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.components, window.wp.blockEditor);

