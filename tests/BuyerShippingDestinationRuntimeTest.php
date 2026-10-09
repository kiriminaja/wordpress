<?php

use PHPUnit\Framework\TestCase;

/** Production calculate_shipping exercised with subprocess-isolated Woo stubs. */
final class BuyerShippingDestinationRuntimeTest extends TestCase {
	private function run_fixture( string $action ): array {
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/buyer-shipping-destination-runtime.php' ) . ' ' . escapeshellarg( $action ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_free_coupon_respects_enabled_service_codes(): void {
		$result = $this->run_fixture( 'free_enabled_service' );
		$this->assertCount( 1, $result['rates'] );
		$this->assertSame( 'kiriminaja-official_jne_REG', $result['rates'][0]['id'] );
		$this->assertSame( 0, $result['rates'][0]['cost'] );
		$this->assertSame( array(), $result['warnings'] );
	}

	public function test_free_coupon_preserves_real_service_inventory_and_carrier_pricing(): void {
		$result = $this->run_fixture( 'free_services' );
		$this->assertCount( 1, $result['pricing_payloads'] );
		$this->assertSame( 1, $result['pricing_payloads'][0]['insurance'] );
		$this->assertSame( array( 'kiriminaja-official_jne_REG', 'kiriminaja-official_jne_YES' ), array_column( $result['rates'], 'id' ) );
		foreach ( $result['rates'] as $rate ) {
			$this->assertSame( 0, $rate['cost'] );
			$this->assertSame( 'jne', $rate['meta_data']['kiriof_rate_service'] );
			$this->assertNotSame( '', $rate['meta_data']['kiriof_rate_service_type'] );
			$meta = $result['coupon_rate_meta'][ $rate['id'] ];
			$this->assertGreaterThan( 0, $meta['original_cost'] );
			$this->assertSame( $meta['original_cost'], $meta['discount_amount'] );
			$this->assertSame( 'Free shipping', $meta['notice'] );
		}
		$this->assertSame( 9000, $result['coupon_rate_meta']['kiriminaja-official_jne_REG']['original_cost'] );
		$this->assertSame( 10000, $result['raw_response']['data']['results'][0]['cost'] );
		$this->assertSame( 500, $result['raw_response']['data']['results'][0]['insurance_cost'] );
		$this->assertSame( array(), $result['warnings'] );
	}

	public function test_free_coupon_still_requires_cod_capable_service(): void {
		$result = $this->run_fixture( 'free_cod' );
		$this->assertCount( 1, $result['rates'] );
		$this->assertSame( 'kiriminaja-official_jne_REG', $result['rates'][0]['id'] );
		$this->assertSame( 'yes', $result['rates'][0]['meta_data']['kiriof_rate_cod_available'] );
		$this->assertSame( 0, $result['rates'][0]['cost'] );
		$this->assertSame( array(), $result['warnings'] );
	}

	public function test_free_coupon_cannot_invent_a_rate_without_a_valid_available_service(): void {
		foreach ( array( 'free_disabled', 'free_empty', 'free_unsupported', 'free_failed', 'free_missing_destination' ) as $action ) {
			$result = $this->run_fixture( $action );
			$this->assertSame( array(), $result['rates'], $action );
			$this->assertSame( array(), $result['warnings'], $action );
			$this->assertCount( in_array( $action, array( 'free_disabled', 'free_missing_destination' ), true ) ? 0 : 1, $result['pricing_payloads'], $action );
		}
	}

	public function test_explicit_empty_modern_destination_does_not_restore_saved_customer_district(): void {
		$result = $this->run_fixture( 'modern_empty' );
		$this->assertSame( array(), $result['pricing_payloads'] );
		$this->assertSame( array(), $result['rates'] );
		$this->assertSame( array(), $result['customer_meta_reads'] );
		$this->assertSame( array(), $result['coupon_rate_meta'] );
		$this->assertSame( array(), $result['warnings'] );
	}

	public function test_positive_modern_snapshot_overrides_both_legacy_session_ids(): void {
		$result = $this->run_fixture( 'modern_positive' );
		$this->assert_priced_destination( '456', $result );
		$this->assertSame( array(), $result['customer_meta_reads'] );
	}

	public function test_package_postcode_mismatch_does_not_price_or_restore_saved_district(): void {
		$this->assert_rejected_package( 'postcode_mismatch' );
	}

	public function test_package_country_mismatch_does_not_price_or_restore_saved_district(): void {
		$this->assert_rejected_package( 'country_mismatch' );
	}

	public function test_legacy_shipping_session_keeps_precedence_without_modern_snapshot(): void {
		$result = $this->run_fixture( 'legacy_shipping_session' );
		$this->assert_priced_destination( '111', $result );
		$this->assertSame( array(), $result['customer_meta_reads'] );
	}

	public function test_legacy_session_fallback_still_works_without_modern_snapshot(): void {
		$result = $this->run_fixture( 'legacy_session' );
		$this->assert_priced_destination( '222', $result );
		$this->assertSame( array(), $result['customer_meta_reads'] );
	}

	public function test_legacy_customer_fallback_still_works_without_modern_snapshot(): void {
		$result = $this->run_fixture( 'legacy_customer' );
		$this->assert_priced_destination( '789', $result );
		$this->assertSame( array( 'shipping_kiriof_destination_area' ), $result['customer_meta_reads'] );
	}

	private function assert_rejected_package( string $action ): void {
		$result = $this->run_fixture( $action );
		$this->assertSame( array(), $result['pricing_payloads'], $action );
		$this->assertSame( array(), $result['rates'], $action );
		$this->assertSame( array(), $result['customer_meta_reads'], $action );
		$this->assertSame( array(), $result['coupon_rate_meta'], $action );
		$this->assertSame( array(), $result['warnings'], $action );
	}

	private function assert_priced_destination( string $destination, array $result ): void {
		$this->assertCount( 1, $result['pricing_payloads'] );
		$this->assertSame( $destination, (string) $result['pricing_payloads'][0]['subdistrict_destination'] );
		$this->assertSame( 123, $result['pricing_payloads'][0]['subdistrict_origin'] );
		$this->assertCount( 1, $result['rates'] );
		$this->assertSame( 'kiriminaja-official_jne_REG', $result['rates'][0]['id'] );
		$this->assertSame( 10000, $result['rates'][0]['cost'] );
		// This fixture deliberately supplies no display metadata: do not add an empty suffix.
		$this->assertSame( 'JNE REG', $result['rates'][0]['label'] );
		$this->assertSame( array(), $result['warnings'] );
	}
}
