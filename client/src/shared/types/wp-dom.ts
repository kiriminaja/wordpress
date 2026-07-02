export interface KiriofAjaxConfig {
  ajaxurl: string;
  nonce?: string;
  destination_nonce?: string;
  update_checkout_nonce?: string;
}

export interface KiriofTrackingConfig {
  i18n?: Record<string, string>;
}

export interface KiriofCodAdjustmentConfig {
  ajaxUrl?: string;
  nonce?: string;
  hintMin?: string;
  hintMax?: string;
  hintPayout?: string;
  processing?: string;
  confirm?: string;
  cancelConfirm?: string;
  hintCodInvalid?: string;
  errorGeneral?: string;
}

export interface KiriofBillingAddressConfig {
  ajaxUrl?: string;
  nonce?: string;
  destinationNonce?: string;
  updateCheckoutNonce?: string;
  storeApiNonce?: string;
  storeApiUpdateCustomerUrl?: string;
  savedCheckoutPostcode?: string;
  savedDistrictByPostcode?: Record<string, Record<string, unknown>>;
  globalInsurance?: boolean;
  isCart?: boolean;
  isCheckout?: boolean;
  fieldKey?: string;
  i18n?: Record<string, string>;
}

export interface KiriofCouponAdminConfig {
  ajaxurl?: string;
  nonce?: string;
  discountTypes?: string[];
  isCacheStale?: boolean;
  isCachePending?: boolean;
  regionTree?: KiriofRegionIsland[];
  strings?: Record<string, string>;
  currentType?: string;
}

export interface KiriofRegionCity {
  id: string | number;
  name: string;
}

export interface KiriofRegionProvince {
  id: string | number;
  name: string;
  cities?: KiriofRegionCity[];
}

export interface KiriofRegionIsland {
  id: string | number;
  name: string;
  provinces?: KiriofRegionProvince[];
}

export interface KiriofTransactionData {
  cod_fee?: number | string;
  insurance_cost?: number | string;
  shipping_cost?: number | string;
  transaction_value?: number | string;
}

type KiriofRuntimeValue = any;

interface KiriofWpDataStore {
  [key: string]: KiriofRuntimeValue;
  getCartData?: () => KiriofRuntimeValue;
  getNotices?: () => KiriofNotice[];
  getPaymentMethods?: () => KiriofRuntimeValue;
  getSelectedPaymentMethod?: () => string;
  getShippingRates?: () => KiriofRuntimeValue[];
  receiveCart?: (cart: KiriofRuntimeValue) => void;
  createNotice?: (status: string, content: string, options?: KiriofRuntimeValue) => void;
  removeNotice?: (id: string, context?: string) => void;
  invalidateResolutionForStore?: () => void;
  invalidateResolution?: (store: string, selector: string, args?: unknown[]) => void;
  setValidationErrors?: (errors: KiriofRuntimeValue) => void;
  clearValidationError?: (id: string) => void;
  clearValidationErrors?: (id: string | string[]) => void;
  invalidateResolutionForStoreSelector?: (selector: string) => void;
  getEditingShippingAddress?: () => KiriofRuntimeValue;
  getEditingBillingAddress?: () => KiriofRuntimeValue;
  setEditingShippingAddress?: (address: KiriofRuntimeValue) => void;
  setEditingBillingAddress?: (address: KiriofRuntimeValue) => void;
  setAdditionalFields?: (fields: KiriofRuntimeValue) => void;
  getAdditionalFields?: () => KiriofRuntimeValue;
  getShippingAddress?: () => KiriofRuntimeValue;
  getBillingAddress?: () => KiriofRuntimeValue;
  selectShippingRate?: (rateId: string, packageId?: string | null) => void;
  setSelectedShippingRate?: (rateId: string, packageId?: string | null) => void;
  extensionCartUpdate?: (args: KiriofRuntimeValue) => Promise<KiriofRuntimeValue>;
  getActivePaymentMethod?: () => string;
  getPaymentMethodData?: () => KiriofRuntimeValue;
}

interface KiriofNotice {
  id: string;
  content: string;
  context?: string;
  status?: string;
  type?: string;
}

interface KiriofWpGlobal {
  data?: {
    select: (store: string) => KiriofWpDataStore;
    dispatch: (store: string) => KiriofWpDataStore;
    subscribe: (listener: () => void) => () => void;
    useSelect?: (selector: (select: (store: string) => KiriofWpDataStore) => KiriofRuntimeValue, deps?: unknown[]) => KiriofRuntimeValue;
  };
  plugins?: {
    registerPlugin?: (name: string, options: KiriofRuntimeValue) => void;
  };
  element?: {
    createElement?: (...args: KiriofRuntimeValue[]) => KiriofRuntimeValue;
    useEffect?: (effect: () => void | (() => void), deps?: unknown[]) => void;
    useMemo?: <T>(factory: () => T, deps?: unknown[]) => T;
    useRef?: <T>(initial: T) => { current: T };
    useState?: <T>(initial: T) => [T, (value: T) => void];
  };
  apiFetch?: {
    nonceMiddleware?: { nonce?: string };
  };
}

interface KiriofWcGlobal {
  blocksCheckout?: {
    registerCheckoutFilters?: (namespace: string, filters: Record<string, unknown>) => void;
    ExperimentalDiscountsMeta?: unknown;
    ExperimentalOrderMeta?: unknown;
    TotalsWrapper?: unknown;
  };
}

declare global {
  const kiriofAjax: KiriofAjaxConfig | undefined;
  const kiriofCodAdj: KiriofCodAdjustmentConfig | undefined;
  const kiriofTransactionData: KiriofTransactionData | undefined;
  const QRCodeStyling: (new (options: Record<string, unknown>) => { append(target: HTMLElement | undefined): void }) | undefined;
  const kiriofFormatRupiah: ((value: string | number) => string) | undefined;
  const wp: KiriofWpGlobal | undefined;
  const wc: KiriofWcGlobal | undefined;
  const kiriofBillingAddressConfig: KiriofBillingAddressConfig;
  const kiriofCouponAdmin: KiriofCouponAdminConfig;

  function kiriofMoneyFormat(value: string | number, prefix?: string): string;
  function kiriofAjaxRoute(): string;
  function kiriofPrintAsString<T>(value: T | null | undefined, placeholder?: string): T | string;

  interface Window {
    kiriofAjax?: KiriofAjaxConfig;
    kiriofTracking?: KiriofTrackingConfig;
    kiriofCodAdj?: KiriofCodAdjustmentConfig;
    kiriofBillingAddressConfig?: KiriofBillingAddressConfig;
    kiriofCouponAdmin?: KiriofCouponAdminConfig;
    kiriofTransactionData?: KiriofTransactionData;
    kiriofBlockCheckoutCompatibilityInitialized?: boolean;
    trackOrder?: () => void;
    kiriofAjaxRoute?: () => string;
    kiriofMoneyFormat?: (value: string | number, prefix?: string) => string;
    kiriofPrintAsString?: <T>(value: T | null | undefined, placeholder?: string) => T | string;
    kiriofRenderQrCode?: (target: HTMLElement | JQuery | string, text: string, options?: Record<string, unknown>) => boolean;
    kiriofGetUrlParameter?: (param: string) => string | boolean;
    kiriofFormatRupiah?: (value: string | number) => string;
    kjShowCodAdjustModal?: (button: HTMLElement) => void;
    kjShowCancelDeficitModal?: (button: HTMLElement) => void;
    wp?: KiriofWpGlobal;
    wc?: KiriofWcGlobal;
    wpApiSettings?: { nonce?: string };
    kiriofBlockPlaceOrderCaptureBound?: boolean;
  }
}

export {};
