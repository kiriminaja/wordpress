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
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/buyer-courier-switch-runtime.php' ) . ' ' . escapeshellarg( json_encode( compact( 'insured', 'ninja_cod' ), JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
		$this->assertSame( array(), $result['warnings'] );
		$this->assertSame( 0, $result['network_calls'] );
		$this->assertSame( 0, $result['instant_constructions'] );
		$this->assertSame( array( null, null, null, null, null ), $result['invalid_cache_results'] );
		$this->assertSame( array( '12345' ), $result['lookup_calls'] );
		$this->assertSame( array( array(
			'subdistrict_origin' => 123, 'subdistrict_destination' => '456',
			'weight' => 1000, 'length' => 10, 'width' => 10, 'height' => 10,
			'insurance' => (int) $insured, 'item_value' => 20000, 'courier' => array( 'ninja', 'tiki', 'jne' ),
		) ), $result['pricing_payloads'] );

		$expected = array();
		foreach ( array( array( 'ninja', 'Standard', 17000, 'Standard service', $ninja_cod ), array( 'tiki', 'REG', 21500, 'Regular service', true ), array( 'jne', 'REG', 24000, 'Regular service', true ) ) as $row ) {
			$id = 'kiriminaja-official_' . $row[0] . '_' . $row[1];
			$expected[$id] = array( 'id' => $id, 'label' => strtoupper( $row[0] ) . ' ' . $row[1], 'cost' => $row[2], 'meta_data' => array(
				'kiriof_rate_eta' => '1-2 business days',
				'kiriof_rate_description' => $row[3] . ( $insured ? ' • Includes insurance' : '' ),
				'kiriof_rate_service' => $row[0], 'kiriof_rate_service_type' => $row[1], 'kiriof_rate_cod_available' => $row[4] ? 'yes' : 'no',
			) );
		}
		$history = $result['history'];
		$this->assertCount( 4, $history );
		foreach ( array( array( 'ninja_Standard', 'bacs' ), array( 'tiki_REG', 'bacs' ), array( 'tiki_REG', 'cod' ), array( 'ninja_Standard', 'bacs' ) ) as $index => $step ) {
			$rates = $expected;
			if ( 'cod' === $step[1] && ! $ninja_cod ) { unset( $rates['kiriminaja-official_ninja_Standard'] ); }
			$this->assertSame( $rates, $history[$index]['rates'], 'Step ' . $index . ': exact IDs, prices, labels and metadata' );
			$this->assertSame( 1, $history[$index]['api_calls'], 'Selection/payment changes must reuse raw pricing.' );
			$session = $history[$index]['session'];
			$this->assertSame( array( 'kiriminaja-official_' . $step[0] ), $session['chosen_shipping_methods'], 'Delayed method payload must not replace latest native selection.' );
			$this->assertSame( $step[1], $session['chosen_payment_method'] );
			$this->assertSame( (int) $insured, $session['kiriof_insurance'] );
			$this->assertSame( (int) $insured, $session['kiriof_force_insurance'] );
			$this->assertSame( '456', $session['shipping_destination_id'] );
			$this->assertSame( 'Canonical district', $session['kiriof_buyer_destination']['district_label'] );
			$this->assertSame( array( 'latitude' => '-6.2', 'longitude' => '106.8' ), $session['kiriof_buyer_destination_coordinates'] );
			$this->assertSame( $history[0]['session']['kiriof_buyer_destination'], $session['kiriof_buyer_destination'] );
			$this->assertCount( 1, $session['kiriof_shipping_price_cache'] );
			$entry = reset( $session['kiriof_shipping_price_cache'] );
			$this->assertTrue( $entry['data']['status'] );
			$this->assertCount( 5, $entry['data']['results'] );
			$this->assertSame( $ninja_cod, $entry['data']['results'][0]['cod'] );
			$this->assertArrayNotHasKey( 'setting', $entry['data']['results'][0] );
			$this->assertSame( 18000, $entry['data']['results'][0]['cost'] );
			$this->assertSame( $history[0]['session']['kiriof_shipping_price_cache'], $session['kiriof_shipping_price_cache'], 'Filtering must not mutate the cached mixed API rows.' );
		}
		$this->assertSame( $history[0]['rates'], $history[1]['rates'] );
		$this->assertSame( $history[0]['rates'], $history[3]['rates'], 'Returning to BACS restores non-COD Ninja.' );
	}
}
