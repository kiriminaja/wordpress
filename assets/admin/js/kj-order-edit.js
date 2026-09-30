(function ($) {
  "use strict";

  $(function () {
    var config = window.kiriofOrderEdit || {};
    var orderData;
    try {
      orderData = JSON.parse(config.orderData || "{}");
    } catch (e) {
      orderData = {};
    }
    var trackingUrl = config.trackingUrl || "#";
    var labelPpn = "11%";

    $("#side-sortables").append(
      '<div id="woocommerce-customer-history" class="postbox">' +
        '<div class="postbox-header"><h2 class="hndle ui-sortable-handle">Shipping</h2></div>' +
        '<div class="inside"><table id="kj-order-edit-shipping" style="width:100%"><tbody>' +
        "<tr><th>Order ID <span>:</span></th><td>" +
        (orderData.order_id || "") +
        "</td></tr>" +
        "<tr><th>Pickup ID <span>:</span></th><td>" +
        (orderData.pickup_id || "") +
        "</td></tr>" +
        "<tr><th>Payment <span>:</span></th><td>" +
        (orderData.payment_type || "") +
        "</td></tr>" +
        "<tr><th>Service <span>:</span></th><td>" +
        (orderData.service || "") +
        "</td></tr>" +
        "<tr><th>AWB <span>:</span></th><td>" +
        (orderData.awb || "") +
        "</td></tr>" +
        "<tr><th>Status <span>:</span></th><td>" +
        (orderData.status || "") +
        "</td></tr>" +
        "<tr><th>Shipping Cost <span>:</span></th><td>" +
        (orderData.shipping_cost || "") +
        "</td></tr>" +
        (orderData.cod_fee !== "-"
          ? "<tr><th>COD Fee <span>:</span><br><em>(Include " +
            labelPpn +
            " Vat)</em></th><td>" +
            (orderData.cod_fee || "") +
            "</td></tr>"
          : "") +
        (orderData.insurance_fee !== "-"
          ? "<tr><th>Insurance Fee <span>:</span></th><td>" +
            (orderData.insurance_fee || "") +
            "</td></tr>"
          : "") +
        "<tr><th>Order Total <span>:</span></th><td>" +
        (orderData.transaction_value || "") +
        "</td></tr>" +
        "<tr><th>Total <span>:</span></th><td>" +
        (orderData.total || "") +
        "</td></tr>" +
        '</tbody></table></div><div class="add_note" style="padding:10px"><a class="button button-primary" href="' +
        trackingUrl +
        '" target="_blank" style="width:100%;text-align:center">Shipment Tracker</a></div></div>',
    );
  });
})(jQuery);
