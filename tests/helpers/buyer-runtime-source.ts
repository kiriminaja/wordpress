import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';

const root = resolve(import.meta.dir, '../..');
type Entry = 'state' | 'blocks' | 'checkout' | 'map' | 'presentation' | 'coupon';
const bundles = new Map<Entry, Promise<string>>();
/** Build the active TypeScript entry and real Svelte components once per test process.
 * No checked-in legacy JS, network requests, or presentation substitutes are used. */
export function buyerRuntimeSource(entry: Entry): Promise<string> {
  let promise = bundles.get(entry);
  if (!promise) { promise = build(entry); bundles.set(entry, promise); }
  return promise;
}
async function build(entry: Entry): Promise<string> {
  const directory = mkdtempSync(join(root, 'node_modules/.buyer-runtime-'));
  try {
    const boot = { checkout: 'bootBuyerCheckout', map: 'bootBlocksMap', presentation: 'bootAddressPresentation', coupon: 'bootCouponNotice' };
    const file = entry === 'state' || entry === 'blocks' ? join(root, `src/buyer/entries/${entry}.ts`) : join(directory, 'entry.ts');
    if (entry !== 'state' && entry !== 'blocks') {
      const module = entry === 'coupon' ? 'coupon-notice' : entry;
      writeFileSync(file, `${entry === 'map' ? "import {flushSync} from 'svelte'; window.__buyerFlushSync = flushSync;" : ''} import {${boot[entry]}} from '${join(root, `src/buyer/blocks/${module}.ts`)}'; ${boot[entry]}(window);`);
    }
    const result = await Bun.build({ entrypoints: [file], target: 'browser', conditions: ['browser'], format: 'iife', plugins: [{ name: 'buyer-real-svelte', setup(builder) {
      builder.onResolve({ filter: /^\$(lib|buyer)\// }, ({ path }) => ({ path: join(root, 'src', path.slice(1)) }));
      builder.onResolve({ filter: /^svelte$/ }, () => ({ path: join(root, 'node_modules/svelte/src/index-client.js') }));
      builder.onLoad({ filter: /\.svelte\.[jt]s$/ }, ({ path }) => ({ contents: compileModule(path.endsWith('.ts') ? new Bun.Transpiler({ loader: 'ts' }).transformSync(readFileSync(path, 'utf8')) : readFileSync(path, 'utf8'), { filename: path, generate: 'client' }).js.code, loader: 'js' }));
      builder.onLoad({ filter: /\.svelte$/ }, ({ path }) => ({ contents: compile(readFileSync(path, 'utf8'), { filename: path, generate: 'client' }).js.code, loader: 'js' }));
    } }] });
    if (!result.success) throw new Error(result.logs.join('\n'));
    return await result.outputs[0].text();
  } finally { rmSync(directory, { recursive: true, force: true }); }
}
/** Each VM gets its own Svelte runtime and native DOM getters. */
export function buyerBrowserContext(window: any, extra: Record<string, any> = {}) {
  return { window, document: window.document, navigator: window.navigator,
    Node: window.Node, Element: window.Element, HTMLElement: window.HTMLElement,
    Document: window.Document, DocumentFragment: window.DocumentFragment,
    Text: window.Text, Comment: window.Comment, Event: window.Event,
    MutationObserver: window.MutationObserver, ResizeObserver: window.ResizeObserver,
    getComputedStyle: window.getComputedStyle?.bind(window),
    requestAnimationFrame: window.requestAnimationFrame?.bind(window),
    setTimeout: window.setTimeout?.bind(window) || setTimeout,
    clearTimeout: window.clearTimeout?.bind(window) || clearTimeout, queueMicrotask,
    ...extra };
}
