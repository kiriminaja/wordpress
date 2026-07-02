import { exposeAjaxRoute } from "../../shared/utils/ajax";
import { bindIntegerInput } from "../../shared/utils/int-input";
import { exposeMoneyFormat } from "../../shared/utils/money";
import { exposePrintAsString } from "../../shared/utils/print";

exposeAjaxRoute();
exposeMoneyFormat();
exposePrintAsString();

function kiriofRenderQrCode(
  target: JQuery<HTMLElement> | HTMLElement | string,
  text: string,
  options?: Record<string, unknown>,
) {
  const $target = typeof target === "string" || target instanceof HTMLElement ? jQuery(target) : target;
  const config = {
    width: 256,
    height: 256,
    ...options,
  };

  $target.empty();

  if (!text) {
    $target.append(
      jQuery("<div />")
        .css({
          color: "#a60000",
          fontWeight: "600",
          textAlign: "center",
          maxWidth: config.width + "px",
        })
        .text("QR payment tidak tersedia. Silakan refresh pembayaran."),
    );
    return false;
  }

  if (typeof jQuery.fn.qrcode === "function") {
    try {
      $target.qrcode({
        text: text,
        width: config.width,
        height: config.height,
      });
      return true;
    } catch (error) {
      console.error("Error rendering QR with jquery-qrcode:", error);
    }
  }

  if (typeof QRCodeStyling === "function") {
    try {
      const qrCode = new QRCodeStyling({
        width: config.width,
        height: config.height,
        type: "canvas",
        data: text,
        margin: 0,
        dotsOptions: {
          color: "#000000",
          type: "square",
        },
        backgroundOptions: {
          color: "#ffffff",
        },
      });
      qrCode.append($target.get(0));
      return true;
    } catch (error) {
      console.error("Error rendering QR with QRCodeStyling:", error);
    }
  }

  $target.append(
    jQuery("<div />")
      .css({
        color: "#a60000",
        fontWeight: "600",
        textAlign: "center",
        maxWidth: config.width + "px",
      })
      .text("QR generator tidak tersedia. Silakan refresh halaman."),
  );
  return false;
}
window.kiriofRenderQrCode = kiriofRenderQrCode;

bindIntegerInput(jQuery);

function kiriofGetUrlParameter(sParam: string): string | boolean {
  const sPageURL = window.location.search.substring(1),
    sURLVariables = sPageURL.split("&");

  for (let i = 0; i < sURLVariables.length; i++) {
    const sParameterName = sURLVariables[i].split("=");

    if (sParameterName[0] === sParam) {
      return sParameterName[1] === undefined ?
          true
        : decodeURIComponent(sParameterName[1]);
    }
  }
  return false;
}
window.kiriofGetUrlParameter = kiriofGetUrlParameter;

/**
 * Unified modal close handling:
 * - ESC key closes the topmost visible modal
 * - Clicking backdrop closes the modal
 * - Clicking .closebtn-container closes the modal
 */
(function ($) {
  "use strict";

  function closeModal($modal: JQuery<HTMLElement>) {
    $modal.addClass("kj-hidden");
    $modal.find(".err_msg").html("").addClass("kj-hidden");
  }

  // ESC key closes the topmost visible modal
  $(document).on("keydown", function (e) {
    if (e.key === "Escape" || e.keyCode === 27) {
      const $visible = $(".modal-container:visible").closest("[id]");
      if ($visible.length) {
        e.preventDefault();
        closeModal($visible.last());
      }
    }
  });

  // Backdrop click closes modal
  $(document).on("click", ".media-modal-backdrop", function () {
    const $modal = $(this).closest("[id]");
    if ($modal.length) {
      closeModal($modal);
    }
  });

  // Close button click (unified delegation)
  $(document).on("click", ".closebtn-container", function () {
    const $modal = $(this)
      .closest("[id]")
      .filter(function () {
        return $(this).find(".modal-container").length > 0;
      });
    if ($modal.length) {
      closeModal($modal);
    }
  });
})(jQuery);
