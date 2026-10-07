// @ts-nocheck -- Transitional adapter for Woo's version-dependent native stores and hooks.
// Queue, destination normalization, pin identity and shipping review live in typed modules.
// Native Woo React owns the select, validation, checkout events and rate mutations.
import type { BlocksRoot } from './types';
import { createQueue } from '../state/checkout-queue';
import { normalizeDestination } from '../state/destination';
import { create as createShippingSelection } from '../state/shipping-selection';
import {
  destinationForAddress,
  validCoordinate,
  shippingAddress,
  shippingAddressKey,
  savedCoordinates,
  completeShippingAddress,
} from './address';
export function bootBuyerCheckout(root: BlocksRoot, wp = root.wp, wc = root.wc): void {
  const document = root.document;
  var settings = wc && wc.wcSettings;
  var config =
    root.kiriofBuyerCheckoutConfig ||
    (settings && settings.getSetting
      ? settings.getSetting('kiriminaja-official-buyer_data', {})
      : {});
  var blocks = wc && wc.blocksCheckout;
  var session = { createQueue, normalizeDestination };
  var namespace = 'kiriminaja-official';
  var errorId = 'kiriof-buyer-destination';
  var fieldId = namespace + '/kiriof_destination_area';
  var destinationSlot = blocks && (blocks.OrderMeta || blocks.ExperimentalOrderMeta);
  destinationSlot =
    destinationSlot && wp && wp.plugins && typeof wp.plugins.registerPlugin === 'function'
      ? destinationSlot
      : null;
  var supportsDistrictInnerBlock = !!(blocks && typeof blocks.registerCheckoutBlock === 'function');
  var checkoutDispatch;
  var validationDispatch;

  if (
    !document.querySelector(
      '.wp-block-woocommerce-checkout, .wc-block-checkout, .wp-block-woocommerce-cart, .wc-block-cart',
    ) ||
    !config.enabled ||
    !session ||
    (!destinationSlot && !supportsDistrictInnerBlock) ||
    !wp ||
    !wp.element ||
    !wp.data ||
    !wp.data.useSelect ||
    !blocks.extensionCartUpdate
  ) {
    return;
  }
  function restoreReviewedShipping(packages, chosen, busy) {
    if (!shippingReview || busy || restoringShipping) return;
    if (shippingReview.matches(chosen)) {
      restorationKey = '';
      return;
    }
    var review = shippingReview.snapshot().packages;
    if (!review.length || review.length !== packages.length) return;
    var differences = [];
    for (var i = 0; i < review.length; i++) {
      var expected = review[i];
      var pkg = packages.find(function (entry) {
        return String(entry.package_id) === expected.package_id;
      });
      if (
        !pkg ||
        !(pkg.shipping_rates || []).some(function (rate) {
          return rate.rate_id === expected.rate_id;
        })
      )
        return;
      var actual = chosen.find(function (entry) {
        return entry.package_id === expected.package_id;
      });
      if (!actual || actual.rate_id !== expected.rate_id) differences.push(expected);
    }
    var key = JSON.stringify([review, chosen]);
    if (!differences.length || key === restorationKey) return;
    var dispatch = wp.data.dispatch('wc/store/cart');
    if (!dispatch || typeof dispatch.selectShippingRate !== 'function') return;
    restorationKey = key;
    restoringShipping = true;
    // Restore only an existing exact reviewed ID after native refresh settles.
    // Missing/changed rates stay blocked; no plugin pricing/booking is triggered.
    var promise = Promise.resolve();
    differences.forEach(function (entry) {
      promise = promise.then(function () {
        if (!api.disabled) return dispatch.selectShippingRate(entry.rate_id, entry.package_id);
      });
    });
    promise
      .catch(function () {
        /* Native errors keep the review conflict visible. */
      })
      .finally(function () {
        restoringShipping = false;
        if (!api.disabled) notify();
      });
  }

  function addressBadge(text, complete, title) {
    return h(
      'span',
      {
        className: 'kiriof-address-status__badge ' + (complete ? 'is-complete' : 'is-warning'),
        title: title,
      },
      h(
        'svg',
        {
          viewBox: '0 0 24 24',
          width: 18,
          height: 18,
          fill: 'none',
          stroke: 'currentColor',
          strokeWidth: 2,
          'aria-hidden': 'true',
          focusable: 'false',
        },
        complete
          ? h('path', { d: 'm5 12 4 4 10-10' })
          : h('path', { d: 'M8 3h8l5 5v8l-5 5H8l-5-5V8Z M12 7v6 M12 16v1' }),
      ),
      text,
    );
  }
  try {
    checkoutDispatch = wp.data.dispatch('wc/store/checkout');
    validationDispatch = wp.data.dispatch('wc/store/validation');
    var cartStore = wp.data.select('wc/store/cart');
    var paymentStore = wp.data.select('wc/store/payment');
    if (
      !checkoutDispatch ||
      !checkoutDispatch.setExtensionData ||
      !validationDispatch ||
      !validationDispatch.setValidationErrors ||
      !validationDispatch.clearValidationError ||
      !cartStore ||
      !cartStore.isShippingRateBeingSelected ||
      !cartStore.isCustomerDataUpdating ||
      !paymentStore ||
      !paymentStore.getActivePaymentMethod
    ) {
      return;
    }
  } catch {
    return;
  }

  var element = wp.element;
  var h = element.createElement;
  var useEffect = element.useEffect;
  var useState = element.useState;
  var useRef = element.useRef;
  // Stable for this entry point; the bridge is an asset dependency, not a late hook.
  var usePresentation =
    root.kiriofAddressPresentation && root.kiriofAddressPresentation.usePresentation;
  var strings = config.i18n || {};
  var listeners = new Set();
  var savedSelections = Object.assign({}, config.savedDistrictByPostcode || {});
  // Prefer in-address mounts over fallback order-summary mounts.
  var controllers = new Map();
  var owner = null;
  var effectCleanups = new Set();
  var refreshVersion = 0;
  var lastRecipientKey = null;
  var recipientRefreshVersion = -1;
  var refreshedDeadline = 0;
  var nextDistrictId = 0;
  var shippingReview = createShippingSelection({
    onChange: function () {
      publishShippingReview();
      notify();
    },
  });
  var shippingSelectionErrorId = 'kiriof-shipping-selection';
  var shippingSelectionPendingId = 'kiriof-shipping-selection-pending';
  var restorationKey = '';
  var restoringShipping = false;
  function selectedPackages(packages) {
    return (packages || []).flatMap(function (pkg) {
      var rate = (pkg.shipping_rates || []).find(function (item) {
        return item.selected;
      });
      return rate && pkg.package_id !== undefined && pkg.package_id !== null
        ? [{ package_id: String(pkg.package_id), rate_id: rate.rate_id }]
        : [];
    });
  }
  function publishShippingReview() {
    if (shippingReview && state.destination)
      checkoutDispatch.setExtensionData(namespace, {
        destination: state.destination,
        shipping_selection: shippingReview.snapshot(),
      });
  }
  function reviewNativeShipping(event) {
    var input = event.target;
    if (!shippingReview || !input || input.tagName !== 'INPUT' || input.type !== 'radio') return;
    var store = wp.data.select('wc/store/cart');
    var data = store.getCartData() || {};
    var packages = data.shippingRates || [];
    if ((event.type !== 'click' && !input.checked) || event.isTrusted === false) return;
    var matching = packages.filter(function (entry) {
      return (entry.shipping_rates || []).some(function (rate) {
        return rate.rate_id === input.value;
      });
    });
    var pkg = matching.length === 1 ? matching[0] : null;
    if (!pkg && input.closest) {
      var container = input.closest('.wc-block-components-shipping-rates-control__package');
      if (container) {
        var containers = Array.from(
          document.querySelectorAll('.wc-block-components-shipping-rates-control__package'),
        );
        var index = containers.indexOf(container);
        var candidate = packages[index];
        if (matching.includes(candidate)) pkg = candidate;
      }
    }
    if (!pkg) return;
    restorationKey = '';
    var chosen = selectedPackages(packages);
    var found = false;
    chosen = chosen.map(function (entry) {
      if (entry.package_id === String(pkg.package_id)) {
        found = true;
        return { package_id: String(pkg.package_id), rate_id: input.value };
      }
      return entry;
    });
    if (!found) chosen.push({ package_id: String(pkg.package_id), rate_id: input.value });
    shippingReview.choose(String(pkg.package_id), input.value, chosen);
  }
  var state = {
    refreshVersion: 0,
    destination: null,
    queue: null,
    selection: null,
    results: { key: '', options: [], loading: false, error: false },
    retryLookup: 0,
    retryUpdate: 0,
    lookupKey: '',
  };
  var savedPin = savedCoordinates(config.savedDestination);
  var restorationAttempted = !savedPin;
  var resolveReady;
  var api = (root.kiriofBuyerCheckout = {
    active: false,
    pending: true,
    disabled: false,
    ready: new Promise(function (resolve) {
      resolveReady = resolve;
    }),
    getDestination: function () {
      return state.destination;
    },
    setCoordinates: setCoordinates,
    getCoordinates: getCoordinates,
  });
  var readinessTimer = root.setTimeout(function () {
    api.pending = false;
    api.disabled = true;
    resolveReady(false);
  }, 2000);

  function notify() {
    listeners.forEach(function (listener) {
      listener(function (revision) {
        return revision + 1;
      });
    });
  }

  function setShared(key, next) {
    state[key] = 'function' === typeof next ? next(state[key]) : next;
    notify();
  }

  function setSelection(next) {
    setShared('selection', next);
  }
  function setResults(next) {
    setShared('results', next);
  }
  function setMapPin(next) {
    var value = 'function' === typeof next ? next(state.mapPin || null) : next;
    if (value && (!validCoordinate(value.latitude, 90) || !validCoordinate(value.longitude, 180))) {
      return;
    }
    setShared('mapPin', value);
  }
  var queue = session.createQueue({
    setTimeout: function (callback, delay) {
      return root.setTimeout(callback, delay);
    },
    clearTimeout: function (handle) {
      root.clearTimeout(handle);
    },
    send: function (snapshot) {
      return blocks.extensionCartUpdate({
        namespace: namespace,
        data: snapshot,
        overwriteDirtyCustomerData: false,
      });
    },
    isBlocked: function () {
      var cart = wp.data.select('wc/store/cart');
      return Boolean(
        !owner ||
        api.disabled ||
        cart.isShippingRateBeingSelected() ||
        cart.isCustomerDataUpdating() ||
        (cart.hasPendingItemsOperations && cart.hasPendingItemsOperations()),
      );
    },
    onChange: function (next) {
      state.queue = next;
      notify();
    },
  });
  state.queue = queue.getState();

  function publish(destination) {
    if (JSON.stringify(state.destination) === JSON.stringify(destination)) {
      return;
    }
    state.destination = destination;
    checkoutDispatch.setExtensionData(namespace, {
      destination: destination,
      ...(shippingReview ? { shipping_selection: shippingReview.snapshot() } : {}),
    });
  }

  function setValidation(message) {
    if (message) {
      var errors = {};
      errors[errorId] = { message: message, hidden: false };
      validationDispatch.setValidationErrors(errors);
    } else {
      validationDispatch.clearValidationError(errorId);
    }
  }

  function hasInnerPlacement() {
    return Array.from(controllers.values()).some(function (placement) {
      return 'shipping-address' === placement;
    });
  }
  function electOwner() {
    var preferred = hasInnerPlacement() ? 'shipping-address' : 'order-summary';
    if (owner && controllers.get(owner) === preferred) {
      return;
    }
    owner = null;
    controllers.forEach(function (placement, token) {
      if (!owner && placement === preferred) {
        owner = token;
      }
    });
  }

  function getCoordinates(address) {
    var cart = wp.data.select('wc/store/cart');
    var data = cart.getCartData() || {};
    var key = shippingAddressKey(address);
    if (key !== shippingAddressKey(data.shippingAddress)) {
      return null;
    }
    // The map may mount before the District owner. Restore silently here so its
    // first render and the first published snapshot see the same real pin.
    // Empty initial cart/customer data is not a failed restoration attempt.
    if (
      !restorationAttempted &&
      data.needsShipping &&
      !cart.isCustomerDataUpdating() &&
      completeShippingAddress(shippingAddress(data.shippingAddress))
    ) {
      restorationAttempted = true;
      if (savedPin.key === key) {
        state.mapPin = savedPin;
      }
    }
    return state.mapPin && state.mapPin.key === key ? state.mapPin : null;
  }
  function setCoordinates(address, point) {
    if (api.disabled || !api.active) {
      return false;
    }
    var cart = wp.data.select('wc/store/cart').getCartData() || {};
    if (shippingAddressKey(address) !== shippingAddressKey(cart.shippingAddress)) {
      return false;
    }
    if (point && (!validCoordinate(point.latitude, 90) || !validCoordinate(point.longitude, 180))) {
      return false;
    }
    restorationAttempted = true;
    setMapPin(
      point
        ? {
            latitude: Number(point.latitude).toFixed(7),
            longitude: Number(point.longitude).toFixed(7),
            key: shippingAddressKey(address),
          }
        : null,
    );
    return true;
  }

  function DistrictControl(props) {
    var slot = props && props.slot ? props.slot : 'order-summary';
    var presentation = usePresentation ? usePresentation() : { editing: true, cardTarget: null };
    var data = wp.data.useSelect(function (select) {
      var cart = select('wc/store/cart');
      var checkout = select('wc/store/checkout');
      var payment = select('wc/store/payment');
      return {
        cart: cart.getCartData(),
        payment: payment.getActivePaymentMethod(),
        busy: cart.isShippingRateBeingSelected() || cart.isCustomerDataUpdating(),
        collection: checkout.prefersCollection ? checkout.prefersCollection() : false,
      };
    }, []);
    var cart = data.cart || {};
    var address = cart.shippingAddress || {};
    var postcode = String(address.postcode || '')
      .replace(/\s+/g, '')
      .toUpperCase();
    var country = String(address.country || 'ID').toUpperCase();
    var addressKey = country + '|' + postcode;
    var required = Boolean(cart.needsShipping && 'ID' === country && !data.collection);
    var rates = (cart.shippingRates || []).flatMap(function (pkg) {
      return pkg.shipping_rates || [];
    });
    var selected = rates.filter(function (rate) {
      return rate.selected;
    });
    var chosenPackages = selectedPackages(cart.shippingRates);
    var chosenPackagesKey = JSON.stringify(chosenPackages);
    var instantSelected = selected.some(function (rate) {
      return (
        'kiriminaja-instant' === rate.method_id ||
        /^kiriminaja-instant(?:_|:)/.test(rate.rate_id || '')
      );
    });
    var instantStatus = (cart.extensions || {})['kiriminaja-official-instant-checkout'] || {};
    var expiresAt = Number(instantStatus.expires_at);
    var recoverableQuote =
      'available' === instantStatus.code ||
      'quote_failed' === instantStatus.code ||
      'quote_failed_or_unavailable' === instantStatus.code;
    var expiredQuote =
      Number.isFinite(expiresAt) && expiresAt > 0 && Date.now() >= expiresAt * 1000;
    var manualRefreshNeeded =
      ('available' === instantStatus.code && false === instantStatus.eligible) ||
      'quote_failed' === instantStatus.code ||
      'quote_failed_or_unavailable' === instantStatus.code ||
      (expiredQuote && (false !== instantStatus.eligible || recoverableQuote));
    var recipient = {
      first_name: String(address.first_name || ''),
      last_name: String(address.last_name || ''),
      phone: String(address.phone || (cart.billingAddress || {}).phone || ''),
    };
    var recipientKey = JSON.stringify(recipient);
    var recipientRef = useRef(recipient);
    recipientRef.current = recipient;
    var quoteVersion = state.refreshVersion;
    var kiriminajaSelected =
      !selected.length ||
      selected.some(function (rate) {
        return (
          'kiriminaja-official' === rate.method_id ||
          'kiriminaja-instant' === rate.method_id ||
          /^kiriminaja-(?:official|instant)(?:_|:)/.test(rate.rate_id || '')
        );
      });
    var revision = useState(0);
    var token = useRef({}).current;
    if (!token.fieldId) {
      token.fieldId = 'kiriof-buyer-district-' + ++nextDistrictId;
    }
    var inputId = token.fieldId;
    var isOwner = owner === token;
    var selection = state.selection;
    var mapPin = getCoordinates(address);
    var awaitingSavedPin = !restorationAttempted;
    var results = state.results;
    var updateState = state.queue;
    var retryLookup = state.retryLookup;
    var currentSelection = selection && selection.key === addressKey ? selection : null;
    var currentPin = mapPin && mapPin.key === shippingAddressKey(address) ? mapPin : null;
    var destination = destinationForAddress(currentSelection, address, currentPin);
    var destinationKey = JSON.stringify(destination);
    var lookupGeneration = useRef(0);
    var destinationRef = useRef(destination);
    destinationRef.current = destination;
    var pinAddressKey = shippingAddressKey(address);
    useEffect(
      function () {
        if (state.mapPin && state.mapPin.key !== pinAddressKey) {
          setMapPin(null);
        }
      },
      [pinAddressKey],
    );

    function ownsEffects() {
      return !api.disabled && owner === token && Boolean(data.cart);
    }

    useEffect(function () {
      if (api.disabled) {
        return;
      }
      controllers.set(token, slot);
      electOwner();
      listeners.add(revision[1]);
      api.active = true;
      if (api.pending) {
        api.pending = false;
        root.clearTimeout(readinessTimer);
        resolveReady(true);
      }
      document.documentElement.classList.add('kiriof-buyer-checkout-active');
      notify();
      return function () {
        listeners.delete(revision[1]);
        controllers.delete(token);
        electOwner();
        if (!owner) {
          setValidation('');
          api.active = false;
          if (document.documentElement.classList.remove) {
            document.documentElement.classList.remove('kiriof-buyer-checkout-active');
          }
        }
        notify();
      };
    }, []);

    useEffect(
      function () {
        if (ownsEffects() && !awaitingSavedPin) {
          publish(destinationRef.current);
        }
      },
      [destinationKey, isOwner, awaitingSavedPin],
    );

    useEffect(
      function () {
        if (!ownsEffects() || !shippingReview) return;
        if (
          !awaitingSavedPin &&
          !data.busy &&
          cart.needsShipping &&
          !data.collection &&
          chosenPackages.length === (cart.shippingRates || []).length
        )
          shippingReview.seed(chosenPackages);
        var matches = shippingReview.reconcile(chosenPackages);
        restoreReviewedShipping(cart.shippingRates || [], chosenPackages, data.busy);
        if (!awaitingSavedPin) publishShippingReview();
        var guarded =
          cart.needsShipping && !data.collection && shippingReview.snapshot().packages.length;
        if (guarded && !matches) {
          var errors = {};
          errors[shippingSelectionErrorId] = {
            message:
              strings.shippingSelectionChanged ||
              'Shipping options changed. Please review and select your courier again before placing the order.',
            hidden: false,
          };
          validationDispatch.setValidationErrors(errors);
        } else validationDispatch.clearValidationError(shippingSelectionErrorId);
        if (guarded && data.busy) {
          var pending = {};
          pending[shippingSelectionPendingId] = {
            message: strings.shippingSelectionUpdating || 'Updating shipping options…',
            hidden: true,
          };
          validationDispatch.setValidationErrors(pending);
        } else validationDispatch.clearValidationError(shippingSelectionPendingId);
      },
      [
        chosenPackagesKey,
        data.busy,
        cart.needsShipping,
        data.collection,
        awaitingSavedPin,
        pinAddressKey,
        isOwner,
        revision[0],
      ],
    );

    useEffect(
      function () {
        if (ownsEffects()) {
          queue.resume();
        }
      },
      [data.busy, isOwner],
    );

    useEffect(
      function () {
        if (!ownsEffects() || awaitingSavedPin || !cart.needsShipping || data.collection) {
          return;
        }
        // Restore a saved identity before issuing a mutation with an empty district.
        if (
          required &&
          (results.key !== addressKey ||
            results.loading ||
            results.error ||
            !results.options.length ||
            !currentSelection ||
            postcode.length < 3)
        ) {
          return;
        }
        if (null !== lastRecipientKey && lastRecipientKey !== recipientKey) {
          recipientRefreshVersion = quoteVersion;
        }
        lastRecipientKey = recipientKey;
        queue.update({
          action: 'sync_checkout',
          destination: destinationRef.current,
          payment_method: data.payment || '',
          insurance: config.globalInsurance ? 1 : 0,
          force_insurance: 0,
          recipient_context: recipientRef.current,
          quote_refresh_version: quoteVersion,
          refresh_instant: quoteVersion > 0 || recipientRefreshVersion === quoteVersion,
        });
      },
      [
        destinationKey,
        recipientKey,
        quoteVersion,
        data.payment,
        cart.needsShipping,
        data.collection,
        required,
        results.key,
        results.loading,
        results.error,
        isOwner,
        awaitingSavedPin,
      ],
    );

    // The server is authoritative for eligibility and quote TTL. Only a selected
    // Instant service can initiate expiry refresh; native courier changes are inert.
    useEffect(
      function () {
        if (
          !ownsEffects() ||
          !instantSelected ||
          (false === instantStatus.eligible && !recoverableQuote) ||
          !cart.needsShipping ||
          data.collection ||
          !Number.isFinite(expiresAt) ||
          expiresAt <= 0 ||
          refreshedDeadline === expiresAt
        ) {
          return;
        }
        var timer = root.setTimeout(
          function () {
            if (!ownsEffects() || refreshedDeadline === expiresAt) {
              return;
            }
            refreshedDeadline = expiresAt;
            setShared('refreshVersion', ++refreshVersion);
          },
          expiredQuote
            ? 0
            : Math.min(2147483647, Math.max(0, expiresAt * 1000 - Date.now() + 1000)),
        );
        var cleanup = function () {
          root.clearTimeout(timer);
          effectCleanups.delete(cleanup);
        };
        effectCleanups.add(cleanup);
        return cleanup;
      },
      [
        expiresAt,
        instantSelected,
        instantStatus.eligible,
        recoverableQuote,
        isOwner,
        cart.needsShipping,
        data.collection,
      ],
    );

    var previousEditing = useRef(presentation.editing);
    useEffect(
      function () {
        var opened = presentation.editing && !previousEditing.current;
        previousEditing.current = presentation.editing;
        if (
          opened &&
          ownsEffects() &&
          results.key === addressKey &&
          !results.loading &&
          (results.error || !results.options.length)
        ) {
          setShared('retryLookup', function (previous) {
            return previous + 1;
          });
        }
      },
      [presentation.editing, isOwner, addressKey],
    );

    useEffect(
      function () {
        if (!ownsEffects()) {
          return;
        }
        var lookupKey = addressKey + '|' + required + '|' + retryLookup;
        // A replacement owner reuses completed results, but restarts an aborted lookup.
        if (state.lookupKey === lookupKey && !state.results.loading) {
          return;
        }
        state.lookupKey = lookupKey;
        var generation = ++lookupGeneration.current;
        var controller = new AbortController();
        var timer;
        var deadline;
        setResults({
          key: addressKey,
          options: [],
          loading: required && postcode.length >= 3,
          error: false,
        });
        if (required && postcode.length >= 3) {
          timer = root.setTimeout(function () {
            var body = new URLSearchParams({
              action: 'kiriminaja_subdistrict_search',
              nonce: config.nonce,
              term: postcode,
            });
            if (retryLookup > 0) body.set('retry', '1');
            deadline = root.setTimeout(function () {
              if (
                generation !== lookupGeneration.current ||
                controller.signal.aborted ||
                !ownsEffects()
              ) {
                return;
              }
              controller.abort();
              setResults({
                key: addressKey,
                options: [],
                loading: false,
                error: true,
                timedOut: true,
              });
            }, 10000);
            root
              .fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString(),
                signal: controller.signal,
              })
              .then(function (response) {
                if (!response.ok) {
                  throw new Error('District lookup failed');
                }
                return response.json();
              })
              .then(function (response) {
                if (generation !== lookupGeneration.current || controller.signal.aborted) {
                  return;
                }
                if (!response.success || !Array.isArray(response.data)) {
                  throw new Error('District lookup failed');
                }
                if (
                  response.data.some(function (row) {
                    return (
                      !row ||
                      !/^[1-9][0-9]*$/.test(String(row.id)) ||
                      typeof row.text !== 'string' ||
                      !row.text.trim()
                    );
                  })
                )
                  throw new Error('Invalid subdistrict rows');
                var options = response.data.map(function (row) {
                  return { value: String(row.id), label: String(row.text) };
                });
                root.clearTimeout(deadline);
                setResults({ key: addressKey, options: options, loading: false, error: false });
                var saved = savedSelections[postcode];
                var stored = config.savedDestination;
                setSelection(function (previous) {
                  var candidates = [
                    previous && previous.key === addressKey ? previous.id : '',
                    stored && stored.postcode === postcode && stored.country === country
                      ? stored.district_id
                      : '',
                    saved && saved.destination_id,
                    address[fieldId],
                    String(config.districtPostcode || '').replace(/\s+/g, '') === postcode &&
                      config.district &&
                      config.district.id,
                  ];
                  var restored = null;
                  candidates.some(function (id) {
                    restored = options.find(function (option) {
                      return option.value === String(id || '');
                    });
                    return Boolean(restored);
                  });
                  return restored
                    ? { id: restored.value, label: restored.label, key: addressKey }
                    : null;
                });
              })
              .catch(function () {
                root.clearTimeout(deadline);
                if (generation === lookupGeneration.current && !controller.signal.aborted) {
                  setResults({ key: addressKey, options: [], loading: false, error: true });
                }
              });
          }, 250);
        }
        var cleanup = function () {
          controller.abort();
          root.clearTimeout(timer);
          root.clearTimeout(deadline);
          effectCleanups.delete(cleanup);
        };
        effectCleanups.add(cleanup);
        return cleanup;
      },
      [addressKey, required, retryLookup, isOwner],
    );

    useEffect(
      function () {
        if (ownsEffects() && state.retryUpdate) {
          state.retryUpdate = 0;
          queue.retry();
        }
      },
      [state.retryUpdate, isOwner],
    );

    var saving = Boolean(updateState.pending || updateState.inFlight);
    var quoteStale =
      instantSelected &&
      (manualRefreshNeeded ||
        (expiresAt > 0 &&
          refreshedDeadline === expiresAt &&
          (false !== instantStatus.eligible || recoverableQuote)));
    var showRetry = instantSelected && quoteStale && !saving;
    var quoteFailure =
      updateState.error && updateState.pending && updateState.pending.refresh_instant;
    var unavailable =
      false === instantStatus.eligible ? instantStatus.message || strings.instantUnavailable : '';
    var lookupEmpty =
      results.key === addressKey && !results.loading && !results.error && !results.options.length;
    var districtUnverified =
      !currentSelection ||
      results.key !== addressKey ||
      results.loading ||
      results.error ||
      lookupEmpty;
    var lookupMessage =
      postcode.length < 3
        ? strings.postcodeRequired
        : results.loading || results.key !== addressKey
          ? strings.loading
          : results.error
            ? results.timedOut
              ? strings.lookupTimeout
              : strings.lookupFailed
            : lookupEmpty
              ? strings.emptyRetry || strings.empty
              : strings.districtRequired;
    var message = updateState.stalled
      ? strings.saveStalled
      : updateState.error
        ? quoteFailure
          ? strings.quoteRefreshFailed
          : strings.updateFailed
        : saving
          ? strings.saving
          : required &&
              (!currentSelection || results.key !== addressKey || results.loading || results.error)
            ? strings.districtRequired
            : quoteStale
              ? unavailable || strings.quoteRefreshFailed
              : instantSelected
                ? unavailable
                : '';
    if (required && districtUnverified) message = lookupMessage;
    useEffect(
      function () {
        if (ownsEffects()) {
          setValidation(required && kiriminajaSelected ? message : '');
        }
      },
      [message, required, kiriminajaSelected, isOwner],
    );

    if (api.disabled || !required || ('order-summary' === slot && hasInnerPlacement())) {
      return null;
    }
    if (!presentation.editing && element.createPortal) {
      if (!isOwner || !presentation.cardTarget) {
        return null;
      }
      var checking = results.loading || results.key !== addressKey || awaitingSavedPin;
      var districtReady = currentSelection && !checking && !results.error;
      return element.createPortal(
        h(
          'div',
          {
            className: 'kiriof-address-status',
            role: 'status',
            'aria-live': 'polite',
            'aria-atomic': 'true',
          },
          !districtReady && (checking || (!results.error && !lookupEmpty))
            ? addressBadge(checking ? strings.checkingDistrict : strings.districtNotSet, false)
            : null,
          addressBadge(
            currentPin ? strings.pinLocation : strings.needPinLocation,
            Boolean(currentPin),
            strings.pinRequirement,
          ),
          !checking && (results.error || lookupEmpty || updateState.error || quoteStale)
            ? addressBadge(message, false)
            : null,
          !districtUnverified && !updateState.error && !quoteStale && unavailable
            ? addressBadge(unavailable, false)
            : null,
          updateState.uncertain
            ? h(
                'button',
                {
                  type: 'button',
                  onClick: function () {
                    root.location.reload();
                  },
                },
                strings.reloadCheckout,
              )
            : results.error || lookupEmpty || updateState.error || showRetry
              ? h(
                  'button',
                  {
                    type: 'button',
                    onClick: function () {
                      if (results.error || lookupEmpty) {
                        setShared('retryLookup', function (previous) {
                          return previous + 1;
                        });
                      } else if (updateState.error) {
                        setShared('retryUpdate', state.retryUpdate + 1);
                      } else {
                        setShared('refreshVersion', ++refreshVersion);
                      }
                    },
                  },
                  strings.retry,
                )
              : null,
        ),
        presentation.cardTarget,
      );
    }
    var status = districtUnverified ? lookupMessage : message;
    return h(
      'div',
      { className: 'kiriof-buyer-district kiriof-buyer-district--inner-block' },
      h(
        'div',
        { className: 'wc-blocks-components-select' },
        h(
          'div',
          { className: 'wc-blocks-components-select__container' },
          h(
            'label',
            { className: 'wc-blocks-components-select__label', htmlFor: inputId },
            strings.district,
          ),
          h(
            'select',
            {
              id: inputId,
              className: 'wc-blocks-components-select__select',
              value: currentSelection ? currentSelection.id : '',
              disabled: postcode.length < 3 || results.loading,
              'aria-required': true,
              'aria-describedby': inputId + '-status',
              'aria-invalid': Boolean(
                results.error ||
                (results.key === addressKey && !results.loading && !currentSelection),
              ),
              onChange: function (event) {
                var value = event.target.value;
                var selected = results.options.find(function (option) {
                  return option.value === value;
                });
                if (selected) {
                  savedSelections[postcode] = {
                    destination_id: selected.value,
                    destination_name: selected.label,
                  };
                } else {
                  delete savedSelections[postcode];
                }
                setSelection(
                  selected ? { id: selected.value, label: selected.label, key: addressKey } : null,
                );
              },
            },
            h('option', { value: '' }, strings.selectDistrict),
            (results.key === addressKey ? results.options : []).map(function (option) {
              return h('option', { key: option.value, value: option.value }, option.label);
            }),
          ),
          h(
            'svg',
            {
              className: 'wc-blocks-components-select__expand',
              viewBox: '0 0 24 24',
              width: 24,
              height: 24,
              'aria-hidden': 'true',
              focusable: 'false',
            },
            h('path', { d: 'm6 9 6 6 6-6', fill: 'none', stroke: 'currentColor', strokeWidth: 2 }),
          ),
        ),
      ),
      h('p', { id: inputId + '-status', role: 'status', 'aria-live': 'polite' }, status),
      !districtUnverified && unavailable && unavailable !== status
        ? h('p', { role: 'status', 'aria-live': 'polite' }, unavailable)
        : null,
      updateState.uncertain
        ? h(
            'button',
            {
              type: 'button',
              className: 'wc-block-components-button wp-element-button',
              onClick: function () {
                root.location.reload();
              },
            },
            strings.reloadCheckout,
          )
        : null,
      results.error || lookupEmpty || ((updateState.error || showRetry) && !updateState.uncertain)
        ? h(
            'button',
            {
              type: 'button',
              className: 'wc-block-components-button wp-element-button',
              onClick: function () {
                if (results.error || lookupEmpty) {
                  setShared('retryLookup', function (previous) {
                    return previous + 1;
                  });
                }
                if (showRetry && !results.error && !updateState.error) {
                  setShared('refreshVersion', ++refreshVersion);
                }
                if (updateState.error) {
                  setShared('retryUpdate', state.retryUpdate + 1);
                }
              },
            },
            strings.retry,
          )
        : null,
    );
  }

  if (destinationSlot) {
    wp.plugins.registerPlugin('kiriminaja-official-buyer-destination', {
      scope: 'woocommerce-checkout',
      render: function () {
        return h(destinationSlot, null, h(DistrictControl, { slot: 'order-summary' }));
      },
    });
  }
  if (supportsDistrictInnerBlock) {
    blocks.registerCheckoutBlock({
      force: true,
      metadata: {
        name: 'kiriminaja-official/checkout-district',
        parent: ['woocommerce/checkout-shipping-address-block'],
        attributes: { lock: { type: 'object', default: { remove: true, move: true } } },
      },
      component: function () {
        return h(DistrictControl, { slot: 'shipping-address' });
      },
    });
  }
  var unsubscribe = wp.data.subscribe(function () {
    if (owner && !api.disabled) {
      queue.resume();
    }
  });
  // Capture native user intent only. Store refreshes/defaults never overwrite it.
  if (document.addEventListener) {
    document.addEventListener('change', reviewNativeShipping, true);
    document.addEventListener('click', reviewNativeShipping, true);
  }
  root.addEventListener('pagehide', function (event) {
    if (event && event.persisted) {
      return;
    }
    api.disabled = true;
    root.clearTimeout(readinessTimer);
    if (api.pending) {
      api.pending = false;
      resolveReady(false);
    }
    unsubscribe();
    if (document.removeEventListener) {
      document.removeEventListener('change', reviewNativeShipping, true);
      document.removeEventListener('click', reviewNativeShipping, true);
    }
    validationDispatch.clearValidationError(shippingSelectionErrorId);
    validationDispatch.clearValidationError(shippingSelectionPendingId);
    effectCleanups.forEach(function (cleanup) {
      cleanup();
    });
    queue.dispose();
  });
}
