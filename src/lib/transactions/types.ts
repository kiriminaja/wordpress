import {
  IconAlertTriangle,
  IconClock,
  IconCircleCheck,
  IconPackage,
  IconTruck,
  IconXboxX,
} from '@tabler/icons-svelte';

export type InstantStatusPresentation = {
  label: string;
  tone: 'info' | 'success' | 'warning' | 'danger' | 'critical' | 'primary' | 'teal';
  key?: string;
  tooltip?: string | null;
  issue?: string | boolean | null;
};

/** Identical informational Instant status icons in list and detail. */
export function instantStatusIcon(status: InstantStatusPresentation) {
  if (status.key === 'waiting_for_shipment') return IconPackage;
  if (status.key === 'on_delivery') return IconTruck;
  if (status.key === 'shipment_problem' || status.key === 'need_confirmation')
    return IconAlertTriangle;
  switch (status.tone) {
    case 'success':
      return IconCircleCheck;
    case 'danger':
    case 'critical':
      return IconXboxX;
    case 'warning':
      return IconAlertTriangle;
    case 'teal':
      return IconTruck;
    case 'primary':
      return IconPackage;
    default:
      return IconClock;
  }
}

export type TransactionFilters = {
  delivery_type: 'express' | 'instant';
  key: string;
  month: string;
  date_from?: string;
  date_to?: string;
  date_range_invalid?: boolean;
  status: string;
  cod: string;
  courier: string;
  print_status: string;
};

export type TransactionStatusOption = { value: string; label: string; count: number };

export type TransactionActionData = {
  nonce: string;
  kaOrderId: string;
  currentOrigin: string;
  currentOriginAddress: string;
  currentLocationId: number;
  currentCod: number;
  codMinimum: number;
  codMaximum: number;
  shippingCost: number;
  insuranceFee: number;
  codFee: number;
  itemPrice: number;
  itemDiscount: number;
  shippingDiscount: number;
  itemCoupon: string;
  shippingCoupon: string;
};

export type TransactionRow = {
  deliveryType: 'express' | 'instant';
  vehicle: string | null;
  instantPayment: { method: string; status: string; id: string };
  id: number;
  wcOrderId: number;
  wcOrderUrl: string;
  detailUrl: string;
  createdAt: string;
  customer: { name: string; phone: string };
  courier: { code: string; service: string; paymentLabel: string };
  status: InstantStatusPresentation & { deficit: boolean };
  printStatus: 'printed' | 'unprinted';
  awb: string;
  kaOrderId: string;
  route: { origin: string; destination: string; addressLines: string[] };
  package: {
    weight: number;
    quantity: number;
    actualShipping: number;
    paidShipping: number;
    insurance: number;
    codFee: number;
    codValue: number;
    itemDiscount: number;
    shippingDiscount: number;
    itemCoupon: string;
    shippingCoupon: string;
  };
  selection: {
    disabled: boolean;
    canPickup: boolean;
    canProcess: boolean;
    canPrint: boolean;
    title: string;
  };
  actions: {
    track: boolean;
    reconcile?: boolean;
    liveTrackingUrl: string;
    paymentUrl?: string;
    preview: boolean;
    process: boolean;
    changeOrigin: boolean;
    adjustDeficit: boolean;
    cancelDeficit: boolean;
    print: boolean;
    cancel: boolean;
    printUrl: string;
  };
  actionData: TransactionActionData;
};

export type TransactionsBootstrap = {
  toolbar: ToolbarConfig;
  filters: TransactionFilters;
  deliveryCounts?: { regular: number; instant: number; issue?: number };
  statusOptions: TransactionStatusOption[];
  monthOptions: Record<string, string>;
  couriers: Array<{ value: string; label: string }>;
  shipmentLocations: Array<{ id: number; name: string; address: string }>;
  locationsUrl: string;
  pagination: { page: number; totalPages: number; total: number; perPage: number };
  rows: TransactionRow[];
  bulk: {
    showPrint: boolean;
    printAction: string;
    printNonce: string;
    printPreviewNonce: string;
    ajaxUrl: string;
    nonce: string;
    pickupUrl: string;
  };
  i18n: Record<string, string> & {
    weight: string;
    actualShipping: string;
    shippingCost: string;
    insurance: string;
    codFee: string;
    itemDiscount: string;
    shippingDiscount: string;
    copyAwb: string;
    copyKaOrderId: string;
    copied: string;
    autoRefresh: string;
    refreshLabels: Record<string, string>;
  };
};
import type { ToolbarConfig } from '$lib/ui/toolbar';

export interface InstantQuoteRow {
  id: string;
  wc_order_number?: string;
  courier?: string;
  service?: string;
  origin_label?: string;
  destination_label?: string;
  before: number | null;
  after: number | null;
  changed: boolean;
  eligible: boolean;
  error: string;
}
export interface InstantQuote {
  credit_balance?: number | null;
  token: string;
  expires_at: number;
  rows: InstantQuoteRow[];
  payment_methods: string[];
  batch_count: number;
}
export interface InstantPayment {
  id: string;
  status: string;
  amount: number | null;
  qr_content: string;
  order_ids: string[];
}
export interface InstantDispatchResult {
  rows: Array<{ id: string; status: string; awb: string; message: string; retryable?: boolean }>;
  payments: InstantPayment[];
}

export type InstantOperationMode = 'tracking' | 'cancel' | 'reconcile';
export type InstantOperationRow = {
  id: string;
  status: 'tracked' | 'not_found' | 'unknown' | 'cancel_requested' | 'canceled' | 'reconciled';
  tracking_url: string;
  message: string;
};

/** External courier URLs are allowed, but never credentials or executable schemes. */
export function safeInstantTrackingUrl(value: unknown): string {
  // eslint-disable-next-line no-control-regex -- Reject unsafe control bytes in external URLs.
  if (typeof value !== 'string' || value.length > 2048 || /[\x00-\x20\x7f\\]/.test(value))
    return '';
  try {
    if (!/^https?:\/\//i.test(value) || value.split('/')[2]?.includes('@')) return '';
    const url = new URL(value);
    return ['http:', 'https:'].includes(url.protocol) &&
      url.hostname &&
      !url.username &&
      !url.password
      ? url.href
      : '';
  } catch {
    return '';
  }
}
