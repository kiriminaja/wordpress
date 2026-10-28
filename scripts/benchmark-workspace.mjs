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
  function visit(key) {
    if (seen.has(key)) return;
    seen.add(key);
    const chunk = manifest[key];
    if (!chunk) return; // Before splitting, all routes live in the entry graph.
    if (chunk.file.endsWith('.js')) files.add(chunk.file);
    for (const dependency of chunk.imports ?? []) visit(dependency);
  }
  keys.forEach(visit);
  let bytes = 0;
  let gzipBytes = 0;
  for (const file of files) {
    const data = readFileSync(resolve(directory, file));
    bytes += data.length;
    gzipBytes += gzipSync(data, { level: 9 }).length;
  }
  return { bytes, gzipBytes, requests: files.size, files: [...files].sort() };
}

console.log(
  JSON.stringify(
    {
      measurement:
        'Unique production JS in entry + selected route static dependency graph; excludes deferred dialogs, CSS, images and HTML. Per-file gzip level 9. Not browser timing.',
      entry: graph([entry]),
      routes: Object.fromEntries(
        Object.entries(routes).map(([route, key]) => [route, graph([entry, key])]),
      ),
    },
    null,
    2,
  ),
);
