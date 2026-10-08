import { describe, expect, test } from 'bun:test';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';
import { normalizeInstantRoute, routePoint, mountInstantRouteMap } from '../src/lib/transaction-detail/instant-route-map';

const root = resolve(import.meta.dir, '..');
const illustration = { origin: { latitude: 0, longitude: 0 }, destination: { latitude: -6.2, longitude: 106.8 }, points: [], mode: 'illustration' as const };
const recorded = { ...illustration, mode: 'recorded' as const, points: [[0, 0], [-6.1, 106.7], [-6.2, 106.8]] as [number, number][] };

test('saved coordinates accept zero/decimal strings, reject missing, non-finite or out-of-range values and copy bounded recorded paths', () => {
  expect(routePoint('0', '0.0')).toEqual([0, 0]);
  for (const invalid of ['', ' ', null, false, Infinity, NaN, '0x10', '1e2', 'invalid']) expect(routePoint(invalid, 0)).toBeNull();
  expect(routePoint(91, 0)).toBeNull(); expect(routePoint(0, 181)).toBeNull();
  expect(normalizeInstantRoute({ ...illustration, origin: null })).toBeNull();
  expect(normalizeInstantRoute({ ...recorded, points: [[0, 0]] })).toBeNull();
  expect(normalizeInstantRoute({ ...recorded, points: Array.from({ length: 10001 }, () => [0, 0]) })).toBeNull();
  const normalized = normalizeInstantRoute(recorded)!;
  normalized.points[0]![0] = 4;
  normalized.origin![0] = 5;
  expect(recorded.points[0]).toEqual([0, 0]); expect(recorded.origin.latitude).toBe(0);
  expect(normalizeInstantRoute(illustration)?.points).toEqual([[0, 0], [-6.2, 106.8]]);
  expect(normalizeInstantRoute({ ...recorded, origin: null, destination: null })).toEqual({ origin: null, destination: null, points: recorded.points, mode: 'recorded' });
  expect(normalizeInstantRoute({ ...recorded, origin: { latitude: 91, longitude: 0 } })?.origin).toBeNull();
  expect(normalizeInstantRoute({ ...illustration, destination: null })).toBeNull();
});

async function fixture(data: any = illustration, missingLeaflet = false, config?: any, google?: any) {
  const window = new happy.Window({ url: 'https://fixture.test' });
  const directory = mkdtempSync(join(root, 'node_modules', '.instant-map-runtime-'));
  const saved = new Map<string, PropertyDescriptor | undefined>();
  const maps: any[] = [], lines: any[] = [], markers: any[] = [], layers: any[] = [], observers: any[] = [];
  const timers = new Map<number, () => void>(); let timer = 0, requests = 0;
  class Observer {
    disconnected = false;
    constructor(public callback: () => void) { observers.push(this); }
    observe() {}
    disconnect() { this.disconnected = true; }
  }
  const L = {
    map(element: any, options: any) {
      const map = { element, options, removed: 0, offCount: 0, fit: null as any, view: null as any, resized: 0,
        fitBounds(points: any, options: any) { this.fit = { points, options }; return this; },
        setView(point: any, zoom: any) { this.view = { point, zoom }; return this; },
        invalidateSize() { this.resized++; }, off() { this.offCount++; }, remove() { this.removed++; },
      }; maps.push(map); return map;
    },
    tileLayer(url: any, options: any) {
      const layer = { url, options, callback: null as any, offCount: 0,
        on(_name: string, callback: any) { this.callback = callback; return this; },
        off() { this.offCount++; }, addTo() { return this; },
      }; layers.push(layer); return layer;
    },
    polyline(points: any, options: any) { const line = { points, options, addTo() { return this; } }; lines.push(line); return line; },
    circleMarker(point: any, options: any) { const marker = { point, options, tooltip: null as any, bindTooltip(node: any) { this.tooltip = node; return this; }, addTo() { return this; } }; markers.push(marker); return marker; },
  };
  if (!missingLeaflet) (window as any).L = L;
  if (google) (window as any).google = { maps: google };
  const globals = { window, document: window.document, navigator: window.navigator, Node: window.Node, Element: window.Element, HTMLElement: window.HTMLElement, SVGElement: window.SVGElement, DocumentFragment: window.DocumentFragment, Text: window.Text, Comment: window.Comment, Event: window.Event, MutationObserver: window.MutationObserver, ResizeObserver: Observer,
    getComputedStyle: window.getComputedStyle.bind(window), requestAnimationFrame: window.requestAnimationFrame.bind(window), cancelAnimationFrame: window.cancelAnimationFrame.bind(window),
    setTimeout: (callback: () => void) => { timers.set(++timer, callback); return timer; }, clearTimeout: (id: number) => timers.delete(id),
    fetch: () => { requests++; throw new Error('Map must not fetch API data'); },
  };
  for (const [key, value] of Object.entries(globals)) { saved.set(key, Object.getOwnPropertyDescriptor(globalThis, key)); Object.defineProperty(globalThis, key, { configurable: true, writable: true, value }); }
  const source = readFileSync(join(root, 'src/lib/transaction-detail/InstantRouteMap.svelte'), 'utf8').replace("'./instant-route-map'", JSON.stringify(join(root, 'src/lib/transaction-detail/instant-route-map.ts')));
  writeFileSync(join(directory, 'Production.svelte'), source);
  writeFileSync(join(directory, 'Host.svelte'), `<script>import Production from './Production.svelte'; let {initial, config} = $props(); let data = $state(initial); let mapConfig = $state(config); export function change(value) {data = value;} export function changeConfig(value) {mapConfig = value;}</script><Production {data} config={mapConfig} i18n={{routeOrigin: 'Penjemputan tersimpan', routeDestination: 'Tujuan tersimpan', routeStart: 'Awal rute tercatat', routeEnd: 'Akhir rute tercatat'}} />`);
  writeFileSync(join(directory, 'entry.ts'), `export {mount, unmount, flushSync} from 'svelte'; export {default as Host} from './Host.svelte';`);
  const build = await Bun.build({ entrypoints: [join(directory, 'entry.ts')], outdir: directory, naming: 'runtime.js', target: 'browser', conditions: ['browser'], plugins: [{ name: 'compiled-svelte', setup(builder) {
    builder.onResolve({ filter: /^svelte$/ }, () => ({ path: join(root, 'node_modules/svelte/src/index-client.js') }));
    builder.onLoad({ filter: /\.svelte$/ }, ({ path }) => ({ contents: compile(readFileSync(path, 'utf8'), { filename: path, generate: 'client' }).js.code, loader: 'js' }));
  } }] });
  if (!build.success) throw new Error(build.logs.join('\n'));
  const r = await import(join(directory, 'runtime.js'));
  const target = window.document.createElement('main'); window.document.body.append(target);
  const host = r.mount(r.Host, { target, props: { initial: data, config: config ?? { enabled: true, tiles: '/tiles/{z}/{x}/{y}.png', attribution: 'Saved map' } } });
  const settle = async () => { for (let i = 0; i < 6; i++) { await Promise.resolve(); r.flushSync(); } };
  await settle();
  let destroyed = false;
  const destroy = async () => { if (!destroyed) { destroyed = true; await r.unmount(host); await settle(); } };
  return { window, target, maps, lines, markers, layers, observers, timers, settle, destroy,
    requests: () => requests,
    change: async (value: any) => { host.change(value); await settle(); },
    changeConfig: async (value: any) => { host.changeConfig(value); await settle(); },
    cleanup: async () => { try { await destroy(); expect(timers.size).toBe(0); expect(requests).toBe(0); } finally { window.happyDOM.abort(); for (const [key, descriptor] of saved) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete (globalThis as any)[key]; } rmSync(directory, { recursive: true, force: true }); } },
  };
}

describe('InstantRouteMap compiled Svelte lifecycle', () => {
  test('zero-valued saved points render dashed illustration; prop updates render solid recorded path and dispose all resources', async () => {
    const h = await fixture(); try {
      expect(h.maps).toHaveLength(1); expect(h.lines[0].points[0]).toEqual([0, 0]); expect(h.lines[0].options.dashArray).toBe('6 8');
      expect(h.target.textContent).toContain('not a driving route or live tracking');
      expect(h.maps[0].fit.options).toEqual({ padding: [24, 24], maxZoom: 15 });
      expect(h.markers.map(marker => marker.tooltip.textContent)).toEqual(['Penjemputan tersimpan', 'Tujuan tersimpan']);
      expect(h.markers[0].tooltip.nodeType).toBe(1);
      expect(h.target.querySelector('[role="region"]')?.getAttribute('tabindex')).toBe('0');
      h.observers[0].callback(); h.observers[0].callback(); expect(h.timers.size).toBe(1);
      const staleTileError = h.layers[0].callback;
      await h.change(recorded);
      expect(h.maps).toHaveLength(2); expect(h.maps[0].removed).toBe(1); expect(h.maps[0].offCount).toBe(1);
      expect(h.observers[0].disconnected).toBe(true); expect(h.layers[0].offCount).toBe(2); expect(h.timers.size).toBe(0);
      expect(h.lines[1].options.dashArray).toBeUndefined(); expect(h.lines[1].points).toEqual(recorded.points);
      expect(h.target.textContent).toContain('Saved route data — not live tracking.');
      staleTileError(); await h.settle(); expect(h.target.querySelector('[role="status"]')).toBeNull();
      h.layers[1].callback(); await h.settle();
      expect(h.target.textContent).toContain('could not be displayed'); expect(h.target.textContent).toContain('not live tracking');
      expect(h.maps[1].removed).toBe(0);
      h.observers[1].callback(); const callback = [...h.timers.values()][0]!; h.timers.clear(); callback(); expect(h.maps[1].resized).toBe(1);
      await h.changeConfig({ enabled: true, tiles: '/new/{z}/{x}/{y}', attribution: '' });
      expect(h.maps).toHaveLength(3); expect(h.maps[1].removed).toBe(1);
      h.observers[2].callback(); const unmountedTimer = [...h.timers.values()][0]!;
      await h.destroy(); expect(h.maps[2].removed).toBe(1); expect(h.observers[2].disconnected).toBe(true);
      unmountedTimer(); h.observers[2].callback(); h.layers[2].callback(); await h.settle();
      expect(h.maps[2].resized).toBe(0); expect(h.timers.size).toBe(0);
      expect(h.requests()).toBe(0);
    } finally { await h.cleanup(); }
  });
  test('missing coordinates produce no empty map; identical points use a bounded local view', async () => {
    const h = await fixture({ ...illustration, origin: null }); try {
      expect(h.maps).toHaveLength(0); expect(h.target.textContent).toContain('coordinates are unavailable'); expect(h.target.querySelector('[role="region"]')).toBeNull();
      await h.change({ ...illustration, destination: { latitude: 0, longitude: 0 } });
      expect(h.maps[0].view).toEqual({ point: [0, 0], zoom: 13 }); expect(h.maps[0].fit).toBeNull();
      await h.change(null); expect(h.maps[0].removed).toBe(1); expect(h.target.querySelector('[role="region"]')).toBeNull();
    } finally { await h.cleanup(); }
  });
  test('missing preloaded Leaflet is isolated and does not fetch a CDN or hide the illustrative warning', async () => {
    const h = await fixture(illustration, true); try {
      expect(h.maps).toHaveLength(0); expect(h.target.textContent).toContain('could not be displayed'); expect(h.target.textContent).toContain('not a driving route or live tracking');
      expect(h.requests()).toBe(0);
    } finally { await h.cleanup(); }
  });
  test('recorded paths without saved pins use distinct translated route endpoint labels and remain read-only', async () => {
    const data = { ...recorded, origin: null, destination: null };
    const h = await fixture(data); try {
      expect(h.maps).toHaveLength(1);
      expect(h.lines[0].points).toEqual(recorded.points);
      expect(h.lines[0].options.dashArray).toBeUndefined();
      expect(h.markers.map(marker => marker.point)).toEqual([recorded.points[0], recorded.points[2]]);
      expect(h.markers.map(marker => marker.tooltip.textContent)).toEqual(['Awal rute tercatat', 'Akhir rute tercatat']);
      expect(h.target.textContent).toContain('not live tracking');
      expect(h.target.querySelector('button, input, select, form')).toBeNull();
      h.lines[0].points[0][0] = 42;
      h.markers[0].point[0] = 43;
      expect(data.points[0]).toEqual([0, 0]);
      expect(h.maps[0].fit.points[0]).toEqual([0, 0]);
      await h.change({ ...recorded, destination: null });
      expect(h.markers.slice(2).map(marker => marker.tooltip.textContent)).toEqual(['Penjemputan tersimpan', 'Akhir rute tercatat']);
      await h.change({ ...recorded, origin: { latitude: 91, longitude: 0 } });
      expect(h.markers.slice(4).map(marker => marker.tooltip.textContent)).toEqual(['Awal rute tercatat', 'Tujuan tersimpan']);
      await h.changeConfig({ enabled: false, tiles: '/tiles/{z}/{x}/{y}.png', attribution: '' });
      expect(h.maps).toHaveLength(3);
      expect(h.maps[2].removed).toBe(1);
      expect(h.target.textContent).toContain('could not be displayed');
      expect(h.target.textContent).toContain('not live tracking');
      expect(h.requests()).toBe(0);
    } finally { await h.cleanup(); }
  });
});


function googleFixture() {
  const maps: any[] = [], overlays: any[] = [], cleared: object[] = [];
  class Map {
    fit: any;
    constructor(public node: HTMLElement, public options: any) { maps.push(this); }
    fitBounds(bounds: any, padding: any) { this.fit = { bounds, padding }; }
  }
  class Overlay {
    detached = 0;
    constructor(public options: any) { overlays.push(this); }
    setMap(value: any) { if (value === null) this.detached++; }
  }
  return { maps, overlays, cleared, api: { Map, Polyline: Overlay, Circle: Overlay, event: {
    clearInstanceListeners: (instance: object) => cleared.push(instance), trigger() {},
  } } };
}

const googleConfig = { enabled: true, provider: 'google', apiKey: 'fixture_key', tiles: '', attribution: '' };
test('Google route map copies original recorded geometry, uses read-only overlays and disposes on provider changes', async () => {
  const google = googleFixture();
  const h = await fixture(recorded, false, googleConfig, google.api);
  try {
    expect(h.maps).toHaveLength(0); expect(h.layers).toHaveLength(0);
    expect(google.maps).toHaveLength(1);
    expect(google.maps[0].options.draggable).toBe(false);
    expect(google.overlays[0].options.path).toEqual(recorded.points.map(([lat, lng]) => ({ lat, lng })));
    expect(google.overlays[0].options.geodesic).toBe(false);
    // The production adapter resolves semantic theme colors in the DOM. This
    // unstyled fixture uses currentColor instead of pinning a duplicate palette.
    expect(google.overlays.slice(1).every(o => typeof o.options.fillColor === 'string' && o.options.fillColor.length > 0)).toBe(true);
    expect(google.maps[0].fit.padding).toBe(24);
    google.overlays[0].options.path[0].lat = 42;
    expect(recorded.points[0]).toEqual([0, 0]);
    await h.changeConfig({ ...googleConfig, provider: 'unknown' });
    expect(google.overlays.every(o => o.detached === 1)).toBe(true);
    expect(google.cleared).toHaveLength(4);
    expect(h.maps).toHaveLength(0);
    expect(h.target.textContent).toContain('could not be displayed');
  } finally { await h.cleanup(); }
});

test('late Google loader completion after disposal creates no stale route map or Leaflet fallback', async () => {
  const google = googleFixture();
  const window = new happy.Window({ url: 'https://fixture.test' });
  const previous = Object.getOwnPropertyDescriptor(globalThis, 'window');
  Object.defineProperty(globalThis, 'window', { configurable: true, value: window });
  (window as any).google = { maps: google.api };
  const container = window.document.createElement('div');
  try {
    const session = mountInstantRouteMap(container as any, normalizeInstantRoute(recorded)!, googleConfig, () => { throw new Error('Unexpected map error'); });
    session.dispose();
    await Promise.resolve(); await Promise.resolve();
    expect(google.maps).toHaveLength(0);
    expect(google.overlays).toHaveLength(0);
  } finally {
    if (previous) Object.defineProperty(globalThis, 'window', previous);
    else delete (globalThis as any).window;
    window.happyDOM.abort();
  }
});
