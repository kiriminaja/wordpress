import { createQueue } from '../state/checkout-queue';
import { normalizeDestination } from '../state/destination';
import { create } from '../state/shipping-selection';
import {
  createMapSession,
  createLocationGate,
  normalizePoint,
  coverageDistance,
  coverageStatus,
} from '../map/leaflet';

export const checkoutSessionBridge = { createQueue, normalizeDestination };
export const shippingSelectionBridge = { create };
export const mapCheckoutBridge = {
  createMapSession,
  createLocationGate,
  normalizePoint,
  coverageDistance,
  coverageStatus,
};

export interface BuyerStateGlobals {
  kiriofBuyerCheckoutSession: typeof checkoutSessionBridge;
  kiriofShippingSelection: typeof shippingSelectionBridge;
  kiriofMapCheckout: typeof mapCheckoutBridge;
}
declare global {
  interface Window extends BuyerStateGlobals {}
}
/** The standalone IIFE entry preserves legacy bridge names, not handwritten assets. */
const root = (typeof window !== 'undefined' ? window : globalThis) as typeof globalThis &
  BuyerStateGlobals;
root.kiriofBuyerCheckoutSession = checkoutSessionBridge;
root.kiriofShippingSelection = shippingSelectionBridge;
root.kiriofMapCheckout = mapCheckoutBridge;
