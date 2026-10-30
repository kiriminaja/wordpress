import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { gzipSync } from 'node:zlib';

const directory = resolve(process.argv[2] ?? 'assets/admin/dist');
const manifest = JSON.parse(readFileSync(resolve(directory, 'workspace-manifest.json'), 'utf8'));
const entry = 'src/entries/admin-workspace.ts';
const routes = {
  transactions: 'src/lib/transactions/TransactionsApp.svelte',
  payments: 'src/lib/payments/PaymentsList.svelte',
  'transaction-detail': 'src/lib/transaction-detail/TransactionDetail.svelte',
  'pickup-detail': 'src/lib/pickup-detail/PickupDetail.svelte',
  settings: 'src/lib/SettingsRoot.svelte',
  account: 'src/lib/settings/AccountSection.svelte',
  couriers: 'src/lib/settings/CouriersSection.svelte',
  tracking: 'src/lib/settings/TrackingSection.svelte',
  technical: 'src/lib/settings/TechnicalSection.svelte',
};

function graph(keys) {
  const seen = new Set();
  const files = new Set();
  const css = new Set();
  function visit(key) {
    if (seen.has(key)) return;
    seen.add(key);
    const chunk = manifest[key];
    if (!chunk) return; // Before splitting, all routes live in the entry graph.
    if (chunk.file.endsWith('.js')) files.add(chunk.file);
    for (const file of chunk.css ?? []) css.add(file);
    for (const dependency of chunk.imports ?? []) visit(dependency);
  }
  keys.forEach(visit);
  return { ...measure(files), css: measure(css) };
}

function measure(files) {
  let bytes = 0;
  let gzipBytes = 0;
  for (const file of files) {
    const data = readFileSync(resolve(directory, file));
    bytes += data.length;
    gzipBytes += gzipSync(data, { level: 9 }).length;
  }
  return { bytes, gzipBytes, requests: files.size, files: [...files].sort() };
}

const interactions = {
  'transactions:instant-process': [
    'transactions',
    'src/lib/transactions/InstantProcessDialog.svelte',
  ],
  'transactions:request-pickup': [
    'transactions',
    'src/lib/transactions/RequestPickupDialog.svelte',
  ],
  'transactions:action': ['transactions', 'src/lib/transactions/TransactionActionDialogs.svelte'],
  'transactions:calendar': ['transactions', 'src/lib/ui/DateRangePanel.svelte'],
  'payments:scan-to-pay': ['payments', 'src/lib/payments/ScanToPayDialog.svelte'],
  'payments:instant-scan-to-pay': ['payments', 'src/lib/payments/InstantScanToPayDialog.svelte'],
  'payments:calendar': ['payments', 'src/lib/ui/DateRangePanel.svelte'],
  'transaction-detail:action': [
    'transaction-detail',
    'src/lib/transactions/TransactionActionDialogs.svelte',
  ],
};

const chunks = [...new Set(Object.values(manifest).map((chunk) => chunk.file))]
  .filter((file) => file.endsWith('.js'))
  .map((file) => ({ file, ...measure(new Set([file])) }))
  .sort((left, right) => right.bytes - left.bytes || left.file.localeCompare(right.file));

console.log(
  JSON.stringify(
    {
      measurement:
        'Unique production JS in entry + selected route static dependency graph; excludes deferred dialogs, CSS, images and HTML. Per-file gzip level 9. Not browser timing.',
      entry: graph([entry]),
      routes: Object.fromEntries(
        Object.entries(routes).map(([route, key]) => [route, graph([entry, key])]),
      ),
      interactions: Object.fromEntries(
        Object.entries(interactions).map(([name, [route, key]]) => {
          const initial = graph([entry, routes[route]]);
          const total = graph([entry, routes[route], key]);
          return [
            name,
            {
              additional: measure(
                new Set(total.files.filter((file) => !initial.files.includes(file))),
              ),
              total,
            },
          ];
        }),
      ),
      emittedJavaScript: measure(new Set(chunks.map((chunk) => chunk.file))),
      largestChunks: chunks.slice(0, 10),
    },
    null,
    2,
  ),
);
