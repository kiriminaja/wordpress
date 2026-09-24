import type { ToolbarConfig } from '$lib/ui/toolbar';

export type PickupDetailRow = {
  number: number;
  orderId: string;
  orderUrl: string;
  customer: { name: string; phone: string; paymentLabel: string };
  courier: { code: string; service: string };
  awb: string;
  route: { origin: string; recipient: string; addressLines: string[] };
  package: {
    weight: number;
    shipping: number;
    insurance: number;
    codFee: number;
    discount: number;
    total: number;
    codValue: number;
  };
  status: { label: string; tone: 'success' | 'warning' | 'info' | 'neutral' | 'critical' };
  printUrl: string;
};

export type PickupDetailBootstrap = {
  toolbar: ToolbarConfig;
  summary: Array<{ value: number; label: string }>;
  rows: PickupDetailRow[];
  schedule: string;
  printAllUrl: string;
  printError: string;
  i18n: Record<string, string>;
};
