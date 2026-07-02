(function ($) {
  "use strict";

  interface TrackingDetails {
    awb?: string;
    service?: string;
    destination?: {
      name?: string;
      city?: string;
      province?: string;
    };
  }

  interface TrackingHistory {
    created_at?: string;
    status?: string;
  }

  interface TrackingData {
    number_order?: string;
    details?: TrackingDetails;
    histories?: TrackingHistory[];
  }

  interface TrackingResponse {
    status?: number;
    message?: string;
    data?: TrackingData;
  }

  interface WpAjaxResponse {
    success?: boolean;
    data?: TrackingResponse;
  }

  function getText(key: string, fallback: string): string {
    if (
      window.kiriofTracking &&
      window.kiriofTracking.i18n &&
      window.kiriofTracking.i18n[key]
    ) {
      return window.kiriofTracking.i18n[key];
    }
    return fallback;
  }

  function ajaxRoute() {
    if (typeof window.kiriofAjaxRoute === "function") {
      return window.kiriofAjaxRoute();
    }
    if (window.kiriofAjax && window.kiriofAjax.ajaxurl) {
      return window.kiriofAjax.ajaxurl;
    }
    return "";
  }

  function printValue(value: string, fallback: string): string {
    return value === null || value === undefined || value === "" ?
        fallback
      : value;
  }

  function hideStateComponent() {
    $(".track-btn").addClass("kj-hidden");
    $(".state-blank").addClass("kj-hidden");
    $(".state-err").addClass("kj-hidden");
    $(".state-loading").addClass("kj-hidden");
    $(".state-success").addClass("kj-hidden");
  }

  function renderDetails(data: TrackingData) {
    const trackingDetails = data && data.details ? data.details : {};
    const destination = trackingDetails.destination || {};
    const trackingOrderNumber = printValue(data ? data.number_order : "", "-");

    const $group = $("<div/>", { class: "tracking-gorup" });
    const $header = $("<div/>", { class: "tracking-header" }).appendTo($group);

    $("<p/>")
      .text(
        getText("orderNumber", "Nomor Order") + " : #" + trackingOrderNumber,
      )
      .appendTo($header);
    $("<p/>")
      .text(
        getText("awbNumber", "Nomor Resi") +
          " : " +
          printValue(trackingDetails.awb, "-"),
      )
      .appendTo($header);

    const $address = $("<div/>", { class: "tracking-address" }).appendTo(
      $group,
    );
    const $inline = $("<div/>", { class: "track-inline" }).appendTo($address);
    $("<p/>", { class: "textprimary" })
      .text(printValue(destination.name, "-"))
      .appendTo($inline);
    $("<p/>", { class: "textseccond" })
      .text(printValue(destination.city, "-"))
      .appendTo($inline);
    $("<p/>", { class: "textseccond textbold" })
      .text(printValue(destination.province, "-"))
      .appendTo($inline);

    const $courier = $("<div/>", { class: "tracking-courier" }).appendTo(
      $group,
    );
    $("<div/>", { class: "borderdashed" }).appendTo($courier);
    const $courierText = $("<div/>", { class: "textseccond" }).appendTo(
      $courier,
    );
    $("<p/>").text(getText("courier", "Kurir")).appendTo($courierText);
    $("<p/>", { class: "fontbold" })
      .text(printValue(trackingDetails.service, "-"))
      .appendTo($courierText);
    $("<div/>", { class: "borderdashed" }).appendTo($courier);

    $(".tracking-details").empty().append($group);
  }

  function renderHistories(histories?: TrackingHistory[]) {
    const $tbody = $(".tracking-table tbody").empty();
    $.each(histories || [], function (index, trackData) {
      $("<tr/>")
        .append($("<td/>").text(printValue(trackData.created_at, "-")))
        .append($("<td/>").text(printValue(trackData.status, "-")))
        .appendTo($tbody);
    });
  }

  function trackOrder() {
    hideStateComponent();
    $(".state-loading").removeClass("kj-hidden");

    $.ajax({
      type: "POST",
      url: ajaxRoute(),
      data: {
        action: "kiriof-tracking-ajax",
        order_number: $('[name="order_number"]').val(),
      },
      success: function (res: WpAjaxResponse) {
        const response = res && res.success ? res.data : null;

        hideStateComponent();
        $(".track-btn").removeClass("kj-hidden");

        if (response && response.status === 200) {
          $(".state-success").removeClass("kj-hidden");
          renderDetails(response.data || {});
          renderHistories(response.data ? response.data.histories : []);
          return;
        }

        $(".state-err").removeClass("kj-hidden");
        $("#err_msg").text(
          response && response.message ?
            response.message
          : getText("notFound", "Order tidak ditemukan"),
        );
      },
      error: function () {
        hideStateComponent();
        $(".track-btn").removeClass("kj-hidden");
        $(".state-err").removeClass("kj-hidden");
        $("#err_msg").text(getText("notFound", "Order tidak ditemukan"));
      },
    });
  }

  window.trackOrder = trackOrder;

  $(function () {
    $(".track-btn").on("click", function (event) {
      event.preventDefault();
      trackOrder();
    });

    const urlParams = new URLSearchParams(window.location.search);
    const orderIdToLoad = urlParams.get("order_id");

    if (orderIdToLoad) {
      $('[name="order_number"]').val(orderIdToLoad);
      trackOrder();
    }
  });
})(jQuery);
