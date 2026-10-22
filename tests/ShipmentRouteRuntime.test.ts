import { afterAll, describe, expect, test } from 'bun:test';
import { existsSync, mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';

const root = resolve(import.meta.dir, '..');
let buildDirectory: string | undefined;
let bundlePromise: Promise<Buffer> | undefined;
afterAll(() => {
  if (buildDirectory) rmSync(buildDirectory, { recursive: true, force: true });
});

// Each Window gets a fresh copy of the production component and Bits UI runtime.
async function buildBundle(): Promise<Buffer> {
  const directory = (buildDirectory = mkdtempSync(
    join(root, 'node_modules', '.shipment-route-build-'),
  ));
  const put = (name: string, source: string) => writeFileSync(join(directory, name), source);
  put(
    'entry.ts',
    `export {mount, unmount, flushSync} from 'svelte'; export {default as App} from '${join(root, 'src/lib/ui/ShipmentRoute.svelte')}';`,
  );
  const build = await Bun.build({
    entrypoints: [join(directory, 'entry.ts')],
    outdir: directory,
    naming: 'runtime.js',
    target: 'browser',
    conditions: ['browser'],
    plugins: [
      {
        name: 'shipment-route-production-runtime',
        setup(builder) {
          builder.onResolve({ filter: /^\$lib\// }, ({ path }) => {
            const base = join(root, 'src/lib', path.slice(5));
            const candidates = [
              base,
              base.replace(/\.js$/, '.ts'),
              `${base}.ts`,
              join(base, 'index.ts'),
            ];
            const found = candidates.find(
              (candidate) => existsSync(candidate) && !readdirIsDirectory(candidate),
            );
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
function readdirIsDirectory(path: string): boolean {
  try {
    readdirSync(path);
    return true;
  } catch {
    return false;
  }
}

async function fixture(rows: Record<string, unknown>[]) {
  const directory = mkdtempSync(join(root, 'node_modules', '.shipment-route-runtime-'));
  const window = new happy.Window({ url: 'https://fixture.test/' });
  const saved = new Map<string, PropertyDescriptor | undefined>();
  const globals = {
    window,
    document: window.document,
    navigator: window.navigator,
    Node: window.Node,
    Element: window.Element,
    HTMLElement: window.HTMLElement,
    HTMLButtonElement: window.HTMLButtonElement,
    HTMLMediaElement: window.HTMLMediaElement,
    SVGElement: window.SVGElement,
    DocumentFragment: window.DocumentFragment,
    HTMLInputElement: window.HTMLInputElement,
    HTMLSelectElement: window.HTMLSelectElement,
    Text: window.Text,
    Comment: window.Comment,
    KeyboardEvent: window.KeyboardEvent,
    MouseEvent: window.MouseEvent,
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
  const apps: any[] = [];
  try {
    writeFileSync(join(directory, 'runtime.js'), await (bundlePromise ??= buildBundle()));
    runtime = await import(join(directory, 'runtime.js'));
    for (const row of rows) {
      const target = window.document.createElement('section');
      window.document.body.append(target);
      apps.push(
        runtime.mount(runtime.App, {
          target,
          props: { title: 'Shipment route', detailsLabel: 'View address details', ...row },
        }),
      );
    }
    async function settle() {
      for (let i = 0; i < 20; i++) {
        await Promise.resolve();
        runtime.flushSync();
      }
      await new Promise((resolve) => window.setTimeout(resolve, 30));
      runtime.flushSync();
    }
    await settle();
    return {
      window,
      settle,
      cleanup: async () => {
        for (const app of apps) await runtime.unmount(app);
        window.happyDOM.abort();
        for (const [key, descriptor] of saved) {
          if (descriptor) Object.defineProperty(globalThis, key, descriptor);
          else delete (globalThis as any)[key];
        }
        rmSync(directory, { recursive: true, force: true });
      },
    };
  } catch (error) {
    window.happyDOM.abort();
    for (const [key, descriptor] of saved) {
      if (descriptor) Object.defineProperty(globalThis, key, descriptor);
      else delete (globalThis as any)[key];
    }
    rmSync(directory, { recursive: true, force: true });
    throw error;
  }
}

describe('ShipmentRoute (actual shadcn Button, Popover, Bits and Tabler icons)', () => {
  test('compact preview opens escaped full details, isolates repeated rows, and restores focus on Escape', async () => {
    const origin = '<img src=x onerror=alert(1)> Warehouse';
    const recipient = 'Recipient & <script>unsafe</script>';
    const lines = [
      'First street <b>not markup</b>',
      'Second district & city',
      'Postal' + 'X'.repeat(350),
    ];
    const f = await fixture([
      { origin, recipient, addressLines: lines },
      { origin: 'Other warehouse', recipient: 'Other recipient', addressLines: ['Other street'] },
    ]);
    try {
      const { document } = f.window;
      const triggers = [...document.querySelectorAll<HTMLButtonElement>('[data-popover-trigger]')];
      expect(triggers).toHaveLength(2);
      for (const trigger of triggers) {
        expect(trigger.getAttribute('data-slot')).toBe('popover-trigger');
        expect(trigger.classList.contains('kiriof-button')).toBe(true);
        expect(trigger.getAttribute('data-variant')).toBe('ghost');
        expect(trigger.classList.contains('bg-muted')).toBe(false);
      }
      expect(document.querySelector('[role="dialog"]')).toBeNull();
      expect(triggers[0].textContent).toContain(lines[0]);
      expect(triggers[0].textContent).not.toContain(lines[1]);
      expect(triggers[0].querySelector('svg')).not.toBeNull();
      expect(document.body.textContent).not.toMatch(/[↓→]/);
      triggers[0].focus();
      triggers[0].dispatchEvent(new f.window.MouseEvent('click', { bubbles: true, button: 0 }));
      await f.settle();
      const dialog = document.querySelector<HTMLElement>('[role="dialog"]')!;
      expect(dialog).not.toBeNull();
      expect(document.getElementById(dialog.getAttribute('aria-labelledby')!)?.textContent).toBe(
        'Shipment route',
      );
      for (const value of [origin, recipient, ...lines])
        expect(dialog.textContent).toContain(value);
      expect(dialog.textContent).not.toContain('Other street');
      expect(dialog.querySelector('img, script, b')).toBeNull();
      expect(dialog.className).toContain('[overflow-wrap:anywhere]');
      expect(dialog.className).toContain('overflow-y-auto');
      document.activeElement!.dispatchEvent(
        new f.window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
      );
      await f.settle();
      expect(document.querySelector('[role="dialog"]')).toBeNull();
      expect(document.activeElement).toBe(triggers[0]);
      triggers[1].focus();
      triggers[1].dispatchEvent(
        new f.window.KeyboardEvent('keydown', { key: 'Enter', bubbles: true }),
      );
      await f.settle();
      const second = document.querySelector<HTMLElement>('[role="dialog"]')!;
      expect(second).not.toBeNull();
      expect(second.id).not.toBe(dialog.id);
      expect(second.textContent).toContain('Other street');
      expect(second.textContent).not.toContain(lines[2]);
    } finally {
      await f.cleanup();
    }
  });

  test('missing address preserves names and empty routes do not create an empty popover', async () => {
    const f = await fixture([
      { origin: 'Origin only', addressLines: null },
      { recipient: 'Recipient only', addressLines: ['', null, '  '] },
      {},
    ]);
    try {
      const { document } = f.window;
      const triggers = [...document.querySelectorAll<HTMLButtonElement>('[data-popover-trigger]')];
      expect(triggers).toHaveLength(2);
      expect(document.querySelectorAll('[data-shipment-route]')).toHaveLength(3);
      expect(triggers[0].textContent).toContain('Origin only');
      triggers[0].dispatchEvent(new f.window.MouseEvent('click', { bubbles: true, button: 0 }));
      await f.settle();
      expect(document.querySelector('[role="dialog"]')?.textContent).toContain('Origin only');
      expect(document.body.textContent).not.toContain('undefined');
      expect(document.body.textContent).not.toContain('null');
    } finally {
      await f.cleanup();
    }
  });
});
