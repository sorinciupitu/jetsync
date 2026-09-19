jQuery(function($){
  // Checkbox Select all / Deselect all
  $(document).on('click', '.jetsync-checkbox-field .jetsync-select-all', function(e){
    e.preventDefault();
    var $field = $(this).closest('.jetsync-checkbox-field');
    $field.find('input[type="checkbox"]').prop('checked', true);
  });
  $(document).on('click', '.jetsync-checkbox-field .jetsync-deselect-all', function(e){
    e.preventDefault();
    var $field = $(this).closest('.jetsync-checkbox-field');
    $field.find('input[type="checkbox"]').prop('checked', false);
  });

  // Relations: toggle connect panel
  $(document).on('click', '.jetsync-rel-connect-toggle', function(e){
    e.preventDefault();
    var $box = $(this).closest('.jetsync-rel-box');
    $box.find('.jetsync-rel-connect-panel').slideToggle(180);
    $box.find('.jetsync-rel-search').focus();
  });
  $(document).on('click', '.jetsync-rel-cancel-connect', function(e){
    e.preventDefault();
    $(this).closest('.jetsync-rel-connect-panel').slideUp(180);
  });

  // Relations: search filter
  $(document).on('input', '.jetsync-rel-search', function(){
    var q = String($(this).val() || '').toLowerCase();
    var $panel = $(this).closest('.jetsync-rel-connect-panel');
    var $opts = $panel.find('.jetsync-rel-connect-select option');
    $opts.each(function(){
      var txt = String($(this).text() || '').toLowerCase();
      $(this).toggle(txt.indexOf(q) !== -1 || q === '');
    });
  });

  // Relations: Add selected -> move to hidden select + add table rows
  $(document).on('click', '.jetsync-rel-do-connect', function(e){
    e.preventDefault();
    var $panel = $(this).closest('.jetsync-rel-connect-panel');
    var $box = $(this).closest('.jetsync-rel-box');
    var $selectSource = $panel.find('.jetsync-rel-connect-select');
    var $hidden = $box.find('.jetsync-rel-hidden-select');
    var $tbody = $box.find('.jetsync-rel-tbody');

    var selectedIds = [];
    $selectSource.find('option:selected').each(function(){
      selectedIds.push({ id: String($(this).val()), label: String($(this).text() || '') });
    });
    if (!selectedIds.length) return;

    // Remove empty row if present
    $tbody.find('.jetsync-rel-empty-row').remove();

    selectedIds.forEach(function(item){
      var id = item.id;
      var label = item.label || ('#' + id);
      // Check already in hidden
      if ($hidden.find('option[value="' + id + '"][selected]').length) return;
      // Add to hidden select
      var $existing = $hidden.find('option[value="' + id + '"]');
      if ($existing.length) {
        $existing.prop('selected', true);
      } else {
        $hidden.append($('<option>').attr('value', id).prop('selected', true).text(label));
      }
      // Remove from source select
      $selectSource.find('option[value="' + id + '"]').remove();
      // Add row to table
      var $tr = $('<tr>').attr('data-id', id);
      $tr.append($('<td>').addClass('jetsync-rel-title').text(label));
      var $actions = $('<td>').addClass('jetsync-rel-row-actions').css('text-align','center');
      // We don't have edit/view URLs here without server data; create placeholder that reload will correct.
      // Try to guess edit url from hidden option? Use ajax to fetch? Simpler: add buttons with reload hint.
      // We'll create generic edit/view that will be correct after save+reload, but for now provide disconnect only plus placeholder links
      var $edit = $('<a>').addClass('button button-small jetsync-btn-edit').attr('href', '#').css('opacity','0.5').css('pointer-events','none').html('<span class="dashicons dashicons-edit" style="font-size:14px;line-height:1;"></span> Edit');
      var $view = $('<a>').addClass('button button-small jetsync-btn-view').attr('href', '#').css('opacity','0.5').css('pointer-events','none').html('<span class="dashicons dashicons-visibility" style="font-size:14px;line-height:1;"></span> View');
      var $disc = $('<button>').attr('type','button').addClass('button button-small jetsync-btn-disconnect').attr('data-id', id).html('<span class="dashicons dashicons-dismiss" style="font-size:14px;line-height:1;"></span> Disconnect');
      $actions.append($edit).append(' ').append($view).append(' ').append($disc);
      $tr.append($actions);
      $tbody.append($tr);
    });

    // Clear search
    $panel.find('.jetsync-rel-search').val('');
    $selectSource.find('option').show();
  });

  // Relations: Disconnect
  $(document).on('click', '.jetsync-btn-disconnect', function(e){
    e.preventDefault();
    var id = String($(this).data('id') || '');
    if (!id) return;
    var $box = $(this).closest('.jetsync-rel-box');
    var $hidden = $box.find('.jetsync-rel-hidden-select');
    var $panelSelect = $box.find('.jetsync-rel-connect-select');
    var $row = $(this).closest('tr');

    // Deselect in hidden
    var $opt = $hidden.find('option[value="' + id + '"]');
    var label = $opt.text() || $row.find('.jetsync-rel-title').text() || ('#' + id);
    $opt.prop('selected', false);
    // Optionally also remove option if it was created dynamically? Keep but deselected
    // Add back to panel select if not exists
    if ($panelSelect.find('option[value="' + id + '"]').length === 0) {
      $panelSelect.append($('<option>').attr('value', id).text(label));
    }
    // Remove row
    $row.remove();
    // If no rows left, show empty row
    if ($box.find('.jetsync-rel-tbody tr').length === 0) {
      $box.find('.jetsync-rel-tbody').append('<tr class="jetsync-rel-empty-row"><td colspan="2" style="text-align:center; color:#64748b; padding:0.75rem;">--</td></tr>');
    }
  });

  // Gallery/Media: ensure after selection preview updates (handled by metabox-media.js) we also update layout classes
  // No extra needed; metabox-media.js already rerenders previews.

});
