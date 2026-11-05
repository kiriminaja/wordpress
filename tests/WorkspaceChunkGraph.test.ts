import { afterAll, beforeAll, describe, expect, test } from 'bun:test';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { resolve } from 'node:path';
import { gzipSync } from 'node:zlib';

type ManifestChunk = {
  file: string;
  imports?: string[];
  dynamicImports?: string[];
  css?: string[];
  assets?: string[];
  isEntry?: boolean;
  isDynamicEntry?: boolean;
};

type Manifest = Record<string, ManifestChunk>;

const root = resolve(import.meta.dir, '..');
const entry = 'src/entries/admin-workspace.ts';
const calendar = 'src/lib/ui/DateRangePanel.svelte';
const routes = [
  {
    name: 'transactions',
    key: 'src/lib/transactions/TransactionsApp.svelte',
    gzipBudget: 170_000,
    dialogs: [
      'src/lib/transactions/InstantProcessDialog.svelte',
      'src/lib/transactions/RequestPickupDialog.svelte',
      'src/lib/transactions/TransactionActionDialogs.svelte',
      'src/lib/transactions/InstantOperationDialog.svelte',
      'src/lib/ui/PrintPreviewDialog.svelte',
    ],
    hasCalendar: true,
  },
  {
    name: 'payments',
    key: 'src/lib/payments/PaymentsList.svelte',
    gzipBudget: 130_000,
    dialogs: [
      'src/lib/payments/PaymentScheduleDialog.svelte',
      'src/lib/payments/ScanToPayDialog.svelte',
      'src/lib/payments/InstantScanToPayDialog.svelte',
    ],
    hasCalendar: true,
  },
  {
    name: 'transaction detail',
    key: 'src/lib/transaction-detail/TransactionDetail.svelte',
    gzipBudget: 125_000,
    dialogs: [
      'src/lib/transactions/TransactionActionDialogs.svelte',
      'src/lib/transactions/InstantOperationDialog.svelte',
      'src/lib/ui/PrintPreviewDialog.svelte',
    ],
    hasCalendar: false,
  },
];

let outputDirectory: string;
let manifest: Manifest;

function chunk(key: string): ManifestChunk {
  const result = manifest[key];
  if (!result) {
    throw new Error(`Production manifest is missing chunk: ${key}`);
  }
  return result;
}

function graph(roots: string[], includeDynamic = false): Set<string> {
  const visited = new Set<string>();
  const pending = [...roots];
  while (pending.length > 0) {
    const key = pending.pop()!;
    if (visited.has(key)) continue;
    const current = chunk(key);
    visited.add(key);
    pending.push(...(current.imports ?? []));
    if (includeDynamic) pending.push(...(current.dynamicImports ?? []));
  }
  return visited;
}

beforeAll(() => {
  outputDirectory = mkdtempSync(resolve(root, 'node_modules/.workspace-chunk-test-'));
  // Use Node, not process.execPath: this suite runs under Bun.
  execFileSync(
    'node',
    [
      resolve(root, 'node_modules/vite/bin/vite.js'),
      'build',
      '--config',
      'vite.config.ts',
      '--outDir',
      outputDirectory,
      '--manifest',
      'workspace-manifest.json',
    ],
    {
      cwd: root,
      env: { ...process.env, NODE_ENV: 'production' },
      timeout: 60_000,
      maxBuffer: 10 * 1024 * 1024,
      stdio: 'pipe',
    },
  );
  manifest = JSON.parse(readFileSync(resolve(outputDirectory, 'workspace-manifest.json'), 'utf8'));
}, 60_000);

afterAll(() => {
  if (outputDirectory) rmSync(outputDirectory, { recursive: true, force: true });
});

for (const route of routes) {
  describe(`${route.name} production chunk graph`, () => {
    test('keeps dialogs and the calendar outside the initial route graph', () => {
      expect(chunk(entry).isEntry).toBe(true);
      expect(chunk(entry).dynamicImports).toContain(route.key);
      const initial = graph([entry, route.key]);
      const initialFiles = new Set([...initial].map((key) => chunk(key).file));
      const deferred = [...route.dialogs, calendar];
      for (const key of deferred) {
        expect(initial.has(key)).toBe(false);
        expect(initialFiles.has(chunk(key).file)).toBe(false);
      }
      const dynamicTargets = new Set(
        [...initial].flatMap((key) => chunk(key).dynamicImports ?? []),
      );
      for (const key of route.dialogs) {
        expect(dynamicTargets.has(key)).toBe(true);
      }
      if (route.hasCalendar) expect(dynamicTargets.has(calendar)).toBe(true);
    });

    test('stays within the initial JavaScript gzip and request budgets', () => {
      const initial = graph([entry, route.key]);
      const files = new Set(
        [...initial].map((key) => chunk(key).file).filter((file) => file.endsWith('.js')),
      );
      const gzipBytes = [...files].reduce(
        (total, file) =>
          total + gzipSync(readFileSync(resolve(outputDirectory, file)), { level: 9 }).length,
        0,
      );
      expect(files.size).toBeGreaterThan(0);
      expect(files.size).toBeLessThanOrEqual(40);
      expect(gzipBytes).toBeLessThan(route.gzipBudget);
    });

    test('emits complete dynamic graphs with mapped dependencies and real files', () => {
      const requested = [...route.dialogs, ...(route.hasCalendar ? [calendar] : [])];
      for (const key of requested) {
        expect(chunk(key).isDynamicEntry).toBe(true);
      }
      const reachable = graph([entry, route.key, ...requested], true);
      for (const key of reachable) {
        const current = chunk(key);
        expect(existsSync(resolve(outputDirectory, current.file))).toBe(true);
        for (const dependency of [...(current.imports ?? []), ...(current.dynamicImports ?? [])]) {
          expect(manifest[dependency]).toBeDefined();
          expect(reachable.has(dependency)).toBe(true);
        }
        for (const asset of [...(current.css ?? []), ...(current.assets ?? [])]) {
          expect(existsSync(resolve(outputDirectory, asset))).toBe(true);
        }
      }
    });
  });
}
