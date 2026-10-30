import { isFunnelKitCheckout } from './address-scope';

type PhoneWidget = {
  getSelectedCountry?(): { iso2?: string; dialCode?: string };
  getSelectedCountryData?(): { iso2?: string; dialCode?: string };
};
type PhoneLibrary = { getInstance?(input: HTMLInputElement): PhoneWidget | null };
type PhoneContextWindow = Window & {
  wfacpIntlTelInput?: PhoneLibrary;
  intlTelInput?: PhoneLibrary;
  intlTelInputGlobals?: PhoneLibrary;
  kiriofFunnelKitPhone?: { dispose(): void };
  jQuery?: (node: Element) => {
    trigger(event: string): unknown;
    on?(events: string, callback: () => void): unknown;
    off?(events: string): unknown;
  };
};
const contextName = 'kiriof_funnelkit_phone_context';

/** Capture the selected dial code even when browser autofill emits no input event. */
export function startFunnelKitPhone(root: PhoneContextWindow = window as PhoneContextWindow) {
  if (root.kiriofFunnelKitPhone) return root.kiriofFunnelKitPhone;
  const document = root.document;
  if (!root.jQuery || document.querySelector('.wc-block-checkout, .wp-block-woocommerce-checkout'))
    return;
  let disposed = false;
  let timer: number | undefined;
  let lastForm: HTMLFormElement | null = null;
  let lastValue = '';
  const sync = (refresh = true) => {
    if (disposed) return;
    const form = document.querySelector<HTMLFormElement>('form.checkout, form.woocommerce-checkout');
    if (!form || !isFunnelKitCheckout(form)) return;
    const context: Record<string, { value: string; iso2: string; dialCode: string }> = {};
    for (const scope of ['billing', 'shipping']) {
      const input = form.querySelector<HTMLInputElement>(`[name="${scope}_phone"]`);
      if (!input || input.disabled || input.value.length > 64) continue;
      try {
        const widget = [root.wfacpIntlTelInput, root.intlTelInput, root.intlTelInputGlobals]
          .map((library) => library?.getInstance?.(input))
          .find(Boolean);
        const country = widget?.getSelectedCountry?.() || widget?.getSelectedCountryData?.();
        if (!country || typeof country.iso2 !== 'string' || typeof country.dialCode !== 'string')
          continue;
        context[scope] = {
          value: input.value,
          iso2: country.iso2.toLowerCase(),
          dialCode: country.dialCode,
        };
      } catch {
        // An initializing widget cannot establish a dial code yet.
      }
    }
    const value = JSON.stringify(context);
    let hidden = form.querySelector<HTMLInputElement>(`input[name="${contextName}"]`);
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = contextName;
      form.append(hidden);
    }
    if (hidden.value !== value) hidden.value = value;
    const changed = lastForm !== form || lastValue !== value;
    lastForm = form;
    lastValue = value;
    if (changed && refresh) {
      root.clearTimeout(timer);
      timer = root.setTimeout(() => {
        if (!disposed && form.isConnected) root.jQuery?.(document.body).trigger('update_checkout');
      }, 200);
    }
  };
  const event = (event: Event) => {
    const target = event.target as Element | null;
    if (target?.matches?.('[name="billing_phone"], [name="shipping_phone"]')) sync();
  };
  const capture = () => sync(false);
  const refresh = () => sync();
  const poll = root.setInterval(() => {
    if (document.visibilityState !== 'hidden') sync();
  }, 500);
  const bridge = {
    dispose() {
      disposed = true;
      root.clearTimeout(timer);
      root.clearInterval(poll);
      for (const name of ['input', 'change', 'focusout', 'countrychange']) document.removeEventListener(name, event, true);
      document.removeEventListener('submit', capture, true);
      root.jQuery?.(document.body).off?.('.kiriofFunnelKitPhone');
      root.removeEventListener('pagehide', bridge.dispose);
      for (const field of document.querySelectorAll(`input[name="${contextName}"]`)) field.remove();
      if (root.kiriofFunnelKitPhone === bridge) delete root.kiriofFunnelKitPhone;
    },
  };
  for (const name of ['input', 'change', 'focusout', 'countrychange']) document.addEventListener(name, event, true);
  document.addEventListener('submit', capture, true);
  root.jQuery(document.body).on?.('update_checkout.kiriofFunnelKitPhone', capture);
  root.jQuery(document.body).on?.('updated_checkout.kiriofFunnelKitPhone wfacp_intl_setup.kiriofFunnelKitPhone', refresh);
  root.addEventListener('pagehide', bridge.dispose);
  root.kiriofFunnelKitPhone = bridge;
  sync();
  return bridge;
}
