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

const scopeMatch = source.match(/function changeScope\(value: string\): void \{([\s\S]*?)\n  \}/);
if (!scopeMatch) throw new Error('changeScope implementation not found');
const switchScope = new Function('window', 'navigate', 'value', `
  let searchTimer = 42;
  let selected = { 'KA-1': true };
  let pickupDialogOpen = true;
  let actionDialog = { type: 'cancel' };
  let printPreviewOpen = true;
  let printPreviewOrderIds = ['KA-1'];
  ${scopeMatch[1]}
  return { searchTimer, selected, pickupDialogOpen, actionDialog, printPreviewOpen, printPreviewOrderIds };
`);

describe('Instant transaction workspace URLs', () => {
  test('enabled Instant tab invokes the actual scope handler and clears stale actions', () => {
    const tabsMatch = source.match(/const scopeTabs = \$derived\((\[[\s\S]*?\])\);/);
    if (!tabsMatch) throw new Error('scopeTabs implementation not found');
    const tabs = new Function('bootstrap', 'orderIssueOption', `return ${tabsMatch[1]}`)(
      { i18n: { regularDelivery: 'Regular', instantDelivery: 'Instant', orderIssue: 'Order Issue' } },
      { count: 2 },
    ) as { value: string; label: string; disabled?: boolean }[];
    expect(tabs.map((tab) => tab.value)).toEqual(['regular', 'instant', 'order-issue']);
    for (const tab of tabs) expect(Boolean(tab.disabled)).toBe(false);
    expect(source).toContain('<WorkspaceTabs value={scopeValue} tabs={scopeTabs} onChange={changeScope} />');

    const instantTab = tabs.find((tab) => tab.value === 'instant')!;
    const cleared: number[] = [];
    const navigations: Record<string, string>[] = [];
    const state = switchScope(
      { clearTimeout: (id: number) => cleared.push(id) },
      (values: Record<string, string>) => navigations.push(values),
      instantTab.value,
    );
    expect(cleared).toEqual([42]);
    expect(state).toEqual({ searchTimer: null, selected: {}, pickupDialogOpen: false, actionDialog: null, printPreviewOpen: false, printPreviewOrderIds: [] });
    expect(navigations).toEqual([{ delivery_type: 'instant', key: '', month: '', status: 'all', cod: '', courier: '', print_status: '' }]);
    const url = build(`${base}&key=stale&month=2025-01&cod=1&courier=jne&print_status=1&per_page=50`, false, navigations[0]);
    expect(url.searchParams.get('delivery_type')).toBe('instant');
    expect(url.searchParams.get('status')).toBe('all');
    expect(url.searchParams.get('cpage')).toBe('1');
    expect(url.searchParams.get('per_page')).toBe('50');
    for (const key of ['key', 'month', 'cod', 'courier', 'print_status']) expect(url.searchParams.has(key)).toBe(false);
  });

  test('scope handler keeps regular and Order Issue Express-only and rejects unknown tabs', () => {
    for (const [value, status] of [['regular', 'all'], ['order-issue', 'order-issue']]) {
      const navigations: Record<string, string>[] = [];
      switchScope({ clearTimeout: () => {} }, (values: Record<string, string>) => navigations.push(values), value);
      expect(navigations).toEqual([{ delivery_type: 'express', key: '', month: '', status, cod: '', courier: '', print_status: '' }]);
    }
    const events: string[] = [];
    switchScope({ clearTimeout: () => events.push('clear') }, () => events.push('navigate'), 'international');
    expect(events).toEqual([]);
  });

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
