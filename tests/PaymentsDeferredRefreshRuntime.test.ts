import { afterAll, describe, expect, test } from 'bun:test';
import { existsSync, mkdtempSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';
import type { PaymentRow } from '../src/lib/payments/types';

const root = resolve(import.meta.dir, '..');
let buildDirectory: string | undefined;
let bundlePromise: Promise<Buffer> | undefined;
afterAll(() => {
  if (buildDirectory) rmSync(buildDirectory, { recursive: true, force: true });
});

async function buildBundle(): Promise<Buffer> {
  const directory = (buildDirectory = mkdtempSync(
    join(root, 'node_modules', '.payments-refresh-build-'),
  ));
  const put = (name: string, source: string) => writeFileSync(join(directory, name), source);
  put('Boundary.svelte', '<script>let { children } = $props();</script>{@render children?.()}');
  put('Empty.svelte', '');
  put(
    'Icon.svelte',
    '<script>let props = $props();</script><svg aria-hidden="true" {...props}></svg>',
  );
  const icons = [
    'IconBuildingBank',
    'IconCalendarClock',
    'IconCircleCheck',
    'IconClock',
    'IconCreditCardPay',
    'IconEye',
    'IconQrcode',
    'IconSearch',
    'IconChevronDown',
    'IconRefresh',
    'IconLoader2',
    'IconX',
  ];
  put(
    'icons.ts',
    `export { ${icons.map((name) => `default as ${name}`).join(', ')} } from './Icon.svelte';`,
  );
  put(
    'Child.svelte',
    `<script>
    let { open = $bindable(false), pickupNumber, paymentId, orderIds } = $props();
  </script>
  {#if open}<section data-loaded-payment data-identity={paymentId || pickupNumber} data-orders={JSON.stringify(orderIds)}>
    <button onclick={() => open = false}>Close loaded payment</button>
  </section>{/if}`,
  );
  put(
    'entry.ts',
    `export { mount, unmount, flushSync } from 'svelte'; export { default as App } from '${join(root, 'src/lib/payments/PaymentsList.svelte')}'; export { default as Child } from './Child.svelte';`,
  );
  const result = await Bun.build({
    entrypoints: [join(directory, 'entry.ts')],
    outdir: directory,
    naming: 'runtime.js',
    target: 'browser',
    conditions: ['browser'],
    plugins: [
      {
        name: 'payments-deferred-refresh-production',
        setup(builder) {
          builder.onResolve(
            {
              filter:
                /(?:Toolbar|WorkspaceTabs|DateRangeFilter|DataTableFooter|StatusBadge)\.svelte$/,
            },
            () => ({ path: join(directory, 'Empty.svelte') }),
          );
          builder.onResolve({ filter: /(?:KiriofCard|ActionTooltip)\.svelte$/ }, () => ({
            path: join(directory, 'Boundary.svelte'),
          }));
          builder.onResolve({ filter: /^@tabler\/icons-svelte$/ }, () => ({
            path: join(directory, 'icons.ts'),
          }));
          builder.onResolve({ filter: /^\$lib\// }, ({ path }) => {
            const base = join(root, 'src/lib', path.slice(5));
            const found = [
              base,
              base.replace(/\.js$/, '.ts'),
              `${base}.ts`,
              join(base, 'index.ts'),
            ].find((candidate) => existsSync(candidate) && statSync(candidate).isFile());
            if (!found) throw new Error(`Unresolved production alias: ${path}`);
            return { path: found };
          });
          builder.onResolve({ filter: /^svelte$/ }, () => ({
            path: join(root, 'node_modules/svelte/src/index-client.js'),
          }));
          builder.onLoad({ filter: /\.svelte\.[jt]s$/ }, ({ path }) => ({
            contents: compileModule(
              path.endsWith('.ts')
                ? new Bun.Transpiler({ loader: 'ts' }).transformSync(readFileSync(path, 'utf8'))
                : readFileSync(path, 'utf8'),
              { filename: path, generate: 'client' },
            ).js.code,
            loader: 'js',
          }));
          builder.onLoad({ filter: /\.svelte$/ }, ({ path }) => {
            let source = readFileSync(path, 'utf8');
            // Only module transport is controlled. The page handlers, DeferredDialog,
            // loading KiriofDialog and AutoRefresh execute their production code.
            if (path === join(root, 'src/lib/payments/PaymentsList.svelte')) {
              for (const name of [
                'PaymentScheduleDialog',
                'ScanToPayDialog',
                'InstantScanToPayDialog',
              ]) {
                const expression = `import('./${name}.svelte')`;
                if (!source.includes(expression))
                  throw new Error(`Missing deferred module seam: ${name}`);
                source = source.replace(expression, `window.__loadPaymentDialog('${name}')`);
              }
            }
            return {
              contents: compile(source, { filename: path, generate: 'client' }).js.code,
              loader: 'js',
            };
          });
        },
      },
    ],
  });
  if (!result.success) throw new Error(result.logs.join('\n'));
  return readFileSync(join(directory, 'runtime.js'));
}

type Scenario = {
  name: string;
  deliveryType: 'express' | 'instant';
  action: 'pay' | 'reschedule';
  module: string;
};
const scenarios: Scenario[] = [
  { name: 'Express payment', deliveryType: 'express', action: 'pay', module: 'ScanToPayDialog' },
  {
    name: 'Instant payment',
    deliveryType: 'instant',
    action: 'pay',
    module: 'InstantScanToPayDialog',
  },
  {
    name: 'Express reschedule',
    deliveryType: 'express',
    action: 'reschedule',
    module: 'PaymentScheduleDialog',
  },
];

async function fixture(scenario: Scenario, deepLink = false) {
  const directory = mkdtempSync(join(root, 'node_modules', '.payments-refresh-runtime-'));
  const url = new URL(
    'https://fixture.test/wp-admin/admin.php?page=kiriminaja-request-pickup&status=unpaid&key=parcel&cpage=2',
  );
  if (deepLink) {
    url.searchParams.set('open_payment', '1');
    url.searchParams.set(
      scenario.deliveryType === 'instant' ? 'instant_payment_id' : 'pickup_number',
      'PAY-42',
    );
  }
  const window = new happy.Window({ url: url.href });
  const timers = new Map<number, () => void>();
  let timerId = 0;
  window.setInterval = ((callback: () => void) => {
    timers.set(++timerId, callback);
    return timerId;
  }) as any;
  window.clearInterval = ((id: number) => {
    timers.delete(id);
  }) as any;
  const requests: { name: string; resolve: (module: any) => void }[] = [];
  (window as any).__loadPaymentDialog = (name: string) =>
    new Promise((resolve) => requests.push({ name, resolve }));
  const saved = new Map<string, PropertyDescriptor | undefined>();
  const globals = {
    window,
    document: window.document,
    navigator: window.navigator,
    Node: window.Node,
    Element: window.Element,
    HTMLElement: window.HTMLElement,
    HTMLButtonElement: window.HTMLButtonElement,
    HTMLInputElement: window.HTMLInputElement,
    HTMLSelectElement: window.HTMLSelectElement,
    HTMLMediaElement: window.HTMLMediaElement,
    SVGElement: window.SVGElement,
    DocumentFragment: window.DocumentFragment,
    Text: window.Text,
    Comment: window.Comment,
    Event: window.Event,
    KeyboardEvent: window.KeyboardEvent,
    CustomEvent: window.CustomEvent,
    MutationObserver: window.MutationObserver,
    ResizeObserver: window.ResizeObserver,
    getComputedStyle: window.getComputedStyle.bind(window),
    requestAnimationFrame: window.requestAnimationFrame.bind(window),
    cancelAnimationFrame: window.cancelAnimationFrame.bind(window),
  };
  for (const [key, value] of Object.entries(globals)) {
    saved.set(key, Object.getOwnPropertyDescriptor(globalThis, key));
    Object.defineProperty(globalThis, key, { configurable: true, writable: true, value });
  }
  const navigations: string[] = [];
  let runtime: any;
  let app: any;
  const target = window.document.createElement('main');
  window.document.body.append(target);
  const row: PaymentRow = {
    number: 1,
    rowKey: 'payment-42',
    deliveryType: scenario.deliveryType,
    identity: 'PAY-42',
    pickupNumber: 'PAY-42',
    orderIds: ['ORDER-42'],
    requestedAt: '2026-10-29',
    schedule: '2026-10-30',
    fees: 'Rp 12.000',
    orders: 1,
    method: 'QRIS',
    status: 'unpaid',
    actions: [{ type: scenario.action, label: scenario.name }],
  };
  async function settle() {
    for (let i = 0; i < 20; i++) {
      await Promise.resolve();
      runtime.flushSync();
    }
  }
  async function cleanup() {
    try {
      if (app) await runtime.unmount(app);
      window.happyDOM.abort();
    } finally {
      for (const [key, descriptor] of saved) {
        if (descriptor) Object.defineProperty(globalThis, key, descriptor);
        else delete (globalThis as any)[key];
      }
      rmSync(directory, { recursive: true, force: true });
    }
  }
  try {
    // Each Window needs a separate Svelte/Bits UI DOM cache.
    writeFileSync(join(directory, 'runtime.js'), await (bundlePromise ??= buildBundle()));
    runtime = await import(join(directory, 'runtime.js'));
    app = runtime.mount(runtime.App, {
      target,
      props: {
        initialBootstrap: {
          toolbar: {},
          rows: [row],
          filters: { key: 'parcel', month: '', status: 'unpaid' },
          monthOptions: {},
          statusTabs: [{ value: 'unpaid', label: 'Unpaid', count: 1 }],
          pagination: { page: 2, totalPages: 2, total: 1, perPage: 20 },
          ajax: { url: '/ajax', nonce: 'fixture' },
          modals: {},
          i18n: {
            search: 'Search',
            autoRefresh: 'Refresh payments',
            refreshLabels: {},
            loading: 'Loading payment workspace',
            close: 'Cancel workspace',
          },
        },
        onNavigate: (next: URL) => {
          navigations.push(next.href);
        },
      },
    });
    await settle();
    function button(label: string) {
      const found = [...window.document.querySelectorAll<HTMLButtonElement>('button')].find(
        (candidate) =>
          candidate.textContent?.trim() === label || candidate.getAttribute('aria-label') === label,
      );
      if (!found) throw new Error(`Missing button: ${label}`);
      return found;
    }
    const refreshButtons = () => [
      ...target.querySelectorAll<HTMLButtonElement>('.kiriof-auto-refresh button'),
    ];
    async function tick(seconds: number) {
      for (let i = 0; i < seconds; i++) {
        for (const callback of [...timers.values()]) callback();
        await settle();
      }
    }
    return {
      window,
      target,
      timers,
      requests,
      navigations,
      settle,
      button,
      refreshButtons,
      tick,
      cleanup,
      resolveModule: async () => {
        requests[0].resolve({ default: runtime.Child });
        await settle();
      },
    };
  } catch (error) {
    await cleanup();
    throw error;
  }
}

async function assertPendingPause(h: Awaited<ReturnType<typeof fixture>>, scenario: Scenario) {
  expect(h.requests.map((request) => request.name)).toEqual([scenario.module]);
  expect(h.window.document.querySelector('[role="dialog"]')?.textContent).toContain(
    'Loading payment workspace',
  );
  expect(h.target.querySelector('[data-loaded-payment]')).toBeNull();
  expect(h.timers.size).toBe(0);
  expect(h.refreshButtons()).toHaveLength(2);
  for (const button of h.refreshButtons()) expect(button.disabled).toBe(true);
  const countdown = h.refreshButtons()[0].textContent;
  h.refreshButtons()[0].click();
  await h.tick(60);
  expect(h.refreshButtons()[0].textContent).toBe(countdown);
  expect(h.navigations).toEqual([]);
}

async function assertResumedRefresh(h: Awaited<ReturnType<typeof fixture>>) {
  expect(h.timers.size).toBe(1);
  for (const button of h.refreshButtons()) expect(button.disabled).toBe(false);
  await h.tick(59);
  expect(h.refreshButtons()[0].textContent?.trim()).toBe('1s');
  expect(h.navigations).toEqual([]);
  await h.tick(1);
  expect(h.navigations).toHaveLength(1);
  const next = new URL(h.navigations[0]);
  expect(next.searchParams.get('status')).toBe('unpaid');
  expect(next.searchParams.get('key')).toBe('parcel');
  expect(h.refreshButtons()[0].textContent?.trim()).toBe('1m');
}

describe('PaymentsList deferred dialogs pause the real AutoRefresh', () => {
  for (const scenario of scenarios) {
    test(`${scenario.name}: pending close resumes refresh; loaded dialog stays paused until closed`, async () => {
      const h = await fixture(scenario);
      try {
        expect(h.timers.size).toBe(1);
        h.button(scenario.name).click();
        await h.settle();
        await assertPendingPause(h, scenario);
        h.button('Cancel workspace').click();
        await h.settle();
        expect(h.window.document.querySelector('[role="dialog"]')).toBeNull();
        await assertResumedRefresh(h);
        h.navigations.length = 0;
        h.button(scenario.name).click();
        await h.settle();
        expect(h.requests).toHaveLength(1);
        await h.resolveModule();
        expect(h.target.querySelector('[data-loaded-payment]')?.getAttribute('data-identity')).toBe(
          'PAY-42',
        );
        if (scenario.deliveryType === 'instant')
          expect(h.target.querySelector('[data-loaded-payment]')?.getAttribute('data-orders')).toBe(
            '["ORDER-42"]',
          );
        expect(h.timers.size).toBe(0);
        for (const button of h.refreshButtons()) expect(button.disabled).toBe(true);
        await h.tick(60);
        expect(h.navigations).toEqual([]);
        h.button('Close loaded payment').click();
        await h.settle();
        await assertResumedRefresh(h);
      } finally {
        await h.cleanup();
      }
    }, 20000);
  }
  for (const scenario of scenarios.filter((item) => item.action === 'pay')) {
    test(`${scenario.name}: production deep link pauses before the module resolves`, async () => {
      const h = await fixture(scenario, true);
      try {
        expect(new URL(h.window.location.href).searchParams.has('open_payment')).toBe(false);
        await assertPendingPause(h, scenario);
        h.button('Cancel workspace').click();
        await h.settle();
        await assertResumedRefresh(h);
      } finally {
        await h.cleanup();
      }
    }, 20000);
  }
});
