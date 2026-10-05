import { test } from '@e2e-dev/web';
import { expect } from 'e2e';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
const root = fileURLToPath(new URL('../../../', import.meta.url)).replace(/\/$/, '');
function php(name: string, input: unknown) { return JSON.parse(execFileSync('php', [root + '/tests/fixtures/' + name, JSON.stringify(input)], { encoding: 'utf8' })); }
const binding = { address_1: 'Fixture street', address_2: '', city: 'Sleman', state: 'YO', postcode: '55581', country: 'ID' };
function html(enabled: boolean) {
 const fields = Object.entries(binding).map(([key,value])=>`<label>${key}<input id="billing_${key}" name="billing_${key}" value="${value}"></label>`).join('');
 const src = (file: string) => readFileSync(root + '/assets/wp/js/' + file, 'utf8');
 const jquery = readFileSync(new URL('../node_modules/jquery/dist/jquery.min.js', import.meta.url), 'utf8');
 const scripts = [jquery, `window.__requests=[];window.__geoCalls=0;window.__maps=0;
 window.kiriofClassicCheckoutConfig=${JSON.stringify({enabled,ownsDistrict:false,needsShipping:true,ajaxUrl:'/admin-ajax.php',nonce:'fixture-nonce',map:{enabled:true,tiles:'https://tiles.fixture.test/{z}/{x}/{y}.png'}})};
 Object.defineProperty(navigator,'geolocation',{value:{getCurrentPosition(ok){window.__geoCalls++;ok({coords:{latitude:-7.7,longitude:110.3}});}}});
 window.L={map(){window.__maps++;const m={setView(){return m},on(){return m},invalidateSize(){},getCenter(){return {lat:-7.7,lng:110.3}},remove(){}};return m;},tileLayer(){return {addTo(){return this},on(){}}}};
 window.fetch=async function(url,init){window.__requests.push({url,body:Object.fromEntries(new URLSearchParams(init.body))});return new Response(JSON.stringify({success:true}),{status:200,headers:{'Content-Type':'application/json'}});};
 jQuery(document.body).on('update_checkout',()=>setTimeout(()=>jQuery(document.body).trigger('updated_checkout'),0));`, src('kiriof-checkout-session.js'),src('kiriof-map-checkout.js'),src('kiriof-classic-checkout-core.js'),src('kiriof-classic-checkout.js')];
 return `<!DOCTYPE html><html><body><form class="checkout"><div class="woocommerce-billing-fields__field-wrapper">${fields}<label for="kiriof_destination_area">District</label><select id="kiriof_destination_area" name="kiriof_destination_area"><option value="123">Fixture district</option></select><input id="kiriof_destination_area_name" value="Fixture district"></div><input class="shipping_method" type="radio" value="kiriminaja-instant:1:gosend:instant" checked><button type="button" id="submit">Place order</button><output id="outcome" role="status"></output></form><script>${scripts.join('\n').replaceAll('</script','<\\/script')}</script><script>document.getElementById('submit').onclick=()=>{document.getElementById('outcome').textContent=jQuery('form.checkout').triggerHandler('checkout_place_order')===false?'blocked':'allowed';};</script></body></html>`;
}
test('seller-disabled Instant does not alter Classic district or ask for location', async ({ app, browser, screen }) => {
 await browser.route('**/*', route=>route.fulfill({contentType:'text/html',body:html(false)})); await app.open('/checkout');
 await expect(screen.getByLabel('District')).toBeVisible();
 expect(await browser.evaluate(()=>({requests:window.__requests.length,geo:window.__geoCalls,maps:window.__maps,pin:document.querySelectorAll('.kiriof-classic-pin').length}))).toEqual({requests:0,geo:0,maps:0,pin:0});
});
test('Classic pin saves only coordinates context and an address edit invalidates Instant submission', async ({ app, browser, screen }) => {
 await browser.route('**/*', route=>route.fulfill({contentType:'text/html',body:html(true)})); await app.open('/checkout');
 await expect(screen.getByRole('heading','Delivery pin')).toBeVisible();
 await expect.poll(()=>browser.evaluate(()=>window.__requests.length)).toBeGreaterThan(0);
 await expect(screen.getByLabel('District')).toBeVisible();
 const data = await browser.evaluate(()=>JSON.parse(window.__requests.at(-1).body.data));
 expect(data.action).toBe('sync_classic_pin');expect(data.destination.version).toBe(2);expect(data.destination.district_id).toBe('123');expect(Object.keys(data).sort()).toEqual(['action','address_scope','destination','effective_address']);
 await screen.getByRole('button','Place order').tap();await expect(browser.locator('#outcome')).toContainText('allowed');
 await screen.getByLabel('address_1').fill('Changed delivery address');
 await screen.getByRole('button','Place order').tap();await expect(browser.locator('#outcome')).toContainText('blocked');
});
test('business: acknowledged QRIS booking confirms once and duplicate attempts never book again', async () => {
 const r=php('instant-dispatch-runtime.php',{working_sample:true,row:{service_name:'instant'},price_service:'instant',retry:true});
 expect(r.dispatch.rows[0].status).toBe('booked');expect(r.rows[0].instant_payment_status).toBe('unpaid');expect(r.rows[0].instant_status_code).toBe(110);expect(r.books).toHaveLength(1);expect(Boolean(r.retry_error)).toBe(true);expect(r.woo[1].completions).toBe(0);
});
test('business: uncertain booking stays guarded and is not automatically resubmitted', async () => {
 const r=php('instant-dispatch-runtime.php',{timeout:true,new_retry:true});
 expect(r.dispatch.rows[0].status).toBe('unknown');expect(r.rows[0].status).toBe('pending');expect(r.books).toHaveLength(1);expect(Boolean(r.new_retry_error)).toBe(true);
});
