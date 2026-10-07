import { flushSync, mount, unmount } from 'svelte';
import TrackingDetails from '../components/TrackingDetails.svelte';
import TrackingHistories from '../components/TrackingHistories.svelte';
import { createTrackingController } from './controller';
import { createTrackingState } from './state.svelte';
import type { TrackingBridge, TrackingLabels, TrackingRoot } from './types';

/** Own only native bindings and the two existing rendering containers. */
export function bootTracking(root: TrackingRoot): TrackingBridge {
  if (root.__kiriofTrackingBridge) return root.__kiriofTrackingBridge;
  const document = root.document;
  const previous = root.trackOrder;
  let active = true;
  let controller: ReturnType<typeof createTrackingController> | undefined;
  let input: HTMLInputElement | null = null;
  let components: ReturnType<typeof mount>[] = [];
  const buttons: Element[] = [];
  const click = (event: Event) => {
    event.preventDefault();
    void bridge.trackOrder();
  };
  const bridge: TrackingBridge = {
    async trackOrder() {
      if (active && controller && input) await controller.lookup(input.value);
    },
    dispose() {
      if (!active) return;
      active = false;
      document.removeEventListener('DOMContentLoaded', ready);
      root.removeEventListener('pagehide', bridge.dispose);
      buttons.forEach((button) => button.removeEventListener('click', click));
      controller?.dispose();
      components.forEach((component) => void unmount(component));
      components = [];
      if (root.trackOrder === bridge.trackOrder) root.trackOrder = previous;
      if (root.__kiriofTrackingBridge === bridge) delete root.__kiriofTrackingBridge;
    },
  };
  function ready() {
    if (!active || controller) return;
    const form = document.querySelector('#tracking-result')?.closest('form');
    const details = form?.querySelector('.tracking-details');
    const histories = form?.querySelector('.tracking-table tbody');
    input = form?.querySelector<HTMLInputElement>('[name="order_number"]') || null;
    if (!form || !details || !histories || !input) return;
    const defaults: TrackingLabels = {
      orderNumber: 'Nomor Order',
      awbNumber: 'Nomor Resi',
      courier: 'Kurir',
      notFound: 'Order tidak ditemukan',
    };
    const labels = { ...defaults };
    for (const key of Object.keys(defaults) as (keyof TrackingLabels)[]) {
      const value = root.kiriofTracking?.i18n?.[key];
      if (typeof value === 'string' && value) labels[key] = value;
    }
    const state = createTrackingState();
    const changed = () => {
      for (const phase of ['blank', 'loading', 'success', 'error']) {
        const selector = phase === 'error' ? '.state-err' : `.state-${phase}`;
        form
          .querySelectorAll(selector)
          .forEach((node) => node.classList.toggle('kj-hidden', state.phase !== phase));
      }
      buttons.forEach((button) => button.classList.toggle('kj-hidden', state.phase === 'loading'));
      const message = form.querySelector('#err_msg');
      if (message && state.phase === 'error') message.textContent = state.message;
    };
    details.replaceChildren();
    histories.replaceChildren();
    components = [
      mount(TrackingDetails, { target: details, props: { state, labels } }),
      mount(TrackingHistories, { target: histories, props: { state } }),
    ];
    flushSync();
    controller = createTrackingController(root, state, labels, changed);
    form.querySelectorAll('.track-btn').forEach((button) => {
      buttons.push(button);
      button.addEventListener('click', click);
    });
    const orderId = new URLSearchParams(root.location.search).get('order_id');
    if (orderId) {
      input.value = orderId;
      void bridge.trackOrder();
    }
  }
  root.__kiriofTrackingBridge = bridge;
  root.trackOrder = bridge.trackOrder;
  root.addEventListener('pagehide', bridge.dispose);
  if (document.readyState === 'loading')
    document.addEventListener('DOMContentLoaded', ready, { once: true });
  else ready();
  return bridge;
}
