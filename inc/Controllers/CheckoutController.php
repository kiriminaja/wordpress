<?php

namespace Inc\Controllers;

class CheckoutController
{

    public function register()
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        if (is_plugin_active('woocommerce/woocommerce.php')) {
            add_action('woocommerce_before_checkout_form', array($this, 'ts_add_order'));
            add_action('woocommerce_after_checkout_billing_form', array($this, 'add_custom_select_options_field_and_script'));
            add_action('woocommerce_after_checkout_shipping_form', array($this, 'add_custom_select_options_field_and_script_shipping'));
            // add_filter( 'woocommerce_cart_needs_shipping', '__return_false' );
            add_action( 'woocommerce_review_order_before_shipping', array($this,'custom_shipping_content'));

        }
    }

    function custom_shipping_content() {
        echo '<tr class="shipping"><th>Custom Shipping</th><td>Nanti Custom Shipping Disini!</td></tr>';
    }

    function ts_add_order()
    {
        echo ('<h2>Testing! 3</h2>');
    }


    function add_custom_select_options_field_and_script()
    {
?>
        <p class="form-row form-row-wide">
            <label for="custom_select_field"><?php _e('Kelurahan', 'woocommerce'); ?> <span class="required">*</span></label>
            <select name="custom_select_field" id="custom_select_field" class="select2 custom_select_field" style="width: 100%;" required></select>
        </p>
        <script type="text/javascript">
            let subdistrictAjaxTimeout = null
            const elemSelectName = 'custom_select_field';


            jQuery(document).ready(function($) {
                $('#custom_select_field').select2({
                    tags: true,
                    placeholder: "Masukkan Kelurahan",
                }).on('select2:open', function(e) {
                    $('.select2-search__field').prop('id', 'billing_search');
                });
            });


            jQuery('body').on('keyup', `#billing_search`, function(e) {
                const thisElem = jQuery(this);

                const searchInputVal = jQuery(this).val()
                if (subdistrictAjaxTimeout) {
                    clearTimeout(subdistrictAjaxTimeout)
                }
                subdistrictAjaxTimeout = setTimeout(function() {
                    jQuery(`[name="${elemSelectName}"]`).empty()
                    jQuery(`[name="${elemSelectName}"]`).append("<option value='' disabled>Loading...</option>");
                    jQuery(`[name="${elemSelectName}"]`).trigger('change');
                    jQuery(`[name="${elemSelectName}"]`).select2('close');
                    jQuery(`[name="${elemSelectName}"]`).select2('open');
                    thisElem.val(searchInputVal);
                    jQuery.ajax({
                        type: "post",
                        url: ajaxRouteGenerator(),
                        data: {
                            action: "nopriv_kiriminaja_subdistrict_search", // the action to fire in the server
                            data: {
                                search: searchInputVal
                            },
                        },
                        complete: function(response) {
                            const options = JSON.parse(response.responseText).data
                            jQuery(`[name="${elemSelectName}"]`).empty()
                            options.forEach(function(arr) {
                                jQuery(`[name="${elemSelectName}"]`).append("<option value='" + arr.id + "'>" + arr.text + "</option>");
                            })
                            jQuery(`[name="${elemSelectName}"]`).trigger('change');
                            jQuery(`[name="${elemSelectName}"]`).select2('close');
                            jQuery(`[name="${elemSelectName}"]`).select2('open');
                            thisElem.val(searchInputVal);
                        },
                    });
                }, 1000)

            })

            
            
        </script>
<?php
    }

    function add_custom_select_options_field_and_script_shipping()
    {
?>
        <p class="form-row form-row-wide">
            <label for="custom_select_field"><?php _e('Kelurahan', 'woocommerce'); ?> <span class="required">*</span></label>
            <select name="custom_select_field_shipping" id="custom_select_field_shipping" class="select2 custom_select_field_shipping" style="width: 100%;" required></select>
        </p>
        <script type="text/javascript">
            let subdistrictAjaxTimeoutShipping = null
            const elemSelectNameShipping = 'custom_select_field_shipping';


            jQuery(document).ready(function($) {
                $('#custom_select_field_shipping').select2({
                    tags: true,
                    placeholder:"Masukkan Kelurahan",
                }).on('select2:open', function(e) {
                    $('.select2-search__field').prop('id', 'shipping_search');
                });

            });

            jQuery('body').on('keyup', `#shipping_search`, function(e) {
                const thisElem = jQuery(this);

                const searchInputVal = jQuery(this).val()
                if (subdistrictAjaxTimeoutShipping) {
                    clearTimeout(subdistrictAjaxTimeoutShipping)
                }
                subdistrictAjaxTimeoutShipping = setTimeout(function() {
                    jQuery(`[name="${elemSelectNameShipping}"]`).empty()
                    jQuery(`[name="${elemSelectNameShipping}"]`).append("<option value='' disabled>Loading...</option>");
                    jQuery(`[name="${elemSelectNameShipping}"]`).trigger('change');
                    jQuery(`[name="${elemSelectNameShipping}"]`).select2('close');
                    jQuery(`[name="${elemSelectNameShipping}"]`).select2('open');
                    thisElem.val(searchInputVal);
                    jQuery.ajax({
                        type: "post",
                        url: ajaxRouteGenerator(),
                        data: {
                            action: "nopriv_kiriminaja_subdistrict_search", // the action to fire in the server
                            data: {
                                search: searchInputVal
                            },
                        },
                        complete: function(response) {
                            const options = JSON.parse(response.responseText).data
                            jQuery(`[name="${elemSelectNameShipping}"]`).empty()
                            options.forEach(function(arr) {
                                jQuery(`[name="${elemSelectNameShipping}"]`).append("<option value='" + arr.id + "'>" + arr.text + "</option>");
                            })
                            jQuery(`[name="${elemSelectNameShipping}"]`).trigger('change');
                            jQuery(`[name="${elemSelectNameShipping}"]`).select2('close');
                            jQuery(`[name="${elemSelectNameShipping}"]`).select2('open');
                            thisElem.val(searchInputVal);
                        },
                    });
                }, 1000)

            })

            
            
        </script>
<?php
    }
}
