import { afterAll, describe, expect, test } from 'bun:test';
import { existsSync, mkdtempSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import type { WarehousesBootstrap, WarehouseLocation } from '../src/lib/settings/warehouses-types';
import { happy } from './helpers/ui-runtime';

const root = resolve(import.meta.dir, '..');
let buildDirectory: string | undefined;
let bundle: Promise<Buffer> | undefined;
afterAll(() => {
  if (buildDirectory) rmSync(buildDirectory, { recursive: true, force: true });
});

// Compile the actual dialog, shared Badge/Button, icons and bits-ui once. Each
// Window imports unique bundle bytes so Svelte's DOM caches remain isolated.
async function buildBundle() {
  const directory = (buildDirectory = mkdtempSync(
    join(root, 'node_modules', '.plugin-update-build-'),
  ));
  writeFileSync(
    join(directory, 'entry.ts'),
    `export {mount, unmount, flushSync} from 'svelte'; export {default as App} from '${join(root, 'src/lib/settings/WarehousesSection.svelte')}';`,
  );
  const result = await Bun.build({
    entrypoints: [join(directory, 'entry.ts')],
    outdir: directory,
    naming: 'runtime.js',
    target: 'browser',
    conditions: ['browser'],
    plugins: [
      {
        name: 'plugin-update-production',
        setup(builder) {
          builder.onResolve({ filter: /^@tabler\/icons-svelte$/ }, () => ({
            path: join(root, 'node_modules/@tabler/icons-svelte/dist/tabler-icons-svelte.js'),
          }));
          builder.onResolve({ filter: /^\$lib\// }, ({ path }) => {
            const base = join(root, 'src/lib', path.slice(5));
            const found = [
              base,
              base.replace(/\.js$/, '.ts'),
              `${base}.ts`,
              join(base, 'index.ts'),
            ].find((candidate) => existsSync(candidate) && statSync(candidate).isFile());
            if (!found) throw new Error(`Unresolved alias ${path}`);
            return { path: found };
          });
          builder.onLoad({ filter: /buyer\/map\/providers\.ts$/ }, () => ({
            contents: `export async function createProviderMapSession(options) {
              const win = options.node.ownerDocument.defaultView;
              win.mapOptions = options; win.mapDisposed = false;
              const session = {isAvailable:()=>true, dispose(){win.mapDisposed=true}, locate(){win.located=(win.located||0)+1}, pick(lat,lng){options.onSelect({latitude:String(lat),longitude:String(lng)});return true}};
              if(win.deferMap) return new Promise(resolve=>{win.resolveMap=()=>resolve(session)});
              return session;
            }`,
            loader: 'js',
          }));
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
          builder.onLoad({ filter: /\.svelte$/ }, ({ path }) => ({
            contents: compile(readFileSync(path, 'utf8'), { filename: path, generate: 'client' }).js
              .code,
            loader: 'js',
          }));
        },
      },
    ],
  });
  if (!result.success) throw new Error(result.logs.join('\n'));
  return readFileSync(join(directory, 'runtime.js'));
}

const row: WarehouseLocation = {
  id: 1,
  name: 'Default warehouse',
  sender_name: 'Default warehouse',
  sender_phone: '08123',
  address: 'Street',
  address_2: '',
  city: 'Jakarta',
  country_state: 'ID:JK',
  zip: '10110',
  district: '123',
  district_label: 'Jakarta district',
  lat: '-6',
  lng: '106',
  is_active: true,
  is_default: true,
};
const custom = { ...row, id: 2, name: 'Branch', sender_name: 'Branch', is_default: false };
const i18n = Object.fromEntries(
  [
    'title',
    'description',
    'add',
    'edit',
    'name',
    'region',
    'status',
    'active',
    'inactive',
    'default',
    'empty',
    'senderPhone',
    'address',
    'address2',
    'city',
    'countryState',
    'area',
    'zip',
    'pin',
    'myLocation',
    'save',
    'saving',
    'cancel',
    'delete',
    'confirmDelete',
    'saveFailed',
    'limitReached',
    'loading',
    'noResults',
    'typeMore',
    'mapUnavailable',
    'pinHint',
    'pinSelected',
    'searchFailed',
  ].map((key) => [key, key]),
);
i18n.limit = '%1$d of %2$d';
const bootstrap: WarehousesBootstrap = {
  view: 'warehouses',
  toolbar: { logoUrl: '', rootUrl: '/admin.php', rootLabel: 'Settings', title: 'Warehouses' },
  ajaxUrl: '/ajax',
  nonce: 'warehouse-nonce',
  locations: [row, custom],
  countryStates: [
    { value: 'ID:JK', label: 'Jakarta' },
    { value: 'ID:JB', label: 'West Java' },
    { value: 'ID:YO', label: 'Indonesia — DI Yogyakarta' },
  ],
  editingKey: '',
  customCount: 1,
  customLimit: 5,
  canCreate: true,
  actions: { save: 'save-warehouse', delete: 'delete-warehouse', toggle: 'toggle-warehouse' },
  map: { provider: 'google', apiKey: 'test-key-123' },
  i18n,
};
async function fixture(overrides: Partial<WarehousesBootstrap> = {}) {
  const directory = mkdtempSync(join(root, 'node_modules', '.plugin-update-runtime-'));
  const window = new happy.Window({ url: 'https://fixture.test/wp-admin/admin.php' });
  let geolocationRequests = 0;
  Object.defineProperty(window.navigator, 'geolocation', {
    configurable: true,
    value: {
      getCurrentPosition() {
        geolocationRequests++;
      },
    },
  });
  const saved = new Map<string, PropertyDescriptor | undefined>();
  const fetchCalls: URLSearchParams[] = [];
  const responses: Array<any> = [];
  const fetch = (...args: unknown[]) => {
    fetchCalls.push((args[1] as any).body);
    const response = responses.shift() ?? { success: false, data: { message: 'Server rejected' } };
    return typeof response === 'function'
      ? response()
      : Promise.resolve(new Response(JSON.stringify(response)));
  };
  window.fetch = fetch as typeof window.fetch;
  const globals = {
    window,
    fetch,
    document: window.document,
    navigator: window.navigator,
    Node: window.Node,
    Element: window.Element,
    HTMLElement: window.HTMLElement,
    HTMLMediaElement: window.HTMLMediaElement,
    HTMLFormElement: window.HTMLFormElement,
    HTMLDialogElement: window.HTMLDialogElement,
    HTMLButtonElement: window.HTMLButtonElement,
    HTMLInputElement: window.HTMLInputElement,
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
  let runtime: any;
  let component: any;
  async function cleanup() {
    try {
      if (component) await runtime.unmount(component);
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
    writeFileSync(join(directory, 'runtime.js'), await (bundle ??= buildBundle()));
    runtime = await import(join(directory, 'runtime.js'));
    const target = window.document.createElement('main');
    window.document.body.append(target);
    component = runtime.mount(runtime.App, {
      target,
      props: { bootstrap: { ...bootstrap, ...overrides } },
    });
    async function settle() {
      for (let i = 0; i < 20; i++) {
        await Promise.resolve();
        runtime.flushSync();
      }
      await new Promise((resolve) => setTimeout(resolve, 30));
      runtime.flushSync();
    }
    await settle();
    const trigger = target.querySelector('[data-warehouse-add]')!;
    const dialog = () => window.document.querySelector('[role="dialog"]')!;
    async function open() {
      trigger.focus();
      (trigger as any).click();
      await settle();
    }
    return {
      window,
      target,
      trigger,
      dialog,
      settle,
      open,
      cleanup,
      fetchCalls,
      responses,
      runtime,
      geolocationRequests: () => geolocationRequests,
      async destroy() {
        await runtime.unmount(component);
        component = null;
      },
    };
  } catch (error) {
    await cleanup();
    throw error;
  }
}

function button(h: any, text: string, scope = h.window.document) {
  return [...scope.querySelectorAll('button')].find(
    (node: any) => node.textContent.trim() === text,
  ) as any;
}
async function click(h: any, selector: string) {
  const node = h.window.document.querySelector(selector)!;
  node.focus();
  node.click();
  await h.settle();
  return node;
}
function submit(h: any) {
  h.window.document
    .querySelector('form')
    .dispatchEvent(new h.window.Event('submit', { bubbles: true, cancelable: true }));
}
function input(h: any, id: string, value: string) {
  const node = h.window.document.getElementById(id)!;
  node.value = value;
  node.dispatchEvent(new h.window.Event('input', { bubbles: true }));
}
describe('Managed warehouses compiled production UI', () => {
  test('toolbar add immediately mounts map without requesting geolocation, validates and restores focus', async () => {
    const h = await fixture();
    try {
      await h.open();
      expect(h.dialog()).not.toBeNull();
      expect(h.fetchCalls).toHaveLength(0);
      expect(h.trigger.closest('.kiriof-app-toolbar__actions')).not.toBeNull();
      expect(h.trigger.textContent.trim()).toBe('add');
      expect(h.window.document.querySelectorAll('[data-warehouse-add]')).toHaveLength(1);
      expect((h.window as any).mapOptions.initial).toBeNull();
      expect((h.window as any).located).toBeUndefined();
      expect(h.geolocationRequests()).toBe(0);
      expect(h.window.document.querySelector('#warehouse-pin[role="region"]')).not.toBeNull();
      expect(button(h, 'pin', h.dialog())).toBeUndefined();
      submit(h);
      await h.settle();
      expect(h.dialog().querySelector('[role="alert"]')?.textContent).toBe('saveFailed');
      expect(h.window.document.getElementById('warehouse-name')?.getAttribute('aria-invalid')).toBe(
        'true',
      );
      expect(h.fetchCalls).toHaveLength(0);
      const pin = h.window.document.getElementById('warehouse-pin');
      expect(pin?.closest('[data-invalid]')?.getAttribute('aria-invalid')).toBe('true');
      expect(pin?.getAttribute('aria-describedby')).toBe('warehouse-pin-description');
      expect(h.window.document.getElementById('warehouse-pin-description')?.textContent).toBe(
        'pinHint',
      );
      button(h, 'cancel', h.dialog()).click();
      await h.settle();
      expect(h.dialog()).toBeNull();
      expect(h.window.document.activeElement).toBe(h.trigger);
    } finally {
      await h.cleanup();
    }
  });
  test('row actions are named icon buttons with shared focus tooltips and working clicks', async () => {
    const h = await fixture();
    try {
      for (const [action, label] of [
        ['edit', 'edit: Branch'],
        ['delete', 'delete: Branch'],
      ]) {
        const control = h.target.querySelector(`[data-warehouse-${action}="2"]`) as any;
        expect(control.getAttribute('aria-label')).toBe(label);
        expect(control.textContent.trim()).toBe('');
        expect(control.querySelector('svg')).not.toBeNull();
        expect(control.closest('.kiriof-tooltip-trigger')).not.toBeNull();
        control.focus();
        (control.closest('.kiriof-tooltip-trigger') as any).focus();
        await h.settle();
        expect(
          [...h.window.document.querySelectorAll('[data-slot="tooltip-content"]')].some((node) =>
            node.textContent.includes(label),
          ),
        ).toBe(true);
        (control.closest('.kiriof-tooltip-trigger') as any).blur();
        await h.settle();
      }
      await click(h, '[data-warehouse-edit="2"]');
      expect(h.dialog()).not.toBeNull();
      button(h, 'cancel', h.dialog()).click();
      await h.settle();
      await click(h, '[data-warehouse-delete="2"]');
      expect(h.window.document.querySelector('[role="alertdialog"]')).not.toBeNull();
    } finally {
      await h.cleanup();
    }
  });
  test('default guards, exact edit payload, retained errors and locked asynchronous save', async () => {
    const h = await fixture();
    try {
      expect(h.target.querySelector('[data-warehouse-delete="1"]')).toBeNull();
      expect(
        (h.target.querySelector('[data-warehouse-row="1"] [role="switch"]') as any).disabled,
      ).toBe(true);
      await click(h, '[data-warehouse-edit="2"]');
      expect((h.window.document.getElementById('warehouse-name') as any).value).toBe('Branch');
      expect(h.fetchCalls).toHaveLength(0);
      expect((h.window as any).mapOptions.initial).toEqual({
        latitude: '-6.0000000',
        longitude: '106.0000000',
      });
      expect((h.window as any).located).toBeUndefined();
      expect(h.geolocationRequests()).toBe(0);
      expect(h.window.document.getElementById('warehouse-country_state')?.textContent).toContain(
        'Jakarta',
      );
      input(h, 'warehouse-name', 'Changed');
      submit(h);
      await h.settle();
      expect(h.dialog().querySelector('[role="alert"]')?.textContent).toBe('Server rejected');
      expect((h.window.document.getElementById('warehouse-name') as any).value).toBe('Changed');
      const body = h.fetchCalls[0];
      expect(body.get('action')).toBe('save-warehouse');
      expect(body.get('data[nonce]')).toBe('warehouse-nonce');
      expect(body.get('data[id]')).toBe('2');
      expect(body.get('data[name]')).toBe('Changed');
      expect(body.has('data[sender_name]')).toBe(false);
      expect(body.has('data[is_active]')).toBe(false);
      let resolveRequest: any;
      h.responses.push(
        () =>
          new Promise((resolve) => {
            resolveRequest = resolve;
          }),
      );
      submit(h);
      await h.settle();
      submit(h);
      await h.settle();
      expect(h.fetchCalls).toHaveLength(2);
      expect((button(h, 'saving', h.dialog()) as any).disabled).toBe(true);
      resolveRequest(
        new Response(
          JSON.stringify({ success: true, data: { status: 200, id: 2, location: { ...custom, name: 'Changed' } } }),
        ),
      );
      await h.settle();
      expect(h.dialog()).toBeNull();
      expect(h.target.querySelector('[data-warehouse-row="2"]')?.textContent).toContain('Changed');
    } finally {
      await h.cleanup();
    }
  });
  test('save rejects malformed DTOs and noncanonical IDs without losing the edit or table', async () => {
    const h = await fixture();
    try {
      await click(h, '[data-warehouse-edit="2"]');
      input(h, 'warehouse-name', 'Retained draft');
      const invalid = [
        { status: 200, id: 2, location: 'truthy' },
        { status: 200, id: 2, location: {} },
        { status: 200, id: 2, location: { ...custom, is_active: 'false' } },
        { status: 200, id: 2, location: { ...custom, is_default: 'false' } },
        { status: 200, id: 3, location: { ...custom, id: 3 } },
        { status: 200, id: 2, location: { ...custom, id: 3 } },
        { status: '200', id: 2, location: custom },
        { id: 2, location: custom },
        ...['2', 0, -1, 2.5, Number.MAX_SAFE_INTEGER + 1].map(id => ({ status: 200, id, location: { ...custom, id } })),
        ...['name', 'sender_name', 'sender_phone', 'address', 'address_2', 'city', 'country_state', 'zip', 'district', 'district_label', 'lat', 'lng'].map(key => ({ status: 200, id: 2, location: { ...custom, [key]: null } })),
      ];
      for (const data of invalid) {
        h.responses.push({ success: true, data });
        submit(h);
        await h.settle();
        expect(h.dialog().querySelector('[role="alert"]')?.textContent).toBe('saveFailed');
        expect((h.window.document.getElementById('warehouse-name') as any).value).toBe('Retained draft');
        expect(button(h, 'save', h.dialog()).disabled).toBe(false);
        expect(h.target.querySelectorAll('[data-warehouse-row]')).toHaveLength(2);
        expect(h.target.querySelector('[data-warehouse-row="2"]')?.textContent).toContain('Branch');
      }
      h.responses.push({ success: true, data: { status: 200, id: 2, location: { ...custom, name: 'Retained draft' } } });
      submit(h);
      await h.settle();
      expect(h.dialog()).toBeNull();
      expect(h.target.querySelector('[data-warehouse-row="2"]')?.textContent).toContain('Retained draft');
    } finally { await h.cleanup(); }
  });
  test('toggle rolls back malformed or stale status and identity responses', async () => {
    const h = await fixture();
    try {
      for (const data of [null, {}, { status: 200, id: 2, is_active: 'false' }, { status: 200, id: '2', is_active: false }, { status: 200, id: 1, is_active: false }, { status: 200, id: 2, is_active: true }, { status: '200', id: 2, is_active: false }]) {
        h.responses.push({ success: true, data });
        await click(h, '[data-warehouse-row="2"] [role="switch"]');
        expect(h.target.querySelector('[role="alert"]')?.textContent).toBe('saveFailed');
        expect(h.target.querySelector('[data-warehouse-row="2"] [role="switch"]')?.getAttribute('aria-checked')).toBe('true');
        expect(h.target.querySelector('[data-warehouse-row="1"] [role="switch"]')?.getAttribute('aria-checked')).toBe('true');
      }
    } finally { await h.cleanup(); }
  });
  test('delete retains its confirmation and row on malformed or mismatched responses', async () => {
    const h = await fixture();
    try {
      await click(h, '[data-warehouse-delete="2"]');
      for (const data of [null, {}, { status: 200, id: 1 }, { status: 200, id: '2' }, { status: 200, id: 2.5 }, { status: '200', id: 2 }]) {
        h.responses.push({ success: true, data });
        button(h, 'delete', h.window.document.querySelector('[role="alertdialog"]')).click();
        await h.settle();
        expect(h.window.document.querySelector('[role="alertdialog"] [role="alert"]')?.textContent).toBe('saveFailed');
        expect(h.target.querySelectorAll('[data-warehouse-row]')).toHaveLength(2);
      }
    } finally { await h.cleanup(); }
  });
  test('partial toggle rolls back failure then accepts success', async () => {
    const h = await fixture();
    try {
      await click(h, '[data-warehouse-row="2"] [role="switch"]');
      expect(h.fetchCalls[0].get('action')).toBe('toggle-warehouse');
      expect([...h.fetchCalls[0].keys()].sort()).toEqual([
        'action',
        'data[id]',
        'data[is_active]',
        'data[nonce]',
      ]);
      expect(h.fetchCalls[0].get('data[is_active]')).toBe('0');
      expect(
        h.target
          .querySelector('[data-warehouse-row="2"] [role="switch"]')
          ?.getAttribute('aria-checked'),
      ).toBe('true');
      h.responses.push({ success: true, data: { status: 200, id: 2, is_active: false } });
      await click(h, '[data-warehouse-row="2"] [role="switch"]');
      expect(
        h.target
          .querySelector('[data-warehouse-row="2"] [role="switch"]')
          ?.getAttribute('aria-checked'),
      ).toBe('false');
    } finally {
      await h.cleanup();
    }
  });
  test('delete cancellation, retained server failure, success, and fallback focus', async () => {
    const h = await fixture();
    try {
      const trigger = await click(h, '[data-warehouse-delete="2"]');
      let alert = h.window.document.querySelector('[role="alertdialog"]')!;
      button(h, 'cancel', alert).click();
      await h.settle();
      expect(h.fetchCalls).toHaveLength(0);
      expect(h.window.document.activeElement).toBe(trigger);
      await click(h, '[data-warehouse-delete="2"]');
      alert = h.window.document.querySelector('[role="alertdialog"]')!;
      button(h, 'delete', alert).click();
      await h.settle();
      alert = h.window.document.querySelector('[role="alertdialog"]')!;
      expect(alert).not.toBeNull();
      expect(alert.querySelector('[role="alert"]')?.textContent).toBe('Server rejected');
      h.responses.push({ success: true, data: { status: 200, id: 2 } });
      button(h, 'delete', alert).click();
      await h.settle();
      expect(h.window.document.querySelector('[role="alertdialog"]')).toBeNull();
      expect(h.target.querySelector('[data-warehouse-row="2"]')).toBeNull();
      expect(h.window.document.activeElement).toBe(h.trigger);
    } finally {
      await h.cleanup();
    }
  });
  test('add searches actual combobox only after typing, selects area and explicit pin, then creates', async () => {
    const h = await fixture();
    try {
      await h.open();
      for (const [key, value] of Object.entries({
        name: 'New branch',
        sender_phone: '08123',
        address: 'New street',
        zip: '10110',
      }))
        input(h, `warehouse-${key}`, value);
      await click(h, '#warehouse-district');
      const search = h.window.document.querySelector('[data-slot="command-input"]') as any;
      h.responses.push({
        success: true,
        data: [{ id: 456, text: 'New district', city: 'Bandung', country_state: 'ID:JB' }],
      });
      search.value = 'New';
      search.dispatchEvent(new h.window.Event('input', { bubbles: true }));
      await new Promise((resolve) => setTimeout(resolve, 300));
      await h.settle();
      await click(h, '[data-slot="command-item"]');
      expect((h.window.document.getElementById('warehouse-city') as any).value).toBe('Bandung');
      const options = (h.window as any).mapOptions;
      expect(options).toBeDefined();
      expect((h.window as any).located).toBeUndefined();
      expect(h.geolocationRequests()).toBe(0);
      button(h, 'myLocation', h.dialog()).click();
      await h.settle();
      expect((h.window as any).located).toBe(1);
      options.onSelect({ latitude: '-6.1', longitude: '107.2' });
      await h.settle();
      // A create response must not duplicate an existing keyed row.
      h.responses.push({ success: true, data: { status: 200, id: 2, location: custom } });
      submit(h);
      await h.settle();
      expect(h.dialog().querySelector('[role="alert"]')?.textContent).toBe('saveFailed');
      expect((h.window.document.getElementById('warehouse-name') as any).value).toBe('New branch');
      expect(h.target.querySelectorAll('[data-warehouse-row]')).toHaveLength(2);
      h.responses.push({
        success: true,
        data: { status: 200, id: 3, location: { ...custom, id: 3, name: 'New branch' } },
      });
      submit(h);
      await h.settle();
      expect(h.dialog()).toBeNull();
      expect(h.fetchCalls.at(-1)?.get('data[id]')).toBe('new');
      expect(h.fetchCalls.at(-1)?.get('data[district]')).toBe('456');
      expect(h.fetchCalls.at(-1)?.get('data[lat]')).toBe('-6.1');
      expect((h.window as any).mapDisposed).toBe(true);
      options.onSelect({ latitude: '0', longitude: '0' }); // stale callbacks must not resurrect the form
      await h.settle();
      expect(h.dialog()).toBeNull();
    } finally {
      await h.cleanup();
    }
  });
  test('closing during provider load disposes the late session and ignores stale map callbacks', async () => {
    const h = await fixture();
    try {
      (h.window as any).deferMap = true;
      await h.open();
      const options = (h.window as any).mapOptions;
      expect(options).toBeDefined();
      expect(button(h, 'myLocation', h.dialog()).disabled).toBe(true);
      button(h, 'cancel', h.dialog()).click();
      await h.settle();
      (h.window as any).resolveMap();
      await h.settle();
      expect((h.window as any).mapDisposed).toBe(true);
      expect(options.onSelect({ latitude: '10', longitude: '20' })).toBe(false);
      expect(h.fetchCalls).toHaveLength(0);
      await h.open();
      expect(h.dialog().textContent).not.toContain('pinSelected');
    } finally {
      await h.cleanup();
    }
  });
  test('country/state displays WooCommerce labels while preserving unknown values and defaults', async () => {
    const h = await fixture({ locations: [{ ...custom, country_state: 'ID:UNKNOWN' }] });
    try {
      await click(h, '[data-warehouse-edit="2"]');
      expect(h.window.document.getElementById('warehouse-country_state')?.tagName).toBe('BUTTON');
      expect(h.window.document.getElementById('warehouse-country_state')?.textContent).toContain(
        'countryState',
      );
      h.window.document
        .getElementById('warehouse-country_state')!
        .dispatchEvent(new h.window.KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
      await h.settle();
      expect(
        h.window.document.querySelector('[data-slot="select-content"]')?.textContent,
      ).toContain('West Java');
    } finally {
      await h.cleanup();
    }
    const fallback = await fixture({ countryStates: [] });
    try {
      await fallback.open();
      const control = fallback.window.document.getElementById('warehouse-country_state') as any;
      expect(control.textContent).toContain('countryState');
      expect(control.disabled).toBe(true);
    } finally {
      await fallback.cleanup();
    }
  });
  test('WooCommerce state codes are never shown as the selected label', async () => {
    const h = await fixture({ locations: [{ ...custom, country_state: 'ID:YO' }] });
    try {
      await click(h, '[data-warehouse-edit="2"]');
      const control = h.window.document.getElementById('warehouse-country_state')!;
      expect(control.textContent).toContain('Indonesia — DI Yogyakarta');
      expect(control.textContent).not.toContain('ID:YO');
      expect(
        h.window.document.querySelector('label[for="warehouse-country_state"]')?.textContent,
      ).toBe('countryState');
      submit(h);
      await h.settle();
      expect(h.fetchCalls[0].get('data[country_state]')).toBe('ID:YO');
      expect(h.fetchCalls[0].get('data[lat]')).toBe('-6');
      expect(h.fetchCalls[0].get('data[lng]')).toBe('106');
      expect(h.geolocationRequests()).toBe(0);
    } finally {
      await h.cleanup();
    }
  });
  test('busy map callbacks are rejected without disposing or rebuilding the map session', async () => {
    const h = await fixture();
    try {
      await click(h, '[data-warehouse-edit="2"]');
      const options = (h.window as any).mapOptions;
      let resolveRequest: any;
      h.responses.push(
        () =>
          new Promise((resolve) => {
            resolveRequest = resolve;
          }),
      );
      submit(h);
      await h.settle();
      expect(options.onSelect({ latitude: '1', longitude: '2' })).toBe(false);
      expect((h.window as any).mapOptions).toBe(options);
      expect((h.window as any).mapDisposed).toBe(false);
      expect(button(h, 'myLocation', h.dialog()).disabled).toBe(true);
      expect(button(h, 'saving', h.dialog()).querySelector('svg[role="status"]')).not.toBeNull();
      resolveRequest(new Response(JSON.stringify({ success: false, data: { message: 'Retry' } })));
      await h.settle();
      expect((h.window as any).mapOptions).toBe(options);
      submit(h);
      await h.settle();
      expect(h.fetchCalls.at(-1)?.get('data[lat]')).toBe('-6');
      expect(options.onSelect({ latitude: '3', longitude: '4' })).toBe(true);
    } finally {
      await h.cleanup();
    }
  });
  test('search retry clears errors and excludes malformed rows', async () => {
    const h = await fixture();
    try {
      await h.open();
      await click(h, '#warehouse-district');
      const search = h.window.document.querySelector('[data-slot="command-input"]') as any;
      search.value = 'First';
      search.dispatchEvent(new h.window.Event('input', { bubbles: true }));
      await new Promise((resolve) => setTimeout(resolve, 300));
      await h.settle();
      expect(h.dialog().textContent).toContain('searchFailed');
      h.responses.push({
        success: true,
        data: [
          null,
          {},
          { id: 0, text: 'Invalid' },
          { id: -2, text: 'Invalid' },
          { id: 1, text: '' },
          { id: 2, text: 23 },
          { id: 456, text: 'Valid district', city: 42 },
        ],
      });
      search.value = 'Second';
      search.dispatchEvent(new h.window.Event('input', { bubbles: true }));
      await new Promise((resolve) => setTimeout(resolve, 300));
      await h.settle();
      expect(h.dialog().textContent).not.toContain('searchFailed');
      expect(h.window.document.querySelectorAll('[data-slot="command-item"]')).toHaveLength(1);
      await click(h, '[data-slot="command-item"]');
      expect((h.window.document.getElementById('warehouse-city') as any).value).toBe('');
    } finally {
      await h.cleanup();
    }
  });
  test('late save after destruction cannot restore dialogs or mutate detached UI', async () => {
    const h = await fixture();
    try {
      await click(h, '[data-warehouse-edit="2"]');
      let resolveRequest: any;
      h.responses.push(
        () =>
          new Promise((resolve) => {
            resolveRequest = resolve;
          }),
      );
      submit(h);
      await h.settle();
      await h.destroy();
      resolveRequest(
        new Response(
          JSON.stringify({ success: true, data: { status: 200, id: 2, location: { ...custom, name: 'Late' } } }),
        ),
      );
      await h.settle();
      expect(h.target.textContent).toBe('');
      expect(h.dialog()).toBeNull();
    } finally {
      await h.cleanup();
    }
  });
  test('late toggle and delete responses after destruction cannot mutate detached UI', async () => {
    for (const operation of ['toggle', 'delete']) {
      const h = await fixture();
      try {
        let resolveRequest: any;
        h.responses.push(() => new Promise(resolve => { resolveRequest = resolve; }));
        if (operation === 'toggle') await click(h, '[data-warehouse-row="2"] [role="switch"]');
        else {
          await click(h, '[data-warehouse-delete="2"]');
          button(h, 'delete', h.window.document.querySelector('[role="alertdialog"]')).click();
          await h.settle();
        }
        await h.destroy();
        resolveRequest(new Response(JSON.stringify({ success: true, data: { status: 200, id: 2, is_active: false } })));
        await h.settle();
        expect(h.target.textContent).toBe('');
        expect(h.window.document.querySelector('[role="alertdialog"]')).toBeNull();
      } finally { await h.cleanup(); }
    }
  });
  test('editing deep link opens once and does not reopen after dismissal or unrelated updates', async () => {
    const h = await fixture({ editingKey: '2' });
    try {
      expect(h.dialog()).not.toBeNull();
      button(h, 'cancel', h.dialog()).click();
      await h.settle();
      expect(h.dialog()).toBeNull();
      h.responses.push({ success: true, data: { status: 200, id: 2, is_active: false } });
      await click(h, '[data-warehouse-row="2"] [role="switch"]');
      expect(h.dialog()).toBeNull();
    } finally {
      await h.cleanup();
    }
  });
  test('custom limit disables creation, including deep link, but default does not count', async () => {
    const h = await fixture({ customLimit: 1, editingKey: 'new' });
    try {
      expect((h.trigger as any).disabled).toBe(true);
      expect(h.dialog()).toBeNull();
      expect(h.fetchCalls).toHaveLength(0);
    } finally {
      await h.cleanup();
    }
  });
});
