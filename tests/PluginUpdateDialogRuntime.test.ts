import { afterAll, describe, expect, test } from 'bun:test';
import { existsSync, mkdtempSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import type { ToolbarUpdate } from '../src/lib/ui/toolbar';
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
    `export {mount, unmount, flushSync} from 'svelte'; export {default as App} from '${join(root, 'src/lib/ui/PluginUpdateDialog.svelte')}';`,
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

const update: ToolbarUpdate = {
  label: 'New update v1.9.8 available',
  title: 'KiriminAja update available',
  version: '1.9.8',
  description: 'A new version of KiriminAja is available.',
  details: ['Legacy detail must not render'],
  changelog: 'Fix pickup status\n\nImprove checkout',
  changelogLabel: 'Changelog',
  changelogUnavailable: 'Release notes are not available.',
  primaryLabel: 'View Plugin Page',
  primaryUrl: 'https://wordpress.org/plugins/kiriminaja/',
  primaryExternal: true,
  secondaryLabel: 'Open Plugins',
  secondaryUrl: '/wp-admin/plugins.php',
  secondaryExternal: false,
  dismissLabel: 'Dismiss',
  dismissUrl: '/wp-admin/admin.php?page=kiriminaja&_wpnonce=fixture&dismiss_update=1.9.8',
  closeLabel: 'Close',
};

async function fixture(overrides: Partial<ToolbarUpdate> = {}) {
  const directory = mkdtempSync(join(root, 'node_modules', '.plugin-update-runtime-'));
  const window = new happy.Window({ url: 'https://fixture.test/wp-admin/admin.php' });
  const saved = new Map<string, PropertyDescriptor | undefined>();
  const fetchCalls: unknown[][] = [];
  const fetch = (...args: unknown[]) => {
    fetchCalls.push(args);
    return Promise.resolve(new Response(null, { status: 204 }));
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
      props: { update: { ...update, ...overrides } },
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
    const trigger = target.querySelector('button')!;
    const dialog = () => window.document.querySelector('[role="dialog"]')!;
    async function open() {
      trigger.focus();
      trigger.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
      await settle();
    }
    return { window, trigger, dialog, settle, open, cleanup, fetchCalls };
  } catch (error) {
    await cleanup();
    throw error;
  }
}

describe('Plugin update dialog (compiled production and real bits-ui)', () => {
  test('localized close icon uses Tutup without forwarding the prop and restores focus on close and Escape', async () => {
    const h = await fixture({ closeLabel: 'Tutup' });
    try {
      await h.open();
      const dialog = h.dialog();
      const close = [...dialog.querySelectorAll('button')].find(
        (button) => button.textContent?.trim() === 'Tutup',
      )!;
      expect(close).toBeDefined();
      expect(close.querySelector('svg')).not.toBeNull();
      expect(close.querySelector('.sr-only')?.textContent).toBe('Tutup');
      expect(close.getAttribute('aria-hidden')).not.toBe('true');
      expect(dialog.hasAttribute('closeLabel')).toBe(false);
      expect(
        dialog.querySelector('[data-slot="dialog-footer"]')?.querySelectorAll('a'),
      ).toHaveLength(2);
      close.click();
      await h.settle();
      expect(h.dialog()).toBeNull();
      expect(h.window.document.activeElement).toBe(h.trigger);

      await h.open();
      expect(
        h.dialog().querySelector('[data-slot="dialog-footer"]')?.querySelectorAll('a'),
      ).toHaveLength(2);
      h.window.document.activeElement!.dispatchEvent(
        new h.window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
      );
      await h.settle();
      expect(h.dialog()).toBeNull();
      expect(h.window.document.activeElement).toBe(h.trigger);
    } finally {
      await h.cleanup();
    }
  });

  test('compact warning badge keeps the full version and opens an accessible dialog by keyboard', async () => {
    const h = await fixture();
    try {
      expect(h.trigger.getAttribute('aria-label')).toBe(update.label);
      expect(h.trigger.textContent?.trim()).toBe(update.label);
      expect(h.trigger.classList.contains('min-h-6')).toBe(true);
      const badge = h.trigger;
      expect(badge.querySelector('[data-slot="badge"]')).toBeNull();
      expect(badge.classList.contains('appearance-none')).toBe(true);
      expect(badge.classList.contains('shadow-none')).toBe(true);
      expect(badge.classList.contains('border-solid')).toBe(true);
      expect(badge.classList.contains('border-warning-border')).toBe(true);
      expect(badge.className).toContain('bg-warning-background');
      expect(badge.querySelector(':scope > svg[data-icon="inline-start"]')).not.toBeNull();
      expect(badge.className).toContain('[&>svg]:size-3!');
      await h.open();
      const dialog = h.dialog();
      expect(dialog).not.toBeNull();
      expect(
        h.window.document.getElementById(dialog.getAttribute('aria-labelledby')!)?.textContent,
      ).toBe(update.title);
      expect(
        h.window.document.getElementById(dialog.getAttribute('aria-describedby')!)?.textContent,
      ).toBe(update.description);
      expect(dialog.contains(h.window.document.activeElement)).toBe(true);
      h.window.document.activeElement!.dispatchEvent(
        new h.window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
      );
      await h.settle();
      expect(h.dialog()).toBeNull();
      expect(h.window.document.activeElement).toBe(h.trigger);
    } finally {
      await h.cleanup();
    }
  });

  test('footer has only native Dismiss and external plugin-page Button links; modified clicks propagate', async () => {
    const h = await fixture();
    try {
      await h.open();
      const footer = h.dialog().querySelector('[data-slot="dialog-footer"]')!;
      const links = [...footer.querySelectorAll('a')];
      expect(links.map((link) => link.textContent?.trim())).toEqual([
        'Dismiss',
        'View Plugin Page',
      ]);
      expect(footer.querySelectorAll('[data-slot="button"]')).toHaveLength(2);
      expect(footer.querySelector('button')).toBeNull();
      expect(links[0].getAttribute('href')).toBe(update.dismissUrl);
      expect(links[0].getAttribute('data-variant')).toBe('outline');
      expect(links[1].getAttribute('href')).toBe(update.primaryUrl);
      expect(links[1].getAttribute('target')).toBe('_blank');
      expect(links[1].getAttribute('rel')).toBe('noopener noreferrer');
      expect(links[1].querySelector('svg[data-icon="inline-end"]')).not.toBeNull();
      expect(h.dialog().textContent).not.toContain('Open Plugins');
      expect(h.dialog().textContent).not.toContain('Legacy detail');
      const clicks: boolean[] = [];
      h.window.document.addEventListener('click', (event) => {
        clicks.push(event.defaultPrevented);
        event.preventDefault();
      });
      for (const link of links)
        link.dispatchEvent(
          new h.window.MouseEvent('click', { bubbles: true, cancelable: true, metaKey: true }),
        );
      expect(clicks).toEqual([false, false]);
      expect(h.dialog()).not.toBeNull();
      const close = [...h.dialog().querySelectorAll('button')].find(
        (button) => button.textContent?.trim() === update.closeLabel,
      )!;
      close.click();
      await h.settle();
      expect(h.dialog()).toBeNull();
      expect(h.window.document.activeElement).toBe(h.trigger);
      expect(clicks).toHaveLength(3);
    } finally {
      await h.cleanup();
    }
  });

  test('Update Now is an exact nonce-protected same-tab native link without eager update requests', async () => {
    const primaryUrl =
      'https://fixture.test/wp-admin/update.php?action=upgrade-plugin&plugin=kiriminaja-official%2Fkiriminaja.php&_wpnonce=update-fixture-nonce';
    const h = await fixture({
      primaryLabel: 'Update Now',
      primaryUrl,
      primaryExternal: false,
    });
    try {
      expect(h.fetchCalls).toHaveLength(0);
      await h.open();
      const dialog = h.dialog();
      const footer = dialog.querySelector('[data-slot="dialog-footer"]')!;
      const links = [...footer.querySelectorAll('a')];
      expect(links.map((link) => link.textContent?.trim())).toEqual(['Dismiss', 'Update Now']);
      expect(footer.querySelectorAll('[data-slot="button"]')).toHaveLength(2);
      expect(footer.querySelector('button')).toBeNull();
      expect(links[0].getAttribute('href')).toBe(update.dismissUrl);
      const primary = links[1];
      expect(primary.getAttribute('href')).toBe(primaryUrl);
      expect(primary.href).toBe(primaryUrl);
      expect(primary.getAttribute('href')).not.toContain('&amp;');
      const destination = new URL(primary.href);
      expect(destination.origin).toBe(h.window.location.origin);
      expect(destination.pathname).toBe('/wp-admin/update.php');
      expect([...destination.searchParams]).toEqual([
        ['action', 'upgrade-plugin'],
        ['plugin', 'kiriminaja-official/kiriminaja.php'],
        ['_wpnonce', 'update-fixture-nonce'],
      ]);
      expect(primary.hasAttribute('target')).toBe(false);
      expect(primary.hasAttribute('rel')).toBe(false);
      expect(primary.querySelector('svg')).toBeNull();
      expect(dialog.textContent).not.toContain('View Plugin Page');
      expect(dialog.textContent).not.toContain('Open Plugins');
      expect(h.fetchCalls).toHaveLength(0);

      // Observe native click propagation, but prevent navigation: never perform
      // the actual upgrade (or the nonce-protected dismissal) in this test.
      const clicks: boolean[] = [];
      h.window.document.addEventListener('click', (event) => {
        clicks.push(event.defaultPrevented);
        event.preventDefault();
      });
      primary.click();
      primary.dispatchEvent(
        new h.window.MouseEvent('click', { bubbles: true, cancelable: true, metaKey: true }),
      );
      await h.settle();
      expect(clicks).toEqual([false, false]);
      expect(primary.getAttribute('href')).toBe(primaryUrl);
      expect(h.fetchCalls).toHaveLength(0);
      expect(h.window.location.href).toBe('https://fixture.test/wp-admin/admin.php');

      h.window.document.activeElement!.dispatchEvent(
        new h.window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
      );
      await h.settle();
      expect(h.dialog()).toBeNull();
      expect(h.window.document.activeElement).toBe(h.trigger);
      await h.open();
      const close = [...h.dialog().querySelectorAll('button')].find(
        (button) => button.textContent?.trim() === update.closeLabel,
      )!;
      close.click();
      await h.settle();
      expect(h.dialog()).toBeNull();
      expect(h.window.document.activeElement).toBe(h.trigger);
      expect(h.fetchCalls).toHaveLength(0);
      expect(h.window.location.href).toBe('https://fixture.test/wp-admin/admin.php');
    } finally {
      await h.cleanup();
    }
  });

  test('release notes are exact escaped plaintext in a labelled keyboard-scrollable bounded region', async () => {
    const changelog = `Release 1.9.8\n\n<script>alert('x')</script>\n<img src=x onerror=alert(1)>\n${'Long note '.repeat(1000)}`;
    const h = await fixture({ changelog });
    try {
      await h.open();
      const region = h.dialog().querySelector('[role="region"]')!;
      expect(region.textContent).toBe(changelog);
      expect(region.getAttribute('tabindex')).toBe('0');
      expect(
        h.window.document.getElementById(region.getAttribute('aria-labelledby')!)?.textContent,
      ).toBe('Changelog');
      for (const token of ['max-h-64', 'overflow-y-auto', 'whitespace-pre-line', 'break-words'])
        expect(region.classList.contains(token)).toBe(true);
      expect(region.querySelector('script,img')).toBeNull();
      (region as any).focus();
      expect(h.window.document.activeElement).toBe(region);
    } finally {
      await h.cleanup();
    }
  });

  test('missing release notes use the localized fallback rather than legacy release details', async () => {
    const h = await fixture({
      changelog: '',
      changelogLabel: 'Catatan perubahan',
      changelogUnavailable: 'Catatan belum tersedia.',
    });
    try {
      await h.open();
      expect(h.dialog().querySelector('[role="region"]')?.textContent).toBe(
        'Catatan belum tersedia.',
      );
      expect(h.dialog().querySelector('h3')?.textContent).toBe('Catatan perubahan');
      expect(h.dialog().textContent).not.toContain(update.changelog);
    } finally {
      await h.cleanup();
    }
  });
});
