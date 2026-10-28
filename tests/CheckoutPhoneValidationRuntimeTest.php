<?php
use PHPUnit\Framework\TestCase;
use KiriminAjaOfficial\Services\CheckoutPhoneValidation;

final class CheckoutPhoneValidationRuntimeTest extends TestCase {
	private function runFixture( array $input ): array {
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/checkout-phone-validation-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_phone_syntax_matches_frontend_contract(): void {
		$cases = json_decode( file_get_contents( __DIR__ . '/fixtures/checkout-phone-cases.json' ), true, 512, JSON_THROW_ON_ERROR );
		foreach ( $cases['valid'] as $phone ) { $this->assertTrue( CheckoutPhoneValidation::valid( $phone ), $phone ); }
		foreach ( $cases['invalid'] as $phone ) { $this->assertFalse( CheckoutPhoneValidation::valid( $phone ), json_encode( $phone ) ); }
	}

	public function test_both_routes_reject_invalid_billing_before_processing_without_mutating_input(): void {
		foreach ( array( 'classic_fields', 'classic_order', 'blocks' ) as $route ) {
			foreach ( array( 'kiriminaja-official_jne_REG', 'kiriminaja-instant:7:gosend:instant' ) as $method ) {
				$result = $this->runFixture( compact( 'route', 'method' ) + array( 'billing_phone' => '08523434AAAAAAXXXXX55678' ) );
				$this->assertFalse( $result['processed'] );
				$this->assertSame( '08523434AAAAAAXXXXX55678', $result['billing_phone'] );
				if ( 'classic_fields' === $route ) {
					$this->assertSame( array( 'id' => 'billing_phone' ), $result['errors']['billing_phone_validation']['data'] );
				} else {
					$this->assertNotNull( $result['error'] );
					$this->assertStringNotContainsString( 'AAAA', $result['error']['message'] );
				}
			}
		}
	}

	public function test_valid_formatting_and_missing_separate_phone_keep_native_values(): void {
		foreach ( array( 'classic_fields', 'classic_order', 'blocks' ) as $route ) {
			$result = $this->runFixture( compact( 'route' ) + array( 'billing_phone' => '+62 (812) 3456-7890', 'ship_to_different_address' => true ) );
			$this->assertTrue( $result['processed'] );
			$this->assertSame( '+62 (812) 3456-7890', $result['billing_phone'] );
		}
	}

	public function test_classic_ignores_inactive_shipping_but_rejects_active_bad_phone(): void {
		foreach ( array( 'classic_fields', 'classic_order' ) as $route ) {
			foreach ( array( false, true ) as $separate ) {
				$result = $this->runFixture( compact( 'route' ) + array( 'shipping_phone' => '0812bad12345', 'ship_to_different_address' => $separate ) );
				$this->assertSame( ! $separate, $result['processed'] );
			}
		}
	}

	public function test_blocks_validates_request_not_saved_contact_and_keeps_patch_editable(): void {
		foreach ( array( 'POST', 'PATCH' ) as $http_method ) {
			$result = $this->runFixture( compact( 'http_method' ) + array( 'request' => array( 'shipping_address' => array( 'phone' => '0812bad12345' ) ) ) );
			$this->assertSame( 'PATCH' === $http_method, $result['processed'] );
			if ( 'POST' === $http_method ) {
				$this->assertSame( 400, $result['error']['status'] );
				$this->assertSame( array( 'shipping_phone' ), $result['error']['data']['invalid_fields'] );
			}
		}
	}

	public function test_other_couriers_and_local_pickup_are_not_changed(): void {
		foreach ( array( 'classic_fields', 'classic_order', 'blocks' ) as $route ) {
			foreach ( array( 'flat_rate:8', 'local_pickup:7', 'kiriminaja-official-fake' ) as $method ) {
				$this->assertTrue( $this->runFixture( compact( 'route', 'method' ) + array( 'billing_phone' => 'bad' ) )['processed'] );
			}
		}
		$this->assertTrue( $this->runFixture( array( 'route' => 'classic_fields', 'needs_shipping' => false, 'billing_phone' => 'bad' ) )['processed'] );
	}

	public function test_classic_raw_malformed_phone_cannot_be_sanitized_into_valid_digits(): void {
		foreach ( array( "0812\n34567890", '0812<script>34567890', array( '081234567890' ) ) as $phone ) {
			$result = $this->runFixture( array( 'route' => 'classic_fields', 'billing_phone' => '081234567890', 'posted' => array( 'billing_phone' => $phone ) ) );
			$this->assertFalse( $result['processed'] );
			$this->assertArrayHasKey( 'billing_phone_validation', $result['errors'] );
		}
	}
}
