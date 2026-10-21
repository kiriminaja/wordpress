import { afterEach, describe, expect, test } from 'bun:test';
import {
  mkdtempSync,
  mkdirSync,
  writeFileSync,
  readFileSync,
  readdirSync,
  rmSync,
  utimesSync,
  symlinkSync,
  cpSync,
} from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import {
  runCachedTests,
  testInputKey,
  defaultRuntimeProbe,
  normalizedEnvironment,
  forceRequested,
  uncacheableCommand,
} from '../scripts/test-cache.mjs';

const roots: string[] = [];
afterEach(() => {
  for (const root of roots.splice(0)) rmSync(root, { recursive: true, force: true });
});
function fixture() {
  const root = mkdtempSync(path.join(os.tmpdir(), 'js-test-cache-'));
  roots.push(root);
  const put = (name: string, content: string) => {
    mkdirSync(path.dirname(path.join(root, name)), { recursive: true });
    writeFileSync(path.join(root, name), content);
  };
  put('package.json', JSON.stringify({ dependencies: { example: '1' } }));
  put(
    'node_modules/example/package.json',
    JSON.stringify({ name: 'example', version: '1', dependencies: { nested: '1' } }),
  );
  put('node_modules/example/index.js', 'export default 1;');
  put(
    'node_modules/example/node_modules/nested/package.json',
    JSON.stringify({ name: 'nested', version: '1' }),
  );
  put('node_modules/example/node_modules/nested/index.js', 'nested');
  put('tests/sample.test.ts', 'sample');
  put('vendor/example.php', '<?php echo 1;');
  put('assets/buyer/dist/buyer.js', 'buyer');
  put('assets/admin/dist/admin.js', 'admin');
  let runs = 0,
    runtime = 'runtime',
    status = 0;
  const options = {
    environment: { PATH: process.env.PATH, CONFIG: 'first' },
    runtimeProbe: () => runtime,
    run: async () => {
      runs++;
      return status;
    },
    log: () => {},
  };
  const command = ['bun', 'test', 'tests/sample.test.ts'];
  const run = (overrides = {}, args = command) =>
    runCachedTests(root, args, { ...options, ...overrides });
  const receiptDir = path.join(root, 'node_modules/.cache/kiriof/tests');
  const receipts = () => readdirSync(receiptDir).filter((name) => name.endsWith('.json'));
  return {
    root,
    put,
    options,
    command,
    run,
    receipts,
    receiptDir,
    runs: () => runs,
    runtime: (value: string) => {
      runtime = value;
    },
    status: (value: number) => {
      status = value;
    },
  };
}

describe('JavaScript success receipts', () => {
  test('forced runs execute without fingerprinting or publishing receipts', async () => {
    const f = fixture();
    await f.run();
    let probes = 0;
    const runtimeProbe = () => {
      probes++;
      throw new Error('force must not fingerprint');
    };
    expect((await f.run({ force: true, runtimeProbe })).status).toBe(0);
    expect(f.receipts()).toHaveLength(0);
    expect(
      (
        await f.run({
          environment: { ...f.options.environment, KIRIOF_TEST_FORCE: '1' },
          runtimeProbe,
        })
      ).status,
    ).toBe(0);
    expect(probes).toBe(0);
    expect(f.runs()).toBe(3);
  });
  test('launcher removal and retargeting invalidate aggregate validation', async () => {
    const f = fixture();
    f.put('node_modules/example/cli.js', 'first launcher');
    f.put('node_modules/example/other.js', 'other launcher');
    mkdirSync(path.join(f.root, 'node_modules/.bin'));
    const launcher = path.join(f.root, 'node_modules/.bin/example');
    symlinkSync('../example/cli.js', launcher);
    await f.run();
    expect((await f.run()).cached).toBe(true);
    rmSync(launcher);
    symlinkSync('../example/other.js', launcher);
    expect((await f.run()).cached).toBe(false);
    rmSync(launcher);
    expect((await f.run()).cached).toBe(false);
  });
  test('generated ZIPs, logs and local browser reports do not invalidate tests', async () => {
    const f = fixture();
    f.put('tests/e2e/README.md', 'browser tests');
    await f.run();
    for (const file of [
      'kiriminaja-official.zip',
      'debug.log',
      'local.tsbuildinfo',
      '.DS_Store',
      'tests/e2e/.e2e-desktop/report.json',
    ]) {
      f.put(file, 'generated output');
    }
    expect((await f.run()).cached).toBe(true);
    expect(f.runs()).toBe(1);
  });
  test('hits unchanged byte inventories, partitions exact command arguments and hashes only secrets', async () => {
    const f = fixture();
    expect((await f.run()).cached).toBe(false);
    expect((await f.run()).cached).toBe(true);
    await f.run({}, [...f.command, '--timeout', '20000']);
    expect(f.runs()).toBe(2);
    expect(f.receipts()).toHaveLength(2);
    expect(readFileSync(path.join(f.receiptDir, f.receipts()[0]), 'utf8')).not.toContain('first');
    expect(
      (await f.run({ environment: { ...f.options.environment, CONFIG: 'other' } })).cached,
    ).toBe(false);
  });
  test.each([
    'tests/sample.test.ts',
    'package.json',
    'vendor/example.php',
    'node_modules/example/index.js',
    'node_modules/example/node_modules/nested/index.js',
    'assets/admin/dist/admin.js',
    'assets/buyer/dist/buyer.js',
  ])('invalidates same-timestamp bytes in %s', async (file) => {
    const f = fixture();
    await f.run();
    const absolute = path.join(f.root, file),
      before = readFileSync(absolute, 'utf8');
    if (file === 'package.json') f.put(file, `${before}\n`);
    else f.put(file, `${before}!`);
    utimesSync(absolute, new Date(0), new Date(0));
    expect((await f.run()).cached).toBe(false);
    expect(f.runs()).toBe(2);
  });
  test('additions/removals and missing generated trees are identities', async () => {
    const f = fixture();
    await f.run();
    f.put('assets/buyer/dist/new.js', 'new');
    expect((await f.run()).cached).toBe(false);
    rmSync(path.join(f.root, 'assets/buyer/dist'), { recursive: true });
    expect((await f.run()).cached).toBe(false);
    expect((await f.run()).cached).toBe(true);
    f.put('tests/added.test.ts', 'new');
    expect((await f.run()).cached).toBe(false);
    rmSync(path.join(f.root, 'vendor/example.php'));
    expect((await f.run()).cached).toBe(false);
  });
  test('runtime/config invalidation; bookkeeping and force are not inputs', async () => {
    const f = fixture();
    await f.run();
    f.runtime('changed');
    expect((await f.run()).cached).toBe(false);
    expect(
      normalizedEnvironment({
        _: 'a',
        SHLVL: '5',
        npm_lifecycle_event: 'test:buyer',
        KIRIOF_BUILD_FORCE: '1',
        KIRIOF_TEST_FORCE: '1',
        CONFIG: 'x',
      }),
    ).toEqual({ CONFIG: 'x' });
    for (const variable of ['KIRIOF_TEST_FORCE', 'KIRIOF_BUILD_FORCE']) {
      expect(forceRequested([], { [variable]: '1' })).toBe(true);
      expect(
        (await f.run({ environment: { ...f.options.environment, [variable]: '1' } })).cached,
      ).toBe(false);
    }
    expect((await f.run({ force: true })).cached).toBe(false);
  });
  test('failure removes old success; no failed run is reused', async () => {
    const f = fixture();
    await f.run();
    f.status(7);
    expect((await f.run({ force: true })).status).toBe(7);
    expect(f.receipts()).toHaveLength(0);
    expect((await f.run()).status).toBe(7);
    expect(f.runs()).toBe(3);
    f.status(0);
    expect((await f.run()).cached).toBe(false);
    expect((await f.run()).cached).toBe(true);
  });
  test('malformed and extra-field receipts never certify success', async () => {
    const f = fixture();
    await f.run();
    const receipt = path.join(f.receiptDir, f.receipts()[0]);
    writeFileSync(receipt, '{');
    expect((await f.run()).cached).toBe(false);
    const saved = JSON.parse(readFileSync(receipt, 'utf8'));
    saved.status = 3;
    writeFileSync(receipt, JSON.stringify(saved));
    expect((await f.run()).cached).toBe(false);
    writeFileSync(receipt, JSON.stringify({ ...saved, status: 0, surprise: true }));
    expect((await f.run()).cached).toBe(false);
  });
  test('post-run edits, throw and signals cannot publish a receipt', async () => {
    const f = fixture();
    await f.run({
      run: () => {
        f.put('tests/sample.test.ts', 'edited');
        return 0;
      },
    });
    expect(f.receipts()).toHaveLength(0);
    await expect(
      f.run({
        run: () => {
          throw new Error('runner failed');
        },
      }),
    ).rejects.toThrow('runner failed');
    expect(readdirSync(f.receiptDir)).toHaveLength(0);
    expect((await f.run({ run: () => ({ status: 0, signal: 'SIGTERM' }) })).signal).toBe('SIGTERM');
    expect(f.receipts()).toHaveLength(0);
    let probeCalls = 0;
    await f.run({
      runtimeProbe: () => {
        if (++probeCalls > 1) throw new Error('gone');
        return 'ok';
      },
    });
    expect(f.receipts()).toHaveLength(0);
  });
  test('missing PHP/native helper or inaccessible runtime bypasses both lookup and publication', async () => {
    const f = fixture();
    await f.run();
    const broken = () => {
      throw new Error('no runtime');
    };
    await f.run({ runtimeProbe: broken });
    await f.run({ runtimeProbe: broken });
    expect(f.receipts()).toHaveLength(0);
    expect(f.runs()).toBe(3);
    expect(() =>
      defaultRuntimeProbe(f.root, [process.execPath, '--version'], f.options.environment),
    ).toThrow();
  });
  test.each([
    '--watch',
    '--hot',
    '-w',
    '-u',
    '--update-snapshots',
    '--coverage',
    '--coverage-reporter=lcov',
    '--reporter=junit',
    '--reporter-outfile=x',
    '--output=report',
    '--snapshot',
    '--seed=5',
  ])('side-effect mode %s always runs and deletes saved receipts', async (flag) => {
    const f = fixture(),
      command = [...f.command, flag];
    expect(uncacheableCommand(command)).toBe(true);
    await f.run({}, command);
    // Derive the exact command namespace from a synthetic old receipt.
    const { createHash } = await import('node:crypto');
    const exact = path.join(
      f.receiptDir,
      `${createHash('sha256').update(JSON.stringify(command)).digest('hex')}.json`,
    );
    writeFileSync(exact, JSON.stringify({ schema: 1, status: 0 }));
    await f.run({}, command);
    expect(f.receipts()).toHaveLength(0);
    expect(f.runs()).toBe(2);
  });
  test('symlinks fail closed and ephemeral node_modules fixtures do not enter the declared dependency closure', async () => {
    const f = fixture();
    await f.run();
    f.put('node_modules/runtime-fixture/temp.js', 'temporary');
    f.put('.e2e-work/temp', 'temporary');
    f.put('.paratest-123/temp', 'temporary');
    expect((await f.run()).cached).toBe(true);
    symlinkSync('sample.test.ts', path.join(f.root, 'tests/link.ts'));
    expect((await f.run()).cached).toBe(false);
    expect(f.receipts()).toHaveLength(0);
  });
  test('serializes concurrent callers and reports abandoned lock after bounded wait', async () => {
    const f = fixture();
    let count = 0;
    const slow = async () => {
      count++;
      await new Promise((resolve) => setTimeout(resolve, 100));
      return 0;
    };
    const results = await Promise.all([f.run({ run: slow }), f.run({ run: slow })]);
    expect(count).toBe(1);
    expect(results.map((result) => result.cached)).toEqual([false, true]);
    const lock = path.join(f.receiptDir, `${f.receipts()[0]}.lock`);
    mkdirSync(lock);
    writeFileSync(
      path.join(lock, 'owner.json'),
      JSON.stringify({ pid: 9999999, identity: 'not verifiable' }),
    );
    await expect(f.run({ lockWaitMs: 20 })).rejects.toMatchObject({ exitCode: 75 });
  });
  test('real command execution forwards exact arguments and exit status; CLI validation and fail-closed helper', async () => {
    const f = fixture();
    f.put(
      'fixture.mjs',
      "if(process.argv[2] !== 'with spaces') process.exit(9); process.exit(Number(process.argv[3]));",
    );
    const { run: _run, ...options } = f.options;
    expect(
      (await runCachedTests(f.root, [process.execPath, 'fixture.mjs', 'with spaces', '6'], options))
        .status,
    ).toBe(6);
    mkdirSync(path.join(f.root, 'scripts'), { recursive: true });
    for (const name of ['test-cache.mjs', 'frontend-cache.mjs'])
      cpSync(path.resolve('scripts', name), path.join(f.root, 'scripts', name));
    const cli = path.join(f.root, 'scripts/test-cache.mjs');
    const node = spawnSync('which', ['node'], { encoding: 'utf8' }).stdout.trim();
    expect(spawnSync(node, [cli, '--bad'], { encoding: 'utf8' }).status).toBe(2);
    const result = spawnSync(node, [cli, '--', node, 'fixture.mjs', 'with spaces', '6'], {
      encoding: 'utf8',
      env: { ...process.env, KIRIOF_TEST_FORCE: '0', KIRIOF_BUILD_FORCE: '0' },
    });
    expect(result.status).toBe(6);
    expect(result.stdout).toContain('cache unavailable');
  });
  test('input helper observes dependency manifests and executable/runtime probe identity', () => {
    const f = fixture();
    const initial = testInputKey(f.root, f.command, f.options);
    f.put(
      'node_modules/example/node_modules/nested/package.json',
      JSON.stringify({ name: 'nested', version: '2' }),
    );
    expect(testInputKey(f.root, f.command, f.options)).not.toBe(initial);
  });
});
