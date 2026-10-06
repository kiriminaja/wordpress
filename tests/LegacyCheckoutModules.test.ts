import { describe, expect, test } from 'bun:test';
import { readFileSync } from 'node:fs';
import { createContext, runInContext } from 'node:vm';
import { legacyCheckoutModules } from './helpers/legacy-checkout-source';

function harness(ownsDistrict = false) {
	const handlers: { event: string; callback: (...args: any[]) => any }[] = [];
	const ready: (() => void)[] = [];
	const timers: { callback: () => void; delay: number }[] = [];
	const chain: any = {
		length: 0,
		on(event: string, ...args: any[]) { handlers.push({ event, callback: args.at(-1) }); return chain; },
		one(event: string, ...args: any[]) { handlers.push({ event, callback: args.at(-1) }); return chain; },
		off() { return chain; },
		ready(callback: () => void) { ready.push(callback); return chain; },
		val() { return ''; },
		attr() { return ''; },
		prop() { return chain; },
		closest() { return chain; },
		find() { return chain; },
		append() { return chain; },
		toggleClass() { return chain; },
		toggle() { return chain; },
		each() { return chain; },
	};
	const jquery: any = () => chain;
	jquery.fn = {};
	jquery.each = (values: any[], callback: any) => values.forEach((value, index) => callback(index, value));
	const config = {
		isCheckout: true, savedDistrictByPostcode: { '12345': { id: '42', name: 'District' } },
		savedCheckoutPostcode: '12345', storeApiNonce: 'nonce', storeApiUpdateCustomerUrl: '/wc/store/v1/cart/update-customer',
	};
	const setTimeout = (callback: () => void, delay: number) => { timers.push({ callback, delay }); return timers.length; };
	const root: any = { kiriofBillingAddressConfig: config, kiriofClassicCheckoutConfig: { enabled: true, ownsDistrict }, setTimeout };
	const context: any = createContext({
		window: root, document: { body: {}, querySelector: () => ({}) }, jQuery: jquery,
		setTimeout, clearTimeout() {}, localStorage: { getItem: () => null, removeItem() {} },
	});
	return { context, handlers, ready, timers, config };
}

function load(h: ReturnType<typeof harness>) {
	for (const { path, source } of legacyCheckoutModules) runInContext(source, h.context, { filename: path });
}

describe('separate legacy checkout browser modules', () => {
	test('Choices/native labels never call Select2 data without a field instance', () => {
		const h=harness();load(h);let calls=0;
		const field:any={val:()=> '123',data:()=>null,find:()=>({text:()=> 'Sari Harjo, Ngaglik'}),attr:()=> 'kiriof_destination_area',selectWoo:()=>{calls++;throw new Error('Select2 data on uninitialized field');}};
		expect(h.context.kiriofGetClassicDistrictLabel(field)).toBe('Sari Harjo, Ngaglik');expect(calls).toBe(0);
		field.data=(key:string)=>key==='select2'?{}:null;field.selectWoo=()=>{calls++;return [{text:'SelectWoo village'}];};
		expect(h.context.kiriofGetClassicDistrictLabel(field)).toBe('SelectWoo village');expect(calls).toBe(1);
	});
	test('actual SelectWoo request and result callbacks accept names, postcodes and legacy response envelopes', () => {
		const h = harness();
		load(h);
		const settings: any[] = [];
		const field: any = {
			length: 1, data: () => null, closest: () => field,
			off: () => field, on: () => field,
			val: () => '', find: () => ({ text: () => '' }),
		};
		const fields: any = { length: 1, each(callback: any) { callback.call(field); return fields; } };
		const jquery: any = (selector: any) => selector === field ? field : fields;
		jquery.fn = { selectWoo(options: any) { settings.push(options); } };
		jquery.map = (rows: any[], callback: any) => rows.map(callback).filter(value => value != null);
		h.context.jQuery = jquery;
		h.config.ajaxUrl = '/wp-admin/admin-ajax.php';
		h.config.nonce = 'district-nonce';
		h.config.i18n = { selectOption: 'Select Option' };
		runInContext('kiriofRestoreClassicDistrictSelections = function() {}; getSearchAreaKelurahan();', h.context);
		expect(settings).toHaveLength(1);
		const options = settings[0];
		expect(options.width).toBe('100%');
		expect(options.dropdownParent).toBe(fields);
		expect(options.dropdownCssClass).toBe('kiriof-classic-district-dropdown');
		expect(options.minimumInputLength).toBe(3);
		for (const [params, term] of [[{ term: '  Gambir  ' }, 'Gambir'], [{ term: ' 10110 ' }, '10110'], [{ q: ' Jakarta ' }, 'Jakarta']] as const) {
			expect(options.ajax.data(params)).toEqual({ action: 'kiriminaja_subdistrict_search', nonce: 'district-nonce', term, data: { search: term, term } });
		}
		expect(options.ajax.url).toBe('/wp-admin/admin-ajax.php');
		expect(options.ajax.type).toBe('POST');
		const rows = [{ id: 123, text: 'Gambir, Jakarta' }];
		for (const response of [rows, { success: true, data: rows }, { results: rows }, { success: true, data: { results: rows } }, { data: { data: rows } }]) {
			expect(options.ajax.processResults(response)).toEqual({ results: rows });
		}
		for (const response of [null, {}, { success: false, data: rows }, { success: true, data: { code: '401', message: 'Error' } }, [null, { message: 'Error' }, { id: 2 }]]) {
			expect(options.ajax.processResults(response)).toEqual({ results: [] });
		}
	});
	test('dependency-order evaluation retains globals and registers events before a single ready boot', () => {
		const h = harness();
		const globals = [
			['kiriofUsesClassicCheckout'],
			['kiriofInitBlockCheckoutCompatibility'],
			['changeDistrict', 'getSearchAreaKelurahan', 'kiriofSyncClassicAddressFields', 'kiriofRestoreClassicDistrictSelections', 'kiriofGetCurrentPostcodeKey'],
			['kiriofCodInsurance', 'kiriofHandleCodInsurance', 'kiriofChangeCodPayment', 'kiriofChangeDifferentAddress', 'kiriofIsBlockCheckoutContext', 'kiriofScheduleClassicShippingMethodSelectInit'],
			[],
		];
		legacyCheckoutModules.forEach(({ path, source }, index) => {
			expect(() => runInContext(source, h.context, { filename: path })).not.toThrow();
			for (const name of globals[index]) expect(typeof h.context[name]).toBe('function');
			expect(h.ready.length).toBe(index === 4 ? 1 : 0);
		});
		expect(h.context.kiriofBillingAddressConfig).toBe(h.config);
		expect(h.context.kiriofSavedDistrictByPostcode).toBe(h.config.savedDistrictByPostcode);
		expect(h.context.kiriofSavedCheckoutPostcode).toBe('12345');
		expect(h.context.kiriofStoreApiNonce).toBe('nonce');
		expect(h.context.kiriofStoreApiUpdateCustomerUrl).toBe(h.config.storeApiUpdateCustomerUrl);
		expect(h.context.kiriofUpdatingCheckoutLock).toBe(false);
		expect(h.context.kiriofFeeRefreshRequest).toBeNull();
		expect(h.handlers.map(handler => handler.event)).toEqual([
			'country_to_state_changing.kiriofClassicAddress updated_checkout.kiriofClassicAddress',
			'change.kiriofClassicAddress',
			'updated_checkout', 'updated_checkout',
			'init_checkout.kiriofClassicShippingMethodSelect updated_checkout.kiriofClassicShippingMethodSelect updated_cart_totals.kiriofClassicShippingMethodSelect updated_shipping_method.kiriofClassicShippingMethodSelect wc_fragments_refreshed.kiriofClassicShippingMethodSelect',
			'change.kiriofClassicShippingMethodSelect',
		]);
		expect(() => h.ready[0]()).not.toThrow();
		expect(h.timers.map(timer => timer.delay)).toEqual([50, 250, 750, 300, 1500]);
		expect(h.handlers.some(handler => handler.event === 'change.kiriofClassicDistrict')).toBe(true);
		expect(h.handlers.some(handler => handler.event === 'change.kiriofPaymentRefresh')).toBe(true);
		expect(h.handlers.some(handler => handler.event === 'change.kiriofDifferentAddress')).toBe(true);
		for (const timer of h.timers) expect(() => timer.callback()).not.toThrow();
	});

	test('entry boot invokes module globals in order and Classic ownership only synchronizes native fields', () => {
		for (const ownsDistrict of [false, true]) {
			const h = harness(ownsDistrict);
			load(h);
			const calls: string[] = [];
			const boot = ['kiriofSyncClassicAddressFields', 'getSearchAreaKelurahan', 'kiriofRestoreClassicDistrictSelections', 'changeDistrict', 'kiriofScheduleClassicShippingMethodSelectInit', 'kiriofInitBlockCheckoutCompatibility', 'kiriofChangeCodPayment', 'kiriofChangeDifferentAddress'];
			h.context.recordBoot = (name: string) => { calls.push(name); };
			for (const name of boot) runInContext(`${name} = function() { recordBoot('${name}'); };`, h.context);
			h.ready[0]();
			expect(calls).toEqual(ownsDistrict ? boot.slice(0, 1) : boot);
			expect(h.timers.map(timer => timer.delay)).toEqual(ownsDistrict ? [] : [300, 1500]);
		}
	});

	test('WordPress registers the same explicit filemtime chain and localizes state before initialization', () => {
		const enqueue = readFileSync(new URL('../inc/Base/Enqueue.php', import.meta.url), 'utf8');
		const template = readFileSync(new URL('../templates/front/form-billing-address.php', import.meta.url), 'utf8');
		expect(enqueue).toContain("foreach ( array( 'state', 'blocks-compatibility', 'classic-district', 'shipping-payment' ) as $module )");
		expect(enqueue).toContain("$relative_path = 'assets/wp/js/checkout/' . $module . '.js';");
		expect(enqueue).toContain("wp_register_script( $handle, $this->plugin_url . $relative_path, $legacy_dependencies, (string) filemtime( KIRIOF_DIR . $relative_path ), array( 'in_footer' => true ) );");
		expect(enqueue).toContain('$legacy_dependencies = array( $handle );');
		expect(enqueue).toContain("'kiriof-form-billing-address',\n            $this->plugin_url . 'assets/wp/js/form-billing-address.js',\n            $legacy_dependencies,");
		expect(template).toContain("wp_localize_script(\n            'kiriof-checkout-state',\n            'kiriofBillingAddressConfig',");
		expect(template).not.toContain("wp_localize_script(\n            'kiriof-form-billing-address'");
		const entry = legacyCheckoutModules.at(-1)!.source;
		expect(entry.split('\n').length).toBeLessThanOrEqual(80);
		expect(entry).not.toContain('var kiriofBillingAddressConfig');
		for (const module of legacyCheckoutModules.slice(0, -1)) expect(module.source).not.toContain('jQuery(document).ready');
	});
});
