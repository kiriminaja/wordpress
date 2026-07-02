export function kiriofHasWpBlockCheckoutContext(): boolean {
  return !!(
    jQuery(
      ".wp-block-woocommerce-checkout, .wc-block-checkout, .wc-block-components-checkout-place-order-button",
    ).length &&
    typeof wp !== "undefined" &&
    wp.data
  );
}
