import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const courier = 'AnterAja Regular Flat with a long courier service label';
function shipment(chosen = 'anteraja') {
  return JSON.parse(execFileSync('php', [root + 'tests/fixtures/classic-shipping-presentation-runtime.php', JSON.stringify({ cart: true, chosen, session: { destination_id: 46310 }, rates: [{ id: 'anteraja', label: courier, cost: 27000 }] })], { encoding: 'utf8' })).html;
}
function html() {
  return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>
    body { margin:16px; font:16px Arial,sans-serif; }
    .layout { display:grid; grid-template-columns:minmax(0,2fr) minmax(0,1fr); gap:30px; max-width:1200px; margin:auto; }
    .cart_totals { padding:24px; border-radius:20px; background:#f7f7f7; }
    table { border-collapse:collapse; width:100%; }
    th, td { padding:16px 0; border-bottom:1px solid #ddd; }
    th { text-align:left; } td { text-align:right; }
    @media(max-width:600px) { .layout { grid-template-columns:minmax(0,1fr); } }
    ${readFileSync(root + 'assets/buyer/css/kj-wp-style.css', 'utf8')}
  </style></head><body class="woocommerce woocommerce-cart"><main class="layout"><section><h1>Cart</h1><p>Product quantity: 6</p></section><aside class="kj-cart-total cart_totals"><h2>Cart totals</h2><table class="shop_table"><tbody>
    <tr><th>Subtotal</th><td>Rp4,914,000</td></tr>${shipment()}
    <tr><th>Insurance</th><td>Rp9,900</td></tr><tr><th>Total</th><td>Rp4,950,900</td></tr>
  </tbody></table><a href="/checkout/">Proceed to checkout</a></aside></main></body></html>`;
}
for (const width of [1200, 768, 390]) {
  test(`Classic cart shows read-only shipping within totals at ${width}px`, async ({ app, browser }) => {
    await browser.route('**/*', (route: any) => new URL(route.request.url).pathname === '/classic-cart-layout'
      ? route.fulfill({ contentType: 'text/html; charset=utf-8', body: html() })
      : route.fulfill({ status: 404, body: '' }));
    await browser.setViewport({ width, height: 1000 });
    await app.open('/classic-cart-layout');
    for (const refresh of [false, true]) {
      if (refresh) await browser.evaluate((markup: string) => { document.querySelector('.kiriof-cart-shipment-row')!.outerHTML = markup; document.body.dispatchEvent(new Event('updated_cart_totals')); }, shipment());
      await expect(browser.locator('.kiriof-cart-shipment-row')).toContainText(courier);
      await expect(browser.locator('.kiriof-cart-shipment-row')).toContainText('Rp 27000');
      expect(await browser.evaluate(() => {
        const panel = document.querySelector('.cart_totals')!.getBoundingClientRect();
        const table = document.querySelector('.shop_table')!.getBoundingClientRect();
        return {
          tableFits: table.left >= panel.left && table.right <= panel.right + 1,
          labelsFit: Array.from(document.querySelectorAll<HTMLElement>('.kiriof-cart-shipment-label, .kiriof-cart-shipment-price')).every(el => el.scrollWidth <= el.clientWidth + 1),
          pageFits: document.documentElement.scrollWidth <= innerWidth,
          controls: document.querySelectorAll('.cart_totals select, .cart_totals input').length,
          shipTo: document.querySelector('.cart_totals')!.textContent!.includes('Ship To'),
        };
      })).toEqual({ tableFits: true, labelsFit: true, pageFits: true, controls: 0, shipTo: false });
      await expect(browser.locator('.cart_totals')).toContainText('Rp4,950,900');
    }
    await expect(browser.locator('.cart_totals a')).toHaveAttribute('href', '/checkout/');
  });
}
