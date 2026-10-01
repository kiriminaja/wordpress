import { describe, expect, test } from 'bun:test';
import { readFileSync, existsSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { createRequire } from 'node:module';
import { homedir } from 'node:os';

const source = readFileSync(new URL('../assets/wp/js/kiriof-map-checkout.js', import.meta.url), 'utf8');
const require = createRequire(import.meta.url);
// React is not a dependency of this Svelte repository. Use an installed runtime
// when available, without downloading dependencies or adding production hooks.
function optionalRuntime(name: string, candidates: string[]) {
	try { return require(name); } catch {}
	for (const candidate of candidates) {
		const path = `${homedir()}/${candidate}/node_modules/${name}`;
		if (existsSync(`${path}/package.json`)) { try { return require(path); } catch {} }
	}
	return null;
}
const React = optionalRuntime('react', ['Kerjaa/portfolio', 'Kerjaa/kaj-shopify-plugin']);
const happy = optionalRuntime('happy-dom', ['Kerjaa/kaj-shopify-plugin-cart']);
// Load the renderer only after DOM globals exist: React determines input event
// support at module initialization. Resolve beside React to share one runtime.
let renderer: any;
let loadRenderer: (() => any) | undefined;
if (React) {
	try { require.resolve('react-dom/client'); loadRenderer = () => require('react-dom/client'); } catch {}
}
if (!loadRenderer && React) {
	for (const candidate of ['Kerjaa/portfolio', 'Kerjaa/kaj-shopify-plugin']) {
		try {
			const scoped = createRequire(`${homedir()}/${candidate}/package.json`);
			if (scoped('react') === React) { loadRenderer = () => scoped('react-dom/client'); break; }
		} catch {}
	}
}

function emitter() {
	const handlers = new Map<string, Set<(...args: any[]) => void>>();
	const offCalls: any[] = [];
	return {
		on(name: string, callback: any) { if (!handlers.has(name)) handlers.set(name, new Set()); handlers.get(name)!.add(callback); return this; },
		off(name?: string) { offCalls.push(name); if (name) handlers.delete(name); else handlers.clear(); return this; },
		fire(name: string, event?: any) { for (const callback of handlers.get(name) || []) callback(event); },
		handlers, offCalls,
	};
}
function fixture(extra: any = {}, browserRoot?: any) {
	const maps: any[] = [], markers: any[] = [], tiles: any[] = [], selections: any[] = [], errors: string[] = [];
	const pending = new Map<number, () => void>(), scheduled: any[] = [], cancelled: any[] = [], observers: any[] = [], locations: any[] = [];
	let id = 0;
	// Use a DOM node where installed; otherwise a branded HTMLElement stand-in,
	// never an untyped {} accepted unconditionally by a permissive Leaflet stub.
	const document = happy ? new happy.Window().document : null;
	class ElementFixture { nodeType = 1; nodeName = 'DIV'; }
	const node = document ? document.createElement('div') : new ElementFixture();
	const isNode = (value: any) => document ? value instanceof document.defaultView.HTMLElement || value instanceof (browserRoot?.HTMLElement || document.defaultView.HTMLElement) : value instanceof ElementFixture;
	const L = {
		map(container: any, options: any) {
			if (!isNode(container)) throw new Error('Leaflet requires an element');
			const map = Object.assign(emitter(), { container, options, views: [] as any[], center: { lat: 0, lng: 0 }, centerReads: 0, removed: 0, invalidations: [] as any[], removedLayers: [] as any[],
				setView(center: any, zoom: number) { this.fire('movestart'); this.center = { lat: center[0], lng: center[1] }; this.views.push({ center, zoom }); this.fire('moveend'); return this; },
				getCenter() { this.centerReads++; return { ...this.center }; },
				invalidateSize(options: any) { this.invalidations.push(options); },
				removeLayer(marker: any) { this.removedLayers.push(marker); marker.off(); },
				remove() { this.removed++; this.off(); for (const marker of markers) marker.off(); for (const layer of tiles) layer.off(); },
			}); maps.push(map); return map;
		},
		marker(position: any, options: any) {
			const marker = Object.assign(emitter(), { position, options, map: null as any,
				addTo(map: any) { this.map = map; return this; },
				setLatLng(position: any) { this.position = position; return this; },
				getLatLng() { return { lat: this.position[0], lng: this.position[1] }; },
			}); markers.push(marker); return marker;
		},
		tileLayer(url: string, options: any) {
			const layer = Object.assign(emitter(), { url, options, addTo(map: any) { return this; } }); tiles.push(layer); return layer;
		},
	};
	const geolocation = { getCurrentPosition(success: any, failure: any, options: any) { locations.push({ success, failure, options }); } };
	const root: any = browserRoot || {};
	root.setTimeout = (callback: any, delay: number) => { pending.set(++id, callback); scheduled.push({ id, callback, delay }); return id; };
	root.clearTimeout = (timer: number) => { cancelled.push(timer); pending.delete(timer); };
	root.ResizeObserver = class { callback: any; nodes: any[] = []; disconnected = 0;
		constructor(callback: any) { this.callback = callback; observers.push(this); }
		observe(node: any) { this.nodes.push(node); }
		disconnect() { this.disconnected++; }
	};
	runInNewContext(source, { window: root });
	const api = root.kiriofMapCheckout;
	const session = api.createMapSession({ leaflet: L, node, tiles: 'https://tiles.example.test/{z}/{x}/{y}.png', label: 'Delivery pin', geolocation,
		onSelect: (point: any) => { selections.push(point); return true; }, onError: (code: string) => errors.push(code), ...extra });
	return { root, api, session, L, node, maps, markers, tiles, selections, errors, locations, pending, scheduled, cancelled, observers,
		click: (lat: any, lng: any) => maps[0].fire('click', { latlng: { lat, lng } }),
		position: (index: number, lat: any, lng: any) => locations[index].success({ coords: { latitude: lat, longitude: lng } }),
	};
}

describe('Map checkout exported session: unchanged production VM, no UI hooks', () => {
	test('exports only the public runtime API without requiring WordPress or a UI hook', () => {
		const h = fixture(); expect(Object.keys(h.api).sort()).toEqual(['createMapSession', 'normalizePoint']);
		expect(h.root.kiriofBuyerMapTest).toBeUndefined();
	});
	test('same selected location recenters an interrupted camera move without republishing', () => {
		const moving: boolean[] = [];
		const h = fixture({ onMove: (value: boolean) => moving.push(value) });
		h.session.pick(1, 2, true);
		h.maps[0].fire('movestart');
		h.maps[0].center = { lat: 3, lng: 4 };
		h.click(1, 2);
		expect(h.maps[0].getCenter()).toEqual({ lat: 1, lng: 2 });
		expect(h.selections).toHaveLength(1);
		expect(moving).toEqual([true, false]);
	});
	test('default center is view-only: no marker, persistence, or geolocation', () => {
		const h = fixture(); expect(h.session.getPoint()).toBeNull(); expect(h.markers).toHaveLength(0);
		expect(h.selections).toHaveLength(0); expect(h.locations).toHaveLength(0);
		expect(h.maps[0].views).toEqual([{ center: [-6.2088, 106.8456], zoom: 13 }]);
	});
	test('numeric and string zero are genuine selections, including range boundaries', () => {
		const h = fixture(); expect(h.api.normalizePoint(0, '0')).toEqual({ latitude: '0.0000000', longitude: '0.0000000' });
		expect(h.api.normalizePoint('-90', '180')).toEqual({ latitude: '-90.0000000', longitude: '180.0000000' });
		expect(h.session.pick(0, 0)).toBe(true); expect(h.session.getPoint()).toEqual({ latitude: '0.0000000', longitude: '0.0000000' }); expect(h.markers).toHaveLength(0); expect(h.selections).toHaveLength(1);
	});
	test('normalization rejects boolean, null, object, whitespace, nonfinite, and out-of-range pairs', () => {
		const h = fixture(); const invalid = [true, false, null, undefined, {}, [], '', '  \t\n', NaN, Infinity, -Infinity, 'NaN', 'Infinity', 'bad'];
		for (const value of invalid) { expect(h.api.normalizePoint(value, 0)).toBeNull(); expect(h.api.normalizePoint(0, value)).toBeNull(); }
		for (const pair of [[90.000001, 0], [-90.000001, 0], [0, 180.000001], [0, -180.000001]]) expect(h.api.normalizePoint(...pair)).toBeNull();
		expect(h.api.normalizePoint(' -6.12345678 ', ' 106.12345678 ')).toEqual({ latitude: '-6.1234568', longitude: '106.1234568' });
	});
	test('invalid pick reports invalid without persisting or replacing a valid selection', () => {
		const h = fixture(); h.session.pick(1, 2); expect(h.session.pick(' ', 3)).toBe(false);
		expect(h.errors).toEqual(['invalid']); expect(h.selections).toHaveLength(1); expect(h.session.getPoint()).toEqual({ latitude: '1.0000000', longitude: '2.0000000' });
	});
	test('click selects once and pans the center without creating a Leaflet marker', () => {
		const h = fixture(); h.click(-6, 106);
		expect(h.selections).toEqual([{ latitude: '-6.0000000', longitude: '106.0000000' }]);
		expect(h.maps[0].center).toEqual({ lat: -6, lng: 106 }); expect(h.maps[0].views.at(-1)).toEqual({ center: [-6, 106], zoom: 16 });
		h.click(0, 0); expect(h.markers).toHaveLength(0); expect(h.selections).toHaveLength(2);
	});
	test('clear persists null without changing the camera, next movement selects again', () => {
		const h = fixture(); h.click(1, 2); h.session.clear();
		expect(h.session.getPoint()).toBeNull(); expect(h.selections.at(-1)).toBeNull(); expect(h.maps[0].center).toEqual({ lat: 1, lng: 2 });
		expect(h.maps[0].removedLayers).toEqual([]); h.maps[0].fire('movestart'); h.maps[0].center = { lat: 3, lng: 4 }; h.maps[0].fire('moveend');
		expect(h.session.getPoint()).toEqual({ latitude: '3.0000000', longitude: '4.0000000' }); expect(h.markers).toHaveLength(0);
	});
	test('valid initial pin centers without republishing; malformed initial is ignored', () => {
		const h = fixture({ initial: { latitude: 0, longitude: 0 } }); expect(h.maps[0].center).toEqual({ lat: 0, lng: 0 }); expect(h.selections).toHaveLength(0);
		expect(h.session.getPoint()).toEqual({ latitude: '0.0000000', longitude: '0.0000000' }); expect(h.markers).toHaveLength(0);
		const bad = fixture({ initial: { latitude: false, longitude: 1 } }); expect(bad.maps[0].views).toHaveLength(1); expect(bad.session.getPoint()).toBeNull();
	});
	test('drag camera makes no selection or geolocation request until moveend', () => {
		const moves: boolean[] = []; const h = fixture({ onMove: (moving: boolean) => moves.push(moving) }); const map = h.maps[0];
		map.fire('movestart'); map.center = { lat: -3, lng: 4 }; map.fire('move');
		expect(h.selections).toEqual([]); expect(h.locations).toEqual([]); expect(map.centerReads).toBe(0); expect(moves).toEqual([true]);
		map.fire('moveend'); expect(map.centerReads).toBe(1); expect(moves).toEqual([true, false]);
		expect(h.session.getPoint()).toEqual({ latitude: '-3.0000000', longitude: '4.0000000' }); expect(h.selections).toHaveLength(1); expect(h.markers).toHaveLength(0);
	});
	test('stray moveend without movestart never selects default center', () => {
		const h = fixture(); h.maps[0].fire('moveend'); expect(h.selections).toEqual([]); expect(h.maps[0].centerReads).toBe(0);
	});
	test('duplicate moveend reads center and publishes only once', () => {
		const h = fixture(); h.maps[0].fire('movestart'); h.maps[0].center = { lat: 1, lng: 2 }; h.maps[0].fire('moveend'); h.maps[0].fire('moveend');
		expect(h.selections).toHaveLength(1); expect(h.maps[0].centerReads).toBe(1);
	});
	test('normalized identical centers deduplicate persistence across movements', () => {
		const h = fixture(); const map = h.maps[0];
		for (const lat of [1, 1.000000001]) { map.fire('movestart'); map.center = { lat, lng: 2 }; map.fire('moveend'); }
		expect(h.selections).toHaveLength(1); expect(map.centerReads).toBe(2);
	});
	test('keyboard arrow movement selects camera center on moveend, Enter deduplicates it', () => {
		const h = fixture(); const map = h.maps[0]; map.fire('keydown', { originalEvent: { key: 'ArrowRight' } }); expect(h.selections).toEqual([]);
		map.fire('movestart'); map.center = { lat: 0, lng: 1 }; map.fire('moveend'); map.fire('keydown', { originalEvent: { key: 'Enter' } });
		expect(h.selections).toEqual([{ latitude: '0.0000000', longitude: '1.0000000' }]); expect(h.locations).toHaveLength(0);
	});
	test('Enter explicitly selects current camera center without requiring prior movement', () => {
		const h = fixture(); const map = h.maps[0]; map.center = { lat: 0, lng: 0 }; map.fire('keydown', {}); expect(h.selections).toHaveLength(0);
		map.fire('keydown', { originalEvent: { key: 'Enter' } }); expect(h.session.getPoint()).toEqual({ latitude: '0.0000000', longitude: '0.0000000' });
	});
	test('programmatic pick pan suppresses synchronous movement callbacks and double saves', () => {
		const moves: boolean[] = []; const h = fixture({ onMove: (moving: boolean) => moves.push(moving) });
		expect(h.session.pick(3, 4, true)).toBe(true); expect(h.maps[0].center).toEqual({ lat: 3, lng: 4 }); expect(h.selections).toHaveLength(1);
		expect(moves).toEqual([]); expect(h.maps[0].centerReads).toBe(0); h.maps[0].fire('moveend'); expect(h.selections).toHaveLength(1);
	});
	test('initial programmatic pan is suppressed and custom default center is not selected', () => {
		const moves: boolean[] = []; const h = fixture({ defaultCenter: [5, 6], initial: { latitude: 1, longitude: 2 }, onMove: (value: boolean) => moves.push(value) });
		expect(h.maps[0].views).toEqual([{ center: [5, 6], zoom: 13 }, { center: [1, 2], zoom: 16 }]); expect(h.selections).toEqual([]); expect(moves).toEqual([]);
		const blank = fixture({ defaultCenter: [5, 6] }); expect(blank.session.getPoint()).toBeNull(); expect(blank.selections).toEqual([]);
	});
	test('camera drag immediately invalidates geolocation success and errors before moveend', () => {
		const h = fixture(); h.session.locate(); h.maps[0].fire('movestart'); h.maps[0].center = { lat: 3, lng: 4 };
		h.position(0, 5, 6); h.locations[0].failure({ code: 1 }); expect(h.selections).toEqual([]); expect(h.errors).toEqual([]); expect(h.maps[0].center).toEqual({ lat: 3, lng: 4 });
		h.maps[0].fire('moveend'); expect(h.session.getPoint()).toEqual({ latitude: '3.0000000', longitude: '4.0000000' });
	});
	test('same-center movement still invalidates a pending geolocation', () => {
		const h = fixture(); h.click(1, 2); h.session.locate(); h.maps[0].fire('movestart'); h.maps[0].fire('moveend'); h.position(0, 5, 6);
		expect(h.selections).toHaveLength(1); expect(h.session.getPoint()).toEqual({ latitude: '1.0000000', longitude: '2.0000000' });
	});
	test('invalid camera center reports invalid without replacing an existing selection', () => {
		const h = fixture(); h.click(1, 2); h.maps[0].fire('movestart'); h.maps[0].center = { lat: 91, lng: 2 }; h.maps[0].fire('moveend');
		expect(h.errors).toEqual(['invalid']); expect(h.selections).toHaveLength(1); expect(h.session.getPoint()).toEqual({ latitude: '1.0000000', longitude: '2.0000000' });
	});
	test('rejected movement persistence leaves selection null without forcing camera back', () => {
		const h = fixture({ onSelect: () => false }); h.maps[0].fire('movestart'); h.maps[0].center = { lat: 3, lng: 4 }; h.maps[0].fire('moveend');
		expect(h.session.getPoint()).toBeNull(); expect(h.maps[0].center).toEqual({ lat: 3, lng: 4 }); expect(h.maps[0].views).toHaveLength(1);
	});
	test('pick without pan changes selection but leaves camera view unchanged', () => {
		const h = fixture(); h.session.pick(3, 4); expect(h.maps[0].views).toHaveLength(1); expect(h.maps[0].center).toEqual({ lat: -6.2088, lng: 106.8456 });
		expect(h.session.getPoint()).toEqual({ latitude: '3.0000000', longitude: '4.0000000' });
	});
	test('clear rejected by persistence keeps the prior selection', () => {
		const writes: any[] = []; const h = fixture({ onSelect: (point: any) => { writes.push(point); return point !== null; } }); h.click(1, 2); h.session.clear();
		expect(writes.at(-1)).toBeNull(); expect(h.session.getPoint()).toEqual({ latitude: '1.0000000', longitude: '2.0000000' });
	});
	test('geolocation pan does not signal movement or select twice', () => {
		const moves: boolean[] = []; const h = fixture({ onMove: (value: boolean) => moves.push(value) }); h.session.locate(); h.position(0, -6, 106);
		expect(h.selections).toHaveLength(1); expect(moves).toEqual([]); expect(h.maps[0].centerReads).toBe(0); expect(h.maps[0].center).toEqual({ lat: -6, lng: 106 });
	});
	test('invalid geolocation coordinates report invalid and do not pan', () => {
		const h = fixture(); h.session.locate(); h.position(0, 91, 0); expect(h.errors).toEqual(['invalid']); expect(h.selections).toEqual([]); expect(h.maps[0].views).toHaveLength(1);
	});
	test('dispose during movement prevents late moveend and Enter selection', () => {
		const h = fixture(); const map = h.maps[0]; map.fire('movestart'); h.session.dispose(); map.center = { lat: 3, lng: 4 }; map.fire('moveend'); map.fire('keydown', { originalEvent: { key: 'Enter' } });
		expect(h.selections).toEqual([]); expect(map.centerReads).toBe(0); expect(map.removed).toBe(1);
	});
	test('getPoint returns a defensive copy', () => {
		const h = fixture(); h.session.pick(1, 2); const point = h.session.getPoint(); point.latitude = '99'; expect(h.session.getPoint().latitude).toBe('1.0000000');
	});
	test('rejected persistence does not place a marker or change selected state', () => {
		const h = fixture({ onSelect: () => false }); expect(h.session.pick(1, 2)).toBe(false); expect(h.markers).toHaveLength(0); expect(h.session.getPoint()).toBeNull();
	});
	test('locate is explicit and requests a fresh high-accuracy position with a timeout', () => {
		const h = fixture(); expect(h.locations).toHaveLength(0); h.session.locate(); expect(h.locations).toHaveLength(1);
		expect(h.locations[0].options).toEqual({ enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }); h.position(0, 0, 0);
		expect(h.session.getPoint()).toEqual({ latitude: '0.0000000', longitude: '0.0000000' }); expect(h.maps[0].views.at(-1)).toEqual({ center: [0, 0], zoom: 16 });
	});
	test('missing geolocation reports permission only when locate is requested', () => {
		const h = fixture({ geolocation: null }); expect(h.errors).toEqual([]); h.session.locate(); expect(h.errors).toEqual(['permission']); expect(h.selections).toEqual([]);
	});
	test('denied permission, unavailable position, and timeout report appropriate errors', () => {
		for (const [code, expected] of [[1, 'permission'], [2, 'location'], [3, 'location']] as const) {
			const h = fixture(); h.session.locate(); h.locations[0].failure({ code }); expect(h.errors).toEqual([expected]); expect(h.markers).toHaveLength(0);
		}
	});
	for (const action of ['click', 'clear', 'dispose'] as const) test(`late geolocation success and error cannot override ${action}`, () => {
		const h = fixture(); h.session.locate(); if (action === 'click') h.click(3, 4); else h.session[action]();
		const before = h.selections.length; h.position(0, 5, 6); h.locations[0].failure({ code: 1 });
		expect(h.selections).toHaveLength(before); expect(h.errors).toEqual([]);
		if (action === 'click') expect(h.session.getPoint()).toEqual({ latitude: '3.0000000', longitude: '4.0000000' }); else expect(h.markers).toHaveLength(0);
	});
	test('a newer locate supersedes both older success and older error callbacks', () => {
		const h = fixture(); h.session.locate(); h.session.locate(); h.position(0, 1, 2); h.locations[0].failure({ code: 3 });
		expect(h.selections).toHaveLength(0); expect(h.errors).toEqual([]); h.position(1, 3, 4); expect(h.selections).toHaveLength(1);
	});
	test('requires a mounted element and HTTPS tiles, never silently passes a fabricated node', () => {
		for (const options of [{ node: null }, { node: {} }, { tiles: 'http://tiles.test' }, { leaflet: null }]) {
			const h = fixture(options); expect(h.session.isAvailable()).toBe(false); expect(h.errors).toEqual(['unavailable']); expect(h.maps).toHaveLength(0); expect(h.session.pick(0, 0)).toBe(false);
		}
	});
	test('resize observes the actual container and delayed invalidation uses no pan', () => {
		const h = fixture(); expect(h.observers[0].nodes).toEqual([h.node]); expect(h.scheduled[0].delay).toBe(150);
		h.scheduled[0].callback(); h.observers[0].callback(); expect(h.maps[0].invalidations).toEqual([{ pan: false }, { pan: false }]); expect(h.selections).toEqual([]); expect(h.locations).toEqual([]); expect(h.maps[0].centerReads).toBe(0);
	});
	test('dispose cancels resize timer, disconnects observer, removes map and handlers exactly once', () => {
		const h = fixture(); h.click(1, 2); h.session.dispose(); h.session.dispose();
		expect(h.cancelled).toEqual([h.scheduled[0].id]); expect(h.pending.size).toBe(0); expect(h.observers[0].disconnected).toBe(1);
		expect(h.maps[0].removed).toBe(1); expect(h.maps[0].offCalls).toHaveLength(1); expect(h.maps[0].handlers.size).toBe(0); expect(h.tiles[0].handlers.size).toBe(0); expect(h.markers).toHaveLength(0);
		h.scheduled[0].callback(); h.observers[0].callback(); h.session.locate(); h.session.clear(); expect(h.session.pick(3, 4)).toBe(false);
		expect(h.maps[0].invalidations).toEqual([]); expect(h.locations).toHaveLength(0); expect(h.selections).toHaveLength(1); expect(h.session.isAvailable()).toBe(false);
	});
	test('tile failure is nonfatal: reports unavailable but center selection still works', () => {
		const h = fixture(); h.tiles[0].fire('tileerror'); expect(h.errors).toEqual(['unavailable']); expect(h.session.isAvailable()).toBe(true);
		h.maps[0].fire('movestart'); h.maps[0].center = { lat: 0, lng: 0 }; h.maps[0].fire('moveend'); expect(h.selections).toEqual([{ latitude: '0.0000000', longitude: '0.0000000' }]);
	});
});

const uiTest = React && loadRenderer && happy ? test : test.skip;
function uiHarness(options: { noLeaflet?: boolean; editing?: boolean } = {}) {
	const window = new happy.Window({ url: 'https://checkout.example.test' });
	const saved = new Map<string, PropertyDescriptor | undefined>();
	for (const [key, value] of Object.entries({ window, document: window.document, navigator: window.navigator, HTMLElement: window.HTMLElement, IS_REACT_ACT_ENVIRONMENT: true })) {
		saved.set(key, Object.getOwnPropertyDescriptor(globalThis, key)); Object.defineProperty(globalThis, key, { value, configurable: true, writable: true });
	}
	const model = { cart: { needsShipping: true, shippingAddress: { address_1: 'First Street', city: 'Jakarta', country: 'ID', postcode: '12345' } }, collection: false };
	const writes: any[] = [], registrations: any[] = [];
	const coordinates = new Map<string, any>();
	const presentation = { editing: options.editing ?? true, cardTarget: null };
	const root: any = window;
	const f = fixture({}, root); f.session.dispose(); f.maps.length = 0; f.markers.length = 0; f.tiles.length = 0;
	root.L = options.noLeaflet ? undefined : f.L;
	Object.defineProperty(window.navigator, 'geolocation', { configurable: true, value: { getCurrentPosition: (success: any, failure: any, options: any) => f.locations.push({ success, failure, options }) } });
	root.kiriofMapCheckoutConfig = { enabled: true, tiles: 'https://tiles.example.test/{z}/{x}/{y}', i18n: {
		mapTitle: 'Delivery pin', mapConsent: 'Load map', mapLocate: 'Locate me', mapLatitude: 'Latitude', mapLongitude: 'Longitude', mapApply: 'Apply pin', mapClear: 'Clear pin', mapInvalid: 'Invalid coordinates', mapUnavailable: 'Map unavailable', mapPermission: 'Permission denied', mapLocationFailed: 'Location failed', mapPlaced: 'Pin placed', mapHelp: 'Choose a pin', mapMoving: 'Moving pin', mapOptional: 'Optional delivery pin',
	} };
	root.kiriofBuyerCheckout = {
		getCoordinates: (address: any) => coordinates.get(JSON.stringify(address)) || null,
		setCoordinates: (address: any, point: any) => { writes.push({ address, point }); coordinates.clear(); if (point) coordinates.set(JSON.stringify(address), { ...point }); return true; },
	};
	// Controlled bridge double isolates the map lifecycle from native DOM discovery.
	// The production hook is resolved before MapControl mounts, just like this double.
	if (options.editing !== undefined) root.kiriofAddressPresentation = { usePresentation: () => presentation };
	root.wp = { element: React, data: { useSelect: (callback: any) => callback((name: string) => name === 'wc/store/cart' ? { getCartData: () => model.cart } : { prefersCollection: () => model.collection }) }, plugins: { registerPlugin() { throw new Error('Map must not register OrderMeta plugin'); } } };
	root.wc = { blocksCheckout: { registerCheckoutBlock: (registration: any) => registrations.push(registration), get OrderMeta() { throw new Error('Map must not access OrderMeta'); } } };
	runInNewContext(source, { window: root });
	const container = window.document.createElement('div'); window.document.body.append(container);
	renderer ||= loadRenderer!();
	const reactRoot = renderer.createRoot(container);
	const render = () => React.act(() => reactRoot.render(React.createElement(registrations[0].component)));
	const button = (text: string) => [...container.querySelectorAll('button')].find((item: any) => item.textContent === text) as any;
	const click = (text: string) => React.act(() => { const target = button(text); expect(target).toBeDefined(); target.dispatchEvent(new window.MouseEvent('click', { bubbles: true })); });
	const cleanup = () => { React.act(() => reactRoot.unmount()); for (const [key, descriptor] of saved) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete (globalThis as any)[key]; } };
	render();
	return { ...f, root, model, writes, registrations, presentation, coordinates, container, render, click, button, cleanup };
}

describe('MapControl: actual React commit/ref runtime (optional installed React + DOM)', () => {
	uiTest('collapsed presentation does not create a map, tiles, or geolocation; editing opens automatically without selecting', () => {
		const h = uiHarness({ editing: false }); try {
			expect(h.container.childElementCount).toBe(0); expect(h.maps).toHaveLength(0); expect(h.tiles).toHaveLength(0);
			expect(h.locations).toEqual([]); expect(h.writes).toEqual([]);
			h.presentation.editing = true; h.render();
			expect(h.maps).toHaveLength(1); expect(h.tiles).toHaveLength(1); expect(h.maps[0].container.isConnected).toBe(true);
			expect(h.maps[0].views).toEqual([{ center: [-6.2088, 106.8456], zoom: 13 }]);
			expect(h.button('Load map')).toBeUndefined(); expect(h.button('Clear pin')).toBeUndefined();
			expect(h.locations).toEqual([]); expect(h.writes).toEqual([]);
		} finally { h.cleanup(); }
	});
	uiTest('closing editing disposes the map and ignores pending location; reopening restores saved coordinates without writes', () => {
		const h = uiHarness({ editing: true }); try {
			React.act(() => h.maps[0].fire('click', { latlng: { lat: 0, lng: 0 } })); h.click('Locate me');
			const saved = [...h.coordinates.values()][0];
			h.presentation.editing = false; h.render();
			expect(h.container.childElementCount).toBe(0); expect(h.maps[0].removed).toBe(1);
			expect(h.observers.at(-1).disconnected).toBe(1); expect(h.pending.size).toBe(0);
			React.act(() => { h.position(0, 5, 6); h.locations[0].failure({ code: 1 }); });
			expect(h.writes).toHaveLength(1); expect([...h.coordinates.values()]).toEqual([saved]);
			h.presentation.editing = true; h.render();
			expect(h.maps).toHaveLength(2); expect(h.maps[1].views).toEqual([{ center: [-6.2088, 106.8456], zoom: 13 }, { center: [0, 0], zoom: 16 }]);
			expect(h.button('Clear pin')).toBeDefined(); expect(h.container.textContent).toContain('Pin placed');
			expect(h.writes).toHaveLength(1); expect(h.locations).toHaveLength(1);
		} finally { h.cleanup(); }
	});
	uiTest('address change while collapsed cannot restore a pin from the old buyer snapshot', () => {
		const h = uiHarness({ editing: true }); try {
			React.act(() => h.maps[0].fire('click', { latlng: { lat: 1, lng: 2 } }));
			h.presentation.editing = false; h.render(); h.model.cart.shippingAddress.address_1 = 'Second Street'; h.render();
			expect(h.maps).toHaveLength(1); expect(h.writes).toHaveLength(1);
			h.presentation.editing = true; h.render();
			expect(h.maps[1].views).toEqual([{ center: [-6.2088, 106.8456], zoom: 13 }]); expect(h.button('Clear pin')).toBeUndefined();
			expect(h.writes).toHaveLength(1); expect(h.locations).toEqual([]);
		} finally { h.cleanup(); }
	});
	uiTest('bridge loaded after registration cannot change the optional hook or legacy fail-open behavior', () => {
		const h = uiHarness(); try {
			h.root.kiriofAddressPresentation = { usePresentation() { throw new Error('Late hook must not be called'); } }; h.render();
			expect(h.maps).toHaveLength(1); expect(h.maps[0].removed).toBe(0); expect(h.writes).toEqual([]);
		} finally { h.cleanup(); }
	});
	uiTest('forced shipping-address registration auto-mounts one connected map without consent or geolocation', () => {
		const h = uiHarness(); try {
			expect(h.registrations).toHaveLength(1); expect(h.registrations[0].force).toBe(true);
			expect(h.registrations[0].metadata).toEqual({ name: 'kiriminaja-official/map-checkout', parent: ['woocommerce/checkout-shipping-address-block'], attributes: { lock: { type: 'object', default: { remove: true, move: true } } } });
			expect(h.maps).toHaveLength(1); expect(h.maps[0].container).toBe(h.container.querySelector('.kiriof-buyer-map__canvas'));
			expect(h.maps[0].container.isConnected).toBe(true); expect(h.locations).toHaveLength(0); expect(h.writes).toEqual([]); expect(h.markers).toHaveLength(0);
			expect(h.button('Load map')).toBeUndefined(); expect(h.button('Apply pin')).toBeUndefined(); expect(h.container.querySelectorAll('input')).toHaveLength(0);
		} finally { h.cleanup(); }
	});
	uiTest('location overlay is a viewport child and only its click requests location', () => {
		const h = uiHarness(); try {
			const viewport = h.container.querySelector('.kiriof-buyer-map__viewport'); const locate = h.container.querySelector('.kiriof-buyer-map__locate');
			expect(locate.parentElement).toBe(viewport); expect(locate.type).toBe('button'); expect(h.locations).toHaveLength(0);
			h.render(); expect(h.locations).toHaveLength(0); h.click('Locate me'); expect(h.locations).toHaveLength(1);
			React.act(() => h.position(0, 0, 0)); expect(h.writes).toHaveLength(1); expect(h.maps[0].center).toEqual({ lat: 0, lng: 0 });
		} finally { h.cleanup(); }
	});
	uiTest('fixed centered indicator is an aria-hidden SVG, not a Leaflet marker', () => {
		const h = uiHarness(); try {
			const indicator = h.container.querySelector('.kiriof-buyer-map__indicator'); expect(indicator).not.toBeNull();
			expect(indicator.parentElement).toBe(h.container.querySelector('.kiriof-buyer-map__viewport')); expect(indicator.getAttribute('aria-hidden')).toBe('true');
			expect(indicator.querySelector('svg').getAttribute('focusable')).toBe('false'); expect(indicator.querySelector('svg path')).not.toBeNull(); expect(h.markers).toHaveLength(0);
		} finally { h.cleanup(); }
	});
	uiTest('camera movement publishes once at moveend with the shipping snapshot and moving status', () => {
		const h = uiHarness(); try {
			React.act(() => { h.maps[0].fire('movestart'); h.maps[0].center = { lat: -6, lng: 106 }; });
			expect(h.writes).toHaveLength(0); expect(h.container.querySelector('.is-moving')).not.toBeNull(); expect(h.container.querySelector('[role="status"]').textContent).toBe('Moving pin');
			React.act(() => h.maps[0].fire('moveend'));
			expect(h.writes).toEqual([{ address: { address_1: 'First Street', address_2: '', city: 'Jakarta', state: '', country: 'ID', postcode: '12345' }, point: { latitude: '-6.0000000', longitude: '106.0000000' } }]);
			expect(h.container.querySelector('.is-moving')).toBeNull(); expect(h.container.textContent).toContain('Pin placed'); expect(h.button('Clear pin')).toBeDefined();
		} finally { h.cleanup(); }
	});
	uiTest('address edits reset to default without saving and invalidate old geolocation', () => {
		const h = uiHarness(); try {
			React.act(() => h.maps[0].fire('click', { latlng: { lat: 0, lng: 0 } })); h.click('Locate me');
			h.model.cart.shippingAddress.address_1 = 'Second Street'; h.render(); expect(h.maps[0].removed).toBe(1); expect(h.maps).toHaveLength(2);
			expect(h.button('Clear pin')).toBeUndefined(); expect(h.maps[1].center).toEqual({ lat: -6.2088, lng: 106.8456 }); expect(h.writes).toHaveLength(1);
			React.act(() => h.position(0, 5, 6)); expect(h.writes).toHaveLength(1); expect(h.writes[0].address.address_1).toBe('First Street'); expect(h.markers).toHaveLength(0);
		} finally { h.cleanup(); }
	});
	uiTest('clear removes selected UI without moving the center or requesting location', () => {
		const h = uiHarness(); try {
			React.act(() => h.maps[0].fire('click', { latlng: { lat: 1, lng: 2 } })); h.click('Clear pin');
			expect(h.writes.at(-1).point).toBeNull(); expect(h.button('Clear pin')).toBeUndefined(); expect(h.maps[0].center).toEqual({ lat: 1, lng: 2 }); expect(h.locations).toHaveLength(0);
		} finally { h.cleanup(); }
	});
	uiTest('tile errors are visible with no manual fallback, and unmount removes map', () => {
		const h = uiHarness(); try {
			React.act(() => h.tiles.at(-1).fire('tileerror')); expect(h.container.textContent).toContain('Map unavailable');
			expect(h.container.querySelectorAll('input')).toHaveLength(0); expect(h.button('Apply pin')).toBeUndefined(); expect(h.writes).toHaveLength(0);
		} finally { h.cleanup(); } expect(h.maps[0].removed).toBe(1);
	});
	uiTest('missing Leaflet displays unavailable and never offers manual coordinates', () => {
		const h = uiHarness({ noLeaflet: true }); try {
			expect(h.maps).toHaveLength(0); expect(h.container.textContent).toContain('Map unavailable'); expect(h.container.querySelectorAll('input')).toHaveLength(0);
			h.click('Locate me'); expect(h.locations).toHaveLength(0); expect(h.writes).toHaveLength(0);
		} finally { h.cleanup(); }
	});
	uiTest('non-Indonesian, collection, and non-shipping checkout hide map and dispose it', () => {
		const h = uiHarness(); try {
			h.model.collection = true; h.render(); expect(h.container.childElementCount).toBe(0); expect(h.maps[0].removed).toBe(1);
			h.model.collection = false; h.model.cart.shippingAddress.country = 'US'; h.render(); expect(h.container.childElementCount).toBe(0);
			h.model.cart.shippingAddress.country = 'ID'; h.model.cart.needsShipping = false; h.render(); expect(h.container.childElementCount).toBe(0); expect(h.writes).toHaveLength(0);
		} finally { h.cleanup(); }
	});
	uiTest('unchanged rerender does not recreate the map or publish default center', () => {
		const h = uiHarness(); try {
			h.render(); h.render(); expect(h.maps).toHaveLength(1); expect(h.maps[0].removed).toBe(0); expect(h.writes).toEqual([]); expect(h.locations).toEqual([]);
		} finally { h.cleanup(); }
	});
	uiTest('returning from collection creates a fresh default view without saving', () => {
		const h = uiHarness(); try {
			h.model.collection = true; h.render(); h.model.collection = false; h.render();
			expect(h.maps).toHaveLength(2); expect(h.maps[1].container.isConnected).toBe(true); expect(h.maps[1].center).toEqual({ lat: -6.2088, lng: 106.8456 }); expect(h.writes).toEqual([]); expect(h.locations).toEqual([]);
		} finally { h.cleanup(); }
	});
	uiTest('unmount invalidates pending location and cleans up the automatic session', () => {
		const h = uiHarness(); h.click('Locate me'); h.cleanup(); h.position(0, 3, 4); h.locations[0].failure({ code: 1 });
		expect(h.maps[0].removed).toBe(1); expect(h.writes).toHaveLength(0); expect(h.observers.at(-1).disconnected).toBe(1);
	});
});
