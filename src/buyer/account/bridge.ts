import { flushSync, mount, unmount } from 'svelte';
import AccountDeliveryPin from '../components/AccountDeliveryPin.svelte';
import AccountStatus from './AccountStatus.svelte';
import { addressKey, lookupKey, normalizeAddress, readAddress, savedPoint } from './address';
import { bindDistrict } from './district';
import { createAccountState } from './state.svelte';
import type { AccountRoot, Address, Destination } from './types';
import type { Point } from '../map/types';

/** Woo owns the six shipping controls, validation, nonce and submit. No checkout stores. */
export function bootAccountShipping(root: AccountRoot) {
  if (root.__kiriofAccountShippingBridge) return root.__kiriofAccountShippingBridge;
  let active = true;
  const editors: (() => void)[] = [];
  const initialized = new WeakSet<HTMLElement>();
  const bridge = {
    dispose() {
      if (!active) return;
      active = false;
      root.document.removeEventListener('DOMContentLoaded', ready);
      root.removeEventListener('pagehide', bridge.dispose);
      editors.forEach((dispose) => dispose());
      if (root.__kiriofAccountShippingBridge === bridge) delete root.__kiriofAccountShippingBridge;
    },
  };
  function initialize(wrapper: HTMLElement) {
    if (initialized.has(wrapper)) return;
    const form = wrapper.closest('form');
    const hidden = wrapper.querySelector<HTMLInputElement>('[name="kiriof_account_destination"]');
    if (!form || !hidden || !wrapper.querySelector('#kiriof-account-district')) return;
    initialized.add(wrapper);
    const config = root.kiriofAccountShippingConfig || {};
    const state = createAccountState(readAddress(form!));
    let disposed = false;
    let saved: Destination | null;
    try {
      saved = hidden.value ? JSON.parse(hidden.value) : config.savedDestination || null;
    } catch {
      saved = null;
    }
    if (
      saved &&
      lookupKey(normalizeAddress(saved)) === lookupKey(state.address) &&
      /^[1-9][0-9]*$/.test(String(saved.district_id))
    ) {
      state.selection = {
        id: String(saved.district_id),
        label: String(saved.district_label || ''),
      };
      state.pin = savedPoint(saved, state.address);
    }
    function publish() {
      hidden!.value = JSON.stringify({
        version: state.pin ? 2 : 1,
        district_id: state.selection?.id || '',
        district_label: state.selection?.label || '',
        postcode: state.address.postcode,
        country: state.address.country,
        address_type: 'shipping',
        ...(state.pin
          ? {
              destination_latitude: state.pin.latitude,
              destination_longitude: state.pin.longitude,
              shipping_address: { ...state.address },
            }
          : {}),
      });
      flushSync();
    }
    const district = bindDistrict(root, form, wrapper, state, config, publish, () => flushSync());
    function synchronize() {
      if (disposed) return;
      const next = readAddress(form!);
      if (addressKey(next) === addressKey(state.address)) return;
      const changedLookup = lookupKey(next) !== lookupKey(state.address);
      state.address = next;
      state.pin = null;
      if (changedLookup) state.selection = null;
      publish();
      district.lookup();
    }
    function select(point: Point | null, expected: Address) {
      if (disposed || addressKey(expected) !== addressKey(readAddress(form!))) return false;
      state.pin = point;
      publish();
      return true;
    }
    const components: ReturnType<typeof mount>[] = [];
    const badges = wrapper.querySelector<HTMLElement>('.kiriof-account-shipping-status');
    if (badges) {
      badges.replaceChildren();
      badges.classList.add('kiriof-address-status');
      badges.setAttribute('role', 'status');
      badges.setAttribute('aria-live', 'polite');
      badges.setAttribute('aria-atomic', 'true');
      components.push(
        mount(AccountStatus, { target: badges, props: { state, strings: config.i18n || {} } }),
      );
    }
    const section = wrapper.querySelector<HTMLElement>('.kiriof-buyer-map');
    // Reuse the server-rendered slot's position; only map presentation is replaced.
    if (section) {
      const target = root.document.createElement('div');
      section.replaceWith(target);
      components.push(
        mount(AccountDeliveryPin, {
          target,
          props: { state, config, root, form, select, synchronize },
        }),
      );
    }
    const edit = (event: Event) => {
      if (disposed) return;
      synchronize();
      if (
        event.type === 'change' &&
        event.target === wrapper.querySelector('#kiriof-account-district')
      )
        district.choose();
    };
    const submit = () => {
      if (!disposed) {
        synchronize();
        publish();
      }
    };
    form.addEventListener('input', edit);
    form.addEventListener('change', edit);
    form.addEventListener('submit', submit);
    editors.push(() => {
      disposed = true;
      district.dispose();
      form.removeEventListener('input', edit);
      form.removeEventListener('change', edit);
      form.removeEventListener('submit', submit);
      components.forEach((component) => void unmount(component));
    });
    if (saved?.version === 2 && !state.pin) publish();
    flushSync();
    district.lookup();
  }
  function ready() {
    if (active)
      root.document.querySelectorAll<HTMLElement>('.kiriof-account-shipping').forEach(initialize);
  }
  root.__kiriofAccountShippingBridge = bridge;
  root.addEventListener('pagehide', bridge.dispose);
  if (root.document.readyState === 'loading')
    root.document.addEventListener('DOMContentLoaded', ready, { once: true });
  else ready();
  return bridge;
}
