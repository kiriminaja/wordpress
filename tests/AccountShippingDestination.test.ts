import { describe, expect, test } from 'bun:test';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { createRequire } from 'node:module';
import { homedir } from 'node:os';

const require = createRequire(import.meta.url);
let happy: any;
for (const path of ['happy-dom', `${homedir()}/Kerjaa/kaj-shopify-plugin-cart/node_modules/happy-dom`]) {
	try { happy = require(path); break; } catch {}
}

const source = readFileSync(new URL('../assets/wp/js/kiriof-account-shipping.js', import.meta.url), 'utf8');
const mapSource = readFileSync(new URL('../assets/wp/js/kiriof-map-checkout.js', import.meta.url), 'utf8');
const initialAddress = { address_1: 'Street', address_2: '', city: 'Jakarta', state: 'JK', postcode: '10110', country: 'ID' };
function destination(extra: any = {}) {
	return { version: 1, district_id: '12', district_label: 'Old label', postcode: '10110', country: 'ID', address_type: 'shipping', ...extra };
}
function savedPin(extra: any = {}) {
	return destination({ version: 2, destination_latitude: 0, destination_longitude: '0', shipping_address: { ...initialAddress }, ...extra });
}
class Node {
	value = ''; textContent = ''; disabled = false; required = false; hidden = false;
	children: Node[] = []; attributes: Record<string, string> = {}; listeners = new Map<string, Set<any>>();
	classes = new Set<string>();
	classList = { remove: (name: string) => this.classes.delete(name), toggle: (name: string, on: boolean) => on ? this.classes.add(name) : this.classes.delete(name) };
	addEventListener(name: string, callback: any) { if (!this.listeners.has(name)) this.listeners.set(name, new Set()); this.listeners.get(name)!.add(callback); }
	removeEventListener(name: string, callback: any) { this.listeners.get(name)?.delete(callback); }
	fire(name: string, target: any = this) { const event = { type: name, target, preventDefault() {} }; for (const callback of [...this.listeners.get(name) || []]) callback(event); }
	setAttribute(name: string, value: string) { this.attributes[name] = value; }
	appendChild(node: Node) { this.children.push(node); }
	replaceChildren() { this.children = []; }
}
function fixture(saved: any = null, extra: any = {}, locationMode = 'grant') {
	const form: any = new Node(); const inputs: Record<string, Node> = {};
	for (const [key, value] of Object.entries(initialAddress)) { inputs[`shipping_${key}`] = Object.assign(new Node(), { value }); }
	form.elements = { namedItem: (name: string) => inputs[name] || null };
	const hidden = Object.assign(new Node(), { value: saved === null ? '' : JSON.stringify(saved) });
	const badges = new Node(), district = new Node(), retry = Object.assign(new Node(), { hidden: true }), status = new Node(), canvas = new Node(), viewport = new Node(), indicator = new Node(), locate = new Node(), mapStatus = new Node(), mapSection = new Node();
	const selectors: any = { '[name="kiriof_account_destination"]': hidden, '#kiriof-account-district': district, '#kiriof-account-district-status': status, '.kiriof-buyer-map__canvas': canvas, '.kiriof-buyer-map__viewport': viewport, '.kiriof-buyer-map__indicator': indicator, '.kiriof-buyer-map__locate': locate, '.kiriof-buyer-map__status': mapStatus, '.kiriof-buyer-map': mapSection };
	selectors['.kiriof-account-shipping-status'] = badges;
	selectors['#kiriof-account-district-retry'] = retry;
	const wrapper = { closest: () => form, querySelector: (selector: string) => selectors[selector] || null };
	const document: any = Object.assign(new Node(), { readyState: 'complete', createElement: () => new Node(), querySelectorAll: () => [wrapper] });
	let timerId = 0;
	const timers = new Map<number, { callback: any; delay: number }>(), requests: any[] = [], maps: any[] = [], locations: any[] = [], tiles: any[] = [];
	function emitter() {
		const handlers = new Map<string, any>();
		return { on(name: string, callback: any) { handlers.set(name, callback); return this; }, fire(name: string, event?: any) { handlers.get(name)?.(event); } };
	}
	const L = {
		map(node: any) {
			expect(node).toBe(canvas);
			const map = Object.assign(emitter(), { removed: 0, center: { lat: 0, lng: 0 },
				setView(center: number[]) { this.fire('movestart'); this.center = { lat: center[0], lng: center[1] }; this.fire('moveend'); return this; },
				getCenter() { return this.center; }, invalidateSize() {}, remove() { this.removed++; },
			}); maps.push(map); return map;
		},
		tileLayer() { const tile = Object.assign(emitter(), { addTo() { return this; } }); tiles.push(tile); return tile; },
	};
	const root: any = Object.assign(new Node(), {
		document, L, AbortController, URLSearchParams,
		navigator: { geolocation: locationMode === 'unavailable' ? undefined : { getCurrentPosition(success: any, failure: any) { locations.push({ success, failure }); if (locationMode === 'grant') success({ coords: { latitude: 0, longitude: 0 } }); } } },
		setTimeout(callback: any, delay: number) { timers.set(++timerId, { callback, delay }); return timerId; },
		clearTimeout(id: number) { timers.delete(id); },
		fetch(url: string, options: any) { return new Promise((resolve, reject) => requests.push({ url, options, resolve, reject })); },
		kiriofAccountShippingConfig: { ajaxUrl: '/ajax', nonce: 'nonce', map: { tiles: 'https://tiles.test/{z}/{x}/{y}', defaultCenter: [0, 0] }, i18n: { selectDistrict: 'Choose district', loading: 'Loading', lookupFailed: 'Lookup failed', districtRequired: 'Required', empty: 'Empty', postcodeRequired: 'Postcode required', mapPlaced: 'Placed', mapUnavailable: 'Map unavailable', mapPermission: 'Permission denied', mapLocationFailed: 'Location failed' }, ...extra },
	});
	runInNewContext(mapSource, { window: root });
	runInNewContext(source, { window: root });
	return {
		root, document, form, hidden, district, retry, status, indicator, locate, mapStatus, mapSection, viewport, maps, tiles, requests, locations, timers, inputs, badges,
		posted: () => hidden.value ? JSON.parse(hidden.value) : null,
		edit(field: string, value: string, type = 'input') { inputs[`shipping_${field}`].value = value; form.fire(type, inputs[`shipping_${field}`]); },
		choose(value: string) { district.value = value; form.fire('change', district); },
		click(lat: number, lng: number) { maps.at(-1).fire('click', { latlng: { lat, lng } }); },
		flush() { for (const [id, timer] of [...timers]) { timers.delete(id); timer.callback(); } },
		advance(delay: number) { for (const [id, timer] of [...timers]) { if (timer.delay === delay) { timers.delete(id); timer.callback(); } } },
		async respond(index = 0, rows: any = [{ id: 12, text: 'Canonical district' }, { id: 13, text: 'Other district' }]) {
			requests[index].resolve({ ok: true, json: async () => ({ success: true, data: rows }) });
			await settle();
		},
	};
}
async function settle() { for (let index = 0; index < 8; index++) await Promise.resolve(); }

(happy ? test : test.skip)('actual native select unlocks after success, failure and timeout; retry publishes canonical choices', async () => {
	const window = new happy.Window({ url: 'https://account.example.test' });
	const document = window.document;
	document.body.innerHTML = `<form>${Object.entries(initialAddress).map(([key, value]) => `<input name="shipping_${key}" value="${value}">`).join('')}
		<div class="kiriof-account-shipping"><input name="kiriof_account_destination" type="hidden"><select id="kiriof-account-district"></select>
		<span id="kiriof-account-district-status"></span><button id="kiriof-account-district-retry" type="button" hidden>Retry</button></div></form>`;
	const hidden = document.querySelector('[name="kiriof_account_destination"]'); hidden.value = JSON.stringify(destination());
	const district = document.querySelector('select'), retry = document.querySelector('button');
	let id = 0; const timers = new Map<number, { callback: any; delay: number }>(), requests: any[] = [];
	const root = {
		document, AbortController, URLSearchParams,
		kiriofAccountShippingConfig: { ajaxUrl: '/ajax', map: { enabled: false }, i18n: { selectDistrict: 'Choose district', lookupFailed: 'Failed' } },
		setTimeout(callback: any, delay: number) { timers.set(++id, { callback, delay }); return id; }, clearTimeout(id: number) { timers.delete(id); },
		fetch() { return new Promise((resolve, reject) => requests.push({ resolve, reject })); },
		addEventListener: window.addEventListener.bind(window), removeEventListener: window.removeEventListener.bind(window),
	};
	function advance(delay: number) { for (const [id, timer] of [...timers]) if (timer.delay === delay) { timers.delete(id); timer.callback(); } }
	async function respond(index: number) { requests[index].resolve({ ok: true, json: async () => ({ success: true, data: [{ id: 12, text: 'Canonical' }, { id: 13, text: 'Other' }] }) }); await settle(); }
	try {
		runInNewContext(source, { window: root }); document.dispatchEvent(new window.Event('DOMContentLoaded'));
		expect(district.disabled).toBe(true); expect(district.value).toBe('12'); expect(district.options[1].text).toBe('Old label');
		advance(250); await respond(0); expect(district.disabled).toBe(false); expect(district.options).toHaveLength(3);
		district.value = '13'; district.dispatchEvent(new window.Event('change', { bubbles: true }));
		expect(JSON.parse(hidden.value).district_label).toBe('Other'); expect(requests).toHaveLength(1);
		const postcode = document.querySelector('[name="shipping_postcode"]'); postcode.value = '10220'; postcode.dispatchEvent(new window.Event('input', { bubbles: true }));
		advance(250); requests[1].reject(new Error('offline')); await settle();
		expect(district.disabled).toBe(false); expect(district.options).toHaveLength(1); expect(retry.hidden).toBe(false);
		retry.click(); expect(district.disabled).toBe(true); advance(250); advance(10000);
		expect(district.disabled).toBe(false); expect(retry.hidden).toBe(false);
		await respond(2); expect(retry.hidden).toBe(false);
		retry.click(); advance(250); await respond(3); expect(district.disabled).toBe(false); expect(retry.hidden).toBe(true);
		district.value = '12'; district.dispatchEvent(new window.Event('change', { bubbles: true }));
		expect(JSON.parse(hidden.value).district_label).toBe('Canonical'); expect(JSON.parse(hidden.value).postcode).toBe('10220');
	} finally { window.dispatchEvent(new window.Event('pagehide')); await window.happyDOM.close(); }
});

describe('Account shipping destination native form', () => {
	test('pending saved district stays visible but disabled until canonical confirmation', async () => {
		const h = fixture(destination(), {}, 'pending');
		expect(h.district.disabled).toBe(true); expect(h.district.value).toBe('12');
		expect(h.district.children.map(node => [node.value, node.textContent])).toEqual([['', 'Choose district'], ['12', 'Old label']]);
		expect(h.retry.hidden).toBe(true); h.flush(); await h.respond();
		expect(h.district.disabled).toBe(false); expect(h.district.value).toBe('12'); expect(h.posted().district_label).toBe('Canonical district');
	});
	test('successful lookup exposes native choices and manual selection makes no additional API call', async () => {
		const h = fixture(null, {}, 'pending'); h.flush(); await h.respond();
		expect(h.district.disabled).toBe(false); expect(h.district.children.map(node => node.value)).toEqual(['', '12', '13']);
		h.choose('13'); expect(h.posted().district_label).toBe('Other district'); expect(h.requests).toHaveLength(1); expect(h.timers.size).toBe(0);
	});
	test('empty lookup keeps placeholder dropdown enabled and rejects fabricated changes', async () => {
		const h = fixture(destination(), {}, 'pending'); h.flush(); await h.respond(0, []);
		expect(h.district.disabled).toBe(false); expect(h.district.children).toHaveLength(1); expect(h.district.value).toBe('');
		expect(h.status.textContent).toBe('Empty'); expect(h.retry.hidden).toBe(true); h.choose('12'); expect(h.posted().district_id).toBe('');
	});
	test('offline lookup exposes retry and an enabled placeholder without inventing selection', async () => {
		const h = fixture(null, {}, 'pending'); h.flush(); h.requests[0].reject(new Error('offline')); await settle();
		expect(h.district.disabled).toBe(false); expect(h.district.children).toHaveLength(1); expect(h.retry.hidden).toBe(false);
		h.choose('12'); expect(h.posted()).toBe(null);
	});
	test('retry restores canonical choices without map, location or pin writes', async () => {
		const h = fixture(savedPin(), {}, 'pending'); h.flush(); h.requests[0].reject(new Error('offline')); await settle();
		const posted = h.hidden.value; h.retry.fire('click');
		expect(h.retry.hidden).toBe(true); expect(h.district.disabled).toBe(true); expect(h.hidden.value).toBe(posted);
		expect(h.locations).toHaveLength(1); expect(h.maps).toHaveLength(0); expect(h.requests[0].options.signal.aborted).toBe(true);
		h.flush(); await h.respond(1); h.choose('13'); expect(h.posted().district_label).toBe('Other district');
		expect(h.posted().destination_latitude).toBe('0.0000000'); expect(h.locations).toHaveLength(1); expect(h.requests).toHaveLength(2);
	});
	test('network timeout begins only when the actual fetch starts and exits loading', () => {
		const h = fixture(destination(), {}, 'pending'); expect([...h.timers.values()].map(timer => timer.delay)).toEqual([250]);
		h.advance(10000); expect(h.requests).toHaveLength(0); h.advance(250);
		expect([...h.timers.values()].map(timer => timer.delay)).toEqual([10000]); h.advance(10000);
		expect(h.requests[0].options.signal.aborted).toBe(true); expect(h.district.disabled).toBe(false);
		expect(h.status.textContent).toBe('Lookup failed'); expect(h.retry.hidden).toBe(false); expect(h.timers.size).toBe(0);
	});
	test('late success after timeout cannot replace remembered identity and retry succeeds', async () => {
		const h = fixture(destination(), {}, 'pending'); h.advance(250); h.advance(10000); await h.respond();
		expect(h.status.textContent).toBe('Lookup failed'); expect(h.posted().district_label).toBe('Old label');
		h.retry.fire('click'); h.advance(250); await h.respond(1); expect(h.status.textContent).toBe(''); expect(h.district.disabled).toBe(false);
		expect(h.posted().district_label).toBe('Canonical district');
	});
	test('abort rejection after timeout leaves failure enabled and retry visible', async () => {
		const h = fixture(null, {}, 'pending'); h.advance(250); h.advance(10000); h.requests[0].reject(new Error('AbortError')); await settle();
		expect(h.status.textContent).toBe('Lookup failed'); expect(h.district.disabled).toBe(false); expect(h.retry.hidden).toBe(false);
	});
	test('HTTP errors stop network timer and unlock district', async () => {
		const h = fixture(null, {}, 'pending'); h.advance(250); h.requests[0].resolve({ ok: false }); await settle();
		expect(h.timers.size).toBe(0); expect(h.district.disabled).toBe(false); expect(h.retry.hidden).toBe(false);
	});
	test('invalid JSON and unsuccessful payloads stop loading and permit retry', async () => {
		for (const response of [{ ok: true, json: async () => { throw new Error('JSON'); } }, { ok: true, json: async () => ({ success: false }) }]) {
			const h = fixture(null, {}, 'pending'); h.advance(250); h.requests[0].resolve(response); await settle();
			expect(h.district.disabled).toBe(false); expect(h.status.textContent).toBe('Lookup failed'); expect(h.timers.size).toBe(0);
		}
	});
	test('incomplete postcode disables dropdown and cancels both request and deadline', () => {
		const h = fixture(destination(), {}, 'pending'); h.advance(250); h.edit('postcode', '10');
		expect(h.requests[0].options.signal.aborted).toBe(true); expect(h.timers.size).toBe(0); expect(h.district.disabled).toBe(true);
		expect(h.status.textContent).toBe('Postcode required'); expect(h.retry.hidden).toBe(true); expect(h.district.value).toBe('');
	});
	test('changing country after failure hides retry and returning to ID starts fresh lookup', async () => {
		const h = fixture(destination(), {}, 'pending'); h.advance(250); h.requests[0].reject(new Error('offline')); await settle();
		h.edit('country', 'US'); expect(h.retry.hidden).toBe(true); expect(h.district.disabled).toBe(true); expect(h.district.required).toBe(false);
		h.edit('country', 'ID'); h.advance(250); await h.respond(1); expect(h.district.disabled).toBe(false); expect(h.district.value).toBe('');
	});
	test('retry is inert except after failure and pagehide removes its listener and all timers', async () => {
		const h = fixture(null, {}, 'pending'); h.retry.fire('click'); h.advance(250); expect(h.requests).toHaveLength(1);
		h.requests[0].reject(new Error('offline')); await settle(); h.retry.fire('click'); h.advance(250);
		h.root.fire('pagehide'); expect(h.retry.listeners.get('click')?.size).toBe(0); expect(h.timers.size).toBe(0);
		h.retry.fire('click'); h.flush(); expect(h.requests).toHaveLength(2); expect(h.requests[1].options.signal.aborted).toBe(true);
	});
	test('pending permission creates neither Leaflet nor tiles, and ordinary edits do not re-request', async () => {
		const h = fixture(null, {}, 'pending');
		expect(h.locations).toHaveLength(1); expect(h.maps).toHaveLength(0); expect(h.tiles).toHaveLength(0);
		expect(h.viewport.hidden).toBe(true); expect(h.mapSection.hidden).toBe(false); expect(h.locate.disabled).toBe(true);
		h.edit('address_1', ' Street '); h.form.fire('input', h.district); h.locate.fire('click');
		h.flush(); await h.respond(); h.choose('12'); h.form.fire('submit');
		expect(h.locations).toHaveLength(1); expect(h.posted().district_id).toBe('12'); expect(h.posted().version).toBe(1);
		h.locations[0].success({ coords: { latitude: 0, longitude: 0 } });
		expect(h.viewport.hidden).toBe(false); expect(h.maps).toHaveLength(1); expect(h.tiles).toHaveLength(1);
		expect(h.posted().destination_latitude).toBe('0.0000000'); expect(h.posted().destination_longitude).toBe('0.0000000');
	});
	test('denial retains matching saved pin without exposing its viewport and permits district/save', async () => {
		const h = fixture(savedPin(), {}, 'pending'); h.locations[0].failure({ code: 1 });
		expect(h.mapStatus.textContent).toBe('Permission denied'); expect(h.viewport.hidden).toBe(true);
		expect(h.maps).toHaveLength(0); expect(h.tiles).toHaveLength(0); expect(h.posted().version).toBe(2);
		h.flush(); await h.respond(); h.choose('13'); h.form.fire('submit'); h.locate.fire('click');
		expect(h.posted().district_id).toBe('13'); expect(h.posted().destination_latitude).toBe('0.0000000');
		expect(h.locations).toHaveLength(1); expect(h.viewport.hidden).toBe(true);
	});
	test('device grant never overwrites a matching saved pin', () => {
		const h = fixture(savedPin({ destination_latitude: 7, destination_longitude: 8 }), {}, 'pending');
		h.locations[0].success({ coords: { latitude: 0, longitude: 0 } });
		expect(h.maps[0].center).toEqual({ lat: 7, lng: 8 }); expect(h.posted().destination_latitude).toBe(7);
		expect(h.indicator.hidden).toBe(false); expect(h.viewport.hidden).toBe(false);
	});
	for (const code of [2, 3]) {
		test(`location error ${code} hides viewport without requiring a pin`, async () => {
			const h = fixture(null, {}, 'pending'); h.locations[0].failure({ code });
			expect(h.mapStatus.textContent).toBe('Location failed'); expect(h.viewport.hidden).toBe(true);
			expect(h.maps).toHaveLength(0); expect(h.tiles).toHaveLength(0);
			h.flush(); await h.respond(); h.choose('12'); h.form.fire('submit'); expect(h.posted().version).toBe(1);
		});
	}
	test('missing geolocation API remains map-unavailable without Leaflet or tiles', async () => {
		const h = fixture(null, {}, 'unavailable'); expect(h.mapStatus.textContent).toBe('Map unavailable');
		expect(h.viewport.hidden).toBe(true); expect(h.maps).toHaveLength(0); expect(h.tiles).toHaveLength(0);
		h.flush(); await h.respond(); h.choose('12'); h.form.fire('submit'); expect(h.posted().district_id).toBe('12');
	});
	test('disabled maps never request and country changes cancel/restart eligible gates', () => {
		const h = fixture(null, { map: { enabled: false } }, 'pending'); expect(h.locations).toHaveLength(0);
		const eligible = fixture(null, {}, 'pending'); eligible.edit('country', 'US');
		eligible.locations[0].success({ coords: { latitude: 1, longitude: 2 } });
		expect(eligible.maps).toHaveLength(0); expect(eligible.mapSection.hidden).toBe(true);
		eligible.edit('country', 'ID'); expect(eligible.locations).toHaveLength(2);
	});
	test('shipping edit badges track verified district, placed pin and full-address invalidation', async () => {
		const h = fixture(null, { i18n: { checkingDistrict: 'Checking', districtNotSet: 'District missing', needPinLocation: 'Need pin', pinLocation: 'Pin saved' } }, 'pending');
		expect(h.badges.children.map(node => node.textContent)).toEqual(['⚠ Checking', '⚠ Need pin']);
		h.flush(); await h.respond(); h.choose('12');
		expect(h.badges.children.map(node => node.textContent)).toEqual(['⚠ Need pin']);
		h.locations[0].success({ coords: { latitude: 0, longitude: 0 } });
		expect(h.badges.children.map(node => node.textContent)).toEqual(['✓ Pin saved']);
		h.edit('address_1', 'Other street');
		expect(h.badges.children.map(node => node.textContent)).toEqual(['⚠ Checking', '⚠ Need pin']);
		expect(h.posted().version).toBe(1);
	});
	test('granted zero device point is selected, district is required, lookup uses the real buyer endpoint', async () => {
		const h = fixture(); expect(h.posted().version).toBe(2); expect(h.maps[0].center).toEqual({ lat: 0, lng: 0 });
		expect(h.locations).toHaveLength(1); expect(h.indicator.hidden).toBe(false); expect(h.district.required).toBe(true);
		expect([...h.timers.values()].some(timer => timer.delay === 250)).toBe(true);
		h.flush(); const request = h.requests[0]; const body = new URLSearchParams(request.options.body);
		expect(Object.fromEntries(body)).toEqual({ action: 'kiriminaja_subdistrict_search', nonce: 'nonce', term: '10110' });
		expect(request.options.credentials).toBe('same-origin'); expect(request.options.method).toBe('POST');
		await h.respond(); expect(h.posted().district_id).toBe(''); expect(h.posted().version).toBe(2);
		h.choose('13'); expect(h.posted().district_label).toBe('Other district'); expect(h.posted().district_id).toBe('13');
		expect(h.posted().destination_latitude).toBe('0.0000000');
	});
	test('matching saved v2 zero pin restores silently and hidden submission wins over saved config', async () => {
		const saved = savedPin(); const h = fixture(saved, { savedDestination: savedPin({ destination_latitude: 7 }) });
		expect(h.hidden.value).toBe(JSON.stringify(saved)); expect(h.maps[0].center).toEqual({ lat: 0, lng: 0 }); expect(h.indicator.hidden).toBe(false);
		h.flush(); await h.respond(); expect(h.posted().district_label).toBe('Canonical district'); expect(h.posted().destination_latitude).toBe('0.0000000');
	});
	test('stale full-address pin is discarded but same-postcode district is lookup-confirmed', async () => {
		const h = fixture(savedPin({ shipping_address: { ...initialAddress, address_1: 'Different street' } }), {}, 'pending');
		expect(h.posted().version).toBe(1); expect(h.posted().destination_latitude).toBeUndefined(); expect(h.posted().district_id).toBe('12');
		h.flush(); await h.respond(0, [{ id: 13, text: 'Another' }]); expect(h.posted().district_id).toBe('');
	});
	test('user-chosen zero pin persists v2, address changes dispose old map and select a fresh device pin', async () => {
		const h = fixture(); h.flush(); await h.respond(); h.choose('12'); h.click(0, 0);
		expect(h.posted().version).toBe(2); expect(h.posted().shipping_address).toEqual(initialAddress);
		const old = h.maps[0]; h.edit('address_2', 'Suite 1');
		expect(old.removed).toBe(1); expect(h.maps).toHaveLength(2); expect(h.indicator.hidden).toBe(false);
		expect(h.locations).toHaveLength(2); expect(h.posted().shipping_address.address_2).toBe('Suite 1'); expect(h.posted().district_id).toBe('12'); expect(h.district.disabled).toBe(true);
		h.flush(); await h.respond(1); expect(h.district.value).toBe('12');
	});
	test('normalized whitespace edits preserve pins and use all six trimmed address fields', () => {
		const h = fixture(savedPin()); h.edit('address_1', ' Street '); h.edit('postcode', '10 110'); h.edit('country', 'id');
		expect(h.maps).toHaveLength(1); expect(h.maps[0].removed).toBe(0); expect(h.posted().version).toBe(2);
		expect(h.locations).toHaveLength(1);
	});
	test('cancellable debounce and generation reject obsolete postcode/country responses even when fetch ignores abort', async () => {
		const h = fixture(destination()); h.flush(); h.edit('postcode', '10220');
		expect(h.requests[0].options.signal.aborted).toBe(true); expect(h.posted().district_id).toBe('');
		h.flush(); await h.respond(0); expect(h.status.textContent).toBe('Loading'); expect(h.district.children).toHaveLength(1);
		h.edit('country', 'US', 'change'); expect(h.requests[1].options.signal.aborted).toBe(true);
		await h.respond(1); expect(h.district.required).toBe(false); expect(h.district.disabled).toBe(true); expect(h.posted().country).toBe('US');
		h.edit('country', 'ID', 'change'); h.edit('postcode', '10330'); h.flush(); expect(h.requests).toHaveLength(3);
		await h.respond(2); h.choose('12'); expect(h.posted().postcode).toBe('10330');
	});
	test('lookup failure enables the remembered option without erasing posted identity or inventing a selection', async () => {
		const saved = savedPin(); const h = fixture(saved); h.flush(); h.requests[0].reject(new Error('offline')); await settle();
		expect(h.hidden.value).toBe(JSON.stringify(saved)); expect(h.district.disabled).toBe(false); expect(h.status.textContent).toBe('Lookup failed');
		h.choose('13'); expect(h.posted().district_id).toBe('12'); h.form.fire('submit'); expect(h.posted().district_id).toBe('12');
	});
	test('replaced Woo state field and silent changes are read on delegated event and explicit submit', () => {
		const h = fixture(savedPin()); h.inputs.shipping_state = Object.assign(new Node(), { value: 'JB' }); h.form.fire('change', h.inputs.shipping_state);
		expect(h.posted().version).toBe(2); h.click(1, 2); expect(h.posted().shipping_address.state).toBe('JB');
		h.inputs.shipping_postcode.value = ' 10 220 '; h.form.fire('submit'); expect(h.posted().postcode).toBe('10220'); expect(h.posted().district_id).toBe(''); expect(h.posted().shipping_address.postcode).toBe('10220');
	});
	test('gate rejects late geolocation after address edit or pagehide and hidden locate never requests', async () => {
		const h = fixture(destination(), {}, 'pending'); expect(h.locations).toHaveLength(1); h.locate.fire('click'); expect(h.locations).toHaveLength(1);
		h.edit('city', 'Bandung'); h.locations[0].success({ coords: { latitude: 4, longitude: 5 } }); expect(h.posted().version).toBe(1);
		h.locate.fire('click'); h.root.fire('pagehide'); h.locations[1].success({ coords: { latitude: 4, longitude: 5 } });
		expect(h.posted().version).toBe(1); expect(h.maps).toHaveLength(0); expect(h.tiles).toHaveLength(0); expect(h.timers.size).toBe(0);
		h.form.fire('input', h.inputs.shipping_city); h.flush(); expect(h.requests).toHaveLength(0);
	});
		test('pagehide aborts pending lookups, late results cannot write', async () => {
		const h = fixture(destination()); h.flush(); h.root.fire('pagehide'); expect(h.requests[0].options.signal.aborted).toBe(true);
		await h.respond(); expect(h.posted().district_label).toBe('Old label');
	});
	test('pagehide disposes granted map and ignores late session callbacks', () => {
		const h = fixture(); h.locate.fire('click'); expect(h.locations).toHaveLength(2);
		const posted = h.hidden.value; h.root.fire('pagehide');
		h.locations[1].success({ coords: { latitude: 4, longitude: 5 } }); h.maps[0].fire('click', { latlng: { lat: 6, lng: 7 } });
		expect(h.hidden.value).toBe(posted); expect(h.maps[0].removed).toBe(1); expect(h.timers.size).toBe(0);
	});
	test('map unavailable is nonmandatory and district remains usable', async () => {
		const h = fixture(null, { map: { tiles: 'http://unsafe.test' } }); expect(h.mapStatus.textContent).toBe('Map unavailable'); expect(h.locate.disabled).toBe(true);
		h.flush(); await h.respond(); h.choose('12'); h.form.fire('submit'); expect(h.posted().district_id).toBe('12'); expect(h.posted().version).toBe(1);
	});
	test('DOMContentLoaded is the only deferred initialization, no public account globals or body observers', () => {
		const document: any = Object.assign(new Node(), { readyState: 'loading', querySelectorAll: () => [] });
		const root: any = { document }; runInNewContext(source, { window: root }); expect(document.listeners.get('DOMContentLoaded')?.size).toBe(1);
		document.fire('DOMContentLoaded'); expect(Object.keys(root)).toEqual(['document']);
		expect(source).not.toContain('MutationObserver'); expect(source).not.toContain('setInterval'); expect(source).not.toContain('Object.getOwnPropertyDescriptor');
	});
});
