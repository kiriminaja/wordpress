import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import type { TransactionDetailBootstrap } from '../../../src/lib/transaction-detail/types';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const detailSelector = '.kiriof-shipment-card';
const metaboxSelector = '#kiriminaja-shipping-info';
const statusSelector = '.kiriof-detail-status-card';
const paymentLabels = ['Payment ID', 'Payment method', 'Payment status'];
const paymentIdentifier = (delivery: Delivery, scenario: Scenario) => (delivery === 'express' ? 'XID-EXPRESS-10' : 'INSTANT-PERSISTED-10') + (scenario === 'discount' ? '-LONG-SAVED-PAYMENT-REFERENCE'.repeat(6) : '');
type Delivery = 'express' | 'instant';
type Scenario = 'simple' | 'discount' | 'missing';
const json = (value: unknown) => JSON.stringify(value).replace(/</g, '\\u003c');
function php(name: string, payload: unknown): any {
  return JSON.parse(execFileSync('php', [root + 'tests/fixtures/' + name, JSON.stringify(payload)], { encoding: 'utf8' }));
}

async function senderRecipientHeaderGeometry(browser: any) {
  const result = await browser.evaluate(() => {
    const headers = ['Sender', 'Recipient'].map(title => {
      const card = Array.from(document.querySelectorAll<HTMLElement>('.kiriof-card')).find(card =>
        card.querySelector(':scope > [data-slot="card-header"] [data-slot="card-title"]')?.textContent?.trim() === title);
      if (!card) throw new Error('Missing card: ' + title);
      const header = card.querySelector<HTMLElement>(':scope > [data-slot="card-header"]')!;
      const rect = header.getBoundingClientRect();
      const style = getComputedStyle(header);
      const action = header.querySelector<HTMLElement>('[data-slot="card-action"]');
      const button = action?.querySelector<HTMLElement>('a,button');
      const buttonRect = button?.getBoundingClientRect();
      return { height: rect.height, minHeight: style.minHeight, boxSizing: style.boxSizing,
        hasAction: !!action, contact: button?.textContent?.trim() || '', href: button?.getAttribute('href') || '',
        top: rect.top, bottom: rect.bottom, center: (rect.top + rect.bottom) / 2,
        button: buttonRect ? { top: buttonRect.top, bottom: buttonRect.bottom, center: (buttonRect.top + buttonRect.bottom) / 2 } : null };
    });
    return { sender: headers[0], recipient: headers[1] };
  });
  for (const header of [result.sender, result.recipient]) {
    expect(header.height).toBe(58);
    expect(header.minHeight).toBe('58px');
    expect(header.boxSizing).toBe('border-box');
  }
  expect(result.sender.height).toBe(result.recipient.height);
  expect(result.sender.hasAction).toBe(false);
  expect(result.recipient.hasAction).toBe(true);
  expect(result.recipient.contact).toBe('Contact Customer');
  expect(result.recipient.href).toBe('tel:08123456789');
  expect(result.recipient.button).not.toBeNull();
  expect(result.recipient.button!.top).toBeGreaterThanOrEqual(result.recipient.top);
  expect(result.recipient.button!.bottom).toBeLessThanOrEqual(result.recipient.bottom);
  expect(Math.abs(result.recipient.button!.center - result.recipient.center)).toBeLessThanOrEqual(1);
}

function data(delivery: Delivery, scenario: Scenario) {
  const paymentId = paymentIdentifier(delivery, scenario);
  const row = {
    delivery_type: delivery, service: delivery === 'express' ? 'jne' : 'gosend', service_name: delivery === 'express' ? 'REG' : 'Instant', vehicle: 'motor',
    shipping_cost: 11000, discount_amount: scenario === 'discount' ? 2000 : 0,
    ...(scenario === 'missing' ? {} : delivery === 'express' ? { pickup_number: paymentId } : {
      instant_payment_id: paymentId, instant_payment_status: 'paid', instant_payment_method: 'qris',
    }),
  };
  const fees = scenario === 'missing' ? [] : [{ type: 'instant_admin_fee', total: 1000 }];
  // This is the actual controller/service/PHP template result, not reconstructed
  // metabox markup. Its native Woo fee collection is authoritative.
  const metabox = php('order-metabox-shipping-runtime.php', {
    row, wc_order: { subtotal: 20000, total: 32000, shipping: scenario === 'discount' ? 9000 : 11000, fees,
      needs_payment: false, meta: { _kiriof_instant_admin_fee: 9000 } },
    ...(delivery === 'express' && scenario !== 'missing' ? { carrier_payment: { pickup_number: paymentId, status: 'paid', method: 'qris' } } : {}),
  }) as { html: string; payment_lookup: string[] };
  // Exercise the production view-model with native fee items too. This existing
  // isolated fixture hardcodes Woo totals (62000/50000/12000) and has no Express
  // PaymentRepository. The typed override below is ONLY a rendering boundary:
  // it supplies the exact 32000/20000/11000 scenario, not a claim that PHP
  // calculated those totals or looked up an Express Payment in this fixture.
  const bootstrap = php('shipment-detail-amounts-runtime.php', {
    mode: 'detail', ...row, wc_order: { paid: true, fees, meta: { _kiriof_instant_admin_fee: 9000 } },
  }) as TransactionDetailBootstrap;
  expect(bootstrap.transaction.shipment.costs.adminFee).toBe(scenario === 'missing' ? 0 : 1000);
  expect(metabox.payment_lookup).toEqual(delivery === 'express' && scenario !== 'missing' ? [paymentId] : []);
  // Derive payment evidence from the independently rendered production metabox.
  const payment = ['Payment method', 'Payment status', 'Payment ID'].map(label => {
    const match = metabox.html.match(new RegExp(`<td>${label}</td>\\s*<td>(.*?)</td>`, 's'));
    if (!match) throw new Error('Missing real PHP payment row: ' + label);
    return match[1].trim() === '—' ? '' : match[1].trim();
  });
  bootstrap.toolbar.logoUrl = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg"/%3E';
  // Saved coordinates exercise the actual bundled Leaflet, not a map-library stub
  // or a driving/live route. Tile requests alone are intercepted below.
  bootstrap.map = { enabled: delivery === 'instant', tiles: 'https://fixture.test/tiles/{z}/{x}/{y}.svg', attribution: 'Fixture tiles' };
  if (delivery === 'instant') bootstrap.transaction.shipment.routeMap = {
    origin: { latitude: -6.2, longitude: 106.8 }, destination: { latitude: -6.22, longitude: 106.84 },
    points: [], mode: 'illustration',
  };
  bootstrap.transaction.supportsLiveTracking = false;
  bootstrap.transaction.shipment.printUrl = '';
  bootstrap.transaction.shipment.paymentMethod = payment[0];
  bootstrap.transaction.shipment.paymentStatus = payment[1];
  bootstrap.transaction.shipment.paymentId = payment[2];
  bootstrap.transaction.shipment.buyerPaymentStatus = 'Paid';
  bootstrap.transaction.shipment.costs = {
    subtotal: 20000, orderTotal: 32000, totalShipping: 11000, actualShipping: 11000,
    shipping: scenario === 'discount' ? 9000 : 11000, shippingDiscount: scenario === 'discount' ? 2000 : 0,
    insurance: 0, codFee: 0, itemDiscount: 0, total: 12000,
    ...(scenario === 'missing' ? {} : { adminFee: bootstrap.transaction.shipment.costs.adminFee }),
  };
  return { bootstrap, metabox, paymentId };
}
const cssNames = ['kiriminaja-kiriof-var.css', 'kiriminaja-kiriof-component.css', 'kiriminaja-admin-workspace.css'];
// PHP emits these styles outside the Svelte payload; preserve that production
// style block rather than inventing replacement metabox layout CSS.
const metaboxStyles = readFileSync(root + 'templates/order/metabox-shipping.php', 'utf8').match(/<style>([\s\S]*?)<\/style>/)![1];
function html(delivery: Delivery, scenario: Scenario, screen: 'detail' | 'metabox') {
  const { bootstrap, metabox } = data(delivery, scenario);
  // Rendering-only contact fixture: leave PHP/source money and parity data intact.
  if (screen === 'detail') {
    bootstrap.transaction.recipient.phone = '08123456789';
    bootstrap.transaction.actions.changeOrigin = false;
  }
  const content = screen === 'detail'
    ? `<div class="wrap kj-wrap kiriof-workspace-shell" data-kiriof-transaction-detail-page><div data-kiriof-transaction-detail-root aria-busy="true"></div><script type="application/json" data-kiriof-transaction-detail-payload>${json(bootstrap)}</script></div>`
    : `<div class="wrap"><div id="kiriminaja-shipping-info"><div data-kiriof-order-metabox-root></div><script type="application/json" data-kiriof-order-metabox-payload>${json({ html: metabox.html })}</script></div></div>`;
  return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;background:#f0f0f1;font:14px Arial,sans-serif}.wrap{margin:20px}input,button{font:inherit}</style>${screen === 'detail' && delivery === 'instant' ? '<link rel="stylesheet" href="/assets/lib/leaflet/leaflet.css"><script src="/assets/lib/leaflet/leaflet.js"></script>' : ''}${cssNames.map(name => `<link rel="stylesheet" href="/assets/admin/dist/${name}">`).join('')}<style>${metaboxStyles}</style></head><body>${content}<script type="module" src="/assets/admin/dist/kiriminaja-${screen === 'detail' ? 'admin-workspace' : 'order-metabox'}.js"></script></body></html>`;
}
async function fixture(browser: any) {
  const unexpected: string[] = [];
  await browser.route('**/*', (route: any) => {
    const url = new URL(route.request.url);
    if (url.origin === 'https://fixture.test' && route.request.method === 'GET') {
      const delivery = url.searchParams.get('delivery_type');
      const scenario = url.searchParams.get('scenario');
      const screen = url.pathname === '/wp-admin/admin.php' && url.searchParams.get('page') === 'kiriminaja-transaction-detail' && url.searchParams.get('id') === '10' ? 'detail'
        : url.pathname === '/wp-admin/post.php' && url.searchParams.get('post') === '10' && url.searchParams.get('action') === 'edit' ? 'metabox' : null;
      if (screen && (delivery === 'express' || delivery === 'instant') && ['simple', 'discount', 'missing'].includes(scenario || '')) {
        return route.fulfill({ contentType: 'text/html', body: html(delivery, scenario as Scenario, screen) });
      }
      if (/^\/tiles\/\d+\/\d+\/\d+\.svg$/.test(url.pathname)) {
        return route.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#e5e7eb"/></svg>' });
      }
      if (/^\/assets\/lib\/leaflet\/leaflet\.(?:js|css)$/.test(url.pathname)) {
        return route.fulfill({ contentType: url.pathname.endsWith('.css') ? 'text/css' : 'text/javascript', body: readFileSync(root + url.pathname.slice(1), 'utf8') });
      }
      if (/^\/assets\/admin\/dist\/(?:assets\/)?[\w.-]+\.(?:js|css)$/.test(url.pathname)) {
        return route.fulfill({ contentType: url.pathname.endsWith('.css') ? 'text/css' : 'text/javascript', body: readFileSync(root + url.pathname.slice(1), 'utf8') });
      }
      if (/^\/assets\/buyer\/img\/couriers\/(?:jne|gosend)\.png$/.test(url.pathname)) {
        return route.fulfill({ contentType: 'image/png', path: root + url.pathname.slice(1) });
      }
    }
    if (url.pathname !== '/favicon.ico') unexpected.push(`${route.request.method} ${url.href}`);
    return route.fulfill({ status: 409, body: 'No live API or admin actions permitted' });
  });
  return unexpected;
}
async function rows(browser: any, screen: 'detail' | 'metabox'): Promise<string[][]> {
  return browser.evaluate((isDetail: boolean) => Array.from(document.querySelectorAll(isDetail ? '.kiriof-shipment-card dl > div' : '#kiriminaja-shipping-info tbody > tr')).map(row => Array.from(row.querySelectorAll(isDetail ? 'dt,dd' : 'td')).map(cell => cell.textContent?.trim() || '')), screen === 'detail');
}
async function geometry(browser: any, selector: string, width: number) {
  const result = await browser.evaluate((selector: string) => {
    const card = document.querySelector<HTMLElement>(selector)!;
    const rect = card.getBoundingClientRect();
    return { left: rect.left, right: rect.right, overflow: card.scrollWidth - card.clientWidth,
      pageOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      cells: Array.from(card.querySelectorAll('dt,dd,td')).map(cell => { const r = cell.getBoundingClientRect(); return { left: r.left, right: r.right }; }),
      images: Array.from(card.querySelectorAll<HTMLImageElement>('img')).map(img => img.complete && img.naturalWidth > 0) };
  }, selector);
  expect(result.left).toBeGreaterThanOrEqual(0);
  expect(result.right).toBeLessThanOrEqual(width);
  expect(result.overflow).toBeLessThanOrEqual(1);
  expect(result.pageOverflow).toBeLessThanOrEqual(1);
  for (const cell of result.cells) { expect(cell.left).toBeGreaterThanOrEqual(result.left); expect(cell.right).toBeLessThanOrEqual(result.right + 1); }
  for (const loaded of result.images) expect(loaded).toBe(true);
}

async function compactRouteGeometry(browser: any, width: number) {
  const region = statusSelector + ' [role="region"]';
  await expect(browser.locator(region)).toBeVisible();
  await expect.poll(() => browser.evaluate(() => {
    const map = document.querySelector('.kiriof-detail-status-card .leaflet-container');
    return !!map && map.querySelectorAll('.leaflet-tile-loaded').length > 0 && map.querySelectorAll('.leaflet-overlay-pane path').length === 3 && Array.from(map.querySelectorAll<HTMLImageElement>('img')).every(img => img.complete && img.naturalWidth > 0);
  })).toBe(true);
  const result = await browser.evaluate(() => {
    const card = document.querySelector('.kiriof-detail-status-card')!;
    const region = card.querySelector<HTMLElement>('[role="region"]')!;
    const section = region.parentElement!;
    const header = section.querySelector<HTMLElement>('header')!;
    const content = card.querySelector<HTMLElement>('[data-slot="card-content"]')!;
    const date = content.firstElementChild!;
    const hidden = header.getBoundingClientRect();
    const rect = region.getBoundingClientRect();
    return {
      embedded: section.parentElement === card, nestedCards: card.querySelectorAll('[data-slot="card"]').length,
      topCards: card.parentElement!.querySelectorAll(':scope > [data-slot="card"]').length,
      previous: section.previousElementSibling === content,
      belowDate: rect.top >= date.getBoundingClientRect().bottom,
      height: rect.height, hiddenWidth: hidden.width, hiddenHeight: hidden.height,
      clipped: getComputedStyle(header).clip !== 'auto' || getComputedStyle(header).clipPath !== 'none', overflow: getComputedStyle(header).overflow,
      title: header.querySelector('h2')?.textContent, label: region.getAttribute('aria-label'),
      caption: header.querySelector('p')?.textContent, ariaHidden: header.getAttribute('aria-hidden'), display: getComputedStyle(header).display, visibility: getComputedStyle(header).visibility,
      error: section.querySelector('[role="status"]')?.textContent || '',
      paths: Array.from(region.querySelectorAll<SVGPathElement>('.leaflet-overlay-pane path')).map(path => ({ d: path.getAttribute('d'), dash: path.getAttribute('stroke-dasharray') })),
    };
  });
  expect(result.embedded).toBe(true);
  expect(result.nestedCards).toBe(0);
  // Status and products are the only top-level main cards: no Delivery Route card.
  expect(result.topCards).toBe(2);
  expect(result.previous).toBe(true);
  expect(result.belowDate).toBe(true);
  expect(result.height).toBeGreaterThanOrEqual(240);
  expect(result.height).toBeLessThanOrEqual(320);
  // sr-only remains available to assistive technology; generic visibility matchers
  // do not necessarily classify a clipped 1px element as visually hidden.
  expect(result.hiddenWidth).toBeLessThanOrEqual(1);
  expect(result.hiddenHeight).toBeLessThanOrEqual(1);
  expect(result.clipped).toBe(true);
  expect(result.overflow).toBe('hidden');
  expect(result.ariaHidden).not.toBe('true');
  expect(result.display).not.toBe('none');
  expect(result.visibility).toBe('visible');
  expect(result.title).toBe(result.label);
  expect(result.title?.length).toBeGreaterThan(0);
  expect(result.caption).toMatch(/not a driving route or live tracking/i);
  expect(result.error).toBe('');
  expect(result.paths[0].dash).toBe('6 8');
  for (const path of result.paths) expect(path.d).toMatch(/M/);
  // Leaflet intentionally keeps off-screen tile/pan layers for dragging. Verify
  // the viewport clips those layers instead of treating its scrollWidth as UI overflow.
  const viewport = await browser.evaluate(() => {
    const node = document.querySelector<HTMLElement>('.kiriof-detail-status-card [role="region"]')!;
    const rect = node.getBoundingClientRect();
    return { left: rect.left, right: rect.right, overflow: getComputedStyle(node).overflow };
  });
  expect(viewport.left).toBeGreaterThanOrEqual(0);
  expect(viewport.right).toBeLessThanOrEqual(width);
  expect(viewport.overflow).toBe('hidden');
}

for (const width of [1440, 390]) for (const delivery of ['express', 'instant'] as const) {
  test(`Shipment production entry/PHP parity: ${delivery} at ${width}px`, async ({ app, browser }) => {
    await browser.setViewport({ width, height: 1000 });
    const unexpected = await fixture(browser);
    for (const scenario of ['simple', 'discount', 'missing'] as const) {
      const snapshots: string[][][] = [];
      for (const screen of ['metabox', 'detail'] as const) {
        const query = `delivery_type=${delivery}&scenario=${scenario}`;
        await app.open(screen === 'detail' ? `/wp-admin/admin.php?page=kiriminaja-transaction-detail&id=10&${query}` : `/wp-admin/post.php?post=10&action=edit&${query}`);
        const selector = screen === 'detail' ? detailSelector : metaboxSelector;
        await expect(browser.locator(selector)).toBeVisible();
        await expect.poll(async () => (await rows(browser, screen)).find(([label]) => label === 'Total')?.[1]).toBe('Rp32.000');
        const values = await rows(browser, screen);
        const labels = values.map(([label]) => label);
        const get = (label: string) => values.filter(([name]) => name === label);
        expect(get('Shipping')).toHaveLength(1);
        // PHP names its pre-discount row Shipping and its buyer charge
        // Discounted Shipping; Svelte explicitly names Actual Shipping.
        if (screen === 'metabox' && scenario === 'discount') {
          expect(get('Shipping')[0][1]).toContain('Rp11.000');
          expect(get('Discounted Shipping')).toEqual([['Discounted Shipping', 'Rp9.000']]);
        } else {
          expect(get('Shipping')).toEqual([['Shipping', scenario === 'discount' ? 'Rp9.000' : 'Rp11.000']]);
        }
        expect(get('Total')).toEqual([['Total', 'Rp32.000']]);
        expect(get('Admin Fee')).toEqual(scenario === 'missing' ? [] : [['Admin Fee', 'Rp1.000']]);
        if (scenario === 'discount') {
          expect(get('Total Shipping')).toHaveLength(1);
          if (screen === 'detail') expect(get('Actual Shipping')).toEqual([['Actual Shipping', 'Rp11.000']]);
          expect(values.some(([label, value]) => label.startsWith('Shipping Discount') && value.includes('2.000'))).toBe(true);
        } else {
          expect(labels).not.toContain('Actual Shipping');
          expect(labels).not.toContain('Total Shipping');
        }
        if (scenario !== 'missing') {
          expect(labels.indexOf('Admin Fee')).toBeGreaterThan(labels.indexOf('Shipping'));
          expect(labels.indexOf('Admin Fee')).toBeLessThan(labels.indexOf('Total'));
        }
        const paymentRows: string[][] = screen === 'detail'
          ? await browser.evaluate(() => Array.from(document.querySelectorAll('.kiriof-detail-status-card [data-slot="card-header"] .kiriof-detail-carrier-payment > div')).map(row => Array.from(row.querySelectorAll('dt,dd')).map(cell => cell.textContent?.trim() || '')))
          : values.filter(([label]) => paymentLabels.includes(label));
        // Compare PHP and detail by label: the compact header puts ID first.
        snapshots.push([...paymentRows].sort(([a], [b]) => a.localeCompare(b)));
        if (screen === 'detail') {
          expect(values.filter(([label]) => paymentLabels.includes(label) || label === 'Buyer Payment Status')).toEqual([]);
          const badge = browser.locator(statusSelector + ' [data-slot="card-header"] .kiriof-detail-buyer-payment');
          await expect(badge).toContainText('Paid');
          const context = await browser.evaluate(() => {
            const badge = document.querySelector('.kiriof-detail-buyer-payment')!;
            return [badge.getAttribute('title'), badge.getAttribute('aria-label')];
          });
          expect(context).toEqual(['Buyer Payment Status', 'Buyer Payment Status: Paid']);
        } else expect(get('Buyer Payment Status')).toEqual([['Buyer Payment Status', 'Paid']]);
        const paymentStructure = await browser.evaluate((isDetail: boolean) => {
          const root = document.querySelector(isDetail ? '.kiriof-detail-status-card [data-slot="card-header"] .kiriof-detail-carrier-payment' : '#kiriminaja-shipping-info')!;
          return Array.from(root.querySelectorAll(isDetail ? ':scope > div' : 'tbody > tr'))
            .filter(row => ['Payment method', 'Payment status', 'Payment ID'].includes(row.firstElementChild?.textContent?.trim() || ''))
            .map(row => ({ tag: row.tagName, children: Array.from(row.children).map(cell => cell.tagName), display: getComputedStyle(row).display }));
        }, screen === 'detail');
        expect(paymentStructure).toHaveLength(3);
        expect(paymentStructure[1]).toEqual(paymentStructure[0]);
        expect(paymentStructure[2]).toEqual(paymentStructure[0]);
        expect(paymentStructure[0].children).toEqual(screen === 'detail' ? ['DT', 'DD'] : ['TD', 'TD']);
        const expectedPayment = [['Payment method', scenario === 'missing' ? '—' : 'qris'], ['Payment status', scenario === 'missing' ? '—' : 'paid'], ['Payment ID', scenario === 'missing' ? '—' : paymentIdentifier(delivery, scenario)]];
        expect(paymentRows).toEqual(screen === 'detail' ? [expectedPayment[2], expectedPayment[0], expectedPayment[1]] : expectedPayment);
        if (scenario === 'missing') {
          const headerText = await browser.evaluate((isDetail: boolean) => document.querySelector(isDetail ? '.kiriof-shipment-card [data-slot="card-header"]' : '#kiriminaja-shipping-info .kiriof-mb-header')?.textContent || '', screen === 'detail');
          expect(/\bpaid\b/i.test(headerText)).toBe(false);
        }
        if (delivery === 'instant') {
          const header = screen === 'detail' ? selector + ' [data-slot="card-header"]' : selector + ' .kiriof-mb-header';
          await expect(browser.locator(header)).toContainText('Motor');
          expect(labels).not.toContain('Vehicle');
          expect(labels.some(label => /COD/i.test(label))).toBe(false);
          await expect(browser.locator(header)).not.toContainText('COD');
        }
        await expect.poll(() => browser.evaluate((selector: string) => Array.from(document.querySelectorAll<HTMLImageElement>(selector + ' img')).every(img => img.complete && img.naturalWidth > 0), selector)).toBe(true);
        await geometry(browser, selector, width);
        if (screen === 'detail') {
          await senderRecipientHeaderGeometry(browser);
          await geometry(browser, statusSelector + ' [data-slot="card-header"]', width);
          if (width === 390 && scenario === 'discount') {
            const wrappedHeader = await browser.evaluate(() => {
              const header = document.querySelector<HTMLElement>('.kiriof-detail-status-card > [data-slot="card-header"]')!;
              return { height: header.getBoundingClientRect().height, overflow: header.scrollHeight - header.clientHeight };
            });
            expect(wrappedHeader.height).toBeGreaterThan(58);
            expect(wrappedHeader.overflow).toBeLessThanOrEqual(1);
          }
          if (delivery === 'instant') await compactRouteGeometry(browser, width);
          if (scenario === 'discount') await app.screenshot(`compact-shipment-${delivery}-${width}`);
        }
      }
      expect(snapshots[0]).toEqual(snapshots[1]);
    }
    expect(unexpected).toEqual([]);
  });
}
