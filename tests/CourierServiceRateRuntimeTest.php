<?php

use PHPUnit\Framework\TestCase;

/** Exercises real rate consumers in subprocesses, without leaking WP/WC stubs. */
final class CourierServiceRateRuntimeTest extends TestCase {
	private function run_fixture( string $action ): array {
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/courier-service-rate-runtime.php' ) . ' ' . escapeshellarg( $action ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_checkout_fee_consumer_rejects_disabled_cached_fees_and_accepts_enabled_aliases(): void {
		$result = $this->run_fixture( 'fees' );
		foreach ( array( 'disabled_legacy', 'disabled_current', 'deny_all', 'international' ) as $key ) {
			$this->assertSame( array(), $result[ $key ]['fees'], $key );
			$this->assertSame( 0, $result[ $key ]['insurance'], $key );
			$this->assertSame( 0, $result[ $key ]['cod'], $key );
			$this->assertNull( $result[ $key ]['context'], $key );
		}
		foreach ( array( 'allowed_alias', 'allowed_colon_alias' ) as $key ) {
			$this->assertSame( array( array( 'name' => 'Insurance', 'amount' => 1500 ), array( 'name' => 'COD Fee', 'amount' => 2500 ) ), $result[ $key ]['fees'], $key );
			$this->assertSame( array( 'jne' => array( 'REG' ) ), $result[ $key ]['context']['courier_services'], $key );
		}
		// A pre-policy cache must be recalculated, even when the pair remains enabled.
		$this->assertSame( array(), $result['allowed_legacy_context']['fees'] );
		$this->assertSame( array( 'jne' => array( 'REG' ) ), $result['allowed_legacy_context']['context']['courier_services'] );
		$this->assertSame( 0, $result['network_calls'] );
	}

	public function test_native_validator_prefers_raw_service_type_and_preserves_legacy_fallback(): void {
		$result = $this->run_fixture( 'validator' );
		$this->assertSame( array( 'raw-allowed', 'legacy-name', 'array-row' ), $result['explicit'] );
		$this->assertSame( array( 'raw-allowed', 'raw-disabled', 'legacy-name', 'array-row' ), $result['legacy'] );
		$this->assertSame( array(), $result['deny_all'] );
	}

	public function test_pricing_options_refilter_cached_rows_and_keep_raw_service_codes(): void {
		$result = $this->run_fixture( 'options' );
		$this->assertSame( array( 'jne_REG23' ), array_column( $result['regular'], 'key' ) );
		$this->assertSame( 'REG23', $result['regular'][0]['service_name'] );
		$this->assertSame( 8000, $result['regular'][0]['price'] );
		$this->assertSame( array( 'jne_YES' ), array_column( $result['express'], 'key' ) );
		$this->assertSame( array(), $result['deny_all'] );
	}

	public function test_selected_expedition_rejects_disabled_cached_row_and_returns_allowed_row(): void {
		$result = $this->run_fixture( 'selected' );
		$this->assertNull( $result['disabled'] );
		$this->assertSame( 'raw-allowed', $result['allowed']['id'] );
		$this->assertSame( 'REG23', $result['allowed']['service_type'] );
		$this->assertNull( $result['deny_all'] );
	}

	public function test_calculation_guard_rejects_disabled_pair_before_free_shipping_fees(): void {
		$result = $this->run_fixture( 'calculation' );
		foreach ( array( 'disabled', 'deny_all', 'deny_all_without_service' ) as $key ) {
			$this->assertSame( 400, $result[ $key ]['status'] );
			$this->assertSame( 'Expedition Not Found', $result[ $key ]['message'] );
			$this->assertSame( array(), $result[ $key ]['data'] );
		}
		$this->assertSame( 0, $result['guard_coupon_reads'] );
		$this->assertSame( 200, $result['allowed']['status'] );
		$this->assertSame( 0, $result['allowed']['data']['calculation_result']['ongkir_fee_amt'] );
		$this->assertSame( 'REG23', $result['allowed']['data']['calculation_result']['selected_expedition']['service_type'] );
	}

	public function test_package_rate_cache_changes_for_different_service_on_same_courier_without_network(): void {
		$result = $this->run_fixture( 'cache' );
		$this->assertSame( array( 'jne' ), $result['couriers_before'] );
		$this->assertSame( $result['couriers_before'], $result['couriers_after'] );
		$this->assertSame( $result['regular'], $result['unchanged'] );
		$this->assertNotSame( $result['regular'], $result['express'] );
		$this->assertNotSame( $result['express'], $result['deny_all'] );
		$this->assertNotSame( $result['legacy_country_policy'], $result['regular'] );
		$this->assertNotSame( $result['deny_all'], $result['foreign_country'] );
		$this->assertSame( $result['destination'], $result['returned_destination'] );
		$this->assertSame( 0, $result['network_calls'] );
	}

	public function test_onboarding_courier_readiness_respects_explicit_policy_over_legacy_csv(): void {
		$this->assertSame( array( 'legacy' => true, 'deny_all' => false, 'empty_services' => false, 'enabled' => true ), $this->run_fixture( 'readiness' ) );
	}

	public function test_shipping_deny_all_guard_precedes_free_coupon_rate_creation(): void {
		$source = file_get_contents( PLUGIN_DIR . '/wc/KiriminajaShippingMethod.php' );
		$start = strpos( $source, 'public function calculate_shipping(' );
		$this->assertNotFalse( $start );
		$source = substr( $source, $start );
		$this->assertMatchesRegularExpression( '/if\s*\(\s*!\s*\( new .*?SettingRepository\(\) \)->hasEnabledCourierServices\(\)\s*\)\s*\{\s*return;\s*\}/s', $source );
		$guard = strpos( $source, '->hasEnabledCourierServices()' );
		$coupon = strpos( $source, 'if ($this->hasActiveFreeShippingCoupon())' );
		$rate = strpos( $source, '$this->add_rate(' );
		$this->assertNotFalse( $guard );
		$this->assertNotFalse( $coupon );
		$this->assertNotFalse( $rate );
		$this->assertLessThan( $coupon, $guard );
		$this->assertLessThan( $rate, $coupon );
	}

	public function test_shipping_rejects_foreign_and_unknown_package_countries_before_pricing_or_free_coupons(): void {
		$result = $this->run_fixture( 'destination_country' );
		foreach ( array( 'paid', 'free' ) as $mode ) {
			foreach ( array( 'ireland', 'us', 'empty', 'missing', 'malformed' ) as $country ) {
				$this->assertSame( array(), $result[ $mode ][ $country ]['rates'], "$mode $country" );
				$this->assertSame( array(), $result[ $mode ][ $country ]['meta'], "$mode $country" );
				$this->assertSame( 0, $result[ $mode ][ $country ]['coupon_reads'], "$mode $country" );
			}
		}
		$this->assertSame( 'kiriminaja-official_jne_REG', $result['paid']['indonesia']['rates'][0]['id'] );
		$this->assertSame( 12000, $result['paid']['indonesia']['rates'][0]['cost'] );
		$this->assertSame( 'kiriminaja-official_free', $result['free']['indonesia']['rates'][0]['id'] );
		$this->assertSame( 0, $result['free']['indonesia']['rates'][0]['cost'] );
		$this->assertSame( array( array( 'kiriminaja-official_jne_REG' ), array(), array( 'kiriminaja-official_jne_REG' ) ), $result['transition'] );
		$this->assertSame( 0, $result['network_calls'] );
	}
}
