import { afterAll, expect, test } from 'bun:test';
import { existsSync, statSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';
import type { GoogleMapsBootstrap, TechnicalBootstrap } from '../src/lib/settings/types';

const root = resolve(import.meta.dir, '..');
const raw = 'AIza-synthetic_replacement_key123';
const mask = (key: string) => key.slice(0, 4) + '*'.repeat(key.length - 7) + key.slice(-3);
const bootstrap: GoogleMapsBootstrap = {
  configured: true,
  maskedKey: mask('AIza-synthetic_saved_key456'),
  nonce: 'google-dedicated-nonce',
  i18n: {
    title: 'Google Maps',
    guidance: 'Use your own browser key',
    configured: 'Configured',
    notConfigured: 'Not configured',
    keyLabel: 'Replacement key',
    placeholder: 'Enter a replacement',
    save: 'Save key',
    remove: 'Remove key',
    busy: 'Saving',
    saved: 'Settings saved',
    failed: 'Unable to save settings',
  },
};

let buildDirectory: string | undefined;
let bundlePromise: Promise<Buffer> | undefined;
let bundleBuildCount = 0;
afterAll(() => {
  try {
    if (bundlePromise) expect(bundleBuildCount).toBe(1);
  } finally {
    if (buildDirectory) rmSync(buildDirectory, { recursive: true, force: true });
  }
});

// Fresh evaluation keeps Svelte's cached native DOM getters local to each Window.
function compiledBundle(): Promise<Buffer> {
  return (bundlePromise ??= buildBundle());
}
async function buildBundle(): Promise<Buffer> {
  const directory = (buildDirectory = mkdtempSync(
    join(root, 'node_modules', '.google-settings-build-'),
  ));
  bundleBuildCount++;
  writeFileSync(
    join(directory, 'entry.ts'),
    `export {mount, unmount, flushSync} from 'svelte'; export {default as Component} from ${JSON.stringify(join(root, 'src/lib/settings/GoogleMapsIntegration.svelte'))}; export {default as Technical} from ${JSON.stringify(join(root, 'src/lib/settings/TechnicalSection.svelte'))};`,
  );
  const build = await Bun.build({
    entrypoints: [join(directory, 'entry.ts')],
    outdir: directory,
    naming: 'runtime.js',
    target: 'browser',
    conditions: ['browser'],
    plugins: [
      {
        name: 'production-svelte',
        setup(builder) {
          builder.onResolve({ filter: /^svelte$/ }, () => ({
            path: join(root, 'node_modules/svelte/src/index-client.js'),
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
          builder.onResolve({ filter: /^@tabler\/icons-svelte$/ }, () => ({
            path: join(root, 'node_modules/@tabler/icons-svelte/dist/tabler-icons-svelte.js'),
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

// Compile the actual production components and their shared UI dependencies; only
// fetch is a mocked WordPress boundary. No source-string assertions or substitute UI.
async function fixture(
  response: (body: URLSearchParams) => unknown,
  technical?: TechnicalBootstrap,
) {
  const bundle = await compiledBundle();
  const window = new happy.Window({ url: 'https://fixture.test' });
  const directory = mkdtempSync(join(root, 'node_modules', '.google-settings-runtime-'));
  const saved = new Map<string, PropertyDescriptor | undefined>();
  const requests: URLSearchParams[] = [];
  (window as any).ajaxurl = '/wp-admin/admin-ajax.php';
  (window as any).kiriofSettings = { nonce: 'general-must-not-be-used' };
  const globals = {
    window,
    document: window.document,
    navigator: window.navigator,
    Node: window.Node,
    Element: window.Element,
    HTMLElement: window.HTMLElement,
    HTMLMediaElement: window.HTMLMediaElement,
    HTMLInputElement: window.HTMLInputElement,
    SVGElement: window.SVGElement,
    DocumentFragment: window.DocumentFragment,
    Text: window.Text,
    Comment: window.Comment,
    Event: window.Event,
    MutationObserver: window.MutationObserver,
    getComputedStyle: window.getComputedStyle.bind(window),
    fetch: async (_url: unknown, options: RequestInit) => {
      expect(options.method).toBe('POST');
      const body = new URLSearchParams(options.body as URLSearchParams);
      requests.push(body);
      return new Response(JSON.stringify(await response(body)));
    },
  };
  for (const [key, value] of Object.entries(globals)) {
    saved.set(key, Object.getOwnPropertyDescriptor(globalThis, key));
    Object.defineProperty(globalThis, key, { configurable: true, writable: true, value });
  }
  let runtime: any, component: any;
  const cleanup = async () => {
    try {
      if (component) await runtime.unmount(component);
    } finally {
      window.happyDOM.abort();
      for (const [key, descriptor] of saved) {
        if (descriptor) Object.defineProperty(globalThis, key, descriptor);
        else delete (globalThis as any)[key];
      }
      rmSync(directory, { recursive: true, force: true });
    }
  };
  try {
    writeFileSync(join(directory, 'runtime.js'), bundle);
    runtime = await import(join(directory, 'runtime.js'));
    const target = window.document.createElement('main');
    window.document.body.append(target);
    component = runtime.mount(technical ? runtime.Technical : runtime.Component, {
      target,
      props: { bootstrap: technical ?? bootstrap },
    });
    const settle = async () => {
      for (let i = 0; i < 12; i++) {
        await Promise.resolve();
        runtime.flushSync();
      }
    };
    await settle();
    const input = target.querySelector<HTMLInputElement>('input')!;
    const submit = async () => {
      input.value = raw;
      input.dispatchEvent(new window.Event('input', { bubbles: true }));
      runtime.flushSync();
      const event = new window.Event('submit', { bubbles: true, cancelable: true });
      target.querySelector('form')!.dispatchEvent(event);
      expect(event.defaultPrevented).toBe(true);
      await settle();
    };
    return { target, input, requests, submit, settle, window, cleanup };
  } catch (error) {
    await cleanup();
    throw error;
  }
}

test('production DOM saves with dedicated nested nonce, renders only mask, clears replacement, and removes without a key', async () => {
  const h = await fixture((body) => ({
    success: true,
    data: {
      status: 200,
      data:
        body.get('data[mode]') === 'remove'
          ? { configured: false, maskedKey: '' }
          : { configured: true, maskedKey: mask(raw) },
    },
  }));
  try {
    expect(h.target.querySelector('h2')?.textContent).toBe('Google Maps');
    expect(h.target.querySelector('code')?.textContent).toBe(bootstrap.maskedKey);
    expect(h.input.type).toBe('password');
    expect(h.input.value).toBe('');
    expect(h.input.getAttribute('value')).toBeNull();
    await h.submit();
    expect(Object.fromEntries(h.requests[0]!)).toEqual({
      action: 'kiriof_save_google_maps_settings',
      'data[mode]': 'save',
      'data[key]': raw,
      'data[nonce]': bootstrap.nonce,
    });
    expect(h.target.querySelector('code')?.textContent).toBe(mask(raw));
    expect(h.target.innerHTML).not.toContain(raw);
    expect(h.input.value).toBe('');
    h.target.querySelector<HTMLButtonElement>('button[type="button"]')!.click();
    await h.settle();
    expect(Object.fromEntries(h.requests[1]!)).toEqual({
      action: 'kiriof_save_google_maps_settings',
      'data[mode]': 'remove',
      'data[nonce]': bootstrap.nonce,
    });
    expect(h.target.querySelector('code')).toBeNull();
    expect(h.target.textContent).toContain('Not configured');
    expect(h.input.value).toBe('');
  } finally {
    await h.cleanup();
  }
});

test('production DOM never echoes a credential-bearing transport error or malformed raw-key summary', async () => {
  let attempt = 0;
  const h = await fixture(() =>
    ++attempt === 1
      ? { success: false, data: { message: `Rejected ${raw}` } }
      : { success: true, data: { status: 200, data: { configured: true, maskedKey: raw } } },
  );
  try {
    await h.submit();
    expect(h.target.querySelector('[role="alert"]')?.textContent).toBe(bootstrap.i18n.failed);
    expect(h.target.innerHTML).not.toContain(raw);
    expect(h.input.value).toBe('');
    await h.submit();
    expect(h.target.querySelector('code')?.textContent).toBe('');
    expect(h.target.innerHTML).not.toContain(raw);
    expect(h.input.value).toBe('');
  } finally {
    await h.cleanup();
  }
});

test('fresh settings fixtures own their DOM and requests independently', async () => {
  const first = await fixture(() => ({
    success: true,
    data: { status: 200, data: { configured: true, maskedKey: mask(raw) } },
  }));
  try {
    expect(first.input.ownerDocument).toBe(first.window.document);
    await first.submit();
    expect(first.requests).toHaveLength(1);
  } finally {
    await first.cleanup();
  }
  const second = await fixture(() => ({ success: false, data: { message: 'Rejected' } }));
  try {
    expect(second.window.document).not.toBe(first.window.document);
    expect(second.input.ownerDocument).toBe(second.window.document);
    expect(second.target.querySelector('button')?.ownerDocument).toBe(second.window.document);
    expect(second.requests).not.toBe(first.requests);
    expect(second.requests).toHaveLength(0);
    expect(second.target.querySelector('code')?.textContent).toBe(bootstrap.maskedKey);
    await second.submit();
    expect(second.requests).toHaveLength(1);
    expect(first.requests).toHaveLength(1);
    expect(second.target.querySelector('[role="alert"]')?.textContent).toBe(bootstrap.i18n.failed);
  } finally {
    await second.cleanup();
  }
});

test('settings bundle compiles at most once per suite', () => {
  expect(bundleBuildCount).toBe(bundlePromise ? 1 : 0);
});

test('labelled password field disables while saving, retains the mask, and restores editing after a safe failure', async () => {
  let complete!: (response: unknown) => void;
  const h = await fixture(
    () =>
      new Promise((resolve) => {
        complete = resolve;
      }),
  );
  try {
    expect(h.target.querySelector(`label[for="${h.input.id}"]`)?.textContent).toBe(
      bootstrap.i18n.keyLabel,
    );
    expect(h.input.readOnly).toBe(false);
    expect(h.input.disabled).toBe(false);
    expect(
      h.target.querySelector(`#${h.input.getAttribute('aria-describedby')}`)?.textContent,
    ).toBe(bootstrap.i18n.guidance);
    await h.submit();
    expect(h.input.disabled).toBe(true);
    expect(h.input.closest('[data-slot="field"]')?.getAttribute('data-disabled')).toBe('true');
    expect(h.target.querySelector('form')?.getAttribute('aria-busy')).toBe('true');
    expect(
      h.target.querySelector('button svg[data-icon="inline-start"].animate-spin'),
    ).not.toBeNull();
    expect([...h.target.querySelectorAll('button')].every((button) => button.disabled)).toBe(true);
    expect(h.target.querySelector('code')?.textContent).toBe(bootstrap.maskedKey);
    await h.submit();
    expect(h.requests).toHaveLength(1);
    complete({ success: false, data: { message: `Rejected ${raw}` } });
    await h.settle();
    expect(h.input.disabled).toBe(false);
    expect(h.input.value).toBe('');
    expect(h.target.querySelector('[role="alert"]')?.textContent).toBe(bootstrap.i18n.failed);
    expect(h.target.querySelector('code')?.textContent).toBe(bootstrap.maskedKey);
    expect(h.target.innerHTML).not.toContain(raw);
  } finally {
    await h.cleanup();
  }
});

test('technical callback inputs have indexed labels and descriptions and remain selectable, readonly rather than disabled', async () => {
  const i18n = Object.fromEntries(
    [
      'regionTitle',
      'regionDescription',
      'courierTitle',
      'courierDescription',
      'logsTitle',
      'callbacksTitle',
      'callbacksDescription',
      'noCallbacks',
      'logsDescription',
      'logsPrivacy',
      'status',
      'provinces',
      'cities',
      'couriers',
      'lastUpdated',
      'validUntil',
      'refreshRegion',
      'scheduling',
      'refreshing',
      'cacheUpdated',
      'refreshFailed',
      'flushCouriers',
      'flushing',
      'cacheRefreshed',
      'flushFailed',
      'cached',
      'notCached',
      'downloadLog',
    ].map((key) => [key, key]),
  ) as TechnicalBootstrap['i18n'];
  const callbacks = [
    'https://fixture.test/wp-json/shipping/callback/one',
    'https://fixture.test/wp-json/shipping/callback/two',
  ];
  const technical: TechnicalBootstrap = {
    view: 'technical',
    toolbar: { logoUrl: '', rootUrl: '/', rootLabel: 'Settings', title: 'Technical' },
    downloadLogUrl: '/logs',
    callbacks,
    region: {
      state: 'ready',
      lastError: '',
      provinceCount: 1,
      cityCount: 2,
      updated: '',
      validUntil: '',
    },
    couriers: { cached: true, count: 2, updated: '', validUntil: '' },
    i18n,
  };
  const h = await fixture(() => ({}), technical);
  try {
    expect(h.target.querySelectorAll('input')).toHaveLength(2);
    callbacks.forEach((callback, index) => {
      const input = h.target.querySelector<HTMLInputElement>(`#kiriof-callback-${index}`)!;
      expect(input.value).toBe(callback);
      expect(input.readOnly).toBe(true);
      expect(input.disabled).toBe(false);
      expect(h.target.querySelector(`label[for="${input.id}"]`)?.textContent).toBe(
        `callbacksTitle ${index + 1}`,
      );
      expect(
        h.target.querySelector(`#${input.getAttribute('aria-describedby')}`)?.textContent,
      ).toBe('callbacksDescription');
      input.focus();
      input.select();
      expect(h.window.document.activeElement).toBe(input);
      expect(input.selectionEnd! - input.selectionStart!).toBe(callback.length);
    });
  } finally {
    await h.cleanup();
  }
});
