import { afterEach, expect, test } from 'bun:test';
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const projectRoot = path.resolve(import.meta.dir, '..');
const roots: string[] = [];
const read = (file: string) => readFileSync(path.join(projectRoot, file), 'utf8');
const manifest = JSON.parse(read('package.json'));

afterEach(() => {
  for (const root of roots.splice(0)) rmSync(root, { recursive: true, force: true });
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

test('reinstalling hooks creates no test hook and preserves unrelated hooks', () => {
  expect(manifest.scripts.prepare).toBe('simple-git-hooks');
  expect(manifest['simple-git-hooks']).toEqual({ preserveUnused: true });
  const root = mkdtempSync(path.join(tmpdir(), 'kiriof-explicit-testing-'));
  roots.push(root);
  expect(spawnSync('git', ['init', '-q', root]).status).toBe(0);
  writeFileSync(path.join(root, 'package.json'), JSON.stringify(manifest));
  const otherHook = path.join(root, '.git/hooks/commit-msg');
  writeFileSync(otherHook, '#!/bin/sh\nexit 7\n');
  const cli = path.join(projectRoot, 'node_modules/simple-git-hooks/cli.js');
  for (let installation = 0; installation < 2; installation++) {
    const result = spawnSync('node', [cli], {
      cwd: root,
      env: { ...process.env, SKIP_INSTALL_SIMPLE_GIT_HOOKS: '0' },
      encoding: 'utf8',
    });
    expect(result.status).toBe(0);
    expect(result.stdout + result.stderr).not.toContain('[ERROR]');
    expect(existsSync(path.join(root, '.git/hooks/pre-commit'))).toBe(false);
    expect(readFileSync(otherHook, 'utf8')).toBe('#!/bin/sh\nexit 7\n');
  }
});
