<?php
use PHPUnit\Framework\TestCase;

/** Current Express behavior, not a claim that provider schema requirements are verified. */
final class ExpressPickupPayloadRuntimeTest extends TestCase {
	private function runFixture( array $input = array() ): array {
		exec( escapeshellarg( PHP_BINARY ) . ' -d error_reporting=' . ( E_ALL & ~E_DEPRECATED ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/express-pickup-payload-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_failed_pickup_logs_wire_payload_with_integer_quantity_and_no_pin(): void {
		foreach ( array( 'credit', 'qris' ) as $payment ) {
			$result = $this->runFixture( array( 'payment' => $payment, 'http_status' => 500, 'item_quantities' => array( '2', '3' ) ) );
			$failures = array_values( array_filter( $result['logs'], static function ( $log ) { return 'Request pickup API request failed.' === $log[1]; } ) );
			$this->assertCount( 1, $failures );
			$context = $failures[0][2];
			$this->assertSame( 'api/mitra/v6.2/request_pickup', $context['endpoint'] );
			$logged = json_decode( $context['request_payload_json'], true, 512, JSON_THROW_ON_ERROR );
			$this->assertSame( 5, $logged['packages'][0]['qty'] );
			$this->assertSame( 'credit' === $payment ? 'credit' : 'cash', $logged['payment_method'] );
			$this->assertStringNotContainsString( '012345', json_encode( $result['logs'] ) );
			$this->assertStringNotContainsString( 'fixture-express-token', json_encode( $result['logs'] ) );
			if ( 'credit' === $payment ) { $this->assertSame( '[redacted]', $logged['pin'] ); }
			else { $this->assertArrayNotHasKey( 'pin', $logged ); }
			foreach ( array( 'Private', 'Buyer', 'private-address', 'Original Store', 'private-origin', '08123456789', 'Purchased variation', 'example.test' ) as $personal ) {
				$this->assertStringNotContainsString( $personal, $context['request_payload_json'] );
			}
			$this->assertSame( '[redacted]', $logged['packages'][0]['destination_phone'] );
			$this->assertSame( '[redacted]', $logged['packages'][0]['destination_address'] );
			$this->assertSame( $result['requests'][0]['payload']['packages'][0]['weight'], $logged['packages'][0]['weight'] );
			$this->assertSame( $result['requests'][0]['payload']['packages'][0]['service'], $logged['packages'][0]['service'] );
		}
	}

	public function test_successful_booking_does_not_log_or_retain_payload(): void {
		$result = $this->runFixture();
		$this->assertSame( '', $result['diagnostic_payload_json'] );
		foreach ( $result['logs'] as $log ) {
			$this->assertArrayNotHasKey( 'request_payload_json', $log[2] );
		}
	}

	public function test_express_package_quantity_sums_order_item_quantities(): void {
		foreach ( array( array( 4 ), array( 2, 3 ), array( '2', '3' ), array() ) as $quantities ) {
			$result = $this->runFixture( array( 'item_quantities' => $quantities ) );
			$this->assertSame( 200, $result['status'] );
			$this->assertSame( max( 1, array_sum( $quantities ) ), $result['requests'][0]['payload']['packages'][0]['qty'] );
		}
	}

	public function test_express_packages_send_default_quantity_on_wire(): void {
		$cases = array(
			'credit'      => array(),
			'qris'        => array( 'payment' => 'qris', 'pin' => '' ),
			'top'         => array( 'top' => true ),
			'cod'         => array( 'payment' => '', 'cod' => true ),
			'empty items' => array( 'empty_items' => true ),
		);
		foreach ( $cases as $label => $input ) {
			$result = $this->runFixture( $input );
			$this->assertSame( 200, $result['status'], $label );
			$this->assertCount( 1, $result['requests'], $label );
			$payload = $result['requests'][0]['payload'];
			$this->assertArrayNotHasKey( 'qty', $payload, $label );
			$this->assertNotEmpty( $payload['packages'], $label );
			foreach ( $payload['packages'] as $package ) {
				$this->assertArrayHasKey( 'qty', $package, $label );
				$this->assertSame( 1, $package['qty'], $label );
			}
		}
	}

	public function test_credit_leading_zero_pin_survives_real_service_repository_and_wire(): void {
		$result = $this->runFixture();
		$this->assertSame( 200, $result['status'] );
		$this->assertCount( 1, $result['requests'] );
		$request = $result['requests'][0];
		$this->assertSame( 'POST', $request['method'] );
		$this->assertSame( 'https://express-api.invalid/api/mitra/v6.2/request_pickup', $request['url'] );
		$this->assertSame( 'credit', $request['payload']['payment_method'] );
		$this->assertTrue( $request['pin_present'] );
		$this->assertTrue( $request['pin_unchanged'] );
		$this->assertSame( 'credit', $result['created']['method'] );
		$this->assertSame( 'paid', $result['created']['status'] );
		$this->assertCount( 1, $result['writes'] );
		$this->assertSame( 'request_pickup', $result['writes'][0]['changes']['status'] );
		$this->assertSame( 'PICKUP-1', $result['writes'][0]['changes']['pickup_number'] );
	}

	public function test_existing_rows_do_not_require_coordinates_or_item_product_details(): void {
		$result = $this->runFixture( array( 'empty_items' => true ) );
		$this->assertSame( 200, $result['status'] );
		$payload = $result['requests'][0]['payload'];
		$this->assertArrayNotHasKey( 'latitude', $payload );
		$this->assertArrayNotHasKey( 'longitude', $payload );
		$package = $payload['packages'][0];
		$this->assertArrayNotHasKey( 'destination_latitude', $package );
		$this->assertArrayNotHasKey( 'destination_longitude', $package );
		$this->assertArrayNotHasKey( 'items', $package );
		$this->assertSame( '', $package['item_name'] );
		$this->assertSame( 100, $package['weight'] );
	}

	public function test_optional_dimensions_keep_existing_min_amount_behavior(): void {
		$result = $this->runFixture( array( 'row' => array( 'weight' => 0, 'width' => null, 'height' => 0, 'length' => '' ) ) );
		$this->assertSame( 200, $result['status'] );
		foreach ( array( 'weight', 'width', 'height', 'length' ) as $key ) {
			$this->assertSame( 1, $result['requests'][0]['payload']['packages'][0][ $key ] );
		}
	}

	public function test_qris_maps_to_cash_on_wire_but_stays_local_qris(): void {
		foreach ( array( 'paid', 'unpaid' ) as $remote_status ) {
			$result = $this->runFixture( array( 'payment' => 'qris', 'pin' => '', 'remote_status' => $remote_status ) );
			$this->assertSame( 200, $result['status'] );
			$this->assertSame( 'cash', $result['requests'][0]['payload']['payment_method'] );
			$this->assertFalse( $result['requests'][0]['pin_present'] );
			$this->assertSame( 'qris', $result['created']['method'] );
			$this->assertSame( 'unpaid', $result['created']['status'] );
			$this->assertTrue( $result['data']['open_payment'] );
		}
	}

	public function test_top_omits_method_and_pin_even_when_user_selects_credit(): void {
		foreach ( array( '', '012345' ) as $pin ) {
			$result = $this->runFixture( array( 'top' => true, 'payment' => 'credit', 'pin' => $pin ) );
			$this->assertSame( 200, $result['status'] );
			$this->assertArrayNotHasKey( 'payment_method', $result['requests'][0]['payload'] );
			$this->assertFalse( $result['requests'][0]['pin_present'] );
			$this->assertSame( 'TOP', $result['created']['method'] );
			$this->assertSame( 'paid', $result['created']['status'] );
		}
	}

	public function test_cod_wire_amount_subtracts_shipping_discount_from_reported_order(): void {
		$result = $this->runFixture( array(
			'payment' => '',
			'cod' => true,
			'row' => array(
				'transaction_value' => 200000,
				'shipping_cost' => 7700,
				'discount_amount' => 385,
				'insurance_cost' => 1200,
				'cod_fee' => 6945,
			),
		) );
		$this->assertSame( 200, $result['status'] );
		$this->assertCount( 1, $result['requests'] );
		$request = $result['requests'][0];
		$this->assertSame( 'https://express-api.invalid/api/mitra/v6.2/request_pickup', $request['url'] );
		$package = $request['payload']['packages'][0];
		$this->assertSame( 215460, $package['cod'] );
		$this->assertNotSame( 215845, $package['cod'] );
		$this->assertSame( 7700, $package['shipping_cost'] );
		$this->assertSame( 385, $package['discount_amount'] );
	}

	public function test_cod_omits_method_and_pin_and_creates_paid_cod_locally(): void {
		$result = $this->runFixture( array( 'payment' => '', 'cod' => true ) );
		$this->assertSame( 200, $result['status'] );
		$this->assertArrayNotHasKey( 'payment_method', $result['requests'][0]['payload'] );
		$this->assertFalse( $result['requests'][0]['pin_present'] );
		$this->assertSame( 21346, $result['requests'][0]['payload']['packages'][0]['cod'] );
		$this->assertSame( 'cod', $result['created']['method'] );
		$this->assertSame( 'paid', $result['created']['status'] );
	}

	private function assertNoPersistence( array $result ): void {
		$this->assertNotSame( 200, $result['status'] );
		$this->assertSame( array(), $result['writes'] );
		$this->assertNull( $result['created'] );
	}

	public function test_disabled_credit_and_invalid_pins_fail_before_transport_or_writes(): void {
		$cases = array( array( 'credit_enabled' => false ) );
		foreach ( array( '', '12345', '1234567', 'abcdef', '01234 ', "012345\n" ) as $pin ) { $cases[] = array( 'pin' => $pin ); }
		foreach ( $cases as $input ) {
			$result = $this->runFixture( $input );
			$this->assertNoPersistence( $result );
			$this->assertSame( array(), $result['requests'] );
		}
	}

	public function test_provider_failures_do_not_write_local_state_or_retry(): void {
		foreach ( array( array( 'response' => array( 'status' => false, 'results' => array( 'error' => 'PIN_INVALID' ) ) ), array( 'http_status' => 500 ), array( 'exception' => true ) ) as $input ) {
			$result = $this->runFixture( $input );
			$this->assertNoPersistence( $result );
			$this->assertCount( 1, $result['requests'] );
		}
	}

	public function test_instant_preflight_remains_before_transport_or_writes(): void {
		$result = $this->runFixture( array( 'row' => array( 'delivery_type' => 'instant' ) ) );
		$this->assertNoPersistence( $result );
		$this->assertSame( array(), $result['requests'] );
	}

	public function test_invalid_recipient_phone_blocks_pickup_before_api_or_writes(): void {
		foreach ( array( '', '0812letters3456', '+628123456789012345', '+6285451111000000', '085451111000000', '1234567' ) as $phone ) {
			$result = $this->runFixture( array( 'recipient_phone' => $phone ) );
			$this->assertNoPersistence( $result );
			$this->assertSame( array(), $result['requests'] );
			$this->assertSame( 'invalid_recipient_phone', $result['data']['error_code'] );
		}
		$this->assertSame( 200, $this->runFixture( array( 'recipient_phone' => '+62 (812) 3456-7890' ) )['status'] );
		$this->assertSame( 200, $this->runFixture( array( 'recipient_phone' => '+6285234534567' ) )['status'] );
	}

	public function test_one_invalid_recipient_rejects_the_complete_selected_batch(): void {
		$result = $this->runFixture( array(
			'additional_rows' => array( array( 'order_id' => 'ORDER-2', 'wp_wc_order_stat_order_id' => 2 ) ),
			'addresses' => array( 2 => array( 'shipping' => array( 'phone' => 'bad' ), 'billing' => array( 'phone' => '08123456789' ) ) ),
		) );
		$this->assertNoPersistence( $result );
		$this->assertSame( array(), $result['requests'] );
		$this->assertSame( 'invalid_recipient_phone', $result['data']['error_code'] );
	}

	public function test_corrected_live_billing_phone_overrides_legacy_invalid_snapshot(): void {
		$result = $this->runFixture( array(
			'row' => array( 'shipping_info' => '{"_billing_phone":"bad"}' ),
			'addresses' => array( 1 => array( 'shipping' => array(), 'billing' => array( 'phone' => '+62 (812) 3456-7890' ) ) ),
		) );
		$this->assertSame( 200, $result['status'] );
		$this->assertSame( '+62 (812) 3456-7890', $result['requests'][0]['payload']['packages'][0]['destination_phone'] );
	}
}
