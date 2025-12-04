<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<script>
    jQuery(document).ready(function (){
        getKiriminAjaTransactionData()
    })
    function getKiriminAjaTransactionData(){
        let orderId = `<?php echo esc_html($orderId); ?>`;
        let trackingUrl = `<?php echo esc_url($trackingUrl); ?>`;
        let kjOrderData = `<?php echo wp_kses_data($kjOrderData); ?>`;
        
        let kjOrderDataParsed = JSON.parse(kjOrderData);
        
        let label_ppn = '11%';
        let tBodycontent = '';
        
        jQuery('#side-sortables').append(`
<div id="woocommerce-customer-history" class="postbox ">
   <div class="postbox-header">
      <h2 class="hndle ui-sortable-handle">Shipping</h2>
      <div class="handle-actions hide-if-no-js"><button type="button" class="handle-order-higher" aria-disabled="false" aria-describedby="woocommerce-customer-history-handle-order-higher-description"><span class="screen-reader-text">Move up</span><span class="order-higher-indicator" aria-hidden="true"></span></button><span class="hidden" id="woocommerce-customer-history-handle-order-higher-description">Move Customer history box up</span><button type="button" class="handle-order-lower" aria-disabled="false" aria-describedby="woocommerce-customer-history-handle-order-lower-description"><span class="screen-reader-text">Move down</span><span class="order-lower-indicator" aria-hidden="true"></span></button><span class="hidden" id="woocommerce-customer-history-handle-order-lower-description">Move Customer history box down</span><button type="button" class="handlediv" aria-expanded="true"><span class="screen-reader-text">Toggle panel: Customer history</span><span class="toggle-indicator" aria-hidden="true"></span></button></div>
   </div>

   <div class="inside">
    <!--Content-->
    <table id="kj-order-edit-shipping" style="width: 100%">
        <tbody>
            <tr>
                <th>Order ID <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.order_id}</td>
            </tr>
            <tr>
                <th>Pickup ID <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.pickup_id}</td>
            </tr>
            <tr>
                <th>Payment <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.payment_type}</td>
            </tr>
            <tr>
                <th>Service <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.service}</td>
            </tr>
            <tr>
                <th>AWB <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.awb}</td>
            </tr>
            <tr>
                <th>Status <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.status}</td>
            </tr>
            <tr>
                <th>Shipping Cost <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.shipping_cost}</td>
            </tr>
            `
            +
            /** COD ROW */
            (
            kjOrderDataParsed?.cod_fee !=='-'
            ?    
             `
            <tr>
                <th>
                    COD Fee <span style="margin-left: auto">:</span>
                    <br/>
                    <em style="font-weight: 500;font-size:12px;">(Include `+label_ppn+` Vat)</em>
                </th>
                <td>${kjOrderDataParsed?.cod_fee}</td>
            </tr>
            `   
             :
            ``
            )
            +
            /** Insurance ROW */
            (
            kjOrderDataParsed?.insurance_fee !=='-'
                ?
                `
            <tr>
                <th>Insurance Fee <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.insurance_fee}</td>
            </tr>
            `
                :
                ``
            )
            +
            `
            <tr>
                <th>Order Total<span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.transaction_value}</td>
            </tr>
            <tr>
                <th>Total <span style="margin-left: auto">:</span></th>
                <td>${kjOrderDataParsed?.total}</td>
            </tr>
        </tbody>
    </table>
   </div>
    <!--BTN-->

    <div class="add_note" style="padding: 10px">
        <div style="display: relative">
            <button type="button" style="width: 100%" class="button save_order button-primary" name="save" value="Update">Shipment Tracker</button>        
            <a href="${trackingUrl}" target="_blank" style="position: absolute; top: 0;left: 0;bottom: 0;right: 0"></a>
        </div>
    </div>
</div>
        `)
        
    }
</script>