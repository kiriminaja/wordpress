import { mount } from 'svelte';
import TransactionsApp from '../lib/transactions/TransactionsApp.svelte';
import type { TransactionsBootstrap } from '../lib/transactions/types';
import '../styles/shadcn-onboarding.css';
import '../styles/toolbar.css';
import '../styles/admin-list.css';

const host = document.querySelector<HTMLElement>('[data-kiriof-transactions-root]');
const payload = document.querySelector<HTMLScriptElement>('[data-kiriof-transactions-payload]');

if (host && payload?.textContent) {
  const bootstrap = JSON.parse(payload.textContent) as TransactionsBootstrap;
  mount(TransactionsApp, { target: host, props: { bootstrap } });
  host.removeAttribute('aria-busy');
  host.classList.add('is-mounted');
}
