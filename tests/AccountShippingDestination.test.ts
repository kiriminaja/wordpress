import { afterEach, describe, expect, test } from 'bun:test';
import { readFileSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import { runInNewContext } from 'node:vm';
import { buyerBrowserContext } from './helpers/buyer-runtime-source';
import { happy } from './helpers/ui-runtime';

const initialAddress = {
  address_1: 'Street',
  address_2: '',
  city: 'Jakarta',
  state: 'JK',
  postcode: '10110',
  country: 'ID',
};
function destination(extra: any = {}) {
  return {
    version: 1,
    district_id: '12',
    district_label: 'Old label',
    postcode: '10110',
    country: 'ID',
    address_type: 'shipping',
    ...extra,
  };
}
function savedPin(extra: any = {}) {
  return destination({
    version: 2,
    destination_latitude: 0,
    destination_longitude: '0',
    shipping_address: { ...initialAddress },
    ...extra,
  });
}
// Scoped compiler: the shared helper does not expose the account entry yet.
const accountSource = await (async () => {
  const directory = mkdtempSync(
    join(resolve(import.meta.dir, '../node_modules'), '.account-runtime-'),
  );
  try {
    const entry = join(directory, 'entry.ts');
    writeFileSync(
      entry,
      `import {flushSync} from 'svelte'; window.__accountFlush = flushSync; import '${resolve(import.meta.dir, '../src/buyer/entries/account-shipping.ts')}';`,
    );
    const result = await Bun.build({
      entrypoints: [entry],
      target: 'browser',
      conditions: ['browser'],
      format: 'iife',
      plugins: [
        {
          name: 'account-real-svelte',
          setup(builder) {
            builder.onResolve({ filter: /^svelte$/ }, () => ({
              path: resolve(import.meta.dir, '../node_modules/svelte/src/index-client.js'),
            }));
            builder.onLoad({ filter: /\.svelte\.[jt]s$/ }, ({ path }) => ({
              contents: compileModule(
                new Bun.Transpiler({ loader: 'ts' }).transformSync(readFileSync(path, 'utf8')),
                { filename: path, generate: 'client' },
              ).js.code,
              loader: 'js',
            }));
            builder.onLoad({ filter: /\.svelte$/ }, ({ path }) => ({
              contents: compile(readFileSync(path, 'utf8'), { filename: path, generate: 'client' })
                .js.code,
              loader: 'js',
            }));
          },
        },
      ],
    });
    if (!result.success) throw new Error(result.logs.join('\n'));
    return await result.outputs[0].text();
  } finally {
    rmSync(directory, { recursive: true, force: true });
  }
})();
function activeAccount(
  saved: any = null,
  extra: any = {},
  synchronousLocation = false,
  locationMode = 'pending',
  nativeProvince = false,
  monitorCheckout = false,
) {
  const window = new happy.Window({
    url: 'https://account.example.test',
    settings: {
      disableJavaScriptFileLoading: true,
      enableJavaScriptEvaluation: false,
      handleDisabledFileLoadingAsSuccess: true,
    },
  }) as any;
  const document = window.document;
  document.body.innerHTML = `<form>${Object.entries(initialAddress)
    .map(([key, value]) =>
      nativeProvince && key === 'state'
        ? '<label for="shipping_state">Province</label><select id="shipping_state" name="shipping_state" class="state_select" required aria-required="true" aria-describedby="province-help"><option value="">Choose province</option><option value="JK" selected>Jakarta</option><option value="JB">West Java</option></select><span id="province-help">Shipping province</span>'
        : `<input name="shipping_${key}" value="${value}">`,
    )
    .join('')}
  <input name="woocommerce-edit-address-nonce" value="native-nonce"><button name="save_address">Save address</button>
  <div class="kiriof-account-shipping"><div class="kiriof-account-shipping-status"></div><input name="kiriof_account_destination" type="hidden">
  <select name="kiriof_account_district" id="kiriof-account-district"></select><p id="kiriof-account-district-status"></p>
  <button id="kiriof-account-district-retry" type="button" hidden>Retry</button><section class="kiriof-buyer-map"></section></div></form>`;
  const form = document.querySelector('form'),
    hidden = document.querySelector('[name="kiriof_account_destination"]');
  hidden.value = saved ? JSON.stringify(saved) : '';
  const nativeControls = [...form.querySelectorAll('[name^="shipping_"]')];
  let id = 0;
  const timers = new Map<number, { callback: any; delay: number }>(),
    requests: any[] = [],
    locations: any[] = [],
    maps: any[] = [],
    tiles: any[] = [],
    circles: any[] = [];
  function emitter() {
    const handlers = new Map<string, any>();
    return {
      on(name: string, callback: any) {
        handlers.set(name, callback);
        return this;
      },
      fire(name: string, event?: any) {
        handlers.get(name)?.(event);
      },
    };
  }
  window.L = {
    map(node: any) {
      expect(node.className).toBe('kiriof-buyer-map__canvas');
      const map = Object.assign(emitter(), {
        center: { lat: 0, lng: 0 },
        removed: 0,
        setView(center: number[]) {
          this.fire('movestart');
          this.center = { lat: center[0], lng: center[1] };
          this.fire('moveend');
          return this;
        },
        getCenter() {
          return this.center;
        },
        invalidateSize() {},
        remove() {
          this.removed++;
        },
      });
      maps.push(map);
      return map;
    },
    tileLayer() {
      const tile = Object.assign(emitter(), {
        addTo() {
          return this;
        },
      });
      tiles.push(tile);
      return tile;
    },
    circle(center: any, options: any) {
      const circle = {
        center,
        options,
        addTo() {
          return this;
        },
      };
      circles.push(circle);
      return circle;
    },
  };
  Object.defineProperty(window.navigator, 'geolocation', {
    value:
      locationMode === 'unavailable'
        ? undefined
        : {
            getCurrentPosition(success: any, failure: any) {
              locations.push({
                success: (...args: any[]) => {
                  success(...args);
                  flush();
                },
                failure: (...args: any[]) => {
                  failure(...args);
                  flush();
                },
              });
              if (synchronousLocation) success({ coords: { latitude: 0, longitude: 0 } });
            },
          },
    configurable: true,
  });
  Object.defineProperty(window.navigator, 'permissions', {
    value: { query: async () => ({ state: 'prompt' }) },
    configurable: true,
  });
  const flush = () => window.__accountFlush();
  window.kiriofAccountShippingConfig = {
    ajaxUrl: '/ajax',
    nonce: 'nonce',
    map: { tiles: 'https://tiles.test/{z}/{x}/{y}' },
    i18n: {
      selectDistrict: 'Choose',
      checkingDistrict: 'Checking',
      needPinLocation: 'Need pin',
      pinLocation: 'Pin saved',
      districtNotSet: 'District missing',
      lookupFailed: 'Failed',
      loading: 'Loading',
      empty: 'Empty',
      mapPlaced: 'Placed',
      mapMoving: 'Moving',
      mapPermission: 'Permission denied',
      mapUnavailable: 'Map unavailable',
      mapLocate: 'Locate',
      mapTitle: 'Delivery pin',
    },
    ...extra,
  };
  window.setTimeout = (callback: any, delay: number) => {
    timers.set(++id, { callback, delay });
    return id;
  };
  window.clearTimeout = (id: number) => timers.delete(id);
  const checkoutWrites: any[] = [];
  if (monitorCheckout)
    window.wp = {
      data: {
        dispatch: (...args: any[]) => {
          checkoutWrites.push(args);
          return {};
        },
      },
    };
  window.fetch = (url: any, options: any) =>
    new Promise((resolve, reject) => requests.push({ url, options, resolve, reject }));
  // Keep Svelte's delegated-event bookkeeping on real runtime timers. Only
  // root.setTimeout (account debounce, request deadline and map) is controlled.
  runInNewContext(
    accountSource,
    buyerBrowserContext(window, {
      setTimeout,
      clearTimeout,
      CustomEvent: window.CustomEvent,
      HTMLInputElement: window.HTMLInputElement,
      HTMLSelectElement: window.HTMLSelectElement,
      HTMLButtonElement: window.HTMLButtonElement,
      KeyboardEvent: window.KeyboardEvent,
      PointerEvent: window.PointerEvent,
    }),
  );
  document.dispatchEvent(new window.Event('DOMContentLoaded'));
  flush();
  return {
    window,
    document,
    form,
    hidden,
    nativeControls,
    requests,
    checkoutWrites,
    locations,
    maps,
    tiles,
    circles,
    timers,
    flush,
    node: (selector: string) => document.querySelector(selector),
    posted: () => (hidden.value ? JSON.parse(hidden.value) : null),
    advance(delay: number) {
      for (const [id, timer] of [...timers])
        if (timer.delay === delay) {
          timers.delete(id);
          timer.callback();
        }
      flush();
    },
    edit(field: string, value: string, type = 'input') {
      const input = form.elements.namedItem(`shipping_${field}`);
      input.value = value;
      input.dispatchEvent(new window.Event(type, { bubbles: true }));
      flush();
    },
    choose(value: string) {
      const district = document.querySelector('select');
      district.value = value;
      district.dispatchEvent(new window.Event('change', { bubbles: true }));
      flush();
    },
    grant(index = locations.length - 1, latitude = 0, longitude = 0) {
      if (index < 0) {
        document.querySelector('.kiriof-buyer-map__locate').click();
        index = locations.length - 1;
      }
      locations[index].success({ coords: { latitude, longitude } });
      flush();
    },
    async respond(
      index = 0,
      rows: any = [
        { id: 12, text: 'Canonical' },
        { id: 13, text: 'Other' },
      ],
    ) {
      requests[index].resolve({ ok: true, json: async () => ({ success: true, data: rows }) });
      await settle();
      flush();
    },
    submit() {
      const event = new window.Event('submit', { bubbles: true, cancelable: true });
      form.dispatchEvent(event);
      flush();
      return event;
    },
    async close() {
      window.dispatchEvent(new window.Event('pagehide'));
      flush();
      await window.happyDOM.close();
    },
  };
}

const accounts: ReturnType<typeof activeAccount>[] = [];
afterEach(async () => {
  for (const account of accounts.splice(0)) await account.close();
});
// Legacy assertion vocabulary now drives native Happy DOM events on the same
// active-entry fixture; no fake nodes or legacy runtime are involved.
function fixture(saved: any = null, extra: any = {}, locationMode = 'grant') {
  const h = activeAccount(
    saved,
    {
      ...extra,
      i18n: {
        selectDistrict: 'Choose subdistrict',
        loading: 'Loading',
        lookupFailed: 'Lookup failed',
        districtRequired: 'Required',
        empty: 'Empty',
        postcodeRequired: 'Postcode required',
        mapPlaced: 'Placed',
        mapUnavailable: 'Map unavailable',
        mapPermission: 'Permission denied',
        mapLocationFailed: 'Location failed',
        ...extra.i18n,
      },
    },
    locationMode === 'grant',
    locationMode,
  );
  accounts.push(h);
  function fire(node: any, name: string, target = node) {
    target.dispatchEvent(new h.window.Event(name, { bubbles: true, cancelable: true }));
    h.flush();
  }
  const root = h.window;
  root.fire = (name: string) => fire(root, name);
  h.form.fire = (name: string, target = h.form) => fire(h.form, name, target);
  const nodes = {
    district: 'select',
    retry: '#kiriof-account-district-retry',
    status: '#kiriof-account-district-status',
    indicator: '.kiriof-buyer-map__indicator',
    locate: '.kiriof-buyer-map__locate',
    mapStatus: '.kiriof-buyer-map__status',
    mapSection: '.kiriof-buyer-map',
    viewport: '.kiriof-buyer-map__viewport',
    badges: '.kiriof-account-shipping-status',
    coverageWarning: '.kiriof-buyer-map__coverage-warning',
    coverageLegend: '.kiriof-buyer-map__coverage-legend',
  };
  const controls = Object.fromEntries(
    Object.entries(nodes).map(([key, selector]) => {
      const node = h.node(selector);
      node.fire = (name: string) => fire(node, name);
      return [key, node];
    }),
  );
  return {
    ...h,
    ...controls,
    root,
    inputs: Object.fromEntries(
      Object.keys(initialAddress).map((key) => [
        `shipping_${key}`,
        h.form.elements.namedItem(`shipping_${key}`),
      ]),
    ),
    click(lat: number, lng: number) {
      h.maps.at(-1).fire('click', { latlng: { lat, lng } });
      h.flush();
    },
    // Start the lookup debounce only; never accidentally expire its deadline.
    flush() {
      h.advance(250);
    },
    async respond(
      index = 0,
      rows: any = [
        { id: 12, text: 'Canonical district' },
        { id: 13, text: 'Other district' },
      ],
    ) {
      await h.respond(index, rows);
    },
  };
}
async function settle() {
  for (let index = 0; index < 8; index++) await Promise.resolve();
}

test('account district request budget for ten separately debounced street edits', async () => {
  const h = activeAccount(savedPin(), {}, false, 'pending', false, true);
  accounts.push(h);
  h.advance(250);
  await h.respond();
  const district = h.node('#kiriof-account-district');
  const options = [...district.options];
  for (let index = 1; index <= 10; index++) {
    h.edit('address_1', `Street ${index}`);
    h.advance(250);
    await settle();
    expect([...district.options]).toEqual(options);
    expect(district.disabled).toBe(false);
    expect(district.value).toBe('12');
    expect(h.posted()).toEqual(destination({ district_label: 'Canonical' }));
    expect(h.node('.kiriof-account-shipping-status').textContent.trim()).toBe('⚠ Need pin');
  }
  expect(h.requests.every((request) => request.options.method === 'POST')).toBe(true);
  expect(h.requests).toHaveLength(1);
  expect(h.checkoutWrites).toEqual([]);
});

test('street edits retain pending verification, its deadline and its eventual canonical response', async () => {
  const h = activeAccount(savedPin(), {}, false, 'pending', false, true);
  accounts.push(h);
  h.edit('address_1', 'Before debounce');
  h.advance(250);
  expect(h.requests).toHaveLength(1);
  const request = h.requests[0];
  const deadline = [...h.timers.entries()].filter(([, timer]) => timer.delay === 10000);
  h.edit('address_2', 'Apartment 2');
  h.edit('city', 'New city');
  h.edit('state', 'JB', 'change');
  h.advance(250);
  expect(request.options.signal.aborted).toBe(false);
  expect([...h.timers.entries()].filter(([, timer]) => timer.delay === 10000)).toEqual(deadline);
  expect(h.requests).toHaveLength(1);
  expect(h.posted()).toEqual(destination());
  await h.respond();
  expect(h.node('#kiriof-account-district').disabled).toBe(false);
  expect(h.node('#kiriof-account-district').value).toBe('12');
  expect(h.posted()).toEqual(destination({ district_label: 'Canonical' }));
  expect(h.checkoutWrites).toEqual([]);
});

test('lookup-key edits clear selection and pin, requery ID postcodes, and keep explicit error retry', async () => {
  const h = activeAccount(savedPin(), {}, false, 'pending', false, true);
  accounts.push(h);
  h.advance(250);
  await h.respond();
  h.edit('postcode', '10220');
  expect(h.posted()).toEqual(
    destination({ district_id: '', district_label: '', postcode: '10220' }),
  );
  h.advance(250);
  expect(h.requests).toHaveLength(2);
  expect(new URLSearchParams(h.requests[1].options.body).get('term')).toBe('10220');
  h.requests[1].reject(new Error('offline'));
  await settle();
  h.flush();
  const retry = h.node('#kiriof-account-district-retry');
  expect(retry.hidden).toBe(false);
  h.edit('address_1', 'Street after failure');
  h.advance(250);
  expect(retry.hidden).toBe(false);
  expect(h.requests).toHaveLength(2);
  retry.click();
  h.advance(250);
  expect(h.requests).toHaveLength(3);
  await h.respond(2);
  h.choose('13');
  h.edit('country', 'US', 'change');
  expect(h.posted().district_id).toBe('');
  expect(h.posted().country).toBe('US');
  expect(h.node('#kiriof-account-district').disabled).toBe(true);
  h.advance(250);
  expect(h.requests).toHaveLength(3);
  h.edit('country', 'ID', 'change');
  h.advance(250);
  expect(h.requests).toHaveLength(4);
  await h.respond(3);
  expect(h.node('#kiriof-account-district').value).toBe('');
  expect(h.checkoutWrites).toEqual([]);
});

test('Google account map opens at Jogja without GPS, preserves saved pins, and remains manual after denial', async () => {
  for (const saved of [null, savedPin({ destination_latitude: 7, destination_longitude: 8 })]) {
    const h = activeAccount(saved, {
      map: { enabled: true, provider: 'google', apiKey: 'fixture-google-key' },
    });
    accounts.push(h);
    await settle();
    const script = h.document.querySelector('script[data-kiriminaja-google-maps]');
    expect(script).not.toBeNull();
    const maps: any[] = [];
    h.window.google = {
      maps: {
        Map: class {
          center: any;
          events: Record<string, any> = {};
          constructor(_node: any, options: any) {
            this.center = options.center;
            maps.push(this);
          }
          getCenter() {
            return { lat: () => this.center.lat, lng: () => this.center.lng };
          }
          setCenter(point: any) {
            this.center = point;
          }
          setZoom() {}
          addListener(name: string, callback: any) {
            this.events[name] = callback;
            return {
              remove: () => {
                delete this.events[name];
              },
            };
          }
        },
        event: { trigger() {}, clearInstanceListeners() {} },
      },
    };
    h.window[new URL(script.src).searchParams.get('callback')!]();
    await settle();
    h.flush();
    expect(maps).toHaveLength(1);
    expect(h.locations).toHaveLength(0);
    expect(maps[0].center).toEqual(
      saved ? { lat: 7, lng: 8 } : { lat: -7.801389, lng: 110.364722 },
    );
    h.submit();
    expect(h.posted().version).toBe(saved ? 2 : 1);
    if (!saved) expect(h.posted().destination_latitude).toBeUndefined();
    h.node('.kiriof-buyer-map__locate').click();
    h.flush();
    h.locations[0].failure({ code: 1 });
    expect(h.node('.kiriof-buyer-map__viewport').hidden).toBe(false);
    expect(h.node('.kiriof-buyer-map__status').textContent).toBe('Permission denied');
    expect(h.posted().version).toBe(saved ? 2 : 1);
    maps[0].events.click({ latLng: { lat: () => 0, lng: () => 0 } });
    h.flush();
    expect(h.posted().destination_latitude).toBe('0.0000000');
  }
});

test('account entry enhances the native province without SelectWoo and keeps native save authority', async () => {
  const h = activeAccount(savedPin(), { map: { enabled: false } }, false, 'pending', true);
  accounts.push(h);
  const province = h.node('#shipping_state');
  const originalValue = h.hidden.value;
  const changes: Event[] = [];
  province.addEventListener('change', (event: Event) => changes.push(event));
  const trigger = h.node('.kiriof-buyer-combobox-trigger');
  expect(trigger).not.toBeNull();
  expect(trigger.textContent).toContain('Jakarta');
  expect(trigger.getAttribute('aria-labelledby')).toBe('shipping_state-buyer-selector-label');
  expect(trigger.getAttribute('aria-required')).toBe('true');
  expect(trigger.getAttribute('aria-describedby')).toBe('province-help');
  expect(h.hidden.value).toBe(originalValue);
  expect(h.node('#kiriof-account-district').classList.contains('kiriof-buyer-native-select')).toBe(
    false,
  );
  trigger.dispatchEvent(new h.window.PointerEvent('pointerdown', { bubbles: true }));
  await settle();
  h.flush();
  const option = h.node('[data-combobox-item][data-value="JB"]');
  expect(option).not.toBeNull();
  option.dispatchEvent(new h.window.MouseEvent('pointermove', { bubbles: true }));
  option.dispatchEvent(
    new h.window.PointerEvent('pointerup', { bubbles: true, pointerType: 'mouse' }),
  );
  await settle();
  h.flush();
  expect(province.value).toBe('JB');
  expect(new h.window.FormData(h.form).get('shipping_state')).toBe('JB');
  expect(changes).toHaveLength(1);
  expect(h.posted().version).toBe(1);
  expect(h.posted().shipping_address).toBeUndefined();
  expect(h.node('[name="woocommerce-edit-address-nonce"]').value).toBe('native-nonce');
  expect(h.submit().defaultPrevented).toBe(false);
});

test('account province tracks Woo replacements, late enhancement and native validation without touching billing or country', async () => {
  const h = activeAccount(null, { map: { enabled: false } }, false, 'pending', true);
  accounts.push(h);
  const province = h.node('#shipping_state');
  h.form.insertAdjacentHTML(
    'afterbegin',
    '<select id="billing_state" name="billing_state"><option value="JK">Billing</option></select><select id="other_country" class="country_select"><option value="ID">Indonesia</option></select>',
  );
  const changes: Event[] = [];
  h.form.addEventListener('change', (event: Event) => changes.push(event));
  let enhancement: object | null = {};
  const destroyed: string[] = [];
  h.window.jQuery = (node: Element) => ({
    data: () => (node === province ? enhancement : null),
    selectWoo(action: string) {
      destroyed.push(action);
      enhancement = null;
      province.removeAttribute('tabindex');
      province.removeAttribute('aria-hidden');
    },
    on() {},
    off() {},
  });
  province.insertAdjacentHTML(
    'afterend',
    '<span class="select2-container"><span id="select2-shipping_state-container">Stale province</span></span>',
  );
  h.node('#other_country').insertAdjacentHTML(
    'afterend',
    '<span class="select2-container"><span id="select2-other_country-container">Country</span></span>',
  );
  province.classList.add('select2-hidden-accessible');
  await new Promise((resolve) => setTimeout(resolve, 10));
  h.flush();
  expect(destroyed).toEqual(['destroy']);
  expect(province.getAttribute('tabindex')).toBe('-1');
  expect(province.getAttribute('aria-hidden')).toBe('true');
  expect(h.node('#select2-shipping_state-container')).toBeNull();
  expect(h.node('#select2-other_country-container')).not.toBeNull();
  expect(h.node('#billing_state').classList.contains('kiriof-buyer-native-select')).toBe(false);
  expect(h.document.querySelectorAll('.kiriof-buyer-combobox-trigger')).toHaveLength(1);
  province.innerHTML =
    '<option value="">Choose province</option><option value="JT" selected>Central Java</option>';
  province.disabled = true;
  province.setAttribute('aria-invalid', 'true');
  h.window.kiriofBuyerSelectors.refresh();
  h.flush();
  expect(h.node('.kiriof-buyer-combobox-trigger').disabled).toBe(true);
  expect(h.node('.kiriof-buyer-combobox-trigger').getAttribute('aria-invalid')).toBe('true');
  expect(h.node('.kiriof-buyer-combobox-trigger').textContent).toContain('Central Java');
  expect(changes).toHaveLength(0);
  const replacement = h.document.createElement('input');
  replacement.id = 'shipping_state';
  replacement.name = 'shipping_state';
  replacement.value = 'Tokyo';
  province.replaceWith(replacement);
  h.window.kiriofBuyerSelectors.refresh();
  h.flush();
  expect(h.node('#shipping_state')).toBe(replacement);
  expect(h.node('.kiriof-buyer-selector')).toBeNull();
  expect(province.isConnected).toBe(false);
  const restored = h.document.createElement('select');
  restored.id = 'shipping_state';
  restored.name = 'shipping_state';
  restored.innerHTML = '<option value="JB" selected>West Java</option>';
  replacement.replaceWith(restored);
  h.window.kiriofBuyerSelectors.refresh();
  h.flush();
  expect(h.node('.kiriof-buyer-combobox-trigger').textContent).toContain('West Java');
  expect(new h.window.FormData(h.form).get('shipping_state')).toBe('JB');
  h.window.__kiriofAccountShippingBridge.dispose();
  h.flush();
  expect(h.node('.kiriof-buyer-selector')).toBeNull();
  expect(restored.getAttribute('aria-hidden')).toBeNull();
  expect(restored.getAttribute('tabindex')).toBeNull();
});

test('account province supports keyboard search, Escape and selection without form submission', async () => {
  const h = activeAccount(null, { map: { enabled: false } }, false, 'pending', true);
  accounts.push(h);
  const trigger = h.node('.kiriof-buyer-combobox-trigger');
  expect(trigger).not.toBeNull();
  const key = async (node: Element, value: string) => {
    node.dispatchEvent(
      new h.window.KeyboardEvent('keydown', { key: value, bubbles: true, cancelable: true }),
    );
    await settle();
    h.flush();
  };
  trigger.focus();
  await key(trigger, 'Enter');
  let search = h.node('[data-combobox-input]');
  expect(search).not.toBeNull();
  await key(search, 'Escape');
  expect(trigger.getAttribute('data-state')).toBe('closed');
  expect(h.document.activeElement).toBe(trigger);
  expect(h.node('#shipping_state').value).toBe('JK');
  await key(trigger, ' ');
  search = h.node('[data-combobox-input]');
  search.value = 'West';
  search.dispatchEvent(new h.window.Event('input', { bubbles: true }));
  await settle();
  h.flush();
  expect(h.document.querySelectorAll('[data-combobox-item]')).toHaveLength(1);
  await key(search, 'ArrowDown');
  await key(search, 'Enter');
  expect(h.node('#shipping_state').value).toBe('JB');
  expect(trigger.getAttribute('data-state')).toBe('closed');
});

test('actual native select unlocks after success, failure and timeout; retry publishes canonical choices', async () => {
  const h = fixture(destination(), { map: { enabled: false } }, 'pending');
  const { document, hidden, district, retry, requests } = h;
  const window = h.window;
  const advance = (delay: number) => h.advance(delay);
  const respond = (index: number) =>
    h.respond(index, [
      { id: 12, text: 'Canonical' },
      { id: 13, text: 'Other' },
    ]);
  try {
    document.dispatchEvent(new window.Event('DOMContentLoaded'));
    h.window.__accountFlush();
    expect(district.disabled).toBe(true);
    expect(district.value).toBe('12');
    expect(district.options[1].text).toBe('Old label');
    advance(250);
    await respond(0);
    expect(district.disabled).toBe(false);
    expect(district.options).toHaveLength(3);
    district.value = '13';
    district.dispatchEvent(new window.Event('change', { bubbles: true }));
    expect(JSON.parse(hidden.value).district_label).toBe('Other');
    expect(requests).toHaveLength(1);
    const postcode = document.querySelector('[name="shipping_postcode"]');
    postcode.value = '10220';
    postcode.dispatchEvent(new window.Event('input', { bubbles: true }));
    advance(250);
    requests[1].reject(new Error('offline'));
    await settle();
    expect(district.disabled).toBe(false);
    expect(district.options).toHaveLength(1);
    expect(retry.hidden).toBe(false);
    retry.click();
    expect(district.disabled).toBe(true);
    advance(250);
    advance(10000);
    expect(district.disabled).toBe(false);
    expect(retry.hidden).toBe(false);
    await respond(2);
    expect(retry.hidden).toBe(false);
    retry.click();
    advance(250);
    await respond(3);
    expect(district.disabled).toBe(false);
    expect(retry.hidden).toBe(true);
    district.value = '12';
    district.dispatchEvent(new window.Event('change', { bubbles: true }));
    expect(JSON.parse(hidden.value).district_label).toBe('Canonical');
    expect(JSON.parse(hidden.value).postcode).toBe('10220');
  } finally {
    window.dispatchEvent(new window.Event('pagehide'));
    await window.happyDOM.close();
  }
});

describe('Account shipping destination native form', () => {
  const coverage = { origin: { latitude: 0, longitude: 0 }, radiusMeters: 40000 };
  const coverageConfig = {
    map: {
      tiles: 'https://tiles.test/{z}/{x}/{y}',
      coverage,
      i18n: {
        mapOutsideRadius: 'Outside Instant area. Express remains available.',
        mapCoverage: 'Instant coverage legend',
      },
    },
  };
  test('saved outside pin warns before permission; denial preserves warning and still allows save', async () => {
    const h = fixture(savedPin({ destination_latitude: 1 }), coverageConfig, 'pending');
    expect(h.coverageWarning.hidden).toBe(false);
    h.locate.fire('click');
    expect(h.coverageWarning.textContent).toBe('Outside Instant area. Express remains available.');
    expect(h.coverageWarning.getAttribute('role')).toBe('note');
    expect(h.coverageLegend.hidden).toBe(false);
    expect(h.coverageLegend.textContent).toBe('Instant coverage legend');
    expect(h.circles).toHaveLength(1);
    h.locations[0].failure({ code: 1 });
    expect(h.viewport.hidden).toBe(false);
    expect(h.mapStatus.textContent).toBe('Permission denied');
    expect(h.coverageWarning.hidden).toBe(false);
    h.advance(250);
    await h.respond();
    h.choose('13');
    h.form.fire('submit');
    expect(h.posted().version).toBe(2);
    expect(h.posted().district_id).toBe('13');
    expect(h.posted().destination_latitude).toBe('1.0000000');
    expect(h.maps).toHaveLength(1);
    expect(h.circles).toHaveLength(1);
  });
  test('permission draws coverage circle without constraining outside pin or native save', async () => {
    const h = fixture(savedPin({ destination_latitude: 1 }), coverageConfig, 'pending');
    expect(h.locations).toHaveLength(0);
    expect(h.circles).toHaveLength(1);
    expect(h.circles[0].center).toEqual([0, 0]);
    expect(h.circles[0].options.radius).toBe(40000);
    expect(h.coverageWarning.hidden).toBe(false);
    expect(h.maps[0].center).toEqual({ lat: 1, lng: 0 });
    h.advance(250);
    await h.respond();
    h.choose('12');
    h.click(2, 3);
    h.form.fire('submit');
    expect(h.posted()).toMatchObject({
      version: 2,
      district_id: '12',
      destination_latitude: '2.0000000',
      destination_longitude: '3.0000000',
      shipping_address: initialAddress,
    });
    expect(h.mapStatus.textContent).toBe('Placed');
    expect(h.coverageWarning.hidden).toBe(false);
    h.click(0.1, 0);
    expect(h.coverageWarning.hidden).toBe(true);
    expect(h.coverageWarning.textContent).toBe('');
    expect(h.posted().destination_latitude).toBe('0.1000000');
    expect(h.mapStatus.textContent).toBe('Placed');
  });
  test('full address change clears outside warning with pin and ignores old location callbacks', () => {
    const h = fixture(savedPin({ destination_latitude: 1 }), coverageConfig, 'pending');
    expect(h.coverageWarning.hidden).toBe(false);
    h.locate.fire('click');
    h.edit('address_2', 'Suite');
    expect(h.coverageWarning.hidden).toBe(true);
    expect(h.posted().version).toBe(1);
    expect(h.posted().destination_latitude).toBeUndefined();
    h.locations[0].success({ coords: { latitude: 2, longitude: 3 } });
    expect(h.coverageWarning.hidden).toBe(true);
    expect(h.maps).toHaveLength(2);
    expect(h.maps[0].removed).toBe(1);
    expect(h.coverageLegend.hidden).toBe(false);
  });
  test('inside saved pin and unknown coverage never show warning or invent within label', () => {
    const inside = fixture(savedPin(), coverageConfig, 'pending');
    expect(inside.coverageWarning.hidden).toBe(true);
    expect(inside.coverageWarning.textContent).toBe('');
    expect(inside.coverageLegend.hidden).toBe(false);
    for (const invalid of [
      undefined,
      null,
      { origin: { latitude: 91, longitude: 0 }, radiusMeters: 40000 },
      { origin: { latitude: '', longitude: 0 }, radiusMeters: 40000 },
    ]) {
      const h = fixture(
        savedPin({ destination_latitude: 1 }),
        { map: { coverage: invalid } },
        'pending',
      );
      expect(h.coverageWarning.hidden).toBe(true);
      expect(h.coverageWarning.textContent).toBe('');
      expect(h.coverageLegend.hidden).toBe(true);
      expect(h.circles).toHaveLength(0);
    }
    const disabled = fixture(
      savedPin({ destination_latitude: 1 }),
      { map: { enabled: false } },
      'pending',
    );
    expect(disabled.coverageWarning.hidden).toBe(true);
    expect(disabled.coverageLegend.hidden).toBe(true);
    expect(disabled.posted().version).toBe(2);
    expect(disabled.locations).toHaveLength(0);
  });
  test('pending saved district stays visible but disabled until canonical confirmation', async () => {
    const h = fixture(destination(), {}, 'pending');
    expect(h.district.disabled).toBe(true);
    expect(h.district.value).toBe('12');
    expect(Array.from(h.district.children).map((node) => [node.value, node.textContent])).toEqual([
      ['', 'Choose subdistrict'],
      ['12', 'Old label'],
    ]);
    expect(h.retry.hidden).toBe(true);
    h.flush();
    await h.respond();
    expect(h.district.disabled).toBe(false);
    expect(h.district.value).toBe('12');
    expect(h.posted().district_label).toBe('Canonical district');
  });
  test('successful lookup exposes native choices and manual selection makes no additional API call', async () => {
    const h = fixture(null, {}, 'pending');
    h.flush();
    await h.respond();
    expect(h.district.disabled).toBe(false);
    expect(Array.from(h.district.children).map((node) => node.value)).toEqual(['', '12', '13']);
    h.choose('13');
    expect(h.posted().district_label).toBe('Other district');
    expect(h.requests).toHaveLength(1);
    expect([...h.timers.values()].filter((timer) => timer.delay !== 150)).toHaveLength(0);
  });
  test('empty lookup keeps placeholder dropdown enabled and rejects fabricated changes', async () => {
    const h = fixture(destination(), {}, 'pending');
    h.flush();
    await h.respond(0, []);
    expect(h.district.disabled).toBe(false);
    expect(h.district.children).toHaveLength(1);
    expect(h.district.value).toBe('');
    expect(h.status.textContent).toBe('Empty');
    expect(h.retry.hidden).toBe(true);
    h.choose('12');
    expect(h.posted().district_id).toBe('');
  });
  test('offline lookup exposes retry and an enabled placeholder without inventing selection', async () => {
    const h = fixture(null, {}, 'pending');
    h.flush();
    h.requests[0].reject(new Error('offline'));
    await settle();
    expect(h.district.disabled).toBe(false);
    expect(h.district.children).toHaveLength(1);
    expect(h.retry.hidden).toBe(false);
    h.choose('12');
    expect(h.posted()).toBe(null);
  });
  test('retry restores canonical choices without map, location or pin writes', async () => {
    const h = fixture(savedPin(), {}, 'pending');
    h.flush();
    h.requests[0].reject(new Error('offline'));
    await settle();
    const posted = h.hidden.value;
    h.retry.fire('click');
    expect(h.retry.hidden).toBe(true);
    expect(h.district.disabled).toBe(true);
    expect(h.hidden.value).toBe(posted);
    expect(h.locations).toHaveLength(0);
    expect(h.maps).toHaveLength(1);
    expect(h.requests[0].options.signal.aborted).toBe(true);
    h.flush();
    await h.respond(1);
    h.choose('13');
    expect(h.posted().district_label).toBe('Other district');
    expect(h.posted().destination_latitude).toBe('0.0000000');
    expect(h.locations).toHaveLength(0);
    expect(h.requests).toHaveLength(2);
  });
  test('network timeout begins only when the actual fetch starts and exits loading', () => {
    const h = fixture(destination(), {}, 'pending');
    expect(
      [...h.timers.values()].filter((timer) => timer.delay !== 150).map((timer) => timer.delay),
    ).toEqual([250]);
    h.advance(10000);
    expect(h.requests).toHaveLength(0);
    h.advance(250);
    expect(
      [...h.timers.values()].filter((timer) => timer.delay !== 150).map((timer) => timer.delay),
    ).toEqual([10000]);
    h.advance(10000);
    expect(h.requests[0].options.signal.aborted).toBe(true);
    expect(h.district.disabled).toBe(false);
    expect(h.status.textContent).toBe('Lookup failed');
    expect(h.retry.hidden).toBe(false);
    expect([...h.timers.values()].filter((timer) => timer.delay !== 150)).toHaveLength(0);
  });
  test('late success after timeout cannot replace remembered identity and retry succeeds', async () => {
    const h = fixture(destination(), {}, 'pending');
    h.advance(250);
    h.advance(10000);
    await h.respond();
    expect(h.status.textContent).toBe('Lookup failed');
    expect(h.posted().district_label).toBe('Old label');
    h.retry.fire('click');
    h.advance(250);
    await h.respond(1);
    expect(h.status.textContent).toBe('');
    expect(h.district.disabled).toBe(false);
    expect(h.posted().district_label).toBe('Canonical district');
  });
  test('abort rejection after timeout leaves failure enabled and retry visible', async () => {
    const h = fixture(null, {}, 'pending');
    h.advance(250);
    h.advance(10000);
    h.requests[0].reject(new Error('AbortError'));
    await settle();
    expect(h.status.textContent).toBe('Lookup failed');
    expect(h.district.disabled).toBe(false);
    expect(h.retry.hidden).toBe(false);
  });
  test('HTTP errors stop network timer and unlock district', async () => {
    const h = fixture(null, {}, 'pending');
    h.advance(250);
    h.requests[0].resolve({ ok: false });
    await settle();
    expect([...h.timers.values()].filter((timer) => timer.delay !== 150)).toHaveLength(0);
    expect(h.district.disabled).toBe(false);
    expect(h.retry.hidden).toBe(false);
  });
  test('invalid JSON and unsuccessful payloads stop loading and permit retry', async () => {
    for (const response of [
      {
        ok: true,
        json: async () => {
          throw new Error('JSON');
        },
      },
      { ok: true, json: async () => ({ success: false }) },
    ]) {
      const h = fixture(null, {}, 'pending');
      h.locate.fire('click');
      h.advance(250);
      h.requests[0].resolve(response);
      await settle();
      expect(h.district.disabled).toBe(false);
      expect(h.status.textContent).toBe('Lookup failed');
      expect([...h.timers.values()].filter((timer) => timer.delay !== 150)).toHaveLength(0);
    }
  });
  test('incomplete postcode disables dropdown and cancels both request and deadline', () => {
    const h = fixture(destination(), {}, 'pending');
    h.advance(250);
    h.edit('postcode', '10');
    expect(h.requests[0].options.signal.aborted).toBe(true);
    expect([...h.timers.values()].filter((timer) => timer.delay !== 150)).toHaveLength(0);
    expect(h.district.disabled).toBe(true);
    expect(h.status.textContent).toBe('Postcode required');
    expect(h.retry.hidden).toBe(true);
    expect(h.district.value).toBe('');
  });
  test('changing country after failure hides retry and returning to ID starts fresh lookup', async () => {
    const h = fixture(destination(), {}, 'pending');
    h.advance(250);
    h.requests[0].reject(new Error('offline'));
    await settle();
    h.edit('country', 'US');
    expect(h.retry.hidden).toBe(true);
    expect(h.district.disabled).toBe(true);
    expect(h.district.required).toBe(false);
    h.edit('country', 'ID');
    h.advance(250);
    await h.respond(1);
    expect(h.district.disabled).toBe(false);
    expect(h.district.value).toBe('');
  });
  test('retry is inert except after failure and pagehide removes its listener and all timers', async () => {
    const h = fixture(null, {}, 'pending');
    h.retry.fire('click');
    h.advance(250);
    expect(h.requests).toHaveLength(1);
    h.requests[0].reject(new Error('offline'));
    await settle();
    h.retry.fire('click');
    h.advance(250);
    h.root.fire('pagehide');
    await settle();
    h.window.__accountFlush();
    expect([...h.timers.values()].filter((timer) => timer.delay !== 150)).toHaveLength(0);
    h.retry.fire('click');
    h.flush();
    expect(h.requests).toHaveLength(2);
    expect(h.requests[1].options.signal.aborted).toBe(true);
  });
  test('map mounts without permission and ordinary edits do not request location', async () => {
    const h = fixture(null, {}, 'pending');
    expect(h.locations).toHaveLength(0);
    expect(h.maps).toHaveLength(1);
    expect(h.tiles).toHaveLength(1);
    expect(h.viewport.hidden).toBe(false);
    expect(h.maps[0].center).toEqual({ lat: -7.801389, lng: 110.364722 });
    expect(h.mapSection.hidden).toBe(false);
    expect(h.locate.disabled).toBe(false);
    h.edit('address_1', ' Street ');
    h.form.fire('input', h.district);
    h.flush();
    await h.respond();
    h.choose('12');
    h.form.fire('submit');
    expect(h.locations).toHaveLength(0);
    expect(h.posted().district_id).toBe('12');
    expect(h.posted().version).toBe(1);
    expect(h.locations).toHaveLength(0);
    expect(h.viewport.hidden).toBe(false);
    expect(h.maps).toHaveLength(1);
    expect(h.tiles).toHaveLength(1);
    expect(h.posted().destination_latitude).toBeUndefined();
    expect(h.posted().destination_longitude).toBeUndefined();
  });
  test('denial retains matching saved pin without exposing its viewport and permits district/save', async () => {
    const h = fixture(savedPin(), {}, 'pending');
    h.locate.fire('click');
    h.locations[0].failure({ code: 1 });
    expect(h.mapStatus.textContent).toBe('Permission denied');
    expect(h.viewport.hidden).toBe(false);
    expect(h.maps).toHaveLength(1);
    expect(h.tiles).toHaveLength(1);
    expect(h.posted().version).toBe(2);
    h.flush();
    await h.respond();
    h.choose('13');
    h.form.fire('submit');
    expect(h.posted().district_id).toBe('13');
    expect(h.posted().destination_latitude).toBe('0.0000000');
    expect(h.locations).toHaveLength(1);
    expect(h.viewport.hidden).toBe(false);
  });
  test('matching saved pin is authoritative without requesting device location', () => {
    const h = fixture(
      savedPin({ destination_latitude: 7, destination_longitude: 8 }),
      {},
      'pending',
    );
    expect(h.locations).toHaveLength(0);
    expect(h.maps[0].center).toEqual({ lat: 7, lng: 8 });
    expect(h.posted().destination_latitude).toBe(7);
    expect(h.indicator.hidden).toBe(false);
    expect(h.viewport.hidden).toBe(false);
  });
  for (const code of [2, 3]) {
    test(`location error ${code} retains viewport without requiring a pin`, async () => {
      const h = fixture(null, {}, 'pending');
      h.locate.fire('click');
      h.locations[0].failure({ code });
      expect(h.mapStatus.textContent).toBe('Location failed');
      expect(h.viewport.hidden).toBe(false);
      expect(h.maps).toHaveLength(1);
      expect(h.tiles).toHaveLength(1);
      h.flush();
      await h.respond();
      h.choose('12');
      h.form.fire('submit');
      expect(h.posted().version).toBe(1);
    });
  }
  test('missing geolocation API keeps manual Leaflet picker and tiles available', async () => {
    const h = fixture(null, {}, 'unavailable');
    expect(h.mapStatus.textContent).toBe('');
    expect(h.viewport.hidden).toBe(false);
    expect(h.maps).toHaveLength(1);
    expect(h.tiles).toHaveLength(1);
    h.locate.fire('click');
    expect(h.mapStatus.textContent).toBe('Permission denied');
    expect(h.viewport.hidden).toBe(false);
    h.flush();
    await h.respond();
    h.choose('12');
    h.form.fire('submit');
    expect(h.posted().district_id).toBe('12');
  });
  test('disabled maps never request and country changes cancel/restart eligible gates', () => {
    const h = fixture(null, { map: { enabled: false } }, 'pending');
    expect(h.locations).toHaveLength(0);
    const eligible = fixture(null, {}, 'pending');
    eligible.locate.fire('click');
    eligible.edit('country', 'US');
    eligible.locations[0].success({ coords: { latitude: 1, longitude: 2 } });
    expect(eligible.maps[0].removed).toBe(1);
    expect(eligible.mapSection.hidden).toBe(true);
    eligible.edit('country', 'ID');
    expect(eligible.locations).toHaveLength(1);
    expect(eligible.maps).toHaveLength(2);
  });
  test('shipping edit badges track verified district, placed pin and full-address invalidation', async () => {
    const h = fixture(
      null,
      {
        i18n: {
          checkingDistrict: 'Checking',
          districtNotSet: 'Subdistrict missing',
          needPinLocation: 'Need pin',
          pinLocation: 'Pin saved',
        },
      },
      'pending',
    );
    expect(Array.from(h.badges.children).map((node) => node.textContent)).toEqual([
      '⚠ Checking',
      '⚠ Need pin',
    ]);
    h.flush();
    await h.respond();
    h.choose('12');
    expect(Array.from(h.badges.children).map((node) => node.textContent)).toEqual(['⚠ Need pin']);
    h.locate.fire('click');
    h.locations[0].success({ coords: { latitude: 0, longitude: 0 } });
    expect(Array.from(h.badges.children).map((node) => node.textContent)).toEqual(['✓ Pin saved']);
    h.edit('address_1', 'Other street');
    expect(Array.from(h.badges.children).map((node) => node.textContent)).toEqual(['⚠ Need pin']);
    expect(h.posted().version).toBe(1);
  });
  test('granted zero device point is selected, district is required, lookup uses the real buyer endpoint', async () => {
    const h = fixture();
    h.locate.fire('click');
    h.locations[0].success({ coords: { latitude: 0, longitude: 0 } });
    expect(h.posted().version).toBe(2);
    expect(h.maps[0].center).toEqual({ lat: 0, lng: 0 });
    expect(h.locations).toHaveLength(1);
    expect(h.indicator.hidden).toBe(false);
    expect(h.district.required).toBe(true);
    expect([...h.timers.values()].some((timer) => timer.delay === 250)).toBe(true);
    h.flush();
    const request = h.requests[0];
    const body = new URLSearchParams(request.options.body);
    expect(Object.fromEntries(body)).toEqual({
      action: 'kiriminaja_subdistrict_search',
      nonce: 'nonce',
      term: '10110',
    });
    expect(request.options.credentials).toBe('same-origin');
    expect(request.options.method).toBe('POST');
    await h.respond();
    expect(h.posted().district_id).toBe('');
    expect(h.posted().version).toBe(2);
    h.choose('13');
    expect(h.posted().district_label).toBe('Other district');
    expect(h.posted().district_id).toBe('13');
    expect(h.posted().destination_latitude).toBe('0.0000000');
  });
  test('matching saved v2 zero pin restores silently and hidden submission wins over saved config', async () => {
    const saved = savedPin();
    const h = fixture(saved, { savedDestination: savedPin({ destination_latitude: 7 }) });
    expect(h.hidden.value).toBe(JSON.stringify(saved));
    expect(h.maps[0].center).toEqual({ lat: 0, lng: 0 });
    expect(h.indicator.hidden).toBe(false);
    h.flush();
    await h.respond();
    expect(h.posted().district_label).toBe('Canonical district');
    expect(h.posted().destination_latitude).toBe('0.0000000');
  });
  test('stale full-address pin is discarded but same-postcode district is lookup-confirmed', async () => {
    const h = fixture(
      savedPin({ shipping_address: { ...initialAddress, address_1: 'Different street' } }),
      {},
      'pending',
    );
    expect(h.posted().version).toBe(1);
    expect(h.posted().destination_latitude).toBeUndefined();
    expect(h.posted().district_id).toBe('12');
    h.flush();
    await h.respond(0, [{ id: 13, text: 'Another' }]);
    expect(h.posted().district_id).toBe('');
  });
  test('user-chosen zero pin persists v2, address changes dispose old map and select a fresh device pin', async () => {
    const h = fixture();
    h.flush();
    await h.respond();
    h.choose('12');
    h.click(0, 0);
    expect(h.posted().version).toBe(2);
    expect(h.posted().shipping_address).toEqual(initialAddress);
    const old = h.maps[0];
    h.edit('address_2', 'Suite 1');
    expect(old.removed).toBe(1);
    expect(h.maps).toHaveLength(2);
    expect(h.indicator.hidden).toBe(true);
    expect(h.locations).toHaveLength(0);
    expect(h.posted().version).toBe(1);
    expect(h.posted().district_id).toBe('12');
    expect(h.district.disabled).toBe(false);
    h.flush();
    expect(h.requests).toHaveLength(1);
    expect(h.district.value).toBe('12');
  });
  test('normalized whitespace edits preserve pins and use all six trimmed address fields', () => {
    const h = fixture(savedPin());
    h.edit('address_1', ' Street ');
    h.edit('postcode', '10 110');
    h.edit('country', 'id');
    expect(h.maps).toHaveLength(1);
    expect(h.maps[0].removed).toBe(0);
    expect(h.posted().version).toBe(2);
    expect(h.locations).toHaveLength(0);
  });
  test('cancellable debounce and generation reject obsolete postcode/country responses even when fetch ignores abort', async () => {
    const h = fixture(destination());
    h.flush();
    h.edit('postcode', '10220');
    expect(h.requests[0].options.signal.aborted).toBe(true);
    expect(h.posted().district_id).toBe('');
    h.flush();
    await h.respond(0);
    expect(h.status.textContent).toBe('Loading');
    expect(h.district.children).toHaveLength(1);
    h.edit('country', 'US', 'change');
    expect(h.requests[1].options.signal.aborted).toBe(true);
    await h.respond(1);
    expect(h.district.required).toBe(false);
    expect(h.district.disabled).toBe(true);
    expect(h.posted().country).toBe('US');
    h.edit('country', 'ID', 'change');
    h.edit('postcode', '10330');
    h.flush();
    expect(h.requests).toHaveLength(3);
    await h.respond(2);
    h.choose('12');
    expect(h.posted().postcode).toBe('10330');
  });
  test('lookup failure enables the remembered option without erasing posted identity or inventing a selection', async () => {
    const saved = savedPin();
    const h = fixture(saved);
    h.flush();
    h.requests[0].reject(new Error('offline'));
    await settle();
    expect(h.hidden.value).toBe(JSON.stringify(saved));
    expect(h.district.disabled).toBe(false);
    expect(h.status.textContent).toBe('Lookup failed');
    h.choose('13');
    expect(h.posted().district_id).toBe('12');
    h.form.fire('submit');
    expect(h.posted().district_id).toBe('12');
  });
  test('replaced Woo state field and silent changes are read on delegated event and explicit submit', () => {
    const h = fixture(savedPin());
    const next = h.document.createElement('select');
    next.name = 'shipping_state';
    next.innerHTML = '<option value="JB">JB</option>';
    h.inputs.shipping_state.replaceWith(next);
    h.inputs.shipping_state = next;
    h.form.fire('change', h.inputs.shipping_state);
    expect(h.posted().version).toBe(1);
    h.click(1, 2);
    expect(h.posted().shipping_address.state).toBe('JB');
    h.inputs.shipping_postcode.value = ' 10 220 ';
    h.form.fire('submit');
    expect(h.posted().postcode).toBe('10220');
    expect(h.posted().district_id).toBe('');
    expect(h.posted().version).toBe(1);
    expect(h.posted().shipping_address).toBeUndefined();
  });
  test('gate rejects late geolocation after address edit or pagehide and hidden locate never requests', async () => {
    const h = fixture(destination(), {}, 'pending');
    expect(h.locations).toHaveLength(0);
    h.locate.fire('click');
    expect(h.locations).toHaveLength(1);
    h.edit('city', 'Bandung');
    h.locations[0].success({ coords: { latitude: 4, longitude: 5 } });
    expect(h.posted().version).toBe(1);
    h.locate.fire('click');
    h.root.fire('pagehide');
    await settle();
    h.window.__accountFlush();
    h.locations[0].success({ coords: { latitude: 4, longitude: 5 } });
    expect(h.posted().version).toBe(1);
    expect(h.maps).toHaveLength(2);
    expect(h.maps.every((map) => map.removed === 1)).toBe(true);
    expect(h.tiles).toHaveLength(2);
    expect([...h.timers.values()].filter((timer) => timer.delay !== 150)).toHaveLength(0);
    h.form.fire('input', h.inputs.shipping_city);
    h.flush();
    expect(h.requests).toHaveLength(0);
  });
  test('pagehide aborts pending lookups, late results cannot write', async () => {
    const h = fixture(destination());
    h.flush();
    h.root.fire('pagehide');
    await settle();
    h.window.__accountFlush();
    expect(h.requests[0].options.signal.aborted).toBe(true);
    await h.respond();
    expect(h.posted().district_label).toBe('Old label');
  });
  test('pagehide disposes granted map and ignores late session callbacks', async () => {
    const h = fixture();
    h.locate.fire('click');
    expect(h.locations).toHaveLength(1);
    const posted = h.hidden.value;
    h.root.fire('pagehide');
    await settle();
    h.window.__accountFlush();
    h.locations[0].success({ coords: { latitude: 4, longitude: 5 } });
    h.maps[0].fire('click', { latlng: { lat: 6, lng: 7 } });
    expect(h.hidden.value).toBe(posted);
    expect(h.maps[0].removed).toBe(1);
    expect([...h.timers.values()].filter((timer) => timer.delay !== 150)).toHaveLength(0);
  });
  test('map unavailable is nonmandatory and district remains usable', async () => {
    const h = fixture(null, { map: { tiles: 'http://unsafe.test' } });
    expect(h.mapStatus.textContent).toBe('Map unavailable');
    expect(h.locate.disabled).toBe(true);
    h.flush();
    await h.respond();
    h.choose('12');
    h.form.fire('submit');
    expect(h.posted().district_id).toBe('12');
    expect(h.posted().version).toBe(1);
  });
  test('DOMContentLoaded initializes once and pagehide disposes account and selector bridges', async () => {
    const window = new happy.Window({
      url: 'https://account.example.test',
      settings: {
        disableJavaScriptFileLoading: true,
        enableJavaScriptEvaluation: false,
        handleDisabledFileLoadingAsSuccess: true,
      },
    }) as any;
    try {
      Object.defineProperty(window.document, 'readyState', {
        value: 'loading',
        configurable: true,
      });
      runInNewContext(accountSource, buyerBrowserContext(window));
      const bridge = window.__kiriofAccountShippingBridge;
      expect(typeof bridge.dispose).toBe('function');
      window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
      window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
      expect(window.__kiriofAccountShippingBridge).toBe(bridge);
      expect(window.kiriofBuyerSelectors.active()).toBe(false);
      window.dispatchEvent(new window.Event('pagehide'));
      window.__accountFlush();
      expect(window.__kiriofAccountShippingBridge).toBeUndefined();
      expect(window.kiriofBuyerSelectors).toBeUndefined();
      expect(window.kiriofClassicChoices).toBeUndefined();
    } finally {
      await window.happyDOM.close();
    }
  });
});

describe('active typed account shipping entry with real Svelte', () => {
  test('synchronous permission grant does not subscribe the map lifecycle to pin changes', async () => {
    const h = activeAccount(null, {}, true);
    try {
      expect(h.locations).toHaveLength(0);
      expect(h.maps).toHaveLength(1);
      h.maps[0].fire('click', { latlng: { lat: 1, lng: 2 } });
      h.flush();
      expect(h.posted().destination_latitude).toBe('1.0000000');
      expect(h.locations).toHaveLength(0);
      expect(h.maps).toHaveLength(1);
    } finally {
      await h.close();
    }
  });

  test('native six fields and submit stay authoritative; canonical district and zero pin persist', async () => {
    const h = activeAccount();
    try {
      expect(h.node('.kiriof-buyer-map__viewport').hidden).toBe(false);
      expect(h.node('.kiriof-account-shipping-status').textContent).toContain('Checking');
      h.advance(250);
      expect(h.requests[0].options.body).toContain('action=kiriminaja_subdistrict_search');
      await h.respond();
      h.choose('13');
      h.grant();
      expect(h.posted()).toMatchObject({
        version: 2,
        district_id: '13',
        district_label: 'Other',
        destination_latitude: '0.0000000',
        destination_longitude: '0.0000000',
        shipping_address: initialAddress,
      });
      expect(h.node('.kiriof-buyer-map__status').textContent).toBe('Placed');
      expect(h.node('.kiriof-account-shipping-status').textContent).toContain('Pin saved');
      expect([...h.form.querySelectorAll('[name^="shipping_"]')]).toEqual(h.nativeControls);
      expect(h.node('[name="woocommerce-edit-address-nonce"]').value).toBe('native-nonce');
      expect(h.submit().defaultPrevented).toBe(false);
      expect(h.window.wp).toBeUndefined();
    } finally {
      await h.close();
    }
  });
  test('saved outside pin survives denied permission and native save; full address invalidates old callbacks', async () => {
    const h = activeAccount(savedPin({ destination_latitude: 1 }), {
      map: {
        coverage: { origin: { latitude: 0, longitude: 0 }, radiusMeters: 40000 },
        i18n: { mapOutsideRadius: 'Outside', mapCoverage: 'Coverage' },
      },
    });
    try {
      expect(h.node('.kiriof-buyer-map__coverage-warning').textContent).toBe('Outside');
      h.node('.kiriof-buyer-map__locate').click();
      h.flush();
      h.locations[0].failure({ code: 1 });
      h.flush();
      expect(h.node('.kiriof-buyer-map__status').textContent).toBe('Permission denied');
      h.advance(250);
      await h.respond();
      h.choose('13');
      h.submit();
      expect(h.posted().destination_latitude).toBe('1.0000000');
      expect(h.maps).toHaveLength(1);
      expect(h.node('.kiriof-buyer-map__viewport').hidden).toBe(false);
      h.edit('address_2', 'Suite');
      expect(h.posted().version).toBe(1);
      expect(h.node('.kiriof-buyer-map__coverage-warning').hidden).toBe(true);
      h.grant(0, 2, 3);
      expect(h.maps).toHaveLength(2);
      expect(h.maps[0].removed).toBe(1);
      expect(h.posted().version).toBe(1);
      h.node('.kiriof-buyer-map__locate').click();
      h.flush();
      h.grant();
      expect(h.posted().shipping_address.address_2).toBe('Suite');
      const map = h.maps.at(-1);
      h.edit('city', 'New city');
      expect(map.removed).toBe(1);
    } finally {
      await h.close();
    }
  });
  test('timeout, late response and retry retain saved identity without extra geolocation', async () => {
    const h = activeAccount(savedPin());
    try {
      expect(h.node('select').disabled).toBe(true);
      expect(h.node('select').value).toBe('12');
      h.advance(250);
      h.advance(10000);
      expect(h.node('select').disabled).toBe(false);
      expect(h.node('#kiriof-account-district-retry').hidden).toBe(false);
      await h.respond();
      expect(h.posted().district_label).toBe('Old label');
      h.node('#kiriof-account-district-retry').click();
      h.advance(250);
      await h.respond(1);
      expect(h.posted().district_label).toBe('Canonical');
      expect(h.locations).toHaveLength(0);
      expect(h.node('#kiriof-account-district-retry').hidden).toBe(true);
      h.choose('13');
      expect(h.posted().destination_latitude).toBe('0.0000000');
    } finally {
      await h.close();
    }
  });
  test('replaced Woo state control, country transitions and pagehide dispose requests and Leaflet', async () => {
    const h = activeAccount();
    try {
      h.grant();
      const map = h.maps[0];
      const old = h.form.elements.namedItem('shipping_state'),
        next = h.document.createElement('select');
      next.name = 'shipping_state';
      next.innerHTML = '<option value="JB">JB</option>';
      old.replaceWith(next);
      h.submit();
      expect(h.posted().version).toBe(1);
      expect(map.removed).toBe(1);
      h.edit('country', 'US');
      expect(h.node('.kiriof-buyer-map').hidden).toBe(true);
      h.grant();
      expect(h.maps).toHaveLength(2);
      expect(h.maps.every((map) => map.removed === 1)).toBe(true);
      h.edit('country', 'ID');
      h.grant();
      h.advance(250);
      const current = h.maps.at(-1);
      h.window.dispatchEvent(new h.window.Event('pagehide'));
      h.flush();
      expect(current.removed).toBe(1);
      expect(h.requests.at(-1).options.signal.aborted).toBe(true);
      const posted = h.hidden.value;
      h.edit('postcode', '10220');
      expect(h.hidden.value).toBe(posted);
      expect([...h.timers.values()].filter((timer) => timer.delay !== 150)).toHaveLength(0);
    } finally {
      await h.close();
    }
  });
  test('stale pin is removed and disabled map never requests permission', async () => {
    const h = activeAccount(
      savedPin({ shipping_address: { ...initialAddress, address_1: 'Stale' } }),
      { map: { enabled: false } },
    );
    try {
      expect(h.posted().version).toBe(1);
      expect(h.locations).toHaveLength(0);
      expect(h.node('.kiriof-buyer-map').hidden).toBe(true);
    } finally {
      await h.close();
    }
  });
});
