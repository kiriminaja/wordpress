import { bootTracking } from '../tracking/bridge';
import type { TrackingRoot } from '../tracking/types';

if (typeof window !== 'undefined') bootTracking(window as TrackingRoot);
