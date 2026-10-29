export function isFunnelKitCheckout(root: ParentNode): boolean {
  const marker = root.querySelector<HTMLInputElement>('[name="_wfacp_post_id"]');
  return Boolean(marker && !marker.disabled && /^[1-9][0-9]*$/.test(marker.value));
}

/** FunnelKit's billing toggle does not change its primary shipping destination. */
export function usesShippingAddress(root: ParentNode): boolean {
  if (isFunnelKitCheckout(root) && root.querySelector('[name="shipping_country"]')) {
    const flag = root.querySelector<HTMLInputElement>(
      '[name="wfacp_billing_same_as_shipping"], [name="billing_same_as_shipping"]',
    );
    if (flag && !flag.disabled && ['0', '1'].includes(flag.value)) return true;
    if (!flag && !root.querySelector('[name="billing_address_1"]')) return true;
  }
  const field = root.querySelector<HTMLInputElement>(
    '[name="ship_to_different_address"], #ship-to-different-address-checkbox',
  );
  if (!field || field.disabled) return false;
  return field.type === 'checkbox' || field.type === 'radio'
    ? field.checked
    : field.value !== '' && field.value !== '0';
}
