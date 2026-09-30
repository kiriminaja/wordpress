(function ($) {
  "use strict";
  var timeout = null;
  var config = window.kiriofFormShippingAddress || {};
  var selectName = "custom_select_field_shipping";
  $(function () {
    $("#custom_select_field_shipping")
      .select2({ tags: true, placeholder: "Masukkan Kelurahan" })
      .on("select2:open", function () {
        $(".select2-search__field").prop("id", "shipping_search");
      });
  });
  $("body").on("keyup", "#shipping_search", function () {
    var input = $(this),
      value = input.val();
    if (timeout) clearTimeout(timeout);
    timeout = setTimeout(function () {
      var select = $('[name="' + selectName + '"]');
      select
        .empty()
        .append('<option value="" disabled>Loading...</option>')
        .trigger("change")
        .select2("close")
        .select2("open");
      input.val(value);
      wp.ajax
        .post(config.ajaxAction || "kiriminaja_subdistrict_search", {
          data: { search: value },
        })
        .done(function (options) {
          select.empty();
          (options || []).forEach(function (item) {
            $("<option>").val(item.id).text(item.text).appendTo(select);
          });
          select.trigger("change").select2("close").select2("open");
          input.val(value);
        });
    }, 1000);
  });
})(jQuery);
