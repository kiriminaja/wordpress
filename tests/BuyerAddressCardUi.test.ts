import { describe, expect, test } from 'bun:test';
import { existsSync, readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { homedir } from 'node:os';
import { runInNewContext } from 'node:vm';

// These optional runtimes are also used by MapCheckout.test.ts. No downloads,
// test-only production exports, live Woo API, or theme behavior are involved.
const require = createRequire(import.meta.url);
function optional(name: string, candidates: string[]) {
	try { return require(name); } catch {}
	for (const candidate of candidates) {
		const path = `${homedir()}/${candidate}/node_modules/${name}`;
		if (existsSync(`${path}/package.json`)) { try { return require(path); } catch {} }
	}
	return null;
}
const React = optional('react', ['Kerjaa/portfolio', 'Kerjaa/kaj-shopify-plugin']);
const happy = optional('happy-dom', ['Kerjaa/kaj-shopify-plugin-cart']);
let rendererRequire: any;
for (const scope of [require, ...['Kerjaa/portfolio', 'Kerjaa/kaj-shopify-plugin'].map(path => createRequire(`${homedir()}/${path}/package.json`))]) {
	try { if (scope('react') === React) { scope.resolve('react-dom/client'); rendererRequire = scope; break; } } catch {}
}
const uiTest = React && happy && rendererRequire ? test : test.skip;
const scripts = ['kiriof-address-presentation', 'kiriof-checkout-session', 'kiriof-buyer-checkout', 'kiriof-map-checkout'].map(name => readFileSync(new URL(`../assets/wp/js/${name}.js`, import.meta.url), 'utf8'));
const address = { address_1: 'Main Road', address_2: '', city: 'Jakarta', state: 'JK', postcode: '12345', country: 'ID' };
function saved(overrides: any = {}) {
	return { version: 2, district_id: '7', district_label: 'Old label', postcode: '12345', country: 'ID', destination_latitude: '0', destination_longitude: 0, shipping_address: { ...address }, ...overrides };
}
function deferred() { let resolve!: (value: any) => void; const promise = new Promise<any>(yes => { resolve = yes; }); return { promise, resolve }; }
function events() {
	const handlers = new Map<string, any>();
	return { on(name: string, callback: any) { handlers.set(name, callback); return this; }, off() { handlers.clear(); return this; }, fire(name: string, event?: any) { handlers.get(name)?.(event); } };
}
async function fixture(options: { editing?: boolean; guest?: boolean; savedDestination?: any; mapFirst?: boolean } = {}) {
	const window = new happy.Window();
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
	const model = { cart: { needsShipping: true, shippingAddress: { ...address }, shippingRates: [{ shipping_rates: [{ method_id: 'kiriminaja-official', selected: true }] }] }, payment: 'cod', busy: false, collection: false };
	const subscribers = new Set<any>(), publications: any[] = [], validations: any[] = [], sends: any[] = [], lookups: any[] = [], maps: any[] = [], registrations: any[] = [];
	const timers = new Map<number, { callback: any; delay: number }>(); let timerId = 0;
	const stores: any = {
		'wc/store/cart': { getCartData: () => model.cart, isShippingRateBeingSelected: () => model.busy, isCustomerDataUpdating: () => false, hasPendingItemsOperations: () => false },
		'wc/store/payment': { getActivePaymentMethod: () => model.payment },
		'wc/store/checkout': { prefersCollection: () => model.collection },
	};
	const select = (name: string) => stores[name];
	window.wp = { element: { ...React, createPortal }, components: { ComboboxControl: (props: any) => React.createElement('label', null, props.label, React.createElement('select', { 'aria-label': props.label, value: props.value || '', disabled: props.disabled, onChange: (event: any) => props.onChange(event.target.value) }, React.createElement('option', { value: '' }, props.placeholder), ...props.options.map((row: any) => React.createElement('option', { key: row.value, value: row.value }, row.label)))) }, data: {
		select,
		useSelect(callback: any) { const [, update] = React.useState(0); React.useEffect(() => { const listener = () => update((value: number) => value + 1); subscribers.add(listener); return () => subscribers.delete(listener); }, []); return callback(select); },
		dispatch: (name: string) => name === 'wc/store/checkout' ? { setExtensionData: (...args: any[]) => publications.push(args) } : { setValidationErrors: (errors: any) => validations.push(errors), clearValidationError: (id: string) => validations.push({ clear: id }) },
		subscribe(callback: any) { subscribers.add(callback); return () => subscribers.delete(callback); },
	} };
	window.wc = { blocksCheckout: { registerCheckoutBlock: (registration: any) => registrations.push(registration), extensionCartUpdate(request: any) { sends.push(request); return Promise.resolve({}); } } };
	const strings = { district: 'District', districtRequired: 'District required', checkingDistrict: 'Checking district', districtNotSet: 'District not set', pinLocation: 'Pin location', needPinLocation: 'Need pin location', pinRequirement: 'Pin required for instant', loading: 'Loading', saving: 'Saving', selectDistrict: 'Select district', empty: 'Empty', mapTitle: 'Delivery pin', mapHelp: 'Move map', mapPlaced: 'Pin placed', mapLocate: 'Locate', mapClear: 'Clear', mapOptional: 'Optional' };
	window.kiriofBuyerCheckoutConfig = { enabled: true, nonce: 'fixture', ajaxUrl: '/fixture-ajax', savedDestination: options.savedDestination, i18n: strings };
	window.kiriofMapCheckoutConfig = { enabled: true, tiles: 'https://tiles.example.test/{z}/{x}/{y}.png', i18n: strings };
	window.fetch = (url: any, init: any) => { const task = deferred(); lookups.push({ url, init, ...task }); return task.promise; };
	window.setTimeout = (callback: any, delay: number) => { timers.set(++timerId, { callback, delay }); return timerId; };
	window.clearTimeout = (id: number) => { timers.delete(id); };
	window.L = {
		map(node: any) { expect(node instanceof window.HTMLElement).toBe(true); const map = Object.assign(events(), { node, removed: 0, center: { lat: 0, lng: 0 }, setView(point: any) { this.center = { lat: point[0], lng: point[1] }; this.fire('movestart'); this.fire('moveend'); return this; }, getCenter() { return this.center; }, invalidateSize() {}, remove() { this.removed++; this.off(); } }); maps.push(map); return map; },
		tileLayer() { return Object.assign(events(), { addTo() { return this; } }); },
	};
	const context = { window, document, AbortController, URLSearchParams, setTimeout: window.setTimeout.bind(window), clearTimeout: window.clearTimeout.bind(window) };
	for (const source of scripts) runInNewContext(source, context);
	expect(registrations.map(row => row.metadata.name)).toEqual(['kiriminaja-official/checkout-district', 'kiriminaja-official/map-checkout']);
	const roots = registrations.map((registration, index) => { const root = createRoot(document.getElementById(index ? 'map-mount' : 'district-mount')); return { root, component: registration.component }; });
	await act(async () => { for (const item of options.mapFirst ? [...roots].reverse() : roots) item.root.render(React.createElement(item.component)); });
	// One event-loop turn lets the real MutationObserver deliver its native
	// mutation batch. We never invoke bridge.refresh or poll bridge timers.
	async function mutation(callback: () => void) { await act(async () => { callback(); await new Promise(resolve => setTimeout(resolve, 0)); }); }
	async function flush(delay: number) { await act(async () => { for (const [id, timer] of [...timers]) if (timer.delay === delay && timers.delete(id)) timer.callback(); }); }
	async function reply(rows = [{ id: 7, text: 'District Seven' }]) { await act(async () => { lookups.at(-1).resolve({ ok: true, json: () => Promise.resolve({ success: true, data: rows }) }); }); }
	async function notify() { await act(async () => { for (const callback of subscribers) callback(); }); }
	async function editing(value: boolean) { await mutation(() => { const wrapper = document.querySelector('#shipping-fields .wc-block-components-address-address-wrapper'); wrapper.classList.toggle('is-editing', value); wrapper.querySelector('button').setAttribute('aria-expanded', String(value)); }); }
	async function cleanup() { await act(async () => { for (const { root } of roots) root.unmount(); }); expect(document.querySelector('.kiriof-address-status-host')).toBeNull(); expect(maps.every(map => map.removed === 1)).toBe(true); window.dispatchEvent(new window.Event('pagehide')); expect(subscribers.size).toBe(0); window.happyDOM.abort(); for (const [key, descriptor] of previous) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete (globalThis as any)[key]; } }
	return { window, document, model, maps, publications, validations, sends, lookups, timers, act, mutation, editing, notify, flush, reply, cleanup,
		card: () => document.querySelector('#shipping-fields .wc-block-components-address-card'),
		badges: () => [...document.querySelectorAll('#shipping-fields .kiriof-address-status__badge')],
	};
}

describe('combined native address-card UI (real React/DOM, unchanged production VM)', () => {
	uiTest('collapsed card does not briefly create a map when Map mounts before District', async () => {
		const h = await fixture({ mapFirst: true });
		try { expect(h.maps).toHaveLength(0); expect(h.document.querySelector('.kiriof-buyer-map')).toBeNull(); expect(h.badges().length).toBeGreaterThan(0); } finally { await h.cleanup(); }
	});
	uiTest('closed native card initializes district validation/publication without controls or map; pending is not green, missing district and pin warn after lookup', async () => {
		const h = await fixture();
		try {
			expect(h.document.querySelector('.kiriof-buyer-district')).toBeNull(); expect(h.document.querySelector('.kiriof-buyer-map')).toBeNull(); expect(h.maps).toHaveLength(0);
			expect(h.badges().map(node => node.textContent)).toEqual(['Checking district', 'Need pin location']);
			for (const badge of h.badges()) { expect(h.card().contains(badge)).toBe(true); expect(badge.classList.contains('is-complete')).toBe(false); }
			expect(h.document.querySelector('#billing-fields .kiriof-address-status-host')).toBeNull();
			expect(h.publications.length).toBeGreaterThan(0); expect(h.validations.some(value => value['kiriof-buyer-destination']?.message === 'District required')).toBe(true);
			await h.flush(250); expect(h.lookups).toHaveLength(1); expect(new URLSearchParams(h.lookups[0].init.body).get('term')).toBe('12345'); await h.reply(); await h.flush(0);
			expect(h.badges().map(node => node.textContent)).toEqual(['District not set', 'Need pin location']); expect(h.badges().every(node => node.classList.contains('is-warning'))).toBe(true);
			expect(h.sends.at(-1).data.destination.district_id).toBe(''); expect(h.window.kiriofBuyerCheckout.active).toBe(true);
		} finally { await h.cleanup(); }
	});
	uiTest('matching saved zero pin is green, edit opens native controls/map automatically, close disposes only map and retains destination', async () => {
		const h = await fixture({ savedDestination: saved() });
		try {
			expect(h.badges().find(node => node.textContent === 'Pin location')?.classList.contains('is-complete')).toBe(true);
			await h.flush(250); await h.reply(); await h.flush(0);
			expect(h.badges().map(node => node.textContent)).toEqual(['Pin location']);
			expect(h.window.kiriofBuyerCheckout.getDestination().district_label).toBe('District Seven');
			await h.editing(true); expect(h.document.querySelector('.kiriof-address-status')).toBeNull(); expect(h.document.querySelector('select[aria-label="District"]')).not.toBeNull(); expect(h.document.querySelector('.kiriof-buyer-map')).not.toBeNull(); expect(h.maps).toHaveLength(1); expect(h.maps[0].center).toEqual({ lat: 0, lng: 0 });
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
		try { expect(h.document.querySelector('.kiriof-buyer-district')).not.toBeNull(); expect(h.document.querySelector('.kiriof-buyer-map')).not.toBeNull(); expect(h.document.querySelector('.kiriof-address-status-host')).toBeNull(); expect(h.maps).toHaveLength(1); await h.flush(250); await h.reply(); expect(h.document.querySelector('select').options[1].textContent).toBe('District Seven'); } finally { await h.cleanup(); }
	});
});
