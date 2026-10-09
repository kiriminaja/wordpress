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
		$expectedCases = $actualCases = [];
		$result = $this->run_fixture( $cached, $stage, $flags );
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: result[warnings]' => array(),
		    '2: result[network_calls]' => 0,
		    '3: result[instant_constructions]' => 0,
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: result[warnings]' => $result['warnings'],
		    '2: result[network_calls]' => $result['network_calls'],
		    '3: result[instant_constructions]' => $result['instant_constructions'],
		    ];
		// Do not let a disabled Instant policy accidentally make this test green.
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: result[instant_enabled]' => array( true, true ),
		    '2: result[lookup_calls]' => array( '12345' ),
		    '3: result[history][A][destination][district_label]' => 'Canonical district',
		    '4: result[history][A][coordinates]' => array( 'latitude' => '-6.2', 'longitude' => '106.8' ),
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: result[instant_enabled]' => $result['instant_enabled'],
		    '2: result[lookup_calls]' => $result['lookup_calls'],
		    '3: result[history][A][destination][district_label]' => $result['history']['A']['destination']['district_label'],
		    '4: result[history][A][coordinates]' => $result['history']['A']['coordinates'],
		    ];
		if ( 'A' !== $stage ) {
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
			    '1: result[history][B][coordinates]' => array( 'latitude' => '0', 'longitude' => '0' ),
			    '2: result[history][B][destination][destination_latitude]' => '0',
			    '3: result[history][B][destination][destination_longitude]' => '0',
			    ];
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
			    '1: result[history][B][coordinates]' => $result['history']['B']['coordinates'],
			    '2: result[history][B][destination][destination_latitude]' => $result['history']['B']['destination']['destination_latitude'],
			    '3: result[history][B][destination][destination_longitude]' => $result['history']['B']['destination']['destination_longitude'],
			    ];
		}
		if ( in_array( $stage, array( 'clear', 'empty' ), true ) ) {
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
			    '1: result[history][clear][coordinates]' => null,
			    '2: result[history][clear][destination][version]' => 1,
			    '3: array_key_exists( destination_latitude, result[history][clear][destination] )' => false,
			    '4: array_key_exists( destination_longitude, result[history][clear][destination] )' => false,
			    '5: result[session][kiriof_buyer_destination_coordinates]' => null,
			    ];
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
			    '1: result[history][clear][coordinates]' => $result['history']['clear']['coordinates'],
			    '2: result[history][clear][destination][version]' => $result['history']['clear']['destination']['version'],
			    '3: array_key_exists( destination_latitude, result[history][clear][destination] )' => array_key_exists( 'destination_latitude', $result['history']['clear']['destination'] ),
			    '4: array_key_exists( destination_longitude, result[history][clear][destination] )' => array_key_exists( 'destination_longitude', $result['history']['clear']['destination'] ),
			    '5: result[session][kiriof_buyer_destination_coordinates]' => $result['session']['kiriof_buyer_destination_coordinates'],
			    ];
		}

		$session = $result['session'];
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: session[chosen_shipping_methods]' => array( 'kiriminaja-official_jne_REG' ),
		    '2: session[kiriof_chosen_shipping_methods]' => array( 'kiriminaja-official_jne_REG' ),
		    '3: session[kiriof_expedition]' => 'jne_REG',
		    '4: session[chosen_payment_method]' => $flags ? 'cod' : 'bacs',
		    '5: session[kiriof_insurance]' => (int) $flags,
		    '6: session[kiriof_force_insurance]' => (int) $flags,
		    '7: session[kiriof_cached_insurance_amt]' => 0,
		    '8: session[kiriof_cached_cod_amt]' => 0,
		    '9: session[kiriof_cached_fee_context]' => null,
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: session[chosen_shipping_methods]' => $session['chosen_shipping_methods'],
		    '2: session[kiriof_chosen_shipping_methods]' => $session['kiriof_chosen_shipping_methods'],
		    '3: session[kiriof_expedition]' => $session['kiriof_expedition'],
		    '4: session[chosen_payment_method]' => $session['chosen_payment_method'],
		    '5: session[kiriof_insurance]' => $session['kiriof_insurance'],
		    '6: session[kiriof_force_insurance]' => $session['kiriof_force_insurance'],
		    '7: session[kiriof_cached_insurance_amt]' => $session['kiriof_cached_insurance_amt'],
		    '8: session[kiriof_cached_cod_amt]' => $session['kiriof_cached_cod_amt'],
		    '9: session[kiriof_cached_fee_context]' => $session['kiriof_cached_fee_context'],
		    ];

		// Production OngkirPricingService must apply the same Express boundary.
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: count( result[options] )' => 1,
		    '2: result[options][0][key]' => 'jne_REG',
		    '3: result[options][0][service_code]' => 'jne',
		    '4: result[options][0][price]' => 10000,
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: count( result[options] )' => count( $result['options'] ),
		    '2: result[options][0][key]' => $result['options'][0]['key'],
		    '3: result[options][0][service_code]' => $result['options'][0]['service_code'],
		    '4: result[options][0][price]' => $result['options'][0]['price'],
		    ];
		if ( 'empty' === $stage ) {
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
			    '1: session[shipping_destination_id]' => '',
			    '2: session[destination_id]' => '',
			    '3: session[kiriof_buyer_destination_coordinates]' => null,
			    '4: result[rates]' => array(),
			    '5: result[coupon_rate_meta]' => array(),
			    '6: result[coupon_rows]' => array(),
			    '7: result[pricing_payloads]' => array(),
			    ];
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
			    '1: session[shipping_destination_id]' => $session['shipping_destination_id'],
			    '2: session[destination_id]' => $session['destination_id'],
			    '3: session[kiriof_buyer_destination_coordinates]' => $session['kiriof_buyer_destination_coordinates'],
			    '4: result[rates]' => $result['rates'],
			    '5: result[coupon_rate_meta]' => $result['coupon_rate_meta'],
			    '6: result[coupon_rows]' => $result['coupon_rows'],
			    '7: result[pricing_payloads]' => $result['pricing_payloads'],
			    ];
			$this->assertSame( $expectedCases, $actualCases );
			return;
		}

		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: session[shipping_destination_id]' => '456',
		    '2: count( result[rates] )' => 1,
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: session[shipping_destination_id]' => $session['shipping_destination_id'],
		    '2: count( result[rates] )' => count( $result['rates'] ),
		    ];
		$rate = $result['rates'][0];
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = 'kiriminaja-official_jne_REG';
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $rate['id'];
		// Wrong-type JNE REG row has a lower price and the same ID: asserting the
		// exact price catches a leak even if add_rate overwrites duplicate IDs.
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: rate[cost]' => 9500,
		    '2: rate[label]' => 'JNE REG',
		    '3: rate[meta_data][kiriof_rate_service]' => 'jne',
		    '4: rate[meta_data][kiriof_rate_service_type]' => 'REG',
		    '5: rate[meta_data][kiriof_rate_cod_available]' => 'yes',
		    '6: rate[meta_data][kiriof_rate_eta]' => '1-2 business days',
		    '7: rate[meta_data][kiriof_rate_description]' => $flags ? 'Regular service • With Insurance' : 'Regular service',
		    '8: array_key_exists( kiriof_shipping_coupon_original_cost, rate[meta_data] )' => false,
		    '9: result[coupon_rows]' => array( array( 'service' => 'jne', 'service_type' => 'REG', 'type' => 'express' ) ),
		    '10: array_keys( result[coupon_rate_meta] )' => array( 'kiriminaja-official_jne_REG' ),
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: rate[cost]' => $rate['cost'],
		    '2: rate[label]' => $rate['label'],
		    '3: rate[meta_data][kiriof_rate_service]' => $rate['meta_data']['kiriof_rate_service'],
		    '4: rate[meta_data][kiriof_rate_service_type]' => $rate['meta_data']['kiriof_rate_service_type'],
		    '5: rate[meta_data][kiriof_rate_cod_available]' => $rate['meta_data']['kiriof_rate_cod_available'],
		    '6: rate[meta_data][kiriof_rate_eta]' => $rate['meta_data']['kiriof_rate_eta'],
		    '7: rate[meta_data][kiriof_rate_description]' => $rate['meta_data']['kiriof_rate_description'],
		    '8: array_key_exists( kiriof_shipping_coupon_original_cost, rate[meta_data] )' => array_key_exists( 'kiriof_shipping_coupon_original_cost', $rate['meta_data'] ),
		    '9: result[coupon_rows]' => $result['coupon_rows'],
		    '10: array_keys( result[coupon_rate_meta] )' => array_keys( $result['coupon_rate_meta'] ),
		    ];
		$breakdown = $result['coupon_rate_meta']['kiriminaja-official_jne_REG'];
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: breakdown[cost]' => 9500,
		    '2: breakdown[original_cost]' => 10000,
		    '3: breakdown[discount_amount]' => 500,
		    '4: breakdown[service]' => 'jne',
		    '5: breakdown[service_type]' => 'REG',
		    '6: breakdown[notice]' => 'Fixture coupon',
		    '7: count( result[pricing_payloads] )' => $cached ? 0 : 1,
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: breakdown[cost]' => $breakdown['cost'],
		    '2: breakdown[original_cost]' => $breakdown['original_cost'],
		    '3: breakdown[discount_amount]' => $breakdown['discount_amount'],
		    '4: breakdown[service]' => $breakdown['service'],
		    '5: breakdown[service_type]' => $breakdown['service_type'],
		    '6: breakdown[notice]' => $breakdown['notice'],
		    '7: count( result[pricing_payloads] )' => count( $result['pricing_payloads'] ),
		    ];
		foreach ( $result['pricing_payloads'] as $payload ) {
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
			    '1: payload[subdistrict_destination]' => '456',
			    '2: payload[subdistrict_origin]' => 123,
			    '3: payload[insurance]' => (int) $flags,
			    '4: in_array( jne, payload[courier], true )' => true,
			    '5: array_keys( payload )' => array( 'origin_postcode', 'destination_postcode', 'subdistrict_origin', 'subdistrict_destination', 'weight', 'length', 'width', 'height', 'insurance', 'item_value', 'courier' ),
			    ];
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
			    '1: payload[subdistrict_destination]' => $payload['subdistrict_destination'],
			    '2: payload[subdistrict_origin]' => $payload['subdistrict_origin'],
			    '3: payload[insurance]' => $payload['insurance'],
			    '4: in_array( jne, payload[courier], true )' => in_array( 'jne', $payload['courier'], true ),
			    '5: array_keys( payload )' => array_keys( $payload ),
			    ];
		}
		// Cached raw rows remain mixed; filtering must run on a real cache hit too.
		$this->assertNotEmpty( $session['kiriof_shipping_price_cache'] );
		$entry = reset( $session['kiriof_shipping_price_cache'] );
		$this->assertCount( 8, $entry['data']['results'] );
		$this->assertSame( $expectedCases, $actualCases );
	}
}
