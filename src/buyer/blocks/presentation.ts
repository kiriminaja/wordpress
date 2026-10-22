// @ts-nocheck -- Woo supplies its React hook implementation at runtime.
import type { BlocksRoot } from './types';
export function bootAddressPresentation(root: BlocksRoot): void {
  'use strict';

  var checkoutSelector = '.wp-block-woocommerce-checkout, .wc-block-checkout';
  var shippingSelector = '.wc-block-checkout__shipping-fields, #shipping-fields';
  var wrapperSelector = '.wc-block-components-address-address-wrapper';
  var editSelector = '.wc-block-components-address-card__edit[aria-controls="shipping"]';
  var cardSelector = '.wc-block-components-address-card';
  var hostSelector = '.kiriof-address-status-host';
  var pluginSelector =
    hostSelector +
    ', .kiriof-buyer-map, .kiriof-buyer-district, .kiriof-checkout-district, .wp-block-kiriminaja-official-checkout-district, .wp-block-kiriminaja-official-map-checkout';
  var nativeSelector =
    checkoutSelector +
    ', ' +
    shippingSelector +
    ', ' +
    wrapperSelector +
    ', ' +
    cardSelector +
    ', ' +
    editSelector;
  var fallback = { editing: true, cardTarget: null };
  var snapshot = fallback;
  var subscribers = [];
  var observer = null;
  var checkout = null;
  var shipping = null;
  var wrapper = null;
  var edit = null;
  var host = null;

  function publish(editing, cardTarget) {
    if (snapshot.editing === editing && snapshot.cardTarget === cardTarget) {
      return;
    }
    snapshot = cardTarget ? { editing: editing, cardTarget: cardTarget } : fallback;
    subscribers.slice().forEach(function (subscriber) {
      subscriber(snapshot);
    });
  }

  function refresh() {
    var document = root.document;
    checkout = document.querySelector(checkoutSelector);
    shipping = checkout && checkout.querySelector(shippingSelector);
    wrapper = shipping && shipping.querySelector(wrapperSelector);
    edit = wrapper && wrapper.querySelector(editSelector);
    var expanded = edit && edit.getAttribute('aria-expanded');
    var card = wrapper && wrapper.querySelector(cardSelector);
    if (!card || (expanded !== 'true' && expanded !== 'false')) {
      var previousHost = host;
      host = null;
      if (previousHost && previousHost.parentNode) {
        previousHost.parentNode.removeChild(previousHost);
      }
      publish(true, null);
      return;
    }
    // Only our empty portal mount is inserted; native form/card ownership stays with Woo.
    if (!host || host.parentNode !== card) {
      if (host && host.parentNode) {
        host.parentNode.removeChild(host);
      }
      host = card.querySelector(hostSelector);
      if (!host) {
        host = document.createElement('div');
        host.className = 'kiriof-address-status-host';
        card.appendChild(host);
      }
    }
    // Contradictory metadata is fail-open while Woo is committing an edit transition.
    publish(wrapper.classList.contains('is-editing') || expanded === 'true', host);
  }

  function isPlugin(node) {
    return node && node.nodeType === 1 && !!node.closest(pluginSelector);
  }

  function nativeChange(node) {
    return (
      node &&
      node.nodeType === 1 &&
      !isPlugin(node) &&
      !node.matches('.wc-block-checkout__billing-fields, #billing-fields') &&
      (node.matches(nativeSelector) || !!node.querySelector(nativeSelector))
    );
  }

  function relevant(record) {
    var target = record.target;
    if (
      target.nodeType === 1 &&
      target.closest('.wc-block-checkout__billing-fields, #billing-fields')
    ) {
      return false;
    }
    if (record.type === 'attributes') {
      return target === wrapper || target === edit;
    }
    if (isPlugin(target)) {
      return false;
    }
    // Native removal of the portal mount must be repaired, unlike our own insertion.
    for (var i = 0; i < record.removedNodes.length; i++) {
      var removed = record.removedNodes[i];
      if (host && (removed === host || (removed.contains && removed.contains(host)))) {
        return true;
      }
      if (nativeChange(removed)) {
        return true;
      }
    }
    for (var j = 0; j < record.addedNodes.length; j++) {
      if (nativeChange(record.addedNodes[j])) {
        return true;
      }
    }
    return false;
  }

  function start() {
    var element = root.wp && root.wp.element;
    if (
      !root.document ||
      !root.document.body ||
      typeof root.MutationObserver !== 'function' ||
      !element ||
      typeof element.createPortal !== 'function'
    ) {
      return;
    }
    // One owner watches the boundary as well as native step replacement. Filter before
    // discovery: Leaflet tiles and district/portal renders never trigger DOM scans.
    observer = new root.MutationObserver(function (records) {
      if (observer && records.some(relevant)) {
        refresh();
      }
    });
    observer.observe(root.document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['class', 'aria-expanded'],
    });
    refresh();
  }

  function subscribe(subscriber) {
    subscribers.push(subscriber);
    if (subscribers.length === 1) {
      start();
    }
    subscriber(snapshot);
    var active = true;
    return function () {
      if (!active) {
        return;
      }
      active = false;
      subscribers.splice(subscribers.indexOf(subscriber), 1);
      if (!subscribers.length) {
        if (observer) {
          observer.disconnect();
          observer = null;
        }
        if (host && host.parentNode) {
          host.parentNode.removeChild(host);
        }
        checkout = shipping = wrapper = edit = host = null;
        snapshot = fallback;
      }
    };
  }

  function usePresentation() {
    var element = root.wp.element;
    var state = element.useState(function () {
      if (subscribers.length || !root.document || !element.createPortal || !root.MutationObserver) {
        return snapshot;
      }
      // Pure initial read: even a map mounted before District must not open
      // tiles under a collapsed card. Host creation remains effect-owned.
      var boundary = root.document.querySelector(checkoutSelector);
      var step = boundary && boundary.querySelector(shippingSelector);
      var container = step && step.querySelector(wrapperSelector);
      var control = container && container.querySelector(editSelector);
      var expanded = control && control.getAttribute('aria-expanded');
      if (
        container &&
        container.querySelector(cardSelector) &&
        (expanded === 'true' || expanded === 'false')
      ) {
        return {
          editing: container.classList.contains('is-editing') || expanded === 'true',
          cardTarget: null,
        };
      }
      return fallback;
    });
    element.useEffect(function () {
      return subscribe(state[1]);
    }, []);
    return state[0];
  }

  root.kiriofAddressPresentation = {
    usePresentation: usePresentation,
    subscribe: subscribe,
    getSnapshot: function () {
      return snapshot;
    },
  };
}
