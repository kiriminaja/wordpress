(function ($) {
  "use strict";

  function districtFields() {
    return $(
      "#billing_kiriof_destination_area, #shipping_kiriof_destination_area",
    );
  }

  function districtNameField($field) {
    var addressType = String($field.attr("id") || "").indexOf("shipping_") === 0
      ? "shipping"
      : "billing";
    return $("#" + addressType + "_kiriof_destination_area_name");
  }

  function postcodeField($field) {
    var addressType = String($field.attr("id") || "").indexOf("shipping_") === 0
      ? "shipping"
      : "billing";
    return $("#" + addressType + "_postcode");
  }

  function extractPostcode(row) {
    row = row || {};
    var postcode = row.postcode
      || row.zipcode
      || row.zip_code
      || row.postal_code
      || row.kode_pos
      || row.kodepos
      || "";

    if (!postcode && row.text) {
      var match = String(row.text).match(/\b\d{5}\b/);
      postcode = match ? match[0] : "";
    }

    return String(postcode || "").replace(/\s+/g, "").trim();
  }

  function setPostcodeFromDistrict($field, selected) {
    var postcode = extractPostcode(selected);
    var $postcode = postcodeField($field);

    if (!postcode || !$postcode.length) {
      return;
    }

    $postcode
      .data("kiriofSettingPostcodeFromDistrict", true)
      .data("kiriofInitialPostcode", postcode)
      .val(postcode)
      .trigger("input")
      .trigger("change");

    setTimeout(function () {
      $postcode.data("kiriofSettingPostcodeFromDistrict", false);
    }, 0);
  }

  function clearDistrict($field) {
    $field.val("").trigger("change.select2");
    districtNameField($field).val("");
  }

  function hideBlockMirrorDistrictFields() {
    $("input, select, textarea").each(function () {
      var fieldName = String(this.name || "");
      var fieldId = String(this.id || "");
      if (fieldName.indexOf("_wc_") === -1 && fieldId.indexOf("_wc_") === -1) {
        return;
      }
      if (
        fieldName.indexOf("kiriminaja-official/kiriof_destination_area") === -1
        && fieldId.indexOf("kiriminaja-official/kiriof_destination_area") === -1
      ) {
        return;
      }

      $(this).closest(".form-row, p, div").first().hide();
    });
  }

  $(function () {
    var select = $.fn.selectWoo || $.fn.select2;
    var $fields = districtFields();

    hideBlockMirrorDistrictFields();
    setTimeout(hideBlockMirrorDistrictFields, 300);

    if (!$fields.length || !select || typeof kiriofAjax === "undefined") {
      return;
    }

    $fields.each(function () {
      var $field = $(this);
      select.call($field, {
        minimumInputLength: 3,
        placeholder:
          typeof kiriofAccountAddress !== "undefined"
            ? kiriofAccountAddress.selectOption
            : "Select Option",
        allowClear: true,
        ajax: {
          url: kiriofAjax.ajaxurl,
          dataType: "json",
          type: "POST",
          delay: 250,
          data: function (params) {
            var term = params && params.term ? params.term : "";
            return {
              action: "kiriminaja_subdistrict_search",
              nonce: kiriofAjax.nonce,
              term: term,
              data: { term: term, search: term },
            };
          },
          processResults: function (response) {
            var rows = response && response.success !== false && response.data
              ? response.data
              : [];
            return {
              results: $.map(rows, function (row) {
                return {
                  id: row.id,
                  text: row.text,
                  postcode: extractPostcode(row),
                };
              }),
            };
          },
          cache: true,
        },
      });

      $field
        .on("select2:select.kiriofAccountDistrict", function (event) {
          var selected = event.params && event.params.data ? event.params.data : {};
          districtNameField($field).val(selected.text || "");
          setPostcodeFromDistrict($field, selected);
        })
        .on("select2:clear.kiriofAccountDistrict", function () {
          districtNameField($field).val("");
        });
    });

    $("#billing_postcode, #shipping_postcode").each(function () {
      $(this).data("kiriofInitialPostcode", String($(this).val() || ""));
    }).on(
      "input.kiriofAccountDistrict change.kiriofAccountDistrict",
      function () {
        if ($(this).data("kiriofSettingPostcodeFromDistrict")) {
          return;
        }
        var currentPostcode = String($(this).val() || "");
        if (currentPostcode === String($(this).data("kiriofInitialPostcode") || "")) {
          return;
        }
        var prefix = String(this.id || "").indexOf("shipping_") === 0
          ? "shipping"
          : "billing";
        clearDistrict($("#" + prefix + "_kiriof_destination_area"));
      },
    );
  });
})(jQuery);
