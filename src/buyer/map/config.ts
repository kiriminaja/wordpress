export const DEFAULT_MAP_PROVIDER = 'leaflet' as const;
export const DEFAULT_MAP_TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
export const DEFAULT_MAP_ATTRIBUTION =
  '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
export interface MapProviderConfigInput {
  provider?: unknown;
  tiles?: unknown;
  attribution?: unknown;
  apiKey?: unknown;
}
export type MapProviderId = 'leaflet' | 'google';
export type MapProviderConfig =
  | { readonly provider: 'leaflet'; readonly tiles: string; readonly attribution: string }
  | {
      readonly provider: 'google';
      readonly apiKey: string;
      /** Compatibility fields for synchronous Leaflet consumers; never used by Google. */
      readonly tiles: string;
      readonly attribution: string;
    };
export function isSupportedMapProvider(provider: unknown): provider is MapProviderId {
  return provider === 'leaflet' || provider === 'google';
}
export function isValidGoogleMapsKey(key: unknown): key is string {
  return typeof key === 'string' && /^[A-Za-z0-9_-]{8,256}$/.test(key);
}
/** Validate before any runtime/network work. Errors never echo configuration or credentials. */
export function resolveMapConfig(input: MapProviderConfigInput = {}): MapProviderConfig {
  const provider = input.provider ?? DEFAULT_MAP_PROVIDER;
  if (!isSupportedMapProvider(provider)) throw new Error('Unsupported map provider');
  if (provider === 'google') {
    if (!isValidGoogleMapsKey(input.apiKey)) throw new Error('Map unavailable');
    return Object.freeze({
      provider,
      apiKey: input.apiKey,
      tiles: DEFAULT_MAP_TILES,
      attribution: DEFAULT_MAP_ATTRIBUTION,
    });
  }
  const tiles = input.tiles ?? DEFAULT_MAP_TILES;
  if (typeof tiles !== 'string' || !tiles.startsWith('https://'))
    throw new Error('Map unavailable');
  const attribution = input.attribution ?? DEFAULT_MAP_ATTRIBUTION;
  if (typeof attribution !== 'string') throw new Error('Map unavailable');
  return Object.freeze({ provider, tiles, attribution });
}
