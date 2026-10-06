import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
const root = fileURLToPath(new URL('../../../', import.meta.url));
for (const width of [1200, 390, 320]) {
  test(`floating map information stays between controls at ${width}px`, async ({ app, browser }) => {
    await browser.setViewport({ width, height: 800 });
    const css=readFileSync(root+'assets/wp/css/kiriof-buyer-checkout.css','utf8');
    await browser.route('**/*',route=>route.fulfill({contentType:'text/html',body:`<!doctype html><html><head><style>body{margin:12px;font-family:sans-serif;}p{margin:1rem 0;font-size:20px}.fixture-zoom{position:absolute;left:12px;top:12px;width:30px;height:60px;background:white;z-index:2}.fixture-attribution{position:absolute;bottom:0;right:0;font-size:10px;} ${css}</style></head><body><section class="kiriof-buyer-map"><h3>Delivery pin</h3><div class="kiriof-buyer-map__viewport"><div class="kiriof-buyer-map__canvas" style="background:#e3edf0"></div><div class="fixture-zoom">+<br>−</div><div class="kiriof-buyer-map__information" role="note" tabindex="0" aria-label="Delivery pin"><p class="kiriof-buyer-map__optional">Optional. A delivery pin helps the courier find your address.</p><p class="kiriof-buyer-map__coverage">Instant coverage: 40 km straight-line from the pickup origin. Express addresses may be outside this area.</p></div><button class="kiriof-buyer-map__locate" aria-label="Current location">⌖</button><div class="kiriof-buyer-map__pin-status">✓ Pin Location</div><div class="fixture-attribution">Leaflet | OpenStreetMap contributors</div></div></section></body></html>`}));
    await app.open('/map');
    expect(await browser.evaluate(()=>{
      const rect=(s:string)=>document.querySelector(s)!.getBoundingClientRect();
      const info=rect('.kiriof-buyer-map__information'),view=rect('.kiriof-buyer-map__viewport'),zoom=rect('.fixture-zoom'),locate=rect('.kiriof-buyer-map__locate'),badge=rect('.kiriof-buyer-map__pin-status'),attribution=rect('.fixture-attribution');
      const disjoint=(a:DOMRect,b:DOMRect)=>a.right<=b.left||b.right<=a.left||a.bottom<=b.top||b.bottom<=a.top;
      const p=document.querySelector('.kiriof-buyer-map__information p')!;
      return {inside:info.left>=view.left&&info.right<=view.right&&info.top>=view.top&&info.bottom<=view.bottom,zoom:disjoint(info,zoom),locate:disjoint(info,locate),badge:disjoint(info,badge),attribution:disjoint(badge,attribution),font:getComputedStyle(p).fontSize,margin:getComputedStyle(p).marginTop,overflow:document.documentElement.scrollWidth>innerWidth};
    })).toEqual({inside:true,zoom:true,locate:true,badge:true,attribution:true,font:'12px',margin:'0px',overflow:false});
    await app.screenshot(`map-information-${width}`);
    await browser.locator('.kiriof-buyer-map__information').tap();
    await browser.keyboard.press('Tab');
    await expect(browser.locator('.kiriof-buyer-map__locate')).toBeVisible();
  });
}
