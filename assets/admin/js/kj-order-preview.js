(function ($) {
  "use strict";
  function palette(status) {
    var colors = {
      primary: ["#2563eb", "#fff"],
      info: ["#0891b2", "#fff"],
      warning: ["#f59e0b", "#1f2937"],
      success: ["#16a34a", "#fff"],
      teal: ["#0f766e", "#fff"],
      orange: ["#ea580c", "#fff"],
      slate: ["#475569", "#fff"],
      rose: ["#e11d48", "#fff"],
      danger: ["#e11d48", "#fff"],
    };
    var key = Object.keys(colors).find(function (name) {
      return (status || "").indexOf(name) !== -1;
    });
    return colors[key || "slate"];
  }
  $(document.body).on("wc_backbone_modal_loaded", function (event, target) {
    if (target !== "wc-modal-view-order") return;
    var modal = $(".wc-backbone-modal.wc-order-preview"),
      details = modal.find(".kiriof-order-preview-shipment-details"),
      header = modal.find(".wc-backbone-modal-header");
    var panel = modal
      .find(".wc-order-preview-addresses .wc-order-preview-address")
      .eq(1);
    if (!panel.length)
      panel = modal
        .find(".wc-order-preview-addresses .wc-order-preview-address")
        .eq(0);
    if (details.length && panel.length) details.appendTo(panel);
    modal.find(".kiriof-order-preview-status").remove();
    var source = modal
      .find(
        ".kiriof-order-preview-shipment-details, .kiriof-order-preview-status-source",
      )
      .first();
    var label = source.data("kiriof-status-label"),
      status = source.data("kiriof-status-class");
    if (!label || !status) return;
    var colors = palette(status);
    var mark = $(
      '<mark class="order-status kiriof-order-preview-status"><span></span></mark>',
    ).css({
      margin: "0 10px 0 6px",
      background: colors[0],
      color: colors[1],
      verticalAlign: "middle",
    });
    mark.find("span").text(label);
    var wcStatus = header.find(".order-status").first();
    if (wcStatus.length) mark.insertAfter(wcStatus);
    else header.prepend(mark);
  });
})(jQuery);
