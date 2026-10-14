import { expect, test } from 'bun:test';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';
import type { GoogleMapsBootstrap } from '../src/lib/settings/types';

const root = resolve(import.meta.dir, '..');
const raw = 'AIza-synthetic_replacement_key123';
const mask = (key: string) => key.slice(0, 4) + '*'.repeat(key.length - 7) + key.slice(-3);
const bootstrap: GoogleMapsBootstrap = { configured: true, maskedKey: mask('AIza-synthetic_saved_key456'), nonce: 'google-dedicated-nonce', i18n: {
  title: 'Google Maps', guidance: 'Use your own browser key', configured: 'Configured', notConfigured: 'Not configured', keyLabel: 'Replacement key', placeholder: 'Enter a replacement', save: 'Save key', remove: 'Remove key', busy: 'Saving', saved: 'Settings saved', failed: 'Unable to save settings',
} };

// Compile the actual production component and its actual Button dependency; only
// fetch is a mocked WordPress boundary. No source-string assertions or substitute UI.
async function fixture(response: (body: URLSearchParams) => unknown) {
  const window = new happy.Window({ url: 'https://fixture.test' });
  const directory = mkdtempSync(join(root, 'node_modules', '.google-settings-runtime-'));
  const saved = new Map<string, PropertyDescriptor | undefined>();
  const requests: URLSearchParams[] = [];
  (window as any).ajaxurl = '/wp-admin/admin-ajax.php';
  (window as any).kiriofSettings = { nonce: 'general-must-not-be-used' };
  const globals = { window, document: window.document, navigator: window.navigator, Node: window.Node, Element: window.Element, HTMLElement: window.HTMLElement, HTMLMediaElement: window.HTMLMediaElement, HTMLInputElement: window.HTMLInputElement, SVGElement: window.SVGElement, DocumentFragment: window.DocumentFragment, Text: window.Text, Comment: window.Comment, Event: window.Event, MutationObserver: window.MutationObserver, getComputedStyle: window.getComputedStyle.bind(window),
    fetch: async (_url: unknown, options: RequestInit) => { expect(options.method).toBe('POST'); const body = new URLSearchParams(options.body as URLSearchParams); requests.push(body); return new Response(JSON.stringify(response(body))); },
  };
  for (const [key, value] of Object.entries(globals)) { saved.set(key, Object.getOwnPropertyDescriptor(globalThis, key)); Object.defineProperty(globalThis, key, { configurable: true, writable: true, value }); }
  let runtime: any, component: any;
  const cleanup = async () => { try { if (component) await runtime.unmount(component); } finally { window.happyDOM.abort(); for (const [key, descriptor] of saved) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete (globalThis as any)[key]; } rmSync(directory, { recursive: true, force: true }); } };
  try {
    writeFileSync(join(directory, 'entry.ts'), `export {mount, unmount, flushSync} from 'svelte'; export {default as Component} from ${JSON.stringify(join(root, 'src/lib/settings/GoogleMapsIntegration.svelte'))};`);
    const build = await Bun.build({ entrypoints: [join(directory, 'entry.ts')], outdir: directory, naming: 'runtime.js', target: 'browser', conditions: ['browser'], plugins: [{ name: 'production-svelte', setup(builder) {
      builder.onResolve({ filter: /^svelte$/ }, () => ({ path: join(root, 'node_modules/svelte/src/index-client.js') }));
      builder.onResolve({ filter: /^\$lib\// }, ({ path }) => ({ path: path === '$lib/components/ui/button' ? join(root, 'src/lib/components/ui/button/index.ts') : join(root, 'src/lib', path.slice(5).replace(/\.js$/, '.ts')) }));
      builder.onLoad({ filter: /\.svelte$/ }, ({ path }) => ({ contents: compile(readFileSync(path, 'utf8'), { filename: path, generate: 'client' }).js.code, loader: 'js' }));
    } }] });
    if (!build.success) throw new Error(build.logs.join('\n'));
    runtime = await import(join(directory, 'runtime.js'));
    const target = window.document.createElement('main'); window.document.body.append(target);
    component = runtime.mount(runtime.Component, { target, props: { bootstrap } });
    const settle = async () => { for (let i = 0; i < 12; i++) { await Promise.resolve(); runtime.flushSync(); } };
    await settle();
    const input = target.querySelector<HTMLInputElement>('input')!;
    const submit = async () => { input.value = raw; input.dispatchEvent(new window.Event('input', { bubbles: true })); runtime.flushSync(); const event = new window.Event('submit', { bubbles: true, cancelable: true }); target.querySelector('form')!.dispatchEvent(event); expect(event.defaultPrevented).toBe(true); await settle(); };
    return { target, input, requests, submit, settle, window, cleanup };
  } catch (error) { await cleanup(); throw error; }
}

test('production DOM saves with dedicated nested nonce, renders only mask, clears replacement, and removes without a key', async () => {
  const h = await fixture(body => ({ success: true, data: { status: 200, data: body.get('data[mode]') === 'remove' ? { configured: false, maskedKey: '' } : { configured: true, maskedKey: mask(raw) } } }));
  try {
    expect(h.target.querySelector('h2')?.textContent).toBe('Google Maps');
    expect(h.target.querySelector('code')?.textContent).toBe(bootstrap.maskedKey);
    expect(h.input.type).toBe('password'); expect(h.input.value).toBe(''); expect(h.input.getAttribute('value')).toBeNull();
    await h.submit();
    expect(Object.fromEntries(h.requests[0]!)).toEqual({ action: 'kiriof_save_google_maps_settings', 'data[mode]': 'save', 'data[key]': raw, 'data[nonce]': bootstrap.nonce });
    expect(h.target.querySelector('code')?.textContent).toBe(mask(raw)); expect(h.target.innerHTML).not.toContain(raw); expect(h.input.value).toBe('');
    h.target.querySelector<HTMLButtonElement>('button[type="button"]')!.click(); await h.settle();
    expect(Object.fromEntries(h.requests[1]!)).toEqual({ action: 'kiriof_save_google_maps_settings', 'data[mode]': 'remove', 'data[nonce]': bootstrap.nonce });
    expect(h.target.querySelector('code')).toBeNull(); expect(h.target.textContent).toContain('Not configured'); expect(h.input.value).toBe('');
  } finally { await h.cleanup(); }
});

test('production DOM never echoes a credential-bearing transport error or malformed raw-key summary', async () => {
  let attempt = 0;
  const h = await fixture(() => ++attempt === 1 ? { success: false, data: { message: `Rejected ${raw}` } } : { success: true, data: { status: 200, data: { configured: true, maskedKey: raw } } });
  try {
    await h.submit(); expect(h.target.querySelector('[role="alert"]')?.textContent).toBe(bootstrap.i18n.failed); expect(h.target.innerHTML).not.toContain(raw); expect(h.input.value).toBe('');
    await h.submit(); expect(h.target.querySelector('code')?.textContent).toBe(''); expect(h.target.innerHTML).not.toContain(raw); expect(h.input.value).toBe('');
  } finally { await h.cleanup(); }
});
