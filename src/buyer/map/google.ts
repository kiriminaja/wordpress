import { normalizePoint, coverageStatus } from './leaflet';
import type {
  GoogleMapsAPI,
  GoogleMapsWindow,
  GoogleLatLngLiteral,
  GoogleOverlay,
  GoogleMapsListener,
  MapSessionOptions,
  MapSession,
  MapProvider,
  MapError,
  Point,
} from './types';

function literal(point: Point): GoogleLatLngLiteral {
  return { lat: Number(point.latitude), lng: Number(point.longitude) };
}
function defaultCenter(value: MapSessionOptions['defaultCenter']): GoogleLatLngLiteral {
  const point = Array.isArray(value)
    ? normalizePoint(value[0], value[1])
    : value && 'lat' in value
      ? normalizePoint(value.lat, value.lng)
      : null;
  return point ? literal(point) : { lat: -6.2088, lng: 106.8456 };
}
/** 64 geodesic destinations; no routing/geometry library or filled coverage polygon. */
function ring(center: GoogleLatLngLiteral, radius: number): GoogleLatLngLiteral[] {
  const angular = radius / 6371000;
  const lat = (center.lat * Math.PI) / 180,
    lng = (center.lng * Math.PI) / 180;
  return Array.from({ length: 65 }, (_, i) => {
    const bearing = (i * 2 * Math.PI) / 64;
    const nextLat = Math.asin(
      Math.sin(lat) * Math.cos(angular) + Math.cos(lat) * Math.sin(angular) * Math.cos(bearing),
    );
    const nextLng =
      lng +
      Math.atan2(
        Math.sin(bearing) * Math.sin(angular) * Math.cos(lat),
        Math.cos(angular) - Math.sin(lat) * Math.sin(nextLat),
      );
    return { lat: (nextLat * 180) / Math.PI, lng: (((nextLng * 180) / Math.PI + 540) % 360) - 180 };
  });
}

/** Synchronous adapter. The component owns the HTML/CSS center pin, not a Google Marker. */
export function createGoogleMapSession(options: MapSessionOptions): MapSession {
  const api: GoogleMapsAPI | undefined | null =
    options.google ??
    (options.window ?? (globalThis.window as unknown as GoogleMapsWindow | undefined))?.google
      ?.maps;
  let map: InstanceType<GoogleMapsAPI['Map']> | null = null;
  let selected: Point | null = null;
  // The first center/idle notifications are boot rendering, not a buyer selection.
  let disposed = false,
    moving = false,
    programmatic = true,
    locationSequence = 0;
  let timer: unknown, resizeTimer: unknown;
  let observer: ResizeObserver | undefined;
  let overlay: GoogleOverlay | undefined;
  const listeners: GoogleMapsListener[] = [];
  const node = options.node;
  const win = options.window ?? node?.ownerDocument?.defaultView;
  const coverage = options.coverage && {
    origin: options.coverage.origin && { ...options.coverage.origin },
    radiusMeters: options.coverage.radiusMeters,
  };
  const schedule =
    options.schedule ?? ((callback: () => void, delay: number) => setTimeout(callback, delay));
  const cancel =
    options.cancel ?? ((handle: unknown) => clearTimeout(handle as ReturnType<typeof setTimeout>));
  function report(code: MapError) {
    if (!disposed) options.onError?.(code);
  }
  function reportCoverage() {
    if (!disposed) options.onCoverage?.(coverageStatus(coverage, selected));
  }
  function endMoving() {
    if (moving) {
      moving = false;
      options.onMove?.(false);
    }
  }
  function show(point: Point) {
    cancel(timer);
    programmatic = true;
    map?.setCenter(literal(point));
    map?.setZoom(16);
    endMoving();
  }
  function pick(latitude: unknown, longitude: unknown, pan = false): boolean {
    if (disposed || !map) return false;
    const point = normalizePoint(latitude, longitude);
    if (!point) {
      report('invalid');
      return false;
    }
    locationSequence++;
    if (selected?.latitude === point.latitude && selected.longitude === point.longitude) {
      if (pan) show(point);
      reportCoverage();
      return true;
    }
    if (options.onSelect?.(point) === false) return false;
    selected = point;
    if (pan) show(point);
    reportCoverage();
    return true;
  }
  function centerPick() {
    const center = map?.getCenter();
    if (center) pick(center.lat(), center.lng());
  }
  function beginMoving() {
    if (disposed || programmatic || !map) return;
    cancel(timer);
    locationSequence++;
    if (!moving) {
      moving = true;
      options.onMove?.(true);
    }
  }
  function resized() {
    if (!disposed && map) api?.event.trigger(map, 'resize');
  }
  function keydown(event: KeyboardEvent) {
    if (event.key === 'Enter' && !disposed) centerPick();
    if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', '+', '-', '='].includes(event.key)) {
      programmatic = false;
      beginMoving();
    }
  }
  function teardown() {
    cancel(timer);
    cancel(resizeTimer);
    observer?.disconnect();
    win?.removeEventListener('resize', resized);
    node?.removeEventListener('keydown', keydown);
    for (const listener of listeners) listener.remove();
    listeners.length = 0;
    overlay?.setMap(null);
    if (map) api?.event.clearInstanceListeners(map);
    map = null;
    node?.replaceChildren();
  }
  try {
    if (!api?.Map || !node) throw new Error('Map unavailable');
    selected = options.initial
      ? normalizePoint(options.initial.latitude, options.initial.longitude)
      : null;
    map = new api.Map(node, {
      center: selected ? literal(selected) : defaultCenter(options.defaultCenter),
      zoom: selected ? 16 : 13,
      scrollwheel: false,
      streetViewControl: false,
      mapTypeControl: false,
      fullscreenControl: false,
    });
    // Optional overlays cannot disable otherwise usable pin selection.
    if (coverage?.origin && coverageStatus(coverage, coverage.origin)) {
      const origin = normalizePoint(coverage.origin.latitude, coverage.origin.longitude)!;
      try {
        if (api.Polyline)
          overlay = new api.Polyline({
            map,
            path: ring(literal(origin), Number(coverage.radiusMeters)),
            geodesic: true,
            clickable: false,
            strokeOpacity: 0,
            icons: [
              {
                icon: {
                  path: 'M 0,-1 0,1',
                  strokeColor: '#64748b',
                  strokeOpacity: 0.85,
                  strokeWeight: 2,
                  scale: 1,
                },
                offset: '0',
                repeat: '7px',
              },
            ],
          });
        else if (api.Circle)
          overlay = new api.Circle({
            map,
            center: literal(origin),
            radius: Number(coverage.radiusMeters),
            clickable: false,
            fillOpacity: 0,
            strokeColor: '#64748b',
            strokeWeight: 2,
            strokeOpacity: 0.85,
          });
      } catch {
        /* Coverage remains available through straight-line business calculation. */
      }
    }
    listeners.push(
      map.addListener('click', (event) => {
        if (event.latLng) pick(event.latLng.lat(), event.latLng.lng(), true);
      }),
    );
    listeners.push(
      map.addListener('dragstart', () => {
        programmatic = false;
        beginMoving();
      }),
    );
    listeners.push(
      map.addListener('center_changed', () => {
        if (!programmatic) beginMoving();
      }),
    );
    listeners.push(
      map.addListener('idle', () => {
        if (disposed) return;
        if (programmatic) {
          programmatic = false;
          return;
        }
        if (!moving) return;
        cancel(timer);
        timer = schedule(() => {
          if (disposed || !moving) return;
          endMoving();
          centerPick();
        }, 160);
      }),
    );
    node.addEventListener('keydown', keydown);
    reportCoverage();
    resizeTimer = schedule(resized, 150);
    if (globalThis.ResizeObserver) {
      observer = new globalThis.ResizeObserver(resized);
      observer.observe(node);
    } else win?.addEventListener('resize', resized);
  } catch {
    teardown();
    selected = null;
    report('unavailable');
  }
  return {
    pick,
    clear() {
      if (disposed) return;
      locationSequence++;
      if (options.onSelect?.(null) === false) return;
      cancel(timer);
      endMoving();
      selected = null;
      reportCoverage();
    },
    locate() {
      if (disposed || !map) return;
      const sequence = ++locationSequence;
      if (!options.geolocation) {
        report('permission');
        return;
      }
      try {
        options.geolocation.getCurrentPosition(
          (position) => {
            if (!disposed && sequence === locationSequence)
              pick(position.coords.latitude, position.coords.longitude, true);
          },
          (error) => {
            if (!disposed && sequence === locationSequence)
              report(error.code === 1 ? 'permission' : 'location');
          },
          { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
        );
      } catch {
        if (!disposed && sequence === locationSequence) report('unavailable');
      }
    },
    getPoint: () => (selected ? { ...selected } : null),
    isAvailable: () => Boolean(map && !disposed),
    dispose() {
      if (disposed) return;
      disposed = true;
      locationSequence++;
      teardown();
    },
  };
}
export const googleProvider: MapProvider = Object.freeze({
  id: 'google',
  createSession: createGoogleMapSession,
});
