<?php
use PHPUnit\Framework\TestCase;

/** Run the real WooCommerce method in isolated, network-free PHP fixtures. */
final class InstantCheckoutShippingRuntimeTest extends TestCase {
	private function run_fixture(): array {
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/instant-checkout-shipping-runtime.php' ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
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

	public function test_ineligible_destinations_cod_and_insurance_never_request_quotes(): void {
		$result = $this->run_fixture();
		foreach ( array( 'missing' => 'destination_required', 'cod' => 'cod_not_supported', 'insurance' => 'insurance_not_supported' ) as $key => $code ) {
			$this->assertSame( 0, $result[ $key ]['calls'] );
			$this->assertSame( $code, array_values( $result[ $key ]['status'] )[0]['code'] );
			$this->assertFalse( array_values( $result[ $key ]['status'] )[0]['eligible'] );
		}
		$this->assertSame( 0, $result['explicit_calls'] );
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
		$this->assertSame( 12345, $rate['cost'] );
		$this->assertSame( array( 'kiriof_delivery_type', 'kiriof_instant_quote_token', 'kiriof_instant_courier', 'kiriof_instant_service', 'kiriof_instant_vehicle', 'kiriof_instant_quote_expires' ), array_keys( $rate['meta_data'] ) );
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
}
