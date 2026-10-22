(function ($) {
  "use strict";
  // Unified tone → [background, text] map for the compact rounded-square
  // kiriof-badge preview pill. Falls back to the legacy kj-badge class
  // palette when no tone is provided.
  function toneColors(tone) {
    var tones = {
      info: ["#e5f0f9", "#0f4c81"],
      success: ["#e6f4ea", "#1a6b35"],
      caution: ["#fef3d8", "#7a5900"],
      warning: ["#fdf0dc", "#8a4d0a"],
      critical: ["#fde7e7", "#a02323"],
      neutral: ["#f0f0f1", "#3c434a"],
      auto: ["#f0f0f1", "#3c434a"],
    };
    return tones[tone] || null;
  }
  function palette(status) {
    var colors = {
      primary: ["#ece6f8", "#5c2d91"],
      info: ["#e5f0f9", "#0f4c81"],
      warning: ["#fdf0dc", "#8a4d0a"],
      success: ["#e6f4ea", "#1a6b35"],
      teal: ["#dcf3f0", "#0d6b5e"],
      orange: ["#fdf0dc", "#8a4d0a"],
      slate: ["#e7ebef", "#3c5065"],
      rose: ["#fce4ec", "#8a2432"],
      danger: ["#fde7e7", "#a02323"],
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
      tone = source.data("kiriof-status-tone"),
      status = source.data("kiriof-status-class");
    if (!label || (!tone && !status)) return;
    var colors = toneColors(tone) || palette(status);
    var mark = $(
      '<mark class="order-status kiriof-order-preview-status"><span></span></mark>',
    ).css({
      margin: "0 10px 0 6px",
      background: colors[0],
      color: colors[1],
      borderRadius: "6px",
      fontSize: "11px",
      fontWeight: 600,
      padding: "1px 6px",
      lineHeight: "1.45",
      verticalAlign: "middle",
    });
    mark.find("span").text(label);
    var wcStatus = header.find(".order-status").first();
    if (wcStatus.length) mark.insertAfter(wcStatus);
    else header.prepend(mark);
  });
})(jQuery);
