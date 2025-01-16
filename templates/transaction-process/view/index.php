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
                            <div class="woocommerce-homescreen-column" style="position: static;width: 100%">

                                <!--CONTENT-->
                                <form id="table-form" action="" style="display: none">
                                    <input type="text" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                                    <input type="text" name="cpage" value="1">
                                    <input type="text" name="key" value="<?php echo esc_attr($_GET['key']) ?? ''; ?>">
                                    <input type="text" name="month" value="<?php echo esc_attr($_GET['month']) ?? ''; ?>">
                                </form>


                                <div>

                                    <div style="padding-left: 5px; background-color: #2271b1;">
                                        <div style="padding: 12px; border: 1px solid #c3c4c7; background-color: white">
                                            <div style="display:flex;">
                                                <div>
                                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M1 10C1.41 10.29 1.96 10.43 2.5 10.43C3.05 10.43 3.59 10.29 4 10C4.62 9.54 5 8.83 5 8C5 8.83 5.37 9.54 6 10C6.41 10.29 6.96 10.43 7.5 10.43C8.05 10.43 8.59 10.29 9 10C9.62 9.54 10 8.83 10 8C10 8.83 10.37 9.54 11 10C11.41 10.29 11.96 10.43 12.51 10.43C13.05 10.43 13.59 10.29 14 10C14.62 9.54 15 8.83 15 8C15 8.83 15.37 9.54 16 10C16.41 10.29 16.96 10.43 17.5 10.43C18.05 10.43 18.59 10.29 19 10C19.63 9.54 20 8.83 20 8V7L17 0H4L0 7V8C0 8.83 0.37 9.54 1 10ZM3 18.99H8V13.99H12V18.99H17V11.99C16.63 11.94 16.28 11.77 16 11.56C15.37 11.11 15 10.83 15 10C15 10.83 14.62 11.11 14 11.56C13.59 11.86 13.05 11.99 12.51 12C11.96 12 11.41 11.86 11 11.56C10.37 11.11 10 10.83 10 10C10 10.83 9.62 11.11 9 11.56C8.59 11.86 8.05 11.99 7.5 12C6.96 12 6.41 11.86 6 11.56C5.37 11.11 5 10.83 5 9.99C5 10.83 4.62 11.11 4 11.56C3.71 11.77 3.37 11.94 3 12V18.99Z" fill="black"/>
                                                    </svg>
                                                </div>
                                                <div style="margin-left: 8px">
                                                    <div style="font-weight: 600; font-size: 16px;">
                                                        Note
                                                    </div>
                                                    <div class="row-divider" style="margin-top: .5rem"></div>
                                                    <div style="font-weight: 500;">
                                                        - <?php echo esc_html( kjHelper()->tlThis('Recent transaction / order with <u>processing</u> status may not shown here immidiately. If this happen please wait for 30 seconds and refresh the page.',$locale) ); ?>
                                                        <br>
                                                        - <?php echo esc_html( kjHelper()->tlThis('Only transaction / order with billing region is Indonesia can be shown here.',$locale) ); ?>
                                                        <br>
                                                        - <?php echo esc_html( kjHelper()->tlThis('Only transaction / order which has not been request pickuped can be shown here.',$locale) ); ?>
                                                        <br>
                                                        - <?php echo esc_html( kjHelper()->tlThis('Only transaction / order which created when KiriminAja plugin is installed and activated can appear here.',$locale) ); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row-divider"></div>
                                    
                                    <div class="container-fluid p-0">
                                        <div class="row">
                                            <div class="col">
                                                <!--Month Search-->
                                                <div style="display: flex;width: 100%; gap: 2px">
                                                    <select  style="width: 100%; max-width: 12.5rem" name="month_search" id="month_search_1">
                                                        <option selected="selected" value="" <?php echo (!isset($_GET['month']) ? "selected" : "") ;?>>All Dates</option>
                                                        <?php
                                                        if (@$monthOptions && count($monthOptions)>0){
                                                            foreach ($monthOptions as $key => $value){
                                                                echo '<option value="'.esc_attr($key).'" '.(isset($_GET['month']) ? esc_html($_GET['month'])===$key ? "selected" : "" : "").'>'.esc_html($value).'</option>';
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
                                                    <input style="width: 100%; max-width: 12.5rem" name="key_search" type="search" class="input-text regular-input" placeholder="Order Number" value="<?php echo esc_attr($_GET['key']); ?>">
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
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kjHelper()->tlThis('Order',$locale) ); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kjHelper()->tlThis('Date',$locale) ); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kjHelper()->tlThis('Status',$locale) ); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kjHelper()->tlThis('Billing',$locale) ); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kjHelper()->tlThis('Ship To',$locale) ); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kjHelper()->tlThis('Total',$locale) ); ?></th>
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
                                                            <input type="checkbox" name="transaction_id[]" value="'.esc_attr($row->order_id).'">
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                        <a href="'.(esc_url(home_url()).'/wp-admin/post.php?post='.esc_attr($row->wc_order_id).'&action=edit').'" target="_blank" style="font-weight: 700">#'.esc_html($row->wc_order_id).' '.esc_html($shippingData->_billing_first_name).' '.esc_html($shippingData->_billing_last_name).' </a>
                                                        </td>
                                                        <td class="manage-column column-thumb">'.esc_html(date('M d, Y',strtotime($row->wc_date_created)) ).'</td>
                                                        <td class="manage-column column-thumb">
                                                        <span class="kj-badge processing">'.esc_html( kjHelper()->transactionStatusLabel($row->status)).'</span>
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                            <div>'.esc_html($shippingData->_billing_first_name.' '.$shippingData->_billing_last_name.', '.$shippingData->_billing_address_1.', '.$shippingData->_billing_address_2.', '.$row->destination_sub_district.', '.$shippingData->_billing_postcode).'</div>
                                                            <div style="position: relative; margin-top: .75rem"></div>
                                                            <div>via '.(@$shippingData->_payment_method==="cod" ? "COD" : "NON COD").'</div>
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                            <div style="color: #2271b1;cursor: pointer" onclick="showTransactionSummaryModal(`'.esc_html($row->wc_order_id).'`)">'.esc_html( ($shippingData->_shipping_first_name ?? $shippingData->_billing_first_name).' '.($shippingData->_shipping_last_name ?? $shippingData->_billing_last_name).', '.($shippingData->_shipping_address_1 ?? $shippingData->_billing_address_1).', '.($shippingData->_shipping_address_2 ?? $shippingData->_billing_address_2).', '.$row->destination_sub_district.', '.($shippingData->_shipping_postcode ?? $shippingData->_billing_postcode) ).'</div>
                                                            <div style="position: relative; margin-top: .75rem"></div>
                                                            <div>via '.esc_html(strtoupper($row->service)).'</div>
                                                            <div style="position: relative; margin-top: .1rem"></div>
                                                            <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <g opacity="0.6">
                                                                <path d="M5.3998 5.40005V1.80005H1.7998V5.40005H5.3998ZM10.1998 5.40005V1.80005H6.5998V5.40005H10.1998ZM5.3998 10.2V6.60005H1.7998V10.2H5.3998ZM10.1998 10.2V6.60005H6.5998V10.2H10.1998Z" fill="black"/>
                                                                </g>
                                                                </svg>
                                                                <span style="margin-left: .5rem">'.esc_html(strtoupper($row->status)).'</span>
                                                            </div>
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                            <p style="font-weight: 600">('.(@$shippingData->_payment_method==="cod" ? "COD" : "NON COD").') Rp'.esc_html(localMoneyFormat($shippingFee)).'</p>
                                                        </td>
                                                    </tr>
                                                    ';
                                                }
                                            }else{
                                                echo '<tr><td colspan="7" style="text-align: center" class="manage-column column-thumb">'.esc_html(kjHelper()->tlThis('Not Found',$locale)).'</td></tr>';
                                            }
                                            ?>
                                        
                                        </tbody>
                                        <tfoot>
                                        <tr>
                                            <th style="width: 4rem;" scope="col" class="manage-column column-thumb">
                                                <input style="margin: 0" type="checkbox" id="check_order_id_all_bottom">
                                            </th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kjHelper()->tlThis('Order',$locale)); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kjHelper()->tlThis('Date',$locale) ); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kjHelper()->tlThis('Status',$locale) ); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kjHelper()->tlThis('Billing',$locale) ); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kjHelper()->tlThis('Ship To',$locale) ); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kjHelper()->tlThis('Total',$locale) ); ?></th>
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
                                                                echo '<option value="'.esc_attr($key).'" '.(@$_GET['month']===$key ? "selected" : "").'>'.esc_html($value).'</option>';
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
                                    <p style="font-weight: 500">KiriminAja Plugin v.<?php echo esc_html(KJ_VERSION_PLUGIN); ?></p>
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
                const sum_cod_fee = transaction_summary?.sum_fee_cod ?? 0;
                const sum_non_cod_fee = transaction_summary?.sum_fee_non_cod ?? 0;
                const total = sum_non_cod_fee;

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
                        <div class="col" style="text-align: right; font-weight: 700">Rp${kjMoneyFormat((sum_cod_fee))}</div>
                    </div>
                </div>
                `)
                
                /** schedules*/
                
                jQuery('#schedule-opt-list').empty()
                jQuery.each(schedules,function (idx,schedule){
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
                /** Reset Err*/
                jQuery('#request-pickup-modal .err_msg').empty()
                jQuery('#request-pickup-modal .err_msg').addClass('kj-hidden')
    
                
                const resp = JSON.parse(response.responseText).data;
                if (resp?.status !== 200){

                    modalElemLoader.addClass('kj-hidden')
                    modalElemErr.addClass('kj-hidden')
                    modalElemContent.removeClass('kj-hidden')
                    
                    jQuery('#request-pickup-modal .err_msg').text('*'+resp?.message)
                    jQuery('#request-pickup-modal .err_msg').removeClass('kj-hidden')
                    return
                }

                window.location.href = `<?php echo esc_url(home_url()).'/wp-admin/admin.php?page=kiriminaja-request-pickup'; ?>&pickup_number=${resp?.data?.pickup_number}`;
                
                
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

        /** Show Modal & show loader*/
        modalElem.removeClass('kj-hidden')
        modalElemLoader.removeClass('kj-hidden')
        modalElemContent.addClass('kj-hidden')
        modalElemErr.addClass('kj-hidden')

        /** Status*/
        jQuery('#transaction-detail-modal .status-container').empty()

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

                if (resp?.status !== 200){
                    /** Hide loader & SHow Err*/
                    modalElemLoader.addClass('kj-hidden')
                    modalElemContent.addClass('kj-hidden')
                    modalElemErr.removeClass('kj-hidden')
                    return
                }
                
                let checkout_data       = resp?.data?.checkout_data
                let cart_data           = resp?.data?.cart_data
                let transaction_data    = resp?.data?.transaction_data

                /** Add transaction number to modal*/
                jQuery('#transaction-detail-modal .wc-order-id').text(wcOrderId)

                /** Empty and add content*/
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
                                <div>${checkout_data?._billing_first_name} ${checkout_data?._billing_last_name}, ${checkout_data?._billing_address_1} ${checkout_data?._billing_address_2}, ${transaction_data?.destination_sub_district}, ${checkout_data?._billing_postcode}</div>
                            </div>
                            <div class="col">
                                <div style="font-weight: 700">Shipping Details</div>
                                <div class="row-divider" style="margin-top: .25rem"></div>
                                <div>${checkout_data?._shipping_first_name ?? checkout_data?._billing_first_name} ${checkout_data?._shipping_last_name ?? checkout_data?._billing_last_name}, ${checkout_data?._shipping_address_1 ?? checkout_data?._billing_address_1} ${checkout_data?._shipping_address_2 ?? checkout_data?._billing_address_2}, ${transaction_data?.destination_sub_district}, ${checkout_data?._shipping_postcode ?? checkout_data?._billing_postcode}</div>
                            </div>
                        </div>
                        <!--2 row-->
                        <div class="row-divider" style="margin-top: .75rem"></div>
                        <div class="row gx-2">
                            <div class="col">
                                <div>
                                    <div style="font-weight: 700">Email</div>
                                    <div class="row-divider" style="margin-top: .25rem"></div>
                                    <div>${checkout_data?._billing_email}</div>
                                </div>
        
                                <div class="row-divider" style="margin-top: .75rem"></div>
                                <div>
                                    <div style="font-weight: 700">Phone</div>
                                    <div class="row-divider" style="margin-top: .25rem"></div>
                                    <div>${checkout_data?._billing_phone}</div>
                                </div>
        
                                <div class="row-divider" style="margin-top: .75rem"></div>
                                <div>
                                    <div style="font-weight: 700">Payment via</div>
                                    <div class="row-divider" style="margin-top: .25rem"></div>
                                    <div>${resp?.data?.payment}</div>
                                </div>
                            </div>
                            <div class="col">
                                <div style="font-weight: 700">Shipping Method</div>
                                <div class="row-divider" style="margin-top: .25rem"></div>
                                <div>${resp?.data?.expedition_service}</div>
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
                                <th>Rp.${kjMoneyFormat(transaction_data?.transaction_value ?? 0)}</th>
                            </tr>
                            <tr>
                                <th colspan="2">Shipping Fee</th>
                                <th>Rp.${kjMoneyFormat(transaction_data?.shipping_cost ?? 0)}</th>
                            </tr>
                            `
                            +
                            (
                            transaction_data?.cod_fee > 0 ? 
                            `
                            <tr>
                            <th colspan="2">COD Fee</th>
                            <th>Rp.${kjMoneyFormat(transaction_data?.cod_fee ?? 0)}</th>
                            </tr>` 
                            : 
                            '')
                            +
                            (
                            transaction_data?.insurance_cost > 0 ?
                            `
                            <tr>
                            <th colspan="2">Insurance Fee</th>
                            <th>Rp.${kjMoneyFormat(transaction_data?.insurance_cost ?? 0)}</th>
                            </tr>`
                            :
                            '')
                            +
                            `
                            <tr>
                                <th colspan="2">Total</th>
                                <th>Rp.${kjMoneyFormat(
                                (
                                    parseInt(transaction_data?.transaction_value ?? 0)
                                    +
                                    parseInt(transaction_data?.shipping_cost ?? 0)
                                    +
                                    parseInt(transaction_data?.cod_fee ?? 0)
                                    +
                                    parseInt(transaction_data?.insurance_cost ?? 0)
                                )
                            )}</th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                `)
                
                /** Status*/
                jQuery('#transaction-detail-modal .status-container').empty()
                jQuery('#transaction-detail-modal .status-container').append(`<span class="${resp?.data?.status_classes}">${resp?.data?.status_label}</span>`)
                

                /**emptying and add the cart table list*/
                jQuery('#transaction-detail-modal .kj-modal-content #cart-table tbody').empty()
                
                jQuery.each(cart_data,function (index, obj){
                    jQuery('#transaction-detail-modal .kj-modal-content #cart-table tbody').append(`
                    <tr>
                        <td>${obj?.product_name}</td>
                        <td>${kjMoneyFormat(obj?.product_qty ?? 0)}</td>
                        <td>Rp.${kjMoneyFormat(obj?.product_gross_revenue ?? 0)}</td>
                    </tr>
                    `)
                })
                
                /** Show Modal*/
                modalElemLoader.addClass('kj-hidden')
                modalElemContent.removeClass('kj-hidden')
            }
        })
        

        
    }
</script>