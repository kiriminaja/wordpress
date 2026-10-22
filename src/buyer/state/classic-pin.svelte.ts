import { createQueue, type QueueState, type QueueOptions } from './checkout-queue';
import { normalizeDestination, type Destination } from './destination';
import { normalizePoint } from '../map/leaflet';
import type { Point, PointInput } from '../map/types';

export type AddressScope = 'billing' | 'shipping';
export const addressFields = [
  'address_1',
  'address_2',
  'city',
  'state',
  'postcode',
  'country',
] as const;
export type NativeAddress = Record<(typeof addressFields)[number], string>;
export interface District {
  id: string;
  label: string;
}
export type PinDestination = Omit<Destination, 'version'> & {
  version: 1 | 2;
  destination_latitude?: string;
  destination_longitude?: string;
  shipping_address?: NativeAddress;
};
export interface PinSnapshot {
  action: 'sync_checkout';
  address_scope: AddressScope;
  effective_address: NativeAddress;
  destination: PinDestination;
}
export interface ClassicPinView {
  address: NativeAddress;
  scope: AddressScope;
  selection: District | null;
  point: Point | null;
  destination: PinDestination;
  queue: QueueState<PinSnapshot>;
}
export function address(input: Partial<Record<keyof NativeAddress, unknown>> = {}): NativeAddress {
  const result = {} as NativeAddress;
  for (const key of addressFields)
    result[key] = String(input[key] || '')
      .trim()
      .replace(/\s+/g, ' ');
  result.country = result.country.toUpperCase();
  result.postcode = result.postcode.replace(/\s+/g, '').toUpperCase();
  return result;
}
export function sameAddress(
  first: Partial<NativeAddress> = {},
  second: Partial<NativeAddress> = {},
) {
  return JSON.stringify(address(first)) === JSON.stringify(address(second));
}
export interface ClassicPinOptions extends Pick<
  QueueOptions<PinSnapshot>,
  'send' | 'isBlocked' | 'setTimeout' | 'clearTimeout'
> {
  address?: Partial<NativeAddress>;
  scope?: AddressScope;
  savedDestination?: PinDestination | null;
  onChange?(view: ClassicPinView): void;
}
/** Pin state reads native Woo fields but never owns or writes their values. */
export function createClassicPin(options: ClassicPinOptions) {
  let current = address(options.address);
  let scope = options.scope || 'billing';
  let selection: District | null = null;
  let point: Point | null = null;
  let destroyed = false;
  const saved = options.savedDestination;
  if (
    saved &&
    saved.country === current.country &&
    saved.postcode === current.postcode &&
    saved.district_id
  ) {
    selection = { id: saved.district_id, label: saved.district_label };
    if (
      saved.version === 2 &&
      saved.shipping_address &&
      sameAddress(saved.shipping_address, current)
    )
      point = normalizePoint(saved.destination_latitude, saved.destination_longitude);
  }
  function destination(): PinDestination {
    const value = normalizeDestination({
      district_id: current.country === 'ID' ? selection?.id : '',
      district_label: selection?.label,
      country: current.country,
      postcode: current.postcode,
      address_type: 'shipping',
    });
    return point && value.district_id
      ? {
          ...value,
          version: 2,
          destination_latitude: point.latitude,
          destination_longitude: point.longitude,
          shipping_address: address(current),
        }
      : value;
  }
  const queue = createQueue<PinSnapshot>({ ...options, onChange: () => notify() });
  const state = (): ClassicPinView => ({
    address: address(current),
    scope,
    selection,
    point,
    destination: destination(),
    queue: queue.getState(),
  });
  let view = $state.raw<ClassicPinView>(state());
  function notify() {
    if (!destroyed) {
      view = state();
      options.onChange?.(view);
    }
  }
  function sync() {
    return queue.update({
      action: 'sync_checkout',
      address_scope: scope,
      effective_address: address(current),
      destination: destination(),
    });
  }
  return {
    get view() {
      return view;
    },
    getState: state,
    updateAddress(input: Partial<NativeAddress>, nextScope: AddressScope) {
      const next = address(input);
      if (sameAddress(current, next) && scope === nextScope) return false;
      if (
        current.country !== next.country ||
        current.postcode !== next.postcode ||
        scope !== nextScope
      )
        selection = null;
      current = next;
      scope = nextScope;
      point = null;
      notify();
      void sync();
      return true;
    },
    selectDistrict(next: District | null) {
      selection =
        next && /^[1-9][0-9]*$/.test(String(next.id)) && next.label
          ? { id: String(next.id), label: next.label }
          : null;
      point = null;
      notify();
      return sync();
    },
    selectPoint(next: PointInput | null, expected?: NativeAddress) {
      if (current.country !== 'ID' || !selection || (expected && !sameAddress(expected, current)))
        return false;
      point = next ? normalizePoint(next.latitude, next.longitude) : null;
      notify();
      void sync();
      return true;
    },
    sync,
    retry: () => queue.retry(),
    flush: () => queue.resume(),
    dispose() {
      destroyed = true;
      queue.dispose();
    },
  };
}
export type ClassicPinController = ReturnType<typeof createClassicPin>;
export function createPinPresentation() {
  const presentation = $state({ visible: true, requirement: '' });
  return presentation;
}
