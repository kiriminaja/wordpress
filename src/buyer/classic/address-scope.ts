/** Checkout builders can serialize Woo's separate-address flag as a hidden input. */
export function usesShippingAddress(root: ParentNode): boolean {
  const field = root.querySelector<HTMLInputElement>(
    '[name="ship_to_different_address"], #ship-to-different-address-checkbox',
  );
  if (!field || field.disabled) return false;
  return field.type === 'checkbox' || field.type === 'radio'
    ? field.checked
    : field.value !== '' && field.value !== '0';
}
