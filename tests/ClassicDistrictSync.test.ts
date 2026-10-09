import { describe, expect, test } from 'bun:test';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { happy } from './helpers/ui-runtime';

function runtime() {
	const window = new happy.Window();
	const document = window.document;
	document.body.innerHTML = `<input name="ship_to_different_address" type="checkbox"><input id="billing_country" value="ID"><input id="shipping_country" value="ID"><input id="billing_postcode" value="11111"><input id="shipping_postcode" value="22222"><select id="kiriof_destination_area" name="kiriof_destination_area"><option value="1">Billing</option><option value="3">Newest</option><option value="">Select Option</option></select><select id="kiriof_shipping_destination_area" name="kiriof_shipping_destination_area"><option value="2">Shipping</option></select><input name="kiriof_destination_area_name"><input name="kiriof_shipping_destination_area_name" value="Shipping"><input name="kiriof_checkout_token" value="1"><input name="payment_method" value="cod" checked type="radio"><div class="woocommerce-notices-wrapper"></div><div id="order_review"><div class="shop_table"></div></div>`;
	const listeners = new Map<any, { name: string; selector?: string; callback: any; once?: boolean }[]>();
	const events: string[] = [];
	const requests: any[] = [];
	let blocked = false;
	let insurance = 1;
	const alerts: string[] = [];
	const jquery: any = (selector: any) => {
		const nodes: any[] = typeof selector === 'string' ? [...document.querySelectorAll(selector.replace(/:selected/g, ''))].filter(node => !selector.includes(':selected') || (node as any).selected) : [selector];
		const chain: any = {
			length: nodes.length,
			val(value?: any) { if (!arguments.length) return nodes[0]?.value; nodes.forEach(node => { node.value = value; }); return chain; },
			attr(key: string) { return nodes[0]?.getAttribute(key); },
			data(key: string, value?: any) { if (arguments.length === 1) return nodes[0]?.[key]; nodes.forEach(node => { node[key] = value; }); return chain; },
			find(css: string) { return jquery(css.includes(':selected') ? [...(nodes[0]?.querySelectorAll('option') || [])].find((option: any) => option.selected) : nodes[0]?.querySelector(css)); },
			text() { return nodes[0]?.textContent || ''; },
			append(html: string) { nodes.forEach(node => node.insertAdjacentHTML('beforeend', html)); return chain; },
			is(css: string) { return nodes[0]?.matches(css); },
			off(name: string, css?: string) { nodes.forEach(node => listeners.set(node, (listeners.get(node) || []).filter(item => !(item.name === name && item.selector === css)))); return chain; },
			on(name: string, css: any, callback?: any) { nodes.forEach(node => listeners.set(node, [...(listeners.get(node) || []), { name, selector: callback ? css : undefined, callback: callback || css }])); return chain; },
			one(name: string, callback: any) { nodes.forEach(node => listeners.set(node, [...(listeners.get(node) || []), { name, callback, once: true }])); return chain; },
			trigger(name: string) {
				events.push(name);
				nodes.forEach(node => {
					for (const target of [node, document]) {
						for (const item of [...(listeners.get(target) || [])]) {
							if (item.name.split('.')[0] === name && (!item.selector || node.matches?.(item.selector))) {
								if (item.once) listeners.set(target, (listeners.get(target) || []).filter(value => value !== item));
								item.callback.call(node);
							}
						}
					}
				});
				return chain;
			},
			block() { blocked = true; return chain; },
			unblock() { blocked = false; return chain; },
		};
		return chain;
	};
	jquery.ajax = (options: any) => { requests.push(options); options.beforeSend(); return { abort() { throw new Error('Server mutations must not be aborted'); } }; };
	const config: any = { isCheckout: true, isCart: false, billingCountry: 'ID', shippingCountry: 'US', i18n: { selectOption: 'Select Option' }, ajaxUrl: '/fallback', destinationNonce: 'fallback' };
	let insuranceCallbacks = 0;
	const context: any = { document, window, console: { warn() {} }, alert: (value: string) => alerts.push(value), jQuery: jquery, kiriofBillingAddressConfig: config, kiriofAjax: { ajaxurl: '/root-ajax', destination_nonce: 'root-nonce' }, kiriofUsesClassicCheckout: () => false, kiriofGetClassicInsuranceValue: () => insurance, kiriofCodInsurance: () => { insuranceCallbacks++; } };
	const source = readFileSync('assets/buyer/js/checkout/classic-district.js', 'utf8');
	runInNewContext(source.slice(0, source.indexOf('function getSearchAreaKelurahan()')), context);
	const payment = readFileSync('assets/buyer/js/checkout/shipping-payment.js', 'utf8');
	runInNewContext(payment.slice(payment.indexOf('function kiriofChangeDifferentAddress()'), payment.indexOf('function kiriofGetClassicInsuranceValue()')), context);
	context.changeDistrict();
	context.kiriofChangeDifferentAddress();
	const finish = (index: number, response: any = { success: true, data: { code: 200 } }) => { requests[index].success(response); requests[index].complete(); };
	return { document, context, config, requests, events, alerts, get blocked() { return blocked; }, get insuranceCallbacks() { return insuranceCallbacks; }, setInsurance: (value: number) => { insurance = value; }, jq: jquery, finish, close: () => window.happyDOM.abort() };
}

describe('legacy Classic district mutation queue', () => {
	test('checkbox toggles serialize writes and publish native update only after latest immutable intent commits', () => {
		const h = runtime();
		try {
			h.jq('#kiriof_destination_area').trigger('change');
			h.jq('[name="ship_to_different_address"]').val('on');
			const checkbox: any = h.document.querySelector('[name="ship_to_different_address"]');
			checkbox.checked = true;
			h.jq(checkbox).trigger('change');
			checkbox.checked = false;
			h.jq('#kiriof_destination_area').val('3');
			h.jq('#billing_postcode').val('33333');
			h.setInsurance(0);
			h.jq(checkbox).trigger('change');
			expect(h.requests).toHaveLength(1);
			expect(h.requests[0].data).toMatchObject({ val: '1', different_address: 0, postcode: '11111', insurance: 1, nonce: 'root-nonce' });
			expect(h.requests[0].url).toBe('/root-ajax');
			expect(h.jq('[name="kiriof_checkout_token"]').val()).toBe('');
			h.finish(0, { success: false, data: { code: 400, msg: 'Obsolete error' } });
			expect(h.requests).toHaveLength(2);
			expect(h.requests[1].data).toMatchObject({ val: '3', text: 'Newest', different_address: 0, postcode: '33333', insurance: 0 });
			expect(h.events.filter(name => name === 'update_checkout')).toHaveLength(0);
			expect(h.document.querySelector('.woocommerce-notices-wrapper')?.textContent).toBe('');
			expect(h.blocked).toBe(true);
			expect(h.jq('[name="kiriof_shipping_destination_area_name"]').val()).toBe('Shipping');
			h.finish(1);
			expect(h.events.filter(name => name === 'update_checkout')).toHaveLength(1);
			expect(h.events.filter(name => name === 'kiriof:classic-district-synced')).toHaveLength(1);
			expect(h.blocked).toBe(false);
			expect(h.jq('[name="kiriof_checkout_token"]').val()).toBe('1');
			h.jq(h.document.body).trigger('updated_checkout');
			expect(h.insuranceCallbacks).toBe(1);
		} finally { h.close(); }
	});

	test('clear intent stays blank after stale completion and final error does not publish rates', () => {
		const h = runtime();
		try {
			h.jq('#kiriof_destination_area').data('kiriofSelectedDistrictText', 'Old cached label').trigger('change');
			h.jq('#kiriof_destination_area').val('').trigger('change');
			expect(h.jq('[name="kiriof_destination_area_name"]').val()).toBe('');
			h.finish(0);
			expect(h.requests[1].data.val).toBe('');
			expect(h.requests[1].data.text).toBe('');
			h.requests[1].error({ status: 503 }, 'error', 'unavailable');
			h.requests[1].complete();
			expect(h.jq('[name="kiriof_destination_area_name"]').val()).toBe('');
			expect(h.jq('[name="kiriof_checkout_token"]').val()).toBe('');
			expect(h.events).not.toContain('update_checkout');
			expect(h.alerts).toHaveLength(1);
			expect(h.blocked).toBe(false);
			h.jq('#kiriof_destination_area').val('3').data('kiriofSelectedDistrictText', '').trigger('change');
			h.finish(2);
			expect(h.jq('[name="kiriof_checkout_token"]').val()).toBe('1');
		} finally { h.close(); }
	});

	test('country config applies only to missing inputs, never explicit foreign or empty fields', () => {
		const h = runtime();
		try {
			h.jq('#billing_country').val('');
			expect(h.context.kiriofGetClassicAddressCountry('billing')).toBe('');
			h.jq('#billing_country').val('US');
			h.jq('#kiriof_destination_area').trigger('change');
			expect(h.requests).toHaveLength(0);
			h.document.querySelector('#billing_country')?.remove();
			expect(h.context.kiriofGetClassicAddressCountry('billing')).toBe('ID');
			h.jq('#kiriof_destination_area').trigger('change');
			expect(h.requests[0].data.country).toBe('ID');
			h.document.querySelector('#shipping_country')?.remove();
			expect(h.context.kiriofGetClassicAddressCountry('shipping')).toBe('US');
			h.config.billingCountry = '';
			expect(h.context.kiriofGetClassicAddressCountry('billing')).toBe('');
		} finally { h.close(); }
	});
});
