import { describe, expect, test } from 'bun:test';
import { startClassicPhoneValidation, validCheckoutPhone } from '../src/buyer/classic/phone';
import { happy } from './helpers/ui-runtime';
import cases from './fixtures/checkout-phone-cases.json';

function fixture() {
  const window: any = new happy.Window();
  window.document.body.innerHTML = `<form class="checkout" novalidate><input class="shipping_method" name="shipping_method[0]" value="kiriminaja-official_jne_REG" type="radio" checked><input type="checkbox" name="ship_to_different_address"><p class="form-row woocommerce-validated"><input name="billing_phone" type="tel" aria-describedby="billing-help"><span id="billing-help">Phone</span></p><p class="form-row"><input name="shipping_phone" type="tel"></p><button>Place order</button></form>`;
  const handlers = new Map<any, Map<string, () => boolean | void>>();
  window.jQuery = (node: any) => ({
    on(name: string, callback: () => boolean | void) { if (!handlers.has(node)) handlers.set(node, new Map()); handlers.get(node)!.set(name, callback); },
    off() { handlers.delete(node); },
  });
  window.kiriofBillingAddressConfig = { i18n: { invalidPhone: 'Please enter a valid phone number.' } };
  const bridge = startClassicPhoneValidation(window)!;
  const form = window.document.querySelector('form');
  const billing = form.querySelector('[name="billing_phone"]');
  const shipping = form.querySelector('[name="shipping_phone"]');
  const enter = (input: any, value: string) => { input.value = value; input.dispatchEvent(new window.Event('input', { bubbles: true })); };
  return { window, form, billing, shipping, enter, bridge, submit: () => handlers.get(form)!.get('checkout_place_order.kiriofPhone')!(), refresh: () => handlers.get(window.document.body)!.get('updated_checkout.kiriofPhone')!(), validate: () => handlers.get(window.document.body)!.get('validate.kiriofPhone')!(), close() { bridge.dispose(); window.happyDOM.abort(); } };
}

describe('Classic checkout phone feedback', () => {
  test('frontend matches PHP phone syntax cases', () => {
    for (const value of cases.valid) expect(validCheckoutPhone(value)).toBe(true);
    for (const value of cases.invalid) if (typeof value === 'string') expect(validCheckoutPhone(value)).toBe(false);
  });
  test('shows an accessible error immediately, blocks submission, and clears on correction without rewriting', () => {
    const h = fixture();
    try {
      expect(h.form.querySelector('.kiriof-phone-error')).toBeNull();
      h.enter(h.billing, '08523434AAAAAAXXXXX55678');
      expect(h.billing.value).toBe('08523434AAAAAAXXXXX55678');
      expect(h.billing.getAttribute('aria-invalid')).toBe('true');
      expect(h.billing.getAttribute('aria-describedby')).toContain('billing-help');
      expect(h.form.querySelector('.kiriof-phone-error').textContent).toBe('Please enter a valid phone number.');
      expect(h.submit()).toBe(false);
      expect(h.window.document.activeElement).toBe(h.billing);
      h.enter(h.billing, '+62 (812) 3456-7890');
      expect(h.billing.value).toBe('+62 (812) 3456-7890');
      expect(h.billing.getAttribute('aria-invalid')).toBeNull();
      expect(h.billing.getAttribute('aria-describedby')).toBe('billing-help');
      expect(h.billing.validity.customError).toBe(false);
      expect(h.submit()).toBe(true);
      h.billing.value = '0812bad12345';
      const submitEvent = new h.window.Event('submit', { bubbles: true, cancelable: true });
      h.form.dispatchEvent(submitEvent);
      expect(submitEvent.defaultPrevented).toBe(true);
    } finally { h.close(); }
  });
  test('Woo field validation cannot mark an invalid short number as validated', async () => {
    const h = fixture();
    try {
      h.enter(h.billing, '1234567');
      h.billing.closest('.form-row').className = 'form-row woocommerce-validated';
      h.billing.removeAttribute('aria-invalid');
      h.validate();
      await new Promise<void>((resolve) => h.window.queueMicrotask(resolve));
      expect(h.billing.closest('.form-row').classList.contains('woocommerce-validated')).toBe(false);
      expect(h.billing.getAttribute('aria-invalid')).toBe('true');
      expect(h.submit()).toBe(false);
    } finally { h.close(); }
  });
  test('empty fresh form stays quiet until submit and hidden shipping stays inactive', () => {
    const h = fixture();
    try {
      expect(h.submit()).toBe(false);
      h.enter(h.billing, '081234567890');
      h.enter(h.shipping, '0812bad12345');
      expect(h.submit()).toBe(true);
      const separate = h.form.querySelector('[name="ship_to_different_address"]');
      separate.checked = true;
      separate.dispatchEvent(new h.window.Event('change', { bubbles: true }));
      expect(h.submit()).toBe(false);
      separate.checked = false;
      separate.dispatchEvent(new h.window.Event('change', { bubbles: true }));
      expect(h.shipping.validity.customError).toBe(false);
      expect(h.submit()).toBe(true);
    } finally { h.close(); }
  });
  test('fragment replacement stays validated and switching to local pickup clears errors', () => {
    const h = fixture();
    try {
      h.enter(h.billing, '0812bad12345');
      h.billing.outerHTML = '<input name="billing_phone" type="tel" value="bad">';
      h.refresh();
      expect(h.submit()).toBe(false);
      h.form.querySelector('.shipping_method').value = 'local_pickup:7';
      h.refresh();
      expect(h.submit()).toBe(true);
      expect(h.form.querySelector('[name="billing_phone"]').validity.customError).toBe(false);
    } finally { h.close(); }
  });
  test('repeated startup is idempotent and preserves unrelated custom errors', () => {
    const h = fixture();
    try {
      expect(startClassicPhoneValidation(h.window)).toBe(h.bridge);
      h.billing.setCustomValidity('Merchant validation');
      h.enter(h.billing, '0812bad12345');
      h.enter(h.billing, '081234567890');
      expect(h.billing.validationMessage).toBe('Merchant validation');
    } finally { h.close(); }
  });
  test('strictly rejects a trailing newline and dispose removes only plugin state', () => {
    const h = fixture();
    try {
      expect(validCheckoutPhone('081234567890\n')).toBe(false);
      h.enter(h.billing, '0812bad12345');
      expect(h.submit()).toBe(false);
      h.billing.setAttribute('aria-invalid', 'grammar');
      h.bridge.dispose();
      expect(h.form.querySelector('.kiriof-phone-error')).toBeNull();
      expect(h.billing.getAttribute('aria-describedby')).toBe('billing-help');
      expect(h.billing.getAttribute('aria-invalid')).toBe('grammar');
      expect(h.billing.validity.customError).toBe(false);
      expect(h.billing.closest('.form-row')?.classList.contains('woocommerce-invalid-phone')).toBe(false);
    } finally { h.window.happyDOM.abort(); }
  });
});
