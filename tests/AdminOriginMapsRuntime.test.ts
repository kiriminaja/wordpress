import { expect, test } from 'bun:test';
import { happy } from './helpers/ui-runtime';
import { createAdminOriginMaps, type AdminMapsWindow } from '../src/buyer/admin/origin-maps';

function fixture(preloaded = true, provider = 'google') {
  const window = new happy.Window({ url: 'https://fixture.test', settings: { disableJavaScriptFileLoading: true, enableJavaScriptEvaluation: false, handleDisabledFileLoadingAsSuccess: true } });
  const win = window as unknown as AdminMapsWindow;
  window.document.body.innerHTML = `<form id="mainform"><input id="kiriof_wc_origin_latitude" value="0"><input id="kiriof_wc_origin_longitude" value="0"><div style="position:relative"><div id="kiriof-wc-origin-map"></div><div class="existing-pin"></div><button type="button" id="kiriof-wc-use-my-location">Locate</button></div><span id="kiriof-wc-map-coords"></span><p id="kiriof-wc-map-error" style="display:none"></p><div class="kiriof-wc-location-card__body"><div class="kiriof-wc-origin-map" data-lat="" data-lng=""></div><input class="kiriof-wc-location-latitude" name="kiriof_locations[one][latitude]" value=""><input class="kiriof-wc-location-longitude" name="kiriof_locations[one][longitude]" value=""><button type="button" class="kiriof-wc-origin-my-location">Locate</button></div><div class="kiriof-wc-origin-map unrelated"></div></form>`;
  const maps: MockMap[] = [];
  let success: PositionCallback | undefined, failure: PositionErrorCallback | undefined;
  let submissions = 0, changes = 0;
  window.document.querySelector('form')!.addEventListener('submit', () => submissions++);
  window.document.querySelector('form')!.addEventListener('change', () => changes++);
  class MockMap {
    center: { lat: number; lng: number };
    events: Record<string, (event: any) => void> = {};
    removed = false;
    constructor(public node: HTMLElement, options: any) { this.center = options.center; maps.push(this); }
    getCenter() { return { lat: () => this.center.lat, lng: () => this.center.lng }; }
    setCenter(point: { lat: number; lng: number }) { this.center = point; }
    setZoom() {}
    addListener(name: string, callback: (event: any) => void) { this.events[name] = callback; return { remove: () => { delete this.events[name]; } }; }
  }
  const google = { maps: { Map: MockMap, event: { trigger() {}, clearInstanceListeners(map: any) { map.removed = true; } } } };
  if (preloaded) win.google = google;
  win.kiriofAdminMapsConfig = { provider, apiKey: 'fixture-google-key' };
  win.kiriofSettings = { i18n: { permissionDenied: 'Localized denial', mapUnavailable: 'Localized unavailable' } };
  Object.defineProperty(window.navigator, 'geolocation', { configurable: true, value: { getCurrentPosition(ok: PositionCallback, fail: PositionErrorCallback) { success = ok; failure = fail; } } });
  const bridge = createAdminOriginMaps(win);
  const settle = async () => { for (let i = 0; i < 6; i++) await Promise.resolve(); };
  const click = (selector: string) => window.document.querySelector<HTMLButtonElement>(selector)!.click();
  return { window, win, maps, bridge, settle, click, changes: () => changes, submissions: () => submissions,
    success: () => success!({ coords: { latitude: -6.3, longitude: 106.9 } } as GeolocationPosition),
    failure: () => failure!({ code: 1 } as GeolocationPositionError),
    load() {
      const script = window.document.querySelector<HTMLScriptElement>('script[data-kiriminaja-google-maps]')!;
      win.google = google;
      const callback = new URL(script.src).searchParams.get('callback')!;
      (win as any)[callback]();
    },
    cleanup() { bridge.dispose(); window.happyDOM.abort(); },
  };
}

test('Google origin maps preserve zero/saved controls, do not publish a default pin, and update native controls without submitting', async () => {
  const h = fixture();
  try {
    h.bridge.initialize(); h.bridge.initialize(); await h.settle();
    expect(h.maps).toHaveLength(2);
    expect(h.maps[0].center).toEqual({ lat: 0, lng: 0 });
    expect(h.window.document.querySelector<HTMLInputElement>('#kiriof_wc_origin_latitude')!.value).toBe('0');
    expect(h.window.document.querySelector<HTMLInputElement>('.kiriof-wc-location-latitude')!.value).toBe('');
    expect(h.window.document.querySelector<HTMLElement>('.kiriof-wc-location-card__body [aria-hidden]')!.hidden).toBe(true);
    expect(h.changes()).toBe(0);
    h.maps[1].events.click({ latLng: { lat: () => 0, lng: () => 120.12345678 } });
    expect(h.window.document.querySelector<HTMLInputElement>('.kiriof-wc-location-latitude')!.value).toBe('0.0000000');
    expect(h.window.document.querySelector<HTMLInputElement>('.kiriof-wc-location-longitude')!.value).toBe('120.1234568');
    expect(h.changes()).toBe(2);
    expect(h.submissions()).toBe(0);
    h.maps[0].events.idle({});
    h.maps[0].events.dragstart({}); h.maps[0].center = { lat: -6.4, lng: 106.7 }; h.maps[0].events.idle({});
    await new Promise(resolve => setTimeout(resolve, 180));
    expect(h.window.document.querySelector<HTMLInputElement>('#kiriof_wc_origin_latitude')!.value).toBe('-6.4000000');
    expect(h.submissions()).toBe(0);
  } finally { h.cleanup(); }
});

test('location buttons recover from localized errors and ignore callbacks after pagehide', async () => {
  const h = fixture();
  try {
    h.bridge.initialize(); await h.settle();
    const button = h.window.document.querySelector<HTMLButtonElement>('#kiriof-wc-use-my-location')!;
    h.click('#kiriof-wc-use-my-location'); expect(button.disabled).toBe(true);
    h.failure(); await h.settle(); expect(button.disabled).toBe(false);
    expect(h.window.document.querySelector('#kiriof-wc-map-error')!.textContent).toBe('Localized denial');
    h.click('#kiriof-wc-use-my-location');
    h.window.dispatchEvent(new h.window.Event('pagehide')); h.success(); await h.settle();
    expect(button.disabled).toBe(false);
    expect(h.maps.every(map => map.removed)).toBe(true);
    expect(h.window.document.querySelector<HTMLInputElement>('#kiriof_wc_origin_latitude')!.value).toBe('0');
  } finally { h.cleanup(); }
});

test('a pending Google loader cannot construct maps after disposal and Leaflet config remains untouched', async () => {
  const pending = fixture(false);
  try {
    pending.bridge.initialize(); await pending.settle();
    expect(pending.maps).toHaveLength(0);
    pending.bridge.dispose(); pending.load(); await pending.settle();
    expect(pending.maps).toHaveLength(0);
  } finally { pending.cleanup(); }
  const leaflet = fixture(false, 'leaflet');
  try {
    leaflet.bridge.initialize(); await leaflet.settle();
    expect(leaflet.window.document.querySelector('script')).toBeNull();
    expect(leaflet.maps).toHaveLength(0);
  } finally { leaflet.cleanup(); }
});
