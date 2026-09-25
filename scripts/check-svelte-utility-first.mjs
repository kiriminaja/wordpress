import { readFileSync } from 'node:fs';

const utilityOnlyFiles = [
  'src/lib/transaction-detail/TransactionDetail.svelte',
  'src/lib/ui/KiriofCard.svelte',
  'src/lib/components/ui/button/button.svelte',
  'src/lib/components/ui/card/card-action.svelte',
  'src/lib/components/ui/card/card-title.svelte',
];

for (const file of utilityOnlyFiles) {
  const source = readFileSync(file, 'utf8');
  if (/<style\b|\sstyle=/.test(source)) {
    throw new Error(`${file} must use Tailwind utilities, not inline or component CSS.`);
  }
}

const workspaceStyles = readFileSync('src/styles/admin-list.css', 'utf8');
const sharedStyles = readFileSync('src/styles/kiriof-component.css', 'utf8');
if (!/kiriof-detail-step-progress/.test(sharedStyles)) {
  throw new Error('Shared component keyframes must live in kiriof-component.css.');
}

if (/\.kiriof-transaction-detail/.test(workspaceStyles)) {
  throw new Error(
    'Transaction detail layout must use Tailwind utilities, not admin-list.css selectors.',
  );
}
