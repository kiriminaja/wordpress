import type { MapProviderInput } from './pin';

export type SelectorOption = {
  value: string;
  label: string;
  disabled?: boolean;
  courier?: string;
  price?: string;
  originalPrice?: string;
  savings?: string;
  note?: string;
};

export type SelectorConfig = {
  map?: MapProviderInput;
  isCheckout?: boolean | '1' | '';
  billingDistrict?: { id?: string | number; name?: string };
  shippingDistrict?: { id?: string | number; name?: string };
  savedDistrictByPostcode?: Record<
    string,
    { destination_id?: string | number; destination_name?: string }
  >;
  fieldKey?: string;
  nonce?: string;
  ajaxUrl?: string;
  billingCountry?: string;
  shippingCountry?: string;
  courierLogos?: Record<string, string>;
  i18n?: Record<string, string>;
};

export type SelectorBridge = {
  active: () => boolean;
  districtPending?: () => boolean;
  refresh: () => void;
  initDistrict: (select: HTMLSelectElement) => unknown;
  initShipping: (select: HTMLSelectElement) => unknown;
  dispose?: () => void;
};
