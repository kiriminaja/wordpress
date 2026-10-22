import { execFileSync } from 'node:child_process';
import { afterAll, describe, expect, test } from 'bun:test';
import { existsSync, statSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
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
  directory = mkdtempSync(join(root, 'node_modules', '.onboarding-forms-build-'));
  writeFileSync(
    join(directory, 'entry.ts'),
    `import Combo from '${join(root, 'src/lib/onboarding/SubdistrictCombobox.svelte')}'; import App from '${join(root, 'src/lib/onboarding/OnboardingApp.svelte')}'; import {mount, unmount, flushSync} from 'svelte'; export {mount, unmount, flushSync, Combo, App};`,
  );
  const build = await Bun.build({
    entrypoints: [join(directory, 'entry.ts')],
    outdir: directory,
    naming: 'runtime.js',
    target: 'browser',
    conditions: ['browser'],
    plugins: [
      {
        name: 'onboarding-forms-svelte',
        setup(builder) {
          builder.onResolve({ filter: /^\$lib\// }, ({ path }) => {
            const base = join(root, 'src/lib', path.slice(5));
            const found = [
              base,
              base.replace(/\.js$/, '.ts'),
              `${base}.ts`,
              join(base, 'index.ts'),
            ].find((candidate) => existsSync(candidate) && statSync(candidate).isFile());
            if (!found) throw new Error(`Unresolved ${path}`);
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
async function fixture(props: Record<string, unknown>, app = false) {
  const temporary = mkdtempSync(join(root, 'node_modules', '.onboarding-forms-runtime-'));
  const window = new happy.Window({ url: 'https://fixture.test' });
  window.document.body.innerHTML = `<label for="district">Subdistrict</label><main></main><p id="help">District required</p>`;
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
  let component: any;
  async function settle() {
    for (let i = 0; i < 20; i++) {
      await Promise.resolve();
      runtime.flushSync();
    }
    await new Promise((resolve) => setTimeout(resolve, 5));
    runtime.flushSync();
  }
  async function cleanup() {
    if (component) runtime.unmount(component);
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
    component = runtime.mount(app ? runtime.App : runtime.Combo, {
      target: window.document.querySelector('main'),
      props,
    });
    await settle();
    const trigger = () => window.document.querySelector('button') as any;
    async function open() {
      trigger().click();
      await settle();
    }
    async function choose(value: string) {
      const option =
        window.document.querySelector(`[data-slot="command-item"][data-value="${value}"]`) ||
        [...window.document.querySelectorAll('[data-slot="command-item"]')].find((node) =>
          node.textContent?.includes(value),
        );
      if (!option) throw new Error(`Missing option ${value}: ${window.document.body.innerHTML}`);
      option.dispatchEvent(new window.MouseEvent('pointermove', { bubbles: true }));
      option.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
      await settle();
    }
    return { window, changes, settle, cleanup, trigger, open, choose };
  } catch (error) {
    if (runtime) await cleanup();
    throw error;
  }
}

const base = {
  id: 'district',
  value: '',
  areas: [{ id: 42, text: 'Yogyakarta' }],
  loading: false,
  placeholder: 'Search subdistrict',
  loadingText: 'Loading',
  noResultsText: 'No results',
  typeMoreText: 'Type three letters',
  onSearch() {},
  onSelect() {},
};

describe('onboarding shared subdistrict combobox (production components)', () => {
  test('label association, invalid description and disabled trigger reach the real Button', async () => {
    const h = await fixture({
      ...base,
      disabled: true,
      'aria-invalid': true,
      'aria-describedby': 'help',
    });
    try {
      expect(h.trigger().id).toBe('district');
      expect(h.window.document.querySelector('label')!.htmlFor).toBe(h.trigger().id);
      expect(h.trigger().disabled).toBe(true);
      expect(h.trigger().getAttribute('aria-invalid')).toBe('true');
      expect(h.trigger().getAttribute('aria-describedby')).toBe('help');
      await h.open();
      expect(h.window.document.querySelector('[data-slot="command-input"]')).toBeNull();
    } finally {
      await h.cleanup();
    }
  });

  test('search and selection use grouped server results, one check and restore trigger focus', async () => {
    const queries: string[] = [],
      selected: unknown[] = [];
    const h = await fixture({
      ...base,
      onSearch: (query: string) => queries.push(query),
      onSelect: (area: unknown) => selected.push(area),
    });
    try {
      await h.open();
      const input = h.window.document.querySelector('[data-slot="command-input"]') as any;
      expect(input.getAttribute('aria-label')).toBe('Search subdistrict');
      expect(h.window.document.body.textContent).toContain('Type three letters');
      input.value = 'Yog';
      input.dispatchEvent(new h.window.Event('input', { bubbles: true }));
      await h.settle();
      expect(queries).toEqual(['Yog']);
      const item = h.window.document.querySelector('[data-slot="command-item"]')!;
      expect(item.closest('[data-slot="command-group"]')).not.toBeNull();
      expect(item.querySelectorAll('svg')).toHaveLength(1);
      await h.choose('42');
      expect(selected).toEqual([{ id: 42, text: 'Yogyakarta' }]);
      expect(h.trigger().getAttribute('aria-expanded')).toBe('false');
      expect(h.window.document.activeElement).toBe(h.trigger());
    } finally {
      await h.cleanup();
    }
  });
});

test('onboarding address validation marks real fields and retains the labeled subdistrict trigger', async () => {
  const bootstrap = JSON.parse(
    execFileSync(
      'php',
      [
        join(root, 'tests/fixtures/onboarding-navigation-runtime.php'),
        'https://fixture.test/wp-admin/',
      ],
      { encoding: 'utf8' },
    ),
  );
  bootstrap.initialStep = 'address';
  bootstrap.address.values.origin_name = '';
  bootstrap.address.values.origin_sub_district_id = '';
  bootstrap.map = { enabled: false };
  const h = await fixture({ bootstrap }, true);
  try {
    const document = h.window.document;
    const input = document.querySelector('#origin-name')!;
    expect(input.getAttribute('aria-invalid')).toBe('false');
    const combo = document.querySelector('#origin-subdistrict')!;
    expect(combo.getAttribute('role')).toBe('combobox');
    expect(document.querySelector('label[for="origin-subdistrict"]')).not.toBeNull();
    const submit = [...document.querySelectorAll('button')].find((button) =>
      button.textContent?.includes(bootstrap.address.i18n.saveContinue),
    );
    const footerButtons = document.querySelectorAll('[data-slot="card-footer"] button');
    (submit || (footerButtons[footerButtons.length - 1] as any)).click();
    await h.settle();
    expect(input.getAttribute('aria-invalid')).toBe('true');
    expect(input.closest('[data-slot="field"]')!.getAttribute('data-invalid')).toBe('true');
    expect(combo.getAttribute('aria-invalid')).toBe('true');
    expect(document.getElementById(input.getAttribute('aria-describedby')!)?.textContent).toContain(
      bootstrap.i18n.shippingAddressRequired,
    );
  } finally {
    await h.cleanup();
  }
});

test('subdistrict loading state exposes busy status without selectable stale options', async () => {
  const h = await fixture({ ...base, loading: true });
  try {
    await h.open();
    expect(
      h.window.document.querySelector('[data-slot="command-list"]')!.getAttribute('aria-busy'),
    ).toBe('true');
    expect(h.window.document.body.textContent).toContain('Loading');
    expect(h.window.document.querySelector('[data-slot="command-item"]')).toBeNull();
  } finally {
    await h.cleanup();
  }
});
