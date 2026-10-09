import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { script } from '../fixtures/buyer-source';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const scripts = (sources: string[]) => sources.map(source => `<script>${source.replace(/<\/script/gi, '<\\/script')}</script>`).join('');
let reactBundle: string;
function reactSource() {
  if (reactBundle) return reactBundle;
  const dir = mkdtempSync(tmpdir() + '/kiriof-cart-react-');
  try {
    writeFileSync(dir + '/entry.js', `import * as React from ${JSON.stringify(root + 'node_modules/react/index.js')};import {createRoot} from ${JSON.stringify(root + 'node_modules/react-dom/client.js')};window.__React=React;window.__createRoot=createRoot;`);
    execFileSync('bun', ['build', dir + '/entry.js', '--target=browser', '--outfile=' + dir + '/bundle.js']);
    return reactBundle = readFileSync(dir + '/bundle.js', 'utf8');
  } finally { rmSync(dir, { recursive: true, force: true }); }
}

// Host Woo React/store/SlotFill boundaries only. The deployed Blocks + state
// bundles and the complete enqueued legacy Cart chain run without replacement.
// This is an isolated browser fixture, not installed-WooCommerce coverage.
function html() {
  return `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>
    body { margin:16px; font:16px Arial,sans-serif; }
    .wc-block-cart { max-width:1000px; margin:auto; display:grid; grid-template-columns:2fr 1fr; gap:24px; }
    .wc-block-cart > *, .wc-block-cart__totals { min-width:0; }
    .wc-block-cart-items, .wc-block-cart__totals { padding:16px; border:1px solid #ddd; }
    .wc-block-components-totals-item { display:flex; justify-content:space-between; gap:8px; padding:12px 0; }
    button, .wc-block-cart__submit-button { display:inline-block; padding:12px; cursor:pointer; }
    .wc-block-cart__submit-button { background:#222; color:white; }
    @media(max-width:600px) { .wc-block-cart { grid-template-columns:1fr; } }
    ${readFileSync(root + 'assets/buyer/css/kiriof-buyer-checkout.css', 'utf8')}
  </style></head><body><div id="cart" class="wp-block-woocommerce-cart wc-block-cart"></div>${scripts([
    readFileSync(new URL('../node_modules/jquery/dist/jquery.min.js', import.meta.url), 'utf8'),
    reactSource(),
    `const R=window.__React;const listeners=new Set();let version=0;
    window.__writes=[];window.__nativeActions=[];window.__plugins=[];window.__blocks=[];window.__filters={};window.__mountedPlugins=[];window.__errors=[];
    window.addEventListener('error',event=>window.__errors.push(event.message));
    window.addEventListener('unhandledrejection',event=>window.__errors.push(String(event.reason)));
    window.__cart={needsShipping:true,coupons:[],items:[{key:'native-product',name:'Native product',quantity:1}],shippingAddress:{country:'ID',state:'YO',city:'Sleman',postcode:'55581',address_1:'Jalan Palagan 12'},billingAddress:{country:'ID'},shippingRates:[{package_id:0,shipping_rates:[{rate_id:'kiriminaja-official_jne_REG',method_id:'kiriminaja-official',name:'JNE Regular',selected:true,price:'18000'}]}],extensions:{},totals:{total_items:'100000',total_shipping:'18000',total_price:'118000'}};
    const emit=()=>{version++;listeners.forEach(fn=>fn());};
    const subscribe=fn=>{listeners.add(fn);return()=>listeners.delete(fn);};
    const stores={'wc/store/cart':{getCartData:()=>window.__cart,isShippingRateBeingSelected:()=>false,isCustomerDataUpdating:()=>false,hasPendingItemsOperations:()=>false},'wc/store/checkout':{prefersCollection:()=>false},'wc/store/payment':{getActivePaymentMethod:()=> 'bacs'},'core/notices':{getNotices:()=>[]}};
    const select=name=>stores[name];
    const record=(name,args)=>{window.__writes.push([name,...args]);return Promise.resolve();};
    window.wp={element:R,data:{select,subscribe,useSelect:fn=>{R.useSyncExternalStore(subscribe,()=>version);return fn(select);},dispatch:name=>({setExtensionData:(...args)=>record(name+'.setExtensionData',args),selectShippingRate:(...args)=>record(name+'.selectShippingRate',args),updateCustomerData:(...args)=>record(name+'.updateCustomerData',args),setValidationErrors:(...args)=>record(name+'.setValidationErrors',args),clearValidationError:(...args)=>record(name+'.clearValidationError',args),createNotice:(...args)=>record(name+'.createNotice',args),removeNotice:(...args)=>record(name+'.removeNotice',args),setItemQuantity:(key,quantity)=>{window.__nativeActions.push([key,quantity]);window.__cart={...window.__cart,items:window.__cart.items.map(item=>({...item,quantity})),totals:{total_items:String(quantity*100000),total_shipping:'18000',total_price:String(quantity*100000+18000)}};emit();jQuery(document.body).trigger('updated_cart_totals');return Promise.resolve();}})},plugins:{registerPlugin:(name,registration)=>window.__plugins.push({name,...registration})}};
    // Woo's plugin area only renders matching scope (or unscoped) fills. Never
    // manually render checkout-scoped coupon/destination plugins on the Cart.
    function OrderMetaSlot(){return R.createElement('div',{'data-native-order-meta-slot':'cart'},window.__plugins.filter(plugin=>!plugin.scope||plugin.scope==='woocommerce-cart').map(plugin=>{window.__mountedPlugins.push(plugin.name);return R.createElement(plugin.render,{key:plugin.name});}));}
    window.wc={blocksCheckout:{OrderMeta:props=>props.children,ExperimentalOrderMeta:props=>props.children,registerCheckoutBlock:registration=>window.__blocks.push(registration),registerCheckoutFilters:(namespace,filters)=>{window.__filters[namespace]=filters;},extensionCartUpdate:(...args)=>record('extensionCartUpdate',args)}};
    window.kiriofAjax={ajaxurl:'/wp-admin/admin-ajax.php',nonce:'isolated'};
    window.kiriofBuyerCheckoutConfig={enabled:true,nonce:'isolated',ajaxUrl:'/wp-admin/admin-ajax.php',map:{enabled:true,provider:'google',google:{apiKey:'not-a-live-key'}},savedDestination:{district_id:'46310',district_label:'Sariharjo, Ngaglik, Sleman, 55581',postcode:'55581',country:'ID',destination_latitude:'-7.71',destination_longitude:'110.37'}};
    window.kiriofBillingAddressConfig={isCart:true,isCheckout:false,globalInsurance:true,savedCheckoutPostcode:'55581',savedDistrictByPostcode:{'55581':{id:'46310',text:'Sariharjo'}},storeApiNonce:'isolated',storeApiUpdateCustomerUrl:'/wp-json/wc/store/v1/cart/update-customer',i18n:{district:'Subdistrict'}};
    window.kiriofBlockCheckoutStrings={couponApplied:'Shipping coupon %s applied'};
    function NativeCart(){R.useSyncExternalStore(subscribe,()=>version);const cart=window.__cart;const quantity=cart.items[0].quantity;const money=amount=>'Rp '+Number(amount).toLocaleString('id-ID');const row=(name,amount,cls)=>R.createElement('div',{className:'wc-block-components-totals-item '+cls},R.createElement('span',null,name),R.createElement('span',{'data-amount':amount},money(amount)));
      return R.createElement(R.Fragment,null,R.createElement('section',{className:'wc-block-cart-items'},R.createElement('h1',null,'Cart'),R.createElement('p',null,cart.items[0].name),R.createElement('div',{className:'wc-block-components-quantity-selector'},R.createElement('button',{type:'button','aria-label':'Increase quantity',onClick:()=>wp.data.dispatch('wc/store/cart').setItemQuantity(cart.items[0].key,quantity+1)},'+'),R.createElement('span',{'data-native-quantity':true},String(quantity)))),
      R.createElement('aside',{className:'wc-block-cart__totals'},R.createElement('h2',null,'Cart totals'),row('Subtotal',cart.totals.total_items,'native-subtotal'),R.createElement('div',{className:'wc-block-components-totals-shipping'},row('Shipping — JNE Regular',cart.totals.total_shipping,'native-shipping')),R.createElement(OrderMetaSlot),R.createElement('button',{type:'button',className:'wc-block-components-totals-coupon-link','aria-expanded':'false'},'Add a coupon'),row('Total',cart.totals.total_price,'native-total'),R.createElement('a',{href:'/checkout/',className:'wc-block-cart__submit-button wc-block-components-button'},'Proceed to checkout')));}
    window.__nativeRoot=window.__createRoot(document.querySelector('#cart'));`,
    script('assets/buyer/js/kiriof-checkout-session.js'),
    script('assets/buyer/js/kiriof-buyer-checkout.js'),
    script('assets/buyer/js/kj-wp-script.js'),
    script('assets/buyer/js/checkout/state.js'),
    script('assets/buyer/js/checkout/blocks-compatibility.js'),
    script('assets/buyer/js/checkout/classic-district.js'),
    script('assets/buyer/js/checkout/shipping-payment.js'),
    script('assets/buyer/js/form-billing-address.js'),
    `window.__nativeRoot.render(R.createElement(NativeCart));jQuery(function(){kiriofInitBlockCheckoutCompatibility();window.__legacyReady=true;});`,
  ])}</body></html>`;
}

// One journey, two viewport sizes, with fresh deployed assets on each open.
test('Blocks Cart stays native without checkout subdistrict, pin or bridge writes before and after totals rerender', async ({ app, browser }) => {
  const unexpected: string[] = [];
  await browser.route('**/*', (route: any) => {
    const url = new URL(route.request.url);
    if (url.pathname === '/cart-boundary') return route.fulfill({ contentType: 'text/html', body: html() });
    if (url.pathname !== '/favicon.ico') unexpected.push(route.request.method + ' ' + url.href);
    return route.fulfill({ status: 409, body: 'No lookup, live booking, map or AJAX network allowed' });
  });
  for (const width of [1200, 390]) {
    await browser.setViewport({ width, height: 1000 });
    await app.open('/cart-boundary');
    await expect.poll(() => browser.evaluate(() => (window as any).__legacyReady)).toBe(true);
    for (const quantity of [1, 2]) {
      await expect(browser.locator('[data-native-quantity]')).toHaveText(String(quantity));
      await expect(browser.locator('.native-subtotal [data-amount]')).toHaveAttribute('data-amount', String(quantity * 100000));
      await expect(browser.locator('.native-total [data-amount]')).toHaveAttribute('data-amount', String(quantity * 100000 + 18000));
      await expect(browser.locator('.native-shipping')).toContainText('JNE Regular');
      await expect(browser.locator('.native-shipping [data-amount]')).toHaveAttribute('data-amount', '18000');
      await expect(browser.locator('.wc-block-components-totals-coupon-link')).toBeVisible();
      await expect(browser.locator('[data-native-order-meta-slot]')).toHaveCount(1);
      await expect(browser.locator('input, select, textarea, [role="combobox"], .kiriof-block-district-field-wrapper, .kiriof-address-status, .kiriof-pin-map')).toHaveCount(0);
      const checkout = browser.locator('.wc-block-cart__submit-button');
      await expect(checkout).toBeVisible();
      await expect(checkout).toHaveAttribute('href', '/checkout/');
      // Keep the native link; navigation into an unsupported checkout fixture is
      // deliberately not part of this Cart-only boundary regression.
      expect(await browser.evaluate(() => {
        const w = window as any;
        return {
          buyerAbsent: typeof w.kiriofBuyerCheckout === 'undefined',
          mapsAbsent: typeof w.google === 'undefined' && typeof w.kiriofBlockMaps === 'undefined',
          compatibilityAbsent: typeof w.kiriofBlockCheckoutCompatibilityInitialized === 'undefined',
          blocks: w.__blocks.length, mountedPlugins: w.__mountedPlugins,
          destinationPlugins: w.__plugins.filter((plugin: any) => /destination|map/.test(plugin.name)).map((plugin: any) => plugin.name),
          couponPlugins: w.__plugins.filter((plugin: any) => plugin.name === 'kiriminaja-official-order-meta').map((plugin: any) => ({ name: plugin.name, scope: plugin.scope })),
          filterPresent: typeof w.__filters['kiriminaja-official']?.showApplyCouponNotice === 'function',
          nativeCouponNotice: w.__filters['kiriminaja-official']?.showApplyCouponNotice(true, {}, { couponCode: 'native-product', context: 'wc/cart' }),
          writes: w.__writes, errors: w.__errors,
          selected: w.__cart.shippingRates[0].shipping_rates.filter((rate: any) => rate.selected).map((rate: any) => rate.rate_id),
          rememberedRate: localStorage.getItem('chosen_shipping_method'),
        };
      })).toEqual({ buyerAbsent: true, mapsAbsent: true, compatibilityAbsent: true, blocks: 0, mountedPlugins: [], destinationPlugins: [], couponPlugins: [{ name: 'kiriminaja-official-order-meta', scope: 'woocommerce-checkout' }], filterPresent: true, nativeCouponNotice: true, writes: [], errors: [], selected: ['kiriminaja-official_jne_REG'], rememberedRate: null });
      if (quantity === 1) await browser.locator('[aria-label="Increase quantity"]').click();
    }
    expect(await browser.evaluate(() => (window as any).__nativeActions)).toEqual([['native-product', 2]]);
    // Flush actual jQuery legacy events and timers after the native store dispatch.
    await browser.evaluate(async () => {
      (window as any).jQuery(document.body).trigger('updated_cart_totals');
      await new Promise(resolve => setTimeout(resolve, 1600));
    });
    await expect(browser.locator('input, select, textarea, [role="combobox"]')).toHaveCount(0);
    expect(await browser.evaluate(() => ({ writes: (window as any).__writes, errors: (window as any).__errors, buyer: typeof (window as any).kiriofBuyerCheckout }))).toEqual({ writes: [], errors: [], buyer: 'undefined' });
    expect(unexpected).toEqual([]);
  }
});
