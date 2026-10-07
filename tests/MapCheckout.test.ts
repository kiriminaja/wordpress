import { describe, expect, test } from 'bun:test';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { React, happy, loadRenderer } from './helpers/ui-runtime';

const source = readFileSync(new URL('../assets/wp/js/kiriof-map-checkout.js', import.meta.url), 'utf8');
let renderer: any;

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
	const maps: any[] = [], markers: any[] = [], circles: any[] = [], tiles: any[] = [], selections: any[] = [], errors: string[] = [];
	const pending = new Map<number, () => void>(), scheduled: any[] = [], cancelled: any[] = [], observers: any[] = [], locations: any[] = [];
	let id = 0;
	// Required DOM runtime supplies real elements; retain the branded fixture
	// fallback for session doubles, never permissively accept an untyped {}.
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
				remove() { this.removed++; this.off(); for (const marker of markers) marker.off(); for (const layer of tiles) layer.off(); for (const circle of circles) { if (circle.map === this) { circle.removed++; circle.off(); } } },
			}); maps.push(map); return map;
		},
		circle(position: any, options: any) {
			const circle = Object.assign(emitter(), { position, options, map: null as any, removed: 0,
				addTo(map: any) { this.map = map; return this; },
			}); circles.push(circle); return circle;
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
	return { root, api, session, L, node, maps, markers, circles, tiles, selections, errors, locations, pending, scheduled, cancelled, observers,
		click: (lat: any, lng: any) => maps[0].fire('click', { latlng: { lat, lng } }),
		position: (index: number, lat: any, lng: any) => locations[index].success({ coords: { latitude: lat, longitude: lng } }),
	};
}

describe('Map checkout exported session: unchanged production VM, no UI hooks', () => {
	test('Haversine metres validates full coordinate objects, zero, antipodes and inclusive millimetre boundary', () => {
		const { api } = fixture(); const origin = { latitude: '0', longitude: 0 };
		expect(api.coverageDistance(origin, origin)).toBe(0);
		expect(api.coverageDistance(origin, { latitude: 0, longitude: 180 })).toBeCloseTo(Math.PI * 6371000, 6);
		for (const invalid of [null, {}, { latitude: '', longitude: 0 }, { latitude: true, longitude: 0 }, { latitude: 91, longitude: 0 }, { latitude: 0, longitude: Infinity }]) {
			expect(api.coverageDistance(origin, invalid)).toBeNull(); expect(api.coverageDistance(invalid, origin)).toBeNull();
		}
		const coverage = { origin, radiusMeters: 40000 };
		for (const [distance, inside] of [[40000, true], [40000.0005, true], [40000.002, false]] as const) {
			const point = { latitude: 0, longitude: distance / 6371000 * 180 / Math.PI };
			expect(api.coverageStatus(coverage, point).distanceMeters).toBeCloseTo(distance, 6);
			expect(api.coverageStatus(coverage, point).inside).toBe(inside);
		}
		for (const radiusMeters of [undefined, null, false, '', ' ', -1, 0, Infinity]) expect(api.coverageStatus({ origin, radiusMeters }, origin)).toBeNull();
		expect(api.coverageStatus(null, origin)).toBeNull(); expect(api.coverageStatus(coverage, null)).toBeNull();
	});
	uiTest('optional and coverage notes float inside the granted map rather than above it', () => {
		const h = uiHarness({ coverage: { origin: { latitude: 0, longitude: 0 }, radiusMeters: 40000 } }); try {
			const section = h.container.querySelector('.kiriof-buyer-map');
			const viewport = section.querySelector('.kiriof-buyer-map__viewport');
			const information = viewport.querySelector('.kiriof-buyer-map__information');
			expect(information.getAttribute('role')).toBe('note');
			expect(information.getAttribute('tabindex')).toBe('0');
			expect(information.querySelector('.kiriof-buyer-map__optional').textContent).toBe('Optional delivery pin');
			expect(information.querySelector('.kiriof-buyer-map__coverage').textContent).toContain('Instant coverage: 40 km');
			expect(section.querySelector(':scope > .kiriof-buyer-map__coverage')).toBeNull();
			expect(section.querySelector(':scope > p:not(.kiriof-buyer-map__status):not(.kiriof-buyer-map__coverage-warning)')).toBeNull();
			expect(h.maps).toHaveLength(1); expect(h.writes).toHaveLength(1);
		} finally { h.cleanup(); }
		const pending = uiHarness({ autoLocation: false }); try {
			expect(pending.container.querySelector('.kiriof-buyer-map__information')).toBeNull();
			expect(pending.container.querySelector('.kiriof-buyer-map__status').textContent).toContain('Requesting location permission');
		} finally { pending.cleanup(); }
	});
	test('seller coverage circle is unrestricted, independent of device view and removed with map', () => {
		const coverage = { origin: { latitude: '0', longitude: '0' }, radiusMeters: 40000 };
		const statuses: any[] = []; const h = fixture({ coverage, defaultCenter: [5, 6], initial: { latitude: 1, longitude: 0 }, onCoverage: (status: any) => statuses.push(status) });
		expect(h.circles).toHaveLength(1); expect(h.circles[0].position).toEqual([0, 0]); expect(h.circles[0].options).toEqual({ radius: 40000, interactive: false, fill: false, fillOpacity: 0, color: '#64748b', weight: 2, opacity: 0.85, dashArray: '1 6', lineCap: 'round' }); expect(h.circles[0].map).toBe(h.maps[0]);
		expect(h.maps[0].options.maxBounds).toBeUndefined(); expect(statuses.at(-1).inside).toBe(false); expect(h.selections).toEqual([]);
		coverage.origin.latitude = '1'; h.click(1, 0); expect(statuses.at(-1).inside).toBe(false); expect(h.selections).toHaveLength(0);
		h.click(0, 0); expect(statuses.at(-1).inside).toBe(true);
		h.maps[0].fire('movestart'); h.maps[0].center = { lat: 2, lng: 0 }; h.maps[0].fire('moveend'); expect(statuses.at(-1).inside).toBe(false);
		h.session.locate(); h.position(0, 3, 0); expect(statuses.at(-1).inside).toBe(false); expect(h.selections).toHaveLength(3);
		h.session.clear(); expect(statuses.at(-1)).toBeNull(); h.session.dispose(); h.session.dispose(); expect(h.circles[0].removed).toBe(1);
	});
	test('unknown origin does not use default center; optional circle unavailable does not disable publication', () => {
		for (const coverage of [undefined, { origin: { lat: 0, lng: 0 }, radiusMeters: 40000 }, { origin: { latitude: null, longitude: 0 }, radiusMeters: 40000 }]) {
			const statuses: any[] = []; const h = fixture({ coverage, onCoverage: (status: any) => statuses.push(status) }); h.click(0, 0);
			expect(h.circles).toEqual([]); expect(statuses).toEqual([null, null]); expect(h.selections).toHaveLength(1);
		}
		const h = fixture(); const leaflet = { ...h.L, circle: undefined };
		const session = h.api.createMapSession({ leaflet, node: h.node, tiles: 'https://tiles.test', coverage: { origin: { latitude: 0, longitude: 0 }, radiusMeters: 40000 } });
		expect(session.isAvailable()).toBe(true); expect(session.pick(5, 6)).toBe(true); session.dispose();
	});
	test('exports only the public runtime API without requiring WordPress or a UI hook', () => {
		const h = fixture(); expect(Object.keys(h.api).sort()).toEqual(['coverageDistance', 'coverageStatus', 'createLocationGate', 'createMapSession', 'normalizePoint']);
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

const uiTest = test;
function uiHarness(options: { noLeaflet?: boolean; editing?: boolean; autoLocation?: boolean; noGeolocation?: boolean; throwLocation?: boolean; savedPoint?: any; coverage?: any } = {}) {
	const window = new happy.Window({ url: 'https://checkout.example.test' });
	const saved = new Map<string, PropertyDescriptor | undefined>();
	for (const [key, value] of Object.entries({ window, document: window.document, navigator: window.navigator, HTMLElement: window.HTMLElement, IS_REACT_ACT_ENVIRONMENT: true })) {
		saved.set(key, Object.getOwnPropertyDescriptor(globalThis, key)); Object.defineProperty(globalThis, key, { value, configurable: true, writable: true });
	}
	const model = { cart: { needsShipping: true, shippingAddress: { address_1: 'First Street', city: 'Jakarta', country: 'ID', postcode: '12345' } }, collection: false };
	const writes: any[] = [], registrations: any[] = [];
	const coordinates = new Map<string, any>();
	if (options.savedPoint) coordinates.set(JSON.stringify({ address_1: 'First Street', address_2: '', city: 'Jakarta', state: '', postcode: '12345', country: 'ID' }), options.savedPoint);
	const presentation = { editing: options.editing ?? true, cardTarget: null };
	const root: any = window;
	const f = fixture({}, root); f.session.dispose(); f.maps.length = 0; f.markers.length = 0; f.circles.length = 0; f.tiles.length = 0;
	root.L = options.noLeaflet ? undefined : f.L;
	const initialRequests: any[] = [];
	Object.defineProperty(window.navigator, 'geolocation', { configurable: true, value: options.noGeolocation ? undefined : { getCurrentPosition: (success: any, failure: any, requestOptions: any) => {
		if (options.throwLocation) throw new Error('Browser location unavailable');
		if (!f.maps.length || f.maps.at(-1).removed) {
			initialRequests.push({ success, failure, options: requestOptions });
			if (options.autoLocation !== false) success({ coords: { latitude: -6, longitude: 106 } });
		} else f.locations.push({ success, failure, options: requestOptions });
	} } });
	root.kiriofMapCheckoutConfig = { enabled: true, coverage: options.coverage, tiles: 'https://tiles.example.test/{z}/{x}/{y}', i18n: {
		pinLocation: 'Pin Location', needPinLocation: 'Need Pin Location', mapCoverage: 'Instant coverage: 40 km straight-line from pickup origin. Express addresses may be outside this area.', mapOutsideRadius: 'Outside Instant coverage; Express is allowed', mapTitle: 'Delivery pin', mapLocating: 'Requesting location permission…', mapConsent: 'Load map', mapLocate: 'Locate me', mapLatitude: 'Latitude', mapLongitude: 'Longitude', mapApply: 'Apply pin', mapInvalid: 'Invalid coordinates', mapUnavailable: 'Map unavailable', mapPermission: 'Permission denied', mapLocationFailed: 'Location failed', mapPlaced: 'Pin placed', mapHelp: 'Delivery location map', mapKeyboard: 'Use arrow keys to move the map. Press Enter to select the center location.', mapMoving: 'Moving pin', mapOptional: 'Optional delivery pin',
	} };
	root.kiriofBuyerCheckout = {
		getCoordinates: (address: any) => coordinates.get(JSON.stringify(address)) || null,
		setCoordinates: (address: any, point: any) => { if (options.rejectCoordinates) return false; writes.push({ address, point }); coordinates.clear(); if (point) coordinates.set(JSON.stringify(address), { ...point }); return true; },
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
	const button = (text: string) => [...container.querySelectorAll('button')].find((item: any) => (item.getAttribute('aria-label') || item.textContent) === text) as any;
	const click = (text: string) => React.act(() => { const target = button(text); expect(target).toBeDefined(); target.dispatchEvent(new window.MouseEvent('click', { bubbles: true })); });
	const cleanup = () => { React.act(() => reactRoot.unmount()); for (const [key, descriptor] of saved) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete (globalThis as any)[key]; } };
	render();
	return { ...f, initialRequests, root, model, writes, registrations, presentation, coordinates, container, render, click, button, cleanup };
}

describe('shared location authorization gate', () => {
	test('start requests once, normalizes decimal zero and ignores duplicate browser responses', () => {
		const h = fixture(), requests: any[] = [], points: any[] = [], errors: any[] = [];
		const gate = h.api.createLocationGate({ geolocation: { getCurrentPosition: (...args: any[]) => requests.push(args) }, onSuccess: (point: any) => points.push(point), onError: (code: any) => errors.push(code) });
		gate.start(); gate.start(); expect(requests).toHaveLength(1);
		expect(requests[0][2]).toEqual({ enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
		requests[0][0]({ coords: { latitude: 0, longitude: '0' } }); requests[0][1]({ code: 1 }); requests[0][0]({ coords: { latitude: 1, longitude: 2 } });
		expect(points).toEqual([{ latitude: '0.0000000', longitude: '0.0000000' }]); expect(errors).toEqual([]);
	});
	test('denied, unavailable, timeout and malformed full point never authorize', () => {
		const h = fixture();
		for (const [reply, expected] of [[(ok: any, no: any) => no({ code: 1 }), 'permission'], [(ok: any, no: any) => no({ code: 2 }), 'location'], [(ok: any, no: any) => no({ code: 3 }), 'location'], [(ok: any) => ok({ coords: { latitude: 0 } }), 'location'], [(ok: any) => ok({ coords: { latitude: 91, longitude: 0 } }), 'location'], [(ok: any) => ok(null), 'location']] as any[]) {
			const points: any[] = [], errors: any[] = [];
			const gate = h.api.createLocationGate({ geolocation: { getCurrentPosition: reply }, onSuccess: (point: any) => points.push(point), onError: (code: any) => errors.push(code) });
			gate.start(); gate.start(); expect(points).toEqual([]); expect(errors).toEqual([expected]);
		}
	});
	test('missing API and synchronous exceptions fail closed; dispose before or after start cancels late callbacks', () => {
		const h = fixture();
		for (const geolocation of [undefined, {}, { getCurrentPosition() { throw new Error('blocked'); } }]) {
			const errors: any[] = []; h.api.createLocationGate({ geolocation, onError: (code: any) => errors.push(code) }).start(); expect(errors).toEqual(['unavailable']);
		}
		for (const before of [true, false]) {
			const requests: any[] = [], responses: any[] = [];
			const gate = h.api.createLocationGate({ geolocation: { getCurrentPosition: (...args: any[]) => requests.push(args) }, onSuccess: (point: any) => responses.push(point), onError: (code: any) => responses.push(code) });
			if (before) gate.dispose(); gate.start(); gate.dispose(); gate.start();
			for (const [ok, no] of requests) { ok({ coords: { latitude: 1, longitude: 2 } }); no({ code: 1 }); }
			expect(requests).toHaveLength(before ? 0 : 1); expect(responses).toEqual([]);
		}
	});
});

describe('MapControl: permission-gated actual React commit/ref runtime', () => {
	uiTest('saved outside pin warning remains visible while permission is denied; device notice is removed', () => {
		const h = uiHarness({ autoLocation: false, savedPoint: {latitude: 1, longitude: 0}, coverage: {origin: {latitude: 0, longitude: 0}, radiusMeters: 40000} });
		try {
			expect(h.container.querySelector('.kiriof-buyer-map__coverage-warning')).not.toBeNull();
			React.act(() => h.initialRequests[0].failure({code: 1}));
			expect(h.container.querySelector('.kiriof-buyer-map__coverage-warning')).not.toBeNull();
			expect(h.container.querySelector('.kiriof-buyer-map__viewport')).toBeNull();
			expect(h.writes).toHaveLength(0);
		} finally { h.cleanup(); }
		const granted = uiHarness();
		try { expect(granted.container.querySelector('.kiriof-buyer-map__device-notice')).toBeNull(); } finally { granted.cleanup(); }
	});
	uiTest('Store API package coverage overrides the initial circle and explicit unknown never falls back', () => {
		const h = uiHarness({ coverage: {origin: {latitude: 0, longitude: 0}, radiusMeters: 40000} });
		try {
			(h.model.cart as any).extensions = {'kiriminaja-official-instant-coverage': {coverage: {origin: {latitude: -6, longitude: 106}, radiusMeters: 40000}}};
			h.render(); expect(h.circles.at(-1).position).toEqual([-6, 106]); expect(h.container.querySelector('.kiriof-buyer-map__coverage-warning')).toBeNull();
			const requests = h.initialRequests.length;
			(h.model.cart as any).extensions['kiriminaja-official-instant-coverage'].coverage = null;
			h.render(); expect(h.container.querySelector('.kiriof-buyer-map__coverage-legend')).toBeNull(); expect(h.container.querySelector('.kiriof-buyer-map__coverage-warning')).toBeNull(); expect(h.initialRequests).toHaveLength(requests);
			expect(h.circles.at(-1).removed).toBe(1);
		} finally { h.cleanup(); }
	});
	uiTest('coverage circle awaits grant; out pin persists and warning never replaces permission or tile errors', () => {
		const h = uiHarness({ autoLocation: false, coverage: { origin: { latitude: 0, longitude: 0 }, radiusMeters: 40000 } }); try {
			expect(h.circles).toHaveLength(0); expect(h.container.querySelector('.kiriof-buyer-map__information')).toBeNull();
			React.act(() => h.initialRequests[0].success({ coords: { latitude: 1, longitude: 0 } }));
			expect(h.circles).toHaveLength(1); expect(h.circles[0].position).toEqual([0, 0]); expect(h.writes).toHaveLength(1);
			expect(h.container.querySelector('.kiriof-buyer-map__information').textContent).toContain('Instant coverage: 40 km');
			expect(h.container.querySelector('.kiriof-buyer-map__coverage-warning').getAttribute('role')).toBe('note'); expect(h.container.textContent).toContain('Outside Instant coverage');
			React.act(() => h.tiles[0].fire('tileerror')); expect(h.container.querySelector('.kiriof-buyer-map__status').textContent).toBe('Map unavailable'); expect(h.container.textContent).toContain('Outside Instant coverage');
			h.click('Locate me'); React.act(() => h.locations[0].failure({ code: 1 })); expect(h.container.querySelector('.kiriof-buyer-map__status').textContent).toBe('Permission denied');
			React.act(() => h.maps[0].fire('click', { latlng: { lat: 0, lng: 0 } })); expect(h.writes).toHaveLength(2); expect(h.container.querySelector('.kiriof-buyer-map__coverage-warning')).toBeNull();
		} finally { h.cleanup(); } expect(h.circles[0].removed).toBe(1);
	});
	uiTest('unknown coverage has no legend or false inside indication; updated config replaces stale origin overlay', () => {
		const h = uiHarness(); try {
			expect(h.container.querySelector('.kiriof-buyer-map__coverage')).toBeNull(); expect(h.circles).toHaveLength(0);
			h.root.kiriofMapCheckoutConfig.coverage = { origin: { latitude: -6, longitude: 106 }, radiusMeters: 40000 }; h.render();
			expect(h.circles.at(-1).position).toEqual([-6, 106]); expect(h.container.querySelector('.kiriof-buyer-map__coverage-warning')).toBeNull();
			h.root.kiriofMapCheckoutConfig.coverage.origin.latitude = 0; h.render();
			expect(h.circles.at(-2).removed).toBe(1); expect(h.circles.at(-1).position).toEqual([0, 106]); expect(h.container.textContent).toContain('Outside Instant coverage');
			expect(h.initialRequests).toHaveLength(1); expect(h.writes).toHaveLength(1);
		} finally { h.cleanup(); }
	});
	uiTest('pending request has a visible status but no canvas, viewport, Leaflet map or tiles; zero grant opens and selects once', () => {
		const h = uiHarness({ autoLocation: false }); try {
			expect(h.initialRequests).toHaveLength(1); expect(h.maps).toHaveLength(0); expect(h.tiles).toHaveLength(0); expect(h.writes).toEqual([]);
			expect(h.container.querySelector('.kiriof-buyer-map__viewport')).toBeNull(); expect(h.container.querySelector('.kiriof-buyer-map__canvas')).toBeNull(); expect(h.button('Locate me')).toBeUndefined();
			expect(h.container.querySelector('.kiriof-buyer-map__status').textContent).toContain('Requesting location permission'); h.render(); expect(h.initialRequests).toHaveLength(1);
			React.act(() => h.initialRequests[0].success({ coords: { latitude: 0, longitude: 0 } }));
			expect(h.maps).toHaveLength(1); expect(h.tiles).toHaveLength(1); expect(h.maps[0].center).toEqual({ lat: 0, lng: 0 }); expect(h.writes[0].point).toEqual({ latitude: '0.0000000', longitude: '0.0000000' });
			expect(h.maps[0].container.isConnected).toBe(true); h.render(); expect(h.writes).toHaveLength(1); expect(h.initialRequests).toHaveLength(1);
		} finally { h.cleanup(); }
	});
	for (const code of [1, 2, 3]) uiTest(`location error ${code} keeps picker hidden and saved pin untouched`, () => {
		const pin = { latitude: '0.0000000', longitude: '0.0000000' };
		const h = uiHarness({ autoLocation: false, savedPoint: pin }); try {
			React.act(() => h.initialRequests[0].failure({ code }));
			expect(h.container.querySelector('.kiriof-buyer-map__status').textContent).toBe(code === 1 ? 'Permission denied' : 'Location failed');
			expect(h.container.querySelector('.kiriof-buyer-map__viewport')).toBeNull(); expect(h.maps).toHaveLength(0); expect(h.tiles).toHaveLength(0); expect(h.writes).toEqual([]); expect([...h.coordinates.values()]).toEqual([pin]);
			h.render(); expect(h.initialRequests).toHaveLength(1);
			React.act(() => h.initialRequests[0].success({ coords: { latitude: 1, longitude: 2 } })); expect(h.maps).toHaveLength(0);
		} finally { h.cleanup(); }
	});
	for (const options of [{ noGeolocation: true }, { throwLocation: true }]) uiTest('API unavailable or synchronous failure has no picker or writes', () => {
		const h = uiHarness(options); try {
			expect(h.container.textContent).toContain('Map unavailable'); expect(h.container.querySelector('.kiriof-buyer-map__viewport')).toBeNull(); expect(h.maps).toHaveLength(0); expect(h.tiles).toHaveLength(0); expect(h.writes).toEqual([]);
		} finally { h.cleanup(); }
	});
	uiTest('collapsed editor does not prompt; entering requests once, closing cancels pending, reentering starts fresh', () => {
		const h = uiHarness({ editing: false, autoLocation: false }); try {
			expect(h.container.childElementCount).toBe(0); expect(h.initialRequests).toEqual([]);
			h.presentation.editing = true; h.render(); h.render(); expect(h.initialRequests).toHaveLength(1);
			h.presentation.editing = false; h.render(); React.act(() => h.initialRequests[0].success({ coords: { latitude: 1, longitude: 2 } })); expect(h.maps).toHaveLength(0); expect(h.writes).toEqual([]);
			h.presentation.editing = true; h.render(); expect(h.initialRequests).toHaveLength(2);
		} finally { h.cleanup(); }
	});
	uiTest('address changes cancel old permission callbacks and require a fresh grant', () => {
		const h = uiHarness({ autoLocation: false }); try {
			h.model.cart.shippingAddress.address_1 = 'Second Street'; h.render(); expect(h.initialRequests).toHaveLength(2);
			React.act(() => { h.initialRequests[0].success({ coords: { latitude: 1, longitude: 2 } }); h.initialRequests[0].failure({ code: 1 }); }); expect(h.maps).toHaveLength(0); expect(h.writes).toEqual([]);
			React.act(() => h.initialRequests[1].success({ coords: { latitude: 3, longitude: 4 } })); expect(h.maps[0].center).toEqual({ lat: 3, lng: 4 }); expect(h.writes[0].address.address_1).toBe('Second Street');
		} finally { h.cleanup(); }
	});
	uiTest('unmount ignores both late permission callbacks without ever making tiles', () => {
		const h = uiHarness({ autoLocation: false }); h.cleanup(); h.initialRequests[0].success({ coords: { latitude: 1, longitude: 2 } }); h.initialRequests[0].failure({ code: 1 }); expect(h.maps).toHaveLength(0); expect(h.tiles).toHaveLength(0); expect(h.writes).toEqual([]);
	});
	uiTest('grant restores matching saved pin without overwriting it with device coordinates; reopening asks again', () => {
		const pin = { latitude: '0.0000000', longitude: '0.0000000' };
		const h = uiHarness({ editing: true, savedPoint: pin }); try {
			expect(h.maps[0].center).toEqual({ lat: 0, lng: 0 }); expect(h.writes).toEqual([]); expect(h.initialRequests).toHaveLength(1);
			h.presentation.editing = false; h.render(); expect(h.maps[0].removed).toBe(1); expect(h.observers.at(-1).disconnected).toBe(1);
			h.presentation.editing = true; h.render(); expect(h.initialRequests).toHaveLength(2); expect(h.maps[1].center).toEqual({ lat: 0, lng: 0 }); expect(h.writes).toEqual([]);
		} finally { h.cleanup(); }
	});
	uiTest('granted map retains accessible canvas, fixed SVG and explicit follow-up locate', () => {
		const h = uiHarness(); try {
			const canvas = h.container.querySelector('.kiriof-buyer-map__canvas'); expect(canvas.getAttribute('aria-label')).toBe('Delivery location map'); expect(canvas.getAttribute('aria-description')).toContain('Use arrow keys');
			const indicator = h.container.querySelector('.kiriof-buyer-map__indicator'); expect(indicator.getAttribute('aria-hidden')).toBe('true'); expect(indicator.querySelector('svg').getAttribute('focusable')).toBe('false'); expect(h.markers).toHaveLength(0);
			expect(h.locations).toHaveLength(0); h.click('Locate me'); expect(h.locations).toHaveLength(1); React.act(() => h.position(0, 0, 0)); expect(h.writes).toHaveLength(2); expect(h.maps[0].center).toEqual({ lat: 0, lng: 0 });
			h.click('Locate me'); React.act(() => h.locations[1].failure({ code: 1 })); expect(h.maps[0].center).toEqual({ lat: 0, lng: 0 }); expect(h.writes).toHaveLength(2);
			expect(h.button('Clear pin')).toBeUndefined(); expect(h.button('Load map')).toBeUndefined(); expect(h.container.querySelectorAll('input')).toHaveLength(0);
		} finally { h.cleanup(); }
	});
	uiTest('floating pin badge reflects accepted coordinates only and locate is icon-only with a translated accessible name', () => {
		for (const rejectCoordinates of [false, true]) {
			const h = uiHarness({ rejectCoordinates }); try {
				const viewport = h.container.querySelector('.kiriof-buyer-map__viewport');
				const badge = viewport.querySelector('.kiriof-buyer-map__pin-status');
				expect(badge.textContent).toBe(rejectCoordinates ? 'Need Pin Location' : 'Pin Location');
				expect(badge.classList.contains(rejectCoordinates ? 'is-warning' : 'is-complete')).toBe(true);
				expect(badge.getAttribute('role')).toBe('status'); expect(badge.getAttribute('aria-live')).toBe('polite');
				expect(badge.querySelector('svg').getAttribute('aria-hidden')).toBe('true');
				expect(badge.querySelector('path').getAttribute('d')).toBe(rejectCoordinates ? 'M5 5h14v14H5Z' : 'm5 12 4 4 10-10');
				expect(h.container.querySelector('.kiriof-buyer-map__status')).toBeNull(); expect(h.container.textContent).not.toContain('Pin placed');
				const locate = viewport.querySelector('button'); expect(locate.textContent).toBe('');
				expect(locate.getAttribute('aria-label')).toBe('Locate me'); expect(locate.getAttribute('title')).toBe('Locate me');
				expect(locate.querySelector('svg').getAttribute('aria-hidden')).toBe('true'); expect(locate.querySelector('svg').getAttribute('focusable')).toBe('false');
				h.click('Locate me'); expect(h.locations).toHaveLength(1);
			} finally { h.cleanup(); }
		}
	});
	uiTest('camera movement publishes once at moveend and rerenders do not prompt or reset', () => {
		const h = uiHarness(); try {
			React.act(() => { h.maps[0].fire('movestart'); h.maps[0].center = { lat: 1, lng: 2 }; }); expect(h.writes).toHaveLength(1); expect(h.container.querySelector('.kiriof-buyer-map__status')).toBeNull();
			expect(h.container.querySelector('.kiriof-buyer-map__information').hidden).toBe(true);
			expect(h.container.querySelector('.kiriof-buyer-map__pin-status').hidden).toBe(true);
			React.act(() => h.maps[0].fire('moveend')); h.render(); h.render(); expect(h.writes).toHaveLength(2); expect(h.maps).toHaveLength(1); expect(h.initialRequests).toHaveLength(1); expect(h.maps[0].center).toEqual({ lat: 1, lng: 2 }); expect(h.container.textContent).toContain('Pin Location'); expect(h.container.querySelector('.kiriof-buyer-map__status')).toBeNull();
			expect(h.container.querySelector('.kiriof-buyer-map__information').hidden).toBe(false);
			expect(h.container.querySelector('.kiriof-buyer-map__pin-status').hidden).toBe(false);
		} finally { h.cleanup(); }
	});
	uiTest('ineligible checkout cancels map and requests; returning requires new authorization', () => {
		const h = uiHarness({ autoLocation: false }); try {
			h.model.collection = true; h.render(); expect(h.container.childElementCount).toBe(0);
			React.act(() => h.initialRequests[0].success({ coords: { latitude: 1, longitude: 2 } })); expect(h.maps).toHaveLength(0);
			h.model.collection = false; h.model.cart.shippingAddress.country = 'US'; h.render(); expect(h.initialRequests).toHaveLength(1);
			h.model.cart.shippingAddress.country = 'ID'; h.model.cart.needsShipping = false; h.render(); expect(h.initialRequests).toHaveLength(1);
			h.model.cart.needsShipping = true; h.render(); expect(h.initialRequests).toHaveLength(2);
		} finally { h.cleanup(); }
	});
	uiTest('missing Leaflet and tile errors never supply manual coordinates or default writes', () => {
		const h = uiHarness({ noLeaflet: true }); try { expect(h.maps).toHaveLength(0); expect(h.writes).toEqual([]); expect(h.container.textContent).toContain('Map unavailable'); } finally { h.cleanup(); }
		const tiles = uiHarness(); try { React.act(() => tiles.tiles.at(-1).fire('tileerror')); expect(tiles.container.textContent).toContain('Map unavailable'); expect(tiles.writes).toHaveLength(1); } finally { tiles.cleanup(); } expect(tiles.maps[0].removed).toBe(1);
	});
	uiTest('forced shipping-address registration keeps map out of OrderMeta', () => {
		const h = uiHarness(); try {
			expect(h.registrations).toHaveLength(1); expect(h.registrations[0].force).toBe(true); expect(h.registrations[0].metadata.parent).toEqual(['woocommerce/checkout-shipping-address-block']);
		} finally { h.cleanup(); }
	});
});
