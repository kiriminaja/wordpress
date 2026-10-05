import { describe, expect, test } from 'bun:test';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { happy } from './helpers/ui-runtime';

const source = readFileSync(new URL('../assets/wp/js/form-billing-address.js', import.meta.url), 'utf8');
const start = source.indexOf('function kiriofGetClassicAddressCountry(addressType)');
const end = source.indexOf('function kiriofRestoreClassicDistrictSelections()', start);

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
	runInNewContext(source.slice(start, end), context);
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
