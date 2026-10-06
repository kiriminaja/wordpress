/* Classic checkout presentation only. Native shipping/insurance inputs are moved, never copied. */
(function (window, document) {
    'use strict';
    var section, list, insuranceArea, observer, queued = false, stopped = false;
    var packages = new Map();
    var insuranceAnchor;
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
    }
    window.kiriofClassicShippingOptions = {refresh: place};
    window.addEventListener('pagehide', function () { stopped = true;if(observer)observer.disconnect();if(layoutObserver)layoutObserver.disconnect();if(window.jQuery)window.jQuery(document.body).off('.kiriofShippingOptions'); }, {once: true});
    if(document.readyState === 'loading')document.addEventListener('DOMContentLoaded', start, {once: true});else start();
})(window, document);
