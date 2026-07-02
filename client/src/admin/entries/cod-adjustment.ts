/* global kiriofCodAdj */
/* jshint esversion: 6 */
import { formatNumber } from "../../shared/utils/money";

(function ($) {
  "use strict";

  function nonce() {
    return typeof kiriofCodAdj !== "undefined" && kiriofCodAdj.nonce ?
        kiriofCodAdj.nonce
      : "";
  }

  function ajaxUrl() {
    return typeof kiriofCodAdj !== "undefined" && kiriofCodAdj.ajaxUrl ?
        kiriofCodAdj.ajaxUrl
      : "/wp-admin/admin-ajax.php";
  }

  // -------------------------------------------------------------------------
  // COD Adjustment modal helpers
  // -------------------------------------------------------------------------
  function getCodAdjModal() {
    return $(".kiriof-cod-adjustment-modal");
  }

  function getCancelDeficitModal() {
    return $(".kiriof-cancel-deficit-modal");
  }

  /**
   * Wire up live recalculation inside the COD adjustment modal after it opens.
   */
  function initCodAdjModal() {
    const $modal = getCodAdjModal();
    if (!$modal.length) {
      return;
    }

    const shippingCost =
      parseFloat($modal.find(".kiriof-adj-raw-shipping").val()) || 0;
    const insuranceCost =
      parseFloat($modal.find(".kiriof-adj-raw-insurance").val()) || 0;
    const codFee =
      parseFloat($modal.find(".kiriof-adj-raw-cod-fee").val()) || 0;
    const minCod = parseFloat($modal.find(".kiriof-adj-raw-min").val()) || 0;
    const maxCod =
      parseFloat($modal.find(".kiriof-adj-raw-max").val()) || 10000000;
    const totalShipping = shippingCost + insuranceCost + codFee;

    function recalc(newCod) {
      const payout = newCod - shippingCost - insuranceCost - codFee;
      $modal
        .find(".kiriof-adj-cod-paid")
        .text(formatNumber(newCod, { currency: true }));
      $modal
        .find(".kiriof-adj-total-shipping")
        .text(formatNumber(totalShipping, { currency: true }));
      $modal
        .find(".kiriof-adj-payout")
        .text(formatNumber(payout, { currency: true }))
        .css("color", payout <= 0 ? "#d63638" : "#007017");

      let hint = "";
      let valid = true;
      if (newCod < minCod) {
        valid = false;
        hint =
          typeof kiriofCodAdj !== "undefined" && kiriofCodAdj.hintMin ?
            kiriofCodAdj.hintMin.replace(
              "{min}",
              formatNumber(minCod, { currency: true }),
            )
          : "Minimum " +
            formatNumber(minCod, { currency: true }) +
            " to avoid COD Settlement deficit";
      } else if (newCod > maxCod) {
        valid = false;
        hint =
          typeof kiriofCodAdj !== "undefined" && kiriofCodAdj.hintMax ?
            kiriofCodAdj.hintMax.replace(
              "{max}",
              formatNumber(maxCod, { currency: true }),
            )
          : "Must not exceed " + formatNumber(maxCod, { currency: true });
      }
      // Payout < 0 is informational (shown in red) but does NOT block submission.
      $modal.find(".kiriof-adj-hint").text(hint);
      $modal.find("#btn-next").prop("disabled", !valid);
      return valid;
    }

    const $input = $modal.find(".kiriof-adj-cod-input");
    recalc(parseFloat($input.val()) || 0);
    $input.off("input.kiriofAdj").on("input.kiriofAdj", function () {
      recalc(parseFloat($(this).val()) || 0);
    });
  }

  // Init the modal after WCBackboneModal renders it into the DOM.
  $(document.body).on("wc_backbone_modal_loaded", function (e, target) {
    if ("kiriof-modal-cod-adjustment" === target) {
      initCodAdjModal();
    }
  });

  // -------------------------------------------------------------------------
  // Open Adjustment modal
  // -------------------------------------------------------------------------
  window.kjShowCodAdjustModal = function (btn) {
    const $btn = $(btn);

    const shippingCost = parseFloat($btn.data("shippingCost")) || 0;
    const insuranceCost = parseFloat($btn.data("insuranceFee")) || 0;
    const codFee = parseFloat($btn.data("codFee")) || 0;
    const subTotal = parseFloat($btn.data("itemPrice")) || 0;
    const itemDiscount = parseFloat($btn.data("itemDiscount")) || 0;
    const shippingDiscount = parseFloat($btn.data("shippingDiscount")) || 0;
    const itemCoupon = $btn.data("itemCoupon") || "";
    const shippingCoupon = $btn.data("shippingCoupon") || "";
    const currentCod = parseFloat($btn.data("currentCod")) || 0;
    const codMinimum = parseFloat($btn.data("codMinimum")) || 0;
    const codMaximum = parseFloat($btn.data("codMaximum")) || 10000000;
    const totalShipping = shippingCost + insuranceCost + codFee;
    const payout = currentCod - shippingCost - insuranceCost - codFee;

    $(document.body).WCBackboneModal({
      template: "kiriof-modal-cod-adjustment",
      variable: {
        order_id: $btn.data("kaOrderId"),
        nonce: $btn.data("nonce") || nonce(),
        cod_minimum: codMinimum,
        cod_maximum: codMaximum,
        current_cod: currentCod,
        shipping_cost: shippingCost,
        insurance_cost: insuranceCost,
        cod_fee: codFee,
        item_discount: itemDiscount,
        shipping_discount: shippingDiscount,
        item_coupon: itemCoupon,
        shipping_coupon: shippingCoupon,
        sub_total: subTotal,
        total_shipping: totalShipping,
        payout: payout,
        // Pre-formatted display values.
        sub_total_fmt: formatNumber(subTotal, { currency: true }),
        shipping_fmt: formatNumber(shippingCost, { currency: true }),
        insurance_fmt: formatNumber(insuranceCost, { currency: true }),
        cod_fee_fmt: formatNumber(codFee, { currency: true }),
        total_shipping_fmt: formatNumber(totalShipping, { currency: true }),
        item_discount_fmt:
          itemDiscount > 0 ?
            "-" + formatNumber(itemDiscount, { currency: true })
          : "",
        shipping_discount_fmt:
          shippingDiscount > 0 ?
            "-" + formatNumber(shippingDiscount, { currency: true })
          : "",
        current_cod_fmt: formatNumber(currentCod, { currency: true }),
        payout_fmt: formatNumber(payout, { currency: true }),
        payout_color: payout <= 0 ? "color:#d63638;" : "color:#007017;",
      },
    });
  };

  // -------------------------------------------------------------------------
  // Open Cancel Deficit modal
  // -------------------------------------------------------------------------
  window.kjShowCancelDeficitModal = function (btn) {
    const $btn = $(btn);
    $(document.body).WCBackboneModal({
      template: "kiriof-modal-cancel-deficit",
      variable: {
        order_id: $btn.data("kaOrderId"),
        nonce: $btn.data("nonce") || nonce(),
      },
    });
  };

  // -------------------------------------------------------------------------
  // WCBackboneModal submit handler
  // -------------------------------------------------------------------------
  $(document.body).on(
    "wc_backbone_modal_next_response",
    function (event, target, data, closeModal) {
      // --- COD Adjustment ---
      if ("kiriof-modal-cod-adjustment" === target) {
        const $modal = getCodAdjModal();
        const $errMsg = $modal.find(".err_msg");
        const $loader = $modal.find(".kiriof-modal-state-loading");
        const $form = $modal.find("form");
        const $btnNext = $modal.find("#btn-next");

        const newTotalCod =
          parseFloat($modal.find(".kiriof-adj-cod-input").val()) || 0;
        const orderId = data.order_package_id || "";
        const dataNonce = data.nonce || "";

        // Re-validate.
        const minCod =
          parseFloat($modal.find(".kiriof-adj-raw-min").val()) || 0;
        const maxCod =
          parseFloat($modal.find(".kiriof-adj-raw-max").val()) || 10000000;
        const shippingCost =
          parseFloat($modal.find(".kiriof-adj-raw-shipping").val()) || 0;
        const insuranceCost =
          parseFloat($modal.find(".kiriof-adj-raw-insurance").val()) || 0;
        const codFee =
          parseFloat($modal.find(".kiriof-adj-raw-cod-fee").val()) || 0;
        const payout = newTotalCod - shippingCost - insuranceCost - codFee;

        if (newTotalCod < minCod || newTotalCod > maxCod || payout < 0) {
          $errMsg
            .text(
              "*" +
                ((
                  typeof kiriofCodAdj !== "undefined" &&
                  kiriofCodAdj.hintCodInvalid
                ) ?
                  kiriofCodAdj.hintCodInvalid
                : "Please correct the COD value."),
            )
            .show();
          return;
        }

        $errMsg.hide().text("");
        $loader.show();
        $form.css("opacity", 0.45);
        $btnNext.prop("disabled", true);

        $.ajax({
          type: "post",
          url: ajaxUrl(),
          data: {
            action: "kiriof_cod_adjust",
            data: {
              nonce: dataNonce,
              order_package_id: orderId,
              new_total_cod: newTotalCod,
            },
          },
          complete: function (response) {
            const resp = JSON.parse(response.responseText);
            if (resp && resp.success) {
              closeModal();
              window.location.reload();
            } else {
              $loader.hide();
              $form.css("opacity", 1);
              $btnNext.prop("disabled", false);
              const msg =
                resp && resp.data && resp.data.message ? resp.data.message
                : (
                  typeof kiriofCodAdj !== "undefined" &&
                  kiriofCodAdj.errorGeneral
                ) ?
                  kiriofCodAdj.errorGeneral
                : "An error occurred.";
              $errMsg.text("*" + msg).show();
            }
          },
        });
        return;
      }

      // --- Cancel Deficit ---
      if ("kiriof-modal-cancel-deficit" === target) {
        const $modal2 = getCancelDeficitModal();
        const $errMsg2 = $modal2.find(".err_msg");
        const $loader2 = $modal2.find(".kiriof-modal-state-loading");
        const $btnNext2 = $modal2.find("#btn-next");

        const orderId2 = data.order_package_id || "";
        const dataNonce2 = data.nonce || "";

        $errMsg2.hide().text("");
        $loader2.show();
        $btnNext2.prop("disabled", true);

        $.ajax({
          type: "post",
          url: ajaxUrl(),
          data: {
            action: "kiriof_cancel_deficit",
            data: {
              nonce: dataNonce2,
              order_package_id: orderId2,
            },
          },
          complete: function (response) {
            const resp = JSON.parse(response.responseText);
            if (resp && resp.success) {
              closeModal();
              window.location.reload();
            } else {
              $loader2.hide();
              $btnNext2.prop("disabled", false);
              const msg2 =
                resp && resp.data && resp.data.message ? resp.data.message
                : (
                  typeof kiriofCodAdj !== "undefined" &&
                  kiriofCodAdj.errorGeneral
                ) ?
                  kiriofCodAdj.errorGeneral
                : "An error occurred.";
              $errMsg2.text("*" + msg2).show();
            }
          },
        });
        return;
      }
    },
  );
})(jQuery);
