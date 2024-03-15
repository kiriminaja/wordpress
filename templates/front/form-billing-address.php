<div id="kj-expedition-inquiry" class="kj-hidden ">
    <h3>Expedition Inquiry</h3>
    <!--Kelurahan Field-->
    <p class="form-row form-row-wide">
        <label for="kj_destination_area"><?php _e('Kelurahan', 'woocommerce'); ?> <span class="required">*</span></label>
        <select name="kj_destination_area" id="kj_destination_area" class="select2 custom_select_field" style="width: 100%;" required></select>
    </p>
    <!--Insurance Field-->
    <p class="form-row form-row-wide">
        <label ><?php _e('Asuransi', 'woocommerce'); ?></label>
    <div>
        <input id="billing_insurance" name="billing_insurance" type="checkbox" value="1">
        <label for="billing_insurance" class="unselectable">Asuransikan pesanan saya</label>
    </div>
    </p>
    <!--Expedition Field-->
    <div style="position: relative">
        <p class="form-row form-row-wide" >
            <label for="kj_expedition"><?php _e('Ekspedisi', 'woocommerce'); ?> <span class="required">*</span></label>
            <select name="kj_expedition" id="kj_expedition" class="select2 custom_select_field" style="width: 100%;" required></select>

            <!--Expedition ERR-->
        <div onclick="getExpeditionPricing()" style="cursor: pointer" class="billing-expedition-state s-error kj-hidden">
            <div style="padding: 6px; background-color: #FCF0F1; border: 1px solid #D63638; border-radius: 10px">
                <div style="display: flex;">
                    <!--Icon-->
                    <div style="top: 4px; position: relative">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M7.99961 1.59998C11.5356 1.59998 14.3996 4.46398 14.3996 7.99998C14.3996 11.536 11.5356 14.4 7.99961 14.4C4.46361 14.4 1.59961 11.536 1.59961 7.99998C1.59961 4.46398 4.46361 1.59998 7.99961 1.59998ZM8.90361 9.10398L9.18361 3.93598H6.81561L7.09561 9.10398H8.90361ZM8.83161 11.792C9.02361 11.608 9.12761 11.352 9.12761 11.024C9.12761 10.688 9.03161 10.432 8.83961 10.248C8.64761 10.064 8.36761 9.96798 7.99161 9.96798C7.61561 9.96798 7.33561 10.064 7.13561 10.248C6.93561 10.432 6.83961 10.688 6.83961 11.024C6.83961 11.352 6.94361 11.608 7.14361 11.792C7.35161 11.976 7.63161 12.064 7.99161 12.064C8.35161 12.064 8.63161 11.976 8.83161 11.792Z" fill="#D63638"/>
                        </svg>
                    </div>
                    <!--Text-->
                    <div style="margin-left: 6px;color: #D63638">
                        <div style="font-weight: 700;">Terjadi kesalahan</div>
                        <div>Klik untuk mendapat ulang opsi ekspedisi</div>
                    </div>
                </div>
            </div>
        </div>

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
    <!--Other invisible Field-->
    <div style="display: none">
        <input type="text" name="kj_checkout_token">
        <input type="text" name="kj_destination_area_name">
    </div>
</div>

<script type="text/javascript">
    let subdistrictAjaxTimeout
    const subDistrictSelectElem = jQuery(`[name="kj_destination_area"]`);
    const subDistrictSelectElemSearchFieldId = 'kj_destination_area_search';


    /** Kelurahan Select Init*/
    function subDistrictSelectElemInit(){
        subDistrictSelectElem.select2({
            placeholder: "Masukkan Kelurahan",
        }).on('select2:open', function(e) {
            jQuery('.select2-search__field').prop('id', subDistrictSelectElemSearchFieldId);
        });
    }
    jQuery(document).ready(function($) {
        subDistrictSelectElemInit()
    });

    /** Get Kelurahan by search key up*/
    jQuery('body').on('keyup', `#${subDistrictSelectElemSearchFieldId}`, function(e) {
        const searchInputVal = jQuery(`#${subDistrictSelectElemSearchFieldId}`).val()
        if (searchInputVal.length < 1){ return }
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
        }, 2000)
    })
    
    /** Flag if calculation is done or not*/
    function toggleCalculationValidation(isCompleted=false){
        jQuery('[name="kj_checkout_token"]').val(isCompleted ? '1' : '')
        /** Make checkout validation cant process becaouse calculation stil in process*/
    }



</script>
<script type="text/javascript">

    const expeditionSelectElem = jQuery('#kj_expedition');
    const expeditionSelectElemSearchFieldId = 'kj_expedition_search';
    
    /** Select expedition Init*/
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

    /** If change PM then re get expedition*/
    let pmExpeditionTimeOut = null
    jQuery(document).on('change','[name="payment_method"]',function (){
        if (pmExpeditionTimeOut){
            clearTimeout(pmExpeditionTimeOut)
        }
        pmExpeditionTimeOut = setTimeout(function (){
            getExpeditionPricing()
        },500)
        
    })

    /** Get List Expedition*/
    function getExpeditionPricing(){

        if (printAsString(jQuery(`[name="kj_destination_area"] option:selected`).val())===''){return ;}

        expeditionSelectElem.select2('destroy');
        expeditionSelectElem.empty()
        expeditionSelectElem.select2({
            placeholder: "Pilih Kelurahan Terlebih Dahulu",
        }).on('select2:open', function(e) {
            jQuery('.select2-search__field').prop('id', expeditionSelectElemSearchFieldId);
        });
        
        
        jQuery('.billing-expedition-state').addClass('kj-hidden')
        jQuery('.billing-expedition-state.s-loading').removeClass('kj-hidden')

        wp.ajax.post( "kj-get-expedition-ajax", {
            data: {
                destination_area_id: jQuery(`[name="kj_destination_area"] option:selected`).val(),
                payment_method: jQuery(`[name="payment_method"]:checked`).val()
            }
        }).done(function(response) {

            console.log(response)
            jQuery('.billing-expedition-state').addClass('kj-hidden')
            
            const options = response?.data?.options ?? [];
            if (response?.status !== 200 || options === 0){
                jQuery('.billing-expedition-state.s-error').removeClass('kj-hidden')
                return
            }
            
            expeditionSelectElem.select2('destroy');
            expeditionSelectElem.empty()
            expeditionSelectElem.append("<option value='' disabled selected>Pilih Ekspedisi</option>");
            options.forEach(function(arr) {
                expeditionSelectElem.append("<option value='" + arr.key + "' "+(lastSelectedExpedition === arr.key ? 'selected' : '')+">" + arr.value + "</option>");
            })

            expeditionSelectElem.select2({
                placeholder: "Pilih Ekspedisi",
            }).on('select2:open', function(e) {
                jQuery('.select2-search__field').prop('id', expeditionSelectElemSearchFieldId);
            })

            checkoutCalculation()
        });
        
    }
    
</script>
<script type="text/javascript">

    /** Calculation Trigger*/
    jQuery('#kj_expedition, #billing_insurance').change(function (){
        checkoutCalculation()
    })
    jQuery(document).ready(function($) {
        jQuery(document).on("change",'input[name="payment_method"], input[name="billing_insurance"]',function (){
            checkoutCalculation()
        })       
    })

    /** Calculation*/
    let checkoutCalculationTimeOut = null
    let lastSelectedExpedition = ''
    function checkoutCalculation(){
        /** Show default total and remove kj calc*/
        jQuery('.woocommerce-checkout-review-order-table tfoot .order-total').removeClass('kj-hidden')
        jQuery('.woocommerce-checkout-review-order-table tfoot .kj-order-row').remove()
        
        /** Checking if requered field is filled*/
        if (
            printAsString(jQuery('#kj_expedition').val())===''
            ||
            printAsString(jQuery(`[name="payment_method"]:checked`).val())===''
        ){return}
        
        /** Backup selected expedition */
        lastSelectedExpedition = jQuery('#kj_expedition').val()
        
        if (checkoutCalculationTimeOut){
            clearTimeout(checkoutCalculationTimeOut)
        }

        
        
        checkoutCalculationTimeOut = setTimeout(function (){
            /** Reset Checkout Process Token
             * if this is not filled transaction cant be done*/
            toggleCalculationValidation(false)
            /** Hide Total */
            jQuery('.woocommerce-checkout-review-order-table tfoot .order-total').addClass('kj-hidden')
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
                jQuery('.woocommerce-checkout-review-order-table tfoot .order-total').addClass('kj-hidden')
                jQuery('.woocommerce-checkout-review-order-table tfoot .kj-order-row').remove()
                
                
                if (response?.status !== 200){
                    jQuery('.woocommerce-checkout-review-order-table tfoot').append(`
                    <tr class="kj-order-row">
                    <th colspan="2">
                        <div style="padding: 6px; background-color: #FCF0F1; border: 1px solid #D63638; border-radius: 10px">
                            <div style="display: flex;">
                                <!--Icon-->
                                <div style="top: 4px; position: relative">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M7.99961 1.59998C11.5356 1.59998 14.3996 4.46398 14.3996 7.99998C14.3996 11.536 11.5356 14.4 7.99961 14.4C4.46361 14.4 1.59961 11.536 1.59961 7.99998C1.59961 4.46398 4.46361 1.59998 7.99961 1.59998ZM8.90361 9.10398L9.18361 3.93598H6.81561L7.09561 9.10398H8.90361ZM8.83161 11.792C9.02361 11.608 9.12761 11.352 9.12761 11.024C9.12761 10.688 9.03161 10.432 8.83961 10.248C8.64761 10.064 8.36761 9.96798 7.99161 9.96798C7.61561 9.96798 7.33561 10.064 7.13561 10.248C6.93561 10.432 6.83961 10.688 6.83961 11.024C6.83961 11.352 6.94361 11.608 7.14361 11.792C7.35161 11.976 7.63161 12.064 7.99161 12.064C8.35161 12.064 8.63161 11.976 8.83161 11.792Z" fill="#D63638"/>
                                    </svg>
                                </div>
                                <!--Text-->
                                <div style="margin-left: 6px;color: #D63638">
                                    <div style="font-weight: 700;">Terjadi kesalahan</div>
                                    <div>${response?.message}</div>
                                </div>
                            </div>
                        </div>
                        <div style="text-align: center; margin-top: 12px">
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


<script>
    /** Hide KJ Inquiry form if region isnot ID*/
    const kjExpeditinonInquiry = jQuery('#kj-expedition-inquiry')
    /** On region Load*/
    jQuery(document).ready(function (){
        let checkoutDestinationRegion = jQuery('[name="billing_country"]').val()
        
        /** Default*/
        resetKJInquiryForm()
        kjExpeditinonInquiry.addClass('kj-hidden')
        if (checkoutDestinationRegion === "ID"){
            kjExpeditinonInquiry.removeClass('kj-hidden')
        }
    })

    /** On region Change*/
    jQuery(document).on('change','[name="billing_country"]',function (){
        resetKJInquiryForm()

        /** Default*/
        kjExpeditinonInquiry.addClass('kj-hidden')
        if (jQuery(this).val() === "ID"){
            kjExpeditinonInquiry.removeClass('kj-hidden')
            subDistrictSelectElemInit()
        }
    })
    
    function resetKJInquiryForm(){
        /** select */
        jQuery('[name="kj_destination_area"]').empty()
        jQuery('[name="kj_expedition"]').empty()

        /** imput */
        jQuery('[name="kj_checkout_token"]').val('')
        jQuery('[name="kj_destination_area_name"]').val('')

        /** Show default total and remove kj calc*/
        jQuery('.woocommerce-checkout-review-order-table tfoot .order-total').removeClass('kj-hidden')
        jQuery('.woocommerce-checkout-review-order-table tfoot .kj-order-row').remove()
    }
</script>