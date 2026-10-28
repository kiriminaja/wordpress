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
  put('Empty.svelte', '<script>let {open = $bindable(false), action = $bindable(null), mode, orderIds} = $props();</script>{#if open || action || mode}<output data-dialog-boundary>{JSON.stringify({open, action, mode, orderIds})}</output>{/if}');
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
    builder.onResolve({ filter: /Toolbar\.svelte$/ }, () => ({ path: join(directory, 'Boundary.svelte') }));
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
async function fixture(deliveryType = 'instant', initialPrint = '0', rows: any[] = [], filterOverrides: Record<string, string> = {}) {
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
    rows, couriers: [{ value: 'gosend', label: 'GoSend', count: 2 }, { value: 'grab', label: 'Grab', count: 1 }],
    statusOptions: [{ value: 'all', label: 'All', count: 3 }, { value: 'processed', label: 'Processed', count: 2 }, { value: 'waiting_for_shipment', label: 'Waiting for shipment', count: 1 }],
    deliveryCounts: { regular: 2, instant: 3, issue: 0 }, bulk: { ajaxUrl: '/ajax', nonce: 'test', printPreviewNonce: 'print' }, shipmentLocations: [],
    i18n: { scanToPay: 'Scan to Pay', action: 'Actions', detail: 'Detail', instantRecheck: 'Recheck', instantCancel: 'Cancel Instant', liveTracking: 'Live tracking', vehicle: 'Vehicle', changeOrigin: 'Change origin', adjustDeficit: 'Adjust deficit', cancel: 'Cancel', regularDelivery: 'Regular', instantDelivery: 'Instant', orderIssue: 'Order issue', transactionScope: 'Delivery workspace', print: 'Print', processShipment: 'Process shipment', requestPickup: 'Request pickup', allDates: 'Date range', apply: 'Apply', clearFilters: 'All', clear: 'Clear filters', allPrints: 'All prints', printed: 'Printed', unprinted: 'Unprinted', allPayment: 'All payment', cod: 'COD', nonCod: 'Non COD', status: 'Status', allCouriers: 'All couriers', search: 'Search', autoRefresh: 'Refresh transactions', refreshLabels: {}, notFound: 'No transactions', items: 'items', pageOf: 'of', searchStatuses: 'Search statuses', searchCouriers: 'Search couriers', selectedFilters: 'selected', noFilterOptions: 'No options' },
  };
  Object.assign(bootstrap.filters, filterOverrides);
  Object.assign(bootstrap.i18n, {
    emptyDescription: 'Transactions for the selected delivery type and date range will appear here.',
    emptyIssueTitle: 'No order issues found.',
    emptyIssueDescription: 'Orders with shipment issues for the selected date range will appear here.',
    emptyFilteredDescription: 'Try a different search or adjust the filters above.',
  });
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
  test('empty lists use labelled shared Empty components for each workspace without creating shipment actions', async () => {
    for (const [deliveryType, status] of [['express', 'all'], ['instant', 'all'], ['express', 'order-issue']]) {
      const h = await fixture(deliveryType, '', [], { key: '', courier: '', cod: '', status });
      try {
        const empty = h.target.querySelector('[data-slot="empty"]')!;
        expect(empty).not.toBeNull();
        expect(empty.closest('td')?.getAttribute('colspan')).toBe('7');
        expect(empty.getAttribute('role')).toBe('region');
        const title = empty.querySelector('[data-slot="empty-title"]')!;
        const description = empty.querySelector('[data-slot="empty-description"]')!;
        expect(empty.getAttribute('aria-labelledby')).toBe(title.id);
        expect(empty.getAttribute('aria-describedby')).toBe(description.id);
        expect(title.textContent).toBe(status === 'order-issue' ? 'No order issues found.' : 'No transactions');
        expect(description.textContent).toContain(status === 'order-issue' ? 'Orders with shipment issues' : 'Transactions for the selected delivery type');
        expect(empty.querySelector('[data-slot="empty-icon"] svg')?.getAttribute('aria-hidden')).toBe('true');
        expect(empty.querySelector('button, a')).toBeNull();
        expect(h.navigations).toEqual([]);
      } finally { await h.cleanup(); }
    }
  });

  test('All filter sentinels and hidden Instant COD do not create a false filtered empty state', async () => {
    for (const deliveryType of ['express', 'instant']) {
      const h = await fixture(deliveryType, 'all', [], { key: '', courier: 'all', cod: deliveryType === 'instant' ? '1' : 'all', status: 'all' });
      try {
        const empty = h.target.querySelector('[data-slot="empty"]')!;
        expect(empty.querySelector('button')).toBeNull();
        expect(empty.querySelector('[data-slot="empty-description"]')?.textContent).toContain('Transactions for the selected delivery type');
      } finally { await h.cleanup(); }
    }
  });

  test('empty filtered lists clear row filters once while preserving workspace, dates and page size', async () => {
    const h = await fixture('express', '1', [], { status: 'order-issue' });
    try {
      const empty = h.target.querySelector('[data-slot="empty"]')!;
      expect(empty.querySelector('[data-slot="empty-description"]')?.textContent).toBe('Try a different search or adjust the filters above.');
      const button = empty.querySelector<HTMLButtonElement>('button')!;
      expect(button.textContent?.trim()).toBe('Clear filters');
      expect(button.getAttribute('type')).toBe('button');
      expect(button.querySelector('svg')?.getAttribute('data-icon')).toBe('inline-start');
      button.focus();
      expect(h.window.document.activeElement).toBe(button);
      button.click(); await h.settle();
      expect(button.disabled).toBe(true);
      expect(h.navigations).toHaveLength(1);
      const url = new URL(h.navigations[0]);
      for (const key of ['key', 'courier', 'cod', 'print_status', 'search_by']) expect(url.searchParams.has(key)).toBe(false);
      expect(url.searchParams.get('status')).toBe('order-issue');
      expect(url.searchParams.get('delivery_type')).toBe('express');
      expect(url.searchParams.get('month')).toBe('2026-01');
      expect(url.searchParams.get('date_from')).toBe('2026-01-02');
      expect(url.searchParams.get('date_to')).toBe('2026-01-27');
      expect(url.searchParams.get('per_page')).toBe('50');
      expect(url.searchParams.get('cpage')).toBe('1');
      await h.finish(); expect(button.disabled).toBe(false);
    } finally { await h.cleanup(); }
  });

  const transactionRow = (deliveryType: string, paymentUrl: string) => ({
    id: 1, kaOrderId: 'KA-1', wcOrderId: 42, wcOrderUrl: '/order/42', detailUrl: '/detail/KA-1', deliveryType,
    customer: { name: 'Fixture buyer', phone: '' }, courier: { code: 'fixture', service: 'Fixture courier', paymentLabel: 'NON COD' },
    vehicle: 'motorbike', status: { code: 105, label: 'Waiting for pickup', tone: 'info' }, printStatus: 'printed',
    route: { origin: 'Warehouse', destination: 'Destination', addressLines: [] },
    package: { weight: 1000, quantity: 1, actualShipping: 12000, paidShipping: 12000 },
    selection: { disabled: true, canPickup: false, canPrint: false, title: '' },
    actions: { preview: true, paymentUrl, print: true, cancel: false, process: false, track: false, reconcile: false },
    actionData: {}, awb: 'AWB-1', createdAt: '2026-01-02',
  });
  test('Order issue shows the original invalid phone in red without a dial link or pickup selection', async () => {
    const reason = 'The recipient phone number is invalid. Correct the shipping or billing phone in the WooCommerce order before requesting pickup.';
    const row = {
      ...transactionRow('express', ''),
      customer: { name: 'Fixture buyer', phone: '+6285451111000000', phoneInvalid: true },
      status: { code: 'new', label: 'Invalid recipient phone', tone: 'warning', issue: reason, deficit: false },
      printStatus: 'unprinted', awb: '',
    };
    const h = await fixture('express', '', [row], { status: 'order-issue', key: '', courier: '', cod: '' });
    try {
      const phone = h.target.querySelector<HTMLElement>('.kiriof-customer-phone')!;
      expect(phone.textContent).toBe(row.customer.phone);
      expect(phone.classList.contains('!text-destructive')).toBe(true);
      expect(phone.tagName).toBe('SPAN');
      expect(phone.parentElement?.tagName).toBe('BUTTON');
      expect(phone.parentElement?.getAttribute('type')).toBe('button');
      expect(h.target.querySelector('a[href^="tel:"]')).toBeNull();
      expect(h.target.querySelector('[name="transaction_id[]"]')).toBeNull();
      expect(h.target.querySelector('tbody')?.textContent).toContain('Order issue');
      expect(h.target.querySelector('a[aria-label="Detail"]')).not.toBeNull();
      expect(h.target.textContent).not.toContain('Invalid recipient phone');
      expect(h.target.textContent).not.toContain(reason);
      const trigger = phone.parentElement!;
      trigger.focus(); await h.settle();
      for (let attempt = 0; attempt < 20 && !h.window.document.querySelector('[data-slot="tooltip-content"]'); attempt++) {
        await new Promise((resolve) => setTimeout(resolve, 10)); await h.settle();
      }
      expect(h.window.document.querySelector('[data-slot="tooltip-content"]')?.textContent).toContain(reason);
      trigger.blur(); await h.settle();
      await new Promise((resolve) => setTimeout(resolve, 20)); await h.settle();
    } finally { await h.cleanup(); }
  });
  test('a genuinely missing phone shows a placeholder rather than warning text', async () => {
    const row = {
      ...transactionRow('express', ''),
      customer: { name: 'Fixture buyer', phone: '', phoneInvalid: true },
      status: { label: 'Invalid recipient phone', tone: 'warning', issue: 'Correct the phone in the order.', deficit: false },
    };
    const h = await fixture('express', '', [row], { status: 'order-issue' });
    try {
      expect(h.target.querySelector('.kiriof-customer-phone')?.textContent).toBe('—');
      expect(h.target.textContent).not.toContain('Invalid recipient phone');
    } finally { await h.cleanup(); }
  });
  test('eligible Instant Scan to Pay is a grouped row action, never courier content', async () => {
    const paymentUrl = '/wp-admin/admin.php?page=kiriminaja-request-pickup&instant_payment_id=PAY-1&open_payment=1';
    const h = await fixture('instant', '0', [transactionRow('instant', paymentUrl)]); try {
      const row = h.target.querySelector('tbody tr')!;
      expect(h.target.querySelector('[data-slot="empty"]')).toBeNull();
      const payment = row.querySelector<HTMLAnchorElement>('a[aria-label="Scan to Pay"]')!;
      expect(payment.getAttribute('href')).toBe(paymentUrl);
      expect(payment.closest('td')?.classList.contains('is-actions')).toBe(true);
      expect(row.children[2].querySelector('[aria-label="Scan to Pay"]')).toBeNull();
      const group = row.querySelector('[data-slot="button-group"]')!;
      expect(group.getAttribute('role')).toBe('group');
      expect(group.getAttribute('aria-label')).toBe('Actions #42');
      expect([...group.querySelectorAll('[data-slot="button"]')].map(button => button.getAttribute('aria-label'))).toEqual(['Detail', 'Scan to Pay', 'Print']);
      expect(h.navigations).toEqual([]);
    } finally { await h.cleanup(); }
  });
  test('ineligible Instant and Regular rows never render a Scan to Pay action', async () => {
    for (const [type, url] of [['instant', ''], ['express', '/must-not-render']]) {
      const h = await fixture(type, '0', [transactionRow(type, url)]); try {
        expect(h.target.querySelector('[aria-label="Scan to Pay"]')).toBeNull();
        expect(h.target.querySelector('td.is-actions [data-slot="button-group"]')).not.toBeNull();
      } finally { await h.cleanup(); }
    }
  });
  test('tooltip-composed Instant actions keep their click handlers and order identity', async () => {
    for (const [flag, label, mode] of [['reconcile', 'Recheck', 'reconcile'], ['track', 'Live tracking', 'tracking'], ['cancel', 'Cancel Instant', 'cancel'], ['process', 'Process shipment', ''], ['print', 'Print', '']]) {
      const row = transactionRow('instant', ''); row.actions = { ...row.actions, print: false, [flag]: true };
      const h = await fixture('instant', '0', [row]); try {
        h.target.querySelector<HTMLButtonElement>(`td.is-actions button[aria-label="${label}"]`)!.click(); await h.settle();
        const dialogs = [...h.target.querySelectorAll('[data-dialog-boundary]')].map(node => JSON.parse(node.textContent!));
        expect(dialogs.some(dialog => dialog.orderIds?.[0] === 'KA-1' && (mode ? dialog.mode === mode : dialog.open))).toBe(true);
        expect(h.navigations).toEqual([]);
      } finally { await h.cleanup(); }
    }
  });
  test('tooltip-composed Express actions preserve origin, deficit and cancel handlers', async () => {
    for (const [flag, label, kind] of [['changeOrigin', 'Change origin', 'origin'], ['adjustDeficit', 'Adjust deficit', 'adjust-deficit'], ['adjustDeficit', 'Cancel', 'cancel-deficit'], ['cancel', 'Cancel', 'cancel']]) {
      const row = transactionRow('express', ''); row.actions = { ...row.actions, [flag]: true }; row.actionData = { orderId: 'KA-1' };
      const h = await fixture('express', '0', [row]); try {
        h.target.querySelector<HTMLButtonElement>(`td.is-actions button[aria-label="${label}"]`)!.click(); await h.settle();
        const dialog = JSON.parse(h.target.querySelector('[data-dialog-boundary]')!.textContent!);
        expect(dialog.action).toEqual({ kind, data: { orderId: 'KA-1' } });
        if (flag === 'adjustDeficit') expect(h.target.querySelector('td.is-actions button[aria-label="Print"]')).toBeNull();
      } finally { await h.cleanup(); }
    }
  });
  test('tracking always opens the iframe dialog rather than navigating to a stored URL', async () => {
    for (const url of ['https://tracking.example.test/KA-1', 'javascript:alert(1)']) {
      const row = transactionRow('instant', ''); row.actions = { ...row.actions, track: true, liveTrackingUrl: url };
      const h = await fixture('instant', '0', [row]); try {
        const control = h.target.querySelector<HTMLElement>('td.is-actions [aria-label="Live tracking"]')!;
        expect(control.tagName).toBe('BUTTON'); control.click(); await h.settle();
        expect(JSON.parse(h.target.querySelector('[data-dialog-boundary]')!.textContent!).mode).toBe('tracking');
      } finally { await h.cleanup(); }
    }
  });
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
