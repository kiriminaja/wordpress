import {
  kiriofGetClassicDistrictLabel,
  kiriofSetClassicDistrictLabel,
} from "./district-label";
import { kiriofExtractJsonResponseText } from "./json";

type ClassicDistrictOptions = {
  config: any;
  getClassicInsuranceValue: () => number;
  refreshCodInsurance: () => void;
};

export function bindClassicDistrictChange({
  config,
  getClassicInsuranceValue,
  refreshCodInsurance,
}: ClassicDistrictOptions): void {
  const kelurahanArea =
    "select#" +
    (config.fieldKey || "kiriof_destination_area") +
    ",select#kiriof_shipping_destination_area";

  jQuery(kelurahanArea)
    .off("change.kiriofClassicDistrict")
    .on("change.kiriofClassicDistrict", function () {
      const root = jQuery(this);
      const differentAddress = jQuery(
        '[name="ship_to_different_address"]:checked',
      ).length;
      const country = jQuery("#billing_country").find(":selected").val();
      const selectedDistrictLabel = kiriofGetClassicDistrictLabel(root, config);
      const ajaxurl =
        typeof kiriofAjax !== "undefined" && kiriofAjax.ajaxurl ?
          kiriofAjax.ajaxurl
        : config.ajaxUrl || "";
      const destinationNonce =
        typeof kiriofAjax !== "undefined" && kiriofAjax.destination_nonce ?
          kiriofAjax.destination_nonce
        : config.destinationNonce || "";
      const insurance = config.isCheckout ? getClassicInsuranceValue() : 0;

      kiriofSetClassicDistrictLabel(
        root,
        selectedDistrictLabel,
        differentAddress,
        config,
      );

      jQuery.ajax({
        url: ajaxurl,
        type: "post",
        data: {
          action: "kiriof_get_destination_area",
          val: root.val(),
          insurance: insurance,
          different_address: differentAddress,
          text: selectedDistrictLabel,
          payment_method: jQuery('input[name="payment_method"]:checked').val(),
          nonce: destinationNonce,
          country: country ?? "ID",
        },
        dataType: "JSON",
        dataFilter: function (raw) {
          return kiriofExtractJsonResponseText(raw);
        },
        beforeSend: function () {
          if (config.isCart) {
            jQuery(".kj-cart-sidebar").block({ message: null });
          } else {
            jQuery("#order_review").find(".shop_table").block({ message: null });
          }
        },
        success: function (response) {
          const responseData = response && response.data ? response.data : {};

          if (response.success === false || responseData.code != 200) {
            jQuery(".woocommerce-notices-wrapper").append(
              responseData.msg || response.msg || "",
            );
            toggleCalculationValidation(false);
          } else {
            toggleCalculationValidation(true);
          }

          kiriofSetClassicDistrictLabel(
            root,
            selectedDistrictLabel,
            differentAddress,
            config,
          );

          if (config.isCart) {
            jQuery('button[name="calc_shipping"]').trigger("click");
            jQuery(document.body).trigger("update_checkout", {
              update_shipping_method: true,
            });
          } else {
            jQuery(document.body).trigger("update_checkout", {
              update_shipping_method: true,
            });

            jQuery(document.body).one("updated_checkout", function () {
              refreshCodInsurance();
            });
          }
        },
        error: function (xhr, textStatus, errorThrown) {
          if (window.console) {
            console.warn("[KiriminAja] Destination area AJAX failed", {
              status: xhr.status,
              textStatus: textStatus,
              error: errorThrown,
            });
          }
          if (String(xhr.status) !== "200") {
            alert("Sorry System Trouble Error Code : " + xhr.status);
          }
          toggleCalculationValidation(false);
          return false;
        },
        complete: function () {
          if (config.isCart) {
            jQuery(".kj-cart-sidebar").unblock();
          } else {
            jQuery("#order_review").find(".shop_table").unblock();
          }
        },
      });
    });

  function toggleCalculationValidation(isCompleted = false) {
    jQuery('[name="kiriof_checkout_token"]').val(isCompleted ? "1" : "");
  }
}

export function bindClassicDistrictSearch(config: any): void {
  const subDistrictSelectElem = jQuery(
    `[name="${config.fieldKey || "kiriof_destination_area"}"],[name=kiriof_shipping_destination_area]`,
  );
  const ajaxurl =
    typeof kiriofAjax !== "undefined" && kiriofAjax.ajaxurl ?
      kiriofAjax.ajaxurl
    : config.ajaxUrl || "";
  const nonce =
    typeof kiriofAjax !== "undefined" && kiriofAjax.nonce ?
      kiriofAjax.nonce
    : config.nonce || "";
  const select2 = jQuery.fn.selectWoo || jQuery.fn.select2;

  if (!subDistrictSelectElem.length || !select2 || !ajaxurl || !nonce) {
    return;
  }

  subDistrictSelectElem.each(function () {
    const $field = jQuery(this);

    if ($field.data("select2") || $field.data("selectWoo")) {
      select2.call($field, "destroy");
    }

    select2.call($field, {
      minimumInputLength: 3,
      placeholder: config.i18n.selectOption || "Select Option",
      allowClear: true,
      ajax: {
        url: ajaxurl,
        dataType: "json",
        type: "POST",
        delay: 250,
        data: function (search) {
          const term =
            search && (search.term || search.search || search.q) ?
              search.term || search.search || search.q
            : "";
          return {
            data: {
              term: term,
              search: term,
            },
            term: term,
            nonce: nonce,
            action: "kiriminaja_subdistrict_search",
          };
        },
        processResults: function (response) {
          const responseData =
            response && response.success !== false && response.data ?
              response.data
            : [];
          return {
            results: jQuery.map(responseData, function (item) {
              return {
                text: item.text,
                id: item.id,
              };
            }),
          };
        },
        cache: true,
      },
    });

    $field
      .off(
        "select2:select.kiriofClassicDistrict select2:clear.kiriofClassicDistrict",
      )
      .on("select2:select.kiriofClassicDistrict", function (event) {
        const selected =
          event.params && event.params.data ? event.params.data : {};
        const selectedId = selected.id || $field.val() || "";
        const selectedText = selected.text || "";

        if (selectedId && selectedText) {
          let hasOption = false;
          $field.find("option").each(function () {
            if (String(jQuery(this).val()) === String(selectedId)) {
              hasOption = true;
              jQuery(this).text(selectedText).prop("selected", true);
              return false;
            }
          });
          if (!hasOption) {
            $field.append(new Option(selectedText, selectedId, true, true));
          }
        }

        $field.data("kiriofSelectedDistrictText", selectedText);
        kiriofSetClassicDistrictLabel(
          $field,
          selectedText,
          jQuery('[name="ship_to_different_address"]:checked').length,
          config,
        );
      })
      .on("select2:clear.kiriofClassicDistrict", function () {
        $field.data("kiriofSelectedDistrictText", "");
        kiriofSetClassicDistrictLabel(
          $field,
          "",
          jQuery('[name="ship_to_different_address"]:checked').length,
          config,
        );
      });
  });

  subDistrictSelectElem.each(function () {
    const $el = jQuery(this);
    const selectedVal = $el.val();
    const selectedText = $el.find("option:selected").text();
    if (
      selectedVal &&
      selectedText &&
      selectedText !== (config.i18n.selectOption || "Select Option")
    ) {
      $el.trigger("change.select2");
    }
  });
}
