import { expect, test } from 'bun:test';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';

const root = resolve(import.meta.dir, '..');
async function fixture(savedDestination?: any, selectedDistrict = false) {
  const window = new happy.Window({ url: 'https://fixture.test' });
  const directory = mkdtempSync(join(root, 'node_modules', '.buyer-pin-runtime-'));
  const saved = new Map<string, PropertyDescriptor | undefined>();
  const maps: any[] = [], requests: any[] = [], handlers = new Map<Element, any[]>();
  let location: any, failSave = false;
  const html = (scope: string) => `<div class="woocommerce-${scope}-fields__field-wrapper">${['address_1','address_2','city','state','postcode','country'].map((key, i) => `<input id="${scope}_${key}" value="${['Street','','City','JK','12345','ID'][i]}">`).join('')}<p class="form-row"><select id="${scope === 'billing' ? 'kiriof_destination_area' : 'kiriof_shipping_destination_area'}"><option value="">Choose</option><option value="12">District</option></select></p></div>`;
  window.document.body.innerHTML = `<form class="checkout"><div class="woocommerce-billing-fields"><h3>Billing</h3><p class="form-row"><input id="billing_email" value="buyer@test.test"></p>${html('billing')}</div>${html('shipping')}<input type="checkbox" id="ship-to-different-address-checkbox"><input type="radio" class="shipping_method" checked value="kiriminaja-instant:1"></form>`;
  if (selectedDistrict) window.document.querySelector<HTMLSelectElement>('#kiriof_destination_area')!.value = '12';
  const L = { map(node: any) { const events: Record<string, any> = {}; const map = { node, events, removed: false, center: {lat: -6.2,lng:106.8}, setView() {return this;}, on(name: string, cb: any) {events[name] = cb; return this;}, getCenter() {return this.center;}, invalidateSize() {}, remove() {this.removed = true;} }; maps.push(map); return map; }, tileLayer() {return { addTo() {return this;}, on() {return this;} };} };
  (window as any).L = L;
  Object.defineProperty(window.navigator, 'geolocation', { configurable: true, value: { getCurrentPosition(ok: any) { location = ok; } } });
  (window as any).fetch = async (_url: any, options: any) => { requests.push(JSON.parse(new URLSearchParams(options.body).get('data')!)); return { ok: true, json: async () => failSave ? {success:true,data:{pin_saved:false,code:'mismatch',message:'unsafe'}} : {success:true,data:{pin_saved:true}} }; };
  (window as any).kiriofClassicCheckoutConfig = {enabled:true, ajaxUrl:'/ajax', nonce:'nonce', savedDestination, map:{enabled:true}, pinErrors:{mismatch:'Localized mismatch'}};
  const trigger = (node: Element, name: string, target?: Element) => { for (const h of handlers.get(node) || []) if (h.names.some((v: string) => v.split('.')[0] === name)) h.cb({type:name,target}); };
  (window as any).jQuery = (node: Element) => { const api = { on(names: string, selector: any, cb?: any) {const list = handlers.get(node) || []; list.push({names:names.split(' '),cb:cb || selector}); handlers.set(node,list); return api;}, off() {handlers.delete(node); return api;}, trigger(name: string) {trigger(node,name);return api;} }; return api; };
  const globals: Record<string, any> = {window, document:window.document,navigator:window.navigator,MutationObserver:window.MutationObserver,getComputedStyle:window.getComputedStyle.bind(window)};
  for (const key of ['Node','Element','HTMLElement','HTMLInputElement','HTMLSelectElement','SVGElement','DocumentFragment','Text','Comment','Event']) globals[key] = (window as any)[key];
  for (const [key,value] of Object.entries(globals)) { saved.set(key,Object.getOwnPropertyDescriptor(globalThis,key)); Object.defineProperty(globalThis,key,{value,configurable:true,writable:true}); }
  writeFileSync(join(directory,'entry.ts'), `export {startClassicPin} from ${JSON.stringify(join(root,'src/buyer/classic/pin.ts'))}; export {flushSync} from 'svelte';`);
  const result = await Bun.build({entrypoints:[join(directory,'entry.ts')],outdir:directory,naming:'runtime.js',target:'browser',conditions:['browser'],plugins:[{name:'svelte',setup(builder) {
    builder.onResolve({filter:/^svelte$/},()=>({path:join(root,'node_modules/svelte/src/index-client.js')}));
    builder.onLoad({filter:/\.svelte$/},({path})=>({contents:compile(readFileSync(path,'utf8'),{filename:path,generate:'client'}).js.code,loader:'js'}));
    builder.onLoad({filter:/\.svelte\.ts$/},async({path})=> {const plain = await Bun.Transpiler.prototype.transform.call(new Bun.Transpiler({loader:'ts'}),readFileSync(path,'utf8'));return {contents:compileModule(plain,{filename:path,generate:'client'}).js.code,loader:'js'};});
  }}]});
  if (!result.success) throw new Error(result.logs.join('\n'));
  const runtime = await import(join(directory,'runtime.js'));
  const bridge = runtime.startClassicPin(window)!;
  const settle = async () => {for (let i=0;i<8;i++) {await new Promise(r=>setTimeout(r,3)); runtime.flushSync();}};
  await settle();
  return { window, maps, requests, bridge, settle, trigger, fail:()=>{failSave=true;}, location:()=>location({coords:{latitude:-6.2,longitude:106.8}}), async cleanup() {bridge.dispose();await settle();window.happyDOM.abort();for(const [key,value] of saved) {if(value) Object.defineProperty(globalThis,key,value);else delete (globalThis as any)[key];}rmSync(directory,{recursive:true,force:true});} };
}

test('compiled pin gates geolocation, accepts suggestions before district, saves localized failures, retains map on queue changes and cleans up', async () => {
  const h = await fixture(); try {
    expect(h.maps).toHaveLength(0); h.location(); await h.settle(); expect(h.maps).toHaveLength(1);
    expect(h.bridge.controller.getState().point).toBeNull();
    h.trigger(h.window.document.body,'updated_checkout'); await h.settle();
    const district = h.window.document.querySelector<HTMLSelectElement>('#kiriof_destination_area')!; district.value='12'; h.bridge.changed(); await h.settle();
    expect(h.bridge.controller.getState().point).not.toBeNull(); expect(h.maps).toHaveLength(1);
    expect(h.requests.at(-1).action).toBe('sync_classic_pin'); expect(h.requests.at(-1).destination.version).toBe(2);
    h.trigger(h.window.document.body,'updated_checkout'); await h.settle();
    h.fail(); h.maps[0].events.movestart(); await h.settle(); expect(h.window.document.querySelector('.kiriof-classic-pin-state')?.hidden).toBe(true);
    h.maps[0].center={lat:-6.3,lng:106.9};h.maps[0].events.moveend(); await h.settle();
    expect(h.window.document.body.textContent).toContain('Localized mismatch');expect(h.window.document.body.textContent).not.toContain('unsafe');expect(h.maps).toHaveLength(1);
    const checkbox=h.window.document.querySelector<HTMLInputElement>('#ship-to-different-address-checkbox')!; checkbox.checked=true; h.bridge.changed();await h.settle();
    expect(h.maps[0].removed).toBe(true);expect(h.bridge.controller.getState().point).toBeNull();
    expect(h.window.document.querySelector('#billing_address_1')?.getAttribute('value')).toBe('Street');
  } finally {await h.cleanup();}
});

test('country and pickup hide and dispose the map without mutating native controls', async()=> {
  const h=await fixture();try {
    h.location();await h.settle();
    const method=h.window.document.querySelector<HTMLInputElement>('.shipping_method')!;method.value='local_pickup:1';h.bridge.changed();await h.settle();
    expect(h.window.document.querySelector<HTMLElement>('.kiriof-classic-pin')?.hidden).toBe(true);expect(h.maps[0].removed).toBe(true);
    method.value='kiriminaja-instant:1';const country=h.window.document.querySelector<HTMLInputElement>('#billing_country')!;country.value='US';h.bridge.changed();await h.settle();
    expect(h.window.document.querySelector<HTMLElement>('.kiriof-classic-pin')?.hidden).toBe(true);expect(country.value).toBe('US');
  }finally{await h.cleanup();}
});


test('saved version-two pin bypasses geolocation only when all six native address fields match', async () => {
  const saved = {version:2,district_id:'12',district_label:'District',country:'ID',postcode:'12345',address_type:'shipping',destination_latitude:'-6.2',destination_longitude:'106.8',shipping_address:{address_1:'Street',address_2:'',city:'City',state:'JK',postcode:'12345',country:'ID'}};
  const h = await fixture(saved, true); try {
    expect(h.bridge.controller.getState().point).not.toBeNull();
    expect(h.maps).toHaveLength(1);
  } finally { await h.cleanup(); }
  const mismatch = await fixture({...saved, shipping_address:{...saved.shipping_address, address_2:'Other'}}, true); try {
    expect(mismatch.bridge.controller.getState().point).toBeNull();
    expect(mismatch.maps).toHaveLength(0);
  } finally { await mismatch.cleanup(); }
});
