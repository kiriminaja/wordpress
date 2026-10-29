import { afterAll, describe, expect, test } from 'bun:test';
import { existsSync, mkdtempSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';

const root = resolve(import.meta.dir, '..');
let buildDirectory: string | undefined;
let bundle: Promise<Buffer> | undefined;
afterAll(() => {
  if (buildDirectory) rmSync(buildDirectory, { recursive: true, force: true });
});

async function buildBundle() {
  const directory = (buildDirectory = mkdtempSync(
    join(root, 'node_modules', '.deferred-dialog-build-'),
  ));
  writeFileSync(
    join(directory, 'Host.svelte'),
    `
<script lang="ts">
  import DeferredDialog from '${join(root, 'src/lib/ui/DeferredDialog.svelte')}';
  let { load, observe }: { load: () => Promise<any>; observe: any } = $props();
  let active = $state(false);
  let ids = $state<number[]>([11]);
  export function select(next: number[]) { ids = next; active = true; }
  export function close() { active = false; }
</script>
<button data-trigger onclick={() => { active = true; }}>Open workspace</button>
<DeferredDialog {active} {load} i18n={{ loading: 'Loading workspace', actionError: 'Workspace failed', retry: 'Retry workspace', close: 'Cancel workspace' }} onClose={() => { active = false; }}>
  {#snippet children(loaded)}
    <loaded.default open={active} {ids} {observe} onClose={() => { active = false; }} />
  {/snippet}
</DeferredDialog>
`,
  );
  writeFileSync(
    join(directory, 'Child.svelte'),
    `
<script lang="ts">
  import { onMount } from 'svelte';
  import KiriofDialog from '${join(root, 'src/lib/ui/KiriofDialog.svelte')}';
  let { open, ids, observe, onClose }: { open: boolean; ids: number[]; observe: any; onClose: () => void } = $props();
  onMount(() => { observe.mounts++; return () => { observe.destroyed++; }; });
  $effect(() => { observe.states.push({ open, ids: [...ids] }); });
</script>
<KiriofDialog {open} title="Imported workspace" primaryLabel="Save workspace" onPrimary={() => {}} secondaryLabel="Close workspace" onSecondary={onClose} onOpenChange={(next) => { if (!next) onClose(); }}>
  <button data-child-input>Selected {ids.join(',')}</button>
</KiriofDialog>
`,
  );
  writeFileSync(
    join(directory, 'entry.ts'),
    `export { mount, unmount, flushSync } from 'svelte'; export { default as App } from './Host.svelte'; export { default as Child } from './Child.svelte';`,
  );
  const result = await Bun.build({
    entrypoints: [join(directory, 'entry.ts')],
    outdir: directory,
    naming: 'runtime.js',
    target: 'browser',
    conditions: ['browser'],
    plugins: [
      {
        name: 'deferred-dialog-production',
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
            contents: compile(readFileSync(path, 'utf8'), {
              filename: path,
              generate: 'client',
            }).js.code,
            loader: 'js',
          }));
        },
      },
    ],
  });
  if (!result.success) throw new Error(result.logs.join('\n'));
  return readFileSync(join(directory, 'runtime.js'));
}

async function fixture() {
  const directory = mkdtempSync(join(root, 'node_modules', '.deferred-dialog-runtime-'));
  const window = new happy.Window({
    url: 'https://fixture.test/wp-admin/admin.php',
  });
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
    Object.defineProperty(globalThis, key, {
      configurable: true,
      writable: true,
      value,
    });
  }
  const attempts: {
    resolve: (value: unknown) => void;
    reject: (reason: Error) => void;
  }[] = [];
  const observe = {
    mounts: 0,
    destroyed: 0,
    states: [] as { open: boolean; ids: number[] }[],
  };
  let runtime: any;
  let component: any;
  async function dispose() {
    if (component) {
      await runtime.unmount(component);
      component = undefined;
    }
  }
  async function cleanup() {
    try {
      await dispose();
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
    // A separate module URL gives every Window its own Svelte and Bits UI DOM caches.
    writeFileSync(join(directory, 'runtime.js'), await (bundle ??= buildBundle()));
    runtime = await import(join(directory, 'runtime.js'));
    const target = window.document.createElement('main');
    window.document.body.append(target);
    component = runtime.mount(runtime.App, {
      target,
      props: {
        observe,
        load: () =>
          new Promise((resolve, reject) => {
            attempts.push({ resolve, reject });
          }),
      },
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
    const trigger = target.querySelector<HTMLButtonElement>('[data-trigger]')!;
    const dialog = () => window.document.querySelector('[role="dialog"]');
    const button = (label: string) =>
      [...window.document.querySelectorAll('button')].find(
        (candidate) => candidate.textContent?.trim() === label,
      )!;
    async function open(ids?: number[]) {
      trigger.focus();
      if (ids) component.select(ids);
      else trigger.click();
      await settle();
    }
    async function close() {
      component.close();
      await settle();
    }
    async function escape() {
      window.document.activeElement!.dispatchEvent(
        new window.KeyboardEvent('keydown', {
          key: 'Escape',
          bubbles: true,
          cancelable: true,
        }),
      );
      await settle();
    }
    async function resolveImport(index = 0) {
      attempts[index].resolve({ default: runtime.Child });
      await settle();
    }
    async function rejectImport(index = 0) {
      attempts[index].reject(new Error('Fixture import failed'));
      await settle();
    }
    return {
      window,
      target,
      trigger,
      dialog,
      button,
      attempts,
      observe,
      settle,
      open,
      close,
      escape,
      resolveImport,
      rejectImport,
      dispose,
      cleanup,
    };
  } catch (error) {
    await cleanup();
    throw error;
  }
}

describe('DeferredDialog (compiled production boundary and real Bits UI)', () => {
  test('closed initial render does not import or mount; first activation loads once with cancellable loading', async () => {
    const h = await fixture();
    try {
      expect(h.attempts).toHaveLength(0);
      expect(h.observe.mounts).toBe(0);
      expect(h.dialog()).toBeNull();
      await h.open();
      expect(h.attempts).toHaveLength(1);
      expect(h.dialog()?.textContent).toContain('Loading workspace');
      expect(h.button('Loading workspace').disabled).toBe(true);
      expect(h.button('Cancel workspace').disabled).toBe(false);
      h.button('Cancel workspace').click();
      await h.settle();
      expect(h.dialog()).toBeNull();
      await h.open();
      expect(h.attempts).toHaveLength(1);
    } finally {
      await h.cleanup();
    }
  });

  test('closing during import cannot open a dialog when that import resolves', async () => {
    const h = await fixture();
    try {
      await h.open();
      h.button('Cancel workspace').click();
      await h.settle();
      await h.resolveImport();
      expect(h.dialog()).toBeNull();
      expect(h.observe.states.some((state) => state.open)).toBe(false);
      expect(h.window.document.activeElement).toBe(h.trigger);
      expect(h.attempts).toHaveLength(1);
    } finally {
      await h.cleanup();
    }
  });

  test('rapid close and reopen passes the latest IDs through the real child snippet', async () => {
    const h = await fixture();
    try {
      await h.open([11]);
      await h.close();
      await h.open([22, 33]);
      await h.resolveImport();
      expect(h.attempts).toHaveLength(1);
      expect(h.observe.mounts).toBe(1);
      expect(h.observe.states).toEqual([{ open: true, ids: [22, 33] }]);
      expect(h.dialog()?.textContent).toContain('Selected 22,33');
    } finally {
      await h.cleanup();
    }
  });

  test('import failure offers a working retry and resets to loading until the new attempt succeeds', async () => {
    const h = await fixture();
    try {
      await h.open();
      await h.rejectImport();
      expect(h.dialog()?.textContent).toContain('Workspace failed');
      expect(h.button('Retry workspace').disabled).toBe(false);
      h.button('Retry workspace').click();
      await h.settle();
      expect(h.attempts).toHaveLength(2);
      expect(h.dialog()?.textContent).toContain('Loading workspace');
      expect(h.button('Loading workspace').disabled).toBe(true);
      await h.resolveImport(1);
      expect(h.dialog()?.textContent).toContain('Imported workspace');
      expect(h.observe.mounts).toBe(1);
    } finally {
      await h.cleanup();
    }
  });

  test('disposing before import completion never mounts the loaded child or runs its effects', async () => {
    const h = await fixture();
    try {
      await h.open();
      await h.dispose();
      await h.resolveImport();
      expect(h.dialog()).toBeNull();
      expect(h.target.textContent).toBe('');
      expect(h.observe).toEqual({ mounts: 0, destroyed: 0, states: [] });
    } finally {
      await h.cleanup();
    }
  });

  test('successful subsequent reopen uses the retained component and current IDs without another import', async () => {
    const h = await fixture();
    try {
      await h.open([44]);
      await h.resolveImport();
      h.button('Close workspace').click();
      await h.settle();
      expect(h.dialog()).toBeNull();
      await h.open([55]);
      expect(h.attempts).toHaveLength(1);
      expect(h.observe.mounts).toBe(1);
      expect(h.observe.states.at(-1)).toEqual({ open: true, ids: [55] });
      expect(h.dialog()?.textContent).toContain('Selected 55');
    } finally {
      await h.cleanup();
    }
  });

  test('Escape closes pending and error dialogs and returns focus to the activating trigger', async () => {
    const h = await fixture();
    try {
      await h.open();
      expect(h.dialog()?.contains(h.window.document.activeElement)).toBe(true);
      await h.escape();
      expect(h.dialog()).toBeNull();
      expect(h.window.document.activeElement).toBe(h.trigger);
      await h.open();
      await h.rejectImport();
      expect(h.dialog()?.textContent).toContain('Workspace failed');
      await h.escape();
      expect(h.dialog()).toBeNull();
      expect(h.window.document.activeElement).toBe(h.trigger);
    } finally {
      await h.cleanup();
    }
  });

  test('loading-to-imported handoff keeps focus in the imported dialog, including after queued focus restoration', async () => {
    const h = await fixture();
    try {
      await h.open();
      await h.resolveImport();
      expect(h.dialog()?.textContent).toContain('Imported workspace');
      expect(h.dialog()?.contains(h.window.document.activeElement)).toBe(true);
      const input = h.dialog()!.querySelector<HTMLButtonElement>('[data-child-input]')!;
      input.focus();
      await h.settle();
      expect(h.window.document.activeElement).toBe(input);
      await h.escape();
      expect(h.dialog()).toBeNull();
      expect(h.window.document.activeElement).toBe(h.trigger);
    } finally {
      await h.cleanup();
    }
  });
});
