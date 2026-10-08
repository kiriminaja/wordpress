import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { readFileSync, writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const read = (path: string) => readFileSync(root + path, 'utf8');
const description = 'Instant coverage: 40 km straight-line from the pickup origin. Express addresses may be outside this area.';
let reactBundle: string;
function reactSource() {
  if (reactBundle) return reactBundle;
  const dir = mkdtempSync(tmpdir() + '/kiriof-map-information-');
  try {
    writeFileSync(dir + '/entry.js', `import * as React from ${JSON.stringify(root + 'node_modules/react/index.js')};import {createRoot} from ${JSON.stringify(root + 'node_modules/react-dom/client.js')};window.__React=React;window.__createRoot=createRoot;`);
    execFileSync('bun', ['build', dir + '/entry.js', '--target=browser', '--outfile=' + dir + '/bundle.js']);
    return reactBundle = readFileSync(dir + '/bundle.js', 'utf8');
  } finally { rmSync(dir, { recursive: true, force: true }); }
}
const scripts = (sources: string[]) => sources.map(source => `<script>${source.replace(/<\/script/gi, '<\\/script')}</script>`).join('');

function html(provider: 'leaflet' | 'google', coverage: boolean) {
  return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>
    body{margin:12px;font-family:sans-serif}p{margin:1rem 0;font-size:20px}
    ${provider === 'leaflet' ? read('assets/lib/leaflet/leaflet.css') : ''}
    ${read('assets/buyer/css/kiriof-buyer-checkout.css')}
    .fixture-zoom{position:absolute;left:12px;top:12px;width:30px;height:60px;background:white}.fixture-attribution{position:absolute;bottom:0;right:0;font-size:10px}
    </style></head><body><div id="map"></div>${scripts([
    reactSource(),
    `window.__runtimeErrors=[];window.addEventListener('error',e=>window.__runtimeErrors.push(e.message));window.addEventListener('unhandledrejection',e=>window.__runtimeErrors.push(String(e.reason)));
    window.__geoCalls=0;Object.defineProperty(navigator,'geolocation',{value:{getCurrentPosition(ok){window.__geoCalls++;ok({coords:{latitude:-7.7,longitude:110.3}})}}});
    const R=window.__React;const cart={needsShipping:true,shippingAddress:{country:'ID',address_1:'Fixture address'}};
    window.wp={element:R,data:{useSelect:fn=>fn(name=>name==='wc/store/cart'?{getCartData:()=>cart}:{prefersCollection:()=>false})}};
    window.wc={blocksCheckout:{registerCheckoutBlock:registration=>{if(registration.metadata.name==='kiriminaja-official/map-checkout')window.__MapControl=registration.component;}}};
    window.kiriofBuyerCheckout={getCoordinates:()=>null,setCoordinates:(address,point)=>{window.__selected=point;return true;}};
    window.kiriofMapCheckoutConfig=${JSON.stringify({ enabled: true, provider, apiKey: provider === 'google' ? 'AIza-synthetic_map_information_key123' : '', tiles: 'https://tiles.fixture.test/{z}/{x}/{y}.png', attribution: 'Fixture tiles', coverage: coverage ? { origin: { latitude: -7.7, longitude: 110.3 }, radiusMeters: 40000 } : null, i18n: { mapTitle: 'Delivery pin', mapHelp: 'Delivery location map', mapKeyboard: 'Use arrows to move', mapLocate: 'Current location', mapLocating: 'Locating', mapCoverage: description, mapCoverageBadge: 'Instant ≤ 40 km', mapOptional: 'Optional. A delivery pin helps the courier find your address.', pinLocation: 'Pin Location', needPinLocation: 'Need Pin Location', mapUnavailable: 'Map unavailable' } })};`,
    ...(provider === 'leaflet' ? [read('assets/lib/leaflet/leaflet.js'), `const nativeMap=L.map;L.map=(...args)=>window.__map=nativeMap(...args);`] : []),
    read('assets/buyer/dist/kiriminaja-buyer-blocks.js'),
    `window.__createRoot(document.querySelector('#map')).render(R.createElement(window.__MapControl));`,
  ])}</body></html>`;
}

// SDK boundary only: the real Google loader, registry, adapter and Blocks/Svelte
// presentation run in Chromium. Controls are representative SDK DOM, not live Google.
function googleSDK(callback: string) {
  return `(()=>{class FixtureMap{constructor(node,options){this.center=options.center;this.listeners={};window.__map=this;node.innerHTML='<div class="fixture-zoom">+<br>−</div><div class="fixture-attribution">Google fixture</div>';setTimeout(()=>this.fire('idle'),0)}addListener(name,cb){(this.listeners[name]??=[]).push(cb);return{remove:()=>this.listeners[name]=this.listeners[name].filter(x=>x!==cb)}}fire(name,event){for(const cb of this.listeners[name]||[])cb(event)}setCenter(center){this.center=center;this.fire('center_changed');setTimeout(()=>this.fire('idle'),0)}getCenter(){return{lat:()=>this.center.lat,lng:()=>this.center.lng}}setZoom(){}}class Overlay{setMap(){}}window.google={maps:{Map:FixtureMap,Polyline:Overlay,event:{trigger:(map,name)=>map.fire(name),clearInstanceListeners:map=>map.listeners={}}}};window[${JSON.stringify(callback)}]();})();`;
}

for (const { provider, width, coverage } of [
  { provider: 'leaflet', width: 1200, coverage: true },
  { provider: 'leaflet', width: 390, coverage: true },
  { provider: 'google', width: 320, coverage: true },
  { provider: 'leaflet', width: 320, coverage: false },
] as const) {
  test(`${provider} compact map information at ${width}px${coverage ? '' : ' without known coverage'}`, async ({ app, browser }) => {
    await browser.setViewport({ width, height: 800 });
    const unexpected: string[] = [], googleRequests: string[] = [];
    await browser.route('**/*', (route: any) => {
      const url = new URL(route.request.url);
      if (url.origin === 'https://fixture.test' && url.pathname === '/map') return route.fulfill({ contentType: 'text/html', body: html(provider, coverage) });
      if (url.origin === 'https://maps.googleapis.com' && url.pathname === '/maps/api/js') {
        googleRequests.push(url.href);
        return route.fulfill({ contentType: 'text/javascript', body: googleSDK(url.searchParams.get('callback')!) });
      }
      if (url.origin === 'https://tiles.fixture.test') return route.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#e3edf0"/></svg>' });
      if (url.pathname !== '/favicon.ico') unexpected.push(url.href);
      return route.fulfill({ status: 409, body: 'No live network permitted' });
    });
    await app.open('/map');
    const status = browser.locator('.kiriof-buyer-map__pin-status');
    await expect(status).toContainText('Pin Location');
    await expect.poll(() => browser.evaluate(() => document.querySelector('.kiriof-buyer-map__pin-status')?.classList.contains('is-complete'))).toBe(true);
    expect(await browser.evaluate(() => ({ optional: document.querySelectorAll('.kiriof-buyer-map__optional').length, optionalText: document.querySelector('.kiriof-buyer-map')!.textContent!.includes('Optional. A delivery pin'), paragraphs: document.querySelectorAll('.kiriof-buyer-map__information p').length, information: document.querySelectorAll('.kiriof-buyer-map__information').length }))).toEqual({ optional: 0, optionalText: false, paragraphs: 0, information: coverage ? 1 : 0 });
    if (coverage) {
      await expect(browser.locator('.kiriof-buyer-map__coverage')).toHaveText('Instant ≤ 40 km');
      expect(await browser.evaluate(() => { const badge=document.querySelector('.kiriof-buyer-map__coverage')!;return {title:badge.getAttribute('title'),label:badge.getAttribute('aria-label')}; })).toEqual({ title: description, label: description });
    }
    expect(await browser.evaluate(() => {
      const rect=(selector:string)=>document.querySelector(selector)!.getBoundingClientRect();
      const view=rect('.kiriof-buyer-map__canvas'),status=rect('.kiriof-buyer-map__pin-status'),locate=rect('.kiriof-buyer-map__locate');
      const indicator=document.querySelector('.kiriof-buyer-map__indicator')!;
      const svg=indicator.querySelector('svg') as SVGSVGElement;
      const point=svg.createSVGPoint();point.x=16;point.y=16;
      const center=point.matrixTransform(svg.getScreenCTM()!);point.y=44;const tip=point.matrixTransform(svg.getScreenCTM()!);
      const label=document.querySelector('.kiriof-buyer-map__pin-status span')!;
      const info=document.querySelector('.kiriof-buyer-map__information')?.getBoundingClientRect();
      const zoom=rect('.leaflet-control-zoom, .fixture-zoom');
      const disjoint=(a:DOMRect,b:DOMRect)=>a.right<=b.left||b.right<=a.left||a.bottom<=b.top||b.bottom<=a.top;
      return {nested:indicator.contains(document.querySelector('.kiriof-buyer-map__pin-status')),size:[status.width,status.height],circleCentered:Math.abs((status.left+status.right)/2-center.x)<1&&Math.abs((status.top+status.bottom)/2-center.y)<1,tipCentered:Math.abs(tip.x-(view.left+view.right)/2)<1&&Math.abs(tip.y-(view.top+view.bottom)/2)<1,label:label.textContent,screenReaderOnly:label.getBoundingClientRect().width===1&&getComputedStyle(label).clipPath==='inset(50%)',live:label.parentElement!.getAttribute('aria-live'),compact:!info||(info.height<=44&&info.width<180),controls:disjoint(status,zoom)&&disjoint(status,locate)&&(!info||(disjoint(info,zoom)&&disjoint(info,locate)&&info.left>=view.left&&info.right<=view.right&&info.top>=view.top&&info.bottom<=view.bottom)),overflow:document.documentElement.scrollWidth>innerWidth};
    })).toEqual({ nested:true,size:[16,16],circleCentered:true,tipCentered:true,label:'Pin Location',screenReaderOnly:true,live:'polite',compact:true,controls:true,overflow:false });
    await app.screenshot(`map-information-${provider}-${width}${coverage ? '' : '-unknown'}`);
    // Invoke SDK movement events, never set hidden attributes directly. Production
    // adapter callbacks must hide both the badge and its live accessible label.
    await browser.evaluate(() => { const map=(window as any).__map;map.fire((window as any).google?'dragstart':'movestart');return true; });
    await expect(status).not.toBeVisible();
    if (coverage) await expect(browser.locator('.kiriof-buyer-map__information')).not.toBeVisible();
    expect(await browser.evaluate(() => document.querySelector('.kiriof-buyer-map__pin-status')!.hasAttribute('hidden'))).toBe(true);
    await browser.evaluate(() => { const map=(window as any).__map;map.fire((window as any).google?'idle':'moveend');return true; });
    await expect(status).toBeVisible();
    if (coverage) await expect(browser.locator('.kiriof-buyer-map__information')).toBeVisible();
    const before=await browser.evaluate(() => (window as any).__geoCalls);
    await browser.locator('.kiriof-buyer-map__locate').tap();
    await expect.poll(() => browser.evaluate(() => (window as any).__geoCalls)).toBeGreaterThan(before);
    expect(googleRequests).toHaveLength(provider==='google'?1:0);
    if (provider==='google') expect(await browser.evaluate(() => typeof (window as any).L)).toBe('undefined');
    expect(unexpected).toEqual([]);
    expect(await browser.evaluate(() => (window as any).__runtimeErrors)).toEqual([]);
  });
}
