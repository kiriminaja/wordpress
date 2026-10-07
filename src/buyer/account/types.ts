import type * as Leaflet from 'leaflet';
import type { Coverage, Point } from '../map/types';
import type { MapProviderConfigInput } from '../map/config';
export const addressFields = [
  'address_1',
  'address_2',
  'city',
  'state',
  'postcode',
  'country',
] as const;
export type Address = Record<(typeof addressFields)[number], string>;
export interface District {
  id: string;
  label: string;
}
export interface Destination extends Partial<Address> {
  version?: number;
  district_id?: unknown;
  district_label?: unknown;
  shipping_address?: Partial<Address>;
  destination_latitude?: unknown;
  destination_longitude?: unknown;
}
export interface AccountConfig {
  ajaxUrl?: string;
  nonce?: string;
  savedDestination?: Destination;
  i18n?: Record<string, string>;
  map?: MapProviderConfigInput & {
    enabled?: boolean;
    coverage?: Coverage;
    i18n?: Record<string, string>;
  };
}
export interface AccountRoot extends Window {
  AbortController: typeof AbortController;
  URLSearchParams: typeof URLSearchParams;
  L?: typeof Leaflet;
  kiriofAccountShippingConfig?: AccountConfig;
  __kiriofAccountShippingBridge?: { dispose(): void };
}
export interface AccountView {
  address: Address;
  pin: Point | null;
  selection: District | null;
  loading: boolean;
  failed: boolean;
}
