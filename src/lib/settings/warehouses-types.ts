import type { ToolbarConfig } from '$lib/ui/toolbar';
import type { MapProviderConfigInput } from '../../buyer/map/config';

export type WarehouseLocation = {
  id: number;
  name: string;
  sender_name: string;
  sender_phone: string;
  address: string;
  address_2: string;
  city: string;
  country_state: string;
  zip: string;
  district: string;
  district_label: string;
  lat: string;
  lng: string;
  is_active: boolean;
  is_default: boolean;
};
export type WarehouseArea = {
  id: string | number;
  text?: string;
  label?: string;
  city?: string;
  country_state?: string;
  zip?: string;
};
export type WarehousesBootstrap = {
  view: 'warehouses';
  toolbar: ToolbarConfig;
  ajaxUrl: string;
  nonce: string;
  locations: WarehouseLocation[];
  /** WooCommerce country/state values paired with localized human-readable labels. */
  countryStates?: { value: string; label: string }[];
  editingKey: string;
  customCount: number;
  customLimit: number;
  canCreate: boolean;
  actions: { save: string; delete: string; toggle: string };
  map: MapProviderConfigInput & { enabled?: boolean };
  i18n: Record<string, string>;
};
