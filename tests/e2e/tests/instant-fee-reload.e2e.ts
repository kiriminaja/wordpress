import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { script } from '../fixtures/buyer-source';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const cartPath = '/wp-json/wc/store/v1/cart';
const storageKey = 'instant-fee-reload-native-cart';
const scripts = (sources: string[]) => sources.map(source => `<script>${source.replace(/<\/script/gi, '<\\/script')}</script>`).join('');

// Match the existing selection fixture: actual React and deployed plugin entries,
// isolated Woo stores/HTTP. This does NOT claim to run installed WooCommerce.
let reactBundle: string;
function reactSource() {
  if (reactBundle) return reactBundle;
  const dir = mkdtempSync(tmpdir() + '/kiriof-fee-reload-react-');
  try {
    writeFileSync(dir + '/entry.js', `import * as React from ${JSON.stringify(root + 'node_modules/react/index.js')};import {createRoot} from ${JSON.stringify(root + 'node_modules/react-dom/client.js')};window.__React=React;window.__createRoot=createRoot;`);
    execFileSync('bun', ['build', dir + '/entry.js', '--target=browser', '--outfile=' + dir + '/bundle.js']);
    return reactBundle = readFileSync(dir + '/bundle.js', 'utf8');
  } finally { rmSync(dir, { recursive: true, force: true }); }
}

function runtime(scenario: string) {
  // This PHP harness loads production quote/controller code with isolated session,
  // product and provider doubles. argv[2], NOT JSON argv[1], selects this scenario.
  const result = JSON.parse(execFileSync('php', [root + 'tests/fixtures/instant-checkout-order-runtime.php', '{}', scenario], { encoding: 'utf8' }));
  expect(result.error).toBe('');
  expect(result.processed_error).toBe('');
  expect(result.production_quote_service).toBe('KiriminAjaOfficial\\Services\\InstantCheckoutQuoteService');
  expect(result.reload.raw_package_has_type).toBe(false);
  expect(result.reload.fresh_session).toBe(true);
  expect(result.reload.fresh_rate).toBe(true);
  expect(result.reload.quoted_package_type_id).toBe(scenario === 'reload_uniform_type' ? 2 : 7);
  expect(Object.values(result.reload.product_types)).toEqual(scenario === 'reload_uniform_type' ? [2, 2] : [2, 3]);
  expect(result.reload.calls_after_quote).toBe(1);
  expect(result.reload.calls_after_fees).toBe(1);
  expect(result.calls).toBe(1);
  const fees = Object.values(result.cart_fees) as any[];
  expect(fees).toEqual([{ id: 'kiriof_instant_admin_fee', name: 'Admin Fee', amount: 1000, taxable: false }]);
  expect(result.shipping_total).toBe(18000);
  const snapshot = result.meta._kiriof_instant_checkout_snapshot;
  expect(snapshot.rate.admin_fee).toBe(fees[0].amount);
  expect(snapshot.rate.total_price).toBe(result.shipping_total + fees[0].amount);
  expect(result.fee_lines).toHaveLength(1);
  expect(result.fee_lines[0].total).toBe(fees[0].amount);
  return result;
}

function cartResponse(result: any) {
  const snapshot = result.meta._kiriof_instant_checkout_snapshot;
  const money = (amount: number) => ({ currency_code: 'IDR', currency_symbol: 'Rp', currency_minor_unit: 0, total: String(amount), total_tax: '0' });
  const itemTotal = snapshot.context.item_value;
  const feeTotal = (Object.values(result.cart_fees) as any[]).reduce((sum, fee) => sum + fee.amount, 0);
  return {
    needs_shipping: true, shipping_address: result.order_address,
    fees: (Object.values(result.cart_fees) as any[]).map(fee => ({ id: fee.id, name: fee.name, totals: money(fee.amount) })),
    totals: { ...money(itemTotal + result.shipping_total + feeTotal), total_items: String(itemTotal), total_shipping: String(result.shipping_total), total_fees: String(feeTotal), total_price: String(itemTotal + result.shipping_total + feeTotal) },
    shipping_rates: [{ package_id: 0, shipping_rates: [{ rate_id: result.reload.selected_method, method_id: 'kiriminaja-instant', name: snapshot.rate.label, selected: true, price: String(result.shipping_total), currency_minor_unit: 0 }] }],
    extensions: { 'kiriminaja-official': { destination: result.meta._kiriof_buyer_destination } },
  };
}

function html(saved: any) {
  return `<!doctype html><html><body><div class="wc-block-checkout"><div id="native"></div><div id="district"></div><p id="shipping-error" hidden></p></div>${scripts([
    reactSource(),
    `const R=window.__React;const listeners=new Set();let version=0;
    window.__extensions={};window.__errors={};window.__nativeActions=[];window.__mutations=[];window.__ready=false;
    window.__persistedAtBoot=JSON.parse(localStorage.getItem(${JSON.stringify(storageKey)})||'null');
    window.__cart={needsShipping:false,shippingRates:[],shippingAddress:{},extensions:{}};
    const emit=()=>{version++;listeners.forEach(fn=>fn());};const subscribe=fn=>{listeners.add(fn);return()=>listeners.delete(fn);};
    function apply(cart){window.__response=cart;window.__cart={...cart,needsShipping:cart.needs_shipping,shippingAddress:cart.shipping_address,shippingRates:cart.shipping_rates};localStorage.setItem(${JSON.stringify(storageKey)},JSON.stringify({rate:cart.shipping_rates[0].shipping_rates.find(rate=>rate.selected).rate_id,destination:cart.extensions['kiriminaja-official'].destination}));emit();}
    const select=name=>({'wc/store/cart':{getCartData:()=>window.__cart,isShippingRateBeingSelected:()=>false,isCustomerDataUpdating:()=>false,hasPendingItemsOperations:()=>false},'wc/store/checkout':{prefersCollection:()=>false},'wc/store/payment':{getActivePaymentMethod:()=> 'bacs'}})[name];
    window.wp={element:R,data:{select,subscribe,useSelect:fn=>{R.useSyncExternalStore(subscribe,()=>version);return fn(select);},dispatch:name=>name==='wc/store/checkout'?{setExtensionData:(namespace,data)=>{window.__extensions[namespace]=data;}}:name==='wc/store/cart'?{selectShippingRate:async(rate,packageId)=>{window.__nativeActions.push([rate,packageId]);throw new Error('Matching persisted selection must not need restoration');}}:{setValidationErrors:errors=>{Object.assign(window.__errors,errors);const p=document.querySelector('#shipping-error');p.hidden=false;p.textContent=Object.values(errors).map(error=>error.message).join(' ');},clearValidationError:id=>{delete window.__errors[id];document.querySelector('#shipping-error').hidden=!Object.keys(window.__errors).length;}}}};
    window.wc={blocksCheckout:{registerCheckoutBlock:registration=>{window.__District=registration.component;},extensionCartUpdate:async payload=>{window.__mutations.push(payload);const response=await fetch('${cartPath}/extensions',{method:'POST',body:JSON.stringify(payload)});apply(await response.json());}}};
    window.kiriofBuyerCheckoutConfig={enabled:true,nonce:'isolated',ajaxUrl:'/saved-district',map:{enabled:false},savedDestination:window.__persistedAtBoot?.destination||${JSON.stringify(saved)},i18n:{shippingSelectionChanged:'Shipping options changed. Please review and select your courier again before placing the order.'}};
    // Native Woo-style fee rows are derived ONLY from Store API cart.fees. The
    // production plugin does not inject the fee markup used by this fixture.
    function NativeCheckout(){R.useSyncExternalStore(subscribe,()=>version);const cart=window.__response;if(!cart)return null;const row=(name,amount,cls)=>R.createElement('tr',{key:cls,className:cls},R.createElement('th',null,name),R.createElement('td',{'data-amount':amount},'Rp '+Number(amount).toLocaleString('id-ID')));return R.createElement('div',null,R.createElement('fieldset',null,cart.shipping_rates[0].shipping_rates.map(rate=>R.createElement('label',{key:rate.rate_id},R.createElement('input',{type:'radio',name:'shipping',value:rate.rate_id,checked:rate.selected,onChange:()=>{throw new Error('No buyer reselection expected');}}),rate.name))),R.createElement('table',{className:'shop_table woocommerce-checkout-review-order-table'},R.createElement('tbody',null,row('Subtotal',cart.totals.total_items,'cart-subtotal'),row('Shipping',cart.totals.total_shipping,'shipping'),...cart.fees.map(fee=>row(fee.name,fee.totals.total,'fee')),row('Total',cart.totals.total_price,'order-total'))));}
    window.__createRoot(document.querySelector('#native')).render(R.createElement(NativeCheckout));`,
    script('assets/buyer/js/kiriof-checkout-session.js'),
    script('assets/buyer/js/kiriof-buyer-checkout.js'),
    `window.__createRoot(document.querySelector('#district')).render(R.createElement(window.__District));fetch('${cartPath}').then(response=>response.json()).then(cart=>{apply(cart);window.__ready=true;});`,
  ])}</body></html>`;
}

for (const scenario of ['reload_uniform_type', 'reload_mixed_type']) {
  test(`Blocks persisted GoSend native Admin Fee survives browser reload (${scenario})`, async ({ app, browser }) => {
    const initial = runtime(scenario);
    const unexpected: string[] = [], cartRequests: string[] = [];
    const expectedReview = { version: 1, packages: [{ package_id: '0', rate_id: initial.reload.selected_method }] };
    let documents = 0;
    await browser.route('**/*', (route: any) => {
      const url = new URL(route.request.url);
      if (url.pathname === '/instant-fee-reload') { documents++; return route.fulfill({ contentType: 'text/html', body: html(initial.meta._kiriof_buyer_destination) }); }
      if (url.pathname === cartPath || url.pathname === cartPath + '/extensions') {
        cartRequests.push(route.request.method + ' ' + url.pathname);
        // Re-run production PHP on the reload and every totals mutation: a static
        // handwritten fee cannot conceal addAdminFee rejecting the session quote.
        return route.fulfill({ contentType: 'application/json', body: JSON.stringify(cartResponse(runtime(scenario))) });
      }
      // Saved district verification is a local mock, never a live provider lookup.
      if (url.pathname === '/saved-district') return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: [{ id: '42', text: 'Jakarta District' }] }) });
      if (url.pathname !== '/favicon.ico') unexpected.push(url.href);
      return route.fulfill({ status: 409, body: 'No live requests permitted' });
    });
    await app.open('/instant-fee-reload');
    const assertCheckout = async () => {
      await expect.poll(() => browser.evaluate(() => (window as any).__extensions['kiriminaja-official']?.shipping_selection)).toEqual(expectedReview);
      await expect.poll(() => browser.evaluate(() => (window as any).__extensions['kiriminaja-official']?.destination?.district_id)).toBe('42');
      await expect(browser.locator('input[name="shipping"]:checked')).toHaveAttribute('value', initial.reload.selected_method);
      const summary = (selector: string) => browser.locator('.woocommerce-checkout-review-order-table ' + selector);
      await expect(summary('tr.fee')).toHaveCount(1);
      await expect(summary('tr.fee')).toContainText('Admin Fee');
      await expect(summary('tr.fee td')).toHaveAttribute('data-amount', '1000');
      await expect(summary('tr.fee td')).toContainText('Rp 1.000');
      await expect(summary('tr.shipping td')).toHaveAttribute('data-amount', '18000');
      await expect(summary('tr.order-total td')).toHaveAttribute('data-amount', '169000');
      expect(await browser.evaluate(() => { const amount=(selector:string)=>Number(document.querySelector(selector)!.getAttribute('data-amount'));return amount('.cart-subtotal td')+amount('.shipping td')+amount('.fee td')===amount('.order-total td'); })).toBe(true);
      expect(await browser.evaluate(() => (window as any).__nativeActions)).toEqual([]);
      expect(await browser.evaluate(() => (window as any).__errors)).toEqual({});
      await expect(browser.locator('#shipping-error')).not.toBeVisible();
    };
    await assertCheckout();
    const persisted = await browser.evaluate((key: string) => JSON.parse(localStorage.getItem(key)!), storageKey);
    expect(persisted.rate).toBe(initial.reload.selected_method);
    expect(persisted.destination.district_id).toBe('42');
    expect(await browser.evaluate(() => (window as any).__persistedAtBoot)).toBe(null);
    await browser.reload();
    await assertCheckout();
    expect(await browser.evaluate(() => (window as any).__persistedAtBoot)).toEqual(persisted);
    expect(documents).toBe(2);
    expect(cartRequests.filter(request => request === 'GET ' + cartPath)).toHaveLength(2);
    expect(cartRequests.some(request => request.includes('select-shipping-rate') || request.includes('restore'))).toBe(false);
    expect(unexpected).toEqual([]);
  });
}
