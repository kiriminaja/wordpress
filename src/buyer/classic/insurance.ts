type InsuranceWindow = Window & {
  kiriofClassicInsurance?: { dispose(): void };
  jQuery?: (node: Element) => { trigger(event: string): unknown };
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
      document.removeEventListener('change', change);
      root.removeEventListener('pagehide', bridge.dispose);
      if (root.kiriofClassicInsurance === bridge) delete root.kiriofClassicInsurance;
    },
  };
  document.addEventListener('change', change);
  root.addEventListener('pagehide', bridge.dispose);
  root.kiriofClassicInsurance = bridge;
  return bridge;
}
