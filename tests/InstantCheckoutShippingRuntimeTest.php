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
            $this->assertSame( array(
                'meta_data kiriof_instant_quote_token' => 'opaque_token',
                'excludes kiriof_shipping_coupon_discount_amount' => false,
            ), array(
                'meta_data kiriof_instant_quote_token' => $rate['meta_data']['kiriof_instant_quote_token'],
                'excludes kiriof_shipping_coupon_discount_amount' => array_key_exists( 'kiriof_shipping_coupon_discount_amount', $rate['meta_data'] ),
            ) );
        }
    }

	public function test_registration_is_safe_without_woocommerce_and_instances_have_distinct_ids(): void {
		$result = $this->run_fixture();
		$this->assertSame( array(
			'before' => array( 'existing' => 'Existing' ),
			'registered kiriminaja-instant' => 'Kiriof_Instant_Shipping_Method_Controller',
			'available' => array( true, false, 0 ),
			'fields enabled default' => 'yes',
			'rates_one count' => 1,
			'rates_two count' => 1,
			'rates_one id' => 'kiriminaja-instant:11:gosend:instant',
			'rates_two id' => 'kiriminaja-instant:22:gosend:instant',
		), array(
			'before' => $result['before'],
			'registered kiriminaja-instant' => $result['registered']['kiriminaja-instant'],
			'available' => $result['available'],
			'fields enabled default' => $result['fields']['enabled']['default'],
			'rates_one count' => count( $result['rates_one'] ),
			'rates_two count' => count( $result['rates_two'] ),
			'rates_one id' => $result['rates_one'][0]['id'],
			'rates_two id' => $result['rates_two'][0]['id'],
		) );
	}

	public function test_ineligible_destinations_and_cod_skip_quotes_but_insurance_settings_are_ignored(): void {
		$result = $this->run_fixture();
		foreach ( array( 'missing' => 'destination_required', 'cod' => 'cod_not_supported' ) as $key => $code ) {
			$this->assertSame( array(
				'calls' => 0,
				'status code' => $code,
				'status eligible' => false,
			), array(
				'calls' => $result[ $key ]['calls'],
				'status code' => array_values( $result[ $key ]['status'] )[0]['code'],
				'status eligible' => array_values( $result[ $key ]['status'] )[0]['eligible'],
			) );
		}
		$this->assertSame( array(
			'insurance calls' => 1,
			'insurance request insurance' => false,
			'insurance rates count' => 1,
			'explicit_calls calls' => 2,
			'explicit_calls request insurance' => false,
			'explicit_calls rates count' => 1,
			'setting' => 'no',
		), array(
			'insurance calls' => $result['insurance']['calls'],
			'insurance request insurance' => $result['insurance']['request']['insurance'],
			'insurance rates count' => count( $result['insurance']['rates'] ),
			'explicit_calls calls' => $result['explicit_calls']['calls'],
			'explicit_calls request insurance' => $result['explicit_calls']['request']['insurance'],
			'explicit_calls rates count' => count( $result['explicit_calls']['rates'] ),
			'setting' => $result['setting'],
		) );
	}

	public function test_quote_arguments_preserve_actual_package_origin_and_native_selection(): void {
		$result = $this->run_fixture();
		$call = $result['calls'][0];
		$this->assertSame( array(
			'payment' => 'bacs',
			'insurance' => false,
			'destination version' => 2,
			'package destination first_name' => 'Actual package',
			'package destination city' => 'Actual city',
			'package destination phone' => '081234',
			'package origin' => array( 'origin_latitude' => '-6.2', 'origin_longitude' => '106.8' ),
			'chosen' => array( 'existing:3' ),
		), array(
			'payment' => $call['payment'],
			'insurance' => $call['insurance'],
			'destination version' => $call['destination']['version'],
			'package destination first_name' => $call['package']['destination']['first_name'],
			'package destination city' => $call['package']['destination']['city'],
			'package destination phone' => $call['package']['destination']['phone'],
			'package origin' => $call['package']['origin'],
			'chosen' => $result['chosen'],
		) );
	}

	public function test_safe_rate_metadata_diagnostics_and_fixed_failure_messages(): void {
		$result = $this->run_fixture();
		$rate = $result['rates_one'][0];
		$this->assertSame( array(
			'label' => 'GoSend Instant',
			'cost' => 54000,
			'meta_data' => array( 'kiriof_delivery_type', 'kiriof_instant_quote_token', 'kiriof_instant_courier', 'kiriof_instant_service', 'kiriof_instant_vehicle', 'kiriof_instant_quote_expires' ),
			'delivery_time' => '1-2 hours',
			'meta_data kiriof_instant_vehicle' => 'motor',
			'success_status count' => 2,
		), array(
			'label' => $rate['label'],
			'cost' => $rate['cost'],
			'meta_data' => array_keys( $rate['meta_data'] ),
			'delivery_time' => $rate['delivery_time'],
			'meta_data kiriof_instant_vehicle' => $rate['meta_data']['kiriof_instant_vehicle'],
			'success_status count' => count( $result['success_status'] ),
		) );
		foreach ( $result['success_status'] as $status ) {
			$this->assertSame( array(
				'code' => 'available',
				'eligible' => true,
				'count' => 1,
				'fingerprint' => 1,
			), array(
				'code' => $status['code'],
				'eligible' => $status['eligible'],
				'count' => $status['count'],
				'fingerprint' => preg_match( '/^[a-f0-9]{64}$/D', $status['fingerprint'] ),
			) );
		}
		$this->assertSame( 'quote_failed', array_values( $result['failure_status'] )[0]['code'] );
		$public = json_encode( array( $rate, $result['success_status'], $result['failure_status'] ) );
		foreach ( array( 'Secret street', 'secret address', 'secret phone', 'Private API' ) as $private ) {
			$this->assertStringNotContainsString( $private, $public );
		}
	}
	public function test_zone_registration_instance_settings_and_disabled_instances_are_local(): void {
		$result = $this->run_fixture();
		$this->assertSame( array(
			'instance_settings_initialized' => true,
			'zone' => array( 'id' => 33, 'enabled' => 'yes' ),
			'disabled' => array( 'available' => false, 'calls' => 0, 'rates' => array() ),
		), array(
			'instance_settings_initialized' => $result['instance_settings_initialized'],
			'zone' => $result['zone'],
			'disabled' => $result['disabled'],
		) );
	}

	public function test_uppercase_service_keeps_identity_and_native_delivery_time_before_selection(): void {
		$result = $this->run_fixture();
		$this->assertCount( 2, $result['uppercase_rates'] );
		$rate = $result['uppercase_rates'][1];
		$this->assertSame( array(
			'id' => 'kiriminaja-instant:55:gosend:GO-INSTANT',
			'meta_data kiriof_instant_service' => 'GO-INSTANT',
			'method_id' => 'kiriminaja-instant',
			'instance_id' => 55,
			'delivery_time' => '1-2 hours',
			'label' => 'GoSend Instant',
			'chosen' => array( 'existing:3' ),
		), array(
			'id' => $rate['id'],
			'meta_data kiriof_instant_service' => $rate['meta_data']['kiriof_instant_service'],
			'method_id' => $rate['method_id'],
			'instance_id' => $rate['instance_id'],
			'delivery_time' => $rate['delivery_time'],
			'label' => $rate['label'],
			'chosen' => $result['chosen'],
		) );
	}

	public function test_invalid_totals_and_quote_fields_are_rejected_and_zero_total_is_valid(): void {
		$result = $this->run_fixture();
		$this->assertCount( 20, $result['invalid'] );
		$expected = $actual = array();
		foreach ( $result['invalid'] as $name => $case ) {
			$expected[$name] = array(
				'rates' => array(),
				'status code' => 'unavailable',
				'status eligible' => false,
				'status count' => 0,
			);
			$actual[$name] = array(
				'rates' => $case['rates'],
				'status code' => $case['status']['code'],
				'status eligible' => $case['status']['eligible'],
				'status count' => $case['status']['count'],
			);
		}
		$this->assertSame( $expected, $actual );
		$this->assertSame( array(
			'zero_rates count' => 1,
			'zero_rates cost' => 0,
			'zero_rates label' => 'GoSend Instant',
		), array(
			'zero_rates count' => count( $result['zero_rates'] ),
			'zero_rates cost' => $result['zero_rates'][0]['cost'],
			'zero_rates label' => $result['zero_rates'][0]['label'],
		) );
	}

	public function test_canonical_multi_package_currency_and_virtual_guards_skip_api_without_selection_changes(): void {
		$result = $this->run_fixture();
		$this->assertSame( array( 'tax_status' => 'none', 'taxes' => false ), $result['money'] );
		foreach ( array( 'multi' => 'packages_invalid', 'currency' => 'currency_unsupported' ) as $scenario => $code ) {
			$guard = $result['guards'][ $scenario ];
			$this->assertSame( array(
				'available' => false,
				'calls' => 0,
				'rates' => array(),
			), array(
				'available' => $guard['available'],
				'calls' => $guard['calls'],
				'rates' => $guard['rates'],
			) );
			$status = $guard['status']['88:' . hash( 'sha256', json_encode( array( 'item' ) ) )];
			$this->assertSame( array(
				'code' => $code,
				'expires' => 0,
			), array(
				'code' => $status['code'],
				'expires' => $status['expires'],
			) );
		}
		$this->assertSame( array(
			'guards virtual status_unchanged' => true,
			'guards virtual calls' => 0,
			'chosen' => array( 'existing:3' ),
		), array(
			'guards virtual status_unchanged' => $result['guards']['virtual']['status_unchanged'],
			'guards virtual calls' => $result['guards']['virtual']['calls'],
			'chosen' => $result['chosen'],
		) );
		$expires = array_column( array_column( $result['earliest']['rates'], 'meta_data' ), 'kiriof_instant_quote_expires' );
		$this->assertSame( array(
			'earliest status expires' => min( $expires ),
			'earliest status count' => 2,
		), array(
			'earliest status expires' => $result['earliest']['status']['expires'],
			'earliest status count' => $result['earliest']['status']['count'],
		) );
	}

	public function test_eligibility_reasons_use_only_fixed_safe_messages_and_codes(): void {
		$result = $this->run_fixture();
		foreach ( $result['reasons'] as $reason => $status ) {
			$this->assertSame( array(
				'code' => 'secret_reason' === $reason ? 'unavailable' : $reason,
				'eligible' => false,
				'expires' => 0,
				'excludes Private' => false,
			), array(
				'code' => $status['code'],
				'eligible' => $status['eligible'],
				'expires' => $status['expires'],
				'excludes Private' => str_contains( $status['message'], 'Private' ),
			) );
		}
		$this->assertStringContainsString( '40 km', $result['reasons']['outside_instant_radius']['message'] );
	}

}
