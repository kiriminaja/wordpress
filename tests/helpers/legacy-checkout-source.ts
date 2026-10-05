import { readFileSync } from 'node:fs';
import { createContext, runInContext, type Context } from 'node:vm';

/** Match WordPress's dependency chain; these are separate global scripts, not a bundle. */
export const legacyCheckoutModules = [
	'assets/wp/js/checkout/state.js',
	'assets/wp/js/checkout/blocks-compatibility.js',
	'assets/wp/js/checkout/classic-district.js',
	'assets/wp/js/checkout/shipping-payment.js',
	'assets/wp/js/form-billing-address.js',
].map(path => ({ path, source: readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8') }));

export function evaluateLegacyCheckout(context: Context) {
	const runtime = createContext(context);
	for (const { path, source } of legacyCheckoutModules) {
		runInContext(source, runtime, { filename: path });
	}
	return runtime;
}

/** Fail loudly if an isolated behavior test's source boundaries move. */
export function legacyCheckoutSlice(path: string, from: string, to: string) {
	const module = legacyCheckoutModules.find(module => module.path === path);
	if (!module) throw new Error(`Unknown legacy checkout module: ${path}`);
	const start = module.source.indexOf(from);
	const end = module.source.indexOf(to, start);
	if (start < 0 || end <= start) throw new Error(`Missing legacy checkout source boundaries: ${from} / ${to}`);
	return module.source.slice(start, end);
}
