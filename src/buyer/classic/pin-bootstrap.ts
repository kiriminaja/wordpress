import { startClassicPin, type ClassicPinBridge, type ClassicPinWindow } from './pin';

type BootstrapWindow = ClassicPinWindow & {
  MutationObserver: typeof MutationObserver;
  kiriofClassicPinBootstrap?: { dispose(): void };
};

/** Multi-step checkout builders may insert or replace the checkout form after boot. */
export function startClassicPinBootstrap(root: BootstrapWindow = window as BootstrapWindow) {
  if (root.kiriofClassicPinBootstrap) return root.kiriofClassicPinBootstrap;
  let form: HTMLFormElement | null = null;
  let active: ClassicPinBridge | undefined;
  let queued = false;
  let stopped = false;
  const refresh = () => {
    if (stopped) return;
    const next = root.document.querySelector<HTMLFormElement>(
      'form.checkout, form.woocommerce-checkout',
    );
    if (form !== next) {
      active?.dispose();
      active = undefined;
      form = next;
    }
    if (active) {
      active.restore();
      return;
    }
    if (!form?.querySelector('#kiriof_destination_area, #kiriof_shipping_destination_area')) return;
    active = startClassicPin(root);
  };
  const schedule = () => {
    if (queued || stopped) return;
    queued = true;
    root.queueMicrotask(() => {
      queued = false;
      refresh();
    });
  };
  const observer = new root.MutationObserver(schedule);
  const bridge = {
    dispose() {
      stopped = true;
      observer.disconnect();
      active?.dispose();
      root.document.removeEventListener('DOMContentLoaded', refresh);
      root.document.body?.removeEventListener('updated_checkout', schedule);
      root.removeEventListener('pagehide', bridge.dispose);
      if (root.kiriofClassicPinBootstrap === bridge) delete root.kiriofClassicPinBootstrap;
    },
  };
  root.kiriofClassicPinBootstrap = bridge;
  observer.observe(root.document.documentElement, { childList: true, subtree: true });
  root.document.addEventListener('DOMContentLoaded', refresh);
  root.document.body?.addEventListener('updated_checkout', schedule);
  root.addEventListener('pagehide', bridge.dispose);
  refresh();
  return bridge;
}
