jQuery(function ($) {
  function isGalleryWrap($wrap) {
    return $wrap.hasClass('jetsync-media-gallery') || $wrap.find('.jetsync-gallery-grid').length > 0;
  }

  function renderGalleryPreview($wrap, attachments) {
    var $grid = $wrap.find(".jetsync-gallery-grid");
    var $hiddenPreview = $wrap.find(".jetsync-media-preview");
    var $container = $grid.length ? $grid : $hiddenPreview;
    if (!$container.length) $container = $wrap.find(".jetsync-media-preview");
    $container.empty();
    if (!attachments.length) {
      $container.append($('<div class="jetsync-gallery-empty">No images selected.</div>'));
      return;
    }
    attachments.forEach(function (att) {
      var url =
        (att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url) ||
        att.url ||
        "";
      if (!url) return;
      if ($grid.length) {
        var $item = $('<div class="jetsync-gallery-item"></div>');
        $item.append($('<img />', { src: url }));
        $container.append($item);
      } else {
        $container.append(
          $("<img />", {
            src: url,
            css: {
              width: "72px",
              height: "72px",
              objectFit: "cover",
              marginRight: "8px",
              marginBottom: "8px",
              borderRadius: "6px",
              border: "1px solid #e5e7eb",
            },
          })
        );
      }
    });
  }

  function renderSinglePreview($wrap, attachment) {
    var url =
      (attachment.sizes &&
        attachment.sizes.thumbnail &&
        attachment.sizes.thumbnail.url) ||
      (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) ||
      attachment.url ||
      "";
    var $preview = $wrap.find(".jetsync-media-preview");
    $preview.empty().removeClass('has-image');
    if (!url) {
      $preview.append('<div class="jetsync-media-placeholder"><span class="dashicons dashicons-format-image"></span></div>');
      return;
    }
    $preview.addClass('has-image');
    $preview.append($("<img />", { src: url, alt: "" }));
  }

  $(document).on("click", ".jetsync-media-select", function (e) {
    e.preventDefault();
    var $wrap = $(this).closest(".jetsync-media-field");
    var multiple = String($(this).data("multiple")) === "1";
    var $input = $wrap.find("input.jetsync-media-value");

    var frame = wp.media({
      title: "Select media",
      button: { text: "Use selection" },
      multiple: multiple,
    });

    frame.on("select", function () {
      var selection = frame.state().get("selection");
      if (!selection) return;

      if (!multiple) {
        var first = selection.first();
        if (!first) return;
        var att = first.toJSON();
        $input.val(att.id || "");
        renderSinglePreview($wrap, att);
        return;
      }

      var ids = [];
      var atts = [];
      selection.each(function (model) {
        var att = model.toJSON();
        if (!att || !att.id) return;
        ids.push(att.id);
        atts.push(att);
      });
      $input.val(ids.join(","));
      renderGalleryPreview($wrap, atts);
    });

    frame.open();
  });

  $(document).on("click", ".jetsync-media-clear", function (e) {
    e.preventDefault();
    var $wrap = $(this).closest(".jetsync-media-field");
    $wrap.find("input.jetsync-media-value").val("");
    var $preview = $wrap.find(".jetsync-media-preview");
    var $grid = $wrap.find(".jetsync-gallery-grid");
    if ($grid.length) {
      $grid.empty().append('<div class="jetsync-gallery-empty">No images selected.</div>');
    } else {
      $preview.empty().removeClass('has-image').append('<div class="jetsync-media-placeholder"><span class="dashicons dashicons-format-image"></span></div>');
    }
  });
});

