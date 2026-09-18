jQuery(function ($) {
  function renderGalleryPreview($wrap, attachments) {
    var $preview = $wrap.find(".jetsync-media-preview");
    $preview.empty();
    attachments.forEach(function (att) {
      var url =
        (att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url) ||
        att.url ||
        "";
      if (!url) return;
      $preview.append(
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
    });
  }

  function renderSinglePreview($wrap, attachment) {
    var url =
      (attachment.sizes &&
        attachment.sizes.thumbnail &&
        attachment.sizes.thumbnail.url) ||
      attachment.url ||
      "";
    $wrap.find(".jetsync-media-preview").empty();
    if (!url) return;
    $wrap
      .find(".jetsync-media-preview")
      .append(
        $("<img />", {
          src: url,
          css: {
            width: "120px",
            height: "120px",
            objectFit: "cover",
            borderRadius: "8px",
            border: "1px solid #e5e7eb",
          },
        })
      );
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
    $wrap.find(".jetsync-media-preview").empty();
  });
});

