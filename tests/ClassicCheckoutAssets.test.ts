import { describe, expect, test } from 'bun:test';
import { readFileSync } from 'node:fs';
import { evaluateLegacyCheckout } from './helpers/legacy-checkout-source';

const enqueue = readFileSync(new URL('../inc/Base/Enqueue.php', import.meta.url), 'utf8');

function harness(enabled = true, checkout = true, ownsDistrict = false) {
	const handlers: { event: string; callback: (...args: any[]) => any }[] = [];
	const ready: (() => void)[] = [];
	const chain: any = {
		length: 1,
		on(event: string, ...args: any[]) { handlers.push({ event, callback: args.at(-1) }); return chain; },
		one(event: string, ...args: any[]) { handlers.push({ event, callback: args.at(-1) }); return chain; },
		off() { return chain; },
		ready(callback: () => void) { ready.push(callback); return chain; },
	};
	const jquery: any = () => chain;
	jquery.each = () => {};
	const root: any = { kiriofClassicCheckoutConfig: { enabled, ownsDistrict }, kiriofBillingAddressConfig: { isCheckout: true } };
	const document = { body: {}, querySelector: (selector: string) => selector === 'form.checkout' && checkout ? {} : null };
	const context: any = { window: root, document, jQuery: jquery };
	evaluateLegacyCheckout(context);
	return { context, handlers, ready };
}

describe('Classic asset registration and legacy ownership', () => {
	test('Classic dependency graph never loads Blocks or WordPress element APIs', () => {
		const registration = enqueue.split('public function register_classic_checkout_assets')[1].split('/** Only the editable')[0];
		expect(registration).not.toContain('wc-blocks');
		expect(registration).not.toContain('wp-element');
		expect(registration).not.toContain('kiriof-buyer-checkout.js');
		expect(registration).toContain("'kiriof-classic-checkout-core' => array( 'assets/wp/js/kiriof-classic-checkout-core.js', array( 'kiriof-checkout-session' ) )");
		expect(registration).toContain("'kiriof-map-checkout-classic' => array( 'assets/wp/js/kiriof-map-checkout.js', array( 'kiriof-checkout-session', 'kiriof-leaflet' ) )");
		expect(registration).toContain("array( 'jquery', 'kiriof-classic-checkout-core', 'kiriof-map-checkout-classic' )");
		expect(registration).toContain("$config = $this->buyer_checkout_config();");
		expect(registration).toContain("$config['enabled'] = $this->classic_instant_enabled();");
		expect(registration).toContain("$config['ownsDistrict'] = false;");
		expect(registration).toContain("'kiriofClassicCheckoutConfig', $config");
		expect(registration).toContain("'kiriofMapCheckoutConfig', $this->map_checkout_config()");
		expect(registration).toContain('assets/wp/css/kiriof-classic-checkout.css');
	});

	test('only editable non-Blocks checkout enqueues Classic; legacy loads after its config', () => {
		const predicate = enqueue.split('private function isClassicCheckoutPage(): bool {')[1].split('\n    }')[0];
		expect(predicate).toContain("function_exists( 'is_checkout' ) && is_checkout()");
		expect(predicate).toContain('! $this->isBlockCartOrCheckoutPage()');
		expect(predicate).toContain("is_wc_endpoint_url( 'order-received' )");
		expect(enqueue).toContain("$this->isClassicCheckoutPage() && $this->classic_instant_enabled() ? array( 'kiriof-script', 'kiriof-classic-checkout' )");
		expect(enqueue).toContain("if ( $this->isClassicCheckoutPage() && $this->classic_instant_enabled() ) {\n            $this->register_classic_checkout_assets( true );\n            wp_enqueue_script( 'kiriof-classic-checkout' );\n            wp_enqueue_style( 'kiriof-classic-checkout' );");
		expect(enqueue).toContain(')->enabledInstant()');
	});

	test('enabled Classic yields every competing legacy entry point and event, including outside ready', () => {
		const h = harness(true, true, true);
		h.ready.forEach(callback => callback());
		for (const name of ['changeDistrict', 'getSearchAreaKelurahan', 'kiriofCodInsurance', 'kiriofHandleCodInsurance', 'kiriofChangeCodPayment', 'kiriofChangeDifferentAddress', 'kiriofInitClassicShippingMethodSelect', 'kiriofScheduleClassicShippingMethodSelectInit', 'kiriofRestoreClassicDistrictSelections', 'kiriofRestoreClassicDistrictSelection', 'kiriofInitBlockCheckoutCompatibility']) {
			expect(() => h.context[name]()).not.toThrow();
		}
		for (const { callback } of h.handlers) expect(() => callback()).not.toThrow();
		expect(h.handlers.some(handler => handler.event.includes('country_to_state_changing'))).toBe(true);
	});

	test('config alone or checkout form alone never steals account/cart/Blocks ownership', () => {
		expect(harness(true, false).context.kiriofUsesClassicCheckout()).toBe(false);
		expect(harness(false, true).context.kiriofUsesClassicCheckout()).toBe(false);
		expect(harness(true, true).context.kiriofUsesClassicCheckout()).toBe(false);
		expect(harness(true, true, true).context.kiriofUsesClassicCheckout()).toBe(true);
		const h = harness(true, false);
		delete h.context.window.kiriofClassicCheckoutConfig;
		expect(h.context.kiriofUsesClassicCheckout()).toBe(false);
	});
});
