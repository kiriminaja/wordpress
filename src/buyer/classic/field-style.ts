const properties = [
  'border-radius',
  'border-width',
  'border-style',
  'border-color',
  'background-color',
  'color',
  'font-family',
  'font-size',
  'font-weight',
  'line-height',
  'letter-spacing',
  'padding-top',
  'padding-bottom',
  'padding-inline-start',
  'padding-inline-end',
] as const;

/** Sample only native address inputs; button and select styles can belong to another widget. */
export function syncClassicFieldStyle(select: HTMLSelectElement, wrapper: HTMLElement) {
  const form = select.form;
  const view = select.ownerDocument.defaultView;
  if (!form || !view) return;
  const scope =
    select.id === 'shipping_state' || select.name === 'kiriof_shipping_destination_area'
      ? 'shipping'
      : 'billing';
  const inputs = ['address_1', 'city', 'postcode', 'first_name', 'last_name', 'phone', 'email']
    .map((field) => form.querySelector<HTMLInputElement>(`input[name="${scope}_${field}"]`))
    .filter((input): input is HTMLInputElement => !!input);
  const reference = inputs.find((input) => {
    if (
      input.disabled ||
      input.type === 'hidden' ||
      input === select.ownerDocument.activeElement ||
      input.getAttribute('aria-invalid') === 'true' ||
      input.closest(
        '.kiriof-buyer-selector, .woocommerce-invalid, .woocommerce-validated, .wfacp-invalid, .fkcf-hidden, [hidden]',
      )
    )
      return false;
    const style = view.getComputedStyle(input);
    return (
      style.display !== 'none' &&
      style.visibility === 'visible' &&
      input.getClientRects().length > 0
    );
  });
  // Keep the last neutral sample while the theme temporarily focuses or validates its inputs.
  if (!reference) return;
  const style = view.getComputedStyle(reference);
  for (const property of properties) {
    const value = style.getPropertyValue(property);
    const variable = `--kiriof-field-${property}`;
    if (value && wrapper.style.getPropertyValue(variable) !== value)
      wrapper.style.setProperty(variable, value);
  }
  // offsetHeight is the border-box height without checkout-builder scale transforms.
  const height = reference.offsetHeight;
  if (height > 0) {
    const value = `${height}px`;
    if (wrapper.style.getPropertyValue('--kiriof-field-height') !== value)
      wrapper.style.setProperty('--kiriof-field-height', value);
  }
}
