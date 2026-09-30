import { describe, expect, test } from 'bun:test';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../src/lib/transactions/TransactionsApp.svelte', import.meta.url), 'utf8');
const match = source.match(/function buildUrl\(values: Record<string, string>\): URL \{([\s\S]*?)\n  \}/);
if (!match) throw new Error('buildUrl implementation not found');
// Execute the actual component URL builder without requiring DOM/Svelte mounts.
const builder = new Function('window', 'isInstant', 'bootstrap', 'values', match[1]);
const build = (href: string, instant: boolean, values: Record<string, string>) => builder(
  { location: { href } }, instant, { pagination: { page: 3 } }, values,
) as URL;

const base = 'https://example.test/wp-admin/admin.php?page=kiriminaja-setting&delivery_type=instant&status=processed&cpage=3';

describe('Instant transaction workspace URLs', () => {
  test('pagination, per-page, filters and refresh keep the persisted partition', () => {
    const changes: Record<string, string>[] = [{ cpage: '4' }, { per_page: '50' }, { key: 'KA-1' }, {}];
    for (const values of changes) {
      const url = build(base, true, values);
      expect(url.searchParams.get('delivery_type')).toBe('instant');
      expect(url.searchParams.get('page')).toBe('kiriminaja-setting');
    }
    expect(build(base, true, {}).searchParams.get('cpage')).toBe('3');
    expect(build(base, true, { key: 'KA-1' }).searchParams.get('cpage')).toBe('1');
  });

  test('switching workspaces overwrites the old delivery type and clears filters', () => {
    const url = build(`${base}&key=old&cod=1&courier=gosend&print_status=1&month=2025-01`, true, {
      delivery_type: 'express', status: 'order-issue', key: '', month: '', cod: '', courier: '', print_status: '',
    });
    expect(url.searchParams.get('delivery_type')).toBe('express');
    expect(url.searchParams.get('status')).toBe('order-issue');
    expect(url.searchParams.get('cpage')).toBe('1');
    for (const key of ['key', 'month', 'cod', 'courier', 'print_status']) expect(url.searchParams.has(key)).toBe(false);
    expect(build(url.href, false, { delivery_type: 'instant', status: 'all' }).searchParams.get('delivery_type')).toBe('instant');
  });
});
