import { afterAll, describe, expect, test } from 'bun:test';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';

const root = resolve(import.meta.dir, '..');
// Build once, but evaluate a fresh self-contained module for each Window. Svelte
// caches native DOM getters at module scope: reusing the imported runtime would
// leak the first Window into subsequent fixtures. Copying bytes does not.
let buildDirectory: string | undefined;
let bundlePromise: Promise<Buffer> | undefined;
let bundleBuildCount = 0;
afterAll(() => { if (buildDirectory) rmSync(buildDirectory, { recursive: true, force: true }); });

function compiledBundle(): Promise<Buffer> {
  return bundlePromise ??= buildBundle();
}

async function buildBundle(): Promise<Buffer> {
  const directory = buildDirectory = mkdtempSync(join(root, 'node_modules', '.pickup-dialog-build-'));
  bundleBuildCount++;
  const put = (name: string, source: string) => writeFileSync(join(directory, name), source);
  put('Container.svelte', `<script>let {children, ...rest} = $props();</script><div {...rest}>{@render children?.()}</div>`);
  put('Root.svelte', `<script>let {children, open} = $props();</script>{#if open}{@render children?.()}{/if}`);
  put('Button.svelte', `<script>let {children, variant, ...rest} = $props();</script><button {...rest}>{@render children?.()}</button>`);
  put('Select.svelte', `<script>let {value = $bindable(''), options = [], onChange, ...rest} = $props();</script><select {...rest} bind:value onchange={() => onChange?.(value)}>{#each options as option}<option value={option.value}>{option.label}</option>{/each}</select>`);
  put('dialog.ts', `export {default as Root} from './Root.svelte'; export {default as Content, default as Header, default as Title, default as Description, default as Footer} from './Container.svelte';`);
  put('field.ts', `export {default as Field, default as FieldGroup, default as FieldLabel, default as FieldDescription} from './Container.svelte';`);
  put('button.ts', `export {default as Button} from './Button.svelte';`);
  let source = readFileSync(join(root, 'src/lib/transactions/RequestPickupDialog.svelte'), 'utf8');
  const replacements = { '$lib/components/ui/dialog': './dialog.ts', '$lib/components/ui/field': './field.ts', '$lib/components/ui/button': './button.ts', '$lib/ui/KiriofSelect.svelte': './Select.svelte', './pickup-schedule': join(root, 'src/lib/transactions/pickup-schedule.ts'), '$lib/components/ui/radio-group': join(root, 'src/lib/components/ui/radio-group/index.ts'), '$lib/components/ui/input-otp': join(root, 'src/lib/components/ui/input-otp/index.ts') };
  for (const [from, to] of Object.entries(replacements)) source = source.replace(`'${from}'`, `'${to}'`);
  put('Production.svelte', source);
  put('Host.svelte', `<script>import Production from './Production.svelte'; let {props} = $props(); let open = $state(true); export function close() {open = false;}</script><Production {...props} bind:open />`);
  put('entry.ts', `export {mount, unmount, flushSync} from 'svelte'; export {default as Host} from './Host.svelte';`);
  const build = await Bun.build({ entrypoints: [join(directory, 'entry.ts')], outdir: directory, naming: 'runtime.js', target: 'browser', conditions: ['browser'], plugins: [{ name: 'svelte-runtime', setup(builder) {
    builder.onResolve({ filter: /^\$lib\/utils(?:\.js)?$/ }, () => ({ path: join(root, 'src/lib/utils.ts') }));
    builder.onResolve({ filter: /^\$lib\/payments\// }, ({ path }) => ({ path: join(root, 'src/lib', path.slice('$lib/'.length)) + (path.endsWith('.svelte') ? '' : '.ts') }));
    // Only the parent's scheduling fields are mocked; shared payment components use real shadcn UI.
    builder.onResolve({ filter: /^\$lib\/components\/ui\// }, ({ path }) => ({ path: join(root, 'src/lib', path.slice('$lib/'.length).replace(/\/index\.js$/, ''), 'index.ts') }));
    builder.onResolve({ filter: /^svelte$/ }, () => ({ path: join(root, 'node_modules/svelte/src/index-client.js') }));
    builder.onLoad({ filter: /\.svelte\.[jt]s$/ }, ({ path }) => ({ contents: compileModule(path.endsWith('.ts') ? new Bun.Transpiler({ loader: 'ts' }).transformSync(readFileSync(path, 'utf8')) : readFileSync(path, 'utf8'), { filename: path, generate: 'client' }).js.code, loader: 'js' }));
    builder.onLoad({ filter: /\.svelte$/ }, ({ path }) => ({ contents: compile(readFileSync(path, 'utf8'), { filename: path, generate: 'client' }).js.code, loader: 'js' }));
  } }] });
  if (!build.success) throw new Error(build.logs.join('\n'));
  return readFileSync(join(directory, 'runtime.js'));
}

async function fixture({ balance = 4000000000, fee = 50000, hasPin = true, status = 200, reject = false, pending = false, missing = false, i18n = {} }: any = {}) {
  const directory = mkdtempSync(join(root, 'node_modules', '.pickup-dialog-runtime-'));
  const window = new happy.Window({ url: 'https://fixture.test' });
  const saved = new Map<string, PropertyDescriptor | undefined>();
  const requests: URLSearchParams[] = [];
  let replyBalance!: () => void;
  const globals = { window, document: window.document, navigator: window.navigator, Node: window.Node, Element: window.Element, HTMLElement: window.HTMLElement, HTMLButtonElement: window.HTMLButtonElement, SVGElement: window.SVGElement, DocumentFragment: window.DocumentFragment, HTMLInputElement: window.HTMLInputElement, HTMLSelectElement: window.HTMLSelectElement, Text: window.Text, Comment: window.Comment, Event: window.Event, CustomEvent: window.CustomEvent, MutationObserver: window.MutationObserver, ResizeObserver: window.ResizeObserver, getComputedStyle: window.getComputedStyle.bind(window), requestAnimationFrame: window.requestAnimationFrame.bind(window), cancelAnimationFrame: window.cancelAnimationFrame.bind(window),
    fetch: async (_url: string, options: any) => {
      const body = new URLSearchParams(options.body); requests.push(body);
      const action = body.get('action');
      let data: any = {};
      if (action === 'kiriof_request_pickup_summary') data = { transaction_summary: { count_non_cod: 1, sum_fee_non_cod: fee } };
      if (action === 'kiriof_get_payment_method_config') data = { is_top: false, ka_credit_enabled: true, has_pin: hasPin };
      if (action === 'kiriof_get_credit_balance') {
        if (pending) await new Promise<void>((resolve) => { replyBalance = resolve; });
        if (reject) throw new Error('Network failure');
        data = missing ? {} : { balance };
      }
      // Exercise submission without allowing the production redirect to navigate.
      if (action === 'kiriof_request_pickup_transaction') {
        await new Promise<void>((resolve) => { replyBalance = resolve; });
        return { ok: true, json: async () => ({ success: true, data: { status: 400, message: 'Invalid PIN', data } }) };
      }
      return { ok: true, json: async () => ({ success: true, data: { status: action === 'kiriof_get_credit_balance' ? status : 200, data } }) };
    },
  };
  for (const [key, value] of Object.entries(globals)) { saved.set(key, Object.getOwnPropertyDescriptor(globalThis, key)); Object.defineProperty(globalThis, key, { configurable: true, writable: true, value }); }
  writeFileSync(join(directory, 'runtime.js'), await compiledBundle());
  const runtime = await import(join(directory, 'runtime.js'));
  const target = window.document.createElement('main'); window.document.body.append(target);
  const host = runtime.mount(runtime.Host, { target, props: { props: { orderIds: ['1'], ajaxUrl: '/ajax', nonce: 'nonce', pickupUrl: '/pickup', i18n } } });
  async function settle() { for (let i = 0; i < 20; i++) { await Promise.resolve(); runtime.flushSync(); } }
  await settle();
  return { target, requests, settle, credit: () => target.querySelector('[role="radio"][aria-label="KA Credit"]') as HTMLButtonElement, close: async () => { host.close(); await settle(); }, reply: async () => { replyBalance(); await settle(); }, cleanup: async () => { await runtime.unmount(host); window.happyDOM.abort(); for (const [key, descriptor] of saved) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete (globalThis as any)[key]; } rmSync(directory, { recursive: true, force: true }); } };
}

describe('Express pickup KA Credit (compiled Svelte with shared payment components)', () => {
  test('only the newly typed PIN digit is revealed; navigation, blur, paste and submission mask it', async () => {
    const h = await fixture(); try {
      (h.target.querySelector('.kiriof-dialog-primary') as HTMLButtonElement).click(); await h.settle();
      const input = h.target.querySelector('#kiriof-pickup-pin') as HTMLInputElement;
      const win = input.ownerDocument.defaultView!;
      const characters = () => [...h.target.querySelectorAll('[data-slot="input-otp-slot"]')].map((cell) => cell.textContent?.trim());
      async function type(value: string) { input.value = value; input.dispatchEvent(new Event('input', { bubbles: true })); await h.settle(); }
      input.focus(); await h.settle();
      await type('1'); expect(characters()).toEqual(['1', '', '', '', '', '']);
      await type('12'); expect(characters()).toEqual(['•', '2', '', '', '', '']);
      input.dispatchEvent(new win.KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true })); await h.settle();
      expect(characters()).toEqual(['•', '•', '', '', '', '']);
      await type('13'); expect(characters()).toEqual(['•', '3', '', '', '', '']);
      input.blur(); await h.settle(); expect(characters()).toEqual(['•', '•', '', '', '', '']);
      input.focus(); await h.settle(); expect(characters()).toEqual(['•', '•', '', '', '', '']);
      await type('1'); expect(characters()).toEqual(['•', '', '', '', '', '']);
      await type('11'); expect(characters()).toEqual(['•', '1', '', '', '', '']);
      input.value = '111'; input.setSelectionRange(1, 1);
      input.dispatchEvent(new Event('input', { bubbles: true })); await h.settle();
      expect(characters()).toEqual(['1', '•', '•', '', '', '']);
      input.dispatchEvent(new win.Event('paste', { bubbles: true })); await type('123456');
      expect(characters()).toEqual(Array(6).fill('•'));
      await type('123457'); expect(characters()).toEqual(['•', '•', '•', '•', '•', '7']);
      input.dispatchEvent(new win.Event('pointerdown', { bubbles: true })); await h.settle();
      expect(characters()).toEqual(Array(6).fill('•'));
      await type('123458'); expect(characters().at(-1)).toBe('8');
      (h.target.querySelector('.kiriof-dialog-primary') as HTMLButtonElement).click(); await h.settle();
      expect(characters()).toEqual(Array(6).fill('•'));
      await h.reply();
    } finally { await h.cleanup(); }
  });
  test('compiles once while each fixture receives isolated module, DOM, state and requests', async () => {
    const first = await fixture({ balance: 70000 });
    let firstDocument: Document;
    try {
      firstDocument = first.target.ownerDocument;
      expect(first.target.textContent).toContain('Rp70.000');
      (first.target.querySelector('.kiriof-dialog-primary') as HTMLButtonElement).click(); await first.settle();
      const input = first.target.querySelector('input') as HTMLInputElement;
      input.value = '654321'; input.dispatchEvent(new Event('input', { bubbles: true })); await first.settle();
      expect(first.target.querySelectorAll('[data-slot="input-otp-slot"]')).toHaveLength(6);
    } finally { await first.cleanup(); }
    const second = await fixture({ balance: 90000 });
    try {
      expect(second.target.ownerDocument).not.toBe(firstDocument!);
      expect(second.target.querySelector('input')).toBeNull();
      expect(second.target.querySelector('[data-loading-layout]')).toBeNull();
      expect(second.target.textContent).toContain('Rp90.000');
      expect(second.target.textContent).not.toContain('Rp70.000');
      expect(second.requests).toHaveLength(3);
      expect(bundleBuildCount).toBe(1);
      (second.target.querySelector('.kiriof-dialog-primary') as HTMLButtonElement).click(); await second.settle();
      expect((second.target.querySelector('input') as HTMLInputElement).value).toBe('');
    } finally { await second.cleanup(); }
  });
  test('Back to Summary retains reviewed Express values and clears the PIN without submitting', async () => {
    const h = await fixture(); try {
      const before = h.requests.length;
      (h.target.querySelector('.kiriof-dialog-primary') as HTMLButtonElement).click(); await h.settle();
      const input = h.target.querySelector('#kiriof-pickup-pin') as HTMLInputElement;
      input.value = '123456'; input.dispatchEvent(new Event('input', { bubbles: true })); await h.settle();
      (h.target.querySelector('.kiriof-dialog-secondary') as HTMLButtonElement).click(); await h.settle();
      expect(h.target.querySelector('#kiriof-pickup-pin')).toBeNull();
      expect(h.target.querySelector('.kiriof-pickup-summary')?.textContent).toContain('Rp50.000');
      expect(h.credit().getAttribute('aria-checked')).toBe('true');
      expect(h.target.querySelector('.kiriof-dialog-secondary')?.textContent).toBe('Close');
      expect(h.requests).toHaveLength(before);
      (h.target.querySelector('.kiriof-dialog-primary') as HTMLButtonElement).click(); await h.settle();
      expect((h.target.querySelector('#kiriof-pickup-pin') as HTMLInputElement).value).toBe('');
      expect((h.target.querySelector('.kiriof-dialog-primary') as HTMLButtonElement).disabled).toBe(true);
    } finally { await h.cleanup(); }
  });
  test('pending summary renders the Express skeleton shape without premature amounts or payment controls', async () => {
    const h = await fixture({ pending: true, i18n: { loading: 'Menyiapkan ringkasan' } }); try {
      expect(h.requests).toHaveLength(3);
      const skeleton = h.target.querySelector('[data-loading-layout="express"]')!;
      expect(skeleton.getAttribute('aria-busy')).toBe('true');
      expect(skeleton.getAttribute('role')).toBe('status');
      expect(skeleton.getAttribute('aria-label')).toBe('Menyiapkan ringkasan');
      expect(skeleton.querySelector('[aria-hidden="true"]')).not.toBeNull();
      const totals = skeleton.querySelector('[data-loading-section="totals"]')!;
      expect(totals.children).toHaveLength(3);
      expect(totals.querySelectorAll('[data-slot="skeleton"]')).toHaveLength(6);
      const schedule = skeleton.querySelector('[data-loading-section="schedule"]')!;
      expect(schedule.children).toHaveLength(2);
      expect(schedule.querySelectorAll('[data-slot="skeleton"]')).toHaveLength(4);
      const methods = skeleton.querySelector('[data-loading-section="payment-methods"]')!;
      expect([...methods.children].filter((card) => card.classList.contains('border'))).toHaveLength(2);
      expect(methods.querySelectorAll('[data-slot="skeleton"]')).toHaveLength(9);
      expect(skeleton.querySelector('[data-loading-section="notice"]')).toBeNull();
      expect(skeleton.querySelector('[data-loading-section="order-trigger"]')).toBeNull();
      expect(h.target.querySelector('.kiriof-pickup-summary')).toBeNull();
      expect(h.target.querySelector('[role="radiogroup"]')).toBeNull();
      expect(h.target.querySelector('select')).toBeNull();
      expect(h.target.textContent).not.toContain('Rp');
      expect(h.target.querySelector('.kiriof-dialog-primary')).toBeNull();
      await h.reply();
      expect(h.target.querySelector('[data-loading-layout]')).toBeNull();
      expect(h.target.querySelectorAll('.kiriof-pickup-summary > div')).toHaveLength(3);
      expect(h.target.querySelectorAll('select')).toHaveLength(2);
      expect([...h.target.querySelectorAll('[role="radio"]')].map((radio) => radio.getAttribute('aria-label'))).toEqual(['KA Credit', 'QRIS']);
      expect(h.target.textContent).toContain('Rp50.000');
      expect(h.credit().getAttribute('aria-checked')).toBe('true');
    } finally { await h.cleanup(); }
  });
  test('Continue opens six masked numeric PIN cells with shared labels and submits the bound PIN', async () => {
    const h = await fixture({ i18n: { enterPin: 'PIN Rahasia', pinDescription: 'Enam angka profil', paymentMethod: 'Metode pembayaran' } }); try {
      const group = h.target.querySelector('[role="radiogroup"]')!;
      expect(h.target.querySelector(`#${group.getAttribute('aria-labelledby')}`)?.textContent).toContain('Metode pembayaran');
      expect(h.target.querySelector('label[for="pickup-method-credit"]')).not.toBeNull();
      expect(h.target.querySelector('label[for="pickup-method-qris"]')).not.toBeNull();
      (h.target.querySelector('.kiriof-dialog-primary') as HTMLButtonElement).click(); await h.settle();
      const input = h.target.querySelector('input') as HTMLInputElement;
      expect(input.id).toBe('kiriof-pickup-pin');
      expect(input.type).toBe('password');
      expect(input.maxLength).toBe(6);
      expect(input.getAttribute('inputmode')).toBe('numeric');
      expect(input.autocomplete).toBe('off');
      expect(input.getAttribute('aria-label')).toBe('PIN Rahasia');
      expect(h.target.querySelector(`label[for="${input.id}"]`)?.textContent).toBe('PIN Rahasia');
      expect(h.target.querySelector(`#${input.getAttribute('aria-describedby')}`)?.textContent).toBe('Enam angka profil');
      expect(h.target.querySelectorAll('[data-slot="input-otp-slot"]')).toHaveLength(6);
       expect(h.target.querySelectorAll('[data-slot="input-otp-group"]')).toHaveLength(0);
       expect(h.target.querySelector(`label[for="${input.id}"]`)?.classList.contains('sr-only')).toBe(true);
       expect(h.target.querySelector(`#${input.getAttribute('aria-describedby')}`)?.classList.contains('sr-only')).toBe(true);
       expect(h.target.querySelectorAll('[data-slot="input-otp-separator"]')).toHaveLength(0);
       expect(input.hasAttribute('data-pin-input-input')).toBe(true);
       expect(h.target.querySelector('.kiriof-dialog-secondary')?.textContent).toBe('Back to Summary');
      const submit = h.target.querySelector('.kiriof-dialog-primary') as HTMLButtonElement;
      expect(submit.disabled).toBe(true);
      input.value = '12345'; input.dispatchEvent(new Event('input', { bubbles: true })); await h.settle();
      expect(submit.disabled).toBe(true);
      input.value = '123456'; input.dispatchEvent(new Event('input', { bubbles: true })); await h.settle();
      expect(submit.disabled).toBe(false);
      expect([...h.target.querySelectorAll('[data-slot="input-otp-slot"]')].map((cell) => cell.textContent?.trim())).toEqual(Array(6).fill('•'));
      submit.click(); await h.settle();
      expect(input.disabled).toBe(true);
      expect(h.target.querySelector('[data-slot="field"][data-disabled="true"]')).not.toBeNull();
      const progress = h.target.querySelector('.kiriof-shipment-operation-progress')!;
      expect(progress.getAttribute('data-slot')).toBe('alert');
      expect(progress.getAttribute('role')).toBe('status');
      expect(progress.getAttribute('aria-live')).toBe('polite');
      expect(progress.getAttribute('aria-busy')).toBe('true');
      expect(progress.textContent).toContain('Processing');
      expect(progress.querySelector('svg.animate-spin[aria-hidden="true"]')).not.toBeNull();
      expect(submit.querySelector('svg.animate-spin[data-icon="inline-start"]')).not.toBeNull();
      expect(submit.hasAttribute('loading')).toBe(false);
      expect(submit.disabled).toBe(true);
      expect((h.target.querySelector('.kiriof-dialog-secondary') as HTMLButtonElement).disabled).toBe(true);
      expect(h.target.querySelectorAll('[data-slot="input-otp-slot"]')).toHaveLength(6);
      expect([...h.target.querySelectorAll('[data-slot="input-otp-slot"]')].map((cell) => cell.textContent?.trim())).toEqual(Array(6).fill('•'));
      expect(h.target.querySelector('[data-loading-layout]')).toBeNull();
      await h.reply();
      const request = h.requests.at(-1)!;
      expect(request.get('action')).toBe('kiriof_request_pickup_transaction');
      expect(request.get('data[pin]')).toBe('123456');
      expect(request.get('data[payment_method]')).toBe('credit');
      expect(request.getAll('data[order_ids][]')).toEqual(['1']);
      expect(request.get('data[nonce]')).toBe('nonce');
      expect(request.get('data[schedule]')).toMatch(/^\d{4}-\d{2}-\d{2} \d{2}:00:00$/);
      expect(input.getAttribute('aria-invalid')).toBe('true');
      expect(h.target.querySelectorAll('[data-slot="input-otp-slot"][aria-invalid="true"]')).toHaveLength(6);
      expect(h.target.querySelector('[role="alert"]')?.textContent).toBe('Invalid PIN');
      expect(h.target.querySelector('.kiriof-shipment-operation-progress')).toBeNull();
      expect(submit.querySelector('svg.animate-spin')).toBeNull();
      expect(input.disabled).toBe(false);
      expect(submit.disabled).toBe(false);
      expect((h.target.querySelector('.kiriof-dialog-secondary') as HTMLButtonElement).disabled).toBe(false);
    } finally { await h.cleanup(); }
  });
  test('WordPress/service envelopes retain a four-billion balance and default to credit', async () => {
    const h = await fixture({ i18n: { creditDescription: 'Sisa Saldo' } }); try {
      expect(h.target.textContent).toContain('Sisa Saldo Rp4.000.000.000');
      expect(h.credit().disabled).toBe(false);
      expect(h.credit().getAttribute('aria-checked')).toBe('true');
      expect(h.requests[2].get('nonce')).toBe('nonce');
      expect(h.requests[2].has('data[nonce]')).toBe(false);
    } finally { await h.cleanup(); }
  });
  test('known insufficient and no-PIN balances remain visible on disabled cards', async () => {
    for (const options of [{ balance: 40000 }, { balance: 4000000000, hasPin: false }]) {
      const h = await fixture(options); try {
        expect(h.credit().disabled).toBe(true);
        expect(h.target.querySelector('label[for="pickup-method-credit"]')?.getAttribute('aria-disabled')).toBe('true');
        expect(h.target.textContent).toContain(`Remaining Credit Rp${options.balance === 40000 ? '40.000' : '4.000.000.000'}`);
        expect(h.target.textContent).toContain(options.hasPin === false ? 'Set a PIN' : 'Insufficient credit');
        const descriptions = h.target.querySelectorAll('.kiriof-payment-method-card p[data-slot="field-description"]');
        expect(descriptions.length).toBeGreaterThan(0);
        for (const description of descriptions) expect(description.classList.contains('m-0')).toBe(true);
      } finally { await h.cleanup(); }
    }
  });
  for (const [scenario, options] of Object.entries({
    missing: { missing: true }, null: { balance: null }, empty: { balance: '' }, boolean: { balance: false },
    malformed: { balance: 'not money' }, infinite: { balance: Infinity }, negative: { balance: -1 }, failed: { status: 400 }, rejected: { reject: true },
  })) {
    test(`unknown balance ${scenario} never becomes zero or eligible even for free pickup`, async () => {
      const h = await fixture({ ...options, fee: 0 }); try {
        expect(h.credit().disabled).toBe(true);
        expect(h.target.textContent).toContain('Unable to verify credit balance.');
        expect(h.target.textContent).not.toContain('Remaining Credit');
        expect(h.target.querySelector('[aria-label="QRIS"]')?.getAttribute('aria-checked')).toBe('true');
      } finally { await h.cleanup(); }
    });
  }
  test('confirmed zero and numeric strings are valid balances', async () => {
    for (const balance of [0, '4000000000.50']) {
      const h = await fixture({ balance, fee: 0 }); try {
        expect(h.credit().disabled).toBe(false);
        expect(h.target.textContent).toContain('Remaining Credit');
      } finally { await h.cleanup(); }
    }
  });
  test('closing while a balance request is pending ignores the late response', async () => {
    const h = await fixture({ pending: true }); try {
      expect(h.requests).toHaveLength(3);
      await h.close(); await h.reply();
      expect(h.target.textContent).toBe('');
    } finally { await h.cleanup(); }
  });
});
