import { afterAll, expect, test } from 'bun:test';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { happy } from './helpers/ui-runtime';

const root = resolve(import.meta.dir, '..');
let directory: string | undefined;
let bundle: Promise<string> | undefined;
afterAll(() => { if (directory) rmSync(directory, { recursive: true, force: true }); });

// Bundle the unmodified production entry. Only its mounted component/Svelte
// boundary is controlled, so fetch, asset loading, ownership, event listeners,
// DOM replacement and history all execute the real navigation implementation.
async function buildBundle() {
  directory = mkdtempSync(join(root, 'node_modules', '.workspace-navigation-'));
  writeFileSync(join(directory, 'component.ts'), 'export default {};');
  writeFileSync(join(directory, 'svelte.ts'), `
export function mount(_component, { target, props }) {
  const state = window.__workspaceBoundary;
  const node = document.createElement('output');
  node.textContent = props.bootstrap?.name ?? props.initialBootstrap?.name;
  target.append(node);
  const instance = { node, name: node.textContent };
  state.mounts.push(instance);
  return instance;
}
export function unmount(instance) {
  const state = window.__workspaceBoundary;
  state.unmounts.push(instance.name);
  const finish = () => instance.node.remove();
  return state.holdUnmount ? new Promise(resolve => state.pending.push(() => { finish(); resolve(); })) : Promise.resolve(finish());
}`);
  const result = await Bun.build({
    entrypoints: [join(root, 'src/entries/admin-workspace.ts')],
    target: 'browser', format: 'iife', outdir: directory, naming: 'runtime.js',
    plugins: [{ name: 'workspace-mounted-boundary', setup(builder) {
      builder.onResolve({ filter: /^svelte$/ }, () => ({ path: join(directory!, 'svelte.ts') }));
      builder.onResolve({ filter: /\.svelte$/ }, () => ({ path: join(directory!, 'component.ts') }));
      builder.onLoad({ filter: /\.css$/ }, () => ({ contents: '', loader: 'js' }));
    } }],
  });
  if (!result.success) throw new Error(result.logs.join('\n'));
  return readFileSync(join(directory, 'runtime.js'), 'utf8');
}

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (error: Error) => void;
  const promise = new Promise<T>((ok, fail) => { resolve = ok; reject = fail; });
  return { promise, resolve, reject };
}
const settle = async () => { for (let i = 0; i < 30; i++) await Promise.resolve(); };
const url = (name: string) => `https://fixture.test/wp-admin/admin.php?page=kiriminaja-setting&route=${name}`;
function html(name: string, asset = '') {
  return `<html><head><title>${name}</title></head><body><main data-kiriof-settings-page><div data-kiriof-settings-root></div><script type="application/json" data-kiriof-settings-payload>${JSON.stringify({ view: 'warehouses', name })}</script></main>${asset}</body></html>`;
}
async function fixture() {
  const window = new happy.Window({ url: url('initial'), settings: { disableJavaScriptFileLoading: true, enableJavaScriptEvaluation: true } });
  const state = { mounts: [] as any[], unmounts: [] as string[], pending: [] as (() => void)[], holdUnmount: false };
  (window as any).__workspaceBoundary = state;
  window.document.write(html('initial'));
  const requests: { url: string; signal: AbortSignal; response: ReturnType<typeof deferred<any>> }[] = [];
  window.fetch = ((input: any, options: any) => {
    const response = deferred<any>();
    requests.push({ url: String(input), signal: options.signal, response });
    // Intentionally ignore abort: resolved fetches/response bodies and route
    // script loads are not cancelled by AbortController in production either.
    return response.promise;
  }) as any;
  window.scrollTo = (() => {}) as any;
  window.eval(await (bundle ??= buildBundle()));
  const navigate = (name: string) => window.dispatchEvent(new window.CustomEvent('kiriof:settings-navigate', { detail: { href: url(name) } }));
  const respond = async (index: number, name: string, asset = '') => {
    requests[index].response.resolve({ ok: true, text: async () => html(name, asset) });
    await settle();
  };
  const current = () => window.document.querySelector('output')?.textContent;
  const assertFinal = (name: string) => {
    expect(current()).toBe(name);
    expect(window.document.title).toBe(name);
    expect(window.location.href).toBe(url(name));
    expect(window.document.querySelector('main')?.hasAttribute('aria-busy')).toBe(false);
  };
  return { window, state, requests, navigate, respond, current, assertFinal, cleanup: () => window.happyDOM.abort() };
}

test('superseded navigation waiting for assets cannot unmount the new warehouse route or commit stale history', async () => {
  const h = await fixture();
  try {
    h.navigate('A');
    await h.respond(0, 'A', '<script id="kiriof-request-pickup-a" src="https://fixture.test/a.js"></script><script id="kiriof-request-pickup-stale-js-extra">window.staleAsset = true;</script>');
    const asset = h.window.document.getElementById('kiriof-request-pickup-a')!;
    expect(h.current()).toBe('initial');
    h.navigate('B'); await h.respond(1, 'B');
    h.assertFinal('B');
    expect(h.requests[0].signal.aborted).toBe(true);
    asset.dispatchEvent(new h.window.Event('load')); await settle();
    h.assertFinal('B');
    expect(h.state.unmounts).toEqual(['initial']);
    expect(h.state.mounts.map(instance => instance.name)).toEqual(['initial', 'B']);
    expect(h.window.document.getElementById('kiriof-request-pickup-stale-js-extra')).toBeNull();
    expect(h.window.history.length).toBe(2);
  } finally { h.cleanup(); }
});

test('unmount claims the old generation synchronously and its late completion cannot clear a newer mounted owner', async () => {
  const h = await fixture();
  try {
    h.state.holdUnmount = true;
    h.navigate('A'); await h.respond(0, 'A');
    expect(h.state.unmounts).toEqual(['initial']);
    expect(h.state.pending).toHaveLength(1);
    h.state.holdUnmount = false;
    h.navigate('B'); await h.respond(1, 'B');
    h.assertFinal('B');
    h.state.pending.shift()!(); await settle();
    h.assertFinal('B');
    expect(h.state.unmounts).toEqual(['initial']);
    h.navigate('C'); await h.respond(2, 'C');
    h.assertFinal('C');
    expect(h.state.unmounts).toEqual(['initial', 'B']);
    expect(h.state.mounts.map(instance => instance.name)).toEqual(['initial', 'B', 'C']);
    expect(h.window.history.length).toBe(3);
  } finally { h.cleanup(); }
});

test('a superseded response body or asset failure cannot restore a stale route via hard navigation', async () => {
  for (const boundary of ['body', 'asset'] as const) {
    const h = await fixture();
    try {
      h.navigate('A');
      const body = deferred<string>();
      if (boundary === 'body') {
        h.requests[0].response.resolve({ ok: true, text: () => body.promise }); await settle();
      } else {
        await h.respond(0, 'A', '<script id="kiriof-request-pickup-error" src="https://fixture.test/error.js"></script>');
      }
      h.navigate('B'); await h.respond(1, 'B');
      h.assertFinal('B');
      if (boundary === 'body') body.resolve(html('A'));
      else h.window.document.getElementById('kiriof-request-pickup-error')!.dispatchEvent(new h.window.Event('error'));
      await settle(); h.assertFinal('B');
      expect(h.state.unmounts).toEqual(['initial']);
      expect(h.state.mounts.map(instance => instance.name)).toEqual(['initial', 'B']);
    } finally { h.cleanup(); }
  }
});
