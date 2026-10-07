import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { readFileSync, writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const read = (path: string) => readFileSync(root + path, 'utf8');
const php = (name: string, input: unknown) => JSON.parse(execFileSync('php', [root + 'tests/fixtures/' + name, JSON.stringify(input)], { encoding: 'utf8' }));
const instant = 'kiriminaja-instant:7:gosend:instant';
const cargo = 'kiriminaja-official_jne_REG'; // Guard fixture's automatic Express substitute, displayed as Cargo here.
const message = 'Shipping options changed. Please review and select your courier again before placing the order.';
const snapshot = (rate: string, ids = ['3']) => ({ version: 1, packages: ids.map(package_id => ({ package_id, rate_id: rate })) });
const scripts = (sources: string[]) => sources.map(source => `<script>${source.replace(/<\/script/gi, '<\\/script')}</script>`).join('');
function table(chosen: string, ids = [3]) {
  return `<table class="kiriof-classic-order-review"><tbody>${ids.map(index => php('classic-shipping-presentation-runtime.php', {
    index, chosen, rates: [{ id: cargo, label: 'JNE Cargo', cost: 15000 }, { id: instant, label: 'GoSend Instant', cost: 20000 }],
  }).html).join('')}</tbody></table>`;
}
function classicHTML() {
  return `<!doctype html><html><head><style>${read('assets/lib/choices/choices.min.css')}${read('assets/wp/css/kiriof-classic-choices.css')}</style></head><body>
  <form class="checkout"><div id="order_review">${table(cargo)}</div><label><input type="checkbox" id="terms">Accept terms</label><button type="submit">Place order</button></form><p id="terms-error" role="alert" hidden></p>
  ${scripts([
    readFileSync(new URL('../node_modules/jquery/dist/jquery.min.js', import.meta.url), 'utf8'), read('assets/lib/choices/choices.min.js'),
    `window.kiriofBillingAddressConfig={i18n:{shippingSelectionChanged:${JSON.stringify(message)}}};window.kiriofClassicCheckoutConfig={enabled:true};window.__orders=0;window.__attempts=0;
    window.__replace=function(html){document.querySelector('table').outerHTML=html;jQuery(document.body).trigger('updated_checkout');};`,
    read('assets/wp/js/checkout/choices-controls.js'), read('assets/wp/js/checkout/state.js'), read('assets/wp/js/checkout/shipping-payment.js'),
    read('assets/wp/js/kiriof-shipping-selection.js'), read('assets/wp/js/checkout/shipping-options.js'),
    `jQuery('form.checkout').on('submit.fixture',function(event){event.preventDefault();window.__attempts++;
      if(jQuery(this).triggerHandler('checkout_place_order')===false)return;
      if(!document.querySelector('#terms').checked){document.querySelector('#terms-error').hidden=false;document.querySelector('#terms-error').textContent='Please accept terms';jQuery(document.body).trigger('checkout_error');return;}
      window.__orders++;});`,
  ])}</body></html>`;
}

// Build real React/ReactDOM from root dev dependencies, not a mock hook runner. All
// Woo stores/HTTP are isolated boundaries; this is not an installed Woo checkout.
let reactBundle: string;
function reactSource() {
  if (reactBundle) return reactBundle;
  const dir = mkdtempSync(tmpdir() + '/kiriof-selection-react-');
  try {
    writeFileSync(dir + '/entry.js', `import * as React from ${JSON.stringify(root + 'node_modules/react/index.js')};import {createRoot} from ${JSON.stringify(root + 'node_modules/react-dom/client.js')};window.__React=React;window.__createRoot=createRoot;`);
    execFileSync('bun', ['build', dir + '/entry.js', '--target=browser', '--outfile=' + dir + '/bundle.js']);
    reactBundle = readFileSync(dir + '/bundle.js', 'utf8');
    return reactBundle;
  } finally { rmSync(dir, { recursive: true, force: true }); }
}
function blocksHTML() {
  return `<!doctype html><html><body><div class="wc-block-checkout"><div id="native"></div><div id="district"></div><label><input type="checkbox" id="terms">Accept terms</label><button id="place">Place order</button><p id="terms-error" role="alert" hidden></p><p id="shipping-error" role="alert" hidden></p><p id="server-error" role="alert" hidden></p></div>${scripts([
    reactSource(),
    `const R=window.__React;const listeners=new Set();let version=0;
    window.__extensions={};window.__errors={};window.__orders=0;window.__attempts=0;window.__trusted=[];window.__nativeActions=[];window.__pluginUpdates=0;window.__customerBusy=false;window.__rateBusy=false;window.__missingQuote=false;window.__holdRestore=false;window.__failRestore=false;
    const rates=[{rate_id:${JSON.stringify(cargo)},method_id:'kiriminaja-official',selected:true,label:'JNE Cargo'},{rate_id:${JSON.stringify(instant)},method_id:'kiriminaja-instant',selected:false,label:'GoSend Instant'}];
    window.__cart={needsShipping:true,shippingAddress:{country:'US',postcode:'90210',first_name:'Test',phone:'123'},extensions:{},shippingRates:[{package_id:3,shipping_rates:rates}]};
    function emit(){version++;listeners.forEach(fn=>fn());}
    function apply(rate){window.__cart={...window.__cart,shippingRates:[{package_id:3,shipping_rates:rates.filter(r=>!window.__missingQuote||r.rate_id!==${JSON.stringify(instant)}).map(r=>({...r,selected:r.rate_id===rate}))}]};emit();}
    window.__refresh=async function(){window.__customerBusy=true;emit();const response=await fetch('/rates?automatic=1');apply((await response.json()).rate);window.__customerBusy=false;emit();};
    const cartDispatch={selectShippingRate:async(rate,packageId)=>{window.__nativeActions.push([rate,packageId]);window.__rateBusy=true;emit();try{if(window.__holdRestore)await new Promise(resolve=>window.__releaseRestore=resolve);const response=await fetch('/rates?restore='+encodeURIComponent(rate));if(window.__failRestore)throw new Error('Native restore failed');apply((await response.json()).rate);}finally{window.__rateBusy=false;emit();}}};
    const stores={'wc/store/cart':{getCartData:()=>window.__cart,isShippingRateBeingSelected:()=>window.__rateBusy,isCustomerDataUpdating:()=>window.__customerBusy,hasPendingItemsOperations:()=>false},'wc/store/checkout':{prefersCollection:()=>false},'wc/store/payment':{getActivePaymentMethod:()=> 'bacs'}};
    const select=name=>stores[name];const subscribe=fn=>{listeners.add(fn);return()=>listeners.delete(fn);};
    function showErrors(){const p=document.querySelector('#shipping-error');const error=window.__errors['kiriof-shipping-selection'];p.hidden=!error;p.textContent=error?error.message:'';}
    window.wp={element:R,data:{select,subscribe,useSelect:fn=>{R.useSyncExternalStore(subscribe,()=>version);return fn(select);},dispatch:name=>name==='wc/store/checkout'?{setExtensionData:(namespace,data)=>{window.__extensions[namespace]=data;}}:name==='wc/store/cart'?cartDispatch:{setValidationErrors:errors=>{Object.assign(window.__errors,errors);showErrors();},clearValidationError:id=>{delete window.__errors[id];showErrors();}}}};
    window.wc={blocksCheckout:{registerCheckoutBlock:registration=>{window.__District=registration.component;},extensionCartUpdate:()=>{window.__pluginUpdates++;return Promise.resolve();}}};
    window.kiriofBuyerCheckoutConfig={enabled:true,map:{enabled:false},i18n:{shippingSelectionChanged:${JSON.stringify(message)}}};
    function NativeRates(){R.useSyncExternalStore(subscribe,()=>version);return R.createElement('fieldset',null,R.createElement('label',null,R.createElement('input',{id:'same-billing',type:'checkbox',defaultChecked:false,onChange:async event=>{window.__trusted.push(event.nativeEvent.isTrusted);if(event.target.checked)await window.__refresh();}}),'Use same address for billing'),R.createElement('input',{id:'billing-address',placeholder:'Billing street'}),window.__cart.shippingRates[0].shipping_rates.map(rate=>R.createElement('label',{key:rate.rate_id},R.createElement('input',{type:'radio',name:'shipping',value:rate.rate_id,checked:rate.selected,onChange:()=>{}}),rate.label)));}
    document.addEventListener('click',async event=>{if(event.target.name!=='shipping')return;window.__trusted.push(event.isTrusted);window.__rateBusy=true;emit();try{const response=await fetch('/rates?chosen='+encodeURIComponent(event.target.value));apply((await response.json()).rate);}finally{window.__rateBusy=false;emit();}},true);
    window.__createRoot(document.querySelector('#native')).render(R.createElement(NativeRates));`,
    read('assets/wp/js/kiriof-checkout-session.js'), read('assets/wp/js/kiriof-shipping-selection.js'), read('assets/wp/js/kiriof-buyer-checkout.js'),
    `window.__createRoot(document.querySelector('#district')).render(R.createElement(window.__District));
    document.querySelector('#place').onclick=async function(){window.__attempts++;
      if(!document.querySelector('#terms').checked){const p=document.querySelector('#terms-error');p.hidden=false;p.textContent='Please accept terms';return;}
      if(window.__errors['kiriof-shipping-selection'])return;
      const response=await fetch('/checkout',{method:'POST',body:JSON.stringify(window.__extensions)});if(response.ok)window.__orders++;
    };`,
  ])}</body></html>`;
}
async function open(app: any, browser: any, blocks = false) {
  const unexpected: string[] = [], requests: any[] = []; let selectedRate = cargo;
  await browser.route('**/*', (route: any) => {
    const url = new URL(route.request.url);
    if (url.pathname === '/selection') return route.fulfill({ contentType: 'text/html', body: blocks ? blocksHTML() : classicHTML() });
    if (url.pathname === '/rates') { selectedRate = url.searchParams.get('chosen') || url.searchParams.get('restore') || cargo; requests.push(url.search); return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ rate: url.searchParams.get('chosen') || url.searchParams.get('restore') || cargo }) }); }
    if (url.pathname === '/checkout') { const payload=JSON.parse(route.request.postData || '{}'); if(payload['kiriminaja-official']?.shipping_selection?.packages?.[0]?.rate_id===selectedRate)return route.fulfill({contentType:'application/json',body:'{}'});unexpected.push('checkout escaped client validation');return route.fulfill({status:409,body:message}); }
    if (url.pathname.startsWith('/assets/wp/img/couriers/')) return route.fulfill({ contentType: 'image/png', path: root + url.pathname });
    if (url.pathname !== '/favicon.ico') unexpected.push(url.href);
    return route.fulfill({ status: 409, body: 'No live requests permitted' });
  });
  await app.open('/selection');
  return { unexpected, requests };
}
const classicReview = (browser: any) => browser.evaluate(() => JSON.parse(document.querySelector<HTMLInputElement>('[name="kiriof_shipping_selection"]')!.value));
const blocksReview = (browser: any) => browser.evaluate(() => (window as any).__extensions['kiriminaja-official']?.shipping_selection);

function assertGuard(classic: boolean) {
  const result = php('checkout-shipping-selection-guard-runtime.php', { case: 'terms', classic });
  expect(result.registered).toBe(true);
  expect(result.priorities).toEqual([5, 10, 20]);
  expect(result.attempts).toEqual([{ status: 'terms', writes: [] }, { status: classic ? 0 : 409, message, writes: [] }]);
  // The fake order's writes represent both route validators/snapshot boundaries;
  // neither runs and no real order/shipment/payment is created by this fixture.
}

for (const classic of [true, false]) {
  test(`${classic ? 'Classic' : 'Blocks'} production PHP terms retry guard denies before either route writes`, async () => {
    assertGuard(classic);
  });
}

test('Classic Choices GoSend survives unchecked terms then automatic Cargo blocks retry', async ({ app, browser }) => {
  const { unexpected } = await open(app, browser);
  await expect(browser.locator('.choices__inner')).toBeVisible();
  expect(await classicReview(browser)).toEqual(snapshot(cargo));
  await browser.locator('.choices__inner').click();
  await browser.locator('.choices__list--dropdown [data-choice-selectable]').filter({ hasText: 'GoSend Instant' }).click();
  expect(await classicReview(browser)).toEqual(snapshot(instant));
  await browser.locator('button[type="submit"]').click();
  await expect(browser.locator('#terms-error')).toContainText('Please accept terms');
  expect(await classicReview(browser)).toEqual(snapshot(instant));
  expect(await browser.evaluate(() => (window as any).__orders)).toBe(0);
  await browser.locator('#terms').click();
  await browser.evaluate((html: string) => (window as any).__replace(html), table(cargo));
  await expect(browser.locator('.choices__list--single')).toContainText('JNE Cargo');
  await expect(browser.locator('.kiriof-shipping-selection-error')).toContainText(message);
  await browser.locator('button[type="submit"]').click();
  expect(await classicReview(browser)).toEqual(snapshot(instant));
  expect(await browser.evaluate(() => ({ orders: (window as any).__orders, attempts: (window as any).__attempts }))).toEqual({ orders: 0, attempts: 2 });
  assertGuard(true);
  expect(unexpected).toEqual([]);
});

test('Classic package additions and removals preserve reviewed IDs until a real Choices choice', async ({ app, browser }) => {
  const { unexpected } = await open(app, browser);
  await expect(browser.locator('.choices__inner')).toBeVisible();
  expect(await classicReview(browser)).toEqual(snapshot(cargo));
  await browser.evaluate((html: string) => (window as any).__replace(html), table(cargo, [3, 9]));
  await expect.poll(() => browser.evaluate(() => document.querySelectorAll('.choices').length)).toBe(2);
  expect(await classicReview(browser)).toEqual(snapshot(cargo));
  await expect(browser.locator('.kiriof-shipping-selection-error')).toBeVisible();
  await browser.locator('.choices__inner').first().click();
  await browser.locator('.choices__list--dropdown [data-choice-selectable]').filter({ hasText: 'GoSend Instant' }).first().click();
  expect(await classicReview(browser)).toEqual({ version: 1, packages: [{ package_id: '3', rate_id: instant }, { package_id: '9', rate_id: cargo }] });
  await browser.evaluate((html: string) => (window as any).__replace(html), table(instant));
  await expect.poll(() => browser.evaluate(() => document.querySelectorAll('.choices').length)).toBe(1);
  await expect(browser.locator('.kiriof-shipping-selection-error')).toBeVisible();
  expect((await classicReview(browser)).packages.length).toBe(2);
  expect(await browser.evaluate(() => document.querySelectorAll('[name="kiriof_shipping_selection"]').length)).toBe(1);
  expect(unexpected).toEqual([]);
});

test('Blocks production React adapter restores available native GoSend through terms error and automatic Cargo', async ({ app, browser }) => {
  const { unexpected, requests } = await open(app, browser, true);
  await expect.poll(() => blocksReview(browser)).toEqual(snapshot(cargo));
  await browser.locator(`input[value="${instant}"]`).click();
  expect(await browser.evaluate(() => (window as any).__trusted)).toEqual([true]);
  await expect.poll(() => blocksReview(browser)).toEqual(snapshot(instant));
  await browser.locator('#place').click();
  await expect(browser.locator('#terms-error')).toContainText('Please accept terms');
  expect(await blocksReview(browser)).toEqual(snapshot(instant));
  expect(await browser.evaluate(() => (window as any).__orders)).toBe(0);
  await browser.locator('#terms').click();
  await browser.evaluate(() => { (window as any).__holdRestore=true; return (window as any).__refresh(); });
  await expect(browser.locator('#shipping-error')).toContainText(message);
  expect(await browser.evaluate(() => document.querySelector<HTMLInputElement>('input[name="shipping"]:checked')!.value)).toBe(cargo);
  await browser.locator('#place').click();
  expect(await blocksReview(browser)).toEqual(snapshot(instant));
  expect(await browser.evaluate(() => ({ orders: (window as any).__orders, attempts: (window as any).__attempts, trusted: (window as any).__trusted }))).toEqual({ orders: 0, attempts: 2, trusted: [true] });
  await browser.evaluate(() => (window as any).__releaseRestore());
  await expect.poll(() => browser.evaluate(() => document.querySelector<HTMLInputElement>('input[name="shipping"]:checked')!.value)).toBe(instant);
  await expect(browser.locator('#shipping-error')).not.toBeVisible();
  await browser.locator('#place').click();
  await expect.poll(() => browser.evaluate(() => (window as any).__orders)).toBe(1);
  expect(await browser.evaluate(() => (window as any).__nativeActions)).toEqual([[instant, '3']]);
  expect(requests).toEqual(['?chosen=' + encodeURIComponent(instant), '?automatic=1', '?restore=' + encodeURIComponent(instant)]);
  assertGuard(false);
  expect(unexpected).toEqual([]);
});


test('Blocks exact unchecked billing, fill billing, choose GoSend, accept terms, same billing restores reviewed rate only', async ({ app, browser }) => {
  const { unexpected, requests } = await open(app, browser, true);
  await expect.poll(() => blocksReview(browser)).toEqual(snapshot(cargo));
  await expect.poll(() => browser.evaluate(() => (window as any).__pluginUpdates)).toBe(1);
  expect(await browser.evaluate(() => document.querySelector<HTMLInputElement>('#same-billing')!.checked)).toBe(false);
  await browser.locator('#billing-address').fill('Separate billing street');
  await browser.locator(`input[value="${instant}"]`).click();
  await expect.poll(() => blocksReview(browser)).toEqual(snapshot(instant));
  expect(requests).toEqual(['?chosen=' + encodeURIComponent(instant)]);
  expect(await browser.evaluate(() => (window as any).__nativeActions)).toEqual([]);
  await browser.locator('#terms').click();
  await browser.evaluate(() => { (window as any).__holdRestore = true; });
  await browser.locator('#same-billing').click();
  await expect.poll(() => browser.evaluate(() => (window as any).__nativeActions)).toEqual([[instant, '3']]);
  await expect(browser.locator('#shipping-error')).toContainText(message);
  expect(await blocksReview(browser)).toEqual(snapshot(instant));
  expect(await browser.evaluate(() => document.querySelector<HTMLInputElement>('input[name="shipping"]:checked')!.value)).toBe(cargo);
  await browser.locator('#place').click();
  expect(await browser.evaluate(() => (window as any).__orders)).toBe(0);
  await browser.evaluate(() => (window as any).__releaseRestore());
  await expect(browser.locator('#shipping-error')).not.toBeVisible();
  await expect.poll(() => browser.evaluate(() => document.querySelector<HTMLInputElement>('input[name="shipping"]:checked')!.value)).toBe(instant);
  await browser.locator('#place').click();
  await expect.poll(() => browser.evaluate(() => (window as any).__orders)).toBe(1);
  expect(await blocksReview(browser)).toEqual(snapshot(instant));
  expect(await browser.evaluate(() => ({ actions:(window as any).__nativeActions, trusted:(window as any).__trusted, updates:(window as any).__pluginUpdates }))).toEqual({actions:[[instant,'3']],trusted:[true,true],updates:1});
  expect(requests).toEqual(['?chosen=' + encodeURIComponent(instant), '?automatic=1', '?restore=' + encodeURIComponent(instant)]);
  expect(unexpected).toEqual([]);
});

test('Blocks missing COD quote stays blocked after same billing until explicit Cargo click; never forces nonexistent GoSend', async ({ app, browser }) => {
  const { unexpected, requests } = await open(app, browser, true);
  await expect.poll(() => blocksReview(browser)).toEqual(snapshot(cargo));
  await browser.locator('#billing-address').fill('Separate billing street');
  await browser.locator(`input[value="${instant}"]`).click();
  await expect.poll(() => blocksReview(browser)).toEqual(snapshot(instant));
  await browser.locator('#terms').click();
  await browser.evaluate(() => { (window as any).__missingQuote = true; });
  await browser.locator('#same-billing').click();
  await expect(browser.locator('#shipping-error')).toContainText(message);
  expect(await blocksReview(browser)).toEqual(snapshot(instant));
  await browser.locator('#place').click();
  expect(await browser.evaluate(() => (window as any).__orders)).toBe(0);
  expect(await browser.evaluate(() => (window as any).__nativeActions)).toEqual([]);
  // Clicking the already checked native radio is still an explicit buyer choice.
  await browser.locator(`input[value="${cargo}"]`).click();
  await expect.poll(() => blocksReview(browser)).toEqual(snapshot(cargo));
  await expect(browser.locator('#shipping-error')).not.toBeVisible();
  await browser.locator('#place').click();
  await expect.poll(() => browser.evaluate(() => (window as any).__orders)).toBe(1);
  expect(requests.some((query: string) => query.includes('restore'))).toBe(false);
  assertGuard(false); expect(unexpected).toEqual([]);
});


test('Blocks failed native restoration remains blocked without busy-marker retry loop', async ({ app, browser }) => {
  const { unexpected, requests } = await open(app, browser, true);
  await expect.poll(() => blocksReview(browser)).toEqual(snapshot(cargo));
  await browser.locator(`input[value="${instant}"]`).click();
  await expect.poll(() => blocksReview(browser)).toEqual(snapshot(instant));
  await browser.locator('#terms').click();
  await browser.evaluate(() => { (window as any).__failRestore = true; });
  await browser.locator('#same-billing').click();
  await expect.poll(() => browser.evaluate(() => (window as any).__nativeActions)).toEqual([[instant, '3']]);
  await expect(browser.locator('#shipping-error')).toContainText(message);
  await expect.poll(() => browser.evaluate(() => (window as any).__rateBusy)).toBe(false);
  await browser.evaluate(async () => { await (window as any).__refresh(); await (window as any).__refresh(); });
  await browser.locator('#place').click();
  expect(await browser.evaluate(() => (window as any).__orders)).toBe(0);
  expect(await blocksReview(browser)).toEqual(snapshot(instant));
  expect(await browser.evaluate(() => (window as any).__nativeActions)).toEqual([[instant, '3']]);
  expect(requests.filter((query: string) => query.includes('restore'))).toHaveLength(1);
  expect(unexpected).toEqual([]);
});
