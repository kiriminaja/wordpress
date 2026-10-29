import { afterAll, describe, expect, test } from 'bun:test';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
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
  directory = mkdtempSync(join(root, 'node_modules', '.buyer-combobox-build-'));
  writeFileSync(
    join(directory, 'entry.ts'),
    `import {startClassicSelectors} from '${join(root, 'src/buyer/classic/selector.ts')}'; import {flushSync} from 'svelte'; export {startClassicSelectors, flushSync};`,
  );
  const build = await Bun.build({
    entrypoints: [join(directory, 'entry.ts')],
    outdir: directory,
    naming: 'runtime.js',
    target: 'browser',
    conditions: ['browser'],
    plugins: [
      {
        name: 'buyer-combobox-svelte',
        setup(builder) {
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
async function fixture(
  html: string,
  config: Record<string, unknown> = {},
  setup?: (window: InstanceType<typeof happy.Window>) => void,
) {
  const temporary = mkdtempSync(join(root, 'node_modules', '.buyer-combobox-runtime-'));
  const window = new happy.Window({ url: 'https://fixture.test' });
  window.document.body.innerHTML = `<form class="checkout">${html}</form>`;
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
  (window as any).kiriofBillingAddressConfig = config;
  setup?.(window);
  async function settle() {
    for (let i = 0; i < 20; i++) {
      await Promise.resolve();
      runtime.flushSync();
    }
    await new Promise((resolve) => setTimeout(resolve, 5));
    runtime.flushSync();
  }
  async function cleanup() {
    window.dispatchEvent(new window.Event('pagehide'));
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
    const bridge = runtime.startClassicSelectors(window);
    window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
    await settle();
    const trigger = () =>
      window.document.querySelector(
        '.kiriof-buyer-combobox-trigger',
      ) as unknown as HTMLButtonElement;
    async function open() {
      trigger().dispatchEvent(new window.PointerEvent('pointerdown', { bubbles: true }));
      await settle();
    }
    async function choose(value: string) {
      const option =
        window.document.querySelector(`[data-combobox-item][data-value="${value}"]`) ||
        [...window.document.querySelectorAll('[data-combobox-item]')].find((node) =>
          node.textContent?.includes(value),
        );
      if (!option) throw new Error(`Missing option ${value}: ${window.document.body.innerHTML}`);
      option.dispatchEvent(new window.MouseEvent('pointermove', { bubbles: true }));
      option.dispatchEvent(
        new window.PointerEvent('pointerup', { bubbles: true, pointerType: 'mouse' }),
      );
      await settle();
    }
    return { window, bridge, changes, settle, cleanup, trigger, open, choose };
  } catch (error) {
    if (runtime) await cleanup();
    throw error;
  }
}

const district =
  '<label for="district">Subdistrict</label><select id="district" name="kiriof_destination_area" required aria-describedby="help"><option value="">Select Option</option><option value="101" selected>Old district</option></select><span id="help">Search your subdistrict</span>';

describe('Classic buyer selectors (compiled actual bits-ui)', () => {
  test('FunnelKit column classes and field placement stay builder-owned', async () => {
    const h = await fixture('<input name="_wfacp_post_id" value="123" type="hidden"><div class="wfacp-row"><p id="shipping_postcode_field" class="form-row wfacp-form-control-wrapper wfacp-col-left-half"><input name="shipping_postcode" value="55222"></p><p id="shipping_state_field" class="form-row wfacp-form-control-wrapper wfacp-col-right-half"><label for="shipping_state">Province</label><select id="shipping_state"><option value="YO">DI Yogyakarta</option></select></p></div>');
    try {
      const form = h.window.document.querySelector('form')!;
      expect(form.classList.contains('kiriof-funnelkit-checkout')).toBe(true);
      expect(h.window.document.querySelector('#shipping_state_field')!.classList.contains('wfacp-col-right-half')).toBe(true);
      expect(h.window.document.querySelector('#shipping_postcode_field')!.classList.contains('wfacp-col-left-half')).toBe(true);
      expect(h.window.document.querySelector('.wfacp-row')!.children).toHaveLength(2);
      const css = readFileSync(join(root, 'assets/buyer/css/kiriof-classic-checkout.css'), 'utf8');
      for (const selector of css.matchAll(/([^{}]+)\{([^{}]+)\}/g)) {
        if (/#[a-z]+_(?:city|state|postcode|phone)_field/.test(selector[1]) && /(?:width|grid-column)\s*:/.test(selector[2]))
          expect(selector[1]).toContain(':not(.kiriof-funnelkit-checkout)');
      }
    } finally { await h.cleanup(); }
  });
  test('FunnelKit fields own a scoped style hook without styling unrelated buttons', async () => {
    const h = await fixture(
      '<div class="wfacp-form-control-wrapper"><label for="billing_state">State</label><select id="billing_state"><option value="YO" selected>Daerah Istimewa Yogyakarta</option></select></div><button type="submit" id="place_order">Place order</button>',
    );
    try {
      const row = h.window.document.querySelector('.wfacp-form-control-wrapper')!;
      expect(row.classList.contains('kiriof-funnelkit-select-field')).toBe(true);
      expect(h.window.document.querySelector('#place_order')!.closest('.kiriof-funnelkit-select-field')).toBeNull();
      expect(h.trigger().getAttribute('aria-labelledby')).toBe('billing_state-buyer-selector-label');
      const css = readFileSync(join(root, 'src/buyer/styles/selectors.css'), 'utf8');
      expect(css).toContain('.kiriof-funnelkit-select-field label[for]');
      expect(css).toContain('background: #fff !important');
      expect(css).toContain('outline: 2px solid #333 !important');
      expect(css).toContain('min-height: 44px !important');
    } finally {
      await h.cleanup();
    }
  });
  test('native authority, accessible labels, one explicit selection change and no refresh writes', async () => {
    const h = await fixture(
      '<label for="billing_state">Province</label><select id="billing_state" required><option value="">Select</option><option value="ID-JB" selected>West Java</option><option value="ID-JT">Central Java</option></select>',
    );
    try {
      const native = h.window.document.querySelector('select')!;
      expect(native.classList.contains('kiriof-buyer-native-select')).toBe(true);
      expect(h.trigger().textContent).toContain('West Java');
      expect(h.trigger().getAttribute('aria-labelledby')).toBe(
        'billing_state-buyer-selector-label',
      );
      expect(h.trigger().getAttribute('aria-required')).toBe('true');
      expect(h.changes).toHaveLength(0);
      await h.open();
      await h.choose('ID-JT');
      expect(native.value).toBe('ID-JT');
      expect(h.changes).toHaveLength(1);
      expect((h.changes[0] as CustomEvent).detail.value).toBe('ID-JT');
      native.value = '';
      h.bridge.refresh();
      await h.settle();
      expect(h.trigger().textContent).toContain('Select');
      expect(h.changes).toHaveLength(1);
    } finally {
      await h.cleanup();
    }
  });

  test('courier metadata survives native option refresh; logos and prices render as server strings', async () => {
    const h = await fixture(
      '<select class="kiriof-classic-shipping-method-select" id="rate"><option value="jne:REG" selected data-courier="jne" data-label="JNE REG" data-price="Rp10.000" data-original-price="Rp12.000" data-savings="Save Rp2.000" data-note="2–3 days">JNE REG (Rp10.000)</option><option value="third-party">Unknown courier</option></select>',
      { courierLogos: { jne: '/assets/buyer/img/couriers/jne.png' } },
    );
    try {
      const native = h.window.document.querySelector('select')!;
      expect(h.trigger().querySelector('img')?.getAttribute('src')).toBe(
        'https://fixture.test/assets/buyer/img/couriers/jne.png',
      );
      expect(h.trigger().querySelector('del')?.textContent).toBe('Rp12.000');
      native.innerHTML =
        '<option value="jne:REG" selected>Changed native label</option><option value="third-party">Unknown courier</option>';
      h.bridge.refresh();
      await h.settle();
      expect(native.options[0].getAttribute('data-price')).toBe('Rp10.000');
      expect(h.trigger().textContent).toContain('JNE REG');
      await h.open();
      await h.choose('third-party');
      expect(h.trigger().querySelector('img')).toBeNull();
      expect(h.trigger().querySelector('svg')).toBeNull();
      expect(h.changes).toHaveLength(1);
      expect((h.changes[0] as CustomEvent).detail.reviewedShipping).toBe(true);
    } finally {
      await h.cleanup();
    }
  });

  test('Woo replacing native select inside wrapper preserves replacement without resurrecting old node', async () => {
    const h = await fixture(
      '<select id="billing_state"><option value="JB" selected>Java</option></select>',
    );
    try {
      const old = h.window.document.querySelector('select')!;
      const replacement = h.window.document.createElement('input');
      replacement.id = 'billing_state';
      replacement.value = 'Tokyo';
      old.replaceWith(replacement);
      h.bridge.refresh();
      await h.settle();
      expect(h.window.document.getElementById('billing_state')).toBe(replacement);
      expect(old.isConnected).toBe(false);
      expect(h.window.document.querySelector('.kiriof-buyer-selector')).toBeNull();
      expect(h.changes).toHaveLength(0);
    } finally {
      await h.cleanup();
    }
  });

  test('unselected district refresh stays native-owned; replacement change before mount wins', async () => {
    const h = await fixture(district);
    try {
      const native = h.window.document.querySelector('select')!;
      native.innerHTML =
        '<option value="999" selected>Server default</option><option value="202">Native choice</option>';
      h.bridge.refresh();
      await h.settle();
      expect(native.value).toBe('999');
      expect(h.changes).toHaveLength(0);
      await h.open();
      await h.choose('202');
      const replacement = native.cloneNode(false) as typeof native;
      replacement.innerHTML = '<option value="303" selected>New native choice</option>';
      native.replaceWith(replacement);
      replacement.dispatchEvent(new h.window.Event('change', { bubbles: true }));
      h.bridge.refresh();
      await h.settle();
      expect(replacement.value).toBe('303');
      expect(h.trigger().textContent).toContain('New native choice');
      expect(h.changes).toHaveLength(2);
    } finally {
      await h.cleanup();
    }
  });

  for (const type of ['billing', 'shipping']) {
    const name =
      type === 'shipping' ? 'kiriof_shipping_destination_area' : 'kiriof_destination_area';
    const context = `<input id="${type}_country" value="ID"><input id="${type}_state" value="JB"><input id="${type}_postcode" value="12345">`;
    const field = `<label for="district">Subdistrict</label><select id="district" name="${name}"><option value="">Select Option</option><option value="101" selected>Old district</option><option value="202">Chosen district</option></select><input type="hidden" name="${name}_name" value="Old district">`;

    test(`${type} explicit district and canonical label survive select/options replacement without writes`, async () => {
      const h = await fixture(context + field);
      try {
        await h.open();
        await h.choose('202');
        const hidden = h.window.document.querySelector<HTMLInputElement>(`[name="${name}_name"]`)!;
        const labelReplacement = hidden.cloneNode() as typeof hidden;
        labelReplacement.value = 'Stale hidden label';
        hidden.replaceWith(labelReplacement);
        h.bridge.refresh();
        await h.settle();
        expect(labelReplacement.value).toBe('Chosen district');
        const old = h.window.document.querySelector('select')!;
        const replacement = old.cloneNode(false) as typeof old;
        replacement.innerHTML = '<option value="999" selected>Replacement default</option>';
        old.replaceWith(replacement);
        h.window.document.body.dispatchEvent(new h.window.Event('updated_checkout'));
        h.bridge.refresh();
        await h.settle();
        expect(old.isConnected).toBe(false);
        expect(replacement.value).toBe('202');
        expect(h.trigger().textContent).toContain('Chosen district');
        expect((h.window.document.querySelector(`[name="${name}_name"]`) as any).value).toBe(
          'Chosen district',
        );
        replacement.innerHTML = '<option value="999" selected>Replacement default</option>';
        h.bridge.refresh();
        await h.settle();
        expect(replacement.value).toBe('202');
        replacement.innerHTML =
          '<option value="202" selected>Theme placeholder</option><option value="999">Replacement default</option>';
        h.bridge.refresh();
        await h.settle();
        expect(h.trigger().textContent).toContain('Chosen district');
        expect((h.window.document.querySelector(`[name="${name}_name"]`) as any).value).toBe(
          'Chosen district',
        );
        expect(h.changes).toHaveLength(1);
        // A later native change is authoritative, including when it precedes refresh.
        replacement.value = '999';
        replacement.dispatchEvent(new h.window.Event('change', { bubbles: true }));
        h.bridge.refresh();
        await h.settle();
        expect(replacement.value).toBe('999');
        expect(h.trigger().textContent).toContain('Replacement default');
        expect(h.changes).toHaveLength(2);
      } finally {
        await h.cleanup();
      }
    });

    test(`${type} explicit clear survives replacement defaults`, async () => {
      const h = await fixture(context + field);
      try {
        (h.window.document.querySelector('.kiriof-buyer-combobox-clear') as any).click();
        await h.settle();
        const old = h.window.document.querySelector('select')!;
        const replacement = old.cloneNode(false) as typeof old;
        replacement.innerHTML = '<option value="999" selected>Replacement default</option>';
        old.replaceWith(replacement);
        h.bridge.refresh();
        await h.settle();
        expect(replacement.value).toBe('');
        expect((h.window.document.querySelector(`[name="${name}_name"]`) as any).value).toBe('');
        expect(h.trigger().textContent).not.toContain('Replacement default');
        expect(h.changes).toHaveLength(1);
      } finally {
        await h.cleanup();
      }
    });

    for (const part of ['country', 'state', 'postcode']) {
      test(`${type} ${part} context invalidates explicit choice and pending lookup`, async () => {
        const h = await fixture(context + field, { ajaxUrl: '/ajax', nonce: 'nonce' });
        try {
          await h.open();
          await h.choose('202');
          let respond!: (value: unknown) => void;
          let signal!: AbortSignal;
          h.window.fetch = ((_url: unknown, init: any) => {
            signal = init.signal;
            return new Promise((resolve) => {
              respond = resolve;
            });
          }) as any;
          await h.open();
          const input = h.window.document.querySelector('[data-combobox-input]') as any;
          input.value = 'Search';
          input.dispatchEvent(new h.window.Event('input', { bubbles: true }));
          await new Promise((resolve) => setTimeout(resolve, 270));
          const address = h.window.document.getElementById(`${type}_${part}`) as any;
          address.value = 'NEW';
          address.dispatchEvent(
            new h.window.Event(part === 'postcode' ? 'input' : 'change', { bubbles: true }),
          );
          expect(signal.aborted).toBe(true);
          respond({
            ok: true,
            json: async () => ({ results: [{ id: 303, text: 'Stale district' }] }),
          });
          await h.settle();
          expect(h.window.document.body.textContent).not.toContain('Stale district');
          const native = h.window.document.querySelector('select')!;
          native.innerHTML = '<option value="999" selected>New context default</option>';
          h.bridge.refresh();
          await h.settle();
          expect(native.value).toBe('999');
          expect(h.changes).toHaveLength(1 + (part === 'postcode' ? 0 : 1));
        } finally {
          await h.cleanup();
        }
      });
    }
  }

  test('read lookup has loading/error/retry and stale term cancellation; clear emits exactly once', async () => {
    const h = await fixture(district, { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'nonce' });
    try {
      const requests: {
        signal: AbortSignal;
        term: string;
        resolve: (value: unknown) => void;
        reject: (error: Error) => void;
      }[] = [];
      h.window.fetch = ((_url: unknown, init: any) =>
        new Promise((resolve, reject) =>
          requests.push({ signal: init.signal, term: init.body.get('term'), resolve, reject }),
        )) as any;
      await h.open();
      const input = h.window.document.querySelector('[data-combobox-input]')! as any;
      const type = async (value: string) => {
        input.value = value;
        input.dispatchEvent(new h.window.Event('input', { bubbles: true }));
        await h.settle();
        await new Promise((resolve) => setTimeout(resolve, 270));
        await h.settle();
      };
      await type('Old');
      expect(requests).toHaveLength(1);
      expect(h.window.document.querySelector('[aria-busy="true"]')).not.toBeNull();
      await type('New');
      expect(requests[0].signal.aborted).toBe(true);
      requests[0].resolve({
        ok: true,
        json: async () => ({ results: [{ id: 202, text: 'Stale district' }] }),
      });
      await h.settle();
      expect(h.window.document.body.textContent).not.toContain('Stale district');
      requests[1].reject(new Error('network'));
      await h.settle();
      const retry = h.window.document.querySelector('.kiriof-buyer-combobox-retry') as any;
      expect(retry).not.toBeNull();
      retry.click();
      await new Promise((resolve) => setTimeout(resolve, 270));
      requests[2].resolve({
        ok: true,
        json: async () => ({ term: 'New', results: [{ id: 303, text: 'New district' }] }),
      });
      await h.settle();
      expect(h.window.document.querySelector('select')!.value).toBe('101');
      expect(h.changes).toHaveLength(0);
      await h.choose('303');
      expect(h.window.document.querySelector('select')!.value).toBe('303');
      expect(h.changes).toHaveLength(1);
      (h.window.document.querySelector('.kiriof-buyer-combobox-clear') as any).click();
      await h.settle();
      expect(h.window.document.querySelector('select')!.value).toBe('');
      expect(h.changes).toHaveLength(2);
    } finally {
      await h.cleanup();
    }
  });
});

const postalAddress = (type = 'billing', postcode = '12345', value = '') =>
  `<input id="${type}_country" value="ID"><input id="${type}_state" value="JB"><input id="${type}_postcode" value="${postcode}"><select id="${type}_district" name="${type === 'shipping' ? 'kiriof_shipping_destination_area' : 'kiriof_destination_area'}"><option value="">Select Option</option>${value ? `<option value="${value}" selected>Existing</option>` : ''}</select>`;
function postalRequests(window: InstanceType<typeof happy.Window>) {
  const requests: {
    term: string;
    signal: AbortSignal;
    resolve: (value: unknown) => void;
    reject: (error: Error) => void;
  }[] = [];
  window.fetch = ((_url: unknown, init: any) =>
    new Promise((resolve, reject) => {
      requests.push({ term: init.body.get('term'), signal: init.signal, resolve, reject });
    })) as any;
  return requests;
}
const postalRows = (rows: [string, string][]) => ({
  ok: true,
  json: async () => ({ results: rows.map(([id, text]) => ({ id, text })) }),
});

describe('Classic checkout postcode recovery (opt-in)', () => {
  for (const interaction of ['combobox', 'native', 'replacement']) {
    test(`explicit ${interaction} district choice updates native hierarchy before preserving its postcode context`, async () => {
      let requests!: ReturnType<typeof postalRequests>;
      const h = await fixture(
        '<input id="billing_country" name="billing_country" value="ID"><input id="billing_city" name="billing_city" value="Yogyakarta"><input id="billing_state" name="billing_state" value="YO"><input id="billing_postcode" name="billing_postcode" value="55222"><input name="kiriof_destination_area_name"><select id="billing_district" name="kiriof_destination_area"><option value="101" selected>Old village, Old district, Yogyakarta, DI Yogyakarta, 55222</option><option value="31554">Sari Harjo, Ngaglik, Sleman, DI Yogyakarta, 55581</option></select>',
        { isCheckout: true, indonesiaStates: { YO: 'Daerah Istimewa Yogyakarta' } },
        (w) => { requests = postalRequests(w); },
      );
      try {
        const document = h.window.document;
        const value = (name: string) => (document.querySelector(`[name="${name}"]`) as any).value;
        expect(value('billing_city')).toBe('Yogyakarta');
        expect(value('billing_postcode')).toBe('55222');
        let native = document.querySelector('select')!;
        if (interaction === 'combobox') {
          await h.open();
          await h.choose('31554');
        } else {
          if (interaction === 'replacement') {
            const replacement = native.cloneNode(true) as typeof native;
            native.replaceWith(replacement);
            native = replacement;
          }
          native.value = '31554';
          native.dispatchEvent(new h.window.Event('change', { bubbles: true }));
          await h.settle();
        }
        expect(value('billing_city')).toBe('Sleman');
        expect(value('billing_state')).toBe('YO');
        expect(value('billing_postcode')).toBe('55581');
        expect(document.querySelector('[name="kiriof_billing_derived_address"]')).toBeNull();
        expect(h.changes).toHaveLength(1);
        expect(requests).toHaveLength(1);
        native.replaceChildren();
        h.bridge.refresh();
        await h.settle();
        expect(native.value).toBe('31554');
        expect(value('kiriof_destination_area_name')).toContain('Sari Harjo');
        expect(requests).toHaveLength(1);
        for (const [field, manual] of [['city', 'Manual city'], ['state', 'JB'], ['postcode', '40111']]) {
          (document.querySelector(`[name="billing_${field}"]`) as any).value = manual;
        }
        const postcode = document.getElementById('billing_postcode')!;
        postcode.dispatchEvent(new h.window.Event('input', { bubbles: true }));
        h.bridge.refresh();
        await h.settle();
        expect(value('billing_city')).toBe('Manual city');
        expect(value('billing_state')).toBe('JB');
        expect(value('billing_postcode')).toBe('40111');
        expect(h.changes).toHaveLength(1);
      } finally {
        await h.cleanup();
      }
    });
  }

  test('closing an untouched dropdown does not cancel pending postcode recovery', async () => {
    let requests!: ReturnType<typeof postalRequests>;
    const h = await fixture(postalAddress(), { isCheckout: '1' }, (w) => {
      requests = postalRequests(w);
    });
    try {
      await h.open();
      const input = h.window.document.querySelector('[data-combobox-input]')!;
      input.dispatchEvent(new h.window.Event('input', { bubbles: true }));
      input.dispatchEvent(new h.window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
      await h.settle();
      expect(requests[0].signal.aborted).toBe(false);
      expect(h.bridge.districtPending()).toBe(true);
      requests[0].resolve(postalRows([['202', 'Canonical']]));
      await h.settle();
      expect(h.window.document.querySelector('select')!.value).toBe('202');
      expect(h.changes).toHaveLength(1);
      expect(h.bridge.districtPending()).toBe(false);
      expect(requests).toHaveLength(1);
    } finally {
      await h.cleanup();
    }
  });

  test('WordPress-localized checkout string enables verified recovery', async () => {
    let requests!: ReturnType<typeof postalRequests>;
    const h = await fixture(postalAddress(), { isCheckout: '1' }, (w) => {
      requests = postalRequests(w);
    });
    try {
      expect(requests.map((request) => request.term)).toEqual(['12345']);
      requests[0].resolve(postalRows([['202', 'Canonical']]));
      await h.settle();
      expect(h.window.document.querySelector('select')!.value).toBe('202');
      expect(h.changes).toHaveLength(1);
    } finally {
      await h.cleanup();
    }
  });

  test('reselecting the current district after recovery failure explicitly retries its save', async () => {
    let requests!: ReturnType<typeof postalRequests>;
    const h = await fixture(postalAddress('billing', '12345', '202'), { isCheckout: true }, (w) => {
      requests = postalRequests(w);
    });
    try {
      requests[0].reject(new Error('Read failed'));
      await h.settle();
      expect(h.bridge.districtPending()).toBe(true);
      await h.open();
      await h.choose('202');
      expect(h.window.document.querySelector('select')!.value).toBe('202');
      expect(h.changes).toHaveLength(1);
      expect(h.bridge.districtPending()).toBe(false);
      expect(h.window.document.querySelector('[role="status"]')?.textContent).toBe('');
      h.bridge.refresh();
      await h.settle();
      expect(h.changes).toHaveLength(1);
      expect(requests).toHaveLength(1);
    } finally {
      await h.cleanup();
    }
  });

  test('fast recovery waits for the district writer and repairs hidden labels without duplicate writes', async () => {
    let requests!: ReturnType<typeof postalRequests>;
    const h = await fixture(
      postalAddress() + '<input type="hidden" name="kiriof_destination_area_name" value="Old label">',
      { isCheckout: true },
      (w) => {
        (w as any).kiriofClassicDistrictMutation = { ready: false };
        requests = postalRequests(w);
      },
    );
    try {
      expect(h.bridge.districtPending()).toBe(true);
      requests[0].resolve(postalRows([['202', 'Canonical']]));
      await h.settle();
      expect(h.window.document.querySelector('select')!.value).toBe('202');
      expect(h.changes).toHaveLength(0);
      expect(h.bridge.districtPending()).toBe(true);
      (h.window as any).kiriofClassicDistrictMutation.ready = true;
      h.window.document.body.dispatchEvent(new h.window.Event('kiriof:classic-district-ready'));
      await h.settle();
      expect(h.changes).toHaveLength(1);
      expect(h.bridge.districtPending()).toBe(false);
      const old = h.window.document.querySelector<HTMLInputElement>('[name="kiriof_destination_area_name"]')!;
      const next = old.cloneNode() as typeof old;
      next.value = 'Stale replacement';
      old.replaceWith(next);
      h.bridge.refresh();
      await h.settle();
      expect(next.value).toBe('Canonical');
      expect(h.changes).toHaveLength(1);
      expect(requests).toHaveLength(1);
    } finally {
      await h.cleanup();
    }
  });

  for (const source of ['saved', 'unique', 'current', 'configured']) {
    test(`${source} boot recovery verifies rows and publishes exactly once across refresh/replacement`, async () => {
      let requests!: ReturnType<typeof postalRequests>;
      const config = {
        isCheckout: true,
        savedDistrictByPostcode:
          source === 'saved'
            ? { '12345': { destination_id: '202', destination_name: 'Saved' } }
            : {},
        billingDistrict: source === 'configured' ? { id: '202', name: 'Configured' } : {},
      };
      const h = await fixture(
        postalAddress('billing', '12345', source === 'current' ? '202' : ''),
        config,
        (w) => {
          requests = postalRequests(w);
        },
      );
      try {
        expect(requests.map((r) => r.term)).toEqual(['12345']);
        requests[0].resolve(
          postalRows(
            source === 'unique'
              ? [['202', 'Canonical']]
              : [
                  ['101', 'Other'],
                  ['202', 'Canonical'],
                ],
          ),
        );
        await h.settle();
        let native = h.window.document.querySelector('select')!;
        expect(native.value).toBe('202');
        expect(h.trigger().textContent).toContain('Canonical');
        expect(h.changes).toHaveLength(1);
        const replacement = native.cloneNode(false) as typeof native;
        replacement.innerHTML = '<option value="">Select Option</option>';
        native.replaceWith(replacement);
        h.bridge.refresh();
        h.window.document.body.dispatchEvent(new h.window.Event('updated_checkout'));
        await h.settle();
        expect(replacement.value).toBe('202');
        expect(h.changes).toHaveLength(1);
        expect(requests).toHaveLength(1);
        replacement.innerHTML = '<option value="999" selected>Refresh default</option>';
        h.bridge.refresh();
        await h.settle();
        expect(replacement.value).toBe('202');
        expect(h.changes).toHaveLength(1);
      } finally {
        await h.cleanup();
      }
    });
  }
  test('ambiguous postcode populates native options without choosing first; unmatched current is cleared', async () => {
    let requests!: ReturnType<typeof postalRequests>;
    const h = await fixture(postalAddress('billing', '12345', '999'), { isCheckout: true }, (w) => {
      requests = postalRequests(w);
    });
    try {
      requests[0].resolve(
        postalRows([
          ['101', 'One'],
          ['202', 'Two'],
        ]),
      );
      await h.settle();
      const native = h.window.document.querySelector('select')!;
      expect(native.value).toBe('');
      expect([...native.options].map((o) => o.value)).toEqual(['', '101', '202']);
      expect(h.changes).toHaveLength(0);
    } finally {
      await h.cleanup();
    }
  });
  for (const action of ['choose', 'clear', 'search']) {
    test(`manual ${action} wins delayed automatic read`, async () => {
      let requests!: ReturnType<typeof postalRequests>;
      const h = await fixture(
        postalAddress('billing', '12345', '101'),
        { isCheckout: true },
        (w) => {
          requests = postalRequests(w);
        },
      );
      try {
        if (action === 'clear')
          (h.window.document.querySelector('.kiriof-buyer-combobox-clear') as any).click();
        else if (action === 'choose') {
          const native = h.window.document.querySelector('select')!;
          const option = h.window.document.createElement('option');
          option.value = '303';
          option.text = 'Manual';
          native.append(option);
          native.value = '303';
          native.dispatchEvent(new h.window.Event('change', { bubbles: true }));
        } else {
          await h.open();
          const input = h.window.document.querySelector('[data-combobox-input]') as any;
          input.value = 'Manual';
          input.dispatchEvent(new h.window.Event('input', { bubbles: true }));
        }
        await h.settle();
        expect(requests[0].signal.aborted).toBe(true);
        requests[0].resolve(postalRows([['202', 'Automatic']]));
        await h.settle();
        expect(h.window.document.querySelector('select')!.value).toBe(
          action === 'clear' ? '' : action === 'choose' ? '303' : '101',
        );
        if (action === 'clear') {
          const old = h.window.document.querySelector('select')!;
          const replacement = old.cloneNode(false) as typeof old;
          replacement.innerHTML = '<option value="999" selected>Replacement default</option>';
          old.replaceWith(replacement);
        }
        h.bridge.refresh();
        await h.settle();
        expect(requests).toHaveLength(1);
        if (action === 'clear') expect(h.window.document.querySelector('select')!.value).toBe('');
      } finally {
        await h.cleanup();
      }
    });
  }
  test('failure surfaces existing retry using empty display term', async () => {
    let requests!: ReturnType<typeof postalRequests>;
    const h = await fixture(postalAddress(), { isCheckout: true }, (w) => {
      requests = postalRequests(w);
    });
    try {
      requests[0].reject(new Error('offline'));
      await h.settle();
      expect(h.window.document.querySelector('[role="status"]')?.textContent).toContain(
        'Could not',
      );
      await h.open();
      (h.window.document.querySelector('.kiriof-buyer-combobox-retry') as any).click();
      await h.settle();
      expect(requests.map((r) => r.term)).toEqual(['12345', '12345']);
      requests[1].resolve(postalRows([['202', 'Recovered']]));
      await h.settle();
      expect(h.window.document.querySelector('select')!.value).toBe('202');
      expect(h.changes).toHaveLength(1);
    } finally {
      await h.cleanup();
    }
  });
  test('postcode change cancels stale read; inactive shipping only publishes when effective', async () => {
    let requests!: ReturnType<typeof postalRequests>;
    const h = await fixture(
      postalAddress() +
        postalAddress('shipping', '54321') +
        '<input type="checkbox" name="ship_to_different_address">',
      { isCheckout: true },
      (w) => {
        requests = postalRequests(w);
      },
    );
    try {
      expect(requests.map((r) => r.term)).toEqual(['12345', '54321']);
      const postcode = h.window.document.getElementById('billing_postcode') as any;
      postcode.value = '67890';
      postcode.dispatchEvent(new h.window.Event('input', { bubbles: true }));
      await h.settle();
      expect(requests[0].signal.aborted).toBe(true);
      // The unrelated shipping lookup must remain independently usable.
      requests.find((r) => r.term === '54321')!.resolve(postalRows([['303', 'Shipping']]));
      requests.find((r) => r.term === '67890')!.resolve(postalRows([['202', 'Billing']]));
      requests[0].resolve(postalRows([['999', 'Stale']]));
      await h.settle();
      expect(h.window.document.getElementById('billing_district')!.getAttribute('name')).toBe(
        'kiriof_destination_area',
      );
      expect((h.window.document.getElementById('billing_district') as any).value).toBe('202');
      expect((h.window.document.getElementById('shipping_district') as any).value).toBe('303');
      expect(h.changes.filter((e) => (e.target as any).tagName === 'SELECT')).toHaveLength(1);
      const separate = h.window.document.querySelector('[name="ship_to_different_address"]') as any;
      separate.checked = true;
      separate.dispatchEvent(new h.window.Event('change', { bubbles: true }));
      await h.settle();
      for (const request of requests.slice(3))
        request.resolve(
          postalRows(request.term === '54321' ? [['303', 'Shipping']] : [['202', 'Billing']]),
        );
      await h.settle();
      expect(h.changes.filter((e) => (e.target as any).tagName === 'SELECT')).toHaveLength(2);
      expect(
        (h.changes.filter((e) => (e.target as any).tagName === 'SELECT')[1].target as any).name,
      ).toBe('kiriof_shipping_destination_area');
    } finally {
      await h.cleanup();
    }
  });
  for (const postcode of ['', '1234', '123456', '12a45']) {
    test(`invalid postcode ${JSON.stringify(postcode)} does not query`, async () => {
      let requests!: ReturnType<typeof postalRequests>;
      const h = await fixture(postalAddress('billing', postcode), { isCheckout: true }, (w) => {
        requests = postalRequests(w);
      });
      try {
        expect(requests).toHaveLength(0);
      } finally {
        await h.cleanup();
      }
    });
  }
});

describe('Classic recovery boundaries', () => {
  for (const boundary of ['not-opted-in', 'account', 'blocks', 'foreign-country']) {
    test(`${boundary} never queries`, async () => {
      let requests!: ReturnType<typeof postalRequests>;
      const html =
        postalAddress() +
        (boundary === 'account'
          ? '<div class="kiriof-account-shipping"></div>'
          : boundary === 'blocks'
            ? '<div class="wc-block-checkout"></div>'
            : '');
      const h = await fixture(html, { isCheckout: boundary !== 'not-opted-in' }, (w) => {
        requests = postalRequests(w);
        if (boundary === 'foreign-country')
          (w.document.getElementById('billing_country') as any).value = 'SG';
      });
      try {
        expect(requests).toHaveLength(0);
      } finally {
        await h.cleanup();
      }
    });
  }
  for (const part of ['country', 'state', 'separate']) {
    test(`${part} cancels delayed recovery before it can publish`, async () => {
      let requests!: ReturnType<typeof postalRequests>;
      const h = await fixture(
        postalAddress() + '<input type="checkbox" name="ship_to_different_address">',
        { isCheckout: true },
        (w) => {
          requests = postalRequests(w);
        },
      );
      try {
        const field = (
          part === 'separate'
            ? h.window.document.querySelector('[name="ship_to_different_address"]')
            : h.window.document.getElementById(`billing_${part}`)
        ) as any;
        if (part === 'separate') field.checked = true;
        else field.value = part === 'country' ? 'SG' : 'JK';
        field.dispatchEvent(new h.window.Event('change', { bubbles: true }));
        await h.settle();
        expect(requests[0].signal.aborted).toBe(true);
        requests[0].resolve(postalRows([['999', 'Stale']]));
        await h.settle();
        expect((h.window.document.querySelector('select') as any).value).toBe('');
        expect(h.changes.filter((e) => (e.target as any).tagName === 'SELECT')).toHaveLength(0);
      } finally {
        await h.cleanup();
      }
    });
  }
});
