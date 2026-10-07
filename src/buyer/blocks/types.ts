import type { Point } from '../map/types';

/** Only plugin-owned communications are modeled here. Woo's native stores are
 * intentionally accessed through its supplied wp.data/wp.element boundary. */
export interface AddressSnapshot {
  address_1: string;
  address_2: string;
  city: string;
  state: string;
  postcode: string;
  country: string;
}
export interface DistrictSelection {
  id: string;
  label: string;
  key?: string;
}
export interface AddressPresentationSnapshot {
  editing: boolean;
  cardTarget: HTMLElement | null;
}
export interface AddressPresentationBridge {
  usePresentation(): AddressPresentationSnapshot;
  subscribe(listener: (snapshot: AddressPresentationSnapshot) => void): () => void;
  getSnapshot(): AddressPresentationSnapshot;
}
export interface BuyerCheckoutBridge {
  active: boolean;
  pending: boolean;
  disabled: boolean;
  ready: Promise<boolean>;
  getDestination(): unknown;
  setCoordinates(address: Partial<AddressSnapshot>, point: Point | null): boolean;
  getCoordinates(address: Partial<AddressSnapshot>): (Point & { key: string }) | null;
}
/** Woo injects these globals; this entry never imports or bundles a second React. */
export type BlocksRoot = Window &
  typeof globalThis & {
    wp?: unknown;
    wc?: unknown;
    kiriofBuyerCheckout?: BuyerCheckoutBridge;
    kiriofAddressPresentation?: AddressPresentationBridge;
  };
