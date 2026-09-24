import type { ToolbarConfig } from '$lib/ui/toolbar';

export type TransactionDetailBootstrap = {
  toolbar: ToolbarConfig;
  transaction: {
    id: number;
    orderId: string;
    orderNumber: string;
    orderUrl: string;
    createdAt: string;
    paymentLabel: string;
    isCod: boolean;
    supportsLiveTracking: boolean;
    pickupNumber: string;
    status: { label: string; tone: 'primary' | 'info' | 'teal' | 'success' | 'danger' };
    steps: Array<{ label: string; date: string; completed: boolean }>;
    sender: { name: string; phone: string; address: string[] };
    recipient: { name: string; phone: string; address: string[] };
    package: { weight: number; length: number; width: number; height: number };
    items: Array<{ name: string; quantity: number; total: number; sku: string }>;
    notes: Array<{ label: string; content: string }>;
    shipment: {
      courier: { code: string; service: string };
      awb: string;
      isPaid: boolean;
      costs: {
        shipping: number;
        insurance: number;
        codFee: number;
        discount: number;
        total: number;
      };
      codValue: number;
      printUrl: string;
      trackingOrder: string;
    };
    actions: {
      changeOrigin: boolean;
      adjustDeficit: boolean;
      cancelDeficit: boolean;
      cancel: boolean;
      data: Record<string, string | number>;
    };
  };
  ajax: { url: string; nonce: string };
  i18n: Record<string, string>;
};

type TrackingHistory = { status: string; created_at: string; driver?: string; receiver?: string };
export type TrackingResponse = { histories?: TrackingHistory[] };
