import { expect, test } from 'bun:test';
import { fallbackMapCenter, suggestGrantedDeviceCenter } from '../src/buyer/map/viewport';
import { normalizePoint } from '../src/buyer/map/leaflet';
import type { MapSessionOptions } from '../src/buyer/map/types';

const settle = async () => { for (let i = 0; i < 6; i++) await Promise.resolve(); };

test('regional origin wins over global defaults; malformed locations fall back safely', () => {
  expect(fallbackMapCenter({ coverage: { origin: { latitude: -6.9, longitude: 107.6 }, radiusMeters: 40000 }, defaultCenter: [-6.2, 106.8] }, normalizePoint)).toEqual([-6.9, 107.6]);
  expect(fallbackMapCenter({ coverage: { origin: { latitude: 'bad', longitude: 0 }, radiusMeters: 40000 }, defaultCenter: [-6.2, 106.8] }, normalizePoint)).toEqual([-6.2, 106.8]);
  expect(fallbackMapCenter({}, normalizePoint)).toEqual([-7.801389, 110.364722]);
});

for (const state of ['prompt', 'denied', 'granted'] as const) {
  test(`only an existing ${state} permission may suggest a device view`, async () => {
    const centers: unknown[] = [], requests: unknown[] = [];
    const options: MapSessionOptions = {
      permissions: { query: async (descriptor) => { expect(descriptor).toEqual({ name: 'geolocation' }); return { state }; } },
      geolocation: { getCurrentPosition(success, _failure, config) { requests.push(config); success({ coords: { latitude: -6.9, longitude: 107.6 } as GeolocationCoordinates }); } },
    };
    suggestGrantedDeviceCenter(options, normalizePoint, () => true, point => centers.push(point));
    expect(requests).toEqual([]);
    await settle();
    expect(requests).toHaveLength(state === 'granted' ? 1 : 0);
    expect(centers).toHaveLength(state === 'granted' ? 1 : 0);
  });
}

test('saved pins and missing Permissions API never trigger GPS', async () => {
  let requests = 0;
  const base: MapSessionOptions = { permissions: { query: async () => ({ state: 'granted' }) }, geolocation: { getCurrentPosition() { requests++; } } };
  suggestGrantedDeviceCenter({ ...base, initial: { latitude: 0, longitude: 0 } }, normalizePoint, () => true, () => {});
  suggestGrantedDeviceCenter({ ...base, permissions: undefined }, normalizePoint, () => true, () => {});
  await settle(); expect(requests).toBe(0);
});

test('stale, denied, throwing and malformed GPS never override the fallback', async () => {
  const centers: unknown[] = [];
  let current = true, success: GeolocationPositionCallback | undefined;
  suggestGrantedDeviceCenter({ permissions: { query: async () => ({ state: 'granted' }) }, geolocation: { getCurrentPosition(ok) { success = ok as GeolocationPositionCallback; } } }, normalizePoint, () => current, point => centers.push(point));
  await settle(); current = false; success!({ coords: { latitude: -6.9, longitude: 107.6 } } as GeolocationPosition);
  for (const geolocation of [
    { getCurrentPosition() { throw new Error('permission revoked'); } },
    { getCurrentPosition(_ok: any, failure: any) { failure({ code: 1 }); } },
    { getCurrentPosition(ok: any) { ok({ coords: { latitude: 100, longitude: 0 } }); } },
  ]) {
    suggestGrantedDeviceCenter({ permissions: { query: async () => ({ state: 'granted' }) }, geolocation }, normalizePoint, () => true, point => centers.push(point));
  }
  await settle(); expect(centers).toEqual([]);
});
