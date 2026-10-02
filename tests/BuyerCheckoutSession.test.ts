import { describe, expect, test } from 'bun:test';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

type Snapshot = Record<string, unknown>;
type Result = { status: 'success' | 'error' | 'superseded' | 'disposed'; snapshot: Snapshot | null; error: unknown };
type State = { pending: Snapshot | null; inFlight: Snapshot | null; acknowledged: Snapshot | null; error: unknown; blocked: boolean; disposed: boolean; stalled: boolean; uncertain: boolean };
type Queue = { update(snapshot: Snapshot): Promise<Result>; retry(): Promise<Result>; resume(): void; dispose(): void; getState(): State };
type Options = { send(snapshot: Snapshot): unknown; onChange?(state: State): void; isBlocked?(): boolean; schedule?(callback: () => void): unknown; cancel?(handle: unknown): void; setTimeout?(callback: () => void, delay: number): unknown; clearTimeout?(handle: unknown): void };
type Session = { createQueue(options: Options): Queue; normalizeDestination(input: Snapshot): Snapshot };

const source = readFileSync(new URL('../assets/wp/js/kiriof-checkout-session.js', import.meta.url), 'utf8');
function load(withWindow = false): Session {
	const context: Record<string, any> = { setTimeout, clearTimeout };
	if (withWindow) context.window = {};
	runInNewContext(source, context);
	return withWindow ? context.window.kiriofBuyerCheckoutSession : context.kiriofBuyerCheckoutSession;
}
const session = load();
function deferred() {
	let resolve!: (value?: unknown) => void;
	let reject!: (error: unknown) => void;
	const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
	return { promise, resolve, reject };
}
async function flush() {
	await Promise.resolve();
	await Promise.resolve();
}
function setup(extra: Partial<Options> = {}) {
	const scheduled = new Map<number, () => void>();
	const requests: ReturnType<typeof deferred>[] = [];
	const sent: Snapshot[] = [];
	const changes: State[] = [];
	let sequence = 0;
	const queue = session.createQueue({
		send(snapshot) {
			sent.push(snapshot);
			const request = deferred();
			requests.push(request);
			return request.promise;
		},
		onChange(state) { changes.push(state); },
		schedule(callback) { const id = ++sequence; scheduled.set(id, callback); return id; },
		cancel(handle) { scheduled.delete(handle as number); },
		...extra,
	});
	function tick() {
		const callbacks = [...scheduled.values()];
		scheduled.clear();
		callbacks.forEach(callback => callback());
	}
	return { queue, sent, requests, changes, scheduled, tick };
}
const A = { district_id: '1' };
const B = { district_id: '2' };
const C = { district_id: '3' };

describe('buyer checkout session', () => {
	test('timeout holds serialization until actual settlement, never acknowledges late success, and retries only latest', async () => {
		const timers = new Map<number, () => void>(); let id = 0;
		const c = setup({ setTimeout(callback, delay) { expect(delay).toBe(15000); timers.set(++id, callback); return id; }, clearTimeout(handle) { timers.delete(handle as number); } });
		const a = c.queue.update(A); c.tick(); const b = c.queue.update(B);
		timers.get(1)!(); expect((await a).status).toBe('error'); expect((await b).status).toBe('error');
		expect(c.queue.getState().uncertain).toBe(true); expect(c.queue.getState().inFlight).toEqual(A);
		expect((await c.queue.retry()).status).toBe('error'); expect((await c.queue.update(C)).status).toBe('error');
		c.queue.resume(); c.tick(); expect(c.sent).toEqual([A]);
		c.requests[0].resolve(); await flush(); expect(c.queue.getState().acknowledged).toBeNull(); expect(c.queue.getState().uncertain).toBe(false);
		c.tick(); expect(c.sent).toEqual([A]); const retry = c.queue.retry(); c.tick(); expect(c.sent).toEqual([A, C]);
		c.requests[1].resolve(); expect((await retry).status).toBe('success'); expect(c.queue.getState().stalled).toBe(false); expect(timers.size).toBe(0);
	});
	test('dispose clears watchdog and late transport rejection is inert', async () => {
		let timer: (() => void) | null = null;
		const c = setup({ setTimeout(callback) { timer = callback; return 1; }, clearTimeout() { timer = null; } });
		const result = c.queue.update(A); c.tick(); expect(timer).not.toBeNull(); c.queue.dispose(); expect(timer).toBeNull();
		c.requests[0].reject(new Error('late')); await flush(); expect((await result).status).toBe('disposed'); expect(c.queue.getState().acknowledged).toBeNull();
	});

	test('browser global and globalThis fallback need no WordPress or jQuery', () => {
		expect(typeof session.createQueue).toBe('function');
		expect(typeof load(true).createQueue).toBe('function');
	});

	test('normalizes canonical version 1 destination strings without coordinates', () => {
		expect(session.normalizeDestination({ district_id: ' 00042 ', district_label: '  North\n District ', postcode: ' 12\t 345 ', country: ' id ', address_type: ' SHIPPING ', latitude: 1 })).toEqual({
			version: 1, district_id: '42', district_label: 'North District', postcode: '12345', country: 'ID', address_type: 'shipping',
		});
	});

	test('invalid IDs clear both district fields and missing destination is supported', () => {
		for (const district_id of [0, -1, '', null, '3.5', '12wrong', Infinity, '9007199254740992']) {
			expect(session.normalizeDestination({ district_id, district_label: 'Old' }).district_id).toBe('');
			expect(session.normalizeDestination({ district_id, district_label: 'Old' }).district_label).toBe('');
		}
	});

	test('acknowledges only successful completion', async () => {
		const c = setup();
		const result = c.queue.update(A);
		expect(c.queue.getState().acknowledged).toBeNull();
		c.tick();
		expect(c.queue.getState().inFlight).toEqual(A);
		c.requests[0].resolve();
		expect((await result).status).toBe('success');
		expect(c.queue.getState().acknowledged).toEqual(A);
	});

	test('rapid updates before dispatch coalesce and settle superseded callers', async () => {
		const c = setup();
		const a = c.queue.update(A);
		const b = c.queue.update(B);
		const latest = c.queue.update(C);
		expect((await a).status).toBe('superseded');
		expect((await b).status).toBe('superseded');
		c.tick();
		expect(c.sent).toEqual([C]);
		c.requests[0].resolve();
		expect((await latest).status).toBe('success');
	});

	test('serializes transports and coalesces intermediates while one is in flight', async () => {
		const c = setup();
		const a = c.queue.update(A);
		c.tick();
		const b = c.queue.update(B);
		const latest = c.queue.update(C);
		c.tick();
		expect(c.sent).toEqual([A]);
		expect((await b).status).toBe('superseded');
		c.requests[0].resolve();
		await a;
		c.tick();
		expect(c.sent).toEqual([A, C]);
		c.requests[1].resolve();
		expect((await latest).status).toBe('success');
	});

	test('completed B then A in flight then B sends corrective B', async () => {
		const c = setup();
		const first = c.queue.update(B);
		c.tick();
		c.requests[0].resolve();
		await first;
		const a = c.queue.update(A);
		c.tick();
		const corrective = c.queue.update(B);
		c.requests[1].resolve();
		await a;
		c.tick();
		expect(c.sent).toEqual([B, A, B]);
		c.requests[2].resolve();
		expect((await corrective).status).toBe('success');
		expect(c.queue.getState().acknowledged).toEqual(B);
	});

	test('deduplicates idle, pending, and in-flight snapshots including reordered keys', async () => {
		const c = setup();
		const first = c.queue.update({ country: 'ID', district_id: '1' });
		const duplicate = c.queue.update({ district_id: '1', country: 'ID' });
		c.tick();
		const inflightDuplicate = c.queue.update({ district_id: '1', country: 'ID' });
		c.requests[0].resolve();
		expect((await first).status).toBe('success');
		expect((await duplicate).status).toBe('success');
		expect((await inflightDuplicate).status).toBe('success');
		expect((await c.queue.update({ country: 'ID', district_id: '1' })).status).toBe('success');
		c.tick();
		expect(c.sent).toHaveLength(1);
	});

	test('same in-flight snapshot is not deduplicated across conflicting pending work', async () => {
		const c = setup();
		const a = c.queue.update(A);
		c.tick();
		const b = c.queue.update(B);
		const returnToA = c.queue.update(A);
		expect((await b).status).toBe('superseded');
		c.requests[0].resolve();
		await a;
		c.tick();
		expect(c.sent).toEqual([A, A]);
		c.requests[1].resolve();
		await returnToA;
	});

	test('failure retains pending error without spontaneous retries; explicit retry succeeds', async () => {
		const c = setup();
		const a = c.queue.update(A);
		c.tick();
		const failure = new Error('offline');
		c.requests[0].reject(failure);
		expect((await a).status).toBe('error');
		expect(c.queue.getState().error).toBe(failure);
		expect(c.queue.getState().pending).toEqual(A);
		expect(c.queue.getState().acknowledged).toBeNull();
		c.queue.resume();
		c.tick();
		expect(c.sent).toHaveLength(1);
		expect((await c.queue.update(A)).status).toBe('error');
		const retry = c.queue.retry();
		expect(c.queue.getState().error).toBeNull();
		c.tick();
		c.requests[1].resolve();
		expect((await retry).status).toBe('success');
		expect(c.queue.getState().pending).toBeNull();
	});

	test('newer pending still sends after preceding request fails', async () => {
		const c = setup();
		const a = c.queue.update(A);
		c.tick();
		const b = c.queue.update(B);
		c.requests[0].reject(new Error('older failed'));
		expect((await a).status).toBe('error');
		c.tick();
		expect(c.sent).toEqual([A, B]);
		c.requests[1].resolve();
		expect((await b).status).toBe('success');
	});

	test('different update replaces failed pending and recovers without retry', async () => {
		const c = setup();
		const a = c.queue.update(A);
		c.tick();
		c.requests[0].reject(new Error('failed'));
		await a;
		const b = c.queue.update(B);
		c.tick();
		c.requests[1].resolve();
		expect((await b).status).toBe('success');
		expect(c.queue.getState().error).toBeNull();
	});

	test('synchronous transport throws are errors and can be retried', async () => {
		let calls = 0;
		const failure = new Error('sync');
		const c = setup({ send() { if (++calls === 1) throw failure; return 'ok'; } });
		const first = c.queue.update(A);
		c.tick();
		expect((await first).error).toBe(failure);
		const retry = c.queue.retry();
		c.tick();
		expect((await retry).status).toBe('success');
		expect(calls).toBe(2);
	});

	test('checks native shipping gate immediately before sending and resumes latest', async () => {
		let blocked = false;
		const c = setup({ isBlocked: () => blocked });
		const a = c.queue.update(A);
		blocked = true;
		c.tick();
		expect(c.sent).toHaveLength(0);
		expect(c.queue.getState().blocked).toBe(true);
		const b = c.queue.update(B);
		expect((await a).status).toBe('superseded');
		c.tick();
		expect(c.sent).toHaveLength(0);
		blocked = false;
		c.queue.resume();
		c.tick();
		expect(c.sent).toEqual([B]);
		expect(c.queue.getState().blocked).toBe(false);
		c.requests[0].resolve();
		await b;
	});

	test('gating applies to a pending update after an in-flight completion', async () => {
		let blocked = false;
		const c = setup({ isBlocked: () => blocked });
		const a = c.queue.update(A);
		c.tick();
		const b = c.queue.update(B);
		blocked = true;
		c.requests[0].resolve();
		await a;
		c.tick();
		expect(c.sent).toEqual([A]);
		blocked = false;
		c.queue.resume();
		c.tick();
		c.requests[1].resolve();
		await b;
	});

	test('dispose cancels scheduled sends and settles pending, retry and future updates', async () => {
		const c = setup();
		const a = c.queue.update(A);
		c.queue.dispose();
		c.queue.dispose();
		c.tick();
		expect(c.sent).toHaveLength(0);
		expect(c.scheduled.size).toBe(0);
		expect((await a).status).toBe('disposed');
		expect((await c.queue.retry()).status).toBe('disposed');
		expect((await c.queue.update(B)).status).toBe('disposed');
		c.queue.resume();
		expect(c.queue.getState().disposed).toBe(true);
	});

	test('dispose never aborts server mutations or notifies after late success or failure', async () => {
		for (const reject of [false, true]) {
			const c = setup();
			const a = c.queue.update(A);
			c.tick();
			const b = c.queue.update(B);
			c.queue.dispose();
			const count = c.changes.length;
			expect((await a).status).toBe('disposed');
			expect((await b).status).toBe('disposed');
			if (reject) c.requests[0].reject(new Error('late')); else c.requests[0].resolve();
			await flush();
			c.tick();
			expect(c.changes).toHaveLength(count);
			expect(c.sent).toEqual([A]);
			expect(c.queue.getState().acknowledged).toBeNull();
		}
	});

	test('snapshots are cloned and deeply immutable across caller, state, send, and result', async () => {
		const c = setup();
		const input = { destination: { district_id: '1' }, values: ['old'] };
		const result = c.queue.update(input);
		input.destination.district_id = '999';
		input.values.push('new');
		expect(c.queue.getState().pending).toEqual({ destination: { district_id: '1' }, values: ['old'] });
		expect(Object.isFrozen(c.queue.getState())).toBe(true);
		c.tick();
		expect(Object.isFrozen(c.sent[0])).toBe(true);
		expect(Object.isFrozen(c.sent[0].destination)).toBe(true);
		expect(Object.isFrozen(c.sent[0].values)).toBe(true);
		c.requests[0].resolve();
		expect((await result).snapshot).toEqual({ destination: { district_id: '1' }, values: ['old'] });
	});

	test('retry during a transport joins the existing request and idle retry is harmless', async () => {
		const c = setup();
		expect((await c.queue.retry()).status).toBe('success');
		const a = c.queue.update(A);
		c.tick();
		const retry = c.queue.retry();
		c.requests[0].resolve();
		expect((await a).status).toBe('success');
		expect((await retry).status).toBe('success');
		c.tick();
		expect(c.sent).toHaveLength(1);
	});
});
