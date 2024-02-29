<h3>Expedition Inquiry</h3>
<p class="form-row form-row-wide">
    <label for="kj_destination_area"><?php _e('Kelurahan', 'woocommerce'); ?> <span class="required">*</span></label>
    <select name="kj_destination_area" id="kj_destination_area" class="select2 custom_select_field" style="width: 100%;" required></select>
</p>
<p class="form-row form-row-wide">
    <label ><?php _e('Asuransi', 'woocommerce'); ?></label>
    <div>
        <input id="billing_insurance" name="billing_insurance" type="checkbox" value="1">
        <label for="billing_insurance" class="unselectable">Asuransikan pesanan saya</label>
    </div>
</p>
<div style="position: relative">
    <p class="form-row form-row-wide" >
        <label for="kj_expedition"><?php _e('Expedisi', 'woocommerce'); ?> <span class="required">*</span></label>
        <select name="kj_expedition" id="kj_expedition" class="select2 custom_select_field" style="width: 100%;" required></select>
        <!--LOADER-->
        <div class="billing-expedition-state s-loading kj-hidden">
                <div style="top: 0;right: 0;bottom: 0;left: 0; background-color: rgb(200 200 200 / 50%);position: absolute;display: flex">
                    <div style="margin: auto">
                        <div class="kj-loader"></div>
                    </div>
                </div>
        </div>
    </p>
</div>
<div style="display: none">
    <input type="text" name="kj_checkout_token">
    <input type="text" name="kj_destination_area_name">
</div>

<script type="text/javascript">
    let subdistrictAjaxTimeout = null
    const subDistrictSelectElem = jQuery(`[name="kj_destination_area"]`);
    const subDistrictSelectElemSearchFieldId = 'kj_destination_area_search';


    jQuery(document).ready(function($) {
        subDistrictSelectElem.select2({
            placeholder: "Masukkan Kelurahan",
        }).on('select2:open', function(e) {
            $('.select2-search__field').prop('id', subDistrictSelectElemSearchFieldId);
        });
    });


    jQuery('body').on('keyup', `#${subDistrictSelectElemSearchFieldId}`, function(e) {
        const searchInputVal = jQuery(`#${subDistrictSelectElemSearchFieldId}`).val()
        if (subdistrictAjaxTimeout) {
            clearTimeout(subdistrictAjaxTimeout)
        }
        subdistrictAjaxTimeout = setTimeout(function() {
            subDistrictSelectElem.select2('destroy')
            subDistrictSelectElem.empty()
            subDistrictSelectElem.append("<option value='' disabled>Loading...</option>");
            subDistrictSelectElem.select2()
            subDistrictSelectElem.select2('close');
            subDistrictSelectElem.select2('open');
            jQuery(`#${subDistrictSelectElemSearchFieldId}`).val(searchInputVal);
            
            wp.ajax.post( "kiriminaja_subdistrict_search", {
                data: {
                    search: searchInputVal
                }
            })
                .done(function(response) {
                    const options = response
                    
                    subDistrictSelectElem.select2('destroy')
                    subDistrictSelectElem.empty()
                    subDistrictSelectElem.append("<option value='' disabled selected>Pilih Area</option>");
                    options.forEach(function(arr) {
                        subDistrictSelectElem.append("<option value='" + arr.id + "'>" + arr.text + "</option>");
                    })
                    subDistrictSelectElem.select2()
                    subDistrictSelectElem.select2('close');
                    subDistrictSelectElem.select2('open');
                    jQuery(`#${subDistrictSelectElemSearchFieldId}`).val(searchInputVal);
                });
        }, 1000)
    })
    
    function toggleCalculationValidation(isCompleted=false){
        jQuery('[name="kj_checkout_token"]').val(isCompleted ? '1' : '')
        /** Make checkout validation cant process becaouse calculation stil in process*/
    }



</script>
<script type="text/javascript">

    const expeditionSelectElem = jQuery('#kj_expedition');
    const expeditionSelectElemSearchFieldId = 'kj_expedition_search';
    
    jQuery(document).ready(function($) {
        expeditionSelectElem.select2({
            placeholder: "Pilih Kelurahan Terlebih Dahulu",
        }).on('select2:open', function(e) {
            $('.select2-search__field').prop('id', expeditionSelectElemSearchFieldId);
        });

        jQuery(`[name="kj_destination_area"]`).change(function (){

            jQuery('[name="kj_destination_area_name"]').val(jQuery(`[name="kj_destination_area"] option:selected`).text())
            getExpeditionPricing()
        })
        
    });

    let pmExpeditionTimeOut = null
    jQuery(document).on('change','[name="payment_method"]',function (){
        if (pmExpeditionTimeOut){
            clearTimeout(pmExpeditionTimeOut)
        }
        
        pmExpeditionTimeOut = setTimeout(function (){
            getExpeditionPricing()
        },500)
        
    })

    
    function getExpeditionPricing(){

        if (printAsString(jQuery(`[name="kj_destination_area"] option:selected`).val())===''){return ;}
        
        jQuery('.billing-expedition-state').addClass('kj-hidden')
        jQuery('.billing-expedition-state.s-loading').removeClass('kj-hidden')

        wp.ajax.post( "kj-get-expedition-ajax", {
            data: {
                destination_area_id: jQuery(`[name="kj_destination_area"] option:selected`).val(),
                payment_method: jQuery(`[name="payment_method"]:checked`).val()
            }
        }).done(function(response) {


            console.log(response.data.options)
            const options = response?.data?.options ?? [];
            expeditionSelectElem.select2('destroy');
            expeditionSelectElem.empty()
            expeditionSelectElem.append("<option value='' disabled selected>Pilih Ekspedisi</option>");
            options.forEach(function(arr) {
                expeditionSelectElem.append("<option value='" + arr.key + "'>" + arr.value + "</option>");
            })

            expeditionSelectElem.select2({
                placeholder: "Pilih Ekspedisi",
            }).on('select2:open', function(e) {
                jQuery('.select2-search__field').prop('id', expeditionSelectElemSearchFieldId);
            })

            jQuery('.billing-expedition-state').addClass('kj-hidden')
            jQuery('.billing-expedition-state.s-ready').removeClass('kj-hidden')
        });
        
    }
    
</script>
<script type="text/javascript">

    jQuery('#kj_expedition, #billing_insurance').change(function (){
        checkoutCalculation()
    })
    jQuery(document).ready(function($) {
        jQuery(document).on("change",'input[name="payment_method"]',function (){
            checkoutCalculation()
        })       
    })


    let checkoutCalculationTimeOut = null
    function checkoutCalculation(){
        
        /** Checking if requered field is filled*/
        if (
            printAsString(jQuery('#kj_expedition').val())===''
            ||
            printAsString(jQuery(`[name="payment_method"]:checked`).val())===''
        ){return}
        
        if (checkoutCalculationTimeOut){
            clearTimeout(checkoutCalculationTimeOut)
        }

        
        
        checkoutCalculationTimeOut = setTimeout(function (){
            /** Reset Checkout Process Token
             * if this is not filled transaction cant be done*/
            toggleCalculationValidation(false)
            /** Delete Total */
            jQuery('.woocommerce-checkout-review-order-table tfoot .order-total').remove()
            jQuery('.woocommerce-checkout-review-order-table tfoot .kj-order-row').remove()
            /** Add Loader*/
            jQuery('.woocommerce-checkout-review-order-table tfoot').append(`
                    <tr class="kj-order-row">
                    <th colspan="2">
                        <div style="position: relative;min-height: 4rem">
                            <div style="top: 0;right: 0;bottom: 0;left: 0; background-color: rgb(200 200 200 / 50%);position: absolute;display: flex">
                                <div style="margin: auto">
                                    <div class="kj-loader"></div>
                                </div>
                            </div>
                        </div>
                    </th>
                    </tr>
                `)
            
            
            wp.ajax.post( "kj-checkout-calc", {
                data: {
                    expedition: jQuery('#kj_expedition').val(),
                    insurance: jQuery('#billing_insurance').is(":checked"),
                    destination_area_id: jQuery(`[name="kj_destination_area"]`).val(),
                    payment_method: jQuery(`[name="payment_method"]:checked`).val(),

                }
            }).done(function(response) {
                console.log(response)


                /** Delete Table row */
                jQuery('.woocommerce-checkout-review-order-table tfoot .order-total').remove()
                jQuery('.woocommerce-checkout-review-order-table tfoot .kj-order-row').remove()
                
                
                if (response?.status !== 200){
                    jQuery('.woocommerce-checkout-review-order-table tfoot').append(`
                    <tr class="kj-order-row">
                    <th colspan="2">
                        <div style="text-align: center">
                            <div style="color: red;margin-bottom: 1rem">
                            Terjadi Kesalahan
                            <br>
                            ${response?.message}
                            </div>       
                            <button onclick="checkoutCalculation()" type="button" class="button alt" data-value="Place order">Recalculate</button>
                        </div>
                    </th>
                    </tr>
                    `)
                    return
                }
                
                const cart_total_amt = response?.data?.calculation_result?.cart_total_amt ?? 0;
                const cod_amt = response?.data?.calculation_result?.cod_amt ?? 0;
                const insurance_amt = response?.data?.calculation_result?.insurance_amt ?? 0;
                const ongkir_fee_amt = response?.data?.calculation_result?.ongkir_fee_amt ?? 0;
                const calc_total_amt = response?.data?.calculation_result?.calc_total_amt ?? 0;
                const selected_expedition = response?.data?.calculation_result?.selected_expedition ?? 0;


                /** Append Table info*/
                jQuery('.woocommerce-checkout-review-order-table tfoot').append(`
                    <tr class="kj-order-row">
                    <th>Shipping Fee</th>
                    <td><strong><span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">Rp</span>${kjMoneyFormat(ongkir_fee_amt)}</br>(${selected_expedition?.service_name})</bdi></span></strong> </td>
                    </tr>
                `)

                if (insurance_amt > 0){
                    jQuery('.woocommerce-checkout-review-order-table tfoot').append(`
                    <tr class="kj-order-row">
                    <th>Insurance Fee</th>
                    <td><strong><span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">Rp</span>${kjMoneyFormat(insurance_amt)}</bdi></span></strong> </td>
                    </tr>
                    `)
                }
                
                if (cod_amt > 0){
                    jQuery('.woocommerce-checkout-review-order-table tfoot').append(`
                    <tr class="kj-order-row">
                    <th>COD Fee</th>
                    <td><strong><span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">Rp</span>${kjMoneyFormat(cod_amt)}</bdi></span></strong> </td>
                    </tr>
                    `)                    
                }

                

                jQuery('.woocommerce-checkout-review-order-table tfoot').append(`
                    <tr class="kj-order-row order-total">
                    <th>Total</th>
                    <td><strong><span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">Rp</span>${kjMoneyFormat(calc_total_amt)}</bdi></span></strong> </td>
                    </tr>
                `)

                toggleCalculationValidation(true)
                
            });
        },600)
    }
    
</script>