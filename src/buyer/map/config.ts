export const DEFAULT_MAP_PROVIDER = 'leaflet' as const;
export const DEFAULT_MAP_TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
export const DEFAULT_MAP_ATTRIBUTION =
  '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
export interface MapProviderConfigInput {
  provider?: unknown;
  tiles?: unknown;
  attribution?: unknown;
}
export interface MapProviderConfig {
  readonly provider: 'leaflet';
  readonly tiles: string;
  readonly attribution: string;
}
export function isSupportedMapProvider(provider: unknown): provider is 'leaflet' {
  return provider === DEFAULT_MAP_PROVIDER;
}
/** Resolve public display config only. Unknown providers fail before map or tile creation. */
export function resolveMapConfig(input: MapProviderConfigInput = {}): MapProviderConfig {
  const provider = input.provider ?? DEFAULT_MAP_PROVIDER;
  if (!isSupportedMapProvider(provider)) throw new Error('Unsupported map provider');
  const tiles = input.tiles ?? DEFAULT_MAP_TILES;
  if (typeof tiles !== 'string' || !tiles.startsWith('https://'))
    throw new Error('Map unavailable');
  const attribution = input.attribution ?? DEFAULT_MAP_ATTRIBUTION;
  if (typeof attribution !== 'string') throw new Error('Map unavailable');
  return Object.freeze({ provider, tiles, attribution });
}
