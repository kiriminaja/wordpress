<?php

global $wpdb;
$paymentTable = $wpdb->prefix . 'kiriminaja_payments';
$transactionTable = $wpdb->prefix . 'kiriminaja_transactions';
$query = "(
SELECT 
`".$paymentTable."`.*
,sum(CASE WHEN `".$transactionTable."`.cod_fee = 0 THEN `".$transactionTable."`.shipping_cost+`".$transactionTable."`.insurance_cost ELSE 0 END) as cost
FROM `".$paymentTable."` 
INNER JOIN `".$transactionTable."`
ON `".$paymentTable."`.pickup_number = `".$transactionTable."`.pickup_number
GROUP BY `".$paymentTable."`.pickup_number
)";

$totalQuery = $wpdb->get_results( "SELECT id FROM `".$paymentTable."`" );
$total = count($totalQuery);

if (strlen(@$wpdb->last_error ?? '') > 0){
    (new \Inc\Base\BaseInit())->logThis('last_error',@$wpdb->last_error);
}
$items_per_page = 3;
$page = @$_GET['cpage'] ?? 1;
$offset = ( $page * $items_per_page ) - $items_per_page;
$results = $wpdb->get_results( $query . " ORDER BY id LIMIT ${offset}, ${items_per_page}" );
?>

<div class="wrap kj-wrapper">
    <h1>Proses Kirim</h1>
    
    <div style="margin-top: 1rem;margin-bottom: 1.5rem">
        <table class="wp-list-table widefat fixed striped table-view-list posts">
            <thead>
            <tr>
                <th style="width: 2rem" scope="col" class="manage-column column-thumb">No</th>
                <th scope="col" class="manage-column column-thumb">Pickup Number</th>
                <th scope="col" class="manage-column column-thumb">Tagihan Ongkir</th>
                <th scope="col" class="manage-column column-thumb">Status</th>
                <th scope="col" class="manage-column column-thumb"><span style="float: right">Action</span></th>
            </tr>
            </thead>
            <tbody id="the-list">
            <?php
            if (@$results&&count($results)>0){
                foreach($results as $id => $row){
                    $btnGroup='';
                    $statusContent= '<span style="color: #009b1e;font-weight: 600">Paid</span>';
                    if (@$row->status==="paid"){
                        $btnGroup.='<button name="save" style="background-color: #009b1e; border: 1px solid #009b1e" class="button-primary woocommerce-save-button" onclick="showPaymentForm(`'.@$row->pickup_number.'`)" type="button" >Bayar</button>';
//                        $btnGroup.='<button name="save" style="background-color: #ad0000; border: 1px solid #ad0000" class="button-primary woocommerce-save-button" type="button" >Batalkan</button>';
                        $btnGroup.= '<button name="save" style="background-color: #4180d0; border: 1px solid #4180d0" class="button-primary woocommerce-save-button" onclick="showRescheduleForm(`'.@$row->pickup_number.'`)" type="button" >ReSchedule</button>';
                        $statusContent= '<span style="color: #ad0000;font-weight: 600">Waiting for payment</span>';
                    }
                    $btnGroup.='<button class="button-primary woocommerce-save-button" onclick="showDetail(`'.@$row->pickup_number.'`)" type="button" >Transaction Detail</button>';
                    
                    echo '
                <tr class="">
                    <td class="thumb column-thumb">'.$id+(($page-1)*$items_per_page+1).'</td>
                    <td class="manage-column column-thumb">'.@$row->pickup_number.'</td>
                    <td class="manage-column column-thumb">Rp. '.localMoneyFormat(@$row->cost ?? 0).'</td>
                    <td class="manage-column column-thumb">'.@$statusContent.'</td>
                    <td class="manage-column column-thumb">
                        <div style="text-align: right">
                        '.$btnGroup.'
                        </div>
                    </td>
                </tr>
                ';

                }
            }else{
                echo '<td colspan="5" style="text-align: center" class="manage-column column-thumb">Not Found</td>';
            }
            ?>
            </tbody>
        </table>
    </div>
    
    <div>
        <div style="float: right">
            <?php echo paginate_links( array(
                'base' => add_query_arg( 'cpage', '%#%' ),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => ceil($total / $items_per_page),
                'current' => $page
            )); ?>
        </div>
    </div>

    <?php include 'modal-detail.php' ?>
    <?php include 'modal-payment.php' ?>
    <?php include 'modal-request-pickup.php' ?>
</div>


<!--Request Pickup Detail-->
<script type="text/javascript">
    let previousSelectedPaymentId = null;
    function refreshShowDetail(){
        if (!previousSelectedPaymentId){return}
        showDetail(previousSelectedPaymentId)
    }
    

    
    function showDetail(paymentId){
        previousSelectedPaymentId = paymentId

        const modalElem = jQuery('#request-pickup-detail-modal')
        const modalElemContent = jQuery('#request-pickup-detail-modal .kj-modal-content')
        const modalElemLoader = jQuery('#request-pickup-detail-modal .kj-modal-loader')
        const modalElemErr = jQuery('#request-pickup-detail-modal .kj-err-container')
        
        modalElem.removeClass('kj-hidden')
        modalElemLoader.removeClass('kj-hidden')
        modalElemContent.addClass('kj-hidden')
        modalElemErr.addClass('kj-hidden')
        
        jQuery.ajax({
            type: "post",
            url: ajaxRouteGenerator(),
            data: {
                action: "kj_get_shipping_process_detail",  // the action to fire in the server
                data: {
                    payment_id:paymentId
                },         // any JS object
            },
            complete: function (response) {
                const resp = JSON.parse(response.responseText).data;
                if (resp?.status !== 200){
                    // formAlertToggler('menu_1',true,'Error',resp.message,'')
                    modalElemLoader.addClass('kj-hidden')
                    modalElemContent.addClass('kj-hidden')
                    modalElemErr.removeClass('kj-hidden')
                    return
                }
                modalElemLoader.addClass('kj-hidden')
                modalElemContent.removeClass('kj-hidden')
                modalElemErr.addClass('kj-hidden')
                
                const payment_data = resp?.data?.payment_data
                const transactions_data = resp?.data?.transactions_data

                console.log(payment_data)
                console.log(transactions_data)

                jQuery('#request-pickup-detail-modal #detail-pickup-number').text(payment_data?.pickup_number)
                jQuery('#request-pickup-detail-modal #detail-status').text(payment_data?.status)
                jQuery('#request-pickup-detail-modal #detail-non_cod_count').text(kjMoneyFormat(payment_data?.non_cod_count))
                jQuery('#request-pickup-detail-modal #detail-non_cod_sum').text(kjMoneyFormat(payment_data?.non_cod_sum,'Rp. '))
                jQuery('#request-pickup-detail-modal #detail-cod_count').text(kjMoneyFormat(payment_data?.cod_count))
                jQuery('#request-pickup-detail-modal #detail-cod_sum').text(kjMoneyFormat(payment_data?.cod_sum,'Rp. '))
                jQuery('#request-pickup-detail-modal #detail-payment_amount').text(kjMoneyFormat(payment_data?.payment_amount,'Rp. '))


                jQuery('#request-pickup-detail-modal #the-list').empty()
                transactions_data.forEach(function (transaction){
                    
                    let ongkirCalc = 0;
                    ongkirCalc+=Number(transaction?.insurance_cost ?? 0)
                    ongkirCalc+=Number(transaction?.shipping_cost ?? 0)
                    if(Number(transaction?.cod_fee)>0){
                        ongkirCalc+=Number(transaction?.transaction_value ?? 0)
                        ongkirCalc+=Number(transaction?.cod_fee ?? 0)
                    }
                    
                    jQuery('#request-pickup-detail-modal #the-list').append(`
                    <tr class="">
                        <td class="">
                            <input style="margin: 0" value="${transaction?.order_id}" type="checkbox" name="req_pickup_ids[]" id="in-product_cat-15">
                        </td>
                        <td class="">${transaction?.order_id}</td>
                        <td class="">${String(transaction?.awb)!=='null' ? transaction?.awb : '-'}</td>
                        <td class="">
                        ${transaction?.cod_fee>0 ? 'COD' : 'NON COD'}
                        <br>
                        ${kjMoneyFormat(ongkirCalc,'Rp. ')}
                        </td>
                        <td class="">
                            <div style="float: right">
                                <button name="save" class="button-primary woocommerce-save-button" type="button">Transaction Detail</button>
                            </div>
                        </td>
                    </tr>
                    `)
                })
                
                
            },
        });
        
    }
</script>

<!--Payment Detail-->
<script type="text/javascript">
    
    function showPaymentForm(paymentId){

        jQuery("#paymentQR").empty()
        
        const modalElem = jQuery('#payment-modal')
        const modalElemContent = jQuery('#payment-modal .kj-modal-content')
        const modalElemLoader = jQuery('#payment-modal .kj-modal-loader')
        const modalElemErr = jQuery('#payment-modal .kj-err-container')
        
        modalElem.removeClass('kj-hidden')
        modalElemLoader.removeClass('kj-hidden')
        modalElemContent.addClass('kj-hidden')
        modalElemErr.addClass('kj-hidden')
        
        jQuery.ajax({
            type: "post",
            url: ajaxRouteGenerator(),
            data: {
                action: "kj_get_shipping_process_detail",  // the action to fire in the server
                data: {
                    payment_id:paymentId
                },         // any JS object
            },
            complete: function (response) {
                const resp = JSON.parse(response.responseText).data;
                
                console.log(resp)
                
                if (resp?.status !== 200){
                    // formAlertToggler('menu_1',true,'Error',resp.message,'')
                    modalElemLoader.addClass('kj-hidden')
                    modalElemContent.addClass('kj-hidden')
                    modalElemErr.removeClass('kj-hidden')
                    return
                }
                modalElemLoader.addClass('kj-hidden')
                modalElemContent.removeClass('kj-hidden')
                modalElemErr.addClass('kj-hidden')

                
                var qrcode = new QRCode(document.getElementById("paymentQR"), {
                    text: "http://jindo.dev.naver.com/collie",
                    width: 256,
                    height: 256,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });


            },
        });
    }
    
</script>

<!--Request Pickup-->
<script type="text/javascript">

    function showRescheduleForm(paymentId){

        const modalElem = jQuery('#request-pickup-modal')
        const modalElemContent = jQuery('#request-pickup-modal .kj-modal-content')
        const modalElemLoader = jQuery('#request-pickup-modal .kj-modal-loader')
        const modalElemErr = jQuery('#request-pickup-modal .kj-err-container')

        modalElem.removeClass('kj-hidden')
        modalElemLoader.removeClass('kj-hidden')
        modalElemContent.addClass('kj-hidden')
        modalElemErr.addClass('kj-hidden')

        jQuery.ajax({
            type: "post",
            url: ajaxRouteGenerator(),
            data: {
                action: "kj_get_shipping_process_detail",  // the action to fire in the server
                data: {
                    payment_id:paymentId
                },         // any JS object
            },
            complete: function (response) {
                const resp = JSON.parse(response.responseText).data;

                console.log(resp)

                if (resp?.status !== 200){
                    // formAlertToggler('menu_1',true,'Error',resp.message,'')
                    modalElemLoader.addClass('kj-hidden')
                    modalElemContent.addClass('kj-hidden')
                    modalElemErr.removeClass('kj-hidden')
                    return
                }
                modalElemLoader.addClass('kj-hidden')
                modalElemContent.removeClass('kj-hidden')
                modalElemErr.addClass('kj-hidden')
                

            },
        });
    }

</script>
