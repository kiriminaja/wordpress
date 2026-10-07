import { describe, expect, test } from 'bun:test';
import type * as Leaflet from 'leaflet';
import {
  resolveMapConfig,
  DEFAULT_MAP_TILES,
  isSupportedMapProvider,
} from '../src/buyer/map/config';
import {
  createMapSession,
  createLocationGate,
  normalizePoint,
  coverageDistance,
  coverageStatus,
  mapProviderRegistry,
} from '../src/buyer/map/leaflet';
import type { MapSessionOptions, GeolocationProvider, Point } from '../src/buyer/map/types';

function fixture(extra: Partial<MapSessionOptions> = {}) {
  const handlers = new Map<string, (event: unknown) => void>();
  const selections: Array<Point | null> = [],
    errors: string[] = [];
  const timers = new Map<number, () => void>();
  let center = { lat: 0, lng: 0 },
    removed = 0,
    mapCalls = 0,
    tileCalls = 0,
    sequence = 0;
  const views: unknown[] = [];
  const map = {
    setView(next: [number, number], zoom: number) {
      center = { lat: next[0], lng: next[1] };
      views.push([next, zoom]);
      return map;
    },
    on(name: string, callback: (event: unknown) => void) {
      handlers.set(name, callback);
      return map;
    },
    getCenter() {
      return center;
    },
    invalidateSize() {},
    remove() {
      removed++;
      handlers.clear();
    },
  };
  const layer = {
    addTo() {
      return layer;
    },
    on() {
      return layer;
    },
  };
  const leaflet = {
    map() {
      mapCalls++;
      return map;
    },
    tileLayer() {
      tileCalls++;
      return layer;
    },
  } as unknown as typeof Leaflet;
  const session = createMapSession({
    leaflet,
    node: { nodeType: 1 } as HTMLElement,
    schedule(callback) {
      timers.set(++sequence, callback);
      return sequence;
    },
    cancel(handle) {
      timers.delete(handle as number);
    },
    onSelect(point) {
      selections.push(point);
    },
    onError(error) {
      errors.push(error);
    },
    ...extra,
  });
  return {
    session,
    selections,
    errors,
    views,
    timers,
    counts: () => ({ removed, mapCalls, tileCalls }),
    fire(name: string, event: unknown = {}) {
      handlers.get(name)?.(event);
    },
    center(next: typeof center) {
      center = next;
    },
  };
}

describe('map provider configuration and typed core', () => {
  test('defaults to public HTTPS Leaflet tiles and only registers Leaflet', () => {
    expect(resolveMapConfig()).toEqual({
      provider: 'leaflet',
      tiles: DEFAULT_MAP_TILES,
      attribution:
        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    });
    expect(Object.keys(mapProviderRegistry)).toEqual(['leaflet']);
    expect(Object.isFrozen(resolveMapConfig())).toBe(true);
    expect(isSupportedMapProvider('google')).toBe(false);
    expect(
      resolveMapConfig({ tiles: 'https://tiles.example/{z}/{x}/{y}', attribution: 'Seller' })
        .attribution,
    ).toBe('Seller');
  });
  test('unknown providers fail before invoking Leaflet or requesting tiles', () => {
    for (const provider of ['google', 'googlemaps', 'unknown', 'Leaflet', '']) {
      expect(() => resolveMapConfig({ provider })).toThrow('Unsupported map provider');
      const f = fixture({ provider });
      expect(f.counts()).toEqual({ removed: 0, mapCalls: 0, tileCalls: 0 });
      expect(f.session.isAvailable()).toBe(false);
      expect(f.errors).toEqual(['unavailable']);
      f.session.dispose();
    }
    for (const tiles of ['', 'http://tiles.example', 'javascript:alert(1)'])
      expect(() => resolveMapConfig({ tiles })).toThrow();
  });
  test('normalizes exact coordinate range and precision without accepting coercible non-numbers', () => {
    expect(normalizePoint(' -6.2 ', 106.8)).toEqual({
      latitude: '-6.2000000',
      longitude: '106.8000000',
    });
    expect(normalizePoint(0, 0)).toEqual({ latitude: '0.0000000', longitude: '0.0000000' });
    expect(normalizePoint(-90, 180)).not.toBeNull();
    for (const invalid of ['', ' ', true, null, undefined, {}, NaN, Infinity, 91])
      expect(normalizePoint(invalid, 0)).toBeNull();
  });
  test('coverage remains straight-line metres and inclusive within one millimetre', () => {
    const origin = { latitude: 0, longitude: 0 };
    expect(coverageDistance(origin, origin)).toBe(0);
    expect(coverageDistance(origin, { latitude: 0, longitude: 180 })).toBeCloseTo(
      Math.PI * 6371000,
      6,
    );
    for (const [distance, inside] of [
      [40000, true],
      [40000.0005, true],
      [40000.002, false],
    ] as const) {
      const point = { latitude: 0, longitude: ((distance / 6371000) * 180) / Math.PI };
      expect(coverageStatus({ origin, radiusMeters: 40000 }, point)?.inside).toBe(inside);
    }
    for (const radiusMeters of [null, false, '', ' ', 0, -1, Infinity])
      expect(coverageStatus({ origin, radiusMeters }, origin)).toBeNull();
    expect(coverageDistance(origin, { latitude: true, longitude: 0 })).toBeNull();
  });
  test('default view never selects a pin; clicks and user movement select, dispose cancels work', () => {
    const f = fixture();
    expect(f.session.isAvailable()).toBe(true);
    expect(f.session.getPoint()).toBeNull();
    expect(f.selections).toEqual([]);
    expect(f.views).toEqual([[[-6.2088, 106.8456], 13]]);
    f.fire('click', { latlng: { lat: 0, lng: 1 } });
    expect(f.session.getPoint()).toEqual({ latitude: '0.0000000', longitude: '1.0000000' });
    f.fire('movestart');
    f.center({ lat: 2, lng: 3 });
    f.fire('moveend');
    expect(f.session.getPoint()?.latitude).toBe('2.0000000');
    const copy = f.session.getPoint()!;
    copy.latitude = 'mutated';
    expect(f.session.getPoint()?.latitude).toBe('2.0000000');
    f.session.clear();
    expect(f.session.getPoint()).toBeNull();
    f.session.dispose();
    f.session.dispose();
    expect(f.timers.size).toBe(0);
    expect(f.counts().removed).toBe(1);
    expect(f.session.pick(0, 0)).toBe(false);
  });
  test('selection veto and duplicate coordinates preserve exact buyer state', () => {
    const selected: Array<Point | null> = [];
    const f = fixture({
      onSelect(point) {
        selected.push(point);
        return point?.latitude !== '1.0000000';
      },
    });
    expect(f.session.pick(0, 0)).toBe(true);
    expect(f.session.pick('0', '0')).toBe(true);
    expect(selected).toHaveLength(1);
    expect(f.session.pick(1, 0)).toBe(false);
    expect(f.session.getPoint()?.latitude).toBe('0.0000000');
    f.session.dispose();
  });
  test('one location permission request per gate, stale callbacks ignored after success/dispose', () => {
    let success: Parameters<GeolocationProvider['getCurrentPosition']>[0] | undefined;
    let failure: Parameters<GeolocationProvider['getCurrentPosition']>[1] | undefined;
    let calls = 0;
    const points: Point[] = [],
      errors: string[] = [];
    const gate = createLocationGate({
      geolocation: {
        getCurrentPosition(yes, no, options) {
          calls++;
          success = yes;
          failure = no;
          expect(options).toEqual({ enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
        },
      },
      onSuccess(point) {
        points.push(point);
      },
      onError(error) {
        errors.push(error);
      },
    });
    gate.start();
    gate.start();
    expect(calls).toBe(1);
    success!({ coords: { latitude: 0, longitude: 0 } as GeolocationCoordinates });
    failure!({ code: 1 });
    expect(points).toHaveLength(1);
    expect(errors).toEqual([]);
    gate.dispose();
    success!({ coords: { latitude: 1, longitude: 1 } as GeolocationCoordinates });
    expect(points).toHaveLength(1);
    const missing = createLocationGate({
      onError(error) {
        errors.push(error);
      },
    });
    missing.start();
    missing.start();
    expect(errors).toEqual(['unavailable']);
  });
});
