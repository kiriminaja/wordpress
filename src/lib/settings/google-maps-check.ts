export type MapsCheckCode =
  | 'tiles_loaded'
  | 'auth_failed'
  | 'network_error'
  | 'timeout'
  | 'not_configured'
  | 'render_failed';

export type MapsCheckResult = {
  type: 'kiriof-google-maps-check';
  channel: string;
  status: 'ready' | 'error';
  code: MapsCheckCode;
};

/** The preview owns its own runtime. Never accept cross-frame or stale-key results. */
export function mapsCheckResult(
  event: MessageEvent,
  frame: HTMLIFrameElement,
  origin: string,
  channel: string,
): MapsCheckResult | null {
  if (
    event.source !== frame.contentWindow ||
    event.origin !== origin ||
    !event.data ||
    typeof event.data !== 'object'
  )
    return null;
  const data = event.data as Record<string, unknown>;
  if (
    Object.keys(data).length !== 4 ||
    data.type !== 'kiriof-google-maps-check' ||
    data.channel !== channel
  )
    return null;
  if (data.status === 'ready' && data.code === 'tiles_loaded') return data as MapsCheckResult;
  if (
    data.status === 'error' &&
    typeof data.code === 'string' &&
    ['auth_failed', 'network_error', 'timeout', 'not_configured', 'render_failed'].includes(
      data.code,
    )
  )
    return data as MapsCheckResult;
  return null;
}

/** Preserve the server-configured storefront URL; never substitute the admin host. */
export function mapsCheckUrl(endpoint: string, channel: string, location: Location): URL | null {
  try {
    const url = new URL(endpoint, location.href);
    if (
      !['https:', 'http:'].includes(url.protocol) ||
      (location.protocol === 'https:' && url.protocol !== 'https:') ||
      url.username ||
      url.password ||
      url.searchParams.get('kiriof_google_maps_browser_check') !== '1'
    )
      return null;
    url.searchParams.set('channel', channel);
    return url;
  } catch {
    return null;
  }
}
