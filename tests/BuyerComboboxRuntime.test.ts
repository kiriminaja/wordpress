import { afterAll, describe, expect, test } from 'bun:test';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';

const root = resolve(import.meta.dir, '..');
let directory: string;
let bundlePromise: Promise<Buffer> | undefined;
afterAll(() => {
  if (directory) rmSync(directory, { recursive: true, force: true });
});
async function bundle() {
  directory = mkdtempSync(join(root, 'node_modules', '.buyer-combobox-build-'));
  writeFileSync(
    join(directory, 'entry.ts'),
    `import {startClassicSelectors} from '${join(root, 'src/buyer/classic/selector.ts')}'; import {flushSync} from 'svelte'; export {startClassicSelectors, flushSync};`,
  );
  const build = await Bun.build({
    entrypoints: [join(directory, 'entry.ts')],
    outdir: directory,
    naming: 'runtime.js',
    target: 'browser',
    conditions: ['browser'],
    plugins: [
      {
        name: 'buyer-combobox-svelte',
        setup(builder) {
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
  if (!build.success) throw new Error(build.logs.join('\n'));
  return readFileSync(join(directory, 'runtime.js'));
}
async function fixture(html: string, config: Record<string, unknown> = {}) {
  const temporary = mkdtempSync(join(root, 'node_modules', '.buyer-combobox-runtime-'));
  const window = new happy.Window({ url: 'https://fixture.test' });
  window.document.body.innerHTML = `<form class="checkout">${html}</form>`;
  const saved = new Map<string, PropertyDescriptor | undefined>();
  const globals = {
    window,
    document: window.document,
    navigator: window.navigator,
    Node: window.Node,
    Element: window.Element,
    HTMLElement: window.HTMLElement,
    HTMLButtonElement: window.HTMLButtonElement,
    SVGElement: window.SVGElement,
    HTMLMediaElement: window.HTMLMediaElement,
    DocumentFragment: window.DocumentFragment,
    HTMLInputElement: window.HTMLInputElement,
    HTMLSelectElement: window.HTMLSelectElement,
    Text: window.Text,
    Comment: window.Comment,
    Event: window.Event,
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
  const changes: Event[] = [];
  window.document.body.addEventListener('change', (event) => changes.push(event));
  (window as any).kiriofBillingAddressConfig = config;
  async function settle() {
    for (let i = 0; i < 20; i++) {
      await Promise.resolve();
      runtime.flushSync();
    }
    await new Promise((resolve) => setTimeout(resolve, 5));
    runtime.flushSync();
  }
  async function cleanup() {
    window.dispatchEvent(new window.Event('pagehide'));
    await settle();
    window.happyDOM.abort();
    for (const [key, descriptor] of saved) {
      if (descriptor) Object.defineProperty(globalThis, key, descriptor);
      else delete (globalThis as any)[key];
    }
    rmSync(temporary, { recursive: true, force: true });
  }
  try {
    writeFileSync(join(temporary, 'runtime.js'), await (bundlePromise ??= bundle()));
    runtime = await import(join(temporary, 'runtime.js'));
    const bridge = runtime.startClassicSelectors(window);
    window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
    await settle();
    const trigger = () =>
      window.document.querySelector(
        '.kiriof-buyer-combobox-trigger',
      ) as unknown as HTMLButtonElement;
    async function open() {
      trigger().dispatchEvent(new window.PointerEvent('pointerdown', { bubbles: true }));
      await settle();
    }
    async function choose(value: string) {
      const option =
        window.document.querySelector(`[data-combobox-item][data-value="${value}"]`) ||
        [...window.document.querySelectorAll('[data-combobox-item]')].find((node) =>
          node.textContent?.includes(value),
        );
      if (!option) throw new Error(`Missing option ${value}: ${window.document.body.innerHTML}`);
      option.dispatchEvent(new window.MouseEvent('pointermove', { bubbles: true }));
      option.dispatchEvent(
        new window.PointerEvent('pointerup', { bubbles: true, pointerType: 'mouse' }),
      );
      await settle();
    }
    return { window, bridge, changes, settle, cleanup, trigger, open, choose };
  } catch (error) {
    if (runtime) await cleanup();
    throw error;
  }
}

const district =
  '<label for="district">Subdistrict</label><select id="district" name="kiriof_destination_area" required aria-describedby="help"><option value="">Select Option</option><option value="101" selected>Old district</option></select><span id="help">Search your subdistrict</span>';

describe('Classic buyer selectors (compiled actual bits-ui)', () => {
  test('native authority, accessible labels, one explicit selection change and no refresh writes', async () => {
    const h = await fixture(
      '<label for="billing_state">Province</label><select id="billing_state" required><option value="">Select</option><option value="ID-JB" selected>West Java</option><option value="ID-JT">Central Java</option></select>',
    );
    try {
      const native = h.window.document.querySelector('select')!;
      expect(native.classList.contains('kiriof-buyer-native-select')).toBe(true);
      expect(h.trigger().textContent).toContain('West Java');
      expect(h.trigger().getAttribute('aria-labelledby')).toBe(
        'billing_state-buyer-selector-label',
      );
      expect(h.trigger().getAttribute('aria-required')).toBe('true');
      expect(h.changes).toHaveLength(0);
      await h.open();
      await h.choose('ID-JT');
      expect(native.value).toBe('ID-JT');
      expect(h.changes).toHaveLength(1);
      expect((h.changes[0] as CustomEvent).detail.value).toBe('ID-JT');
      native.value = '';
      h.bridge.refresh();
      await h.settle();
      expect(h.trigger().textContent).toContain('Select');
      expect(h.changes).toHaveLength(1);
    } finally {
      await h.cleanup();
    }
  });

  test('courier metadata survives native option refresh; logos and prices render as server strings', async () => {
    const h = await fixture(
      '<select class="kiriof-classic-shipping-method-select" id="rate"><option value="jne:REG" selected data-courier="jne" data-label="JNE REG" data-price="Rp10.000" data-original-price="Rp12.000" data-savings="Save Rp2.000" data-note="2–3 days">JNE REG (Rp10.000)</option><option value="third-party">Unknown courier</option></select>',
      { courierLogos: { jne: '/assets/buyer/img/couriers/jne.png' } },
    );
    try {
      const native = h.window.document.querySelector('select')!;
      expect(h.trigger().querySelector('img')?.getAttribute('src')).toBe(
        'https://fixture.test/assets/buyer/img/couriers/jne.png',
      );
      expect(h.trigger().querySelector('del')?.textContent).toBe('Rp12.000');
      native.innerHTML =
        '<option value="jne:REG" selected>Changed native label</option><option value="third-party">Unknown courier</option>';
      h.bridge.refresh();
      await h.settle();
      expect(native.options[0].getAttribute('data-price')).toBe('Rp10.000');
      expect(h.trigger().textContent).toContain('JNE REG');
      await h.open();
      await h.choose('third-party');
      expect(h.trigger().querySelector('img')).toBeNull();
      expect(h.trigger().querySelector('svg')).toBeNull();
      expect(h.changes).toHaveLength(1);
      expect((h.changes[0] as CustomEvent).detail.reviewedShipping).toBe(true);
    } finally {
      await h.cleanup();
    }
  });

  test('Woo replacing native select inside wrapper preserves replacement without resurrecting old node', async () => {
    const h = await fixture(
      '<select id="billing_state"><option value="JB" selected>Java</option></select>',
    );
    try {
      const old = h.window.document.querySelector('select')!;
      const replacement = h.window.document.createElement('input');
      replacement.id = 'billing_state';
      replacement.value = 'Tokyo';
      old.replaceWith(replacement);
      h.bridge.refresh();
      await h.settle();
      expect(h.window.document.getElementById('billing_state')).toBe(replacement);
      expect(old.isConnected).toBe(false);
      expect(h.window.document.querySelector('.kiriof-buyer-selector')).toBeNull();
      expect(h.changes).toHaveLength(0);
    } finally {
      await h.cleanup();
    }
  });

  test('read lookup has loading/error/retry and stale term cancellation; clear emits exactly once', async () => {
    const h = await fixture(district, { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'nonce' });
    try {
      const requests: {
        signal: AbortSignal;
        term: string;
        resolve: (value: unknown) => void;
        reject: (error: Error) => void;
      }[] = [];
      h.window.fetch = ((_url: unknown, init: any) =>
        new Promise((resolve, reject) =>
          requests.push({ signal: init.signal, term: init.body.get('term'), resolve, reject }),
        )) as any;
      await h.open();
      const input = h.window.document.querySelector('[data-combobox-input]')! as any;
      const type = async (value: string) => {
        input.value = value;
        input.dispatchEvent(new h.window.Event('input', { bubbles: true }));
        await h.settle();
        await new Promise((resolve) => setTimeout(resolve, 270));
        await h.settle();
      };
      await type('Old');
      expect(requests).toHaveLength(1);
      expect(h.window.document.querySelector('[aria-busy="true"]')).not.toBeNull();
      await type('New');
      expect(requests[0].signal.aborted).toBe(true);
      requests[0].resolve({
        ok: true,
        json: async () => ({ results: [{ id: 202, text: 'Stale district' }] }),
      });
      await h.settle();
      expect(h.window.document.body.textContent).not.toContain('Stale district');
      requests[1].reject(new Error('network'));
      await h.settle();
      const retry = h.window.document.querySelector('.kiriof-buyer-combobox-retry') as any;
      expect(retry).not.toBeNull();
      retry.click();
      await new Promise((resolve) => setTimeout(resolve, 270));
      requests[2].resolve({
        ok: true,
        json: async () => ({ term: 'New', results: [{ id: 303, text: 'New district' }] }),
      });
      await h.settle();
      expect(h.window.document.querySelector('select')!.value).toBe('101');
      expect(h.changes).toHaveLength(0);
      await h.choose('303');
      expect(h.window.document.querySelector('select')!.value).toBe('303');
      expect(h.changes).toHaveLength(1);
      (h.window.document.querySelector('.kiriof-buyer-combobox-clear') as any).click();
      await h.settle();
      expect(h.window.document.querySelector('select')!.value).toBe('');
      expect(h.changes).toHaveLength(2);
    } finally {
      await h.cleanup();
    }
  });
});
