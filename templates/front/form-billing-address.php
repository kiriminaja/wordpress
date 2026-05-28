<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Variables provided by CheckoutController::add_custom_select_options_field_and_script().
 *
 * @var string $field_key
 * @var bool   $kiriof_checkout_token
 * @var string $destination_name
 * @var string $shipping_destination_name
 * @var bool   $kiriof_global_insurance
 */
?>
<div id="kiriof_destination_area_group">    
    <div style="display: none">
        <input type="hidden" name="kiriof_checkout_token" value="<?php echo esc_attr($kiriof_checkout_token); ?>">
        <input type="hidden" name="kiriof_destination_area_name" value="<?php echo esc_attr($destination_name); ?>">
        <input type="hidden" name="kiriof_shipping_destination_area_name" value="<?php echo esc_attr($shipping_destination_name); ?>">
        <input type="hidden" name="kiriof_force_insurance" value="0">
    </div>

</div>

<?php if( is_checkout() || is_cart() ) { ?>
    <?php ob_start(); ?>

        jQuery(document).ready(function($) {    
            <?php if ( $kiriof_global_insurance ) : ?>
            // Global insurance forced — check and disable the checkbox
            var $ins = jQuery('#kiriof_insurance, #kiriof_shipping_insurance');
            $ins.prop('checked', true).prop('disabled', true);
            $ins.closest('.form-row').css('opacity', '0.6');
            <?php endif; ?>

            getSearchAreaKelurahan();
            changeDistrict();

            <?php if(is_cart()): ?>

                setTimeout(() => {
                    jQuery('.shipping-calculator-form').show();
                }, 300);

                jQuery( document.body ).on( 'updated_cart_totals', function(){
                    getSearchAreaKelurahan();
                    changeDistrict(); 
                });

                // Save chosen shipping method to local storage
                jQuery(document).on('change', 'input[name="shipping_method[0]"]', function() {
                    localStorage.setItem('chosen_shipping_method', jQuery(this).val());
                });
            <?php endif; ?>

            <?php if(is_checkout()): ?>
                kiriofChangeCodPayment();
                kiriofChangeDifferentAddress();

                jQuery(document.body).on( 'change', 'input.shipping_method', function() {
                    kiriofHandleCodInsurance();
                });

                // Block checkout: listen for shipping method changes via WC data store.
                // React blocks don't use jQuery change events reliably.
                if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
                    var kiriofLastShippingMethod = '';
                    wp.data.subscribe(function() {
                        try {
                            var store = wp.data.select('wc/store/cart');
                            if (!store || typeof store.getShippingRates !== 'function') return;
                            var allRates = store.getShippingRates();
                            if (!allRates || !allRates.length) return;
                            var currentMethod = '';
                            for (var i = 0; i < allRates.length; i++) {
                                var pkg = allRates[i];
                                if (pkg && pkg.shipping_rates) {
                                    for (var j = 0; j < pkg.shipping_rates.length; j++) {
                                        if (pkg.shipping_rates[j].selected) {
                                            currentMethod = pkg.shipping_rates[j].rate_id;
                                            break;
                                        }
                                    }
                                }
                            }
                            if (currentMethod && currentMethod !== kiriofLastShippingMethod && currentMethod.indexOf('kiriminaja-official') === 0) {
                                kiriofLastShippingMethod = currentMethod;
                                // Delay to let the Store API update the cart first
                                setTimeout(function() { kiriofCodInsurance(); }, 400);
                            }
                        } catch(e) {}
                    });

                    // Dynamic District options from postcode
                    var kiriofLastPostcode = '';
                    var kiriofFieldId = 'kiriminaja-official/kiriof_destination_area';
                    var kiriofBlockPostcodeSelectors = [
                        'input#billing-postcode',
                        'input#shipping-postcode',
                        'input[name="billing_postcode"]',
                        'input[name="shipping_postcode"]',
                        'input[name="billing-postcode"]',
                        'input[name="shipping-postcode"]',
                        'input[id$="-postcode"]',
                        'input[name$="[postcode]"]'
                    ].join(',');

                    function kiriofGetCheckoutPostcodeFromDom() {
                        var postcode = '';
                        jQuery(kiriofBlockPostcodeSelectors).each(function() {
                            var val = jQuery(this).val();
                            if (val && String(val).length >= 3) {
                                postcode = String(val);
                                return false;
                            }
                        });
                        return postcode;
                    }

                    function kiriofFetchDistricts(postcode) {
                        if (!postcode || postcode === kiriofLastPostcode || postcode.length < 3) return;
                        kiriofLastPostcode = postcode;

                        var kiriofAjaxUrl = (typeof kiriofAjax !== 'undefined' && kiriofAjax.ajaxurl)
                            ? kiriofAjax.ajaxurl
                            : '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
                        var kiriofAjaxNonce = (typeof kiriofAjax !== 'undefined' && kiriofAjax.nonce)
                            ? kiriofAjax.nonce
                            : '<?php echo esc_js(wp_create_nonce(KIRIOF_NONCE)); ?>';

                        jQuery.ajax({
                            type: 'post',
                            url: kiriofAjaxUrl,
                            data: {
                                action: 'kiriminaja_subdistrict_search',
                                data: { term: postcode },
                                nonce: kiriofAjaxNonce
                            },
                            success: function(response) {
                                if (!response || !response.data || !response.data.length) return;
                                var $field = jQuery('[name="' + kiriofFieldId + '"]');
                                if (!$field.length) return;

                                var currentValue = $field.val() || '';
                                var currentName = jQuery('[name="kiriof_destination_area_name"]').val() || '';
                                var html = '<option value=""><?php echo esc_js(__('Select District','kiriminaja-official')); ?></option>';
                                response.data.forEach(function(d) {
                                    var selected = String(d.id) === String(currentValue) ? ' selected' : '';
                                    html += '<option value="' + d.id + '"' + selected + '>' + d.text + '</option>';
                                    if (String(d.id) === String(currentValue)) {
                                        currentName = d.text;
                                    }
                                });

                                if (!$field.is('select')) {
                                    // WooCommerce Store API validates this field as text so dynamic
                                    // postcode results are not restricted to an enum. The visible UI is
                                    // still a select so the buyer chooses from valid District options.
                                    var select = '<select name="' + kiriofFieldId + '" id="' + kiriofFieldId + '" class="kiriof-block-district-select" style="width:100%;padding:8px;border:1px solid #50575e;border-radius:4px;font-size:14px;">' + html + '</select>';
                                    $field.replaceWith(select);
                                } else {
                                    $field.html(html);
                                }

                                if (currentValue && currentName) {
                                    jQuery('[name="kiriof_destination_area_name"]').val(currentName);
                                }
                            }
                        });
                    }

                    jQuery(document).off('change.kiriofBlockDistrict', '[name="' + kiriofFieldId + '"]')
                        .on('change.kiriofBlockDistrict', '[name="' + kiriofFieldId + '"]', function() {
                            var val = jQuery(this).val();
                            var label = jQuery(this).find('option:selected').text();
                            jQuery('[name="kiriof_destination_area_name"]').val(label || '');
                            try {
                                var cartStore = wp.data.select('wc/store/cart');
                                var cartDispatch = wp.data.dispatch('wc/store/cart');
                                var billing = cartStore.getBillingAddress ? (cartStore.getBillingAddress() || {}) : {};
                                var shipping = cartStore.getShippingAddress ? (cartStore.getShippingAddress() || {}) : {};
                                billing[kiriofFieldId] = val;
                                shipping[kiriofFieldId] = val;
                                if (cartDispatch.setBillingAddress) {
                                    cartDispatch.setBillingAddress(billing);
                                }
                                if (cartDispatch.setShippingAddress) {
                                    cartDispatch.setShippingAddress(shipping);
                                }
                            } catch(e) {}
                            kiriofCodInsurance();
                        });

                    // Watch for postcode changes via direct DOM events too. Some Woo
                    // checkout blocks debounce address writes through /wc/store/v1/batch,
                    // so the cart data store can lag behind the visible postcode input.
                    jQuery(document).off('input.kiriofBlockPostcode change.kiriofBlockPostcode', kiriofBlockPostcodeSelectors)
                        .on('input.kiriofBlockPostcode change.kiriofBlockPostcode', kiriofBlockPostcodeSelectors, function() {
                            kiriofFetchDistricts(jQuery(this).val());
                        });
                    setTimeout(function() {
                        kiriofFetchDistricts(kiriofGetCheckoutPostcodeFromDom());
                    }, 300);

                    // Watch for postcode changes via data store
                    wp.data.subscribe(function() {
                        try {
                            var store = wp.data.select('wc/store/cart');
                            if (!store) return;
                            var shipping = store.getShippingAddress ? store.getShippingAddress() : {};
                            var billing = store.getBillingAddress ? store.getBillingAddress() : {};
                            var postcode = (shipping && shipping.postcode) || (billing && billing.postcode) || kiriofGetCheckoutPostcodeFromDom();
                            kiriofFetchDistricts(postcode);
                        } catch(e) {}
                    });
                }

                // Re-run after AJAX fragment refresh (theme compatibility)
                jQuery(document.body).on( 'updated_checkout', function() {
                    kiriofChangeCodPayment();
                    kiriofChangeDifferentAddress();
                    setTimeout(function() { kiriofCodInsurance(); }, 300);
                });

            <?php endif; ?>
        }); 
          
        function changeDistrict(){
            
            let kelurahanArea = "select#<?php echo esc_js($field_key); ?>,select#kiriof_shipping_destination_area";
            
            jQuery(kelurahanArea).change( function () {
                let root = jQuery(this);
                let different_address = jQuery('[name="ship_to_different_address"]:checked').length;
                let country = jQuery('#billing_country').find(':selected').val();
                let _insurance;

                <?php if(is_checkout()): ?>
                    if( different_address > 0 ){
                        _insurance = jQuery('#kiriof_insurance:checked').length;
                        jQuery('[name="kiriof_shipping_destination_area_name"]').val(root.find('option:selected').text());
                    }else{
                        _insurance = jQuery('#kiriof_shipping_insurance:checked').length;
                        jQuery('[name="kiriof_shipping_destination_area_name"]').val('');
                    }
                <?php else: ?>
                    _insurance = 0;
                <?php endif; ?>
                
                
                jQuery.ajax({
                    url:"<?php echo esc_url( admin_url('admin-ajax.php') ); ?>",
                    type: 'post',
                    data: {
                        action:'kiriof_get_destination_area',
                        'val':root.val(),
                        'insurance':_insurance,
                        'different_address': different_address,
                        'text':root.find('option:selected').text(),
                        'payment_method':jQuery('input[name="payment_method"]:checked').val(),
                        'nonce':"<?php echo esc_js( wp_create_nonce('kiriof-destination') ); ?>",
                        'country':country ?? 'ID'
                    },
                    dataType:'JSON',
                    beforeSend:function(){
                        <?php if(is_cart()): ?>
                            jQuery('.kj-cart-sidebar').block({ message: null }); 
                        <?php else: ?>
                            jQuery('#order_review').find('.shop_table').block({ message: null });
                        <?php endif;?>
                    },
                    success:function(response){
                        
                        if( response.data.code != 200 ){
                            jQuery('.woocommerce-notices-wrapper').append(response.msg);
                            toggleCalculationValidation(false);
                        }else{
                            <?php if(is_cart()): ?>
                                jQuery('.kj-cart-sidebar').unblock();
                            <?php else: ?>
                                jQuery('#order_review').find('.shop_table').unblock();
                            <?php endif; ?>
                            toggleCalculationValidation(true);
                            
                        }

                        /** add Destination Name */
                        jQuery('[name="kiriof_destination_area_name"]').val(jQuery(`[name="kiriof_destination_area"] option:selected`).text())

                        <?php if( is_cart() ): ?>
                            jQuery('button[name="calc_shipping"]').trigger('click');
                            jQuery( document.body ).trigger( 'update_checkout',{update_shipping_method:true} );                        

                        <?php else:?>
                            jQuery( document.body ).trigger( 'update_checkout',{update_shipping_method:true} );                        
                            
                                jQuery(document.body).one('updated_checkout', function() {
                                    kiriofCodInsurance();                                    
                                });
                            
                            
                        <?php endif;?>

                    },
                    error:function(xhr){
                        alert("Sorry System Trouble Error Code : "+xhr.status)
                        return false;
                    }
                });
                
            });

            /** Flag if calculation is done or not*/
            function toggleCalculationValidation(isCompleted=false){
                jQuery('[name="kiriof_checkout_token"]').val(isCompleted ? '1' : '');
            }
        }
        
        /**
         * Get Kelurahan by search key up New
         */
        function getSearchAreaKelurahan(){
            let ajaxurl = "<?php echo esc_url( admin_url('admin-ajax.php') ); ?>";
            let subDistrictSelectElem = jQuery(`[name="<?php echo esc_js( $field_key ); ?>"],[name=kiriof_shipping_destination_area]`); 
            let nonce = "<?php echo esc_js(wp_create_nonce(KIRIOF_NONCE)); ?>";

            subDistrictSelectElem.select2({
                minimumInputLength: 3,
                placeholder: "<?php echo esc_js(__('Select Option','kiriminaja-official')); ?>",
                allowClear: true,
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    type: "POST",
                    delay: 250,
                    data: function (search) {
                        return {
                            data:search,
                            nonce:nonce,
                            action: 'kiriminaja_subdistrict_search'
                        };
                    },
                    processResults: function (response) {
                        return {
                            results: jQuery.map(response.data, function (item) {
                                return {
                                    text: item.text,
                                    id: item.id
                                }
                            })
                        };
                    },
                    cache: true
                }
            });

            // Restore Select2 display for pre-selected values (e.g. from session on cart page)
            subDistrictSelectElem.each(function() {
                var $el = jQuery(this);
                var selectedVal = $el.val();
                var selectedText = $el.find('option:selected').text();
                if (selectedVal && selectedText && selectedText !== '<?php echo esc_js(__('Select Option','kiriminaja-official')); ?>') {
                    $el.trigger('change.select2');
                }
            }); 
        }

        jQuery(document.body).on('updated_checkout', function() {
            let shipping_metode_id = jQuery('#shipping_method .shipping_method:checked').val(); // return kiriminaja_lion_REGPACK
            let different_address = jQuery(`[name="ship_to_different_address"]:checked`).length;
            let destination_id = (different_address == 0) ? jQuery('#kiriof_destination_area option:selected').val() : jQuery('#kiriof_shipping_destination_area option:selected').val(); 
            
            if( destination_id != 'undefined' ) return false;
        
            
            if(jQuery('#shipping_method .shipping_method:checked').length == 0 && destination_id != 0  ){
                jQuery( document.body ).trigger( 'update_checkout',{update_shipping_method:true} );                                        
            }    
            
        }); 

        jQuery(document.body).one('updated_checkout', function() {
            /**
             * set chosen shipping method from local storage
             * remove local storage
             */
            if (localStorage.getItem('chosen_shipping_method')) {
                jQuery('input[name="shipping_method[0]"][value="' + localStorage.getItem('chosen_shipping_method') + '"]').prop('checked', true);                    
            }

            localStorage.removeItem('chosen_shipping_method');

            kiriofHandleCodInsurance();
        });


        function kiriofChangeCodPayment(){
            jQuery(document).on('change','[name="payment_method"]:checked,#kiriof_insurance,#kiriof_shipping_insurance',function() {
                kiriofHandleCodInsurance();
            });

        }

        function kiriofChangeDifferentAddress(){
            jQuery('[name="ship_to_different_address"]').on('change',function(e) {
                if(jQuery(this).is(':checked')){
                    jQuery('#kiriof_destination_area').val(jQuery('#kiriof_shipping_destination_area option:selected').val()).trigger("change");
                }else{
                    jQuery('#kiriof_destination_area').val(jQuery('#kiriof_destination_area option:selected').val()).trigger("change");
                }
            });
        }

        function kiriofHandleCodInsurance(){
            
            <?php if( is_checkout()){ ?>

                jQuery( document.body ).trigger( 'update_checkout',{update_shipping_method:true} );                        
                
                setTimeout(() => {
                    jQuery( document ).one( "ajaxComplete", function(event,xhr,settings) {
                        kiriofCodInsurance();
                    });
                }, 300);

            <?php } ?>


        }

        function kiriofBlockExtensionCartUpdate(data) {
            if (typeof wp === 'undefined' || !wp.data || !wp.data.dispatch) {
                return;
            }

            try {
                var cartDispatch = wp.data.dispatch('wc/store/cart');
                if (cartDispatch && typeof cartDispatch.extensionCartUpdate === 'function') {
                    cartDispatch.extensionCartUpdate({
                        namespace: 'kiriminaja-official',
                        data: {
                            shipping_metode_id: data.shipping_metode_id,
                            destination_id: data.destination_id,
                            payment_method: data.payment_method,
                            insurance: data.insurance
                        }
                    });
                    return;
                }

                if (cartDispatch && typeof cartDispatch.invalidateResolutionForStore === 'function') {
                    cartDispatch.invalidateResolutionForStore();
                }
            } catch(e) {}
        }

        function kiriofCodInsurance(){
           
            let different_address = jQuery(`[name="ship_to_different_address"]:checked`).length;
            
            // Read shipping method: traditional, block radio, or block data store
            let shipping_metode_id = jQuery('#shipping_method .shipping_method:checked').val()
                || jQuery('.wc-block-components-radio-control__input:checked').val();
            // Fallback: block checkout data store
            if (!shipping_metode_id && typeof wp !== 'undefined' && wp.data && wp.data.select) {
                try {
                    var rates = wp.data.select('wc/store/cart').getShippingRates();
                    if (rates && rates.length) {
                        for (var i = 0; i < rates.length; i++) {
                            var pkg = rates[i];
                            if (pkg && pkg.shipping_rates) {
                                for (var j = 0; j < pkg.shipping_rates.length; j++) {
                                    if (pkg.shipping_rates[j].selected) {
                                        shipping_metode_id = pkg.shipping_rates[j].rate_id;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                } catch(e) {}
            }
            shipping_metode_id = shipping_metode_id || '';
            
            let destination_id = ( 
                different_address == '0' 
                ? 
                (jQuery('#kiriof_destination_area option:selected').val() || jQuery('[name="kiriof_destination_area"]').val())
                : 
                (jQuery('#kiriof_shipping_destination_area option:selected').val() || jQuery('[name="kiriof_shipping_destination_area"]').val())
            ); 

            // Global insurance forced = always true
            <?php if ( $kiriof_global_insurance ) : ?>
            let insurance = 1;
            <?php else : ?>
            let insurance = ( 
                different_address == '0' 
                ? 
                jQuery('#kiriof_insurance:checked').val() 
                : 
                jQuery('#kiriof_shipping_insurance:checked').val()
            );
            <?php endif; ?>

            let payment_method = jQuery("[name=payment_method]:checked").val() ?? jQuery("[name=payment_method]").val() ;
                        

            let data = {
                action:'kiriof_get_data_after_update_checkout',
                nonce:"<?php echo esc_js( wp_create_nonce('kiriof-update-checkout') ); ?>",
                shipping_metode_id : (typeof shipping_metode_id === 'undefined' ? '' : shipping_metode_id),
                destination_id,
                payment_method,
                insurance : (typeof insurance === 'undefined' ? 0 : parseInt(insurance))
            };

            jQuery.ajax({
                        url:"<?php echo esc_url( admin_url('admin-ajax.php') ); ?>",
                        type: 'post',
                        data: data,
                        dataType:'JSON',
                        beforeSend:function(){
                            jQuery('#order_review').find('.shop_table').block({ message: null });
                        },
                        success:function(response){                                 
                            jQuery('#order_review').find('.shop_table').unblock();  
                
                            let insurance_res = response?.data?.insurance_fee ?? 0;
                            let cod_fee_res = response?.data?.cod_fee ?? 0;
                                    
                            if( response?.data?.is_insurance == 0 ){
                                jQuery('.kiriof_cart_item_insurane').hide();
                            }else{
                                jQuery('.kiriof_cart_item_insurane').show();
                            }
                            
                            if( response?.data?.is_cod_amt  == 0 ){
                                jQuery('.kiriof_cart_item_cod_fee').hide();
                            }else{
                                jQuery('.kiriof_cart_item_cod_fee').show();
                            }

                            jQuery('[name=kiriof_force_insurance]').val(response?.data?.force_insurance); 

                            jQuery('#order_review').find('.order-total td').html(response?.data?.price_total);  
                            


                            /**
                             * Display cost insurance information
                             * Display cost codfee information
                             */
                            jQuery('.kj-cost-insurance').html(insurance_res);
                            jQuery('.kj-cost-codfee').html(cod_fee_res);

                            // Block checkout (ShopVerse/React) does not re-render totals from
                            // classic update_checkout fragments. Use Store API cart/extensions
                            // so WooCommerce recalculates and returns native fee rows.
                            kiriofBlockExtensionCartUpdate(data);
        
                        },
                        error:function(xhr){
                            alert("Sorry System Trouble Error Code : "+xhr.status);                                
                         }
            });
        }

    <?php
    $kiriof_inline_script = ob_get_clean();
    wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
    ?>

<?php } ?>