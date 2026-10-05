import { afterAll, expect, test } from 'bun:test';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { resolve, join } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';
const root=resolve(import.meta.dir,'..');
let buildDir: string | undefined;
let bundle: Promise<Buffer> | undefined;
afterAll(()=>{if(buildDir)rmSync(buildDir,{recursive:true,force:true});});
async function build(){
 const dir=buildDir=mkdtempSync(join(root,'node_modules','.instant-pay-build-'));
 const put=(name:string,source:string)=>writeFileSync(join(dir,name),source);
 put('Dialog.svelte',`<script>let {open,children,onPrimary,primaryDisabled,primaryLabel,secondaryLabel,onOpenChange}= $props();</script>{#if open}<div data-dialog>{@render children?.()}<button disabled={primaryDisabled} onclick={onPrimary}>{primaryLabel}</button><button onclick={()=>onOpenChange(false)}>{secondaryLabel}</button></div>{/if}`);
 put('ajax.ts',`export async function postWordPressAction(...args){return globalThis.__paymentRequest(...args);}`);
 put('qr.ts',`export function qr(node,options){node.setAttribute('data-qr',options.data);return {update(next){node.setAttribute('data-qr',next.data);},destroy(){}};}`);
 put('Production.svelte',readFileSync(join(root,'src/lib/payments/InstantScanToPayDialog.svelte'),'utf8').replace("'$lib/ui/KiriofDialog.svelte'","'./Dialog.svelte'").replace("'$lib/wordpress/ajax'","'./ajax.ts'").replace("'@svelte-put/qr/svg'","'./qr.ts'"));
 put('Host.svelte',`<script>import Production from './Production.svelte';let open=$state(true);export function close(){open=false;}</script><Production bind:open paymentId="PAY-1" orderIds={['KA-1','KA-2']} ajaxUrl="/ajax" nonce="nonce" i18n={{}} />`);
 put('entry.ts',`export {mount,unmount,flushSync} from 'svelte'; export {default as Host} from './Host.svelte';`);
 const result=await Bun.build({entrypoints:[join(dir,'entry.ts')],outdir:dir,naming:'runtime.js',target:'browser',conditions:['browser'],plugins:[{name:'svelte',setup(b){
 b.onResolve({filter:/^\$lib\/utils(?:\.js)?$/},()=>({path:join(root,'src/lib/utils.ts')}));
 b.onResolve({filter:/^\$lib\/components\/ui\//},({path})=>({path:join(root,'src/lib',path.slice(5).replace(/\/index\.js$/,''),'index.ts')}));
 b.onResolve({filter:/^\$lib\/transactions\//},({path})=>({path:join(root,'src/lib',path.slice(5)+'.ts')}));
 b.onResolve({filter:/^svelte$/},()=>({path:join(root,'node_modules/svelte/src/index-client.js')}));
 b.onLoad({filter:/\.svelte\.[jt]s$/},({path})=>({contents:compileModule(path.endsWith('.ts')?new Bun.Transpiler({loader:'ts'}).transformSync(readFileSync(path,'utf8')):readFileSync(path,'utf8'),{filename:path,generate:'client'}).js.code,loader:'js'}));
 b.onLoad({filter:/\.svelte$/},({path})=>({contents:compile(readFileSync(path,'utf8'),{filename:path,generate:'client'}).js.code,loader:'js'}));
 }}]});
 if(!result.success)throw new Error(result.logs.join('\n'));return readFileSync(join(dir,'runtime.js'));
}
async function fixture(){
 const win=new happy.Window({url:'https://fixture.test'});
 const saved=new Map<string,PropertyDescriptor|undefined>();
 const requests:any[]=[];const timers=new Map<number,{at:number,fn:()=>void}>();let now=1700000000000,counter=0;
 const globals={window:win,document:win.document,navigator:win.navigator,Node:win.Node,Element:win.Element,HTMLElement:win.HTMLElement,HTMLButtonElement:win.HTMLButtonElement,SVGElement:win.SVGElement,DocumentFragment:win.DocumentFragment,Text:win.Text,Comment:win.Comment,Event:win.Event,CustomEvent:win.CustomEvent,MutationObserver:win.MutationObserver,getComputedStyle:win.getComputedStyle.bind(win),requestAnimationFrame:win.requestAnimationFrame.bind(win),cancelAnimationFrame:win.cancelAnimationFrame.bind(win),setTimeout:(fn:()=>void,delay:number)=>{const id=++counter;timers.set(id,{at:now+(delay ?? 0),fn});return id;},clearTimeout:(id:number)=>timers.delete(id),__paymentRequest:(...args:any[])=>new Promise((resolve,reject)=>requests.push({args,resolve,reject}))};
 for(const [k,v] of Object.entries(globals)){saved.set(k,Object.getOwnPropertyDescriptor(globalThis,k));Object.defineProperty(globalThis,k,{configurable:true,writable:true,value:v});}
 const oldNow=Date.now;Date.now=()=>now;
 const dir=mkdtempSync(join(root,'node_modules','.instant-pay-runtime-'));writeFileSync(join(dir,'runtime.js'),await(bundle??=build()));
 const r=await import(join(dir,'runtime.js'));const target=win.document.createElement('main');win.document.body.append(target);const host=r.mount(r.Host,{target});
 async function settle(){for(let i=0;i<20;i++){await Promise.resolve();r.flushSync();}}
 async function advance(ms:number){const end=now+ms;for(let i=0;i<100;i++){const next=[...timers].filter(([,v])=>v.at<=end).sort((a,b)=>a[1].at-b[1].at)[0];if(!next)break;now=next[1].at;timers.delete(next[0]);next[1].fn();await settle();}now=end;await settle();}
 await settle();return {target,requests,settle,advance,close:async()=>{host.close();await settle();},reply:async(data:any)=>{requests.at(-1).resolve({status:200,data});await settle();},cleanup:async()=>{await r.unmount(host);await settle();await advance(0);expect(timers.size).toBe(0);Date.now=oldNow;win.happyDOM.abort();for(const[k,d]of saved){if(d)Object.defineProperty(globalThis,k,d);else delete(globalThis as any)[k];}rmSync(dir,{recursive:true,force:true});}};
}
test('Instant Scan to Pay loads its own authenticated group, retains QR on refresh and closes on paid',async()=>{
 const h=await fixture();try{
 await h.advance(6500);expect(h.requests[0].args[0]).toBe('kiriof_instant_payment');expect(h.requests[0].args[1]).toEqual({payment_id:'PAY-1',order_ids:'["KA-1","KA-2"]'});expect(h.requests[0].args[2].nonce).toBe('nonce');
 await h.reply({id:'PAY-1',status:'unpaid',amount:12000,qr_content:'booking-qr'});expect(h.target.querySelector('svg[data-qr]')?.getAttribute('data-qr')).toBe('booking-qr');
 (h.target.querySelector('button') as HTMLButtonElement).click();await h.settle();await h.advance(6500);await h.reply({id:'PAY-1',status:'unpaid',amount:null,qr_content:''});expect(h.target.textContent).toContain('Rp12.000');expect(h.target.querySelector('svg[data-qr]')).not.toBeNull();
 await h.advance(15000);expect(h.requests).toHaveLength(3);await h.reply({id:'PAY-1',status:'paid',amount:12000,qr_content:''});expect(h.target.querySelector('[data-dialog]')).toBeNull();expect(h.requests).toHaveLength(3);
 }finally{await h.cleanup();}
});
test('close aborts pending Instant payment lookup and stale response cannot show QR',async()=>{
 const h=await fixture();try{await h.advance(6500);await h.close();expect(h.requests[0].args[2].signal.aborted).toBe(true);await h.reply({id:'PAY-1',status:'unpaid',amount:1,qr_content:'stale'});expect(h.target.textContent).toBe('');}finally{await h.cleanup();}
});
test('missing booking QR has safe unavailable state and mismatch is never shown',async()=>{
 const h=await fixture();try{await h.advance(6500);await h.reply({id:'WRONG',status:'unpaid',amount:1,qr_content:'wrong'});expect(h.target.querySelector('svg[data-qr]')).toBeNull();await h.advance(13000);(h.target.querySelector('button') as HTMLButtonElement).click();await h.settle();await h.advance(6500);await h.reply({id:'PAY-1',status:'unpaid',amount:null,qr_content:''});expect(h.target.textContent).toContain('no longer available');expect(h.target.querySelector('svg[data-qr]')).toBeNull();}finally{await h.cleanup();}
});
