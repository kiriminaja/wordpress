import type { InstantQuote } from './types';

/** Round up the actual server deadline, never a locally assumed TTL. */
export function quoteSecondsRemaining(quote: InstantQuote, now = Date.now()): number {
  const deadline = quote.expires_at * 1000;
  if (typeof quote.expires_at !== 'number' || !Number.isFinite(deadline) || !Number.isFinite(now))
    return 0;
  return Math.max(0, Math.ceil((deadline - now) / 1000));
}

export function isTopAccount(quote: InstantQuote): boolean {
  return quote.payment_methods.length === 1 && quote.payment_methods[0] === 'top';
}

/** Keep explicit exclusions for surviving rows; newly eligible rows default to selected. */
export function reviewSelection(
  next: InstantQuote,
  previousChecked?: Record<string, boolean>,
): Record<string, boolean> {
  return Object.fromEntries(
    next.rows.map((row) => [
      row.id,
      row.eligible &&
        (previousChecked && Object.hasOwn(previousChecked, row.id)
          ? previousChecked[row.id] === true
          : true),
    ]),
  );
}

/** Only complete, finite, nonnegative price pairs contribute to comparable totals. */
export function quoteSummary(quote: InstantQuote, checked: Record<string, boolean>) {
  let before = 0;
  let after = 0;
  let selectedCount = 0;
  let changedCount = 0;
  let unavailableCount = 0;
  for (const row of quote.rows) {
    if (
      !row.eligible ||
      typeof row.before !== 'number' ||
      !Number.isFinite(row.before) ||
      row.before < 0 ||
      typeof row.after !== 'number' ||
      !Number.isFinite(row.after) ||
      row.after < 0
    ) {
      unavailableCount++;
      continue;
    }
    if (!checked[row.id]) continue;
    before += row.before;
    after += row.after;
    selectedCount++;
    if (row.changed) changedCount++;
  }
  return { before, after, gap: after - before, selectedCount, changedCount, unavailableCount };
}

export interface InstantQuoteClockDeps {
  onTick: (secondsRemaining: number) => void;
  onExpire: () => void;
  now?: () => number;
  setTimer?: (callback: () => void, delay: number) => ReturnType<typeof setTimeout>;
  clearTimer?: (timer: ReturnType<typeof setTimeout>) => void;
  isVisible?: () => boolean;
}

/** A read-only clock. Generation guards allow callbacks to stop or replace the quote. */
export function createInstantQuoteClock(deps: InstantQuoteClockDeps) {
  const now = deps.now ?? Date.now;
  const setTimer = deps.setTimer ?? ((callback, delay) => setTimeout(callback, delay));
  const clearTimer = deps.clearTimer ?? ((timer) => clearTimeout(timer));
  const isVisible = deps.isVisible ?? (() => typeof document === 'undefined' || !document.hidden);
  let generation = 0;
  let timer: ReturnType<typeof setTimeout> | undefined;
  let disposed = false;

  function stop() {
    generation++;
    if (timer !== undefined) clearTimer(timer);
    timer = undefined;
  }

  function start(quote: InstantQuote) {
    stop();
    if (disposed) return;
    const current = generation;
    function tick() {
      if (current !== generation) return;
      timer = undefined;
      const remaining = quoteSecondsRemaining(quote, now());
      deps.onTick(remaining);
      if (current !== generation) return;
      if (remaining === 0 && isVisible()) {
        stop();
        deps.onExpire();
        return;
      }
      timer = setTimer(tick, 1000);
    }
    tick();
  }

  function dispose() {
    disposed = true;
    stop();
  }

  return { start, stop, dispose };
}

export function quoteExpired(quote: InstantQuote, now = Date.now()): boolean {
  return !Number.isFinite(Number(quote.expires_at)) || Number(quote.expires_at) * 1000 <= now;
}

export function reviewedIds(
  quote: InstantQuote,
  checked: Record<string, boolean>,
  acknowledged: Record<string, boolean>,
): string[] {
  return quote.rows
    .filter((row) => row.eligible && checked[row.id] && (!row.changed || acknowledged[row.id]))
    .map((row) => row.id);
}

/** Tokens are burned before network dispatch, including failures and aborts. */
export class InstantProcessSession {
  private sent = new Set<string>();
  consume(token: string): boolean {
    if (!token || this.sent.has(token)) return false;
    this.sent.add(token);
    return true;
  }
}
