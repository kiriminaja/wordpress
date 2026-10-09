import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const json = (value: unknown) => JSON.stringify(value).replace(/</g, '\\u003c');
const bootstrap = {
  view: 'couriers',
  toolbar: { logoUrl: '', rootUrl: '/wp-admin/admin.php?page=kiriminaja-setting', rootLabel: 'Settings', title: 'Courier List' },
  i18n: { searchCouriers: 'Search couriers or services', enableAll: 'Enable All', disableAll: 'Disable All', loading: 'Loading', loadFailed: 'Could not load', saveFailed: 'Could not save', noCouriers: 'No couriers', noResults: 'No matching couriers', autoSave: 'Changes are saved automatically.' },
};
const payload = { couriers: [
  { code: 'jne', name: 'JNE', services: [{ code: 'REG', name: 'Regular' }] },
  { code: 'gosend', name: 'GoSend', delivery_type: 'instant', services: [{ code: 'instant', name: 'Instant' }] },
], whitelist_ids: ['jne', 'gosend'], service_selection: { jne: ['REG'], gosend: ['instant'] } };

// Render the deployed workspace; WordPress AJAX is the explicit mocked boundary.
// A later native input rule reproduces the extra border without changing globals.
test('courier toolbar search has one border and filters locally under native input styles', async ({ app, browser }) => {
  const unexpected: string[] = [], requests: string[] = [];
  const html = `<!doctype html><html><head><meta charset="utf-8">${['kiriminaja-kiriof-var.css', 'kiriminaja-kiriof-component.css', 'kiriminaja-admin-workspace.css'].map(name => `<link rel="stylesheet" href="/assets/admin/dist/${name}">`).join('')}
  <style>body{margin:0}.woocommerce input[type=search]{border:1px solid #777;border-radius:0;box-shadow:inset 0 1px 2px #ddd;min-height:40px}.woocommerce input[type=search]:focus{border-color:blue;box-shadow:0 0 0 1px blue;outline:1px solid blue}</style></head>
  <body class="woocommerce"><div class="kiriof-workspace-shell" data-kiriof-settings-page><div data-kiriof-settings-root></div><script type="application/json" data-kiriof-settings-payload>${json(bootstrap)}</script></div><script>window.ajaxurl='/wp-admin/admin-ajax.php';window.kiriofSettings={nonce:'fixture-nonce'};</script><script type="module" src="/assets/admin/dist/kiriminaja-admin-workspace.js"></script></body></html>`;
  await browser.route('**/*', (route: any) => {
    const url = new URL(route.request.url);
    if (url.origin === 'https://fixture.test') {
      if (url.pathname === '/wp-admin/admin-ajax.php' && route.request.method === 'POST') {
        const body = new URLSearchParams(route.request.postData || ''); requests.push(body.get('action') || '');
        expect(body.get('action')).toBe('kiriof_get_courier_whitelist');
        return route.fulfill({ contentType: 'application/json', body: json({ success: true, data: { status: 200, data: payload } }) });
      }
      if (url.pathname === '/wp-admin/admin.php') return route.fulfill({ contentType: 'text/html', body: html });
      if (url.pathname.startsWith('/assets/admin/dist/')) return route.fulfill({ contentType: url.pathname.endsWith('.css') ? 'text/css' : 'text/javascript', body: readFileSync(root + url.pathname.slice(1), 'utf8') });
      if (url.pathname.startsWith('/assets/buyer/img/')) return route.fulfill({ contentType: 'image/png', path: root + url.pathname.slice(1) });
    }
    if (url.pathname !== '/favicon.ico') unexpected.push(url.href);
    return route.fulfill({ status: 409, body: 'No live requests' });
  });
  await app.open('/wp-admin/admin.php?page=kiriminaja-setting&section=couriers');
  const search = browser.locator('.kiriof-couriers-search input');
  await expect(browser.locator('h3')).toHaveCount(2);
  for (const width of [1440, 390]) {
    await browser.setViewport({ width, height: 900 });
    for (const focused of [false, true]) {
      if (focused) await search.focus(); else await browser.evaluate(() => document.querySelector<HTMLInputElement>('.kiriof-couriers-search input')!.blur());
      const state = await browser.evaluate(() => {
        const group = document.querySelector<HTMLElement>('.kiriof-couriers-search')!;
        const input = group.querySelector<HTMLInputElement>('input')!;
        const outer = group.getBoundingClientRect(), inner = input.getBoundingClientRect();
        const styles = getComputedStyle(input), parent = getComputedStyle(group);
        return { border: styles.borderWidth, shadow: styles.boxShadow, outline: styles.outlineStyle, height: outer.height,
          outerBorder: parent.borderTopWidth, outerFocus: parent.boxShadow, left: inner.left - outer.left, right: inner.right - outer.right,
          pageOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth };
      });
      expect(state.border).toBe('0px');
      // Tailwind ring-0 composes zero-sized shadow layers instead of CSS "none".
      expect(state.shadow).not.toMatch(/-?[1-9][\d.]*px/);
      expect(state.outline).toBe('none');
      expect(state.height).toBe(34); expect(state.outerBorder).toBe('1px');
      expect(state.left).toBeGreaterThanOrEqual(0); expect(state.right).toBeLessThanOrEqual(0);
      expect(state.pageOverflow).toBeLessThanOrEqual(1);
      if (focused) expect(state.outerFocus).not.toBe('none');
    }
  }
  await search.fill('GoSend'); await expect(browser.locator('h3')).toHaveCount(1); await expect(browser.locator('h3')).toContainText('GoSend');
  await search.fill('Regular'); await expect(browser.locator('h3')).toHaveCount(1); await expect(browser.locator('h3')).toContainText('JNE');
  await search.fill(''); await expect(browser.locator('h3')).toHaveCount(2);
  expect(requests).toEqual(['kiriof_get_courier_whitelist']); expect(unexpected).toEqual([]);
});
