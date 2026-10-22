import type * as Leaflet from 'leaflet';
import type { InstantRouteMapConfig, InstantRouteMapData } from './types';
import { loadGoogleMaps } from '../../buyer/map/google-loader';
import { resolveMapConfig } from '../../buyer/map/config';
import type {
  GoogleMapsAPI,
  GoogleMapInstance,
  GoogleOverlay,
  GoogleLatLngLiteral,
  GoogleLatLng,
} from '../../buyer/map/types';

export type RoutePoint = [number, number];
export type NormalizedInstantRoute = {
  origin: RoutePoint | null;
  destination: RoutePoint | null;
  points: RoutePoint[];
  mode: 'recorded' | 'illustration';
};

/** Native map panes need DOM nodes, not mounted UI components. */
export function createRouteMarker(
  document: Document,
  kind: 'origin' | 'destination',
  label: string,
): HTMLElement {
  const node = document.createElement('div');
  node.className =
    kind === 'origin'
      ? 'box-border flex items-center justify-center rounded-full border-2 border-success bg-card text-success'
      : 'box-border flex items-center justify-center rounded-full border-2 border-destructive bg-card text-destructive';
  node.style.width = '32px';
  node.style.height = '32px';
  node.setAttribute('role', 'img');
  node.setAttribute('aria-label', label);
  node.title = label;
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('width', '16');
  svg.setAttribute('height', '16');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('stroke-width', '2');
  svg.setAttribute('stroke-linecap', 'round');
  svg.setAttribute('stroke-linejoin', 'round');
  svg.setAttribute('aria-hidden', 'true');
  svg.setAttribute('focusable', 'false');
  svg.setAttribute('data-icon', kind === 'origin' ? 'building-warehouse' : 'map-pin');
  // Tabler outline paths from the installed @tabler/icons-svelte icons:
  // building-warehouse.svelte and map-pin.svelte (MIT licensed).
  const paths =
    kind === 'origin'
      ? [
          'M3 21v-13l9 -4l9 4v13',
          'M13 13h4v8h-10v-6h6',
          'M13 21v-9a1 1 0 0 0 -1 -1h-2a1 1 0 0 0 -1 1v3',
        ]
      : [
          'M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0',
          'M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0',
        ];
  for (const d of paths) {
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', d);
    svg.appendChild(path);
  }
  node.appendChild(svg);
  return node;
}

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
    for (const [point, label, kind] of [
      [start, route.origin ? labels?.origin : labels?.start, 'origin'],
      [end, route.destination ? labels?.destination : labels?.end, 'destination'],
    ] as const) {
      const tooltip = container.ownerDocument.createElement('span');
      tooltip.textContent = label ?? '';
      const marker = leaflet.marker([...point] as RoutePoint, {
        icon: leaflet.divIcon({
          html: createRouteMarker(container.ownerDocument, kind, label ?? ''),
          iconSize: [32, 32],
          iconAnchor: [16, 16],
          className: '',
        }),
        title: label ?? '',
        alt: label ?? '',
        draggable: false,
        keyboard: false,
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
  labels?: RouteMapLabels,
): { dispose: () => void } {
  // Route-specific additions keep the shared buyer API small.
  type RouteMap = GoogleMapInstance & {
    fitBounds(
      bounds: { north: number; south: number; east: number; west: number },
      padding: number,
    ): void;
  };
  type RouteOverlayView = GoogleOverlay & {
    onAdd(): void;
    draw(): void;
    onRemove(): void;
    getPanes(): { markerLayer: HTMLElement } | null;
    getProjection(): {
      fromLatLngToDivPixel(point: GoogleLatLng): { x: number; y: number } | null;
    };
  };
  type RouteAPI = {
    Map: new (node: HTMLElement, options: Record<string, unknown>) => RouteMap;
    Polyline: new (options: Record<string, unknown>) => GoogleOverlay;
    OverlayView: new () => RouteOverlayView;
    LatLng: new (lat: number, lng: number) => GoogleLatLng;
  };
  const api = google as unknown as RouteAPI;
  let map: RouteMap | undefined;
  const overlays: GoogleOverlay[] = [];
  const markerNodes: HTMLElement[] = [];
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
  const primary = color('--primary');
  const dispose = () => {
    if (disposed) return;
    disposed = true;
    observer?.disconnect();
    for (const overlay of overlays) {
      try {
        google.event.clearInstanceListeners(overlay);
      } catch {
        // Continue cleaning up other resources if provider listener cleanup fails.
      }
      try {
        overlay.setMap(null);
      } catch {
        // A partially attached provider overlay must not prevent DOM cleanup.
      }
    }
    for (const node of markerNodes) node.remove();
    try {
      if (map) google.event.clearInstanceListeners(map);
    } catch {
      // Cleanup remains idempotent even when a partially initialized map fails.
    } finally {
      container.replaceChildren();
    }
  };
  try {
    if (!api.Map || !api.Polyline || !api.OverlayView || !api.LatLng)
      throw new Error('Map unavailable');
    const start = route.origin ?? route.points[0]!;
    const end = route.destination ?? route.points[route.points.length - 1]!;
    map = new api.Map(container, {
      center: literal(start),
      zoom: 13,
      maxZoom: 18,
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
    class RouteMarker extends api.OverlayView {
      constructor(
        private node: HTMLElement,
        private position: GoogleLatLng,
      ) {
        super();
        node.style.position = 'absolute';
        node.style.transform = 'translate(-50%, -50%)';
        node.style.pointerEvents = 'none';
      }
      onAdd() {
        if (!disposed) this.getPanes()?.markerLayer.appendChild(this.node);
      }
      draw() {
        if (disposed) return;
        const pixel = this.getProjection().fromLatLngToDivPixel(this.position);
        if (!pixel) return;
        this.node.style.left = `${pixel.x}px`;
        this.node.style.top = `${pixel.y}px`;
      }
      onRemove() {
        this.node.remove();
      }
    }
    for (const [point, label, kind] of [
      [start, route.origin ? labels?.origin : labels?.start, 'origin'],
      [end, route.destination ? labels?.destination : labels?.end, 'destination'],
    ] as const) {
      const node = createRouteMarker(container.ownerDocument, kind, label ?? '');
      markerNodes.push(node);
      const marker = new RouteMarker(node, new api.LatLng(point[0], point[1]));
      // Track before attachment: setMap can throw after onAdd has inserted the node.
      overlays.push(marker);
      marker.setMap(map);
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
            session = createGoogleInstantRouteMapSession(container, route, api, report, labels);
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
