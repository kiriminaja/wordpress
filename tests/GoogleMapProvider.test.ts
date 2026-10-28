import { describe, expect, test } from 'bun:test';
import { Window } from 'happy-dom';
import { loadGoogleMaps } from '../src/buyer/map/google-loader';
import { createGoogleMapSession } from '../src/buyer/map/google';
import { createProviderMapSession, mapProviderRegistry } from '../src/buyer/map/providers';
import type {
  GoogleMapsAPI,
  GoogleMapsWindow,
  GoogleMapEvent,
  GoogleLatLngLiteral,
  GeolocationProvider,
  Point,
} from '../src/buyer/map/types';

function runtime() {
  const handlers = new Map<string, (event: GoogleMapEvent) => void>();
  let center: GoogleLatLngLiteral = { lat: 0, lng: 0 },
    cleanup = 0;
  let path: GoogleLatLngLiteral[] = [];
  const api: GoogleMapsAPI = {
    Map: class {
      constructor(_node: HTMLElement, options: { center: GoogleLatLngLiteral }) {
        center = options.center;
      }
      getCenter() {
        return { lat: () => center.lat, lng: () => center.lng };
      }
      setCenter(next: GoogleLatLngLiteral) {
        center = next;
        handlers.get('center_changed')?.({});
      }
      setZoom() {}
      addListener(name: string, callback: (event: GoogleMapEvent) => void) {
        handlers.set(name, callback);
        return {
          remove: () => {
            handlers.delete(name);
          },
        };
      }
    },
    Polyline: class {
      constructor(options: { path: GoogleLatLngLiteral[] }) {
        path = options.path;
      }
      setMap() {}
    },
    event: {
      clearInstanceListeners() {
        cleanup++;
      },
      trigger() {},
    },
  };
  return {
    api,
    handlers,
    center(next: GoogleLatLngLiteral) {
      center = next;
    },
    counts: () => ({ cleanup, path }),
    getCenter: () => center,
  };
}

describe('shared Google runtime provider', () => {
	test('granted GPS suggests the camera only; a manual pin wins over late device results', async () => {
		const f = runtime(), selections: unknown[] = [];
		let success: any;
		const session = createGoogleMapSession({ google: f.api, node: new Window().document.createElement('div') as unknown as HTMLElement,
			coverage: { origin: { latitude: -6.9, longitude: 107.6 }, radiusMeters: 40000 },
			permissions: { query: async () => ({ state: 'granted' }) },
			geolocation: { getCurrentPosition(ok) { success = ok; } }, onSelect: point => { selections.push(point); } });
		expect(f.getCenter()).toEqual({ lat: -6.9, lng: 107.6 });
		await Promise.resolve(); await Promise.resolve();
		success({ coords: { latitude: -6.2, longitude: 106.8 } });
		expect(f.getCenter()).toEqual({ lat: -6.2, lng: 106.8 });
		expect(session.getPoint()).toBeNull(); expect(selections).toEqual([]);
		session.dispose();
	});
  for (const geolocation of [undefined, { getCurrentPosition() { throw new Error('blocked'); } }, { getCurrentPosition(_success: any, failure: any) { failure({ code: 1 }); } }]) {
    test('optional location failure preserves the Jogja view and manual Google selection', () => {
      const f = runtime(), selections: any[] = [], errors: string[] = [];
      const session = createGoogleMapSession({ google: f.api, node: new Window().document.createElement('div') as unknown as HTMLElement, geolocation, onSelect: point => { selections.push(point); }, onError: code => errors.push(code) });
      expect(f.getCenter()).toEqual({ lat: -7.801389, lng: 110.364722 });
      f.handlers.get('idle')!({});
      expect(session.getPoint()).toBeNull();
      expect(selections).toEqual([]);
      expect(errors).toEqual([]);
      session.locate();
      expect(errors).toEqual(['permission']);
      expect(session.isAvailable()).toBe(true);
      expect(selections).toEqual([]);
      f.handlers.get('click')!({ latLng: { lat: () => 0, lng: () => 0 } });
      expect(session.getPoint()).toEqual({ latitude: '0.0000000', longitude: '0.0000000' });
      expect(selections).toHaveLength(1);
      session.dispose();
    });
  }

  test('deduplicates loader per window/key with only the HTTPS weekly API URL and cleans callback', async () => {
    const win = new Window({
      settings: {
        disableJavaScriptFileLoading: true,
        enableJavaScriptEvaluation: false,
        handleDisabledFileLoadingAsSuccess: true,
      },
    }) as unknown as GoogleMapsWindow;
    const f = runtime();
    const first = loadGoogleMaps('valid_key-123', win.document, win);
    expect(loadGoogleMaps('valid_key-123', win.document, win)).toBe(first);
    await Promise.resolve();
    const scripts = win.document.querySelectorAll('script');
    expect(scripts.length).toBe(1);
    const url = new URL(scripts[0]!.src);
    expect(url.origin + url.pathname).toBe('https://maps.googleapis.com/maps/api/js');
    expect(url.searchParams.get('v')).toBe('weekly');
    expect(url.searchParams.get('loading')).toBe('async');
    expect(url.searchParams.has('libraries')).toBe(false);
    win.google = { maps: f.api };
    const callback = url.searchParams.get('callback') as `__kiriminajaGoogleMaps${number}`;
    win[callback]!();
    expect(await first).toBe(f.api);
    expect(win[callback]).toBeUndefined();
    await expect(loadGoogleMaps('other_key-123', win.document, win)).rejects.toThrow(
      'Map unavailable',
    );
    const failWin = new Window({
      settings: {
        disableJavaScriptFileLoading: true,
        enableJavaScriptEvaluation: false,
        handleDisabledFileLoadingAsSuccess: true,
      },
    }) as unknown as GoogleMapsWindow;
    let chained = 0;
    failWin.gm_authFailure = () => {
      chained++;
    };
    const failed = loadGoogleMaps('valid_key-123', failWin.document, failWin);
    await Promise.resolve();
    failWin.gm_authFailure!();
    await expect(failed).rejects.toThrow('Map unavailable');
    expect(chained).toBe(1);
    expect(failWin.document.querySelectorAll('script').length).toBe(0);
  });

  test('preserves empty/saved selection, moves after 160ms, coverage and stale geolocation disposal', () => {
    const win = new Window({
      settings: {
        disableJavaScriptFileLoading: true,
        enableJavaScriptEvaluation: false,
        handleDisabledFileLoadingAsSuccess: true,
      },
    });
    const node = win.document.createElement('div') as unknown as HTMLElement;
    const f = runtime();
    const selections: Array<Point | null> = [],
      coverage: unknown[] = [];
    const timers = new Map<number, () => void>();
    let id = 0;
    let success: Parameters<GeolocationProvider['getCurrentPosition']>[0] | undefined;
    const session = createGoogleMapSession({
      google: f.api,
      node,
      coverage: { origin: { latitude: 0, longitude: 0 }, radiusMeters: 40000 },
      onSelect: (point) => {
        selections.push(point);
      },
      onCoverage: (result) => {
        coverage.push(result);
      },
      schedule(callback, delay) {
        expect([150, 160]).toContain(delay);
        timers.set(++id, callback);
        return id;
      },
      cancel(handle) {
        timers.delete(handle as number);
      },
      geolocation: {
        getCurrentPosition(yes) {
          success = yes;
        },
      },
    });
    expect(session.getPoint()).toBeNull();
    expect(selections).toEqual([]);
    expect(coverage).toEqual([null]);
    expect(f.counts().path.length).toBe(65);
    session.locate();
    session.pick(0, 0, true);
    success!({ coords: { latitude: 2, longitude: 3 } as GeolocationCoordinates });
    expect(session.getPoint()?.latitude).toBe('0.0000000');
    expect(coverage.at(-1)).toEqual({ distanceMeters: 0, inside: true });
    f.handlers.get('idle')!({});
    f.handlers.get('dragstart')!({});
    f.center({ lat: 1, lng: 2 });
    f.handlers.get('idle')!({});
    for (const callback of [...timers.values()]) callback();
    expect(session.getPoint()).toEqual({ latitude: '1.0000000', longitude: '2.0000000' });
    session.clear();
    session.locate();
    session.dispose();
    session.dispose();
    success!({ coords: { latitude: 2, longitude: 3 } as GeolocationCoordinates });
    expect(session.getPoint()).toBeNull();
    expect(session.pick(0, 0)).toBe(false);
    expect(f.handlers.size).toBe(0);
    expect(f.counts().cleanup).toBe(1);
    expect(timers.size).toBe(0);
    const saved = createGoogleMapSession({
      google: runtime().api,
      node,
      initial: { latitude: 3, longitude: 4 },
      onSelect: (point) => {
        selections.push(point);
      },
    });
    expect(saved.getPoint()?.latitude).toBe('3.0000000');
    saved.dispose();
  });

  test('registry is immutable; unknown providers and invalid Google keys fail before runtime creation', async () => {
    expect(Object.keys(mapProviderRegistry)).toEqual(['leaflet', 'google']);
    expect(Object.isFrozen(mapProviderRegistry)).toBe(true);
    for (const options of [{ provider: 'unknown' }, { provider: 'google', apiKey: 'short' }]) {
      await expect(createProviderMapSession(options)).rejects.toThrow();
    }
  });
});
