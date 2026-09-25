export type TransactionFilters = {
  key: string;
  month: string;
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
  id: number;
  wcOrderId: number;
  wcOrderUrl: string;
  detailUrl: string;
  createdAt: string;
  customer: { name: string; phone: string };
  courier: { code: string; service: string; paymentLabel: string };
  status: {
    label: string;
    tone: 'info' | 'success' | 'warning' | 'danger' | 'critical' | 'primary' | 'teal';
    deficit: boolean;
  };
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
  selection: { disabled: boolean; canPickup: boolean; canPrint: boolean; title: string };
  actions: {
    preview: boolean;
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
  statusOptions: TransactionStatusOption[];
  monthOptions: Record<string, string>;
  couriers: Array<{ value: string; label: string }>;
  shipmentLocations: Array<{ id: number; name: string; address: string }>;
  pagination: { page: number; totalPages: number; total: number; perPage: number };
  rows: TransactionRow[];
  bulk: {
    showPrint: boolean;
    printAction: string;
    printNonce: string;
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
