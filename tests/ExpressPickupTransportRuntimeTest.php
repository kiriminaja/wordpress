<?php

use PHPUnit\Framework\TestCase;

/** Actual HTTP contract through the repository and installed SDK, entirely offline. */
final class ExpressPickupTransportRuntimeTest extends TestCase {
	private function payload(): array {
		return array(
			'name' => 'Offline sender',
			'address' => 'Offline origin',
			'phone' => '080000000001',
			'kelurahan_id' => 31483,
			'zipcode' => '55791',
			'schedule' => '2030-01-02 11:00:00',
			'platform_name' => 'wordpress',
			'dropoff' => false,
			'packages' => array( array( 'order_id' => 'ORDER-OFFLINE', 'service' => 'jne', 'service_type' => 'REG', 'weight' => 1000, 'cod' => 0 ) ),
		);
	}

	private function run_fixture( array $payload, array $options = array() ): array {
		$input = base64_encode( json_encode( array_merge( $options, array( 'payload' => $payload ) ), JSON_THROW_ON_ERROR ) );
		$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/express-pickup-transport-runtime.php' ) . ' ' . escapeshellarg( $input );
		exec( $command . ' 2>&1', $output, $status );
		// Do not expose fixture diagnostics or request secrets on process failure.
		$this->assertSame( 0, $status, 'Offline transport fixture must exit successfully.' );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	private function assert_request_contract( array $fixture ): array {
		$this->assertCount( 1, $fixture['requests'], 'Pickup must never fall back or retry.' );
		$request = $fixture['requests'][0];
		$this->assertSame( 'POST', $request['method'] );
		$this->assertSame( 'https://express-api.invalid/api/mitra/v6.2/request_pickup', $request['url'] );
		$this->assertSame( '', $request['query'] );
		$this->assertSame( 'wordpress', $request['user_agent'] );
		$this->assertSame( 'application/json', $request['content_type'] );
		$this->assertSame( 'application/json', $request['accept'] );
		$this->assertTrue( $request['authorized'] );
		$this->assertSame( '', $request['cookie'] );
		$this->assertTrue( $fixture['payload_unchanged'] );
		$this->assertTrue( $fixture['output_empty'] );
		$this->assertFalse( $fixture['logs_contain_pin'], 'Request PIN must not enter logs.' );
		return $request;
	}

	public function test_credit_preserves_string_method_and_six_digit_pin_over_v62_http(): void {
		$payload = array_merge( $this->payload(), array( 'payment_method' => 'credit', 'pin' => '012345' ) );
		$fixture = $this->run_fixture( $payload );
		$request = $this->assert_request_contract( $fixture );
		$this->assertTrue( $request['pin_present'] );
		$this->assertTrue( $request['pin_unchanged'], 'Credit PIN must remain a six-digit string including its leading zero.' );
		unset( $payload['pin'] );
		$this->assertSame( $payload, $request['payload'] );
		$this->assertTrue( $fixture['result']['status'] );
		$this->assertTrue( $fixture['result_data_is_object'] );
		$this->assertSame( 'PICKUP-OFFLINE', $fixture['result']['data']['pickup_number'] );
	}

	public function test_qris_maps_to_remote_cash_and_cash_strips_unneeded_pin(): void {
		foreach ( array( 'qris', 'cash' ) as $method ) {
			$payload = array_merge( $this->payload(), array( 'payment_method' => $method, 'pin' => '012345' ) );
			$fixture = $this->run_fixture( $payload );
			$request = $this->assert_request_contract( $fixture );
			unset( $payload['pin'] );
			$payload['payment_method'] = 'cash';
			$this->assertSame( $payload, $request['payload'] );
			$this->assertFalse( $request['pin_present'] );
			$this->assertTrue( $fixture['result']['status'] );
		}
	}

	public function test_omitted_payment_method_for_top_and_cod_stays_omitted_and_strips_pin(): void {
		foreach ( array( 0, 150000 ) as $cod ) {
			$payload = $this->payload();
			$payload['packages'][0]['cod'] = $cod;
			$payload['pin'] = '012345';
			$fixture = $this->run_fixture( $payload );
			$request = $this->assert_request_contract( $fixture );
			unset( $payload['pin'] );
			$this->assertSame( $payload, $request['payload'] );
			$this->assertArrayNotHasKey( 'payment_method', $request['payload'] );
			$this->assertFalse( $request['pin_present'] );
			$this->assertTrue( $fixture['result']['status'] );
		}
	}

	public function test_invalid_payment_method_is_rejected_before_http(): void {
        foreach ( array( 'bank_transfer', 1, null, array( 'credit' ) ) as $method ) {
			$fixture = $this->run_fixture( array_merge( $this->payload(), array( 'payment_method' => $method, 'pin' => '012345' ) ) );
			$this->assertFalse( $fixture['result']['status'] );
			$this->assertSame( array(), $fixture['requests'] );
			$this->assertFalse( $fixture['logs_contain_pin'] );
			$this->assertTrue( $fixture['output_empty'] );
		}
	}

	public function test_transport_and_body_failures_never_retry_or_fall_back(): void {
		foreach ( array(
			array( 'exception' => true ),
			array( 'http_status' => 422, 'body' => '{"status":false,"message":"Rejected"}' ),
			array( 'http_status' => 500, 'body' => 'Unavailable' ),
			array( 'body' => 'not json' ),
			array( 'body' => 'null' ),
			array( 'body' => '"scalar"' ),
			array( 'body' => '{"status":false,"message":"Rejected"}' ),
		) as $options ) {
			$fixture = $this->run_fixture( array_merge( $this->payload(), array( 'payment_method' => 'credit', 'pin' => '012345' ) ), $options );
			$this->assert_request_contract( $fixture );
			$this->assertFalse( $fixture['result']['status'] );
		}
	}

    public function test_existing_v2_entry_point_uses_the_same_payment_contract(): void {
		$payload = array_merge( $this->payload(), array( 'payment_method' => 'qris', 'pin' => '012345' ) );
		$fixture = $this->run_fixture( $payload, array( 'v2' => true ) );
		$request = $this->assert_request_contract( $fixture );
        unset( $payload['pin'] );
        $payload['payment_method'] = 'cash';
        $this->assertSame( $payload, $request['payload'] );
        $this->assertFalse( $request['pin_present'] );
        $this->assertTrue( $fixture['result']['status'] );
    }

    public function test_echoed_pin_and_response_body_never_escape_on_rejection(): void {
        foreach ( array( 'PIN_INVALID', 'PIN_MAX_ATTEMPT_REACHED', 'BALANCE_NOT_ENOUGH', 'OTHER' ) as $code ) {
            $fixture = $this->run_fixture( array_merge( $this->payload(), array( 'payment_method' => 'credit', 'pin' => '012345' ) ), array(
                'body' => json_encode( array( 'status' => false, 'text' => 'PIN 012345 private-response', 'results' => array( 'error' => $code, 'error_metafield' => '012345' ) ) ),
            ) );
            $this->assert_request_contract( $fixture );
            $this->assertFalse( $fixture['result']['status'] );
            $this->assertStringNotContainsString( '012345', json_encode( $fixture['result'] ) );
            $this->assertStringNotContainsString( 'private-response', json_encode( $fixture['result'] ) );
            if ( 'OTHER' !== $code ) {
                $this->assertSame( $code, $fixture['result']['data']['results']['error'] );
            }
        }
    }
}
