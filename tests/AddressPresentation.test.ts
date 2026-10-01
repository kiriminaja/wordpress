import { describe, expect, test } from 'bun:test';
import { readFileSync, existsSync } from 'node:fs';
import { createRequire } from 'node:module';
import { homedir } from 'node:os';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../assets/wp/js/kiriof-address-presentation.js', import.meta.url), 'utf8');
const require = createRequire(import.meta.url);
function optionalRuntime(name: string, candidates: string[]) {
	try { return require(name); } catch {}
	for (const candidate of candidates) {
		const path = `${homedir()}/${candidate}/node_modules/${name}`;
		if (existsSync(`${path}/package.json`)) { try { return require(path); } catch {} }
	}
	return null;
}
const happy = optionalRuntime('happy-dom', ['Kerjaa/kaj-shopify-plugin-cart']);
const React = optionalRuntime('react', ['Kerjaa/portfolio', 'Kerjaa/kaj-shopify-plugin']);
let scoped: any;
for (const candidate of ['Kerjaa/portfolio', 'Kerjaa/kaj-shopify-plugin']) {
	try { const r = createRequire(`${homedir()}/${candidate}/package.json`); if (r('react') === React) { scoped = r; break; } } catch {}
}
const domTest = happy ? test : test.skip;
const uiTest = happy && React && scoped ? test : test.skip;
const step = (scope = 'shipping', editing = false) => `<section id="${scope}-fields"><div class="wc-block-components-address-address-wrapper${editing ? ' is-editing' : ''}"><div class="wc-block-components-address-card"><p>Native address</p><button class="wc-block-components-address-card__edit" aria-controls="${scope}" aria-expanded="${editing}">Edit</button></div><form><input name="${scope}_address_1"></form><div class="kiriof-buyer-map"><div class="leaflet-pane"></div></div><div class="kiriof-buyer-district"></div></div></section>`;
function fixture(options: { native?: boolean; portal?: boolean; observer?: boolean; html?: string } = {}) {
	const window = new happy.Window();
	window.document.body.innerHTML = options.html ?? `<main class="wp-block-woocommerce-checkout">${step('billing')}${step()}</main>`;
	const observers: any[] = [];
	class Observer {
		callback: any; disconnected = 0; nodes: any[] = [];
		constructor(callback: any) { this.callback = callback; observers.push(this); }
		observe(node: any, options: any) { this.nodes.push({ node, options }); }
		disconnect() { this.disconnected++; }
	}
	window.MutationObserver = options.observer === false ? undefined : options.native ? window.MutationObserver : Observer;
	window.wp = { element: { createPortal: options.portal === false ? undefined : () => {} } };
	runInNewContext(source, { window });
	const api = window.kiriofAddressPresentation;
	const changed = (target: any, type = 'attributes', addedNodes: any[] = [], removedNodes: any[] = []) => observers[0].callback([{ target, type, addedNodes, removedNodes }]);
	return { window, document: window.document, api, observers, changed };
}

describe('Address presentation shared native bridge', () => {
	test('runtime without DOM fails open, requires no globals besides window', () => {
		const window: any = {};
		runInNewContext(source, { window });
		const api = window.kiriofAddressPresentation;
		const cleanup = api.subscribe(() => {});
		expect(api.getSnapshot()).toEqual({ editing: true, cardTarget: null }); cleanup();
		expect(source).not.toMatch(/setTimeout|setInterval|addEventListener|jQuery/);
	});
	domTest('closed card has one host, native form/card/edit remain and click is not intercepted', () => {
		const h = fixture(); const clean = h.api.subscribe(() => {});
		const card = h.document.querySelector('#shipping-fields .wc-block-components-address-card');
		expect(h.api.getSnapshot().editing).toBe(false);
		expect(h.api.getSnapshot().cardTarget.parentNode).toBe(card);
		expect(h.document.querySelectorAll('.kiriof-address-status-host')).toHaveLength(1);
		expect(h.document.querySelector('#shipping-fields form')).not.toBeNull();
		const button = card.querySelector('button'); const click = new h.window.MouseEvent('click', { bubbles: true, cancelable: true });
		button.dispatchEvent(click); expect(click.defaultPrevented).toBe(false); expect(h.api.getSnapshot().editing).toBe(false);
		clean(); expect(h.document.querySelectorAll('.kiriof-address-status-host')).toHaveLength(0);
	});
	domTest('Woo shipping class works without an ID and edit must explicitly control shipping', () => {
		const html = `<main class="wc-block-checkout">${step().replace('id="shipping-fields"', 'class="wc-block-checkout__shipping-fields"')}</main>`;
		const h = fixture({ html }); const clean = h.api.subscribe(() => {});
		expect(h.api.getSnapshot().editing).toBe(false); clean();
		const bad = fixture({ html: html.replace('aria-controls="shipping"', 'aria-controls="billing"') });
		const off = bad.api.subscribe(() => {}); expect(bad.api.getSnapshot()).toEqual({ editing: true, cardTarget: null }); off();
	});
	domTest('programmatic class and aria changes open safely; identical state retains identity', () => {
		const h = fixture(); let calls = 0; const clean = h.api.subscribe(() => calls++);
		const wrapper = h.document.querySelector('#shipping-fields .wc-block-components-address-address-wrapper');
		const edit = wrapper.querySelector('button'); const closed = h.api.getSnapshot(); const before = calls;
		h.changed(wrapper); expect(h.api.getSnapshot()).toBe(closed); expect(calls).toBe(before);
		wrapper.classList.add('is-editing'); h.changed(wrapper); expect(h.api.getSnapshot().editing).toBe(true);
		wrapper.classList.remove('is-editing'); edit.setAttribute('aria-expanded', 'true'); h.changed(edit); expect(h.api.getSnapshot().editing).toBe(true);
		edit.setAttribute('aria-expanded', 'false'); h.changed(edit); expect(h.api.getSnapshot().editing).toBe(false);
		edit.removeAttribute('aria-expanded'); h.changed(edit); expect(h.api.getSnapshot()).toEqual({ editing: true, cardTarget: null }); clean();
	});
	domTest('district and map share one owner, last idempotent cleanup disconnects once', () => {
		const h = fixture(); const district = h.api.subscribe(() => {}); const map = h.api.subscribe(() => {});
		expect(h.observers).toHaveLength(1); expect(h.document.querySelectorAll('.kiriof-address-status-host')).toHaveLength(1);
		district(); district(); expect(h.observers[0].disconnected).toBe(0);
		map(); map(); expect(h.observers[0].disconnected).toBe(1); expect(h.api.getSnapshot().cardTarget).toBeNull();
		const next = h.api.subscribe(() => {}); expect(h.observers).toHaveLength(2); next();
	});
	domTest('plugin edits and billing mutations do not even scan the DOM or notify', () => {
		const h = fixture(); let calls = 0; const clean = h.api.subscribe(() => calls++); const before = calls;
		const nodes = [h.api.getSnapshot().cardTarget, h.document.querySelector('.leaflet-pane'), h.document.querySelector('.kiriof-buyer-district'), h.document.querySelector('#billing-fields')];
		const original = h.document.querySelector.bind(h.document); let scans = 0;
		h.document.querySelector = (...args: any[]) => { scans++; return original(...args); };
		for (const node of nodes) { const child = h.document.createElement('div'); node.appendChild(child); h.changed(node, 'childList', [child]); h.changed(node); }
		expect(scans).toBe(0); expect(calls).toBe(before); clean();
	});
	domTest('native host removal repairs once and ignores its own insertion', () => {
		const h = fixture(); const clean = h.api.subscribe(() => {}); const host = h.api.getSnapshot().cardTarget; const card = host.parentNode;
		host.remove(); h.changed(card, 'childList', [], [host]); const repaired = h.api.getSnapshot().cardTarget;
		expect(repaired).not.toBe(host); expect(repaired.parentNode).toBe(card);
		const before = h.api.getSnapshot(); h.changed(card, 'childList', [repaired]); expect(h.api.getSnapshot()).toBe(before);
		expect(card.querySelectorAll('.kiriof-address-status-host')).toHaveLength(1); clean();
	});
	for (const options of [{ portal: false }, { observer: false }, { html: `<main class="wc-block-checkout">${step('billing')}</main>` }, { html: step() }, { html: '<main class="wc-block-checkout"><section id="shipping-fields"></section></main>' }]) {
		domTest(`missing capabilities/markup fail open: ${JSON.stringify(options)}`, () => {
			const h = fixture(options); const clean = h.api.subscribe(() => {}); expect(h.api.getSnapshot()).toEqual({ editing: true, cardTarget: null });
			expect(h.document.querySelector('.kiriof-address-status-host')).toBeNull(); clean();
		});
	}
	domTest('real MutationObserver reacts to async step and whole checkout replacement', async () => {
		const h = fixture({ native: true }); const clean = h.api.subscribe(() => {});
		const flush = () => h.window.happyDOM.waitUntilComplete();
		try {
			const oldHost = h.api.getSnapshot().cardTarget;
			h.document.querySelector('#shipping-fields').outerHTML = step('shipping', true); await flush();
			expect(h.api.getSnapshot().editing).toBe(true); expect(h.api.getSnapshot().cardTarget).not.toBe(oldHost);
			h.document.querySelector('main').remove(); await flush(); expect(h.api.getSnapshot().cardTarget).toBeNull();
			h.document.body.innerHTML = `<main class="wc-block-checkout">${step()}</main>`; await flush();
			expect(h.api.getSnapshot().editing).toBe(false); expect(h.document.querySelectorAll('.kiriof-address-status-host')).toHaveLength(1);
		} finally { clean(); }
	});
	uiTest('actual React hooks share subscription and portal badge without native DOM takeover', async () => {
		const h = fixture({ native: true }); const saved = new Map<string, PropertyDescriptor | undefined>();
		for (const [key, value] of Object.entries({ window: h.window, document: h.document, navigator: h.window.navigator, HTMLElement: h.window.HTMLElement, IS_REACT_ACT_ENVIRONMENT: true })) {
			saved.set(key, Object.getOwnPropertyDescriptor(globalThis, key)); Object.defineProperty(globalThis, key, { value, configurable: true, writable: true });
		}
		const renderer = scoped('react-dom/client'); const portals = scoped('react-dom'); h.window.wp.element = { ...React, createPortal: portals.createPortal };
		const mount = h.document.createElement('div'); h.document.body.appendChild(mount); const root = renderer.createRoot(mount); const values: any[] = [];
		function District() { const state = h.api.usePresentation(); values[0] = state; return state.cardTarget ? portals.createPortal(React.createElement('span', null, 'District ready'), state.cardTarget) : null; }
		function MapControl() { const state = h.api.usePresentation(); values[1] = state; return React.createElement('div', { hidden: !state.editing }, 'Map controls'); }
		try {
			await React.act(async () => { root.render(React.createElement(React.Fragment, null, React.createElement(District), React.createElement(MapControl))); await h.window.happyDOM.waitUntilComplete(); });
			expect(values[0]).toBe(values[1]); expect(values[0].editing).toBe(false);
			expect(h.document.querySelector('.kiriof-address-status-host').textContent).toBe('District ready'); expect(mount.querySelector('div').hidden).toBe(true);
			await React.act(async () => { h.document.querySelector('#shipping-fields button').setAttribute('aria-expanded', 'true'); await h.window.happyDOM.waitUntilComplete(); });
			expect(mount.querySelector('div').hidden).toBe(false); expect(h.document.querySelector('#shipping-fields form')).not.toBeNull();
		} finally {
			await React.act(async () => root.unmount());
			for (const [key, descriptor] of saved) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete (globalThis as any)[key]; }
		}
		expect(h.document.querySelector('.kiriof-address-status-host')).toBeNull();
	});
});
