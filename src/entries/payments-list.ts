import { mount } from 'svelte';
import PaymentsList from '../lib/payments/PaymentsList.svelte';
import type { PaymentsBootstrap } from '../lib/payments/types';
import '../styles/admin-list.css';

const host = document.querySelector<HTMLElement>('[data-kiriof-payments-root]');
const payload = document.querySelector<HTMLScriptElement>('[data-kiriof-payments-payload]');
if (host && payload?.textContent) {
  const bootstrap = JSON.parse(payload.textContent) as PaymentsBootstrap;
  mount(PaymentsList, { target: host, props: { bootstrap } });
  host
    .closest<HTMLElement>('[data-kiriof-payments-page]')
    ?.classList.add('kiriof-payments-page--enhanced');
}
