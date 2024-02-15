<div class="kj-wrapper kj-wrap">
    <div class="wrap ">
        <div id="root">
            <div class="woocommerce-layout">
                <div class="woocommerce-layout__header is-scrolled">
                    <div class="woocommerce-layout__header-wrapper">
                        <h1 data-wp-c16t="true" data-wp-component="Text" class="components-truncate components-text woocommerce-layout__header-heading css-wv5nn e19lxcc00">Shipment Process</h1>
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
                                    <input type="text" name="status" value="<?php echo @$_GET['status']; ?>">
                                    <input type="text" name="month" value="<?php echo @$_GET['month']; ?>">
                                </form>
                                
                                
                                <div>
                                    <div style="display: inline-block">
                                        <ul class="subsubsub">
                                            <li ><a href="#" onclick="applySearch('status','')" <?php echo !@$_GET['status']||@$_GET['status']==='all' ? 'class="current"' : ''; ?> >Semua <span class="count">(1)</span></a> |</li>
                                            <!--<li ><a href="#" onclick="applySearch('status','process')" <?php /*echo @$_GET['status']==='process' ? 'class="current"' : ''; */?> >Diproses <span class="count">(1)</span></a>  |</li>-->
                                            <li ><a href="#" onclick="applySearch('status','unpaid')" <?php echo @$_GET['status']==='unpaid' ? 'class="current"' : ''; ?> >Waiting for Payment <span class="count">(1)</span></a>  |</li>
                                            <li ><a href="#" onclick="applySearch('status','paid')" <?php echo @$_GET['status']==='paid' ? 'class="current"' : ''; ?> >Paid <span class="count">(1)</span></a>  |</li>
                                            <!--<li ><a href="#" onclick="applySearch('status','cancel')" <?php /*echo @$_GET['status']==='cancel' ? 'class="current"' : ''; */?> >Cancel <span class="count">(1)</span></a></li>-->
                                        </ul>
                                    </div>
                                    
                                    <div class="row-divider"></div>
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
                                                    <input style="width: 100%; max-width: 12.5rem" name="key_search" type="search" class="input-text regular-input" placeholder="Search Payment" value="<?php echo @$_GET['key']; ?>">
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
                                            <th style="width: 4rem;" scope="col" class="manage-column column-thumb">No</th>
                                            <th scope="col" class="manage-column column-thumb">Pickup Number</th>
                                            <th scope="col" class="manage-column column-thumb">Scedule</th>
                                            <th scope="col" class="manage-column column-thumb">Fees</th>
                                            <th scope="col" class="manage-column column-thumb">Orders</th>
                                            <th scope="col" class="manage-column column-thumb">Status</th>
                                            <th scope="col" class="manage-column column-thumb"><span style="float: right">Action</span></th>
                                        </tr>
                                        </thead>
                                        <tbody id="the-list">
                                        <?php
                                        if (@$results&&count($results)>0){
                                            foreach($results as $id => $row){
                                                $btnGroup='';
                                            

                                                $statusContent= '
                                                                    <div class="kj-badge warning">
                                                                        <span>Waiting For Payment</span>
                                                                    </div>
                                                                    ';
                                                if (@$row->status==="paid"){
                                                    if (strtotime(@$row->pickup_schedule)>strtotime("now")){
                                                        $btnGroup.='
                                                        <button class="button-wp" type="button" onclick="showPaymentForm(`'.@$row->pickup_number.'`)">
                                                                <div style="display: flex">
                                                                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                        <svg style="position: relative; top: 1px" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                            <path d="M8.47961 7.20001C8.15961 7.12001 7.83961 6.96001 7.59961 6.72001C7.35961 6.64001 7.27961 6.40001 7.27961 6.24001C7.27961 6.08001 7.35961 5.84001 7.51961 5.76001C7.75961 5.60001 7.99961 5.44001 8.23961 5.52001C8.71961 5.52001 9.11961 5.76001 9.35961 6.08001L10.0796 5.12001C9.83961 4.88001 9.59961 4.72001 9.35961 4.56001C9.11961 4.40001 8.79961 4.32001 8.47961 4.32001V3.20001H7.51961V4.32001C7.11961 4.40001 6.71961 4.64001 6.39961 4.96001C6.07961 5.36001 5.83961 5.84001 5.91961 6.32001C5.91961 6.80001 6.07961 7.28001 6.39961 7.60001C6.79961 8.00001 7.35961 8.24001 7.83961 8.48001C8.07961 8.56001 8.39961 8.72001 8.63961 8.88001C8.79961 9.04001 8.87961 9.28001 8.87961 9.52001C8.87961 9.76001 8.79961 10 8.63961 10.24C8.39961 10.48 8.07961 10.56 7.83961 10.56C7.51961 10.56 7.11961 10.48 6.87961 10.24C6.63961 10.08 6.39961 9.84001 6.23961 9.60001L5.43961 10.48C5.67961 10.8 5.91961 11.04 6.23961 11.28C6.63961 11.52 7.11961 11.76 7.59961 11.76V12.8H8.47961V11.6C8.95961 11.52 9.35961 11.28 9.67961 10.96C10.0796 10.56 10.3196 9.92001 10.3196 9.36001C10.3196 8.88001 10.1596 8.32001 9.75961 8.00001C9.35961 7.60001 8.95961 7.36001 8.47961 7.20001ZM7.99961 1.60001C4.47961 1.60001 1.59961 4.48001 1.59961 8.00001C1.59961 11.52 4.47961 14.4 7.99961 14.4C11.5196 14.4 14.3996 11.52 14.3996 8.00001C14.3996 4.48001 11.5196 1.60001 7.99961 1.60001ZM7.99961 13.52C4.95961 13.52 2.47961 11.04 2.47961 8.00001C2.47961 4.96001 4.95961 2.48001 7.99961 2.48001C11.0396 2.48001 13.5196 4.96001 13.5196 8.00001C13.5196 11.04 11.0396 13.52 7.99961 13.52Z" fill="white"/>
                                                                        </svg>
                                                                        <span style="margin-left: 6px">Payment</span>
                                                                    </div>
                                                                </div>
                                                            </button>
                                                        ';                                                        
                                                    }else{
                                                        $btnGroup.= '
                                                        <button class="button-wp" type="button" onclick="showRescheduleForm(`'.@$row->pickup_number.'`)">
                                                                <div style="display: flex">
                                                                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                        <span>Reschedule</span>
                                                                    </div>
                                                                </div>
                                                        </button>
                                                        ';    
                                                    }

                                                    
//                                                  $btnGroup.='<button name="save" style="background-color: #ad0000; border: 1px solid #ad0000" class="button-primary woocommerce-save-button" type="button" >Batalkan</button>';
                                                    $statusContent= '
                                                                    <div class="kj-badge success">
                                                                        <span>Paid</span>
                                                                    </div>
                                                                    ';
                                                }
                                                $btnGroup.='
                                                            <button class="button-wp-secondary" type="button" onclick="showDetail(`'.@$row->pickup_number.'`)">
                                                                <div style="display: flex">
                                                                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                       <span>Details</span>
                                                                    </div>
                                                                </div>
                                                            </button>
                                                ';

                                                echo '
                                                <tr class="">
                                                    <td style="font-weight: 700;" class="thumb column-thumb">'.$id+(($page-1)*$items_per_page+1).'</td>
                                                    <td class="manage-column column-thumb">
                                                        <div style="font-weight: 700">'.@$row->pickup_number.'</div>
                                                        <div style="font-size: 12px;">Requested: '.date('Y/m/d H:i',strtotime(@$row->created_at)).'</div>
                                                    </td>
                                                    <td class="manage-column column-thumb">'.date('Y/m/d H:i',strtotime(@$row->pickup_schedule)).'</td>
                                                    <td class="manage-column column-thumb">
                                                        <div style="font-weight: 700">Rp. '.localMoneyFormat(@$row->cost ?? 0).'</div>
                                                    </td>
                                                    <td class="manage-column column-thumb">'.@$row->order_amt.' Order</td>
                                                    <td class="manage-column column-thumb">'.$statusContent.'</td>
                                                    <td class="manage-column column-thumb">
                                                        <div style="display: flex;justify-content: end;gap: 4px; flex-wrap: wrap">'.$btnGroup.'</div>
                                                    </td>
                                                </tr>
                                                ';

                                            }
                                        }else{
                                            echo '<td colspan="7" style="text-align: center" class="manage-column column-thumb">Not Found</td>';
                                        }
                                        ?>
                                        </tbody>
                                        <tfoot>
                                        <tr>
                                            <th style="width: 4rem;" scope="col" class="manage-column column-thumb">No</th>
                                            <th scope="col" class="manage-column column-thumb">Pickup Number</th>
                                            <th scope="col" class="manage-column column-thumb">Scedule</th>
                                            <th scope="col" class="manage-column column-thumb">Fees</th>
                                            <th scope="col" class="manage-column column-thumb">Orders</th>
                                            <th scope="col" class="manage-column column-thumb">Status</th>
                                            <th scope="col" class="manage-column column-thumb"><span style="float: right">Action</span></th>
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
                                                <!--Pagination-->
                                                <div style="display: flex;justify-content: end;align-items: center;justify-items: center;gap: 6px">
                                                    <span style="font-weight: 700;"><?php echo count($results) ?> items</span>
                                                    <div>
                                                        <button <?php echo @$prev_page_link!='' ? '' : 'disabled'; ?> style="position: relative" class="button-wp-blank" type="button">
                                                            <?php echo @$prev_page_link!='' ? '<a href="'.$prev_page_link.'" class="inset-absolute"></a>' : ''; ?>
                                                            <div style="display: flex">
                                                                <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                        <path d="M11.1998 4L7.1998 8L11.1998 12L10.3998 13.6L4.7998 8L10.3998 2.4L11.1998 4Z" fill="black"/>
                                                                    </svg>
                                                                </div>
                                                            </div>
                                                        </button>
                                                    </div>
                                                    <span style="font-weight: 700;"> <?php echo $page; ?> of <?php echo $total_pages; ?> </span>
                                                    <div>
                                                        <button <?php echo @$next_page_link!='' ? '' : 'disabled'; ?> style="position: relative" class="button-wp-blank" type="button">
                                                            <?php echo @$next_page_link!='' ? '<a href="'.$next_page_link.'" class="inset-absolute"></a>' : ''; ?>

                                                            <div style="display: flex">
                                                                <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                        <path d="M4.7998 12L8.7998 8L4.7998 4L5.5998 2.4L11.1998 8L5.5998 13.6L4.7998 12Z" fill="black"/>
                                                                    </svg>
                                                                </div>
                                                            </div>
                                                        </button>
                                                    </div>
                                                </div>
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

    <?php include 'modal-detail.php' ?>
    <?php include 'modal-payment.php' ?>
    <?php include 'modal-request-pickup.php' ?>
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
                //
                // const payment_data = resp?.data?.payment_data
                // const transactions_data = resp?.data?.transactions_data
                //
                // console.log(payment_data)
                // console.log(transactions_data)
                //
                // jQuery('#request-pickup-detail-modal #detail-pickup-number').text(payment_data?.pickup_number)
                // jQuery('#request-pickup-detail-modal #detail-status').text(payment_data?.status)
                // jQuery('#request-pickup-detail-modal #detail-non_cod_count').text(kjMoneyFormat(payment_data?.non_cod_count))
                // jQuery('#request-pickup-detail-modal #detail-non_cod_sum').text(kjMoneyFormat(payment_data?.non_cod_sum,'Rp. '))
                // jQuery('#request-pickup-detail-modal #detail-cod_count').text(kjMoneyFormat(payment_data?.cod_count))
                // jQuery('#request-pickup-detail-modal #detail-cod_sum').text(kjMoneyFormat(payment_data?.cod_sum,'Rp. '))
                // jQuery('#request-pickup-detail-modal #detail-payment_amount').text(kjMoneyFormat(payment_data?.payment_amount,'Rp. '))
                //
                //
                // jQuery('#request-pickup-detail-modal #the-list').empty()
                // transactions_data.forEach(function (transaction){
                //
                //     let ongkirCalc = 0;
                //     ongkirCalc+=Number(transaction?.insurance_cost ?? 0)
                //     ongkirCalc+=Number(transaction?.shipping_cost ?? 0)
                //     if(Number(transaction?.cod_fee)>0){
                //         ongkirCalc+=Number(transaction?.transaction_value ?? 0)
                //         ongkirCalc+=Number(transaction?.cod_fee ?? 0)
                //     }
                //
                //     jQuery('#request-pickup-detail-modal #the-list').append(`
                //     <tr class="">
                //         <td class="">
                //             <input style="margin: 0" value="${transaction?.order_id}" type="checkbox" name="req_pickup_ids[]" id="in-product_cat-15">
                //         </td>
                //         <td class="">${transaction?.order_id}</td>
                //         <td class="">${String(transaction?.awb)!=='null' ? transaction?.awb : '-'}</td>
                //         <td class="">
                //         ${transaction?.cod_fee>0 ? 'COD' : 'NON COD'}
                //         <br>
                //         ${kjMoneyFormat(ongkirCalc,'Rp. ')}
                //         </td>
                //         <td class="">
                //             <div style="float: right">
                //                 <button name="save" class="button-primary woocommerce-save-button" type="button">Transaction Detail</button>
                //             </div>
                //         </td>
                //     </tr>
                //     `)
                // })


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
