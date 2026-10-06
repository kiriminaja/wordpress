import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

declare global {
  interface Window {
    __geoCalls: number;
    __maps: number;
    __checkoutUpdates: number;
    __allowGeo: boolean;
  }
}

const root = fileURLToPath(new URL('../../../', import.meta.url)).replace(/\/$/, '');
const binding = {
  address_1: 'Jalan Magelang 10',
  address_2: 'Unit 2',
  city: 'Sleman',
  state: 'YO',
  postcode: '55581',
  country: 'ID',
};
const district = { id: '4567', text: 'Sleman, Sleman, DI Yogyakarta, 55581' };

function php(name: string, input: unknown) {
  return JSON.parse(
    execFileSync('php', [root + '/tests/fixtures/' + name, JSON.stringify(input)], {
      encoding: 'utf8',
    }),
  );
}

// Exact minimal WC Booster flex rules, upstream SVN revision 3729938:
// assets/build/css/style.css lines 124–141 and 216–233. No provider rates are fetched.
const boosterCss = `body.wc-booster-checkout-customization .woocommerce-checkout form.woocommerce-checkout .woocommerce-billing-fields__field-wrapper {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  margin: 0 -10px;
}
body.wc-booster-checkout-customization .woocommerce-checkout form.woocommerce-checkout .woocommerce-billing-fields__field-wrapper .form-row {
  flex: 0 0 33.33%;
  position: relative;
  padding-top: 26px;
  margin-bottom: 20px;
}
@media (max-width: 768px) {
  body.wc-booster-checkout-customization .woocommerce-checkout form.woocommerce-checkout .woocommerce-billing-fields__field-wrapper .form-row {
    max-width: 50%;
    flex: 0 0 50%;
  }
}
body.wc-booster-checkout-customization .woocommerce form .shipping_address .woocommerce-shipping-fields__field-wrapper {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  margin: 0;
}
body.wc-booster-checkout-customization .woocommerce form .shipping_address .woocommerce-shipping-fields__field-wrapper .form-row {
  flex: 0 0 33.33%;
  position: relative;
  padding-top: 26px;
  margin-bottom: 20px;
}
@media (max-width: 768px) {
  body.wc-booster-checkout-customization .woocommerce form .shipping_address .woocommerce-shipping-fields__field-wrapper .form-row {
    max-width: 50%;
    flex: 0 0 50%;
  }
}`;
const fixtureRates = [
  { id: 'kiriminaja-instant:1:gosend:instant', label: 'Fixture Instant courier', cost: 20000 },
  { id: 'kiriminaja-official:1:fixture:regular', label: 'Fixture Regular courier', cost: 15000 },
];

function html(enabled: boolean, geometry = false, shipping: false | 'enhanced' | 'native' = false) {
  const src = (file: string) => readFileSync(root + '/assets/wp/js/' + file, 'utf8');
  const dependency = (file: string) =>
    readFileSync(new URL('../node_modules/' + file, import.meta.url), 'utf8');
  const addressFields = (scope: string) =>
    Object.entries(binding)
      .map(
        ([key, value], index) => `
		<p class="form-row form-row-wide" id="${scope}_${key}_field" data-priority="${70 + index * 10}">
			<label for="${scope}_${key}">${scope} ${key}</label>
			<input id="${scope}_${key}" name="${scope}_${key}" value="${value}">
		</p>`,
      )
      .join('');
  const districtFields = (scope: string) => {
    const id = scope === 'billing' ? 'kiriof_destination_area' : 'kiriof_shipping_destination_area';
    return `<p class="form-row form-row-wide" id="${id}_field" data-priority="60">
			<label for="${id}">${scope === 'billing' ? 'District' : 'Shipping district'}</label>
			<select id="${id}" name="${id}"><option value=""></option><option value="123" selected>Fixture district</option></select>
			<input type="hidden" id="${id}_name" name="${id}_name" value="Fixture district">
		</p>`;
  };
  const scripts = [
    ...(geometry ? [readFileSync(root + '/assets/lib/leaflet/leaflet.js', 'utf8')] : []),
    dependency('jquery/dist/jquery.min.js'),
    dependency('select2/dist/js/select2.full.min.js'),
    `window.__geoCalls = 0; window.__maps = 0; window.__checkoutUpdates = 0; window.__allowGeo = true;
		window.kiriofClassicCheckoutConfig = ${JSON.stringify({
      enabled,
      ownsDistrict: false,
      needsShipping: true,
      ajaxUrl: '/admin-ajax.php',
      nonce: 'fixture-pin-nonce',
      map: {
        enabled: true,
        tiles: 'https://tiles.fixture.test/{z}/{x}/{y}.png',
        attribution: 'Fixture tiles',
      },
    })};
		window.kiriofBillingAddressConfig = {
			isCheckout: true, isCart: false, fieldKey: 'kiriof_destination_area',
			ajaxUrl: '/admin-ajax.php', nonce: 'fixture-search-nonce',
			i18n: { selectOption: 'Select Option' }
		};
		// Only the production district module is bootstrapped, not the legacy
		// entry/ready hooks (which also initialize shipping/payment side effects).
		window.kiriofIsBlockCheckoutContext = function() { return false; };
		Object.defineProperty(navigator, 'geolocation', { value: {
			getCurrentPosition(ok) {
				window.__geoCalls++;
				if (window.__allowGeo) ok({ coords: { latitude: -7.7, longitude: 110.3 } });
			}
		} });
		${
      geometry
        ? `const nativeMap = L.map; L.map = function(...args) { window.__maps++; return nativeMap.apply(this, args); };`
        : `window.L = {
			map() {
				window.__maps++;
				const map = {
					setView() { return map; }, on() { return map; }, invalidateSize() {},
					getCenter() { return { lat: -7.7, lng: 110.3 }; }, remove() {}
				};
				return map;
			},
			tileLayer() { return { addTo() { return this; }, on() {} }; }
		};`
    }
		jQuery(document.body).on('update_checkout', () => {
			window.__checkoutUpdates++;
			setTimeout(() => jQuery(document.body).trigger('updated_checkout'), 0);
		});`,
    src('checkout/state.js'),
    src('checkout/classic-district.js'),
    'getSearchAreaKelurahan();',
    src('kiriof-checkout-session.js'),
    src('kiriof-map-checkout.js'),
    src('kiriof-classic-checkout-core.js'),
    src('kiriof-classic-checkout.js'),
    ...(shipping ? [
      src('checkout/shipping-payment.js'),
      `// Woo's delegated native shipping change boundary; never run COD/network ready hooks.
      window.__shippingUpdates = [];
      kiriofCodInsurance = function() { throw new Error('Unexpected COD fixture call'); };
      // Test-only rates above render via the real PHP template; they are not provider quotes.
      jQuery(document.body).on('change.fixtureWoo', 'input.shipping_method', function() {
        window.__shippingUpdates.push({ id: this.value, name: this.name, checked: this.checked });
        jQuery(document.body).trigger('update_checkout', { update_shipping_method: true });
      });
      ${shipping === 'native' ? 'jQuery.fn.select2 = undefined; jQuery.fn.selectWoo = undefined;' : ''}
      kiriofScheduleClassicShippingMethodSelectInit();`,
    ] : []),
    `document.getElementById('submit').onclick = () => {
			document.getElementById('outcome').textContent =
				jQuery('form.checkout').triggerHandler('checkout_place_order') === false ? 'blocked' : 'allowed';
		};`,
  ];
  return `<!DOCTYPE html><html><head>
		<style>${dependency('select2/dist/css/select2.min.css')}
			body { font-family: sans-serif; } form { max-width: 600px; margin: 24px; }
			.form-row { margin: 12px 0; } label { display: block; }
			.kiriof-classic-map { height: 100px; } [hidden] { display: none !important; }
		</style>
    ${
      geometry
        ? `<style>${readFileSync(root + '/assets/lib/leaflet/leaflet.css', 'utf8')}</style>
    <style>${readFileSync(root + '/assets/wp/css/kiriof-classic-checkout.css', 'utf8')}</style>
    <style>
      /* Adverse Woo/theme rules deliberately follow the plugin styles. */
      .woocommerce button.button { min-width: 125px; width: 145px; height: 50px;
        padding: 15px 24px; background: #00a632; color: white; font-size: 14px; }
      .woocommerce form.checkout .form-row { float: left; width: 48%; overflow: hidden; }
      ${boosterCss}
      .woocommerce a { text-decoration: underline; }
      body { margin: 0; } form.checkout { margin: 24px; width: calc(100% - 48px); }
    </style>`
        : ''
    }
    ${shipping ? `<style>${readFileSync(root + '/assets/wp/css/kj-wp-style.css', 'utf8')}</style>` : ''}
    </head><body${geometry ? ' class="woocommerce wc-booster-checkout-customization"' : ''}><main class="woocommerce-checkout"><form class="checkout woocommerce-checkout">
		<div class="woocommerce-billing-fields">
			<h3>Billing details</h3>
			<div class="woocommerce-billing-fields__field-wrapper">
				<p class="form-row form-row-wide" id="billing_email_field" data-priority="110">
					<label for="billing_email">Email address</label>
					<input type="email" id="billing_email" name="billing_email" value="buyer@example.test">
				</p>
				${districtFields('billing')}${addressFields('billing')}
			</div>
		</div>
		<div class="woocommerce-shipping-fields">
			<h3><label for="ship-to-different-address-checkbox">Ship to a different address?</label></h3>
			<input id="ship-to-different-address-checkbox" type="checkbox" name="ship_to_different_address">
			<div class="shipping_address" hidden>
				<div class="woocommerce-shipping-fields__field-wrapper">
					${districtFields('shipping')}${addressFields('shipping')}
				</div>
			</div>
		</div>
        ${shipping ? `<div id="order_review"><table class="shop_table woocommerce-checkout-review-order-table"><tbody>${php('classic-shipping-presentation-runtime.php', { rates: fixtureRates, chosen: fixtureRates[0].id }).html}</tbody></table></div>` : '<input class="shipping_method" name="shipping_method[0]" type="radio" value="kiriminaja-instant:1:gosend:instant" checked>'}
		<button type="button" id="submit">Place order</button>
		<output id="outcome" role="status"></output>
	</form></main><script>${scripts.join('\n').replaceAll('</script', '<\\/script')}</script>
	<script>jQuery('#ship-to-different-address-checkbox').on('change', function() {
		document.querySelector('.shipping_address').hidden = !this.checked;
	});</script></body></html>`;
}

// Every request, including unexpected third-party URLs, is intercepted. Never
// fall through to a server, WordPress installation, tile provider or live API.
async function checkout(
  app: any,
  browser: any,
  enabled: boolean,
  searchResponse: unknown = { success: true, data: [district] },
  geometry = false,
  shipping: false | 'enhanced' | 'native' = false,
) {
  const requests: { url: string; method: string; body: Record<string, string> }[] = [];
  const unexpected: string[] = [];
  await browser.route('**/*', async (route: any) => {
    const url = new URL(route.request.url);
    if (
      url.origin === 'https://fixture.test' &&
      url.pathname === '/checkout' &&
      route.request.method === 'GET'
    ) {
      await route.fulfill({ contentType: 'text/html', body: html(enabled, geometry, shipping) });
      return;
    }
    if (geometry && url.origin === 'https://tiles.fixture.test' && route.request.method === 'GET') {
      await route.fulfill({
        contentType: 'image/svg+xml',
        body: '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#edf2ee"/><path d="M0 128H256M128 0V256" stroke="#cbd5ce"/></svg>',
      });
      return;
    }
    if (
      url.origin === 'https://fixture.test' &&
      url.pathname === '/admin-ajax.php' &&
      route.request.method === 'POST'
    ) {
      const body = Object.fromEntries(new URLSearchParams(route.request.postData));
      requests.push({ url: route.request.url, method: route.request.method, body });
      if (
        body.action === 'kiriminaja_subdistrict_search' ||
        body.action === 'kiriof-session-save'
      ) {
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify(
            body.action === 'kiriminaja_subdistrict_search' ? searchResponse : { success: true },
          ),
        });
        return;
      }
    }
    unexpected.push(route.request.url);
    await route.fulfill({ status: 404, body: 'Unexpected fixture request' });
  });
  await app.open('/checkout');
  return {
    requests,
    unexpected,
    searches: () =>
      requests.filter((request) => request.body.action === 'kiriminaja_subdistrict_search'),
    saves: () => requests.filter((request) => request.body.action === 'kiriof-session-save'),
  };
}

async function placement(browser: any) {
  return browser.evaluate(() => {
    const panel = document.querySelector('.kiriof-classic-pin');
    const shipping = (
      document.querySelector('#ship-to-different-address-checkbox') as HTMLInputElement
    ).checked;
    const row = document.querySelector(
      shipping ? '#kiriof_shipping_destination_area_field' : '#kiriof_destination_area_field',
    );
    const billing = document.querySelector('.woocommerce-billing-fields');
    const contact = document.querySelector('.kiriof-classic-contact');
    const email = document.querySelector('#billing_email') as HTMLInputElement;
    return {
      underDistrict: row.nextElementSibling === panel,
      priority: panel.getAttribute('data-priority'),
      panels: document.querySelectorAll('.kiriof-classic-pin').length,
      contacts: document.querySelectorAll('.kiriof-classic-contact').length,
      contactFirst: billing.firstElementChild === contact,
      billingHeading: contact.nextElementSibling.textContent,
      emailInContact: contact.contains(email),
      emailValue: email.value,
      emailName: email.name,
    };
  });
}

const expectedPlacement = {
  underDistrict: true,
  priority: '60.5',
  panels: 1,
  contacts: 1,
  contactFirst: true,
  billingHeading: 'Billing details',
  emailInContact: true,
  emailValue: 'buyer@example.test',
  emailName: 'billing_email',
};

test('seller-disabled Instant does not alter Classic district or ask for location', async ({
  app,
  browser,
  screen,
}) => {
  const fixture = await checkout(app, browser, false);
  await expect(browser.locator('#kiriof_destination_area_field .select2-container')).toBeVisible();
  await expect(screen.getByLabel('Email address')).toHaveValue('buyer@example.test');
  expect(
    await browser.evaluate(() => ({
      geo: window.__geoCalls,
      maps: window.__maps,
      pin: document.querySelectorAll('.kiriof-classic-pin').length,
      contact: document.querySelectorAll('.kiriof-classic-contact').length,
      district: (document.querySelector('#kiriof_destination_area') as HTMLSelectElement).value,
      emailInNativeWrapper: document
        .querySelector('.woocommerce-billing-fields__field-wrapper')
        .contains(document.querySelector('#billing_email')),
    })),
  ).toEqual({ geo: 0, maps: 0, pin: 0, contact: 0, district: '123', emailInNativeWrapper: true });
  expect(fixture.requests).toHaveLength(0);
  expect(fixture.unexpected).toEqual([]);
});

test('Classic pin saves only coordinates context and an address edit invalidates Instant submission', async ({
  app,
  browser,
  screen,
}) => {
  const fixture = await checkout(app, browser, true);
  await expect(screen.getByLabel('Delivery location map')).toBeVisible();
  await expect(browser.locator('.kiriof-classic-pin-state')).toContainText('Pin Location');
  await expect
    .poll(() =>
      browser.evaluate(() =>
        document.querySelector('.kiriof-classic-pin-state').classList.contains('is-complete'),
      ),
    )
    .toBe(true);
  await expect(browser.locator('#kiriof_destination_area_field .select2-container')).toBeVisible();

  const request = fixture.saves().at(-1);
  expect(request.body.action).toBe('kiriof-session-save');
  expect(request.body.nonce).toBe('fixture-pin-nonce');
  const data = JSON.parse(request.body.data);
  expect(data.action).toBe('sync_classic_pin');
  expect(data.address_scope).toBe('billing');
  expect(data.effective_address).toEqual(binding);
  expect(data.destination.version).toBe(2);
  expect(data.destination.district_id).toBe('123');
  expect(data.destination.destination_latitude).toBe('-7.7000000');
  expect(data.destination.destination_longitude).toBe('110.3000000');
  expect(Object.keys(data).sort()).toEqual([
    'action',
    'address_scope',
    'destination',
    'effective_address',
  ]);

  await screen.getByRole('button', 'Place order').tap();
  await expect(browser.locator('#outcome')).toContainText('allowed');
  // The next location request remains pending, as a real browser permission
  // prompt would. An old pin must not authorize the newly edited address.
  await browser.evaluate(() => {
    window.__allowGeo = false;
    return true;
  });
  await screen.getByLabel('billing address_1').fill('Jalan Kaliurang 20');
  await screen.getByRole('button', 'Place order').tap();
  await expect(browser.locator('#outcome')).toContainText('blocked');
  await expect(browser.locator('.kiriof-classic-pin-state')).toContainText('Need Pin Location');
  expect(fixture.searches()).toHaveLength(0);
  expect(fixture.unexpected).toEqual([]);
});

test('Classic placement survives separate shipping toggles and Woo native row reordering without an observer loop', async ({
  app,
  browser,
  screen,
}) => {
  const fixture = await checkout(app, browser, true);
  await expect(screen.getByRole('heading', 'Contact Information')).toBeVisible();
  await expect.poll(() => placement(browser)).toEqual(expectedPlacement);
  const locate = screen.getByRole('button', 'Current location');
  await expect(locate).toBeVisible();
  expect(
    await browser.evaluate(() => ({
      text: document.querySelector('.kiriof-classic-map-locate').textContent,
      svg: document.querySelectorAll('.kiriof-classic-map-locate svg').length,
      badgeInViewport: !!document.querySelector(
        '.kiriof-classic-map-viewport > .kiriof-classic-pin-state[role="status"]',
      ),
      pinHeading: document.querySelectorAll('.kiriof-classic-pin h3').length,
    })),
  ).toEqual({ text: '', svg: 1, badgeInViewport: true, pinHeading: 0 });
  await expect
    .poll(() =>
      browser.evaluate(() =>
        document.querySelector('.kiriof-classic-pin-state').classList.contains('is-complete'),
      ),
    )
    .toBe(true);
  const geoBefore = await browser.evaluate(() => window.__geoCalls);
  await locate.tap();
  await expect.poll(() => browser.evaluate(() => window.__geoCalls)).toBeGreaterThan(geoBefore);
  await screen.getByLabel('Email address').fill('edited@example.test');
  const editedPlacement = { ...expectedPlacement, emailValue: 'edited@example.test' };

  for (const separate of [true, false, true, false]) {
    await screen.getByLabel('Ship to a different address?').tap();
    await expect
      .poll(() =>
        browser.evaluate(
          () =>
            (document.querySelector('#ship-to-different-address-checkbox') as HTMLInputElement)
              .checked,
        ),
      )
      .toBe(separate);
    await expect.poll(() => placement(browser)).toEqual(editedPlacement);
    // Woo's country/locale sorter operates on data-priority and may put the
    // pin elsewhere; themes can also return the email to its native wrapper.
    await browser.evaluate(() => {
      const panel = document.querySelector('.kiriof-classic-pin');
      const wrapper = panel.parentElement;
      const rows = Array.from(wrapper.children).filter((row) => row.classList.contains('form-row'));
      rows.sort(
        (a, b) => Number(b.getAttribute('data-priority')) - Number(a.getAttribute('data-priority')),
      );
      rows.forEach((row) => wrapper.append(row));
      document
        .querySelector('.woocommerce-billing-fields__field-wrapper')
        .append(document.querySelector('#billing_email_field'));
      return true;
    });
    await expect.poll(() => placement(browser)).toEqual(editedPlacement);
  }

  // Observe an idle window after the repair. A self-triggering childList loop
  // would starve this timer (and fail the Chromium test timeout).
  const idle = await browser.evaluate(async () => {
    let mutations = 0;
    const observer = new MutationObserver((records) => {
      mutations += records.length;
    });
    observer.observe(document.querySelector('form.checkout'), { childList: true, subtree: true });
    await new Promise((resolve) => setTimeout(resolve, 400));
    mutations = 0;
    const maps = window.__maps;
    const updates = window.__checkoutUpdates;
    await new Promise((resolve) => setTimeout(resolve, 400));
    observer.disconnect();
    return {
      mutations,
      extraMaps: window.__maps - maps,
      extraUpdates: window.__checkoutUpdates - updates,
    };
  });
  expect(idle).toEqual({ mutations: 0, extraMaps: 0, extraUpdates: 0 });
  expect(fixture.searches()).toHaveLength(0);
  expect(fixture.unexpected).toEqual([]);
});

// Real bundled Leaflet is used only here: the interaction fixtures above keep
// their small mock, while geometry must exercise Leaflet's actual control DOM.
async function mapGeometry(browser: any) {
  return browser.evaluate(() => {
    const rect = (selector: string) => {
      const r = document.querySelector(selector).getBoundingClientRect();
      return {
        left: r.left,
        right: r.right,
        top: r.top,
        bottom: r.bottom,
        width: r.width,
        height: r.height,
      };
    };
    const viewport = rect('.kiriof-classic-map-viewport');
    const map = rect('.kiriof-classic-map');
    const locate = rect('.kiriof-classic-map-locate');
    const badge = rect('.kiriof-classic-pin-state');
    const attribution = rect('.leaflet-control-attribution');
    const zoom = rect('.leaflet-control-zoom');
    const marker = rect('.kiriof-classic-map-indicator svg');
    const contained = (r: typeof map) =>
      r.width > 0 &&
      r.height > 0 &&
      r.left >= map.left &&
      r.right <= map.right &&
      r.top >= map.top &&
      r.bottom <= map.bottom;
    const disjoint = (a: typeof map, b: typeof map) =>
      a.right <= b.left || b.right <= a.left || a.bottom <= b.top || b.bottom <= a.top;
    return {
      panelFullWidth:
        Math.abs(
          rect('.kiriof-classic-pin').width -
            document.querySelector('.kiriof-classic-pin').parentElement.getBoundingClientRect()
              .width,
        ) < 1,
      addressGrid: getComputedStyle(document.querySelector('.kiriof-classic-pin').parentElement).display,
      districtFullWidth: (() => {
        const panel = document.querySelector('.kiriof-classic-pin');
        const districtRow = panel.previousElementSibling;
        return Math.abs(districtRow.getBoundingClientRect().width - panel.parentElement.getBoundingClientRect().width) < 1;
      })(),
      panelFloat: getComputedStyle(document.querySelector('.kiriof-classic-pin')).cssFloat,
      viewportHeight: viewport.height,
      mapHeight: map.height,
      mapWidth: map.width,
      viewportWidth: viewport.width,
      locateWidth: locate.width,
      locateHeight: locate.height,
      locateContained: contained(locate),
      locateTopRight:
        Math.abs(map.right - locate.right - 12) < 1 && Math.abs(locate.top - map.top - 12) < 1,
      locateBackground: getComputedStyle(document.querySelector('.kiriof-classic-map-locate'))
        .backgroundColor,
      badgeContained: contained(badge),
      controlsContained: contained(zoom) && contained(attribution),
      badgeNoOverlap:
        disjoint(badge, locate) && disjoint(badge, attribution) && disjoint(badge, zoom),
      zoomDecorations: Array.from(document.querySelectorAll('.leaflet-control-zoom a')).map(
        (a) => getComputedStyle(a).textDecorationLine,
      ),
      markerCentered:
        Math.abs((marker.left + marker.right) / 2 - (map.left + map.right) / 2) < 1 &&
        Math.abs(marker.bottom - (map.top + map.bottom) / 2) < 1,
      markerVisible:
        contained(marker) &&
        getComputedStyle(document.querySelector('.kiriof-classic-map-indicator')).display !==
          'none',
      noHorizontalOverflow: document.documentElement.scrollWidth <= window.innerWidth,
    };
  });
}

for (const width of [1200, 390]) {
  test(`Classic real Leaflet map resists adverse theme geometry at ${width}px`, async ({
    app,
    browser,
    screen,
  }) => {
    await browser.setViewport({ width, height: 1000 });
    const fixture = await checkout(app, browser, true, { success: true, data: [district] }, true);
    await expect(browser.locator('.leaflet-control-zoom')).toBeVisible();
    await expect.poll(() => fixture.saves().length).toBeGreaterThan(0);
    await expect
      .poll(() =>
        browser.evaluate(() =>
          document.querySelector('.kiriof-classic-pin-state').classList.contains('is-complete'),
        ),
      )
      .toBe(true);
    await expect
      .poll(() =>
        browser.evaluate(() =>
          Array.from(document.querySelectorAll('.leaflet-tile')).some(
            (tile) =>
              (tile as HTMLImageElement).complete && (tile as HTMLImageElement).naturalWidth > 0,
          ),
        ),
      )
      .toBe(true);
    const assertGeometry = async () => {
      const geometry = await mapGeometry(browser);
      expect(geometry.panelFullWidth).toBe(true);
      expect(geometry.panelFloat).toBe('none');
      expect(geometry.addressGrid).toBe('grid');
      expect(geometry.districtFullWidth).toBe(true);
      expect(geometry.viewportHeight).toBe(320);
      expect(geometry.mapHeight).toBe(318);
      expect(geometry.mapWidth).toBe(geometry.viewportWidth - 2);
      expect(geometry.locateWidth).toBe(44);
      expect(geometry.locateHeight).toBe(44);
      expect(geometry.locateBackground).toBe('rgb(255, 255, 255)');
      expect(geometry.zoomDecorations).toEqual(['none', 'none']);
      for (const key of [
        'locateContained',
        'locateTopRight',
        'badgeContained',
        'controlsContained',
        'badgeNoOverlap',
        'markerCentered',
        'markerVisible',
        'noHorizontalOverflow',
      ]) {
        expect(geometry[key]).toBe(true);
      }
      return geometry;
    };
    // Capture evidence even when geometry assertions expose a CSS regression.
    await app.screenshot(`classic-map-${width}`);
    const initial = await assertGeometry();
    // Successful appearance must follow a real intercepted production save,
    // not a fixture assigning the badge or coordinates directly.
    expect(JSON.parse(fixture.saves().at(-1).body.data).destination.destination_latitude).toBe(
      '-7.7000000',
    );
    await screen.getByRole('button', 'Current location').tap();
    for (const separate of [true, false, true, false]) {
      await screen.getByLabel('Ship to a different address?').tap();
      await expect.poll(() => placement(browser)).toEqual(expectedPlacement);
      await expect
        .poll(() =>
          browser.evaluate(() =>
            document.querySelector('.kiriof-classic-pin-state').classList.contains('is-complete'),
          ),
        )
        .toBe(true);
      expect(await assertGeometry()).toEqual(initial);
    }
    // Wait for Leaflet's own size timer and tile loads before measuring idle
    // child-list changes; a checkout observer loop would starve this timer.
    const idle = await browser.evaluate(async () => {
      await new Promise((resolve) => setTimeout(resolve, 400));
      let mutations = 0;
      const observer = new MutationObserver((records) => {
        mutations += records.length;
      });
      observer.observe(document.querySelector('form.checkout'), { childList: true, subtree: true });
      const maps = window.__maps;
      const updates = window.__checkoutUpdates;
      const viewport = document.querySelector('.kiriof-classic-map-viewport');
      const before = viewport.getBoundingClientRect().toJSON();
      await new Promise((resolve) => setTimeout(resolve, 400));
      observer.disconnect();
      return {
        mutations,
        maps: window.__maps - maps,
        updates: window.__checkoutUpdates - updates,
        stable:
          JSON.stringify(before) === JSON.stringify(viewport.getBoundingClientRect().toJSON()),
      };
    });
    expect(idle).toEqual({ mutations: 0, maps: 0, updates: 0, stable: true });
    expect(fixture.unexpected).toEqual([]);
  });
}

const rows = [district, null, { text: 'No identifier' }, { id: '9999' }];
for (const [envelope, response] of [
  ['WordPress data array', { success: true, data: rows }],
  ['legacy root array', rows],
  ['nested data.results', { success: true, data: { results: rows } }],
] as const) {
  test(`Classic Select2 searches and selects backend district from ${envelope}`, async ({
    app,
    browser,
    screen,
  }) => {
    const fixture = await checkout(app, browser, true, response);
    await browser.locator('#kiriof_destination_area_field .select2-selection').tap();
    const search = browser.locator('.select2-container--open .select2-search__field');
    await search.fill('Sl');
    await browser.evaluate(
      () => new Promise<boolean>((resolve) => setTimeout(() => resolve(true), 350)),
    );
    expect(fixture.searches()).toHaveLength(0);
    await search.fill('Sleman');
    await expect(screen.getByRole('option', district.text)).toBeVisible();
    expect(fixture.searches()).toHaveLength(1);
    expect(fixture.searches()[0]).toEqual({
      url: 'https://fixture.test/admin-ajax.php',
      method: 'POST',
      body: {
        'data[term]': 'Sleman',
        'data[search]': 'Sleman',
        term: 'Sleman',
        nonce: 'fixture-search-nonce',
        action: 'kiriminaja_subdistrict_search',
      },
    });
    expect(
      await browser.evaluate(
        () => document.querySelectorAll('.select2-results__option[aria-selected]').length,
      ),
    ).toBe(1);
    await screen.getByRole('option', district.text).tap();
    await expect
      .poll(() =>
        browser.evaluate(() => ({
          id: (document.querySelector('#kiriof_destination_area') as HTMLSelectElement).value,
          name: (document.querySelector('#kiriof_destination_area_name') as HTMLInputElement).value,
          label: document.querySelector('#kiriof_destination_area option:checked').textContent,
        })),
      )
      .toEqual({ id: district.id, name: district.text, label: district.text });
    await expect
      .poll(() =>
        fixture
          .saves()
          .some((request) => JSON.parse(request.body.data).destination.district_id === district.id),
      )
      .toBe(true);
    await expect.poll(() => placement(browser)).toEqual(expectedPlacement);
    expect(fixture.unexpected).toEqual([]);
  });
}

for (const [envelope, response] of [
  ['malformed data object', { success: true, data: { message: 'No rows' } }],
  ['explicit failure with misleading rows', { success: false, data: [district] }],
] as const) {
  test(`Classic Select2 rejects ${envelope} without fabricating a district`, async ({
    app,
    browser,
  }) => {
    const fixture = await checkout(app, browser, true, response);
    await browser.locator('#kiriof_destination_area_field .select2-selection').tap();
    await browser.locator('.select2-container--open .select2-search__field').fill('Sleman');
    await expect(browser.locator('.select2-results__message')).toContainText('No results found');
    expect(fixture.searches()).toHaveLength(1);
    expect(fixture.searches()[0].body.term).toBe('Sleman');
    expect(
      await browser.evaluate(() => ({
        options: document.querySelectorAll('.select2-results__option[aria-selected]').length,
        value: (document.querySelector('#kiriof_destination_area') as HTMLSelectElement).value,
        fabricated: !!document.querySelector('#kiriof_destination_area option[value="4567"]'),
      })),
    ).toEqual({ options: 0, value: '123', fabricated: false });
    expect(fixture.unexpected).toEqual([]);
  });
}

test('business: acknowledged QRIS booking confirms once and duplicate attempts never book again', async () => {
  const result = php('instant-dispatch-runtime.php', {
    working_sample: true,
    row: { service_name: 'instant' },
    price_service: 'instant',
    retry: true,
  });
  expect(result.dispatch.rows[0].status).toBe('booked');
  expect(result.rows[0].instant_payment_status).toBe('unpaid');
  expect(result.rows[0].instant_status_code).toBe(110);
  expect(result.books).toHaveLength(1);
  expect(Boolean(result.retry_error)).toBe(true);
  expect(result.books[0].payment_method).toBe('qris');
  expect(result.dispatch.payments[0].status).toBe('unpaid');
  expect(result.rows[0].instant_payment_id).toBe(result.dispatch.payments[0].id);
  expect(result.claims).toEqual(['KA-1']);
  expect(result.releases).toEqual([]);
  expect(result.payments_called).toBe(0);
  expect(result.woo[1].completions).toBe(0);
});

test('business: uncertain booking stays guarded and is not automatically resubmitted', async () => {
  const result = php('instant-dispatch-runtime.php', { timeout: true, new_retry: true });
  expect(result.dispatch.rows[0].status).toBe('unknown');
  expect(result.rows[0].status).toBe('pending');
  expect(result.books).toHaveLength(1);
  expect(Boolean(result.new_retry_error)).toBe(true);
  expect(result.dispatch.rows[0].retryable).toBe(false);
  expect(result.dispatch.payments).toEqual([]);
  expect(result.claims).toEqual(['KA-1']);
  expect(result.releases).toEqual([]);
  expect(result.payments_called).toBe(0);
  expect(result.woo[1].completions).toBe(0);
  expect(JSON.parse(result.rows[0].shipping_info)._kiriof_instant_prepared.version).toBe(1);
});

for (const width of [1200, 390]) {
  test(`Classic full district and courier visibility under WC Booster at ${width}px`, async ({ app, browser, screen }) => {
    await browser.setViewport({ width, height: 1000 });
    const fixture = await checkout(app, browser, true, [district], true, 'enhanced');
    const courier = browser.locator('.kiriof-classic-shipping-method-select-wrap .select2-selection');
    await expect(courier).toBeVisible();
    await courier.tap();
    expect(await browser.evaluate(() => {
      const $ = (window as any).jQuery;
      const select = $('.kiriof-classic-shipping-method-select');
      const instance = select.data('select2');
      const dropdown = document.querySelector('.select2-dropdown');
      $(document.body).trigger('updated_checkout');
      return instance === select.data('select2') && dropdown === document.querySelector('.select2-dropdown');
    })).toBe(true);
    const updatesBefore = await browser.evaluate(() => window.__checkoutUpdates);
    await screen.getByRole('option', 'Fixture Regular courier: Rp 15000').tap();
    expect(await browser.evaluate(() => ({
      chosen: (document.querySelector('input.shipping_method:checked') as HTMLInputElement).value,
      events: (window as any).__shippingUpdates,
    }))).toEqual({ chosen: fixtureRates[1].id, events: [{ id: fixtureRates[1].id, name: 'shipping_method[0]', checked: true }] });
    await expect.poll(() => browser.evaluate(() => window.__checkoutUpdates)).toBe(updatesBefore + 1);
    await expect(browser.locator('#shipping_method')).not.toBeVisible();
    for (let cycle = 0; cycle < 3; cycle++) {
      for (const scope of ['billing', 'shipping']) {
        if (scope === 'shipping') await screen.getByLabel('Ship to a different address?').tap();
        const id = scope === 'billing' ? 'kiriof_destination_area' : 'kiriof_shipping_destination_area';
        await browser.locator(`#${id}_field .select2-selection`).tap();
        await expect(browser.locator('.kiriof-classic-district-dropdown')).toBeVisible();
        expect(await browser.evaluate(() => {
          const $ = (window as any).jQuery;
          const select = $('.select2-hidden-accessible').filter(function() { return $(this).data('select2')?.isOpen(); }).first();
          const instance = select.data('select2');
          const dropdown = document.querySelector('.kiriof-classic-district-dropdown');
          $(document.body).trigger('updated_checkout');
          (window as any).getSearchAreaKelurahan();
          return dropdown.parentElement.parentElement === document.body && instance === select.data('select2') && dropdown === document.querySelector('.kiriof-classic-district-dropdown');
        })).toBe(true);
        await browser.locator('.select2-container--open .select2-search__field').fill('Sleman');
        await expect(screen.getByRole('option', district.text)).toBeVisible();
        expect(await browser.evaluate(() => {
          const option = document.querySelector('.select2-results__option[aria-selected]');
          const r = option.getBoundingClientRect();
          return r.width > 100 && r.left >= 0 && r.right <= innerWidth && option.contains(document.elementFromPoint((r.left + r.right) / 2, (r.top + r.bottom) / 2));
        })).toBe(true);
        await screen.getByRole('option', district.text).tap();
        await expect(browser.locator(`#${id}`)).toHaveValue(district.id);
        await expect(browser.locator(`#${id}_name`)).toHaveValue(district.text);
        await expect.poll(() => placement(browser)).toEqual(expectedPlacement);
        if (scope === 'shipping') await screen.getByLabel('Ship to a different address?').tap();
      }
    }
    await app.screenshot(`classic-full-visibility-${width}`);
    const idle = await browser.evaluate(async () => {
      await new Promise(resolve => setTimeout(resolve, 1000));
      let mutations = 0;
      const observer = new MutationObserver(records => { mutations += records.length; });
      observer.observe(document.body, { childList: true, subtree: true });
      const updates = window.__checkoutUpdates;
      const maps = window.__maps;
      await new Promise(resolve => setTimeout(resolve, 400));
      observer.disconnect();
      return { mutations, updates: window.__checkoutUpdates - updates, maps: window.__maps - maps };
    });
    expect(idle).toEqual({ mutations: 0, updates: 0, maps: 0 });
    expect(fixture.searches()).toHaveLength(6);
    expect(fixture.unexpected).toEqual([]);
  });
}

test('Classic courier radios remain selectable when Select2 is unavailable', async ({ app, browser }) => {
  const fixture = await checkout(app, browser, true, [district], true, 'native');
  await expect(browser.locator('#shipping_method')).toBeVisible();
  const radio = browser.locator(`input.shipping_method[value="${fixtureRates[1].id}"]`);
  await expect(radio).toBeVisible();
  await radio.tap();
  expect(await browser.evaluate(() => ({
    ready: !!document.querySelector('.kiriof-shipping-methods-ready'),
    chosen: (document.querySelector('input.shipping_method:checked') as HTMLInputElement).value,
    events: (window as any).__shippingUpdates,
  }))).toEqual({ ready: false, chosen: fixtureRates[1].id, events: [{ id: fixtureRates[1].id, name: 'shipping_method[0]', checked: true }] });
  expect(fixture.unexpected).toEqual([]);
});
