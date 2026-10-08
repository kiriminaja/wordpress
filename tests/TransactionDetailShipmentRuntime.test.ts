import { afterAll, describe, expect, test } from 'bun:test';
import { existsSync, mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';

const root = resolve(import.meta.dir, '..');
let buildDirectory: string | undefined;
let bundlePromise: Promise<Buffer> | undefined;
afterAll(() => { if (buildDirectory) rmSync(buildDirectory, { recursive: true, force: true }); });

// Compile the real detail component once; compile production once, import unique bundle bytes
// per Window so Svelte's cached native DOM getters never cross fixture boundaries.
// Only unrelated map/modal/toolbar/tooltip boundaries and SVG artwork are
// substituted; shipment markup, badges, and all amount logic remain production.
async function buildBundle(): Promise<Buffer> {
  const directory = buildDirectory = mkdtempSync(join(root, 'node_modules', '.transaction-detail-build-'));
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
  put('entry.ts', `export {mount, unmount, flushSync} from 'svelte'; export {default as App} from '${join(root, 'src/lib/transaction-detail/TransactionDetail.svelte')}';`);
  const build = await Bun.build({ entrypoints: [join(directory, 'entry.ts')], outdir: directory, naming: 'runtime.js', target: 'browser', conditions: ['browser'], plugins: [{ name: 'transaction-detail-production-runtime', setup(builder) {
    builder.onResolve({ filter: /(?:PrintPreviewDialog|InstantProcessDialog|RequestPickupDialog|InstantRouteMap|TransactionActionDialogs|InstantOperationDialog)\.svelte$/ }, () => ({ path: join(directory, 'Empty.svelte') }));
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


async function fixture(options: { deliveryType?: string; vehicle?: string | null; costs?: Record<string, number>; payment?: Record<string, string>; fallback?: boolean } = {}) {
  const directory = mkdtempSync(join(root, 'node_modules', '.transaction-detail-runtime-'));
  const window = new happy.Window({ url: 'https://fixture.test/wp-admin/' });
  const saved = new Map<string, PropertyDescriptor | undefined>();
  const globals = { window, document: window.document, navigator: window.navigator, Node: window.Node, Element: window.Element, HTMLElement: window.HTMLElement, HTMLButtonElement: window.HTMLButtonElement, SVGElement: window.SVGElement, DocumentFragment: window.DocumentFragment, Text: window.Text, Comment: window.Comment, Event: window.Event, CustomEvent: window.CustomEvent, MutationObserver: window.MutationObserver, getComputedStyle: window.getComputedStyle.bind(window), requestAnimationFrame: window.requestAnimationFrame.bind(window), cancelAnimationFrame: window.cancelAnimationFrame.bind(window) };
  for (const [key, value] of Object.entries(globals)) { saved.set(key, Object.getOwnPropertyDescriptor(globalThis, key)); Object.defineProperty(globalThis, key, { configurable: true, writable: true, value }); }
  let runtime: any;
  let app: any;
  const target = window.document.createElement('main'); window.document.body.append(target);
  const bootstrap = {
    toolbar: {}, bootstrapError: options.fallback ? 'Fallback detail available' : '',
    transaction: {
      deliveryType: options.deliveryType ?? 'express', vehicle: options.vehicle === undefined ? 'motor' : options.vehicle,
      id: 42, orderId: '42', orderNumber: '#42', orderUrl: '', createdAt: '', paymentLabel: 'COD', isCod: false,
      supportsLiveTracking: false, pickupNumber: '', status: { label: 'Processed', tone: 'neutral' }, steps: [],
      sender: { name: 'Sender', phone: '', address: [] }, recipient: { name: 'Recipient', phone: '', address: [] },
      package: { weight: 100, length: 1, width: 2, height: 3 }, items: [], notes: [],
      shipment: {
        courier: { code: 'jne', service: 'REG' }, awb: '', printUrl: '', trackingOrder: '', liveTrackingUrl: '', codValue: 0,
        paymentMethod: 'QRIS', paymentStatus: 'Paid', paymentId: 'PAY-42', ...options.payment,
        costs: { subtotal: 100000, orderTotal: 111000, totalShipping: 10000, actualShipping: 10000, shipping: 10000, shippingDiscount: 0, insurance: 0, codFee: 0, itemDiscount: 0, total: 11000, ...options.costs },
      },
      actions: { track: false, changeOrigin: false, adjustDeficit: false, cancelDeficit: false, cancel: false, data: {} },
    },
    ajax: { url: '/ajax', nonce: 'test', printPreviewNonce: 'print' }, shipmentLocations: [], locationsUrl: '',
    i18n: { shipment: 'Shipment', orderId: 'Order ID', orderSubtotal: 'Order Subtotal', totalShipping: 'Total Shipping', actualShipping: 'Actual Shipping', shipping: 'Shipping', shippingDiscount: 'Shipping Discount', insurance: 'Insurance', codFee: 'COD Fee', adminFee: 'Admin Fee', total: 'Total', paymentMethod: 'Payment Method', paymentStatus: 'Payment Status', paymentId: 'Payment ID', pickup: 'Pickup', unpaid: 'Unpaid', vehicle: 'Vehicle', vehicleUnavailable: 'Vehicle unavailable', motor: 'Translated Motor', mobil: 'Translated Mobil' },
  };
  async function cleanup() {
    if (app) await runtime.unmount(app);
    window.happyDOM.abort();
    for (const [key, descriptor] of saved) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete (globalThis as any)[key]; }
    rmSync(directory, { recursive: true, force: true });
  }
  try {
    writeFileSync(join(directory, 'runtime.js'), await (bundlePromise ??= buildBundle()));
    runtime = await import(join(directory, 'runtime.js'));
    app = runtime.mount(runtime.App, { target, props: { bootstrap } });
    for (let i = 0; i < 8; i++) { await Promise.resolve(); runtime.flushSync(); }
    const card = target.querySelector('.kiriof-shipment-card')!;
    const rows = () => [...card.querySelectorAll('dl > div')].map((node) => [node.querySelector('dt')?.textContent?.trim(), node.querySelector('dd')?.textContent?.trim()]);
    const paymentRows = () => [...target.querySelectorAll('.kiriof-detail-carrier-payment > div')].map((node) => [node.querySelector('dt')?.textContent?.trim(), node.querySelector('dd')?.textContent?.trim()]);
    return { card, target, rows, paymentRows, cleanup };
  } catch (error) { await cleanup(); throw error; }
}

describe('TransactionDetail shipment (compiled production Svelte)', () => {
  for (const deliveryType of ['express', 'instant']) {
    for (const fallback of [false, true]) test(`${deliveryType} ${fallback ? 'fallback' : 'normal'} collapses equal carrier amounts and keeps admin fee separate before Total`, async () => {
      const h = await fixture({ deliveryType, fallback, costs: { adminFee: 1000 } });
      try {
        expect(h.rows()).toEqual([['Order ID', '42'], ['Order Subtotal', 'Rp100.000'], ['Shipping', 'Rp10.000'], ['Admin Fee', 'Rp1.000'], ['Total', 'Rp111.000']]);
        expect(h.card.textContent).not.toContain('Actual Shipping');
        expect(h.card.textContent).not.toContain('Total Shipping');
      } finally { await h.cleanup(); }
    });
    test(`${deliveryType} defaults missing admin fee to zero and hides zero/negative fees`, async () => {
      for (const costs of [{}, { adminFee: 0 }, { adminFee: -100 }]) {
        const h = await fixture({ deliveryType, costs });
        try { expect(h.rows().map(([label]) => label)).not.toContain('Admin Fee'); }
        finally { await h.cleanup(); }
      }
    });
    test(`${deliveryType} displays exact available payment fields and neutral placeholders for absent values`, async () => {
      for (const payment of [{ paymentMethod: 'Wallet', paymentStatus: 'pending-provider', paymentId: 'INV/<42>' }, { paymentMethod: '', paymentStatus: '', paymentId: '' }]) {
        const h = await fixture({ deliveryType, payment });
        try {
          expect(h.paymentRows()).toEqual([['Payment ID', payment.paymentId || '—'], ['Payment Method', payment.paymentMethod || '—'], ['Payment Status', payment.paymentStatus || '—']]);
        } finally { await h.cleanup(); }
      }
    });
  }
  for (const costs of [
    { shipping: 8000, shippingDiscount: 2000 },
    { shippingDiscount: 500 },
    { actualShipping: 12000 },
    { totalShipping: 13000 },
    { totalShipping: 11000, insurance: 1000 },
    { totalShipping: 12000, codFee: 2000 },
  ]) test(`preserves carrier breakdown for ${JSON.stringify(costs)}`, async () => {
    const h = await fixture({ costs: { ...costs, adminFee: 1000 } });
    try {
      const labels = h.rows().map(([label]) => label);
      expect(labels).toContain('Total Shipping'); expect(labels).toContain('Actual Shipping'); expect(labels).toContain('Shipping');
      if (costs.shippingDiscount) expect(h.rows()).toContainEqual(['Shipping Discount', `−Rp${new Intl.NumberFormat('id-ID').format(costs.shippingDiscount)}`]);
      if (costs.insurance) expect(labels).toContain('Insurance');
      if (costs.codFee) expect(labels).toContain('COD Fee');
      expect(labels.slice(-2)).toEqual(['Admin Fee', 'Total']);
    } finally { await h.cleanup(); }
  });
  for (const [vehicle, label] of [['motor', 'Translated Motor'], ['mobil', 'Translated Mobil'], ['cargo-bike', 'cargo-bike'], [null, 'Vehicle unavailable']] as const) test(`Instant ${vehicle} vehicle occupies shipment header badge, not definition row`, async () => {
    const h = await fixture({ deliveryType: 'instant', vehicle });
    try {
      expect(h.card.querySelector('[data-slot="card-header"]')?.textContent).toContain(label);
      expect(h.rows().map(([name]) => name)).not.toContain('Vehicle');
      expect(h.card.textContent).not.toContain('Pickup');
    } finally { await h.cleanup(); }
  });
});
