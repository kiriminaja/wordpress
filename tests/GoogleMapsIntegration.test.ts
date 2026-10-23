import { afterAll, expect, test } from 'bun:test';
import { existsSync, statSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';
import type { GoogleMapsBootstrap } from '../src/lib/settings/types';

const root = resolve(import.meta.dir, '..');
const raw = 'AIza-synthetic_replacement_key123';
const mask = (key: string) => key.slice(0, 4) + '*'.repeat(key.length - 7) + key.slice(-3);
const bootstrap: GoogleMapsBootstrap = { configured: true, maskedKey: mask('AIza-synthetic_saved_key456'), nonce: 'google-dedicated-nonce', browserCheckUrl: '/?kiriof_google_maps_browser_check=1&_wpnonce=probe-nonce', i18n: {
  title: 'Google Maps', guidance: 'Use your own browser key', configured: 'Configured', notConfigured: 'Not configured', keyLabel: 'Replacement key', placeholder: 'Enter a replacement', save: 'Save key', remove: 'Remove key', busy: 'Saving', saved: 'Settings saved', failed: 'Unable to save settings', keyHint: 'Saved browser key', showKey: 'Show key', hideKey: 'Hide key', browserCheckTitle: 'Browser check', browserCheckGuidance: 'Check this origin', originLabel: 'Tested origin', checking: 'Checking browser', checkAgain: 'Check again', browserReady: 'Map ready', browserFailed: 'Browser check failed', browserNetwork: 'Network failed', browserTimeout: 'Check timed out', browserNotConfigured: 'No saved key', previewTitle: 'Google Maps preview', details: 'Details',
} };

let buildDirectory: string | undefined;
let bundlePromise: Promise<Buffer> | undefined;
let bundleBuildCount = 0;
afterAll(() => {
  try { if (bundlePromise) expect(bundleBuildCount).toBe(1); }
  finally { if (buildDirectory) rmSync(buildDirectory, { recursive: true, force: true }); }
});

// Fresh evaluation keeps Svelte's cached native DOM getters local to each Window.
function compiledBundle(): Promise<Buffer> { return bundlePromise ??= buildBundle(); }
async function buildBundle(): Promise<Buffer> {
  const directory = buildDirectory = mkdtempSync(join(root, 'node_modules', '.google-settings-build-'));
  bundleBuildCount++;
  writeFileSync(join(directory, 'entry.ts'), `export {mount, unmount, flushSync} from 'svelte'; export {default as Component} from ${JSON.stringify(join(root, 'src/lib/settings/GoogleMapsIntegration.svelte'))};`);
  const build = await Bun.build({ entrypoints: [join(directory, 'entry.ts')], outdir: directory, naming: 'runtime.js', target: 'browser', conditions: ['browser'], plugins: [{ name: 'production-svelte', setup(builder) {
    builder.onResolve({ filter: /^svelte$/ }, () => ({ path: join(root, 'node_modules/svelte/src/index-client.js') }));
    builder.onResolve({ filter: /^\$lib\// }, ({ path }) => {
      const base = join(root, 'src/lib', path.slice(5));
      const candidates = [base, base.replace(/\.js$/, '.ts'), base + '.ts', join(base, 'index.ts')];
      return { path: candidates.find(candidate => existsSync(candidate) && statSync(candidate).isFile())! };
    });
    builder.onResolve({ filter: /\.js$/ }, ({ path, importer }) => {
      if (!path.startsWith('.')) return;
      const candidate = resolve(importer, '..', path.replace(/\.js$/, '.ts'));
      if (existsSync(candidate)) return { path: candidate };
    });
    builder.onLoad({ filter: /\.svelte\.[jt]s$/ }, ({ path }) => ({ contents: compileModule(readFileSync(path, 'utf8'), { filename: path, generate: 'client' }).js.code, loader: 'js' }));
    builder.onLoad({ filter: /\.svelte$/ }, ({ path }) => ({ contents: compile(readFileSync(path, 'utf8'), { filename: path, generate: 'client' }).js.code, loader: 'js' }));
  } }] });
  if (!build.success) throw new Error(build.logs.join('\n'));
  return readFileSync(join(directory, 'runtime.js'));
}

// Compile the actual production component and its actual Button dependency; only
// fetch is a mocked WordPress boundary. No source-string assertions or substitute UI.
async function fixture(response: (body: URLSearchParams) => unknown, options: Partial<GoogleMapsBootstrap> = {}) {
  const bundle = await compiledBundle();
  const window = new happy.Window({ url: 'https://fixture.test', settings: { disableIframePageLoading: true } });
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
    writeFileSync(join(directory, 'runtime.js'), bundle);
    runtime = await import(join(directory, 'runtime.js'));
    const target = window.document.createElement('main'); window.document.body.append(target);
    component = runtime.mount(runtime.Component, { target, props: { bootstrap: { ...bootstrap, ...options } } });
    const settle = async () => { for (let i = 0; i < 12; i++) { await Promise.resolve(); runtime.flushSync(); } };
    await settle();
    const input = target.querySelector<HTMLInputElement>('input')!;
    const submit = async () => { input.value = raw; input.dispatchEvent(new window.Event('input', { bubbles: true })); runtime.flushSync(); const event = new window.Event('submit', { bubbles: true, cancelable: true }); target.querySelector('form')!.dispatchEvent(event); expect(event.defaultPrevented).toBe(true); await settle(); };
    const frame = () => target.querySelector<HTMLIFrameElement>('[data-kiriof-google-maps-probe]');
    const check = () => target.querySelector('[data-kiriof-google-maps-check]')!;
    const button = (label: string) => Array.from(target.querySelectorAll<HTMLButtonElement>('button')).find(node => node.textContent?.includes(label))!;
    const message = async (data: unknown, source = frame()!.contentWindow, origin = window.location.origin) => {
      window.dispatchEvent(new window.MessageEvent('message', { data, source, origin })); await settle();
    };
    const result = (code = 'tiles_loaded', status = 'ready', selected = frame()!) => ({ type: 'kiriof-google-maps-check', channel: new URL(selected.src).searchParams.get('channel'), status, code });
    return { target, input, requests, submit, settle, window, cleanup, frame, check, button, message, result };
  } catch (error) { await cleanup(); throw error; }
}

test('production DOM saves with dedicated nested nonce, renders only mask, clears replacement, and removes without a key', async () => {
  const h = await fixture(body => ({ success: true, data: { status: 200, data: body.get('data[mode]') === 'remove' ? { configured: false, maskedKey: '' } : { configured: true, maskedKey: mask(raw) } } }));
  try {
    expect(h.target.querySelector('[role="heading"]')?.textContent).toBe('Google Maps');
    expect(h.target.querySelector('code')?.textContent).toBe(bootstrap.maskedKey);
    expect(h.input.type).toBe('password'); expect(h.input.value).toBe(''); expect(h.input.getAttribute('value')).toBeNull();
    const hint = h.target.querySelector<HTMLElement>('#kiriof-google-maps-key-hint')!;
    expect(h.input.getAttribute('aria-describedby')).toBe(hint.id);
    expect(hint.hidden).toBe(true);
    expect(hint.textContent).toBe(`${bootstrap.i18n.placeholder} ${bootstrap.i18n.keyHint}`);
    await h.submit();
    expect(Object.fromEntries(h.requests[0]!)).toEqual({ action: 'kiriof_save_google_maps_settings', 'data[mode]': 'save', 'data[key]': raw, 'data[nonce]': bootstrap.nonce });
    expect(h.target.querySelector('code')?.textContent).toBe(mask(raw)); expect(h.target.innerHTML).not.toContain(raw); expect(h.input.value).toBe('');
    h.button(bootstrap.i18n.remove).click(); await h.settle();
    expect(Object.fromEntries(h.requests[1]!)).toEqual({ action: 'kiriof_save_google_maps_settings', 'data[mode]': 'remove', 'data[nonce]': bootstrap.nonce });
    expect(h.target.querySelector('code')).toBeNull(); expect(h.target.textContent).toContain('Not configured'); expect(h.input.value).toBe('');
  } finally { await h.cleanup(); }
});

test('production DOM never echoes a credential-bearing transport error or malformed raw-key summary', async () => {
  let attempt = 0;
  const h = await fixture(() => ++attempt === 1 ? { success: false, data: { message: `Rejected ${raw}` } } : { success: true, data: { status: 200, data: { configured: true, maskedKey: raw } } });
  try {
    await h.submit(); expect(h.target.querySelector('#kiriof-google-maps-save-message')?.textContent).toContain(bootstrap.i18n.failed); expect(h.target.innerHTML).not.toContain(raw); expect(h.input.value).toBe('');
    await h.submit(); expect(h.target.querySelector('code')?.textContent).toBe(''); expect(h.target.innerHTML).not.toContain(raw); expect(h.input.value).toBe('');
  } finally { await h.cleanup(); }
});

test('fresh settings fixtures own their DOM and requests independently', async () => {
  const first = await fixture(() => ({ success: true, data: { status: 200, data: { configured: true, maskedKey: mask(raw) } } }));
  try {
    expect(first.input.ownerDocument).toBe(first.window.document);
    await first.submit(); expect(first.requests).toHaveLength(1);
  } finally { await first.cleanup(); }
  const second = await fixture(() => ({ success: false, data: { message: 'Rejected' } }));
  try {
    expect(second.window.document).not.toBe(first.window.document);
    expect(second.input.ownerDocument).toBe(second.window.document);
    expect(second.target.querySelector('button')?.ownerDocument).toBe(second.window.document);
    expect(second.requests).not.toBe(first.requests); expect(second.requests).toHaveLength(0);
    expect(second.target.querySelector('code')?.textContent).toBe(bootstrap.maskedKey);
    await second.submit(); expect(second.requests).toHaveLength(1); expect(first.requests).toHaveLength(1);
    expect(second.target.querySelector('#kiriof-google-maps-save-message')?.textContent).toContain(bootstrap.i18n.failed);
  } finally { await second.cleanup(); }
});

test('settings bundle compiles at most once per suite', () => {
  expect(bundleBuildCount).toBe(bundlePromise ? 1 : 0);
});

const savedResponse = (body: URLSearchParams) => ({ success: true, data: { status: 200, data: body.get('data[mode]') === 'remove' ? { configured: false, maskedKey: '' } : { configured: true, maskedKey: mask(raw) } } });

test('compact card collapses help and map without remounting the automatic probe', async () => {
  const h = await fixture(savedResponse);
  try {
    const frame = h.frame()!, channel = h.result().channel;
    const details = h.target.querySelector<HTMLElement>('#kiriof-google-maps-details')!;
    const toggle = h.button(bootstrap.i18n.details);
    expect(toggle.getAttribute('aria-controls')).toBe(details.id);
    expect(toggle.getAttribute('aria-expanded')).toBe('false');
    expect(details.getAttribute('aria-hidden')).toBe('true');
    expect(details.hasAttribute('inert')).toBe(true);
    expect(frame.closest('[inert]')).toBe(details);
    await h.message(h.result()); expect(h.check().textContent).toContain(bootstrap.i18n.browserReady);
    toggle.click(); await h.settle();
    expect(toggle.getAttribute('aria-expanded')).toBe('true');
    expect(details.getAttribute('aria-hidden')).toBe('false');
    expect(details.hasAttribute('inert')).toBe(false);
    expect(frame.getAttribute('tabindex')).toBeNull();
    expect(h.frame()).toBe(frame); expect(h.result().channel).toBe(channel);
    toggle.click(); await h.settle();
    expect(details.hasAttribute('inert')).toBe(true);
    expect(h.frame()).toBe(frame);
    await h.message(h.result('auth_failed', 'error'));
    expect(h.check().getAttribute('role')).toBe('alert');
    expect(h.check().textContent).toContain(bootstrap.i18n.browserFailed);
    await h.submit();
    expect(h.target.querySelector('#kiriof-google-maps-save-message')?.getAttribute('data-slot')).toBeNull();
    expect(h.target.querySelector('[data-slot="card-footer"]')).toBeNull();
  } finally { await h.cleanup(); }
});

test('configured settings automatically mount exactly one credential-free, same-origin browser probe', async () => {
  const h = await fixture(savedResponse);
  try {
    expect(h.target.querySelectorAll('iframe')).toHaveLength(1);
    const frame = h.frame()!, url = new URL(frame.src);
    expect(url.origin).toBe(h.window.location.origin);
    expect(url.pathname).toBe('/');
    expect(url.searchParams.get('kiriof_google_maps_browser_check')).toBe('1');
    expect(url.searchParams.get('channel')).toMatch(/^[a-f0-9]{32}$/);
    expect(url.searchParams.get('_wpnonce')).toBe('probe-nonce');
    expect(frame.title).toBe(bootstrap.i18n.previewTitle);
    expect(frame.getAttribute('referrerpolicy')).toBe('strict-origin-when-cross-origin');
    expect(frame.src).not.toContain(raw); expect(frame.src).not.toContain('key=');
    expect(h.target.innerHTML).not.toContain(raw);
    expect(h.check().getAttribute('role')).toBe('status');
    expect(h.check().textContent).toContain(bootstrap.i18n.checking);
    expect(h.requests).toHaveLength(0);
    await h.message(h.result());
    expect(h.check().textContent).toContain(bootstrap.i18n.browserReady);
    expect(h.check().getAttribute('aria-busy')).toBe('false');
    await h.message(h.result('auth_failed', 'error'));
    expect(h.check().getAttribute('role')).toBe('alert');
    expect(h.check().textContent).toContain(bootstrap.i18n.browserFailed);
  } finally { await h.cleanup(); }
});

test('browser probe ignores forged channels, origins, sources, payload additions and invalid result combinations', async () => {
  const h = await fixture(savedResponse);
  try {
    const valid = h.result();
    const forgeries = [null, 'ready', { ...valid, channel: 'f'.repeat(32) }, { ...valid, credential: raw }, { ...valid, code: 'auth_failed' }, { ...valid, status: 'error' }, { ...valid, type: 'other' }, { ...valid, code: raw }];
    for (const data of forgeries) { await h.message(data); expect(h.check().textContent).toContain(bootstrap.i18n.checking); }
    await h.message(valid, h.frame()!.contentWindow, 'https://forged.test');
    expect(h.check().textContent).toContain(bootstrap.i18n.checking);
    await h.message(valid, h.window);
    expect(h.check().textContent).toContain(bootstrap.i18n.checking);
    expect(h.target.innerHTML).not.toContain(raw);
    await h.message(valid);
    expect(h.check().textContent).toContain(bootstrap.i18n.browserReady);
  } finally { await h.cleanup(); }
});

test('check again while checking replaces the frame and invalidates stale messages', async () => {
  const h = await fixture(savedResponse);
  try {
    const old = h.frame()!, oldSource = old.contentWindow, oldResult = h.result();
    expect(h.button(bootstrap.i18n.checkAgain).disabled).toBe(false);
    h.button(bootstrap.i18n.checkAgain).click(); await h.settle();
    expect(old.isConnected).toBe(false); expect(h.frame()).not.toBe(old);
    expect(h.target.querySelectorAll('iframe')).toHaveLength(1);
    expect(h.result().channel).not.toBe(oldResult.channel);
    await h.message(oldResult, oldSource);
    expect(h.check().textContent).toContain(bootstrap.i18n.checking);
    await h.message(oldResult);
    expect(h.check().textContent).toContain(bootstrap.i18n.checking);
    await h.message(h.result()); expect(h.check().textContent).toContain(bootstrap.i18n.browserReady);
  } finally { await h.cleanup(); }
});

test('browser result error codes map only to localized messages', async () => {
  const h = await fixture(savedResponse);
  try {
    for (const [code, text] of [['network_error', bootstrap.i18n.browserNetwork], ['timeout', bootstrap.i18n.browserTimeout], ['not_configured', bootstrap.i18n.browserNotConfigured], ['render_failed', bootstrap.i18n.browserFailed], ['auth_failed', bootstrap.i18n.browserFailed]]) {
      await h.message(h.result(code, 'error'));
      expect(h.check().getAttribute('role')).toBe('alert'); expect(h.check().textContent).toContain(text!);
    }
  } finally { await h.cleanup(); }
});

test('saving starts a fresh probe, removing cancels it, and stale results cannot revive either', async () => {
  const h = await fixture(savedResponse);
  try {
    const before = h.frame()!, oldSource = before.contentWindow, oldResult = h.result();
    await h.submit();
    expect(before.isConnected).toBe(false); expect(h.frame()).not.toBe(before);
    expect(h.result().channel).not.toBe(oldResult.channel);
    await h.message(oldResult, oldSource); expect(h.check().textContent).toContain(bootstrap.i18n.checking);
    const removedSource = h.frame()!.contentWindow, removedResult = h.result();
    h.button(bootstrap.i18n.remove).click(); await h.settle();
    expect(h.frame()).toBeNull(); expect(h.button(bootstrap.i18n.checkAgain).disabled).toBe(true);
    await h.message(removedResult, removedSource);
    expect(h.frame()).toBeNull(); expect(h.check().textContent).toContain(bootstrap.i18n.browserNotConfigured);
    expect(h.requests).toHaveLength(2);
  } finally { await h.cleanup(); }
});

test('unconfigured settings do not mount a probe until a key is saved', async () => {
  const h = await fixture(savedResponse, { configured: false, maskedKey: '' });
  try {
    expect(h.frame()).toBeNull(); expect(h.check().textContent).toContain(bootstrap.i18n.browserNotConfigured);
    expect(h.button(bootstrap.i18n.checkAgain).disabled).toBe(true);
    expect(h.requests).toHaveLength(0);
    await h.submit(); expect(h.target.querySelectorAll('iframe')).toHaveLength(1);
  } finally { await h.cleanup(); }
});

test('invalid configured preview URLs never mount an iframe', async () => {
  for (const browserCheckUrl of ['ftp://store.test/?kiriof_google_maps_browser_check=1', 'javascript:alert(1)', '/?action=other', 'https://user:password@store.test/?kiriof_google_maps_browser_check=1', 'http://store.test/?kiriof_google_maps_browser_check=1']) {
    const h = await fixture(savedResponse, { browserCheckUrl });
    try { expect(h.frame()).toBeNull(); expect(h.check().textContent).toContain(bootstrap.i18n.browserNetwork); }
    finally { await h.cleanup(); }
  }
});

test('configured storefront host and subdirectory are preserved instead of the current admin host', async () => {
  const h = await fixture(savedResponse, { browserCheckUrl: 'https://store.test:9443/shop/?kiriof_google_maps_browser_check=1&_wpnonce=probe-nonce' });
  try {
    const frame = h.frame()!, url = new URL(frame.src);
    expect(url.origin).toBe('https://store.test:9443');
    expect(url.pathname).toBe('/shop/');
    expect(h.target.textContent).toContain('https://store.test:9443');
    await h.message(h.result());
    expect(h.check().textContent).toContain(bootstrap.i18n.checking);
    await h.message(h.result(), frame.contentWindow, url.origin);
    expect(h.check().textContent).toContain(bootstrap.i18n.browserReady);
  } finally { await h.cleanup(); }
});
