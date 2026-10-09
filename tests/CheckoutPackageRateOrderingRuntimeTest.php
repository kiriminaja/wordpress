<?php

use PHPUnit\Framework\TestCase;

/** Combined checkout ordering must preserve native WooCommerce rates and selections. */
final class CheckoutPackageRateOrderingRuntimeTest extends TestCase {
	private static ?array $result = null;

	private function fixture(): array {
		if ( null === self::$result ) {
			$output = array();
			$status = 0;
			exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/checkout-package-rate-ordering-runtime.php' ) . ' 2>&1', $output, $status );
			$this->assertSame( 0, $status, implode( "\n", $output ) );
			self::$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
		}
		return self::$result;
	}

	public function test_controller_registers_and_invokes_the_real_package_filter(): void {
		$result = $this->fixture();
		$this->assertTrue( $result['registered_callback'] );
		$this->assertSame( 100, $result['priority'] );
		$this->assertSame( 2, $result['accepted_args'] );
		$this->assertTrue( $result['callback_matches_helper'] );
		$this->assertSame( array( 'instant-cheap', 'express-middle', 'instant-expensive' ), $result['cases']['combined']['keys'] );
	}

	public function test_each_method_sorts_alone_and_zero_delivery_cost_is_eligible(): void {
		$cases = $this->fixture()['cases'];
		$this->assertSame( array( 'instant-free', 'instant-low', 'instant-high' ), $cases['instant_only']['keys'] );
		$this->assertSame( array( 'express-free', 'express-low', 'express-high' ), $cases['express_only']['keys'] );
		$this->assertSame( array(), $cases['empty']['keys'] );
		$this->assertSame( array( 'only' ), $cases['singleton']['keys'] );
	}

	public function test_third_party_and_invalid_entries_keep_their_exact_slots(): void {
		$this->assertSame(
			array( 'third-first', 'valid-free', 'wrong-object', 'negative', 'valid-low', 'infinity', 'nan', 'third-middle', 'numeric-string', 'not-numeric', 'null-cost', 'boolean-cost', 'valid-high', 'prefix-impostor', 'null-entry', 'array-entry', 'third-last' ),
			$this->fixture()['cases']['mixed']['keys']
		);
		$this->assertTrue( $this->fixture()['cases']['mixed']['fixed_slots'] );
	}

	public function test_case_insensitive_label_ties_preserve_original_order_across_methods(): void {
		$this->assertSame(
			array( 'lowest', 'alpha-instant', 'alpha-express', 'alpha-second-instant', 'beta', 'zulu', 'highest' ),
			$this->fixture()['cases']['ties']['keys']
		);
	}

	public function test_sorting_preserves_associative_keys_identity_amounts_taxes_and_metadata_without_side_effects(): void {
		$result = $this->fixture();
		foreach ( $result['cases'] as $name => $case ) {
			$this->assertTrue( $case['identity_preserved'], $name );
			$this->assertTrue( $case['payload_preserved'], $name );
			$this->assertTrue( $case['input_unchanged'], $name );
			$this->assertTrue( $case['idempotent'], $name );
		}
		$this->assertSame( array( 'raw-instant', 'express-with-lower-total' ), $result['cases']['raw_delivery']['keys'], 'Instant admin fees are separate from the visible delivery cost.' );
		$this->assertSame( 0, $result['network_calls'] );
		$this->assertSame( 0, $result['session_writes'] );
		$this->assertTrue( $result['session_unchanged'] );
	}

	public function test_packages_sort_independently_and_keep_a_previous_native_instant_selection(): void {
		$result = $this->fixture();
		$this->assertSame( array( 'shared-instant', 'shared-express' ), $result['cases']['package_one']['keys'] );
		$this->assertSame( array( 'shared-express', 'shared-instant' ), $result['cases']['package_two']['keys'] );
		$this->assertTrue( $result['packages_independent'] );
		$this->assertSame( 'shared-instant', $result['chosen_method'] );
	}
}
