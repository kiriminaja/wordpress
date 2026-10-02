import type { ToolbarConfig } from '$lib/ui/toolbar';
import type { InstantStatusPresentation, TransactionActionData } from '$lib/transactions/types';

export type InstantRouteCoordinate = { latitude: number; longitude: number };
export type InstantRouteMapData = {
  origin: InstantRouteCoordinate | null;
  destination: InstantRouteCoordinate | null;
  points: Array<[number, number]>;
  mode: 'recorded' | 'illustration' | 'unavailable';
};
export type InstantRouteMapConfig = { tiles: string; attribution: string; enabled: boolean };

export type TransactionDetailBootstrap = {
  map?: InstantRouteMapConfig;
  toolbar: ToolbarConfig;
  transaction: {
    deliveryType: 'express' | 'instant';
    vehicle: string | null;
    id: number;
    orderId: string;
    orderNumber: string;
    orderUrl: string;
    createdAt: string;
    paymentLabel: string;
    isCod: boolean;
    supportsLiveTracking: boolean;
    pickupNumber: string;
    status: InstantStatusPresentation;
    steps: Array<{ label: string; date: string; completed: boolean }>;
    sender: { name: string; phone: string; address: string[] };
    recipient: { name: string; phone: string; address: string[] };
    package: { weight: number; length: number; width: number; height: number };
    items: Array<{ name: string; quantity: number; total: number; sku: string }>;
    notes: Array<{ label: string; content: string }>;
    shipment: {
      routeMap?: InstantRouteMapData | null;
      courier: { code: string; service: string };
      awb: string;
      paymentStatus: string;
      paymentMethod: string;
      paymentId: string;
      costs: {
        orderTotal: number;
        subtotal: number;
        totalShipping: number;
        actualShipping: number;
        shippingDiscount: number;
        shipping: number;
        insurance: number;
        codFee: number;
        itemDiscount: number;
        total: number;
      };
      codValue: number;
      printUrl: string;
      trackingOrder: string;
      liveTrackingUrl: string;
    };
    actions: {
      track: boolean;
      reconcile?: false;
      changeOrigin: boolean;
      adjustDeficit: boolean;
      cancelDeficit: boolean;
      cancel: boolean;
      data: TransactionActionData;
    };
  };
  ajax: { url: string; nonce: string; printPreviewNonce: string };
  shipmentLocations: Array<{ id: number; name: string; address: string }>;
  locationsUrl: string;
  bootstrapError?: string;
  i18n: Record<string, string>;
};

type TrackingHistory = { status: string; created_at: string; driver?: string; receiver?: string };
export type TrackingResponse = { histories?: TrackingHistory[] };
