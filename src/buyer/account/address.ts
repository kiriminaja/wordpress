import { coordinate } from '../map/leaflet';
import { addressFields } from './types';
import type { Address, Destination } from './types';
export function normalizeAddress(value?: Partial<Address> | null): Address {
  const address = Object.fromEntries(
    addressFields.map((field) => [field, String(value?.[field] || '').trim()]),
  ) as Address;
  address.postcode = address.postcode.replace(/\s+/g, '').toUpperCase();
  address.country = address.country.toUpperCase();
  return address;
}
export function readAddress(form: HTMLFormElement): Address {
  return normalizeAddress(
    Object.fromEntries(
      addressFields.map((field) => {
        // Woo replaces state controls on country changes; never retain field references.
        const control = form.elements.namedItem(`shipping_${field}`) as HTMLInputElement | null;
        return [field, control?.value || ''];
      }),
    ),
  );
}
export const addressKey = (address?: Partial<Address>) => JSON.stringify(normalizeAddress(address));
export const lookupKey = (address: Address) => `${address.country}|${address.postcode}`;
export function savedPoint(saved: Destination | null, address: Address) {
  if (
    saved?.version !== 2 ||
    !saved.shipping_address ||
    address.country !== 'ID' ||
    addressKey(saved.shipping_address) !== addressKey(address)
  )
    return null;
  // Account persistence accepts decimal coordinates only (not hex/scientific forms).
  const strict = (value: unknown, limit: number) =>
    /(?:^)-?(?:\d+(?:\.\d*)?|\.\d+)$/.test(String(value).trim()) ? coordinate(value, limit) : null;
  const latitude = strict(saved.destination_latitude, 90),
    longitude = strict(saved.destination_longitude, 180);
  return latitude !== null && longitude !== null ? { latitude, longitude } : null;
}
