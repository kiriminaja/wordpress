<div id="kj_destination_area_group">
    
    <!--Other invisible Field-->
    <div style="display: none">
        <input type="hidden" name="kj_checkout_token" value="<?php echo  $kj_checkout_token; ?>">
        <input type="hidden" name="kj_destination_area_name" value="<?php echo  $dentination_name; ?>">
        <input type="hidden" name="kj_shipping_destination_area_name" value="<?php echo  $shipping_dentination_name; ?>">
        <input type="hidden" name="kj_force_insurance" value="0">
    </div>

</div>

<?php if( is_checkout() || is_cart() ) { ?>
    <script>

        jQuery(document).ready(function($) {    
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
                kj_changeCodPaymentMethod();
                kj_changeDifferentAdrress();

                jQuery(document.body).on( 'change', 'input.shipping_method', function() {
                    AjaxHandleCodInsurance();
                });

            <?php endif; ?>
        }); 
          
        function changeDistrict(){
            
            let kelurahanArea = "select#<?php echo  $field_key; ?>,select#kj_shipping_destination_area";
            
            jQuery(kelurahanArea).change( function () {
                let root = jQuery(this);
                let different_address = jQuery('[name="ship_to_different_address"]:checked').length;
                let country = jQuery('#billing_country').find(':selected').val();
                let _insurance;

                <?php if(is_checkout()): ?>
                    if( different_address > 0 ){
                        _insurance = jQuery('#kj_insurance:checked').length;
                        jQuery('[name="kj_shipping_destination_area_name"]').val(root.find('option:selected').text());
                    }else{
                        _insurance = jQuery('#kj_shipping_insurance:checked').length;
                        jQuery('[name="kj_shipping_destination_area_name"]').val('');
                    }
                <?php else: ?>
                    _insurance = 0;
                <?php endif; ?>
                
                
                jQuery.ajax({
                    url:"<?php echo admin_url('admin-ajax.php'); ?>",
                    type: 'post',
                    data: {
                        action:'getDestinationArea',
                        'val':root.val(),
                        'insurance':_insurance,
                        'different_address': different_address,
                        'text':root.find('option:selected').text(),
                        'payment_method':jQuery('input[name="payment_method"]:checked').val(),
                        'nonce':"<?php echo  wp_create_nonce('kj-destination'); ?>",
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
                        jQuery('[name="kj_destination_area_name"]').val(jQuery(`[name="kj_destination_area"] option:selected`).text())

                        <?php if( is_cart() ): ?>
                            jQuery('button[name="calc_shipping"]').trigger('click');
                            jQuery( document.body ).trigger( 'update_checkout',{update_shipping_method:true} );                        

                        <?php else:?>
                            jQuery( document.body ).trigger( 'update_checkout',{update_shipping_method:true} );                        
                            
                                jQuery(document.body).one('updated_checkout', function() {
                                    ajaxCodInsurance();                                    
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
                jQuery('[name="kj_checkout_token"]').val(isCompleted ? '1' : '');
            }
        }
        
        /**
         * Get Kelurahan by search key up New
         */
        function getSearchAreaKelurahan(){
            let ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
            let subDistrictSelectElem = jQuery(`[name="<?php echo  $field_key; ?>"],[name=kj_shipping_destination_area]`); 
       
            subDistrictSelectElem.select2({
                minimumInputLength: 3,
                placeholder: "<?php echo __('Select Option','plugin-wp'); ?>",
                allowClear: true,
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    type: "POST",
                    delay: 250,
                    data: function (search) {
                        return {
                            data:search,
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
        }

        jQuery(document.body).on('updated_checkout', function() {
            let shipping_metode_id = jQuery('#shipping_method .shipping_method:checked').val(); // return kiriminaja_lion_REGPACK
            let different_address = jQuery(`[name="ship_to_different_address"]:checked`).length;
            let destination_id = (different_address == 0) ? jQuery('#kj_destination_area option:selected').val() : jQuery('#kj_shipping_destination_area option:selected').val(); 
            
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

            AjaxHandleCodInsurance();
        });


        function kj_changeCodPaymentMethod(){
            jQuery(document).on('change','[name="payment_method"]:checked,#kj_insurance,#kj_shipping_insurance',function() {
                AjaxHandleCodInsurance();
            });

        }

        function kj_changeDifferentAdrress(){
            jQuery('[name="ship_to_different_address"]').on('change',function(e) {
                if(jQuery(this).is(':checked')){
                    jQuery('#kj_destination_area').val(jQuery('#kj_shipping_destination_area option:selected').val()).trigger("change");
                }else{
                    jQuery('#kj_destination_area').val(jQuery('#kj_destination_area option:selected').val()).trigger("change");
                }
            });
        }

        function AjaxHandleCodInsurance(){
            
            <?php if( is_checkout()){ ?>

                jQuery( document.body ).trigger( 'update_checkout',{update_shipping_method:true} );                        
                
                setTimeout(() => {
                    jQuery( document ).one( "ajaxComplete", function(event,xhr,settings) {
                        ajaxCodInsurance();
                    });
                }, 300);

            <?php } ?>


        }

        function ajaxCodInsurance(){
           
            let different_address = jQuery(`[name="ship_to_different_address"]:checked`).length;
            
            let shipping_metode_id = jQuery('#shipping_method .shipping_method:checked').val(); // return kiriminaja_lion_REGPACK
            
            let destination_id = ( 
                different_address == '0' 
                ? 
                jQuery('#kj_destination_area option:selected').val() 
                : 
                jQuery('#kj_shipping_destination_area option:selected').val() 
            ); 

            let insurance = ( 
                different_address == '0' 
                ? 
                jQuery('#kj_insurance:checked').val() 
                : 
                jQuery('#kj_shipping_insurance:checked').val()
            );

            let payment_method = jQuery("[name=payment_method]:checked").val() ?? jQuery("[name=payment_method]").val() ;
                        

            let data = {
                action:'kj_get_data_after_update_checkout',
                nonce:"<?php echo  wp_create_nonce('kj-update-checkout'); ?>",
                shipping_metode_id : (typeof shipping_metode_id === 'undefined' ? '' : shipping_metode_id),
                destination_id,
                payment_method,
                insurance : (typeof insurance === 'undefined' ? 0 : parseInt(insurance))
            };

            jQuery.ajax({
                        url:"<?php echo admin_url('admin-ajax.php'); ?>",
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
                                jQuery('.kj_cart_item_insurane').hide();
                            }else{
                                jQuery('.kj_cart_item_insurane').show();
                            }
                            
                            if( response?.data?.is_cod_amt  == 0 ){
                                jQuery('.kj_cart_item_cod_fee').hide();
                            }else{
                                jQuery('.kj_cart_item_cod_fee').show();
                            }

                            jQuery('[name=kj_force_insurance]').val(response?.data?.force_insurance); 

                            jQuery('#order_review').find('.order-total td').html(response?.data?.price_total);  
                            


                            /**
                             * Display cost insurance information
                             * Display cost codfee information
                             */
                            jQuery('.kj-cost-insurance').html(insurance_res);
                            jQuery('.kj-cost-codfee').html(cod_fee_res);
        
                        },
                        error:function(xhr){
                            alert("Sorry System Trouble Error Code : "+xhr.status);                                
                         }
            });
        }

    </script>

<?php } ?>