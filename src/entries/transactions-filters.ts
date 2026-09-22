import { mount } from 'svelte';
import TransactionFilters from '../lib/transactions/TransactionFilters.svelte';
import type { TransactionFiltersBootstrap } from '../lib/transactions/types';
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
}
