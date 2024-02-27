<div class="kj-wrapper kj-wrap">
    <div class="wrap ">
        <div id="root">
            <div class="woocommerce-layout">
                <div class="woocommerce-layout__header is-scrolled">
                    <div class="woocommerce-layout__header-wrapper">
                        <h1 data-wp-c16t="true" data-wp-component="Text" class="components-truncate components-text woocommerce-layout__header-heading css-wv5nn e19lxcc00">Transaction Process</h1>
                        <div style="padding-right: 40px">
                            <button onclick="kjRequestPickup()" class="button button-wp" type="button">
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
                                    <input type="text" name="status" value="<?php echo @$_GET['status']; ?>">
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
                                                    $shippingData = json_decode($row->shipping_info);
                                                    $shippingFee = (@$row->shipping_cost ?? 0) + (@$row->insurance_cost ?? 0);
                                                    if ((@$row->cod_fee ?? 0) > 0){
                                                        $shippingFee += (@$row->transaction_value ?? 0) + (@$row->cod_fee ?? 0);
                                                    }
                                                    echo '
                                                      <tr class="">
                                                        <td class="manage-column column-thumb">
                                                            <input type="checkbox" name="transaction_id[]" value="'.$row->id.'">
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                        <a href="" style="font-weight: 700">#'.$row->wc_order_id.' '.$shippingData->_billing_first_name.' '.$shippingData->_billing_last_name.' </a>
                                                        </td>
                                                        <td class="manage-column column-thumb">'.date('M d, Y',strtotime($row->wc_date_created)).'</td>
                                                        <td class="manage-column column-thumb">'.strtoupper($row->status).'</td>
                                                        <td class="manage-column column-thumb">
                                                            <div>'.$shippingData->_billing_first_name.' '.$shippingData->_billing_last_name.', '.$shippingData->_billing_address_1.', '.$shippingData->_shipping_address_2.', '.$row->destination_sub_district.', '.$shippingData->_billing_postcode.'</div>
                                                            <div style="position: relative; margin-top: .75rem"></div>
                                                            <div>via '.($row->service==="cod" ? "COD" : "NON COD").'</div>
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                            <div>'.$shippingData->_shipping_first_name.' '.$shippingData->_shipping_last_name.', '.$shippingData->_shipping_address_1.', '.$shippingData->_shipping_address_2.', '.$row->destination_sub_district.', '.$shippingData->_shipping_postcode.'</div>
                                                            <div style="position: relative; margin-top: .75rem"></div>
                                                            <div>via '.strtoupper($row->service).'</div>
                                                            <div style="position: relative; margin-top: .1rem"></div>
                                                            <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <g opacity="0.6">
                                                                <path d="M5.3998 5.40005V1.80005H1.7998V5.40005H5.3998ZM10.1998 5.40005V1.80005H6.5998V5.40005H10.1998ZM5.3998 10.2V6.60005H1.7998V10.2H5.3998ZM10.1998 10.2V6.60005H6.5998V10.2H10.1998Z" fill="black"/>
                                                                </g>
                                                                </svg>
                                                                <span style="margin-left: .5rem">'.strtoupper($row->status).'</span>
                                                            </div>
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                            <p style="font-weight: 600">('.($shippingData->_payment_method==="cod" ? "COD" : "NON COD").') Rp'.localMoneyFormat($shippingFee).'</p>
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
</div>

<script>
    jQuery(document).on('change','#check_order_id_all_top, #check_order_id_all_bottom',function (){
        const is_checked = jQuery(this).prop('checked')
        jQuery('#check_order_id_all_top').prop('checked',is_checked)
        jQuery('#check_order_id_all_bottom').prop('checked',is_checked)
        jQuery('[name="transaction_id[]"]').prop('checked',is_checked)
    })
    
    function kjRequestPickup(){
        let transactionIds = [];
        jQuery('input[name="transaction_id[]"]:checked').each(function() {
            transactionIds.push(jQuery(this).val());
        });
        
        if (transactionIds.length === 0){
            alert('There is no selected transaction')
            return
        }
        console.log(transactionIds)

        jQuery.ajax({
            type: "post",
            url: ajaxRouteGenerator(),
            data: {
                action: "kj_request_pickup",  // the action to fire in the server
                data: {
                    transaction_ids:transactionIds
                },         // any JS object
            },
            complete: function (response) {
                const resp = JSON.parse(response.responseText).data;
                console.log(resp)
                
            }
        })
        
    }
</script>