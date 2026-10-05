import { afterEach, describe, expect, test } from 'bun:test';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { happy } from './helpers/ui-runtime';

const script = (name: string) => readFileSync(new URL(`../assets/wp/js/${name}.js`, import.meta.url), 'utf8');
const binding = { address_1: 'Jalan Merdeka 1', address_2: 'Unit 2', city: 'Jakarta', state: 'JK', postcode: '10110', country: 'ID' };
const saved = (overrides: any = {}) => ({ version: 2, district_id: '123', district_label: 'Gambir', country: 'ID', postcode: '10110', destination_latitude: '-6.2000000', destination_longitude: '106.8000000', shipping_address: { ...binding }, ...overrides });
const cleanups: (() => void)[] = [];
afterEach(() => { cleanups.splice(0).forEach(cleanup => cleanup()); });
function deferred() {
	let resolve!: (value: any) => void, reject!: (reason: any) => void;
	const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
	return { promise, resolve, reject };
}
const settle = async () => { for (let i = 0; i < 12; i++) await Promise.resolve(); };
function runtime() {
	const window: any = new happy.Window({ url: 'https://shop.example/checkout/' });
	let now = 0, sequence = 0;
	const timers = new Map<number, { at: number; callback: () => void }>();
	const setTimeout = (callback: () => void, delay = 0) => { const id = ++sequence; timers.set(id, { at: now + delay, callback }); return id; };
	const clearTimeout = (id: number) => timers.delete(id);
	window.setTimeout = setTimeout; window.clearTimeout = clearTimeout;
	// happy-dom omits the browser Option convenience constructor.
	window.Option = function(text = '', value = '') { const option = window.document.createElement('option'); option.textContent = text; option.value = value; return option; };
	const context = { window, document: window.document, setTimeout, clearTimeout, URLSearchParams, AbortController, Option: window.Option, console };
	const load = (name: string) => runInNewContext(script(name), context);
	load('kiriof-checkout-session');
	load('kiriof-map-checkout'); load('kiriof-classic-checkout-core');
	const advance = async (duration = 0) => {
		const end = now + duration;
		for (let guard = 0; guard < 100; guard++) {
			await settle();
			const next = [...timers.entries()].filter(([, timer]) => timer.at <= end).sort((a, b) => a[1].at - b[1].at)[0];
			if (!next) { now = end; await settle(); return; }
			timers.delete(next[0]); now = next[1].at; next[1].callback();
		}
		throw new Error('Checkout event/timer cycle');
	};
	cleanups.push(() => { timers.clear(); window.happyDOM.abort(); });
	return { window, load, advance, timers };
}
function coreHarness(options: any = {}) {
	const h = runtime(); const sends: any[] = [];
	const controller = h.window.kiriofClassicCheckoutCore.create({ address: binding, scope: 'billing', settings: () => ({ payment_method: 'bacs', insurance: false, shipping_methods: ['kiriminaja:regular'] }), send: (snapshot: any) => { const request = deferred(); sends.push({ snapshot, ...request }); return request.promise; }, ...options });
	return { ...h, controller, sends };
}
function adapter(options: any = {}) {
	const h = runtime(), { window } = h; const document = window.document;
	document.body.innerHTML = `<form class="checkout"><div class="woocommerce-billing-fields__field-wrapper"></div><div class="woocommerce-shipping-fields__field-wrapper"></div><input id="ship-to-different-address-checkbox" type="checkbox" name="ship_to_different_address"><input name="payment_method" type="radio" value="bacs" checked><input id="kiriof_insurance" type="checkbox"><input class="shipping_method" type="radio" name="shipping_method[0]" value="kiriminaja:regular" checked><input class="shipping_method" type="radio" name="shipping_method[0]" value="kiriminaja-instant:gosend"><input class="shipping_method" type="hidden" name="shipping_method[1]" value="flat_rate:2"></form>`;
	const form: any = document.querySelector('form');
	for (const scope of ['billing', 'shipping']) {
		for (const key of ['first_name', 'last_name', 'company', ...Object.keys(binding)]) { const input = document.createElement('input'); input.id = `${scope}_${key}`; input.name = input.id; input.value = (binding as any)[key] || ''; form.querySelector(`.woocommerce-${scope}-fields__field-wrapper`).append(input); }
		const id = scope === 'billing' ? 'kiriof_destination_area' : 'kiriof_shipping_destination_area';
		const wrapper = document.createElement('div'); wrapper.id = `${id}_field`; wrapper.innerHTML = `<select id="${id}" name="${id}"><option value="123" selected>Gambir</option></select><input id="${id}_name" name="${id}_name" value="Gambir">`; form.append(wrapper);
	}
	const handlers: any[] = [], events: string[] = [];
	const emit = (node: any, event: string, target = node) => {
		events.push(event); let result: any;
		for (const entry of [...handlers]) if (entry.node === node && entry.event.split('.')[0] === event && (!entry.selector || target.matches(entry.selector))) { const next = entry.callback.call(target, { target, type: event }); if (next === false) result = false; else if (result !== false) result = next; }
		return result;
	};
	window.jQuery = (node: any) => {
		if (typeof node === 'function') { node(); return; }
		const chain: any = { on(names: string, selector: any, callback?: any) { for (const event of names.split(' ')) handlers.push({ node, event, selector: callback ? selector : null, callback: callback || selector }); return chain; }, off(namespace: string) { for (let i = handlers.length - 1; i >= 0; i--) if (handlers[i].node === node && handlers[i].event.endsWith(namespace)) handlers.splice(i, 1); return chain; }, trigger(event: string) { emit(node, event); return chain; } }; return chain;
	};
	const requests: any[] = [];
	window.fetch = (_url: string, init: any) => { const request = deferred(); requests.push({ init, body: new URLSearchParams(init.body), ...request }); return request.promise; };
	const geo: any[] = []; Object.defineProperty(window.navigator, 'geolocation', { value: { getCurrentPosition(success: any, failure: any) { geo.push({ success, failure }); } }, configurable: true });
	const maps: any[] = [];
	window.L = { map() { const listeners: any = {}; const map: any = { listeners, removed: false, setView() { return map; }, on(name: string, callback: any) { listeners[name] = callback; return map; }, invalidateSize() {}, getCenter() { return { lat: -6.2, lng: 106.8 }; }, remove() { map.removed = true; } }; maps.push(map); return map; }, tileLayer() { return { addTo() { return this; }, on() {} }; } };
	window.HTMLElement.prototype.scrollIntoView = () => {};
	window.kiriofClassicCheckoutConfig = { enabled: true, ownsDistrict: false, ajaxUrl: '/admin-ajax.php', nonce: 'nonce', needsShipping: true, map: { enabled: true, tiles: 'https://tiles.example/{z}/{x}/{y}.png', i18n: { mapPermission: 'Permission denied', mapLocationFailed: 'Location failed', mapPlaced: 'Pin placed', mapLocating: 'Locating' } }, ...options };
	h.load('kiriof-classic-checkout');
	const query = (selector: string): any => document.querySelector(selector);
	const hidden = () => JSON.parse(query('[name="kiriof_buyer_destination_snapshot"]').value);
	const change = (id: string, value: any, kind = 'change') => { const input = query(`#${id}`); if (typeof value === 'boolean') input.checked = value; else input.value = value; emit(form, kind, input); };
	const respond = async (request: any, data: any = null, success = true) => { request.resolve({ ok: true, json: async () => ({ success, data }) }); await settle(); };
	const mutations = () => requests.filter(r => r.body.get('action') === 'kiriof-session-save');
	const payload = (request: any) => JSON.parse(request.body.get('data'));
	const lookups = () => requests.filter(r => r.body.get('action') === 'kiriminaja_subdistrict_search');
	const acknowledge = async () => { for (let i = 0; i < 8; i++) { await h.advance(); const request = mutations().find(r => !r.done); if (!request) return; request.done = true; await respond(request); emit(document.body, 'updated_checkout'); } throw new Error('Checkout update cycle'); };
	const choose = (value = '123', label = 'Gambir', scope = 'billing') => { const id = scope === 'shipping' ? 'kiriof_shipping_destination_area' : 'kiriof_destination_area'; const select = query(`#${id}`); select.append(new window.Option(label, value)); select.value = value; query(`#${id}_name`).value = label; emit(window.document.body, 'kiriof:classic-district-synced'); };
	cleanups.push(() => window.dispatchEvent(new window.Event('pagehide')));
	return { ...h, form, query, hidden, change, respond, mutations, lookups, acknowledge, choose, requests, payload, emit, events, handlers, geo, maps };
}

describe('Classic core with real shared session queue', () => {
	test('serializes address context alongside native settings without changing core queue contract', async () => {
		const h = coreHarness(); h.controller.sync(); await h.advance();
		expect(h.sends[0].snapshot).toMatchObject({ action: 'sync_checkout', address_scope: 'billing', effective_address: binding, insurance: false, payment_method: 'bacs', shipping_methods: ['kiriminaja:regular'] });
	});
	test('boots against the actual session export without a compatibility alias', () => {
		const h = runtime(); delete h.window.kiriofCheckoutSession;
		expect(() => h.window.kiriofClassicCheckoutCore.create({ address: binding, settings: () => ({}), send: () => Promise.resolve() })).not.toThrow();
	});
	test('restores v2 pins using six delivery binding fields, not recipient metadata', () => {
		const h = coreHarness({ address: { ...binding, first_name: 'Buyer', company: 'Company' }, savedDestination: saved() });
		expect(h.controller.getState().point).toEqual({ latitude: '-6.2000000', longitude: '106.8000000' });
		expect(h.controller.getState().destination.version).toBe(2);
	});
	test('normalizes country/postcode and never restores a pin from v1 or foreign/mismatched destination', () => {
		for (const snapshot of [saved({ version: 1 }), saved({ postcode: '99999' }), saved({ country: 'US' }), saved({ shipping_address: { ...binding, city: 'Bandung' } })]) {
			const h = coreHarness({ savedDestination: snapshot }); expect(h.controller.getState().point).toBeNull();
		}
		const h = coreHarness({ address: { ...binding, country: ' id ', postcode: '10 110' }, savedDestination: saved() });
		expect(h.controller.getState().destination.country).toBe('ID'); expect(h.controller.getState().destination.postcode).toBe('10110'); expect(h.controller.getState().point).not.toBeNull();
	});
	test('every binding-field edit invalidates pins; postcode/country/scope also clear district', () => {
		for (const key of Object.keys(binding)) {
			const h = coreHarness({ savedDestination: saved() }); h.controller.updateAddress({ ...binding, [key]: key === 'country' ? 'US' : 'changed' }, 'billing');
			expect(h.controller.getState().point).toBeNull(); expect(Boolean(h.controller.getState().selection)).toBe(!['postcode', 'country'].includes(key));
		}
		const h = coreHarness({ savedDestination: saved() }); h.controller.updateAddress(binding, 'shipping'); expect(h.controller.getState().scope).toBe('shipping'); expect(h.controller.getState().selection).toBeNull(); expect(h.controller.getState().point).toBeNull();
	});
	test('serializes writes, supersedes unsent snapshots, and preserves newer pending before older completion', async () => {
		const h = coreHarness(); const first = h.controller.selectDistrict({ id: '123', label: 'First' }); await h.advance();
		const middle = h.controller.selectDistrict({ id: '124', label: 'Middle' }); const latest = h.controller.selectDistrict({ id: '125', label: 'Latest' });
		expect((await middle).status).toBe('superseded'); expect(h.sends).toHaveLength(1); expect(h.controller.getState().queue.pending.destination.district_id).toBe('125');
		h.sends[0].resolve({}); await h.advance(); expect((await first).status).toBe('success'); expect(h.sends).toHaveLength(2); expect(h.sends[1].snapshot.destination.district_id).toBe('125');
		h.sends[1].resolve({}); await settle(); expect((await latest).status).toBe('success');
	});
	test('failed save remains pending and retries without aborting server mutations on dispose', async () => {
		const h = coreHarness(); const result = h.controller.sync(); await h.advance(); h.sends[0].reject(new Error('offline')); await settle(); expect((await result).status).toBe('error'); expect(h.controller.getState().queue.error).not.toBeNull();
		const retry = h.controller.retry(); await h.advance(); expect(h.sends).toHaveLength(2); expect(Object.isFrozen(h.sends[1].snapshot)).toBe(true); h.controller.dispose(); expect((await retry).status).toBe('disposed'); h.sends[1].resolve({}); await settle(); expect(h.controller.getState().queue.disposed).toBe(true);
	});
	test('rejects stale and invalid coordinates', () => {
		const h = coreHarness({ savedDestination: saved() }); expect(h.controller.selectPoint({ latitude: -6.2, longitude: 106.8 }, { ...binding, address_1: 'Old' })).toBe(false);
		h.controller.selectPoint({ latitude: 100, longitude: 106.8 }, binding); expect(h.controller.getState().point).toBeNull();
	});
});

describe('Classic pin-only DOM adapter with delegated Woo events', () => {
	test('observes existing legacy district and label without adding lookup/select/fee requests or rewriting native fields', async () => {
		const h = adapter();
		const ids = ['billing_city', 'billing_state', 'billing_postcode', 'kiriof_destination_area', 'kiriof_destination_area_name', 'kiriof_shipping_destination_area'];
		const before = ids.map(id => h.query(`#${id}`).outerHTML);
		const panel = h.query('.kiriof-classic-pin');
		expect(panel.parentNode.lastElementChild).toBe(panel);
		expect(panel.getAttribute('data-priority')).toBe('999');
		expect(panel.querySelectorAll('select')).toHaveLength(0);
		expect(h.query('#kiriof-classic-district')).toBeNull();
		expect(h.geo).toHaveLength(1);
		h.geo[0].success({ coords: { latitude: -6.2, longitude: 106.8 } });
		await h.acknowledge();
		expect(ids.map(id => h.query(`#${id}`).outerHTML)).toEqual(before);
		expect(ids.every(id => !h.query(`#${id}`).hidden)).toBe(true);
		expect(h.lookups()).toHaveLength(0);
		expect(h.requests.every(r => r.body.get('action') === 'kiriof-session-save')).toBe(true);
		expect(h.hidden()).toMatchObject({ version: 2, district_id: '123', district_label: 'Gambir', destination_latitude: '-6.2000000' });
	});
	test('canonical pin save uses root nonce and JSON data, never district/payment/insurance/method mutations', async () => {
		const h = adapter({ savedDestination: saved() }); await h.acknowledge();
		const request = h.mutations().at(-1);
		expect([...request.body.keys()].sort()).toEqual(['action', 'data', 'nonce']);
		expect(request.body.get('nonce')).toBe('nonce');
		expect(h.payload(request)).toEqual({ action: 'sync_classic_pin', address_scope: 'billing', effective_address: binding, destination: h.hidden() });
		expect(request.init.credentials).toBe('same-origin');
		expect(request.init.signal).toBeUndefined();
		expect(new h.window.FormData(h.form).get('kiriof_buyer_destination_snapshot')).toBe(JSON.stringify(h.hidden()));
	});
	test('saved address-bound pin wins over geolocation and Woo update rounds never loop', async () => {
		const h = adapter({ savedDestination: saved() });
		expect(h.geo).toHaveLength(0); expect(h.maps).toHaveLength(1);
		await h.acknowledge(); const count = h.mutations().length;
		for (let i = 0; i < 4; i++) { h.emit(h.window.document.body, 'update_checkout'); h.emit(h.window.document.body, 'updated_checkout'); await h.advance(); }
		expect(h.mutations()).toHaveLength(count); expect(h.maps).toHaveLength(1);
		expect(h.events.filter(e => e === 'update_checkout')).toHaveLength(count + 4);
	});
	test('existing district-synced event observes matching label and retains device suggestion until district exists', async () => {
		const h = adapter(); h.query('#kiriof_destination_area').value = ''; h.query('#kiriof_destination_area_name').value = '';
		h.emit(h.window.document.body, 'kiriof:classic-district-synced');
		h.geo[0].success({ coords: { latitude: 0, longitude: 0 } });
		expect(h.hidden().version).toBe(1);
		h.choose('456', 'Existing legacy label');
		expect(h.hidden()).toMatchObject({ version: 2, district_id: '456', district_label: 'Existing legacy label', destination_latitude: '0.0000000' });
		await h.acknowledge(); expect(h.lookups()).toHaveLength(0);
	});
	test('seller-disabled map makes no geolocation or tile/map requests', async () => {
		const h = adapter({ map: { enabled: false } }); await h.acknowledge();
		expect(h.geo).toHaveLength(0); expect(h.maps).toHaveLength(0); expect(h.lookups()).toHaveLength(0);
		h.query('.kiriof-classic-map-locate').click(); expect(h.geo).toHaveLength(0);
		const disabled = adapter({ enabled: false }); await disabled.advance(1000);
		expect(disabled.query('.kiriof-classic-pin')).toBeNull(); expect(disabled.requests).toHaveLength(0);
	});
	test('virtual, foreign and pickup checkouts hide only pin panel and do not require pin', async () => {
		for (const options of [{ needsShipping: false }, {}]) {
			const h = adapter(options);
			if (options.needsShipping !== false) { h.query('input.shipping_method').value = 'local_pickup:1'; h.query('input.shipping_method[type="hidden"]').value = 'local_pickup:2'; h.emit(h.form, 'change', h.query('input.shipping_method')); await h.advance(200); }
			expect(h.query('.kiriof-classic-pin').hidden).toBe(true);
			expect(h.query('#billing_postcode').hidden).toBe(false);
			expect(h.emit(h.form, 'checkout_place_order')).toBe(true);
		}
		const foreign = adapter(); foreign.change('billing_country', 'US'); await foreign.advance(200);
		expect(foreign.query('.kiriof-classic-pin').hidden).toBe(true);
	});
	test('auto geolocation failure can retry and stale callbacks cannot overwrite changed address', async () => {
		const h = adapter(); h.geo[0].failure({ code: 1 });
		expect(h.query('.kiriof-classic-pin [role="status"]').textContent).toBe('Permission denied'); expect(h.maps).toHaveLength(0);
		h.query('.kiriof-classic-map-locate').click(); const stale = h.geo[1];
		h.change('billing_address_1', 'New street'); await h.advance(200);
		const status = h.query('.kiriof-classic-pin [role="status"]').textContent;
		stale.success({ coords: { latitude: -7, longitude: 107 } }); stale.failure({ code: 1 });
		expect(h.maps).toHaveLength(0); expect(h.query('.kiriof-classic-pin [role="status"]').textContent).toBe(status);
		h.geo.at(-1).success({ coords: { latitude: -6.2, longitude: 106.8 } });
		expect(h.hidden().destination_latitude).toBe('-6.2000000');
	});
	test('address and scope edits invalidate pin without changing existing district fields', async () => {
		const h = adapter({ savedDestination: saved() }); await h.acknowledge();
		h.change('billing_address_2', 'Changed unit'); await h.advance(200);
		expect(h.maps[0].removed).toBe(true); expect(h.hidden().destination_latitude).toBeUndefined();
		expect(h.query('#kiriof_destination_area').value).toBe('123');
		h.choose('456', 'Shipping legacy label', 'shipping');
		h.change('ship-to-different-address-checkbox', true); await h.advance(200); await h.acknowledge();
		expect(h.query('.kiriof-classic-pin').parentNode.className).toBe('woocommerce-shipping-fields__field-wrapper');
		expect(h.payload(h.mutations().at(-1))).toMatchObject({ address_scope: 'shipping', destination: { district_id: '456', district_label: 'Shipping legacy label' } });
		expect(h.hidden().destination_latitude).toBeUndefined();
	});
	test('pending pin blocks ONLY Instant; regular remains unaffected, settled Instant allows submit', async () => {
		const h = adapter({ savedDestination: saved() });
		expect(h.emit(h.form, 'checkout_place_order')).toBe(true);
		h.query('[value="kiriminaja-instant:gosend"]').checked = true;
		expect(h.emit(h.form, 'checkout_place_order')).toBe(false);
		await h.acknowledge(); expect(h.emit(h.form, 'checkout_place_order')).toBe(true);
		h.change('billing_address_1', 'Changed street'); await h.advance(200); await h.acknowledge();
		expect(h.emit(h.form, 'checkout_place_order')).toBe(false);
		h.query('[value="kiriminaja:regular"]').checked = true;
		expect(h.emit(h.form, 'checkout_place_order')).toBe(true);
	});
	test('failed pin saves retry without permitting Instant or aborting server mutations', async () => {
		const h = adapter({ savedDestination: saved() }); await h.advance();
		await h.respond(h.mutations()[0], null, false);
		expect(h.query('.kiriof-classic-pin > button').hidden).toBe(false);
		h.query('[value="kiriminaja-instant:gosend"]').checked = true;
		expect(h.emit(h.form, 'checkout_place_order')).toBe(false);
		h.query('.kiriof-classic-pin > button').click(); await h.advance();
		expect(h.mutations()).toHaveLength(2); await h.respond(h.mutations()[1]); h.emit(h.window.document.body, 'updated_checkout'); await h.advance();
		expect(h.emit(h.form, 'checkout_place_order')).toBe(true);
	});
	test('pagehide removes pin namespace handlers and map, leaves in-flight save un-aborted', async () => {
		const h = adapter({ savedDestination: saved() }); await h.advance();
		expect(h.handlers.every(entry => entry.event.endsWith('.kiriofClassicPin'))).toBe(true);
		h.window.dispatchEvent(new h.window.Event('pagehide'));
		expect(h.handlers).toHaveLength(0); expect(h.maps[0].removed).toBe(true);
		expect(h.mutations()[0].init.signal).toBeUndefined(); const before = h.hidden();
		await h.respond(h.mutations()[0]); await h.advance(200000);
		expect(h.hidden()).toEqual(before); expect(h.events.filter(e => e === 'update_checkout')).toHaveLength(0);
	});
});
