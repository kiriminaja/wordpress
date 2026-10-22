import { describe, expect, test } from 'bun:test';
import {
  createInstantQuoteClock,
  InstantProcessSession,
  isTopAccount,
  quoteExpired,
  quoteSecondsRemaining,
  quoteSummary,
  reviewSelection,
  reviewedIds,
} from '../src/lib/transactions/instant-process-session';
import type { InstantQuote } from '../src/lib/transactions/types';

const quote: InstantQuote = {
  token: 'review-1',
  expires_at: 100,
  batch_count: 1,
  payment_methods: ['qris'],
  rows: [
    { id: 'unchanged', before: 10, after: 10, changed: false, eligible: true, error: '' },
    { id: 'changed', before: 10, after: 20, changed: true, eligible: true, error: '' },
    {
      id: 'failed',
      before: 10,
      after: null,
      changed: false,
      eligible: false,
      error: 'Unavailable',
    },
  ],
};

describe('Instant review session', () => {
  test('expiry is inclusive and malformed expiry fails closed', () => {
    expect(quoteExpired(quote, 99999)).toBe(false);
    expect(quoteExpired(quote, 100000)).toBe(true);
    expect(quoteExpired({ ...quote, expires_at: NaN }, 0)).toBe(true);
  });

  function fakeClock() {
    let now = 0;
    let visible = true;
    let nextId = 0;
    const timers = new Map<number, () => void>();
    const ticks: number[] = [];
    let expirations = 0;
    const deps = {
      now: () => now,
      isVisible: () => visible,
      onTick: (remaining: number) => {
        ticks.push(remaining);
      },
      onExpire: () => {
        expirations++;
      },
      setTimer: (callback: () => void, delay: number) => {
        expect(delay).toBe(1000);
        const id = ++nextId;
        timers.set(id, callback);
        return id as unknown as ReturnType<typeof setTimeout>;
      },
      clearTimer: (id: ReturnType<typeof setTimeout>) => {
        timers.delete(id as unknown as number);
      },
    };
    return {
      deps,
      ticks,
      timers,
      get expirations() {
        return expirations;
      },
      setNow: (value: number) => {
        now = value;
      },
      setVisible: (value: boolean) => {
        visible = value;
      },
      fire() {
        const entry = timers.entries().next().value;
        if (!entry) return;
        timers.delete(entry[0]);
        entry[1]();
      },
    };
  }

  describe('Instant quote clock', () => {
    test('uses server epoch, rounding up positive fractions and catching up late ticks', () => {
      const fake = fakeClock();
      const clock = createInstantQuoteClock(fake.deps);
      clock.start({ ...quote, expires_at: 120 });
      expect(fake.ticks).toEqual([120]);
      fake.setNow(115001);
      fake.fire();
      expect(fake.ticks).toEqual([120, 5]);
      fake.setNow(130000);
      const stale = [...fake.timers.values()][0];
      fake.fire();
      expect(fake.ticks).toEqual([120, 5, 0]);
      expect(fake.expirations).toBe(1);
      expect(fake.timers.size).toBe(0);
      stale();
      fake.fire();
      expect(fake.expirations).toBe(1);
      expect(fake.ticks).toEqual([120, 5, 0]);
    });

    test('hidden expiry stays at zero until a visible recomputation', () => {
      const fake = fakeClock();
      const clock = createInstantQuoteClock(fake.deps);
      clock.start(quote);
      fake.setVisible(false);
      fake.setNow(100000);
      fake.fire();
      fake.setNow(180000);
      fake.fire();
      expect(fake.ticks).toEqual([100, 0, 0]);
      expect(fake.expirations).toBe(0);
      expect(fake.timers.size).toBe(1);
      fake.setVisible(true);
      fake.fire();
      expect(fake.expirations).toBe(1);
      expect(fake.timers.size).toBe(0);
    });

    test('stop on closing or dispatch clears the timer and ignores queued callbacks', () => {
      const fake = fakeClock();
      const clock = createInstantQuoteClock(fake.deps);
      clock.start(quote);
      const queued = [...fake.timers.values()][0];
      clock.stop();
      clock.stop();
      expect(fake.timers.size).toBe(0);
      fake.setNow(200000);
      queued();
      expect(fake.expirations).toBe(0);
      expect(fake.ticks).toEqual([100]);
      clock.start({ ...quote, expires_at: 300 });
      expect(fake.ticks).toEqual([100, 100]);
      clock.dispose();
      clock.start(quote);
      expect(fake.timers.size).toBe(0);
      expect(fake.ticks).toEqual([100, 100]);
    });

    test('onTick can stop immediately without scheduling or expiring', () => {
      const fake = fakeClock();
      const clock = createInstantQuoteClock({
        ...fake.deps,
        onTick: (remaining) => {
          fake.deps.onTick(remaining);
          clock.stop();
        },
      });
      clock.start({ ...quote, expires_at: 0 });
      expect(fake.ticks).toEqual([0]);
      expect(fake.expirations).toBe(0);
      expect(fake.timers.size).toBe(0);
    });

    test('onTick can replace an expiring quote with exactly one live timer', () => {
      const fake = fakeClock();
      const clock = createInstantQuoteClock({
        ...fake.deps,
        onTick: (remaining) => {
          fake.deps.onTick(remaining);
          if (remaining === 0) clock.start({ ...quote, expires_at: 120 });
        },
      });
      clock.start({ ...quote, expires_at: 0 });
      expect(fake.ticks).toEqual([0, 120]);
      expect(fake.expirations).toBe(0);
      expect(fake.timers.size).toBe(1);
      clock.dispose();
    });

    test('onExpire can start a fresh quote without the old clock stopping it', () => {
      const fake = fakeClock();
      const clock = createInstantQuoteClock({
        ...fake.deps,
        onExpire: () => {
          fake.deps.onExpire();
          clock.start({ ...quote, expires_at: 120 });
        },
      });
      clock.start({ ...quote, expires_at: 0 });
      expect(fake.expirations).toBe(1);
      expect(fake.ticks).toEqual([0, 120]);
      expect(fake.timers.size).toBe(1);
      clock.dispose();
    });

    test('malformed and expired deadlines expire immediately once without timers', () => {
      for (const expires_at of [0, -1, NaN, Infinity, -Infinity, null, undefined, '120']) {
        const fake = fakeClock();
        const clock = createInstantQuoteClock(fake.deps);
        clock.start({ ...quote, expires_at } as InstantQuote);
        expect(fake.ticks).toEqual([0]);
        expect(fake.expirations).toBe(1);
        expect(fake.timers.size).toBe(0);
        clock.stop();
        clock.dispose();
        expect(fake.expirations).toBe(1);
      }
    });
  });

  describe('Instant quote review helpers', () => {
    test('seconds remaining is epoch based and inclusive at expiry', () => {
      expect(quoteSecondsRemaining(quote, 99999)).toBe(1);
      expect(quoteSecondsRemaining(quote, 100000)).toBe(0);
      expect(quoteSecondsRemaining(quote, 120000)).toBe(0);
      expect(quoteSecondsRemaining(quote, NaN)).toBe(0);
      expect(quoteSecondsRemaining({ ...quote, expires_at: Number.MAX_VALUE }, 0)).toBe(0);
    });

    test('TOP account requires exactly one exact payment method', () => {
      expect(isTopAccount({ ...quote, payment_methods: ['top'] })).toBe(true);
      for (const payment_methods of [[], ['qris'], ['TOP'], ['top', 'qris'], ['top', 'top']]) {
        expect(isTopAccount({ ...quote, payment_methods })).toBe(false);
      }
    });

    test('refresh preserves surviving exclusions, defaults new rows and drops stale keys', () => {
      expect(reviewSelection(quote)).toEqual({ unchanged: true, changed: true, failed: false });
      const previous = { unchanged: false, failed: true, removed: false };
      expect(reviewSelection(quote, previous)).toEqual({
        unchanged: false,
        changed: true,
        failed: false,
      });
      expect(previous).toEqual({ unchanged: false, failed: true, removed: false });
      expect(
        reviewSelection({ ...quote, rows: [{ ...quote.rows[0], id: 'toString' }] }, {}),
      ).toEqual({ toString: true });
    });

    test('summarizes only selected eligible comparable amounts without inventing null prices', () => {
      const checked = { unchanged: true, changed: true, failed: true };
      expect(quoteSummary(quote, checked)).toEqual({
        before: 20,
        after: 30,
        gap: 10,
        selectedCount: 2,
        changedCount: 1,
        unavailableCount: 1,
      });
      expect(quoteSummary(quote, { changed: true })).toEqual({
        before: 10,
        after: 20,
        gap: 10,
        selectedCount: 1,
        changedCount: 1,
        unavailableCount: 1,
      });
      const rows = [null, NaN, Infinity, -1, '10'].flatMap((amount, index) => [
        { ...quote.rows[0], id: `before-${index}`, before: amount },
        { ...quote.rows[0], id: `after-${index}`, after: amount },
      ]);
      const malformed = { ...quote, rows } as InstantQuote;
      expect(
        quoteSummary(malformed, Object.fromEntries(rows.map((row) => [row.id, true]))),
      ).toEqual({
        before: 0,
        after: 0,
        gap: 0,
        selectedCount: 0,
        changedCount: 0,
        unavailableCount: 10,
      });
      const zero = { ...quote, rows: [{ ...quote.rows[0], before: 0, after: 0 }] };
      expect(quoteSummary(zero, { unchanged: true }).selectedCount).toBe(1);
    });
  });
  test('only selected eligible rows with acknowledged price changes dispatch', () => {
    const checked = { unchanged: true, changed: true, failed: true, extra: true };
    expect(reviewedIds(quote, checked, {})).toEqual(['unchanged']);
    expect(reviewedIds(quote, checked, { changed: true })).toEqual(['unchanged', 'changed']);
    expect(reviewedIds(quote, { changed: false }, { changed: true })).toEqual([]);
  });
  test('tokens are one-shot even when network outcomes are unknown', () => {
    const session = new InstantProcessSession();
    expect(session.consume('')).toBe(false);
    expect(session.consume(quote.token)).toBe(true);
    expect(session.consume(quote.token)).toBe(false);
    expect(session.consume('fresh-review')).toBe(true);
    expect(session.consume(quote.token)).toBe(false);
  });
});
