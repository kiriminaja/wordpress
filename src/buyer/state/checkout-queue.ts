export type QueueStatus = 'success' | 'error' | 'superseded' | 'disposed';
export interface QueueResult<T> {
  status: QueueStatus;
  snapshot: T | null;
  error: unknown;
}
export interface QueueState<T> {
  readonly pending: T | null;
  readonly inFlight: T | null;
  readonly acknowledged: T | null;
  readonly error: unknown;
  readonly stalled: boolean;
  readonly uncertain: boolean;
  readonly blocked: boolean;
  readonly disposed: boolean;
}
export interface QueueOptions<T> {
  send(snapshot: T): unknown;
  onChange?(state: QueueState<T>): void;
  isBlocked?(): boolean;
  schedule?(callback: () => void): unknown;
  cancel?(handle: unknown): void;
  setTimeout?(callback: () => void, delay: number): unknown;
  clearTimeout?(handle: unknown): void;
  timeout?: number;
}
export interface CheckoutQueue<T> {
  update(snapshot: T): Promise<QueueResult<T>>;
  retry(): Promise<QueueResult<T>>;
  resume(): void;
  dispose(): void;
  getState(): QueueState<T>;
}
interface QueueEntry<T> {
  snapshot: T;
  key: string;
  waiters: Array<(result: QueueResult<T>) => void>;
  timer?: unknown;
  timedOut?: boolean;
}
function freeze<T>(value: T): T {
  if (value && 'object' === typeof value) {
    Object.keys(value).forEach(function (key) {
      freeze((value as Record<string, unknown>)[key]);
    });
    Object.freeze(value);
  }
  return value;
}

function clone<T>(value: T): T {
  return freeze(JSON.parse(JSON.stringify(value)));
}

function fingerprint(value: unknown): string {
  if (value && 'object' === typeof value && !Array.isArray(value)) {
    return (
      '{' +
      Object.keys(value)
        .sort()
        .map(function (key) {
          return JSON.stringify(key) + ':' + fingerprint((value as Record<string, unknown>)[key]);
        })
        .join(',') +
      '}'
    );
  }
  if (Array.isArray(value)) {
    return '[' + value.map(fingerprint).join(',') + ']';
  }
  return JSON.stringify(value) ?? 'undefined';
}

/**
 * Serialized, latest-pending queue for JSON-compatible snapshots.
 * Promises never reject for transport failures: results have status 'success',
 * 'error', 'superseded', or 'disposed', plus the caller's immutable snapshot.
 * Replacing an unsent snapshot settles its callers as 'superseded'. A failed
 * latest snapshot remains pending until retry() (or a different update()).
 * send() must reject on application/HTTP failure, not merely resolve an error
 * response. Server mutations are never aborted, including on dispose().
 */
export function createQueue<T>(options: QueueOptions<T>): CheckoutQueue<T> {
  var send = options.send;
  var onChange = options.onChange || function () {};
  var isBlocked =
    options.isBlocked ||
    function () {
      return false;
    };
  var schedule =
    options.schedule ||
    function (callback: () => void) {
      return setTimeout(callback, 0);
    };
  var cancel =
    options.cancel ||
    function (handle: unknown) {
      clearTimeout(handle as ReturnType<typeof setTimeout>);
    };
  var startTimer =
    options.setTimeout ||
    function (callback: () => void, delay: number) {
      return setTimeout(callback, delay);
    };
  var clearTimer =
    options.clearTimeout ||
    function (handle: unknown) {
      clearTimeout(handle as ReturnType<typeof setTimeout>);
    };
  var timeout = undefined === options.timeout ? 15000 : options.timeout;
  var stalled = false;
  var pending: QueueEntry<T> | null = null;
  var inFlight: QueueEntry<T> | null = null;
  var acknowledged: T | null = null;
  var acknowledgedKey: string | null = null;
  var error: unknown = null;
  var blocked = false;
  var disposed = false;
  var scheduled = false;
  var scheduledHandle: unknown;

  function getState(): QueueState<T> {
    return Object.freeze({
      pending: pending ? pending.snapshot : null,
      inFlight: inFlight ? inFlight.snapshot : null,
      acknowledged: acknowledged,
      error: error,
      stalled: stalled,
      uncertain: stalled && Boolean(inFlight),
      blocked: blocked,
      disposed: disposed,
    });
  }

  function notify() {
    if (!disposed) {
      onChange(getState());
    }
  }

  function settle(entry: QueueEntry<T>, status: QueueStatus, failure?: unknown) {
    entry.waiters.splice(0).forEach(function (resolve) {
      resolve({ status: status, snapshot: entry.snapshot, error: failure || null });
    });
  }

  function wait(entry: QueueEntry<T>): Promise<QueueResult<T>> {
    return new Promise<QueueResult<T>>(function (resolve) {
      entry.waiters.push(resolve);
    });
  }

  function enqueueSend() {
    if (disposed || scheduled || inFlight || !pending || error) {
      return;
    }
    scheduled = true;
    scheduledHandle = schedule(function () {
      scheduled = false;
      pump();
    });
  }

  function complete(entry: QueueEntry<T>, failure: unknown, failed: boolean) {
    if (disposed || inFlight !== entry) {
      return;
    }
    clearTimer(entry.timer);
    inFlight = null;
    // A timed-out Woo mutation may have reached the server. Never acknowledge
    // its late result or automatically send work until an explicit retry.
    if (entry.timedOut) {
      notify();
      return;
    }
    if (failed) {
      settle(entry, 'error', failure);
      if (!pending) {
        pending = entry;
        error = failure;
      }
    } else {
      acknowledged = entry.snapshot;
      acknowledgedKey = entry.key;
      settle(entry, 'success');
    }
    notify();
    enqueueSend();
  }

  function pump() {
    var entry: QueueEntry<T>;
    var result: unknown;
    if (disposed || inFlight || !pending || error) {
      return;
    }
    blocked = Boolean(isBlocked());
    if (blocked) {
      notify();
      return;
    }
    entry = pending;
    pending = null;
    inFlight = entry;
    notify();
    // A listener may dispose the queue before the transport starts.
    if (disposed) {
      return;
    }
    entry.timer = startTimer(function () {
      if (disposed || inFlight !== entry) {
        return;
      }
      entry.timedOut = true;
      stalled = true;
      error = Object.assign(new Error('Checkout update timed out'), {
        code: 'checkout_update_timeout',
      });
      settle(entry, 'error', error);
      if (!pending) {
        pending = { snapshot: entry.snapshot, key: entry.key, waiters: [] };
      }
      settle(pending, 'error', error);
      notify();
    }, timeout);
    try {
      result = send(entry.snapshot);
    } catch (failure) {
      complete(entry, failure || new Error('Checkout update failed'), true);
      return;
    }
    Promise.resolve(result).then(
      function () {
        complete(entry, null, false);
      },
      function (failure) {
        complete(entry, failure || new Error('Checkout update failed'), true);
      },
    );
  }

  function update(snapshot: T): Promise<QueueResult<T>> {
    var entry: QueueEntry<T> = { snapshot: clone(snapshot), key: '', waiters: [] };
    var promise: Promise<QueueResult<T>>;
    entry.key = fingerprint(entry.snapshot);
    if (disposed) {
      return Promise.resolve({ status: 'disposed', snapshot: entry.snapshot, error: null });
    }
    if (pending && pending.key === entry.key) {
      if (error) {
        return Promise.resolve({ status: 'error', snapshot: pending.snapshot, error: error });
      }
      return wait(pending);
    }
    if (!pending && inFlight && inFlight.key === entry.key) {
      if (stalled) {
        return Promise.resolve({ status: 'error', snapshot: entry.snapshot, error: error });
      }
      return wait(inFlight);
    }
    if (!pending && !inFlight && acknowledgedKey === entry.key) {
      return Promise.resolve({ status: 'success', snapshot: entry.snapshot, error: null });
    }
    if (pending) {
      settle(pending, 'superseded');
    }
    pending = entry;
    if (stalled) {
      notify();
      return Promise.resolve({ status: 'error', snapshot: entry.snapshot, error: error });
    }
    error = null;
    promise = wait(entry);
    notify();
    enqueueSend();
    return promise;
  }

  function retry(): Promise<QueueResult<T>> {
    var promise: Promise<QueueResult<T>>;
    if (disposed) {
      return Promise.resolve({ status: 'disposed', snapshot: null, error: null });
    }
    if (inFlight && stalled) {
      return Promise.resolve({
        status: 'error',
        snapshot: pending ? pending.snapshot : inFlight.snapshot,
        error: error,
      });
    }
    if (pending) {
      stalled = false;
      promise = wait(pending);
      error = null;
      notify();
      enqueueSend();
      return promise;
    }
    if (inFlight) {
      return wait(inFlight);
    }
    return Promise.resolve({ status: 'success', snapshot: acknowledged, error: null });
  }

  function resume() {
    enqueueSend();
  }

  function dispose() {
    if (disposed) {
      return;
    }
    disposed = true;
    if (scheduled) {
      cancel(scheduledHandle);
      scheduled = false;
    }
    if (pending) {
      settle(pending, 'disposed');
    }
    if (inFlight) {
      clearTimer(inFlight.timer);
      settle(inFlight, 'disposed');
    }
    pending = null;
    inFlight = null;
  }

  return { update: update, retry: retry, resume: resume, dispose: dispose, getState: getState };
}
