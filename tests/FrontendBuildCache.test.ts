import { afterEach, describe, expect, test } from 'bun:test';
import {
  copyFileSync,
  existsSync,
  mkdirSync,
  mkdtempSync,
  readFileSync,
  rmSync,
  symlinkSync,
  utimesSync,
  writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import {
  dependencyInventory,
  forceRequested,
  frontendInputKey,
  runCachedFrontend,
} from '../scripts/frontend-cache.mjs';

const roots: string[] = [];
const projectRoot = path.resolve(import.meta.dir, '..');
function put(root: string, file: string, value = file) {
  mkdirSync(path.dirname(path.join(root, file)), { recursive: true });
  writeFileSync(path.join(root, file), value);
}
function fixture() {
  const root = mkdtempSync(path.join(tmpdir(), 'kiriof-frontend-cache-'));
  roots.push(root);
  put(root, 'package.json', JSON.stringify({ dependencies: { vite: '1' } }));
  put(root, 'node_modules/vite/package.json', JSON.stringify({ name: 'vite', version: '1' }));
  put(root, 'bun.lock');
  put(root, 'src/buyer/entries/state.ts');
  put(root, 'src/lib/shared.ts');
  put(root, 'vite.buyer.config.ts');
  put(root, 'scripts/build-buyer.mjs');
  return root;
}
function runner(root: string) {
  let calls = 0;
  let status = 0;
  const options = {
    root,
    name: 'buyer-build',
    outputDirectories: ['assets/buyer/dist'],
    requiredOutputs: ['assets/buyer/dist/state.js'],
    log: () => {},
    run: () => {
      calls++;
      rmSync(path.join(root, 'assets/buyer/dist'), { recursive: true, force: true });
      put(root, 'assets/buyer/dist/state.js', '(function(){window.state=true})();');
      put(root, 'assets/buyer/dist/state.css', '.buyer{color:red}');
      return status;
    },
  };
  return {
    options,
    run: (force = false) => runCachedFrontend({ ...options, force }),
    calls: () => calls,
    fail: (value: number) => (status = value),
  };
}
afterEach(() => {
  for (const root of roots.splice(0)) rmSync(root, { recursive: true, force: true });
});

describe('content-addressed frontend receipts (offline, no production builds)', () => {
  test('unchanged successful builds skip the command and preserve output bytes and mtimes', () => {
    const root = fixture();
    const build = runner(root);
    expect(build.run()).toEqual({ cached: false, status: 0 });
    const output = path.join(root, 'assets/buyer/dist/state.js');
    const old = new Date('2001-01-01');
    utimesSync(output, old, old);
    expect(build.run()).toEqual({ cached: true });
    expect(build.calls()).toBe(1);
    expect(readFileSync(output, 'utf8')).toBe('(function(){window.state=true})();');
    // Cache validation is content-addressed, not timestamp-addressed.
    put(root, 'src/lib/shared.ts', 'same length change');
    utimesSync(path.join(root, 'src/lib/shared.ts'), old, old);
    expect(build.run().cached).toBe(false);
  });

  test.each([
    'src/buyer/entries/extra.ts',
    'src/lib/unimported.svelte',
    'src/styles/shared.css',
    'assets/buyer/js/checkout/canonical.js',
    'assets/buyer/css/canonical.css',
    'vite.buyer.config.ts',
    'vite.config.ts',
    'svelte.config.js',
    'tsconfig.json',
    'components.json',
    'scripts/build-buyer.mjs',
    'scripts/frontend-cache.mjs',
    'scripts/new-check.mjs',
    'bun.lock',
    'tests/new-test.ts',
    'inc/Enqueue.php',
    'templates/front/checkout.php',
    'kiriminaja.php',
    'lang/messages.po',
    '.env.production',
  ])('source additions and content changes invalidate: %s', (file) => {
    const root = fixture();
    const build = runner(root);
    build.run();
    put(root, file, 'new input');
    expect(build.run().cached).toBe(false);
    expect(build.run().cached).toBe(true);
    put(root, file, 'new bytes');
    expect(build.run().cached).toBe(false);
    rmSync(path.join(root, file));
    expect(build.run().cached).toBe(false);
  });

  test('output JS/CSS byte tampering, additions, removals and directories invalidate', () => {
    const root = fixture();
    const build = runner(root);
    build.run();
    for (const file of ['state.js', 'state.css']) {
      put(root, `assets/buyer/dist/${file}`, 'tampered');
      expect(build.run().cached).toBe(false);
      rmSync(path.join(root, `assets/buyer/dist/${file}`));
      expect(build.run().cached).toBe(false);
    }
    put(root, 'assets/buyer/dist/unexpected.js');
    expect(build.run().cached).toBe(false);
    mkdirSync(path.join(root, 'assets/buyer/dist/extra-directory'));
    expect(build.run().cached).toBe(false);
    symlinkSync(path.join(root, 'bun.lock'), path.join(root, 'assets/buyer/dist/linked.js'));
    expect(build.run().cached).toBe(false);
    rmSync(path.join(root, 'assets/buyer/dist'), { recursive: true });
    expect(build.run().cached).toBe(false);
  });

  test('ignores dependency payloads and generated outputs, not dependency metadata', () => {
    const root = fixture();
    const before = frontendInputKey(root);
    for (const file of [
      'node_modules/vite/dist/index.js',
      'node_modules/.cache/kiriof/receipt.json',
      'assets/buyer/dist/generated.js',
      'assets/admin/dist/generated.js',
      'playwright-report/index.html',
      'test-results/results.json',
    ])
      put(root, file);
    expect(frontendInputKey(root)).toBe(before);
    put(root, 'node_modules/vite/package.json', JSON.stringify({ name: 'vite', version: '2' }));
    expect(frontendInputKey(root)).not.toBe(before);
  });

  test('tracks transitive, peer and optional installed package metadata without walking modules', () => {
    const root = fixture();
    put(
      root,
      'node_modules/vite/package.json',
      JSON.stringify({
        name: 'vite',
        version: '1',
        dependencies: { transitive: '1' },
        peerDependencies: { peer: '1' },
        optionalDependencies: { optional: '1' },
      }),
    );
    put(
      root,
      'node_modules/transitive/package.json',
      JSON.stringify({ name: 'transitive', version: '1' }),
    );
    put(root, 'node_modules/peer/package.json', JSON.stringify({ name: 'peer', version: '1' }));
    const before = frontendInputKey(root);
    put(
      root,
      'node_modules/optional/package.json',
      JSON.stringify({ name: 'optional', version: '1' }),
    );
    expect(frontendInputKey(root)).not.toBe(before);
    put(
      root,
      'node_modules/transitive/package.json',
      JSON.stringify({ name: 'transitive', version: '2' }),
    );
    expect(dependencyInventory(root).some(([file]) => file.includes('transitive'))).toBe(true);
    rmSync(path.join(root, 'node_modules/peer/package.json'));
    expect(() => frontendInputKey(root)).toThrow('Required frontend dependency is missing: peer');
  });

  test('missing packages fail closed even with a valid receipt and existing outputs', () => {
    const root = fixture();
    const build = runner(root);
    build.run();
    rmSync(path.join(root, 'node_modules/vite/package.json'));
    expect(build.run().cached).toBe(false);
    expect(build.run().cached).toBe(false);
    expect(existsSync(path.join(root, 'node_modules/.cache/kiriof/buyer-build.json'))).toBe(false);
  });

  test('force flags bypass a valid receipt', () => {
    const build = runner(fixture());
    build.run();
    expect(build.run(true).cached).toBe(false);
    expect(build.run().cached).toBe(true);
    expect(forceRequested(['--force'], {})).toBe(true);
    expect(forceRequested([], { KIRIOF_BUILD_FORCE: '1' })).toBe(true);
    expect(forceRequested([], { KIRIOF_BUILD_FORCE: '0' })).toBe(false);
  });

  test('environment and command context affect identity', () => {
    const root = fixture();
    expect(frontendInputKey(root, {}, { MODE: 'a' })).not.toBe(
      frontendInputKey(root, {}, { MODE: 'b' }),
    );
    expect(frontendInputKey(root, { command: ['check'] }, {})).not.toBe(
      frontendInputKey(root, { command: ['lint'] }, {}),
    );
    expect(frontendInputKey(root, {}, {})).toBe(
      frontendInputKey(root, {}, { KIRIOF_BUILD_FORCE: '1' }),
    );
  });

  test('failed or throwing rebuilds invalidate older receipts; corrupt receipts are misses', () => {
    const root = fixture();
    const build = runner(root);
    build.run();
    build.fail(7);
    expect(build.run(true)).toEqual({ cached: false, status: 7 });
    expect(build.run().status).toBe(7);
    build.fail(0);
    build.run();
    expect(() =>
      runCachedFrontend({
        ...build.options,
        force: true,
        run: () => {
          throw new Error('failed');
        },
      }),
    ).toThrow('failed');
    expect(build.run().cached).toBe(false);
    put(root, 'node_modules/.cache/kiriof/buyer-build.json', '{broken');
    expect(build.run().cached).toBe(false);
  });

  test('does not certify missing required output or inputs edited during execution', () => {
    const root = fixture();
    const build = runner(root);
    runCachedFrontend({
      ...build.options,
      run() {
        build.options.run();
        put(root, 'src/lib/shared.ts', 'edited during build');
        return 0;
      },
    });
    expect(build.run().cached).toBe(false);
    runCachedFrontend({
      ...build.options,
      force: true,
      run() {
        build.options.run();
        rmSync(path.join(root, 'assets/buyer/dist/state.js'));
        return 0;
      },
    });
    expect(build.run().cached).toBe(false);
  });

  test('refuses concurrent cache owners and releases locks after failures', () => {
    const root = fixture();
    const build = runner(root);
    mkdirSync(path.join(root, 'node_modules/.cache/kiriof/buyer-build.lock'), { recursive: true });
    expect(() => build.run()).toThrow('is locked');
    rmSync(path.join(root, 'node_modules/.cache/kiriof/buyer-build.lock'), { recursive: true });
    expect(build.run().cached).toBe(false);
  });

  test('real orchestrator keeps state first, fixed CLI/output paths and all-entry rebuilds', () => {
    const root = fixture();
    for (const name of ['build-buyer.mjs', 'frontend-cache.mjs']) {
      copyFileSync(path.join(projectRoot, 'scripts', name), path.join(root, 'scripts', name));
    }
    for (const entry of ['tracking', 'account-shipping', 'classic'])
      put(root, `src/buyer/entries/${entry}.ts`);
    // A fake Vite executable records the unmodified production invocation. No
    // compiler, network, plugins or production output are touched by this test.
    put(
      root,
      'node_modules/vite/bin/vite.js',
      `
      const fs = require('node:fs');
      fs.appendFileSync('node_modules/invocations', JSON.stringify(process.argv.slice(2)) + '\\n');
      if (process.env.FAIL_VITE === '1') process.exit(9);
      const mode = process.argv.at(-1);
      fs.mkdirSync('assets/buyer/dist', { recursive: true });
      fs.writeFileSync('assets/buyer/dist/kiriminaja-buyer-' + mode + '.js', '(function(){})();');
      fs.writeFileSync('assets/buyer/dist/kiriminaja-buyer-' + mode + '.css', '.buyer{}');
    `,
    );
    const run = (args: string[] = [], extraEnv = {}) =>
      spawnSync(process.execPath, ['scripts/build-buyer.mjs', ...args], {
        cwd: root,
        env: { ...process.env, ...extraEnv },
        encoding: 'utf8',
      });
    const calls = () =>
      readFileSync(path.join(root, 'node_modules/invocations'), 'utf8')
        .trim()
        .split('\n')
        .map((line) => JSON.parse(line));
    const first = run();
    expect(first.status).toBe(0);
    expect(calls()).toEqual(
      ['state', 'account-shipping', 'classic', 'tracking'].map((entry) => [
        'build',
        '--config',
        'vite.buyer.config.ts',
        '--mode',
        entry,
      ]),
    );
    expect(run().stdout).toContain('cache hit');
    expect(calls()).toHaveLength(4);
    put(root, 'assets/buyer/css/canonical.css', '.canonical{}');
    expect(run().status).toBe(0);
    expect(calls()).toHaveLength(8);
    expect(readFileSync(path.join(root, 'assets/buyer/css/canonical.css'), 'utf8')).toBe(
      '.canonical{}',
    );
    expect(run(['--force']).status).toBe(0);
    expect(calls()).toHaveLength(12);
    expect(run([], { KIRIOF_BUILD_FORCE: '1' }).status).toBe(0);
    expect(calls()).toHaveLength(16);
    expect(run([], { FAIL_VITE: '1' }).status).toBe(9);
    expect(run().stdout).not.toContain('cache hit');
    expect(calls()).toHaveLength(21);
  });
});
