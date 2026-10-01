<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Pins enrich destination context; buyer checkout remains an Express-only rate surface. */
final class BuyerInstantExclusionRuntimeTest extends TestCase {
	public static function checkout_cases(): iterable {
		foreach ( array( false, true ) as $cached ) {
			foreach ( array( 'A', 'B', 'clear', 'empty' ) as $stage ) {
				foreach ( array( false, true ) as $flags ) {
					yield ( $cached ? 'cache hit' : 'fresh response' ) . ' / ' . $stage . ' / ' . ( $flags ? 'COD insured' : 'BACS uninsured' ) => array( $cached, $stage, $flags );
				}
			}
		}
	}

	private function run_fixture( bool $cached, string $stage, bool $flags ): array {
		$input = json_encode( compact( 'cached', 'stage', 'flags' ), JSON_THROW_ON_ERROR );
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/buyer-instant-exclusion-runtime.php' ) . ' ' . escapeshellarg( $input ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	#[DataProvider( 'checkout_cases' )]
	public function test_pin_sync_never_exposes_instant_rates_or_invokes_instant_pricing( bool $cached, string $stage, bool $flags ): void {
		$result = $this->run_fixture( $cached, $stage, $flags );
		$this->assertSame( array(), $result['warnings'] );
		$this->assertSame( 0, $result['network_calls'] );
		$this->assertSame( 0, $result['instant_constructions'] );
		// Do not let a disabled Instant policy accidentally make this test green.
		$this->assertSame( array( true, true ), $result['instant_enabled'] );
		$this->assertSame( array( '12345' ), $result['lookup_calls'] );
		$this->assertSame( 'Canonical district', $result['history']['A']['destination']['district_label'] );
		$this->assertSame( array( 'latitude' => '-6.2', 'longitude' => '106.8' ), $result['history']['A']['coordinates'] );
		if ( 'A' !== $stage ) {
			$this->assertSame( array( 'latitude' => '0', 'longitude' => '0' ), $result['history']['B']['coordinates'] );
			$this->assertSame( '0', $result['history']['B']['destination']['destination_latitude'] );
			$this->assertSame( '0', $result['history']['B']['destination']['destination_longitude'] );
		}
		if ( in_array( $stage, array( 'clear', 'empty' ), true ) ) {
			$this->assertNull( $result['history']['clear']['coordinates'] );
			$this->assertSame( 1, $result['history']['clear']['destination']['version'] );
			$this->assertArrayNotHasKey( 'destination_latitude', $result['history']['clear']['destination'] );
			$this->assertArrayNotHasKey( 'destination_longitude', $result['history']['clear']['destination'] );
			$this->assertNull( $result['session']['kiriof_buyer_destination_coordinates'] );
		}

		$session = $result['session'];
		$this->assertSame( array( 'kiriminaja-official_jne_REG' ), $session['chosen_shipping_methods'] );
		$this->assertSame( array( 'kiriminaja-official_jne_REG' ), $session['kiriof_chosen_shipping_methods'] );
		$this->assertSame( 'jne_REG', $session['kiriof_expedition'] );
		$this->assertSame( $flags ? 'cod' : 'bacs', $session['chosen_payment_method'] );
		$this->assertSame( (int) $flags, $session['kiriof_insurance'] );
		$this->assertSame( (int) $flags, $session['kiriof_force_insurance'] );
		$this->assertSame( 0, $session['kiriof_cached_insurance_amt'] );
		$this->assertSame( 0, $session['kiriof_cached_cod_amt'] );
		$this->assertNull( $session['kiriof_cached_fee_context'] );

		// Production OngkirPricingService must apply the same Express boundary.
		$this->assertCount( 1, $result['options'] );
		$this->assertSame( 'jne_REG', $result['options'][0]['key'] );
		$this->assertSame( 'jne', $result['options'][0]['service_code'] );
		$this->assertSame( 10000, $result['options'][0]['price'] );
		if ( 'empty' === $stage ) {
			$this->assertSame( '', $session['shipping_destination_id'] );
			$this->assertSame( '', $session['destination_id'] );
			$this->assertNull( $session['kiriof_buyer_destination_coordinates'] );
			$this->assertSame( array(), $result['rates'] );
			$this->assertSame( array(), $result['coupon_rate_meta'] );
			$this->assertSame( array(), $result['coupon_rows'] );
			$this->assertSame( array(), $result['pricing_payloads'] );
			return;
		}

		$this->assertSame( '456', $session['shipping_destination_id'] );
		$this->assertCount( 1, $result['rates'] );
		$rate = $result['rates'][0];
		$this->assertSame( 'kiriminaja-official_jne_REG', $rate['id'] );
		// Wrong-type JNE REG row has a lower price and the same ID: asserting the
		// exact price catches a leak even if add_rate overwrites duplicate IDs.
		$this->assertSame( 9500, $rate['cost'] );
		$this->assertSame( 'jne', $rate['meta_data']['kiriof_rate_service'] );
		$this->assertSame( 'REG', $rate['meta_data']['kiriof_rate_service_type'] );
		$this->assertSame( 'yes', $rate['meta_data']['kiriof_rate_cod_available'] );
		$this->assertSame( '1-2 business days', $rate['meta_data']['kiriof_rate_eta'] );
		$this->assertSame( $flags ? 'Regular service • Includes insurance' : 'Regular service', $rate['meta_data']['kiriof_rate_description'] );
		$this->assertArrayNotHasKey( 'kiriof_shipping_coupon_original_cost', $rate['meta_data'] );
		$this->assertSame( array( array( 'service' => 'jne', 'service_type' => 'REG', 'type' => 'express' ) ), $result['coupon_rows'] );
		$this->assertSame( array( 'kiriminaja-official_jne_REG' ), array_keys( $result['coupon_rate_meta'] ) );
		$breakdown = $result['coupon_rate_meta']['kiriminaja-official_jne_REG'];
		$this->assertSame( 9500, $breakdown['cost'] );
		$this->assertSame( 10000, $breakdown['original_cost'] );
		$this->assertSame( 500, $breakdown['discount_amount'] );
		$this->assertSame( 'jne', $breakdown['service'] );
		$this->assertSame( 'REG', $breakdown['service_type'] );
		$this->assertSame( 'Fixture coupon', $breakdown['notice'] );

		$this->assertCount( $cached ? 0 : 1, $result['pricing_payloads'] );
		foreach ( $result['pricing_payloads'] as $payload ) {
			$this->assertSame( '456', $payload['subdistrict_destination'] );
			$this->assertSame( 123, $payload['subdistrict_origin'] );
			$this->assertSame( (int) $flags, $payload['insurance'] );
			$this->assertContains( 'jne', $payload['courier'] );
			$this->assertSame( array( 'subdistrict_origin', 'subdistrict_destination', 'weight', 'length', 'width', 'height', 'insurance', 'item_value', 'courier' ), array_keys( $payload ) );
		}
		// Cached raw rows remain mixed; filtering must run on a real cache hit too.
		$this->assertNotEmpty( $session['kiriof_shipping_price_cache'] );
		$entry = reset( $session['kiriof_shipping_price_cache'] );
		$this->assertCount( 8, $entry['data']['results'] );
	}
}
