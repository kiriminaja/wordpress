'use strict';
// Run both production scripts, with a Deferred that adopts returned rejection promises.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '../..');
const plain = value => JSON.parse(JSON.stringify(value));
const catalog = [
	{ code: 'jne', name: 'JNE', services: [{ code: 'REG' }, { code: 'YES' }] },
	{ code: 'jnt', name: 'J&T', services: [{ code: 'EZ' }] },
	{ code: 'mystery', name: 'Mystery', services: [] }
];
const all = { jne: ['REG', 'YES'], jnt: ['EZ'], mystery: ['*'] };
class Element {
	constructor(tag) { this.tag = tag; this.children = []; this.events = {}; this.classes = new Set(); this.attributes = {}; this._text = ''; this.classList = { add: name => this.classes.add(name) }; }
	set textContent(value) { this._text = value; this.children = []; }
	get textContent() { return this._text; }
	appendChild(child) { this.children.push(child); return child; }
	setAttribute(name, value) { this.attributes[name] = value; }
	addEventListener(name, callback) { this.events[name] = callback; }
}
function boot(persisted = {}, finished = false) {
	const elements = new Map();
	const requests = [];
	const queue = [];
	const get = selector => {
		if (!elements.has(selector)) { elements.set(selector, new Element('div')); }
		return elements.get(selector);
	};
	get('[data-kiriof-onboarding]').data = { 'current-step': finished ? 'complete' : 'couriers', 'account-complete': '1' };
	get('[data-step-target="address"]').classes.add('is-done');
	if (Object.keys(persisted).length) { get('[data-step-target="couriers"]').classes.add('is-done'); }
	if (finished) { get('[data-step-target="shipping"]').classes.add('is-done'); }
	function $(selector) {
		const nodes = typeof selector === 'string' ? selector.split(', ').map(get) : [selector];
		const el = nodes[0];
		return { 0: el, length: nodes.length,
			data: key => (el.data || {})[key],
			text(value) { if (value === undefined) { return el.textContent; } nodes.forEach(node => { node.textContent = value; }); return this; },
			html(value) { if (value === undefined) { return el.textContent; } el.textContent = value; return this; },
			val: () => el.value || '',
			prop(name, value) { nodes.forEach(node => { node[name] = value; }); return this; },
			attr(name, value) { el.setAttribute(name, value); return this; },
			toggleClass(name, enabled) { nodes.forEach(node => { if (enabled) { node.classes.add(name); } else { node.classes.delete(name); } }); return this; },
			addClass(name) { el.classes.add(name); return this; },
			removeClass(name) { el.classes.delete(name); return this; },
			hasClass: name => el.classes.has(name),
			toggle() { return this; }, find(selector) { return selector === '.dashicons' ? $(new Element('span')) : { length: 0 }; },
			on(name, callback) { nodes.forEach(node => { node.events[name] = callback; }); return this; }
		};
	}
	function deferred() {
		let state = 'pending';
		let value;
		const callbacks = { done: [], fail: [] };
		function add(type, fn) {
			if (state === type) { fn(value); } else if (state === 'pending') { callbacks[type].push(fn); }
			return api;
		}
		function settle(type, result) {
			if (state !== 'pending') { return api; }
			state = type; value = result; callbacks[type].forEach(fn => fn(value)); return api;
		}
		const api = {
			done: fn => add('done', fn), fail: fn => add('fail', fn),
			always(fn) { api.done(fn); api.fail(fn); return api; },
			then(success, failure) {
				const child = deferred();
				function transform(fn, reject) {
					return input => queue.push(() => {
						if (!fn) { child[reject ? 'reject' : 'resolve'](input); return; }
						try {
							const result = fn(input);
							if (result && typeof result.then === 'function') { result.done(child.resolve).fail(child.reject); }
							else { child.resolve(result); }
						} catch (error) { child.reject(error); }
					});
				}
				api.done(transform(success, false)).fail(transform(failure, true)); return child;
			},
			resolve: result => settle('done', result), reject: result => settle('fail', result),
			promise() { return api; }, abort() { return api.reject(); }
		};
		return api;
	}
	$.Deferred = deferred;
	$.ajax = options => { const request = deferred(); request.options = plain(options); requests.push(request); return request; };
	const strings = { enabledCount: '%1$s of %2$s', allServices: 'All services', enableCourier: 'Enable %s', enableService: '%1$s %2$s', loading: 'Loading', selectService: 'Select a service', saved: 'Saved' };
	const document = { createElement: tag => new Element(tag), getElementById: () => null };
	const window = { kiriofCourierServicesI18n: strings };
	const context = { window, document, jQuery: $, kiriofCourierServicesI18n: strings, kiriofOnboarding: { nonce: 'nonce', networkError: 'Network error', saveFailed: 'Save failed' } };
	for (const file of ['kj-courier-services.js', 'kj-onboarding.js']) { vm.runInNewContext(fs.readFileSync(path.join(root, 'assets/admin/js', file), 'utf8'), context); }
	const flush = () => { while (queue.length) { queue.shift()(); } };
	let submissions = 0;
	function click(selector, step) {
		const event = { prevented: false, preventDefault() { this.prevented = true; } };
		get(selector).events.click.call(step ? { data: { 'step-target': step } } : get(selector), event);
		if (!event.prevented) { submissions++; }
		assert.equal(event.prevented, true, 'wizard clicks must prevent default form submission');
	}
	function load() {
		requests.at(-1).resolve({ success: true, data: { status: 200, data: { couriers: catalog, service_selection: persisted } } }); flush();
	}
	return { get, requests, click, load, flush, submissions: () => submissions,
		active: step => get('[data-step-panel="' + step + '"]').classes.has('is-active') };
}
for (const failure of [null, 'logical', 'transport', 'outer']) {
	let app = boot();
	assert.equal(app.get('[data-kiriof-continue]').disabled, true);
	app.click('[data-kiriof-continue]');
	assert.equal(app.requests.length, 1, 'cannot save before the catalog is ready');
	app.load();
	assert.equal(app.get('[data-kiriof-continue]').disabled, true);
	app.click('[data-couriers-all]');
	assert.equal(app.get('[data-kiriof-continue]').disabled, false);
	app.click('[data-kiriof-continue]');
	const save = app.requests.at(-1);
	assert.equal(save.options.data.action, 'kiriof_store_courier_whitelist');
	assert.deepEqual(JSON.parse(save.options.data.data.service_selection), all);
	assert.equal(save.options.data.data.whitelist_ids, 'jne,jnt,mystery');
	assert.equal(save.options.data.data.nonce, 'nonce');
	app.click('[data-kiriof-continue]');
	app.click('[data-step-target]', 'shipping');
	app.click('[data-kiriof-back]');
	app.click('[data-couriers-none]');
	assert.equal(app.requests.length, 2, 'no duplicate save while pending');
	assert.equal(app.active('couriers'), true, 'cannot navigate away during save');
	if (failure) {
		const payload = failure === 'outer' ? { success: false, data: { status: 200, message: 'Validation failed' } } : { success: false, data: { status: 400, message: 'Unknown service code: EZ' } };
		if (failure === 'transport') { save.reject({ responseJSON: payload }); } else { save.resolve(payload); }
		app.flush();
		assert.equal(app.active('shipping'), false, 'HTTP 200 logical rejection must not advance');
		assert.equal(app.get('[data-step-target="couriers"]').classes.has('is-done'), false);
		assert.equal(app.get('[data-step-message="couriers"]').textContent, payload.data.message);
		assert.equal(app.get('[data-kiriof-continue]').disabled, false, 'failed save can be retried');
		app.click('[data-kiriof-continue]');
	}
	app.requests.at(-1).resolve({ success: true, data: { status: 200 } }); app.flush();
	assert.equal(app.active('shipping'), true);
	assert.equal(app.get('[data-step-target="couriers"]').classes.has('is-done'), true);
	app.click('[data-step-target]', 'complete');
	assert.equal(app.active('shipping'), true, 'completion cannot bypass shipping setup');
	app.click('[data-kiriof-continue]');
	assert.equal(app.requests.at(-1).options.data.action, 'kiriof_enable_shipping_method');
	app.requests.at(-1).resolve({ success: true, data: { status: 200, locations_ready: false } }); app.flush();
	assert.equal(app.active('complete'), false, 'locations must be enabled');
	app.click('[data-kiriof-continue]');
	app.requests.at(-1).resolve({ success: true, data: { status: 200, locations_ready: true } }); app.flush();
	assert.equal(app.active('complete'), true);
	assert.equal(app.submissions(), 0);
	// A new page uses the saved service selection and server-rendered done flags.
	app = boot(all, true);
	assert.equal(app.active('complete'), true);
	app.click('[data-step-target]', 'couriers'); app.load();
	app.click('[data-kiriof-continue]');
	assert.deepEqual(JSON.parse(app.requests.at(-1).options.data.data.service_selection), all, 'selection survives a page reload');
}
// Legacy raw responses contain their own data: do not unwrap away status.
const raw = boot();
raw.requests[0].resolve({ status: 200, data: { couriers: catalog, service_selection: all } }); raw.flush();
assert.equal(raw.get('[data-kiriof-continue]').disabled, false);
raw.click('[data-couriers-none]');
raw.click('[data-kiriof-continue]');
assert.equal(raw.requests.length, 1, 'empty selection is never saved or advanced');
assert.equal(raw.active('shipping'), false);
for (const transport of [false, true]) {
	const retry = boot();
	if (transport) { retry.requests[0].reject(); }
	else { retry.requests[0].resolve({ success: false, data: { status: 400, message: 'Catalog unavailable' } }); }
	retry.flush();
	assert.equal(retry.get('[data-kiriof-continue]').disabled, true);
	retry.click('[data-step-target]', 'couriers');
	assert.equal(retry.requests.length, 2, 'a failed catalog load can be retried');
	retry.load(); retry.click('[data-couriers-all]');
	assert.equal(retry.get('[data-kiriof-continue]').disabled, false);
}
console.log('Onboarding load, bulk save, rejection, finish and reload runtime tests passed');
