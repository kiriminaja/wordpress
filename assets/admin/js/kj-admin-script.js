// Use AJAX URL passed from WordPress via wp_localize_script
function kiriofAjaxRoute() {
  return kiriofAjax.ajaxurl;
}

function kiriofMoneyFormat(angka, prefix) {
  var number_string = angka;
  number_string = number_string.toString();
  var split = number_string.split(",");
  var sisa = split[0].length % 3;
  var rupiah = split[0].substr(0, sisa);
  var ribuan = split[0].substr(sisa).match(/\d{3}/gi);

  // tambahkan titik jika yang di input sudah menjadi angka ribuan
  if (ribuan) {
    separator = sisa ? "." : "";
    rupiah += separator + ribuan.join(".");
  }

  rupiah = split[1] != undefined ? rupiah + "," + split[1] : rupiah;
  return (
    prefix == undefined ? rupiah
    : rupiah ? "Rp. " + rupiah
    : ""
  );
}

function kiriofPrintAsString(value, placeholder = "") {
  if (value == null) return placeholder;
  return value;
}

function kiriofRenderQrCode(target, text, options) {
  var $target = jQuery(target);
  var config = jQuery.extend(
    {
      width: 256,
      height: 256,
    },
    options || {}
  );

  $target.empty();

  if (!text) {
    $target
      .append(
        jQuery("<div />")
          .css({
            color: "#a60000",
            fontWeight: "600",
            textAlign: "center",
            maxWidth: config.width + "px",
          })
          .text("QR payment tidak tersedia. Silakan refresh pembayaran.")
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
      var qrCode = new QRCodeStyling({
        width: config.width,
        height: config.height,
        type: "canvas",
        data: text,
        margin: 0,
        dotsOptions: {
          color: "#000000",
          type: "square"
        },
        backgroundOptions: {
          color: "#ffffff"
        }
      });
      qrCode.append($target.get(0));
      return true;
    } catch (error) {
      console.error("Error rendering QR with QRCodeStyling:", error);
    }
  }

  $target
    .append(
      jQuery("<div />")
        .css({
          color: "#a60000",
          fontWeight: "600",
          textAlign: "center",
          maxWidth: config.width + "px",
        })
        .text("QR generator tidak tersedia. Silakan refresh halaman.")
    );
  return false;
}

jQuery(document).on("input", ".kiriof_int_input", function () {
  this.value = this.value.replace(/\D/g, "");
  if (jQuery(this).hasClass("duplicate_into")) {
    var duplicateTarget = jQuery(
      'input[name="' + $(this).data("duplicate_into") + '"]',
    );
    duplicateTarget.val(this.value);
    duplicateTarget.trigger("change");
  }
  if (jQuery(this).hasClass("currency")) {
    this.value = kiriofFormatRupiah(this.value);
  }
});

function kiriofGetUrlParameter(sParam) {
  var sPageURL = window.location.search.substring(1),
    sURLVariables = sPageURL.split("&"),
    sParameterName,
    i;

  for (i = 0; i < sURLVariables.length; i++) {
    sParameterName = sURLVariables[i].split("=");

    if (sParameterName[0] === sParam) {
      return sParameterName[1] === undefined ?
          true
        : decodeURIComponent(sParameterName[1]);
    }
  }
  return false;
}

/**
 * Unified modal close handling:
 * - ESC key closes the topmost visible modal
 * - Clicking backdrop closes the modal
 * - Clicking .closebtn-container closes the modal
 */
(function ($) {
  "use strict";

  function closeModal($modal) {
    $modal.addClass("kj-hidden");
    $modal.find(".err_msg").html("").addClass("kj-hidden");
  }

  // ESC key closes the topmost visible modal
  $(document).on("keydown", function (e) {
    if (e.key === "Escape" || e.keyCode === 27) {
      var $visible = $(".modal-container:visible").closest("[id]");
      if ($visible.length) {
        e.preventDefault();
        closeModal($visible.last());
      }
    }
  });

  // Backdrop click closes modal
  $(document).on("click", ".media-modal-backdrop", function () {
    var $modal = $(this).closest("[id]");
    if ($modal.length) {
      closeModal($modal);
    }
  });

  // Close button click (unified delegation)
  $(document).on("click", ".closebtn-container", function () {
    var $modal = $(this)
      .closest("[id]")
      .filter(function () {
        return $(this).find(".modal-container").length > 0;
      });
    if ($modal.length) {
      closeModal($modal);
    }
  });
})(jQuery);
