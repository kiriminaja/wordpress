import { describe, expect, test } from 'bun:test';
import { InstantProcessSession, quoteExpired, reviewedIds } from '../src/lib/transactions/instant-process-session';
import type { InstantQuote } from '../src/lib/transactions/types';

const quote: InstantQuote = {
  token: 'review-1', expires_at: 100, batch_count: 1, payment_methods: ['qris'],
  rows: [
    { id: 'unchanged', before: 10, after: 10, changed: false, eligible: true, error: '' },
    { id: 'changed', before: 10, after: 20, changed: true, eligible: true, error: '' },
    { id: 'failed', before: 10, after: null, changed: false, eligible: false, error: 'Unavailable' },
  ],
};

describe('Instant review session', () => {
  test('expiry is inclusive and malformed expiry fails closed', () => {
    expect(quoteExpired(quote, 99999)).toBe(false);
    expect(quoteExpired(quote, 100000)).toBe(true);
    expect(quoteExpired({ ...quote, expires_at: NaN }, 0)).toBe(true);
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
