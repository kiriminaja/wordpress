import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const read = (path: string) => readFileSync(root + path, 'utf8');
const express = 'kiriminaja-official:1:jne:reg';
const instant = 'kiriminaja-instant:1:gosend:instant';
const section = '.kiriof-classic-shipping-options';
const wrap = '.kiriof-classic-shipping-method-select-wrap';
const render = (fixture: string, input: unknown) => JSON.parse(execFileSync('php', [root + 'tests/fixtures/' + fixture, JSON.stringify(input)], { encoding: 'utf8' })).html as string;
const review = () => render('classic-order-review-runtime.php', { scene: 'discounted' });
// All rate rows and monetary text come from production PHP templates, not JS reconstructions.
function fragment(generation: number, packages = 1, virtual = false) {
  const rows = Array.from({ length: packages }, (_, index) => render('classic-shipping-presentation-runtime.php', {
    index, chosen: instant, rates: [
      { id: express, label: `Refresh ${generation} JNE package ${index}`, cost: 15000 },
      { id: instant, label: `Refresh ${generation} GOSEND package ${index}`, cost: 20000 },
    ],
  })).join('');
  return review().replace(/<tr[^>]*class="woocommerce-shipping-totals[^"\n]*"[\s\S]*?<\/tr>/, virtual ? '' : rows);
}

function pageHTML(enhanced = true, mandatory = false, virtual = false) {
  const table = virtual ? fragment(0, 0, true) : review();
  const scripts = [
    readFileSync(new URL('../node_modules/jquery/dist/jquery.min.js', import.meta.url), 'utf8'),
    ...(enhanced ? [read('assets/lib/choices/choices.min.js')] : []),
    `window.kiriofBillingAddressConfig = { i18n: { shippingOptions: 'Shipping options' }, courierLogos: { jne: '/assets/wp/img/couriers/jne.png', gosend: '/assets/wp/img/couriers/gosend.png', lion: '/assets/wp/img/couriers/lion.png' } };
     window.kiriofClassicCheckoutConfig = { enabled: true, ownsDistrict: false };
     window.__observerCalls = 0; const RealObserver = window.MutationObserver;
     window.MutationObserver = class extends RealObserver { constructor(callback) { super((records, observer) => { window.__observerCalls++; callback(records, observer); }); } };
     window.__nativeChanges = []; window.__insurancePayloads = []; window.__networkCalls = 0;
     const forbidden = () => { window.__networkCalls++; throw new Error('Live network forbidden'); };
     window.fetch = forbidden; XMLHttpRequest.prototype.open = forbidden; navigator.sendBeacon = forbidden;
     window.__originalInsurance = document.getElementById('kiriof-classic-insurance-field');
     window.__originalCheckbox = document.getElementById('kiriof_insurance');
     window.__originalHidden = window.__originalInsurance.querySelector('input[type=hidden]');
     window.__payment = document.getElementById('payment');
     window.__summary = document.getElementById('order_review');
     window.__summaryTotal = document.querySelector('.order-total').outerHTML;
     window.__replaceReview = function(html, notify) {
       // Native Woo-like fragment replacement: replace ONLY the complete table, never the card/payment.
       document.querySelector('table.kiriof-classic-order-review').outerHTML = html;
       if (notify) { for (let i = 0; i < 3; i++) jQuery(document.body).trigger('updated_checkout'); }
     };
     jQuery(document).on('change.fixtureWoo', 'input.shipping_method', function() {
       window.__nativeChanges.push({ value: this.value, name: this.name, checked: this.checked });
     });
     jQuery(document).on('change.fixtureInsurance', '#kiriof_insurance', function() {
       window.__insurancePayloads.push(jQuery('form.checkout').serializeArray().filter(x => x.name === 'kiriof_insurance'));
       window.__pendingInsurance = true;
       setTimeout(() => { if (window.__slowFragment) window.__replaceReview(window.__slowFragment, true); window.__pendingInsurance = false; }, 200);
     });`,
    read('assets/wp/js/checkout/choices-controls.js'),
    read('assets/wp/js/checkout/state.js'),
    read('assets/wp/js/checkout/shipping-payment.js'),
    read('assets/wp/js/checkout/shipping-options.js'),
    'kiriofScheduleClassicShippingMethodSelectInit();',
  ];
  return `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">
    <style>body{margin:12px;font-family:sans-serif}table{width:100%;table-layout:fixed}th,td{padding:8px;text-align:left}</style>
    <style>${read('assets/lib/choices/choices.min.css')}${read('assets/wp/css/kiriof-classic-choices.css')}${read('assets/wp/css/kj-wp-style.css')}</style>
    </head><body><div class="woocommerce woocommerce-checkout"><form class="checkout woocommerce-checkout">
    <div id="insurance-anchor"><p id="kiriof-classic-insurance-field"><input type="hidden" name="kiriof_insurance" value="${mandatory ? '1' : '0'}"><label for="kiriof_insurance"><input id="kiriof_insurance" type="checkbox" name="kiriof_insurance" value="1" ${mandatory ? 'checked disabled' : ''}>Shipping insurance</label></p></div>
    <div id="order_review" class="summary-payment-card">${table}<div id="payment"><label><input type="radio" name="payment_method" value="bacs" checked>Bank transfer</label></div></div>
    </form></div>${scripts.map(script => `<script>${script.replace(/<\/script/gi, '<\\/script')}</script>`).join('')}</body></html>`;
}

async function openFixture(app: any, browser: any, enhanced = true, mandatory = false, virtual = false) {
  const unexpected: string[] = [];
  await browser.route('**/*', (route: any) => {
    const url = new URL(route.request.url);
    if (url.pathname === '/shipping-options') return route.fulfill({ contentType: 'text/html', body: pageHTML(enhanced, mandatory, virtual) });
    if (/^\/assets\/wp\/img\/couriers\/(jne|gosend|lion)\.png$/.test(url.pathname)) return route.fulfill({ contentType: 'image/png', path: root + url.pathname });
    if (url.pathname !== '/favicon.ico') unexpected.push(url.href);
    return route.fulfill({ status: 409, body: 'No live requests permitted' });
  });
  await app.open('/shipping-options');
  return unexpected;
}

async function assertInsurance(browser: any, checked: boolean, mandatory = false) {
  expect(await browser.evaluate(() => {
    const w = window as any;
    const checkbox = document.querySelector<HTMLInputElement>('#kiriof_insurance')!;
    const hidden = document.querySelector<HTMLInputElement>('#kiriof-classic-insurance-field input[type=hidden]')!;
    return { field: document.querySelector('#kiriof-classic-insurance-field') === w.__originalInsurance, checkbox: checkbox === w.__originalCheckbox,
      hidden: hidden === w.__originalHidden, value: checkbox.value, hiddenValue: hidden.value, checked: checkbox.checked, disabled: checkbox.disabled,
      fields: document.querySelectorAll('#kiriof-classic-insurance-field').length, inputs: document.querySelectorAll('[name="kiriof_insurance"]').length };
  })).toEqual({ field: true, checkbox: true, hidden: true, value: '1', hiddenValue: mandatory ? '1' : '0', checked, disabled: mandatory, fields: 1, inputs: 2 });
}

async function assertIdle(browser: any) {
  // Count callbacks from REAL native MutationObservers (both production modules), not mocked refresh calls.
  const counts = await browser.evaluate(async () => {
    await new Promise(resolve => setTimeout(resolve, 900));
    const before = (window as any).__observerCalls;
    await new Promise(resolve => setTimeout(resolve, 250));
    return { before, after: (window as any).__observerCalls, network: (window as any).__networkCalls };
  });
  expect(counts.after).toBe(counts.before);
  expect(counts.after < 100).toBe(true);
  expect(counts.network).toBe(0);
}

for (const width of [1200, 390]) {
  test(`shipping section moves native controls before summary and survives whole-table AJAX at ${width}px`, async ({ app, browser }) => {
    await browser.setViewport({ width, height: 1000 });
    const unexpected = await openFixture(app, browser);
    await expect(browser.locator(section)).toBeVisible();
    await expect(browser.locator(`${section} h3`)).toContainText('Shipping options');
    await expect(browser.locator(`${section} ${wrap} .choices__inner`)).toBeVisible();
    await expect(browser.locator(`${section} .choices__list--single`)).toContainText('Rp 6,000');
    await expect(browser.locator('.cart-subtotal')).toContainText('Rp 120,000');
    await expect(browser.locator('.order-total')).toContainText('Rp 126,000');
    expect(await browser.evaluate(() => {
      const shipping = document.querySelector('.kiriof-classic-shipping-options')!;
      const summary = document.querySelector('#order_review')!;
      const insurance = document.querySelector('#kiriof-classic-insurance-field')!;
      const packages = shipping.querySelector('.kiriof-classic-shipping-packages')!;
      const row = document.querySelector<HTMLTableRowElement>('.woocommerce-shipping-totals')!;
      const rect = shipping.getBoundingClientRect();
      return { outside: !shipping.closest('#order_review, table, .summary-payment-card'), before: summary.previousElementSibling === shipping,
        insuranceAfter: !!(packages.compareDocumentPosition(insurance) & Node.DOCUMENT_POSITION_FOLLOWING), inputsInTable: summary.querySelectorAll('input.shipping_method, [name="kiriof_insurance"]').length,
        hidden: row.hidden && row.classList.contains('kiriof-shipping-row-relocated') && row.getBoundingClientRect().height === 0,
        rates: shipping.querySelectorAll('input.shipping_method').length, within: rect.left >= 0 && rect.right <= innerWidth };
    })).toEqual({ outside: true, before: true, insuranceAfter: true, inputsInTable: 0, hidden: true, rates: 5, within: true });
    await assertInsurance(browser, false);
    await browser.locator(`${section} ${wrap} .choices__inner`).click();
    await browser.locator(`${section} .choices__list--dropdown [data-choice-selectable]`).filter({ hasText: 'Fixture GOSEND Instant' }).click();
    expect(await browser.evaluate(() => ({ checked: Array.from(document.querySelectorAll<HTMLInputElement>('input.shipping_method:checked')).map(x => x.value), changes: (window as any).__nativeChanges }))).toEqual({ checked: [instant], changes: [{ value: instant, name: 'shipping_method[0]', checked: true }] });

    for (const [generation, packages] of [[1, 2], [2, 1]]) {
      await browser.evaluate(({ html, notify }: any) => {
        (window as any).__oldRates = Array.from(document.querySelectorAll('input.shipping_method, .choices'));
        (window as any).__replaceReview(html, notify);
      }, { html: fragment(generation, packages), notify: generation === 2 });
      // First replacement deliberately has NO updated_checkout: the subtree observer must handle it.
      await expect.poll(() => browser.evaluate(() => document.querySelectorAll('.kiriof-classic-shipping-package').length)).toBe(packages);
      await expect.poll(() => browser.evaluate(() => Array.from(document.querySelectorAll('.choices__list--single')).map(x => x.textContent!.trim()))).toEqual(Array.from({ length: packages }, (_, index) => `Refresh ${generation} GOSEND package ${index}Rp 20000`));
      expect(await browser.evaluate(() => ({ stale: (window as any).__oldRates.some((x: Element) => x.isConnected), sections: document.querySelectorAll('.kiriof-classic-shipping-options').length,
        choices: document.querySelectorAll('.kiriof-classic-shipping-package .choices').length, rates: document.querySelectorAll('input.shipping_method').length,
        payment: document.querySelector('#payment') === (window as any).__payment, summary: document.querySelector('#order_review') === (window as any).__summary,
        total: document.querySelector('.order-total')!.outerHTML === (window as any).__summaryTotal, changes: (window as any).__nativeChanges.length,
        checked: Array.from(document.querySelectorAll<HTMLInputElement>('input.shipping_method:checked')).map(x => x.value) }))).toEqual({ stale: false, sections: 1, choices: packages, rates: packages * 2, payment: true, summary: true, total: true, changes: 1, checked: Array(packages).fill(instant) });
      await assertInsurance(browser, false);
    }
    await browser.evaluate((html: string) => { (window as any).__slowFragment = html; }, fragment(3));
    await browser.locator('#kiriof_insurance').click();
    await assertInsurance(browser, true);
    expect(await browser.evaluate(() => ({ pending: (window as any).__pendingInsurance, payloads: (window as any).__insurancePayloads }))).toEqual({ pending: true, payloads: [[{ name: 'kiriof_insurance', value: '0' }, { name: 'kiriof_insurance', value: '1' }]] });
    await expect(browser.locator(`${section} .choices__list--single`)).toContainText('Refresh 3 GOSEND');
    await assertInsurance(browser, true);
    await browser.locator('#kiriof_insurance').click();
    await expect.poll(() => browser.evaluate(() => (window as any).__pendingInsurance)).toBe(false);
    await assertInsurance(browser, false);
    expect(await browser.evaluate(() => (window as any).__insurancePayloads[1])).toEqual([{ name: 'kiriof_insurance', value: '0' }]);
    await assertIdle(browser);
    expect(unexpected).toEqual([]);
    await app.screenshot(`shipping-section-${width}`);
  });
}

test('mandatory insurance preserves disabled checked checkbox and hidden-one payload', async ({ app, browser }) => {
  const unexpected = await openFixture(app, browser, true, true);
  await expect(browser.locator(section)).toBeVisible();
  await assertInsurance(browser, true, true);
  await browser.evaluate((html: string) => (window as any).__replaceReview(html, true), fragment(1));
  await expect(browser.locator(`${section} .choices__list--single`)).toContainText('Refresh 1 GOSEND');
  await assertInsurance(browser, true, true);
  expect(await browser.evaluate(() => (window as any).jQuery('form.checkout').serializeArray().filter((x: any) => x.name === 'kiriof_insurance'))).toEqual([{ name: 'kiriof_insurance', value: '1' }]);
  await assertIdle(browser);
  expect(unexpected).toEqual([]);
});

for (const width of [1200, 390]) {
  test(`without Choices native shipping radios remain visible and usable at ${width}px`, async ({ app, browser }) => {
    await browser.setViewport({ width, height: 1000 });
    const unexpected = await openFixture(app, browser, false);
    await expect(browser.locator(section)).toBeVisible();
    await expect(browser.locator(`input.shipping_method[value="${instant}"]`)).toBeVisible();
    await browser.locator(`input.shipping_method[value="${instant}"]`).click();
    expect(await browser.evaluate(() => ({ wrappers: document.querySelectorAll('.choices').length, ready: document.querySelectorAll('.kiriof-classic-shipping-package.kiriof-shipping-methods-ready').length,
      checked: Array.from(document.querySelectorAll<HTMLInputElement>('input.shipping_method:checked')).map(x => x.value), changes: (window as any).__nativeChanges }))).toEqual({ wrappers: 0, ready: 0, checked: [instant], changes: [{ value: instant, name: 'shipping_method[0]', checked: true }] });
    await browser.evaluate((html: string) => (window as any).__replaceReview(html, true), fragment(1));
    await expect(browser.locator(`input.shipping_method[value="${instant}"]`)).toBeVisible();
    await expect(browser.locator(`${section} label[for="shipping_method_0_kiriminaja-instant-1-gosend-instant"]`)).toContainText('Refresh 1 GOSEND');
    expect(await browser.evaluate(() => ({ radios: document.querySelectorAll('input.shipping_method').length, wrappers: document.querySelectorAll('.choices').length }))).toEqual({ radios: 2, wrappers: 0 });
    await assertInsurance(browser, false);
    await assertIdle(browser);
    expect(unexpected).toEqual([]);
  });
}

test('virtual checkout has no shipping section and keeps insurance at its original valid anchor', async ({ app, browser }) => {
  const unexpected = await openFixture(app, browser, true, false, true);
  expect(await browser.evaluate(() => ({ sections: document.querySelectorAll('.kiriof-classic-shipping-options').length, rows: document.querySelectorAll('.woocommerce-shipping-totals').length,
    anchored: document.querySelector('#kiriof-classic-insurance-field')!.parentElement!.id, inputs: document.querySelectorAll('input.shipping_method').length }))).toEqual({ sections: 0, rows: 0, anchored: 'insurance-anchor', inputs: 0 });
  await assertInsurance(browser, false);
  await assertIdle(browser);
  expect(unexpected).toEqual([]);
});

test('removing all shipping packages restores moved insurance to the original anchor', async ({ app, browser }) => {
  const unexpected = await openFixture(app, browser);
  await expect(browser.locator(section)).toBeVisible();
  await browser.evaluate((html: string) => (window as any).__replaceReview(html, true), fragment(1, 0, true));
  await expect.poll(() => browser.evaluate(() => ({ sections: document.querySelectorAll('.kiriof-classic-shipping-options').length,
    anchored: document.querySelector('#kiriof-classic-insurance-field')?.parentElement?.id || null }))).toEqual({ sections: 0, anchored: 'insurance-anchor' });
  await assertInsurance(browser, false);
  await assertIdle(browser);
  expect(unexpected).toEqual([]);
});
