import { afterAll, describe, expect, test } from 'bun:test';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { compile, compileModule } from 'svelte/compiler';
import type { OnboardingBootstrap } from '../src/lib/onboarding/types';
import { happy } from './helpers/ui-runtime';

const root = resolve(import.meta.dir, '..');
let directory: string | undefined;
let runtime: Promise<{ html(bootstrap: OnboardingBootstrap): string }> | undefined;
afterAll(() => {
  if (directory) rmSync(directory, { recursive: true, force: true });
});

function bootstrap(adminBase = 'https://fixture.test/wp-admin/'): OnboardingBootstrap {
  return JSON.parse(
    execFileSync(
      'php',
      [join(root, 'tests/fixtures/onboarding-navigation-runtime.php'), adminBase],
      {
        encoding: 'utf8',
      },
    ),
  );
}

// Render the production app and its real shared Button, not a replacement link.
// SSR avoids starting maps, AJAX or a browser for a completion-only regression.
async function buildRuntime() {
  directory = mkdtempSync(join(root, 'node_modules', '.onboarding-navigation-'));
  writeFileSync(
    join(directory, 'entry.ts'),
    `import { render } from 'svelte/server';
import App from '${join(root, 'src/lib/onboarding/OnboardingApp.svelte')}';
export function html(bootstrap) { return render(App, { props: { bootstrap } }).body; }`,
  );
  const result = await Bun.build({
    entrypoints: [join(directory, 'entry.ts')],
    outdir: directory,
    naming: 'runtime.js',
    target: 'bun',
    plugins: [
      {
        name: 'onboarding-production-server',
        setup(builder) {
          builder.onResolve({ filter: /^@tabler\/icons-svelte$/ }, () => ({
            path: join(root, 'node_modules/@tabler/icons-svelte/dist/tabler-icons-svelte.js'),
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
          builder.onLoad({ filter: /\.svelte\.[jt]s$/ }, ({ path }) => ({
            contents: compileModule(
              path.endsWith('.ts')
                ? new Bun.Transpiler({ loader: 'ts' }).transformSync(readFileSync(path, 'utf8'))
                : readFileSync(path, 'utf8'),
              { filename: path, generate: 'server' },
            ).js.code,
            loader: 'js',
          }));
          builder.onLoad({ filter: /\.svelte$/ }, ({ path }) => ({
            contents: compile(readFileSync(path, 'utf8'), { filename: path, generate: 'server' }).js
              .code,
            loader: 'js',
          }));
        },
      },
    ],
  });
  if (!result.success) throw new Error(result.logs.join('\n'));
  return import(join(directory, 'runtime.js'));
}

async function assertCompletionLink(
  data: OnboardingBootstrap,
  expectedHref: string,
  adminBase: string,
) {
  const app = await (runtime ??= buildRuntime());
  const window = new happy.Window({ url: `${adminBase}admin.php?page=kiriminaja-onboarding` });
  try {
    window.document.body.innerHTML = app.html(data);
    const links = [...window.document.querySelectorAll('a')].filter(
      (link) => link.textContent?.trim() === 'Go to KiriminAja',
    );
    expect(links).toHaveLength(1);
    const link = links[0];
    expect(link.getAttribute('href')).toBe(expectedHref);
    expect(link.href).toBe(`${adminBase}admin.php?page=kiriminaja-transaction`);
    expect([...new URL(link.href).searchParams]).toEqual([['page', 'kiriminaja-transaction']]);
    expect(link.hasAttribute('target')).toBe(false);
    expect(link.hasAttribute('download')).toBe(false);
    expect(link.hasAttribute('onclick')).toBe(false);
  } finally {
    window.happyDOM.abort();
  }
}

describe('onboarding completion navigation (production PHP bootstrap and Svelte SSR)', () => {
  for (const adminBase of [
    'https://fixture.test/wp-admin/',
    'https://fixture.test/shop/wp-admin/',
  ]) {
    test(`backend completion link retains the absolute transactions URL under ${adminBase}`, async () => {
      await assertCompletionLink(
        bootstrap(adminBase),
        `${adminBase}admin.php?page=kiriminaja-transaction`,
        adminBase,
      );
    });

    test(`provided absolute backend destination is a same-tab native link under ${adminBase}`, async () => {
      const data = bootstrap(adminBase);
      data.dashboardUrl = `${adminBase}admin.php?page=kiriminaja-transaction`;
      await assertCompletionLink(data, data.dashboardUrl, adminBase);
    });

    for (const missing of ['absent', 'empty'] as const) {
      test(`${missing} dashboard URL falls back to transactions under ${adminBase}`, async () => {
        const data = bootstrap(adminBase);
        if (missing === 'absent') delete data.dashboardUrl;
        else data.dashboardUrl = '';
        await assertCompletionLink(data, 'admin.php?page=kiriminaja-transaction', adminBase);
      });
    }
  }
});
