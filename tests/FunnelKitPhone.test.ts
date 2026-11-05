import { describe, expect, test } from 'bun:test';
import { startFunnelKitPhone } from '../src/buyer/classic/funnelkit-phone';
import { happy } from './helpers/ui-runtime';

function fixture(funnelkit = true) {
  const window: any = new happy.Window();
  window.document.body.innerHTML = `<form class="checkout">${funnelkit ? '<input name="_wfacp_post_id" type="hidden" value="1248">' : ''}<input name="billing_phone" value="81229917900"><input name="wfacp_input_phone_field" type="hidden" value="{}"></form>`;
  const form = window.document.querySelector('form');
  const phone = form.querySelector('[name="billing_phone"]');
  let country = { iso2: 'id', dialCode: '62' };
  let ready = true;
  const events: string[] = [];
  const payloads: Record<string, unknown>[] = [];
  const handlers = new Map<string, () => void>();
  const timeouts = new Map<number, () => void>();
  const intervals = new Map<number, () => void>();
  let sequence = 0;
  window.setTimeout = (callback: () => void) => { const id = ++sequence; timeouts.set(id, callback); return id; };
  window.clearTimeout = (id: number) => timeouts.delete(id);
  window.setInterval = (callback: () => void) => { const id = ++sequence; intervals.set(id, callback); return id; };
  window.clearInterval = (id: number) => intervals.delete(id);
  window.wfacpIntlTelInput = { getInstance(input: unknown) { return ready && input === phone ? { getSelectedCountryData: () => country } : null; } };
  window.jQuery = () => ({
    trigger(event: string) {
      events.push(event);
      for (const [names, callback] of [...handlers])
        if (names.split(' ').some(name => name.split('.')[0] === event)) callback();
      payloads.push(JSON.parse(form.querySelector('[name="kiriof_funnelkit_phone_context"]').value));
    },
    on(event: string, callback: () => void) { handlers.set(event, callback); },
    off(namespace: string) { for (const key of handlers.keys()) if (key.endsWith(namespace)) handlers.delete(key); },
  });
  const bridge = startFunnelKitPhone(window)!;
  const flush = () => { for (const [id, callback] of [...timeouts]) { timeouts.delete(id); callback(); } };
  const poll = () => { for (const callback of intervals.values()) callback(); flush(); };
  return { window, form, phone, bridge, events, payloads, handlers, flush, poll,
    ready(value: boolean) { ready = value; },
    country(iso2: string, dialCode: string) { country = { iso2, dialCode }; },
    close() { bridge.dispose(); window.happyDOM.abort(); },
  };
}

describe('FunnelKit selected-country phone context', () => {
  test('submit captures the latest value without treating it as a completed checkout refresh', () => {
    const h = fixture();
    try {
      h.flush();
      h.phone.value = '87777777777';
      h.phone.dispatchEvent(new h.window.Event('input', { bubbles: true }));
      h.phone.value = '88888888888';
      h.form.dispatchEvent(new h.window.Event('submit', { bubbles: true, cancelable: true }));
      expect(JSON.parse(h.form.querySelector('[name="kiriof_funnelkit_phone_context"]').value).billing.value).toBe('88888888888');
      expect(h.events).toHaveLength(1);
      h.flush();
      expect(h.events).toHaveLength(2);
      expect(h.payloads.at(-1).billing.value).toBe('88888888888');
    } finally { h.close(); }
  });
  test('checkout capture cancels a pending refresh and serializes the latest silent value', () => {
    const h = fixture();
    try {
      h.flush(); h.events.length = 0;
      h.phone.value = '87777777777';
      h.phone.dispatchEvent(new h.window.Event('input', { bubbles: true }));
      h.phone.value = '89999999999';
      h.window.jQuery(h.window.document.body).trigger('update_checkout');
      expect(h.payloads.at(-1)).toEqual({ billing: { value: '89999999999', iso2: 'id', dialCode: '62' } });
      h.flush(); h.poll();
      expect(h.events).toEqual(['update_checkout']);
    } finally { h.close(); }
  });
  test('national autofill captures +62 without changing the visible value or FunnelKit JSON', () => {
    const h = fixture();
    try {
      h.flush();
      expect(h.payloads).toEqual([{ billing: { value: '81229917900', iso2: 'id', dialCode: '62' } }]);
      expect(h.phone.value).toBe('81229917900');
      expect(h.form.querySelector('[name="wfacp_input_phone_field"]').value).toBe('{}');
      h.poll(); h.poll();
      expect(h.events).toEqual(['update_checkout']);
      expect(startFunnelKitPhone(h.window)).toBe(h.bridge);
    } finally { h.close(); }
  });
  test('silent autofill and delayed widget initialization each refresh the current context once', () => {
    const h = fixture();
    try {
      h.ready(false); h.poll();
      expect(h.payloads.at(-1)).toEqual({});
      h.ready(true); h.poll();
      expect(h.payloads.at(-1)).toEqual({ billing: { value: '81229917900', iso2: 'id', dialCode: '62' } });
      h.phone.value = '87777777777'; h.poll();
      expect(h.payloads.at(-1)).toEqual({ billing: { value: '87777777777', iso2: 'id', dialCode: '62' } });
      const count = h.events.length; h.poll();
      expect(h.events).toHaveLength(count);
    } finally { h.close(); }
  });
  test('prefix changes and clears replace previous context; disposal stops polling', () => {
    const h = fixture();
    try {
      h.flush(); h.country('au', '61'); h.poll();
      expect(h.payloads.at(-1)).toEqual({ billing: { value: '81229917900', iso2: 'au', dialCode: '61' } });
      h.phone.value = ''; h.poll();
      expect(h.payloads.at(-1)).toEqual({ billing: { value: '', iso2: 'au', dialCode: '61' } });
      h.bridge.dispose(); const count = h.events.length;
      h.phone.value = '81229917900'; h.poll();
      expect(h.events).toHaveLength(count);
      expect(h.form.querySelector('[name="kiriof_funnelkit_phone_context"]')).toBeNull();
    } finally { h.close(); }
  });
  test('ordinary Classic form has no widget context and no refresh', () => {
    const h = fixture(false);
    try {
      h.flush(); h.poll();
      expect(h.events).toEqual([]);
      expect(h.form.querySelector('[name="kiriof_funnelkit_phone_context"]')).toBeNull();
    } finally { h.close(); }
  });
});
