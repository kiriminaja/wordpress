export type PickupDetailBootstrap = {
  summary: Array<{ value: number; label: string }>;
  rows: Array<{
    number: number;
    orderId: string;
    orderUrl: string;
    courier: string;
    awb: string;
    origin: string;
    destination: string;
    weight: number;
    fee: string;
    codValue: string;
    status: string;
    printUrl: string;
  }>;
  schedule: string;
  printAllUrl: string;
  i18n: Record<string, string>;
};
