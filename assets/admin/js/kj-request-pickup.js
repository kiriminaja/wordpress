/* global jQuery, kiriofAjax, kiriofAjaxRoute, kiriofMoneyFormat, kiriofRenderQrCode, kiriofRequestPickupConfig */
(function ($) {
  "use strict";

  var config = window.kiriofRequestPickupConfig || {};
  var i18n = config.i18n || {};
  var paymentId = null;
  var rescheduleId = null;
  var paymentTimer;

  function applySearch(key, value) {
    var $form = $("#table-form");
    $form.find('[name="' + key + '"]').val(value);
    if (key === "status" && value) {
      $form.find('[name="key"]').val("");
      $("#kiriof-payment-search").val("");
    }
    $form.find('[name="cpage"]').val("1").end().trigger("submit");
  }

  function goToPage(page) {
    $('#table-form [name="cpage"]').val(page);
    $("#table-form").trigger("submit");
  }

  function modalState(selector, state) {
    var $modal = $(selector);
    $modal.toggleClass("kj-hidden", state === "hidden");
    $modal
      .find(".kj-modal-loader")
      .toggleClass("kj-hidden", state !== "loading");
    $modal
      .find(".kj-modal-content")
      .toggleClass("kj-hidden", state !== "content");
    $modal
      .find(".kj-err-container")
      .toggleClass("kj-hidden", state !== "error");
  }

  function requestPickup() {
    var $modal = $("#request-pickup-modal");
    var orderId = $modal.find(".kiriof-request-pickup-submit").data("tid");
    $modal.find(".err_msg").addClass("kj-hidden").empty();
    modalState("#request-pickup-modal", "loading");
    $.ajax({
      type: "post",
      url: kiriofAjaxRoute(),
      data: {
        action: "kiriof_request_pickup_transaction",
        data: {
          schedule: $('[name="schedule-opt"]:checked').val(),
          order_ids: [orderId],
          nonce: kiriofAjax.nonce,
        },
      },
      complete: function (response) {
        var resp = JSON.parse(response.responseText).data;
        if (resp && resp.status !== 200) {
          modalState("#request-pickup-modal", "content");
          $modal
            .find(".err_msg")
            .text("*" + (resp.message || ""))
            .removeClass("kj-hidden");
          return;
        }
        var number = encodeURIComponent(
          (resp.data && resp.data.pickup_number) || "",
        );
        var url = config.redirectUrl + "&pickup_number=" + number;
        var openPayment =
          resp.data &&
          (resp.data.open_payment === true ||
            resp.data.open_payment === 1 ||
            resp.data.open_payment === "1");
        window.location.href = openPayment ? url + "&open_payment=1" : url;
      },
    });
  }

  function showPayment(id, retry) {
    retry = retry || 0;
    paymentId = id;
    $("#paymentQR").empty();
    modalState("#payment-modal", "loading");
    $.ajax({
      type: "post",
      url: kiriofAjaxRoute(),
      data: {
        action: "kiriof_get_payment_form",
        data: { payment_id: id, nonce: kiriofAjax.nonce },
      },
      complete: function (response) {
        var resp = JSON.parse(response.responseText).data;
        if (!resp || resp.status !== 200) {
          modalState("#payment-modal", "error");
          return;
        }
        var remote = resp.data.payment_data || {},
          local = resp.data.payment_in_wc_data || {};
        var localMethod = String(local.method || "").toLowerCase();
        var status = String(
          remote.payment_status || remote.status || "",
        ).toLowerCase();
        var paid =
          localMethod === "qris"
            ? !!remote.paid_at ||
              ["paid", "settlement", "settled", "success"].indexOf(status) !==
                -1
            : String(remote.status_code || "").trim() === "0" ||
              !!remote.pay_time ||
              !!remote.paid_at ||
              ["paid", "settlement", "settled", "success"].indexOf(status) !==
                -1;
        if (paid || String(local.status || "").toLowerCase() === "paid") {
          $("#payment-modal").addClass("kj-hidden");
          window.location.reload();
          return;
        }
        modalState("#payment-modal", "content");
        $("#payment-modal #trx-code").text(remote.payment_id || "");
        $("#payment-modal #trx-expired-at").text(resp.data.expired_at || "");
        $("#payment-modal .trx-pay-amount").text(
          kiriofMoneyFormat(resp.data.sum_fee_non_cod, "Rp"),
        );
        if (!remote.qr_content && retry < 20) {
          modalState("#payment-modal", "loading");
          setTimeout(function () {
            showPayment(id, retry + 1);
          }, 1000);
          return;
        }
        kiriofRenderQrCode("#paymentQR", remote.qr_content, {
          width: 256,
          height: 256,
        });
      },
      error: function () {
        modalState("#payment-modal", "error");
      },
    });
  }

  function showReschedule(id) {
    rescheduleId = id;
    modalState("#request-pickup-modal", "loading");
    $.ajax({
      type: "post",
      url: kiriofAjaxRoute(),
      data: {
        action: "kiriof_get_shipping_reschedule_pickup",
        data: { payment_id: id, nonce: kiriofAjax.nonce },
      },
      complete: function (response) {
        var resp = JSON.parse(response.responseText).data;
        if (!resp || resp.status !== 200) {
          modalState("#request-pickup-modal", "error");
          alert((resp && resp.message) || i18n.error);
          return;
        }
        var summary = resp.data.transaction_summary || {},
          amount = summary.sum_fee_non_cod || 0;
        $("#schedule-transaction-summary").html(
          '<div><div class="row"><div class="col">' +
            i18n.codCharges +
            '</div><div class="col" style="text-align:right;font-weight:700">Rp0</div></div><div class="row-divider" style="margin-top:.5rem"></div><div class="row"><div class="col">' +
            i18n.nonCodCharges +
            '</div><div class="col" style="text-align:right;font-weight:700">Rp' +
            kiriofMoneyFormat(amount) +
            '</div></div><div class="row-divider" style="margin-top:.5rem"></div><div class="row"><div class="col">' +
            i18n.totalCharges +
            '</div><div class="col" style="text-align:right;font-weight:700">Rp' +
            kiriofMoneyFormat(amount) +
            "</div></div></div>",
        );
        var $list = $("#schedule-opt-list").empty();
        $.each(resp.data.schedules || [], function (_, schedule) {
          var id = "opt_" + schedule.clock;
          $(
            '<div style="margin-bottom:.75rem"><div style="display:flex;align-items:center;justify-items:center;"><input style="margin:0" type="radio" name="schedule-opt"><span style="margin-left:.5rem;margin-top:auto;margin-bottom:auto"><label></label></span></div></div>',
          )
            .find("input")
            .attr({ id: id, value: schedule.clock })
            .end()
            .find("label")
            .attr("for", id)
            .text(schedule.label)
            .end()
            .appendTo($list);
        });
        var $modal = $("#request-pickup-modal");
        $modal
          .find(".kiriof-request-pickup-submit")
          .attr("data-tid", summary.order_id);
        modalState("#request-pickup-modal", "content");
      },
      error: function () {
        modalState("#request-pickup-modal", "error");
      },
    });
  }

  $(document).on("click", ".kiriof-filter-link", function (e) {
    e.preventDefault();
    applySearch($(this).data("filter-key"), $(this).data("filter-value"));
  });
  $(document).on("click", ".kiriof-month-apply", function () {
    applySearch("month", $("#" + $(this).data("month-select")).val());
  });
  $(document).on("change", ".kiriof-month-sync", function () {
    $("#" + $(this).data("sync-target")).val(this.value);
    applySearch("month", this.value);
  });
  $(document).on("click", ".kiriof-page-link", function (e) {
    e.preventDefault();
    goToPage($(this).data("page"));
  });
  $(document).on("click", ".kiriof-payment-button", function () {
    showPayment($(this).data("pickup-number"));
  });
  $(document).on("click", ".kiriof-payment-refresh", function () {
    showPayment(paymentId);
  });
  $(document).on("submit", ".kiriof-payment-search-form", function (e) {
    e.preventDefault();
  });
  $(document).on("keyup", "#kiriof-payment-search", function () {
    clearTimeout(paymentTimer);
    var value = this.value;
    paymentTimer = setTimeout(function () {
      applySearch("key", value);
    }, 400);
  });
  $(document).on("heartbeat-send", function (_, data) {
    data.kiriof_nonce_check = true;
  });
  $(document).on("heartbeat-tick", function (_, data) {
    if (data.kiriof_new_nonce) kiriofAjax.nonce = data.kiriof_new_nonce;
  });
  $(function () {
    var params = new URLSearchParams(window.location.search),
      number = params.get("pickup_number"),
      open = params.get("open_payment");
    if (number && (open === "1" || open === "true"))
      setTimeout(function () {
        params.delete("pickup_number");
        params.delete("open_payment");
        window.history.replaceState(
          null,
          "",
          window.location.pathname +
            (params.toString() ? "?" + params : "") +
            window.location.hash,
        );
        $(".kiriof-payment-button")
          .filter(function () {
            return $(this).attr("data-pickup-number") === number;
          })
          .first()
          .trigger("click");
      }, 150);
  });
})(jQuery);
