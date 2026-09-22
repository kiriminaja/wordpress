import { mount } from 'svelte';
import TransactionFilters from '../lib/transactions/TransactionFilters.svelte';
import TransactionTable from '../lib/transactions/TransactionTable.svelte';
import type {
  TransactionFiltersBootstrap,
  TransactionTableBootstrap,
} from '../lib/transactions/types';
import '../styles/admin-list.css';

const host = document.querySelector<HTMLElement>('[data-kiriof-transactions-filters-root]');
const payload = document.querySelector<HTMLScriptElement>(
  '[data-kiriof-transactions-filters-payload]',
);
if (host && payload?.textContent) {
  const bootstrap = JSON.parse(payload.textContent) as TransactionFiltersBootstrap;
  mount(TransactionFilters, { target: host, props: { bootstrap } });
  host
    .closest<HTMLElement>('[data-kiriof-transactions-page]')
    ?.classList.add('kiriof-transactions-page--enhanced');
  host
    .closest<HTMLElement>('[data-kiriof-transactions-page]')
    ?.querySelectorAll('[data-kiriof-transactions-controls-fallback]')
    .forEach((fallback) => fallback.remove());
}

const tableHost = document.querySelector<HTMLElement>('[data-kiriof-transactions-table-root]');
const tablePayload = document.querySelector<HTMLScriptElement>(
  '[data-kiriof-transactions-table-payload]',
);
if (tableHost && tablePayload?.textContent) {
  const bootstrap = JSON.parse(tablePayload.textContent) as TransactionTableBootstrap;
  mount(TransactionTable, { target: tableHost, props: { bootstrap } });
  tableHost
    .closest<HTMLElement>('[data-kiriof-transactions-page]')
    ?.classList.add('kiriof-transactions-table--enhanced');
  tableHost
    .closest<HTMLElement>('[data-kiriof-transactions-page]')
    ?.querySelectorAll('[data-kiriof-transactions-table-fallback]')
    .forEach((fallback) => fallback.remove());
}
