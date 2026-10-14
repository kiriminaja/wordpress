import { trackingData } from './api';
import type { TrackingState } from './types';

export function createTrackingState(): TrackingState {
  const state: TrackingState = $state({ phase: 'blank', data: trackingData(null), message: '' });
  return state;
}
