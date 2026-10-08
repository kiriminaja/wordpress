import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import type { GoogleMapsBootstrap, TechnicalBootstrap } from '../../../src/lib/settings/types';

const root = fileURLToPath(new URL('../../../', import.meta.url));
// Deliberately synthetic: all external requests are intercepted, never billed.
const savedKey = 'AIza-synthetic_saved_browser_key456';
const replacement = 'AIza-synthetic_replacement_browser_key123';
const runtimeKey = 'AIza-synthetic_runtime_browser_key789';
const mask = (key: string) => key.slice(0, 4) + '*'.repeat(key.length - 7) + key.slice(-3);
const json = (value: unknown) => JSON.stringify(value).replace(/</g, '\\u003c');
const googleMaps: GoogleMapsBootstrap = { configured: true, maskedKey: mask(savedKey), nonce: 'dedicated-google-nonce', i18n: {
  title: 'Google Maps integration', guidance: 'Bring your own browser key', configured: 'Configured', notConfigured: 'Not configured', keyLabel: 'Replacement browser key', placeholder: 'Enter replacement key', save: 'Save key', remove: 'Remove key', busy: 'Saving', saved: 'Settings saved', failed: 'Settings could not be saved',
} };
const technical: TechnicalBootstrap = { view: 'technical', toolbar: { logoUrl: '', rootUrl: '/wp-admin/admin.php?page=kiriminaja-setting', rootLabel: 'Settings', title: 'Technical settings' }, downloadLogUrl: '', callbacks: [], googleMaps,
  region: { state: 'ready', lastError: '', provinceCount: 1, cityCount: 1, updated: '', validUntil: '' }, couriers: { cached: true, count: 1, updated: '', validUntil: '' },
  i18n: { regionTitle: 'Regions', regionDescription: 'Cached regions', courierTitle: 'Couriers', courierDescription: 'Cached couriers', logsTitle: 'Logs', callbacksTitle: 'Callbacks', callbacksDescription: 'Registered callbacks', noCallbacks: 'None', logsDescription: 'Download logs', logsPrivacy: 'Private', status: 'Status', provinces: 'Provinces', cities: 'Cities', couriers: 'Couriers', lastUpdated: 'Updated', validUntil: 'Valid until', refreshRegion: 'Refresh regions', scheduling: 'Scheduling', refreshing: 'Refreshing', cacheUpdated: 'Updated', refreshFailed: 'Failed', flushCouriers: 'Refresh couriers', flushing: 'Refreshing', cacheRefreshed: 'Updated', flushFailed: 'Failed', cached: 'Cached', notCached: 'Not cached', downloadLog: 'Download' },
};
function settingsHtml() {
  return `<!doctype html><html><head><meta charset="utf-8">${['kiriminaja-kiriof-var.css', 'kiriminaja-kiriof-component.css', 'kiriminaja-admin-workspace.css'].map(name => `<link rel="stylesheet" href="/assets/admin/dist/${name}">`).join('')}</head><body><div data-kiriof-settings-page><div data-kiriof-settings-root></div><script type="application/json" data-kiriof-settings-payload>${json(technical)}</script></div><script>window.ajaxurl='/wp-admin/admin-ajax.php';window.kiriofSettings={nonce:'general-nonce-not-google'};</script><script type="module" src="/assets/admin/dist/kiriminaja-admin-workspace.js"></script></body></html>`;
}

test('actual admin workspace saves and removes a BYOK credential using masked summaries only', async ({ app, browser }) => {
  const unexpected: string[] = [], bodies: Record<string, string>[] = [], responses: string[] = [];
  const html = settingsHtml();
  expect(html).not.toContain(savedKey); expect(html).not.toContain(replacement);
  // Explicit mocked WordPress AJAX boundary. PHP authorization/persistence is
  // independently tested by GoogleMapsSettingsRuntimeTest.php, not claimed here.
  await browser.route('**/*', (route: any) => {
    const url = new URL(route.request.url);
    if (url.origin === 'https://fixture.test' && url.pathname === '/wp-admin/admin-ajax.php' && route.request.method === 'POST') {
      const body = Object.fromEntries(new URLSearchParams(route.request.postData || '')); bodies.push(body);
      const expected = body['data[mode]'] === 'save'
        ? { action: 'kiriof_save_google_maps_settings', 'data[nonce]': googleMaps.nonce, 'data[mode]': 'save', 'data[key]': replacement }
        : { action: 'kiriof_save_google_maps_settings', 'data[nonce]': googleMaps.nonce, 'data[mode]': 'remove' };
      expect(body).toEqual(expected);
      const summary = body['data[mode]'] === 'save' ? { configured: true, maskedKey: mask(replacement) } : { configured: false, maskedKey: '' };
      const response = json({ success: true, data: { status: 200, data: summary } }); responses.push(response);
      return route.fulfill({ contentType: 'application/json', body: response });
    }
    if (url.origin === 'https://fixture.test' && route.request.method === 'GET') {
      if (url.pathname === '/wp-admin/admin.php') return route.fulfill({ contentType: 'text/html', body: html });
      if (url.pathname.startsWith('/assets/admin/dist/')) return route.fulfill({ contentType: url.pathname.endsWith('.css') ? 'text/css' : 'text/javascript', body: readFileSync(root + url.pathname.slice(1), 'utf8') });
    }
    if (url.pathname !== '/favicon.ico') unexpected.push(url.href);
    return route.fulfill({ status: 409, body: 'No live network permitted' });
  });
  await app.open('/wp-admin/admin.php?page=kiriminaja-setting&section=technical');
  const card = browser.locator('section[aria-labelledby="kiriof-google-maps-title"]');
  await expect(browser.locator('#kiriof-google-maps-title')).toHaveText(googleMaps.i18n.title);
  await expect(browser.locator('section[aria-labelledby="kiriof-google-maps-title"] code')).toHaveText(mask(savedKey));
  const input = browser.locator('#kiriof-google-maps-key');
  expect(await browser.evaluate(() => { const input = document.querySelector<HTMLInputElement>('#kiriof-google-maps-key')!; return { type: input.type, value: input.value, attribute: input.getAttribute('value') }; })).toEqual({ type: 'password', value: '', attribute: null });
  await input.fill(replacement); await browser.locator('section[aria-labelledby="kiriof-google-maps-title"] button[type="submit"]').click();
  await expect(browser.locator('section[aria-labelledby="kiriof-google-maps-title"] [role="status"]')).toHaveText(googleMaps.i18n.saved);
  await expect(browser.locator('section[aria-labelledby="kiriof-google-maps-title"] code')).toHaveText(mask(replacement));
  expect(await browser.evaluate(() => document.querySelector<HTMLInputElement>('#kiriof-google-maps-key')!.value)).toBe('');
  expect(await browser.evaluate(() => document.body.innerHTML)).not.toContain(replacement);
  await browser.locator('section[aria-labelledby="kiriof-google-maps-title"] button[type="button"]').click();
  await expect(card).toContainText(googleMaps.i18n.notConfigured);
  expect(bodies).toHaveLength(2); expect(responses.join('')).not.toContain(replacement);
  expect(await browser.evaluate(() => ({ value: document.querySelector<HTMLInputElement>('#kiriof-google-maps-key')!.value, scripts: document.querySelectorAll('script[data-kiriminaja-google-maps]').length }))).toEqual({ value: '', scripts: 0 });
  expect(unexpected).toEqual([]);
});

function originHtml() {
  // Native Woo form contract stays authoritative; map bridge only changes coords.
  return `<!doctype html><html><head><meta charset="utf-8"><style>.fixture-google-map{width:500px;height:260px}.fixture-map-pin{pointer-events:none}</style></head><body><form id="native-origin-form"><input name="_wpnonce" value="native-woo-nonce"><input name="save" value="Save changes"><input name="kiriof_wc_origin_latitude" id="kiriof_wc_origin_latitude" value="0"><input name="kiriof_wc_origin_longitude" id="kiriof_wc_origin_longitude" value="0"><div id="kiriof-wc-origin-map" class="fixture-google-map"></div><span class="fixture-map-pin" aria-hidden="true">📍</span><p id="kiriof-wc-map-error" hidden></p><div class="kiriof-wc-location-card__body"><input name="locations[7][latitude]" class="kiriof-wc-location-latitude" value="-6.2"><input name="locations[7][longitude]" class="kiriof-wc-location-longitude" value="106.8"><div class="kiriof-wc-origin-map fixture-google-map"></div></div></form><script>window.__runtimeErrors=[];window.addEventListener('error',e=>window.__runtimeErrors.push(e.message));window.addEventListener('unhandledrejection',e=>window.__runtimeErrors.push(String(e.reason)));window.__nativeSubmissions=0;document.querySelector('form').addEventListener('submit',e=>{e.preventDefault();window.__nativeSubmissions++});window.kiriofAdminMapsConfig=${json({ provider: 'google', apiKey: runtimeKey })};window.kiriofSettings={i18n:{mapUnavailable:'Map unavailable'}};</script><script src="/assets/buyer/dist/kiriminaja-buyer-admin-maps.js"></script></body></html>`;
}
// Google SDK boundary only: production loader, registry, adapter and origin bridge
// are real. A DOM button invokes the SDK click callback like an actual map click.
// Scope the fake SDK: a top-level `class Map` shadows the browser's native Map
// for both the production bundle and Playwright's injected selector evaluator.
function mockGoogle(callback: string) {
  return `(() => { window.__googleMaps=[];class FixtureGoogleMap { constructor(node,options){this.center=options.center;this.listeners={};window.__googleMaps.push(this);const button=document.createElement('button');button.type='button';button.textContent='Select fixture map point';button.onclick=()=>this.fire('click',{latLng:{lat:()=>-6.21,lng:()=>106.81}});node.append(button);setTimeout(()=>this.fire('idle'),0)}addListener(name,cb){(this.listeners[name]??=[]).push(cb);return{remove:()=>{this.listeners[name]=this.listeners[name].filter(x=>x!==cb)}}}fire(name,event){for(const cb of this.listeners[name]||[])cb(event)}setCenter(center){this.center=center;this.fire('center_changed');setTimeout(()=>this.fire('idle'),0)}getCenter(){return{lat:()=>this.center.lat,lng:()=>this.center.lng}}setZoom(){}}class Overlay{setMap(){}}window.google={maps:{Map:FixtureGoogleMap,Polyline:Overlay,Circle:Overlay,event:{trigger:(map,name)=>map.fire(name),clearInstanceListeners:map=>map.listeners={}}}};window[${json(callback)}](); })();`;
}

for (const failure of [false, true]) {
  test(failure ? 'Google load failure fails closed without fetching Leaflet or changing native coords' : 'actual origin bridge shares one Google runtime and preserves native form saving', async ({ app, browser }) => {
    const unexpected: string[] = [], googleRequests: string[] = [];
    const html = originHtml(); expect(html).not.toContain('leaflet');
    await browser.route('**/*', (route: any) => {
      const url = new URL(route.request.url);
      if (url.origin === 'https://maps.googleapis.com' && url.pathname === '/maps/api/js') {
        googleRequests.push(url.href); expect(url.searchParams.get('key')).toBe(runtimeKey);
        if (failure) return route.fulfill({ contentType: 'text/javascript', body: 'window.gm_authFailure();' });
        return route.fulfill({ contentType: 'text/javascript', body: mockGoogle(url.searchParams.get('callback')!) });
      }
      if (url.origin === 'https://fixture.test' && route.request.method === 'GET') {
        if (url.pathname === '/origin') return route.fulfill({ contentType: 'text/html', body: html });
        if (url.pathname === '/assets/buyer/dist/kiriminaja-buyer-admin-maps.js') return route.fulfill({ contentType: 'text/javascript', body: readFileSync(root + url.pathname.slice(1), 'utf8') });
      }
      if (url.pathname !== '/favicon.ico') unexpected.push(url.href);
      return route.fulfill({ status: 409, body: 'No live maps, tiles, payments or API requests' });
    });
    await app.open('/origin');
    if (failure) {
      await expect(browser.locator('#kiriof-wc-map-error')).toHaveText('Map unavailable');
      expect(await browser.evaluate(() => document.querySelector<HTMLInputElement>('#kiriof_wc_origin_latitude')!.value)).toBe('0');
      await expect(browser.locator('.kiriof-wc-location-card__body [role="status"]')).toHaveText('Map unavailable');
      expect(await browser.evaluate(() => [...document.querySelectorAll('#kiriof-wc-map-error, .kiriof-wc-location-card__body [role="status"]')].map(node => node.textContent))).toEqual(['Map unavailable', 'Map unavailable']);
      expect(await browser.evaluate(() => ({ lng: document.querySelector<HTMLInputElement>('#kiriof_wc_origin_longitude')!.value, secondaryLat: document.querySelector<HTMLInputElement>('.kiriof-wc-location-latitude')!.value, secondaryLng: document.querySelector<HTMLInputElement>('.kiriof-wc-location-longitude')!.value, submissions: (window as any).__nativeSubmissions }))).toEqual({ lng: '0', secondaryLat: '-6.2', secondaryLng: '106.8', submissions: 0 });
    } else {
      await expect(browser.locator('#kiriof-wc-origin-map button')).toBeVisible();
      await expect(browser.locator('.kiriof-wc-origin-map button')).toBeVisible();
      await browser.locator('#kiriof-wc-origin-map button').click();
      expect(await browser.evaluate(() => ({ lat: document.querySelector<HTMLInputElement>('#kiriof_wc_origin_latitude')!.value, lng: document.querySelector<HTMLInputElement>('#kiriof_wc_origin_longitude')!.value, submissions: (window as any).__nativeSubmissions, nonce: String((new FormData(document.querySelector('form')!)).get('_wpnonce')), save: String((new FormData(document.querySelector('form')!)).get('save')), secondary: document.querySelector<HTMLInputElement>('.kiriof-wc-location-latitude')!.value }))).toEqual({ lat: '-6.2100000', lng: '106.8100000', submissions: 0, nonce: 'native-woo-nonce', save: 'Save changes', secondary: '-6.2' });
      expect(await browser.evaluate(() => document.querySelectorAll('script[data-kiriminaja-google-maps]').length)).toBe(1);
      await browser.locator('.kiriof-wc-location-card__body .kiriof-wc-origin-map button').click();
      expect(await browser.evaluate(() => ({
        lat: document.querySelector<HTMLInputElement>('#kiriof_wc_origin_latitude')!.value,
        lng: document.querySelector<HTMLInputElement>('#kiriof_wc_origin_longitude')!.value,
        secondaryLat: document.querySelector<HTMLInputElement>('.kiriof-wc-location-latitude')!.value,
        secondaryLng: document.querySelector<HTMLInputElement>('.kiriof-wc-location-longitude')!.value,
        submissions: (window as any).__nativeSubmissions,
        maps: (window as any).__googleMaps.length,
      }))).toEqual({ lat: '-6.2100000', lng: '106.8100000', secondaryLat: '-6.2100000', secondaryLng: '106.8100000', submissions: 0, maps: 2 });
    }
    expect(googleRequests).toHaveLength(1);
    expect(await browser.evaluate(() => ({ leaflet: typeof (window as any).L, references: [...document.querySelectorAll('script[src],link[href]')].map(node => node.getAttribute('src') || node.getAttribute('href')).filter(value => /leaflet|openstreetmap/i.test(value || '')) }))).toEqual({ leaflet: 'undefined', references: [] });
    expect(unexpected).toEqual([]);
    expect(await browser.evaluate(() => (window as any).__runtimeErrors)).toEqual([]);
  });
}
