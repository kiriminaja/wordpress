import { mount } from 'svelte';
import PickupDetail from '../lib/pickup-detail/PickupDetail.svelte';
import type { PickupDetailBootstrap } from '../lib/pickup-detail/types';
import '../styles/admin-list.css';

const host = document.querySelector<HTMLElement>('[data-kiriof-pickup-detail-root]');
const payload = document.querySelector<HTMLScriptElement>('[data-kiriof-pickup-detail-payload]');
if (host && payload?.textContent) {
  const bootstrap = JSON.parse(payload.textContent) as PickupDetailBootstrap;
  mount(PickupDetail, { target: host, props: { bootstrap } });
  host
    .closest<HTMLElement>('[data-kiriof-pickup-detail-page]')
    ?.classList.add('kiriof-pickup-detail-page--enhanced');
  host
    .closest<HTMLElement>('[data-kiriof-pickup-detail-page]')
    ?.querySelectorAll('[data-kiriof-pickup-detail-fallback]')
    .forEach((fallback) => fallback.remove());
}
