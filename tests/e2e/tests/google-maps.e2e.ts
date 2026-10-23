import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import type { GoogleMapsBootstrap, TechnicalBootstrap } from '../../../src/lib/settings/types';

const root = fileURLToPath(new URL('../../../', import.meta.url));
// Deliberately synthetic: all external requests are intercepted, never billed.
const savedKey = 'AIza-synthetic_saved_browser_key456';
const replacement = 'AIza-synthetic_replacement_browser_key123';
const runtimeKey = 'AIza-synthetic_runtime_browser_key789';
const mask = (key: string) => key.slice(0, 4) + '*'.repeat(key.length - 7) + key.slice(-3);
const json = (value: unknown) => JSON.stringify(value).replace(/</g, '\\u003c');
const googleMaps: GoogleMapsBootstrap = { configured: true, maskedKey: mask(savedKey), nonce: 'dedicated', browserCheckUrl: 'https://fixture.test/?kiriof_google_maps_browser_check=1&_wpnonce=dedicated', i18n: {
  title: 'Google Maps', guidance: 'Enable the Maps JavaScript API and billing in Google Cloud. Restrict this browser key to your website HTTP referrers and the Maps JavaScript API. Browser keys are public when maps load.', configured: 'Saved browser key', notConfigured: 'No browser key saved. Maps use OpenStreetMap.', keyLabel: 'Browser API key', placeholder: 'Leave blank to keep the saved key', save: 'Save key', remove: 'Remove key', busy: 'Updating…', saved: 'Google Maps settings updated.', failed: 'Unable to update Google Maps settings.',
  keyHint: 'Browser key is public when a map loads.', showKey: 'Show key', hideKey: 'Hide key', browserCheckTitle: 'Browser origin check', browserCheckGuidance: 'Tests the configured WordPress website origin, including WP_HOME from wp-config.php. The preview requires the same WordPress login session; separate-site cookies may block it. Checkout path restrictions may differ. Requests may count toward Google Maps usage.', originLabel: 'Tested origin', checking: 'Checking Google Maps in this browser…', checkAgain: 'Recheck', browserReady: 'Map loaded successfully.', browserFailed: 'Google Maps could not be verified. Review allowed HTTP referrers, API restrictions, billing, and quota in Google Cloud.', browserNetwork: 'Google Maps could not load. Check your network, content security policy, and browser blockers.', browserTimeout: 'The preview did not finish loading. Check the configured website URL, WordPress login session, iframe and cookie restrictions, network, and Google Cloud settings, then try again.', browserNotConfigured: 'Save a browser key to run this check.', previewTitle: 'Google Maps browser-origin preview', details: 'Details',
} };
const technical: TechnicalBootstrap = { view: 'technical', toolbar: { logoUrl: '', rootUrl: '/wp-admin/admin.php?page=kiriminaja-setting', rootLabel: 'Settings', title: 'Technical settings' }, downloadLogUrl: '', callbacks: [], googleMaps,
  region: { state: 'ready', lastError: '', provinceCount: 1, cityCount: 1, updated: '', validUntil: '' }, couriers: { cached: true, count: 1, updated: '', validUntil: '' },
  i18n: { regionTitle: 'Regions', regionDescription: 'Cached regions', courierTitle: 'Couriers', courierDescription: 'Cached couriers', logsTitle: 'Logs', callbacksTitle: 'Callbacks', callbacksDescription: 'Registered callbacks', noCallbacks: 'None', logsDescription: 'Download logs', logsPrivacy: 'Private', status: 'Status', provinces: 'Provinces', cities: 'Cities', couriers: 'Couriers', lastUpdated: 'Updated', validUntil: 'Valid until', refreshRegion: 'Refresh regions', scheduling: 'Scheduling', refreshing: 'Refreshing', cacheUpdated: 'Updated', refreshFailed: 'Failed', flushCouriers: 'Refresh couriers', flushing: 'Refreshing', cacheRefreshed: 'Updated', flushFailed: 'Failed', cached: 'Cached', notCached: 'Not cached', downloadLog: 'Download' },
};
function settingsHtml(config = googleMaps) {
  return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;background:#f0f0f1;color:#1d2327;font:13px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}button,input{font:inherit}h1,h2,h3,p{margin-top:0}button,input,fieldset{box-sizing:border-box}</style>${['kiriminaja-kiriof-var.css', 'kiriminaja-kiriof-component.css', 'kiriminaja-admin-workspace.css'].map(name => `<link rel="stylesheet" href="/assets/admin/dist/${name}">`).join('')}</head><body class="wp-admin kiriof-admin"><div class="wrap kj-wrap kiriof-workspace-shell kiriof-settings-shell" data-kiriof-settings-page><div data-kiriof-settings-root></div><script type="application/json" data-kiriof-settings-payload>${json({ ...technical, googleMaps: config })}</script></div><script>window.ajaxurl='/wp-admin/admin-ajax.php';window.kiriofSettings={nonce:'general-nonce-not-google'};</script><script type="module" src="/assets/admin/dist/kiriminaja-admin-workspace.js"></script></body></html>`;
}
const cardSelector = 'section[aria-labelledby="kiriof-google-maps-title"]';
const checkSelector = '[data-kiriof-google-maps-check]';
type ProbeMode = 'tiles' | 'auth' | 'callback-only' | 'network';
// Only the Google SDK is a double. PHP controller/template and isolated probe JS
// are production code. The subprocess harness supplies WP doubles, not real auth.
function mockProbeSdk(callback: string, mode: ProbeMode) {
  if (mode === 'auth') return 'window.gm_authFailure();';
  return `(()=>{class FixtureMap{constructor(node){this.listeners={};window.__probeMap=this;node.innerHTML='<div role="img" aria-label="Synthetic Google map tiles" style="height:260px;background:#dce8d6">Synthetic map tiles</div>';${mode === 'tiles' ? "const paint=()=>{let frame=window.frameElement,visible=frame?frame.getBoundingClientRect().height>0:window.innerWidth>0&&window.innerHeight>0;for(let ancestor=frame?.parentElement;ancestor;ancestor=ancestor.parentElement){const style=getComputedStyle(ancestor);if(ancestor.hidden||ancestor.inert||style.display==='none'||style.visibility==='hidden'||(style.overflowY==='hidden'&&ancestor.getBoundingClientRect().height===0))visible=false;}if(visible)this.fire('tilesloaded');else setTimeout(paint,50);};setTimeout(paint,0);" : ''}}addListener(name,cb){(this.listeners[name]??=[]).push(cb);return{remove:()=>this.listeners[name]=this.listeners[name].filter(x=>x!==cb)}}fire(name){for(const cb of [...(this.listeners[name]||[])])cb()}}window.google={maps:{Map:FixtureMap,event:{clearInstanceListeners:map=>map.listeners={}}}};window[${json(callback)}]();})();`;
}
async function settingsFixture(browser: any, options: { configured?: boolean; mode?: ProbeMode; homeUrl?: string; redirect?: boolean } = {}) {
  let key = options.configured === false ? '' : savedKey;
  const homeUrl = options.homeUrl || 'https://fixture.test/';
  const probeUrl = new URL(homeUrl);
  probeUrl.searchParams.set('kiriof_google_maps_browser_check', '1');
  probeUrl.searchParams.set('_wpnonce', googleMaps.nonce);
  const config = { ...googleMaps, browserCheckUrl: probeUrl.href, configured: !!key, maskedKey: key ? mask(key) : '' };
  const html = settingsHtml(config);
  const unexpected: string[] = [], bodies: Record<string, string>[] = [], responses: string[] = [], frames: string[] = [], googleRequests: string[] = [], referrers: string[] = [];
  expect(html).not.toContain(savedKey); expect(html).not.toContain(replacement);
  await browser.route('**/*', (route: any) => {
    const url = new URL(route.request.url);
    if (url.origin === 'https://maps.googleapis.com' && url.pathname === '/maps/api/js') {
      googleRequests.push(url.href); expect(url.searchParams.get('key')).toBe(key);
      referrers.push(route.request.headers['referer'] || '');
      if (options.mode === 'network') return route.fulfill({ status: 503, body: 'Synthetic SDK outage' });
      return route.fulfill({ contentType: 'text/javascript', body: mockProbeSdk(url.searchParams.get('callback')!, options.mode || 'tiles') });
    }
    if (url.origin === 'https://fixture.test' && url.pathname === '/wp-admin/admin-ajax.php' && route.request.method === 'POST') {
      const body = Object.fromEntries(new URLSearchParams(route.request.postData || '')); bodies.push(body);
      expect(body).toEqual(body['data[mode]'] === 'save'
        ? { action: 'kiriof_save_google_maps_settings', 'data[nonce]': googleMaps.nonce, 'data[mode]': 'save', 'data[key]': replacement }
        : { action: 'kiriof_save_google_maps_settings', 'data[nonce]': googleMaps.nonce, 'data[mode]': 'remove' });
      key = body['data[mode]'] === 'save' ? replacement : '';
      const response = json({ success: true, data: { status: 200, data: { configured: !!key, maskedKey: key ? mask(key) : '' } } }); responses.push(response);
      return route.fulfill({ contentType: 'application/json', body: response });
    }
    if (route.request.method === 'GET' && url.searchParams.get('kiriof_google_maps_browser_check') === '1' && (url.origin === probeUrl.origin || url.origin === 'https://fixture.test')) {
      if (options.redirect && url.origin === probeUrl.origin) return route.fulfill({ status: 302, headers: { location: 'https://fixture.test/?' + url.searchParams.toString() } });
      frames.push(url.href);
      expect(url.searchParams.get('_wpnonce')).toBe(googleMaps.nonce);
      expect(url.searchParams.get('channel')).toMatch(/^[A-Za-z0-9_-]{32}$/);
      expect(url.href).not.toContain(key);
      const rendered = JSON.parse(execFileSync('php', [root + 'tests/fixtures/google-maps-browser-check-runtime.php', JSON.stringify({ key, dispatch: true, home_url: homeUrl, admin_url: 'https://fixture.test/wp-admin/', query: Object.fromEntries(url.searchParams) })], { encoding: 'utf8' }));
      expect(rendered.status).toBe(200);
      // Preserve production response headers relevant to iframe/referrer behavior.
      const headers = Object.fromEntries(rendered.headers.map((header: string) => { const separator = header.indexOf(':'); return [header.slice(0, separator).toLowerCase(), header.slice(separator + 1).trim()]; }));
      return route.fulfill({ contentType: 'text/html', headers, body: rendered.html });
    }
    if (url.origin === 'https://fixture.test' && route.request.method === 'GET') {
      if (url.pathname === '/wp-admin/admin.php') return route.fulfill({ contentType: 'text/html', body: html });
      if (url.pathname.startsWith('/assets/admin/dist/')) return route.fulfill({ contentType: url.pathname.endsWith('.css') ? 'text/css' : 'text/javascript', body: readFileSync(root + url.pathname.slice(1), 'utf8') });
    }
    // Fixture KIRIOF_URL deliberately differs from admin_url; no remote fetch.
    if (url.origin === 'https://shop.example' && url.pathname === '/wp-content/plugins/kiriminaja/assets/admin/js/google-maps-browser-check.js') return route.fulfill({ contentType: 'text/javascript', body: readFileSync(root + 'assets/admin/js/google-maps-browser-check.js', 'utf8') });
    if (url.pathname !== '/favicon.ico') unexpected.push(url.href);
    return route.fulfill({ status: 409, body: 'No live network permitted' });
  });
  return { unexpected, bodies, responses, frames, googleRequests, referrers };
}

test('configured WordPress storefront origin supplies the Google referrer even when admin has another host', async ({ app, browser }) => {
  const fixture = await settingsFixture(browser, { homeUrl: 'https://store.test:9443/shop/' });
  await app.open('/wp-admin/admin.php?page=kiriminaja-setting&section=technical');
  await expect(browser.locator(checkSelector)).toHaveText(googleMaps.i18n.browserReady);
  expect(fixture.frames).toHaveLength(1);
  expect(new URL(fixture.frames[0]).origin).toBe('https://store.test:9443');
  expect(new URL(fixture.frames[0]).pathname).toBe('/shop/');
  expect(fixture.referrers).toEqual(['https://store.test:9443/']);
  expect(fixture.unexpected).toEqual([]);
});

test('redirects away from the configured storefront fail without sending the key to Google', async ({ app, browser }) => {
  const fixture = await settingsFixture(browser, { homeUrl: 'https://store.test/shop/', redirect: true });
  await app.open('/wp-admin/admin.php?page=kiriminaja-setting&section=technical');
  await browser.evaluate(async () => {
    for (let i = 0; i < 220; i++) {
      if (document.querySelector('[data-kiriof-google-maps-check]')?.getAttribute('aria-busy') === 'false') return true;
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    throw new Error('Redirected storefront probe never reached its deadline');
  });
  await expect(browser.locator(checkSelector)).toHaveText(googleMaps.i18n.browserTimeout);
  expect(fixture.googleRequests).toEqual([]);
  expect(fixture.unexpected).toEqual([]);
});

test('actual admin workspace saves and removes a BYOK credential using masked summaries only', async ({ app, browser, screen }) => {
  const fixture = await settingsFixture(browser);
  await app.open('/wp-admin/admin.php?page=kiriminaja-setting&section=technical');
  const card = browser.locator(cardSelector);
  await expect(browser.locator('#kiriof-google-maps-title')).toHaveText(googleMaps.i18n.title);
  await expect(browser.locator(`${cardSelector} code`)).toHaveText(mask(savedKey));
  const input = browser.locator('#kiriof-google-maps-key');
  expect(await browser.evaluate(() => { const input = document.querySelector<HTMLInputElement>('#kiriof-google-maps-key')!; return { type: input.type, value: input.value, attribute: input.getAttribute('value') }; })).toEqual({ type: 'password', value: '', attribute: null });
  await expect(browser.locator(checkSelector)).toHaveText(googleMaps.i18n.browserReady);
  await input.fill(replacement); await screen.getByRole('button', { name: 'Save key', exact: true }).click();
  await expect(browser.locator('#kiriof-google-maps-save-message')).toHaveText(googleMaps.i18n.saved);
  await expect(browser.locator(`${cardSelector} code`)).toHaveText(mask(replacement));
  await expect(browser.locator(checkSelector)).toHaveText(googleMaps.i18n.browserReady);
  expect(await browser.evaluate(() => document.querySelector<HTMLInputElement>('#kiriof-google-maps-key')!.value)).toBe('');
  expect(await browser.evaluate(() => document.body.innerHTML)).not.toContain(replacement);
  expect(fixture.googleRequests.map(url => new URL(url).searchParams.get('key'))).toEqual([savedKey, replacement]);
  await screen.getByRole('button', { name: 'Remove key', exact: true }).click();
  await expect(card).toContainText(googleMaps.i18n.notConfigured);
  await expect(browser.locator(checkSelector)).toHaveText(googleMaps.i18n.browserNotConfigured);
  expect(fixture.bodies).toHaveLength(2); expect(fixture.responses.join('')).not.toContain(replacement);
  expect(await browser.evaluate(() => ({ value: document.querySelector<HTMLInputElement>('#kiriof-google-maps-key')!.value, scripts: document.querySelectorAll('script[data-kiriminaja-google-maps]').length, frames: document.querySelectorAll('iframe[data-kiriof-google-maps-probe]').length }))).toEqual({ value: '', scripts: 0, frames: 0 });
  expect(fixture.unexpected).toEqual([]);
});

for (const width of [320, 390, 768, 1280]) {
  test(`browser origin preview automatically loads tiles with masked key and usable controls at ${width}px`, async ({ app, browser, screen }) => {
    await browser.setViewport({ width, height: 1000 });
    const fixture = await settingsFixture(browser);
    await app.open('/wp-admin/admin.php?page=kiriminaja-setting&section=technical');
    await expect(browser.locator(checkSelector)).toHaveText(googleMaps.i18n.browserReady);
    expect(await browser.evaluate(() => {
      const input = document.querySelector<HTMLInputElement>('#kiriof-google-maps-key')!;
      const hint = document.getElementById(input.getAttribute('aria-describedby')!)!;
      return { hidden: hint.hidden, display: getComputedStyle(hint).display, text: hint.textContent };
    })).toEqual({ hidden: true, display: 'none', text: `${googleMaps.i18n.placeholder} ${googleMaps.i18n.keyHint}` });
    expect(await browser.evaluate(() => {
      const card = document.querySelector('section[aria-labelledby="kiriof-google-maps-title"]')!;
      const frame = card.querySelector<HTMLIFrameElement>('iframe')!;
      const reference = document.querySelector('section[aria-labelledby="kiriof-region-cache-title"]')!;
      const cardStyle = getComputedStyle(card), referenceStyle = getComputedStyle(reference);
      const title = getComputedStyle(card.querySelector('h2')!), referenceTitle = getComputedStyle(reference.querySelector('h2')!);
      const referenceButton = reference.querySelector('button')!.getBoundingClientRect();
      return { visiblePreview: frame.getBoundingClientRect().height > 0 && !frame.closest('[inert],[hidden],[aria-hidden="true"]'), noDetails: !card.querySelector('[aria-controls="kiriof-google-maps-details"]'), matchingCard: cardStyle.padding === referenceStyle.padding && cardStyle.borderRadius === referenceStyle.borderRadius && card.getBoundingClientRect().width === reference.getBoundingClientRect().width, matchingTitle: title.fontSize === referenceTitle.fontSize && title.fontWeight === referenceTitle.fontWeight && title.lineHeight === referenceTitle.lineHeight, compactControls: [...card.querySelectorAll('button')].every(node => { const rect = node.getBoundingClientRect(); return rect.height >= 24 && rect.width >= 24 && rect.height <= referenceButton.height; }) };
    })).toEqual({ visiblePreview: true, noDetails: true, matchingCard: true, matchingTitle: true, compactControls: true });
    expect(await browser.evaluate(() => {
      const card = document.querySelector('section[aria-labelledby="kiriof-google-maps-title"]')!;
      const input = card.querySelector<HTMLInputElement>('input')!;
      const frame = card.querySelector<HTMLIFrameElement>('iframe')!;
      return { field: !!input.closest('[data-slot="field"]'), group: !!input.closest('[data-slot="input-group"]'), title: frame.title, tiles: !!frame.contentDocument!.querySelector('[aria-label="Synthetic Google map tiles"]'), overflow: [...card.querySelectorAll('input,button,iframe')].some(node => { const r = node.getBoundingClientRect(); return r.left < 0 || r.right > innerWidth + 1; }) };
    })).toEqual({ field: true, group: true, title: googleMaps.i18n.previewTitle, tiles: true, overflow: false });
    expect(await browser.evaluate(() => {
      const card = document.querySelector('section[aria-labelledby="kiriof-google-maps-title"]')!;
      const input = card.querySelector<HTMLInputElement>('input')!;
      const group = input.closest('[data-slot="input-group"]')!;
      const style = getComputedStyle(input);
      const groupStyle = getComputedStyle(group);
      const cardStyle = getComputedStyle(card);
      const bounds = card.getBoundingClientRect();
      return { scope: !!input.closest('.kiriof-shadcn'), inputBorder: style.borderTopWidth, inputBackground: style.backgroundColor, groupBorder: groupStyle.borderTopWidth, cardBackground: cardStyle.backgroundColor, withinCard: [...card.querySelectorAll('[data-slot="alert"],iframe')].every(node => { const r = node.getBoundingClientRect(); return r.left >= bounds.left && r.right <= bounds.right + 1; }) };
    })).toEqual({ scope: true, inputBorder: '0px', inputBackground: 'rgba(0, 0, 0, 0)', groupBorder: '1px', cardBackground: 'oklch(1 0 0)', withinCard: true });
    expect(await browser.evaluate(() => document.body.innerHTML)).not.toContain(savedKey);
    expect(await browser.evaluate(() => {
      const canvas = document.createElement('canvas'); canvas.width = canvas.height = 1;
      const context = canvas.getContext('2d')!;
      function luminance(color: string) {
        context.fillStyle = color; context.fillRect(0, 0, 1, 1);
        const channels = [...context.getImageData(0, 0, 1, 1).data].slice(0, 3).map(value => { const channel = value / 255; return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4; });
        return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
      }
      const foreground = luminance(getComputedStyle(document.querySelector('[data-kiriof-google-maps-check] p')!).color);
      const background = luminance(getComputedStyle(document.querySelector('section[aria-labelledby="kiriof-google-maps-title"]')!).backgroundColor);
      return (Math.max(foreground, background) + 0.05) / (Math.min(foreground, background) + 0.05) >= 4.5;
    })).toBe(true);
    await app.screenshot(`google-maps-browser-check-${width}`);
    await expect(browser.locator(cardSelector)).toContainText(googleMaps.i18n.browserCheckGuidance);
    expect(await browser.evaluate(() => {
      const frame = document.querySelector<HTMLIFrameElement>('[data-kiriof-google-maps-probe]')!;
      return { tabIndex: frame.getAttribute('tabindex'), frameHeight: frame.getBoundingClientRect().height, statusVisible: frame.contentDocument!.querySelector('#kiriof-google-maps-check-status')!.getBoundingClientRect().height > 1 };
    })).toEqual({ tabIndex: null, frameHeight: 208, statusVisible: false });
    await browser.locator('#kiriof-google-maps-key').fill(replacement);
    // Native keyboard traversal must reach the reveal control, not submit/save.
    await browser.keyboard.press('Tab'); await browser.keyboard.press('Enter');
    await expect(screen.getByRole('button', { name: 'Hide key', exact: true })).toBeVisible();
    expect(await browser.evaluate(() => document.querySelector<HTMLInputElement>('#kiriof-google-maps-key')!.type)).toBe('text');
    await browser.keyboard.press('Enter');
    await expect(screen.getByRole('button', { name: 'Show key', exact: true })).toBeVisible();
    expect(await browser.evaluate(() => {
      document.documentElement.style.fontSize = '200%';
      const card = document.querySelector('section[aria-labelledby="kiriof-google-maps-title"]')!;
      const bounds = card.getBoundingClientRect();
      return { overflow: card.scrollWidth > card.clientWidth, controlsFit: [...card.querySelectorAll('input,button,iframe')].every(node => { const rect = node.getBoundingClientRect(); return rect.left >= bounds.left && rect.right <= bounds.right; }) };
    })).toEqual({ overflow: false, controlsFit: true });
    expect(fixture.bodies).toEqual([]); expect(fixture.frames).toHaveLength(1); expect(fixture.googleRequests).toHaveLength(1); expect(fixture.unexpected).toEqual([]);
  });
}

for (const mode of ['auth', 'network'] as const) {
  test(`browser origin ${mode} failure maps to a fixed accessible error and retry uses a fresh frame`, async ({ app, browser, screen }) => {
    await browser.setViewport({ width: 320, height: 900 });
    const fixture = await settingsFixture(browser, { mode });
    await app.open('/wp-admin/admin.php?page=kiriminaja-setting&section=technical');
    const text = mode === 'auth' ? googleMaps.i18n.browserFailed : googleMaps.i18n.browserNetwork;
    await expect(browser.locator(checkSelector)).toHaveText(text);
    expect(await browser.evaluate(() => document.querySelector('[data-kiriof-google-maps-check]')!.getAttribute('role'))).toBe('alert');
    expect(await browser.evaluate(() => {
      const status = document.querySelector('[data-kiriof-google-maps-check]')!;
      const card = document.querySelector('section[aria-labelledby="kiriof-google-maps-title"]')!;
      return { statusWidth: status.getBoundingClientRect().width > card.getBoundingClientRect().width * 0.8, overflow: document.documentElement.scrollWidth > innerWidth, overflowing: [...document.querySelectorAll('body *')].filter(node => node.getBoundingClientRect().right > innerWidth + 1).slice(0, 12).map(node => ({ tag: node.tagName, class: node.className, width: node.getBoundingClientRect().width, right: node.getBoundingClientRect().right })) };
    })).toEqual({ statusWidth: true, overflow: false, overflowing: [] });
    await screen.getByRole('button', { name: googleMaps.i18n.checkAgain, exact: true }).click();
    await expect(browser.locator(checkSelector)).toHaveText(text);
    expect(fixture.frames).toHaveLength(2); expect(fixture.googleRequests).toHaveLength(2);
    expect(new URL(fixture.frames[0]).searchParams.get('channel')).not.toBe(new URL(fixture.frames[1]).searchParams.get('channel'));
    expect(fixture.bodies).toEqual([]); expect(fixture.unexpected).toEqual([]);
  });
}

test('no configured browser key means no automatic iframe or Google request', async ({ app, browser, screen }) => {
  const fixture = await settingsFixture(browser, { configured: false });
  await app.open('/wp-admin/admin.php?page=kiriminaja-setting&section=technical');
  await expect(browser.locator(checkSelector)).toHaveText(googleMaps.i18n.browserNotConfigured);
  expect(await browser.evaluate(() => document.querySelectorAll('iframe[data-kiriof-google-maps-probe]').length)).toBe(0);
  expect(fixture.frames).toEqual([]); expect(fixture.googleRequests).toEqual([]); expect(fixture.unexpected).toEqual([]);
});

test('SDK callback alone and forged or stale probe messages cannot mark the browser ready; late auth revokes readiness', async ({ app, browser, screen }) => {
  const fixture = await settingsFixture(browser, { mode: 'callback-only' });
  await app.open('/wp-admin/admin.php?page=kiriminaja-setting&section=technical');
  // Wait for the real frame SDK callback before exercising its message boundary.
  await browser.evaluate(async () => { for (let i = 0; i < 100; i++) { const frame = document.querySelector<HTMLIFrameElement>('iframe[data-kiriof-google-maps-probe]'); if ((frame?.contentWindow as any)?.__probeMap) return true; await new Promise(resolve => setTimeout(resolve, 20)); } throw new Error('Probe SDK callback missing'); });
  await expect(browser.locator(checkSelector)).toHaveText(googleMaps.i18n.checking);
  await browser.evaluate(() => {
    const frame = document.querySelector<HTMLIFrameElement>('iframe[data-kiriof-google-maps-probe]')!;
    const channel = new URL(frame.src).searchParams.get('channel');
    const data = { type: 'kiriof-google-maps-check', channel, status: 'ready', code: 'tiles_loaded' };
    for (const init of [{ origin: 'https://forged.invalid', source: frame.contentWindow, data }, { origin: location.origin, source: window, data }, { origin: location.origin, source: frame.contentWindow, data: { ...data, channel: 'wrong_channel_12345678901234567890' } }]) window.dispatchEvent(new MessageEvent('message', init));
    (window as any).__staleProbe = { source: frame.contentWindow, data };
    return true;
  });
  await expect(browser.locator(checkSelector)).toHaveText(googleMaps.i18n.checking);
  await screen.getByRole('button', { name: googleMaps.i18n.checkAgain, exact: true }).click();
  await browser.evaluate(async () => { for (let i = 0; i < 100; i++) { const f = document.querySelector<HTMLIFrameElement>('iframe[data-kiriof-google-maps-probe]')!; if ((f.contentWindow as any)?.__probeMap) return true; await new Promise(resolve => setTimeout(resolve, 20)); } throw new Error('Retry SDK missing'); });
  await browser.evaluate(() => { const old = (window as any).__staleProbe; const frame = document.querySelector<HTMLIFrameElement>('iframe[data-kiriof-google-maps-probe]')!; window.dispatchEvent(new MessageEvent('message', { origin: location.origin, source: old.source, data: { ...old.data, channel: new URL(frame.src).searchParams.get('channel') } })); window.dispatchEvent(new MessageEvent('message', { origin: location.origin, source: frame.contentWindow, data: old.data })); return true; });
  // Longer than the real 800ms readiness grace: callback/no tiles is not success.
  await browser.evaluate(() => new Promise<boolean>(resolve => setTimeout(() => resolve(true), 1000)));
  await expect(browser.locator(checkSelector)).toHaveText(googleMaps.i18n.checking);
  await browser.evaluate(() => { (document.querySelector<HTMLIFrameElement>('iframe[data-kiriof-google-maps-probe]')!.contentWindow as any).__probeMap.fire('tilesloaded'); return true; });
  await expect(browser.locator(checkSelector)).toHaveText(googleMaps.i18n.browserReady);
  await browser.evaluate(() => { (document.querySelector<HTMLIFrameElement>('iframe[data-kiriof-google-maps-probe]')!.contentWindow as any).gm_authFailure(); return true; });
  await expect(browser.locator(checkSelector)).toHaveText(googleMaps.i18n.browserFailed);
  expect(fixture.frames).toHaveLength(2); expect(fixture.googleRequests).toHaveLength(2); expect(fixture.unexpected).toEqual([]);
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
