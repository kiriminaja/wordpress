import { mount } from 'svelte';
import OrderMetabox from '../lib/order-metabox/OrderMetabox.svelte';

const host = document.querySelector<HTMLElement>('[data-kiriof-order-metabox-root]');
const payload = document.querySelector<HTMLScriptElement>('[data-kiriof-order-metabox-payload]');
if (host && payload?.textContent) {
  const bootstrap = JSON.parse(payload.textContent) as { html: string };
  mount(OrderMetabox, { target: host, props: bootstrap });
  host.parentElement?.querySelector('[data-kiriof-order-metabox-fallback]')?.remove();
}
