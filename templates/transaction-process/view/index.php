<div class="kj-wrapper kj-wrap">
    <div class="wrap ">
        <div id="root">
            <div class="woocommerce-layout">
                <div class="woocommerce-layout__header is-scrolled">
                    <div class="woocommerce-layout__header-wrapper">
                        <h1 data-wp-c16t="true" data-wp-component="Text" class="components-truncate components-text woocommerce-layout__header-heading css-wv5nn e19lxcc00">Transaction Process</h1>
                        <div style="padding-right: 40px">
                            <button onclick="kjRequestPickupSchedule()" class="button button-wp" type="button">
                                <div style="display: flex">
                                    <div style="margin: auto">
                                        <span>Request Pickup</span>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="woocommerce-layout__primary" id="woocommerce-layout__primary">
                    <div id="woocommerce-layout__notice-list" class="woocommerce-layout__notice-list"></div>
                    <div class="woocommerce-layout__main">

                        <div class="woocommerce-homescreen">
                            <div class="woocommerce-homescreen-column" style="position: static;max-width: 1600px; width: 100%">

                                <!--CONTENT-->
                                <form id="table-form" action="" style="display: none">
                                    <input type="text" name="page" value="<?php echo @$_GET['page']; ?>">
                                    <input type="text" name="cpage" value="1">
                                    <input type="text" name="key" value="<?php echo @$_GET['key']; ?>">
                                    <input type="text" name="month" value="<?php echo @$_GET['month']; ?>">
                                </form>


                                <div>
                                    <div class="container-fluid p-0">
                                        <div class="row">
                                            <div class="col">
                                                <!--Month Search-->
                                                <div style="display: flex;width: 100%; gap: 2px">
                                                    <select  style="width: 100%; max-width: 12.5rem" name="month_search" id="month_search_1">
                                                        <option selected="selected" value="" <?php echo (!@$_GET['month'] ? "selected" : "") ;?>>All Dates</option>
                                                        <?php
                                                        if (@$monthOptions && count($monthOptions)>0){
                                                            foreach ($monthOptions as $key => $value){
                                                                echo '<option value="'.$key.'" '.(@$_GET['month']===$key ? "selected" : "").'>'.$value.'</option>';
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                    <button class="button-wp-secondary" type="button" onclick="applySearch('month',document.getElementById('month_search_1').value)">
                                                        <div style="display: flex">
                                                            <div style="margin: auto">
                                                                <span>Apply</span>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <!--Key Search-->
                                                <div style="display: flex;justify-content: end;width: 100%; gap: 2px">
                                                    <input style="width: 100%; max-width: 12.5rem" name="key_search" type="search" class="input-text regular-input" placeholder="Order Number" value="<?php echo @$_GET['key']; ?>">
                                                    <button class="button-wp-secondary" type="button" onclick="applySearch('key',document.getElementsByName('key_search')[0].value)">
                                                        <div style="display: flex">
                                                            <div style="margin: auto">
                                                                <span>Search</span>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row-divider"></div>
                                    <table class="wp-list-table widefat fixed striped table-view-list posts">
                                        <thead>
                                        <tr>
                                            <th style="width: 4rem;" scope="col" class="manage-column column-thumb">
                                                <input style="margin: 0" type="checkbox" id="check_order_id_all_top">
                                            </th>
                                            <th scope="col" class="manage-column column-thumb">Order</th>
                                            <th scope="col" class="manage-column column-thumb">Date</th>
                                            <th scope="col" class="manage-column column-thumb">Status</th>
                                            <th scope="col" class="manage-column column-thumb">Billing</th>
                                            <th scope="col" class="manage-column column-thumb">Ship To</th>
                                            <th scope="col" class="manage-column column-thumb">Total</th>
                                        </tr>
                                        </thead>
                                        <tbody id="the-list">


                                            <?php
                                            if (@$results&&count($results)>0){
                                                foreach($results as $id => $row){
                                                    $shippingData = json_decode(@$row->shipping_info);
                                                    $shippingFee = (@$row->shipping_cost ?? 0) + (@$row->insurance_cost ?? 0);
                                                    if ((@$row->cod_fee ?? 0) > 0){
                                                        $shippingFee += (@$row->transaction_value ?? 0) + (@$row->cod_fee ?? 0);
                                                    }
                                                    echo '
                                                      <tr class="">
                                                        <td class="manage-column column-thumb">
                                                            <input type="checkbox" name="transaction_id[]" value="'.@$row->order_id.'">
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                        <a href="" style="font-weight: 700">#'.@$row->wc_order_id.' '.@$shippingData->_billing_first_name.' '.@$shippingData->_billing_last_name.' </a>
                                                        </td>
                                                        <td class="manage-column column-thumb">'.date('M d, Y',strtotime(@$row->wc_date_created)).'</td>
                                                        <td class="manage-column column-thumb">'.strtoupper(@$row->status).'</td>
                                                        <td class="manage-column column-thumb">
                                                            <div>'.@$shippingData->_billing_first_name.' '.@$shippingData->_billing_last_name.', '.@$shippingData->_billing_address_1.', '.@$shippingData->_shipping_address_2.', '.@$row->destination_sub_district.', '.@$shippingData->_billing_postcode.'</div>
                                                            <div style="position: relative; margin-top: .75rem"></div>
                                                            <div>via '.(@$row->service==="cod" ? "COD" : "NON COD").'</div>
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                            <div style="color: #2271b1;cursor: pointer" onclick="showTransactionSummaryModal(`'.@$row->wc_order_id.'`)">'.@$shippingData->_shipping_first_name.' '.@$shippingData->_shipping_last_name.', '.@$shippingData->_shipping_address_1.', '.@$shippingData->_shipping_address_2.', '.@$row->destination_sub_district.', '.@$shippingData->_shipping_postcode.'</div>
                                                            <div style="position: relative; margin-top: .75rem"></div>
                                                            <div>via '.strtoupper(@$row->service).'</div>
                                                            <div style="position: relative; margin-top: .1rem"></div>
                                                            <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <g opacity="0.6">
                                                                <path d="M5.3998 5.40005V1.80005H1.7998V5.40005H5.3998ZM10.1998 5.40005V1.80005H6.5998V5.40005H10.1998ZM5.3998 10.2V6.60005H1.7998V10.2H5.3998ZM10.1998 10.2V6.60005H6.5998V10.2H10.1998Z" fill="black"/>
                                                                </g>
                                                                </svg>
                                                                <span style="margin-left: .5rem">'.strtoupper(@$row->status).'</span>
                                                            </div>
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                            <p style="font-weight: 600">('.(@$shippingData->_payment_method==="cod" ? "COD" : "NON COD").') Rp'.localMoneyFormat($shippingFee).'</p>
                                                        </td>
                                                    </tr>
                                                    ';
                                                }
                                            }else{
                                                echo '<tr><td colspan="7" style="text-align: center" class="manage-column column-thumb">Not Found</td></tr>';
                                            }
                                            ?>
                                        
                                        </tbody>
                                        <tfoot>
                                        <tr>
                                            <th style="width: 4rem;" scope="col" class="manage-column column-thumb">
                                                <input style="margin: 0" type="checkbox" id="check_order_id_all_bottom">
                                            </th>
                                            <th scope="col" class="manage-column column-thumb">Order</th>
                                            <th scope="col" class="manage-column column-thumb">Date</th>
                                            <th scope="col" class="manage-column column-thumb">Status</th>
                                            <th scope="col" class="manage-column column-thumb">Billing</th>
                                            <th scope="col" class="manage-column column-thumb">Ship To</th>
                                            <th scope="col" class="manage-column column-thumb">Total</th>
                                        </tr>
                                        </tfoot>
                                    </table>

                                    <div class="row-divider"></div>
                                    <div class="container-fluid p-0">
                                        <div class="row">
                                            <div class="col">
                                                <!--Month Search-->
                                                <div style="display: flex;width: 100%; gap: 2px">
                                                    <select  style="width: 100%; max-width: 12.5rem" name="month_search_2" id="month_search_2">
                                                        <option selected="selected" value="" <?php echo (!@$_GET['month'] ? "selected" : "") ;?>>All Dates</option>
                                                        <?php
                                                        if (@$monthOptions && count($monthOptions)>0){
                                                            foreach ($monthOptions as $key => $value){
                                                                echo '<option value="'.$key.'" '.(@$_GET['month']===$key ? "selected" : "").'>'.$value.'</option>';
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                    <button class="button-wp-secondary" type="button" onclick="applySearch('month',document.getElementById('month_search_2').value)">
                                                        <div style="display: flex">
                                                            <div style="margin: auto">
                                                                <span>Apply</span>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row-divider"></div>
                                    <p style="font-weight: 500">KiriminAja Plugin v0.0.27</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="woocommerce-layout__footer">
                        <div class="components-snackbar-list woocommerce-transient-notices components-notices__snackbar"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'modal-request-pickup.php' ?>
    <?php include 'modal-detail.php' ?>
    
</div>

<!--Table Search-->
<script type="text/javascript">
    function applySearch (key,value){
        if (jQuery(`#table-form [name="${key}"]`).length > 0){
            jQuery(`#table-form [name="${key}"]`).val(value)
            jQuery(`#table-form`).trigger('submit')
        }
    }
</script>
<script>
    let orderIds = [];
    jQuery(document).on('change','#check_order_id_all_top, #check_order_id_all_bottom',function (){
        const is_checked = jQuery(this).prop('checked')
        jQuery('#check_order_id_all_top').prop('checked',is_checked)
        jQuery('#check_order_id_all_bottom').prop('checked',is_checked)
        jQuery('[name="transaction_id[]"]').prop('checked',is_checked)
    })
    function kjRequestPickupSchedule(){

        /** Reset orderIds*/
        orderIds = []
        jQuery('input[name="transaction_id[]"]:checked').each(function() {
            orderIds.push(jQuery(this).val());
        });

        if (orderIds.length === 0){
            alert('There is no selected transaction')
            return
        }
        
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
                action: "kj_request_pickup_schedule",  // the action to fire in the server
                data: {
                    order_ids:orderIds
                },         // any JS object
            },
            complete: function (response) {
                const resp = JSON.parse(response.responseText).data;
                
                if (resp?.status !== 200){
                    modalElemLoader.addClass('kj-hidden')
                    modalElemContent.addClass('kj-hidden')
                    modalElemErr.removeClass('kj-hidden')
                    alert(resp?.message ?? 'Terjadi kesalahan')
                    return
                }

                const schedules = resp?.data?.schedules ?? [];
                const transaction_summary = resp?.data?.transaction_summary ?? {};
                
                /** transaction_summary*/
                jQuery('#schedule-transaction-summary').empty()
                jQuery('#schedule-transaction-summary').append(`
                <div>
                    <div class="row">
                        <div class="col">Tagihan Paket COD</div>
                        <div class="col" style="text-align: right; font-weight: 700">Rp${kjMoneyFormat((transaction_summary?.sum_fee_cod ?? 0))}</div>
                    </div>
                    <div class="row-divider" style="margin-top: .5rem"></div>
                    <div class="row">
                        <div class="col">Tagihan Paket Non-COD</div>
                        <div class="col" style="text-align: right; font-weight: 700">Rp${kjMoneyFormat((transaction_summary?.sum_fee_non_cod ?? 0))}</div>
                    </div>
                    <div class="row-divider" style="margin-top: .5rem"></div>
                    <div class="row">
                        <div class="col">Total Tagihan</div>
                        <div class="col" style="text-align: right; font-weight: 700">Rp${kjMoneyFormat((transaction_summary?.sum_fee_non_cod ?? 0))}</div>
                    </div>
                </div>
                `)
                
                /** schedules*/
                
                jQuery('#schedule-opt-list').empty()
                jQuery.each(schedules,function (idx,schedule){
                    console.log(schedule)
                    jQuery('#schedule-opt-list').append(`
                        <div style="margin-bottom: .75rem">
                            <div style="display: flex;align-items: center;justify-items: center;">
                                <input id="opt_${schedule?.clock}" style="margin: 0" value="${schedule?.clock}" type="radio" name="schedule-opt">
                                <span style="margin-left: .5rem;margin-top: auto;margin-bottom: auto">
                                    <label for="opt_${schedule?.clock}">${schedule?.label}</label>                                
                                </span>
                            </div>
                        </div>
                `)
                })

                
                modalElemLoader.addClass('kj-hidden')
                modalElemContent.removeClass('kj-hidden')
                modalElemErr.addClass('kj-hidden')
            }
        })
        
    }
    function kjRequestPickupProcess(){
        jQuery('#request-pickup-modal .err_msg').addClass('kj-hidden')

        const modalElem = jQuery('#request-pickup-modal')
        const modalElemContent = jQuery('#request-pickup-modal .kj-modal-content')
        const modalElemLoader = jQuery('#request-pickup-modal .kj-modal-loader')
        const modalElemErr = jQuery('#request-pickup-modal .kj-err-container')

        modalElemLoader.removeClass('kj-hidden')
        modalElemContent.addClass('kj-hidden')
        modalElemErr.addClass('kj-hidden')

        jQuery.ajax({
            type: "post",
            url: ajaxRouteGenerator(),
            data: {
                action: "kj_request_pickup_transaction",  // the action to fire in the server
                data: {
                    schedule : jQuery('[name="schedule-opt"]:checked').val(),
                    order_ids : orderIds
                },         // any JS object
            },
            complete: function (response) {

    
                
                const resp = JSON.parse(response.responseText).data;
                console.log(resp)
                if (resp?.status !== 200){

                    modalElemLoader.addClass('kj-hidden')
                    modalElemErr.addClass('kj-hidden')
                    modalElemContent.removeClass('kj-hidden')
                    
                    jQuery('#request-pickup-modal .err_msg').text('*'+resp?.message)
                    jQuery('#request-pickup-modal .err_msg').removeClass('kj-hidden')
                    return
                }

                window.location.href = `<?php echo @home_url().'/wp-admin/admin.php?page=kiriminaja-request-pickup'; ?>&pickup_number=${resp?.data?.pickup_number}`;
                
                
            }
        })
    }

    let lastwcOrderIdForshowTransactionSummaryModal = 0;
    function showTransactionSummaryModalRefresh(){
        showTransactionSummaryModal(lastwcOrderIdForshowTransactionSummaryModal)
    }
    function showTransactionSummaryModal(wcOrderId){
        lastwcOrderIdForshowTransactionSummaryModal = wcOrderId;
        const modalElem = jQuery('#transaction-detail-modal')
        const modalElemContent = jQuery('#transaction-detail-modal .kj-modal-content')
        const modalElemLoader = jQuery('#transaction-detail-modal .kj-modal-loader')
        const modalElemErr = jQuery('#transaction-detail-modal .kj-err-container')

        modalElem.removeClass('kj-hidden')
        modalElemLoader.removeClass('kj-hidden')
        modalElemContent.addClass('kj-hidden')
        modalElemErr.addClass('kj-hidden')

        jQuery.ajax({
            type: "post",
            url: ajaxRouteGenerator(),
            data: {
                action: "kj_transaction-detail-summary",  // the action to fire in the server
                data:{
                    'wc_order_id' : wcOrderId 
                } ,         // any JS object
            },
            complete: function (response) {
                const resp = JSON.parse(response.responseText).data;

                console.log(resp)
                if (resp?.status !== 200){
                    modalElemLoader.addClass('kj-hidden')
                    modalElemContent.addClass('kj-hidden')
                    modalElemErr.removeClass('kj-hidden')
                    return
                }

                jQuery('#transaction-detail-modal .wc-order-id').text(wcOrderId)


                jQuery('#transaction-detail-modal .kj-modal-content').empty()
                jQuery('#transaction-detail-modal .kj-modal-content').append(`
                <div>
                    <div style="padding: 0 15px 0 15px;">
                        <!--1 row-->
                        <div class="row-divider" style="margin-top: .75rem"></div>
                        <div class="row gx-2">
                            <div class="col">
                                <div style="font-weight: 700">Billing Details</div>
                                <div class="row-divider" style="margin-top: .25rem"></div>
                                <div>Bima Daniel, Jalan jalan ke Yogyakarta, Rumah Ungu, Kec. Banguntapan, Kabupaten Bantul, DI Yogyakarta, 55198</div>
                            </div>
                            <div class="col">
                                <div style="font-weight: 700">Shipping Details</div>
                                <div class="row-divider" style="margin-top: .25rem"></div>
                                <div>Bima Daniel, Jalan jalan ke Yogyakarta, Rumah Ungu, Kec. Banguntapan, Kabupaten Bantul, DI Yogyakarta, 55198</div>
                            </div>
                        </div>
                        <!--2 row-->
                        <div class="row-divider" style="margin-top: .75rem"></div>
                        <div class="row gx-2">
                            <div class="col">
                                <div>
                                    <div style="font-weight: 700">Email</div>
                                    <div class="row-divider" style="margin-top: .25rem"></div>
                                    <div>coba@kiriminaja.com</div>
                                </div>
        
                                <div class="row-divider" style="margin-top: .75rem"></div>
                                <div>
                                    <div style="font-weight: 700">Phone</div>
                                    <div class="row-divider" style="margin-top: .25rem"></div>
                                    <div>085156722807</div>
                                </div>
        
                                <div class="row-divider" style="margin-top: .75rem"></div>
                                <div>
                                    <div style="font-weight: 700">Payment via</div>
                                    <div class="row-divider" style="margin-top: .25rem"></div>
                                    <div>Transfer</div>
                                </div>
                            </div>
                            <div class="col">
                                <div style="font-weight: 700">Shipping Method</div>
                                <div class="row-divider" style="margin-top: .25rem"></div>
                                <div>ID Express Standard</div>
                            </div>
                        </div>
                    </div>
                    <!--3 row-->
                    <div class="row-divider"></div>
                    <div>
                        <table id="cart-table">
                            <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td>Product</td>
                                <td>Quantity</td>
                                <td>Total</td>
                            </tr>
                            </tbody>
                            <tfoot>
                            <tr>
                                <th colspan="2">Sub Total</th>
                                <th>Rp.10.000</th>
                            </tr>
                            <tr>
                                <th colspan="2">Shipping Fee</th>
                                <th>Rp.10.000</th>
                            </tr>
                            <tr>
                                <th colspan="2">COD Fee</th>
                                <th>Rp.10.000</th>
                            </tr>
                            <tr>
                                <th colspan="2">Insurance Fee</th>
                                <th>Rp.10.000</th>
                            </tr>
                            <tr>
                                <th colspan="2">Total</th>
                                <th>Rp.10.000</th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
        `)

                modalElemLoader.addClass('kj-hidden')
                modalElemContent.removeClass('kj-hidden')
            }
        })
        

        
    }
</script>