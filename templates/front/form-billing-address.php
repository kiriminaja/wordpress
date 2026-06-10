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
 * @var array  $kiriof_saved_destination_map
 * @var string $kiriof_saved_checkout_postcode
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

        var kiriofUpdatingCheckoutLock = false;
        var kiriofTriggeredInitialShippingUpdate = false;
        var kiriofSavedDistrictByPostcode = <?php echo wp_json_encode( is_array( $kiriof_saved_destination_map ) ? $kiriof_saved_destination_map : array() ); ?>;
        var kiriofSavedCheckoutPostcode = <?php echo wp_json_encode( (string) $kiriof_saved_checkout_postcode ); ?>;

        jQuery(document).ready(function($) {
            <?php if ( $kiriof_global_insurance ) : ?>
            // Global insurance forced — check and disable the checkbox
            var $ins = jQuery('#kiriof_insurance, #kiriof_shipping_insurance');
            $ins.prop('checked', true).prop('disabled', true);
            $ins.closest('.form-row').css('opacity', '0.6');
            <?php endif; ?>

            getSearchAreaKelurahan();
            changeDistrict();
            kiriofInitBlockCheckoutCompatibility();

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



                // Re-bind handlers after AJAX fragment refresh (theme compatibility).
                // Do not call kiriofCodInsurance() here: its success callback triggers
                // update_checkout once so WooCommerce can render native fee rows. Calling
                // it again from updated_checkout creates an endless loading loop.
                jQuery(document.body).on( 'updated_checkout', function() {
                    kiriofChangeCodPayment();
                    kiriofChangeDifferentAddress();
                });

            <?php endif; ?>
        }); 

        function kiriofInitBlockCheckoutCompatibility() {
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
            
                // Block checkout: listen for payment method changes via WC payment store.
                // COD radios in Woo Blocks do not use the classic name=payment_method input.
                var kiriofLastPaymentMethod = '';
                wp.data.subscribe(function() {
                    try {
                        var currentPaymentMethod = kiriofGetPaymentMethod();
                        if (!currentPaymentMethod || currentPaymentMethod === kiriofLastPaymentMethod) return;
                        kiriofLastPaymentMethod = currentPaymentMethod;
                        setTimeout(function() { kiriofCodInsurance(); }, 250);
                    } catch(e) {}
                });
            
                // Dynamic District options from postcode
                var kiriofLastPostcode = '';
                var kiriofLastTypedPostcode = '';
                var kiriofLastTypedPostcodeAt = 0;
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

                function kiriofGetRelevantPostcodeInputs() {
                    var useShippingAddress = jQuery('[name="ship_to_different_address"]:checked').length > 0;
                    var primarySelector = useShippingAddress
                        ? 'input#shipping-postcode, input[name="shipping_postcode"], input[name="shipping-postcode"]'
                        : 'input#billing-postcode, input[name="billing_postcode"], input[name="billing-postcode"]';

                    var $primary = jQuery(primarySelector).filter(':enabled').filter(':visible');
                    if ($primary.length) {
                        return $primary;
                    }

                    return jQuery(kiriofBlockPostcodeSelectors).filter(':enabled').filter(function() {
                        return jQuery(this).is(':visible') && jQuery(this).attr('type') !== 'hidden';
                    });
                }

                function kiriofGetFocusedPostcodeInput() {
                    return kiriofGetRelevantPostcodeInputs().filter(function() {
                        return this === document.activeElement;
                    }).first();
                }

                function kiriofRestoreSavedPostcodeField() {
                    var savedPostcode = kiriofNormalizePostcode(kiriofSavedCheckoutPostcode);
                    if (!savedPostcode) {
                        return false;
                    }

                    var restored = false;
                    kiriofGetRelevantPostcodeInputs().each(function() {
                        var $input = jQuery(this);
                        var currentValue = kiriofNormalizePostcode($input.val());
                        if (currentValue === savedPostcode || currentValue) {
                            return;
                        }

                        $input.val(savedPostcode).trigger('input').trigger('change');
                        restored = true;
                    });

                    if (restored) {
                        kiriofLastTypedPostcode = savedPostcode;
                        kiriofLastTypedPostcodeAt = Date.now();
                    }

                    return restored;
                }

                function kiriofUpdateBlockCheckoutPostcode(postcode) {
                    postcode = kiriofNormalizePostcode(postcode);

                    if (typeof wp === 'undefined' || !wp.data || !wp.data.select || !wp.data.dispatch) {
                        return;
                    }

                    try {
                        var checkoutStore = wp.data.select('wc/store/checkout');
                        var checkoutDispatch = wp.data.dispatch('wc/store/checkout');
                        var editingShippingAddress = checkoutStore && typeof checkoutStore.getEditingShippingAddress === 'function'
                            ? (checkoutStore.getEditingShippingAddress() || {})
                            : {};
                        var editingBillingAddress = checkoutStore && typeof checkoutStore.getEditingBillingAddress === 'function'
                            ? (checkoutStore.getEditingBillingAddress() || {})
                            : {};

                        editingShippingAddress = Object.assign({}, editingShippingAddress, { postcode: postcode });
                        editingBillingAddress = Object.assign({}, editingBillingAddress, { postcode: postcode });

                        if (checkoutDispatch && typeof checkoutDispatch.setEditingShippingAddress === 'function') {
                            checkoutDispatch.setEditingShippingAddress(editingShippingAddress);
                        }

                        if (checkoutDispatch && typeof checkoutDispatch.setEditingBillingAddress === 'function') {
                            checkoutDispatch.setEditingBillingAddress(editingBillingAddress);
                        }
                    } catch(e) {}

                    try {
                        var cartStore = wp.data.select('wc/store/cart');
                        var cartDispatch = wp.data.dispatch('wc/store/cart');
                        if (!cartStore || !cartDispatch) {
                            return;
                        }

                        var billingAddress = cartStore.getBillingAddress ? (cartStore.getBillingAddress() || {}) : {};
                        var shippingAddress = cartStore.getShippingAddress ? (cartStore.getShippingAddress() || {}) : {};

                        billingAddress = Object.assign({}, billingAddress, { postcode: postcode });
                        shippingAddress = Object.assign({}, shippingAddress, { postcode: postcode });

                        if (typeof cartDispatch.setBillingAddress === 'function') {
                            cartDispatch.setBillingAddress(billingAddress);
                        }

                        if (typeof cartDispatch.setShippingAddress === 'function') {
                            cartDispatch.setShippingAddress(shippingAddress);
                        }
                    } catch(e) {}
                }
            
                function kiriofGetCheckoutPostcodeFromDom() {
                    var postcode = '';
                    var $focused = kiriofGetFocusedPostcodeInput();
                    if ($focused.length) {
                        return String($focused.val() || '');
                    }

                    kiriofGetRelevantPostcodeInputs().each(function() {
                        var val = jQuery(this).val();
                        if (val && String(val).length >= 3) {
                            postcode = String(val);
                            return false;
                        }
                    });
                    return postcode;
                }
            
                function kiriofGetBlockDistrictField() {
                    function kiriofIsBlockDistrictSourceField($field) {
                        var name = String($field.attr('name') || '');
                        var id = String($field.attr('id') || '');

                        if (!name && !id) {
                            return false;
                        }

                        if (
                            name.indexOf('kiriof_destination_area_name') !== -1 ||
                            name.indexOf('kiriof_shipping_destination_area_name') !== -1
                        ) {
                            return false;
                        }

                        return (
                            name === kiriofFieldId ||
                            name.slice(-kiriofFieldId.length) === kiriofFieldId ||
                            name.indexOf('/kiriof_destination_area') !== -1 ||
                            id.indexOf('kiriof_destination_area') !== -1 ||
                            id.indexOf('kiriof-destination-area') !== -1
                        );
                    }

                    var selectors = [
                        '.kiriof-block-district-source',
                        '[name="' + kiriofFieldId + '"]',
                        'input[name*="kiriof_destination_area"]',
                        'textarea[name*="kiriof_destination_area"]',
                        'input[id*="kiriof_destination_area"]',
                        'textarea[id*="kiriof_destination_area"]',
                        'input[id*="kiriof-destination-area"]',
                        'textarea[id*="kiriof-destination-area"]'
                    ].join(',');
            
                    var $field = jQuery(selectors).filter(function() {
                                    var $currentField = jQuery(this);

                                    return !$currentField.is('select')
                                        && !$currentField.hasClass('kiriof-block-district-select')
                                        && kiriofIsBlockDistrictSourceField($currentField);
                    }).first();
            
                    return $field;
                }

                function kiriofEnsureLegacyBlockDistrictMirror() {
                    var $existing = jQuery('#kiriof-block-district-mirror');
                    if ($existing.length) {
                        return $existing;
                    }

                    var $container = jQuery('#kiriof_destination_area_group > div').first();
                    if (!$container.length) {
                        $container = jQuery('<div style="display: none"></div>').appendTo('#kiriof_destination_area_group');
                    }

                    return jQuery('<input type="hidden" id="kiriof-block-district-mirror" name="kiriof_destination_area" value="">')
                        .appendTo($container);
                }

                function kiriofSetCheckoutTokenValue(isCompleted) {
                    jQuery('[name="kiriof_checkout_token"]').val(isCompleted ? '1' : '');
                }

                            function kiriofSyncBlockDistrictSourceField(destinationId, destinationName, options) {
                                options = options || {};

                                var $field = kiriofGetBlockDistrictField();
                                if (!$field.length) {
                                    return false;
                                }

                                $field.addClass('kiriof-block-district-source').attr('type', 'hidden').removeAttr('required').hide();

                                var $wrapper = $field.closest('.wc-block-components-text-input, .wc-block-components-address-form__input').first();
                                if (!$wrapper.length) {
                                    $wrapper = $field.parent();
                                }
                                $wrapper.addClass('kiriof-block-district-source-wrapper').hide();

                                var normalizedValue = String(destinationId || '');
                                var currentValue = String($field.val() || '');
                                var sourceField = $field.get(0);
                                if (sourceField) {
                                    sourceField.removeAttribute('required');
                                    sourceField.required = false;
                                    sourceField.setAttribute('aria-invalid', normalizedValue ? 'false' : 'true');
                                    if (normalizedValue && typeof sourceField.setCustomValidity === 'function') {
                                        sourceField.setCustomValidity('');
                                    }
                                }
                                if (currentValue !== normalizedValue) {
                                    if (sourceField) {
                                        try {
                                            var valueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');
                                            if (valueSetter && typeof valueSetter.set === 'function') {
                                                valueSetter.set.call(sourceField, normalizedValue);
                                            } else {
                                                sourceField.value = normalizedValue;
                                            }
                                        } catch(e) {
                                            sourceField.value = normalizedValue;
                                        }

                                        sourceField.setAttribute('value', normalizedValue);
                                        sourceField.dispatchEvent(new Event('input', { bubbles: true }));
                                        if (options.triggerChange) {
                                            sourceField.dispatchEvent(new Event('change', { bubbles: true }));
                                        }
                                    }
                                }

                                kiriofEnsureLegacyBlockDistrictMirror().val(normalizedValue);
                                kiriofSetCheckoutTokenValue(!!normalizedValue);

                                if (typeof destinationName !== 'undefined') {
                                    jQuery('[name="kiriof_destination_area_name"]').val(destinationName || '');
                                    jQuery('[name="kiriof_shipping_destination_area_name"]').val(destinationName || '');
                                }

                                if (normalizedValue) {
                                    try {
                                        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
                                            var validationDispatch = wp.data.dispatch('wc/store/validation');
                                            if (validationDispatch && typeof validationDispatch.clearValidationErrors === 'function') {
                                                validationDispatch.clearValidationErrors([
                                                    'shipping_' + kiriofFieldId,
                                                    'billing_' + kiriofFieldId,
                                                    kiriofFieldId,
                                                ]);
                                            } else if (validationDispatch && typeof validationDispatch.clearValidationError === 'function') {
                                                validationDispatch.clearValidationError('shipping_' + kiriofFieldId);
                                                validationDispatch.clearValidationError('billing_' + kiriofFieldId);
                                                validationDispatch.clearValidationError(kiriofFieldId);
                                            }
                                        }
                                    } catch(e) {}
                                }

                                return true;
                            }
            
                function kiriofUpdateCheckoutAdditionalFields(val) {
                    if (typeof wp === 'undefined' || !wp.data || !wp.data.select || !wp.data.dispatch) {
                        return;
                    }
            
                    try {
                        var checkoutStore = wp.data.select('wc/store/checkout');
                        var checkoutDispatch = wp.data.dispatch('wc/store/checkout');
                        if (checkoutDispatch && typeof checkoutDispatch.setAdditionalFields === 'function') {
                            var additionalFields = checkoutStore && typeof checkoutStore.getAdditionalFields === 'function'
                                ? (checkoutStore.getAdditionalFields() || {})
                                : {};

                            additionalFields[kiriofFieldId] = val;
                            checkoutDispatch.setAdditionalFields(additionalFields);
                        }

                        var editingShippingAddress = checkoutStore && typeof checkoutStore.getEditingShippingAddress === 'function'
                            ? (checkoutStore.getEditingShippingAddress() || {})
                            : {};
                        var editingBillingAddress = checkoutStore && typeof checkoutStore.getEditingBillingAddress === 'function'
                            ? (checkoutStore.getEditingBillingAddress() || {})
                            : {};

                        editingShippingAddress = Object.assign({}, editingShippingAddress);
                        editingBillingAddress = Object.assign({}, editingBillingAddress);

                        editingShippingAddress[kiriofFieldId] = val;
                        editingBillingAddress[kiriofFieldId] = val;

                        if (typeof checkoutDispatch.setEditingShippingAddress === 'function') {
                            checkoutDispatch.setEditingShippingAddress(editingShippingAddress);
                        }

                        if (typeof checkoutDispatch.setEditingBillingAddress === 'function') {
                            checkoutDispatch.setEditingBillingAddress(editingBillingAddress);
                        }
                    } catch(e) {}

                    try {
                        var cartStore = wp.data.select('wc/store/cart');
                        var cartDispatch = wp.data.dispatch('wc/store/cart');
                        if (!cartStore || !cartDispatch) {
                            return;
                        }

                        var billingAddress = cartStore.getBillingAddress ? (cartStore.getBillingAddress() || {}) : {};
                        var shippingAddress = cartStore.getShippingAddress ? (cartStore.getShippingAddress() || {}) : {};

                        billingAddress = Object.assign({}, billingAddress);
                        shippingAddress = Object.assign({}, shippingAddress);

                        billingAddress[kiriofFieldId] = val;
                        shippingAddress[kiriofFieldId] = val;

                        if (typeof cartDispatch.setBillingAddress === 'function') {
                            cartDispatch.setBillingAddress(billingAddress);
                        }

                        if (typeof cartDispatch.setShippingAddress === 'function') {
                            cartDispatch.setShippingAddress(shippingAddress);
                        }
                    } catch(e) {}
                }
            
                function kiriofGetDestinationAreaAjaxData(val, label, different_address) {
                    return {
                        action: 'kiriof_get_destination_area',
                        val: val,
                        insurance: <?php echo $kiriof_global_insurance ? '1' : '0'; ?>,
                        different_address: different_address,
                        text: label || '',
                        postcode: kiriofGetCurrentPostcodeKey(),
                        payment_method: kiriofGetPaymentMethod(),
                        nonce: "<?php echo esc_js( wp_create_nonce('kiriof-destination') ); ?>",
                        country: jQuery('#billing_country').find(':selected').val() || 'ID'
                    };
                }
            
                function kiriofPersistDestinationArea(val, label, different_address, done) {
                    if (!val) {
                        if (typeof done === 'function') done(false);
                        return;
                    }
                    jQuery.ajax({
                        url: "<?php echo esc_url( admin_url('admin-ajax.php') ); ?>",
                        type: 'post',
                        data: kiriofGetDestinationAreaAjaxData(val, label, different_address),
                        dataType: 'JSON',
                        complete: function(response) {
                            var ok = false;
                            try {
                                ok = !!(response && response.responseJSON && response.responseJSON.success);
                            } catch(e) {}
                            if (typeof done === 'function') done(ok);
                        }
                    });
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
            
                var kiriofLastDistrictResults = [];
                var kiriofPendingDistrictRestore = false;
                var kiriofDistrictResultsLoading = false;

                function kiriofNormalizePostcode(postcode) {
                    return String(postcode || '').replace(/\s+/g, '').trim();
                }
            
                function kiriofRenderBlockDistrictSelect(results) {
                    if (!results || !results.length) return false;
                    kiriofLastDistrictResults = results;
            
                    var $field = kiriofGetBlockDistrictField();
                    if (!$field.length) return false;
            
                    // Woo Blocks renders this as a React-controlled text input.
                    // Hide the React source field and render our selectable UI at
                    // the block field wrapper so React re-renders do not remove it.
                    kiriofSyncBlockDistrictSourceField($field.val(), undefined);
            
                    var $wrapper = $field.closest('.wc-block-components-text-input, .wc-block-components-address-form__input').first();
                    if (!$wrapper.length) {
                        $wrapper = $field.parent();
                    }
                    $wrapper.addClass('kiriof-block-district-source-wrapper').hide();
            
                    var $select = jQuery('.kiriof-block-district-select');
                    if (!$select.length) {
                        var $fieldWrapper = jQuery('<div class="wc-block-components-address-form__state wc-block-components-state-input kiriof-block-district-field-wrapper"></div>');
                        var $selectWrapper = jQuery('<div class="wc-blocks-components-select kiriof-block-district-select-wrapper"></div>');
                        var $container = jQuery('<div class="wc-blocks-components-select__container kiriof-block-district-select-container"></div>');
                        var $label = jQuery('<label for="kiriof-block-district-select" class="wc-blocks-components-select__label"><?php echo esc_js(__('District','kiriminaja-official')); ?></label>');
                        $select = jQuery('<select size="1" class="wc-blocks-components-select__select kiriof-block-district-select" id="kiriof-block-district-select" aria-invalid="false" autocomplete="section-shipping shipping address-level3"></select>');
                        var $expand = jQuery('<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" class="wc-blocks-components-select__expand" aria-hidden="true" focusable="false"><path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path></svg>');
                        $container.append($label, $select, $expand);
                        $selectWrapper.append($container);
                        $fieldWrapper.append($selectWrapper);
                        $wrapper.after($fieldWrapper);
                    }
            
                    var currentPostcode = kiriofGetCurrentPostcodeKey();
                    var savedDistrict = kiriofGetSavedDistrictForPostcode(currentPostcode);
                    // MutationObserver may fire before React has applied the controlled value to
                    // the input's DOM property, so $field.val() can be '' even for logged-in users
                    // who have a saved district in their WC customer meta. Fall back to the WC
                    // cart data store (getBillingAddress/getShippingAddress) which always reflects
                    // the customer's persisted address, including additional address fields.
                    var currentValue = $field.val()
                        || kiriofGetStoredDistrictIdFromWcStore()
                        || ($select.length ? $select.val() : '')
                        || (savedDistrict ? String(savedDistrict.destination_id || '') : '');
                    var currentName = jQuery('[name="kiriof_destination_area_name"]').val() || (savedDistrict ? String(savedDistrict.destination_name || '') : '');
                    var hasMatchingSavedDistrict = false;
                    var placeholderSelected = currentValue ? '' : ' selected';
                    var html = '<option value="" data-alternate-values="[<?php echo esc_js(__('Select District','kiriminaja-official')); ?>]" disabled' + placeholderSelected + '><?php echo esc_js(__('Select District','kiriminaja-official')); ?></option>';
                    results.forEach(function(d) {
                        var selected = String(d.id) === String(currentValue) ? ' selected' : '';
                        html += '<option value="' + d.id + '" data-alternate-values="[' + d.text + ']"' + selected + '>' + d.text + '</option>';
                        if (String(d.id) === String(currentValue)) {
                            currentName = d.text;
                            hasMatchingSavedDistrict = true;
                        }
                    });

                    if (currentValue && !hasMatchingSavedDistrict) {
                        currentValue = '';
                        currentName = '';
                    }
            
                                $select.html(html).val(currentValue);
                                kiriofSyncBlockDistrictSourceField(currentValue, currentName);

                    if (currentValue && currentName) {
                        kiriofUpdateCheckoutAdditionalFields(String(currentValue));
                        kiriofRememberDistrictForPostcode(currentPostcode, currentValue, currentName);
                    } else {
                                    kiriofSyncBlockDistrictSourceField('', '');
                    }
            
                    return true;
                }

                function kiriofRestoreSavedCheckoutState() {
                    kiriofRestoreSavedPostcodeField();
                    if (kiriofLastDistrictResults.length && !jQuery('.kiriof-block-district-select').length) {
                        kiriofRenderBlockDistrictSelect(kiriofLastDistrictResults);
                    }
                    kiriofRestoreSavedDistrictForCurrentPostcode();
                    kiriofSyncBlockDistrictWarningState();
                }
            
                if (typeof MutationObserver !== 'undefined') {
                    var kiriofDistrictObserver = new MutationObserver(function() {
                        if (kiriofLastDistrictResults.length && !jQuery('.kiriof-block-district-select').length) {
                            kiriofRenderBlockDistrictSelect(kiriofLastDistrictResults);
                            kiriofRestoreSavedCheckoutState();
                            return;
                        }

                        var selectValue = String(jQuery('.kiriof-block-district-select').val() || '');
                        if (selectValue) {
                            kiriofSyncBlockDistrictSourceField(
                                selectValue,
                                jQuery('.kiriof-block-district-select option:selected').text() || jQuery('[name="kiriof_destination_area_name"]').val() || ''
                            );
                        }
                    });
                    kiriofDistrictObserver.observe(document.body, { childList: true, subtree: true });
                }
            
                function kiriofFetchDistricts(postcode) {
                    postcode = kiriofNormalizePostcode(postcode);
                    var activePostcode = kiriofNormalizePostcode(kiriofGetCheckoutPostcodeFromDom());
                    if (activePostcode && postcode && activePostcode !== postcode) {
                        var recentlyTyped = kiriofLastTypedPostcodeAt && (Date.now() - kiriofLastTypedPostcodeAt) < 2500;
                        if (recentlyTyped || kiriofGetFocusedPostcodeInput().length) {
                            postcode = activePostcode;
                        }
                    }
                    if (!postcode || postcode === kiriofLastPostcode || postcode.length < 3) return;
                    kiriofLastPostcode = postcode;
                    // Also check WC store so the "loading" state is shown for logged-in users
                    // whose district is saved in customer meta but not yet in kiriofSavedDistrictByPostcode.
                    kiriofPendingDistrictRestore = !!kiriofGetSavedDistrictForPostcode(postcode) || !!kiriofGetStoredDistrictIdFromWcStore();
                    kiriofDistrictResultsLoading = true;
                    kiriofResetBlockDistrictState({
                        silentWarning: kiriofPendingDistrictRestore,
                        skipStoreSync: true
                    });
            
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
                            kiriofDistrictResultsLoading = false;
                            if (!response || !response.data || !response.data.length) {
                                kiriofPendingDistrictRestore = false;
                                kiriofSyncBlockDistrictWarningState();
                                return;
                            }
                            kiriofRenderBlockDistrictSelect(response.data);
                            kiriofRestoreSavedDistrictForCurrentPostcode();
                            kiriofSyncBlockDistrictWarningState();
                        },
                        error: function() {
                            kiriofDistrictResultsLoading = false;
                            kiriofPendingDistrictRestore = false;
                            kiriofSyncBlockDistrictWarningState();
                        }
                    });
                }

                function kiriofGetCurrentPostcodeKey() {
                    var postcode = kiriofGetCheckoutPostcodeFromDom();
                    if (!postcode && kiriofSavedCheckoutPostcode) {
                        postcode = kiriofSavedCheckoutPostcode;
                    }
                    if (!postcode && typeof wp !== 'undefined' && wp.data && wp.data.select) {
                        try {
                            var store = wp.data.select('wc/store/cart');
                            var shipping = store && store.getShippingAddress ? store.getShippingAddress() : {};
                            var billing = store && store.getBillingAddress ? store.getBillingAddress() : {};
                            postcode = (shipping && shipping.postcode) || (billing && billing.postcode) || '';
                        } catch(e) {}
                    }

                    return kiriofNormalizePostcode(postcode);
                }

                function kiriofGetSavedDistrictForPostcode(postcode) {
                    postcode = kiriofNormalizePostcode(postcode);
                    if (!postcode || !kiriofSavedDistrictByPostcode || !kiriofSavedDistrictByPostcode[postcode]) {
                        return null;
                    }

                    return kiriofSavedDistrictByPostcode[postcode];
                }

                function kiriofRememberDistrictForPostcode(postcode, destinationId, destinationName) {
                    postcode = kiriofNormalizePostcode(postcode);
                    if (!postcode || !destinationId) {
                        return;
                    }

                    kiriofSavedDistrictByPostcode[postcode] = {
                        destination_id: String(destinationId),
                        destination_name: destinationName || ''
                    };
                }

                /**
                 * Read the saved kiriof_destination_area value from the WC cart data store.
                 * For logged-in users, WC persists additional address fields to customer meta,
                 * so this works even when the WC session is fresh (e.g. after session expiry).
                 */
                function kiriofGetStoredDistrictIdFromWcStore() {
                    try {
                        if (typeof wp === 'undefined' || !wp.data || !wp.data.select) return '';
                        var cartStore = wp.data.select('wc/store/cart');
                        if (!cartStore) return '';
                        var billing  = cartStore.getBillingAddress  ? cartStore.getBillingAddress()  : {};
                        var shipping = cartStore.getShippingAddress ? cartStore.getShippingAddress() : {};
                        return String(
                            (shipping && shipping[kiriofFieldId]) ||
                            (billing  && billing[kiriofFieldId])  || ''
                        );
                    } catch(e) { return ''; }
                }

                function kiriofGetBlockShippingOptionsContainer() {
                    return jQuery('.wp-block-woocommerce-checkout-shipping-methods-block, .wc-block-components-shipping-rates-control, .wc-block-checkout__shipping-method, .wc-block-components-checkout-step--shipping-method').first();
                }

                function kiriofEnsureBlockDistrictWarning() {
                    // Place the warning inside the shipping options step using the native
                    // WC "no shipping address" structure so it blends with the WC UI.
                    var $shippingMethodStep = jQuery(
                        '.wp-block-woocommerce-checkout-shipping-methods-block, ' +
                        '.wc-block-checkout__shipping-option, ' +
                        '.wc-block-components-checkout-step--shipping-method'
                    ).first();

                    // Fall back to the shipping address section if the shipping methods
                    // step is not in the DOM yet.
                    if (!$shippingMethodStep.length) {
                        $shippingMethodStep = jQuery(
                            '.wp-block-woocommerce-checkout-shipping-address-block, ' +
                            '.wc-block-components-checkout-step--shipping-address, ' +
                            '.wc-block-checkout__shipping-address'
                        ).first();
                    }

                    if (!$shippingMethodStep.length) {
                        return jQuery();
                    }

                    var $warning = $shippingMethodStep.find('.kiriof-block-district-warning');
                    if (!$warning.length) {
                        // Use the exact same markup & classes WooCommerce uses for its
                        // "Enter a shipping address to view shipping options." message
                        // so the notice looks completely native.
                        $warning = jQuery(
                            '<div class="wc-block-components-shipping-rates-control kiriof-block-district-warning" role="alert">' +
                                '<div class="wc-block-components-shipping-rates-control__package">' +
                                    '<p role="status" aria-live="polite" ' +
                                       'class="wc-block-components-shipping-rates-control__no-shipping-address-message">' +
                                        '<?php echo esc_js(__('Please select your District to view shipping options.', 'kiriminaja-official')); ?>' +
                                    '</p>' +
                                '</div>' +
                            '</div>'
                        ).hide();

                        // Inject inside the step content so it appears where rates normally live.
                        var $stepContent = $shippingMethodStep.find('.wc-block-components-checkout-step__content').first();
                        if ($stepContent.length) {
                            $stepContent.prepend($warning);
                        } else {
                            var $headingContainer = $shippingMethodStep.find('.wc-block-components-checkout-step__heading-container').first();
                            if ($headingContainer.length) {
                                $warning.insertAfter($headingContainer);
                            } else {
                                $shippingMethodStep.prepend($warning);
                            }
                        }
                    }

                    return $warning;
                }

                function kiriofHasValidBlockDistrict() {
                    var districtId = kiriofGetDestinationId(jQuery('[name="ship_to_different_address"]:checked').length);
                    if (districtId && String(districtId).trim() !== '' && String(districtId) !== '0') {
                        return true;
                    }
                    // Logged-in users show a compact address summary (no visible form inputs).
                    // Fall back to the saved district map for the current postcode.
                    var postcode = kiriofGetCurrentPostcodeKey();
                    if (postcode) {
                        var saved = kiriofGetSavedDistrictForPostcode(postcode);
                        if (saved && saved.destination_id && String(saved.destination_id) !== '0') {
                            return true;
                        }
                    }
                    // Final fallback: WC store holds the persisted additional address field value
                    // for logged-in users even before kiriofSavedDistrictByPostcode is populated.
                    var storedId = kiriofGetStoredDistrictIdFromWcStore();
                    if (storedId && String(storedId) !== '0') {
                        return true;
                    }
                    return false;
                }

                function kiriofClearBlockShippingRatesFromStore() {
                    // Guard against re-entrant calls (e.g. triggered by wp.data.subscribe).
                    if (kiriofClearBlockShippingRatesFromStore._pending) {
                        return;
                    }
                    kiriofClearBlockShippingRatesFromStore._pending = true;
                    window.setTimeout(function() {
                        kiriofClearBlockShippingRatesFromStore._pending = false;
                    }, 800);

                    // Clear the server-side destination_id session so calculate_shipping()
                    // returns no rates — removes stale rates from the Order Summary when
                    // the district warning is shown.
                    kiriofBlockExtensionCartUpdate({
                        destination_id: 0,
                        destination_name: '',
                        postcode: kiriofGetCurrentPostcodeKey(),
                        payment_method: kiriofGetPaymentMethod(),
                        insurance: 0,
                        force_insurance: parseInt(jQuery('[name=kiriof_force_insurance]').val() || 0)
                    });
                    kiriofRefreshBlockShippingRates();
                }

                function kiriofClearBlockShippingMethodSelection() {
                    jQuery('.wc-block-components-radio-control__input[name*="shipping"], .wc-block-components-radio-control__input[value*="kiriminaja-official"], input.shipping_method').prop('checked', false);
                }

                function kiriofGetBlockPaymentOptionsContainer() {
                    return jQuery('.wp-block-woocommerce-checkout-payment-block, .wc-block-components-checkout-step--payment-method, .wc-block-checkout__payment').first();
                }

                // Split warning state sync into two concerns:
                // - kiriofSyncBlockDistrictWarningState: pure UI (body class + warning element)
                //   safe to call from anywhere including wp.data.subscribe
                // - kiriofClearBlockShippingRatesFromStore: triggers store dispatch, must ONLY
                //   be called from explicit user interactions, never from subscribe handlers
                function kiriofGetPlaceOrderButtonSelector() {
                    return [
                        'button.wc-block-components-checkout-place-order-button',
                        '.wc-block-components-checkout-place-order button',
                        '.wp-block-woocommerce-checkout-place-order button'
                    ].join(', ');
                }

                function kiriofGetPlaceOrderButton() {
                    return jQuery(
                        kiriofGetPlaceOrderButtonSelector()
                    );
                }

                function kiriofSetPlaceOrderDisabled(disabled) {
                    var $btn = kiriofGetPlaceOrderButton();
                    if (!$btn.length) return;
                    if (disabled) {
                        $btn.attr('data-kiriof-disabled', 'true')
                            .addClass('kiriof-place-order-soft-disabled');

                        $btn.each(function() {
                            var $currentButton = jQuery(this);
                            if (!$currentButton.prop('disabled') && $currentButton.attr('aria-disabled') !== 'true') {
                                $currentButton.attr('data-kiriof-aria-disabled', 'true')
                                    .attr('aria-disabled', 'true');
                            }
                        });
                    } else {
                        $btn.removeAttr('data-kiriof-disabled')
                            .removeClass('kiriof-place-order-soft-disabled');

                        $btn.each(function() {
                            var $currentButton = jQuery(this);
                            if ($currentButton.attr('data-kiriof-aria-disabled') === 'true' && !$currentButton.prop('disabled')) {
                                $currentButton.removeAttr('aria-disabled');
                            }
                            $currentButton.removeAttr('data-kiriof-aria-disabled');
                        });
                    }
                }

                function kiriofCommitSelectedBlockDistrict() {
                    var $districtSelect = jQuery('.kiriof-block-district-select').first();
                    if (!$districtSelect.length) {
                        return false;
                    }

                    var districtValue = String($districtSelect.val() || '');
                    if (!districtValue) {
                        return false;
                    }

                    var districtLabel = $districtSelect.find('option:selected').text() || jQuery('[name="kiriof_destination_area_name"]').val() || '';
                    var postcode = kiriofGetCurrentPostcodeKey();

                    kiriofSyncBlockDistrictSourceField(districtValue, districtLabel, { triggerChange: true });
                    kiriofUpdateCheckoutAdditionalFields(districtValue);
                    kiriofRememberDistrictForPostcode(postcode, districtValue, districtLabel);
                    kiriofPendingDistrictRestore = false;
                    kiriofDistrictResultsLoading = false;

                    return true;
                }

                if (!window.kiriofBlockPlaceOrderCaptureBound) {
                    document.addEventListener('click', function(event) {
                        var target = event.target;
                        if (!target || typeof target.closest !== 'function') {
                            return;
                        }

                        var button = target.closest(kiriofGetPlaceOrderButtonSelector());
                        if (!button) {
                            return;
                        }

                        kiriofCommitSelectedBlockDistrict();
                    }, true);
                    window.kiriofBlockPlaceOrderCaptureBound = true;
                }

                jQuery(document)
                    .off('click.kiriofBlockPlaceOrder')
                    .on('click.kiriofBlockPlaceOrder', kiriofGetPlaceOrderButtonSelector(), function(event) {
                        var $button = jQuery(this);
                        kiriofCommitSelectedBlockDistrict();

                        if ($button.attr('data-kiriof-disabled') !== 'true') {
                            return;
                        }

                        event.preventDefault();
                        event.stopImmediatePropagation();

                        kiriofSyncBlockDistrictWarningState();

                        var $warning = kiriofEnsureBlockDistrictWarning();
                        if ($warning.length) {
                            jQuery('html, body').animate({
                                scrollTop: Math.max($warning.offset().top - 120, 0)
                            }, 200);
                        }

                        var $districtSelect = jQuery('.kiriof-block-district-select').first();
                        if ($districtSelect.length) {
                            $districtSelect.trigger('focus');
                        }
                    });

                function kiriofSyncBlockDistrictWarningState() {
                    var hasValidDistrict = kiriofHasValidBlockDistrict();
                    var $warning = kiriofEnsureBlockDistrictWarning();
                    var $shippingOptions = kiriofGetBlockShippingOptionsContainer();

                    if (kiriofDistrictResultsLoading || kiriofPendingDistrictRestore) {
                        $warning.hide();
                        // If we already know the district is valid (e.g. from the WC cart store
                        // for a logged-in user), keep shipping options visible so there is no
                        // blank gap while the district select is being fetched and restored.
                        if (!hasValidDistrict) {
                            $shippingOptions.addClass('kiriof-shipping-options-blocked');
                            jQuery('body').addClass('kiriof-no-district');
                        }
                        return;
                    }

                    if (hasValidDistrict) {
                        $warning.hide();
                        $shippingOptions.removeClass('kiriof-shipping-options-blocked');
                        jQuery('body').removeClass('kiriof-no-district');
                        kiriofSetCheckoutTokenValue(true);
                        kiriofSetPlaceOrderDisabled(false);
                        return;
                    }

                    // For non-logged-in users on a fresh form (no postcode entered yet),
                    // showing "District required" immediately is bad UX — the user hasn't
                    // done anything wrong. Suppress warning and blocking until they have
                    // actually typed a postcode (or there is a saved one from a prior visit).
                    var hasPostcode = !!(kiriofGetCurrentPostcodeKey() || kiriofSavedCheckoutPostcode || kiriofLastTypedPostcode);
                    if (!hasPostcode) {
                        $warning.hide();
                        $shippingOptions.removeClass('kiriof-shipping-options-blocked');
                        jQuery('body').removeClass('kiriof-no-district');
                        kiriofSetCheckoutTokenValue(false);
                        kiriofSetPlaceOrderDisabled(false);
                        return;
                    }

                    kiriofClearBlockShippingMethodSelection();
                    $warning.show();
                    $shippingOptions.addClass('kiriof-shipping-options-blocked');
                    jQuery('body').addClass('kiriof-no-district');
                    kiriofSetCheckoutTokenValue(false);
                    kiriofSetPlaceOrderDisabled(true);
                }

                // Call this version (with store clear) only from explicit user interactions.
                // Skips the clear when a district fetch or restore is already in progress to
                // prevent the "jank" where WC rates disappear and reappear on first page load.
                function kiriofSyncBlockDistrictWarningStateWithClear() {
                    if (!kiriofDistrictResultsLoading && !kiriofPendingDistrictRestore) {
                        kiriofClearBlockShippingRatesFromStore();
                    }
                    kiriofSyncBlockDistrictWarningState();
                }

                function kiriofResetBlockDistrictState(options) {
                    options = options || {};
                    var $sourceField = kiriofGetBlockDistrictField();
                    var $select = jQuery('.kiriof-block-district-select');

                    $sourceField.val('').trigger('input');
                    if ($select.length) {
                        $select.val('');
                    }

                    jQuery('[name="kiriof_destination_area_name"]').val('');
                    jQuery('[name="kiriof_shipping_destination_area_name"]').val('');
                    kiriofEnsureLegacyBlockDistrictMirror().val('');
                    kiriofSetCheckoutTokenValue(false);
                    kiriofUpdateCheckoutAdditionalFields('');
                    if (!options.skipStoreSync) {
                        kiriofBlockExtensionCartUpdate({
                            shipping_metode_id: '',
                            destination_id: 0,
                            destination_name: '',
                            postcode: kiriofGetCurrentPostcodeKey(),
                            payment_method: kiriofGetPaymentMethod(),
                            insurance: 0,
                            force_insurance: parseInt(jQuery('[name=kiriof_force_insurance]').val() || 0)
                        });
                        kiriofRefreshBlockShippingRates();
                    }
                    if (!options.silentWarning) {
                        kiriofSyncBlockDistrictWarningState();
                    }
                }

                function kiriofRestoreSavedDistrictForCurrentPostcode() {
                    var postcode = kiriofGetCurrentPostcodeKey();
                    var savedDistrict = kiriofGetSavedDistrictForPostcode(postcode);

                    // For logged-in users with a fresh WC session (e.g. after session expiry),
                    // kiriofSavedDistrictByPostcode is empty but the district_id is preserved in
                    // WC customer meta. Read it from the cart store and populate the local map
                    // so subsequent calls work without hitting the store again.
                    if (!savedDistrict) {
                        var storedId = kiriofGetStoredDistrictIdFromWcStore();
                        if (storedId) {
                            var storedName = '';
                            for (var ri = 0; ri < kiriofLastDistrictResults.length; ri++) {
                                if (String(kiriofLastDistrictResults[ri].id) === String(storedId)) {
                                    storedName = kiriofLastDistrictResults[ri].text;
                                    break;
                                }
                            }
                            savedDistrict = { destination_id: storedId, destination_name: storedName };
                            if (postcode) {
                                kiriofRememberDistrictForPostcode(postcode, storedId, storedName);
                            }
                        }
                    }

                    var $select = jQuery('.kiriof-block-district-select');
                    var $sourceField = kiriofGetBlockDistrictField();

                    if (!savedDistrict || !$select.length) {
                        kiriofPendingDistrictRestore = false;
                        return;
                    }

                    if (!$select.find('option[value="' + String(savedDistrict.destination_id) + '"]').length) {
                        kiriofPendingDistrictRestore = false;
                        return;
                    }

                    if (String($select.val() || '') !== String(savedDistrict.destination_id)) {
                        $select.val(String(savedDistrict.destination_id));
                    }

                    kiriofSyncBlockDistrictSourceField(String(savedDistrict.destination_id), savedDistrict.destination_name || '');
                    kiriofUpdateCheckoutAdditionalFields(String(savedDistrict.destination_id));
                    kiriofPendingDistrictRestore = false;
                    kiriofSyncBlockDistrictWarningState();

                    // Persist to Store API (saves postcode map to session so district restores on next page load).
                    kiriofBlockExtensionCartUpdate({
                        destination_id: parseInt(savedDistrict.destination_id) || 0,
                        destination_name: savedDistrict.destination_name || '',
                        postcode: postcode,
                        payment_method: kiriofGetPaymentMethod(),
                        insurance: parseInt(jQuery('[name=kiriof_insurance]').val() || 0),
                        force_insurance: parseInt(jQuery('[name=kiriof_force_insurance]').val() || 0)
                    });

                    kiriofPersistDestinationArea(String(savedDistrict.destination_id), savedDistrict.destination_name || '', jQuery('[name="ship_to_different_address"]:checked').length, function() {
                        kiriofRefreshBlockShippingRates();
                        kiriofCodInsurance();
                    });
                }
            
                jQuery(document).off('change.kiriofBlockDistrict', '[name="' + kiriofFieldId + '"], .kiriof-block-district-select')
                    .on('change.kiriofBlockDistrict', '[name="' + kiriofFieldId + '"], .kiriof-block-district-select', function() {
                        var val = jQuery(this).val();
                        var label = jQuery(this).find('option:selected').text();
                        var postcode = kiriofGetCurrentPostcodeKey();
                        var differentAddress = jQuery('[name="ship_to_different_address"]:checked').length;
                        var $sourceField = kiriofGetBlockDistrictField();
            
                        kiriofSyncBlockDistrictSourceField(val, label || '', { triggerChange: true });
            
                        kiriofUpdateCheckoutAdditionalFields(val);
                        kiriofRememberDistrictForPostcode(postcode, val, label);
                        kiriofPendingDistrictRestore = false;
                        kiriofDistrictResultsLoading = false;
                        kiriofSyncBlockDistrictWarningState();

                        // Persist to WC session via Store API (saves to postcode map for restore on next page load).
                        kiriofBlockExtensionCartUpdate({
                            destination_id: parseInt(val) || 0,
                            destination_name: label || '',
                            postcode: postcode,
                            payment_method: kiriofGetPaymentMethod(),
                            insurance: parseInt(jQuery('[name=kiriof_insurance]').val() || 0),
                            force_insurance: parseInt(jQuery('[name=kiriof_force_insurance]').val() || 0)
                        });

                        kiriofPersistDestinationArea(val, label, differentAddress, function() {
                            kiriofRefreshBlockShippingRates();
                            kiriofCodInsurance();
                        });
                    });
            
                // Watch for postcode changes via direct DOM events too. Some Woo
                // checkout blocks debounce address writes through /wc/store/v1/batch,
                // so the cart data store can lag behind the visible postcode input.
                jQuery(document).off('input.kiriofBlockPostcode change.kiriofBlockPostcode', kiriofBlockPostcodeSelectors)
                    .on('input.kiriofBlockPostcode change.kiriofBlockPostcode', kiriofBlockPostcodeSelectors, function() {
                        var newPostcode = kiriofNormalizePostcode(jQuery(this).val());

                        // When postcode is cleared, reset all district state immediately so
                        // the shipping options section is blocked until a new district is chosen.
                        if (!newPostcode) {
                            kiriofLastTypedPostcode    = '';
                            kiriofSavedCheckoutPostcode = '';
                            kiriofLastPostcode          = ''; // allow re-fetch when user types again
                            kiriofResetBlockDistrictState();
                            kiriofSyncBlockDistrictWarningStateWithClear();
                            return;
                        }

                        kiriofLastTypedPostcode = newPostcode;
                        kiriofLastTypedPostcodeAt = Date.now();
                        kiriofSavedCheckoutPostcode = kiriofLastTypedPostcode;
                        kiriofUpdateBlockCheckoutPostcode(kiriofLastTypedPostcode);
                        kiriofFetchDistricts(kiriofLastTypedPostcode);
                    });
                setTimeout(function() {
                    kiriofRestoreSavedCheckoutState();
                    kiriofFetchDistricts(kiriofGetCurrentPostcodeKey());
                    kiriofSyncBlockDistrictWarningStateWithClear();
                }, 300);
                // Retry for block themes (e.g. ShopVerse) that hydrate form inputs after initial render
                setTimeout(function() {
                    kiriofRestoreSavedCheckoutState();
                    kiriofFetchDistricts(kiriofGetCurrentPostcodeKey());
                    kiriofSyncBlockDistrictWarningStateWithClear();
                }, 1500);
            
                // Watch for postcode changes via data store — use UI-only sync (no store dispatch)
                // to avoid triggering an infinite subscribe → dispatch → subscribe loop.
                // Debounced at 150ms to reduce DOM thrashing on rapid store updates.
                var kiriofSubscribeDebounceTimer = null;
                wp.data.subscribe(function() {
                    if (kiriofSubscribeDebounceTimer) clearTimeout(kiriofSubscribeDebounceTimer);
                    kiriofSubscribeDebounceTimer = setTimeout(function() {
                        try {
                            var store = wp.data.select('wc/store/cart');
                            if (!store) return;
                            var shipping = store.getShippingAddress ? store.getShippingAddress() : {};
                            var billing = store.getBillingAddress ? store.getBillingAddress() : {};
                            var domPostcode = kiriofNormalizePostcode(kiriofGetCheckoutPostcodeFromDom());
                            var recentlyTyped = kiriofLastTypedPostcodeAt && (Date.now() - kiriofLastTypedPostcodeAt) < 2500;
                            var postcode;
                            if (domPostcode || recentlyTyped) {
                                // DOM has a value or user recently typed — trust DOM + last-typed.
                                postcode = domPostcode || kiriofLastTypedPostcode;
                            } else {
                                // DOM is empty and no recent typing — fall back to store.
                                postcode = kiriofNormalizePostcode((shipping && shipping.postcode) || (billing && billing.postcode) || kiriofLastTypedPostcode);
                            }

                            // If every source is empty the user has cleared the postcode field.
                            // Reset saved state so hasPostcode check suppresses the warning correctly.
                            if (!postcode && !kiriofLastTypedPostcode) {
                                kiriofSavedCheckoutPostcode = '';
                            }

                            kiriofFetchDistricts(postcode);
                            kiriofSyncBlockDistrictWarningState();
                        } catch(e) {}
                    }, 150);
                });

                // Logged-in compact address: when user clicks "Edit" the full form
                // expands. Re-fetch districts and re-sync warning state after it renders.
                jQuery(document).on('click.kiriofAddressEdit', '.wc-block-components-address-card__edit, [class*="address-card__edit"], button[class*="edit"]', function() {
                    setTimeout(function() {
                        kiriofRestoreSavedCheckoutState();
                        kiriofFetchDistricts(kiriofGetCurrentPostcodeKey());
                        kiriofSyncBlockDistrictWarningStateWithClear();
                    }, 400);
                    setTimeout(function() {
                        kiriofRestoreSavedCheckoutState();
                        kiriofFetchDistricts(kiriofGetCurrentPostcodeKey());
                        kiriofSyncBlockDistrictWarningState();
                    }, 1200);
                    setTimeout(function() {
                        kiriofRestoreSavedCheckoutState();
                        kiriofFetchDistricts(kiriofGetCurrentPostcodeKey());
                        kiriofSyncBlockDistrictWarningState();
                    }, 2200);
                });
            }
        }

        // Script-scope fallback: kiriofGetCurrentPostcodeKey must be accessible from
        // all script-level functions (e.g. kiriofCodInsurance). On block-checkout,
        // the richer version defined inside kiriofInitBlockCheckoutCompatibility()
        // shadows this via var-hoisting within that function scope, so the inner
        // version is used for block-checkout code and this one for classic checkout.
        function kiriofGetCurrentPostcodeKey() {
            var postcode = jQuery('input[name="billing_postcode"], input[name="shipping_postcode"]')
                .filter(':visible').first().val() || '';
            if (!postcode && typeof kiriofSavedCheckoutPostcode !== 'undefined') {
                postcode = kiriofSavedCheckoutPostcode || '';
            }
            if (!postcode && typeof wp !== 'undefined' && wp.data && wp.data.select) {
                try {
                    var store = wp.data.select('wc/store/cart');
                    var shipping = store && store.getShippingAddress ? store.getShippingAddress() : {};
                    var billing  = store && store.getBillingAddress  ? store.getBillingAddress()  : {};
                    postcode = (shipping && shipping.postcode) || (billing && billing.postcode) || '';
                } catch(e) {}
            }
            return String(postcode).replace(/\D/g, '');
        }

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
            kiriofUpdatingCheckoutLock = false;
            <?php if( is_checkout()){ ?>
                jQuery(document.body).off('updated_checkout.kiriofFeeRefresh').one('updated_checkout.kiriofFeeRefresh', function() {
                    kiriofCodInsurance();
                });
                jQuery( document.body ).trigger( 'update_checkout',{update_shipping_method:true} );                        
            <?php } ?>
        }

        function kiriofSetFeeSkeletonLoading(isLoading) {
            jQuery('#order_review').toggleClass('kiriof-fee-loading', !!isLoading);
        }

        if (!jQuery('#kiriof-fee-skeleton-style').length) {
            jQuery('head').append('<style id="kiriof-fee-skeleton-style">#order_review.kiriof-fee-loading .shop_table{opacity:.65;position:relative}#order_review.kiriof-fee-loading .shop_table:after{content:"";position:absolute;inset:0;pointer-events:none;background:linear-gradient(90deg,rgba(255,255,255,0) 0%,rgba(255,255,255,.35) 50%,rgba(255,255,255,0) 100%);animation:kiriofFeeSkeletonShimmer 1.2s ease-in-out infinite}@keyframes kiriofFeeSkeletonShimmer{0%{transform:translateX(-100%)}100%{transform:translateX(100%)}}</style>');
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
                            destination_name: data.destination_name,
                            postcode: data.postcode,
                            payment_method: data.payment_method,
                            insurance: data.insurance,
                            force_insurance: data.force_insurance
                        }
                    });
                    return;
                }

                if (cartDispatch && typeof cartDispatch.invalidateResolutionForStore === 'function') {
                    cartDispatch.invalidateResolutionForStore();
                }
            } catch(e) {}
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

        function kiriofGetPaymentMethod() {
            let payment_method = kiriofNormalizePaymentMethod(
                jQuery("[name=payment_method]:checked").val() || ''
            );

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
            
            let destination_id = kiriofGetDestinationId(different_address);
            let destination_name = jQuery('.kiriof-block-district-select option:selected').text()
                || jQuery('[name="kiriof_destination_area_name"]').val()
                || jQuery('[name="kiriof_shipping_destination_area_name"]').val()
                || '';

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

            let payment_method = kiriofGetPaymentMethod();
                        

            let data = {
                action:'kiriof_get_data_after_update_checkout',
                nonce:"<?php echo esc_js( wp_create_nonce('kiriof-update-checkout') ); ?>",
                shipping_metode_id : (typeof shipping_metode_id === 'undefined' ? '' : shipping_metode_id),
                destination_id,
                destination_name,
                postcode: kiriofGetCurrentPostcodeKey(),
                payment_method,
                insurance : (typeof insurance === 'undefined' ? 0 : parseInt(insurance)),
                force_insurance : parseInt(jQuery('[name=kiriof_force_insurance]').val() || 0)
            };

            // Persist the block-checkout selections immediately through Store API.
            // The legacy admin-ajax fee request below can finish after the buyer
            // submits the order; if we wait for that success callback, the final
            // Store API checkout hook may not have kiriminaja session context yet.
            kiriofBlockExtensionCartUpdate(data);

            jQuery.ajax({
                        url:"<?php echo esc_url( admin_url('admin-ajax.php') ); ?>",
                        type: 'post',
                        data: data,
                        dataType:'JSON',
                        beforeSend:function(){
                            jQuery('#order_review').find('.shop_table').block({ message: null });
                            kiriofSetFeeSkeletonLoading(true);
                        },
                        success:function(response){                                 
                            jQuery('#order_review').find('.shop_table').unblock();  
                
                            kiriofSetFeeSkeletonLoading(false);
                                    
                            jQuery('[name=kiriof_force_insurance]').val(response?.data?.force_insurance);

                            kiriofUpdatingCheckoutLock = true;
                            jQuery(document.body).trigger('update_checkout', { update_shipping_method: false });

                        },
                        error:function(xhr){
                            kiriofSetFeeSkeletonLoading(false);
                            jQuery('#order_review').find('.shop_table').unblock();
                            alert("Sorry System Trouble Error Code : "+xhr.status);
                         }
            });
        }

    <?php
    $kiriof_inline_script = ob_get_clean();
    wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
    ?>

<?php } ?>
