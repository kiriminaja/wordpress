import { describe, expect, test } from 'bun:test';
import { buyerRuntimeSource, buyerBrowserContext } from './helpers/buyer-runtime-source';
import { React, happy, rendererRequire } from './helpers/ui-runtime';
import { runInNewContext } from 'node:vm';
import { mutationListeners } from 'happy-dom/lib/PropertySymbol.js';

const uiTest = test;
const scripts = [await buyerRuntimeSource('state'), await buyerRuntimeSource('blocks')];
const address = { address_1: 'Main Road', address_2: '', city: 'Jakarta', state: 'JK', postcode: '12345', country: 'ID' };
function saved(overrides: any = {}) {
	return { version: 2, district_id: '7', district_label: 'Old label', postcode: '12345', country: 'ID', destination_latitude: '0', destination_longitude: 0, shipping_address: { ...address }, ...overrides };
}
function deferred() { let resolve!: (value: any) => void; const promise = new Promise<any>(yes => { resolve = yes; }); return { promise, resolve }; }
function events() {
	const handlers = new Map<string, any>();
	return { on(name: string, callback: any) { handlers.set(name, callback); return this; }, off() { handlers.clear(); return this; }, fire(name: string, event?: any) { handlers.get(name)?.(event); } };
}
async function fixture(options: { editing?: boolean; guest?: boolean; savedDestination?: any; mapFirst?: boolean; autoLocation?: boolean; restore?: boolean; address?: any } = {}) {
	const window = new happy.Window();
	// Happy DOM 20.8.4 stores only a WeakRef to its listener closure. Retain
	// the native closure while observed so Bun GC cannot drop live delivery.
	// Record creation, batching and callbacks still belong to the real observer.
	const NativeObserver = window.MutationObserver;
	window.MutationObserver = class extends NativeObserver {
		callbacks = new Set<any>();
		observe(node: any, options: any) {
			super.observe(node, options);
			for (const listener of node[mutationListeners]) this.callbacks.add(listener.callback.deref());
		}
		disconnect() { super.disconnect(); this.callbacks.clear(); }
	};
	const document = window.document;
	const previous = new Map<string, any>();
	for (const [key, value] of Object.entries({ window, document, navigator: window.navigator, HTMLElement: window.HTMLElement, Node: window.Node, MutationObserver: window.MutationObserver, IS_REACT_ACT_ENVIRONMENT: true })) {
		previous.set(key, Object.getOwnPropertyDescriptor(globalThis, key));
		Object.defineProperty(globalThis, key, { configurable: true, writable: true, value });
	}
	const { createRoot } = rendererRequire('react-dom/client');
	const { createPortal } = rendererRequire('react-dom');
	const act = React.act || rendererRequire('react-dom/test-utils').act;
	// Woo 10.6: native card and shipping form are siblings; plugin child
	// mounts are outside the address wrapper, not inside its hidden card/form.
	document.body.innerHTML = `<div class="wp-block-woocommerce-checkout wc-block-checkout"><div id="shipping-fields" class="wc-block-checkout__shipping-fields"><div class="wc-block-components-address-address-wrapper${options.editing ? ' is-editing' : ''}">${options.guest ? '' : '<div class="wc-block-components-address-card"><address>Main Road, Jakarta</address><button class="wc-block-components-address-card__edit" aria-controls="shipping" aria-expanded="' + !!options.editing + '">Edit</button></div>'}<div id="shipping" class="wc-block-components-address-form">Native shipping inputs</div></div><div class="wp-block-kiriminaja-official-checkout-district" id="district-mount"></div><div class="wp-block-kiriminaja-official-map-checkout" id="map-mount"></div></div><div id="billing-fields" class="wc-block-checkout__billing-fields"><div class="wc-block-components-address-address-wrapper is-editing"><div class="wc-block-components-address-card"><button class="wc-block-components-address-card__edit" aria-controls="billing" aria-expanded="true">Billing edit</button></div></div></div></div>`;
	const model = { cart: { needsShipping: true, shippingAddress: { ...address, ...options.address }, shippingRates: [{ package_id: 0, shipping_rates: [{ rate_id: 'kiriminaja:jne', method_id: 'kiriminaja-official', selected: true }] }] }, payment: 'cod', busy: false, customerBusy: false, collection: false };
	const subscribers = new Set<any>(), publications: any[] = [], validations: any[] = [], sends: any[] = [], lookups: any[] = [], maps: any[] = [], registrations: any[] = [];
	const locations: any[] = [], tiles: any[] = [], restores: any[] = [];
	const activeValidation: Record<string, { message: string; hidden: boolean }> = {};
	const validationSubscribers = new Set<() => void>();
	const validationDispatch = {
		setValidationErrors(errors: any) { validations.push(errors); Object.assign(activeValidation, errors); for (const callback of validationSubscribers) callback(); },
		clearValidationError(id: string) { validations.push({ clear: id }); if (activeValidation[id]) { delete activeValidation[id]; for (const callback of validationSubscribers) callback(); } },
	};
	function ValidationDisplay() {
		const [, update] = React.useState(0);
		React.useEffect(() => { const callback = () => update((value: number) => value + 1); validationSubscribers.add(callback); return () => { validationSubscribers.delete(callback); }; }, []);
		return React.createElement('div', { 'data-testid': 'validation-display' }, ...Object.entries(activeValidation).map(([id, error]) => React.createElement('p', { key: id, 'data-validation-id': id, hidden: error.hidden }, error.message)));
	}

	const cartDispatch = { selectShippingRate(rate: string, packageId: number | string) { const task = deferred(); restores.push({ rate, packageId, ...task }); return task.promise; } };
	Object.defineProperty(window.navigator, 'geolocation', { configurable: true, value: { getCurrentPosition(success: any, failure: any, options: any) { locations.push({ success, failure, options }); if (fixtureOptionsAutoLocation) success({ coords: { latitude: -6, longitude: 106 } }); } } });
	const fixtureOptionsAutoLocation = options.autoLocation !== false;
	const timers = new Map<number, { callback: any; delay: number }>(); let timerId = 0;
	const stores: any = {
		'wc/store/cart': { getCartData: () => model.cart, isShippingRateBeingSelected: () => model.busy, isCustomerDataUpdating: () => model.customerBusy, hasPendingItemsOperations: () => false },
		'wc/store/payment': { getActivePaymentMethod: () => model.payment },
		'wc/store/checkout': { prefersCollection: () => model.collection },
	};
	const select = (name: string) => stores[name];
	window.wp = { element: { ...React, createPortal }, data: {
		select,
		useSelect(callback: any) { const [, update] = React.useState(0); React.useEffect(() => { const listener = () => update((value: number) => value + 1); subscribers.add(listener); return () => subscribers.delete(listener); }, []); return callback(select); },
		dispatch: (name: string) => name === 'wc/store/cart' && options.restore ? cartDispatch : name === 'wc/store/checkout' ? { setExtensionData: (...args: any[]) => publications.push(args) } : validationDispatch,
		subscribe(callback: any) { subscribers.add(callback); return () => subscribers.delete(callback); },
	} };
	window.wc = { blocksCheckout: { registerCheckoutBlock: (registration: any) => registrations.push(registration), extensionCartUpdate(request: any) { sends.push(request); return Promise.resolve({}); } } };
	const strings = { district: 'Subdistrict', districtRequired: 'Subdistrict required', checkingDistrict: 'Checking subdistrict', districtNotSet: 'Subdistrict not set', pinLocation: 'Pin location', needPinLocation: 'Need pin location', pinRequirement: 'Pin required for instant', loading: 'Loading', saving: 'Saving', selectDistrict: 'Select subdistrict', empty: 'Empty', emptyRetry: 'No subdistricts found. Retry lookup.', lookupFailed: 'Lookup failed', retry: 'Retry', mapTitle: 'Delivery pin', mapLocating: 'Requesting location permission…', mapHelp: 'Move map', mapPlaced: 'Pin placed', mapLocate: 'Locate', mapClear: 'Clear', mapOptional: 'Optional', mapPermission: 'Permission denied', mapUnavailable: 'Map unavailable', mapLocationFailed: 'Location failed' };
	window.kiriofBuyerCheckoutConfig = { enabled: true, nonce: 'fixture', ajaxUrl: '/fixture-ajax', savedDestination: options.savedDestination, i18n: strings };
	window.kiriofMapCheckoutConfig = { enabled: true, tiles: 'https://tiles.example.test/{z}/{x}/{y}.png', i18n: strings };
	window.fetch = (url: any, init: any) => { const task = deferred(); lookups.push({ url, init, ...task }); return task.promise; };
	window.setTimeout = (callback: any, delay: number) => { timers.set(++timerId, { callback, delay }); return timerId; };
	window.clearTimeout = (id: number) => { timers.delete(id); };
	window.L = {
		map(node: any) { expect(node instanceof window.HTMLElement).toBe(true); const map = Object.assign(events(), { node, removed: 0, center: { lat: 0, lng: 0 }, setView(point: any) { this.center = { lat: point[0], lng: point[1] }; this.fire('movestart'); this.fire('moveend'); return this; }, getCenter() { return this.center; }, invalidateSize() {}, remove() { this.removed++; this.off(); } }); maps.push(map); return map; },
		tileLayer() { const tile = Object.assign(events(), { addTo() { return this; } }); tiles.push(tile); return tile; },
	};
	const context = buyerBrowserContext(window, { AbortController, URLSearchParams });
	for (const source of scripts) runInNewContext(source, context);
	expect(registrations.map(row => row.metadata.name)).toEqual(['kiriminaja-official/checkout-district', 'kiriminaja-official/map-checkout']);
	const roots = registrations.map((registration, index) => { const root = createRoot(document.getElementById(index ? 'map-mount' : 'district-mount')); return { root, component: registration.component }; });
	const validationMount = document.createElement('div'); document.body.append(validationMount);
	roots.push({ root: createRoot(validationMount), component: ValidationDisplay });
	await act(async () => { for (const item of options.mapFirst ? [...roots].reverse() : roots) item.root.render(React.createElement(item.component)); });
	// Deliver native mutation and host-repair batches inside act, then let act
	// commit React effects (including the permission-gated map). No timer sleeps
	// or calls to bridge.refresh: the real observer remains the only authority.
	async function drainMutations() {
		await new Promise<void>(resolve => window.queueMicrotask(resolve));
		await new Promise<void>(resolve => window.queueMicrotask(resolve));
	}
	async function mutation(callback: () => void) {
		await act(async () => { callback(); await drainMutations(); });
		// React's commit can enqueue further native/Svelte DOM work.
		await act(async () => { await drainMutations(); });
	}
	async function flush(delay: number) { await act(async () => { for (const [id, timer] of [...timers]) if (timer.delay === delay && timers.delete(id)) timer.callback(); }); }
	async function reply(rows = [{ id: 7, text: 'District Seven' }]) { await act(async () => { lookups.at(-1).resolve({ ok: true, json: () => Promise.resolve({ success: true, data: rows }) }); }); }
	async function notify() { await act(async () => { for (const callback of subscribers) callback(); }); }
	async function editing(value: boolean) { await mutation(() => { const wrapper = document.querySelector('#shipping-fields .wc-block-components-address-address-wrapper'); wrapper.classList.toggle('is-editing', value); wrapper.querySelector('button').setAttribute('aria-expanded', String(value)); }); }
	async function cleanup() {
		try {
			await act(async () => { for (const { root } of roots) root.unmount(); await drainMutations(); });
			expect(document.querySelector('.kiriof-address-status-host')).toBeNull();
			expect(maps.every(map => map.removed === 1)).toBe(true);
			window.dispatchEvent(new window.Event('pagehide'));
			expect(subscribers.size).toBe(0);
		} finally {
			try { await window.happyDOM.close(); }
			finally {
				for (const [key, descriptor] of previous) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete (globalThis as any)[key]; }
			}
		}
	}
	return { window, document, model, maps, locations, tiles, restores, publications, validations, sends, lookups, timers, act, mutation, editing, notify, flush, reply, cleanup,
		card: () => document.querySelector('#shipping-fields .wc-block-components-address-card'),
		badges: () => [...document.querySelectorAll('#shipping-fields .kiriof-address-status__badge')],
	};
}

describe('combined native address-card UI (real React/DOM, unchanged production VM)', () => {
	for (const busy of ['customerBusy', 'busy'] as const) {
		uiTest(`matching GoSend ${busy} billing toggle renders hidden pending without a changed-courier warning`, async () => {
			const h = await fixture({ restore: true, savedDestination: saved() });
			try {
				await h.flush(250); await h.reply(); await h.flush(0);
				const go = 'kiriminaja-instant:7:gosend:instant';
				const rates = [{ rate_id: go, method_id: 'kiriminaja-instant', selected: false }, { rate_id: 'kiriminaja:jne', method_id: 'kiriminaja-official', selected: true }];
				h.model.cart.shippingRates = [{ package_id: 0, shipping_rates: rates }];
				const radio = h.document.createElement('input'); radio.type = 'radio'; radio.checked = true; radio.value = go; h.document.body.append(radio);
				const beforeChoice = JSON.stringify(h.publications.at(-1)[1].shipping_selection);
				// Document capture must leave publication alone until the native
				// handler has updated Woo's selected flags during event propagation.
				let nativeChanges = 0;
				radio.addEventListener('change', () => {
					expect(JSON.stringify(h.publications.at(-1)[1].shipping_selection)).toBe(beforeChoice);
					rates[0].selected = true; rates[1].selected = false; nativeChanges++;
				});
				await h.act(async () => radio.dispatchEvent(new h.window.Event('change', { bubbles: true })));
				expect(nativeChanges).toBe(1);
				expect(JSON.stringify(h.publications.at(-1)[1].shipping_selection)).toBe(beforeChoice);
				await h.flush(0);
				expect(h.publications.at(-1)[1].shipping_selection.packages[0].rate_id).toBe(go);
				const original = JSON.stringify(h.publications.at(-1)[1].shipping_selection), sends = h.sends.length;
				const checkbox = h.document.createElement('input'); checkbox.type = 'checkbox'; checkbox.checked = true; h.document.querySelector('#billing-fields').append(checkbox);
				h.model[busy] = true;
				await h.act(async () => checkbox.dispatchEvent(new h.window.Event('change', { bubbles: true }))); await h.notify();
				const display = h.document.querySelector('[data-testid="validation-display"]');
				const pending = display.querySelector('[data-validation-id="kiriof-shipping-selection-pending"]');
				expect(pending).not.toBeNull(); expect(pending.hidden).toBe(true); expect(pending.textContent).toBe('Updating shipping options…');
				expect(display.textContent).not.toContain('Shipping options changed');
				expect(display.querySelector('[data-validation-id="kiriof-shipping-selection"]')).toBeNull();
				expect(JSON.stringify(h.publications.at(-1)[1].shipping_selection)).toBe(original);
				h.model[busy] = false; await h.notify();
				expect(display.querySelector('[data-validation-id="kiriof-shipping-selection-pending"]')).toBeNull();
				expect(display.textContent).not.toContain('Shipping options changed');
				expect(JSON.stringify(h.publications.at(-1)[1].shipping_selection)).toBe(original);
				expect(h.restores).toHaveLength(0); expect(h.sends).toHaveLength(sends);
			} finally { await h.cleanup(); }
		});
	}
	uiTest('real React collapsed native card billing checkbox preserves reviewed selection through asynchronous restoration', async () => {
		const h = await fixture({ restore: true, savedDestination: saved() });
		try {
			await h.flush(250); await h.reply(); await h.flush(0);
			const go = 'kiriminaja-instant:7:gosend:instant';
			const rates = [{ rate_id: go, method_id: 'kiriminaja-instant', selected: false }, { rate_id: 'kiriminaja:jne', method_id: 'kiriminaja-official', selected: true }];
			h.model.cart.shippingRates = [{ package_id: 0, shipping_rates: rates }];
			// Happy DOM cannot create trusted browser events: this side-event uses the
			// actual registered listener; Chromium separately verifies trusted input.
			const radio = h.document.createElement('input'); radio.type = 'radio'; radio.checked = true; radio.value = go; h.document.body.append(radio);
			const beforeChoice = JSON.stringify(h.publications.at(-1)[1].shipping_selection);
			let nativeChanges = 0;
			radio.addEventListener('change', () => {
				expect(JSON.stringify(h.publications.at(-1)[1].shipping_selection)).toBe(beforeChoice);
				rates[0].selected = true; rates[1].selected = false; nativeChanges++;
			});
			await h.act(async () => radio.dispatchEvent(new h.window.Event('change', { bubbles: true })));
			expect(nativeChanges).toBe(1);
			expect(JSON.stringify(h.publications.at(-1)[1].shipping_selection)).toBe(beforeChoice);
			await h.flush(0);
			expect(h.publications.at(-1)[1].shipping_selection.packages[0].rate_id).toBe(go);
			const sends = h.sends.length;
			const checkbox = h.document.createElement('input'); checkbox.type = 'checkbox'; checkbox.checked = true; h.document.querySelector('#billing-fields').append(checkbox);
			h.model.busy = true; rates[0].selected = false; rates[1].selected = true;
			await h.act(async () => checkbox.dispatchEvent(new h.window.Event('change', { bubbles: true }))); await h.notify();
			expect(h.restores).toHaveLength(0); expect(h.publications.at(-1)[1].shipping_selection.packages[0].rate_id).toBe(go);
			h.model.busy = false; await h.notify();
			expect(h.restores.map(({ rate, packageId }) => [rate, packageId])).toEqual([[go, 0]]);
			expect(h.validations.filter(row => row['kiriof-shipping-selection'] || row.clear === 'kiriof-shipping-selection').at(-1)['kiriof-shipping-selection']).toBeDefined();
			rates[0].selected = true; rates[1].selected = false;
			await h.act(async () => h.restores[0].resolve({})); await h.notify();
			expect(h.validations.filter(row => row['kiriof-shipping-selection'] || row.clear === 'kiriof-shipping-selection').at(-1)).toEqual({ clear: 'kiriof-shipping-selection' });
			expect(h.sends).toHaveLength(sends); expect(h.restores).toHaveLength(1);
			expect(h.document.querySelector('.kiriof-buyer-map')).toBeNull(); expect(h.document.querySelector('#billing-fields .kiriof-address-status-host')).toBeNull();
		} finally { await h.cleanup(); }
	});
	for (const failed of [false, true]) {
		uiTest(`55581 saved pin stays green after ${failed ? 'HTTP failure' : 'genuine empty'}; native Edit performs one fresh retry and canonical recovery`, async () => {
			const shipping = { ...address, postcode: '55581' };
			const h = await fixture({ address: shipping, savedDestination: saved({ postcode: '55581', district_id: '123', shipping_address: shipping }) });
			try {
				await h.flush(250);
				if (failed) await h.act(async () => h.lookups[0].resolve({ ok: false, json: () => Promise.resolve({ success: true, data: [] }) }));
				else await h.reply([]);
				await h.flush(0);
				const label = failed ? 'Lookup failed' : 'No subdistricts found. Retry lookup.';
				expect(h.sends).toHaveLength(0); expect(h.window.kiriofBuyerCheckout.getDestination().district_id).toBe('');
				expect(h.window.kiriofBuyerCheckout.getCoordinates(shipping)).toMatchObject({ latitude: '0.0000000', longitude: '0.0000000' });
				expect(h.badges().find(node => node.textContent === 'Pin location').classList.contains('is-complete')).toBe(true);
				const notices = h.card().querySelectorAll('.kiriof-address-status__recovery .kiriof-address-status__message');
				expect(notices).toHaveLength(1); expect(notices[0].textContent).toBe(label);
				expect(notices[0].classList.contains('is-warning')).toBe(true);
				expect(h.badges().map(node => node.textContent)).toEqual(['Pin location']);
				expect(h.badges().some(node => node.textContent === 'Subdistrict not set')).toBe(false);
				expect([...h.card().querySelectorAll('.kiriof-address-status button')].map(node => node.textContent)).toEqual(['Retry']);
				await h.editing(true);
				expect(h.document.querySelector('.kiriof-buyer-map')).not.toBeNull(); expect(h.maps).toHaveLength(1);
				expect(h.document.querySelector('select').disabled).toBe(true); expect(h.document.querySelector('select').options).toHaveLength(1);
				await h.notify(); await h.flush(250); expect(h.lookups).toHaveLength(2);
				const request = h.lookups[1]; expect(request.init.method).toBe('POST');
				expect(new URLSearchParams(request.init.body).get('term')).toBe('55581'); expect(new URLSearchParams(request.init.body).get('retry')).toBe('1');
				expect(h.sends).toHaveLength(0);
				await h.reply([{ id: 123, text: 'Canonical 55581 district' }]); await h.flush(0);
				expect(h.document.querySelector('select').value).toBe('123'); expect(h.document.querySelector('select').disabled).toBe(false);
				expect(h.window.kiriofBuyerCheckout.getDestination()).toMatchObject({ district_id: '123', district_label: 'Canonical 55581 district', destination_latitude: '0.0000000', postcode: '55581' });
				expect(h.sends).toHaveLength(1); expect(h.sends[0].data.destination.district_label).toBe('Canonical 55581 district');
				await h.editing(false); await h.editing(true); await h.flush(250); expect(h.lookups).toHaveLength(2);
			} finally { await h.cleanup(); }
		});
	}
	uiTest('collapsed empty card Retry and editing genuine-empty Retry remain actionable without billing-triggered lookup', async () => {
		const h = await fixture({ savedDestination: saved() });
		try {
			await h.flush(250); await h.reply([]); await h.flush(0);
			const checkbox = h.document.createElement('input'); checkbox.type = 'checkbox'; checkbox.checked = true; h.document.querySelector('#billing-fields').append(checkbox);
			await h.act(async () => checkbox.dispatchEvent(new h.window.Event('change', { bubbles: true }))); await h.notify(); await h.flush(250);
			expect(h.lookups).toHaveLength(1); expect(h.sends).toHaveLength(0);
			await h.act(async () => h.card().querySelector('.kiriof-address-status button').click()); await h.flush(250);
			expect(new URLSearchParams(h.lookups[1].init.body).get('retry')).toBe('1'); await h.reply([]);
			await h.editing(true); await h.flush(250); await h.reply([]);
			expect(h.document.querySelector('.kiriof-buyer-district [role="status"]').textContent).toBe('No subdistricts found. Retry lookup.');
			expect(h.document.querySelector('.kiriof-buyer-district button').textContent).toBe('Retry');
			await h.act(async () => h.document.querySelector('.kiriof-buyer-district button').click()); await h.flush(250); await h.reply([{ id: 7, text: 'Recovered canonical district' }]); await h.flush(0);
			expect(h.document.querySelector('select').value).toBe('7'); expect(h.sends).toHaveLength(1);
		} finally { await h.cleanup(); }
	});
	uiTest('failed lookup inside native Edit is an error with Retry, not an empty-results label', async () => {
		const h = await fixture({ editing: true, savedDestination: saved() });
		try {
			await h.flush(250);
			await h.act(async () => h.lookups[0].resolve({ ok: true, json: () => Promise.resolve({ success: false, data: [] }) }));
			await h.flush(0);
			expect(h.document.querySelector('.kiriof-buyer-district [role="status"]').textContent).toBe('Lookup failed');
			expect(h.document.querySelector('.kiriof-buyer-district button').textContent).toBe('Retry'); expect(h.sends).toHaveLength(0);
			await h.act(async () => h.document.querySelector('.kiriof-buyer-district button').click()); await h.flush(250);
			expect(new URLSearchParams(h.lookups[1].init.body).get('retry')).toBe('1');
			await h.reply([{ id: 7, text: 'Verified district' }]); await h.flush(0);
			expect(h.document.querySelector('select').value).toBe('7'); expect(h.sends).toHaveLength(1);
		} finally { await h.cleanup(); }
	});
	uiTest('native edit opens permission status only; saved pin survives denial and map awaits an explicit grant on reentry', async () => {
		const h = await fixture({ savedDestination: saved(), autoLocation: false });
		try {
			await h.flush(250); await h.reply(); await h.flush(0);
			expect(h.locations).toHaveLength(0);
			await h.editing(true);
			expect(h.locations).toHaveLength(1); expect(h.maps).toHaveLength(0); expect(h.tiles).toHaveLength(0);
			expect(h.document.querySelector('.kiriof-buyer-map__canvas')).toBeNull(); expect(h.document.querySelector('.kiriof-buyer-map [role="status"]').textContent).toContain('Requesting location permission');
			await h.act(async () => h.locations[0].failure({ code: 1 }));
			expect(h.maps).toHaveLength(0); expect(h.tiles).toHaveLength(0); expect(h.window.kiriofBuyerCheckout.getCoordinates(h.model.cart.shippingAddress)).toMatchObject({ latitude: '0.0000000', longitude: '0.0000000' });
			await h.editing(false); await h.editing(true); expect(h.locations).toHaveLength(2); expect(h.maps).toHaveLength(0);
			await h.act(async () => h.locations[0].success({ coords: { latitude: 1, longitude: 2 } })); expect(h.maps).toHaveLength(0);
			await h.act(async () => h.locations[1].success({ coords: { latitude: -6, longitude: 106 } }));
			expect(h.maps).toHaveLength(1); expect(h.tiles).toHaveLength(1); expect(h.maps[0].center).toEqual({ lat: 0, lng: 0 });
		} finally { await h.cleanup(); }
	});
	uiTest('collapsed card does not briefly create a map when Map mounts before District', async () => {
		const h = await fixture({ mapFirst: true });
		try { expect(h.locations).toHaveLength(0); expect(h.maps).toHaveLength(0); expect(h.document.querySelector('.kiriof-buyer-map')).toBeNull(); expect(h.badges().length).toBeGreaterThan(0); } finally { await h.cleanup(); }
	});
	uiTest('closed native card initializes district validation/publication without controls or map; pending is not green, missing district and pin warn after lookup', async () => {
		const h = await fixture();
		try {
			expect(h.document.querySelector('.kiriof-buyer-district')).toBeNull(); expect(h.document.querySelector('.kiriof-buyer-map')).toBeNull(); expect(h.maps).toHaveLength(0);
			expect(h.badges().map(node => node.textContent)).toEqual(['Checking subdistrict', 'Need pin location']);
			for (const badge of h.badges()) { expect(h.card().contains(badge)).toBe(true); expect(badge.classList.contains('is-complete')).toBe(false); }
			expect(h.document.querySelector('#billing-fields .kiriof-address-status-host')).toBeNull();
			expect(h.publications.length).toBeGreaterThan(0); expect(h.validations.some(value => value['kiriof-buyer-destination']?.message === 'Loading')).toBe(true);
			await h.flush(250); expect(h.lookups).toHaveLength(1); expect(new URLSearchParams(h.lookups[0].init.body).get('term')).toBe('12345'); await h.reply(); await h.flush(0);
			expect(h.badges().map(node => node.textContent)).toEqual(['Subdistrict not set', 'Need pin location']); expect(h.badges().every(node => node.classList.contains('is-warning'))).toBe(true);
			expect(h.sends).toHaveLength(0); expect(h.window.kiriofBuyerCheckout.getDestination().district_id).toBe(''); expect(h.window.kiriofBuyerCheckout.active).toBe(true);
		} finally { await h.cleanup(); }
	});
	uiTest('matching saved zero pin is green, edit requests permission before opening native map, close disposes only map and retains destination', async () => {
		const h = await fixture({ savedDestination: saved() });
		try {
			expect(h.badges().find(node => node.textContent === 'Pin location')?.classList.contains('is-complete')).toBe(true);
			await h.flush(250); await h.reply(); await h.flush(0);
			expect(h.badges().map(node => node.textContent)).toEqual(['Pin location']);
			expect(h.window.kiriofBuyerCheckout.getDestination().district_label).toBe('District Seven');
			expect(h.publications.every(([, payload]) => payload.destination !== null)).toBe(true);
			expect(h.publications.at(-1)).toEqual(['kiriminaja-official', { destination: h.window.kiriofBuyerCheckout.getDestination(), shipping_selection: { version: 1, packages: [{ package_id: '0', rate_id: 'kiriminaja:jne' }] } }]);
			await h.editing(true); expect(h.document.querySelector('.kiriof-address-status')).toBeNull(); expect(h.document.querySelector('.wc-blocks-components-select__select')).not.toBeNull(); expect(h.document.querySelector('.kiriof-buyer-map')).not.toBeNull(); expect(h.maps).toHaveLength(1); expect(h.maps[0].center).toEqual({ lat: 0, lng: 0 });
			await h.act(async () => h.maps[0].fire('click', { latlng: { lat: 1, lng: 2 } }));
			const destination = h.window.kiriofBuyerCheckout.getDestination(); expect(destination.destination_latitude).toBe('1.0000000');
			await h.editing(false); expect(h.maps[0].removed).toBe(1); expect(h.document.querySelector('.kiriof-buyer-map')).toBeNull(); expect(h.document.querySelector('.kiriof-buyer-district')).toBeNull(); expect(h.window.kiriofBuyerCheckout.getDestination()).toEqual(destination); expect(h.window.kiriofBuyerCheckout.getCoordinates(h.model.cart.shippingAddress).longitude).toBe('2.0000000'); expect(h.window.kiriofBuyerCheckout.active).toBe(true);
			await h.editing(true); expect(h.maps).toHaveLength(2); expect(h.maps[1].center).toEqual({ lat: 1, lng: 2 });
		} finally { await h.cleanup(); }
	});
	uiTest('saved pin must match entire address, not just postcode', async () => {
		const h = await fixture({ savedDestination: saved({ shipping_address: { ...address, address_1: 'Other Road' } }) });
		try { await h.flush(250); await h.reply(); expect(h.badges().map(node => node.textContent)).toEqual(['Need pin location']); expect(h.badges()[0].classList.contains('is-warning')).toBe(true); expect(h.window.kiriofBuyerCheckout.getCoordinates(h.model.cart.shippingAddress)).toBeNull(); } finally { await h.cleanup(); }
	});
	uiTest('programmatic address change while collapsed clears pin and green badge without mounting map', async () => {
		const h = await fixture({ savedDestination: saved() });
		try { await h.flush(250); await h.reply(); h.model.cart = { ...h.model.cart, shippingAddress: { ...address, address_2: 'Unit 2' } }; await h.notify(); expect(h.window.kiriofBuyerCheckout.getCoordinates(h.model.cart.shippingAddress)).toBeNull(); expect(h.window.kiriofBuyerCheckout.getDestination().version).toBe(1); expect(h.window.kiriofBuyerCheckout.getDestination().destination_latitude).toBeUndefined(); expect(h.badges().map(node => node.textContent)).toEqual(['Need pin location']); expect(h.document.querySelector('.is-complete')).toBeNull(); expect(h.maps).toHaveLength(0); } finally { await h.cleanup(); }
	});
	uiTest('native entire-card remount repairs exactly one portal without duplicate owners or lookup', async () => {
		const h = await fixture({ savedDestination: saved() });
		try { await h.flush(250); await h.reply(); const oldCard = h.card(); const replacement = oldCard.cloneNode(true); replacement.querySelector('.kiriof-address-status-host').remove(); await h.mutation(() => oldCard.replaceWith(replacement)); expect(oldCard.querySelector('.kiriof-address-status-host')).toBeNull(); expect(h.document.querySelectorAll('.kiriof-address-status-host')).toHaveLength(1); expect(h.document.querySelectorAll('.kiriof-address-status')).toHaveLength(1); expect(replacement.querySelector('.kiriof-address-status').textContent).toBe('Pin location'); expect(h.lookups).toHaveLength(1); expect(h.maps).toHaveLength(0); } finally { await h.cleanup(); }
	});
	uiTest('guest without a saved native card defaults to visible editing controls and map', async () => {
		const h = await fixture({ guest: true });
		try {
			const badge = h.document.querySelector('.kiriof-buyer-map__pin-status');
			expect(badge.textContent).toBe('Pin location');
			expect(badge.classList.contains('is-complete')).toBe(true);
			expect(h.document.querySelector('.kiriof-buyer-map__device-notice')).toBeNull();
			expect(h.document.querySelector('.kiriof-buyer-map__status')).toBeNull();
			const locate = h.document.querySelector('.kiriof-buyer-map__locate');
			expect(locate.textContent).toBe('');
			expect(locate.getAttribute('aria-label')).toBe('Locate');
			expect(locate.getAttribute('title')).toBe('Locate');
			expect(h.document.querySelector('.kiriof-buyer-district')).not.toBeNull(); expect(h.document.querySelector('.kiriof-buyer-map')).not.toBeNull(); expect(h.document.querySelector('.kiriof-address-status-host')).toBeNull(); expect(h.maps).toHaveLength(1); expect(h.document.querySelector('.kiriof-buyer-map__coverage')).toBeNull(); expect(h.document.querySelector('.kiriof-buyer-map__coverage-warning')).toBeNull(); await h.flush(250); await h.reply(); expect(h.document.querySelector('select').options[1].textContent).toBe('District Seven'); } finally { await h.cleanup(); }
	});
});
