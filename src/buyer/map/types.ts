import type * as Leaflet from 'leaflet';

export interface Point {
  latitude: string;
  longitude: string;
}

export interface GoogleLatLngLiteral {
  lat: number;
  lng: number;
}
export interface GoogleLatLng {
  lat(): number;
  lng(): number;
}
export interface GoogleMapEvent {
  latLng?: GoogleLatLng | null;
}
export interface GoogleMapsListener {
  remove(): void;
}
export interface GoogleMapInstance {
  getCenter(): GoogleLatLng | undefined;
  setCenter(center: GoogleLatLngLiteral): void;
  setZoom(zoom: number): void;
  addListener(name: string, callback: (event: GoogleMapEvent) => void): GoogleMapsListener;
}
export interface GoogleOverlay {
  setMap(map: GoogleMapInstance | null): void;
}
/** Small runtime surface, intentionally independent of @types/google.maps. */
export interface GoogleMapsAPI {
  Map: new (
    node: HTMLElement,
    options: {
      center: GoogleLatLngLiteral;
      zoom: number;
      scrollwheel?: boolean;
      streetViewControl?: boolean;
      mapTypeControl?: boolean;
      fullscreenControl?: boolean;
    },
  ) => GoogleMapInstance;
  Polyline?: new (options: {
    map: GoogleMapInstance;
    path: GoogleLatLngLiteral[];
    geodesic: boolean;
    clickable: boolean;
    strokeOpacity: number;
    icons: Array<{
      icon: {
        path: string;
        strokeColor: string;
        strokeOpacity: number;
        strokeWeight: number;
        scale: number;
      };
      offset: string;
      repeat: string;
    }>;
  }) => GoogleOverlay;
  Circle?: new (options: {
    map: GoogleMapInstance;
    center: GoogleLatLngLiteral;
    radius: number;
    clickable: boolean;
    fillOpacity: number;
    strokeColor: string;
    strokeWeight: number;
    strokeOpacity: number;
  }) => GoogleOverlay;
  event: {
    clearInstanceListeners(instance: object): void;
    trigger(instance: object, name: string): void;
  };
}
export type GoogleMapsWindow = Window & {
  google?: { maps: GoogleMapsAPI };
  gm_authFailure?: () => void;
  [callback: `__kiriminajaGoogleMaps${number}`]: (() => void) | undefined;
};
export interface PointInput {
  latitude: unknown;
  longitude: unknown;
}
export interface Coverage {
  origin?: PointInput | null;
  radiusMeters: unknown;
}
export interface CoverageResult {
  distanceMeters: number;
  inside: boolean;
}
export type MapError = 'invalid' | 'unavailable' | 'permission' | 'location';
export interface GeolocationProvider {
  getCurrentPosition(
    success: (position: Pick<GeolocationPosition, 'coords'>) => void,
    error: (error: Pick<GeolocationPositionError, 'code'>) => void,
    options: PositionOptions,
  ): void;
}
export interface LocationGateOptions {
  geolocation?: GeolocationProvider | null;
  onSuccess?(point: Point): void;
  onError?(code: Exclude<MapError, 'invalid'>): void;
}
export interface MapSessionOptions {
  provider?: string;
  apiKey?: string;
  google?: GoogleMapsAPI | null;
  document?: Document;
  window?: GoogleMapsWindow;
  leaflet?: typeof Leaflet | null;
  node?: HTMLElement | null;
  tiles?: string;
  attribution?: string;
  defaultCenter?: [number, number] | Leaflet.LatLngExpression;
  initial?: PointInput | null;
  coverage?: Coverage | null;
  label?: string;
  geolocation?: GeolocationProvider | null;
  schedule?(callback: () => void, delay: number): unknown;
  cancel?(handle: unknown): void;
  onError?(code: MapError): void;
  onSelect?(point: Point | null): boolean | void;
  onMove?(moving: boolean): void;
  onCoverage?(coverage: CoverageResult | null): void;
}
export interface MapSession {
  pick(latitude: unknown, longitude: unknown, pan?: boolean): boolean;
  clear(): void;
  locate(): void;
  getPoint(): Point | null;
  isAvailable(): boolean;
  dispose(): void;
}
/** Synchronous adapters require an already loaded runtime. */
export interface MapProvider {
  readonly id: string;
  createSession(options: MapSessionOptions): MapSession;
}
