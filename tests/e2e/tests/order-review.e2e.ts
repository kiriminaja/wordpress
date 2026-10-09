import { script, classicCss } from '../fixtures/buyer-source';
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
    `window.kiriofBillingAddressConfig = { courierLogos: { jne: '/assets/buyer/img/couriers/jne.png', gosend: '/assets/buyer/img/couriers/gosend.png', lion: '/assets/buyer/img/couriers/lion.png' } };`,
    script('assets/buyer/js/kiriof-checkout-session.js'),
    ...(enhanced ? [script('assets/buyer/js/checkout/choices-controls.js')] : []),
    // Only bootstrap the production shipping module, not payment/booking ready hooks.
    'window.kiriofClassicCheckoutConfig = { enabled: true, ownsDistrict: false };',
    script('assets/buyer/js/checkout/state.js'),
    script('assets/buyer/js/checkout/shipping-payment.js'),
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
    <style>${enhanced ? classicCss() + read('assets/buyer/css/kiriof-classic-choices.css') : ''}</style>
    <style>${read('assets/buyer/css/kj-wp-style.css')}</style><style>${booster}</style></head>
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
        const pathname = new URL(url).pathname;
        if (/^\/assets\/buyer\/img\/couriers\/(jne|gosend|lion)\.png$/.test(pathname)) {
          return route.fulfill({ contentType: 'image/png', path: root + pathname });
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
          await expect(browser.locator('.kiriof-classic-shipping-method-select-wrap .kiriof-buyer-combobox-trigger')).toBeVisible();
          await browser.locator('.kiriof-classic-shipping-method-select-wrap .kiriof-buyer-combobox-trigger').click();
          await expect(browser.locator('.kiriof-buyer-combobox-content')).toContainText('Fixture JNE Express REG');
          await expect(browser.locator('.kiriof-buyer-combobox-content')).toContainText('Fixture GOSEND Instant');
          await browser.locator('.kiriof-buyer-combobox-option').filter({ hasText: 'Fixture GOSEND Instant' }).click();
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

for (const broken of [false, true]) {
  test(`courier Choices uses local logos, neutral unknowns and fresh Woo fragments (${broken ? 'broken artwork' : 'healthy artwork'})`, async ({ app, browser }) => {
    const requests: string[] = [];
    const images: string[] = [];
    await browser.route('**/*', route => {
      const url = new URL(route.request.url);
      if (url.pathname === '/order-review') return route.fulfill({ contentType: 'text/html', body: html('rates', true) });
      if (/^\/assets\/buyer\/img\/couriers\/(jne|gosend|lion)\.png$/.test(url.pathname)) {
        images.push(url.pathname);
        // An actual aborted image request must emit error, not merely hide via test CSS.
        if (broken && url.pathname.endsWith('/lion.png')) return route.abort();
        return route.fulfill({ contentType: 'image/png', path: root + url.pathname });
      }
      requests.push(url.href);
      return route.fulfill({ status: 409, body: 'Unexpected fixture request' });
    });
    await app.open('/order-review');
    const wrap = '.kiriof-classic-shipping-method-select-wrap';
    const dropdown = `.kiriof-buyer-combobox-content`;
    await expect(browser.locator(`${wrap} .kiriof-buyer-combobox-trigger`)).toBeVisible();
    // Verify actual template courier identities; names alone never authorize a logo.
    expect(await browser.evaluate(() => Array.from(document.querySelectorAll<HTMLOptionElement>('.kiriof-classic-shipping-method-select option')).filter(option => option.value).map(option => ({ value: option.value, courier: option.getAttribute('data-courier') || '' })))).toEqual([
      { value: express, courier: 'jne' }, { value: instant, courier: 'gosend' },
      { value: 'kiriminaja-official:1:lion:reg', courier: 'lion' },
      { value: 'flat_rate:9', courier: '' }, { value: 'kiriminaja-official:1:fixture:regular', courier: '' },
    ]);
    await expect.poll(() => browser.evaluate(() => {
      const image = document.querySelector<HTMLImageElement>('.kiriof-buyer-combobox-trigger img');
      return !!image && image.complete && image.naturalWidth > 0 && image.src.endsWith('/jne.png');
    })).toBe(true);
    await browser.locator(`${wrap} .kiriof-buyer-combobox-trigger`).click();
    for (const [label, file] of [['Fixture JNE Express REG', 'jne'], ['Fixture GOSEND Instant', 'gosend'], ['Fixture Lion Parcel REG', 'lion']]) {
      const row = browser.locator(`.kiriof-buyer-combobox-option`).filter({ hasText: label });
      await expect(row).toBeVisible();
      if (broken && file === 'lion') {
        await expect.poll(() => browser.evaluate(() => {
          const row = Array.from(document.querySelectorAll('.kiriof-buyer-combobox-option')).find(node => node.textContent!.includes('Fixture Lion Parcel REG'))!;
          return row.querySelector('img, svg, .kiriof-buyer-courier-logo') === null && row.textContent!.includes('Fixture Lion Parcel REG');
        })).toBe(true);
      } else {
        await expect.poll(() => browser.evaluate((file: string) => {
          const image = document.querySelector<HTMLImageElement>(`.kiriof-buyer-combobox-content img[src$="/${file}.png"]`);
          return !!image && image.complete && image.naturalWidth > 0;
        }, file)).toBe(true);
      }
    }
    for (const label of ['Third-party JNE delivery', 'Unknown plugin courier']) {
      await expect(browser.locator(`.kiriof-buyer-combobox-option`).filter({ hasText: label })).toBeVisible();
      expect(await browser.evaluate((label: string) => {
        const row = Array.from(document.querySelectorAll('.kiriof-buyer-combobox-option')).find(node => node.textContent!.includes(label))!;
        return { images: row.querySelectorAll('img').length, svg: row.querySelectorAll('svg').length, badges: row.querySelectorAll('.kiriof-buyer-courier-logo').length };
      }, label)).toEqual({ images: 0, svg: 0, badges: 0 });
    }
    // Capture a synthetic error on an existing image as well as the aborted-request case.
    if (!broken) {
      await browser.evaluate(() => {
        const item = Array.from(document.querySelectorAll('.kiriof-buyer-combobox-option')).find(node => node.textContent!.includes('Fixture GOSEND Instant'))!;
        item.querySelector('img')!.dispatchEvent(new Event('error'));
      });
      await expect.poll(() => browser.evaluate(() => {
        const item = Array.from(document.querySelectorAll('.kiriof-buyer-combobox-option')).find(node => node.textContent!.includes('Fixture GOSEND Instant'))!;
        return { images: item.querySelectorAll('img').length, svg: item.querySelectorAll('svg').length, badges: item.querySelectorAll('.kiriof-buyer-courier-logo').length, text: item.textContent!.includes('Fixture GOSEND Instant') };
      })).toEqual({ images: 0, svg: 0, badges: 0, text: true });
    }
    const selected = broken ? 'kiriminaja-official:1:lion:reg' : instant;
    const selectedLabel = broken ? 'Fixture Lion Parcel REG' : 'Fixture GOSEND Instant';
    await browser.locator(`.kiriof-buyer-combobox-option`).filter({ hasText: selectedLabel }).click();
    expect(await browser.evaluate(() => ({ checked: document.querySelector<HTMLInputElement>('input.shipping_method:checked')!.value, changes: (window as any).__nativeChanges }))).toEqual({ checked: selected, changes: [{ value: selected, name: 'shipping_method[0]', checked: true }] });
    await expect(browser.locator(`${wrap} .kiriof-buyer-combobox-trigger`)).toContainText(selectedLabel);
    // Unknown plugin labels remain selectable, without a guessed branded image.
    await browser.locator(`${wrap} .kiriof-buyer-combobox-trigger`).click();
    await browser.locator(`.kiriof-buyer-combobox-option`).filter({ hasText: 'Unknown plugin courier' }).click();
    await expect(browser.locator(`${wrap} .kiriof-buyer-combobox-trigger`)).toContainText('Unknown plugin courier');
    expect(await browser.evaluate(() => ({ value: document.querySelector<HTMLInputElement>('input.shipping_method:checked')!.value, images: document.querySelectorAll('.kiriof-buyer-combobox-trigger img').length }))).toEqual({ value: 'kiriminaja-official:1:fixture:regular', images: 0 });
    await browser.locator(`${wrap} .kiriof-buyer-combobox-trigger`).click();
    await browser.locator(`.kiriof-buyer-combobox-option`).filter({ hasText: selectedLabel }).click();
    const replacement = JSON.parse(execFileSync('php', [root + 'tests/fixtures/classic-shipping-presentation-runtime.php', JSON.stringify({ rates: [
      { id: express, label: 'Fragment JNE REG', cost: 15000 },
      { id: instant, label: 'Fragment GOSEND Instant', cost: 20000 },
      { id: 'kiriminaja-official:1:lion:reg', label: 'Fragment Lion REG', cost: 18000 },
    ], chosen: selected })], { encoding: 'utf8' })).html;
    await browser.evaluate((replacement: string) => {
      const oldSelect = document.querySelector('.kiriof-classic-shipping-method-select')!;
      (window as any).__oldCourier = { select: oldSelect, outer: oldSelect.closest('.kiriof-buyer-selector') };
      document.querySelector('.woocommerce-shipping-totals')!.outerHTML = replacement;
      (window as any).jQuery(document.body).trigger('updated_checkout');
    }, replacement);
    await expect(browser.locator(`${wrap} .kiriof-buyer-combobox-trigger`)).toBeVisible();
    await expect(browser.locator('.kiriof-classic-shipping-method-select')).toHaveValue(selected);
    await expect.poll(() => browser.evaluate(() => ({
      oldSelect: (window as any).__oldCourier.select.isConnected,
      oldOuter: (window as any).__oldCourier.outer.isConnected,
      wrappers: document.querySelectorAll('.kiriof-classic-shipping-method-select-wrap .kiriof-buyer-combobox').length,
      changes: (window as any).__nativeChanges.length,
    }))).toEqual({ oldSelect: false, oldOuter: false, wrappers: 1, changes: 3 });
    await browser.locator(`${wrap} .kiriof-buyer-combobox-trigger`).click();
    await browser.locator(`.kiriof-buyer-combobox-option`).filter({ hasText: 'Fragment JNE REG' }).click();
    expect(await browser.evaluate(() => (window as any).__nativeChanges)).toEqual([
      { value: selected, name: 'shipping_method[0]', checked: true },
      { value: 'kiriminaja-official:1:fixture:regular', name: 'shipping_method[0]', checked: true },
      { value: selected, name: 'shipping_method[0]', checked: true },
      { value: express, name: 'shipping_method[0]', checked: true },
    ]);
    expect(new Set(images)).toEqual(new Set(['jne', 'gosend', 'lion'].map(file => `/assets/buyer/img/couriers/${file}.png`)));
    expect(requests.filter(url => !url.endsWith('/favicon.ico'))).toEqual([]);
    expect(await browser.evaluate(() => (window as any).__networkCalls)).toBe(0);
    await app.screenshot(`courier-logos-${broken ? 'broken' : 'healthy'}`);
  });
}

// Read browser output from the actual PHP templates: JS must never reconstruct prices.
for (const width of [1200, 390]) {
  test(`discounted courier metadata and left-aligned bordered rows resist hostile theme at ${width}px`, async ({ app, browser }) => {
    await browser.setViewport({ width, height: 1100 });
    const requests: string[] = [];
    await browser.route('**/*', route => {
      const url = new URL(route.request.url);
      if (url.pathname === '/order-review') {
        const page = html('discounted', true).replace('</head>', `<style>
          /* Later theme rules must not center courier rows or stretch artwork. */
          .woocommerce #order_review td, .woocommerce #order_review .kiriof-buyer-combobox { text-align:center; }
          .woocommerce #order_review img { width:240px; height:120px; max-width:none; border:8px solid red; margin:20px; }
        </style></head>`);
        return route.fulfill({ contentType: 'text/html', body: page });
      }
      if (/^\/assets\/buyer\/img\/couriers\/(jne|gosend|lion)\.png$/.test(url.pathname)) {
        return route.fulfill({ contentType: 'image/png', path: root + url.pathname });
      }
      requests.push(url.href);
      return route.fulfill({ status: 409, body: 'Unexpected fixture request' });
    });
    await app.open('/order-review');
    const wrap = '.kiriof-classic-shipping-method-select-wrap';
    await expect(browser.locator(`${wrap} .kiriof-buyer-combobox-trigger`)).toBeVisible();
    const metadata = await browser.evaluate(() => {
      const option = document.querySelector<HTMLOptionElement>('.kiriof-classic-shipping-method-select option[value="kiriminaja-official:1:lion:reg"]')!;
      return { label: option.getAttribute('data-label'), price: option.getAttribute('data-price'), original: option.getAttribute('data-original-price'), savings: option.getAttribute('data-savings'), note: option.getAttribute('data-note') };
    });
    expect(metadata).toEqual({ label: 'Fixture Lion Parcel REG', price: 'Rp 6,000', original: 'Rp 12,000', savings: 'Save Rp 6,000', note: 'Fixture shipping coupon' });
    const assertRow = async (selector: string, expected: typeof metadata, branded = true) => {
      const row = browser.locator(selector);
      await expect(row).toBeVisible();
      await expect(browser.locator(`${selector} .kiriof-buyer-courier-label`)).toContainText(expected.label!);
      await expect(browser.locator(`${selector} .kiriof-buyer-courier-current`)).toContainText(expected.price!);
      if (branded) await expect.poll(() => browser.evaluate((selector: string) => {
        const image = document.querySelector<HTMLImageElement>(`${selector} img`);
        return !!image && image.complete && image.naturalWidth > 0;
      }, selector)).toBe(true);
      const result = await browser.evaluate((selector: string) => {
        const row = document.querySelector(selector)!;
        const label = row.querySelector('.kiriof-buyer-courier-label')!;
        const prices = row.querySelector('.kiriof-buyer-courier-prices')!;
        const badge = row.querySelector('.kiriof-buyer-courier-logo');
        const image = row.querySelector('img');
        const rect = row.getBoundingClientRect();
        const labelRect = label.getBoundingClientRect();
        const pricesRect = prices.getBoundingClientRect();
        const badgeRect = badge?.getBoundingClientRect();
        const imageRect = image?.getBoundingClientRect();
        const css = getComputedStyle(row);
        const badgeCss = badge && getComputedStyle(badge);
        return {
          label: label.textContent, price: row.querySelector('.kiriof-buyer-courier-current')?.textContent,
          original: row.querySelector('del')?.textContent || null, savings: row.querySelector('.kiriof-buyer-courier-savings')?.textContent || null,
          note: row.querySelector('.kiriof-buyer-courier-note')?.textContent || null,
          images: row.querySelectorAll('img').length, svg: row.querySelectorAll('svg').length, badges: row.querySelectorAll('.kiriof-buyer-courier-logo').length,
          noCopiedText: Array.from(row.childNodes).every(node => node.nodeType !== Node.TEXT_NODE || !node.textContent?.trim()),
          alignment: css.textAlign === 'start' ? 'left' : css.textAlign, justify: getComputedStyle(row.querySelector('.kiriof-buyer-courier')!).justifyContent === 'normal' ? 'flex-start' : getComputedStyle(row.querySelector('.kiriof-buyer-courier')!).justifyContent, copyAlignment: getComputedStyle(label).textAlign === 'start' ? 'left' : getComputedStyle(label).textAlign,
          below: pricesRect.top >= labelRect.bottom, sameLeft: Math.abs(pricesRect.left - labelRect.left) < 1,
          leftLogo: !badgeRect || (badgeRect.right <= labelRect.left && badgeRect.left - rect.left < 20),
          logoWidth: badgeRect?.width ?? null, logoHeight: badgeRect?.height ?? null, border: badgeCss?.borderTopWidth ?? null, borderStyle: badgeCss?.borderTopStyle ?? null,
          imageContained: !imageRect || !!badgeRect && imageRect.left >= badgeRect.left + 1 && imageRect.right <= badgeRect.right - 1 && imageRect.top >= badgeRect.top + 1 && imageRect.bottom <= badgeRect.bottom - 1,
          rowContained: rect.left >= 0 && rect.right <= innerWidth && labelRect.right <= rect.right + 1 && pricesRect.right <= rect.right + 1,
          overflow: document.documentElement.scrollWidth > innerWidth,
          labelOccurrences: row.textContent!.split(label.textContent!).length - 1,
        };
      }, selector);
      for (const key of ['label', 'price', 'original', 'savings', 'note'] as const) expect(result[key]).toBe(expected[key]);
      expect(result.images).toBe(branded ? 1 : 0);
      expect(result.badges).toBe(branded ? 1 : 0);
      expect(result.svg).toBe(0);
      expect(result.alignment).toBe('left');
      expect(result.copyAlignment).toBe('left');
      expect(result.justify).toBe('flex-start');
      expect(result.labelOccurrences).toBe(1);
      expect(Object.fromEntries(['noCopiedText', 'below', 'sameLeft', 'leftLogo', 'imageContained', 'rowContained'].map(key => [key, result[key]]))).toEqual({ noCopiedText: true, below: true, sameLeft: true, leftLogo: true, imageContained: true, rowContained: true });
      expect(result.overflow).toBe(false);
      if (branded) {
        expect(result.logoWidth).toBe(64); expect(result.logoHeight).toBe(44);
        expect(result.border).toBe('1px'); expect(result.borderStyle).toBe('solid');
      }
    };
    const selected = `${wrap} .kiriof-buyer-combobox-trigger`;
    const dropdown = `.kiriof-buyer-combobox-content`;
    await assertRow(selected, metadata);
    await browser.locator(`${wrap} .kiriof-buyer-combobox-trigger`).click();
    const lionRow = `.kiriof-buyer-combobox-option[data-value="kiriminaja-official:1:lion:reg"]`;
    await assertRow(lionRow, metadata);
    const unknownValue = 'kiriminaja-official:1:fixture:regular';
    const unknown = await browser.evaluate((value: string) => {
      const option = Array.from(document.querySelectorAll<HTMLOptionElement>('.kiriof-classic-shipping-method-select option')).find(option => option.value === value)!;
      return { label: option.getAttribute('data-label'), price: option.getAttribute('data-price'), original: option.getAttribute('data-original-price'), savings: option.getAttribute('data-savings'), note: option.getAttribute('data-note') };
    }, unknownValue);
    expect(unknown.label).toBe('Unknown plugin courier');
    const unknownRow = `.kiriof-buyer-combobox-option[data-value="${unknownValue}"]`;
    await assertRow(unknownRow, unknown, false);
    await browser.locator(unknownRow).click();
    await assertRow(selected, unknown, false);
    await browser.locator(`${wrap} .kiriof-buyer-combobox-trigger`).click();
    await browser.locator(lionRow).click();
    await assertRow(selected, metadata);
    // A broken selected logo removes its entire box, leaving authoritative copy intact.
    await browser.evaluate(() => document.querySelector('.kiriof-buyer-combobox-trigger img')!.dispatchEvent(new Event('error')));
    await assertRow(selected, metadata, false);
    expect(await browser.evaluate(() => (window as any).__networkCalls)).toBe(0);
    expect(requests.filter(url => !url.endsWith('/favicon.ico'))).toEqual([]);
    await app.screenshot(`courier-metadata-${width}`);
  });
}
