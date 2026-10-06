import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const read = (path: string) => readFileSync(root + path, 'utf8');
const dependency = (path: string) => readFileSync(new URL('../node_modules/' + path, import.meta.url), 'utf8');
// Exact WC Booster selector responsible for hiding the complete order summary.
// Deliberately loaded AFTER the production plugin stylesheet.
const booster = 'body.wc-booster-checkout-customization .woocommerce-checkout div#order_review table.shop_table.woocommerce-checkout-review-order-table {display:none}';
const express = 'kiriminaja-official:1:jne:reg';
const instant = 'kiriminaja-instant:1:gosend:instant';

function html(scene: string, enhanced: boolean) {
  const result = JSON.parse(execFileSync('php', [root + 'tests/fixtures/classic-order-review-runtime.php', JSON.stringify({ scene })], { encoding: 'utf8' }));
  const scripts = [
    dependency('jquery/dist/jquery.min.js'),
    ...(enhanced ? [dependency('select2/dist/js/select2.full.min.js')] : []),
    // Only bootstrap the production shipping module, not payment/booking ready hooks.
    'window.kiriofClassicCheckoutConfig = { enabled: true, ownsDistrict: false };',
    read('assets/wp/js/checkout/state.js'),
    read('assets/wp/js/checkout/shipping-payment.js'),
    `window.__nativeChanges = []; window.__networkCalls = 0;
     const forbidden = () => { window.__networkCalls++; throw new Error('Unexpected mutation or booking request'); };
     window.fetch = forbidden; XMLHttpRequest.prototype.open = forbidden; navigator.sendBeacon = forbidden;
     jQuery(document).on('change.fixtureWoo', 'input.shipping_method', function() {
       window.__nativeChanges.push({ value: this.value, name: this.name, checked: this.checked });
     });
     kiriofScheduleClassicShippingMethodSelectInit();`,
  ];
  return `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">
    <style>body{margin:12px;font-family:sans-serif}table{width:100%;table-layout:${enhanced ? 'fixed' : 'auto'};border-collapse:collapse}th,td{padding:8px;text-align:left}</style>
    <style>${enhanced ? dependency('select2/dist/css/select2.min.css') : ''}</style>
    <style>${read('assets/wp/css/kj-wp-style.css')}</style><style>${booster}</style></head>
    <body class="wc-booster-checkout-customization"><div class="woocommerce woocommerce-checkout">
    <form class="checkout woocommerce-checkout"><div id="order_review">${result.html}</div></form>
    </div>${scripts.map(script => `<script>${script.replace(/<\/script/gi, '<\\/script')}</script>`).join('')}</body></html>`;
}

for (const width of [1200, 390]) {
  for (const mode of ['enhanced', 'native', 'missing-api'] as const) {
    test(`real production order review survives later WC Booster hide at ${width}px (${mode})`, async ({ app, browser }) => {
      await browser.setViewport({ width, height: 900 });
      const requests: string[] = [];
      await browser.route('**/*', route => {
        const url = route.request.url;
        if (new URL(url).pathname === '/order-review') {
          return route.fulfill({ contentType: 'text/html', body: html(mode === 'missing-api' ? mode : 'rates', mode === 'enhanced') });
        }
        requests.push(url);
        return route.fulfill({ status: 409, contentType: 'text/plain', body: 'No external provider or mutation requests allowed' });
      });
      await app.open('/order-review');
      const table = browser.locator('#order_review table.kiriof-classic-order-review');
      await expect(table).toBeVisible();
      await expect(browser.locator('.cart_item .product-name')).toContainText('Fixture coffee');
      await expect(browser.locator('.cart-subtotal')).toContainText('Rp 120,000');
      await expect(browser.locator('.cart-subtotal')).toBeVisible();
      await expect(browser.locator('.order-total')).toBeVisible();
      await expect(browser.locator('.woocommerce-shipping-contents')).toContainText('Fixture coffee');
      expect(await browser.evaluate(() => {
        const table = document.querySelector('#order_review table')!;
        const rect = table.getBoundingClientRect();
        return { display: getComputedStyle(table).display, width: rect.width > 0, withinViewport: rect.left >= 0 && rect.right <= innerWidth, overflow: document.documentElement.scrollWidth > innerWidth };
      })).toEqual({ display: 'table', width: true, withinViewport: true, overflow: false });

      if (mode === 'missing-api') {
        await expect(browser.locator('.woocommerce-shipping-totals')).toContainText('Shipping options are currently unavailable. Please contact us for assistance.');
        await expect(browser.locator('.order-total')).toContainText('Rp 120,000');
        expect(await browser.evaluate(() => ({ rates: document.querySelectorAll('input.shipping_method').length, pinError: /\bpin\b.*(required|invalid|error)/i.test(document.querySelector('#order_review')!.textContent || '') }))).toEqual({ rates: 0, pinError: false });
      } else {
        await expect(browser.locator('.order-total')).toContainText('Rp 135,000');
        if (mode === 'enhanced') {
          await expect(browser.locator('.select2-selection')).toBeVisible();
          await browser.locator('.select2-selection').click();
          await expect(browser.locator('.select2-results')).toContainText('Fixture JNE Express REG');
          await expect(browser.locator('.select2-results')).toContainText('Fixture GOSEND Instant');
          await browser.locator('.select2-results__option').filter({ hasText: 'Fixture GOSEND Instant' }).click();
        } else {
          await expect(browser.locator('label[for="shipping_method_0_kiriminaja-official-1-jne-reg"]')).toBeVisible();
          await expect(browser.locator('label[for="shipping_method_0_kiriminaja-instant-1-gosend-instant"]')).toBeVisible();
          await browser.locator(`input.shipping_method[value="${instant}"]`).click();
        }
        expect(await browser.evaluate(() => ({ checked: Array.from(document.querySelectorAll<HTMLInputElement>('input.shipping_method:checked')).map(input => input.value), changes: (window as any).__nativeChanges })) ).toEqual({ checked: [instant], changes: [{ value: instant, name: 'shipping_method[0]', checked: true }] });
        expect(await browser.evaluate(() => document.querySelector<HTMLInputElement>('input.shipping_method:checked')!.value)).not.toBe(express);
      }
      expect(await browser.evaluate(() => (window as any).__networkCalls)).toBe(0);
      expect(requests.filter(url => !url.endsWith('/favicon.ico'))).toEqual([]);
      await app.screenshot(`real-order-review-${width}-${mode}`);
    });
  }
}
