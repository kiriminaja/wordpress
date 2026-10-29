import { afterEach, describe, expect, test } from 'bun:test';
import { happy } from './helpers/ui-runtime';
import { clearFunnelKitLayout, syncFunnelKitLayout } from '../src/buyer/classic/funnelkit-layout';

const windows: InstanceType<typeof happy.Window>[] = [];
afterEach(() => windows.splice(0).forEach((window) => window.happyDOM.abort()));

function fixture() {
  const window = new happy.Window();
  windows.push(window);
  window.document.body.innerHTML = `<form class="checkout kiriof-funnelkit-checkout">
    ${['shipping', 'billing']
      .map(
        (scope) => `<div class="wfacp_divider_${scope}">
      <p id="${scope}_address_1_field" class="wfacp-col-left-half"></p>
      <p id="${scope}_postcode_field" class="form-row wfacp-col-left-third"><input id="${scope}_postcode"></p>
      <p id="${scope}_country_field" class="form-row wfacp-col-left-third" style="display:none"><input type="hidden" value="ID"></p>
      <p id="${scope}_state_field" class="form-row wfacp-col-left-third kiriof-funnelkit-select-field"></p>
      <p id="${scope}_district_field" class="form-row wfacp-col-full kiriof-funnelkit-select-field"></p>
    </div>`,
      )
      .join('')}
  </form>`;
  const form = window.document.querySelector('form') as unknown as HTMLFormElement;
  const row = (id: string) => form.querySelector<HTMLElement>(`#${id}`)!;
  return { form, row };
}

describe('FunnelKit adjacent address thirds', () => {
  test('redistributes hidden country independently per address without moving nodes', () => {
    const { form, row } = fixture();
    const order = Array.from(form.querySelectorAll('[id]'), (node) => node.id);
    row('billing_country_field').style.display = 'block';
    syncFunnelKitLayout(form);
    expect(row('shipping_state_field').style.getPropertyValue('--kiriof-region-width')).toBe('50%');
    expect(row('shipping_postcode_field').style.getPropertyValue('--kiriof-region-width')).toBe(
      '50%',
    );
    expect(row('billing_state_field').style.getPropertyValue('--kiriof-region-width')).toBe(
      `${100 / 3}%`,
    );
    expect(row('shipping_country_field').style.display).toBe('none');
    expect(row('shipping_district_field').classList.contains('kiriof-funnelkit-region-field')).toBe(
      false,
    );
    expect(row('shipping_address_1_field').className).toBe('wfacp-col-left-half');
    row('billing_country_field').style.display = 'none';
    clearFunnelKitLayout(form);
    expect(Array.from(form.querySelectorAll('[id]'), (node) => node.id)).toEqual(order);
    expect(form.querySelector('.kiriof-funnelkit-region-field')).toBeNull();
  });

  test('recounts visibility and is mutation-idempotent', () => {
    const { form, row } = fixture();
    syncFunnelKitLayout(form);
    const before = form.innerHTML;
    syncFunnelKitLayout(form);
    expect(form.innerHTML).toBe(before);
    row('shipping_country_field').style.display = 'block';
    syncFunnelKitLayout(form);
    expect(row('shipping_state_field').style.getPropertyValue('--kiriof-region-width')).toBe(
      `${100 / 3}%`,
    );
    row('shipping_country_field').hidden = true;
    syncFunnelKitLayout(form);
    expect(row('shipping_state_field').style.getPropertyValue('--kiriof-region-width')).toBe('50%');
  });

  test('does not combine different containers or unrelated intervening fields', () => {
    const { form, row } = fixture();
    row('shipping_state_field').before(row('shipping_address_1_field'));
    row('billing_district_field').append(row('billing_country_field'));
    syncFunnelKitLayout(form);
    expect(form.querySelector('.kiriof-funnelkit-region-field')).toBeNull();
  });

  test('samples native input height rather than wrapping state text', () => {
    const { form, row } = fixture();
    row('shipping_postcode').getBoundingClientRect = () => ({ height: 47 }) as DOMRect;
    syncFunnelKitLayout(form);
    expect(row('shipping_state_field').style.getPropertyValue('--kiriof-control-height')).toBe(
      '47px',
    );
    expect(row('shipping_district_field').style.getPropertyValue('--kiriof-control-height')).toBe(
      '47px',
    );
    clearFunnelKitLayout(form);
    expect(row('shipping_state_field').style.getPropertyValue('--kiriof-control-height')).toBe('');
  });
});
