import type { MapProviderConfigInput } from '../map/config';
import type { Coverage } from '../map/types';

/** PHP-provided map settings stay separate from destination/address payloads. */
export interface MapProviderInput extends MapProviderConfigInput {
  apiKey?: string;
  enabled?: boolean;
  defaultCenter?: [number, number];
  coverage?: Coverage;
  i18n?: Record<string, string>;
}
