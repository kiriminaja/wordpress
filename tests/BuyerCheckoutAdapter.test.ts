import { describe, expect, test } from 'bun:test';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const sessionSource = readFileSync(new URL('../assets/wp/js/kiriof-checkout-session.js', import.meta.url), 'utf8');
const adapterSource = readFileSync(new URL('../assets/wp/js/kiriof-buyer-checkout.js', import.meta.url), 'utf8');
function deferred() {
	let resolve!: (value?: any) => void;
	let reject!: (reason: any) => void;
	const promise = new Promise<any>((yes, no) => { resolve = yes; reject = no; });
	return { promise, resolve, reject };
}
type Node = { type: any; props: any; children: any[] };

// A small commit-phase hook runner: effects run after render, changed effects clean
// up first, setters are stable, and updates during effects cause another render.
// Both production scripts execute unchanged in the same browser-like VM.
function harness(options: { block?: boolean; enabled?: boolean; slot?: string; inner?: boolean; missing?: string; config?: any; collapsed?: boolean; restore?: boolean } = {}) {
	const timers = new Map<number, { delay: number; callback: () => void }>();
	let nextTimer = 0;
	const setTimeout = (callback: () => void, delay = 0) => { timers.set(++nextTimer, { callback, delay }); return nextTimer; };
	const clearTimeout = (id: number) => { timers.delete(id); };
	const lookups: any[] = [], sends: any[] = [], publications: any[] = [], validations: any[] = [], classes: string[] = [], queries: string[] = [];
	const subscribers = new Set<() => void>();
	const events: Record<string, { callback: (event?: any) => void; once: boolean }> = {};
	const documentEvents = new Map<string, Set<(event: any) => void>>();
	const model = {
		cart: { needsShipping: true, shippingAddress: { postcode: '12345', country: 'ID' } as any,
			extensions: {} as any, shippingRates: [{ package_id: 0, shipping_rates: [{ rate_id: 'kiriminaja:jne', method_id: 'kiriminaja-official', selected: true }] }] },
		payment: 'cod', rateBusy: false, customerBusy: false, pendingItems: false, collection: false,
	};
	const restores: any[] = [];
	const nativeDispatch = { selectShippingRate: (rate: string, packageId: string) => { const task = deferred(); restores.push({ rate, packageId, ...task }); return task.promise; } };
	const forbidden = () => { throw new Error('Adapter must not call native customer/rate or legacy DOM methods'); };
	const cartStore: any = {
		getCartData: () => model.cart,
		isShippingRateBeingSelected: () => model.rateBusy,
		isCustomerDataUpdating: () => model.customerBusy,
		hasPendingItemsOperations: () => model.pendingItems,
		selectShippingRate: forbidden, updateCustomerData: forbidden,
	};
	const checkoutStore = { prefersCollection: () => model.collection };
	const paymentStore: any = { getActivePaymentMethod: () => model.payment };
	const checkoutDispatch: any = { setExtensionData: (...args: any[]) => publications.push(args), selectShippingRate: forbidden, updateCustomerData: forbidden };
	const validationDispatch: any = {
		setValidationErrors: (errors: any) => validations.push(errors),
		clearValidationError: (id: string) => validations.push({ clear: id }),
	};
	const select = (name: string) => ({ 'wc/store/cart': cartStore, 'wc/store/checkout': checkoutStore, 'wc/store/payment': paymentStore } as any)[name];
	let plugin: any, pluginName = '', cursor = 0;
	const registeredBlocks: any[] = [];
	type Instance = { component: any; props: any; hooks: any[]; tree: Node | null; dirty: boolean; mounted: boolean };
	const instances: Instance[] = [];
	let current: Instance;
	let effects: (() => void)[] = [];
	const element = {
		createPortal: (node: Node, target: any) => ({ type: 'portal', props: { target }, children: [node] }),
		createElement: (type: any, props: any, ...children: any[]): Node => ({ type, props: props || {}, children }),
		useState(initial: any) {
			const index = cursor++;
			if (!current.hooks[index]) {
				const instance = current;
				const hook: any = { value: initial };
				hook.set = (next: any) => { const value = typeof next === 'function' ? next(hook.value) : next; if (!Object.is(value, hook.value)) { hook.value = value; instance.dirty = true; } };
				current.hooks[index] = hook;
			}
			return [current.hooks[index].value, current.hooks[index].set];
		},
		useRef(initial: any) { const index = cursor++; if (!current.hooks[index]) current.hooks[index] = { current: initial }; return current.hooks[index]; },
		useEffect(callback: () => any, deps: any[]) {
			const instance = current;
			const index = cursor++, previous = instance.hooks[index];
			if (!previous || deps.some((value, i) => !Object.is(value, previous.deps[i]))) {
				current.hooks[index] = { deps, cleanup: previous?.cleanup };
				effects.push(() => { instance.hooks[index].cleanup?.(); instance.hooks[index].cleanup = callback(); });
			}
		},
	};
	const wp: any = {
		element, components: {},
		plugins: { registerPlugin: (name: string, value: any) => { pluginName = name; plugin = value; } },
		data: { select, useSelect: (callback: any) => callback(select),
			dispatch: (name: string) => name === 'wc/store/checkout' ? checkoutDispatch : name === 'wc/store/cart' && options.restore ? nativeDispatch : validationDispatch,
			subscribe: (callback: () => void) => { subscribers.add(callback); return () => subscribers.delete(callback); } },
	};
	const blocks: any = {
		[options.slot || 'OrderMeta']: 'OrderMetaSlot',
		registerCheckoutBlock: options.inner === false ? undefined : (registration: any) => { registeredBlocks.push(registration); },
		extensionCartUpdate: (request: any) => { const task = deferred(); sends.push({ request, ...task }); return task.promise; },
	};
	const strings = { district: 'Subdistrict', districtRequired: 'Subdistrict required', postcodeRequired: 'Postcode required', loading: 'Loading', lookupFailed: 'Lookup failed', lookupTimeout: 'Lookup timed out', saveStalled: 'Save stalled', reloadCheckout: 'Reload checkout', quoteRefreshFailed: 'Quote refresh failed', instantUnavailable: 'Instant unavailable', empty: 'Empty', emptyRetry: 'No subdistricts found. Retry lookup.', saving: 'Saving', updateFailed: 'Update failed', retry: 'Retry', selectDistrict: 'Select subdistrict', mapTitle: 'Delivery pin', mapHelp: 'Tap map', mapPlaced: 'Pin placed', mapLocate: 'Locate me', mapUnavailable: 'No map' };
	const root: any = {
		kiriofAddressPresentation: options.collapsed ? { usePresentation: () => ({ editing: false, cardTarget: {} }) } : undefined,
		wp, wc: { blocksCheckout: blocks }, setTimeout, clearTimeout, location: { reload: () => { root.reloaded = true; } },
		kiriofBuyerCheckoutConfig: { enabled: options.enabled !== false, nonce: 'nonce', ajaxUrl: '/ajax', globalInsurance: true, map: { enabled: true }, i18n: strings, ...options.config },
		fetch: (url: string, init: any) => { const task = deferred(); lookups.push({ url, init, ...task }); return task.promise; },
		addEventListener: (name: string, callback: () => void, init: any) => { events[name] = { callback, once: !!init?.once }; },
	};
	const document = {
		addEventListener(name: string, callback: (event: any) => void) { if (!documentEvents.has(name)) documentEvents.set(name, new Set()); documentEvents.get(name)!.add(callback); },
		removeEventListener(name: string, callback: (event: any) => void) { documentEvents.get(name)?.delete(callback); },
		querySelector: (selector: string) => { queries.push(selector); return options.block === false ? null : {}; },
		documentElement: { classList: { add: (name: string) => { if (!classes.includes(name)) classes.push(name); }, remove: (name: string) => { const index = classes.indexOf(name); if (index >= 0) classes.splice(index, 1); } } },
		querySelectorAll: forbidden, getElementById: forbidden, createEvent: forbidden,
	};
	const context = { window: root, document, setTimeout, clearTimeout, AbortController, URLSearchParams,
		HTMLInputElement: new Proxy({}, { get: forbidden }), HTMLSelectElement: new Proxy({}, { get: forbidden }), jQuery: forbidden };
	runInNewContext(sessionSource, context);
	runInNewContext(readFileSync(new URL('../assets/wp/js/kiriof-shipping-selection.js', import.meta.url), 'utf8'), context);
	if (options.missing === 'session') delete root.kiriofBuyerCheckoutSession;
	if (options.missing === 'slot') delete blocks[options.slot || 'OrderMeta'];
	if (options.missing === 'inner') delete blocks.registerCheckoutBlock;
	if (options.missing === 'plugins') delete wp.plugins;
	if (options.missing === 'update') delete blocks.extensionCartUpdate;
	if (options.missing === 'useSelect') delete wp.data.useSelect;
	if (options.missing === 'validation') delete validationDispatch.clearValidationError;
	if (options.missing === 'nativeRateStatus') delete cartStore.isShippingRateBeingSelected;
	if (options.missing === 'nativeCustomerStatus') delete cartStore.isCustomerDataUpdating;
	if (options.missing === 'payment') delete paymentStore.getActivePaymentMethod;
	runInNewContext(adapterSource, context);
	function render() {
		for (const instance of instances) if (instance.mounted) instance.dirty = true;
		let count = 0;
		while (instances.some(instance => instance.mounted && instance.dirty)) {
			if (++count > 50) throw new Error('Hook render failed to converge');
			for (const instance of instances) {
				if (!instance.mounted || !instance.dirty) continue;
				current = instance; instance.dirty = false; cursor = 0; effects = []; instance.tree = instance.component(instance.props);
				for (const effect of effects) effect();
			}
		}
	}
	function mount() {
		if (!plugin) throw new Error('Plugin was not registered');
		const slot = plugin.render();
		expect(slot.type).toBe('OrderMetaSlot');
		const child = slot.children[0];
		instances.push({ component: child.type, props: child.props, hooks: [], tree: null, dirty: true, mounted: true }); render();
		return instances.length - 1;
	}
	function mountRegisteredBlock(index = 0) {
		const registration = registeredBlocks[index];
		expect(typeof registration?.component).toBe('function');
		const rendered = registration.component();
		instances.push({ component: rendered.type, props: rendered.props, hooks: [], tree: null, dirty: true, mounted: true });
		render();
		return instances.length - 1;
	}
	function find(type: string, node: any = instances.find(instance => instance.mounted)?.tree): any {
		if (!node || typeof node !== 'object') return undefined;
		if (node.type === type) return node;
		for (const child of node.children || []) { if (child === undefined) continue; const match = find(type, child); if (match) return match; }
	}
	function findIn(index: number, type: string) { return find(type, instances[index]?.tree); }
	async function settle() {
		// Drain response.json(), lookup, queue completion, and their hook updates.
		for (let i = 0; i < 12; i++) await Promise.resolve();
		if (instances.some(instance => instance.mounted && instance.dirty)) render();
	}
	async function flush(delay: number) {
		const ready = [...timers.entries()].filter(([, timer]) => timer.delay === delay);
		for (const [id, timer] of ready) { if (timers.delete(id)) timer.callback(); }
		await settle();
	}
	async function reply(index = 0, rows: any[] = [{ id: 7, text: 'District Seven' }]) {
		lookups[index].resolve({ ok: true, json: () => Promise.resolve({ success: true, data: rows }) }); await settle();
	}
	function notify() { for (const callback of subscribers) callback(); render(); }
	function pagehide(persisted = false) { const event = events.pagehide; event?.callback({ persisted }); if (event?.once) delete events.pagehide; }
	function unmount(index = 0) { const instance = instances[index]; instance.mounted = false; for (const hook of instance.hooks) hook?.cleanup?.(); render(); }
	return { root, model, timers, lookups, sends, publications, validations, classes, queries, subscribers, documentEvents, restores,
		billingChange: () => { for (const callback of documentEvents.get('change') || []) callback({ type: 'change', target: { tagName: 'INPUT', type: 'checkbox', checked: true, value: 'on' } }); render(); },
		shippingChange: (value: string) => { for (const callback of documentEvents.get('change') || []) callback({ type: 'change', target: { tagName: 'INPUT', type: 'radio', checked: true, value } }); render(); },
		plugin: () => plugin, pluginName: () => pluginName, registeredBlocks: () => registeredBlocks,
		mount, mountRegisteredBlock, render, settle, flush, reply, notify, pagehide, unmount, find, findIn,
		choose: (id: string | null) => { find('select').props.onChange({ target: { value: id || '' } }); render(); },
		retry: () => { find('button').props.onClick(); render(); },
		status: () => find('p')?.children[0],
	};
}

function reviewed(rate_id: string, package_id = '0') { return { version: 1, packages: [{ package_id, rate_id }] }; }
function latestValidation(h: ReturnType<typeof harness>, id: string) { return h.validations.filter(value => value[id] || value.clear === id).at(-1); }

function options(node: any): {value: string; label: string}[] { return node.children.flat(Infinity).filter((child: any) => child?.type === 'option' && child.props.value).map((child: any) => ({value: child.props.value, label: child.children[0]})); }

async function ready(h: ReturnType<typeof harness>) { h.mount(); await h.flush(250); await h.reply(); }

describe('buyer checkout Blocks adapter (unchanged production VM)', () => {
	for (const busy of ['customerBusy', 'rateBusy'] as const) {
		test(`matching reviewed GoSend clears conflict during ${busy} billing toggle and keeps only hidden pending validation`, async () => {
			const h = harness({ restore: true }); h.mount();
			const go = 'kiriminaja-instant:7:gosend:instant';
			h.model.cart.shippingRates = [{ package_id: 0, shipping_rates: [{ rate_id: go, method_id: 'kiriminaja-instant', selected: true }] }];
			h.shippingChange(go);
			const sends = h.sends.length, lookups = h.lookups.length;
			const original = JSON.stringify(h.publications.at(-1)[1].shipping_selection);
			for (let attempt = 0; attempt < 3; attempt++) {
				h.validations.push({ terms: { message: `Please accept terms ${attempt}`, hidden: false }, payment: { message: 'Payment error', hidden: false } });
				h.model[busy] = true; h.billingChange(); h.notify(); await h.settle();
				expect(latestValidation(h, 'kiriof-shipping-selection')).toEqual({ clear: 'kiriof-shipping-selection' });
				expect(latestValidation(h, 'kiriof-shipping-selection-pending')).toEqual({ 'kiriof-shipping-selection-pending': { message: 'Updating shipping options…', hidden: true } });
				expect(JSON.stringify(h.publications.at(-1)[1].shipping_selection)).toBe(original);
				h.model[busy] = false; h.notify(); await h.settle();
				expect(latestValidation(h, 'kiriof-shipping-selection-pending')).toEqual({ clear: 'kiriof-shipping-selection-pending' });
				expect(latestValidation(h, 'kiriof-shipping-selection')).toEqual({ clear: 'kiriof-shipping-selection' });
				expect(latestValidation(h, 'terms').terms.message).toBe(`Please accept terms ${attempt}`);
				expect(latestValidation(h, 'payment').payment).toEqual({ message: 'Payment error', hidden: false });
				expect(JSON.stringify(h.publications.at(-1)[1].shipping_selection)).toBe(original);
			}
			expect(h.restores).toHaveLength(0); expect(h.sends).toHaveLength(sends); expect(h.lookups).toHaveLength(lookups);
			expect(h.validations.filter(value => value.clear && !value.clear.startsWith('kiriof-'))).toEqual([]);
		});
	}
	test('existing explicit GoSend review reconciles even while saved-pin initialization awaits a busy customer', async () => {
		const h = harness({ restore: true, config: { savedDestination: { version: 2, district_id: '7', postcode: '12345', country: 'ID', destination_latitude: '0', destination_longitude: '0', shipping_address: { postcode: '12345', country: 'ID' } } } });
		const go = 'kiriminaja-instant:7:gosend:instant';
		h.model.customerBusy = true;
		h.model.cart.shippingRates = [{ package_id: 0, shipping_rates: [{ rate_id: go, method_id: 'kiriminaja-instant', selected: true }] }];
		h.mount(); h.shippingChange(go); h.billingChange(); h.notify(); await h.settle();
		expect(h.publications.at(-1)[1].shipping_selection).toEqual(reviewed(go));
		expect(latestValidation(h, 'kiriof-shipping-selection')).toEqual({ clear: 'kiriof-shipping-selection' });
		expect(latestValidation(h, 'kiriof-shipping-selection-pending')).toEqual({ 'kiriof-shipping-selection-pending': { message: 'Updating shipping options…', hidden: true } });
		h.model.customerBusy = false; h.notify(); await h.settle();
		expect(latestValidation(h, 'kiriof-shipping-selection-pending')).toEqual({ clear: 'kiriof-shipping-selection-pending' });
		expect(h.publications.at(-1)[1].shipping_selection).toEqual(reviewed(go)); expect(h.restores).toHaveLength(0);
	});
	test('genuine Cargo snapshot mismatch stays visible while customer is busy and after settling without replacing reviewed GoSend', async () => {
		const h = harness({ restore: true }); h.mount();
		const go = 'kiriminaja-instant:7:gosend:instant', cargo = 'kiriminaja:jne:cargo';
		h.model.cart.shippingRates = [{ package_id: 0, shipping_rates: [{ rate_id: go, method_id: 'kiriminaja-instant', selected: true }] }]; h.shippingChange(go);
		h.model.customerBusy = true;
		h.model.cart.shippingRates = [{ package_id: 0, shipping_rates: [{ rate_id: cargo, method_id: 'kiriminaja-official', selected: true }] }]; h.billingChange(); h.notify(); await h.settle();
		expect(latestValidation(h, 'kiriof-shipping-selection')['kiriof-shipping-selection']).toEqual({ message: 'Shipping options changed. Please review and select your courier again before placing the order.', hidden: false });
		expect(latestValidation(h, 'kiriof-shipping-selection-pending')['kiriof-shipping-selection-pending']).toEqual({ message: 'Updating shipping options…', hidden: true });
		h.model.customerBusy = false; h.notify(); await h.settle();
		expect(latestValidation(h, 'kiriof-shipping-selection')['kiriof-shipping-selection'].hidden).toBe(false);
		expect(latestValidation(h, 'kiriof-shipping-selection-pending')).toEqual({ clear: 'kiriof-shipping-selection-pending' });
		expect(h.publications.at(-1)[1].shipping_selection).toEqual(reviewed(go)); expect(h.restores).toHaveLength(0); expect(h.sends).toHaveLength(0);
	});
	test('billing checkbox cannot review automatic Express; exact available GoSend restores after customer busy settles', async () => {
		const h = harness({ restore: true }); h.mount();
		const go = 'kiriminaja-instant:7:gosend:instant', express = 'kiriminaja:jne';
		const rates = (selected: string) => [{ package_id: 0, shipping_rates: [
			{ rate_id: express, method_id: 'kiriminaja-official', selected: selected === express },
			{ rate_id: go, method_id: 'kiriminaja-instant', selected: selected === go },
		] }];
		h.model.cart.shippingRates = rates(go); h.shippingChange(go);
		const sends = h.sends.length;
		h.model.customerBusy = true; h.model.cart.shippingRates = rates(express); h.billingChange(); h.notify(); await h.settle();
		expect(h.restores).toHaveLength(0);
		expect(h.publications.at(-1)[1].shipping_selection).toEqual(reviewed(go));
		expect(latestValidation(h, 'kiriof-shipping-selection')['kiriof-shipping-selection']).toBeDefined();
		h.model.customerBusy = false; h.notify(); await h.settle();
		expect(h.restores.map(({ rate, packageId }) => [rate, packageId])).toEqual([[go, '0']]);
		h.model.rateBusy = true; h.notify(); h.model.pendingItems = true; h.notify(); h.model.pendingItems = false; h.notify();
		expect(h.restores).toHaveLength(1); expect(h.sends).toHaveLength(sends);
		h.model.cart.shippingRates = rates(go); h.model.rateBusy = false; h.restores[0].resolve(); await h.settle(); h.notify();
		expect(latestValidation(h, 'kiriof-shipping-selection')).toEqual({ clear: 'kiriof-shipping-selection' });
		expect(h.publications.at(-1)[1].shipping_selection).toEqual(reviewed(go)); expect(h.restores).toHaveLength(1);
	});
	test('failed exact restoration is blocked once, busy-only transitions do not loop or mutate plugin pricing', async () => {
		const h = harness({ restore: true }); h.mount();
		const go = 'kiriminaja-instant:7:gosend:instant';
		const rates = [{ rate_id: go, method_id: 'kiriminaja-instant', selected: true }, { rate_id: 'kiriminaja:jne', method_id: 'kiriminaja-official', selected: false }];
		h.model.cart.shippingRates = [{ package_id: 0, shipping_rates: rates }]; h.shippingChange(go);
		rates[0].selected = false; rates[1].selected = true; h.notify(); await h.settle();
		expect(h.restores).toHaveLength(1); h.restores[0].reject(new Error('native failure')); await h.settle();
		for (let i = 0; i < 3; i++) { h.model.customerBusy = true; h.notify(); h.model.customerBusy = false; h.notify(); await h.settle(); }
		expect(h.restores).toHaveLength(1); expect(h.sends).toHaveLength(0);
		expect(h.publications.at(-1)[1].shipping_selection).toEqual(reviewed(go));
		expect(latestValidation(h, 'kiriof-shipping-selection')['kiriof-shipping-selection']).toBeDefined();
	});
	test('COD removes reviewed quote: no nonexistent rate restoration, only explicit Cargo re-review clears guard', async () => {
		const h = harness({ restore: true }); h.mount();
		const go = 'kiriminaja-instant:7:gosend:instant';
		h.model.cart.shippingRates = [{ package_id: 0, shipping_rates: [{ rate_id: go, method_id: 'kiriminaja-instant', selected: true }] }]; h.shippingChange(go);
		h.model.cart.shippingRates = [{ package_id: 0, shipping_rates: [{ rate_id: 'kiriminaja:jne', method_id: 'kiriminaja-official', selected: true }] }]; h.billingChange(); h.notify(); await h.settle();
		expect(h.restores).toHaveLength(0); expect(h.publications.at(-1)[1].shipping_selection).toEqual(reviewed(go));
		expect(latestValidation(h, 'kiriof-shipping-selection')['kiriof-shipping-selection']).toBeDefined();
		h.shippingChange('kiriminaja:jne'); expect(h.publications.at(-1)[1].shipping_selection).toEqual(reviewed('kiriminaja:jne'));
		expect(latestValidation(h, 'kiriof-shipping-selection')).toEqual({ clear: 'kiriof-shipping-selection' }); expect(h.sends).toHaveLength(0);
	});
	test('explicit native GoSend choice survives terms failure, automatic Cargo fallback conflicts until explicitly chosen', async () => {
		const h = harness({ config: { districtPostcode: '12345', district: { id: 7 } } });
		await ready(h); await h.flush(0); h.sends[0].resolve(); await h.settle();
		const oldInstant = 'kiriminaja-instant:7:gosend:instant';
		const cargo = 'kiriminaja:jne:cargo';
		const selected = (rate_id: string, method_id: string) => [{ package_id: 0, shipping_rates: [{ rate_id, method_id, selected: true }] }];
		h.model.cart.shippingRates = selected(oldInstant, 'kiriminaja-instant');
		h.model.cart.extensions['kiriminaja-official-instant-checkout'] = { eligible: true, code: 'available', expires_at: 0 };
		// The native store is updated before its captured INPUT radio change.
		h.shippingChange(oldInstant);
		expect(h.documentEvents.get('change')?.size).toBe(1);
		expect(h.publications.at(-1)).toEqual(['kiriminaja-official', { destination: h.root.kiriofBuyerCheckout.getDestination(), shipping_selection: reviewed(oldInstant) }]);
		expect(latestValidation(h, 'kiriof-shipping-selection')).toEqual({ clear: 'kiriof-shipping-selection' });
		const publications = h.publications.length;
		// Woo terms/checkout failures are unrelated to buyer courier intent.
		h.validations.push({ terms: { message: 'Please accept the terms', hidden: false } }); h.notify();
		expect(h.publications.at(-1)[1].shipping_selection).toEqual(reviewed(oldInstant));
		expect(h.publications.slice(publications).every(([, payload]) => JSON.stringify(payload.shipping_selection) === JSON.stringify(reviewed(oldInstant)))).toBe(true);
		expect(latestValidation(h, 'terms')).toEqual({ terms: { message: 'Please accept the terms', hidden: false } });
		// Native/server fallback is not a user choice and must not silently review Cargo.
		h.model.cart.shippingRates = selected(cargo, 'kiriminaja-official'); h.notify();
		expect(latestValidation(h, 'kiriof-shipping-selection')).toEqual({ 'kiriof-shipping-selection': { message: 'Shipping options changed. Please review and select your courier again before placing the order.', hidden: false } });
		expect(h.publications.at(-1)[1].shipping_selection).toEqual(reviewed(oldInstant));
		expect(h.sends).toHaveLength(1);
		h.shippingChange(cargo);
		expect(h.publications.at(-1)).toEqual(['kiriminaja-official', { destination: h.root.kiriofBuyerCheckout.getDestination(), shipping_selection: reviewed(cargo) }]);
		expect(latestValidation(h, 'kiriof-shipping-selection')).toEqual({ clear: 'kiriof-shipping-selection' });
		expect(latestValidation(h, 'terms')).toEqual({ terms: { message: 'Please accept the terms', hidden: false } });
		expect(h.sends).toHaveLength(1);
		h.pagehide(); expect(h.documentEvents.get('change')?.size).toBe(0);
	});
	test('initial review waits for complete stable native packages and does not replace intent on price changes', async () => {
		const h = harness(); h.model.rateBusy = true;
		h.model.cart.shippingRates = [
			{ package_id: 0, shipping_rates: [{ rate_id: 'kiriminaja:jne', method_id: 'kiriminaja-official', selected: true }] },
			{ package_id: 1, shipping_rates: [] },
		];
		h.mount(); expect(h.publications.at(-1)[1].shipping_selection).toEqual({ version: 1, packages: [] });
		h.model.rateBusy = false; h.notify();
		expect(h.publications.at(-1)[1].shipping_selection).toEqual({ version: 1, packages: [] });
		h.model.cart.shippingRates[1].shipping_rates = [{ rate_id: 'flat_rate:2', method_id: 'flat_rate', selected: true }]; h.notify();
		const expected = { version: 1, packages: [{ package_id: '0', rate_id: 'kiriminaja:jne' }, { package_id: '1', rate_id: 'flat_rate:2' }] };
		expect(h.publications.at(-1)[1].shipping_selection).toEqual(expected);
		Object.assign(h.model.cart.shippingRates[0].shipping_rates[0], { price: '99999', name: 'New label' }); h.notify();
		expect(h.publications.at(-1)[1].shipping_selection).toEqual(expected);
		expect(latestValidation(h, 'kiriof-shipping-selection')).toEqual({ clear: 'kiriof-shipping-selection' });
	});
	test('district lookup deadline aborts and reports once; retry ignores late old success', async () => {
		const h = harness(); h.mount(); await h.flush(250); await h.flush(10000);
		expect(h.lookups[0].init.signal.aborted).toBe(true); expect(h.status()).toBe('Lookup timed out');
		h.retry(); await h.flush(250); await h.reply(1, [{ id: 9, text: 'Fresh' }]); await h.reply(0);
		expect(options(h.find('select'))).toEqual([{ value: '9', label: 'Fresh' }]);
		expect([...h.timers.values()].some(timer => timer.delay === 10000)).toBe(false);
	});
	test('expired-on-arrival available quote with no deadline offers manual refresh without a timer loop', async () => {
		for (const collapsed of [false, true]) {
			const h = harness({ collapsed, config: { districtPostcode: '12345', district: { id: 7 } } });
			h.model.cart.shippingRates[0].shipping_rates[0] = { rate_id: 'kiriminaja-instant:1', method_id: 'kiriminaja-instant', selected: true };
			h.model.cart.extensions['kiriminaja-official-instant-checkout'] = { eligible: false, code: 'available', message: 'Quote expired', expires_at: 0 };
			h.mount();
			expect(latestValidation(h, 'kiriof-buyer-destination')['kiriof-buyer-destination'].message).toBe('Loading');
			await h.flush(250); await h.reply(); await h.flush(0);
			expect(h.sends[0].request.data.refresh_instant).toBe(false);
			h.sends[0].resolve(); await h.settle();
			expect(latestValidation(h, 'kiriof-buyer-destination')['kiriof-buyer-destination'].message).toBe('Quote expired');
			expect(h.find('button').children[0]).toBe('Retry');
			h.model.rateBusy = true; h.retry(); await h.flush(0);
			expect(h.sends).toHaveLength(1); expect(h.find('button')).toBeUndefined();
			h.model.rateBusy = false; h.notify(); await h.flush(0);
			expect(h.sends).toHaveLength(2);
			expect(h.sends[1].request.overwriteDirtyCustomerData).toBe(false);
			expect(h.sends[1].request.data.refresh_instant).toBe(true);
			expect(h.sends[1].request.data.quote_refresh_version).toBe(1);
			expect(h.sends[1].request.data.destination).toEqual(h.sends[0].request.data.destination);
			expect(h.sends[1].request.data.shipping_method).toBeUndefined();
			h.sends[1].resolve(); await h.settle();
			for (let i = 0; i < 3; i++) { h.notify(); await h.flush(0); }
			expect(h.sends).toHaveLength(2); expect(h.find('button').children[0]).toBe('Retry');
		}
	});
	test('positive expired initial deadline schedules one immediate refresh and never retries failed refresh automatically', async () => {
		const h = harness({ config: { districtPostcode: '12345', district: { id: 7 } } });
		const expires_at = Math.floor(Date.now() / 1000) - 10;
		h.model.cart.shippingRates[0].shipping_rates[0] = { rate_id: 'kiriminaja-instant:1', method_id: 'kiriminaja-instant', selected: true };
		h.model.cart.extensions['kiriminaja-official-instant-checkout'] = { eligible: false, code: 'available', message: 'Quote expired', expires_at };
		h.mount(); expect([...h.timers.values()].filter(timer => timer.delay === 0)).toHaveLength(1);
		await h.flush(0); expect(h.sends).toHaveLength(0);
		await h.flush(250); await h.reply(); await h.flush(0);
		expect(h.sends).toHaveLength(1); expect(h.sends[0].request.data.quote_refresh_version).toBe(1);
		expect(h.sends[0].request.data.refresh_instant).toBe(true);
		h.sends[0].reject(new Error('offline')); await h.settle();
		for (let i = 0; i < 3; i++) { h.notify(); await h.flush(0); }
		expect(h.sends).toHaveLength(1); expect(h.status()).toBe('Quote refresh failed');
		expect(h.find('button').children[0]).toBe('Retry');
	});
	test('permanent Instant ineligibility never schedules expiry refresh or offers quote Retry', async () => {
		for (const code of ['outside_coverage', 'cod_not_supported', 'unsupported_items']) {
			const h = harness({ config: { districtPostcode: '12345', district: { id: 7 } } });
			h.model.cart.shippingRates[0].shipping_rates[0] = { rate_id: 'kiriminaja-instant:1', method_id: 'kiriminaja-instant', selected: true };
			h.model.cart.extensions['kiriminaja-official-instant-checkout'] = { eligible: false, code, message: 'Not available', expires_at: Math.floor(Date.now() / 1000) - 10 };
			await ready(h); await h.flush(0); h.sends[0].resolve(); await h.settle();
			for (let i = 0; i < 3; i++) { h.notify(); await h.flush(0); }
			expect(h.sends).toHaveLength(1); expect(h.sends[0].request.data.refresh_instant).toBe(false);
			expect(h.find('button')).toBeUndefined(); expect(h.status()).toBe('Not available');
		}
	});
	test('failed server quotes offer manual Retry and future courier changes remain inert', async () => {
		for (const code of ['quote_failed', 'quote_failed_or_unavailable']) {
			const h = harness({ config: { districtPostcode: '12345', district: { id: 7 } } });
			h.model.cart.shippingRates[0].shipping_rates[0] = { rate_id: 'kiriminaja-instant:1', method_id: 'kiriminaja-instant', selected: true };
			h.model.cart.extensions['kiriminaja-official-instant-checkout'] = { eligible: false, code, expires_at: 0 };
			await ready(h); await h.flush(0); h.sends[0].resolve(); await h.settle();
			expect(h.find('button').children[0]).toBe('Retry');
			h.model.cart.extensions['kiriminaja-official-instant-checkout'] = { eligible: true, code: 'available', expires_at: Math.floor(Date.now() / 1000) + 120 };
			h.model.cart.shippingRates[0].shipping_rates[0].rate_id = 'kiriminaja-instant:2'; h.notify(); await h.flush(0);
			expect(h.sends).toHaveLength(1); expect(h.find('button')).toBeUndefined();
		}
	});
	test('never-settling cart mutation requires reload or actual settle before explicit retry', async () => {
		const h = harness(); await ready(h); h.choose('7'); await h.flush(0); await h.flush(15000);
		expect(h.status()).toBe('Save stalled'); expect(h.find('button').children[0]).toBe('Reload checkout');
		h.model.payment = 'stripe'; h.notify(); await h.flush(0); expect(h.sends).toHaveLength(1);
		h.find('button').props.onClick(); expect(h.root.reloaded).toBe(true);
		h.sends[0].resolve(); await h.settle(); await h.flush(0); expect(h.sends).toHaveLength(1);
		h.retry(); await h.flush(0); expect(h.sends).toHaveLength(2); expect(h.sends[1].request.data.payment_method).toBe('stripe');
	});
	test('selected Instant quote expiry refreshes once through the serialized native gate', async () => {
		const h = harness(); const expires_at = Math.floor(Date.now() / 1000) + 120;
		h.model.cart.extensions['kiriminaja-official-instant-checkout'] = { eligible: true, code: 'available', message: '', expires_at };
		h.model.cart.shippingRates[0].shipping_rates[0] = { rate_id: 'kiriminaja-instant:1', method_id: 'kiriminaja-instant', selected: true };
		await ready(h); h.choose('7'); await h.flush(0); h.sends[0].resolve(); await h.settle();
		const timer = [...h.timers.values()].find(timer => timer.delay > 100000)!; expect(timer.delay).toBeLessThanOrEqual(121000);
		h.model.rateBusy = true; await h.flush(timer.delay); await h.flush(0); expect(h.sends).toHaveLength(1);
		h.model.rateBusy = false; h.notify(); await h.flush(0); expect(h.sends).toHaveLength(2);
		expect(h.sends[1].request.data.refresh_instant).toBe(true); expect(h.sends[1].request.data.quote_refresh_version).toBe(1);
		expect(h.sends[1].request.data.destination).toEqual(h.sends[0].request.data.destination);
		h.sends[1].resolve(); await h.settle(); h.notify(); await h.flush(timer.delay); await h.flush(0); expect(h.sends).toHaveLength(2);
		expect(h.status()).toBe('Quote refresh failed');
	});
	test('server ineligibility blocks stale selected Instant and recipient changes enqueue fresh snapshots', async () => {
		const h = harness(); await ready(h); h.choose('7'); await h.flush(0); h.sends[0].resolve(); await h.settle();
		h.model.cart.shippingRates[0].shipping_rates[0] = { rate_id: 'kiriminaja-instant:1', method_id: 'kiriminaja-instant', selected: true };
		h.model.cart.extensions['kiriminaja-official-instant-checkout'] = { eligible: false, code: 'outside_coverage', message: 'Outside coverage', expires_at: 0 };
		h.notify(); expect(latestValidation(h, 'kiriof-buyer-destination')['kiriof-buyer-destination'].message).toBe('Outside coverage');
		h.model.cart.shippingAddress.first_name = 'New Buyer'; h.model.cart.shippingAddress.phone = '123'; h.notify(); await h.flush(0);
		expect(h.sends).toHaveLength(2); expect(h.sends[1].request.data.recipient_context).toEqual({ first_name: 'New Buyer', last_name: '', phone: '123' });
		expect(h.sends[1].request.data.destination).toEqual(h.sends[0].request.data.destination);
	});

	test('registers one in-address District component with shared District logic', async () => {
		const h = harness();
		expect(h.registeredBlocks()).toHaveLength(1);
		expect(h.registeredBlocks()[0].metadata).toEqual({ name: 'kiriminaja-official/checkout-district', parent: ['woocommerce/checkout-shipping-address-block'], attributes: { lock: { type: 'object', default: { remove: true, move: true } } } });
		expect(h.registeredBlocks()[0].force).toBe(true);
		expect(typeof h.registeredBlocks()[0].component).toBe('function');
		h.mountRegisteredBlock();
		await h.flush(250);
		expect(h.lookups).toHaveLength(1);
		await h.reply();
		expect(h.find('select')).toBeDefined();
		expect(h.root.kiriofBuyerCheckout.active).toBe(true);
		expect(h.root.kiriofBuyerCheckout.getDestination().postcode).toBe('12345');
	});
	const savedAddress = { address_1: 'Main Road', address_2: '', city: 'Jakarta', state: 'JK', postcode: '12345', country: 'ID' };
	const savedDestination = { version: 2, district_id: '7', district_label: 'Old label', postcode: '12345', country: 'ID', address_type: 'shipping', destination_latitude: '-6.2000000', destination_longitude: '106.8000000', shipping_address: savedAddress };
	test('reload restores a real saved pin before publication but confirms District independently', async () => {
		const h = harness({ config: { savedDestination } });
		Object.assign(h.model.cart.shippingAddress, savedAddress);
		const api = h.root.kiriofBuyerCheckout;
		expect(api.setCoordinates(savedAddress, { latitude: 0, longitude: 0 })).toBe(false);
		expect(api.active).toBe(false);
		expect(api.getCoordinates(savedAddress)).toEqual({ latitude: '-6.2000000', longitude: '106.8000000', key: JSON.stringify(savedAddress) });
		expect(api.active).toBe(false); expect(h.publications).toHaveLength(0);
		h.mount(); h.mountRegisteredBlock();
		expect(h.publications.length).toBeGreaterThan(0);
		for (const [, payload] of h.publications) {
			expect(payload.destination).not.toBeNull();
			expect(payload.destination.version).toBe(2);
			expect(payload.destination.destination_latitude).toBe('-6.2000000');
		}
		expect(api.getDestination().version).toBe(2); expect(api.getDestination().district_id).toBe('');
		await h.flush(0); expect(h.sends).toHaveLength(0);
		await h.flush(250); expect(h.lookups).toHaveLength(1); await h.reply();
		expect(api.getDestination()).toEqual({ ...savedDestination, district_label: 'District Seven' });
		await h.flush(0); expect(h.sends).toHaveLength(1);
		expect(h.sends[0].request.data.destination.version).toBe(2);
		expect(h.sends[0].request.data.destination.destination_latitude).toBe('-6.2000000');
	});
	test('55581 empty lookup retains saved full pin locally and retries without writing empty or stale destination', async () => {
		const address = { ...savedAddress, postcode: '55581' };
		const stored = { ...savedDestination, postcode: '55581', district_id: '123', shipping_address: address };
		const h = harness({ config: { savedDestination: stored } }); Object.assign(h.model.cart.shippingAddress, address);
		h.mount(); await h.flush(250); await h.reply(0, []); await h.flush(0);
		expect(h.status()).toBe('No subdistricts found. Retry lookup.'); expect(h.find('button').children[0]).toBe('Retry');
		expect(h.sends).toHaveLength(0); expect(h.find('select').props.value).toBe('');
		expect(h.root.kiriofBuyerCheckout.getCoordinates(address)?.latitude).toBe('-6.2000000');
		h.retry(); await h.flush(250);
		expect(h.lookups).toHaveLength(2); expect(h.lookups[1].init.method).toBe('POST');
		expect(new URLSearchParams(h.lookups[1].init.body).get('retry')).toBe('1');
		await h.reply(1, [{ id: 123, text: 'Canonical 55581 district' }]); await h.flush(0);
		expect(h.find('select').props.value).toBe('123'); expect(h.sends).toHaveLength(1);
		expect(h.sends[0].request.data.destination).toEqual({ ...stored, district_label: 'Canonical 55581 district' });
	});
	for (const failure of [ { ok: false }, { ok: true, success: false, data: [] }, { ok: true, success: true, data: [{ id: 0, text: 'Invalid' }] }, { ok: true, success: true, data: [{ id: 123, text: '   ' }] } ]) {
		test(`failed lookup is not genuine empty: ${JSON.stringify(failure)}`, async () => {
			const h = harness(); h.mount(); await h.flush(250);
			h.lookups[0].resolve({ ok: failure.ok, json: () => Promise.resolve(failure) }); await h.settle(); await h.flush(0);
			expect(h.status()).toBe('Lookup failed'); expect(h.find('button').children[0]).toBe('Retry'); expect(h.sends).toHaveLength(0);
			h.retry(); await h.flush(250); await h.reply(1, []);
			expect(h.status()).toBe('No subdistricts found. Retry lookup.'); expect(h.sends).toHaveLength(0);
		});
	}
	test('same-postcode refreshed options invalidate stale identity and recover only exact matches with canonical labels', async () => {
		const h = harness(); await ready(h); h.choose('7'); await h.flush(0); h.sends[0].resolve(); await h.settle();
		// Native collection transition changes lookup eligibility, not the postcode.
		h.model.collection = true; h.notify(); h.model.collection = false; h.notify();
		await h.flush(250); await h.reply(1, []); await h.flush(0);
		expect(h.find('select').props.value).toBe(''); expect(h.sends).toHaveLength(1);
		h.retry(); await h.flush(250); await h.reply(2, [{ id: 9, text: 'Replacement' }]); await h.flush(0);
		expect(h.find('select').props.value).toBe(''); expect(h.root.kiriofBuyerCheckout.getDestination().district_id).toBe(''); expect(h.sends).toHaveLength(1);
		h.model.collection = true; h.notify(); h.model.collection = false; h.notify();
		await h.flush(250); await h.reply(3, [{ id: 7, text: 'Canonical updated label' }]); await h.flush(0);
		expect(h.find('select').props.value).toBe('7'); expect(h.root.kiriofBuyerCheckout.getDestination().district_label).toBe('Canonical updated label');
	});
	test('stale selected label is refreshed even when the same identity remains valid', async () => {
		const h = harness(); await ready(h); h.choose('7');
		h.model.collection = true; h.notify(); h.model.collection = false; h.notify();
		await h.flush(250); await h.reply(1, [{ id: 7, text: 'New canonical label' }]);
		expect(h.find('select').props.value).toBe('7'); expect(h.root.kiriofBuyerCheckout.getDestination().district_label).toBe('New canonical label');
	});
	test('invalid saved identity falls through to valid postcode history then additional configuration', async () => {
		for (const history of [true, false]) {
			const h = harness({ config: { savedDestination: { postcode: '12345', country: 'ID', district_id: '99' }, savedDistrictByPostcode: { '12345': { destination_id: history ? '7' : '98' } }, districtPostcode: '12345', district: { id: 8 } } });
			h.mount(); await h.flush(250); await h.reply(0, [{ id: 7, text: 'History canonical' }, { id: 8, text: 'Config canonical' }]);
			expect(h.find('select').props.value).toBe(history ? '7' : '8'); expect(h.root.kiriofBuyerCheckout.getDestination().district_label).toBe(history ? 'History canonical' : 'Config canonical');
		}
	});
	test('billing checkbox cannot reuse its value as a changed shipping postcode or retry a completed empty lookup', async () => {
		const h = harness(); h.mount(); await h.flush(250); await h.reply(0, []);
		h.billingChange(); await h.flush(250); expect(h.lookups).toHaveLength(1);
		h.model.cart.shippingAddress.postcode = '55581'; h.model.customerBusy = true; h.billingChange(); h.notify(); await h.flush(250);
		expect(new URLSearchParams(h.lookups[1].init.body).get('term')).toBe('55581');
		await h.reply(1, [{ id: 123, text: 'Changed postcode' }]); expect(h.find('select').props.value).toBe('');
		h.model.customerBusy = false; h.notify(); await h.flush(250); await h.flush(0);
		expect(h.lookups).toHaveLength(2); expect(h.sends).toHaveLength(0);
	});
	test('explicitly cleared restored pin never revives for the same shipping address', async () => {
		const h = harness({ config: { savedDestination } }); Object.assign(h.model.cart.shippingAddress, savedAddress);
		await ready(h);
		expect(h.root.kiriofBuyerCheckout.setCoordinates(savedAddress, null)).toBe(true); h.render();
		for (let i = 0; i < 3; i++) { expect(h.root.kiriofBuyerCheckout.getCoordinates(savedAddress)).toBeNull(); h.notify(); }
		h.unmount(); h.mountRegisteredBlock();
		expect(h.root.kiriofBuyerCheckout.getDestination().version).toBe(1);
		await h.flush(0); expect(h.sends).toHaveLength(1); expect(h.sends[0].request.data.destination.version).toBe(1);
	});
	test('a different stable street rejects saved pin permanently without repairing its address', async () => {
		const h = harness({ config: { savedDestination } }); Object.assign(h.model.cart.shippingAddress, savedAddress, { address_1: 'Other Road' });
		await ready(h);
		expect(h.root.kiriofBuyerCheckout.getCoordinates(savedAddress)).toBeNull();
		expect(h.root.kiriofBuyerCheckout.getDestination().version).toBe(1);
		Object.assign(h.model.cart.shippingAddress, savedAddress); h.notify();
		expect(h.root.kiriofBuyerCheckout.getCoordinates(savedAddress)).toBeNull();
		expect(savedDestination.shipping_address.address_1).toBe('Main Road');
	});
	test('empty initial cart and incomplete customer defer saved pin restoration and publishing until hydration', async () => {
		const h = harness({ config: { savedDestination } }); h.model.cart.needsShipping = false; h.model.cart.shippingAddress = {};
		h.mount(); expect(h.publications).toHaveLength(0);
		expect(h.root.kiriofBuyerCheckout.getCoordinates(savedAddress)).toBeNull();
		h.model.cart.needsShipping = true; h.model.cart.shippingAddress = { postcode: '12345', country: 'ID' }; h.notify();
		await h.flush(250); await h.reply(); await h.flush(0);
		expect(h.publications).toHaveLength(0); expect(h.sends).toHaveLength(0);
		Object.assign(h.model.cart.shippingAddress, savedAddress); h.notify();
		expect(h.root.kiriofBuyerCheckout.getDestination().version).toBe(2);
		await h.flush(0); expect(h.sends).toHaveLength(1); expect(h.sends[0].request.data.destination).toEqual({ ...savedDestination, district_label: 'District Seven' });
	});
	test('saved pin never bypasses District lookup failure or invents a selected district', async () => {
		const h = harness({ config: { savedDestination } }); Object.assign(h.model.cart.shippingAddress, savedAddress);
		h.mount(); await h.flush(250); h.lookups[0].reject(new Error('offline')); await h.settle(); await h.flush(0);
		expect(h.sends).toHaveLength(0); expect(h.status()).toBe('Lookup failed');
		expect(h.root.kiriofBuyerCheckout.getCoordinates(savedAddress)?.latitude).toBe('-6.2000000');
		h.retry(); await h.flush(250); await h.reply(1, [{ id: 9, text: 'Other district' }]);
		expect(h.find('select').props.value).toBe('');
	});
	test('saved coordinate restoration rejects non-plain numbers, range errors and inconsistent address context', async () => {
		for (const patch of [{ version: 1 }, { destination_latitude: '0x10' }, { destination_longitude: '1e2' }, { destination_latitude: '91' }, { destination_longitude: '181' }, { district_id: '' }, { country: 'SG' }, { postcode: '54321' }]) {
			const h = harness({ config: { savedDestination: { ...savedDestination, ...patch } } }); Object.assign(h.model.cart.shippingAddress, savedAddress);
			await ready(h); expect(h.root.kiriofBuyerCheckout.getCoordinates(savedAddress)).toBeNull(); expect(h.root.kiriofBuyerCheckout.getDestination().version).toBe(1);
		}
	});
	test('restoration compares all six shipping fields and waits for customer updates to settle', async () => {
		for (const field of ['address_1', 'address_2', 'city', 'state', 'postcode', 'country']) {
			const h = harness({ config: { savedDestination } }); Object.assign(h.model.cart.shippingAddress, savedAddress, { [field]: field === 'country' ? 'SG' : 'Different' });
			h.mount(); expect(h.root.kiriofBuyerCheckout.getCoordinates(h.model.cart.shippingAddress)).toBeNull();
			Object.assign(h.model.cart.shippingAddress, savedAddress); h.notify(); expect(h.root.kiriofBuyerCheckout.getCoordinates(savedAddress)).toBeNull();
		}
		const h = harness({ config: { savedDestination: { ...savedDestination, destination_latitude: '0', destination_longitude: 0 } } });
		Object.assign(h.model.cart.shippingAddress, savedAddress); h.model.customerBusy = true; h.mount();
		expect(h.publications).toHaveLength(0); expect(h.root.kiriofBuyerCheckout.getCoordinates(savedAddress)).toBeNull();
		h.model.customerBusy = false; h.notify(); expect(h.root.kiriofBuyerCheckout.getCoordinates(savedAddress)?.latitude).toBe('0.0000000');
		expect(h.root.kiriofBuyerCheckout.getDestination().destination_longitude).toBe('0.0000000');
	});
	test('unrelated carriers remain checkout-valid when district lookup fails', async () => {
		const h = harness();
		h.model.cart.shippingRates[0].shipping_rates[0] = { rate_id: 'flat_rate:2', method_id: 'flat_rate', selected: true };
		h.mount(); await h.flush(250); h.lookups[0].reject(new Error('offline')); await h.settle();
		expect(latestValidation(h, 'kiriof-buyer-destination')).toEqual({ clear: 'kiriof-buyer-destination' });
		expect(h.sends).toHaveLength(0);
	});
	test('activates the public API and OrderMeta plugin only on Blocks pages', () => {
		const h = harness();
		expect(h.root.kiriofBuyerCheckout.active).toBe(false);
		expect(h.root.kiriofBuyerCheckout.pending).toBe(true);
		expect(h.pluginName()).toBe('kiriminaja-official-buyer-destination');
		expect(h.plugin().scope).toBe('woocommerce-checkout');
		expect(h.classes).toEqual([]);
		expect(h.queries[0]).toContain('.wp-block-woocommerce-cart');
		h.mount(); expect(h.root.kiriofBuyerCheckout.active).toBe(true); expect(h.classes).toEqual(['kiriof-buyer-checkout-active']); expect(h.root.kiriofBuyerCheckout.getDestination().postcode).toBe('12345');
		const classic = harness({ block: false });
		expect(classic.root.kiriofBuyerCheckout).toBeUndefined(); expect(classic.plugin()).toBeUndefined(); expect(classic.classes).toEqual([]);
	});
	test('waits for an actual Slot mount and permanently yields after readiness timeout', async () => {
		const mounted = harness(); const pending = mounted.root.kiriofBuyerCheckout.ready;
		expect(mounted.root.kiriofBuyerCheckout.pending).toBe(true);
		mounted.mount(); expect(await pending).toBe(true);
		expect(mounted.root.kiriofBuyerCheckout.pending).toBe(false);
		await mounted.flush(2000); expect(mounted.root.kiriofBuyerCheckout.disabled).toBe(false);
		const missingSlot = harness(); await missingSlot.flush(2000);
		expect(await missingSlot.root.kiriofBuyerCheckout.ready).toBe(false);
		expect(missingSlot.root.kiriofBuyerCheckout.pending).toBe(false);
		missingSlot.mount(); await missingSlot.flush(250); await missingSlot.flush(0);
		expect(missingSlot.find('select')).toBeUndefined();
		expect(missingSlot.classes).toEqual([]); expect(missingSlot.root.kiriofBuyerCheckout.active).toBe(false);
		expect(missingSlot.lookups).toHaveLength(0); expect(missingSlot.sends).toHaveLength(0);
		expect(missingSlot.publications).toHaveLength(0); expect(missingSlot.validations).toHaveLength(0);
	});
	test('duplicate mobile Slots mirror selection and retries with one effects owner', async () => {
		const h = harness(); h.mount(); h.mount(); await h.flush(250);
		expect(h.lookups).toHaveLength(1);
		expect(new Set(h.publications.map(([, payload]) => JSON.stringify(payload.destination))).size).toBe(1);
		expect(h.publications.at(-1)).toEqual(['kiriminaja-official', { destination: h.root.kiriofBuyerCheckout.getDestination(), shipping_selection: reviewed('kiriminaja:jne') }]);
		h.lookups[0].reject(new Error('offline')); await h.settle();
		expect(h.findIn(0, 'p').children[0]).toBe('Lookup failed');
		expect(h.findIn(1, 'p').children[0]).toBe('Lookup failed');
		h.findIn(1, 'button').props.onClick(); h.render(); await h.flush(250);
		expect(h.lookups).toHaveLength(2); await h.reply(1);
		const publications = h.publications.length;
		h.findIn(1, 'select').props.onChange({ target: { value: '7' } }); h.render();
		expect(h.findIn(0, 'select').props.value).toBe('7');
		expect(h.findIn(1, 'select').props.value).toBe('7');
		expect(h.publications.length).toBeGreaterThan(publications);
		for (const [, payload] of h.publications.slice(publications)) expect(payload.destination).toEqual(h.root.kiriofBuyerCheckout.getDestination());
		await h.flush(0); expect(h.sends).toHaveLength(1);
		h.sends[0].reject(new Error('offline')); await h.settle();
		h.findIn(1, 'button').props.onClick(); h.render(); await h.flush(0);
		expect(h.sends).toHaveLength(2); expect(h.sends[1].request.data).toEqual(h.sends[0].request.data);
		h.sends[1].resolve(); await h.settle();
		const validations = h.validations.length;
		h.unmount(1);
		expect(h.validations.slice(validations).filter(value => value['kiriof-buyer-destination'] || value.clear === 'kiriof-buyer-destination')).toEqual([]);
		expect(h.root.kiriofBuyerCheckout.active).toBe(true);
		h.unmount(0); expect(h.root.kiriofBuyerCheckout.active).toBe(false); expect(h.classes).toEqual([]);
	});
	test('owner unmount aborts lookup and transfers to the remaining Slot without stale results', async () => {
		const h = harness(); h.mount(); h.mount(); await h.flush(250);
		const validations = h.validations.length; h.unmount(0);
		expect(h.lookups[0].init.signal.aborted).toBe(true);
		expect(h.validations.slice(validations).some(value => value.clear === 'kiriof-buyer-destination')).toBe(false);
		expect(h.root.kiriofBuyerCheckout.active).toBe(true); await h.flush(250);
		expect(h.lookups).toHaveLength(2);
		await h.reply(1, [{ id: 9, text: 'Replacement' }]); await h.reply(0);
		expect(options(h.findIn(1, 'select'))).toEqual([{ value: '9', label: 'Replacement' }]);
		h.choose('9'); await h.flush(0); expect(h.sends).toHaveLength(1);
		h.sends[0].resolve(); await h.settle();
		h.mount(); const searches = h.lookups.length; h.unmount(1); await h.flush(250);
		expect(h.lookups).toHaveLength(searches); expect(h.find('select').props.value).toBe('9');
		h.model.payment = 'bacs'; h.render(); await h.flush(0);
		expect(h.sends).toHaveLength(2); expect(h.sends[1].request.data.destination.district_id).toBe('9');
	});
	test('last owner unmount pauses unsent mutations until a Slot owns effects again', async () => {
		const h = harness(); await ready(h); h.choose('7'); h.unmount(); await h.flush(0);
		expect(h.sends).toHaveLength(0); h.notify(); await h.flush(0); expect(h.sends).toHaveLength(0);
		h.mount(); await h.flush(0); expect(h.sends).toHaveLength(1);
		expect(h.sends[0].request.data.destination.district_id).toBe('7');
	});
	test('BFCache pagehide retains the owner subscription and resumes native gating', async () => {
		const h = harness(); h.model.rateBusy = true; await ready(h); h.choose('7'); h.pagehide(true);
		expect(h.subscribers.size).toBe(1); h.model.rateBusy = false; h.notify(); await h.flush(0);
		expect(h.sends).toHaveLength(1); expect(h.root.kiriofBuyerCheckout.active).toBe(true);
	});
	test('restores live postcode selections and config district fallback after lookup confirmation', async () => {
		const h = harness({ config: { districtPostcode: ' 12 345 ', district: { id: 7 } } }); await ready(h);
		expect(h.find('select').props.value).toBe('7'); h.choose(null);
		h.choose('7'); h.model.cart.shippingAddress.postcode = '54321'; h.render(); await h.flush(250);
		await h.reply(1, [{ id: 9, text: 'Other' }]); h.choose('9');
		h.model.cart.shippingAddress.postcode = '12345'; h.render(); await h.flush(250); await h.reply(2);
		expect(h.find('select').props.value).toBe('7');
	});
	test('feature detection fails closed and supports ExperimentalOrderMeta', () => {
		for (const missing of ['session', 'update', 'useSelect', 'validation', 'nativeRateStatus', 'nativeCustomerStatus', 'payment']) {
			const h = harness({ missing }); expect(h.root.kiriofBuyerCheckout).toBeUndefined(); expect(h.plugin()).toBeUndefined(); expect(h.classes).toEqual([]);
		}
		const innerOnly = harness({ missing: 'plugins' }); innerOnly.mountRegisteredBlock();
		expect(innerOnly.root.kiriofBuyerCheckout.active).toBe(true); expect(innerOnly.plugin()).toBeUndefined();
		expect(harness({ enabled: false }).registeredBlocks()).toHaveLength(0);
		expect(harness({ missing: 'slot' }).registeredBlocks()).toHaveLength(1);
		const experimental = harness({ slot: 'ExperimentalOrderMeta' }); experimental.mount(); expect(experimental.find('select')).toBeDefined();
		const slotOnly = harness({ missing: 'inner' }); slotOnly.mount(); expect(slotOnly.find('select')).toBeDefined();
	});
	test('debounces lookup, rejects bad rows and publishes canonical selection after retry', async () => {
		const h = harness(); h.model.cart.shippingAddress.postcode = ' 12 345 '; h.mount();
		expect(h.status()).toBe('Loading'); expect(h.find('select').props.disabled).toBe(true);
		await h.flush(0); expect(h.lookups).toHaveLength(0); expect(h.sends).toHaveLength(0);
		await h.flush(250);
		expect(h.lookups[0].url).toBe('/ajax'); expect(h.lookups[0].init.credentials).toBe('same-origin');
		expect(new URLSearchParams(h.lookups[0].init.body).get('term')).toBe('12345');
		expect(new URLSearchParams(h.lookups[0].init.body).get('nonce')).toBe('nonce');
		await h.reply(0, [{ id: 0, text: 'Bad' }, { id: 'abc', text: 'Bad' }, { id: 8, text: '' }, { id: 7, text: 'District Seven' }]);
		expect(h.status()).toBe('Lookup failed'); expect(options(h.find('select'))).toEqual([]);
		h.retry(); await h.flush(250); await h.reply(1);
		expect(options(h.find('select'))).toEqual([{ value: '7', label: 'District Seven' }]);
		h.choose('7'); expect(h.root.kiriofBuyerCheckout.getDestination()).toEqual({ version: 1, district_id: '7', district_label: 'District Seven', postcode: '12345', country: 'ID', address_type: 'shipping' });
		expect(h.validations.some(errors => errors['kiriof-buyer-destination']?.hidden === false)).toBe(true);
	});
	test('sends immutable extension snapshots without taking native shipping ownership', async () => {
		const h = harness(); await ready(h); h.choose('7'); await h.flush(0);
		expect(h.sends).toHaveLength(1);
		const request = h.sends[0].request;
		expect(request.namespace).toBe('kiriminaja-official'); expect(request.overwriteDirtyCustomerData).toBe(false);
		expect(request.data).toEqual({ action: 'sync_checkout', destination: h.root.kiriofBuyerCheckout.getDestination(), payment_method: 'cod', insurance: 1, force_insurance: 0, recipient_context: { first_name: '', last_name: '', phone: '' }, quote_refresh_version: 0, refresh_instant: false });
		expect(request.data.shipping_method).toBeUndefined(); expect(Object.isFrozen(request.data)).toBe(true); expect(Object.isFrozen(request.data.destination)).toBe(true);
		expect(h.status()).toBe('Saving'); h.sends[0].resolve(); await h.settle();
		expect(latestValidation(h, 'kiriof-buyer-destination')).toEqual({ clear: 'kiriof-buyer-destination' });
	});
	for (const gate of ['rateBusy', 'customerBusy', 'pendingItems'] as const) {
		test(`waits for native ${gate} before updating, then resumes from subscription`, async () => {
			const h = harness(); h.model[gate] = true; await ready(h); h.choose('7'); await h.flush(0); expect(h.sends).toHaveLength(0);
			h.model[gate] = false; h.notify(); await h.flush(0); expect(h.sends).toHaveLength(1); expect(h.sends[0].request.data.destination.district_id).toBe('7');
		});
	}
	test('restores saved identity only after lookup confirms an option, before empty mutation', async () => {
		const h = harness({ config: { savedDestination: { postcode: '12345', country: 'ID', district_id: '7', district_label: 'Stale label' } } });
		h.mount(); await h.flush(0); expect(h.sends).toHaveLength(0); expect(h.root.kiriofBuyerCheckout.getDestination().district_id).toBe('');
		await h.flush(250); await h.reply(); expect(h.find('select').props.value).toBe('7');
		await h.flush(0); expect(h.sends).toHaveLength(1); expect(h.sends[0].request.data.destination.district_label).toBe('District Seven');
		const invalid = harness({ config: { savedDistrictByPostcode: { '12345': { destination_id: 99 } } } });
		await ready(invalid); expect(invalid.find('select').props.value).toBe('');
	});
	test('aborts obsolete postcode AJAX and ignores even a late successful response', async () => {
		const h = harness(); h.mount(); await h.flush(250);
		h.model.cart.shippingAddress.postcode = '54321'; h.render(); expect(h.lookups[0].init.signal.aborted).toBe(true);
		await h.flush(250); await h.reply(1, [{ id: 9, text: 'New district' }]); await h.reply(0);
		expect(options(h.find('select'))).toEqual([{ value: '9', label: 'New district' }]);
		h.choose('9'); expect(h.root.kiriofBuyerCheckout.getDestination().postcode).toBe('54321');
	});
	test('same postcode with new country invalidates selection and lookup context', async () => {
		const h = harness(); await ready(h); h.choose('7');
		h.model.cart.shippingAddress.country = 'SG'; h.render(); expect(h.find('select')).toBeUndefined();
		expect(h.root.kiriofBuyerCheckout.getDestination().country).toBe('SG'); expect(h.root.kiriofBuyerCheckout.getDestination().district_id).toBe('');
		h.model.cart.shippingAddress.country = 'ID'; h.render(); expect(h.find('select').props.disabled).toBe(true);
		await h.flush(250); expect(h.lookups).toHaveLength(2);
	});
	test('lookup failure blocks mutation and offers a real retry', async () => {
		const h = harness(); h.mount(); await h.flush(250); h.lookups[0].reject(new Error('offline')); await h.settle();
		expect(h.status()).toBe('Lookup failed'); await h.flush(0); expect(h.sends).toHaveLength(0);
		h.retry(); await h.flush(250); expect(h.lookups).toHaveLength(2); await h.reply(1); h.choose('7'); await h.flush(0); expect(h.sends).toHaveLength(1);
	});
	test('failed checkout update preserves selected district and retries the identical snapshot', async () => {
		const h = harness(); await ready(h); h.choose('7'); await h.flush(0);
		h.sends[0].reject(new Error('offline')); await h.settle();
		expect(h.status()).toBe('Update failed'); expect(h.find('select').props.value).toBe('7'); expect(h.root.kiriofBuyerCheckout.getDestination().district_id).toBe('7');
		h.notify(); await h.flush(0); expect(h.sends).toHaveLength(1);
		h.retry(); await h.flush(0); expect(h.sends).toHaveLength(2); expect(h.sends[1].request.data).toEqual(h.sends[0].request.data);
		h.sends[1].resolve(); await h.settle(); expect(latestValidation(h, 'kiriof-buyer-destination')).toEqual({ clear: 'kiriof-buyer-destination' });
	});
	test('serializes payment updates without replaying courier selection', async () => {
		const h = harness(); await ready(h); h.choose('7'); await h.flush(0);
		h.model.payment = 'bacs'; h.render();
		h.model.payment = 'stripe'; h.model.cart.shippingRates[0].shipping_rates[0].rate_id = 'kiriminaja:sicepat'; h.render();
		await h.flush(0); expect(h.sends).toHaveLength(1);
		expect(h.sends[0].request.data.payment_method).toBe('cod');
		h.sends[0].resolve(); await h.settle(); await h.flush(0);
		expect(h.sends).toHaveLength(2); expect(h.sends[1].request.data.payment_method).toBe('stripe');
		expect(h.sends[1].request.data.shipping_context).toBeUndefined();
		expect(h.sends[1].request.data.shipping_method).toBeUndefined();
	});
	test('native courier changes alone do not trigger another plugin cart mutation', async () => {
		const h = harness(); await ready(h); h.choose('7'); await h.flush(0);
		h.sends[0].resolve(); await h.settle();
		h.model.cart.shippingRates[0].shipping_rates[0].rate_id = 'kiriminaja:sicepat'; h.render();
		await h.flush(0); expect(h.sends).toHaveLength(1);
	});
	test('Instant selection still validates a required district without owning courier selection', async () => {
		const h = harness();
		h.model.cart.shippingRates[0].shipping_rates[0] = { rate_id: 'kiriminaja-instant:7:gosend:instant', method_id: 'kiriminaja-instant', selected: true };
		await ready(h);
		await h.flush(0); expect(h.sends).toHaveLength(0);
		expect(latestValidation(h, 'kiriof-buyer-destination')['kiriof-buyer-destination'].message).toBe('Subdistrict required');
		h.choose('7'); await h.flush(0); h.sends[0].resolve(); await h.settle();
		expect(latestValidation(h, 'kiriof-buyer-destination')).toEqual({ clear: 'kiriof-buyer-destination' });
		expect(h.sends[0].request.data.shipping_method).toBeUndefined();
	});
	test('native District select shares Woo floating-label markup and offers postcode options without search requests', async () => {
		const h = harness(); h.mount(); await h.flush(250);
		await h.reply(0, [{ id: 7, text: 'North district' }, { id: 8, text: 'South district' }]);
		const select = h.find('select');
		expect(select.props.className).toBe('wc-blocks-components-select__select');
		expect(h.find('label').props.className).toBe('wc-blocks-components-select__label');
		expect(h.find('label').props.htmlFor).toBe(select.props.id);
		expect(select.props['aria-describedby']).toBe(h.find('p').props.id);
		expect(select.props['aria-required']).toBe(true);
		expect(options(select)).toEqual([{ value: '7', label: 'North district' }, { value: '8', label: 'South district' }]);
		h.choose('8'); expect(h.find('select').props.value).toBe('8'); expect(h.lookups).toHaveLength(1);
	});
	test('native select placements have unique stable label/status IDs without admin component APIs', async () => {
		const h = harness(); h.mount();
		const fallbackId = h.findIn(0, 'select').props.id;
		const inner = h.mountRegisteredBlock();
		const innerId = h.findIn(inner, 'select').props.id;
		expect(innerId).not.toBe(fallbackId);
		h.render(); expect(h.findIn(inner, 'select').props.id).toBe(innerId);
		await h.flush(250); await h.reply();
		expect(h.findIn(inner, 'label').props.htmlFor).toBe(innerId);
		expect(h.findIn(inner, 'select').props['aria-invalid']).toBe(true);
		h.findIn(inner, 'select').props.onChange({ target: { value: '7' } }); h.render();
		expect(h.findIn(inner, 'select').props['aria-invalid']).toBe(false);
		h.findIn(inner, 'select').props.onChange({ target: { value: '' } }); h.render();
		expect(h.findIn(inner, 'select').props.value).toBe('');
		expect(h.root.kiriofBuyerCheckout.getDestination().district_id).toBe('');
	});
	test('pagehide unsubscribes and disposes pending updates; unmount aborts lookup and clears validation', async () => {
		const h = harness(); h.mount(); await h.flush(250); expect(h.subscribers.size).toBe(1);
		h.unmount(); expect(h.lookups[0].init.signal.aborted).toBe(true); expect(latestValidation(h, 'kiriof-buyer-destination')).toEqual({ clear: 'kiriof-buyer-destination' });
		h.pagehide(); expect(h.subscribers.size).toBe(0); await h.reply(); await h.flush(0); expect(h.sends).toHaveLength(0);
		const pending = harness(); await ready(pending); pending.choose('7'); pending.pagehide(); await pending.flush(0); expect(pending.sends).toHaveLength(0);
		const inFlight = harness(); await ready(inFlight); inFlight.choose('7'); await inFlight.flush(0); inFlight.model.payment = 'bacs'; inFlight.render(); inFlight.pagehide();
		inFlight.sends[0].resolve(); await inFlight.settle(); await inFlight.flush(0); expect(inFlight.sends).toHaveLength(1);
	});
	test('collection and no-shipping carts render no district and perform no lookup or mutation', async () => {
		for (const mode of ['collection', 'noShipping']) {
			const h = harness(); if (mode === 'collection') h.model.collection = true; else h.model.cart.needsShipping = false;
			h.mount(); await h.flush(250); await h.flush(0); expect(h.find('select')).toBeUndefined(); expect(h.lookups).toHaveLength(0); expect(h.sends).toHaveLength(0);
			expect(latestValidation(h, 'kiriof-buyer-destination')).toEqual({ clear: 'kiriof-buyer-destination' });
		}
	});
	test('public zero pin publishes version 2 with exactly six normalized shipping fields and the latest queued payload', async () => {
		const h = harness();
		Object.assign(h.model.cart.shippingAddress, { address_1: ' Main Road ', address_2: ' Unit 2 ', city: ' Jakarta ', state: ' JK ', postcode: ' 12 345 ', country: 'id', first_name: 'Buyer', phone: 'private' });
		await ready(h); h.choose('7');
		const address = { ...h.model.cart.shippingAddress };
		expect(h.root.kiriofBuyerCheckout.setCoordinates(address, { latitude: 0, longitude: 0 })).toBe(true); h.render();
		const destination = h.root.kiriofBuyerCheckout.getDestination();
		expect(destination).toEqual({ version: 2, district_id: '7', district_label: 'District Seven', postcode: '12345', country: 'ID', address_type: 'shipping', destination_latitude: '0.0000000', destination_longitude: '0.0000000', shipping_address: { address_1: 'Main Road', address_2: 'Unit 2', city: 'Jakarta', state: 'JK', postcode: '12345', country: 'ID' } });
		expect(h.publications.at(-1)).toEqual(['kiriminaja-official', { destination, shipping_selection: reviewed('kiriminaja:jne') }]);
		h.model.payment = 'bacs'; h.render(); await h.flush(0);
		expect(h.sends).toHaveLength(1);
		expect(h.sends[0].request.data).toEqual({ action: 'sync_checkout', destination, payment_method: 'bacs', insurance: 1, force_insurance: 0, recipient_context: { first_name: 'Buyer', last_name: '', phone: 'private' }, quote_refresh_version: 0, refresh_instant: false });
		expect(Object.isFrozen(h.sends[0].request.data.destination.shipping_address)).toBe(true);
	});
	test('a map-only destination still requires district and never writes an empty identity', async () => {
		const h = harness(); await ready(h);
		expect(h.root.kiriofBuyerCheckout.setCoordinates(h.model.cart.shippingAddress, { latitude: 0, longitude: 106 })).toBe(true); h.render();
		expect(h.root.kiriofBuyerCheckout.getDestination().version).toBe(2);
		expect(h.root.kiriofBuyerCheckout.getDestination().district_id).toBe('');
		await h.flush(0); expect(h.sends).toHaveLength(0);
		expect(latestValidation(h, 'kiriof-buyer-destination')).toEqual({ 'kiriof-buyer-destination': { message: 'Subdistrict required', hidden: false } });
		expect(h.find('select').props.value).toBe('');
	});
	test('changing address_1 invalidates a pin without losing district and rejects stale coordinate callbacks', async () => {
		const h = harness(); await ready(h); h.choose('7');
		const oldAddress = { ...h.model.cart.shippingAddress, address_1: 'Old road' };
		Object.assign(h.model.cart.shippingAddress, oldAddress); h.render();
		expect(h.root.kiriofBuyerCheckout.setCoordinates(oldAddress, { latitude: -6, longitude: 106 })).toBe(true); h.render();
		expect(h.root.kiriofBuyerCheckout.getDestination().version).toBe(2);
		h.model.cart.shippingAddress.address_1 = 'New road'; h.render();
		expect(h.root.kiriofBuyerCheckout.getDestination()).toEqual({ version: 1, district_id: '7', district_label: 'District Seven', postcode: '12345', country: 'ID', address_type: 'shipping' });
		expect(h.root.kiriofBuyerCheckout.getCoordinates(h.model.cart.shippingAddress)).toBeNull();
		expect(h.root.kiriofBuyerCheckout.setCoordinates(oldAddress, { latitude: 1, longitude: 2 })).toBe(false);
		expect(h.root.kiriofBuyerCheckout.setCoordinates(oldAddress, null)).toBe(false);
		h.render(); await h.flush(0);
		expect(h.sends).toHaveLength(1); expect(h.sends[0].request.data.destination.version).toBe(1);
	});
	test('clearing coordinates with null queues a version 1 district-only destination', async () => {
		const h = harness(); await ready(h); h.choose('7');
		expect(h.root.kiriofBuyerCheckout.setCoordinates(h.model.cart.shippingAddress, { latitude: 0, longitude: 0 })).toBe(true); h.render(); await h.flush(0);
		h.sends[0].resolve(); await h.settle();
		expect(h.root.kiriofBuyerCheckout.setCoordinates(h.model.cart.shippingAddress, null)).toBe(true); h.render();
		expect(h.root.kiriofBuyerCheckout.getCoordinates(h.model.cart.shippingAddress)).toBeNull(); await h.flush(0);
		expect(h.sends).toHaveLength(2); expect(h.sends[1].request.data.destination).toEqual({ version: 1, district_id: '7', district_label: 'District Seven', postcode: '12345', country: 'ID', address_type: 'shipping' });
	});
	test('pin changes during an inflight update serialize only the latest point', async () => {
		const h = harness(); await ready(h); h.choose('7'); await h.flush(0);
		const first = h.sends[0].request.data;
		for (const latitude of [0, -6.2]) {
			expect(h.root.kiriofBuyerCheckout.setCoordinates(h.model.cart.shippingAddress, { latitude, longitude: 106.8 })).toBe(true); h.render(); await h.flush(0);
		}
		expect(h.sends).toHaveLength(1); expect(first.destination.version).toBe(1);
		h.sends[0].resolve(); await h.settle(); await h.flush(0);
		expect(h.sends).toHaveLength(2); expect(h.sends[1].request.data.destination.destination_latitude).toBe('-6.2000000');
		expect(h.sends[1].request.data.destination.version).toBe(2);
	});
	test('pending item operations gate coordinate snapshots and resume with the newest point', async () => {
		const h = harness(); h.model.pendingItems = true; await ready(h); h.choose('7');
		for (const latitude of [1, 0]) {
			expect(h.root.kiriofBuyerCheckout.setCoordinates(h.model.cart.shippingAddress, { latitude, longitude: 0 })).toBe(true); h.render(); await h.flush(0);
		}
		expect(h.sends).toHaveLength(0); h.model.pendingItems = false; h.notify(); await h.flush(0);
		expect(h.sends).toHaveLength(1); expect(h.sends[0].request.data.destination.destination_latitude).toBe('0.0000000');
	});
	test('shipping-address owner suppresses OrderMeta fallback and transfers pin state after unmount', async () => {
		const h = harness(); const fallback = h.mount(); await h.flush(250); await h.reply();
		const inner = h.mountRegisteredBlock();
		expect(h.findIn(fallback, 'select')).toBeUndefined(); expect(h.findIn(inner, 'select')).toBeDefined();
		h.findIn(inner, 'select').props.onChange({ target: { value: '7' } }); h.render();
		expect(h.root.kiriofBuyerCheckout.setCoordinates(h.model.cart.shippingAddress, { latitude: 0, longitude: 0 })).toBe(true); h.render(); await h.flush(0);
		expect(h.lookups).toHaveLength(1); expect(h.sends).toHaveLength(1);
		h.sends[0].resolve(); await h.settle(); h.unmount(inner);
		expect(h.findIn(fallback, 'select').props.value).toBe('7');
		expect(h.root.kiriofBuyerCheckout.getDestination().version).toBe(2); expect(h.root.kiriofBuyerCheckout.active).toBe(true);
		h.model.payment = 'bacs'; h.render(); await h.flush(0);
		expect(h.lookups).toHaveLength(1); expect(h.sends).toHaveLength(2);
		expect(h.sends[1].request.data.destination.destination_latitude).toBe('0.0000000'); expect(h.sends[1].request.data.payment_method).toBe('bacs');
	});

});
