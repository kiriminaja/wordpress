import { lookupTracking } from './api';
import type { TrackingLabels, TrackingRoot, TrackingState } from './types';

/** Tracking is a read-only lookup: replacing or disposing one may safely abort it. */
export function createTrackingController(
  root: TrackingRoot,
  state: TrackingState,
  labels: TrackingLabels,
  changed: () => void,
) {
  let sequence = 0;
  let pending: AbortController | undefined;
  let disposed = false;
  return {
    async lookup(orderNumber: string): Promise<void> {
      if (disposed) return;
      const current = ++sequence;
      pending?.abort();
      pending = new AbortController();
      state.phase = 'loading';
      changed();
      try {
        const result = await lookupTracking(root, orderNumber, pending.signal);
        if (disposed || current !== sequence) return;
        if (result.ok) {
          state.data = result.data;
          state.phase = 'success';
        } else {
          state.message = result.message || labels.notFound;
          state.phase = 'error';
        }
      } catch {
        if (disposed || current !== sequence) return;
        state.message = labels.notFound;
        state.phase = 'error';
      }
      if (disposed || current !== sequence) return;
      pending = undefined;
      changed();
    },
    dispose() {
      disposed = true;
      sequence++;
      pending?.abort();
      pending = undefined;
    },
  };
}
