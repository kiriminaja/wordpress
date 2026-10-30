import { describe, expect, test } from 'bun:test';
import { runInNewContext } from 'node:vm';
import { happy } from './helpers/ui-runtime';
import { legacyCheckoutSlice } from './helpers/legacy-checkout-source';
import { syncDistrictAddress } from '../src/buyer/classic/district-address';

const source = legacyCheckoutSlice(
	'assets/buyer/js/checkout/classic-district.js',
	'function kiriofGetClassicAddressCountry(addressType)',
	'function kiriofRestoreClassicDistrictSelections()',
);

function runtime(classic = false) {
	const window = new happy.Window();
	const document = window.document;
	const handlers: { names: string; callback: () => void }[] = [];
	for (const scope of ['billing', 'shipping']) {
		for (const field of ['country', 'city', 'state', 'postcode', 'company']) {
			const row = document.createElement('p');
			row.id = `${scope}_${field}_field`;
			row.className = 'form-row address-field validate-required';
			row.innerHTML = `<input id="${scope}_${field}" name="${scope}_${field}" required aria-required="true" value="${field === 'country' ? 'ID' : 'Native value'}">`;
			document.body.append(row);
		}
		const id = scope === 'billing' ? 'kiriof_destination_area' : 'kiriof_shipping_destination_area';
		const row = document.createElement('p');
		row.className = 'form-row';
		row.innerHTML = `<label>District<span class="optional">optional</span></label><select id="${id}"></select>`;
		document.body.append(row);
	}
	const jQuery: any = (selector: any) => {
		const nodes: any[] = typeof selector === 'string' ? [...document.querySelectorAll(selector)] : [selector];
		const chain: any = {
			length: nodes.length,
			val: () => nodes[0]?.value,
			prop(key: string, value: any) { nodes.forEach(node => { node[key] = value; }); return chain; },
			attr(key: string, value: any) { nodes.forEach(node => node.setAttribute(key, value)); return chain; },
			closest: (selector: string) => jQuery(nodes[0]?.closest(selector)),
			find: (selector: string) => jQuery(nodes[0]?.querySelector(selector) || document.createElement('span')),
			toggleClass(key: string, active: boolean) { nodes.forEach(node => node.classList.toggle(key, active)); return chain; },
			toggle(active: boolean) { nodes.forEach(node => { node.hidden = !active; }); return chain; },
			append(html: string) { nodes.forEach(node => node.insertAdjacentHTML('beforeend', html)); return chain; },
			on(names: string, selector: any, callback?: () => void) { handlers.push({ names, callback: callback || selector }); return chain; },
		};
		return chain;
	};
	jQuery.each = (values: any[], callback: any) => values.forEach((value, index) => callback(index, value));
	const config = { isCheckout: true };
	let blocks = false;
	const context: any = { document, jQuery, kiriofBillingAddressConfig: config, kiriofIsBlockCheckoutContext: () => blocks, kiriofUsesClassicCheckout: () => classic };
	runInNewContext(source, context);
	return { document, handlers, config, setBlocks: (value: boolean) => { blocks = value; }, sync: context.kiriofSyncClassicAddressFields, close: () => window.happyDOM.abort() };
}

describe('legacy Classic native address fields', () => {
	for (const classic of [false, true]) {
		test(`country changes preserve native locale visibility, required attributes and values (adapter ${classic})`, () => {
			const h = runtime(classic);
			try {
				for (const [billing, shipping] of [['ID', 'US'], ['US', 'ID'], ['ID', 'ID'], ['DE', 'GB']]) {
					(h.document.querySelector('#billing_country') as any).value = billing;
					(h.document.querySelector('#shipping_country') as any).value = shipping;
					// Simulate WooCommerce locale changes before either country/update event.
					(h.document.querySelector('#billing_state_field') as any).hidden = billing === 'DE';
					(h.document.querySelector('#shipping_postcode') as any).required = shipping !== 'ID';
					const original = [...h.document.querySelectorAll('.address-field')].map(node => node.outerHTML);
					for (const handler of h.handlers) handler.callback();
					const current = [...h.document.querySelectorAll('.address-field')].map(node => node.outerHTML);
					expect(current).toEqual(original);
					if (!classic) {
						for (const [scope, country] of [['billing', billing], ['shipping', shipping]]) {
							const id = scope === 'billing' ? 'kiriof_destination_area' : 'kiriof_shipping_destination_area';
							const district: any = h.document.querySelector(`#${id}`);
							expect(district.required).toBe(country === 'ID');
							expect(district.disabled).toBe(country !== 'ID');
							expect(district.closest('.form-row').classList.contains('kiriof-classic-address-hidden')).toBe(country !== 'ID');
						}
					}
				}
			} finally { h.close(); }
		});
  }
});

describe('FunnelKit conditional address fields', () => {
  test('removed fields get hidden backing values only in domestic FunnelKit and clear on foreign country', () => {
    const window = new happy.Window();
    try {
      window.document.body.innerHTML = '<form><input name="_wfacp_post_id" value="1248" type="hidden"><input name="shipping_country" id="shipping_country" value="ID"><select name="kiriof_shipping_destination_area"><option value="31554">Sari Harjo, Ngaglik, Sleman, DI Yogyakarta, 55581</option></select></form>';
      const form: any = window.document.querySelector('form');
      const sync = () => syncDistrictAddress(form, { YO: 'Daerah Istimewa Yogyakarta' });
      sync(); sync();
      expect(form.querySelectorAll('[data-kiriof-derived-field]')).toHaveLength(3);
      expect(form.querySelector('#shipping_city').value).toBe('Sleman');
      expect(form.querySelector('#shipping_state').value).toBe('YO');
      expect(form.querySelector('#shipping_postcode').value).toBe('55581');
      expect(form.querySelector('#shipping_postcode').type).toBe('hidden');
      form.querySelector('#shipping_country').value = 'US'; sync();
      expect(form.querySelectorAll('[data-kiriof-derived-field]')).toHaveLength(0);
      expect(form.querySelector('[name="kiriof_shipping_derived_address"]')).toBeNull();
      form.insertAdjacentHTML('beforeend', '<input name="shipping_city" id="shipping_city" value="Manual city">');
      form.querySelector('#shipping_country').value = 'ID'; sync();
      expect(form.querySelector('#shipping_city').value).toBe('Manual city');
      expect(form.querySelectorAll('[name="shipping_city"]')).toHaveLength(1);
      form.querySelector('select').value = ''; sync();
      expect(form.querySelector('#shipping_postcode').value).toBe('');
    } finally { window.happyDOM.abort(); }
  });
	for (const [province, state] of [['DI Yogyakarta', 'YO'], ['D.I. Yogyakarta', 'YO'], ['DKI Jakarta', 'JK']]) {
		test(`explicit domestic choice synchronizes visible hierarchy for ${province} without adopting hidden markers`, () => {
			const window = new happy.Window();
			try {
				window.document.body.innerHTML = `<form><input name="billing_country" value="ID"><input name="billing_city" value="Yogyakarta"><input name="billing_state" value="JB"><input name="billing_postcode" value="55222"><select name="kiriof_destination_area"><option value="31554" selected>Sari Harjo, Ngaglik, Sleman, ${province}, 55581</option></select><input name="shipping_country" value="ID"><input name="shipping_city" value="Other city"><input name="shipping_state" value="JB"><input name="shipping_postcode" value="40111"><select name="kiriof_shipping_destination_area"><option value="31554" selected>Sari Harjo, Ngaglik, Sleman, ${province}, 55581</option></select></form>`;
				const form: any = window.document.querySelector('form');
				const value = (name: string) => form.querySelector(`[name="${name}"]`).value;
				const states = { YO: 'Daerah Istimewa Yogyakarta', JK: 'Daerah Khusus Ibukota Jakarta' };
				let changes = 0;
				form.addEventListener('change', () => changes++);
				syncDistrictAddress(form, states);
				expect(value('billing_postcode')).toBe('55222');
				syncDistrictAddress(form, states, 'billing');
				expect(value('billing_city')).toBe('Sleman');
				expect(value('billing_state')).toBe(state);
				expect(value('billing_postcode')).toBe('55581');
				expect(value('shipping_city')).toBe('Other city');
				expect(value('shipping_state')).toBe('JB');
				expect(value('shipping_postcode')).toBe('40111');
				expect(form.querySelector('[name="kiriof_billing_derived_address"]')).toBeNull();
				for (const field of ['city', 'state', 'postcode']) form.querySelector(`[name="billing_${field}"]`).value = 'Manual';
				syncDistrictAddress(form, states);
				for (const field of ['city', 'state', 'postcode']) expect(value(`billing_${field}`)).toBe('Manual');
				form.querySelector('[name="billing_country"]').value = 'US';
				syncDistrictAddress(form, states, 'billing');
				expect(value('billing_postcode')).toBe('Manual');
				expect(changes).toBe(0);
			} finally { window.happyDOM.abort(); }
		});
  }

	test('restores only hidden domestic fields from the selected hierarchy without change events', () => {
		const window = new happy.Window();
		try {
			window.document.body.innerHTML = `<form class="checkout"><input name="billing_country" value="ID"><select name="kiriof_destination_area"><option value="123" selected>Bandar Selamat, Medan Tembung, Medan, Sumatera Utara, 20223</option></select><p id="billing_city_field" class="fkcf-hidden"><input name="billing_city"></p><p id="billing_state_field" class="fkcf-hidden"><select name="billing_state"><option value="">Select</option><option value="SU">Sumatera Utara</option></select></p><p id="billing_postcode_field" class="fkcf-hidden"><input name="billing_postcode"></p><input name="shipping_country" value="US"><p class="fkcf-hidden"><input name="shipping_postcode" value="10001"></p></form>`;
			const form: any = window.document.querySelector('form');
			const value = (name: string) => form.querySelector(`[name="${name}"]`).value;
			let changes = 0;
			form.addEventListener('change', () => changes++);
			syncDistrictAddress(form);
			expect(value('billing_city')).toBe('Medan');
			expect(value('billing_state')).toBe('SU');
			expect(value('billing_postcode')).toBe('20223');
			expect(value('kiriof_billing_derived_address')).toBe('city,state,postcode');
			expect(value('shipping_postcode')).toBe('10001');
			expect(form.querySelector('[name="kiriof_shipping_derived_address"]')).toBeNull();
			form.querySelector('[name="billing_postcode"]').value = '';
			syncDistrictAddress(form);
			expect(value('billing_postcode')).toBe('20223');
			window.document.querySelector('#billing_city_field')!.classList.remove('fkcf-hidden');
			form.querySelector('[name="billing_city"]').value = 'Manual';
			syncDistrictAddress(form);
			expect(value('billing_city')).toBe('Manual');
			expect(value('kiriof_billing_derived_address')).toBe('state,postcode');
			form.querySelector('[name="billing_country"]').value = 'SG';
			form.querySelector('[name="billing_postcode"]').value = '123456';
			syncDistrictAddress(form);
			expect(value('billing_postcode')).toBe('123456');
			expect(form.querySelector('[name="kiriof_billing_derived_address"]')).toBeNull();
			expect(changes).toBe(0);
		} finally { window.happyDOM.abort(); }
	});

	test('replaces stale hidden hierarchy after district changes and rejects malformed labels', () => {
		const window = new happy.Window();
		try {
			window.document.body.innerHTML = `<form><input name="shipping_country" value="ID"><select name="kiriof_shipping_destination_area"><option value="123" selected>Village, District, Medan, Sumatera Utara, 20223</option><option value="456">Village, District, Bandung, Jawa Barat, 40111</option><option value="789">Malformed 12345</option></select><p class="fkcf-hidden"><input name="shipping_city" value="Old"><input name="shipping_state"><input name="shipping_postcode" value="11111"></p></form>`;
			const form: any = window.document.querySelector('form');
			const states = { SU: 'Sumatera Utara', JB: 'Jawa Barat' };
			syncDistrictAddress(form, states);
			expect(form.querySelector('[name="shipping_state"]').value).toBe('SU');
			form.querySelector('select').value = '456';
			syncDistrictAddress(form, states);
			expect(form.querySelector('[name="shipping_city"]').value).toBe('Bandung');
			expect(form.querySelector('[name="shipping_state"]').value).toBe('JB');
			expect(form.querySelector('[name="shipping_postcode"]').value).toBe('40111');
			form.querySelector('select').value = '789';
			form.querySelector('[name="shipping_postcode"]').value = '';
			syncDistrictAddress(form, states);
			expect(form.querySelector('[name="shipping_postcode"]').value).toBe('');
		} finally { window.happyDOM.abort(); }
	});
});

describe('legacy synchronization boundaries', () => {
	test('legacy synchronization does not run for account or Blocks forms', () => {
		const h = runtime();
		try {
			const original = h.document.body.innerHTML;
			h.config.isCheckout = false;
			h.sync();
			expect(h.document.body.innerHTML).toBe(original);
			h.config.isCheckout = true;
			h.setBlocks(true);
			h.sync();
			expect(h.document.body.innerHTML).toBe(original);
		} finally { h.close(); }
	});
});
