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
  refresh: () => void;
  initDistrict: (select: HTMLSelectElement) => unknown;
  initShipping: (select: HTMLSelectElement) => unknown;
};
