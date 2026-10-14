import { normalizeDestination } from '../state/destination';
import type { Destination, DestinationInput } from '../state/destination';
import type { PointInput } from '../map/types';
import type { AddressSnapshot, DistrictSelection } from './types';

const addressFields = ['address_1', 'address_2', 'city', 'state', 'postcode', 'country'] as const;
export function shippingAddress(address?: Partial<AddressSnapshot> | null): AddressSnapshot {
  const snapshot = {} as AddressSnapshot;
  addressFields.forEach((key) => {
    snapshot[key] = String(address?.[key] || '').trim();
  });
  snapshot.postcode = snapshot.postcode.replace(/\s+/g, '').toUpperCase();
  snapshot.country = snapshot.country.toUpperCase();
  return snapshot;
}
export function shippingAddressKey(address?: Partial<AddressSnapshot> | null): string {
  return JSON.stringify(shippingAddress(address));
}
export function validCoordinate(value: unknown, limit: number): boolean {
  if (value === null || value === undefined || (typeof value === 'string' && value.trim() === ''))
    return false;
  if (typeof value !== 'number' && typeof value !== 'string') return false;
  const number = Number(value);
  return isFinite(number) && Math.abs(number) <= limit;
}
export interface PinnedDestination extends Omit<Destination, 'version'> {
  version: 2;
  destination_latitude: string;
  destination_longitude: string;
  shipping_address: AddressSnapshot;
}
export function destinationForAddress(
  selection: DistrictSelection | null,
  address: Partial<AddressSnapshot>,
  coordinates?: PointInput | null,
): Destination | PinnedDestination {
  const destination = normalizeDestination({
    district_id: selection ? selection.id : '',
    district_label: selection ? selection.label : '',
    postcode: String(address.postcode || '')
      .replace(/\s+/g, '')
      .toUpperCase(),
    country: address.country || 'ID',
    address_type: 'shipping',
    destination_latitude: '',
    destination_longitude: '',
  });
  if (
    coordinates &&
    validCoordinate(coordinates.latitude, 90) &&
    validCoordinate(coordinates.longitude, 180)
  ) {
    return {
      ...destination,
      destination_latitude: String(coordinates.latitude).trim(),
      destination_longitude: String(coordinates.longitude).trim(),
      version: 2,
      shipping_address: shippingAddress(address),
    };
  }
  return destination;
}
export function completeShippingAddress(address: AddressSnapshot): boolean {
  return ['address_1', 'city', 'state', 'postcode', 'country'].every((key) =>
    Boolean(address[key as keyof AddressSnapshot]),
  );
}
export function savedCoordinates(
  destination?: DestinationInput | null,
): { latitude: string; longitude: string; key: string } | null {
  function plainCoordinate(value: unknown, limit: number): boolean {
    return (
      (typeof value === 'number' || typeof value === 'string') &&
      /^-?(?:\d+(?:\.\d*)?|\.\d+)$/.test(String(value).trim()) &&
      validCoordinate(value, limit)
    );
  }
  if (
    !destination ||
    destination.version !== 2 ||
    !/^[1-9][0-9]*$/.test(String(destination.district_id)) ||
    !destination.shipping_address ||
    !plainCoordinate(destination.destination_latitude, 90) ||
    !plainCoordinate(destination.destination_longitude, 180)
  )
    return null;
  const address = shippingAddress(destination.shipping_address as Partial<AddressSnapshot>);
  if (
    !completeShippingAddress(address) ||
    address.country !== 'ID' ||
    address.country !== String(destination.country || '').toUpperCase() ||
    address.postcode !==
      String(destination.postcode || '')
        .replace(/\s+/g, '')
        .toUpperCase()
  )
    return null;
  return {
    latitude: Number(destination.destination_latitude).toFixed(7),
    longitude: Number(destination.destination_longitude).toFixed(7),
    key: shippingAddressKey(address),
  };
}
