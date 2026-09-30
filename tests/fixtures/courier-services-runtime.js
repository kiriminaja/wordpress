'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '../..');
class Element {
	constructor(tag) { this.tag = tag; this.children = []; this.attributes = {}; this.events = {}; this.classes = new Set(); this._text = ''; this.classList = { add: name => this.classes.add(name) }; }
	set textContent(value) { this._text = value; this.children = []; }
	get textContent() { return this._text; }
	appendChild(child) { this.children.push(child); return child; }
	setAttribute(name, value) { this.attributes[name] = value; }
	addEventListener(name, callback) { this.events[name] = callback; }
	change(checked) { this.checked = checked; this.events.change.call(this); }
}
const strings = { enabledCount: '%1$s of %2$s', allServices: 'All services', enableCourier: 'Enable %s', enableService: '%1$s %2$s', noCouriers: 'None', loading: 'Loading', selectService: 'Select a service' };
const document = { createElement: tag => new Element(tag), getElementById: () => null };
const window = { kiriofCourierServicesI18n: strings };
vm.runInNewContext(fs.readFileSync(path.join(root, 'assets/admin/js/kj-courier-services.js'), 'utf8'), { window, document });
const couriers = [{ code: 'jne', name: 'JNE', services: [{ code: 'REG', aliases: ['Regular'] }, { code: 'YES', aliases: [] }] }];
function picker(selection, ids = [], catalog = couriers) {
	const element = new Element('div');
	const api = window.kiriofCourierServices.create(element, { data: { couriers: catalog, service_selection: selection, whitelist_ids: ids } });
	return { api, element, inputs: element.children.flatMap(card => {
		const walk = el => [ ...(el.tag === 'input' ? [el] : []), ...el.children.flatMap(walk) ];
		return walk(card);
	}) };
}
const plain = value => JSON.parse(JSON.stringify(value));
assert.deepEqual(plain(picker(null).api.getSelection()), { jne: ['REG', 'YES'] });
assert.deepEqual(plain(picker({}).api.getSelection()), {});
assert.equal(picker({}).api.hasSelection(), false);
assert.deepEqual(plain(picker(null, ['jne']).api.getSelection()), { jne: ['REG', 'YES'] });
const wildcard = picker({ jne: ['*'] });
assert.deepEqual(plain(wildcard.api.getSelection()), { jne: ['REG', 'YES'] });
assert.equal(wildcard.api.hasSelection(), true);
assert.ok(wildcard.inputs.every(input => input.checked));
assert.deepEqual(JSON.parse(wildcard.api.getPayload().service_selection), { jne: ['REG', 'YES'] });
const subset = picker({ jne: ['regular', 'reg'] });
assert.deepEqual(plain(subset.api.getSelection()), { jne: ['REG'] });
assert.equal(subset.inputs[0].indeterminate, true);
const nodes = subset.inputs.slice();
subset.inputs[0].change(false);
assert.equal(subset.api.hasSelection(), false);
subset.inputs[0].change(true);
assert.deepEqual(plain(subset.api.getSelection()), { jne: ['REG'] });
const snapshot = subset.api.getState();
subset.api.setAll(true);
subset.api.setAll(false);
subset.api.setState(snapshot);
assert.deepEqual(plain(subset.api.getState()), plain(snapshot));
assert.equal(subset.inputs[1].checked, true);
assert.equal(subset.inputs[2].checked, false);
subset.api.setDisabled(true);
assert.ok(nodes.every(input => input.disabled));
subset.api.setAll(true);
assert.deepEqual(plain(subset.api.getSelection()), { jne: ['REG'] });
subset.api.setDisabled(false);
const collect = el => [ ...(el.tag === 'input' ? [el] : []), ...el.children.flatMap(collect) ];
assert.deepEqual(collect(subset.element), nodes, 'state changes must not rebuild controls');
const unknown = picker({ jne: ['OLD'], retired: ['HISTORIC'] });
assert.deepEqual(JSON.parse(unknown.api.getPayload().service_selection), { jne: ['OLD'], retired: ['HISTORIC'] });
assert.equal(unknown.api.hasSelection(), true);
assert.ok(unknown.inputs.some(input => input.checked && input.attributes['aria-label'].includes('HISTORIC')));
assert.equal(picker(null, ['retired']).api.hasSelection(), true);
const unsupported = [{ code: 'ninja_inter', type: 'regular' }, { code: 'foreign', region: 'international' }, { code: 'quick', type: 'instant' }];
const oldInternational = picker({ jne: ['REG'], ninja_inter: ['*'], foreign: ['OLD'], quick: ['OLD'] }, [], couriers.concat(unsupported));
assert.equal(oldInternational.element.children.length, 1);
assert.deepEqual(JSON.parse(oldInternational.api.getPayload().service_selection), { jne: ['REG'] });
assert.equal(oldInternational.api.getPayload().whitelist_ids, 'jne');
assert.deepEqual(plain(picker({ ninja_inter: ['*'] }).api.getSelection()), {});
assert.equal(picker(null, ['ninja_inter']).api.hasSelection(), false);
assert.deepEqual(plain(picker(null, ['ninja_inter']).api.getSelection()), {});
oldInternational.api.setAll(true);
assert.deepEqual(JSON.parse(oldInternational.api.getPayload().service_selection), { jne: ['REG', 'YES'] });
oldInternational.api.setState({ selection: { ninja_inter: ['*'] }, remembered: { ninja_inter: ['*'] } });
assert.deepEqual(plain(oldInternational.api.getState()), { selection: {}, remembered: {} });

// Small jQuery/deferred adapter: run the real onboarding entry point and its events.
const elements = new Map();
const requests = [];
function get(selector) {
	if (!elements.has(selector)) { elements.set(selector, new Element('div')); }
	return elements.get(selector);
}
get('[data-kiriof-onboarding]').data = { 'current-step': 'couriers', 'account-complete': '1' };
get('#kiriof-onboarding-setup-key').value = 'replacement-account';
function $(selector) {
	const el = typeof selector === 'string' ? get(selector) : selector;
	const result = { 0: el, length: 1,
		data: key => (el.data || {})[key],
		text(value) { if (value === undefined) { return el.textContent; } el.textContent = value; return this; },
		html(value) { if (value === undefined) { return el.textContent; } el.textContent = value; return this; },
		val: () => el.value || '',
		prop(name, value) { el[name] = value; return this; },
		attr(name, value) { el.setAttribute(name, value); return this; },
		toggleClass(name, enabled) { if (enabled) { el.classes.add(name); } else { el.classes.delete(name); } return this; },
		addClass(name) { el.classes.add(name); return this; },
		removeClass(name) { el.classes.delete(name); return this; },
		hasClass: name => el.classes.has(name),
		toggle() { return this; },
		find() { return { length: 0, removeClass() { return this; }, addClass() { return this; } }; },
		on(name, callback) { el.events[name] = callback; return this; }
	};
	return result;
}
function deferred() {
	const callbacks = { done: [], fail: [], always: [] };
	const request = {
		done(fn) { callbacks.done.push(fn); return this; },
		fail(fn) { callbacks.fail.push(fn); return this; },
		always(fn) { callbacks.always.push(fn); return this; },
		then(fn) { return this.done(fn); },
		resolve(value) { callbacks.done.forEach(fn => fn(value)); callbacks.always.forEach(fn => fn()); return this; },
		reject(value) { callbacks.fail.forEach(fn => fn(value)); callbacks.always.forEach(fn => fn()); return this; },
		promise() { return this; },
		abort() { this.aborted = true; return this.reject(); }
	};
	return request;
}
$.Deferred = deferred;
$.ajax = options => { const request = deferred(); request.action = options.data.action; requests.push(request); return request; };
const context = { window, document, jQuery: $, kiriofCourierServicesI18n: strings, kiriofOnboarding: { nonce: 'nonce', networkError: 'Network error' } };
vm.runInNewContext(fs.readFileSync(path.join(root, 'assets/admin/js/kj-onboarding.js'), 'utf8'), context);
const oldLoad = requests[0];
const navigate = step => get('[data-step-target]').events.click.call({ data: { 'step-target': step } });
navigate('account');
get('[data-kiriof-continue]').events.click();
requests[1].resolve({ status: 200 });
assert.equal(oldLoad.aborted, true);
navigate('couriers');
const newLoad = requests[2];
assert.equal(newLoad.action, 'kiriof_get_courier_whitelist');
// Simulate a response already queued before abort, including its old always callback.
oldLoad.resolve({ success: true, data: { status: 200, data: { couriers, service_selection: { jne: ['YES'] } } } });
assert.equal(get('[data-courier-list]').textContent, 'Loading');
oldLoad.reject();
assert.equal(get('[data-step-message="couriers"]').textContent, '');
navigate('couriers');
assert.equal(requests.length, 3, 'stale always must not clear the new request loading flag');
newLoad.resolve({ success: true, data: { status: 200, data: { couriers, service_selection: { jne: ['REG'] } } } });
assert.equal(get('[data-kiriof-continue]').disabled, false);
const finalInputs = collect(get('[data-courier-list]'));
assert.equal(finalInputs[1].checked, true);
assert.equal(finalInputs[2].checked, false);
navigate('couriers');
assert.equal(requests.length, 3, 'successful current response must mark catalog loaded');
console.log('Courier service picker and onboarding runtime tests passed');
