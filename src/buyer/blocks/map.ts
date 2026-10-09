// @ts-nocheck -- React hooks and registration are supplied by Woo, not bundled React.
import type { BlocksRoot } from './types';
import { createLocationGate, coverageStatus } from '../map/leaflet';
import { mapProviderRegistry } from '../map/providers';
import { loadGoogleMaps } from '../map/google-loader';
import { resolveMapConfig } from '../map/config';
import type { GoogleMapsAPI, GoogleMapsWindow, MapSession } from '../map/types';
import { shippingAddress } from './address';
import { createMapPresentation } from './svelte-bridge';

export function bootBlocksMap(root: BlocksRoot): void {
  var wp = root.wp;
  var wc = root.wc;
  var settings = wc && wc.wcSettings;
  var integration =
    settings && settings.getSetting
      ? settings.getSetting('kiriminaja-official-buyer_data', {})
      : {};
  var config = root.kiriofMapCheckoutConfig || integration.map || {};
  var blocks = wc && wc.blocksCheckout;
  if (
    !config.enabled ||
    !wp ||
    !wp.element ||
    !wp.data ||
    !wp.data.useSelect ||
    !blocks ||
    !blocks.registerCheckoutBlock
  ) {
    return;
  }
  var h = wp.element.createElement;
  var mapPresentation = createMapPresentation(wp.element);
  var useState = wp.element.useState;
  var useEffect = wp.element.useEffect;
  var useRef = wp.element.useRef;
  // Resolve the optional hook once so later bridge availability cannot change hook order.
  var usePresentation =
    root.kiriofAddressPresentation && root.kiriofAddressPresentation.usePresentation;
  var strings = config.i18n || {};
  var buyerStrings = (root.kiriofBuyerCheckoutConfig || integration).i18n || {};

  function MapControl() {
    var presentation = usePresentation ? usePresentation() : { editing: true };
    var data = wp.data.useSelect(function (select) {
      var checkout = select('wc/store/checkout');
      return {
        cart: select('wc/store/cart').getCartData(),
        collection: checkout.prefersCollection ? checkout.prefersCollection() : false,
      };
    }, []);
    var cart = data.cart || {};
    var coverageExtension =
      cart.extensions && cart.extensions['kiriminaja-official-instant-coverage'];
    var coverage =
      coverageExtension && Object.prototype.hasOwnProperty.call(coverageExtension, 'coverage')
        ? coverageExtension.coverage
        : config.coverage;
    var coverageKey = JSON.stringify(coverage || null);
    var hasCoverage = Boolean(coverageStatus(coverage, coverage && coverage.origin));
    var address = shippingAddress(cart.shippingAddress || {});
    var addressKey = JSON.stringify(address);
    var visible = Boolean(
      config.enabled &&
      presentation.editing &&
      cart.needsShipping &&
      address.country === 'ID' &&
      !data.collection,
    );
    var node = useRef(null);
    var session = useRef(null);
    var latest = useRef({ address: address, key: addressKey });
    latest.current = { address: address, key: addressKey };
    var pointState = useState(null);
    var point = pointState[0];
    var setPoint = pointState[1];
    var movingState = useState(false);
    var moving = movingState[0];
    var setMoving = movingState[1];
    var errorState = useState('');
    var error = errorState[0];
    var setError = errorState[1];
    var coverageState = useState(null);
    var coverageResult = coverageState[0];
    var setCoverage = coverageState[1];
    var grantState = useState(null);
    var grant = grantState[0];
    var setGrant = grantState[1];
    var granted = visible && grant && grant.key === addressKey;
    var selected = point && point.key === addressKey ? point : null;
    function apply(next, snapshot, key) {
      var buyer = root.kiriofBuyerCheckout;
      if (
        latest.current.key !== key ||
        !buyer ||
        !buyer.setCoordinates ||
        !buyer.setCoordinates(snapshot, next)
      ) {
        return false;
      }
      setPoint(next ? Object.assign({ key: key }, next) : null);
      setError('');
      return true;
    }
    useEffect(
      function () {
        setGrant(null);
        setPoint(null);
        setError('');
        setMoving(false);
        setCoverage(null);
        if (!visible) {
          return;
        }
        var gate = createLocationGate({
          geolocation: root.navigator && root.navigator.geolocation,
          onSuccess: function (next) {
            if (latest.current.key === addressKey) {
              setGrant({ key: addressKey, point: next });
            }
          },
          onError: function (code) {
            if (latest.current.key !== addressKey) return;
            setError(
              code === 'permission'
                ? strings.mapPermission
                : code === 'location'
                  ? strings.mapLocationFailed
                  : strings.mapUnavailable || 'Map unavailable',
            );
          },
        });
        gate.start();
        return function () {
          gate.dispose();
        };
      },
      [addressKey, visible],
    );
    useEffect(
      function () {
        if (!granted || !node.current) {
          return;
        }
        var initial = root.kiriofBuyerCheckout && root.kiriofBuyerCheckout.getCoordinates(address);
        if (initial) {
          setPoint(Object.assign({ key: addressKey }, initial));
        }
        var disposed = false;
        var mapSession: MapSession | undefined;
        function current() {
          return !disposed && latest.current.key === addressKey;
        }
        function unavailable() {
          if (current()) setError(strings.mapUnavailable || 'Map unavailable');
        }
        function instantiate(
          provider: ReturnType<typeof resolveMapConfig>,
          google?: GoogleMapsAPI,
        ) {
          if (!current()) return;
          mapSession = mapProviderRegistry[provider.provider].createSession({
            ...provider,
            google: google,
            document: root.document,
            window: root as GoogleMapsWindow,
            leaflet: root.L,
            node: node.current,
            defaultCenter: [Number(grant.point.latitude), Number(grant.point.longitude)],
            initial: initial,
            label: strings.mapTitle,
            coverage: coverage,
            onCoverage: function (status) {
              if (current()) setCoverage({ key: coverageKey, status: status });
            },
            geolocation: root.navigator && root.navigator.geolocation,
            onSelect: function (next) {
              return current() && apply(next, address, addressKey);
            },
            onMove: function (next) {
              if (current()) setMoving(next);
            },
            onError: function (code) {
              if (!current()) return;
              setError(
                code === 'invalid'
                  ? strings.mapInvalid
                  : code === 'permission'
                    ? strings.mapPermission
                    : code === 'location'
                      ? strings.mapLocationFailed
                      : strings.mapUnavailable || 'Map unavailable',
              );
            },
          });
          session.current = mapSession;
          if (!initial && mapSession.isAvailable()) {
            mapSession.pick(grant.point.latitude, grant.point.longitude, true);
          }
        }
        try {
          var provider = resolveMapConfig(config);
          if (provider.provider === 'google') {
            void (async function () {
              try {
                var google = await loadGoogleMaps(
                  provider.apiKey,
                  root.document,
                  root as GoogleMapsWindow,
                );
                if (!current()) return;
                instantiate(provider, google);
              } catch {
                unavailable();
              }
            })();
          } else instantiate(provider);
        } catch {
          unavailable();
        }
        return function () {
          disposed = true;
          mapSession?.dispose();
          if (session.current === mapSession) {
            session.current = null;
          }
        };
      },
      [addressKey, visible, grant, coverageKey],
    );
    if (!visible) {
      return null;
    }
    var status = moving ? '' : error || (!granted ? strings.mapLocating : '');
    var outside =
      coverageResult && coverageResult.key === coverageKey
        ? coverageResult.status
        : coverageStatus(
            coverage,
            root.kiriofBuyerCheckout && root.kiriofBuyerCheckout.getCoordinates(address),
          );
    return h(
      'section',
      { className: 'kiriof-buyer-map', 'aria-label': strings.mapTitle },
      h('h3', { className: 'kiriof-buyer-map__title' }, strings.mapTitle),
      granted
        ? h(
            'div',
            { className: 'kiriof-buyer-map__viewport' + (moving ? ' is-moving' : '') },
            h('div', {
              className: 'kiriof-buyer-map__canvas',
              ref: node,
              'aria-label': strings.mapHelp,
              'aria-description': strings.mapKeyboard,
            }),
            hasCoverage
              ? h(mapPresentation.Information, {
                  attributes: {
                    className: 'kiriof-buyer-map__information',
                    role: 'note',
                    hidden: moving,
                    tabIndex: 0,
                    'aria-label': strings.mapCoverage,
                  },
                  model: {
                    badge: strings.mapCoverageBadge,
                    coverage: strings.mapCoverage,
                    hasCoverage: hasCoverage,
                  },
                })
              : null,
            h(
              'div',
              { className: 'kiriof-buyer-map__indicator' },
              h(
                'svg',
                {
                  viewBox: '0 0 32 44',
                  width: 32,
                  height: 44,
                  focusable: 'false',
                  'aria-hidden': 'true',
                },
                h('path', {
                  d: 'M16 1C7.7 1 1 7.7 1 16c0 11 15 28 15 28s15-17 15-28C31 7.7 24.3 1 16 1Z',
                  fill: 'currentColor',
                  stroke: '#fff',
                  strokeWidth: 2,
                }),
                h('circle', { cx: 16, cy: 16, r: 10, fill: '#fff' }),
              ),
              h(mapPresentation.Status, {
                attributes: {
                  className:
                    'kiriof-buyer-map__pin-status ' + (selected ? 'is-complete' : 'is-warning'),
                  hidden: moving,
                  role: 'status',
                  'aria-live': 'polite',
                },
                model: {
                  complete: Boolean(selected),
                  text: selected
                    ? strings.pinLocation || buyerStrings.pinLocation
                    : strings.needPinLocation || buyerStrings.needPinLocation,
                },
              }),
            ),
            h(
              'button',
              {
                type: 'button',
                className: 'kiriof-buyer-map__locate',
                'aria-label': strings.mapLocate,
                title: strings.mapLocate,
                onClick: function () {
                  if (session.current) {
                    session.current.locate();
                  }
                },
              },
              h(
                'svg',
                {
                  viewBox: '0 0 24 24',
                  width: 22,
                  height: 22,
                  fill: 'none',
                  stroke: 'currentColor',
                  strokeWidth: 2,
                  'aria-hidden': 'true',
                  focusable: 'false',
                },
                h('circle', { cx: 12, cy: 12, r: 7 }),
                h('circle', { cx: 12, cy: 12, r: 2 }),
                h('path', { d: 'M12 2v3 M12 19v3 M2 12h3 M19 12h3' }),
              ),
            ),
          )
        : null,
      status
        ? h(
            'p',
            { className: 'kiriof-buyer-map__status', role: 'status', 'aria-live': 'polite' },
            status,
          )
        : null,
      !moving && outside && !outside.inside
        ? h(
            'p',
            {
              className: 'kiriof-buyer-map__coverage-warning',
              role: 'note',
              'aria-live': 'polite',
            },
            strings.mapOutsideRadius,
          )
        : null,
    );
  }
  blocks.registerCheckoutBlock({
    force: true,
    metadata: {
      name: 'kiriminaja-official/map-checkout',
      parent: ['woocommerce/checkout-shipping-address-block'],
      attributes: { lock: { type: 'object', default: { remove: true, move: true } } },
    },
    component: MapControl,
  });
}
