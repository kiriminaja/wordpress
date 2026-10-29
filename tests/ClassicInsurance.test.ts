import { describe, expect, test } from 'bun:test';
import { startClassicInsurance } from '../src/buyer/classic/insurance';
import { happy } from './helpers/ui-runtime';

function fixture(blocks = false) {
  const window: any = new happy.Window();
  window.document.body.innerHTML = `<form class="checkout"><input type="hidden" name="kiriof_insurance" value="0"><input type="checkbox" id="kiriof_insurance" name="kiriof_insurance" value="1"><input type="checkbox" name="unrelated"></form>${blocks ? '<div class="wc-block-checkout"></div>' : ''}`;
  const events: string[] = [];
  const payloads: string[] = [];
  const form = window.document.querySelector('form');
  window.jQuery = () => ({
    trigger(event: string) {
      events.push(event);
      const data = new URLSearchParams();
      for (const input of form.querySelectorAll('input'))
        if (!input.disabled && (input.type !== 'checkbox' || input.checked))
          data.append(input.name, input.value);
      payloads.push(data.toString());
    },
  });
  const bridge = startClassicInsurance(window);
  const change = (checked: boolean) => {
    const input = form.querySelector('input[type="checkbox"][name="kiriof_insurance"]');
    input.checked = checked;
    input.dispatchEvent(new window.Event('change', { bubbles: true }));
  };
  return {
    window,
    form,
    events,
    payloads,
    bridge,
    change,
    close() {
      bridge?.dispose();
      window.happyDOM.abort();
    },
  };
}

describe('Classic insurance totals refresh', () => {
  test('ordinary Classic checkout keeps the insurance row in its original position', async () => {
    const h = fixture();
    try {
      h.form.insertAdjacentHTML(
        'afterbegin',
        '<div class="wfacp_shipping_options"><input class="shipping_method" type="radio" value="jnt"></div>',
      );
      const row = h.window.document.createElement('h3');
      row.id = 'kiriof-classic-insurance-field';
      h.form.append(row);
      await h.window.happyDOM.waitUntilComplete();
      expect(row.parentElement).toBe(h.form);
      expect(row.classList.contains('kiriof-funnelkit-insurance-field')).toBe(false);
      expect(h.form.querySelector('.wfacp_shipping_options').nextElementSibling).not.toBe(row);
    } finally {
      h.close();
    }
  });

  test('FunnelKit moves the original insurance row after courier fragments and preserves form values', async () => {
    const h = fixture();
    try {
      h.bridge?.dispose();
      h.form.insertAdjacentHTML(
        'afterbegin',
        '<input type="hidden" name="_wfacp_post_id" value="1248"><section class="shipping-section"><div class="wfacp_shipping_options"><input class="shipping_method" name="shipping_method[0]" value="jnt" type="radio" checked></div></section><section id="summary">Summary</section>',
      );
      const row = h.window.document.createElement('h3');
      row.id = 'kiriof-classic-insurance-field';
      for (const input of [...h.form.querySelectorAll('[name="kiriof_insurance"]')])
        row.append(input);
      h.form.append(row);
      const checkbox = row.querySelector('input[type="checkbox"]');
      checkbox.checked = true;
      const bridge = startClassicInsurance(h.window)!;
      expect(row.previousElementSibling.classList.contains('wfacp_shipping_options')).toBe(true);
      expect(row.parentElement.classList.contains('shipping-section')).toBe(true);
      expect(checkbox.checked).toBe(true);
      const old = h.form.querySelector('.wfacp_shipping_options');
      old.outerHTML =
        '<div class="wfacp_shipping_options"><input class="shipping_method" name="shipping_method[0]" value="jne" type="radio" checked></div>';
      await h.window.happyDOM.waitUntilComplete();
      expect(row.previousElementSibling).toBe(h.form.querySelector('.wfacp_shipping_options'));
      expect(h.form.querySelectorAll('#kiriof-classic-insurance-field')).toHaveLength(1);
      expect(h.form.querySelector('input[type="checkbox"][name="kiriof_insurance"]')).toBe(
        checkbox,
      );
      h.form.querySelector('.shipping-section').outerHTML =
        '<section class="shipping-section"><div class="wfacp_shipping_options"><input class="shipping_method" name="shipping_method[0]" value="jnt" type="radio" checked></div></section>';
      await h.window.happyDOM.waitUntilComplete();
      expect(row.previousElementSibling).toBe(h.form.querySelector('.wfacp_shipping_options'));
      expect(row.querySelector('input[type="checkbox"]')).toBe(checkbox);
      expect(checkbox.checked).toBe(true);
      h.change(false);
      expect(h.events).toEqual(['update_checkout']);
      expect(h.payloads[0]).toContain('kiriof_insurance=0');
      expect(h.payloads[0]).not.toContain('kiriof_insurance=1');
      bridge.dispose();
      expect(row.parentElement).toBe(h.form);
    } finally {
      h.close();
    }
  });
  test('checked and unchecked native form values refresh without a map or Instant adapter', () => {
    const h = fixture();
    try {
      expect(h.events).toEqual([]);
      h.change(true);
      h.change(false);
      expect(h.events).toEqual(['update_checkout', 'update_checkout']);
      expect(h.payloads).toEqual(['kiriof_insurance=0&kiriof_insurance=1', 'kiriof_insurance=0']);
    } finally {
      h.close();
    }
  });
  test('fragment-replaced checkbox uses delegated listener without extra startup refresh', () => {
    const h = fixture();
    try {
      expect(startClassicInsurance(h.window)).toBe(h.bridge);
      h.form.querySelector('#kiriof_insurance').outerHTML =
        '<input type="checkbox" id="kiriof_insurance" name="kiriof_insurance" value="1">';
      h.change(true);
      expect(h.events).toEqual(['update_checkout']);
      h.form
        .querySelector('[name="unrelated"]')
        .dispatchEvent(new h.window.Event('change', { bubbles: true }));
      expect(h.events).toHaveLength(1);
      h.window.dispatchEvent(new h.window.Event('pagehide'));
      h.change(false);
      expect(h.events).toHaveLength(1);
    } finally {
      h.close();
    }
  });
  test('disabled forced checkbox and Blocks do not start competing fee writers', () => {
    const h = fixture();
    const block = fixture(true);
    try {
      h.form.querySelector('#kiriof_insurance').disabled = true;
      h.change(true);
      expect(h.events).toEqual([]);
      expect(block.bridge).toBeUndefined();
      block.change(true);
      expect(block.events).toEqual([]);
    } finally {
      h.close();
      block.close();
    }
  });
});
