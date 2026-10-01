import type { InstantQuote } from './types';

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
