/* Classic checkout presentation only. Native shipping/insurance inputs are moved, never copied. */
(function (window, document) {
    'use strict';
    var section, list, insuranceArea, observer, queued = false, stopped = false;
    var packages = new Map();
    var insuranceAnchor;
    var reviewed = window.kiriofShippingSelection && window.kiriofShippingSelection.create();
    var reviewInput, reviewNotice;
    function packageKey(input) {
        var index=input.getAttribute('data-index');
        if(index===null){var match=(input.name || '').match(/^shipping_method\[([^\]]+)\]$/);index=match ? match[1] : '';}
        return index;
    }
    function choices(form) {
        return Array.from(form.querySelectorAll('input.shipping_method:checked, input.shipping_method[type="hidden"]')).map(function(input){
            var index=packageKey(input);
            return {package_id:index,rate_id:input.value};
        });
    }
    function reconcileSelection(form) {
        if(!reviewed)return;
        if(!reviewInput){reviewInput=document.createElement('input');reviewInput.type='hidden';reviewInput.name='kiriof_shipping_selection';form.append(reviewInput);reviewNotice=document.createElement('p');reviewNotice.className='kiriof-shipping-selection-error';reviewNotice.setAttribute('role','alert');}
        var current=choices(form);reviewed.seed(current);var match=reviewed.reconcile(current);reviewInput.value=JSON.stringify(reviewed.snapshot());
        reviewNotice.hidden=!reviewed.snapshot().packages.length || match;
        var message=reviewNotice.hidden?'':(window.kiriofBillingAddressConfig.i18n || {}).shippingSelectionChanged || 'Shipping options changed. Please review and select your courier again before placing the order.';
        if(reviewNotice.textContent!==message)reviewNotice.textContent=message;
        if(section && reviewNotice.parentNode!==section)section.append(reviewNotice);
    }
    function choose(event) {
        if(!reviewed)return;
        var target=event.target;var form=document.querySelector('form.checkout');if(!active() || !form || !target || !form.contains(target))return;
        var isRadio=target.matches('input.shipping_method[type="radio"]:checked') && event.isTrusted && (event.type==='change' || event.type==='click');
        var isChoices=event.type==='change' && target.matches('select.kiriof-classic-shipping-method-select') && (event.isTrusted || (event.detail && typeof event.detail.value==='string' && event.detail.reviewedShipping===true));
        if(!isRadio && !isChoices)return;
        var index=packageKey(target);
        var current=choices(form);var found=false;current=current.map(function(item){if(item.package_id===index){found=true;return {package_id:index,rate_id:target.value};}return item;});if(!found)current.push({package_id:index,rate_id:target.value});
        reviewed.choose(index,target.value,current);reconcileSelection(form);
    }
    var layoutObserver, observedParent, observedReview;
    function align(review) {
        var css = window.getComputedStyle(review);
        var float = css.cssFloat;
        var floating = float === 'left' || float === 'right';
        var parent = review.parentElement;
        var parentCss = window.getComputedStyle(parent);
        var available = parent.clientWidth - parseFloat(parentCss.paddingLeft || 0) - parseFloat(parentCss.paddingRight || 0);
        var width = review.getBoundingClientRect().width;
        var nextWidth = floating && available > 0 && width > 0 ? (width / available * 100) + '%' : '100%';
        if (section.style.width !== nextWidth) { section.style.width = nextWidth; }
        if (section.style.cssFloat !== (floating ? float : 'none')) { section.style.cssFloat = floating ? float : 'none'; }
        section.style.clear = 'none';
        section.classList.toggle('is-summary-column', floating);
        review.classList.toggle('kiriof-shipping-options-review-right', float === 'right');
        review.classList.toggle('kiriof-shipping-options-review-left', float === 'left');
        if (window.ResizeObserver && (observedParent !== parent || observedReview !== review)) {
            if(layoutObserver)layoutObserver.disconnect();
            layoutObserver = new window.ResizeObserver(refresh);
            layoutObserver.observe(parent);layoutObserver.observe(review);
            observedParent = parent; observedReview = review;
        }
    }
    function active() { return document.querySelector('form.checkout') && !document.querySelector('.wc-block-checkout'); }
    function place() {
        if (stopped || !active()) { return; }
        var form = document.querySelector('form.checkout');
        var table = form.querySelector('table.kiriof-classic-order-review');
        if (!table) { return; }
        var rows = Array.from(table.querySelectorAll('tr.woocommerce-shipping-totals'));
        if (!rows.length) {
            var originalInsurance = document.getElementById('kiriof-classic-insurance-field');
            if (originalInsurance && insuranceAnchor && insuranceAnchor.isConnected && insuranceAnchor.nextSibling !== originalInsurance) { insuranceAnchor.parentNode.insertBefore(originalInsurance, insuranceAnchor.nextSibling); }
            if (section) { section.remove(); }
            packages.clear();
            return;
        }
        if (!section) {
            section = document.createElement('section'); section.className = 'kiriof-classic-shipping-options';
            var heading = document.createElement('h3'); heading.id = 'kiriof-classic-shipping-options-title';
            heading.textContent = (window.kiriofBillingAddressConfig.i18n || {}).shippingOptions || 'Shipping options';
            section.setAttribute('aria-labelledby', heading.id);
            list = document.createElement('div'); list.className = 'kiriof-classic-shipping-packages';
            insuranceArea = document.createElement('div'); insuranceArea.className = 'kiriof-classic-shipping-insurance';
            section.append(heading, list, insuranceArea);
        }
        // Keep the selector inside form.checkout but outside the theme's summary/payment card.
        var review = table.closest('#order_review') || table;
        if (!form.contains(review.parentNode)) { review = table; }
        if (review.previousElementSibling !== section) { review.parentNode.insertBefore(section, review); }
        align(review);
        var current = new Set();
        rows.forEach(function (row, index) {
            var key = row.getAttribute('data-kiriof-package-index') || String(index);
            current.add(key);
            var cell = row.querySelector('td');
            var previous = packages.get(key);
            if (previous && previous.row === row) { return; }
            if (previous) { previous.content.remove(); }
            var content = document.createElement('div'); content.className = 'kiriof-classic-shipping-package'; content.setAttribute('data-package-index', key);
            if (cell && cell.classList.contains('kiriof-shipping-methods-ready')) { content.classList.add('kiriof-shipping-methods-ready'); }
            if (rows.length > 1) {
                var name = document.createElement('p');name.className = 'kiriof-classic-package-label';
                var title = row.querySelector('th');name.textContent = title ? title.textContent : '';
                content.append(name);
            }
            if (cell) { while (cell.firstChild) { content.append(cell.firstChild); } }
            row.hidden = true; row.classList.add('kiriof-shipping-row-relocated');
            list.append(content); packages.set(key, {row: row, content: content});
        });
        packages.forEach(function (entry, key) { if (!current.has(key)) { entry.content.remove(); packages.delete(key); } });
        var insurance = document.getElementById('kiriof-classic-insurance-field');
        if (insurance && insurance.parentNode !== insuranceArea) {
            if (!insuranceAnchor || !insuranceAnchor.isConnected) { insuranceAnchor = document.createComment('kiriof insurance original position');insurance.parentNode.insertBefore(insuranceAnchor, insurance); }
            insuranceArea.append(insurance);
        }
        reconcileSelection(form);
    }
    function refresh() {
        if (queued || stopped) { return; }
        queued = true;window.queueMicrotask(function () { queued = false;place(); });
    }
    function start() {
        if (!active()) { return; }
        place();observer = new window.MutationObserver(refresh);
        observer.observe(document.querySelector('form.checkout'), {childList: true, subtree: true});
        if (window.jQuery) { window.jQuery(document.body).on('updated_checkout.kiriofShippingOptions init_checkout.kiriofShippingOptions', refresh); }
        document.addEventListener('change',choose,true);
        document.addEventListener('click',choose,true);
        if(window.jQuery)window.jQuery(document.querySelector('form.checkout')).on('checkout_place_order.kiriofShippingOptions',function(){reconcileSelection(this);return !reviewed || reviewed.matches(choices(this));});
    }
    window.kiriofClassicShippingOptions = {refresh: place};
    window.addEventListener('pagehide', function () { stopped = true;if(observer)observer.disconnect();if(layoutObserver)layoutObserver.disconnect();document.removeEventListener('change',choose,true);document.removeEventListener('click',choose,true);if(window.jQuery){window.jQuery(document.body).off('.kiriofShippingOptions');window.jQuery('form.checkout').off('.kiriofShippingOptions');} }, {once: true});
    if(document.readyState === 'loading')document.addEventListener('DOMContentLoaded', start, {once: true});else start();
})(window, document);
