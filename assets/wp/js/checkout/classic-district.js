// Legacy checkout classic district; globals retained for theme compatibility.
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

function kiriofExtractJsonResponseText(raw) {
    raw = String(raw || '').trim();
    if (!raw) {
        return raw;
    }

    try {
        JSON.parse(raw);
        return raw;
    } catch(e) {}

    var jsonStart = raw.indexOf('{');
    var jsonEnd = raw.lastIndexOf('}');
    if (jsonStart >= 0 && jsonEnd > jsonStart) {
        var extracted = raw.substring(jsonStart, jsonEnd + 1);
        try {
            JSON.parse(extracted);
            return extracted;
        } catch(e) {}
    }

    return raw;
}

function kiriofIsPlaceholderDistrictText(text) {
    text = String(text || '').trim();
    return !text || text === (kiriofBillingAddressConfig.i18n.selectOption || 'Select Option');
}

function kiriofGetClassicDistrictLabel($select) {
    var label = String($select.data('kiriofSelectedDistrictText') || '').trim();
    if (!kiriofIsPlaceholderDistrictText(label)) {
        return label;
    }

    try {
        var selectData = $select.selectWoo ? $select.selectWoo('data') : ($select.select2 ? $select.select2('data') : []);
        if (selectData && selectData.length && selectData[0].text) {
            label = String(selectData[0].text || '').trim();
            if (!kiriofIsPlaceholderDistrictText(label)) {
                return label;
            }
        }
    } catch(e) {}

    label = String($select.find('option:selected').text() || '').trim();
    if (!kiriofIsPlaceholderDistrictText(label)) {
        return label;
    }

    if (String($select.attr('name') || '') === 'kiriof_shipping_destination_area') {
        return String(jQuery('[name="kiriof_shipping_destination_area_name"]').val() || '').trim();
    }

    return String(jQuery('[name="kiriof_destination_area_name"]').val() || '').trim();
}

function kiriofSetClassicDistrictLabel($select, label, different_address) {
    label = String(label || '').trim();
    if (kiriofIsPlaceholderDistrictText(label)) {
        label = '';
    }

    if (String($select.attr('name') || '') === 'kiriof_shipping_destination_area') {
        jQuery('[name="kiriof_shipping_destination_area_name"]').val(label);
        return;
    }

    jQuery('[name="kiriof_destination_area_name"]').val(label);
    if (!different_address) {
        jQuery('[name="kiriof_shipping_destination_area_name"]').val('');
    }
}

function kiriofGetClassicAddressCountry(addressType) {
    return String(jQuery('#' + addressType + '_country').val() || '').toUpperCase();
}

function kiriofSyncClassicAddressFields() {
    if (!kiriofBillingAddressConfig.isCheckout || kiriofIsBlockCheckoutContext()) {
        return;
    }

    jQuery.each(['billing', 'shipping'], function(_, addressType) {
        var isIndonesia = kiriofGetClassicAddressCountry(addressType) === 'ID';
        if (!kiriofUsesClassicCheckout()) {
            var districtId = addressType === 'shipping' ? 'kiriof_shipping_destination_area' : 'kiriof_destination_area';
            var $district = jQuery('#' + districtId);
            $district.prop('disabled', !isIndonesia).prop('required', isIndonesia).attr('aria-required', String(isIndonesia));
            var $districtRow = $district.closest('.form-row');
            $districtRow.toggleClass('kiriof-classic-address-hidden', !isIndonesia).toggleClass('validate-required', isIndonesia);
            $districtRow.find('label .optional').toggle(!isIndonesia);
            $districtRow.find('label .required').toggle(isIndonesia);
            if (isIndonesia && !$districtRow.find('label .required').length) {
                $districtRow.find('label').append('&nbsp;<span class="required" aria-hidden="true">*</span>');
            }
        }

        // Native fields remain under WooCommerce's country/locale control.
    });
}

jQuery(document.body).on('country_to_state_changing.kiriofClassicAddress updated_checkout.kiriofClassicAddress', kiriofSyncClassicAddressFields);
jQuery(document).on('change.kiriofClassicAddress', '#billing_country, #shipping_country', kiriofSyncClassicAddressFields);

function kiriofRestoreClassicDistrictSelections() {
    if (kiriofUsesClassicCheckout()) {
        return;
    }
    kiriofRestoreClassicDistrictSelection(
        jQuery('#kiriof_destination_area'),
        kiriofBillingAddressConfig.billingDistrict || {},
        jQuery('[name="kiriof_destination_area_name"]')
    );
    kiriofRestoreClassicDistrictSelection(
        jQuery('#kiriof_shipping_destination_area'),
        kiriofBillingAddressConfig.shippingDistrict || {},
        jQuery('[name="kiriof_shipping_destination_area_name"]')
    );
}

function kiriofRestoreClassicDistrictSelection($select, district, $nameField) {
    if (kiriofUsesClassicCheckout()) {
        return;
    }
    var addressType = $select.attr('id') === 'kiriof_shipping_destination_area' ? 'shipping' : 'billing';
    if (kiriofBillingAddressConfig.isCheckout && kiriofGetClassicAddressCountry(addressType) !== 'ID') {
        return;
    }
    if (!$select.length || String($select.val() || '')) {
        return;
    }

    var districtId = String((district && district.id) || '');
    var districtName = String((district && district.name) || $nameField.val() || '').trim();
    if (!districtId || !districtName || kiriofIsPlaceholderDistrictText(districtName)) {
        return;
    }

    var hasOption = false;
    $select.find('option').each(function() {
        if (String(jQuery(this).val()) === districtId) {
            hasOption = true;
            jQuery(this).text(districtName).prop('selected', true);
            return false;
        }
    });
    if (!hasOption) {
        $select.append(new Option(districtName, districtId, true, true));
    }
    $select.val(districtId).data('kiriofSelectedDistrictText', districtName).trigger('change.select2');
    $nameField.val(districtName);
}

function changeDistrict(){
    if (kiriofUsesClassicCheckout()) {
        return;
    }

    let kelurahanArea = "select#" + (kiriofBillingAddressConfig.fieldKey || 'kiriof_destination_area') + ",select#kiriof_shipping_destination_area";

    jQuery(kelurahanArea).off('change.kiriofClassicDistrict').on('change.kiriofClassicDistrict', function () {
        let root = jQuery(this);
        let different_address = jQuery('[name="ship_to_different_address"]:checked').length;
        let addressType = root.attr('id') === 'kiriof_shipping_destination_area' ? 'shipping' : 'billing';
        let destinationAddressType = different_address > 0 ? 'shipping' : 'billing';
        let country = kiriofGetClassicAddressCountry(addressType);
        if (kiriofBillingAddressConfig.isCheckout && country !== 'ID') {
            return;
        }
        let selectedDistrictLabel = kiriofGetClassicDistrictLabel(root);
        kiriofSetClassicDistrictLabel(root, selectedDistrictLabel, different_address);
        if (kiriofBillingAddressConfig.isCheckout && addressType !== destinationAddressType) {
            return;
        }
        let ajaxurl = (typeof kiriofAjax !== 'undefined' && kiriofAjax.ajaxurl)
            ? kiriofAjax.ajaxurl
            : kiriofBillingAddressConfig.ajaxUrl || '';
        let destinationNonce = (typeof kiriofAjax !== 'undefined' && kiriofAjax.destination_nonce)
            ? kiriofAjax.destination_nonce
            : kiriofBillingAddressConfig.destinationNonce || '';
        let _insurance;

        if (kiriofBillingAddressConfig.isCheckout) {
            if( different_address > 0 ){
                _insurance = kiriofGetClassicInsuranceValue();
            }else{
                _insurance = kiriofGetClassicInsuranceValue();
            }
        } else {
            _insurance = 0;
        }

        jQuery.ajax({
            url:ajaxurl,
            type: 'post',
            data: {
                action:'kiriof_get_destination_area',
                'val':root.val(),
                'insurance':_insurance,
                'different_address': different_address,
                'text':selectedDistrictLabel,
                'payment_method':jQuery('input[name="payment_method"]:checked').val(),
                'nonce':destinationNonce,
                'postcode': jQuery('#' + (different_address > 0 ? 'shipping' : 'billing') + '_postcode').val() || '',
                'country':country ?? 'ID'
            },
            dataType:'JSON',
            dataFilter: function(raw) {
                return kiriofExtractJsonResponseText(raw);
            },
            beforeSend:function(){
                if (kiriofBillingAddressConfig.isCart) {
                    jQuery('.kj-cart-sidebar').block({ message: null });
                } else {
                    jQuery('#order_review').find('.shop_table').block({ message: null });
                }
            },
            success:function(response){
                var responseData = response && response.data ? response.data : {};

                if( response.success === false || responseData.code != 200 ){
                    jQuery('.woocommerce-notices-wrapper').append(responseData.msg || response.msg || '');
                    toggleCalculationValidation(false);
                }else{
                    toggleCalculationValidation(true);
                    jQuery(document.body).trigger('kiriof:classic-district-synced');

                }

                /** add Destination Name */
                kiriofSetClassicDistrictLabel(root, selectedDistrictLabel, different_address);

                if (kiriofBillingAddressConfig.isCart) {
                    jQuery('button[name="calc_shipping"]').trigger('click');
                    jQuery( document.body ).trigger( 'update_checkout',{update_shipping_method:true} );

                } else {
                    jQuery( document.body ).trigger( 'update_checkout',{update_shipping_method:true} );

                        jQuery(document.body).one('updated_checkout', function() {
                            kiriofCodInsurance();
                        });


                }

            },
            error:function(xhr, textStatus, errorThrown){
                if (window.console) {
                    console.warn('[KiriminAja] Destination area AJAX failed', {
                        status: xhr.status,
                        textStatus: textStatus,
                        error: errorThrown
                    });
                }
                if (String(xhr.status) !== '200') {
                    alert("Sorry System Trouble Error Code : "+xhr.status)
                }
                toggleCalculationValidation(false);
                return false;
            },
            complete:function(){
                if (kiriofBillingAddressConfig.isCart) {
                    jQuery('.kj-cart-sidebar').unblock();
                } else {
                    jQuery('#order_review').find('.shop_table').unblock();
                }
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
    if (kiriofUsesClassicCheckout()) {
        return;
    }
    let subDistrictSelectElem = jQuery(`[name="${kiriofBillingAddressConfig.fieldKey || 'kiriof_destination_area'}"],[name=kiriof_shipping_destination_area]`);
    let ajaxurl = (typeof kiriofAjax !== 'undefined' && kiriofAjax.ajaxurl)
        ? kiriofAjax.ajaxurl
        : kiriofBillingAddressConfig.ajaxUrl || '';
    let nonce = (typeof kiriofAjax !== 'undefined' && kiriofAjax.nonce)
        ? kiriofAjax.nonce
        : kiriofBillingAddressConfig.nonce || '';
    let select2 = jQuery.fn.selectWoo || jQuery.fn.select2;

    if (!subDistrictSelectElem.length || !select2 || !ajaxurl || !nonce) {
        return;
    }

    subDistrictSelectElem.each(function() {
        let $field = jQuery(this);

        if ($field.data('select2') || $field.data('selectWoo')) {
            select2.call($field, 'destroy');
        }

        select2.call($field, {
            width: '100%',
            dropdownParent: $field.closest('.form-row'),
            minimumInputLength: 3,
            placeholder: kiriofBillingAddressConfig.i18n.selectOption || 'Select Option',
            allowClear: true,
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                type: "POST",
                delay: 250,
                data: function (search) {
                    let term = search && (search.term || search.search || search.q)
                        ? search.term || search.search || search.q
                        : '';
                    term = String(term).trim();
                    return {
                        data:{
                            term:term,
                            search:term
                        },
                        term:term,
                        nonce:nonce,
                        action: 'kiriminaja_subdistrict_search'
                    };
                },
                processResults: function (response) {
                    // Current WordPress responses wrap the rows in data; older
                    // installations return rows at the root or in data.results.
                    // Never map an error object as though it were a district row.
                    let responseData = response && response.success !== false ? response : [];
                    if (!Array.isArray(responseData)) {
                        responseData = responseData.data || responseData.results || [];
                    }
                    if (!Array.isArray(responseData)) {
                        responseData = responseData.results || responseData.data || [];
                    }
                    if (!Array.isArray(responseData)) {
                        responseData = [];
                    }
                    return {
                        results: jQuery.map(responseData, function (item) {
                            if (!item || item.id === undefined || item.id === null || !item.text) {
                                return null;
                            }
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

        $field
            .off('select2:select.kiriofClassicDistrict select2:clear.kiriofClassicDistrict')
            .on('select2:select.kiriofClassicDistrict', function(event) {
                var selected = event.params && event.params.data ? event.params.data : {};
                var selectedId = selected.id || $field.val() || '';
                var selectedText = selected.text || '';

                if (selectedId && selectedText) {
                    var hasOption = false;
                    $field.find('option').each(function() {
                        if (String(jQuery(this).val()) === String(selectedId)) {
                            hasOption = true;
                            jQuery(this).text(selectedText).prop('selected', true);
                            return false;
                        }
                    });
                    if (!hasOption) {
                        $field.append(new Option(selectedText, selectedId, true, true));
                    }
                }

                $field.data('kiriofSelectedDistrictText', selectedText);
                kiriofSetClassicDistrictLabel(
                    $field,
                    selectedText,
                    jQuery('[name="ship_to_different_address"]:checked').length
                );
            })
            .on('select2:clear.kiriofClassicDistrict', function() {
                $field.data('kiriofSelectedDistrictText', '');
                kiriofSetClassicDistrictLabel(
                    $field,
                    '',
                    jQuery('[name="ship_to_different_address"]:checked').length
                );
            });
    });

    // Restore Select2 display for pre-selected values (e.g. from session on cart page)
    subDistrictSelectElem.each(function() {
        var $el = jQuery(this);
        var selectedVal = $el.val();
        var selectedText = $el.find('option:selected').text();
        if (selectedVal && selectedText && selectedText !== (kiriofBillingAddressConfig.i18n.selectOption || 'Select Option')) {
            $el.trigger('change.select2');
        }
    });
    kiriofRestoreClassicDistrictSelections();
}
