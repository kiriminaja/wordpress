/* global Choices, jQuery */
/** Native Classic checkout controls. Only read-only district lookups are cancellable. */
(function (window, document) {
    'use strict';

    var records = new Map();
    var stopped = false;
    var queued = false;
    var observer;
    var config = window.kiriofBillingAddressConfig || {};
    var strings = config.i18n || {};
    var districtKey = config.fieldKey || 'kiriof_destination_area';

    function active() {
        return !stopped && !!document.querySelector('form.checkout, form.woocommerce-checkout') &&
            !document.querySelector('.wc-block-checkout');
    }

    function district(select) {
        return select.name === districtKey || select.name === 'kiriof_shipping_destination_area';
    }

    function eligible(select) {
        return select && select.tagName === 'SELECT' &&
            !!select.closest('form.checkout, form.woocommerce-checkout') &&
            (district(select) || select.id === 'billing_state' || select.id === 'shipping_state');
    }

    function signature(select) {
        return JSON.stringify([select.disabled, select.value, select.getAttribute('aria-required'),
            select.getAttribute('aria-invalid'), Array.from(select.options).map(function (option) {
                return [option.value, option.text, option.selected, option.disabled,
                    option.parentElement.tagName === 'OPTGROUP' && option.parentElement.disabled];
            })]);
    }

    function scope(select) {
        var type = select.name === 'kiriof_shipping_destination_area' ? 'shipping' : 'billing';
        var country = document.getElementById(type + '_country');
        var state = document.getElementById(type + '_state');
        var checkbox = document.querySelector('[name="ship_to_different_address"]');
        return JSON.stringify([country ? country.value : config[type + 'Country'],
            state ? state.value : '', !!(checkbox && checkbox.checked)]);
    }

    function status(record, text) {
        record.status.textContent = text || '';
        record.choices.config.noResultsText = text || strings.noResults || 'No results found';
    }

    function cancel(record) {
        record.generation++;
        window.clearTimeout(record.debounce);
        window.clearTimeout(record.deadline);
        if (record.controller) { record.controller.abort(); }
        record.controller = null;
    }

    function destroySelectWoo(select) {
        if (!window.jQuery) { return; }
        var field = window.jQuery(select);
        if (field.data('select2') || field.data('selectWoo')) {
            var destroy = field.selectWoo || field.select2;
            if (destroy) { destroy.call(field, 'destroy'); }
        }
    }

    function accessibility(record) {
        var select = record.select;
        var targets = [record.choices.containerOuter.element, record.choices.input.element];
        targets.forEach(function (target) {
            ['aria-required', 'aria-invalid', 'aria-describedby'].forEach(function (name) {
                var value = select.getAttribute(name);
                if (value !== null) { target.setAttribute(name, value); }
                else { target.removeAttribute(name); }
            });
            if (select.required && !select.hasAttribute('aria-required')) { target.setAttribute('aria-required', 'true'); }
        });
        if (select.disabled) { record.choices.disable(); }
        else { record.choices.enable(); }
    }

    function rows(response, term) {
        for (var depth = 0; depth < 5 && !Array.isArray(response); depth++) {
            if (!response || response.success === false ||
                (response.term !== undefined && String(response.term).trim() !== term)) {
                throw new Error('Invalid district response');
            }
            response = response.data !== undefined ? response.data : response.results;
        }
        if (!Array.isArray(response)) { throw new Error('Invalid district response'); }
        return response.filter(function (row) {
            return row && /^[1-9][0-9]*$/.test(String(row.id)) && typeof row.text === 'string' && row.text.trim();
        }).map(function (row) {
            return { value: String(row.id), label: String(row.text) };
        });
    }

    function search(record, value) {
        cancel(record);
        var term = String(value || '').trim();
        record.term = term;
        if (term.length < 3) {
            status(record, strings.searchMinChars || 'Enter at least 3 characters');
            record.choices.setChoices([], 'value', 'label', true, false, false);
            record.signature = signature(record.select);
            return;
        }
        var generation = record.generation;
        var addressScope = scope(record.select);
        status(record, strings.searching || 'Searching…');
        record.debounce = window.setTimeout(function () {
            if (!active() || !record.select.isConnected || generation !== record.generation) { return; }
            var controller = new window.AbortController();
            record.controller = controller;
            var root = window.kiriofAjax || {};
            var body = new window.URLSearchParams();
            body.set('action', 'kiriminaja_subdistrict_search');
            body.set('nonce', root.nonce || config.nonce || '');
            body.set('term', term);
            body.set('data[term]', term);
            body.set('data[search]', term);
            var current = function () {
                return active() && record.select.isConnected && generation === record.generation &&
                    addressScope === scope(record.select) && term === record.term;
            };
            record.deadline = window.setTimeout(function () {
                if (current()) {
                    record.generation++;
                    controller.abort();
                    record.choices.setChoices([], 'value', 'label', true, false, false);
                    record.signature = signature(record.select);
                    status(record, strings.searchError || strings.lookupError || 'Could not search subdistricts. Please try again.');
                }
            }, 10000);
            window.fetch(root.ajaxurl || config.ajaxUrl || '', {
                method: 'POST', credentials: 'same-origin', body: body, signal: controller.signal
            }).then(function (response) {
                if (!response.ok) { throw new Error('District lookup failed'); }
                return response.json();
            }).then(function (response) {
                if (!current()) { return; }
                var choices = rows(response, term).filter(function (choice) { return choice.value !== record.select.value; });
                // Retain the selected native option and never produce a change/write here.
                record.choices.setChoices(choices, 'value', 'label', true, false, false);
                record.signature = signature(record.select);
                status(record, choices.length ? '' : strings.noResults || 'No results found');
            }).catch(function () {
                if (current()) {
                    record.choices.setChoices([], 'value', 'label', true, false, false);
                    record.signature = signature(record.select);
                    status(record, strings.searchError || strings.lookupError || 'Could not search subdistricts. Please try again.');
                }
            }).finally(function () {
                if (generation === record.generation) {
                    window.clearTimeout(record.deadline);
                    record.controller = null;
                }
            });
        }, 250);
    }

    function syncLabel(record) {
        if (!window.jQuery) { return; }
        var select = record.select;
        var option = select.options[select.selectedIndex];
        var label = select.value && option ? option.text : '';
        var field = window.jQuery(select);
        field.data('kiriofSelectedDistrictText', label);
        if (typeof window.kiriofSetClassicDistrictLabel === 'function') {
            window.kiriofSetClassicDistrictLabel(field, label,
                document.querySelectorAll('[name="ship_to_different_address"]:checked').length);
        }
    }

    function init(select) {
        if (!active() || !eligible(select) || !window.Choices) { return null; }
        if (records.has(select)) { return records.get(select).choices; }
        destroySelectWoo(select);
        var isDistrict = district(select);
        var label = Array.from(document.querySelectorAll('label[for]')).find(function (item) {
            return item.htmlFor === select.id;
        });
        if (label && !label.id) { label.id = select.id + '-choices-label'; }
        var choices = new window.Choices(select, {
            allowHTML: false, shouldSort: false, searchEnabled: true,
            searchChoices: !isDistrict, searchFloor: isDistrict ? 3 : 1,
            removeItemButton: isDistrict, labelId: label ? label.id : '',
            itemSelectText: '', placeholderValue: strings.selectOption || 'Select Option',
            noResultsText: strings.noResults || 'No results found',
            noChoicesText: strings.searchMinChars || 'Enter at least 3 characters'
        });
        var notice = document.createElement('div');
        notice.className = 'kiriof-choices-status';
        notice.setAttribute('role', 'status');
        notice.setAttribute('aria-live', 'polite');
        choices.containerOuter.element.appendChild(notice);
        var record = { select: select, choices: choices, status: notice, generation: 0,
            signature: '', addressScope: scope(select), term: '' };
        records.set(select, record);
        // Themes can intercept clicks on the selected item. Own activation of
        // the closed control, while leaving results/search/clear events alone.
        record.onActivate = function (event) {
            if (select.disabled || !event.target.closest('.choices__inner') || event.target.closest('.choices__button')) { return; }
            event.preventDefault();
            event.stopImmediatePropagation();
            if (choices.containerOuter.element.classList.contains('is-open')) { choices.hideDropdown(); }
            else { choices.showDropdown(); }
        };
        record.onOpen = function () {
            window.requestAnimationFrame(function () {
                if (!stopped && select.isConnected && !select.disabled && choices.containerOuter.element.classList.contains('is-open')) {
                    choices.input.element.focus();
                }
            });
        };
        choices.containerOuter.element.addEventListener('click', record.onActivate, true);
        record.onEscape = function (event) {
            if ((event.key === 'Enter' || (event.key === ' ' && event.target === choices.containerOuter.element)) && !select.disabled && !choices.containerOuter.element.classList.contains('is-open')) {
                event.preventDefault();
                event.stopImmediatePropagation();
                choices.showDropdown();
                return;
            }
            if (event.key !== 'Escape' || !choices.containerOuter.element.classList.contains('is-open')) { return; }
            event.preventDefault();
            event.stopImmediatePropagation();
            choices.hideDropdown(true);
            choices.containerOuter.element.focus();
        };
        choices.containerOuter.element.addEventListener('keydown', record.onEscape, true);
        select.addEventListener('showDropdown', record.onOpen);
        record.onSearch = function (event) { search(record, event.detail.value); };
        record.onInput = function () {
            var term = choices.input.element.value.trim();
            if (isDistrict && term.length < 3 && term !== record.term) { search(record, term); }
        };
        // Choices emits a bubbling native CustomEvent. Do not re-trigger jQuery change:
        // capture synchronizes the hidden label before Woo's delegated write handler.
        record.onChange = function (event) {
            if (isDistrict) {
                if (event.detail && event.detail.value === '') { select.value = ''; }
                cancel(record);
                record.term = '';
                status(record, '');
                syncLabel(record);
            }
            queueRefresh();
        };
        select.addEventListener('change', record.onChange, true);
        if (isDistrict) {
            record.onClear = function (event) {
                if (!event.target.closest('.choices__button') || (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ')) { return; }
                event.preventDefault();
                event.stopImmediatePropagation();
                choices.setChoices([{ value: '', label: strings.selectOption || 'Select Option', selected: true, placeholder: true }], 'value', 'label', true, true, true);
                select.value = '';
                select.dispatchEvent(new window.Event('change', { bubbles: true }));
            };
            choices.containerOuter.element.addEventListener('pointerdown', record.onClear, true);
            choices.containerOuter.element.addEventListener('mousedown', record.onClear, true);
            choices.containerOuter.element.addEventListener('keydown', record.onClear, true);
            select.addEventListener('search', record.onSearch);
            choices.input.element.addEventListener('input', record.onInput);
        }
        accessibility(record);
        record.signature = signature(select);
        return choices;
    }

    function destroy(record) {
        cancel(record);
        record.select.removeEventListener('change', record.onChange, true);
        record.select.removeEventListener('search', record.onSearch);
        record.select.removeEventListener('showDropdown', record.onOpen);
        record.choices.containerOuter.element.removeEventListener('click', record.onActivate, true);
        record.choices.containerOuter.element.removeEventListener('keydown', record.onEscape, true);
        record.choices.input.element.removeEventListener('input', record.onInput);
        var disconnected = !record.select.isConnected;
        var outer = record.choices.containerOuter.element;
        if (record.onClear) { outer.removeEventListener('pointerdown', record.onClear, true); outer.removeEventListener('mousedown', record.onClear, true); outer.removeEventListener('keydown', record.onClear, true); }
        // Woo replaced the select within our wrapper. Preserve its replacement
        // before Choices unwrap removes the old wrapper and resurrects the select.
        if (disconnected && outer.parentNode) {
            Array.from(outer.querySelectorAll('input, select')).forEach(function (node) {
                if (node.id === record.select.id && node !== record.select) {
                    outer.parentNode.insertBefore(node, outer);
                }
            });
        }
        record.choices.destroy();
        // Woo replaced this node inside the wrapper; destroy must not resurrect it.
        if (disconnected) { record.select.remove(); }
        record.status.remove();
        records.delete(record.select);
    }

    function refresh() {
        if (stopped) { return; }
        records.forEach(function (record, select) {
            if (!active() || !select.isConnected || !eligible(select)) { destroy(record); return; }
            destroySelectWoo(select);
            var addressScope = scope(select);
            if (addressScope !== record.addressScope) {
                cancel(record);
                record.term = '';
                status(record, '');
                record.addressScope = addressScope;
            }
            var next = signature(select);
            if (next !== record.signature) {
                // refresh() preserves Choices' old selection; setChoices instead honors
                // authoritative Woo native options, including explicit clears.
                var options = Array.from(select.options).map(function (option) {
                    return { value: option.value, label: option.text, selected: option.selected,
                        disabled: option.disabled || (option.parentElement.tagName === 'OPTGROUP' && option.parentElement.disabled),
                        placeholder: option.value === '' };
                });
                record.choices.setChoices(options, 'value', 'label', true, true, true);
                accessibility(record);
                record.signature = signature(select);
            }
        });
        if (active()) {
            document.querySelectorAll('form.checkout select, form.woocommerce-checkout select').forEach(init);
        }
    }

    function queueRefresh() {
        if (queued || stopped) { return; }
        queued = true;
        window.queueMicrotask(function () { queued = false; if (!stopped) { refresh(); } });
    }

    function addressChange(event) {
        if (event.target.matches && event.target.matches('#billing_country, #shipping_country, #billing_state, #shipping_state, [name="ship_to_different_address"]')) {
            // Invalidate synchronously, before any pending response microtask can render.
            records.forEach(function (record) {
                if (district(record.select)) { cancel(record); }
            });
        }
        queueRefresh();
    }

    function start() {
        if (stopped) { return; }
        refresh();
        observer = new window.MutationObserver(queueRefresh);
        observer.observe(document.body, { childList: true, subtree: true, attributes: true,
            attributeFilter: ['disabled', 'selected', 'value', 'aria-required', 'aria-invalid', 'aria-describedby'], characterData: true });
        document.addEventListener('change', addressChange, true);
        document.body.addEventListener('updated_checkout', queueRefresh);
        document.body.addEventListener('country_to_state_changed', queueRefresh);
        if (window.jQuery) {
            window.jQuery(document.body).on('updated_checkout.kiriofChoices country_to_state_changed.kiriofChoices', queueRefresh);
        }
    }

    window.kiriofClassicChoices = { initDistrict: function (select) {
        return district(select) ? init(select) : null;
    }, refresh: refresh, active: active };

    window.addEventListener('pagehide', function () {
        stopped = true;
        if (observer) { observer.disconnect(); }
        document.removeEventListener('DOMContentLoaded', start);
        document.removeEventListener('change', addressChange, true);
        document.body.removeEventListener('updated_checkout', queueRefresh);
        document.body.removeEventListener('country_to_state_changed', queueRefresh);
        if (window.jQuery) { window.jQuery(document.body).off('.kiriofChoices'); }
        records.forEach(destroy);
    }, { once: true });
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start, { once: true }); }
    else { start(); }
})(window, document);
