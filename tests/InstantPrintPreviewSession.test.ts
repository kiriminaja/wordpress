import { describe, expect, test } from 'bun:test';
import { PrintPreviewSession, type PreviewState } from '../src/lib/ui/print-preview-session';

function deferred() {
  let resolve!: (value: string) => void;
  let reject!: (error: Error) => void;
  const promise = new Promise<string>((yes, no) => { resolve = yes; reject = no; });
  return { promise, resolve, reject };
}

function setup() {
  let state: PreviewState = { url: '', loading: false, error: '' };
  const errors: string[] = [];
  const session = new PrintPreviewSession((next) => { state = next; });
  return { session, state: () => state, errors, onError: (error: string) => errors.push(error) };
}

describe('shared Instant/Express print preview session', () => {
  test('close/unmount aborts and ignores success even if the server ignores abort', async () => {
    const context = setup();
    const pending = deferred();
    let signal!: AbortSignal;
    const request = context.session.load((next) => { signal = next; return pending.promise; }, 'Failed', context.onError);
    expect(context.state().loading).toBe(true);
    context.session.cancel();
    expect(signal.aborted).toBe(true);
    expect(context.state()).toEqual({ url: '', loading: false, error: '' });
    pending.resolve('/old-label');
    await request;
    expect(context.state()).toEqual({ url: '', loading: false, error: '' });
    expect(context.errors).toEqual([]);
  });

  test('identity change/reopen aborts old generation and stale errors cannot clobber a new request', async () => {
    const context = setup();
    const old = deferred();
    const fresh = deferred();
    let signal!: AbortSignal;
    const first = context.session.load((next) => { signal = next; return old.promise; }, 'Failed', context.onError);
    const second = context.session.load(() => fresh.promise, 'Failed', context.onError);
    expect(signal.aborted).toBe(true);
    old.reject(new Error('stale failure'));
    await first;
    expect(context.state()).toEqual({ url: '', loading: true, error: '' });
    expect(context.errors).toEqual([]);
    fresh.resolve('/new-label');
    await second;
    expect(context.state()).toEqual({ url: '/new-label', loading: false, error: '' });
  });

  test('manual retry creates a new generation and clears the error until completion', async () => {
    const context = setup();
    await context.session.load(() => Promise.reject(new Error('Try again')), 'Failed', context.onError);
    expect(context.state().error).toBe('Try again');
    expect(context.errors).toEqual(['Try again']);
    const retry = deferred();
    const request = context.session.load(() => retry.promise, 'Failed', context.onError);
    expect(context.state()).toEqual({ url: '', loading: true, error: '' });
    retry.resolve('/retry-label');
    await request;
    expect(context.state()).toEqual({ url: '/retry-label', loading: false, error: '' });
    context.session.cancel();
    expect(context.state()).toEqual({ url: '', loading: false, error: '' });
  });

  test('superseded successful response cannot overwrite the latest ready URL', async () => {
    const context = setup();
    const old = deferred();
    const first = context.session.load(() => old.promise, 'Failed', context.onError);
    await context.session.load(() => Promise.resolve('/current-label'), 'Failed', context.onError);
    old.resolve('/old-label');
    await first;
    expect(context.state().url).toBe('/current-label');
  });
});
