<?php
use PHPUnit\Framework\TestCase;

/** Current Express behavior, not a claim that provider schema requirements are verified. */
final class ExpressPickupPayloadRuntimeTest extends TestCase {
	private function runFixture( array $input = array() ): array {
		exec( escapeshellarg( PHP_BINARY ) . ' -d error_reporting=' . ( E_ALL & ~E_DEPRECATED ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/express-pickup-payload-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
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
}
