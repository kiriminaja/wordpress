import { afterAll, describe, expect, test } from 'bun:test';
import { existsSync, mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';

const root = resolve(import.meta.dir, '..');
let buildDirectory: string | undefined;
let bundlePromise: Promise<Buffer> | undefined;
afterAll(() => { if (buildDirectory) rmSync(buildDirectory, { recursive: true, force: true }); });

// Like DateRangeFilterRuntime: compile production once, import unique bundle bytes
// per Window so Svelte's cached native DOM getters never cross fixture boundaries.
// TransactionsApp, Select/bits-ui, date/status/courier controls and AutoRefresh are
// unchanged. Only unrelated heavyweight modal/toolbar/tooltip boundaries and SVG
// artwork are substituted; no production filter or navigation code is extracted.
async function buildBundle(): Promise<Buffer> {
  const directory = buildDirectory = mkdtempSync(join(root, 'node_modules', '.transaction-print-build-'));
  const put = (name: string, source: string) => writeFileSync(join(directory, name), source);
  put('Empty.svelte', '<script>let {open = $bindable(false), action = $bindable(null)} = $props();</script>');
  put('Boundary.svelte', '<script>let {children} = $props();</script>{@render children?.()}');
  put('Icon.svelte', '<script>let props = $props();</script><svg aria-hidden="true" {...props}></svg>');
  const iconNames = new Set<string>();
  for (const file of readdirSync(join(root, 'src'), { recursive: true })) {
    if (typeof file !== 'string' || !/\.(svelte|ts)$/.test(file)) continue;
    for (const name of readFileSync(join(root, 'src', file), 'utf8').match(/\bIcon[A-Z]\w*/g) ?? []) iconNames.add(name);
  }
  put('icons.ts', `export {${[...iconNames].map((name) => `default as ${name}`).join(',')}} from './Icon.svelte';`);
  put('entry.ts', `export {mount, unmount, flushSync} from 'svelte'; export {default as App} from '${join(root, 'src/lib/transactions/TransactionsApp.svelte')}';`);
  const build = await Bun.build({ entrypoints: [join(directory, 'entry.ts')], outdir: directory, naming: 'runtime.js', target: 'browser', conditions: ['browser'], plugins: [{ name: 'transaction-print-production-runtime', setup(builder) {
    builder.onResolve({ filter: /(?:PrintPreviewDialog|InstantProcessDialog|RequestPickupDialog|TransactionActionDialogs|InstantOperationDialog)\.svelte$/ }, () => ({ path: join(directory, 'Empty.svelte') }));
    builder.onResolve({ filter: /(?:Toolbar|ActionTooltip)\.svelte$/ }, () => ({ path: join(directory, 'Boundary.svelte') }));
    builder.onResolve({ filter: /^@tabler\/icons-svelte$/ }, () => ({ path: join(directory, 'icons.ts') }));
    builder.onResolve({ filter: /^\$lib\// }, ({ path }) => {
      const base = join(root, 'src/lib', path.slice(5));
      const candidates = [base, base.replace(/\.js$/, '.ts'), `${base}.ts`, join(base, 'index.ts')];
      const found = candidates.find((candidate) => existsSync(candidate) && !readdirIsDirectory(candidate));
      if (!found) throw new Error(`Unresolved production alias: ${path}`);
      return { path: found };
    });
    builder.onResolve({ filter: /^svelte$/ }, () => ({ path: join(root, 'node_modules/svelte/src/index-client.js') }));
    builder.onLoad({ filter: /\.svelte\.[jt]s$/ }, ({ path }) => ({ contents: compileModule(path.endsWith('.ts') ? new Bun.Transpiler({ loader: 'ts' }).transformSync(readFileSync(path, 'utf8')) : readFileSync(path, 'utf8'), { filename: path, generate: 'client' }).js.code, loader: 'js' }));
    builder.onLoad({ filter: /\.svelte$/ }, ({ path }) => ({ contents: compile(readFileSync(path, 'utf8'), { filename: path, generate: 'client' }).js.code, loader: 'js' }));
  } }] });
  if (!build.success) throw new Error(build.logs.join('\n'));
  return readFileSync(join(directory, 'runtime.js'));
}
function readdirIsDirectory(path: string): boolean {
  try { readdirSync(path); return true; } catch { return false; }
}

const baseUrl = 'https://fixture.test/wp-admin/admin.php?page=kiriminaja-transactions&delivery_type=instant&key=parcel+42&status=processed%2Cwaiting_for_shipment&courier=gosend%2Cgrab&month=2026-01&date_from=2026-01-02&date_to=2026-01-27&per_page=50&cpage=4&print_status=0&cod=1&search_by=awb';
async function fixture(deliveryType = 'instant', initialPrint = '0') {
  const directory = mkdtempSync(join(root, 'node_modules', '.transaction-print-runtime-'));
  const initialUrl = new URL(baseUrl);
  initialUrl.searchParams.set('delivery_type', deliveryType);
  if (initialPrint) initialUrl.searchParams.set('print_status', initialPrint);
  else initialUrl.searchParams.delete('print_status');
  const window = new happy.Window({ url: initialUrl.href });
  // Drive the actual AutoRefresh interval callback deterministically, without a
  // minute of wall-clock waits or replacing the component's countdown behavior.
  const timers = new Map<number, () => void>();
  let timerId = 0;
  window.setInterval = ((callback: () => void) => { timers.set(++timerId, callback); return timerId; }) as any;
  window.clearInterval = ((id: number) => { timers.delete(id); }) as any;
  const saved = new Map<string, PropertyDescriptor | undefined>();
  const globals = { window, document: window.document, navigator: window.navigator, Node: window.Node, Element: window.Element, HTMLElement: window.HTMLElement, HTMLButtonElement: window.HTMLButtonElement, HTMLMediaElement: window.HTMLMediaElement, SVGElement: window.SVGElement, DocumentFragment: window.DocumentFragment, HTMLInputElement: window.HTMLInputElement, HTMLSelectElement: window.HTMLSelectElement, Text: window.Text, Comment: window.Comment, Event: window.Event, CustomEvent: window.CustomEvent, MutationObserver: window.MutationObserver, ResizeObserver: window.ResizeObserver, getComputedStyle: window.getComputedStyle.bind(window), requestAnimationFrame: window.requestAnimationFrame.bind(window), cancelAnimationFrame: window.cancelAnimationFrame.bind(window) };
  for (const [key, value] of Object.entries(globals)) { saved.set(key, Object.getOwnPropertyDescriptor(globalThis, key)); Object.defineProperty(globalThis, key, { configurable: true, writable: true, value }); }
  const navigations: string[] = [];
  let completeNavigation: (() => void) | undefined;
  let runtime: any;
  let app: any;
  const target = window.document.createElement('main'); window.document.body.append(target);
  const bootstrap = {
    filters: { delivery_type: deliveryType, key: 'parcel 42', month: '2026-01', date_from: '2026-01-02', date_to: '2026-01-27', status: 'processed,waiting_for_shipment', courier: 'gosend,grab', cod: '1', print_status: initialPrint },
    pagination: { page: 4, perPage: 50, total: 200, totalPages: 4 },
    rows: [], couriers: [{ value: 'gosend', label: 'GoSend', count: 2 }, { value: 'grab', label: 'Grab', count: 1 }],
    statusOptions: [{ value: 'all', label: 'All', count: 3 }, { value: 'processed', label: 'Processed', count: 2 }, { value: 'waiting_for_shipment', label: 'Waiting for shipment', count: 1 }],
    deliveryCounts: { regular: 2, instant: 3, issue: 0 }, bulk: { ajaxUrl: '/ajax', nonce: 'test', printPreviewNonce: 'print' }, shipmentLocations: [],
    i18n: { regularDelivery: 'Regular', instantDelivery: 'Instant', orderIssue: 'Order issue', transactionScope: 'Delivery workspace', print: 'Print', processShipment: 'Process shipment', requestPickup: 'Request pickup', allDates: 'Date range', apply: 'Apply', clearFilters: 'All', clear: 'Clear filters', allPrints: 'All prints', printed: 'Printed', unprinted: 'Unprinted', allPayment: 'All payment', cod: 'COD', nonCod: 'Non COD', status: 'Status', allCouriers: 'All couriers', search: 'Search', autoRefresh: 'Refresh transactions', refreshLabels: {}, notFound: 'No transactions', items: 'items', pageOf: 'of', searchStatuses: 'Search statuses', searchCouriers: 'Search couriers', selectedFilters: 'selected', noFilterOptions: 'No options' },
  };
  async function cleanup() {
    completeNavigation?.();
    if (app) await runtime.unmount(app);
    window.happyDOM.abort();
    for (const [key, descriptor] of saved) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete (globalThis as any)[key]; }
    rmSync(directory, { recursive: true, force: true });
  }
  try {
    writeFileSync(join(directory, 'runtime.js'), await (bundlePromise ??= buildBundle()));
    runtime = await import(join(directory, 'runtime.js'));
    app = runtime.mount(runtime.App, { target, props: { bootstrap, onNavigate: async (url: URL) => {
      navigations.push(url.href);
      await new Promise<void>((resolve) => { completeNavigation = resolve; });
      // Model the admin AJAX navigation updating its address before completion.
      window.history.replaceState(null, '', url.href);
    } } });
    async function settle() { for (let i = 0; i < 20; i++) { await Promise.resolve(); runtime.flushSync(); } }
    await settle();
    async function finish() { completeNavigation?.(); completeNavigation = undefined; await settle(); }
    const trigger = (label: string) => {
      const found = [...target.querySelectorAll<HTMLButtonElement>('[data-slot="select-trigger"]')].find((button) => button.textContent?.trim() === label);
      if (!found) throw new Error(`Missing select trigger: ${label}; available: ${[...target.querySelectorAll('[data-slot="select-trigger"]')].map((button) => button.textContent?.trim()).join(', ')}`);
      return found;
    };
    async function select(label: string, value: string) {
      const button = trigger(label);
      // bits-ui opens mouse selects on pointerdown, not a synthetic click alone.
      button.dispatchEvent(new window.PointerEvent('pointerdown', { bubbles: true, button: 0, pointerType: 'mouse' }));
      button.dispatchEvent(new window.PointerEvent('pointerup', { bubbles: true, button: 0, pointerType: 'mouse' }));
      button.click(); await settle();
      const option = window.document.querySelector<HTMLElement>(`[role="option"][data-value="${value}"]`);
      if (!option) throw new Error(`Missing real bits-ui option ${value}; available options: ${[...window.document.querySelectorAll('[role="option"]')].map((item) => `${item.getAttribute('data-value')}: ${item.textContent?.trim()}`).join(', ') || '(none)'}`);
      option.dispatchEvent(new window.PointerEvent('pointerdown', { bubbles: true, button: 0, pointerType: 'mouse' }));
      option.dispatchEvent(new window.PointerEvent('pointerup', { bubbles: true, button: 0, pointerType: 'mouse' }));
      option.click(); await settle();
    }
    return { target, window, navigations, initialUrl, settle, finish, select, trigger, cleanup, tick: async (seconds: number) => { for (let i = 0; i < seconds; i++) { for (const callback of [...timers.values()]) callback(); await settle(); } } };
  } catch (error) { await cleanup(); throw error; }
}

function expectedFilterUrl(initial: URL, printStatus: string, deliveryType = 'instant'): string {
  const expected = new URL(initial);
  expected.searchParams.set('delivery_type', deliveryType);
  expected.searchParams.set('cpage', '1');
  expected.searchParams.delete('search_by');
  if (deliveryType === 'instant') expected.searchParams.delete('cod');
  if (printStatus === 'all') expected.searchParams.delete('print_status');
  else expected.searchParams.set('print_status', printStatus);
  return expected.href;
}

describe('TransactionsApp print filters (compiled production Svelte and bits-ui)', () => {
  test('Instant selects Printed, Unprinted and All, preserves other query state and strips COD', async () => {
    const h = await fixture(); try {
      expect(h.target.querySelector('form')?.classList.contains('is-instant')).toBe(true);
      expect([...h.target.querySelectorAll('[data-slot="select-trigger"]')].map((button) => button.textContent?.trim())).toEqual(['50', 'Unprinted']);
      let label = 'Unprinted';
      for (const [value, nextLabel] of [['1', 'Printed'], ['0', 'Unprinted'], ['all', 'All prints']]) {
        await h.select(label, value);
        expect(h.navigations.at(-1)).toBe(expectedFilterUrl(h.initialUrl, value));
        expect(h.trigger(nextLabel).disabled).toBe(true); // async onNavigate remains in flight
        await h.finish();
        expect(h.trigger(nextLabel).disabled).toBe(false);
        label = nextLabel;
      }
      expect(h.navigations).toHaveLength(3);
    } finally { await h.cleanup(); }
  });

  test('clear filters resets print/search/status/courier/COD but retains dates, workspace and page size', async () => {
    const h = await fixture('instant', '1'); try {
      const clear = h.target.querySelector<HTMLButtonElement>('button[aria-label="Clear filters"]');
      expect(clear).not.toBeNull(); clear!.click(); await h.settle();
      const expected = new URL(h.initialUrl);
      for (const key of ['key', 'courier', 'cod', 'print_status', 'search_by']) expected.searchParams.delete(key);
      expected.searchParams.set('status', 'all'); expected.searchParams.set('cpage', '1');
      expect(h.navigations).toEqual([expected.href]);
      expect(clear!.disabled).toBe(true); await h.finish(); expect(clear!.disabled).toBe(false);
    } finally { await h.cleanup(); }
  });

  test('manual and timed refresh retain the current print filter and page without applying draft filters', async () => {
    const h = await fixture('instant', '1'); try {
      const expected = new URL(h.initialUrl); expected.searchParams.delete('search_by');
      const refresh = h.target.querySelector<HTMLButtonElement>('.kiriof-auto-refresh button');
      expect(refresh).not.toBeNull(); refresh!.click(); await h.settle();
      expect(h.navigations).toEqual([expected.href]); expect(refresh!.disabled).toBe(true);
      await h.tick(60); expect(h.navigations).toHaveLength(1); // no duplicate while awaiting navigation
      await h.finish(); expect(refresh!.disabled).toBe(false);
      await h.tick(59); expect(h.navigations).toHaveLength(1);
      await h.tick(1); expect(h.navigations).toEqual([expected.href, expected.href]);
      await h.finish();
    } finally { await h.cleanup(); }
  });

  test('Express retains its payment selector/COD behavior and the same print filter transitions', async () => {
    const h = await fixture('express'); try {
      expect(h.target.querySelector('form')?.classList.contains('is-instant')).toBe(false);
      expect(h.trigger('COD')).toBeDefined();
      let label = 'Unprinted';
      for (const [value, nextLabel] of [['1', 'Printed'], ['0', 'Unprinted'], ['all', 'All prints']]) {
        await h.select(label, value);
        expect(h.navigations.at(-1)).toBe(expectedFilterUrl(h.initialUrl, value, 'express'));
        await h.finish(); label = nextLabel;
      }
      expect(h.trigger('COD').disabled).toBe(false);
    } finally { await h.cleanup(); }
  });
});
