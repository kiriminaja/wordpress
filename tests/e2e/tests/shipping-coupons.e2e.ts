import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { script, classicCss } from '../fixtures/buyer-source';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const cartPath = '/wp-json/wc/store/v1/cart';
const ninja = 'kiriminaja-official_ninja_STANDARD';
const gosend = 'kiriminaja-instant:4:gosend:GO-INSTANT';
const scripts = (sources: string[]) => sources.map(s => `<script>${s.replace(/<\/script/gi, '<\\/script')}</script>`).join('');
const php = (name: string, ...args: string[]) => JSON.parse(execFileSync('php', [root + 'tests/fixtures/' + name, ...args], { encoding: 'utf8' }));
let reactBundle: string;
function reactSource() {
  if (reactBundle) return reactBundle;
  const dir = mkdtempSync(tmpdir() + '/kiriof-coupon-react-');
  try {
    writeFileSync(dir + '/entry.js', `import * as React from ${JSON.stringify(root + 'node_modules/react/index.js')};import {createRoot} from ${JSON.stringify(root + 'node_modules/react-dom/client.js')};window.__React=React;window.__createRoot=createRoot;`);
    execFileSync('bun', ['build', dir + '/entry.js', '--target=browser', '--outfile=' + dir + '/bundle.js']);
    return reactBundle = readFileSync(dir + '/bundle.js', 'utf8');
  } finally { rmSync(dir, { recursive: true, force: true }); }
}

// Only Woo/provider/session boundaries are doubled. Price transitions come from
// production PHP coupon + quote/calculation services; never calculate a discount in JS.
function response(delivery: string, state: string) {
  const money = (amount: number) => ({ currency_code: 'IDR', currency_symbol: 'Rp', currency_minor_unit: 0, total: String(amount), total_tax: '0' });
  let subtotal: number, shipping: number, fees: any[], rates: any[], destination: any, address: any;
  if (delivery === 'express') {
    const result = php('express-coupon-repricing-runtime.php');
    const row = result.states[state];
    expect(row.api_calls).toBe(1);
    const calc = result.calculation.data.calculation_result;
    subtotal = calc.cart_total_after_discount;
    shipping = row.cost;
    fees = [{ id: 'insurance', name: 'Shipping insurance', totals: money(calc.insurance_amt) }];
    rates = row.ids.map((id: string) => {
      const meta = row.meta[id];
      return { rate_id: id, method_id: 'kiriminaja-official', name: meta.label, selected: id === ninja, price: String(meta.cost), currency_minor_unit: 0,
        extensions: { 'kiriminaja-official': { ...meta, coupon_notice: meta.notice } } };
    });
    // Domestic saved pin is acknowledged before coupon requests, not changed by them.
    destination = { district_id: '42', district_label: 'Jakarta District', postcode: '12345', country: 'ID', address_type: 'shipping', version: 2, destination_latitude: '-6.3', destination_longitude: '106.9', shipping_address: { address_1: 'Jalan Pembeli number 123 Jakarta', address_2: '', city: 'Jakarta', state: 'JK', postcode: '12345', country: 'ID' } };
    address = destination.shipping_address;
  } else {
    const scenario = state === 'percent' ? 'coupon_percent' : state === 'fixed' ? 'coupon_fixed' : state === 'free' ? 'coupon_free' : '';
    const result = php('instant-checkout-order-runtime.php', '{}', scenario);
    expect(result.error).toBe('');
    expect(result.processed_error).toBe('');
    expect(result.calls).toBe(1);
    const snapshot = result.meta._kiriof_instant_checkout_snapshot;
    subtotal = snapshot.context.item_value;
    shipping = result.shipping_total;
    fees = Object.values(result.cart_fees).map((fee: any) => ({ id: fee.id, name: fee.name, totals: money(fee.amount) }));
    rates = [{ rate_id: gosend, method_id: 'kiriminaja-instant', name: snapshot.rate.label, selected: true, price: String(shipping), currency_minor_unit: 0,
      extensions: { 'kiriminaja-official': { ...snapshot.customer_pricing, coupon_notice: state === 'free' ? 'Free shipping' : '' } } }];
    destination = result.meta._kiriof_buyer_destination;
    address = result.order_address;
    if (state === 'percent') {
      expect(snapshot.customer_pricing.discount_amount).toBe(4500); // 25% fixture, not an assumed merchant coupon amount.
      expect(result.rows[0].discount_amount).toBe(snapshot.customer_pricing.discount_amount);
      expect(result.rows[0].shipping_cost).toBe(snapshot.rate.shipping_costs); // raw provider quote stays intact.
    }
  }
  const feeTotal = fees.reduce((sum, fee) => sum + Number(fee.totals.total), 0);
  return { needs_shipping: true, shipping_address: address, fees,
    totals: { ...money(subtotal + shipping + feeTotal), total_items: String(subtotal), total_shipping: String(shipping), total_fees: String(feeTotal), total_price: String(subtotal + shipping + feeTotal) },
    shipping_rates: [{ package_id: 0, shipping_rates: rates }], extensions: { 'kiriminaja-official': { destination } } };
}

function classicTable(cart: any) {
  const rates = cart.shipping_rates[0].shipping_rates;
  const html = php('classic-shipping-presentation-runtime.php', JSON.stringify({ chosen: rates.find((r: any) => r.selected).rate_id,
    session: { kiriof_shipping_coupon_rate_meta: Object.fromEntries(rates.map((r: any) => [r.rate_id, r.extensions['kiriminaja-official']])) },
    rates: rates.map((r: any) => ({ id: r.rate_id, label: r.name, cost: Number(r.price) })) })).html;
  const row = (name: string, amount: string, cls: string) => `<tr class="${cls}"><th>${name}</th><td data-amount="${amount}">Rp ${amount}</td></tr>`;
  // The host Woo summary is intentionally native, not plugin-generated coupon rows.
  return `<table class="shop_table kiriof-classic-order-review"><tbody>${row('Subtotal', cart.totals.total_items, 'cart-subtotal')}${html}${cart.fees.map((f: any) => row(f.name, f.totals.total, 'fee')).join('')}${row('Total', cart.totals.total_price, 'order-total')}</tbody></table>`;
}
const controls = '<p><button type="button" data-coupon="percent">Apply percentage</button><button type="button" data-coupon="fixed">Apply fixed</button><button type="button" data-coupon="free">Apply free</button><button type="button" data-coupon="removed">Remove coupon</button></p>';
function classicHTML(cart: any) {
  return `<!doctype html><html><head><style>${classicCss()}</style></head><body><div class="woocommerce"><form class="checkout">${controls}<div id="kiriof-classic-insurance-field"><input type="hidden" name="kiriof_insurance" value="1"><label><input id="kiriof_insurance" type="checkbox" value="1" checked disabled>Shipping insurance</label></div><div id="order_review">${classicTable(cart)}<div id="payment">Bank transfer</div></div></form></div>${scripts([
    readFileSync(new URL('../node_modules/jquery/dist/jquery.min.js', import.meta.url), 'utf8'),
    `window.kiriofBillingAddressConfig={i18n:{shippingOptions:'Shipping options'},courierLogos:{}};window.kiriofClassicCheckoutConfig={enabled:true,ownsDistrict:false};window.__changes=[];window.__cart=${JSON.stringify(cart)};window.__insurance=document.querySelector('#kiriof_insurance');jQuery(document).on('change','input.shipping_method',function(){window.__changes.push(this.value);});document.querySelectorAll('[data-coupon]').forEach(button=>button.onclick=async()=>{const response=await fetch('/coupon',{method:'POST',body:JSON.stringify({state:button.dataset.coupon})});const data=await response.json();window.__cart=data.cart;document.querySelector('table.kiriof-classic-order-review').outerHTML=data.html;jQuery(document.body).trigger('updated_checkout');window.__state=button.dataset.coupon;});`,
    script('assets/buyer/js/kiriof-checkout-session.js'), script('assets/buyer/js/checkout/choices-controls.js'), script('assets/buyer/js/checkout/state.js'), script('assets/buyer/js/checkout/shipping-payment.js'), script('assets/buyer/js/checkout/shipping-options.js'), 'kiriofScheduleClassicShippingMethodSelectInit();',
  ])}</body></html>`;
}
function blocksHTML(saved: any) {
  return `<!doctype html><html><body><div class="wc-block-checkout">${controls}<div id="native"></div><div id="district"></div><p id="shipping-error" hidden></p></div>${scripts([
    reactSource(),
    `const R=window.__React;const listeners=new Set();let version=0;window.__extensions={};window.__errors={};window.__changes=[];window.__cart={needsShipping:false,shippingRates:[],shippingAddress:{},extensions:{}};
    const emit=()=>{version++;listeners.forEach(fn=>fn());};const subscribe=fn=>{listeners.add(fn);return()=>listeners.delete(fn);};
    function apply(cart){window.__response=cart;window.__cart={...cart,needsShipping:cart.needs_shipping,shippingAddress:cart.shipping_address,shippingRates:cart.shipping_rates};emit();}
    const select=name=>({'wc/store/cart':{getCartData:()=>window.__cart,isShippingRateBeingSelected:()=>false,isCustomerDataUpdating:()=>false,hasPendingItemsOperations:()=>false},'wc/store/checkout':{prefersCollection:()=>false},'wc/store/payment':{getActivePaymentMethod:()=> 'bacs'}})[name];
    window.wp={element:R,data:{select,subscribe,useSelect:fn=>{R.useSyncExternalStore(subscribe,()=>version);return fn(select);},dispatch:name=>name==='wc/store/checkout'?{setExtensionData:(namespace,data)=>{window.__extensions[namespace]=data;}}:name==='wc/store/cart'?{selectShippingRate:async(rate,id)=>{window.__changes.push([rate,id]);throw new Error('Coupon must preserve selected rate');}}:{setValidationErrors:errors=>{Object.assign(window.__errors,errors);document.querySelector('#shipping-error').hidden=false;},clearValidationError:id=>{delete window.__errors[id];document.querySelector('#shipping-error').hidden=!Object.keys(window.__errors).length;}}}};
    window.wc={blocksCheckout:{registerCheckoutBlock:registration=>{window.__District=registration.component;},extensionCartUpdate:async payload=>{const response=await fetch('${cartPath}/extensions',{method:'POST',body:JSON.stringify(payload)});apply(await response.json());}}};
    window.kiriofBuyerCheckoutConfig={enabled:true,nonce:'isolated',ajaxUrl:'/saved-district',map:{enabled:false},savedDestination:${JSON.stringify(saved)},i18n:{shippingSelectionChanged:'Shipping options changed. Please review and select your courier again before placing the order.'}};
    function NativeCheckout(){R.useSyncExternalStore(subscribe,()=>version);const cart=window.__response;if(!cart)return null;const row=(name,amount,cls)=>R.createElement('tr',{key:cls,className:cls},R.createElement('th',null,name),R.createElement('td',{'data-amount':amount},'Rp '+Number(amount).toLocaleString('id-ID')));return R.createElement('div',null,R.createElement('fieldset',null,cart.shipping_rates[0].shipping_rates.map(rate=>R.createElement('label',{key:rate.rate_id},R.createElement('input',{type:'radio',name:'shipping',value:rate.rate_id,checked:rate.selected,onChange:()=>{throw new Error('No reselection expected');}}),rate.name+' Rp '+rate.price,R.createElement('span',{className:'coupon-notice'},rate.extensions['kiriminaja-official'].coupon_notice)))),R.createElement('table',null,R.createElement('tbody',null,row('Subtotal',cart.totals.total_items,'cart-subtotal'),row('Shipping',cart.totals.total_shipping,'shipping'),...cart.fees.map(fee=>row(fee.name,fee.totals.total,'fee')),row('Total',cart.totals.total_price,'order-total'))));}
    window.__createRoot(document.querySelector('#native')).render(R.createElement(NativeCheckout));document.querySelectorAll('[data-coupon]').forEach(button=>button.onclick=async()=>{const response=await fetch('${cartPath}/coupons',{method:button.dataset.coupon==='removed'?'DELETE':'POST',body:JSON.stringify({state:button.dataset.coupon})});apply(await response.json());window.__state=button.dataset.coupon;});`,
    script('assets/buyer/js/kiriof-checkout-session.js'), script('assets/buyer/js/kiriof-buyer-checkout.js'),
    `window.__createRoot(document.querySelector('#district')).render(R.createElement(window.__District));fetch('${cartPath}').then(r=>r.json()).then(apply);`,
  ])}</body></html>`;
}

// Two browser cases, four delivery/checkout flows. All HTTP stays intercepted:
// actual deployed Svelte Classic controls / React Blocks entry, not installed Woo.
for (const checkout of ['Classic', 'Blocks']) {
  test(`${checkout} Express and Instant coupons refresh native shipping without double-discount or reselection`, async ({ app, browser }) => {
    let delivery = 'express', state = 'none';
    const unexpected: string[] = [], requests: string[] = [];
    await browser.route('**/*', (route: any) => {
      const url = new URL(route.request.url);
      if (url.pathname === '/shipping-coupons') {
        const cart = response(delivery, 'none');
        return route.fulfill({ contentType: 'text/html', body: checkout === 'Classic' ? classicHTML(cart) : blocksHTML(cart.extensions['kiriminaja-official'].destination) });
      }
      if (url.pathname === '/coupon' || url.pathname === cartPath + '/coupons') {
        state = JSON.parse(route.request.postData || '{}').state;
        requests.push(delivery + ':' + state);
        const cart = response(delivery, state);
        return route.fulfill({ contentType: 'application/json', body: JSON.stringify(checkout === 'Classic' ? { cart, html: classicTable(cart) } : cart) });
      }
      if (url.pathname === cartPath || url.pathname === cartPath + '/extensions') return route.fulfill({ contentType: 'application/json', body: JSON.stringify(response(delivery, state)) });
      if (url.pathname === '/saved-district') return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: [{ id: '42', text: 'Jakarta District' }] }) });
      if (/^\/assets\/buyer\/img\/couriers\/[a-z_-]+\.png$/.test(url.pathname)) return route.fulfill({ contentType: 'image/png', path: root + url.pathname });
      if (url.pathname !== '/favicon.ico') unexpected.push(url.href);
      return route.fulfill({ status: 409, body: 'Live network prohibited' });
    });
    for (delivery of ['express', 'instant']) {
      state = 'none';
      await app.open('/shipping-coupons');
      const id = delivery === 'express' ? ninja : gosend;
      const selected = checkout === 'Classic' ? 'input.shipping_method:checked, input.shipping_method[type="hidden"]' : 'input[name="shipping"]:checked';
      await expect(browser.locator(selected)).toHaveAttribute('value', id);
      if (checkout === 'Blocks') await expect.poll(() => browser.evaluate(() => (window as any).__extensions['kiriminaja-official']?.shipping_selection)).toEqual({ version: 1, packages: [{ package_id: '0', rate_id: id }] });
      const baseline = response(delivery, 'none');
      for (const next of ['percent', 'removed', 'fixed', 'removed', 'free', 'removed']) {
        await browser.locator(`[data-coupon="${next}"]`).click();
        await expect.poll(() => browser.evaluate(() => (window as any).__state)).toBe(next);
        const expected = response(delivery, next);
        await expect(browser.locator(selected)).toHaveAttribute('value', id);
        await expect(browser.locator('.cart-subtotal td')).toHaveAttribute('data-amount', baseline.totals.total_items);
        await expect(browser.locator('.fee')).toHaveCount(1);
        await expect(browser.locator('.fee td')).toHaveAttribute('data-amount', baseline.totals.total_fees);
        await expect(browser.locator('.order-total td')).toHaveAttribute('data-amount', expected.totals.total_price);
        if (checkout === 'Classic') {
          await expect(browser.locator('.kiriof-classic-shipping-summary-cost td')).toContainText('Rp ' + expected.totals.total_shipping);
          if (delivery === 'express') await expect(browser.locator('.kiriof-buyer-combobox-trigger')).toContainText('Rp ' + expected.totals.total_shipping);
          expect(await browser.evaluate(() => { const w=window as any;return document.querySelector('#kiriof_insurance')===w.__insurance && w.__insurance.checked; })).toBe(true);
        } else {
          await expect(browser.locator('.shipping td')).toHaveAttribute('data-amount', expected.totals.total_shipping);
          expect(await browser.evaluate(() => (window as any).__extensions['kiriminaja-official'].shipping_selection)).toEqual({ version: 1, packages: [{ package_id: '0', rate_id: id }] });
          expect(await browser.evaluate(() => (window as any).__errors)).toEqual({});
          await expect(browser.locator('#shipping-error')).not.toBeVisible();
          if (delivery === 'express' && ['fixed', 'percent'].includes(next)) await expect(browser.locator('.coupon-notice').filter({ hasText: 'Coupon not applicable' })).toHaveCount(1);
        }
        const cart = await browser.evaluate(() => (window as any).__response || (window as any).__cart);
        expect(Number(cart.totals.total_items) + Number(cart.totals.total_shipping) + Number(cart.totals.total_fees)).toBe(Number(cart.totals.total_price));
        expect(cart.shipping_rates[0].shipping_rates.find((r: any) => r.selected).price).toBe(expected.totals.total_shipping);
        expect(await browser.evaluate(() => (window as any).__changes)).toEqual([]);
      }
    }
    expect(requests).toEqual(['express', 'instant'].flatMap(d => ['percent', 'removed', 'fixed', 'removed', 'free', 'removed'].map(s => d + ':' + s)));
    expect(unexpected).toEqual([]);
  });
}
