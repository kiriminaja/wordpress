import { afterEach, expect, test } from 'bun:test';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { checkReleaseValidation } from '../scripts/check-release-validation.mjs';

const projectRoot = path.resolve(import.meta.dir, '..');
const roots: string[] = [];
const read = (file: string) => readFileSync(path.join(projectRoot, file), 'utf8');
const manifest = JSON.parse(read('package.json'));

afterEach(() => {
  for (const root of roots.splice(0)) rmSync(root, { recursive: true, force: true });
});

test('release checks existing validation before dependencies, build, or publication', () => {
  const workflow = read('.github/workflows/release.yml');
  expect(workflow).toContain('actions: read');
  expect(workflow).toContain('git rev-parse HEAD^{commit}');
  const gate = workflow.indexOf('node scripts/check-release-validation.mjs');
  expect(gate).toBeGreaterThan(0);
  for (const action of [
    'composer install',
    'make zip plain',
    'softprops/action-gh-release',
    '10up/',
  ])
    expect(gate).toBeLessThan(workflow.indexOf(action));
});

const releaseSha = 'a'.repeat(40);
const successfulRun = {
  id: 42,
  run_attempt: 1,
  head_sha: releaseSha,
  event: 'workflow_dispatch',
  status: 'completed',
  conclusion: 'success',
  html_url: 'https://github.com/example/plugin/actions/runs/42',
};
const validationOptions = {
  repository: 'example/plugin',
  sha: releaseSha,
  token: 'offline-token',
};
const runsResponse = (runs: object[]) => new Response(JSON.stringify({ workflow_runs: runs }));

test.each(['pull_request', 'workflow_dispatch'])(
  'release accepts existing successful %s validation for exact SHA',
  async (event) => {
    const run = { ...successfulRun, event };
    const result = await checkReleaseValidation({
      ...validationOptions,
      fetchImpl: async (url: URL, options: RequestInit) => {
        expect(url.pathname).toBe('/repos/example/plugin/actions/workflows/test.yml/runs');
        expect(url.searchParams.get('head_sha')).toBe(releaseSha);
        expect(new Headers(options.headers).get('Authorization')).toBe('Bearer offline-token');
        expect(options.method).toBeUndefined();
        return runsResponse([run]);
      },
    });
    expect(result).toEqual(run);
  },
);

test.each(
  [
    [],
    [{ ...successfulRun, head_sha: 'b'.repeat(40) }],
    [{ ...successfulRun, event: 'push' }],
    [{ ...successfulRun, conclusion: 'failure' }],
    [{ ...successfulRun, status: 'in_progress', conclusion: null }],
    [{ ...successfulRun, conclusion: 'cancelled' }],
    [successfulRun, { ...successfulRun, id: 43, conclusion: 'failure' }],
    [successfulRun, { ...successfulRun, id: 43, status: 'queued', conclusion: null }],
    [successfulRun, { ...successfulRun, run_attempt: 2, conclusion: 'failure' }],
  ].map((runs) => [runs]),
)('release refuses absent, stale, wrong-SHA, or unsuccessful validation %#', async (runs) => {
  await expect(
    checkReleaseValidation({
      ...validationOptions,
      fetchImpl: async () => runsResponse(runs),
    }),
  ).rejects.toThrow('Release blocked');
});

test('release scans paginated results and supports GitHub Enterprise API paths', async () => {
  const pages: string[] = [];
  const result = await checkReleaseValidation({
    ...validationOptions,
    apiUrl: 'https://github.example/api/v3',
    fetchImpl: async (url: URL) => {
      expect(url.pathname).toBe('/api/v3/repos/example/plugin/actions/workflows/test.yml/runs');
      pages.push(url.searchParams.get('page')!);
      return runsResponse(
        pages.length === 1
          ? Array.from({ length: 100 }, () => ({ ...successfulRun, head_sha: 'b'.repeat(40) }))
          : [successfulRun],
      );
    },
  });
  expect(result).toEqual(successfulRun);
  expect(pages).toEqual(['1', '2']);
});

test('release fails closed on API failure or invalid responses', async () => {
  for (const response of [new Response('', { status: 403 }), new Response('{}')])
    await expect(
      checkReleaseValidation({ ...validationOptions, fetchImpl: async () => response }),
    ).rejects.toThrow();
  await expect(
    checkReleaseValidation({
      ...validationOptions,
      fetchImpl: async () => {
        throw new Error('offline');
      },
    }),
  ).rejects.toThrow('offline');
});

test('packaging builds both asset bundles without running validation', () => {
  const result = spawnSync('make', ['-n', 'zip'], { cwd: projectRoot, encoding: 'utf8' });
  expect(result.status).toBe(0);
  expect(result.stdout).toContain('run build');
  expect(manifest.scripts.build).toBe('bun run build:admin && bun run build:buyer');
  expect(result.stdout).not.toMatch(
    /frontend:check|test-cache|vendor\/bin\/paratest|bun test|check-php-literal-escapes/,
  );
  expect(result.stdout).toContain('--exclude=tests/');
});

test('manual suites and explicit PR validation remain available', () => {
  for (const name of ['frontend:check', 'test:js', 'test:js:force']) {
    expect(manifest.scripts[name]).toBeTruthy();
  }
  expect(read('Makefile')).toContain('test-force:');
  const workflow = read('.github/workflows/test.yml');
  expect(workflow).toContain('pull_request:');
  for (const command of [
    'bun run frontend:check',
    'php scripts/check-php-literal-escapes.php',
    'make zip',
    'make test',
  ])
    expect(workflow).toContain(command);
  expect(workflow.indexOf('run: bun run frontend:check')).toBeLessThan(
    workflow.indexOf('make zip'),
  );
  for (const name of ['release', 'staging', 'development']) {
    expect(read(`.github/workflows/${name}.yml`)).not.toMatch(
      /make test|frontend:check|bun run test/,
    );
  }
});

test('legacy pre-commit script succeeds even without Git or Bun', () => {
  const result = spawnSync('/bin/sh', [path.join(projectRoot, 'scripts/frontend-pre-commit.sh')], {
    cwd: tmpdir(),
    env: { PATH: '/nonexistent' },
    encoding: 'utf8',
  });
  expect(result.status).toBe(0);
  expect(result.stdout + result.stderr).toBe('');
});

test.each(['config', 'environment'])(
  'reinstalling hooks ignores hostile Git templates (%s) and preserves unrelated hooks',
  (templateSource) => {
    expect(manifest.scripts.prepare).toBe('simple-git-hooks');
    expect(manifest['simple-git-hooks']).toEqual({ preserveUnused: true });
    const root = mkdtempSync(path.join(tmpdir(), 'kiriof-explicit-testing-'));
    roots.push(root);
    const emptyTemplate = path.join(root, 'empty-template');
    const hostileTemplate = path.join(root, 'hostile-template');
    mkdirSync(emptyTemplate);
    mkdirSync(path.join(hostileTemplate, 'hooks'), { recursive: true });
    writeFileSync(path.join(hostileTemplate, 'hooks/pre-commit'), '#!/bin/sh\nexit 99\n');
    const env = { ...process.env };
    delete env.GIT_TEMPLATE_DIR;
    const gitArgs = [];
    if (templateSource === 'environment') env.GIT_TEMPLATE_DIR = hostileTemplate;
    else gitArgs.push('-c', `init.templateDir=${hostileTemplate}`);
    expect(
      spawnSync('git', [...gitArgs, 'init', '-q', `--template=${emptyTemplate}`, root], { env })
        .status,
    ).toBe(0);
    writeFileSync(path.join(root, 'package.json'), JSON.stringify(manifest));
    mkdirSync(path.join(root, '.git/hooks'), { recursive: true });
    const otherHook = path.join(root, '.git/hooks/commit-msg');
    writeFileSync(otherHook, '#!/bin/sh\nexit 7\n');
    const cli = path.join(projectRoot, 'node_modules/simple-git-hooks/cli.js');
    for (let installation = 0; installation < 2; installation++) {
      const result = spawnSync('node', [cli], {
        cwd: root,
        env: { ...env, SKIP_INSTALL_SIMPLE_GIT_HOOKS: '0' },
        encoding: 'utf8',
      });
      expect(result.status).toBe(0);
      expect(result.stdout + result.stderr).not.toContain('[ERROR]');
      expect(existsSync(path.join(root, '.git/hooks/pre-commit'))).toBe(false);
      expect(readFileSync(otherHook, 'utf8')).toBe('#!/bin/sh\nexit 7\n');
    }
  },
);
