import { afterAll, describe, expect, test } from 'bun:test';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { happy } from './helpers/ui-runtime';
import { join, resolve } from 'node:path';
import { compile } from 'svelte/compiler';

const runtimeTest = test;
const root = resolve(import.meta.dir, '..');
let directory: string;
let runtime: any;
afterAll(() => { if (directory) rmSync(directory, { recursive: true, force: true }); });

async function compiledRuntime() {
  // Svelte caches native DOM getters; each happy-dom Window needs an isolated bundle.
  if (directory) rmSync(directory, { recursive: true, force: true });
  directory = mkdtempSync(join(root, 'node_modules', '.instant-dialog-runtime-'));
  const put = (name: string, source: string) => writeFileSync(join(directory, name), source);
  // Only UI/transport boundaries are doubled. The production script, effects,
  // event handlers, markup, clock, token session and payment poller run unchanged.
  put('Container.svelte', `<script>let {children, ...rest} = $props();</script><div {...rest}>{@render children?.()}</div>`);
  put('Root.svelte', `<script>let {children, open} = $props();</script>{#if open}{@render children?.()}{/if}`);
  put('Label.svelte', `<script>let {children, ...rest} = $props();</script><label {...rest}>{@render children?.()}</label>`);
  put('Button.svelte', `<script>let {children, loading, variant, ...rest} = $props();</script><button {...rest}>{@render children?.()}</button>`);
  put('Checkbox.svelte', `<script>let {checked = $bindable(false), onCheckedChange, ...rest} = $props();</script><input type="checkbox" {...rest} {checked} onchange={(event) => {checked = event.currentTarget.checked; onCheckedChange?.(checked);}} />`);
  put('Icon.svelte', `<script>let {...rest} = $props();</script><svg {...rest}></svg>`);
  put('dialog.ts', `export {default as Root} from './Root.svelte'; export {default as Content, default as Header, default as Title, default as Description, default as Footer} from './Container.svelte';`);
  put('field.ts', `export {default as Field} from './Container.svelte'; export {default as Label} from './Label.svelte';`);
  put('ajax.ts', `export function postWordPressAction(...args) { return globalThis.__instantDialogAjax(...args); }`);
  put('icons.ts', `export {default as IconAlertTriangle, default as IconChevronDown, default as IconArrowUp, default as IconArrowDown} from './Icon.svelte';`);
  put('qr.ts', `export function qr() { return {destroy() {}}; }`);
  let source = readFileSync(join(root, 'src/lib/transactions/InstantProcessDialog.svelte'), 'utf8');
  const substitutions: Record<string, string> = {
    '@tabler/icons-svelte': './icons.ts', '@svelte-put/qr/svg': './qr.ts',
    '$lib/components/ui/dialog': './dialog.ts', '$lib/components/ui/field': './field.ts',
    '$lib/wordpress/ajax': './ajax.ts',
    './instant-process-session': join(root, 'src/lib/transactions/instant-process-session.ts'),
    './instant-payment-poller': join(root, 'src/lib/transactions/instant-payment-poller.ts'),
  };
  for (const [from, to] of Object.entries(substitutions)) source = source.replace(`'${from}'`, `'${to}'`);
  source = source.replace("import { Checkbox } from '$lib/components/ui/checkbox';", "import Checkbox from './Checkbox.svelte';")
    .replace("import { Button } from '$lib/components/ui/button';", "import Button from './Button.svelte';");
  put('Production.svelte', source);
  put('Host.svelte', `<script>import Production from './Production.svelte'; let {props} = $props(); let open = $state(true); let orderIds = $state(props.orderIds); export function setOpen(value) {open = value;} export function setOrderIds(value) {orderIds = value;}</script><Production {...props} {orderIds} bind:open />`);
  put('entry.ts', `export {mount, unmount, flushSync} from 'svelte'; export {default as Host} from './Host.svelte';`);
  const build = await Bun.build({ entrypoints: [join(directory, 'entry.ts')], outdir: directory, naming: 'runtime.js', target: 'browser', conditions: ['browser'], plugins: [{ name: 'real-svelte-5', setup(builder) {
    builder.onResolve({ filter: /^svelte$/ }, () => ({ path: join(root, 'node_modules/svelte/src/index-client.js') }));
    builder.onLoad({ filter: /\.svelte$/ }, async ({ path }) => ({ contents: compile(readFileSync(path, 'utf8'), { filename: path, generate: 'client' }).js.code, loader: 'js' }));
  } }] });
  if (!build.success) throw new Error(build.logs.join('\n'));
  runtime = await import(join(directory, 'runtime.js'));
  return runtime;
}

function deferred() {
  let resolve!: (value: any) => void, reject!: (reason: any) => void;
  const promise = new Promise<any>((yes, no) => { resolve = yes; reject = no; });
  return { promise, resolve, reject };
}
async function fixture() {
  const window = new happy.Window({ url: 'https://fixture.test' });
  const saved = new Map<string, PropertyDescriptor | undefined>();
  let now = 1_700_000_000_000, timerId = 0;
  const timers = new Map<number, { callback: () => void; at: number }>();
  const requests: any[] = [];
  const globals = { window, document: window.document, navigator: window.navigator, Node: window.Node, Element: window.Element, HTMLElement: window.HTMLElement, HTMLInputElement: window.HTMLInputElement, HTMLSelectElement: window.HTMLSelectElement, Text: window.Text, Comment: window.Comment, Event: window.Event, CustomEvent: window.CustomEvent, MutationObserver: window.MutationObserver, getComputedStyle: window.getComputedStyle.bind(window),
    setTimeout: (callback: () => void, delay = 0) => { timers.set(++timerId, { callback, at: now + delay }); return timerId; },
    clearTimeout: (id: number) => timers.delete(id),
    __instantDialogAjax: (action: string, values: any, options: any) => { const task = deferred(); requests.push({ action, values, options, ...task }); return task.promise; },
  };
  for (const [key, value] of Object.entries(globals)) { saved.set(key, Object.getOwnPropertyDescriptor(globalThis, key)); Object.defineProperty(globalThis, key, { configurable: true, writable: true, value }); }
  const oldNow = Date.now; Date.now = () => now;
  const r = await compiledRuntime();
  const keys = [...readFileSync(join(root, 'src/lib/transactions/InstantProcessDialog.svelte'), 'utf8').matchAll(/text\('([^']+)'\)/g)].map(match => match[1]);
  const i18n = Object.fromEntries(keys.map(key => [key, key]));
  Object.assign(i18n, { instantResult_booked: 'BOOKED', instantResult_unknown: 'UNKNOWN', instantResult_skipped: 'SKIPPED', instantTop: 'TOP', instantConfirmTop: 'I confirm this shipment', paymentMethod: 'Payment method' });
  const target = window.document.createElement('main'); window.document.body.append(target);
  const host = r.mount(r.Host, { target, props: { props: { orderIds: ['1', '2', '3', '4', '5', '6'], ajaxUrl: '/ajax', nonce: 'nonce', i18n } } });
  async function settle() { for (let i = 0; i < 8; i++) { await Promise.resolve(); r.flushSync(); } }
  await settle();
  const query = (selector: string): any => target.querySelector(selector);
  const button = (label: string): any => [...target.querySelectorAll('button')].find((node: any) => node.textContent.includes(label));
  async function click(node: any) { expect(node).toBeTruthy(); node.click(); await settle(); await advance(0); }
  async function change(selector: string, value: string) { const node = query(selector); node.value = value; node.dispatchEvent(new window.Event(selector === '#instant-pin' ? 'input' : 'change', { bubbles: true })); await settle(); }
  async function reply(data: any, index = requests.length - 1) { requests[index].resolve({ data }); await settle(); }
  async function advance(ms: number) { const end = now + ms; let steps = 0; while (true) { const next = [...timers].sort((a, b) => a[1].at - b[1].at)[0]; if (!next || next[1].at > end) break; if (++steps > 1000) throw new Error('Timer loop'); now = next[1].at; timers.delete(next[0]); next[1].callback(); await settle(); } now = end; await settle(); }
  function quote(token = 'quote-a', methods = ['top'], rows?: any[]) { return { token, expires_at: now / 1000 + 120, payment_methods: methods, batch_count: 1, rows: rows ?? Array.from({ length: 6 }, (_, i) => ({ id: String(i + 1), wc_order_number: `WC-${i + 1}`, before: 1000, after: 1200, eligible: true, changed: false, error: '' })) }; }
  let destroyed = false;
  async function destroy() { if (!destroyed) { destroyed = true; await r.unmount(host); await settle(); } }
  async function cleanup() { try { await destroy(); await advance(0); expect(timers.size).toBe(0); } finally { Date.now = oldNow; window.happyDOM.abort(); for (const [key, descriptor] of saved) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete (globalThis as any)[key]; } } }
  return { target, query, button, click, change, reply, advance, quote, requests, timers, settle, destroy, cleanup, close: async () => { host.setOpen(false); await settle(); }, setOrderIds: async (value: string[]) => { host.setOrderIds(value); await settle(); } };
}

describe('InstantProcessDialog compiled Svelte 5 lifecycle (real DOM)', () => {
  runtimeTest('TOP has no payment selector or PIN; collapsed five-row preview and selected-only totals exclude unavailable rows', async () => {
    const h = await fixture(); try {
      const q = h.quote(); q.rows[1] = { ...q.rows[1], eligible: false, before: 999999, after: null, error: 'Unavailable' };
      await h.reply(q);
      expect(h.query('#instant-payment-method')).toBeNull(); expect(h.query('#instant-pin')).toBeNull();
      expect(h.target.textContent).not.toMatch(/\bTOP\b/);
      expect(h.target.textContent).not.toContain('Payment method');
      expect(h.target.querySelectorAll('select')).toHaveLength(0);
      expect(h.target.querySelectorAll('details')).toHaveLength(5);
      expect([...h.target.querySelectorAll('details')].filter((node: any) => node.open)).toHaveLength(1);
      const totals = h.query('section[aria-label="instantShippingInformation"]');
      expect([...totals.querySelectorAll('dd')].map((node: any) => node.textContent)).toEqual(['Rp5.000', 'Rp6.000', 'Rp1.000', 'Rp6.000']);
      await h.click(h.button('instantShowMore')); expect(h.target.querySelectorAll('details')).toHaveLength(6);
      await h.click(h.query('#instant-row-1')); expect(totals.textContent).toContain('Rp4.800');
      expect(h.requests.map(request => request.action)).toEqual(['kiriof_instant_quote']);
    } finally { await h.cleanup(); }
  });
  runtimeTest('user Close during automatic refresh aborts the request and stays closed after a stale response without timers', async () => {
    const h = await fixture(); try {
      await h.reply(h.quote()); await h.advance(120000);
      expect(h.requests).toHaveLength(2);
      expect(h.query('[role="status"]')).not.toBeNull();
      expect(h.button('instantClose').disabled).toBe(false);
      await h.click(h.button('instantClose'));
      expect(h.requests[1].options.signal.aborted).toBe(true);
      expect(h.target.textContent).toBe(''); expect(h.timers.size).toBe(0);
      await h.reply(h.quote('stale-refresh'), 1); await h.advance(600000);
      expect(h.target.textContent).toBe(''); expect(h.timers.size).toBe(0);
      expect(h.requests).toHaveLength(2);
    } finally { await h.cleanup(); }
  });
  for (const invalid of ['missing token', 'invalid rows', 'invalid methods', 'invalid expiry', 'expired', 'same token'] as const) runtimeTest(`automatic refresh rejects ${invalid} response once without an infinite loop`, async () => {
    const h = await fixture(); try {
      await h.reply(h.quote()); await h.advance(120000);
      const response = h.quote('quote-b');
      if (invalid === 'missing token') response.token = '';
      if (invalid === 'invalid rows') response.rows = null as any;
      if (invalid === 'invalid methods') response.payment_methods = null as any;
      if (invalid === 'invalid expiry') response.expires_at = NaN;
      if (invalid === 'expired') response.expires_at -= 120;
      if (invalid === 'same token') response.token = 'quote-a';
      await h.reply(response, 1);
      expect(h.query('[role="alert"]').textContent).toBe('instantQuoteRefreshFailed');
      expect(h.target.querySelectorAll('details')).toHaveLength(0);
      expect(h.button('instantReview').disabled).toBe(false);
      expect(h.button('instantContinuePayment').disabled).toBe(true);
      await h.advance(600000);
      expect(h.requests).toHaveLength(2); expect(h.timers.size).toBe(0);
      await h.click(h.button('instantReview'));
      expect(h.requests).toHaveLength(3);
      await h.reply(h.quote('recovered'));
      expect(h.query('[role="alert"]')).toBeNull();
      expect(h.target.querySelectorAll('details')).toHaveLength(5);
    } finally { await h.cleanup(); }
  });
  for (const staleFirst of [true, false]) runtimeTest(`external orderIds change aborts pending quote, starts a new quote and ignores stale response ${staleFirst ? 'before' : 'after'} replacement`, async () => {
    const h = await fixture(); try {
      expect(h.requests).toHaveLength(1);
      await h.setOrderIds(['7']);
      expect(h.requests[0].options.signal.aborted).toBe(true);
      expect(h.requests).toHaveLength(2);
      expect(h.requests[1].action).toBe('kiriof_instant_quote');
      expect(h.requests[1].values).toEqual({ order_ids: '["7"]' });
      expect(h.requests[1].options.signal.aborted).toBe(false);
      const oldQuote = h.quote('old-selection');
      const nextQuote = h.quote('new-selection', ['top'], [{ ...oldQuote.rows[0], id: '7', wc_order_number: 'NEW-7' }]);
      if (staleFirst) {
        await h.reply(oldQuote, 0);
        expect(h.target.querySelectorAll('details')).toHaveLength(0);
        expect(h.query('[role="status"]')).not.toBeNull();
        expect(h.timers.size).toBe(0);
      }
      await h.reply(nextQuote, 1);
      if (!staleFirst) await h.reply(oldQuote, 0);
      expect(h.target.querySelectorAll('details')).toHaveLength(1);
      expect(h.query('#instant-row-7').checked).toBe(true);
      expect(h.query('#instant-row-1')).toBeNull();
      expect(h.target.textContent).toContain('NEW-7');
      expect(h.query('[role="alert"]')).toBeNull();
      expect(h.query('[role="status"]')).toBeNull();
      await h.advance(119000); expect(h.requests).toHaveLength(2);
      await h.advance(1000); expect(h.requests).toHaveLength(3);
      expect(h.requests[2].values).toEqual({ order_ids: '["7"]' });
    } finally { await h.cleanup(); }
  });
  runtimeTest('server 120-second deadline requotes once without dispatch; resets consent, acknowledgments and PIN while retaining exclusions', async () => {
    const h = await fixture(); try {
      const q = h.quote('quote-a', ['credit']); q.rows[0].changed = true;
      await h.reply(q); expect(h.button('instantContinuePayment').textContent).toContain('(120s)');
      await h.click(h.query('#instant-row-2')); await h.click(h.query('#instant-change-1')); await h.click(h.query('#instant-skip'));
      await h.change('#instant-pin', '123456'); await h.click(h.query('#instant-confirm'));
      expect(h.button('instantContinuePayment').disabled).toBe(false);
      await h.advance(119000); expect(h.requests).toHaveLength(1); expect(h.button('instantContinuePayment').textContent).toContain('(1s)');
      await h.advance(1000); expect(h.requests).toHaveLength(2); expect(h.requests[1].action).toBe('kiriof_instant_quote');
      await h.advance(5000); expect(h.requests).toHaveLength(2);
      const next = h.quote('quote-b', ['credit']); next.rows[0].changed = true; await h.reply(next);
      expect(h.query('#instant-row-2').checked).toBe(false); expect(h.query('#instant-row-1').checked).toBe(true);
      expect(h.query('#instant-skip').checked).toBe(true); expect(h.query('#instant-change-1').checked).toBe(false);
      expect(h.query('#instant-confirm').checked).toBe(false); expect(h.query('#instant-pin').value).toBe('');
      expect(h.button('instantContinuePayment').disabled).toBe(true);
    } finally { await h.cleanup(); }
  });
  runtimeTest('failed automatic refresh makes a single request and exposes explicit review recovery, without loops', async () => {
    const h = await fixture(); try {
      await h.reply(h.quote()); await h.advance(120000);
      h.requests[1].reject(new Error('Refresh unavailable')); await h.settle();
      expect(h.query('[role="alert"]').textContent).toBe('Refresh unavailable');
      await h.advance(600000); expect(h.requests).toHaveLength(2); expect(h.timers.size).toBe(0);
      await h.click(h.button('instantReview')); expect(h.requests).toHaveLength(3);
      await h.reply(h.quote('recovered')); expect(h.query('[role="alert"]')).toBeNull();
    } finally { await h.cleanup(); }
  });
  for (const action of ['close', 'destroy'] as const) runtimeTest(`${action} before quote response aborts and prevents stale DOM commit or timers`, async () => {
    const h = await fixture(); try {
      await h[action](); expect(h.requests[0].options.signal.aborted).toBe(true);
      await h.reply(h.quote()); await h.advance(300000);
      expect(h.target.querySelectorAll('details')).toHaveLength(0); expect(h.timers.size).toBe(0); expect(h.requests).toHaveLength(1);
    } finally { await h.cleanup(); }
  });
  runtimeTest('credit dispatch requires six digits and explicit POST intent; results stop refresh and never retry dispatch', async () => {
    const h = await fixture(); try {
      await h.reply(h.quote('credit-token', ['credit'])); await h.click(h.query('#instant-confirm'));
      for (const value of ['12345', '12a456']) { await h.change('#instant-pin', value); expect(h.button('instantContinuePayment').disabled).toBe(true); }
      expect(h.requests).toHaveLength(1);
      await h.change('#instant-pin', '123456'); expect(h.button('instantContinuePayment').disabled).toBe(false);
      await h.click(h.button('instantContinuePayment')); expect(h.requests).toHaveLength(2); expect(h.timers.size).toBe(0);
      expect(h.requests[1].action).toBe('kiriof_instant_dispatch');
      expect(h.requests[1].values).toEqual({ token: 'credit-token', order_ids: '["1","2","3","4","5","6"]', method: 'credit', pin: '123456', confirmed: 'yes' });
      await h.advance(300000); expect(h.requests).toHaveLength(2);
      await h.reply({ rows: [{ id: '1', status: 'booked', awb: 'AWB-123', message: '' }], payments: [] });
      expect(h.target.textContent).toContain('BOOKED'); expect(h.target.textContent).toContain('AWB-123'); expect(h.query('#instant-pin')).toBeNull();
      await h.advance(600000); expect(h.requests).toHaveLength(2); expect(h.button('instantContinuePayment')).toBeUndefined();
    } finally { await h.cleanup(); }
  });
  runtimeTest('dispatch network failure produces unknown results instead of automatically retrying or obtaining another quote', async () => {
    const h = await fixture(); try {
      await h.reply(h.quote()); await h.click(h.query('#instant-confirm')); await h.click(h.button('confirmProcess'));
      h.requests[1].reject(new Error('Connection lost')); await h.settle();
      expect(h.target.textContent).toContain('UNKNOWN'); expect(h.target.textContent).toContain('instantUnknown');
      await h.advance(600000); expect(h.requests).toHaveLength(2); expect(h.timers.size).toBe(0);
    } finally { await h.cleanup(); }
  });
});
