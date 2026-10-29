import {
  address,
  addressFields,
  type AddressScope,
  type District,
} from '../state/classic-pin.svelte';
import { syncDistrictAddress } from './district-address';
import { usesShippingAddress } from './address-scope';

export function nativeAddress(form: HTMLFormElement, states: Record<string, string> = {}) {
  const field = (id: string) => form.querySelector<HTMLInputElement | HTMLSelectElement>(`#${id}`);
  const scope = (): AddressScope => (usesShippingAddress(form) ? 'shipping' : 'billing');
  const district = (): District | null => {
    const id =
      scope() === 'shipping' ? 'kiriof_shipping_destination_area' : 'kiriof_destination_area';
    const node = field(id);
    const label = form.querySelector<HTMLInputElement>(`[name="${id}_name"]`);
    const text =
      label?.value ||
      (node instanceof HTMLSelectElement
        ? Array.from(node.options).find((option) => option.value === node.value)?.text
        : '') ||
      '';
    return node && /^[1-9][0-9]*$/.test(node.value) && text
      ? { id: node.value, label: text }
      : null;
  };
  return {
    scope,
    district,
    address: () => {
      syncDistrictAddress(form, states);
      return address(
        Object.fromEntries(
          addressFields.map((key) => [key, field(`${scope()}_${key}`)?.value || '']),
        ),
      );
    },
    collection: () => {
      const inputs = [
        ...form.querySelectorAll<HTMLInputElement>(
          'input.shipping_method:checked, input.shipping_method[type="hidden"]',
        ),
      ];
      return (
        inputs.length > 0 &&
        inputs.every((input) => /^(local_pickup|pickup_location)/.test(input.value))
      );
    },
  };
}
/** The only native DOM mutations: preserve fields/validation while moving existing rows. */
export function placeNativePin(
  form: HTMLFormElement,
  panel: HTMLElement,
  scope: AddressScope,
  contactLabel: string,
) {
  form
    .querySelectorAll(
      '.woocommerce-billing-fields__field-wrapper, .woocommerce-shipping-fields__field-wrapper',
    )
    .forEach((wrapper) => wrapper.classList.add('kiriof-classic-address-layout'));
  const district = form.querySelector(
    `#${scope === 'shipping' ? 'kiriof_shipping_destination_area' : 'kiriof_destination_area'}`,
  );
  const row = district?.closest('.form-row');
  if (row && (panel.parentNode !== row.parentNode || row.nextElementSibling !== panel))
    row.insertAdjacentElement('afterend', panel);
  if (row) {
    const priority =
      row.getAttribute('data-priority') === null ? 61 : Number(row.getAttribute('data-priority'));
    const value = String(Number.isFinite(priority) ? priority + 0.5 : 61.5);
    if (panel.getAttribute('data-priority') !== value) panel.setAttribute('data-priority', value);
  }
  const email = form.querySelector('#billing_email')?.closest('.form-row');
  const billing = form.querySelector('.woocommerce-billing-fields');
  const heading = billing?.querySelector('h3');
  if (!email || !billing || !heading) return;
  let contact = form.querySelector('.kiriof-classic-contact');
  if (!contact) {
    contact = form.ownerDocument.createElement('section');
    contact.className = 'kiriof-classic-contact';
    const title = form.ownerDocument.createElement('h3');
    title.textContent = contactLabel;
    contact.append(title);
    billing.insertBefore(contact, heading);
  }
  if (email.parentNode !== contact) contact.append(email);
}
