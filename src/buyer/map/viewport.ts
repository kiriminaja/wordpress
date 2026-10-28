import type { MapSessionOptions, Point } from './types';

export const BUYER_MAP_CENTER: [number, number] = [-7.801389, 110.364722];

type NormalizePoint = (latitude: unknown, longitude: unknown) => Point | null;

/** A regional camera fallback, never evidence of the buyer's delivery location. */
export function fallbackMapCenter(
  options: MapSessionOptions,
  normalize: NormalizePoint,
): [number, number] {
  const origin = options.coverage?.origin;
  const regional = origin && normalize(origin.latitude, origin.longitude);
  if (regional) return [Number(regional.latitude), Number(regional.longitude)];
  const configured = options.defaultCenter;
  const point = Array.isArray(configured)
    ? normalize(configured[0], configured[1])
    : configured && 'lat' in configured
      ? normalize(configured.lat, configured.lng)
      : null;
  return point ? [Number(point.latitude), Number(point.longitude)] : [...BUYER_MAP_CENTER];
}

/** Only reuse an existing grant. Never prompt for GPS or delay map construction. */
export function suggestGrantedDeviceCenter(
  options: MapSessionOptions,
  normalize: NormalizePoint,
  current: () => boolean,
  center: (point: Point) => void,
): void {
  if (options.initial || !options.permissions || !options.geolocation) return;
  try {
    void Promise.resolve(options.permissions.query({ name: 'geolocation' }))
      .then((permission) => {
        if (permission.state !== 'granted' || !current()) return;
        try {
          options.geolocation!.getCurrentPosition(
            (position) => {
              if (!current()) return;
              const point = normalize(position.coords.latitude, position.coords.longitude);
              if (point) center(point);
            },
            () => {},
            { enableHighAccuracy: false, timeout: 5000, maximumAge: 300000 },
          );
        } catch {
          // The existing fallback view remains usable if the grant was revoked.
        }
      })
      .catch(() => {});
  } catch {
    // Permissions API is optional; Locate remains an explicit user action.
  }
}
