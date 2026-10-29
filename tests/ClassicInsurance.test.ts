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
