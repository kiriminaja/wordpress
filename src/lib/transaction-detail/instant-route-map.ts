import type * as Leaflet from 'leaflet';
import type { InstantRouteMapConfig, InstantRouteMapData } from './types';
import { loadGoogleMaps } from '../../buyer/map/google-loader';
import { resolveMapConfig } from '../../buyer/map/config';
import type {
  GoogleMapsAPI,
  GoogleMapInstance,
  GoogleOverlay,
  GoogleLatLngLiteral,
} from '../../buyer/map/types';

export type RoutePoint = [number, number];
export type NormalizedInstantRoute = {
  origin: RoutePoint | null;
  destination: RoutePoint | null;
  points: RoutePoint[];
  mode: 'recorded' | 'illustration';
};

export type RouteMapLabels = {
  origin: string;
  destination: string;
  start: string;
  end: string;
};

function decimal(value: unknown): number | null {
  if (typeof value !== 'number' && typeof value !== 'string') return null;
  if (typeof value === 'string' && !/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/.test(value.trim()))
    return null;
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
}

export function routePoint(latitude: unknown, longitude: unknown): RoutePoint | null {
  const lat = decimal(latitude);
  const lng = decimal(longitude);
  return lat !== null && lng !== null && Math.abs(lat) <= 90 && Math.abs(lng) <= 180
    ? [lat, lng]
    : null;
}

/** Copy saved data; never normalize by mutating the bootstrap or infer a driving route. */
export function normalizeInstantRoute(
  data: InstantRouteMapData | null | undefined,
): NormalizedInstantRoute | null {
  if (!data || (data.mode !== 'recorded' && data.mode !== 'illustration')) return null;
  const origin = routePoint(data.origin?.latitude, data.origin?.longitude);
  const destination = routePoint(data.destination?.latitude, data.destination?.longitude);
  if (data.mode === 'illustration') {
    if (!origin || !destination) return null;
    return { origin, destination, points: [[...origin], [...destination]], mode: data.mode };
  }
  if (!Array.isArray(data.points) || data.points.length < 2 || data.points.length > 10_000)
    return null;
  const points: RoutePoint[] = [];
  for (const point of data.points) {
    if (!Array.isArray(point) || point.length !== 2) return null;
    const normalized = routePoint(point[0], point[1]);
    if (!normalized) return null;
    points.push(normalized);
  }
  return { origin, destination, points, mode: data.mode };
}

export function preloadedLeaflet(): typeof Leaflet | undefined {
  return typeof window === 'undefined' ? undefined : (window as Window & { L?: typeof Leaflet }).L;
}

/** A static, read-only session. Its only network activity is the configured tile layer. */
export function createInstantRouteMapSession(
  container: HTMLElement,
  route: NormalizedInstantRoute,
  config: InstantRouteMapConfig,
  leaflet: typeof Leaflet,
  onError: () => void,
  labels?: RouteMapLabels,
): { dispose: () => void } {
  let map: Leaflet.Map | undefined;
  let tiles: Leaflet.TileLayer | undefined;
  let observer: ResizeObserver | undefined;
  let timer: ReturnType<typeof setTimeout> | undefined;
  let disposed = false;
  const tileError = () => {
    if (!disposed) onError();
  };
  const dispose = () => {
    if (disposed) return;
    disposed = true;
    if (timer !== undefined) clearTimeout(timer);
    observer?.disconnect();
    tiles?.off('tileerror', tileError);
    tiles?.off();
    map?.off();
    map?.remove();
  };
  try {
    map = leaflet.map(container, {
      zoomControl: true,
      scrollWheelZoom: false,
      keyboard: true,
      dragging: true,
      maxZoom: 18,
    });
    tiles = leaflet.tileLayer(config.tiles, { attribution: config.attribution, maxZoom: 18 });
    tiles.on('tileerror', tileError);
    tiles.addTo(map);
    // Each layer receives its own copy, even if a map implementation mutates coordinates.
    leaflet
      .polyline(
        route.points.map((point) => [...point] as RoutePoint),
        {
          color: 'var(--primary)',
          weight: 4,
          ...(route.mode === 'illustration' ? { dashArray: '6 8' } : {}),
        },
      )
      .addTo(map);
    const start = route.origin ?? route.points[0]!;
    const end = route.destination ?? route.points[route.points.length - 1]!;
    for (const [point, label, color] of [
      [start, route.origin ? labels?.origin : labels?.start, 'var(--success)'],
      [end, route.destination ? labels?.destination : labels?.end, 'var(--destructive)'],
    ] as const) {
      const tooltip = container.ownerDocument.createElement('span');
      tooltip.textContent = label ?? '';
      const marker = leaflet.circleMarker([...point] as RoutePoint, {
        radius: 7,
        color,
        fillColor: color,
        fillOpacity: 1,
        weight: 2,
      });
      if (label) marker.bindTooltip(tooltip);
      marker.addTo(map);
    }
    const bounds = [...route.points, start, end].map((point) => [...point] as RoutePoint);
    const first = bounds[0]!;
    if (bounds.every((point) => point[0] === first[0] && point[1] === first[1]))
      map.setView(first, 13);
    else map.fitBounds(bounds, { padding: [24, 24], maxZoom: 15 });
    if (typeof ResizeObserver !== 'undefined') {
      observer = new ResizeObserver(() => {
        if (disposed) return;
        if (timer !== undefined) clearTimeout(timer);
        timer = setTimeout(() => {
          timer = undefined;
          if (!disposed) map?.invalidateSize({ pan: false });
        }, 150);
      });
      observer.observe(container);
    }
  } catch {
    dispose();
    onError();
  }
  return { dispose };
}

/** Only saved geometry is displayed; no Directions service, location or selection handlers. */
export function createGoogleInstantRouteMapSession(
  container: HTMLElement,
  route: NormalizedInstantRoute,
  google: GoogleMapsAPI,
  onError: () => void,
): { dispose: () => void } {
  // Route-specific additions keep the shared buyer API small.
  type RouteMap = GoogleMapInstance & {
    fitBounds(
      bounds: { north: number; south: number; east: number; west: number },
      padding: number,
    ): void;
  };
  type RouteAPI = {
    Map: new (node: HTMLElement, options: Record<string, unknown>) => RouteMap;
    Polyline: new (options: Record<string, unknown>) => GoogleOverlay;
    Circle: new (options: Record<string, unknown>) => GoogleOverlay;
  };
  const api = google as unknown as RouteAPI;
  let map: RouteMap | undefined;
  const overlays: GoogleOverlay[] = [];
  let observer: ResizeObserver | undefined;
  let disposed = false;
  const literal = (point: RoutePoint): GoogleLatLngLiteral => ({ lat: point[0], lng: point[1] });
  // Google does not resolve CSS var() in overlay options. Resolve the shared
  // semantic token through a DOM probe without hardcoding a second palette.
  const color = (token: string) => {
    const probe = container.ownerDocument.createElement('span');
    probe.style.color = `var(${token})`;
    container.appendChild(probe);
    const resolved =
      container.ownerDocument.defaultView?.getComputedStyle(probe).color || 'currentColor';
    probe.remove();
    return resolved;
  };
  const primary = color('--primary'),
    success = color('--success'),
    destructive = color('--destructive');
  const dispose = () => {
    if (disposed) return;
    disposed = true;
    observer?.disconnect();
    for (const overlay of overlays) {
      google.event.clearInstanceListeners(overlay);
      overlay.setMap(null);
    }
    if (map) google.event.clearInstanceListeners(map);
    container.replaceChildren();
  };
  try {
    if (!api.Map || !api.Polyline || !api.Circle) throw new Error('Map unavailable');
    const start = route.origin ?? route.points[0]!;
    const end = route.destination ?? route.points[route.points.length - 1]!;
    map = new api.Map(container, {
      center: literal(start),
      zoom: 13,
      scrollwheel: false,
      draggable: false,
      disableDoubleClickZoom: true,
      streetViewControl: false,
      mapTypeControl: false,
      fullscreenControl: false,
      clickableIcons: false,
    });
    overlays.push(
      new api.Polyline({
        map,
        path: route.points.map(literal),
        geodesic: false,
        clickable: false,
        strokeColor: primary,
        strokeWeight: 4,
        strokeOpacity: route.mode === 'illustration' ? 0 : 1,
        ...(route.mode === 'illustration'
          ? {
              icons: [
                {
                  icon: {
                    path: 'M 0,-1 0,1',
                    strokeOpacity: 1,
                    strokeColor: primary,
                    strokeWeight: 4,
                    scale: 1,
                  },
                  offset: '0',
                  repeat: '14px',
                },
              ],
            }
          : {}),
      }),
    );
    for (const [point, color] of [
      [start, success],
      [end, destructive],
    ] as const) {
      overlays.push(
        new api.Circle({
          map,
          center: literal(point),
          radius: 35,
          clickable: false,
          fillColor: color,
          fillOpacity: 1,
          strokeColor: color,
          strokeWeight: 2,
        }),
      );
    }
    const points = [...route.points, start, end];
    if (!points.every((point) => point[0] === start[0] && point[1] === start[1])) {
      map.fitBounds(
        {
          north: Math.max(...points.map((p) => p[0])),
          south: Math.min(...points.map((p) => p[0])),
          east: Math.max(...points.map((p) => p[1])),
          west: Math.min(...points.map((p) => p[1])),
        },
        24,
      );
    }
    if (typeof ResizeObserver !== 'undefined') {
      observer = new ResizeObserver(() => {
        if (!disposed && map) google.event.trigger(map, 'resize');
      });
      observer.observe(container);
    }
  } catch {
    dispose();
    onError();
  }
  return { dispose };
}

/** A cancellable async boundary prevents late script loads from creating stale maps. */
export function mountInstantRouteMap(
  container: HTMLElement,
  route: NormalizedInstantRoute,
  config: InstantRouteMapConfig,
  onError: () => void,
  labels?: RouteMapLabels,
): { dispose: () => void } {
  let disposed = false;
  let session: { dispose: () => void } | undefined;
  const report = () => {
    if (!disposed) onError();
  };
  const dispose = () => {
    disposed = true;
    session?.dispose();
  };
  try {
    if (!config.enabled) throw new Error('Map unavailable');
    // Existing admin Leaflet bootstraps may use local tile URLs. Validate provider/key
    // without changing the established Leaflet URL contract.
    const provider = resolveMapConfig({ provider: config.provider, apiKey: config.apiKey });
    if (provider.provider === 'google') {
      void loadGoogleMaps(provider.apiKey)
        .then((api) => {
          if (!disposed)
            session = createGoogleInstantRouteMapSession(container, route, api, report);
        })
        .catch(report);
    } else {
      const leaflet = preloadedLeaflet();
      if (!leaflet || !config.tiles) throw new Error('Map unavailable');
      session = createInstantRouteMapSession(container, route, config, leaflet, report, labels);
    }
  } catch {
    report();
  }
  return { dispose };
}
