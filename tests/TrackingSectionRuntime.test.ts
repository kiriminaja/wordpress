import { afterAll, expect, test } from 'bun:test';
import { existsSync, statSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import { happy } from './helpers/ui-runtime';
import type { TrackingBootstrap } from '../src/lib/settings/types';

const root = resolve(import.meta.dir, '..');
const bootstrap: TrackingBootstrap = {
  view: 'tracking',
  toolbar: { logoUrl: '', rootUrl: '/settings', rootLabel: 'Settings', title: 'Tracking' },
  pages: [
    {
      id: 1,
      title: 'Track my parcel',
      url: 'https://fixture.test/tracking?one=1&two=2',
      editUrl: '/wp-admin/post.php?post=1&action=edit',
    },
    {
      id: 2,
      title: '<script>alert("title")</script>',
      url: 'https://fixture.test/<img src=x onerror=alert(1)>',
      editUrl: '/wp-admin/post.php?post=2&action=edit',
    },
  ],
  i18n: {
    guideTitle: 'Set up tracking',
    guideSteps: ['Create a page', '<script>alert("guide")</script>', 'Publish the shortcode'],
    pagesTitle: 'Tracking pages',
    emptyTitle: 'No tracking pages',
    emptyDescription: 'Create a page with the tracking shortcode.',
    view: 'View page',
    edit: 'Edit page',
  },
};

let buildDirectory: string | undefined;
let bundlePromise: Promise<Buffer> | undefined;
let bundleBuildCount = 0;
afterAll(() => {
  try {
    if (bundlePromise) expect(bundleBuildCount).toBe(1);
  } finally {
    if (buildDirectory) rmSync(buildDirectory, { recursive: true, force: true });
  }
});

// Fresh evaluation keeps Svelte's cached native DOM getters local to each Window.
function compiledBundle(): Promise<Buffer> {
  return (bundlePromise ??= buildBundle());
}
async function buildBundle(): Promise<Buffer> {
  const directory = (buildDirectory = mkdtempSync(
    join(root, 'node_modules', '.tracking-settings-build-'),
  ));
  bundleBuildCount++;
  writeFileSync(
    join(directory, 'entry.ts'),
    `export {mount, unmount, flushSync} from 'svelte'; export {default as Component} from ${JSON.stringify(join(root, 'src/lib/settings/TrackingSection.svelte'))};`,
  );
  const build = await Bun.build({
    entrypoints: [join(directory, 'entry.ts')],
    outdir: directory,
    naming: 'runtime.js',
    target: 'browser',
    conditions: ['browser'],
    plugins: [
      {
        name: 'production-svelte',
        setup(builder) {
          builder.onResolve({ filter: /^svelte$/ }, () => ({
            path: join(root, 'node_modules/svelte/src/index-client.js'),
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
          builder.onResolve({ filter: /^@tabler\/icons-svelte$/ }, () => ({
            path: join(root, 'node_modules/@tabler/icons-svelte/dist/tabler-icons-svelte.js'),
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

// Evaluate a fresh bundle for each Window so Svelte's cached DOM getters cannot leak.
async function fixture(data: TrackingBootstrap = bootstrap) {
  const bundle = await compiledBundle();
  const window = new happy.Window({ url: 'https://fixture.test' });
  const directory = mkdtempSync(join(root, 'node_modules', '.tracking-settings-runtime-'));
  const saved = new Map<string, PropertyDescriptor | undefined>();
  const globals = {
    window,
    document: window.document,
    navigator: window.navigator,
    Node: window.Node,
    Element: window.Element,
    HTMLElement: window.HTMLElement,
    HTMLMediaElement: window.HTMLMediaElement,
    HTMLInputElement: window.HTMLInputElement,
    SVGElement: window.SVGElement,
    DocumentFragment: window.DocumentFragment,
    Text: window.Text,
    Comment: window.Comment,
    Event: window.Event,
    MutationObserver: window.MutationObserver,
    getComputedStyle: window.getComputedStyle.bind(window),
  };
  for (const [key, value] of Object.entries(globals)) {
    saved.set(key, Object.getOwnPropertyDescriptor(globalThis, key));
    Object.defineProperty(globalThis, key, { configurable: true, writable: true, value });
  }
  let runtime: any, component: any;
  const cleanup = async () => {
    try {
      if (component) await runtime.unmount(component);
    } finally {
      window.happyDOM.abort();
      for (const [key, descriptor] of saved) {
        if (descriptor) Object.defineProperty(globalThis, key, descriptor);
        else delete (globalThis as any)[key];
      }
      rmSync(directory, { recursive: true, force: true });
    }
  };
  try {
    writeFileSync(join(directory, 'runtime.js'), bundle);
    runtime = await import(join(directory, 'runtime.js'));
    const target = window.document.createElement('main');
    window.document.body.append(target);
    component = runtime.mount(runtime.Component, { target, props: { bootstrap: data } });
    runtime.flushSync();
    return { target, window, cleanup };
  } catch (error) {
    await cleanup();
    throw error;
  }
}

test('tracking renders shared themed cards, ordered guidance and labelled shared link buttons', async () => {
  const h = await fixture();
  try {
    const content = h.target.querySelector('.kiriof-settings-content')!;
    for (const token of ['kiriof-shadcn', 'flex', 'flex-col', 'gap-4']) {
      expect(content.classList.contains(token)).toBe(true);
    }
    const cards = content.querySelectorAll('[data-slot="card"]');
    expect(cards).toHaveLength(4);
    for (const card of cards) {
      expect(card.classList.contains('border-border')).toBe(true);
      expect(card.classList.contains('rounded-xl')).toBe(true);
    }
    expect(
      [
        ...content.querySelectorAll(
          ':scope > [data-slot="card"] > [data-slot="card-header"] > [data-slot="card-title"]',
        ),
      ].map((node) => node.textContent),
    ).toEqual([bootstrap.i18n.guideTitle, bootstrap.i18n.pagesTitle]);
    const guide = content.querySelector('ol')!;
    expect(guide.classList.contains('list-decimal')).toBe(true);
    expect(guide.classList.contains('pl-5')).toBe(true);
    for (const token of ['flex', 'flex-col', 'gap-2']) {
      expect(guide.classList.contains(token)).toBe(true);
    }
    expect(content.querySelector('[class*="space-y-"]')).toBeNull();
    expect([...guide.querySelectorAll('li')].map((node) => node.textContent)).toEqual(
      bootstrap.i18n.guideSteps,
    );
    expect(content.querySelector('code')?.textContent).toBe('[kiriminaja-tracking-front-page]');
    const articles = content.querySelectorAll('article');
    expect(articles).toHaveLength(bootstrap.pages.length);
    articles.forEach((article, index) => {
      const page = bootstrap.pages[index]!;
      expect(article.querySelector('[data-slot="card-title"]')?.textContent).toBe(page.title);
      expect(article.querySelector('[data-slot="card-description"]')?.textContent).toBe(page.url);
      const links = article.querySelectorAll('a');
      expect(links).toHaveLength(2);
      const [view, edit] = links;
      expect(view!.textContent?.trim()).toBe(bootstrap.i18n.view);
      expect(view!.getAttribute('href')).toBe(page.url);
      expect(view!.target).toBe('_blank');
      expect(view!.rel.split(' ').sort()).toEqual(['noopener', 'noreferrer']);
      expect(view!.querySelector('svg[data-icon="inline-end"][aria-hidden="true"]')).not.toBeNull();
      expect(edit!.textContent?.trim()).toBe(bootstrap.i18n.edit);
      expect(edit!.getAttribute('href')).toBe(page.editUrl);
      expect(edit!.getAttribute('target')).toBeNull();
      for (const link of links) {
        expect(link.getAttribute('data-slot')).toBe('button');
        expect(link.getAttribute('data-variant')).toBe('outline');
        expect(link.getAttribute('data-size')).toBe('sm');
        expect(link.classList.contains('border-border')).toBe(true);
        expect(link.classList.contains('button')).toBe(false);
        expect(link.classList.contains('button-small')).toBe(false);
      }
    });
    expect(content.querySelector('script, img, [onerror]')).toBeNull();
    expect(content.querySelector('[data-slot="empty"]')).toBeNull();
  } finally {
    await h.cleanup();
  }
});

test('tracking empty pages render shared empty state without page actions or injected markup', async () => {
  const emptyTitle = '<script>alert("empty")</script>';
  const emptyDescription = '<img src=x onerror=alert(1)>';
  const h = await fixture({
    ...bootstrap,
    pages: [],
    i18n: { ...bootstrap.i18n, emptyTitle, emptyDescription },
  });
  try {
    const content = h.target.querySelector('.kiriof-settings-content')!;
    expect(content.querySelectorAll('[data-slot="card"]')).toHaveLength(2);
    expect(content.querySelector('[data-slot="empty-title"]')?.textContent).toBe(emptyTitle);
    expect(content.querySelector('[data-slot="empty-description"]')?.textContent).toBe(
      emptyDescription,
    );
    expect(
      content.querySelector(
        '[data-slot="empty-icon"][data-variant="icon"] svg[aria-hidden="true"]',
      ),
    ).not.toBeNull();
    expect(content.querySelector('article, a, button, script, img, [onerror]')).toBeNull();
    expect(content.querySelectorAll('ol li')).toHaveLength(bootstrap.i18n.guideSteps.length);
  } finally {
    await h.cleanup();
  }
});
