<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Payment limits COD capability; courier selection must not mutate shared pricing. */
final class BuyerCourierSwitchRuntimeTest extends TestCase {
	public static function switch_cases(): iterable {
		foreach ( array( false, true ) as $insured ) {
			foreach ( array( false, true ) as $ninja_cod ) {
				yield ( $insured ? 'insured' : 'uninsured' ) . ' / Ninja ' . ( $ninja_cod ? 'COD' : 'non-COD' ) => array( $insured, $ninja_cod );
			}
		}
	}

	#[DataProvider( 'switch_cases' )]
	public function test_full_shipping_calculations_preserve_rates_across_courier_and_payment_switches( bool $insured, bool $ninja_cod ): void {
		$expectedCases = $actualCases = [];
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/buyer-courier-switch-runtime.php' ) . ' ' . escapeshellarg( json_encode( compact( 'insured', 'ninja_cod' ), JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
		$contractCase = implode( "\n", $output );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = 0;
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $status;
		$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: result[warnings]' => array(),
		    '2: result[network_calls]' => 0,
		    '3: result[instant_constructions]' => 0,
		    '4: result[invalid_cache_results]' => array( null, null, null, null, null ),
		    '5: result[lookup_calls]' => array( '12345' ),
		    '6: result[pricing_payloads]' => array( array(
			'origin_postcode' => '', 'destination_postcode' => '12345',
			'subdistrict_origin' => 123, 'subdistrict_destination' => '456',
			'weight' => 1000, 'length' => 10, 'width' => 10, 'height' => 10,
			'insurance' => (int) $insured, 'item_value' => 20000, 'courier' => array( 'ninja', 'tiki', 'jne' ),
		) ),
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: result[warnings]' => $result['warnings'],
		    '2: result[network_calls]' => $result['network_calls'],
		    '3: result[instant_constructions]' => $result['instant_constructions'],
		    '4: result[invalid_cache_results]' => $result['invalid_cache_results'],
		    '5: result[lookup_calls]' => $result['lookup_calls'],
		    '6: result[pricing_payloads]' => $result['pricing_payloads'],
		    ];

		$expected = array();
		foreach ( array( array( 'ninja', 'Standard', 17000, 'Standard service', $ninja_cod ), array( 'tiki', 'REG', 21500, 'Regular service', true ), array( 'jne', 'REG', 24000, 'Regular service', true ) ) as $row ) {
			$id = 'kiriminaja-official_' . $row[0] . '_' . $row[1];
			$expected[$id] = array( 'id' => $id, 'label' => strtoupper( $row[0] ) . ' ' . $row[1], 'cost' => $row[2], 'meta_data' => array(
				'kiriof_rate_eta' => '1-2 business days',
				'kiriof_rate_description' => $row[3] . ( $insured ? ' • With Insurance' : '' ),
				'kiriof_rate_service' => $row[0], 'kiriof_rate_service_type' => $row[1], 'kiriof_rate_cod_available' => $row[4] ? 'yes' : 'no',
			) );
		}
		$history = $result['history'];
		$this->assertCount( 4, $history );
		foreach ( array( array( 'ninja_Standard', 'bacs' ), array( 'tiki_REG', 'bacs' ), array( 'tiki_REG', 'cod' ), array( 'ninja_Standard', 'bacs' ) ) as $index => $step ) {
			$rates = $expected;
			if ( 'cod' === $step[1] && ! $ninja_cod ) { unset( $rates['kiriminaja-official_ninja_Standard'] ); }
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
			    '1: history[index][rates]' => $rates,
			    '2: history[index][api_calls]' => 1,
			    ];
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
			    '1: history[index][rates]' => $history[$index]['rates'],
			    '2: history[index][api_calls]' => $history[$index]['api_calls'],
			    ];
			$session = $history[$index]['session'];
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
			    '1: session[chosen_shipping_methods]' => array( 'kiriminaja-official_' . $step[0] ),
			    '2: session[chosen_payment_method]' => $step[1],
			    '3: session[kiriof_insurance]' => (int) $insured,
			    '4: session[kiriof_force_insurance]' => (int) $insured,
			    '5: session[shipping_destination_id]' => '456',
			    '6: session[kiriof_buyer_destination][district_label]' => 'Canonical district',
			    '7: session[kiriof_buyer_destination_coordinates]' => array( 'latitude' => '-6.2', 'longitude' => '106.8' ),
			    '8: session[kiriof_buyer_destination]' => $history[0]['session']['kiriof_buyer_destination'],
			    '9: count( session[kiriof_shipping_price_cache] )' => 1,
			    ];
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
			    '1: session[chosen_shipping_methods]' => $session['chosen_shipping_methods'],
			    '2: session[chosen_payment_method]' => $session['chosen_payment_method'],
			    '3: session[kiriof_insurance]' => $session['kiriof_insurance'],
			    '4: session[kiriof_force_insurance]' => $session['kiriof_force_insurance'],
			    '5: session[shipping_destination_id]' => $session['shipping_destination_id'],
			    '6: session[kiriof_buyer_destination][district_label]' => $session['kiriof_buyer_destination']['district_label'],
			    '7: session[kiriof_buyer_destination_coordinates]' => $session['kiriof_buyer_destination_coordinates'],
			    '8: session[kiriof_buyer_destination]' => $session['kiriof_buyer_destination'],
			    '9: count( session[kiriof_shipping_price_cache] )' => count( $session['kiriof_shipping_price_cache'] ),
			    ];
			$entry = reset( $session['kiriof_shipping_price_cache'] );
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
			    '1: entry[data][status]' => true,
			    '2: count( entry[data][results] )' => 5,
			    '3: entry[data][results][0][cod]' => $ninja_cod,
			    '4: array_key_exists( setting, entry[data][results][0] )' => false,
			    '5: entry[data][results][0][cost]' => 18000,
			    '6: session[kiriof_shipping_price_cache]' => $history[0]['session']['kiriof_shipping_price_cache'],
			    ];
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
			    '1: entry[data][status]' => $entry['data']['status'],
			    '2: count( entry[data][results] )' => count( $entry['data']['results'] ),
			    '3: entry[data][results][0][cod]' => $entry['data']['results'][0]['cod'],
			    '4: array_key_exists( setting, entry[data][results][0] )' => array_key_exists( 'setting', $entry['data']['results'][0] ),
			    '5: entry[data][results][0][cost]' => $entry['data']['results'][0]['cost'],
			    '6: session[kiriof_shipping_price_cache]' => $session['kiriof_shipping_price_cache'],
			    ];
		}
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: history[1][rates]' => $history[0]['rates'],
		    '2: history[3][rates]' => $history[0]['rates'],
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: history[1][rates]' => $history[1]['rates'],
		    '2: history[3][rates]' => $history[3]['rates'],
		    ];
		$this->assertSame( $expectedCases, $actualCases );
	}
}
