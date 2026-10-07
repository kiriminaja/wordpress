// Checkout entry point. Libraries and configuration are loaded by WordPress dependencies.
jQuery(document).ready(function($) {
    if (kiriofUsesClassicCheckout()) {
        kiriofSyncClassicAddressFields();
        return;
    }
    if (kiriofBillingAddressConfig.globalInsurance) {
    // Global insurance forced — check and disable the checkbox
    var $ins = jQuery('#kiriof_insurance, #kiriof_shipping_insurance');
    $ins.prop('checked', true).prop('disabled', true);
    $ins.closest('.form-row').css('opacity', '0.6');
    }

    kiriofSyncClassicAddressFields();
    getSearchAreaKelurahan();
    kiriofRestoreClassicDistrictSelections();
    changeDistrict();
    kiriofScheduleClassicShippingMethodSelectInit();
    kiriofInitBlockCheckoutCompatibility();
    if (kiriofBillingAddressConfig.isCheckout) {
        setTimeout(kiriofRestoreClassicDistrictSelections, 300);
        setTimeout(kiriofRestoreClassicDistrictSelections, 1500);
    }

    if (kiriofBillingAddressConfig.isCart) {

        setTimeout(() => {
            jQuery('.shipping-calculator-form').show();
        }, 300);

        jQuery( document.body ).on( 'updated_cart_totals', function(){
            getSearchAreaKelurahan();
            changeDistrict();
            kiriofScheduleClassicShippingMethodSelectInit();
        });

        // Save chosen shipping method to local storage
        jQuery(document).on('change', 'input[name="shipping_method[0]"]', function() {
            localStorage.setItem('chosen_shipping_method', jQuery(this).val());
        });
    }

    if (kiriofBillingAddressConfig.isCheckout) {
        kiriofChangeCodPayment();
        kiriofChangeDifferentAddress();

        jQuery(document.body).on( 'change', 'input.shipping_method', function() {
            kiriofRememberSelectedShippingMethod(jQuery(this).val());
            kiriofHandleCodInsurance();
        });



        // Re-bind handlers after AJAX fragment refresh (theme compatibility).
        // Do not call kiriofCodInsurance() here: its success callback triggers
        // update_checkout once so WooCommerce can render native fee rows. Calling
        // it again from updated_checkout creates an endless loading loop.
        jQuery(document.body).on( 'updated_checkout', function() {
            kiriofSyncClassicAddressFields();
            // Themes may replace address controls along with checkout fragments.
            // Initialization is idempotent and leaves existing open widgets alone.
            getSearchAreaKelurahan();
            kiriofRestoreClassicDistrictSelections();
            kiriofChangeCodPayment();
            kiriofChangeDifferentAddress();
        });

    }
});
