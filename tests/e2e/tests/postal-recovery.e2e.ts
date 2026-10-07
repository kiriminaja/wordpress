import { script, classicCss } from '../fixtures/buyer-source';
import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { readFileSync, writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const read = (path: string) => readFileSync(root + path, 'utf8');
const postal = (scenario: string) => JSON.parse(execFileSync('php', ['-d', 'error_reporting=22527', root + 'tests/fixtures/postal-lookup-recovery-runtime.php', scenario], { encoding: 'utf8' }));
const cargo = 'kiriminaja-official_jne_REG';
const instant = 'kiriminaja-instant:7:gosend:instant';
const label = 'Sariharjo, Ngaglik, Sleman, DI Yogyakarta, 55581';
const address = { address_1: 'Jalan Palagan 12', address_2: '', city: 'Sleman', state: 'YO', postcode: '55581', country: 'ID', first_name: 'Test', last_name: 'Buyer', phone: '08123456789' };
// Use the production PHP fixture's official village ID, not a fabricated provider row.
const saved = { version: 2, district_id: '46310', district_label: label, postcode: '55581', country: 'ID', address_type: 'shipping', destination_latitude: '-7.7100000', destination_longitude: '110.3700000', shipping_address: address };
const emptyMessage = 'No villages returned. Retry lookup or edit your address.';
const errorMessage = 'Village lookup failed. Please retry.';
const shippingMessage = 'Shipping options changed. Please review and select your courier again before placing the order.';
const scripts = (sources: string[]) => sources.map(source => `<script>${source.replace(/<\/script/gi, '<\\/script')}</script>`).join('');
let reactBundle: string;
function reactSource() {
  if (reactBundle) return reactBundle;
  const dir = mkdtempSync(tmpdir() + '/kiriof-postal-react-');
  try {
    writeFileSync(dir + '/entry.js', `import * as React from ${JSON.stringify(root + 'node_modules/react/index.js')};import {createRoot} from ${JSON.stringify(root + 'node_modules/react-dom/client.js')};import {createPortal} from ${JSON.stringify(root + 'node_modules/react-dom/index.js')};window.__React={...React,createPortal};window.__createRoot=createRoot;`);
    execFileSync('bun', ['build', dir + '/entry.js', '--target=browser', '--outfile=' + dir + '/bundle.js']);
    return reactBundle = readFileSync(dir + '/bundle.js', 'utf8');
  } finally { rmSync(dir, { recursive: true, force: true }); }
}
function html() {
  return `<!doctype html><html><body><div class="wc-block-checkout"><div id="native"></div><div id="district"></div><div id="fallback"></div><p id="shipping-error" role="alert" hidden></p></div>${scripts([
    reactSource(),
    `const R=window.__React;const listeners=new Set();let version=0;
    window.__extensions={};window.__errors={};window.__mutations=[];window.__emptyWrites=[];window.__trusted=[];window.__nativeActions=[];window.__customerBusy=false;
    const rates=[{rate_id:${JSON.stringify(cargo)},method_id:'kiriminaja-official',selected:true,label:'JNE Cargo'},{rate_id:${JSON.stringify(instant)},method_id:'kiriminaja-instant',selected:false,label:'GoSend Instant'}];
    window.__cart={needsShipping:true,shippingAddress:${JSON.stringify(address)},billingAddress:{address_1:'Separate billing street'},extensions:{},shippingRates:[{package_id:3,shipping_rates:rates}]};
    const emit=()=>{version++;listeners.forEach(fn=>fn());};
    const subscribe=fn=>{listeners.add(fn);return()=>listeners.delete(fn);};
    const stores={'wc/store/cart':{getCartData:()=>window.__cart,isShippingRateBeingSelected:()=>false,isCustomerDataUpdating:()=>window.__customerBusy,hasPendingItemsOperations:()=>false},'wc/store/checkout':{prefersCollection:()=>false},'wc/store/payment':{getActivePaymentMethod:()=> 'bacs'}};
    const select=name=>stores[name];
    function showErrors(){const p=document.querySelector('#shipping-error');const error=window.__errors['kiriof-shipping-selection'];p.hidden=!error;p.textContent=error?error.message:'';}
    // No selectShippingRate adapter: an automatic billing refresh cannot silently
    // restore/review a courier. The production shipping-selection guard stays active.
    window.wp={element:R,data:{select,subscribe,useSelect:fn=>{R.useSyncExternalStore(subscribe,()=>version);return fn(select);},dispatch:name=>name==='wc/store/checkout'?{setExtensionData:(namespace,data)=>{window.__extensions[namespace]=data;}}:name==='wc/store/cart'?{}:{setValidationErrors:errors=>{Object.assign(window.__errors,errors);showErrors();},clearValidationError:id=>{delete window.__errors[id];showErrors();}}},plugins:{registerPlugin:(name,registration)=>{window.__Fallback=registration.render;}}};
    window.wc={blocksCheckout:{OrderMeta:props=>props.children,registerCheckoutBlock:registration=>{window.__District=registration.component;},extensionCartUpdate:async payload=>{window.__mutations.push(payload);if(!payload.data.destination.district_id){window.__emptyWrites.push(payload);throw new Error('Empty district mutation rejected');}const response=await fetch('/cart-sync',{method:'POST',body:JSON.stringify(payload)});if(!response.ok)throw new Error('Cart mutation rejected');}}};
    window.kiriofBuyerCheckoutConfig={enabled:true,nonce:'valid',ajaxUrl:'/postal',map:{enabled:true},savedDestination:${JSON.stringify(saved)},i18n:{district:'Village',selectDistrict:'Choose village',loading:'Loading villages',checkingDistrict:'Checking village',districtNotSet:'Village not verified',districtRequired:'Choose village',postcodeRequired:'Enter postcode',emptyRetry:${JSON.stringify(emptyMessage)},lookupFailed:${JSON.stringify(errorMessage)},lookupTimeout:'Lookup timed out',pinLocation:'Pin location saved',needPinLocation:'Choose pin location',pinRequirement:'Saved destination pin',retry:'Retry',saving:'Saving destination',updateFailed:'Destination save failed',shippingSelectionChanged:${JSON.stringify(shippingMessage)}}};
    function NativeCheckout(){const [editing,setEditing]=R.useState(false);R.useSyncExternalStore(subscribe,()=>version);return R.createElement('div',null,
      R.createElement('div',{id:'shipping-fields',className:'wc-block-checkout__shipping-fields'},R.createElement('div',{className:'wc-block-components-address-address-wrapper'+(editing?' is-editing':'')},R.createElement('div',{className:'wc-block-components-address-card'},R.createElement('p',null,'Test Buyer, Jalan Palagan 12, Sleman, 55581'),R.createElement('button',{type:'button',className:'wc-block-components-address-card__edit','aria-controls':'shipping','aria-expanded':String(editing),onClick:()=>setEditing(!editing)},editing?'Done':'Edit')))),
      R.createElement('div',{id:'billing-fields',className:'wc-block-checkout__billing-fields'},R.createElement('label',null,R.createElement('input',{id:'same-billing',type:'checkbox',defaultChecked:false,onChange:async event=>{window.__trusted.push(event.nativeEvent.isTrusted);window.__customerBusy=true;emit();const response=await fetch('/billing-rates');const result=await response.json();window.__cart={...window.__cart,billingAddress:{...window.__cart.shippingAddress},shippingRates:[{package_id:3,shipping_rates:rates.map(rate=>({...rate,selected:rate.rate_id===result.rate}))}]};window.__customerBusy=false;emit();}}),'Use same address for billing')),
      R.createElement('fieldset',null,window.__cart.shippingRates[0].shipping_rates.map(rate=>R.createElement('label',{key:rate.rate_id},R.createElement('input',{type:'radio',name:'shipping',value:rate.rate_id,checked:rate.selected,onChange:()=>{}}),rate.label))));}
    window.__createRoot(document.querySelector('#native')).render(R.createElement(NativeCheckout));`,
    script('assets/buyer/js/kiriof-checkout-session.js'),


    script('assets/buyer/js/kiriof-buyer-checkout.js'),
    `window.__createRoot(document.querySelector('#district')).render(R.createElement(window.__District));window.__createRoot(document.querySelector('#fallback')).render(R.createElement(window.__Fallback));`,
  ])}</body></html>`;
}

type Failure = 'empty' | 'http503' | 'malformed' | 'invalidrows';
async function open(app: any, browser: any, failure: Failure) {
  const first = postal('empty');
  const recovery = postal('retry');
  // Factory/service/repository integration: empty v3 cache is ignored, an empty
  // response is never cached, v4 caches only validated rows, explicit retry bypasses it.
  expect(first.response[0].data).toEqual([]);
  expect(first.calls).toHaveLength(2);
  expect(first.writes).toHaveLength(1);
  expect(first.writes[0][0]).toContain('kiriof_district_search_v4_');
  expect(recovery.calls).toHaveLength(2);
  expect(recovery.response.success).toBe(true);
  const requests: { method: string; term: string | null; retry: string | null; action: string | null; nonce: string | null }[] = [];
  const writes: any[] = [], unexpected: string[] = [], rateRequests: string[] = [];
  await browser.route('**/*', (route: any) => {
    const url = new URL(route.request.url);
    if (url.pathname === '/postal-recovery') return route.fulfill({ contentType: 'text/html', body: html() });
    if (url.pathname === '/postal') {
      const body = new URLSearchParams(route.request.postData || '');
      requests.push({ method: route.request.method, term: body.get('term'), retry: body.get('retry'), action: body.get('action'), nonce: body.get('nonce') });
      if (requests.length > 1) return route.fulfill({ contentType: 'application/json', body: JSON.stringify(recovery.response) });
      if (failure === 'http503') return route.fulfill({ status: 503, contentType: 'application/json', body: '{"success":false}' });
      if (failure === 'malformed') return route.fulfill({ contentType: 'application/json', body: '{not-json' });
      if (failure === 'invalidrows') return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: [{ id: 0, text: 'Invalid nonempty village' }] }) });
      return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: first.response[0].data }) });
    }
    if (url.pathname === '/billing-rates') { rateRequests.push(url.pathname); return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ rate: instant }) }); }
    if (url.pathname === '/cart-sync') {
      const payload = JSON.parse(route.request.postData || '{}');
      writes.push(payload);
      if (!payload.data?.destination?.district_id) { unexpected.push('Empty district reached cart API'); return route.fulfill({ status: 409, body: 'Empty district rejected' }); }
      return route.fulfill({ contentType: 'application/json', body: '{}' });
    }
    if (url.pathname !== '/favicon.ico') unexpected.push(url.href);
    return route.fulfill({ status: 409, body: 'No live requests permitted' });
  });
  await app.open('/postal-recovery');
  return { requests, writes, unexpected, rateRequests };
}
const destination = (browser: any) => browser.evaluate(() => (window as any).kiriofBuyerCheckout.getDestination());
const review = (browser: any) => browser.evaluate(() => (window as any).__extensions['kiriminaja-official']?.shipping_selection);
const pin = (browser: any) => browser.evaluate(() => (window as any).kiriofBuyerCheckout.getCoordinates((window as any).__cart.shippingAddress));

for (const failure of ['empty', 'http503', 'malformed', 'invalidrows'] as const) {
  for (const recoveryAction of ['Edit', 'collapsed Retry'] as const) {
  test(`Blocks saved 55581 pin survives ${failure}; billing is inert; ${recoveryAction} recovers official village`, async ({ app, browser }) => {
    const state = await open(app, browser, failure);
    const card = browser.locator('.wc-block-components-address-card');
    const message = failure === 'empty' ? emptyMessage : errorMessage;
    await expect(card).toContainText(message);
    await expect(card).toContainText('Pin location saved');
    await expect(card).not.toContainText('Choose pin location');
    await expect.poll(() => browser.evaluate(() => document.querySelectorAll('.kiriof-address-status__badge.is-warning').length)).toBe(1);
    await expect.poll(() => review(browser)).toEqual({ version: 1, packages: [{ package_id: '3', rate_id: cargo }] });
    expect(state.requests).toEqual([{ method: 'POST', term: '55581', retry: null, action: 'kiriminaja_subdistrict_search', nonce: 'valid' }]);
    expect(state.writes).toEqual([]);
    expect(await browser.evaluate(() => (window as any).__mutations)).toEqual([]);
    const restoredPin = await pin(browser);
    expect(restoredPin.latitude).toBe('-7.7100000');
    expect(restoredPin.longitude).toBe('110.3700000');
    expect(await browser.evaluate(() => document.querySelector<HTMLInputElement>('#same-billing')!.checked)).toBe(false);

    await browser.locator('#same-billing').click();
    await expect.poll(() => browser.evaluate(() => (window as any).__customerBusy)).toBe(false);
    await expect(browser.locator('#shipping-error')).toContainText(shippingMessage);
    await expect(card).toContainText(message);
    expect(state.requests).toHaveLength(1);
    expect(state.rateRequests).toEqual(['/billing-rates']);
    expect(state.writes).toEqual([]);
    expect(await review(browser)).toEqual({ version: 1, packages: [{ package_id: '3', rate_id: cargo }] });
    expect(await browser.evaluate(() => document.querySelector<HTMLInputElement>('input[name="shipping"]:checked')!.value)).toBe(instant);

    if (recoveryAction === 'Edit') {
      await browser.locator('.wc-block-components-address-card__edit').click();
    } else {
      await browser.locator('.wc-block-components-address-card button').filter({ hasText: 'Retry' }).click();
      await expect.poll(() => destination(browser).then((value: any) => value?.district_id)).toBe('46310');
      // Opening after a completed successful retry is not another retry.
      await browser.locator('.wc-block-components-address-card__edit').click();
    }
    await expect.poll(() => browser.evaluate(() => document.querySelector<HTMLSelectElement>('.kiriof-buyer-district select')?.value)).toBe('46310');
    await expect(browser.locator('.kiriof-buyer-district select')).toContainText(label);
    expect(await browser.evaluate(() => document.querySelector<HTMLSelectElement>('.kiriof-buyer-district select')!.selectedOptions[0].textContent)).toBe(label);
    await expect.poll(() => state.writes.length).toBe(1);
    expect(state.requests).toEqual([
      { method: 'POST', term: '55581', retry: null, action: 'kiriminaja_subdistrict_search', nonce: 'valid' },
      { method: 'POST', term: '55581', retry: '1', action: 'kiriminaja_subdistrict_search', nonce: 'valid' },
    ]);
    const recovered = await destination(browser);
    expect(recovered.district_id).toBe('46310');
    expect(recovered.district_label).toBe(label);
    expect(recovered.postcode).toBe('55581');
    expect(recovered.destination_latitude).toBe('-7.7100000');
    expect(recovered.destination_longitude).toBe('110.3700000');
    expect(await pin(browser)).toEqual(restoredPin);
    expect(state.writes[0].data.destination).toEqual(recovered);
    expect(await browser.evaluate(() => (window as any).__emptyWrites)).toEqual([]);
    expect(await browser.evaluate(() => (window as any).__trusted)).toEqual([true]);
    expect(await browser.evaluate(() => (window as any).__nativeActions)).toEqual([]);
    // Edit/Done loops and duplicate fallback mounts never acquire a second owner.
    for (let cycle = 0; cycle < 2; cycle++) {
      await browser.locator('.wc-block-components-address-card__edit').click();
      await expect(card).toContainText('Pin location saved');
      await expect(card).not.toContainText(message);
      await browser.locator('.wc-block-components-address-card__edit').click();
      await expect(browser.locator('.kiriof-buyer-district select')).toBeVisible();
    }
    await expect(browser.locator('#shipping-error')).toContainText(shippingMessage);
    expect(await review(browser)).toEqual({ version: 1, packages: [{ package_id: '3', rate_id: cargo }] });
    expect(state.requests).toHaveLength(2);
    expect(state.writes).toHaveLength(1);
    expect(state.unexpected).toEqual([]);
  });
}

}
