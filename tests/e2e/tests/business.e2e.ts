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

function html(enabled: boolean) {
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
    dependency('jquery/dist/jquery.min.js'),
    dependency('select2/dist/js/select2.full.min.js'),
    `window.__geoCalls = 0; window.__maps = 0; window.__checkoutUpdates = 0; window.__allowGeo = true;
		window.kiriofClassicCheckoutConfig = ${JSON.stringify({
      enabled,
      ownsDistrict: false,
      needsShipping: true,
      ajaxUrl: '/admin-ajax.php',
      nonce: 'fixture-pin-nonce',
      map: { enabled: true, tiles: 'https://tiles.fixture.test/{z}/{x}/{y}.png' },
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
		window.L = {
			map() {
				window.__maps++;
				const map = {
					setView() { return map; }, on() { return map; }, invalidateSize() {},
					getCenter() { return { lat: -7.7, lng: 110.3 }; }, remove() {}
				};
				return map;
			},
			tileLayer() { return { addTo() { return this; }, on() {} }; }
		};
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
		</style></head><body><form class="checkout">
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
		<input class="shipping_method" name="shipping_method[0]" type="radio" value="kiriminaja-instant:1:gosend:instant" checked>
		<button type="button" id="submit">Place order</button>
		<output id="outcome" role="status"></output>
	</form><script>${scripts.join('\n').replaceAll('</script', '<\\/script')}</script>
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
      await route.fulfill({ contentType: 'text/html', body: html(enabled) });
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
