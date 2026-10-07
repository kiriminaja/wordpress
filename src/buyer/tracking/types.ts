export interface TrackingHistory {
  created_at: string;
  status: string;
}
export interface TrackingData {
  number_order: string;
  details: {
    awb: string;
    service: string;
    destination: { name: string; city: string; province: string };
  };
  histories: TrackingHistory[];
}
export interface TrackingLabels {
  orderNumber: string;
  awbNumber: string;
  courier: string;
  notFound: string;
}
export interface TrackingState {
  phase: 'blank' | 'loading' | 'success' | 'error';
  data: TrackingData;
  message: string;
}
export interface TrackingBridge {
  trackOrder(): Promise<void>;
  dispose(): void;
}
export type TrackingRoot = Window & {
  kiriofTracking?: { i18n?: Partial<TrackingLabels> };
  kiriofAjaxRoute?: () => string;
  kiriofAjax?: { ajaxurl?: string };
  trackOrder?: () => unknown;
  __kiriofTrackingBridge?: TrackingBridge;
};
