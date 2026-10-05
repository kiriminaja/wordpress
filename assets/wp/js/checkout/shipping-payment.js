// Legacy checkout shipping payment; globals retained for theme compatibility.
jQuery(document.body).on('updated_checkout', function() {
    if (kiriofUsesClassicCheckout()) {
        return;
    }
    if ( kiriofTriggeredInitialShippingUpdate ) {
        return false;
    }
    let different_address = jQuery(`[name="ship_to_different_address"]:checked`).length;
    let destination_id = (different_address == 0) ? jQuery('#kiriof_destination_area option:selected').val() : jQuery('#kiriof_shipping_destination_area option:selected').val();

    if ( ! destination_id || destination_id === 'undefined' || destination_id == 0 ) {
        return false;
    }

    if ( jQuery('#shipping_method .shipping_method:checked').length == 0 ) {
        kiriofTriggeredInitialShippingUpdate = true;
        jQuery( document.body ).trigger( 'update_checkout',{update_shipping_method:true} );
    }
});

jQuery(document.body).one('updated_checkout', function() {
    if (kiriofUsesClassicCheckout()) {
        return;
    }
    /**
     * set chosen shipping method from local storage
     * remove local storage
     */
    if (localStorage.getItem('chosen_shipping_method')) {
        var $methodInput = jQuery('input[name="shipping_method[0]"][value="' + localStorage.getItem('chosen_shipping_method') + '"]');
        if ($methodInput.length) {
            $methodInput.prop('checked', true);
            kiriofHandleCodInsurance();
        }
    }

    localStorage.removeItem('chosen_shipping_method');
});


function kiriofChangeCodPayment(){
    if (kiriofUsesClassicCheckout()) {
        return;
    }
    jQuery(document)
        .off('change.kiriofPaymentRefresh', '[name="payment_method"], #kiriof_insurance, #kiriof_shipping_insurance')
        .on('change.kiriofPaymentRefresh', '[name="payment_method"], #kiriof_insurance, #kiriof_shipping_insurance', function() {
            if (this.name === 'payment_method' && !jQuery(this).is(':checked')) {
                return;
            }
            kiriofHandleCodInsurance();
        });
}

function kiriofChangeDifferentAddress(){
    if (kiriofUsesClassicCheckout()) {
        return;
    }
    jQuery(document)
        .off('change.kiriofDifferentAddress', '[name="ship_to_different_address"]')
        .on('change.kiriofDifferentAddress', '[name="ship_to_different_address"]', function() {
            if(jQuery(this).is(':checked')){
                jQuery('#kiriof_shipping_destination_area').trigger('change');
            }else{
                jQuery('#kiriof_destination_area').val(jQuery('#kiriof_destination_area option:selected').val()).trigger("change");
            }
        });
}

function kiriofGetClassicInsuranceValue() {
    return jQuery('#kiriof_insurance:checked').length ? 1 : 0;
}

function kiriofInitClassicShippingMethodSelect() {
    if (kiriofUsesClassicCheckout()) {
        return;
    }
    var select2 = jQuery.fn.selectWoo || jQuery.fn.select2;

    jQuery('.kiriof-classic-shipping-method-select').each(function() {
        var $select = jQuery(this);
        var index = String($select.data('index') || '0');
        var $checkedMethod = jQuery('input.shipping_method[data-index="' + index + '"]:checked').first();

        if ($checkedMethod.length && String($select.val() || '') !== String($checkedMethod.val() || '')) {
            $select.val($checkedMethod.val());
        }

        if (!select2) {
            return;
        }

        if ($select.data('select2') || $select.data('selectWoo')) {
            select2.call($select, 'destroy');
        }

        select2.call($select, {
            width: '100%',
            minimumResultsForSearch: 8
        });

        $select.addClass('kiriof-classic-shipping-method-select--enhanced');
    });
}

function kiriofScheduleClassicShippingMethodSelectInit() {
    if (kiriofUsesClassicCheckout()) {
        return;
    }
    kiriofInitClassicShippingMethodSelect();

    jQuery.each([50, 250, 750], function(_, delay) {
        window.setTimeout(kiriofInitClassicShippingMethodSelect, delay);
    });
}

jQuery(document)
    .off('init_checkout.kiriofClassicShippingMethodSelect updated_checkout.kiriofClassicShippingMethodSelect updated_cart_totals.kiriofClassicShippingMethodSelect updated_shipping_method.kiriofClassicShippingMethodSelect wc_fragments_refreshed.kiriofClassicShippingMethodSelect')
    .on('init_checkout.kiriofClassicShippingMethodSelect updated_checkout.kiriofClassicShippingMethodSelect updated_cart_totals.kiriofClassicShippingMethodSelect updated_shipping_method.kiriofClassicShippingMethodSelect wc_fragments_refreshed.kiriofClassicShippingMethodSelect', function() {
        kiriofScheduleClassicShippingMethodSelectInit();
    });

jQuery(document)
    .off('change.kiriofClassicShippingMethodSelect', '.kiriof-classic-shipping-method-select')
    .on('change.kiriofClassicShippingMethodSelect', '.kiriof-classic-shipping-method-select', function() {
    if (kiriofUsesClassicCheckout()) {
        return;
    }
        var $select = jQuery(this);
        var selectedMethod = String($select.val() || '');
        var index = String($select.data('index') || '0');

        if (!selectedMethod) {
            return;
        }

        var $method = jQuery('input.shipping_method[data-index="' + index + '"]').filter(function() {
            return String(jQuery(this).val() || '') === selectedMethod;
        }).first();

        if (!$method.length) {
            return;
        }

        $method.prop('checked', true).trigger('change');
    });

function kiriofHandleCodInsurance(){
    if (kiriofUsesClassicCheckout()) {
        return;
    }
    if ( kiriofUpdatingCheckoutLock ) {
        kiriofPendingFeeRefresh = true;
        return;
    }
    if (kiriofBillingAddressConfig.isCheckout) {
        if ( kiriofIsBlockCheckoutContext() ) {
            kiriofCodInsurance();
            return;
        }
        jQuery(document.body).off('updated_checkout.kiriofFeeRefresh').one('updated_checkout.kiriofFeeRefresh', function() {
            kiriofCodInsurance();
        });
        jQuery( document.body ).trigger( 'update_checkout',{update_shipping_method:true} );
    }
}

function kiriofSetFeeSkeletonLoading(isLoading) {
    jQuery('#order_review').toggleClass('kiriof-fee-loading', !!isLoading);
}

function kiriofIsKiriminajaShippingMethod(method) {
    return typeof method === 'string' && method.indexOf('kiriminaja-official') === 0;
}

function kiriofRememberSelectedShippingMethod(method) {
    if (!kiriofIsKiriminajaShippingMethod(method)) {
        return;
    }

    kiriofPendingShippingMethod = method;
    kiriofPendingShippingMethodAt = Date.now();
    localStorage.setItem('chosen_shipping_method', method);
}

function kiriofGetPendingShippingMethod() {
    if (!kiriofPendingShippingMethod || Date.now() - kiriofPendingShippingMethodAt > 3000) {
        return '';
    }

    return kiriofPendingShippingMethod;
}

function kiriofRefreshBlockShippingRates() {
    if (typeof wp === 'undefined' || !wp.data || !wp.data.dispatch) {
        return;
    }

    try {
        var cartDispatch = wp.data.dispatch('wc/store/cart');
        if (cartDispatch && typeof cartDispatch.invalidateResolutionForStoreSelector === 'function') {
            cartDispatch.invalidateResolutionForStoreSelector('getShippingRates');
        }
        if (cartDispatch && typeof cartDispatch.invalidateResolutionForStore === 'function') {
            cartDispatch.invalidateResolutionForStore();
        }
    } catch(e) {}

    try {
        var coreDataDispatch = wp.data.dispatch('core/data');
        if (coreDataDispatch && typeof coreDataDispatch.invalidateResolution === 'function') {
            coreDataDispatch.invalidateResolution('wc/store/cart', 'getShippingRates', []);
        }
    } catch(e) {}
}

function kiriofScheduleBlockShippingRatesRefresh(delay) {
    delay = typeof delay === 'number' ? delay : 120;
    if (kiriofBlockRatesRefreshTimer) {
        clearTimeout(kiriofBlockRatesRefreshTimer);
    }
    kiriofBlockRatesRefreshTimer = setTimeout(function() {
        kiriofBlockRatesRefreshTimer = null;
        kiriofRefreshBlockShippingRates();
    }, delay);
}

function kiriofIsBlockCheckoutContext() {
    return jQuery('.wp-block-woocommerce-checkout, .wc-block-checkout, .wc-block-components-sidebar-layout').length > 0;
}

function kiriofUsesNativeBuyerCheckout() {
    return !!(window.kiriofBuyerCheckout && window.kiriofBuyerCheckout.active && jQuery('.wp-block-woocommerce-checkout, .wc-block-checkout, .wp-block-woocommerce-cart, .wc-block-cart').length);
}

function kiriofFindBlockShippingPackageId(rateId) {
    if (!rateId || typeof wp === 'undefined' || !wp.data || !wp.data.select) {
        return null;
    }

    try {
        var store = wp.data.select('wc/store/cart');
        if (!store || typeof store.getShippingRates !== 'function') {
            return null;
        }

        var packages = store.getShippingRates() || [];
        for (var i = 0; i < packages.length; i++) {
            var pkg = packages[i];
            var packageRates = pkg && pkg.shipping_rates ? pkg.shipping_rates : [];
            for (var j = 0; j < packageRates.length; j++) {
                if (packageRates[j] && packageRates[j].rate_id === rateId) {
                    return pkg.package_id || pkg.packageId || pkg.key || i;
                }
            }
        }
    } catch(e) {}

    if (kiriofIsBlockCheckoutContext() && jQuery('.wc-block-components-radio-control__input[value="' + rateId + '"]').length) {
        return 0;
    }

    return null;
}

function kiriofSelectBlockShippingRate(rateId) {
    if (!kiriofIsKiriminajaShippingMethod(rateId) || typeof wp === 'undefined' || !wp.data || !wp.data.dispatch) {
        return;
    }

    try {
        var cartDispatch = wp.data.dispatch('wc/store/cart');
        var packageId = kiriofFindBlockShippingPackageId(rateId);

        if (cartDispatch && typeof cartDispatch.selectShippingRate === 'function') {
            if (packageId !== null && typeof packageId !== 'undefined') {
                cartDispatch.selectShippingRate(rateId, packageId);
            } else {
                cartDispatch.selectShippingRate(rateId);
            }
        } else if (cartDispatch && typeof cartDispatch.setSelectedShippingRate === 'function') {
            cartDispatch.setSelectedShippingRate(rateId);
        }
    } catch(e) {}
}

function kiriofScheduleBlockCartDataRefresh(refreshKey, delay) {
    delay = typeof delay === 'number' ? delay : 240;

    if (
        refreshKey
        && refreshKey === kiriofLastBlockCartRefreshKey
        && Date.now() - kiriofLastBlockCartRefreshAt < 1200
    ) {
        return;
    }

    if (kiriofBlockCartRefreshTimer) {
        clearTimeout(kiriofBlockCartRefreshTimer);
    }

    kiriofBlockCartRefreshTimer = setTimeout(function() {
        kiriofBlockCartRefreshTimer = null;
        kiriofLastBlockCartRefreshKey = refreshKey || '';
        kiriofLastBlockCartRefreshAt = Date.now();
        kiriofRefreshBlockCartData();
    }, delay);
}

function kiriofRefreshBlockCartData() {
    if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
        try {
            var cartDispatch = wp.data.dispatch('wc/store/cart');
            if (cartDispatch && typeof cartDispatch.invalidateResolutionForStoreSelector === 'function') {
                cartDispatch.invalidateResolutionForStoreSelector('getCartData');
                cartDispatch.invalidateResolutionForStoreSelector('getCartTotals');
            }
        } catch(e) {}

        try {
            var coreDataDispatch = wp.data.dispatch('core/data');
            if (coreDataDispatch && typeof coreDataDispatch.invalidateResolution === 'function') {
                coreDataDispatch.invalidateResolution('wc/store/cart', 'getCartData', []);
                coreDataDispatch.invalidateResolution('wc/store/cart', 'getCartTotals', []);
            }
        } catch(e) {}
    }
}

function kiriofRefreshBlockPaymentMethodsData() {
    if (typeof wp === 'undefined' || !wp.data || !wp.data.dispatch) {
        return;
    }

    var selectors = [
        'getPaymentMethods',
        'getAvailablePaymentMethods',
        'getActivePaymentMethod',
        'getPaymentMethodData'
    ];

    try {
        var paymentDispatch = wp.data.dispatch('wc/store/payment');
        if (paymentDispatch && typeof paymentDispatch.invalidateResolutionForStoreSelector === 'function') {
            selectors.forEach(function(selector) {
                paymentDispatch.invalidateResolutionForStoreSelector(selector);
            });
        }
    } catch(e) {}

    try {
        var coreDataDispatch = wp.data.dispatch('core/data');
        if (coreDataDispatch && typeof coreDataDispatch.invalidateResolution === 'function') {
            selectors.forEach(function(selector) {
                coreDataDispatch.invalidateResolution('wc/store/payment', selector, []);
            });
        }
    } catch(e) {}
}

if (!jQuery('#kiriof-fee-skeleton-style').length) {
    jQuery('head').append('<style id="kiriof-fee-skeleton-style">#order_review.kiriof-fee-loading .shop_table{opacity:.65;position:relative}#order_review.kiriof-fee-loading .shop_table:after{content:"";position:absolute;inset:0;pointer-events:none;background:linear-gradient(90deg,rgba(255,255,255,0) 0%,rgba(255,255,255,.35) 50%,rgba(255,255,255,0) 100%);animation:kiriofFeeSkeletonShimmer 1.2s ease-in-out infinite}@keyframes kiriofFeeSkeletonShimmer{0%{transform:translateX(-100%)}100%{transform:translateX(100%)}}</style>');
}

function kiriofBlockExtensionCartUpdate(data) {
    if (typeof wp === 'undefined' || !wp.data || !wp.data.dispatch) {
        if (window.console) console.warn('[KiriminAja] wp.data.dispatch not available');
        return null;
    }

    try {
        var cartDispatch = wp.data.dispatch('wc/store/cart');
        if (cartDispatch && typeof cartDispatch.extensionCartUpdate === 'function') {
            if (window.console) console.log('[KiriminAja] Calling extensionCartUpdate', data);
            var result = cartDispatch.extensionCartUpdate({
                namespace: 'kiriminaja-official',
                data: {
                    shipping_metode_id: data.shipping_metode_id,
                    destination_id: data.destination_id,
                    destination_name: data.destination_name,
                    postcode: data.postcode,
                    payment_method: data.payment_method,
                    insurance: data.insurance,
                    force_insurance: data.force_insurance
                }
            });
            return result;
        }

        if (cartDispatch && typeof cartDispatch.invalidateResolutionForStore === 'function') {
            cartDispatch.invalidateResolutionForStore();
        }
    } catch(e) {
        if (window.console) console.error('[KiriminAja] extensionCartUpdate error:', e);
    }

    return null;
}

function kiriofGetDestinationId(different_address) {
    let blockDestinationSource = jQuery('[name="kiriminaja-official/kiriof_destination_area"], input[name*="kiriof_destination_area"], textarea[name*="kiriof_destination_area"]').not('select, .kiriof-block-district-select').first();
    let blockDestinationId = jQuery('.kiriof-block-district-select').val()
        || blockDestinationSource.val();

    if (blockDestinationId) {
        return blockDestinationId;
    }

    return (
        different_address == '0'
        ?
        (jQuery('#kiriof_destination_area option:selected').val() || jQuery('[name="kiriof_destination_area"]').val())
        :
        (jQuery('#kiriof_shipping_destination_area option:selected').val() || jQuery('[name="kiriof_shipping_destination_area"]').val())
    );
}

function kiriofNormalizePaymentMethod(paymentMethod) {
    if (!paymentMethod) {
        return '';
    }
    if (typeof paymentMethod === 'string') {
        return paymentMethod;
    }
    if (typeof paymentMethod === 'object') {
        return paymentMethod.paymentMethodSlug
            || paymentMethod.name
            || paymentMethod.id
            || paymentMethod.value
            || '';
    }
    return '';
}

function kiriofRememberPendingPaymentMethod(paymentMethod) {
    paymentMethod = kiriofNormalizePaymentMethod(paymentMethod);
    if (!paymentMethod) {
        return;
    }

    kiriofPendingPaymentMethod = paymentMethod;
    kiriofPendingPaymentMethodAt = Date.now();
}

function kiriofGetPendingPaymentMethod() {
    if (!kiriofPendingPaymentMethod || Date.now() - kiriofPendingPaymentMethodAt > 2500) {
        return '';
    }

    return kiriofPendingPaymentMethod;
}

function kiriofGetPaymentMethodFromElement(element) {
    var $element = jQuery(element);
    var paymentMethod = kiriofNormalizePaymentMethod(
        $element.val()
        || $element.attr('value')
        || $element.data('paymentMethod')
        || $element.attr('data-payment-method')
        || ''
    );

    if (paymentMethod) {
        return paymentMethod;
    }

    var $input = $element
        .find('input[name="payment_method"], input[name="radio-control-wc-payment-method-options"], input[type="radio"][value]')
        .addBack('input[name="payment_method"], input[name="radio-control-wc-payment-method-options"], input[type="radio"][value]')
        .first();

    paymentMethod = kiriofNormalizePaymentMethod($input.val() || $input.attr('value') || '');
    if (paymentMethod) {
        return paymentMethod;
    }

    var identifiers = [
        $element.attr('id') || '',
        $element.attr('for') || '',
        $element.attr('aria-labelledby') || ''
    ].join(' ');
    var match = identifiers.match(/(?:payment_method_|payment-method-options[-_]|wc-payment-method-options[-_])([a-z0-9_-]+)/i);

    return match ? kiriofNormalizePaymentMethod(match[1]) : '';
}

function kiriofGetPaymentMethod() {
    let payment_method = kiriofNormalizePaymentMethod(
        jQuery("[name=payment_method]:checked").val() || ''
    );

    if (!payment_method) {
        payment_method = kiriofGetPendingPaymentMethod();
    }

    if (!payment_method && typeof wp !== 'undefined' && wp.data && wp.data.select) {
        try {
            var paymentStore = wp.data.select('wc/store/payment');
            if (paymentStore && typeof paymentStore.getActivePaymentMethod === 'function') {
                payment_method = kiriofNormalizePaymentMethod(paymentStore.getActivePaymentMethod());
            }
            if (!payment_method && paymentStore && paymentStore.getActivePaymentMethod) {
                payment_method = kiriofNormalizePaymentMethod(paymentStore.getActivePaymentMethod);
            }
            if (!payment_method && paymentStore && typeof paymentStore.getPaymentMethodData === 'function') {
                var paymentData = paymentStore.getPaymentMethodData() || {};
                payment_method = kiriofNormalizePaymentMethod(paymentData.payment_method || paymentData.paymentMethod || paymentData.gateway || '');
            }
        } catch(e) {}
    }

    if (!payment_method) {
        var $codInput = jQuery('[name=payment_method][value="cod"]');
        var $checkedCodInput = $codInput.filter(':checked');
        var $activeCodWrapper = $codInput.closest('[aria-checked="true"], .is-active, .wc-block-components-radio-control-accordion-option--checked');
        if ($checkedCodInput.length || $activeCodWrapper.length) {
            payment_method = 'cod';
        }
    }

    return payment_method || '';
}

function kiriofGetSelectedBlockShippingMethod() {
    if (typeof wp === 'undefined' || !wp.data || !wp.data.select) {
        return '';
    }

    try {
        var cartStore = wp.data.select('wc/store/cart');
        var rates = cartStore && typeof cartStore.getShippingRates === 'function'
            ? cartStore.getShippingRates()
            : [];

        if (!rates || !rates.length) {
            return '';
        }

        for (var i = 0; i < rates.length; i++) {
            var pkg = rates[i];
            var packageRates = pkg && pkg.shipping_rates ? pkg.shipping_rates : [];
            for (var j = 0; j < packageRates.length; j++) {
                if (packageRates[j] && packageRates[j].selected) {
                    return packageRates[j].rate_id || packageRates[j].id || '';
                }
            }
        }
    } catch(e) {}

    return '';
}

function kiriofBuildFeeRefreshKey(data) {
    return [
        data.shipping_metode_id || '',
        data.destination_id || '',
        data.destination_name || '',
        data.postcode || '',
        data.payment_method || '',
        data.insurance || 0,
        data.force_insurance || 0
    ].join('|');
}

function kiriofCodInsurance(){
    if (kiriofUsesClassicCheckout()) {
        return;
    }
    if (window.kiriofBuyerCheckout && window.kiriofBuyerCheckout.pending) {
        return;
    }
    if (kiriofUsesNativeBuyerCheckout()) {
        return;
    }

    let different_address = jQuery(`[name="ship_to_different_address"]:checked`).length;

    // Read shipping method: traditional, block radio, or block data store
    let shipping_metode_id = kiriofGetPendingShippingMethod()
        || (kiriofIsBlockCheckoutContext() ? kiriofGetSelectedBlockShippingMethod() : '');
    shipping_metode_id = shipping_metode_id
        || jQuery('#shipping_method .shipping_method:checked').val()
        || jQuery('.wc-block-components-radio-control__input:checked').val();
    shipping_metode_id = shipping_metode_id || '';

    let destination_id = kiriofGetDestinationId(different_address);
    let destination_name = jQuery('.kiriof-block-district-select option:selected').text()
        || jQuery('[name="kiriof_destination_area_name"]').val()
        || jQuery('[name="kiriof_shipping_destination_area_name"]').val()
        || '';

    // Global insurance forced = always true
    let insurance = kiriofBillingAddressConfig.globalInsurance
        ? 1
        : (
            kiriofIsBlockCheckoutContext()
            ? 0
            : (
                different_address == '0'
                ?
                kiriofGetClassicInsuranceValue()
                :
                kiriofGetClassicInsuranceValue()
            )
        );

    let payment_method = kiriofGetPaymentMethod();


    let data = {
        action:'kiriof_get_data_after_update_checkout',
        nonce:(typeof kiriofAjax !== 'undefined' && kiriofAjax.update_checkout_nonce)
            ? kiriofAjax.update_checkout_nonce
            : kiriofBillingAddressConfig.updateCheckoutNonce || '',
        shipping_metode_id : (typeof shipping_metode_id === 'undefined' ? '' : shipping_metode_id),
        destination_id,
        destination_name,
        postcode: kiriofGetCurrentPostcodeKey(),
        payment_method,
        insurance : (typeof insurance === 'undefined' ? 0 : parseInt(insurance)),
        force_insurance : parseInt(jQuery('[name=kiriof_force_insurance]').val() || 0)
    };

    let refreshKey = kiriofBuildFeeRefreshKey(data);
    let isBlockCheckout = kiriofIsBlockCheckoutContext();

    if (
        isBlockCheckout
        && refreshKey === kiriofLastCompletedFeeRefreshKey
        && Date.now() - kiriofLastCompletedFeeRefreshAt < 1200
    ) {
        return;
    }

    if (kiriofUpdatingCheckoutLock) {
        if (refreshKey === kiriofInFlightFeeRefreshKey) {
            return;
        }

        kiriofPendingFeeRefresh = true;
        kiriofPendingFeeRefreshKey = refreshKey;

        if (kiriofFeeRefreshRequest && kiriofFeeRefreshRequest.readyState !== 4) {
            try {
                kiriofFeeRefreshRequest.abort();
            } catch(e) {}
        }

        return;
    }

    kiriofUpdatingCheckoutLock = true;
    kiriofInFlightFeeRefreshKey = refreshKey;
    kiriofPendingFeeRefresh = false;
    kiriofPendingFeeRefreshKey = '';

    if (isBlockCheckout) {
        var blockResult = kiriofBlockExtensionCartUpdate(data);
        var completeBlockRefresh = function() {
            kiriofUpdatingCheckoutLock = false;
            kiriofInFlightFeeRefreshKey = '';
            kiriofLastCompletedFeeRefreshKey = refreshKey;
            kiriofLastCompletedFeeRefreshAt = Date.now();

            kiriofScheduleBlockShippingRatesRefresh(180);
            kiriofScheduleBlockCartDataRefresh(refreshKey, 260);
            kiriofRefreshBlockPaymentMethodsData();

            if (kiriofPendingFeeRefresh && kiriofPendingFeeRefreshKey && kiriofPendingFeeRefreshKey !== refreshKey) {
                kiriofPendingFeeRefresh = false;
                kiriofPendingFeeRefreshKey = '';
                window.setTimeout(kiriofCodInsurance, 150);
            } else {
                kiriofPendingFeeRefresh = false;
                kiriofPendingFeeRefreshKey = '';
            }
        };

        if (blockResult && typeof blockResult.then === 'function') {
            blockResult.then(completeBlockRefresh).catch(completeBlockRefresh);
        } else {
            completeBlockRefresh();
        }
        return;
    }

    kiriofFeeRefreshRequest = jQuery.ajax({
                url:(typeof kiriofAjax !== 'undefined' && kiriofAjax.ajaxurl)
                    ? kiriofAjax.ajaxurl
                    : kiriofBillingAddressConfig.ajaxUrl || '',
                type: 'post',
                data: data,
                dataType:'JSON',
                dataFilter: function(raw) {
                    return kiriofExtractJsonResponseText(raw);
                },
                beforeSend:function(){
                    jQuery('#order_review').find('.shop_table').block({ message: null });
                    kiriofSetFeeSkeletonLoading(true);
                },
                success:function(response){
                    jQuery('[name=kiriof_force_insurance]').val(response?.data?.force_insurance);
                    if (!kiriofIsBlockCheckoutContext()) {
                        jQuery(document.body).trigger('update_checkout', { update_shipping_method: false });
                    }

                    // Block checkout: the React sidebar (order summary) reads from the
                    // Store API, not from classic checkout fragments. After the server
                    // session is updated, tell WC blocks to re-fetch the cart so the
                    // selected shipping method and fees appear in the summary sidebar.
                    if (kiriofIsBlockCheckoutContext()) {
                        kiriofScheduleBlockShippingRatesRefresh(80);
                        kiriofScheduleBlockCartDataRefresh(refreshKey, 120);
                    }

                },
                error:function(xhr, textStatus){
                    if (textStatus === 'abort') {
                        return;
                    }
                    if (window.console) {
                        console.warn('[KiriminAja] Checkout fee refresh AJAX failed', {
                            status: xhr.status,
                            textStatus: textStatus
                        });
                    }
                    if (String(xhr.status) !== '200') {
                        alert("Sorry System Trouble Error Code : "+xhr.status);
                    }
                 },
                complete:function(){
                    kiriofSetFeeSkeletonLoading(false);
                    jQuery('#order_review').find('.shop_table').unblock();
                    kiriofUpdatingCheckoutLock = false;
                    kiriofFeeRefreshRequest = null;
                    kiriofInFlightFeeRefreshKey = '';
                    kiriofLastCompletedFeeRefreshKey = refreshKey;
                    kiriofLastCompletedFeeRefreshAt = Date.now();

                    if (kiriofPendingFeeRefresh) {
                        var pendingKey = kiriofPendingFeeRefreshKey;
                        kiriofPendingFeeRefresh = false;
                        kiriofPendingFeeRefreshKey = '';

                        if (!pendingKey || pendingKey !== refreshKey) {
                            window.setTimeout(kiriofCodInsurance, 150);
                        }
                    }
                 }
    });
}
