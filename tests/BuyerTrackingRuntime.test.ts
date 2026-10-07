import { expect, test } from 'bun:test';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { runInNewContext } from 'node:vm';
import { compile, compileModule } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';
import { buyerBrowserContext } from './helpers/buyer-runtime-source';

const root = resolve(import.meta.dir, '..');
let bundle: Promise<string> | undefined;
function source() {
  return (bundle ||= (async () => {
    const directory = mkdtempSync(join(root, 'node_modules/.tracking-runtime-'));
    try {
      const entry = join(directory, 'entry.ts');
      writeFileSync(
        entry,
        `import '${join(root, 'src/buyer/entries/tracking.ts')}'; import {bootTracking} from '${join(root, 'src/buyer/tracking/bridge.ts')}'; import {flushSync} from 'svelte'; window.__bootTracking = bootTracking; window.__flush = flushSync;`,
      );
      const result = await Bun.build({
        entrypoints: [entry],
        target: 'browser',
        conditions: ['browser'],
        format: 'iife',
        plugins: [
          {
            name: 'tracking-svelte',
            setup(builder) {
              builder.onResolve({ filter: /^svelte$/ }, () => ({
                path: join(root, 'node_modules/svelte/src/index-client.js'),
              }));
              builder.onLoad({ filter: /\.svelte\.ts$/ }, ({ path }) => ({
                contents: compileModule(
                  new Bun.Transpiler({ loader: 'ts' }).transformSync(readFileSync(path, 'utf8')),
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
      return await result.outputs[0].text();
    } finally {
      rmSync(directory, { recursive: true, force: true });
    }
  })());
}
async function fixture(query = '', routeHelper = true) {
  const window = new happy.Window({ url: `https://fixture.test/tracking${query}` });
  window.document.body.innerHTML = `<form class="checkout"><input name="order_number"><button type="button" class="track-btn">Track</button><div id="tracking-result"><div class="state-blank">Blank</div><div class="state-loading kj-hidden">Loading</div><div class="state-err kj-hidden"><span id="err_msg"></span></div><div class="state-success kj-hidden"><div class="tracking-details"></div><table class="tracking-table"><thead><tr><th>Date</th><th>Status</th></tr></thead><tbody><tr><td>Legacy sample</td></tr></tbody></table></div></div></form>`;
  const requests: {
    url: string;
    options: RequestInit;
    resolve: (value: unknown) => void;
    reject: (reason: unknown) => void;
  }[] = [];
  const w = window as any;
  w.kiriofAjax = { ajaxurl: '/fallback' };
  if (routeHelper) w.kiriofAjaxRoute = () => '/ajax';
  w.kiriofTracking = {
    i18n: { orderNumber: 'Order', awbNumber: 'AWB', courier: 'Courier', notFound: 'Missing order' },
  };
  w.fetch = (url: string, options: RequestInit) =>
    new Promise((resolve, reject) => requests.push({ url, options, resolve, reject }));
  runInNewContext(
    await source(),
    buyerBrowserContext(window, { URLSearchParams, AbortController }),
  );
  window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
  const settle = async () => {
    for (let i = 0; i < 6; i++) {
      await new Promise((resolve) => setTimeout(resolve, 1));
      w.__flush();
    }
  };
  const click = (value = 'ORDER 1&2') => {
    window.document.querySelector<HTMLInputElement>('input')!.value = value;
    window.document
      .querySelector('button')!
      .dispatchEvent(new window.MouseEvent('click', { bubbles: true, cancelable: true }));
  };
  const respond = (index: number, body: unknown, ok = true) =>
    requests[index]!.resolve({ ok, json: async () => body });
  const shown = (name: string) =>
    !window.document.querySelector(`.state-${name}`)!.classList.contains('kj-hidden');
  return {
    window,
    w,
    requests,
    settle,
    click,
    respond,
    shown,
    cleanup: async () => {
      w.__kiriofTrackingBridge?.dispose();
      await settle();
      window.happyDOM.abort();
    },
  };
}
const success = (number = 'A') => ({
  success: true,
  data: {
    status: 200,
    data: {
      number_order: number,
      details: {
        awb: 'AWB',
        service: 'JNE',
        destination: { name: 'Recipient', city: 'City', province: 'Province' },
      },
      histories: [{ created_at: 'Today', status: 'Delivered' }],
    },
  },
});

test('active Svelte tracking preserves POST, native state classes, button semantics and escapes all data', async () => {
  const h = await fixture();
  try {
    expect(h.shown('blank')).toBe(true);
    expect(h.requests).toHaveLength(0);
    h.click();
    expect(h.shown('loading')).toBe(true);
    expect(h.window.document.querySelector('button')!.classList.contains('kj-hidden')).toBe(true);
    const request = h.requests[0]!;
    expect(request.url).toBe('/ajax');
    expect(request.options.method).toBe('POST');
    expect(request.options.credentials).toBe('same-origin');
    const body = new URLSearchParams(String(request.options.body));
    expect(body.get('action')).toBe('kiriof-tracking-ajax');
    expect(body.get('order_number')).toBe('ORDER 1&2');
    const hostile = '<img src=x onerror=alert(1)>';
    const result = success(hostile);
    result.data.data.details.destination.name = hostile;
    result.data.data.histories[0]!.status = hostile;
    h.respond(0, result);
    await h.settle();
    expect(h.shown('success')).toBe(true);
    expect(h.shown('loading')).toBe(false);
    expect(h.window.document.querySelector('button')!.classList.contains('kj-hidden')).toBe(false);
    expect(h.window.document.querySelectorAll('img,script')).toHaveLength(0);
    expect(h.window.document.querySelector('.tracking-header')!.textContent).toContain(
      `Order : #${hostile}`,
    );
    expect(h.window.document.querySelector('.textprimary')!.textContent).toBe(hostile);
    expect(h.window.document.querySelector('tbody td:last-child')!.textContent).toBe(hostile);
    expect(h.window.document.querySelectorAll('.tracking-gorup')).toHaveLength(1);
    expect(h.window.document.querySelectorAll('tbody tr')).toHaveLength(1);
    expect(h.window.document.querySelectorAll('thead th')).toHaveLength(2);
  } finally {
    await h.cleanup();
  }
});

test('auto order_id lookup, idempotent boot, global compatibility, stale completion and disposal', async () => {
  const h = await fixture('?order_id=A%26B');
  try {
    expect(h.requests).toHaveLength(1);
    expect(new URLSearchParams(String(h.requests[0]!.options.body)).get('order_number')).toBe(
      'A&B',
    );
    const bridge = h.w.__kiriofTrackingBridge;
    expect(h.w.__bootTracking(h.window)).toBe(bridge);
    h.window.document.dispatchEvent(new h.window.Event('DOMContentLoaded'));
    expect(h.requests).toHaveLength(1);
    h.click('new');
    expect(h.requests).toHaveLength(2);
    expect(h.requests[0]!.options.signal!.aborted).toBe(true);
    h.respond(1, success('new'));
    await h.settle();
    h.respond(0, success('old'));
    await h.settle();
    expect(h.window.document.querySelector('.tracking-header')!.textContent).toContain('#new');
    void h.w.trackOrder();
    expect(h.requests).toHaveLength(3);
    bridge.dispose();
    expect(h.requests[2]!.options.signal!.aborted).toBe(true);
    h.respond(2, success('disposed'));
    await h.settle();
    h.click();
    expect(h.requests).toHaveLength(3);
    expect(h.w.trackOrder).toBeUndefined();
    expect(h.w.__kiriofTrackingBridge).toBeUndefined();
  } finally {
    await h.cleanup();
  }
});

test('application, malformed, HTTP and network errors use safe messages and recover', async () => {
  const h = await fixture('', false);
  try {
    h.click();
    expect(h.requests[0]!.url).toBe('/fallback');
    h.respond(0, { success: true, data: { status: 404, message: '<script>bad()</script>' } });
    await h.settle();
    expect(h.shown('err')).toBe(true);
    expect(h.window.document.querySelector('#err_msg')!.textContent).toBe('<script>bad()</script>');
    expect(h.window.document.querySelector('script')).toBeNull();
    h.click();
    h.respond(1, { success: false });
    await h.settle();
    expect(h.window.document.querySelector('#err_msg')!.textContent).toBe('Missing order');
    h.click();
    h.respond(2, success(), false);
    await h.settle();
    expect(h.shown('err')).toBe(true);
    h.click();
    h.requests[3]!.reject(new Error('offline'));
    await h.settle();
    expect(h.shown('err')).toBe(true);
    expect(h.window.document.querySelector('button')!.classList.contains('kj-hidden')).toBe(false);
    h.click();
    h.respond(4, {
      success: true,
      data: { status: 200, data: { details: null, histories: { unsafe: true } } },
    });
    await h.settle();
    expect(h.shown('success')).toBe(true);
    expect(h.window.document.querySelectorAll('tbody tr')).toHaveLength(0);
    expect(h.window.document.querySelector('.textprimary')!.textContent).toBe('-');
  } finally {
    await h.cleanup();
  }
});
