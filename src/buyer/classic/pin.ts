import { mount, unmount } from 'svelte';
import DeliveryPin from '../components/DeliveryPin.svelte';
import {
  createClassicPin,
  createPinPresentation,
  type ClassicPinController,
  type NativeAddress,
  type PinDestination,
} from '../state/classic-pin.svelte';
import { saveClassicPin, type ClassicPinTransportConfig } from '../api/checkout';
import { nativeAddress, placeNativePin } from './native-address';
import type { Point } from '../map/types';
import type { MapProviderInput } from '../types/pin';
import type * as Leaflet from 'leaflet';

export interface ClassicPinConfig extends ClassicPinTransportConfig {
  enabled?: boolean;
  needsShipping?: boolean;
  savedDestination?: PinDestination | null;
  i18n?: Record<string, string>;
  map?: MapProviderInput;
}
interface JQueryEvents {
  on(
    events: string,
    callback: (event: { type: string; target?: EventTarget | null }) => unknown,
  ): JQueryEvents;
  on(
    events: string,
    selector: string,
    callback: (event: { type: string; target?: EventTarget | null }) => unknown,
  ): JQueryEvents;
  off(events: string): JQueryEvents;
  trigger(event: string): JQueryEvents;
}
export type ClassicPinWindow = Window & {
  kiriofClassicCheckoutConfig?: ClassicPinConfig;
  kiriofBuyerPin?: ClassicPinBridge;
  jQuery?: (node: Element) => JQueryEvents;
  L?: typeof Leaflet;
};

export interface ClassicPinBridge {
  controller: ClassicPinController;
  changed(): void;
  dispose(): void;
}

/** Focused Woo event/placement bridge. No district, payment or address values are changed. */
export function startClassicPin(
  root: ClassicPinWindow = window as ClassicPinWindow,
): ClassicPinBridge | undefined {
  if (root.kiriofBuyerPin) return root.kiriofBuyerPin;
  const config = root.kiriofClassicCheckoutConfig;
  const $ = root.jQuery;
  const form = root.document.querySelector<HTMLFormElement>('form.checkout');
  if (
    !config?.enabled ||
    !config.map?.enabled ||
    !$ ||
    !form ||
    root.document.querySelector('.wc-block-checkout')
  )
    return;
  const strings = { ...config.i18n, ...config.map.i18n };
  const native = nativeAddress(form);
  const panel = root.document.createElement('section');
  panel.className = 'kiriof-classic-pin form-row form-row-wide';
  const hidden = root.document.createElement('input');
  hidden.type = 'hidden';
  hidden.name = 'kiriof_buyer_destination_snapshot';
  form.append(hidden);
  let busy = false,
    disposed = false,
    lastDistrict = '',
    point: Point | null = null;
  let pointAddress: NativeAddress | null = null;
  let timer: ReturnType<typeof setTimeout> | undefined;
  const presentation = createPinPresentation();
  const place = () =>
    placeNativePin(
      form,
      panel,
      native.scope(),
      strings.contactInformation || 'Contact Information',
    );
  const render = () => {
    if (disposed) return;
    hidden.value = JSON.stringify(controller.getState().destination);
    presentation.visible =
      config.needsShipping !== false && native.address().country === 'ID' && !native.collection();
    panel.hidden = !presentation.visible;
    presentation.requirement = '';
    place();
  };
  const controller = createClassicPin({
    address: native.address(),
    scope: native.scope(),
    savedDestination: config.savedDestination,
    isBlocked: () => busy,
    send: async (snapshot) => {
      await saveClassicPin(
        snapshot,
        config,
        strings.pinSaveFailed || 'Could not save the delivery pin. Please retry.',
        root.fetch.bind(root),
      );
      if (!disposed) $(root.document.body).trigger('update_checkout');
    },
    onChange: render,
  });
  const select = (next: Point | null, expected: NativeAddress) => {
    point = next;
    pointAddress = expected;
    return native.district() ? controller.selectPoint(next, expected) : true;
  };
  function changed() {
    if (disposed) return;
    if (controller.updateAddress(native.address(), native.scope())) {
      point = null;
      pointAddress = null;
    }
    const selected = native.district();
    const id = selected?.id || '';
    if (id !== lastDistrict || (selected && !controller.getState().selection)) {
      lastDistrict = id;
      void controller.selectDistrict(selected);
      if (point && selected && pointAddress) controller.selectPoint(point, pointAddress);
    }
    render();
  }
  const current = native.district();
  lastDistrict = current?.id || '';
  if (!current || controller.getState().selection?.id !== current.id)
    void controller.selectDistrict(current);
  place();
  render();
  const component = mount(DeliveryPin, {
    target: panel,
    props: {
      controller,
      config,
      root,
      strings,
      select,
      get visible() {
        return presentation.visible;
      },
      get requirement() {
        return presentation.requirement;
      },
    },
  });
  $(form).on('input.kiriofClassicPin change.kiriofClassicPin', 'input, select', (event) => {
    if (event.target === hidden) return;
    clearTimeout(timer);
    timer = setTimeout(changed, 200);
  });
  $(root.document.body)
    .on('update_checkout.kiriofClassicPin', () => {
      busy = true;
    })
    .on(
      'updated_checkout.kiriofClassicPin checkout_error.kiriofClassicPin kiriof:classic-district-synced.kiriofClassicPin',
      (event) => {
        busy = false;
        changed();
        if (event.type === 'kiriof:classic-district-synced' && controller.getState().queue.error)
          void controller.retry();
        else controller.flush();
      },
    )
    .on('country_to_state_changed.kiriofClassicPin init_checkout.kiriofClassicPin', place);
  $(form).on('checkout_place_order.kiriofClassicPin', () => {
    changed();
    const state = controller.getState();
    const instant = form.querySelector(
      'input.shipping_method:checked[value^="kiriminaja-instant:"]',
    );
    const invalid = Boolean(
      instant && (!state.point || state.queue.pending || state.queue.inFlight || state.queue.error),
    );
    if (invalid)
      presentation.requirement = strings.pinRequirement || 'Please select a delivery pin.';
    return !invalid;
  });
  const observer = new MutationObserver(() => {
    if (!disposed) place();
  });
  form
    .querySelectorAll(
      '.woocommerce-billing-fields__field-wrapper, .woocommerce-shipping-fields__field-wrapper',
    )
    .forEach((wrapper) => observer.observe(wrapper, { childList: true }));
  const dispose = () => {
    if (disposed) return;
    disposed = true;
    observer.disconnect();
    clearTimeout(timer);
    controller.dispose();
    $(form).off('.kiriofClassicPin');
    $(root.document.body).off('.kiriofClassicPin');
    root.removeEventListener('pagehide', dispose);
    void unmount(component);
    panel.remove();
    hidden.remove();
    delete root.kiriofBuyerPin;
  };
  root.addEventListener('pagehide', dispose);
  if (controller.getState().point) void controller.sync();
  const bridge = { controller, changed, dispose };
  root.kiriofBuyerPin = bridge;
  return bridge;
}
