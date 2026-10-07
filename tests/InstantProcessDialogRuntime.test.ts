import { afterAll, describe, expect, test } from 'bun:test';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { happy } from './helpers/ui-runtime';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';

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
  put('Footer.svelte', `<script>let {children} = $props();</script><div data-slot="dialog-footer">{@render children?.()}</div>`);
  put('dialog.ts', `export {default as Root} from './Root.svelte'; export {default as Content, default as Header, default as Title, default as Description} from './Container.svelte'; export {default as Footer} from './Footer.svelte';`);
  put('field.ts', `export {default as Field} from './Container.svelte'; export {default as Label} from './Label.svelte';`);
  put('ajax.ts', `export function postWordPressAction(...args) { return globalThis.__instantDialogAjax(...args); }`);
  put('icons.ts', `export {default as IconAlertTriangle, default as IconChevronDown, default as IconArrowUp, default as IconArrowDown, default as IconLoader2, default as IconCreditCard, default as IconQrcode, default as IconMinus, default as IconLoader} from './Icon.svelte';`);
  put('qr.ts', `export function qr(node, options) { node.setAttribute('data-test-qr', options.data); return {update(next) { node.setAttribute('data-test-qr', next.data); }, destroy() {}}; }`);
  let source = readFileSync(join(root, 'src/lib/transactions/InstantProcessDialog.svelte'), 'utf8');
  const substitutions: Record<string, string> = {
    '@tabler/icons-svelte': './icons.ts', '@svelte-put/qr/svg': './qr.ts',
    '$lib/components/ui/dialog': './dialog.ts', '$lib/components/ui/field': join(root, 'src/lib/components/ui/field/index.ts'),
    '$lib/components/ui/radio-group': join(root, 'src/lib/components/ui/radio-group/index.ts'),
    '$lib/utils': join(root, 'src/lib/utils.ts'),
    '$lib/payments/CreditPinInput.svelte': join(root, 'src/lib/payments/CreditPinInput.svelte'),
    '$lib/payments/PaymentMethodSelector.svelte': join(root, 'src/lib/payments/PaymentMethodSelector.svelte'),
    '$lib/wordpress/ajax': './ajax.ts',
    '$lib/components/ui/alert': join(root, 'src/lib/components/ui/alert/index.ts'),
    '$lib/components/ui/collapsible': join(root, 'src/lib/components/ui/collapsible/index.ts'),
    '$lib/components/ui/spinner': join(root, 'src/lib/components/ui/spinner/index.ts'),
    '$lib/payments/ShipmentSummarySkeleton.svelte': join(root, 'src/lib/payments/ShipmentSummarySkeleton.svelte'),
    '$lib/payments/ShipmentOperationProgress.svelte': join(root, 'src/lib/payments/ShipmentOperationProgress.svelte'),
    '$lib/components/ui/button': join(root, 'src/lib/components/ui/button/index.ts'),
    './instant-process-session': join(root, 'src/lib/transactions/instant-process-session.ts'),
    './instant-payment-poller': join(root, 'src/lib/transactions/instant-payment-poller.ts'),
  };
  for (const [from, to] of Object.entries(substitutions)) source = source.replace(`'${from}'`, `'${to}'`);
  source = source.replace("import { Checkbox } from '$lib/components/ui/checkbox';", "import Checkbox from './Checkbox.svelte';");
  put('Production.svelte', source);
  put('Host.svelte', `<script>import Production from './Production.svelte'; let {props} = $props(); let open = $state(true); let orderIds = $state(props.orderIds); export function setOpen(value) {open = value;} export function setOrderIds(value) {orderIds = value;}</script><Production {...props} {orderIds} bind:open />`);
  put('entry.ts', `export {mount, unmount, flushSync} from 'svelte'; export {default as Host} from './Host.svelte';`);
  const build = await Bun.build({ entrypoints: [join(directory, 'entry.ts')], outdir: directory, naming: 'runtime.js', target: 'browser', conditions: ['browser'], plugins: [{ name: 'real-svelte-5', setup(builder) {
    builder.onResolve({ filter: /^\$lib\/utils\.js$/ }, () => ({ path: join(root, 'src/lib/utils.ts') }));
    builder.onResolve({ filter: /^\$lib\/utils$/ }, () => ({ path: join(root, 'src/lib/utils.ts') }));
    builder.onResolve({ filter: /^\$lib\/components\/ui\// }, (args) => ({ path: join(root, 'src/lib/components/ui', args.path.split('/')[3]!, 'index.ts') }));
    builder.onResolve({ filter: /^@tabler\/icons-svelte$/ }, () => ({ path: join(directory, 'icons.ts') }));
    builder.onResolve({ filter: /^svelte$/ }, () => ({ path: join(root, 'node_modules/svelte/src/index-client.js') }));
    builder.onLoad({ filter: /\.svelte\.[jt]s$/ }, async ({ path }) => {
      const input = readFileSync(path, 'utf8');
      const js = path.endsWith('.ts') ? new Bun.Transpiler({ loader: 'ts' }).transformSync(input) : input;
      return { contents: compileModule(js, { filename: path, generate: 'client' }).js.code, loader: 'js' };
    });
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
  const globals = { window, document: window.document, navigator: window.navigator, Node: window.Node, Element: window.Element, HTMLElement: window.HTMLElement, HTMLButtonElement: window.HTMLButtonElement, SVGElement: window.SVGElement, DocumentFragment: window.DocumentFragment, HTMLInputElement: window.HTMLInputElement, HTMLSelectElement: window.HTMLSelectElement, Text: window.Text, Comment: window.Comment, Event: window.Event, CustomEvent: window.CustomEvent, MutationObserver: window.MutationObserver, ResizeObserver: window.ResizeObserver, getComputedStyle: window.getComputedStyle.bind(window),
    requestAnimationFrame: window.requestAnimationFrame.bind(window), cancelAnimationFrame: window.cancelAnimationFrame.bind(window),
    setTimeout: (callback: () => void, delay = 0) => { timers.set(++timerId, { callback, at: now + delay }); return timerId; },
    clearTimeout: (id: number) => timers.delete(id),
    __instantDialogAjax: (action: string, values: any, options: any) => { const task = deferred(); requests.push({ action, values, options, ...task }); return task.promise; },
  };
  for (const [key, value] of Object.entries(globals)) { saved.set(key, Object.getOwnPropertyDescriptor(globalThis, key)); Object.defineProperty(globalThis, key, { configurable: true, writable: true, value }); }
  const oldNow = Date.now; Date.now = () => now;
  const r = await compiledRuntime();
  const keys = [...readFileSync(join(root, 'src/lib/transactions/InstantProcessDialog.svelte'), 'utf8').matchAll(/text\('([^']+)'\)/g)].map(match => match[1]);
  const i18n = Object.fromEntries(keys.map(key => [key, key]));
  Object.assign(i18n, { creditDescription: 'Remaining Credit', instantResult_booked: 'BOOKED', instantResult_failed: 'FAILED', instantResult_unknown: 'UNKNOWN', instantResult_skipped: 'SKIPPED', instantTop: 'TOP', instantConfirmTop: 'I confirm this shipment', paymentMethod: 'Payment method' });
  const target = window.document.createElement('main'); window.document.body.append(target);
  const host = r.mount(r.Host, { target, props: { props: { orderIds: ['1', '2', '3', '4', '5', '6'], ajaxUrl: '/ajax', nonce: 'nonce', i18n } } });
  async function settle() { for (let i = 0; i < 8; i++) { await Promise.resolve(); r.flushSync(); } }
  await settle();
  const query = (selector: string): any => target.querySelector(selector);
  const button = (label: string): any => [...target.querySelectorAll('button')].find((node: any) => node.textContent.includes(label));
  async function click(node: any) { expect(node).toBeTruthy(); node.click(); await settle(); await advance(0); }
  async function change(selector: string, value: string) { const node = query(selector); node.value = value; node.dispatchEvent(new window.Event(selector === '#instant-credit-pin' ? 'input' : 'change', { bubbles: true })); await settle(); }
  async function reply(data: any, index = requests.length - 1) { requests[index].resolve({ data }); await settle(); }
  async function advance(ms: number) { const end = now + ms; let steps = 0; while (true) { const next = [...timers].sort((a, b) => a[1].at - b[1].at)[0]; if (!next || next[1].at > end) break; if (++steps > 1000) throw new Error('Timer loop'); now = next[1].at; timers.delete(next[0]); next[1].callback(); await settle(); } now = end; await settle(); }
  function quote(token = 'quote-a', methods = ['top'], rows?: any[]) { return { token, expires_at: now / 1000 + 120, payment_methods: methods, credit_balance: 4000724100, batch_count: 1, rows: rows ?? Array.from({ length: 6 }, (_, i) => ({ id: String(i + 1), wc_order_number: `WC-${i + 1}`, before: 1000, after: 1000, eligible: true, changed: false, error: '' })) }; }
  let destroyed = false;
  async function destroy() { if (!destroyed) { destroyed = true; await r.unmount(host); await settle(); } }
  async function cleanup() { try { await destroy(); await advance(0); expect(timers.size).toBe(0); } finally { Date.now = oldNow; window.happyDOM.abort(); for (const [key, descriptor] of saved) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete (globalThis as any)[key]; } } }
  return { target, query, button, click, change, reply, advance, quote, requests, timers, settle, destroy, cleanup, close: async () => { host.setOpen(false); await settle(); }, setOrderIds: async (value: string[]) => { host.setOrderIds(value); await settle(); } };
}

describe('InstantProcessDialog compiled Svelte 5 lifecycle (real DOM)', () => {
  runtimeTest('QRIS status refresh retains QR and amount, centers it and places refresh beside Close', async () => {
    const h = await fixture(); try {
      await h.reply(h.quote('qris-only', ['qris']));
      await h.click(h.button('instantContinuePayment'));
      await h.reply({ rows: [{ id: '1', status: 'booked', awb: '', message: '' }], payments: [{ id: 'PAY-1', status: 'unpaid', amount: 12000, qr_content: 'booking-qr', order_ids: ['1'] }] });
      const footer = h.query('[data-slot="dialog-footer"]');
      expect(footer.querySelectorAll('button')).toHaveLength(2);
      expect(footer.contains(h.button('instantClose'))).toBe(true);
      expect(footer.contains(h.button('instantRefreshPayment'))).toBe(true);
      expect(h.query('.kiriof-instant-payment-card').querySelector('button')).toBeNull();
      expect(h.query('.kiriof-instant-payment-qr').getAttribute('data-test-qr')).toBe('booking-qr');
      await h.click(h.button('instantRefreshPayment'));
      expect(h.button('instantRefreshPayment').disabled).toBe(true);
      await h.advance(6500);
      expect(h.requests[2].action).toBe('kiriof_instant_payment');
      await h.reply({ id: 'PAY-1', status: 'unpaid', amount: null, qr_content: '' });
      expect(h.query('.kiriof-instant-payment-qr').getAttribute('data-test-qr')).toBe('booking-qr');
      expect(h.query('.kiriof-instant-payment-card').textContent).toContain('Rp12.000');
      expect(h.button('instantRefreshPayment').disabled).toBe(false);
      await h.click(h.button('instantRefreshPayment')); await h.advance(6500);
      await h.reply({ id: 'PAY-1', status: 'paid', amount: 12000, qr_content: '' });
      expect(h.query('.kiriof-instant-payment-qr')).toBeNull();
      expect(h.button('instantRefreshPayment')).toBeUndefined();
      expect(footer.querySelectorAll('button')).toHaveLength(1);
      expect(h.requests.filter((r: any) => r.action === 'kiriof_instant_dispatch')).toHaveLength(1);
    } finally { await h.cleanup(); }
  });
  runtimeTest('loading and refresh show the actual collapsed-summary and payment-card layout before data arrives', async () => {
    const h = await fixture(); try {
      const loader = h.query('[data-loading-layout="instant"]');
      expect(loader).toBeTruthy(); expect(loader.getAttribute('aria-busy')).toBe('true');
      expect(loader.getAttribute('aria-label')).toBe('instantRefreshingPrices');
      expect(loader.querySelector('[data-loading-section="notice"]')).toBeTruthy();
      expect(loader.querySelector('[data-loading-section="order-trigger"]')).toBeTruthy();
      expect(loader.querySelector('[data-loading-section="totals"]')).toBeTruthy();
      expect(loader.querySelector('[data-loading-section="payment-methods"]')).toBeTruthy();
      expect(loader.querySelector('[data-loading-section="schedule"]')).toBeNull();
      expect(loader.querySelectorAll('[data-slot="skeleton"]').length).toBeGreaterThan(10);
      expect(h.target.querySelectorAll('[role="radio"]')).toHaveLength(0);
      expect(h.target.querySelectorAll('[data-order-id]')).toHaveLength(0);
      await h.reply(h.quote()); expect(h.query('[data-loading-layout]')).toBeNull();
      await h.click(h.query('[data-slot="collapsible-trigger"]')); expect(h.target.querySelectorAll('[data-order-id]')).toHaveLength(6);
      await h.advance(120000); expect(h.query('[data-loading-layout="instant"]')).toBeTruthy();
      expect(h.target.querySelectorAll('[data-order-id]')).toHaveLength(0);
      await h.reply(h.quote('refresh')); expect(h.query('[data-loading-layout]')).toBeNull();
      expect(h.query('[data-slot="collapsible-trigger"]').getAttribute('aria-expanded')).toBe('true');
      expect(h.target.querySelectorAll('[data-order-id]')).toHaveLength(6);
      await h.close(); expect(h.target.textContent).toBe('');
    } finally { await h.cleanup(); }
  });
  runtimeTest('payment radio cards show the 4B balance and guard genuinely insufficient or unknown credit', async () => {
    const h = await fixture(); try {
      await h.reply(h.quote('balance', ['qris','credit']));
      expect([...h.target.querySelectorAll('[role="radio"]')].map((radio: any)=>radio.id)).toEqual(['instant-method-credit','instant-method-qris']);
      expect(h.query('#instant-method-credit').getAttribute('aria-checked')).toBe('true');
      expect(h.target.textContent).toContain('Remaining Credit Rp4.000.724.100');
      expect(h.query('select')).toBeNull(); expect(h.query('[role="radiogroup"]')).toBeTruthy();
      expect(h.target.textContent).toContain('Rp4.000.724.100');
      expect(h.query('#instant-credit-pin')).toBeNull();
      await h.click(h.query('#instant-method-credit'));
      expect(h.query('#instant-method-credit').getAttribute('aria-checked')).toBe('true');
      expect(h.button('instantContinuePayment').disabled).toBe(false);
      await h.click(h.button('instantContinuePayment')); await h.change('#instant-credit-pin', '123456');
      await h.click(h.button('instantBackSummary'));
      expect(h.query('#instant-credit-pin')).toBeNull();
      await h.click(h.button('instantContinuePayment'));
      expect(h.query('#instant-credit-pin').value).toBe('');
      expect(h.query('.kiriof-shipment-operation-progress')).toBeNull();
      expect(h.requests).toHaveLength(1);
    } finally { await h.cleanup(); }
    for (const balance of [null, 0, 5999, '4000724100', -1, Infinity]) {
      const h = await fixture(); try {
        await h.reply({ ...h.quote('balance', ['credit','qris']), credit_balance: balance });
        expect(h.query('#instant-method-credit').disabled).toBe(true);
        expect(h.query('#instant-method-qris').disabled).toBe(false);
        expect(h.query('#instant-method-qris').getAttribute('aria-checked')).toBe('true');
        expect(h.query('#instant-credit-pin')).toBeNull();
      } finally { await h.cleanup(); }
    }
  });
  runtimeTest('failed PIN validation stays retryable without booking and stale validation after close cannot dispatch', async () => {
    const h = await fixture(); try {
      await h.reply(h.quote('pin-retry', ['credit'])); await h.click(h.button('instantContinuePayment'));
      await h.change('#instant-credit-pin','123456'); await h.click(h.button('instantValidatePin'));
      expect(h.requests).toHaveLength(2); expect(h.button('instantBackSummary').disabled).toBe(true);
      expect(h.query('.kiriof-shipment-operation-progress').getAttribute('role')).toBe('status');
      expect(h.query('.kiriof-shipment-operation-progress').textContent).toContain('instantPinValidating');
      expect(h.query('.kiriof-shipment-operation-progress svg')).toBeTruthy();
      expect(h.query('[data-loading-layout]')).toBeNull();
      expect(h.target.querySelectorAll('[data-slot="input-otp-slot"]')).toHaveLength(6);
      expect(h.button('instantPinValidating').querySelector('svg').getAttribute('data-icon')).toBe('inline-start');
      h.requests[1].reject(new Error('PIN validation failed')); await h.settle();
      expect(h.query('#instant-credit-pin').value).toBe('');
      expect(h.target.textContent).toContain('PIN validation failed');
      expect(h.query('[data-slot="input-otp-slot"]').getAttribute('aria-invalid')).toBe('true');
      expect(h.requests.every((request)=>request.action !== 'kiriof_instant_dispatch')).toBe(true);
      await h.change('#instant-credit-pin','654321'); await h.click(h.button('instantValidatePin'));
      expect(h.requests).toHaveLength(3);
       await h.close(); expect(h.requests[2].options.signal.aborted).toBe(true);
      await h.reply({valid:true}); expect(h.requests).toHaveLength(3);
      expect(h.target.textContent).toBe('');
    } finally { await h.cleanup(); }
  });
  runtimeTest('TOP has no payment selector or checkbox; real Collapsible starts closed and unchanged summary stays compact', async () => {
    const h = await fixture(); try {
      await h.reply(h.quote());
      expect(h.query('#instant-payment-method')).toBeNull(); expect(h.query('#instant-pin')).toBeNull();
      expect(h.target.querySelectorAll('input[type="checkbox"]')).toHaveLength(0);
      expect(h.target.querySelectorAll('details')).toHaveLength(0);
      expect(h.query('[data-slot="alert"]')).not.toBeNull();
      expect(h.query('[data-slot="collapsible-trigger"]').getAttribute('data-state')).toBe('closed');
      const trigger = h.query('[data-slot="collapsible-trigger"]');
      expect(trigger.tagName).toBe('BUTTON');
      expect(trigger.type).toBe('button');
      expect(trigger.classList.contains('kiriof-button')).toBe(true);
      expect(trigger.getAttribute('data-variant')).toBe('outline');
      expect(trigger.getAttribute('aria-expanded')).toBe('false');
      expect(trigger.querySelector('button')).toBeNull();
      expect(trigger.querySelector('h3')).toBeNull();
      expect(trigger.querySelector('svg').getAttribute('data-icon')).toBe('inline-end');
      expect(trigger.querySelector('svg').classList.contains('size-4')).toBe(false);
      expect(h.query('[data-slot="collapsible-content"]').getAttribute('data-state')).toBe('closed');
      expect(h.query('[data-slot="collapsible-content"]').hidden).toBe(true);
      expect(h.target.querySelectorAll('[data-order-id]')).toHaveLength(0);
      const totals = h.query('section[aria-label="instantShippingInformation"]');
      expect([...totals.querySelectorAll('dd')].map((node: any) => node.textContent)).toEqual(['Rp6.000']);
      expect(h.target.textContent).not.toContain('instantOrdersChanged');
      expect(h.target.textContent).not.toContain('instantPriceGap');
      expect(h.target.textContent).not.toContain('instantQuoteValidity');
      expect(h.button('confirmProcess').disabled).toBe(false);
      await h.click(h.query('[data-slot="collapsible-trigger"]'));
      expect(h.query('[data-slot="collapsible-trigger"]').getAttribute('data-state')).toBe('open');
      expect(trigger.getAttribute('aria-expanded')).toBe('true');
      expect(h.query(`#${trigger.getAttribute('aria-controls')}`)).toBe(h.query('[data-slot="collapsible-content"]'));
      expect(h.target.querySelectorAll('[data-order-id]')).toHaveLength(6);
      expect(h.target.textContent).not.toContain('→');
      await h.click(h.query('[data-slot="collapsible-trigger"]'));
      expect(h.query('[data-slot="collapsible-trigger"]').getAttribute('data-state')).toBe('closed');
      expect(h.target.querySelectorAll('[data-order-id]')).toHaveLength(0);
      expect(totals.textContent).toContain('Rp6.000');
      expect(h.requests).toHaveLength(1);
    } finally { await h.cleanup(); }
  });
  runtimeTest('failed quote rows remain visible when collapsed and block the whole table selection', async () => {
    const h = await fixture(); try {
      const q = h.quote(); q.rows[1] = { ...q.rows[1], eligible: false, after: null, error: 'Unavailable' };
      await h.reply(q);
      expect(h.query('[role="alert"]').textContent).toContain('Unavailable');
      expect(h.button('confirmProcess').disabled).toBe(true);
      expect(h.target.querySelectorAll('input[type="checkbox"]')).toHaveLength(0);
      await h.click(h.button('confirmProcess')); expect(h.requests).toHaveLength(1);
      await h.click(h.button('instantClose')); expect(h.target.textContent).toBe('');
    } finally { await h.cleanup(); }
  });
  runtimeTest('Confirm explicitly accepts changed prices for every selected row without extra consent', async () => {
    const h = await fixture(); try {
      const q = h.quote(); q.rows[0] = { ...q.rows[0], after: 1200, changed: true };
      await h.reply(q);
      expect(h.target.textContent).toContain('instantOrdersChanged');
      expect(h.target.textContent).toContain('instantPriceGap');
      expect(h.button('confirmProcess').disabled).toBe(false);
      await h.click(h.query('[data-slot="collapsible-trigger"]'));
      expect(h.target.textContent).toMatch(/Rp1\.000 →\s*Rp1\.200/);
      await h.click(h.button('confirmProcess'));
      expect(h.requests[1].values).toEqual({ token: 'quote-a', order_ids: '["1","2","3","4","5","6"]', method: 'top', pin: '', confirmed: 'yes' });
    } finally { await h.cleanup(); }
  });
  for (const invalid of ['missing row', 'unexpected row', 'duplicate row'] as const) runtimeTest(`rejects quote with ${invalid} instead of silently processing a subset`, async () => {
    const h = await fixture(); try {
      const q = h.quote();
      if (invalid === 'missing row') q.rows.pop();
      if (invalid === 'unexpected row') q.rows[0].id = '99';
      if (invalid === 'duplicate row') q.rows[1].id = '1';
      await h.reply(q);
      expect(h.query('[role="alert"]').textContent).toBe('instantQuoteRefreshFailed');
      expect(h.button('instantContinuePayment').disabled).toBe(true);
      expect(h.requests).toHaveLength(1);
    } finally { await h.cleanup(); }
  });
  for (const amount of [null, NaN, Infinity, -1]) runtimeTest(`incomplete or invalid price ${amount} blocks dispatch`, async () => {
    const h = await fixture(); try {
      const q = h.quote(); q.rows[0].after = amount as any; await h.reply(q);
      expect(h.button('confirmProcess').disabled).toBe(true);
      await h.click(h.button('confirmProcess')); expect(h.requests).toHaveLength(1);
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
      expect(h.query('[data-slot="collapsible-trigger"]').getAttribute('data-state')).toBe('closed');
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
      expect(h.query('[data-slot="collapsible-trigger"]').getAttribute('data-state')).toBe('closed');
      await h.click(h.query('[data-slot="collapsible-trigger"]'));
      expect(h.query('[data-order-id="7"]')).not.toBeNull();
      expect(h.query('[data-order-id="1"]')).toBeNull();
      expect(h.target.textContent).toContain('NEW-7');
      expect(h.query('[role="alert"]')).toBeNull();
      expect(h.query('[role="status"]')).toBeNull();
      await h.advance(119000); expect(h.requests).toHaveLength(2);
      await h.advance(1000); expect(h.requests).toHaveLength(3);
      expect(h.requests[2].values).toEqual({ order_ids: '["7"]' });
    } finally { await h.cleanup(); }
  });
  runtimeTest('server 120-second deadline requotes once without dispatch and clears credit PIN', async () => {
    const h = await fixture(); try {
      await h.reply(h.quote('quote-a', ['credit']));
      expect(h.button('instantContinuePayment').textContent).toContain('(120s)');
      expect(h.query('#instant-credit-pin')).toBeNull();
      await h.click(h.button('instantContinuePayment'));
      await h.change('#instant-credit-pin', '123456');
      expect(h.button('instantValidatePin').disabled).toBe(false);
      await h.advance(119000); expect(h.requests).toHaveLength(1); expect(h.button('instantValidatePin').textContent).toContain('(1s)');
      await h.advance(1000); expect(h.requests).toHaveLength(2); expect(h.requests[1].action).toBe('kiriof_instant_quote');
      await h.advance(5000); expect(h.requests).toHaveLength(2);
      await h.reply(h.quote('quote-b', ['credit']));
      expect(h.query('#instant-credit-pin')).toBeNull();
      await h.click(h.button('instantContinuePayment'));
      expect(h.query('#instant-credit-pin').value).toBe('');
      expect(h.button('instantValidatePin').disabled).toBe(true);
      expect(h.target.querySelectorAll('input[type="checkbox"]')).toHaveLength(0);
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
      await h.reply(h.quote('credit-token', ['credit']));
      expect(h.query('select')).toBeNull(); expect(h.query('#instant-credit-pin')).toBeNull();
      await h.click(h.button('instantContinuePayment')); expect(h.requests).toHaveLength(1);
      expect(h.target.querySelectorAll('[data-slot="input-otp-slot"]')).toHaveLength(6);
      expect(h.target.querySelectorAll('[data-slot="input-otp-group"]')).toHaveLength(0);
      expect(h.query('label[for="instant-credit-pin"]').classList.contains('sr-only')).toBe(true);
      expect(h.query('#instant-credit-pin-help').classList.contains('sr-only')).toBe(true);
      expect(h.target.querySelectorAll('[data-slot="input-otp-separator"]')).toHaveLength(0);
      expect(h.query('#instant-credit-pin').hasAttribute('data-pin-input-input')).toBe(true);
      expect(h.query('[data-slot="dialog-footer"]').querySelectorAll('button')).toHaveLength(2);
      expect(h.button('instantClose')).toBeUndefined();
      expect(h.button('instantBackSummary')).toBeTruthy();
      for (const value of ['12345', '12a456']) { await h.change('#instant-credit-pin', value); expect(h.button('instantValidatePin').disabled).toBe(true); }
      await h.change('#instant-credit-pin', '123456'); expect(h.button('instantValidatePin').disabled).toBe(false);
      expect(h.query('#instant-credit-pin').type).toBe('password'); expect(h.target.textContent).not.toContain('123456');
      expect(h.query('#instant-credit-pin').autocomplete).toBe('off');
      expect(h.query(`#${h.query('#instant-credit-pin').getAttribute('aria-describedby')}`)).toBeTruthy();
      await h.click(h.button('instantValidatePin')); expect(h.requests).toHaveLength(2);
      expect(h.requests[1].action).toBe('kiriof_instant_validate_credit');
      expect(h.requests[1].values).toEqual({ token: 'credit-token', order_ids: '["1","2","3","4","5","6"]', pin: '123456' });
      await h.reply({ valid: true }); expect(h.requests).toHaveLength(3); expect(h.timers.size).toBe(0);
      expect(h.requests[2].action).toBe('kiriof_instant_dispatch');
      expect(h.requests[2].values).toEqual({ token: 'credit-token', order_ids: '["1","2","3","4","5","6"]', method: 'credit', pin: '123456', confirmed: 'yes' });
      await h.advance(300000); expect(h.requests).toHaveLength(3);
      await h.reply({ rows: [{ id: '1', status: 'booked', awb: 'AWB-123', message: '' }], payments: [] });
      expect(h.target.textContent).toContain('BOOKED'); expect(h.target.textContent).toContain('AWB-123'); expect(h.query('#instant-credit-pin')).toBeNull();
      await h.advance(600000); expect(h.requests).toHaveLength(3); expect(h.button('instantContinuePayment')).toBeUndefined();
    } finally { await h.cleanup(); }
  });
  runtimeTest('server rejected and unknown outcomes are displayed unchanged without inventing missing rows', async () => {
    const h = await fixture(); try {
      await h.reply(h.quote()); await h.click(h.button('confirmProcess'));
      await h.reply({ rows: [{ id: '1', status: 'rejected', awb: '', message: 'Booking rejected' }, { id: '2', status: 'unknown', awb: '', message: 'Booking uncertain' }], payments: [] });
      expect(h.target.textContent).toContain('Booking rejected');
      expect(h.target.textContent).toContain('Booking uncertain');
      expect(h.target.textContent).not.toContain('WC-3');
      expect(h.button('instantReview')).toBeUndefined();
      await h.advance(600000); expect(h.requests).toHaveLength(2);
    } finally { await h.cleanup(); }
  });
  runtimeTest('dispatch network failure surfaces the actual error without inventing unknown results or retrying', async () => {
    const h = await fixture(); try {
      await h.reply(h.quote()); await h.click(h.button('confirmProcess'));
      h.requests[1].reject(new Error('Connection lost')); await h.settle();
      expect(h.query('[role="alert"]').textContent).toBe('Connection lost');
      expect(h.target.textContent).not.toContain('UNKNOWN'); expect(h.button('instantReview')).toBeUndefined();
      await h.advance(600000); expect(h.requests).toHaveLength(2); expect(h.timers.size).toBe(0);
    } finally { await h.cleanup(); }
  });
  runtimeTest('partial batch retains earlier payment and presents restored rows without rerunning booking', async () => {
    const h = await fixture(); try {
      await h.reply(h.quote()); await h.click(h.button('confirmProcess'));
      await h.reply({ rows: [
        { id: '1', status: 'booked', awb: 'AWB-1', retryable: false, message: 'Accepted earlier group' },
        { id: '2', status: 'failed', awb: '', retryable: true, message: 'Original data restored. Close and request a new quote.' },
        { id: '3', status: 'skipped', awb: '', retryable: true, message: 'Not submitted; restored.' },
      ], payments: [{ id: 'EARLIER-PAYMENT', order_ids: ['1'], status: 'paid', amount: 1000, qr_content: '' }] });
      expect(h.target.textContent).toContain('BOOKED');
      expect(h.target.textContent).toContain('FAILED');
      expect(h.target.textContent).toContain('SKIPPED');
      expect(h.target.textContent).toContain('Original data restored');
      expect(h.target.textContent).toContain('EARLIER-PAYMENT');
      expect(h.button('instantReview')).toBeUndefined();
      expect(h.button('confirmProcess')).toBeUndefined();
      await h.advance(600000); expect(h.requests).toHaveLength(2);
    } finally { await h.cleanup(); }
  });
});
