import { afterAll, describe, expect, test } from 'bun:test';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';

const root = resolve(import.meta.dir, '..');
let buildDirectory: string | undefined;
let bundlePromise: Promise<Buffer> | undefined;
let bundleBuildCount = 0;
afterAll(() => { if (buildDirectory) rmSync(buildDirectory, { recursive: true, force: true }); });

// Compile once, then evaluate the bundle bytes at a unique path per Window.
// Svelte caches native DOM getters: sharing an imported runtime breaks isolation.
function compiledBundle(): Promise<Buffer> { return bundlePromise ??= buildBundle(); }

async function buildBundle(): Promise<Buffer> {
  const directory = buildDirectory = mkdtempSync(join(root, 'node_modules', '.date-range-build-'));
  bundleBuildCount++;
  const put = (name: string, source: string) => writeFileSync(join(directory, name), source);
  // Only boundary UI is replaced. The official shadcn RangeCalendar and all its
  // bits-ui selection, navigation and date arithmetic are compiled unchanged.
  put('PopoverRoot.svelte', `<script>import {setContext} from 'svelte'; let {children, open = $bindable(false), onOpenChange} = $props(); setContext('date-range-test-popover', {get open(){return open}, toggle(){open = !open; onOpenChange?.(open)}});</script>{@render children?.()}`);
  put('PopoverTrigger.svelte', `<script>import {getContext} from 'svelte'; let {child} = $props(); const context = getContext('date-range-test-popover');</script>{@render child({props: {'aria-expanded': context.open, onclick: () => context.toggle()}})}`);
  put('PopoverContent.svelte', `<script>import {getContext} from 'svelte'; let {children, align, ...rest} = $props(); const context = getContext('date-range-test-popover');</script>{#if context.open}<div {...rest} data-slot="popover-content">{@render children?.()}</div>{/if}`);
  put('popover.ts', `export {default as Root} from './PopoverRoot.svelte'; export {default as Trigger} from './PopoverTrigger.svelte'; export {default as Content} from './PopoverContent.svelte';`);
  put('Button.svelte', `<script>let {children, variant, size, ...rest} = $props();</script><button {...rest}>{@render children?.()}</button>`);
  put('button.ts', `export {default as Button} from './Button.svelte'; export const buttonVariants = () => '';`);
  put('Icon.svelte', `<script>let props = $props();</script><svg aria-hidden="true" {...props}></svg>`);
  put('icons.ts', `export {default as IconCalendar, default as IconChevronDown, default as IconChevronLeft, default as IconChevronRight} from './Icon.svelte';`);
  put('Host.svelte', `<script>import Production from '${join(root, 'src/lib/ui/DateRangeFilter.svelte')}'; let {props} = $props(); let current = $state(props); export function update(next){current = {...current, ...next};}</script><Production {...current} />`);
  put('entry.ts', `export {mount, unmount, flushSync} from 'svelte'; export {default as Host} from './Host.svelte';`);
  const build = await Bun.build({ entrypoints: [join(directory, 'entry.ts')], outdir: directory, naming: 'runtime.js', target: 'browser', conditions: ['browser'], plugins: [{ name: 'date-range-svelte-runtime', setup(builder) {
    builder.onResolve({ filter: /^\$lib\/components\/ui\/popover(?:\/index\.js)?$/ }, () => ({ path: join(directory, 'popover.ts') }));
    builder.onResolve({ filter: /^\$lib\/components\/ui\/button(?:\/index\.js)?$/ }, () => ({ path: join(directory, 'button.ts') }));
    builder.onResolve({ filter: /^@tabler\/icons-svelte$/ }, () => ({ path: join(directory, 'icons.ts') }));
    builder.onResolve({ filter: /^\$lib\/utils(?:\.js)?$/ }, () => ({ path: join(root, 'src/lib/utils.ts') }));
    builder.onResolve({ filter: /^\$lib\/components\/ui\/range-calendar$/ }, () => ({ path: join(root, 'src/lib/components/ui/range-calendar/index.ts') }));
    builder.onResolve({ filter: /^svelte$/ }, () => ({ path: join(root, 'node_modules/svelte/src/index-client.js') }));
    builder.onLoad({ filter: /\.svelte\.[jt]s$/ }, ({ path }) => ({ contents: compileModule(path.endsWith('.ts') ? new Bun.Transpiler({ loader: 'ts' }).transformSync(readFileSync(path, 'utf8')) : readFileSync(path, 'utf8'), { filename: path, generate: 'client' }).js.code, loader: 'js' }));
    builder.onLoad({ filter: /\.svelte$/ }, ({ path }) => ({ contents: compile(readFileSync(path, 'utf8'), { filename: path, generate: 'client' }).js.code, loader: 'js' }));
  } }] });
  if (!build.success) throw new Error(build.logs.join('\n'));
  return readFileSync(join(directory, 'runtime.js'));
}

type Range = { date_from: string; date_to: string; month: string };
async function fixture(props: Record<string, unknown> = {}) {
  const directory = mkdtempSync(join(root, 'node_modules', '.date-range-runtime-'));
  const window = new happy.Window({ url: 'https://fixture.test' });
  const saved = new Map<string, PropertyDescriptor | undefined>();
  const changes: Range[] = [];
  const globals = { window, document: window.document, navigator: window.navigator, Node: window.Node, Element: window.Element, HTMLElement: window.HTMLElement, HTMLButtonElement: window.HTMLButtonElement, SVGElement: window.SVGElement, DocumentFragment: window.DocumentFragment, HTMLInputElement: window.HTMLInputElement, HTMLSelectElement: window.HTMLSelectElement, Text: window.Text, Comment: window.Comment, Event: window.Event, CustomEvent: window.CustomEvent, MutationObserver: window.MutationObserver, ResizeObserver: window.ResizeObserver, getComputedStyle: window.getComputedStyle.bind(window), requestAnimationFrame: window.requestAnimationFrame.bind(window), cancelAnimationFrame: window.cancelAnimationFrame.bind(window) };
  for (const [key, value] of Object.entries(globals)) { saved.set(key, Object.getOwnPropertyDescriptor(globalThis, key)); Object.defineProperty(globalThis, key, { configurable: true, writable: true, value }); }
  let runtime: any;
  let host: any;
  const target = window.document.createElement('main'); window.document.body.append(target);
  async function cleanup() {
    if (host) await runtime.unmount(host);
    window.happyDOM.abort();
    for (const [key, descriptor] of saved) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete (globalThis as any)[key]; }
    rmSync(directory, { recursive: true, force: true });
  }
  try {
    writeFileSync(join(directory, 'runtime.js'), await compiledBundle());
    runtime = await import(join(directory, 'runtime.js'));
    host = runtime.mount(runtime.Host, { target, props: { props: { label: 'Date range', applyLabel: 'Apply', clearLabel: 'All', onChange: (range: Range) => changes.push(range), ...props } } });
    async function settle() { for (let i = 0; i < 20; i++) { await Promise.resolve(); runtime.flushSync(); } }
    await settle();
    function button(text: string): HTMLButtonElement {
      const found = [...target.querySelectorAll('button')].find((element) => element.textContent?.trim() === text);
      if (!found) throw new Error(`Missing button: ${text}`);
      return found as HTMLButtonElement;
    }
    function day(date: string): HTMLElement {
      const found = target.querySelector(`[data-range-calendar-day][data-value="${date}"]`);
      if (!found) throw new Error(`Missing calendar day: ${date}`);
      return found as HTMLElement;
    }
    const trigger = () => target.querySelector('.kiriof-date-range-trigger') as HTMLButtonElement;
    async function toggle() { trigger().click(); await settle(); }
    async function select(date: string) {
      // A real pointer hovers before clicking. bits-ui uses that highlighted
      // date to distinguish completing a one-day range from deselection.
      day(date).dispatchEvent(new window.MouseEvent('mouseenter'));
      await settle(); day(date).click(); await settle();
    }
    async function navigate(direction: 'next' | 'prev') {
      const element = target.querySelector(`[data-range-calendar-${direction}-button]`) as HTMLButtonElement;
      if (!element) throw new Error(`Missing ${direction} month button`);
      element.click(); await settle();
    }
    // Count actual selected dates, not weeks/grid padding. Both ends are inclusive.
    const numberDays = () => new Set([...target.querySelectorAll('[data-range-calendar-day][data-selected]')].map((element) => element.getAttribute('data-value'))).size;
    return { target, changes, settle, cleanup, trigger, button, day, toggle, select, navigate, numberDays, update: async (next: Record<string, unknown>) => { host.update(next); await settle(); } };
  } catch (error) { await cleanup(); throw error; }
}

function expectRange(actual: Range[], dateFrom: string, dateTo: string) {
  expect(actual).toEqual([{ date_from: dateFrom, date_to: dateTo, month: '' }]);
  expect(actual[0].date_from).toMatch(/^\d{4}-\d{2}-\d{2}$/);
  expect(actual[0].date_to).toMatch(/^\d{4}-\d{2}-\d{2}$/);
}

describe('DateRangeFilter (compiled official shadcn RangeCalendar / bits-ui)', () => {
  test('restores the initial applied range and submits inclusive plain dates, clearing legacy month', async () => {
    const h = await fixture({ dateFrom: '2024-02-10', dateTo: '2024-02-14', month: '2024-01' }); try {
      expect(h.trigger().textContent).toBe('2024-02-10 – 2024-02-14');
      expect(h.trigger().getAttribute('aria-label')).toBe('Date range');
      expect(h.target.querySelector('[data-range-calendar-root]')).toBeNull();
      await h.toggle();
      expect(h.day('2024-02-10').hasAttribute('data-range-start')).toBe(true);
      expect(h.day('2024-02-14').hasAttribute('data-range-end')).toBe(true);
      expect(h.numberDays()).toBe(5);
      expect(h.button('Apply').disabled).toBe(false);
      expect(h.changes).toEqual([]);
      h.button('Apply').click(); await h.settle();
      expectRange(h.changes, '2024-02-10', '2024-02-14');
      expect(h.target.querySelector('[data-slot="popover-content"]')).toBeNull();
    } finally { await h.cleanup(); }
  });

  test('a single date requires a completed range, then counts as exactly one inclusive day', async () => {
    const h = await fixture({ dateFrom: '2024-02-10' }); try {
      await h.toggle();
      expect(h.button('Apply').disabled).toBe(true);
      h.button('Apply').click(); await h.settle(); expect(h.changes).toEqual([]);
      // With a restored start-only value, the first click supplies its end.
      await h.select('2024-02-12');
      expect(h.button('Apply').disabled).toBe(false);
      // Once complete, clicking its endpoint deselects the entire range.
      await h.select('2024-02-12');
      expect(h.button('Apply').disabled).toBe(true);
      expect(h.numberDays()).toBe(0); // Clicking the completed end deselects it.
      await h.select('2024-02-12');
      expect(h.button('Apply').disabled).toBe(true);
      await h.select('2024-02-12');
      expect(h.button('Apply').disabled).toBe(false);
      expect(h.numberDays()).toBe(1);
      h.button('Apply').click(); await h.settle();
      expectRange(h.changes, '2024-02-12', '2024-02-12');
    } finally { await h.cleanup(); }
  });

  test('cross-month selection includes leap day and both endpoints without emitting drafts', async () => {
    const h = await fixture({ dateFrom: '2024-02-10', dateTo: '2024-02-14', month: '2023-12' }); try {
      await h.toggle(); await h.select('2024-02-28');
      expect(h.button('Apply').disabled).toBe(true);
      await h.navigate('next'); await h.select('2024-03-02');
      expect(h.day('2024-02-28').hasAttribute('data-range-start')).toBe(true);
      expect(h.day('2024-02-29').hasAttribute('data-selected')).toBe(true);
      expect(h.day('2024-03-02').hasAttribute('data-range-end')).toBe(true);
      expect(h.numberDays()).toBe(4);
      expect(h.changes).toEqual([]);
      expect(h.trigger().textContent).toBe('2024-02-10 – 2024-02-14');
      h.button('Apply').click(); await h.settle();
      expectRange(h.changes, '2024-02-28', '2024-03-02');
    } finally { await h.cleanup(); }
  });

  test('reverse-order selection normalizes endpoints and numberDays stays inclusive', async () => {
    const h = await fixture({ dateFrom: '2024-02-10', dateTo: '2024-02-14' }); try {
      await h.toggle(); await h.select('2024-02-20'); await h.select('2024-02-18');
      expect(h.numberDays()).toBe(3);
      h.button('Apply').click(); await h.settle();
      expectRange(h.changes, '2024-02-18', '2024-02-20');
    } finally { await h.cleanup(); }
  });

  test('All clears both date endpoints and legacy month, even for an incomplete draft', async () => {
    const h = await fixture({ dateFrom: '2024-02-10', month: '2024-02' }); try {
      await h.toggle(); expect(h.button('Apply').disabled).toBe(true);
      h.button('All').click(); await h.settle();
      expect(h.changes).toEqual([{ date_from: '', date_to: '', month: '' }]);
      expect(h.target.querySelector('[data-slot="popover-content"]')).toBeNull();
      await h.update({ dateFrom: '', dateTo: '', month: '' });
      expect(h.trigger().textContent).toBe('Date range');
      await h.toggle(); expect(h.numberDays()).toBe(0); expect(h.button('Apply').disabled).toBe(true);
    } finally { await h.cleanup(); }
  });

  test('closing discards uncommitted drafts and reopening reloads current parent props', async () => {
    const h = await fixture({ dateFrom: '2024-02-10', dateTo: '2024-02-14' }); try {
      await h.toggle(); await h.select('2024-02-18'); await h.select('2024-02-20');
      expect(h.numberDays()).toBe(3); await h.toggle(); expect(h.changes).toEqual([]);
      await h.toggle();
      expect(h.day('2024-02-10').hasAttribute('data-range-start')).toBe(true);
      expect(h.day('2024-02-14').hasAttribute('data-range-end')).toBe(true);
      expect(h.numberDays()).toBe(5);
      await h.select('2024-02-22'); expect(h.button('Apply').disabled).toBe(true);
      await h.toggle();
      await h.update({ dateFrom: '2024-03-04', dateTo: '2024-03-06', month: '' });
      expect(h.trigger().textContent).toBe('2024-03-04 – 2024-03-06');
      await h.toggle();
      expect(h.day('2024-03-04').hasAttribute('data-range-start')).toBe(true);
      expect(h.day('2024-03-06').hasAttribute('data-range-end')).toBe(true);
      expect(h.numberDays()).toBe(3); expect(h.changes).toEqual([]);
    } finally { await h.cleanup(); }
  });

  test('disabled native trigger cannot open or emit a change; legacy month remains the closed label', async () => {
    const h = await fixture({ disabled: true, month: '2024-02' }); try {
      expect(h.trigger().disabled).toBe(true); expect(h.trigger().textContent).toBe('2024-02');
      await h.toggle(); expect(h.target.querySelector('[data-slot="popover-content"]')).toBeNull(); expect(h.changes).toEqual([]);
      await h.update({ disabled: false }); await h.toggle();
      expect(h.button('Apply').disabled).toBe(true); expect(h.numberDays()).toBe(0);
    } finally { await h.cleanup(); }
  });

  test('invalid stored dates recover to an empty incomplete calendar', async () => {
    const h = await fixture({ dateFrom: 'not-a-date', dateTo: '2024-02-14' }); try {
      await h.toggle(); expect(h.numberDays()).toBe(0); expect(h.button('Apply').disabled).toBe(true); expect(h.changes).toEqual([]);
    } finally { await h.cleanup(); }
  });

  test('bundle is compiled once but each Window has isolated DOM and selection state', async () => {
    const first = await fixture({ dateFrom: '2024-02-10', dateTo: '2024-02-14' });
    let firstDocument: Document;
    try { firstDocument = first.target.ownerDocument; await first.toggle(); await first.select('2024-02-22'); } finally { await first.cleanup(); }
    const second = await fixture({ dateFrom: '2024-03-04', dateTo: '2024-03-06' }); try {
      expect(second.target.ownerDocument).not.toBe(firstDocument!); expect(second.changes).toEqual([]);
      await second.toggle(); expect(second.numberDays()).toBe(3); expect(second.button('Apply').disabled).toBe(false);
      expect(second.day('2024-03-04').ownerDocument).toBe(second.target.ownerDocument);
      expect(bundleBuildCount).toBe(1);
    } finally { await second.cleanup(); }
  });

  test('WordPress trigger borders declare their own solid style without relying on Tailwind preflight', () => {
    // Source guard only: happy-dom does not implement Tailwind layout. CSS lives
    // in the main admin stylesheet and must survive WP's border-style defaults.
    const css = readFileSync(join(root, 'src/styles/admin-list.css'), 'utf8');
    const trigger = css.match(/\.kiriof-admin-list-app \.kiriof-date-range-trigger\s*\{([^}]+)\}/)?.[1];
    expect(trigger).toBeDefined();
    expect(trigger).toContain('!border '); expect(trigger).toContain('!border-solid'); expect(trigger).toContain('!border-border');
    expect(css).toMatch(/\.kiriof-date-range-popover table\s*\{[^}]*!border-0/);
  });
});
