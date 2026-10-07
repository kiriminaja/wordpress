import { describe, expect, test } from 'bun:test';
import { readFileSync } from 'node:fs';
import {
  destinationForAddress,
  shippingAddress,
  shippingAddressKey,
  savedCoordinates,
  validCoordinate,
} from '../src/buyer/blocks/address';

const address = { address_1: ' Main Road ', address_2: '', city: 'Jakarta', state: 'JK', postcode: '55 581', country: 'id' };
const saved = {
  version: 2, district_id: '7', district_label: 'District Seven', postcode: '55581', country: 'ID',
  destination_latitude: '0', destination_longitude: 0, shipping_address: address,
};
function source(file: string) { return readFileSync(new URL(`../src/buyer/${file}`, import.meta.url), 'utf8'); }

describe('Blocks source ownership and typed destination boundary', () => {
  test('address identity includes the complete native shipping address, not just postcode', () => {
    expect(shippingAddress(address)).toEqual({ ...address, address_1: 'Main Road', postcode: '55581', country: 'ID' });
    expect(shippingAddressKey(address)).not.toBe(shippingAddressKey({ ...address, address_1: 'Other Road' }));
  });
  test('matching saved zero pin restores without inventing a district or accepting malformed coordinates', () => {
    expect(savedCoordinates(saved)).toEqual({ latitude: '0.0000000', longitude: '0.0000000', key: shippingAddressKey(address) });
    for (const malformed of ['', '0x01', '1e2', 'Infinity', null, true]) {
      expect(savedCoordinates({ ...saved, destination_longitude: malformed })).toBeNull();
    }
    expect(savedCoordinates({ ...saved, district_id: '0' })).toBeNull();
    expect(savedCoordinates({ ...saved, shipping_address: { ...address, country: 'SG' } })).toBeNull();
    expect(savedCoordinates({ ...saved, postcode: '12345' })).toBeNull();
    expect(savedCoordinates({ ...saved, version: 1 })).toBeNull();
  });
  test('version two pin communications preserve recipient identity; district-only remains version one', () => {
    const destination = destinationForAddress({ id: '7', label: 'District Seven' }, address, { latitude: 0, longitude: '106' });
    expect(destination).toMatchObject({ version: 2, district_id: '7', postcode: '55581', destination_latitude: '0', destination_longitude: '106', shipping_address: shippingAddress(address) });
    expect(destinationForAddress(null, address)).toMatchObject({ version: 1, district_id: '', district_label: '', postcode: '55581' });
    expect(destinationForAddress(null, address, { latitude: 91, longitude: 0 }).version).toBe(1);
    expect(validCoordinate('', 90)).toBe(false);
    expect(validCoordinate(false, 90)).toBe(false);
    expect(validCoordinate(0, 90)).toBe(true);
  });
  test('source entry explicitly installs presentation before Woo components and never evaluates old assets', () => {
    const entry = source('entries/blocks.ts');
    expect(entry.indexOf('bootAddressPresentation(root)')).toBeLessThan(entry.indexOf('bootBuyerCheckout(root)'));
    expect(entry.indexOf('bootBuyerCheckout(root)')).toBeLessThan(entry.indexOf('bootBlocksMap(root)'));
    for (const file of ['checkout', 'map', 'presentation', 'coupon-notice']) {
      const module = source(`blocks/${file}.ts`);
      expect(module).not.toMatch(/assets\/|eval\(|new Function/);
      expect(module).not.toMatch(/from ['"]react['"]/);
    }
    expect(source('blocks/checkout.ts')).toContain("from '../state/checkout-queue'");
    expect(source('blocks/checkout.ts')).toContain("from '../state/shipping-selection'");
    expect(source('blocks/map.ts')).toContain("from '../map/leaflet'");
    expect(source('blocks/map.ts')).not.toContain('function createMapSession');
  });
  test('Svelte owns actual map presentation children; native React retains the exact hosts and lifecycle', () => {
    const bridge = source('blocks/svelte-bridge.ts');
    expect(bridge).toContain('mount(component');
    expect(bridge).toContain('unmount(instance)');
    expect(bridge).toContain("element.createElement('div', { ...props.attributes, ref: node })");
    expect(source('blocks/map.ts')).toContain('h(mapPresentation.Information');
    expect(source('blocks/map.ts')).toContain('h(mapPresentation.Status');
    expect(source('components/MapInformation.svelte')).toContain('kiriof-buyer-map__optional');
    expect(source('components/PinStatus.svelte')).toContain('m5 12 4 4 10-10');
  });
});
