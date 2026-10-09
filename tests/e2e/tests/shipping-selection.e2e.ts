import { script, classicCss } from '../fixtures/buyer-source';
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

for (const activation of ['pointer', 'keyboard', 'native radio', 'native select']) {
  test(`Classic automatic GoSend remains guarded until explicit ${activation} reselect and then submits`, async ({ app, browser }) => {
    const fallback = activation.startsWith('native');
    const { unexpected } = await open(app, browser, false, fallback);
    await expect.poll(() => classicReview(browser)).toEqual(snapshot(cargo));
    await browser.locator('#terms').click();
    await browser.evaluate((html: string) => (window as any).__replace(html), table(instant));
    await expect(browser.locator('.kiriof-shipping-selection-error')).toContainText(message);
    expect(await classicReview(browser)).toEqual(snapshot(cargo));
    await browser.locator('button[type="submit"]').click();
    expect(await browser.evaluate(() => (window as any).__orders)).toBe(0);
    await browser.evaluate(() => {
      (window as any).__changes = 0;
      document.addEventListener('change', event => {
        if ((event.target as Element).matches('select.kiriof-classic-shipping-method-select')) (window as any).__changes++;
      });
      (window as any).kiriofBuyerSelectors?.refresh();
      (window as any).kiriofClassicShippingOptions.refresh();
    });
    expect(await classicReview(browser)).toEqual(snapshot(cargo));
    expect(await browser.evaluate(() => (window as any).__changes)).toBe(0);
    if (fallback) {
      if (activation === 'native radio') {
        await browser.evaluate(() => {
          // Exercise the native input name fallback, not only PHP's data-index.
          document.querySelectorAll('input.shipping_method').forEach(input => input.removeAttribute('data-index'));
        });
        await browser.locator(`input.shipping_method[value="${instant}"]`).click();
      } else {
        // A native select change must be trusted too, without bridge detail.
        await expect(browser.locator('select.kiriof-classic-shipping-method-select')).toBeVisible();
        await browser.evaluate(() => {
          const select = document.querySelector<HTMLSelectElement>('select.kiriof-classic-shipping-method-select')!;
          // A visible native listbox avoids platform-specific popup key handling.
          select.size = select.options.length;
          (window as any).__nativeSelectChanges = [];
          select.addEventListener('change', event => {
            (window as any).__nativeSelectChanges.push({ value: select.value, trusted: event.isTrusted, detail: (event as CustomEvent).detail ?? null });
            // Woo refreshes checkout after the delegated native adapter has
            // synchronized shipping_method; this isolated fixture has no Woo AJAX.
            setTimeout(() => (window as any).jQuery(document.body).trigger('updated_checkout'), 0);
          });
          select.focus();
        });
        expect(await browser.evaluate(() => document.activeElement?.matches('select.kiriof-classic-shipping-method-select'))).toBe(true);
        await browser.keyboard.press('Home');
        await expect.poll(() => classicReview(browser)).toEqual(snapshot(cargo));
        await browser.evaluate(() => document.querySelector<HTMLSelectElement>('select.kiriof-classic-shipping-method-select')!.focus());
        await browser.keyboard.press('End');
        expect(await browser.evaluate(() => (window as any).__nativeSelectChanges)).toEqual([
          { value: cargo, trusted: true, detail: null }, { value: instant, trusted: true, detail: null },
        ]);
        expect(await browser.evaluate(() => document.querySelector<HTMLInputElement>('input.shipping_method:checked')?.value)).toBe(instant);
      }
    } else {
      await expect(browser.locator('.kiriof-buyer-combobox-trigger')).toContainText('GoSend Instant');
      await browser.locator('.kiriof-buyer-combobox-trigger').click();
      if (activation === 'keyboard') {
        await browser.locator('.kiriof-buyer-combobox-input').fill('GoSend');
        await browser.keyboard.press('ArrowDown');
        await browser.keyboard.press('Enter');
      } else {
        await browser.locator('.kiriof-buyer-combobox-option').filter({ hasText: 'GoSend Instant' }).click();
      }
      expect(await browser.evaluate(() => (window as any).__changes)).toBe(1);
    }
    await expect.poll(() => classicReview(browser)).toEqual(snapshot(instant));
    await expect(browser.locator('.kiriof-shipping-selection-error')).not.toBeVisible();
    await browser.locator('button[type="submit"]').click();
    await expect.poll(() => browser.evaluate(() => (window as any).__orders)).toBe(1);
    expect(unexpected).toEqual([]);
  });
}

for (const activation of ['pointer', 'keyboard']) {
  test(`Blocks explicit ${activation} GoSend selection runs document capture before native React onChange without plugin restoration`, async ({ app, browser }) => {
    const { unexpected, requests } = await open(app, browser, true);
    await expect.poll(() => blocksReview(browser)).toEqual(snapshot(cargo));
    if (activation === 'keyboard') {
      await browser.evaluate((rate: string) => document.querySelector<HTMLInputElement>(`input[value="${rate}"]`)!.focus(), cargo);
      await browser.keyboard.press('ArrowDown');
    } else {
      await browser.locator(`input[value="${instant}"]`).click();
    }
    await expect.poll(() => blocksReview(browser)).toEqual(snapshot(instant));
    await expect.poll(() => browser.evaluate(() => (window as any).__rateBusy)).toBe(false);
    // An eager restoration here can synchronously rerender the native root and
    // swallow React's onChange before Woo has even started its own selection.
    expect(await browser.evaluate(() => (window as any).__nativeActions)).toEqual([]);
    expect(await browser.evaluate(() => (window as any).__selectionSequence)).toEqual([
      ['document capture', instant, false], ['native onChange', instant, true],
    ]);
    expect(await browser.evaluate(() => (window as any).__trusted)).toEqual([true]);
    expect(requests).toEqual(['?chosen=' + encodeURIComponent(instant)]);
    await expect(browser.locator('#shipping-error')).not.toBeVisible();
    await browser.locator('#terms').click();
    await browser.locator('#place').click();
    await expect.poll(() => browser.evaluate(() => (window as any).__orders)).toBe(1);
    expect(await blocksReview(browser)).toEqual(snapshot(instant));
    expect(await browser.evaluate(() => (window as any).__nativeActions)).toEqual([]);
    expect(unexpected).toEqual([]);
  });
}

test('matching selected GoSend never shows changed-courier warning during customer or rate updates', async ({ app, browser }) => {
  const { unexpected } = await open(app, browser, true);
  await expect.poll(() => blocksReview(browser)).toEqual(snapshot(cargo));
  await browser.locator(`input[value="${instant}"]`).click();
  await expect.poll(() => blocksReview(browser)).toEqual(snapshot(instant));
  await expect.poll(() => browser.evaluate(() => (window as any).__rateBusy)).toBe(false);
  await browser.locator('#terms').click();
  for (const key of ['__customerBusy', '__rateBusy']) {
    await browser.evaluate((name: string) => { (window as any)[name] = true; }, key);
    // Native store update notification uses the public fixture refresh boundary,
    // without selecting another courier or clearing the reviewed choice.
    await browser.evaluate(() => { (window as any).__notify(); });
    await expect(browser.locator('#shipping-error')).not.toBeVisible();
    await expect.poll(() => browser.evaluate(() => (window as any).__errors['kiriof-shipping-selection-pending'])).toEqual({message:'Updating shipping options…',hidden:true});
    expect(await blocksReview(browser)).toEqual(snapshot(instant));
    await browser.locator('#place').click();
    expect(await browser.evaluate(() => (window as any).__orders)).toBe(0);
    await browser.evaluate((name: string) => { (window as any)[name] = false; (window as any).__notify(); }, key);
    await expect.poll(() => browser.evaluate(() => Boolean((window as any).__errors['kiriof-shipping-selection-pending']))).toBe(false);
    await expect(browser.locator('#shipping-error')).not.toBeVisible();
  }
  await browser.locator('#place').click();
  await expect.poll(() => browser.evaluate(() => (window as any).__orders)).toBe(1);
  expect(await browser.evaluate(() => (window as any).__nativeActions)).toEqual([]);
  expect(unexpected).toEqual([]);
});
// Saved domestic address: zero is a valid pin, not a missing-location sentinel.
const bootstrapAddress = { address_1: 'Fixture street', address_2: '', city: 'Jakarta', state: 'JK', postcode: '12345', country: 'ID' };
const bootstrapDestination = { version: 2, district_id: '7', district_label: 'Fixture district', postcode: '12345', country: 'ID', address_type: 'shipping', destination_latitude: '0.0000000', destination_longitude: '0.0000000', shipping_address: bootstrapAddress };

for (const explicit of [false, true]) {
  test(`Blocks saved pin initial destination acknowledgement ${explicit ? 'preserves explicit Cargo intent during bootstrap' : 'seeds GoSend without editing or geolocation'}`, async ({ app, browser }) => {
    const { unexpected, requests, releaseBootstrap, checkouts } = await open(app, browser, true, false, true);
    await expect.poll(() => browser.evaluate(() => (window as any).__bootstrapStarted)).toBe(true);
    await expect(browser.locator('input[name="shipping"]:checked')).toHaveAttribute('value', cargo);
    expect(await browser.evaluate(() => (window as any).__customerBusy)).toBe(true);
    if (explicit) {
      // Already checked native radios still receive trusted clicks: capture must
      // retain this buyer intent even when Woo's selected-value effect is a no-op.
      await browser.locator(`input[value="${cargo}"]`).click();
      await expect.poll(() => blocksReview(browser)).toEqual(snapshot(cargo));
      expect(await browser.evaluate(() => (window as any).__selectionSequence)).toEqual([['document capture', cargo, false]]);
    }
    releaseBootstrap!();
    const expected = explicit ? cargo : instant;
    await expect.poll(() => browser.evaluate(() => (window as any).__bootstrapFinished)).toBe(true);
    await expect.poll(() => blocksReview(browser)).toEqual(snapshot(expected));
    await expect.poll(() => browser.evaluate(() => (window as any).__rateBusy || (window as any).__customerBusy)).toBe(false);
    await expect(browser.locator('input[name="shipping"]:checked')).toHaveAttribute('value', expected);
    await expect(browser.locator('#native-summary')).toHaveAttribute('data-rate', expected);
    expect(await browser.evaluate(() => (window as any).__extensions['kiriminaja-official'].destination)).toEqual(bootstrapDestination);
    expect(await browser.evaluate(() => (window as any).__nativeActions)).toEqual(explicit ? [[cargo, 3]] : []);
    expect(await browser.evaluate(() => (window as any).__restoreBusy)).toEqual(explicit ? [false] : []);
    expect(requests).toEqual(explicit ? ['?restore=' + encodeURIComponent(cargo)] : []);
    if (!explicit) expect(await browser.evaluate(() => (window as any).__selectionSequence)).toEqual([]);
    await expect(browser.locator('#shipping-error')).not.toBeVisible();
    await browser.locator('#terms').click();
    await browser.locator('#place').click();
    await expect.poll(() => browser.evaluate(() => (window as any).__orders)).toBe(1);
    expect(checkouts).toEqual([{ rate: expected, review: snapshot(expected), status: 0 }]);
    expect(await blocksReview(browser)).toEqual(snapshot(expected));
    expect(unexpected).toEqual([]);
  });
}

const scripts = (sources: string[]) => sources.map(source => `<script>${source.replace(/<\/script/gi, '<\\/script')}</script>`).join('');
function table(chosen: string, ids = [3]) {
  return `<table class="kiriof-classic-order-review"><tbody>${ids.map(index => php('classic-shipping-presentation-runtime.php', {
    index, chosen, rates: [{ id: cargo, label: 'JNE Cargo', cost: 15000 }, { id: instant, label: 'GoSend Instant', cost: 20000 }],
  }).html).join('')}</tbody></table>`;
}
function classicHTML(fallback = false) {
  return `<!doctype html><html><head><style>${classicCss()}${read('assets/buyer/css/kiriof-classic-choices.css')}</style></head><body>
  <form class="checkout"><div id="order_review">${table(cargo)}</div><label><input type="checkbox" id="terms">Accept terms</label><button type="submit">Place order</button></form><p id="terms-error" role="alert" hidden></p>
  ${scripts([
    readFileSync(new URL('../node_modules/jquery/dist/jquery.min.js', import.meta.url), 'utf8'),
    `window.kiriofBillingAddressConfig={i18n:{shippingSelectionChanged:${JSON.stringify(message)}}};window.kiriofClassicCheckoutConfig={enabled:true};window.__orders=0;window.__attempts=0;
    window.__replace=function(html){document.querySelector('table').outerHTML=html;jQuery(document.body).trigger('updated_checkout');};`,
    script('assets/buyer/js/kiriof-checkout-session.js'), ...(fallback ? [] : [script('assets/buyer/js/checkout/choices-controls.js')]), script('assets/buyer/js/checkout/state.js'), script('assets/buyer/js/checkout/shipping-payment.js'),
     script('assets/buyer/js/checkout/shipping-options.js'),
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
function blocksHTML(initialBootstrap = false) {
  return `<!doctype html><html><head><meta charset="utf-8"></head><body><div class="wc-block-checkout"><div id="native"></div><div id="district"></div><label><input type="checkbox" id="terms">Accept terms</label><button id="place">Place order</button><p id="terms-error" role="alert" hidden></p><p id="shipping-error" role="alert" hidden></p><p id="server-error" role="alert" hidden></p></div>${scripts([
    reactSource(),
    `const R=window.__React;const listeners=new Set();let version=0;
    window.__initialBootstrap=${JSON.stringify(initialBootstrap)};window.__bootstrapStarted=false;window.__bootstrapFinished=false;window.__restoreBusy=[];window.__extensions={};window.__errors={};window.__orders=0;window.__attempts=0;window.__trusted=[];window.__selectionSequence=[];window.__nativeActions=[];window.__pluginUpdates=0;window.__customerBusy=false;window.__rateBusy=false;window.__missingQuote=false;window.__holdRestore=false;window.__failRestore=false;
    const rates=[{rate_id:${JSON.stringify(cargo)},method_id:'kiriminaja-official',selected:true,label:'JNE Cargo'},{rate_id:${JSON.stringify(instant)},method_id:'kiriminaja-instant',selected:false,label:'GoSend Instant'}];
    window.__cart={needsShipping:true,shippingAddress:${JSON.stringify(initialBootstrap ? { ...bootstrapAddress, first_name: 'Test', last_name: 'Buyer', phone: '123' } : {country:'US',postcode:'90210',first_name:'Test',phone:'123'})},extensions:{},shippingRates:[{package_id:3,shipping_rates:rates}]};
    function emit(){version++;listeners.forEach(fn=>fn());}window.__notify=emit;
    function apply(rate){window.__cart={...window.__cart,shippingRates:[{package_id:3,shipping_rates:rates.filter(r=>!window.__missingQuote||r.rate_id!==${JSON.stringify(instant)}).map(r=>({...r,selected:r.rate_id===rate}))}]};emit();}
    window.__refresh=async function(){window.__customerBusy=true;emit();const response=await fetch('/rates?automatic=1');apply((await response.json()).rate);window.__customerBusy=false;emit();};
    const cartDispatch={selectShippingRate:async(rate,packageId)=>{window.__nativeActions.push([rate,packageId]);window.__restoreBusy.push(window.__customerBusy||window.__rateBusy);window.__rateBusy=true;emit();try{if(window.__holdRestore)await new Promise(resolve=>window.__releaseRestore=resolve);const response=await fetch('/rates?restore='+encodeURIComponent(rate));if(window.__failRestore)throw new Error('Native restore failed');apply((await response.json()).rate);}finally{window.__rateBusy=false;emit();}}};
    const stores={'wc/store/cart':{getCartData:()=>window.__cart,isShippingRateBeingSelected:()=>window.__rateBusy,isCustomerDataUpdating:()=>window.__customerBusy,hasPendingItemsOperations:()=>false},'wc/store/checkout':{prefersCollection:()=>false},'wc/store/payment':{getActivePaymentMethod:()=> 'bacs'}};
    const select=name=>stores[name];const subscribe=fn=>{listeners.add(fn);return()=>listeners.delete(fn);};
    function showErrors(){const p=document.querySelector('#shipping-error');const error=window.__errors['kiriof-shipping-selection'];p.hidden=!error;p.textContent=error?error.message:'';}
    window.wp={element:R,data:{select,subscribe,useSelect:fn=>{R.useSyncExternalStore(subscribe,()=>version);return fn(select);},dispatch:name=>name==='wc/store/checkout'?{setExtensionData:(namespace,data)=>{window.__extensions[namespace]=data;}}:name==='wc/store/cart'?cartDispatch:{setValidationErrors:errors=>{Object.assign(window.__errors,errors);showErrors();},clearValidationError:id=>{delete window.__errors[id];showErrors();}}}};
    window.wc={blocksCheckout:{registerCheckoutBlock:registration=>{window.__District=registration.component;},extensionCartUpdate:async payload=>{window.__pluginUpdates++;if(!window.__initialBootstrap||window.__pluginUpdates!==1)return;window.__bootstrapStarted=true;window.__customerBusy=true;emit();try{const response=await fetch('/cart-bootstrap',{method:'POST',body:JSON.stringify(payload)});apply((await response.json()).rate);}finally{window.__customerBusy=false;window.__bootstrapFinished=true;emit();}}}};
    window.kiriofBuyerCheckoutConfig={enabled:true,map:{enabled:false},${initialBootstrap ? `nonce:'isolated',ajaxUrl:'/saved-district',savedDestination:${JSON.stringify(bootstrapDestination)},` : ''}i18n:{shippingSelectionChanged:${JSON.stringify(message)}}};
    function NativeRates(){R.useSyncExternalStore(subscribe,()=>version);
      // WooPackageRates initializes radio state from the selected Store API rate.
      // Its per-code effect skips selection when that code is already selected.
      const selected=window.__cart.shippingRates[0].shipping_rates.find(rate=>rate.selected).rate_id;
      const [code,setCode]=R.useState(selected);
      R.useEffect(()=>{if(window.__initialBootstrap)setCode(selected);},[selected]);
      R.useEffect(()=>{if(window.__initialBootstrap&&code!==selected)cartDispatch.selectShippingRate(code,'3');},[code]);
      return R.createElement('fieldset',null,R.createElement('label',null,R.createElement('input',{id:'same-billing',type:'checkbox',defaultChecked:false,onChange:async event=>{window.__trusted.push(event.nativeEvent.isTrusted);if(event.target.checked)await window.__refresh();}}),'Use same address for billing'),R.createElement('input',{id:'billing-address',placeholder:'Billing street'}),window.__cart.shippingRates[0].shipping_rates.map(rate=>R.createElement('label',{key:rate.rate_id},R.createElement('input',{type:'radio',name:'shipping',value:rate.rate_id,checked:window.__initialBootstrap?code===rate.rate_id:rate.selected,onChange:async event=>{const chosen=event.target.value;if(window.__initialBootstrap){setCode(chosen);return;}window.__trusted.push(event.nativeEvent.isTrusted);window.__rateBusy=true;window.__selectionSequence.push(['native onChange',chosen,window.__rateBusy]);emit();try{const response=await fetch('/rates?chosen='+encodeURIComponent(chosen));apply((await response.json()).rate);}finally{window.__rateBusy=false;emit();}}}),rate.label)),R.createElement('output',{id:'native-summary','data-rate':selected},window.__cart.shippingRates[0].shipping_rates.find(rate=>rate.selected).label));}
    // Observe only: Woo's React root onChange owns busy state and the request.
    // Document capture (including the plugin listener registered below) runs first.
    document.addEventListener('click',event=>{if(event.target.name==='shipping')window.__selectionSequence.push(['document capture',event.target.value,window.__rateBusy]);},true);
    window.__createRoot(document.querySelector('#native')).render(R.createElement(NativeRates));`,
    script('assets/buyer/js/kiriof-checkout-session.js'),  script('assets/buyer/js/kiriof-buyer-checkout.js'),
    `window.__createRoot(document.querySelector('#district')).render(R.createElement(window.__District));
    document.querySelector('#place').onclick=async function(){window.__attempts++;
      if(!document.querySelector('#terms').checked){const p=document.querySelector('#terms-error');p.hidden=false;p.textContent='Please accept terms';return;}
      if(window.__errors['kiriof-shipping-selection'] || window.__errors['kiriof-shipping-selection-pending'])return;
      const response=await fetch('/checkout',{method:'POST',body:JSON.stringify(window.__extensions)});if(response.ok)window.__orders++;
    };`,
  ])}</body></html>`;
}
async function open(app: any, browser: any, blocks = false, fallback = false, initialBootstrap = false) {
  const unexpected: string[] = [], requests: any[] = [], checkouts: any[] = []; let selectedRate = cargo;
  let releaseBootstrap: (() => void) | undefined;
  const bootstrapBarrier = new Promise<void>(resolve => { releaseBootstrap = resolve; });
  await browser.route('**/*', async (route: any) => {
    const url = new URL(route.request.url);
    if (url.pathname === '/selection') return route.fulfill({ contentType: 'text/html', body: blocks ? blocksHTML(initialBootstrap) : classicHTML(fallback) });
    if (initialBootstrap && url.pathname === '/saved-district') {
      expect(new URLSearchParams(route.request.postData).get('term')).toBe(bootstrapAddress.postcode);
      return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: [{ id: '7', text: bootstrapDestination.district_label }] }) });
    }
    if (initialBootstrap && url.pathname === '/cart-bootstrap') {
      expect(JSON.parse(route.request.postData).data.destination).toEqual(bootstrapDestination);
      await bootstrapBarrier;
      selectedRate = instant;
      return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ rate: instant }) });
    }
    if (url.pathname === '/rates') { selectedRate = url.searchParams.get('chosen') || url.searchParams.get('restore') || cargo; requests.push(url.search); return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ rate: url.searchParams.get('chosen') || url.searchParams.get('restore') || cargo }) }); }
    if (url.pathname === '/checkout') { const payload=JSON.parse(route.request.postData || '{}');
      if (initialBootstrap) {
        // Reuse production PHP guard classes/hooks, then feed the actual posted
        // review and HTTP-selected server rate, not a preselected scenario match.
        const result = JSON.parse(execFileSync('php', ['-r', `
          $fixture=$argv[1]; $argv[1]='{"case":"matching","classic":false}';
          ob_start(); require $fixture; ob_end_clean();
          $input=json_decode($argv[2],true);
          $actual=$input['rate']===$instant->id?$instant:$express;
          // Actual Woo updates current keys without pruning old package choices.
          $GLOBALS['wc']=new GuardWC(array(3=>array('rates'=>array($actual->id=>$actual))),new GuardSession(array(3=>$actual->id,0=>'flat_rate:obsolete')));
          $order=new GuardOrder(array(42=>clone $actual)); $status=0;
          try { foreach($GLOBALS['hooks']['woocommerce_store_api_checkout_update_order_from_request'] as $callbacks) foreach($callbacks as $callback) $callback($order,new GuardRequest($input['review'])); }
          catch(\\Throwable $error){$status=$error->getCode();}
          echo json_encode(array('status'=>$status,'writes'=>$order->writes));
        `, root + 'tests/fixtures/checkout-shipping-selection-guard-runtime.php', JSON.stringify({ case: 'matching', classic: false, rate: selectedRate, review: payload['kiriminaja-official']?.shipping_selection })], { encoding: 'utf8' }));
        checkouts.push({ rate: selectedRate, review: payload['kiriminaja-official']?.shipping_selection, status: result.status });
        if (!result.status) expect(result.writes).toEqual(['express-validation', 'instant-validation']);
        return route.fulfill({ status: result.status || 200, contentType: 'application/json', body: JSON.stringify(result) });
      }
      if(payload['kiriminaja-official']?.shipping_selection?.packages?.[0]?.rate_id===selectedRate)return route.fulfill({contentType:'application/json',body:'{}'});unexpected.push('checkout escaped client validation');return route.fulfill({status:409,body:message}); }
    if (url.pathname.startsWith('/assets/buyer/img/couriers/')) return route.fulfill({ contentType: 'image/png', path: root + url.pathname });
    if (url.pathname !== '/favicon.ico') unexpected.push(url.href);
    return route.fulfill({ status: 409, body: 'No live requests permitted' });
  });
  await app.open('/selection');
  return { unexpected, requests, releaseBootstrap, checkouts };
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
  await expect(browser.locator('.kiriof-buyer-combobox-trigger')).toBeVisible();
  expect(await classicReview(browser)).toEqual(snapshot(cargo));
  await browser.locator('.kiriof-buyer-combobox-trigger').click();
  await browser.locator('.kiriof-buyer-combobox-option').filter({ hasText: 'GoSend Instant' }).click();
  expect(await classicReview(browser)).toEqual(snapshot(instant));
  await browser.locator('button[type="submit"]').click();
  await expect(browser.locator('#terms-error')).toContainText('Please accept terms');
  expect(await classicReview(browser)).toEqual(snapshot(instant));
  expect(await browser.evaluate(() => (window as any).__orders)).toBe(0);
  await browser.locator('#terms').click();
  await browser.evaluate((html: string) => (window as any).__replace(html), table(cargo));
  await expect(browser.locator('.kiriof-buyer-combobox-trigger')).toContainText('JNE Cargo');
  await expect(browser.locator('.kiriof-shipping-selection-error')).toContainText(message);
  await browser.locator('button[type="submit"]').click();
  expect(await classicReview(browser)).toEqual(snapshot(instant));
  expect(await browser.evaluate(() => ({ orders: (window as any).__orders, attempts: (window as any).__attempts }))).toEqual({ orders: 0, attempts: 2 });
  assertGuard(true);
  expect(unexpected).toEqual([]);
});

test('Classic package additions and removals preserve reviewed IDs until a real Choices choice', async ({ app, browser }) => {
  const { unexpected } = await open(app, browser);
  await expect(browser.locator('.kiriof-buyer-combobox-trigger')).toBeVisible();
  expect(await classicReview(browser)).toEqual(snapshot(cargo));
  await browser.evaluate((html: string) => (window as any).__replace(html), table(cargo, [3, 9]));
  await expect.poll(() => browser.evaluate(() => document.querySelectorAll('.kiriof-buyer-combobox').length)).toBe(2);
  expect(await classicReview(browser)).toEqual(snapshot(cargo));
  await expect(browser.locator('.kiriof-shipping-selection-error')).toBeVisible();
  await browser.locator('.kiriof-buyer-combobox-trigger').first().click();
  await browser.locator('.kiriof-buyer-combobox-option').filter({ hasText: 'GoSend Instant' }).first().click();
  expect(await classicReview(browser)).toEqual({ version: 1, packages: [{ package_id: '3', rate_id: instant }, { package_id: '9', rate_id: cargo }] });
  await browser.evaluate((html: string) => (window as any).__replace(html), table(instant));
  await expect.poll(() => browser.evaluate(() => document.querySelectorAll('.kiriof-buyer-combobox').length)).toBe(1);
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
  expect(await browser.evaluate(() => (window as any).__nativeActions)).toEqual([[instant, 3]]);
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
  await expect.poll(() => browser.evaluate(() => (window as any).__nativeActions)).toEqual([[instant, 3]]);
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
  expect(await browser.evaluate(() => ({ actions:(window as any).__nativeActions, trusted:(window as any).__trusted, updates:(window as any).__pluginUpdates }))).toEqual({actions:[[instant,3]],trusted:[true,true],updates:1});
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
  const requestsBeforeReselect = requests.slice();
  const trustedBeforeReselect = await browser.evaluate(() => (window as any).__trusted.slice());
  await browser.locator(`input[value="${cargo}"]`).click();
  await expect.poll(() => blocksReview(browser)).toEqual(snapshot(cargo));
  await expect(browser.locator('#shipping-error')).not.toBeVisible();
  // React does not fire onChange or write the native rate for an unchanged radio.
  expect(await browser.evaluate(() => (window as any).__trusted)).toEqual(trustedBeforeReselect);
  expect(await browser.evaluate(() => (window as any).__selectionSequence.at(-1))).toEqual(['document capture', cargo, false]);
  expect(requests).toEqual(requestsBeforeReselect);
  expect(await browser.evaluate(() => (window as any).__nativeActions)).toEqual([]);
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
  await expect.poll(() => browser.evaluate(() => (window as any).__nativeActions)).toEqual([[instant, 3]]);
  await expect(browser.locator('#shipping-error')).toContainText(message);
  await expect.poll(() => browser.evaluate(() => (window as any).__rateBusy)).toBe(false);
  await browser.evaluate(async () => { await (window as any).__refresh(); await (window as any).__refresh(); });
  await browser.locator('#place').click();
  expect(await browser.evaluate(() => (window as any).__orders)).toBe(0);
  expect(await blocksReview(browser)).toEqual(snapshot(instant));
  expect(await browser.evaluate(() => (window as any).__nativeActions)).toEqual([[instant, 3]]);
  expect(requests.filter((query: string) => query.includes('restore'))).toHaveLength(1);
  expect(unexpected).toEqual([]);
});
