import { isFunnelKitCheckout } from './address-scope';

type InsuranceWindow = Window & {
  MutationObserver: typeof MutationObserver;
  kiriofClassicInsurance?: { dispose(): void };
  jQuery?: (node: Element) => {
    trigger(event: string): unknown;
    on?(event: string, callback: () => void): unknown;
    off?(event: string): unknown;
  };
};

/** Use WooCommerce's totals lifecycle even when the delivery-pin adapter is disabled. */
export function startClassicInsurance(root: InsuranceWindow = window as InsuranceWindow) {
  if (root.kiriofClassicInsurance) return root.kiriofClassicInsurance;
  const document = root.document;
  if (
    !root.jQuery ||
    !document.querySelector('form.checkout, form.woocommerce-checkout') ||
    document.querySelector('.wc-block-checkout, .wp-block-woocommerce-checkout')
  )
    return;
  let field: HTMLElement | null = null;
  let anchor: Comment | null = null;
  let queued = false;
  let disposed = false;
  const place = () => {
    if (disposed) return;
    const form = document.querySelector<HTMLFormElement>(
      'form.checkout, form.woocommerce-checkout',
    );
    if (
      !form ||
      !(
        isFunnelKitCheckout(form) ||
        form.closest('.wfacp_main_form, #wfacp_checkout_form, .wfacp_checkout_form')
      )
    )
      return;
    const shipping = form.querySelector<HTMLElement>('.wfacp_shipping_options');
    if (!shipping || !shipping.querySelector('input.shipping_method, select.shipping_method'))
      return;
    const current = form.querySelector<HTMLElement>('#kiriof-classic-insurance-field');
    if (current && current !== field) {
      anchor?.remove();
      field = current;
      anchor = document.createComment('kiriof-insurance-position');
      field.before(anchor);
    }
    if (!field || !field.querySelector('[name="kiriof_insurance"]')) return;
    field.classList.add('kiriof-funnelkit-insurance-field');
    if (shipping.nextElementSibling !== field) shipping.after(field);
  };
  const refresh = () => {
    if (queued || disposed) return;
    queued = true;
    root.queueMicrotask(() => {
      queued = false;
      place();
    });
  };
  const observer = new root.MutationObserver((mutations) => {
    if (mutations.some((mutation) => mutation.type === 'childList')) refresh();
  });
  const change = (event: Event) => {
    const input = event.target as HTMLInputElement | null;
    if (
      input?.tagName !== 'INPUT' ||
      input.type !== 'checkbox' ||
      input.disabled ||
      !['kiriof_insurance', 'kiriof_shipping_insurance'].includes(input.name) ||
      !input.form?.matches('form.checkout, form.woocommerce-checkout')
    )
      return;
    root.jQuery?.(document.body).trigger('update_checkout');
  };
  const bridge = {
    dispose() {
      disposed = true;
      observer.disconnect();
      document.body.removeEventListener('updated_checkout', refresh);
      root.jQuery?.(document.body).off?.('.kiriofInsurancePlacement');
      if (field && anchor?.isConnected) anchor.after(field);
      field?.classList.remove('kiriof-funnelkit-insurance-field');
      anchor?.remove();
      document.removeEventListener('change', change);
      root.removeEventListener('pagehide', bridge.dispose);
      if (root.kiriofClassicInsurance === bridge) delete root.kiriofClassicInsurance;
    },
  };
  document.addEventListener('change', change);
  document.body.addEventListener('updated_checkout', refresh);
  root.jQuery?.(document.body).on?.('updated_checkout.kiriofInsurancePlacement', refresh);
  observer.observe(document.body, { childList: true, subtree: true });
  place();
  root.addEventListener('pagehide', bridge.dispose);
  root.kiriofClassicInsurance = bridge;
  return bridge;
}
