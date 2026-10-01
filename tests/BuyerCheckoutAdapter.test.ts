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
function harness(options: { block?: boolean; enabled?: boolean; slot?: string; inner?: boolean; missing?: string; config?: any } = {}) {
	const timers = new Map<number, { delay: number; callback: () => void }>();
	let nextTimer = 0;
	const setTimeout = (callback: () => void, delay = 0) => { timers.set(++nextTimer, { callback, delay }); return nextTimer; };
	const clearTimeout = (id: number) => { timers.delete(id); };
	const lookups: any[] = [], sends: any[] = [], publications: any[] = [], validations: any[] = [], classes: string[] = [], queries: string[] = [];
	const subscribers = new Set<() => void>();
	const events: Record<string, { callback: (event?: any) => void; once: boolean }> = {};
	const model = {
		cart: { needsShipping: true, shippingAddress: { postcode: '12345', country: 'ID' } as any,
			shippingRates: [{ package_id: 0, shipping_rates: [{ rate_id: 'kiriminaja:jne', method_id: 'kiriminaja-official', selected: true }] }] },
		payment: 'cod', rateBusy: false, customerBusy: false, pendingItems: false, collection: false,
	};
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
	let plugin: any, pluginName = '', component: any, cursor = 0;
	const registeredBlocks: any[] = [];
	type Instance = { hooks: any[]; tree: Node | null; dirty: boolean; mounted: boolean };
	const instances: Instance[] = [];
	let current: Instance;
	let effects: (() => void)[] = [];
	const element = {
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
		element, components: { ComboboxControl: 'ComboboxControl' },
		plugins: { registerPlugin: (name: string, value: any) => { pluginName = name; plugin = value; } },
		data: { select, useSelect: (callback: any) => callback(select),
			dispatch: (name: string) => name === 'wc/store/checkout' ? checkoutDispatch : validationDispatch,
			subscribe: (callback: () => void) => { subscribers.add(callback); return () => subscribers.delete(callback); } },
	};
	const blocks: any = {
		[options.slot || 'OrderMeta']: 'OrderMetaSlot',
		registerCheckoutBlock: options.inner === false ? undefined : (registration: any) => { registeredBlocks.push(registration); },
		extensionCartUpdate: (request: any) => { const task = deferred(); sends.push({ request, ...task }); return task.promise; },
	};
	const strings = { district: 'District', districtRequired: 'District required', postcodeRequired: 'Postcode required', loading: 'Loading', lookupFailed: 'Lookup failed', empty: 'Empty', saving: 'Saving', updateFailed: 'Update failed', retry: 'Retry', selectDistrict: 'Select district' };
	const root: any = {
		wp, wc: { blocksCheckout: blocks }, setTimeout, clearTimeout,
		kiriofBuyerCheckoutConfig: { enabled: options.enabled !== false, nonce: 'nonce', ajaxUrl: '/ajax', globalInsurance: true, i18n: strings, ...options.config },
		fetch: (url: string, init: any) => { const task = deferred(); lookups.push({ url, init, ...task }); return task.promise; },
		addEventListener: (name: string, callback: () => void, init: any) => { events[name] = { callback, once: !!init?.once }; },
	};
	const document = {
		querySelector: (selector: string) => { queries.push(selector); return options.block === false ? null : {}; },
		documentElement: { classList: { add: (name: string) => { if (!classes.includes(name)) classes.push(name); }, remove: (name: string) => { const index = classes.indexOf(name); if (index >= 0) classes.splice(index, 1); } } },
		querySelectorAll: forbidden, getElementById: forbidden, createEvent: forbidden,
	};
	const context = { window: root, document, setTimeout, clearTimeout, AbortController, URLSearchParams,
		HTMLInputElement: new Proxy({}, { get: forbidden }), HTMLSelectElement: new Proxy({}, { get: forbidden }), jQuery: forbidden };
	runInNewContext(sessionSource, context);
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
				current = instance; instance.dirty = false; cursor = 0; effects = []; instance.tree = component();
				for (const effect of effects) effect();
			}
		}
	}
	function mount() {
		if (!plugin) throw new Error('Plugin was not registered');
		const slot = plugin.render();
		expect(slot.type).toBe('OrderMetaSlot');
		component = slot.children[0].type;
		instances.push({ hooks: [], tree: null, dirty: true, mounted: true }); render();
		return instances.length - 1;
	}
	function mountRegisteredBlock(index = 0) {
		const registration = registeredBlocks[index];
		expect(typeof registration?.component).toBe('function');
		const rendered = registration.component();
		component = () => rendered.type( rendered.props );
		instances.push({ hooks: [], tree: null, dirty: true, mounted: true });
		render();
		return instances.length - 1;
	}
	function find(type: string, node: any = instances.find(instance => instance.mounted)?.tree): any {
		if (!node || typeof node !== 'object') return undefined;
		if (node.type === type) return node;
		for (const child of node.children || []) { const match = find(type, child); if (match) return match; }
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
	return { root, plugin: () => plugin, pluginName: () => pluginName, classes, queries, model, publications, validations, sends, lookups, subscribers, timers, registeredBlocks: () => registeredBlocks,
		mount, mountRegisteredBlock, render, settle, flush, reply, notify, pagehide, unmount, find, findIn,
		choose: (id: string | null) => { find('ComboboxControl').props.onChange(id); render(); },
		retry: () => { find('button').props.onClick(); render(); },
		status: () => find('p')?.children[0],
	};
}

async function ready(h: ReturnType<typeof harness>) { h.mount(); await h.flush(250); await h.reply(); }

describe('buyer checkout Blocks adapter (unchanged production VM)', () => {
	test('registers one in-address District component with shared District logic', async () => {
		const h = harness();
		expect(h.registeredBlocks()).toHaveLength(1);
		expect(h.registeredBlocks()[0].metadata).toEqual({ name: 'kiriminaja-official/checkout-district', parent: ['woocommerce/checkout-shipping-address-block'] });
		expect(typeof h.registeredBlocks()[0].component).toBe('function');
		h.mountRegisteredBlock();
		await h.flush(250);
		expect(h.lookups).toHaveLength(1);
		await h.reply();
		expect(h.find('ComboboxControl')).toBeDefined();
		expect(h.root.kiriofBuyerCheckout.active).toBe(true);
		expect(h.root.kiriofBuyerCheckout.getDestination().postcode).toBe('12345');
	});
	test('unrelated carriers remain checkout-valid when district lookup fails', async () => {
		const h = harness();
		h.model.cart.shippingRates[0].shipping_rates[0] = { rate_id: 'flat_rate:2', method_id: 'flat_rate', selected: true };
		h.mount(); await h.flush(250); h.lookups[0].reject(new Error('offline')); await h.settle();
		expect(h.validations.at(-1)).toEqual({ clear: 'kiriof-buyer-destination' });
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
		expect(missingSlot.find('ComboboxControl')).toBeUndefined();
		expect(missingSlot.classes).toEqual([]); expect(missingSlot.root.kiriofBuyerCheckout.active).toBe(false);
		expect(missingSlot.lookups).toHaveLength(0); expect(missingSlot.sends).toHaveLength(0);
		expect(missingSlot.publications).toHaveLength(0); expect(missingSlot.validations).toHaveLength(0);
	});
	test('duplicate mobile Slots mirror selection and retries with one effects owner', async () => {
		const h = harness(); h.mount(); h.mount(); await h.flush(250);
		expect(h.lookups).toHaveLength(1); expect(h.publications).toHaveLength(1);
		h.lookups[0].reject(new Error('offline')); await h.settle();
		expect(h.findIn(0, 'p').children[0]).toBe('Lookup failed');
		expect(h.findIn(1, 'p').children[0]).toBe('Lookup failed');
		h.findIn(1, 'button').props.onClick(); h.render(); await h.flush(250);
		expect(h.lookups).toHaveLength(2); await h.reply(1);
		const publications = h.publications.length;
		h.findIn(1, 'ComboboxControl').props.onChange('7'); h.render();
		expect(h.findIn(0, 'ComboboxControl').props.value).toBe('7');
		expect(h.findIn(1, 'ComboboxControl').props.value).toBe('7');
		expect(h.publications.length).toBe(publications + 1);
		await h.flush(0); expect(h.sends).toHaveLength(1);
		h.sends[0].reject(new Error('offline')); await h.settle();
		h.findIn(1, 'button').props.onClick(); h.render(); await h.flush(0);
		expect(h.sends).toHaveLength(2); expect(h.sends[1].request.data).toEqual(h.sends[0].request.data);
		h.sends[1].resolve(); await h.settle();
		const validations = h.validations.length;
		h.unmount(1); expect(h.validations).toHaveLength(validations);
		expect(h.root.kiriofBuyerCheckout.active).toBe(true);
		h.unmount(0); expect(h.root.kiriofBuyerCheckout.active).toBe(false); expect(h.classes).toEqual([]);
	});
	test('owner unmount aborts lookup and transfers to the remaining Slot without stale results', async () => {
		const h = harness(); h.mount(); h.mount(); await h.flush(250);
		const validations = h.validations.length; h.unmount(0);
		expect(h.lookups[0].init.signal.aborted).toBe(true);
		expect(h.validations.slice(validations).some(value => value.clear)).toBe(false);
		expect(h.root.kiriofBuyerCheckout.active).toBe(true); await h.flush(250);
		expect(h.lookups).toHaveLength(2);
		await h.reply(1, [{ id: 9, text: 'Replacement' }]); await h.reply(0);
		expect(h.findIn(1, 'ComboboxControl').props.options).toEqual([{ value: '9', label: 'Replacement' }]);
		h.choose('9'); await h.flush(0); expect(h.sends).toHaveLength(1);
		h.sends[0].resolve(); await h.settle();
		h.mount(); const searches = h.lookups.length; h.unmount(1); await h.flush(250);
		expect(h.lookups).toHaveLength(searches); expect(h.find('ComboboxControl').props.value).toBe('9');
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
		expect(h.find('ComboboxControl').props.value).toBe('7'); h.choose(null);
		h.choose('7'); h.model.cart.shippingAddress.postcode = '54321'; h.render(); await h.flush(250);
		await h.reply(1, [{ id: 9, text: 'Other' }]); h.choose('9');
		h.model.cart.shippingAddress.postcode = '12345'; h.render(); await h.flush(250); await h.reply(2);
		expect(h.find('ComboboxControl').props.value).toBe('7');
	});
	test('feature detection fails closed and supports ExperimentalOrderMeta', () => {
		for (const missing of ['session', 'update', 'useSelect', 'validation', 'nativeRateStatus', 'nativeCustomerStatus', 'payment', 'plugins']) {
			const h = harness({ missing }); expect(h.root.kiriofBuyerCheckout).toBeUndefined(); expect(h.plugin()).toBeUndefined(); expect(h.classes).toEqual([]);
		}
		expect(harness({ enabled: false }).registeredBlocks()).toHaveLength(0);
		expect(harness({ missing: 'slot' }).registeredBlocks()).toHaveLength(1);
		const experimental = harness({ slot: 'ExperimentalOrderMeta' }); experimental.mount(); expect(experimental.find('ComboboxControl')).toBeDefined();
		const slotOnly = harness({ missing: 'inner' }); slotOnly.mount(); expect(slotOnly.find('ComboboxControl')).toBeDefined();
	});
	test('debounces lookup, validates district, filters bad IDs and publishes canonical selection', async () => {
		const h = harness(); h.model.cart.shippingAddress.postcode = ' 12 345 '; h.mount();
		expect(h.status()).toBe('Loading'); expect(h.find('ComboboxControl').props.disabled).toBe(true);
		await h.flush(0); expect(h.lookups).toHaveLength(0); expect(h.sends).toHaveLength(0);
		await h.flush(250);
		expect(h.lookups[0].url).toBe('/ajax'); expect(h.lookups[0].init.credentials).toBe('same-origin');
		expect(new URLSearchParams(h.lookups[0].init.body).get('term')).toBe('12345');
		expect(new URLSearchParams(h.lookups[0].init.body).get('nonce')).toBe('nonce');
		await h.reply(0, [{ id: 0, text: 'Bad' }, { id: 'abc', text: 'Bad' }, { id: 8, text: '' }, { id: 7, text: 'District Seven' }]);
		expect(h.find('ComboboxControl').props.options).toEqual([{ value: '7', label: 'District Seven' }]);
		h.choose('7'); expect(h.root.kiriofBuyerCheckout.getDestination()).toEqual({ version: 1, district_id: '7', district_label: 'District Seven', postcode: '12345', country: 'ID', address_type: 'shipping' });
		expect(h.validations.some(errors => errors['kiriof-buyer-destination']?.hidden === false)).toBe(true);
	});
	test('sends immutable extension snapshots without taking native shipping ownership', async () => {
		const h = harness(); await ready(h); h.choose('7'); await h.flush(0);
		expect(h.sends).toHaveLength(1);
		const request = h.sends[0].request;
		expect(request.namespace).toBe('kiriminaja-official'); expect(request.overwriteDirtyCustomerData).toBe(false);
		expect(request.data).toEqual({ action: 'sync_checkout', destination: h.root.kiriofBuyerCheckout.getDestination(), payment_method: 'cod', insurance: 1, force_insurance: 0 });
		expect(request.data.shipping_method).toBeUndefined(); expect(Object.isFrozen(request.data)).toBe(true); expect(Object.isFrozen(request.data.destination)).toBe(true);
		expect(h.status()).toBe('Saving'); h.sends[0].resolve(); await h.settle();
		expect(h.validations.at(-1)).toEqual({ clear: 'kiriof-buyer-destination' });
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
		await h.flush(250); await h.reply(); expect(h.find('ComboboxControl').props.value).toBe('7');
		await h.flush(0); expect(h.sends).toHaveLength(1); expect(h.sends[0].request.data.destination.district_label).toBe('District Seven');
		const invalid = harness({ config: { savedDistrictByPostcode: { '12345': { destination_id: 99 } } } });
		await ready(invalid); expect(invalid.find('ComboboxControl').props.value).toBeNull();
	});
	test('aborts obsolete postcode AJAX and ignores even a late successful response', async () => {
		const h = harness(); h.mount(); await h.flush(250);
		h.model.cart.shippingAddress.postcode = '54321'; h.render(); expect(h.lookups[0].init.signal.aborted).toBe(true);
		await h.flush(250); await h.reply(1, [{ id: 9, text: 'New district' }]); await h.reply(0);
		expect(h.find('ComboboxControl').props.options).toEqual([{ value: '9', label: 'New district' }]);
		h.choose('9'); expect(h.root.kiriofBuyerCheckout.getDestination().postcode).toBe('54321');
	});
	test('same postcode with new country invalidates selection and lookup context', async () => {
		const h = harness(); await ready(h); h.choose('7');
		h.model.cart.shippingAddress.country = 'SG'; h.render(); expect(h.find('ComboboxControl')).toBeUndefined();
		expect(h.root.kiriofBuyerCheckout.getDestination().country).toBe('SG'); expect(h.root.kiriofBuyerCheckout.getDestination().district_id).toBe('');
		h.model.cart.shippingAddress.country = 'ID'; h.render(); expect(h.find('ComboboxControl').props.disabled).toBe(true);
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
		expect(h.status()).toBe('Update failed'); expect(h.find('ComboboxControl').props.value).toBe('7'); expect(h.root.kiriofBuyerCheckout.getDestination().district_id).toBe('7');
		h.notify(); await h.flush(0); expect(h.sends).toHaveLength(1);
		h.retry(); await h.flush(0); expect(h.sends).toHaveLength(2); expect(h.sends[1].request.data).toEqual(h.sends[0].request.data);
		h.sends[1].resolve(); await h.settle(); expect(h.validations.at(-1)).toEqual({ clear: 'kiriof-buyer-destination' });
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
	test('filters district labels locally without issuing new search requests', async () => {
		const h = harness(); h.mount(); await h.flush(250);
		await h.reply(0, [{ id: 7, text: 'North district' }, { id: 8, text: 'South district' }]);
		h.find('ComboboxControl').props.onFilterValueChange('south'); h.render();
		expect(h.find('ComboboxControl').props.options).toEqual([{ value: '8', label: 'South district' }]);
		expect(h.lookups).toHaveLength(1);
	});
	test('pagehide unsubscribes and disposes pending updates; unmount aborts lookup and clears validation', async () => {
		const h = harness(); h.mount(); await h.flush(250); expect(h.subscribers.size).toBe(1);
		h.unmount(); expect(h.lookups[0].init.signal.aborted).toBe(true); expect(h.validations.at(-1)).toEqual({ clear: 'kiriof-buyer-destination' });
		h.pagehide(); expect(h.subscribers.size).toBe(0); await h.reply(); await h.flush(0); expect(h.sends).toHaveLength(0);
		const pending = harness(); await ready(pending); pending.choose('7'); pending.pagehide(); await pending.flush(0); expect(pending.sends).toHaveLength(0);
		const inFlight = harness(); await ready(inFlight); inFlight.choose('7'); await inFlight.flush(0); inFlight.model.payment = 'bacs'; inFlight.render(); inFlight.pagehide();
		inFlight.sends[0].resolve(); await inFlight.settle(); await inFlight.flush(0); expect(inFlight.sends).toHaveLength(1);
	});
	test('collection and no-shipping carts render no district and perform no lookup or mutation', async () => {
		for (const mode of ['collection', 'noShipping']) {
			const h = harness(); if (mode === 'collection') h.model.collection = true; else h.model.cart.needsShipping = false;
			h.mount(); await h.flush(250); await h.flush(0); expect(h.find('ComboboxControl')).toBeUndefined(); expect(h.lookups).toHaveLength(0); expect(h.sends).toHaveLength(0);
			expect(h.validations.at(-1)).toEqual({ clear: 'kiriof-buyer-destination' });
		}
	});
});
