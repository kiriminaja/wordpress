export type PaymentAction = {
  type: 'pay' | 'reschedule' | 'details';
  label: string;
  href?: string;
};
export type PaymentRow = {
  number: number;
  pickupNumber: string;
  requestedAt: string;
  schedule: string;
  fees: string;
  orders: number;
  method: string;
  status: 'paid' | 'unpaid';
  actions: PaymentAction[];
};
export type PaymentsBootstrap = {
  rows: PaymentRow[];
  filters: { key: string; month: string; status: string };
  monthOptions: Record<string, string>;
  statusTabs: Array<{ value: string; label: string; count: number }>;
  pagination: { page: number; totalPages: number };
  i18n: Record<string, string>;
};
