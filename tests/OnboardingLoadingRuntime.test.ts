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
    join(root, 'node_modules', '.deferred-step-build-'),
  ));
  writeFileSync(
    join(directory, 'Host.svelte'),
    `
<script lang="ts">
  import DeferredStep from '${join(root, 'src/lib/onboarding/DeferredStep.svelte')}';
  let { load, observe } = $props();
  let active = $state(false);
  let ids = $state([11]);
  export function select(next) { ids = next; active = true; }
  export function close() { active = false; }
</script>
<button data-trigger onclick={() => { active = true; }}>Open workspace</button>
{#if active}
<DeferredStep {load} i18n={{ loading: 'Loading workspace', actionError: 'Workspace failed', retry: 'Retry workspace' }}>
  {#snippet children(loaded)}
    <loaded.default {ids} {observe} />
  {/snippet}
</DeferredStep>
{/if}
`,
  );
  writeFileSync(
    join(directory, 'Child.svelte'),
    `
<script lang="ts">
  import { onMount } from 'svelte';
  let { ids, observe } = $props();
  onMount(() => { observe.mounts++; return () => { observe.destroyed++; }; });
  $effect(() => { observe.states.push({ ids: [...ids] }); });
</script>
<button data-child-input>Selected {ids.join(',')}</button>
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
        name: 'deferred-step-production',
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
  const directory = mkdtempSync(join(root, 'node_modules', '.deferred-step-runtime-'));
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

describe('DeferredStep (compiled production boundary)', () => {
  test('imports only on the active step and reads current saved props after resolution', async () => {
    const h = await fixture();
    try {
      expect(h.attempts).toHaveLength(0);
      await h.open([11]);
      expect(h.target.querySelector('[role="status"]')?.textContent).toContain('Loading workspace');
      await h.open([22, 33]);
      expect(h.attempts).toHaveLength(1);
      await h.resolveImport();
      expect(h.observe.states).toEqual([{ ids: [22, 33] }]);
      await h.close();
      expect(h.observe.destroyed).toBe(1);
    } finally {
      await h.cleanup();
    }
  });
  test('a failed import stays failed until explicit retry and then mounts once', async () => {
    const h = await fixture();
    try {
      await h.open();
      await h.rejectImport();
      expect(h.target.querySelector('[role="alert"]')?.textContent).toContain('Workspace failed');
      expect(h.attempts).toHaveLength(1);
      h.button('Retry workspace').click();
      await h.settle();
      expect(h.attempts).toHaveLength(2);
      expect(h.target.querySelector('[role="status"]')).not.toBeNull();
      await h.resolveImport(1);
      expect(h.observe.mounts).toBe(1);
    } finally {
      await h.cleanup();
    }
  });
  test('rapid navigation discards a stale component and stale error without disturbing the newer step', async () => {
    const h = await fixture();
    try {
      await h.open([11]);
      await h.close();
      await h.open([44]);
      expect(h.attempts).toHaveLength(2);
      await h.resolveImport(0);
      expect(h.observe.mounts).toBe(0);
      await h.resolveImport(1);
      expect(h.observe.states).toEqual([{ ids: [44] }]);
      await h.close();
      await h.open([55]);
      await h.close();
      await h.open([66]);
      await h.rejectImport(2);
      expect(h.target.querySelector('[role="alert"]')).toBeNull();
      await h.resolveImport(3);
      expect(h.observe.states.at(-1)).toEqual({ ids: [66] });
    } finally {
      await h.cleanup();
    }
  });
  test('unmount cancels pending ownership without mounting or reporting late failures', async () => {
    for (const failed of [false, true]) {
      const h = await fixture();
      try {
        await h.open();
        await h.dispose();
        if (failed) await h.rejectImport();
        else await h.resolveImport();
        expect(h.target.textContent).toBe('');
        expect(h.observe.mounts).toBe(0);
      } finally {
        await h.cleanup();
      }
    }
  });
});
