import { afterAll, describe, expect, test } from 'bun:test';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';

const root = resolve(import.meta.dir, '..');
let directory: string;
afterAll(() => { if (directory) rmSync(directory, { recursive: true, force: true }); });

async function compiledRuntime() {
  // Isolate Svelte's cached native DOM getters for each HappyDOM window.
  if (directory) rmSync(directory, { recursive: true, force: true });
  directory = mkdtempSync(join(root, 'node_modules', '.instant-operation-runtime-'));
  const put = (name: string, source: string) => writeFileSync(join(directory, name), source);
  // Only the dialog, button and HTTP boundaries are doubled. Production script,
  // markup, lifecycle and safeInstantTrackingUrl from types.ts remain unchanged.
  put('Container.svelte', `<script>let {children, ...rest} = $props();</script><div {...rest}>{@render children?.()}</div>`);
  put('Root.svelte', `<script>let {children, open, onOpenChange} = $props();</script>{#if open}<button data-dialog-dismiss onclick={() => onOpenChange(false)}>Dialog dismissal</button>{@render children?.()}{/if}`);
  put('Content.svelte', `<script>let {children, showCloseButton, escapeKeydownBehavior, interactOutsideBehavior, ...rest} = $props();</script><section data-dialog-content data-show-close={String(showCloseButton)} data-escape={escapeKeydownBehavior} data-outside={interactOutsideBehavior} {...rest}>{@render children?.()}</section>`);
  put('Button.svelte', `<script>let {children, variant, href, ...rest} = $props();</script>{#if href}<a {href} {...rest}>{@render children?.()}</a>{:else}<button {...rest}>{@render children?.()}</button>{/if}`);
  put('dialog.ts', `export {default as Root} from './Root.svelte'; export {default as Content} from './Content.svelte'; export {default as Header, default as Title, default as Description, default as Footer} from './Container.svelte';`);
  put('button.ts', `export {default as Button} from './Button.svelte';`);
  put('ajax.ts', `export function postWordPressAction(...args) {return globalThis.__instantOperationAjax(...args);}`);
  let source = readFileSync(join(root, 'src/lib/transactions/InstantOperationDialog.svelte'), 'utf8');
  for (const [from, to] of Object.entries({
    '$lib/components/ui/dialog': './dialog.ts',
    '$lib/components/ui/button': './button.ts',
    '$lib/wordpress/ajax': './ajax.ts',
    './types': join(root, 'src/lib/transactions/types.ts'),
  })) source = source.replace(`'${from}'`, `'${to}'`);
  put('Production.svelte', source);
  put('entry.ts', `export {mount, unmount, flushSync} from 'svelte'; export {default as Production} from './Production.svelte'; export {safeInstantTrackingUrl} from '${join(root, 'src/lib/transactions/types.ts')}';`);
  const build = await Bun.build({
    entrypoints: [join(directory, 'entry.ts')], outdir: directory, naming: 'runtime.js',
    target: 'browser', conditions: ['browser'],
    plugins: [{ name: 'production-svelte', setup(builder) {
      builder.onResolve({ filter: /^svelte$/ }, () => ({ path: join(root, 'node_modules/svelte/src/index-client.js') }));
      builder.onLoad({ filter: /\.svelte$/ }, async ({ path }) => ({
        contents: compile(readFileSync(path, 'utf8'), { filename: path, generate: 'client' }).js.code,
        loader: 'js',
      }));
    } }],
  });
  if (!build.success) throw new Error(build.logs.join('\n'));
  return await import(join(directory, 'runtime.js'));
}

async function fixture(orderIds = ['42']) {
  const window = new happy.Window({ url: 'https://fixture.test' });
  const saved = new Map<string, PropertyDescriptor | undefined>();
  const requests: any[] = [];
  const closes: boolean[] = [];
  const globals = {
    window, document: window.document, navigator: window.navigator,
    Node: window.Node, Element: window.Element, HTMLElement: window.HTMLElement,
    HTMLButtonElement: window.HTMLButtonElement, SVGElement: window.SVGElement,
    DocumentFragment: window.DocumentFragment, Text: window.Text, Comment: window.Comment,
    Event: window.Event, CustomEvent: window.CustomEvent, MutationObserver: window.MutationObserver,
    getComputedStyle: window.getComputedStyle.bind(window),
    requestAnimationFrame: window.requestAnimationFrame.bind(window),
    cancelAnimationFrame: window.cancelAnimationFrame.bind(window),
    __instantOperationAjax: (action: string, values: any, options: any) => new Promise((resolve, reject) => {
      requests.push({ action, values, options, resolve, reject });
    }),
  };
  for (const [key, value] of Object.entries(globals)) {
    saved.set(key, Object.getOwnPropertyDescriptor(globalThis, key));
    Object.defineProperty(globalThis, key, { configurable: true, writable: true, value });
  }
  const runtime = await compiledRuntime();
  const i18n = {
    instantRecheck: 'Recheck', instantRecheckDescription: 'Check the existing booking without booking again.',
    instantClose: 'Close', liveTracking: 'Live Tracking', instantCancel: 'Cancel',
    instantResult_reconciled: 'Shipment status refreshed', instantResult_not_found: 'Booking not found',
    instantResult_unknown: 'Unknown result', instantOperationUnknown: 'Check again later; do not dispatch again.',
  };
  const target = window.document.createElement('main');
  window.document.body.append(target);
  const component = runtime.mount(runtime.Production, { target, props: {
    mode: 'reconcile', orderIds, ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'recheck-nonce', i18n,
    onClose: (completed: boolean) => closes.push(completed),
  } });
  async function settle() { for (let i = 0; i < 8; i++) { await Promise.resolve(); runtime.flushSync(); } }
  await settle();
  const query = (selector: string): any => target.querySelector(selector);
  const button = (label: string): any => [...target.querySelectorAll('button')].find(node => node.textContent === label);
  async function click(node: any) { expect(node).toBeTruthy(); node.click(); await settle(); }
  async function reply(rows: any[]) { requests[requests.length - 1].resolve({ data: { rows } }); await settle(); }
  async function cleanup() {
    try { await runtime.unmount(component); await settle(); }
    finally {
      window.happyDOM.abort();
      for (const [key, descriptor] of saved) {
        if (descriptor) Object.defineProperty(globalThis, key, descriptor);
        else delete (globalThis as any)[key];
      }
    }
  }
  return { target, query, button, click, reply, requests, closes, settle, cleanup, runtime };
}

function expectNoRetryOrDispatch(h: Awaited<ReturnType<typeof fixture>>) {
  expect(h.button('Recheck')).toBeUndefined();
  expect([...h.target.querySelectorAll('button')].some(node => /retry|dispatch|confirm|process/i.test(node.textContent ?? ''))).toBe(false);
  expect(h.requests.map(request => request.action)).toEqual(['kiriof_instant_reconcile']);
}

describe('InstantOperationDialog recheck compiled production Svelte (real DOM)', () => {
  test('requires explicit intent, posts only the order ID and nonce, blocks busy dismissal, and refreshes once', async () => {
    const h = await fixture();
    try {
      expect(h.target.textContent).toContain('Check the existing booking without booking again.');
      expect(h.target.textContent).toContain('42');
      await h.settle();
      expect(h.requests).toHaveLength(0);
      expect(h.button('Recheck').disabled).toBe(false);
      await h.click(h.button('Recheck'));
      expect(h.requests).toHaveLength(1);
      expect(h.requests[0].action).toBe('kiriof_instant_reconcile');
      expect(h.requests[0].values).toEqual({ order_ids: '["42"]' });
      expect(h.requests[0].options.ajaxUrl).toBe('/wp-admin/admin-ajax.php');
      expect(h.requests[0].options.nonce).toBe('recheck-nonce');
      expect(h.requests[0].options.signal.aborted).toBe(false);
      for (const key of ['pin', 'token', 'confirmed', 'method']) expect(h.requests[0].values).not.toHaveProperty(key);
      const content = h.query('[data-dialog-content]');
      expect(content.getAttribute('aria-busy')).toBe('true');
      expect(content.dataset.showClose).toBe('false');
      expect(content.dataset.escape).toBe('ignore');
      expect(content.dataset.outside).toBe('ignore');
      expect(h.button('Close').disabled).toBe(true);
      expect(h.button('Recheck').disabled).toBe(true);
      await h.click(h.button('Close'));
      await h.click(h.query('[data-dialog-dismiss]'));
      await h.click(h.button('Recheck'));
      expect(h.closes).toEqual([]);
      expect(h.requests).toHaveLength(1);
      expect(h.requests[0].options.signal.aborted).toBe(false);
      const trackingUrl = 'https://courier.test/track/AWB-42';
      // Verify the imported production URL helper accepts this URL: absence of
      // a link below is the reconcile-mode rule, not a stub rejecting all URLs.
      expect(h.runtime.safeInstantTrackingUrl(trackingUrl)).toBe(trackingUrl);
      expect(h.runtime.safeInstantTrackingUrl('javascript:alert(1)')).toBe('');
      await h.reply([{ id: '42', status: 'reconciled', tracking_url: trackingUrl, message: 'Existing booking recovered: AWB-42' }]);
      expect(h.query('[role="status"]').textContent).toContain('Shipment status refreshed');
      expect(h.target.textContent).toContain('Existing booking recovered: AWB-42');
      expect(h.target.querySelectorAll('a')).toHaveLength(0);
      expect(h.target.textContent).not.toContain('Live Tracking');
      expect(content.getAttribute('aria-busy')).toBe('false');
      expect(content.dataset.showClose).toBe('true');
      expect(content.dataset.escape).toBe('close');
      expect(content.dataset.outside).toBe('close');
      expect(h.closes).toEqual([]);
      await h.settle();
      expectNoRetryOrDispatch(h);
      await h.click(h.button('Close'));
      expect(h.closes).toEqual([true]);
      expect(h.requests).toHaveLength(1);
    } finally { await h.cleanup(); }
  });

  test('closing before explicit recheck reports incomplete and makes no request', async () => {
    const h = await fixture();
    try {
      await h.click(h.button('Close'));
      expect(h.closes).toEqual([false]);
      expect(h.requests).toHaveLength(0);
    } finally { await h.cleanup(); }
  });

  for (const status of ['not_found', 'unknown'] as const) test(`${status} is displayed without retry, tracking link or another dispatch`, async () => {
    const h = await fixture();
    try {
      await h.click(h.button('Recheck'));
      await h.reply([{ id: '42', status, tracking_url: 'https://courier.test/track/42', message: 'No existing booking matched.' }]);
      const result = h.query('[role="status"]').textContent;
      expect(result).toContain(status === 'not_found' ? 'Booking not found' : 'Unknown result');
      expect(result).toContain(status === 'not_found' ? 'No existing booking matched.' : 'Check again later; do not dispatch again.');
      expect(h.target.querySelectorAll('a')).toHaveLength(0);
      expect(h.closes).toEqual([]);
      await h.settle();
      // No client-side retry/dispatch unlock: eligibility remains backend-owned.
      expectNoRetryOrDispatch(h);
      await h.click(h.button('Close'));
      expect(h.closes).toEqual([true]);
    } finally { await h.cleanup(); }
  });

  test('transport failure displays unknown without a retry or automatic resubmission', async () => {
    const h = await fixture();
    try {
      await h.click(h.button('Recheck'));
      h.requests[0].reject(new Error('Connection lost'));
      await h.settle();
      expect(h.query('[role="status"]').textContent).toContain('Unknown result');
      expect(h.target.textContent).toContain('Check again later; do not dispatch again.');
      expect(h.button('Close').disabled).toBe(false);
      expectNoRetryOrDispatch(h);
    } finally { await h.cleanup(); }
  });

  for (const ids of [[], ['42', '43']]) test(`does not submit recheck for ${ids.length} selected orders`, async () => {
    const h = await fixture(ids);
    try {
      expect(h.button('Recheck').disabled).toBe(true);
      await h.click(h.button('Recheck'));
      expect(h.requests).toHaveLength(0);
    } finally { await h.cleanup(); }
  });
});
