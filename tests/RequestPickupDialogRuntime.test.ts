import { describe, expect, test } from 'bun:test';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';

const root = resolve(import.meta.dir, '..');
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
  const put = (name: string, source: string) => writeFileSync(join(directory, name), source);
  put('Container.svelte', `<script>let {children, ...rest} = $props();</script><div {...rest}>{@render children?.()}</div>`);
  put('Root.svelte', `<script>let {children, open} = $props();</script>{#if open}{@render children?.()}{/if}`);
  put('Button.svelte', `<script>let {children, loading, variant, ...rest} = $props();</script><button {...rest}>{@render children?.()}</button>`);
  put('Icon.svelte', `<svg></svg>`);
  put('Select.svelte', `<script>let {value = $bindable(''), options = [], onChange, ...rest} = $props();</script><select {...rest} bind:value onchange={() => onChange?.(value)}>{#each options as option}<option value={option.value}>{option.label}</option>{/each}</select>`);
  put('dialog.ts', `export {default as Root} from './Root.svelte'; export {default as Content, default as Header, default as Title, default as Description, default as Footer} from './Container.svelte';`);
  put('field.ts', `export {default as Field, default as FieldGroup, default as FieldLabel, default as FieldDescription} from './Container.svelte';`);
  put('button.ts', `export {default as Button} from './Button.svelte';`);
  put('icons.ts', `export {default as IconCreditCard, default as IconLoader2, default as IconQrcode} from './Icon.svelte';`);
  let source = readFileSync(join(root, 'src/lib/transactions/RequestPickupDialog.svelte'), 'utf8');
  const replacements = { '$lib/components/ui/dialog': './dialog.ts', '$lib/components/ui/field': './field.ts', '$lib/components/ui/button': './button.ts', '@tabler/icons-svelte': './icons.ts', '$lib/ui/KiriofSelect.svelte': './Select.svelte', './pickup-schedule': join(root, 'src/lib/transactions/pickup-schedule.ts'), '$lib/components/ui/radio-group': join(root, 'src/lib/components/ui/radio-group/index.ts'), '$lib/components/ui/input-otp': join(root, 'src/lib/components/ui/input-otp/index.ts') };
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
  const runtime = await import(join(directory, 'runtime.js'));
  const target = window.document.createElement('main'); window.document.body.append(target);
  const host = runtime.mount(runtime.Host, { target, props: { props: { orderIds: ['1'], ajaxUrl: '/ajax', nonce: 'nonce', pickupUrl: '/pickup', i18n } } });
  async function settle() { for (let i = 0; i < 20; i++) { await Promise.resolve(); runtime.flushSync(); } }
  await settle();
  return { target, requests, settle, credit: () => target.querySelector('[role="radio"][aria-label="KA Credit"]') as HTMLButtonElement, close: async () => { host.close(); await settle(); }, reply: async () => { replyBalance(); await settle(); }, cleanup: async () => { await runtime.unmount(host); window.happyDOM.abort(); for (const [key, descriptor] of saved) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete (globalThis as any)[key]; } rmSync(directory, { recursive: true, force: true }); } };
}

describe('Express pickup KA Credit (compiled Svelte with shared payment components)', () => {
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
      expect(h.target.querySelectorAll('[data-slot="input-otp-group"]')).toHaveLength(2);
      expect(h.target.querySelectorAll('[data-slot="input-otp-separator"]')).toHaveLength(1);
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
      } finally { await h.cleanup(); }
    }
  });
  test('unknown, malformed, non-finite and failed balances never become zero or eligible, even for free pickup', async () => {
    for (const options of [{ missing: true }, { balance: null }, { balance: '' }, { balance: false }, { balance: 'not money' }, { balance: Infinity }, { balance: -1 }, { status: 400 }, { reject: true }]) {
      const h = await fixture({ ...options, fee: 0 }); try {
        expect(h.credit().disabled).toBe(true);
        expect(h.target.textContent).toContain('Unable to verify credit balance.');
        expect(h.target.textContent).not.toContain('Remaining Credit');
        expect(h.target.querySelector('[aria-label="QRIS"]')?.getAttribute('aria-checked')).toBe('true');
      } finally { await h.cleanup(); }
    }
  }, 30000);
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
