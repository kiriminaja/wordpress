import { resolveMapConfig } from './config';
import { createMapSession, leafletProvider } from './leaflet';
import { createGoogleMapSession, googleProvider } from './google';
import { loadGoogleMaps } from './google-loader';
import type { MapProvider, MapSession, MapSessionOptions } from './types';

export const mapProviderRegistry: Readonly<Record<'leaflet' | 'google', MapProvider>> =
  Object.freeze({
    leaflet: leafletProvider,
    google: googleProvider,
  });

/** Configuration failure rejects before scripts, tiles or a fallback map can be created. */
export async function createProviderMapSession(options: MapSessionOptions): Promise<MapSession> {
  const config = resolveMapConfig(options);
  if (config.provider === 'leaflet') return createMapSession(options);
  const google =
    options.google ?? (await loadGoogleMaps(config.apiKey, options.document, options.window));
  return createGoogleMapSession({ ...options, provider: 'google', google });
}
