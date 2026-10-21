import { afterAll, describe, expect, test } from 'bun:test';
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';
import { createRouteMarker, normalizeInstantRoute, routePoint, mountInstantRouteMap } from '../src/lib/transaction-detail/instant-route-map';

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

let buildDirectory: string | undefined;
let bundlePromise: Promise<Buffer> | undefined;
let bundleBuildCount = 0;
afterAll(() => {
  try { if (bundlePromise) expect(bundleBuildCount).toBe(1); }
  finally { if (buildDirectory) rmSync(buildDirectory, { recursive: true, force: true }); }
});

// Compile once, but evaluate at a unique path for each fresh Window: Svelte's
// cached native DOM getters must belong to that fixture's document.
function compiledBundle(): Promise<Buffer> { return bundlePromise ??= buildBundle(); }
async function buildBundle(): Promise<Buffer> {
  const directory = buildDirectory = mkdtempSync(join(root, 'node_modules', '.instant-map-build-'));
  bundleBuildCount++;
  const source = readFileSync(join(root, 'src/lib/transaction-detail/InstantRouteMap.svelte'), 'utf8').replace("'./instant-route-map'", JSON.stringify(join(root, 'src/lib/transaction-detail/instant-route-map.ts')));
  writeFileSync(join(directory, 'Production.svelte'), source);
  writeFileSync(join(directory, 'Host.svelte'), `<script>import Production from './Production.svelte'; let {initial, config} = $props(); let data = $state(initial); let mapConfig = $state(config); export function change(value) {data = value;} export function changeConfig(value) {mapConfig = value;}</script><Production {data} config={mapConfig} i18n={{routeOrigin: 'Penjemputan tersimpan', routeDestination: 'Tujuan tersimpan', routeStart: 'Awal rute tercatat', routeEnd: 'Akhir rute tercatat'}} />`);
  writeFileSync(join(directory, 'entry.ts'), `export {mount, unmount, flushSync} from 'svelte'; export {default as Host} from './Host.svelte';`);
  const build = await Bun.build({ entrypoints: [join(directory, 'entry.ts')], outdir: directory, naming: 'runtime.js', target: 'browser', conditions: ['browser'], plugins: [{ name: 'compiled-svelte', setup(builder) {
    builder.onResolve({ filter: /^svelte$/ }, () => ({ path: join(root, 'node_modules/svelte/src/index-client.js') }));
    builder.onLoad({ filter: /\.svelte$/ }, ({ path }) => ({ contents: compile(readFileSync(path, 'utf8'), { filename: path, generate: 'client' }).js.code, loader: 'js' }));
  } }] });
  if (!build.success) throw new Error(build.logs.join('\n'));
  return readFileSync(join(directory, 'runtime.js'));
}

async function fixture(data: any = illustration, missingLeaflet = false, config?: any, google?: any, failSetup?: (stage: string, directory: string) => void) {
  const bundle = await compiledBundle();
  const window = new happy.Window({ url: 'https://fixture.test' });
  const directory = mkdtempSync(join(root, 'node_modules', '.instant-map-runtime-'));
  const saved = new Map<string, PropertyDescriptor | undefined>();
  const maps: any[] = [], lines: any[] = [], markers: any[] = [], layers: any[] = [], observers: any[] = [];
  const timers = new Map<number, () => void>(); let timer = 0, requests = 0, geolocations = 0;
  Object.defineProperty(window.navigator, 'geolocation', { configurable: true, value: { getCurrentPosition() { geolocations++; throw new Error('No geolocation'); }, watchPosition() { geolocations++; throw new Error('No geolocation'); } } });
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
        setZoom(zoom: number) { this.view = { ...this.view, zoom }; return this; },
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
    divIcon(options: any) { return { options }; },
    circleMarker() { throw new Error('Route pins must not use circleMarker'); },
    marker(point: any, options: any) {
      const marker = { point, options, tooltip: null as any,
        bindTooltip(node: any) { this.tooltip = node; return this; },
        addTo(map: any) { map.element.appendChild(options.icon.options.html); return this; },
      }; markers.push(marker); return marker;
    },
  };
  if (!missingLeaflet) (window as any).L = L;
  if (google) (window as any).google = { maps: google };
  const globals = { window, document: window.document, navigator: window.navigator, Node: window.Node, Element: window.Element, HTMLElement: window.HTMLElement, SVGElement: window.SVGElement, DocumentFragment: window.DocumentFragment, Text: window.Text, Comment: window.Comment, Event: window.Event, MutationObserver: window.MutationObserver, ResizeObserver: Observer,
    getComputedStyle: window.getComputedStyle.bind(window), requestAnimationFrame: window.requestAnimationFrame.bind(window), cancelAnimationFrame: window.cancelAnimationFrame.bind(window),
    setTimeout: (callback: () => void) => { timers.set(++timer, callback); return timer; }, clearTimeout: (id: number) => timers.delete(id),
    fetch: () => { requests++; throw new Error('Map must not fetch API data'); },
  };
  let r: any, host: any;
  let destroyed = false;
  const settle = async () => { for (let i = 0; i < 6; i++) { await Promise.resolve(); r?.flushSync(); } };
  const destroy = async () => { if (host && !destroyed) { destroyed = true; await r.unmount(host); await settle(); } };
  async function cleanup() {
    try { await destroy(); expect(timers.size).toBe(0); expect(requests).toBe(0); expect(geolocations).toBe(0); }
    finally {
      timers.clear();
      try { await window.happyDOM.abort(); }
      finally {
        try { for (const [key, descriptor] of saved) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete (globalThis as any)[key]; } }
        finally { rmSync(directory, { recursive: true, force: true }); }
      }
    }
  }
  try {
    for (const [key, value] of Object.entries(globals)) { saved.set(key, Object.getOwnPropertyDescriptor(globalThis, key)); Object.defineProperty(globalThis, key, { configurable: true, writable: true, value }); }
    failSetup?.('globals', directory);
    writeFileSync(join(directory, 'runtime.js'), bundle);
    r = await import(join(directory, 'runtime.js'));
    failSetup?.('imported', directory);
    const target = window.document.createElement('main'); window.document.body.append(target);
    host = r.mount(r.Host, { target, props: { initial: data, config: config ?? { enabled: true, tiles: '/tiles/{z}/{x}/{y}.png', attribution: 'Saved map' } } });
    failSetup?.('mounted', directory);
    await settle();
    failSetup?.('settled', directory);
    return { window, target, maps, lines, markers, layers, observers, timers, settle, destroy,
      requests: () => requests,
      change: async (value: any) => { host.change(value); await settle(); },
      changeConfig: async (value: any) => { host.changeConfig(value); await settle(); },
      cleanup,
    };
  } catch (error) {
    await cleanup();
    throw error;
  }
}

test('fixture setup failures restore globals and remove temporary runtime paths', async () => {
  for (const stage of ['globals', 'imported', 'mounted', 'settled']) {
    const before = Object.getOwnPropertyDescriptors(globalThis);
    const oldNow = Date.now;
    let directory = '';
    const failure = new Error(`Injected setup failure: ${stage}`);
    function inject(current: string, path: string) { if (current === stage) { directory = path; throw failure; } }
    await expect(fixture(illustration, false, undefined, undefined, inject)).rejects.toBe(failure);
    expect(directory).not.toBe('');
    expect(existsSync(directory)).toBe(false);
    expect(Date.now).toBe(oldNow);
    const after = Object.getOwnPropertyDescriptors(globalThis);
    for (const key of ['window', 'document', 'navigator', 'Node', 'Element', 'HTMLElement', 'HTMLButtonElement', 'SVGElement', 'DocumentFragment', 'HTMLInputElement', 'HTMLSelectElement', 'Text', 'Comment', 'Event', 'CustomEvent', 'MutationObserver', 'ResizeObserver', 'getComputedStyle', 'requestAnimationFrame', 'cancelAnimationFrame', 'setTimeout', 'clearTimeout', 'fetch', '__instantDialogAjax', '__instantOperationAjax']) {
      expect(after[key]).toEqual(before[key]);
    }
  }
});

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


function googleFixture(failAttachment = 0) {
  const maps: any[] = [], overlays: any[] = [], cleared: object[] = [], projected: any[] = [];
  let circleCalls = 0, attachments = 0;
  class LatLng {
    constructor(private latitude: number, private longitude: number) {}
    lat() { return this.latitude; }
    lng() { return this.longitude; }
  }
  class APIMap {
    fit: any;
    zoom = 13;
    markerLayer: HTMLElement;
    constructor(public node: HTMLElement, public options: any) {
      this.markerLayer = node.ownerDocument.createElement('div');
      node.appendChild(this.markerLayer); maps.push(this);
    }
    fitBounds(bounds: any, padding: any) { this.fit = { bounds, padding }; }
    setZoom(zoom: number) {
      this.zoom = zoom;
      for (const overlay of overlays) if (overlay.map === this && overlay.draw) overlay.draw();
    }
  }
  class Polyline {
    detached = 0;
    constructor(public options: any) { overlays.push(this); }
    setMap(value: any) { if (value === null) this.detached++; }
  }
  class OverlayView {
    map: APIMap | null = null;
    lastMap: APIMap | null = null;
    detached = 0;
    constructor() { overlays.push(this); }
    onAdd() {}
    draw() {}
    onRemove() {}
    getPanes() { return { markerLayer: this.lastMap!.markerLayer }; }
    getProjection() {
      return { fromLatLngToDivPixel: (point: LatLng) => {
        expect(point).toBeInstanceOf(LatLng);
        projected.push([point.lat(), point.lng()]);
        const scale = 2 ** this.lastMap!.zoom;
        return { x: (point.lng() + 180) * scale, y: (90 - point.lat()) * scale };
      } };
    }
    setMap(value: APIMap | null) {
      this.map = value;
      if (value) {
        this.lastMap = value; this.onAdd(); this.draw();
        if (++attachments === failAttachment) throw new Error('Partial provider attachment');
      } else { this.detached++; this.onRemove(); }
    }
  }
  return { maps, overlays, cleared, projected, circleCalls: () => circleCalls,
    api: { Map: APIMap, Polyline, OverlayView, LatLng,
      Circle: class { constructor() { circleCalls++; throw new Error('No geographic Circle pins'); } },
      event: { clearInstanceListeners: (instance: object) => cleared.push(instance), trigger() {} },
    },
  };
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
    expect(google.overlays).toHaveLength(3);
    expect(google.overlays.slice(1).every(o => o instanceof google.api.OverlayView)).toBe(true);
    expect(google.circleCalls()).toBe(0);
    expect(google.projected).toEqual([[0, 0], [-6.2, 106.8]]);
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

const markerPaths = {
  origin: ['M3 21v-13l9 -4l9 4v13', 'M13 13h4v8h-10v-6h6', 'M13 21v-9a1 1 0 0 0 -1 -1h-2a1 1 0 0 0 -1 1v3'],
  destination: ['M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0', 'M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0'],
};
function assertMarker(node: any, kind: 'origin' | 'destination', label: string) {
  expect(node.style.width).toBe('32px'); expect(node.style.height).toBe('32px');
  expect(node.getAttribute('role')).toBe('img'); expect(node.getAttribute('aria-label')).toBe(label); expect(node.title).toBe(label);
  expect(node.classList.contains('bg-card')).toBe(true);
  expect(node.classList.contains(kind === 'origin' ? 'border-success' : 'border-destructive')).toBe(true);
  expect(node.classList.contains(kind === 'origin' ? 'text-success' : 'text-destructive')).toBe(true);
  const svg = node.querySelector('svg');
  expect(svg.namespaceURI).toBe('http://www.w3.org/2000/svg');
  expect(svg.getAttribute('width')).toBe('16'); expect(svg.getAttribute('height')).toBe('16');
  expect(svg.getAttribute('viewBox')).toBe('0 0 24 24'); expect(svg.getAttribute('aria-hidden')).toBe('true');
  expect(svg.getAttribute('focusable')).toBe('false');
  expect(svg.getAttribute('data-icon')).toBe(kind === 'origin' ? 'building-warehouse' : 'map-pin');
  expect([...svg.querySelectorAll('path')].map((path: any) => path.getAttribute('d'))).toEqual(markerPaths[kind]);
}

const nearby = { origin: { latitude: 0, longitude: 0 }, destination: { latitude: 0.00001, longitude: 0.00001 }, points: [], mode: 'illustration' as const };

test('shared fixed DOM markers treat untrusted labels as attributes, never HTML', () => {
  const window = new happy.Window();
  try {
    const label = '<img src=x onerror="alert(1)"><script>alert(2)</script>&"';
    for (const kind of ['origin', 'destination'] as const) {
      const node = createRouteMarker(window.document as any, kind, label);
      assertMarker(node, kind, label);
      expect(node.querySelector('img, script')).toBeNull();
      expect(node.querySelector('[onerror], [onclick]')).toBeNull();
      expect(node.children).toHaveLength(1);
    }
  } finally { window.happyDOM.abort(); }
});

test('compiled Leaflet nearby endpoints keep distinct fixed 32px divIcons at zoom 5, 13 and 18 without changing saved coordinates', async () => {
  const before = JSON.stringify(nearby);
  const h = await fixture(nearby);
  try {
    expect(h.markers).toHaveLength(2);
    expect(h.markers.map(m => m.point)).toEqual([[0, 0], [0.00001, 0.00001]]);
    expect(h.lines[0].points).toEqual([[0, 0], [0.00001, 0.00001]]);
    for (const zoom of [5, 13, 18]) {
      h.maps[0].setZoom(zoom);
      h.markers.forEach((marker, index) => {
        const label = index === 0 ? 'Penjemputan tersimpan' : 'Tujuan tersimpan';
        expect(marker.options.icon.options.iconSize).toEqual([32, 32]);
        expect(marker.options.icon.options.iconAnchor).toEqual([16, 16]);
        expect(marker.options.icon.options.className).toBe('');
        expect(marker.options).toMatchObject({ title: label, alt: label, draggable: false, keyboard: false });
        expect(marker.tooltip.textContent).toBe(label);
        const node = marker.options.icon.options.html;
        expect(h.maps[0].element.contains(node)).toBe(true);
        assertMarker(node, index === 0 ? 'origin' : 'destination', label);
      });
    }
    expect(JSON.stringify(nearby)).toBe(before);
  } finally { await h.cleanup(); }
});

test('compiled Google nearby endpoint overlays redraw only pixel position at zoom 5, 13 and 18 and ignore stale callbacks after disposal', async () => {
  const google = googleFixture(); const before = JSON.stringify(nearby);
  const h = await fixture(nearby, false, googleConfig, google.api);
  try {
    expect(google.overlays).toHaveLength(3);
    expect(google.overlays[0].options.path).toEqual([{ lat: 0, lng: 0 }, { lat: 0.00001, lng: 0.00001 }]);
    expect(google.overlays[0].options.strokeOpacity).toBe(0);
    const nodes = [...google.maps[0].markerLayer.children] as HTMLElement[];
    expect(nodes).toHaveLength(2);
    const positions: string[] = [];
    for (const zoom of [5, 13, 18]) {
      google.maps[0].setZoom(zoom);
      nodes.forEach((node, index) => {
        assertMarker(node, index === 0 ? 'origin' : 'destination', index === 0 ? 'Penjemputan tersimpan' : 'Tujuan tersimpan');
        expect(node.style.position).toBe('absolute'); expect(node.style.transform).toBe('translate(-50%, -50%)');
        expect(node.style.pointerEvents).toBe('none');
        const point = index === 0 ? [0, 0] : [0.00001, 0.00001];
        expect(node.style.left).toBe(`${(point[1]! + 180) * 2 ** zoom}px`);
        expect(node.style.top).toBe(`${(90 - point[0]!) * 2 ** zoom}px`);
      });
      expect(nodes[0]!.style.left).not.toBe(nodes[1]!.style.left);
      expect(nodes[0]!.style.top).not.toBe(nodes[1]!.style.top);
      positions.push(nodes.map(n => `${n.style.left}/${n.style.top}`).join(','));
    }
    expect(new Set(positions).size).toBe(3);
    expect(JSON.stringify(nearby)).toBe(before); expect(google.circleCalls()).toBe(0);
    const styles = nodes.map(n => n.getAttribute('style')); const projected = google.projected.length;
    await h.destroy();
    for (const overlay of google.overlays.slice(1)) { overlay.onAdd(); overlay.draw(); }
    expect(google.projected).toHaveLength(projected);
    expect(nodes.map(n => n.getAttribute('style'))).toEqual(styles);
    expect(nodes.every(n => !n.isConnected)).toBe(true);
    expect(google.maps[0].markerLayer.children).toHaveLength(0);
    expect(google.overlays.every(o => o.detached === 1)).toBe(true);
    expect(google.cleared).toHaveLength(4);
  } finally { await h.cleanup(); }
});

test('compiled Google recorded endpoints use translated fallback labels and keep geometry immutable', async () => {
  const google = googleFixture(); const data = { ...recorded, origin: null, destination: null };
  const before = JSON.stringify(data);
  const h = await fixture(data, false, googleConfig, google.api);
  try {
    const nodes = [...google.maps[0].markerLayer.children];
    assertMarker(nodes[0], 'origin', 'Awal rute tercatat');
    assertMarker(nodes[1], 'destination', 'Akhir rute tercatat');
    expect(google.projected).toEqual([recorded.points[0], recorded.points[2]]);
    google.overlays[0].options.path[0].lat = 42;
    expect(JSON.stringify(data)).toBe(before);
    await h.change({ ...recorded, destination: null });
    const next = [...google.maps[1].markerLayer.children];
    assertMarker(next[0], 'origin', 'Penjemputan tersimpan'); assertMarker(next[1], 'destination', 'Akhir rute tercatat');
  } finally { await h.cleanup(); }
});

for (const failAttachment of [1, 2]) {
  test(`Google partial marker attachment ${failAttachment} failure detaches every tracked overlay and removes marker DOM`, async () => {
    const google = googleFixture(failAttachment);
    const h = await fixture(nearby, false, googleConfig, google.api);
    try {
      expect(google.overlays).toHaveLength(failAttachment + 1);
      expect(google.overlays.every(o => o.detached === 1)).toBe(true);
      expect(google.cleared).toHaveLength(failAttachment + 2);
      expect(google.maps[0].markerLayer.children).toHaveLength(0);
      expect(google.maps[0].node.children).toHaveLength(0);
      expect(h.target.textContent).toContain('could not be displayed');
      await h.destroy();
      for (const overlay of google.overlays.slice(1)) { overlay.onAdd(); overlay.draw(); }
      expect(google.maps[0].markerLayer.children).toHaveLength(0);
      expect(google.overlays.every(o => o.detached === 1)).toBe(true);
      expect(google.circleCalls()).toBe(0);
    } finally { await h.cleanup(); }
  });
}

test('fresh map fixtures own their DOM and map resources independently', async () => {
  const first = await fixture();
  try {
    expect(first.maps[0].element.ownerDocument).toBe(first.window.document);
    expect(first.markers[0].options.icon.options.html.ownerDocument).toBe(first.window.document);
  } finally { await first.cleanup(); }
  const second = await fixture(recorded);
  try {
    expect(second.window.document).not.toBe(first.window.document);
    expect(second.maps[0]).not.toBe(first.maps[0]);
    expect(second.maps[0].element.ownerDocument).toBe(second.window.document);
    expect(second.markers[0].options.icon.options.html.ownerDocument).toBe(second.window.document);
    expect(second.markers[0].tooltip.ownerDocument).toBe(second.window.document);
    expect(first.maps).toHaveLength(1); expect(first.maps[0].removed).toBe(1);
    expect(first.maps[0].offCount).toBe(1); expect(first.observers[0].disconnected).toBe(true);
    expect(first.timers.size).toBe(0); expect(first.requests()).toBe(0); expect(second.requests()).toBe(0);
  } finally { await second.cleanup(); }
});

test('map bundle compiles at most once per suite', () => {
  expect(bundleBuildCount).toBe(bundlePromise ? 1 : 0);
});
