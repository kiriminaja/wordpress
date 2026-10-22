import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import type { WarehousesBootstrap } from '../../../src/lib/settings/warehouses-types';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const json = (value: unknown) => JSON.stringify(value).replace(/</g, '\\u003c');
const read = (path: string) => readFileSync(root + path, 'utf8');

// Production PHP DTO/i18n and Woo country/state serialization, with an in-memory
// repository only. No WordPress server, database, customer or external API.
function bootstrap(): WarehousesBootstrap {
  const row = { id: 1, name: 'Synthetic default warehouse', phone: '0000000000', address: 'Synthetic warehouse road', address_2: '', city: 'Fixture city', country: 'ID', state: 'YO', sub_district_id: 123, sub_district_name: 'Synthetic district', zip_code: '12345', latitude: '-7.8', longitude: '110.3', is_default: 1, is_active: 1 };
  const output = JSON.parse(execFileSync('php', [root + 'tests/fixtures/warehouse-settings-runtime.php', JSON.stringify({ operation: 'bootstrap', rows: [row, { ...row, id: 2, name: 'Synthetic branch warehouse', is_default: 0 }], countries: { ID: 'Indonesia' }, states: { ID: { JK: 'DKI Jakarta', YO: 'DI Yogyakarta' } } })], { encoding: 'utf8' }));
  const data: WarehousesBootstrap = output.result;
  data.ajaxUrl = '/wp-admin/admin-ajax.php';
  data.toolbar.rootUrl = '/wp-admin/admin.php?page=kiriminaja-setting';
  data.toolbar.logoUrl = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg"/%3E';
  data.map = { provider: 'google', apiKey: 'synthetic-warehouse-sdk-key', enabled: true };
  return data;
}

// Relevant adverse native WP rules deliberately arrive AFTER all shipped CSS.
const wordpress = `
body{margin:0;font:13px/1.4 Arial,sans-serif;color:#3c434a;background:#f0f0f1}
h2{font-size:23px;font-weight:400;margin:1em 0}p{margin:1em 0}
label{cursor:pointer;vertical-align:middle}fieldset{padding:0;border:0}
input[type=text],input[type=tel],textarea,select{box-shadow:0 0 0 transparent;border-radius:4px;border:1px solid #8c8f94;background:#fff;color:#2c3338;min-height:30px;line-height:2;padding:0 8px}
input[type=text]:focus,textarea:focus{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;outline:2px solid transparent}
@media(max-width:782px){input[type=text],input[type=tel],textarea,select{min-height:40px;font-size:16px}}
`;
function html(data: WarehousesBootstrap) {
  return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <style>${read('assets/admin/css/kj-admin-style.css')}</style>
  ${['kiriminaja-kiriof-var.css', 'kiriminaja-kiriof-component.css', 'kiriminaja-admin-workspace.css'].map(name => `<link rel="stylesheet" href="/assets/admin/dist/${name}">`).join('')}
  <style>${wordpress}</style></head>
  <body class="wp-admin kiriof-admin"><main class="wrap kj-wrap kiriof-workspace-shell" data-kiriof-settings-page><div data-kiriof-settings-root></div><script type="application/json" data-kiriof-settings-payload>${json(data)}</script></main>
  <script>window.__warehouseErrors=[];window.__warehouseGeolocation=0;window.addEventListener('error',e=>window.__warehouseErrors.push(e.message));window.addEventListener('unhandledrejection',e=>window.__warehouseErrors.push(String(e.reason)));Object.defineProperty(navigator,'geolocation',{configurable:true,value:{getCurrentPosition(){window.__warehouseGeolocation++}}});</script>
  <script type="module" src="/assets/admin/dist/kiriminaja-admin-workspace.js"></script></body></html>`;
}

// Only the Google SDK network boundary is synthetic, matching google-maps.e2e.ts.
// Production loader, map adapter, lifecycle and WarehousePin remain unmodified.
function google(callback: string) {
  return `(()=>{class FixtureMap{constructor(node,options){this.center=options.center;this.listeners={};const button=document.createElement('button');button.type='button';button.textContent='Select synthetic map point';button.onclick=()=>this.fire('click',{latLng:{lat:()=>-7.81,lng:()=>110.31}});node.append(button);setTimeout(()=>this.fire('idle'),0)}addListener(name,cb){(this.listeners[name]??=[]).push(cb);return{remove:()=>{this.listeners[name]=this.listeners[name].filter(x=>x!==cb)}}}fire(name,event){for(const cb of this.listeners[name]||[])cb(event)}setCenter(center){this.center=center;this.fire('center_changed');setTimeout(()=>this.fire('idle'),0)}getCenter(){return{lat:()=>this.center.lat,lng:()=>this.center.lng}}setZoom(){}}class Overlay{setMap(){}}window.google={maps:{Map:FixtureMap,Polyline:Overlay,Circle:Overlay,event:{trigger:(map,name)=>map.fire(name),clearInstanceListeners:map=>map.listeners={}}}};window[${json(callback)}]();})();`;
}
async function fixture(browser: any, data: WarehousesBootstrap) {
  const mutations: Record<string, string>[] = [], unexpected: string[] = [], maps: string[] = [];
  await browser.route('**/*', (route: any) => {
    const url = new URL(route.request.url);
    if (url.origin === 'https://fixture.test') {
      if (url.pathname === '/wp-admin/admin.php' && route.request.method === 'GET') return route.fulfill({ contentType: 'text/html', body: html(data) });
      if (/^\/assets\/admin\/dist\/(?:assets\/)?[\w.-]+\.(?:js|css)$/.test(url.pathname)) return route.fulfill({ contentType: url.pathname.endsWith('.css') ? 'text/css' : 'text/javascript', body: read(url.pathname.slice(1)) });
      if (url.pathname === '/wp-admin/admin-ajax.php' && route.request.method === 'POST') {
        const body = Object.fromEntries(new URLSearchParams(route.request.postData || ''));
        mutations.push(body);
        if (body.action === data.actions.toggle) return route.fulfill({ contentType: 'application/json', body: json({ success: true, data: { status: 200, id: 2, is_active: body['data[is_active]'] === '1' } }) });
        unexpected.push('Unexpected mutation: ' + body.action);
      }
    }
    if (url.origin === 'https://maps.googleapis.com' && url.pathname === '/maps/api/js') {
      maps.push(url.href);
      return route.fulfill({ contentType: 'text/javascript', body: google(url.searchParams.get('callback')!) });
    }
    if (url.pathname !== '/favicon.ico') unexpected.push(route.request.method + ' ' + url.href);
    return route.fulfill({ status: 409, body: 'No live network or unapproved mutation permitted' });
  });
  return { mutations, unexpected, maps };
}

async function dialogMetrics(browser: any) {
  // Wait for Bits UI's production animation before measuring final geometry.
  return browser.evaluate(async () => {
    const dialog = document.querySelector<HTMLElement>('[role="dialog"], [role="alertdialog"]')!;
    await Promise.all(dialog.getAnimations().map(animation => animation.finished.catch(() => {})));
    const box = dialog.getBoundingClientRect();
    return { left: box.left, right: box.right, top: box.top, bottom: box.bottom, width: innerWidth, height: innerHeight, overflow: dialog.scrollWidth - dialog.clientWidth };
  });
}
function assertDialog(metrics: Awaited<ReturnType<typeof dialogMetrics>>) {
  expect(metrics.left).toBeGreaterThanOrEqual(0);
  expect(metrics.right).toBeLessThanOrEqual(metrics.width);
  expect(metrics.top).toBeGreaterThanOrEqual(0);
  expect(metrics.bottom).toBeLessThanOrEqual(metrics.height);
  expect(metrics.overflow).toBeLessThanOrEqual(1);
}

for (const width of [1440, 390]) {
  test(`built warehouse workspace: toolbar, actions, labels, map and containment at ${width}px`, async ({ app, browser }) => {
    const data = bootstrap();
    const boundary = await fixture(browser, data);
    await browser.setViewport({ width, height: 900 });
    await app.open('/wp-admin/admin.php?page=kiriminaja-setting&section=warehouses');
    const add = browser.locator('[data-warehouse-add]');
    await expect(add).toHaveText(data.i18n.add);
    await expect(browser.locator('[data-warehouse-row="2"]')).toContainText('Synthetic branch warehouse');
    const layout = await browser.evaluate(() => {
      const shell = document.querySelector<HTMLElement>('.kiriof-workspace-shell')!;
      const section = document.querySelector<HTMLElement>('section[aria-labelledby="warehouses-title"]')!;
      const button = document.querySelector('[data-warehouse-add]')!;
      const rect = button.getBoundingClientRect();
      return { pageOverflow: document.documentElement.scrollWidth - innerWidth, shellOverflow: shell.scrollWidth - shell.clientWidth, sectionOverflow: section.scrollWidth - section.clientWidth, addInToolbar: !!button.closest('.kiriof-app-toolbar__actions'), addReachable: rect.left >= 0 && rect.right <= innerWidth, defaultLocked: (document.querySelector('[data-warehouse-row="1"] [role="switch"]') as HTMLButtonElement).disabled, defaultDelete: !!document.querySelector('[data-warehouse-delete="1"]') };
    });

    // A table may scroll on narrow screens; each action must be reachable and
    // unclipped INSIDE that scrollport, rather than hidden by page overflow.
    for (const action of ['edit', 'delete']) {
      const control = browser.locator(`[data-warehouse-${action}="2"]`);
      await control.scrollIntoView();
      await control.hover();
      await expect(browser.locator('[data-slot="tooltip-content"]')).toContainText(`${data.i18n[action]}: Synthetic branch warehouse`);
      expect(await browser.evaluate((action: string) => {
        const button = document.querySelector<HTMLElement>(`[data-warehouse-${action}="2"]`)!;
        const box = button.getBoundingClientRect();
        const port = button.closest('[data-slot="table-container"]')!.getBoundingClientRect();
        return { iconOnly: button.textContent!.trim() === '' && !!button.querySelector('svg'), reachable: box.left >= Math.max(0, port.left) && box.right <= Math.min(innerWidth, port.right) + 1, height: box.height };
      }, action)).toEqual({ iconOnly: true, reachable: true, height: 28 });
    }
    const active = browser.locator('[data-warehouse-row="2"] [role="switch"]');
    await active.click();
    await expect(active).toHaveAttribute('aria-checked', 'false');
    await expect(browser.locator('[data-warehouse-row="2"]')).toContainText(data.i18n.inactive);
    expect(boundary.mutations).toEqual([{ action: data.actions.toggle, 'data[nonce]': data.nonce, 'data[id]': '2', 'data[is_active]': '0' }]);

    await browser.locator('[data-warehouse-delete="2"]').click();
    await expect(browser.locator('[role="alertdialog"]')).toContainText(data.i18n.confirmDelete);
    assertDialog(await dialogMetrics(browser));
    await browser.locator('[role="alertdialog"]').getByRole('button', { name: data.i18n.cancel, exact: true }).click();
    await expect(browser.locator('[role="alertdialog"]')).toHaveCount(0);
    await expect(browser.locator('[data-warehouse-row="2"]')).toBeVisible();
    expect(boundary.mutations).toHaveLength(1);

    await browser.locator('[data-warehouse-edit="2"]').click();
    await expect(browser.locator('#warehouse-name')).toHaveValue('Synthetic branch warehouse');
    await expect(browser.locator('#warehouse-country_state')).toHaveText('Indonesia / DI Yogyakarta');
    await expect(browser.locator('#warehouse-pin button')).toHaveText('Select synthetic map point');
    assertDialog(await dialogMetrics(browser));
    const fields = await browser.evaluate(() => [...document.querySelectorAll<HTMLLabelElement>('[role="dialog"] label[for]')].map(label => {
      const field = document.getElementById(label.htmlFor)!;
      const style = getComputedStyle(label);
      return { id: label.htmlFor, text: label.textContent!.trim(), exists: !!field, height: field.getBoundingClientRect().height, display: style.display, weight: style.fontWeight };
    }));
    expect(fields.map((field: any) => field.text)).toEqual([data.i18n.name, data.i18n.senderPhone, data.i18n.address, data.i18n.address2, data.i18n.city, data.i18n.countryState, data.i18n.zip, data.i18n.area]);
    for (const field of fields) {
      expect(field.exists).toBe(true); expect(field.display).toBe('flex'); expect(field.weight).toBe('500');
      if (field.id !== 'warehouse-address') expect(field.height).toBe(32);
    }
    await browser.locator('#warehouse-country_state').click();
    await expect(browser.locator('body').getByRole('option', { name: 'Indonesia / DKI Jakarta', exact: true })).toBeVisible();
    await browser.locator('body').getByRole('option', { name: 'Indonesia / DKI Jakarta', exact: true }).click();
    await expect(browser.locator('#warehouse-country_state')).toHaveText('Indonesia / DKI Jakarta');
    await browser.locator('[role="dialog"]').getByRole('button', { name: data.i18n.cancel, exact: true }).click();
    await expect(browser.locator('[role="dialog"]')).toHaveCount(0);

    await add.click();
    await expect(browser.locator('[role="dialog"]')).toBeVisible();
    await expect(browser.locator('#warehouse-pin[role="region"]')).toHaveAttribute('aria-labelledby', 'warehouse-pin-label');
    await expect(browser.locator('#warehouse-pin button')).toBeVisible();
    expect(await browser.evaluate((pin: string) => [...document.querySelectorAll('[role="dialog"] button')].some(node => node.textContent!.trim() === pin), data.i18n.pin)).toBe(false);
    await browser.locator('#warehouse-pin button').click();
    await expect(browser.locator('[role="dialog"]')).toContainText(data.i18n.pinSelected);
    assertDialog(await dialogMetrics(browser));
    await browser.locator('[role="dialog"]').getByRole('button', { name: data.i18n.cancel, exact: true }).click();
    await expect(browser.locator('[role="dialog"]')).toHaveCount(0);
    expect(await browser.evaluate(() => ({ geolocation: (window as any).__warehouseGeolocation, errors: (window as any).__warehouseErrors, focusRestored: document.activeElement === document.querySelector('[data-warehouse-add]') }))).toEqual({ geolocation: 0, errors: [], focusRestored: true });
    expect(boundary.mutations).toHaveLength(1);
    expect(boundary.maps).toHaveLength(1);
    expect(boundary.unexpected).toEqual([]);
    // Keep page-level containment last so all interactive regression probes run
    // even when inherited WP .wrap margins expose an outer-shell defect.
    expect(layout).toEqual({ pageOverflow: 0, shellOverflow: 0, sectionOverflow: 0, addInToolbar: true, addReachable: true, defaultLocked: true, defaultDelete: false });
  });
}
