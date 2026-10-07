import type * as Leaflet from 'leaflet';
import { resolveMapConfig } from './config';
import type {
  Point,
  PointInput,
  Coverage,
  CoverageResult,
  MapError,
  LocationGateOptions,
  MapSessionOptions,
  MapSession,
  MapProvider,
} from './types';

export function coordinate(value: unknown, limit: number): string | null {
  if (('string' !== typeof value && 'number' !== typeof value) || '' === String(value).trim()) {
    return null;
  }
  var number = Number(value);
  return Number.isFinite(number) && Math.abs(number) <= limit ? number.toFixed(7) : null;
}

/** Straight-line metres, not a driving route. Invalid points have unknown coverage. */
export function coverageDistance(
  origin?: PointInput | null,
  destination?: PointInput | null,
): number | null {
  if (
    !origin ||
    !destination ||
    !normalizePoint(origin.latitude, origin.longitude) ||
    !normalizePoint(destination.latitude, destination.longitude)
  ) {
    return null;
  }
  var radians = Math.PI / 180;
  var latitude = (Number(destination.latitude) - Number(origin.latitude)) * radians;
  var longitude = (Number(destination.longitude) - Number(origin.longitude)) * radians;
  var a =
    Math.sin(latitude / 2) ** 2 +
    Math.cos(Number(origin.latitude) * radians) *
      Math.cos(Number(destination.latitude) * radians) *
      Math.sin(longitude / 2) ** 2;
  return 6371000 * 2 * Math.asin(Math.sqrt(Math.min(1, Math.max(0, a))));
}
export function coverageStatus(
  coverage?: Coverage | null,
  point?: PointInput | null,
): CoverageResult | null {
  if (
    !coverage ||
    ('number' !== typeof coverage.radiusMeters && 'string' !== typeof coverage.radiusMeters) ||
    '' === String(coverage.radiusMeters).trim()
  ) {
    return null;
  }
  var radius = Number(coverage.radiusMeters);
  var distance = coverageDistance(coverage.origin, point);
  return null === distance || !Number.isFinite(radius) || radius <= 0
    ? null
    : { distanceMeters: distance, inside: distance <= radius + 0.001 };
}

/** One authorization request per editor lifecycle; disposal ignores browser callbacks. */
export function createLocationGate(options: LocationGateOptions) {
  var started = false;
  var disposed = false;
  var sequence = 0;
  return {
    start: function () {
      if (started || disposed) {
        return;
      }
      started = true;
      var request = ++sequence;
      function fail(code: Exclude<MapError, 'invalid'>) {
        if (disposed || request !== sequence) {
          return;
        }
        sequence++;
        if (options.onError) {
          options.onError(code);
        }
      }
      var geolocation = options.geolocation;
      if (!geolocation || 'function' !== typeof geolocation.getCurrentPosition) {
        fail('unavailable');
        return;
      }
      try {
        geolocation.getCurrentPosition(
          function (position) {
            if (disposed || request !== sequence) {
              return;
            }
            var coords = position && position.coords;
            var point = coords && normalizePoint(coords.latitude, coords.longitude);
            if (!point) {
              fail('location');
              return;
            }
            sequence++;
            if (options.onSuccess) {
              options.onSuccess(point);
            }
          },
          function (error) {
            fail(error && error.code === 1 ? 'permission' : 'location');
          },
          { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
        );
      } catch {
        fail('unavailable');
      }
    },
    dispose: function () {
      disposed = true;
      sequence++;
    },
  };
}
export function normalizePoint(latitude: unknown, longitude: unknown): Point | null {
  var lat = coordinate(latitude, 90);
  var lng = coordinate(longitude, 180);
  return null === lat || null === lng ? null : { latitude: lat, longitude: lng };
}

/** Owns one Leaflet container; defaultCenter is a view, never a selected pin. */
export function createMapSession(options: MapSessionOptions): MapSession {
  var L = options.leaflet;
  var map: Leaflet.Map | null = null;
  var moving = false;
  var ignoreMove = false;
  var selected: Point | null = null;
  var disposed = false;
  var locationSequence = 0;
  var timer: unknown;
  var observer: ResizeObserver | undefined;
  // Copy the origin so a caller mutating its config cannot move coverage silently.
  var coverage = options.coverage && {
    origin: options.coverage.origin && Object.assign({}, options.coverage.origin),
    radiusMeters: options.coverage.radiusMeters,
  };
  var schedule =
    options.schedule || ((callback: () => void, delay: number) => setTimeout(callback, delay));
  var cancel =
    options.cancel || ((handle: unknown) => clearTimeout(handle as ReturnType<typeof setTimeout>));
  function report(message: MapError) {
    if (!disposed && options.onError) {
      options.onError(message);
    }
  }
  function reportCoverage(point: Point | null) {
    if (!disposed && options.onCoverage) {
      options.onCoverage(coverageStatus(coverage, point));
    }
  }
  function show(point: Point) {
    ignoreMove = true;
    try {
      map!.setView([Number(point.latitude), Number(point.longitude)], 16, { animate: false });
    } finally {
      ignoreMove = false;
    }
    if (moving) {
      moving = false;
      if (options.onMove) {
        options.onMove(false);
      }
    }
  }

  function pick(latitude: unknown, longitude: unknown, pan?: boolean) {
    if (disposed || !map) {
      return false;
    }
    var point = normalizePoint(latitude, longitude);
    if (!point) {
      report('invalid');
      return false;
    }
    locationSequence++;
    if (
      selected &&
      selected.latitude === point.latitude &&
      selected.longitude === point.longitude
    ) {
      if (pan) {
        show(point);
      }
      reportCoverage(point);
      return true;
    }
    if (options.onSelect && false === options.onSelect(point)) {
      return false;
    }
    selected = point;
    if (pan) {
      show(point);
    }
    reportCoverage(point);
    return true;
  }
  try {
    const config = resolveMapConfig(options);
    if (!options.node || !L) {
      throw new Error('Map unavailable');
    }
    map = L.map(options.node, { scrollWheelZoom: false }).setView(
      options.defaultCenter || [-6.2088, 106.8456],
      13,
    );
    if (
      coverage &&
      coverage.origin &&
      coverageStatus(coverage, coverage.origin) &&
      'function' === typeof L.circle
    ) {
      // A missing optional overlay must not disable Express pin selection.
      try {
        L.circle([Number(coverage.origin.latitude), Number(coverage.origin.longitude)], {
          radius: Number(coverage.radiusMeters),
          interactive: false,
          fill: false,
          fillOpacity: 0,
          color: '#64748b',
          weight: 2,
          opacity: 0.85,
          dashArray: '1 6',
          lineCap: 'round',
        }).addTo(map);
      } catch {
        /* Keep the unrestricted map usable. */
      }
    }
    var tiles = L.tileLayer(config.tiles, { maxZoom: 19, attribution: config.attribution }).addTo(
      map,
    );
    tiles.on('tileerror', function () {
      report('unavailable');
    });
    map.on('click', function (event: Leaflet.LeafletMouseEvent) {
      pick(event.latlng.lat, event.latlng.lng, true);
    });
    map.on('movestart', function () {
      if (disposed || ignoreMove) {
        return;
      }
      moving = true;
      locationSequence++;
      if (options.onMove) {
        options.onMove(true);
      }
    });
    map.on('moveend', function () {
      if (disposed || ignoreMove || !moving) {
        return;
      }
      moving = false;
      if (options.onMove) {
        options.onMove(false);
      }
      var center = map!.getCenter();
      pick(center.lat, center.lng);
    });
    map.on('keydown', function (event: Leaflet.LeafletKeyboardEvent) {
      if (event.originalEvent && event.originalEvent.key === 'Enter') {
        var center = map!.getCenter();
        pick(center.lat, center.lng);
      }
    });
    if (options.initial) {
      selected = normalizePoint(options.initial.latitude, options.initial.longitude);
      if (selected) {
        show(selected);
      }
    }
    reportCoverage(selected);
    timer = schedule(function () {
      if (!disposed) {
        map!.invalidateSize({ pan: false });
      }
    }, 150);
    if (globalThis.ResizeObserver) {
      observer = new globalThis.ResizeObserver(function () {
        if (!disposed) {
          map!.invalidateSize({ pan: false });
        }
      });
      observer.observe(options.node);
    }
  } catch {
    if (map) {
      map.remove();
      map = null;
    }
    report('unavailable');
  }
  return {
    pick: pick,
    clear: function () {
      if (disposed) {
        return;
      }
      locationSequence++;
      if (options.onSelect && false === options.onSelect(null)) {
        return;
      }
      selected = null;
      reportCoverage(null);
    },
    locate: function () {
      if (disposed || !map) {
        return;
      }
      var sequence = ++locationSequence;
      var geolocation = options.geolocation;
      if (!geolocation) {
        report('permission');
        return;
      }
      geolocation.getCurrentPosition(
        function (position) {
          if (!disposed && sequence === locationSequence) {
            pick(position.coords.latitude, position.coords.longitude, true);
          }
        },
        function (error) {
          if (!disposed && sequence === locationSequence) {
            report(error.code === 1 ? 'permission' : 'location');
          }
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
      );
    },
    getPoint: function () {
      return selected ? Object.assign({}, selected) : null;
    },
    isAvailable: function () {
      return Boolean(map && !disposed);
    },
    dispose: function () {
      if (disposed) {
        return;
      }
      disposed = true;
      locationSequence++;
      cancel(timer);
      if (observer) {
        observer.disconnect();
      }
      if (map) {
        map.remove();
        map = null;
      }
    },
  };
}

export const leafletProvider: MapProvider = Object.freeze({
  id: 'leaflet',
  createSession: createMapSession,
});
export const mapProviderRegistry: Readonly<Record<'leaflet', MapProvider>> = Object.freeze({
  leaflet: leafletProvider,
});
