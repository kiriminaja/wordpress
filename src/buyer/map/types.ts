import type * as Leaflet from 'leaflet';

export interface Point {
  latitude: string;
  longitude: string;
}
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
  leaflet?: typeof Leaflet | null;
  node?: HTMLElement | null;
  tiles?: string;
  attribution?: string;
  defaultCenter?: Leaflet.LatLngExpression;
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
/** Contract for future adapters; only Leaflet is implemented/registered today. */
export interface MapProvider {
  readonly id: string;
  createSession(options: MapSessionOptions): MapSession;
}
