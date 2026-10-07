import { describe, expect, test } from 'bun:test';
import { buyerRuntimeSource, buyerBrowserContext } from './helpers/buyer-runtime-source';
import { runInNewContext } from 'node:vm';

type Package = { package_id: string | number; rate_id: string };
type Snapshot = { version: 1; packages: Package[] };
type Selection = {
	seed(packages: unknown): boolean;
	choose(packageId: unknown, rateId: unknown, packages?: unknown): boolean;
	review(packages: unknown): boolean;
	reconcile(packages: unknown): boolean;
	matches(packages: unknown): boolean;
	snapshot(): Snapshot;
};
type Library = { create(options?: { onChange?(snapshot: Snapshot): void }): Selection };
const source = await buyerRuntimeSource('state');
function load(browser = false): Library {
	const context: Record<string, any> = browser ? { window: {} } : {};
	runInNewContext(source, context);
	return browser ? context.window.kiriofShippingSelection : context.kiriofShippingSelection;
}
const library = load();
const express = [{ package_id: '0', rate_id: 'kiriminaja-official_jne:REG' }];
const gosend = [{ package_id: '0', rate_id: 'kiriminaja-instant:gosend:same_day' }];
const cargo = [{ package_id: '0', rate_id: 'kiriminaja-official_jne:JTR' }];

describe('shipping selection buyer intent', () => {
	test('pure browser global and globalThis fallback require no DOM or Woo dependencies', () => {
		expect(typeof library.create).toBe('function');
		expect(typeof load(true).create).toBe('function');
		expect(library.create().snapshot()).toEqual({ version: 1, packages: [] });
	});

	test('initial Express then native GoSend user choice survives terms failure and repeated updates', () => {
		const changes: Snapshot[] = [];
		const selection = library.create({ onChange(value) { changes.push(value); } });
		expect(selection.seed(express)).toBe(true);
		expect(selection.choose('0', gosend[0].rate_id, gosend)).toBe(true);
		const before = selection.snapshot();
		// A terms/checkout validation failure only causes native reconciliation.
		for (let i = 0; i < 3; i++) {
			expect(selection.reconcile(gosend)).toBe(true);
			expect(selection.seed(express)).toBe(false);
		}
		expect(selection.snapshot()).toEqual(before);
		expect(changes).toEqual([{ version: 1, packages: express }, { version: 1, packages: gosend }]);
	});

	test('automatic Express fallback conflicts without overwriting GoSend, and restored quote matches', () => {
		const selection = library.create();
		selection.seed(express);
		selection.choose('0', gosend[0].rate_id, gosend);
		expect(selection.reconcile(express)).toBe(false);
		expect(selection.seed(express)).toBe(false);
		expect(selection.matches(express)).toBe(false);
		expect(selection.reconcile([])).toBe(false);
		expect(selection.choose('0', gosend[0].rate_id)).toBe(false);
		expect(selection.snapshot().packages).toEqual(gosend);
		expect(selection.reconcile(gosend)).toBe(true);
		expect(selection.choose('0', cargo[0].rate_id, cargo)).toBe(true);
		expect(selection.matches(cargo)).toBe(true);
	});

	test('temporary empty bootstrap never consumes the initial stable seed', () => {
		const selection = library.create();
		expect(selection.seed([])).toBe(false);
		expect(selection.seed(null)).toBe(false);
		expect(selection.reconcile(express)).toBe(false);
		expect(selection.matches([])).toBe(false);
		expect(selection.seed(express)).toBe(true);
		expect(selection.seed(gosend)).toBe(false);
	});

	test('multiple package changes fail safe until an explicit native choice reviews the whole next set', () => {
		const selection = library.create();
		const second = { package_id: 'warehouse-west', rate_id: 'flat_rate:21' };
		selection.seed(express);
		expect(selection.reconcile([...express, second])).toBe(false);
		expect(selection.snapshot().packages).toEqual(express);
		expect(selection.choose('0', gosend[0].rate_id, [...gosend, second])).toBe(true);
		expect(selection.matches([second, ...gosend])).toBe(true);
		expect(selection.reconcile(gosend)).toBe(false);
		expect(selection.seed(gosend)).toBe(false);
		expect(selection.choose('0', gosend[0].rate_id)).toBe(true);
		expect(selection.matches(gosend)).toBe(true);
		// User can intentionally choose an entirely native, non-plugin rate.
		expect(selection.review([second])).toBe(true);
		expect(selection.matches([second])).toBe(true);
	});

	test('choice only accepts an exact available pair and never manufactures an ID', () => {
		const selection = library.create();
		selection.seed(express);
		expect(selection.choose('missing', express[0].rate_id, express)).toBe(false);
		expect(selection.choose('0', 'kiriminaja-official', express)).toBe(false);
		expect(selection.choose('0', cargo[0].rate_id)).toBe(false);
		selection.reconcile(cargo);
		expect(selection.choose(0, cargo[0].rate_id)).toBe(true);
		expect(selection.snapshot()).toEqual({ version: 1, packages: cargo });
	});

	test('copies inputs, snapshots, current candidates and listener payloads; persists IDs only', () => {
		const selection = library.create({ onChange(value) { value.packages[0].rate_id = 'listener mutation'; } });
		const input = [{ ...express[0], price: 1000, label: 'Express', currency: 'IDR' }];
		selection.seed(input);
		input[0].rate_id = 'input mutation';
		const snapshot = selection.snapshot();
		snapshot.packages[0].package_id = 'snapshot mutation';
		snapshot.packages.push({ package_id: '9', rate_id: 'extra' });
		expect(selection.snapshot()).toEqual({ version: 1, packages: express });
		const next = gosend.map(entry => ({ ...entry }));
		selection.reconcile(next);
		next[0].rate_id = 'candidate mutation';
		expect(selection.choose('0', gosend[0].rate_id)).toBe(true);
		expect(selection.snapshot().packages).toEqual(gosend);
	});

	test('invalid IDs or duplicate package keys invalidate the entire set, not just a row', () => {
		const selection = library.create();
		for (const package_id of ['', null, undefined, {}, -1, 1.5, NaN, Infinity, Number.MAX_SAFE_INTEGER + 1, 'x'.repeat(257), '0\n', '0\u007f', '0\u0085']) {
			const invalid = [{ package_id, rate_id: 'flat_rate:1' }];
			expect(selection.seed(invalid)).toBe(false);
			expect(selection.review(invalid)).toBe(false);
			expect(selection.matches(invalid)).toBe(false);
		}
		for (const rate_id of ['', null, undefined, {}, 1, 'x'.repeat(257), 'rate\r', 'rate\u009f']) {
			expect(selection.seed([{ package_id: '0', rate_id }])).toBe(false);
		}
		for (const invalid of [{}, [null], [false], [,], [...express, ...express], [express[0], { package_id: 0, rate_id: 'other' }]]) {
			expect(selection.seed(invalid)).toBe(false);
		}
		expect(selection.seed(express)).toBe(true);
		expect(selection.reconcile([{ ...express[0], rate_id: 'invalid\n' }])).toBe(false);
		expect(selection.snapshot().packages).toEqual(express);
	});

	test('raw IDs retain spaces, punctuation, leading zeros and prototype-like package keys', () => {
		const selection = library.create();
		const packages = [
			{ package_id: '__proto__', rate_id: ' opaque rate:1 ' },
			{ package_id: '01', rate_id: 'service:exact' },
			{ package_id: Number.MAX_SAFE_INTEGER, rate_id: 'x'.repeat(256) },
		];
		expect(selection.seed(packages)).toBe(true);
		expect(selection.matches(packages)).toBe(true);
		expect(selection.matches(packages.map(entry => ({ ...entry, rate_id: entry.rate_id.trim() })))).toBe(false);
		expect(selection.snapshot().packages[2].package_id).toBe(String(Number.MAX_SAFE_INTEGER));
		expect(selection.matches([packages[0], { ...packages[1], package_id: '1' }, packages[2]])).toBe(false);
	});

	test('explicit review before bootstrap also prevents later seed overwrite and instances are isolated', () => {
		const selection = library.create();
		expect(selection.review(gosend)).toBe(true);
		expect(selection.seed(express)).toBe(false);
		const other = library.create();
		expect(other.seed(express)).toBe(true);
		expect(selection.matches(gosend)).toBe(true);
		expect(other.matches(express)).toBe(true);
	});
});
