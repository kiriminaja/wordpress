import { describe, expect, test } from 'bun:test';
import { readFileSync } from 'node:fs';

const app = readFileSync(new URL('../src/lib/transactions/TransactionsApp.svelte', import.meta.url), 'utf8');
const tabsExpression = app.match(/const scopeTabs = \$derived\((\[[\s\S]*?\])\);/)?.[1];
if (!tabsExpression) throw new Error('Missing production workspace tabs view model');
// Evaluate the production tab view model, not a duplicated count mapping.
const scopeTabs = new Function('bootstrap', 'filters', `return ${tabsExpression};`);
const historicalStatusOptions = [
  { value: 'all', label: 'All', count: 200 },
  { value: 'wc-processing', label: 'Waiting for Shipment', count: 89 },
  { value: 'order-issue', label: 'Order Issue', count: 47 },
];
const bootstrap = {
  i18n: { regularDelivery: 'Regular Delivery', instantDelivery: 'Instant Delivery', orderIssue: 'Order Issue' },
  statusOptions: historicalStatusOptions,
  pagination: { total: 47 },
  rows: [],
};

describe('pending workspace tab counts', () => {
  test('uses pending counts without changing historical issue list totals or status counts', () => {
    for (const status of ['all', 'processed', 'order-issue']) {
      const filters = { status, key: 'filtered order', courier: 'jne', cod: '1', print_status: '1', date_from: '2026-01-01', date_to: '2026-01-31' };
      const workspace = { ...bootstrap, filters, deliveryCounts: { regular: 3, instant: 2, issue: 4 } };
      expect(scopeTabs(workspace, filters)).toEqual([
        { value: 'regular', label: 'Regular Delivery', count: 3 },
        { value: 'instant', label: 'Instant Delivery', count: 2 },
        { value: 'order-issue', label: 'Order Issue', count: 4 },
      ]);
      expect(workspace.statusOptions).toEqual(historicalStatusOptions);
      expect(workspace.pagination.total).toBe(47);
    }
  });

  test('legacy bootstraps and explicit zero never fall back to historical issue totals', () => {
    for (const deliveryCounts of [undefined, { regular: 3, instant: 2 }, { regular: 3, instant: 2, issue: 0 }]) {
      const tabs = scopeTabs({ ...bootstrap, deliveryCounts }, { status: 'order-issue' });
      expect(tabs[2]).toEqual({ value: 'order-issue', label: 'Order Issue', count: 0 });
    }
  });
});
