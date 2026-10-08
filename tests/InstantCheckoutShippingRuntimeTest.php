<?php
use PHPUnit\Framework\TestCase;

/** Run the real WooCommerce method in isolated, network-free PHP fixtures. */
final class InstantCheckoutShippingRuntimeTest extends TestCase {
	private function run_fixture(): array {
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/instant-checkout-shipping-runtime.php' ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
		$this->assertSame( array(), $result['logs'] );
		return $result;
	}

    public function test_coupon_pricing_uses_raw_delivery_only_and_native_id_session_metadata(): void {
        $r = $this->run_fixture();
        foreach (['fixed' => 49000, 'percent' => 40500, 'free' => 0, 'removed' => 54000] as $kind => $cost) {
            $rate = $r['coupons'][$kind]['rates'][0];
            $meta = $r['coupons'][$kind]['meta'];
            $this->assertEquals($cost, $rate['cost']);
            $this->assertEquals(54000, $meta['original_cost']);
            $this->assertEquals(54000 - $cost, $meta['discount_amount']);
            $this->assertSame('opaque_token', $rate['meta_data']['kiriof_instant_quote_token']);
            $this->assertArrayNotHasKey('kiriof_shipping_coupon_discount_amount', $rate['meta_data']);
        }
    }

	public function test_registration_is_safe_without_woocommerce_and_instances_have_distinct_ids(): void {
		$result = $this->run_fixture();
		$this->assertSame( array( 'existing' => 'Existing' ), $result['before'] );
		$this->assertSame( 'Kiriof_Instant_Shipping_Method_Controller', $result['registered']['kiriminaja-instant'] );
		$this->assertSame( array( true, false, 0 ), $result['available'] );
		$this->assertSame( 'yes', $result['fields']['enabled']['default'] );
		$this->assertCount( 1, $result['rates_one'] );
		$this->assertCount( 1, $result['rates_two'] );
		$this->assertSame( 'kiriminaja-instant:11:gosend:instant', $result['rates_one'][0]['id'] );
		$this->assertSame( 'kiriminaja-instant:22:gosend:instant', $result['rates_two'][0]['id'] );
	}

	public function test_ineligible_destinations_and_cod_skip_quotes_but_insurance_settings_are_ignored(): void {
		$result = $this->run_fixture();
		foreach ( array( 'missing' => 'destination_required', 'cod' => 'cod_not_supported' ) as $key => $code ) {
			$this->assertSame( 0, $result[ $key ]['calls'] );
			$this->assertSame( $code, array_values( $result[ $key ]['status'] )[0]['code'] );
			$this->assertFalse( array_values( $result[ $key ]['status'] )[0]['eligible'] );
		}
		$this->assertSame( 1, $result['insurance']['calls'] );
		$this->assertFalse( $result['insurance']['request']['insurance'] );
		$this->assertCount( 1, $result['insurance']['rates'] );
		$this->assertSame( 2, $result['explicit_calls']['calls'] );
		$this->assertFalse( $result['explicit_calls']['request']['insurance'] );
		$this->assertCount( 1, $result['explicit_calls']['rates'] );
		$this->assertSame( 'no', $result['setting'] );
	}

	public function test_quote_arguments_preserve_actual_package_origin_and_native_selection(): void {
		$result = $this->run_fixture();
		$call = $result['calls'][0];
		$this->assertSame( 'bacs', $call['payment'] );
		$this->assertFalse( $call['insurance'] );
		$this->assertSame( 2, $call['destination']['version'] );
		$this->assertSame( 'Actual package', $call['package']['destination']['first_name'] );
		$this->assertSame( 'Actual city', $call['package']['destination']['city'] );
		$this->assertSame( '081234', $call['package']['destination']['phone'] );
		$this->assertSame( array( 'origin_latitude' => '-6.2', 'origin_longitude' => '106.8' ), $call['package']['origin'] );
		$this->assertSame( array( 'existing:3' ), $result['chosen'] );
	}

	public function test_safe_rate_metadata_diagnostics_and_fixed_failure_messages(): void {
		$result = $this->run_fixture();
		$rate = $result['rates_one'][0];
		$this->assertSame( 'GoSend Instant', $rate['label'] );
		$this->assertSame( 54000, $rate['cost'] );
		$this->assertSame( array( 'kiriof_delivery_type', 'kiriof_instant_quote_token', 'kiriof_instant_courier', 'kiriof_instant_service', 'kiriof_instant_vehicle', 'kiriof_instant_quote_expires' ), array_keys( $rate['meta_data'] ) );
		$this->assertSame( '1-2 hours', $rate['delivery_time'] );
		$this->assertSame( 'motor', $rate['meta_data']['kiriof_instant_vehicle'] );
		$this->assertCount( 2, $result['success_status'] );
		foreach ( $result['success_status'] as $status ) {
			$this->assertSame( 'available', $status['code'] );
			$this->assertTrue( $status['eligible'] );
			$this->assertSame( 1, $status['count'] );
			$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/D', $status['fingerprint'] );
		}
		$this->assertSame( 'quote_failed', array_values( $result['failure_status'] )[0]['code'] );
		$public = json_encode( array( $rate, $result['success_status'], $result['failure_status'] ) );
		foreach ( array( 'Secret street', 'secret address', 'secret phone', 'Private API' ) as $private ) {
			$this->assertStringNotContainsString( $private, $public );
		}
	}
	public function test_zone_registration_instance_settings_and_disabled_instances_are_local(): void {
		$result = $this->run_fixture();
		$this->assertTrue( $result['instance_settings_initialized'] );
		$this->assertSame( array( 'id' => 33, 'enabled' => 'yes' ), $result['zone'] );
		$this->assertSame( array( 'available' => false, 'calls' => 0, 'rates' => array() ), $result['disabled'] );
	}

	public function test_uppercase_service_keeps_identity_and_native_delivery_time_before_selection(): void {
		$result = $this->run_fixture();
		$this->assertCount( 2, $result['uppercase_rates'] );
		$rate = $result['uppercase_rates'][1];
		$this->assertSame( 'kiriminaja-instant:55:gosend:GO-INSTANT', $rate['id'] );
		$this->assertSame( 'GO-INSTANT', $rate['meta_data']['kiriof_instant_service'] );
		$this->assertSame( 'kiriminaja-instant', $rate['method_id'] );
		$this->assertSame( 55, $rate['instance_id'] );
		$this->assertSame( '1-2 hours', $rate['delivery_time'] );
		$this->assertSame( 'GoSend Instant', $rate['label'] );
		$this->assertSame( array( 'existing:3' ), $result['chosen'] );
	}

	public function test_invalid_totals_and_quote_fields_are_rejected_and_zero_total_is_valid(): void {
		$result = $this->run_fixture();
		$this->assertCount( 20, $result['invalid'] );
		foreach ( $result['invalid'] as $name => $case ) {
			$this->assertSame( array(), $case['rates'], $name );
			$this->assertSame( 'unavailable', $case['status']['code'], $name );
			$this->assertFalse( $case['status']['eligible'], $name );
			$this->assertSame( 0, $case['status']['count'], $name );
		}
		$this->assertCount( 1, $result['zero_rates'] );
		$this->assertSame( 0, $result['zero_rates'][0]['cost'] );
		$this->assertSame( 'GoSend Instant', $result['zero_rates'][0]['label'] );
	}

	public function test_canonical_multi_package_currency_and_virtual_guards_skip_api_without_selection_changes(): void {
		$result = $this->run_fixture();
		$this->assertSame( array( 'tax_status' => 'none', 'taxes' => false ), $result['money'] );
		foreach ( array( 'multi' => 'packages_invalid', 'currency' => 'currency_unsupported' ) as $scenario => $code ) {
			$guard = $result['guards'][ $scenario ];
			$this->assertFalse( $guard['available'] );
			$this->assertSame( 0, $guard['calls'] );
			$this->assertSame( array(), $guard['rates'] );
			$status = $guard['status']['88:' . hash( 'sha256', json_encode( array( 'item' ) ) )];
			$this->assertSame( $code, $status['code'] );
			$this->assertSame( 0, $status['expires'] );
		}
		$this->assertTrue( $result['guards']['virtual']['status_unchanged'] );
		$this->assertSame( 0, $result['guards']['virtual']['calls'] );
		$this->assertSame( array( 'existing:3' ), $result['chosen'] );
		$expires = array_column( array_column( $result['earliest']['rates'], 'meta_data' ), 'kiriof_instant_quote_expires' );
		$this->assertSame( min( $expires ), $result['earliest']['status']['expires'] );
		$this->assertSame( 2, $result['earliest']['status']['count'] );
	}

	public function test_eligibility_reasons_use_only_fixed_safe_messages_and_codes(): void {
		$result = $this->run_fixture();
		foreach ( $result['reasons'] as $reason => $status ) {
			$this->assertSame( 'secret_reason' === $reason ? 'unavailable' : $reason, $status['code'] );
			$this->assertFalse( $status['eligible'] );
			$this->assertSame( 0, $status['expires'] );
			$this->assertStringNotContainsString( 'Private', $status['message'] );
		}
		$this->assertStringContainsString( '40 km', $result['reasons']['outside_instant_radius']['message'] );
	}

}
