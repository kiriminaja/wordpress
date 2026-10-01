<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Capability copy belongs to every native rate, not to the selected-rate UI. */
final class CheckoutRatePresentationRuntimeTest extends TestCase {
	public function test_explicit_capability_flags_and_unknown_values_in_arrays_and_objects(): void {
		$cases = array();
		$expected = array();
		foreach ( array( false, true ) as $object ) {
			foreach ( array( false, true ) as $requested ) {
				foreach ( array( 'insurance_supported', 'allow_insurance', 'insurance_available' ) as $key ) {
					foreach ( array( false, true ) as $nested ) {
						foreach ( array( false, 0, '0', 'no', 'false', true, 1, '1', 'yes', 'true', null, '', 'unknown', 'YES', 2, array() ) as $value ) {
							$fields = array( $key => $value );
							$row = $nested ? array( 'setting' => $fields ) : $fields;
							$cases[] = compact( 'row', 'requested', 'object' );
							if ( in_array( $value, array( false, 0, '0', 'no', 'false' ), true ) ) {
								$expected[] = 'No Insurance Support';
							} elseif ( in_array( $value, array( true, 1, '1', 'yes', 'true' ), true ) ) {
								$expected[] = $requested ? 'With Insurance' : 'Additional Insurance Supported';
							} else {
								$expected[] = $requested ? 'With Insurance' : '';
							}
						}
					}
				}
			}
		}
		$this->assertSame( $expected, $this->run_label_fixture( $cases ) );
	}

	public function test_missing_capability_forcing_api_fees_and_conflicting_evidence(): void {
		$cases = array();
		$expected = array();
		$rows = array(
			'missing' => array( array(), '' ),
			'empty settings' => array( array( 'setting' => array() ), '' ),
			'zero fee' => array( array( 'insurance' => 0 ), '' ),
			'negative fee' => array( array( 'insurance' => -10 ), '' ),
			'nonnumeric fee' => array( array( 'insurance' => 'unexpected' ), '' ),
			'infinite string fee' => array( array( 'insurance' => 'INF' ), '' ),
			'NaN string fee' => array( array( 'insurance' => 'NaN' ), '' ),
			'boolean fee is not a price' => array( array( 'insurance' => true ), '' ),
			'positive fee' => array( array( 'insurance' => 1250 ), 'With Insurance' ),
			'numeric string fee' => array( array( 'insurance' => '1250.50' ), 'With Insurance' ),
			'force insurance' => array( array( 'force_insurance' => true ), 'With Insurance' ),
			'force insurance integer' => array( array( 'force_insurance' => 1 ), 'With Insurance' ),
			'force insurance disabled' => array( array( 'force_insurance' => false ), '' ),
			'force insurance zero string' => array( array( 'force_insurance' => '0' ), '' ),
			'force false string' => array( array( 'force_insurance' => 'false' ), '' ),
			'overflow fee' => array( array( 'insurance' => '1e309' ), '' ),
			'unsupported overrides fee and force' => array( array( 'insurance_supported' => false, 'insurance' => 1250, 'force_insurance' => true ), 'No Insurance Support' ),
			'nested unsupported overrides fee and force' => array( array( 'setting' => array( 'allow_insurance' => false ), 'insurance' => 1250, 'force_insurance' => true ), 'No Insurance Support' ),
			'unknown row flag defers to known setting' => array( array( 'insurance_supported' => null, 'setting' => array( 'insurance_available' => false ) ), 'No Insurance Support' ),
			'row unsupported overrides nested support' => array( array( 'allow_insurance' => false, 'setting' => array( 'insurance_supported' => true ) ), 'No Insurance Support' ),
			'nested unsupported overrides row support' => array( array( 'insurance_supported' => true, 'setting' => array( 'allow_insurance' => false ) ), 'No Insurance Support' ),
		);
		foreach ( $rows as $name => list( $row, $unrequested ) ) {
			foreach ( array( false, true ) as $requested ) {
				foreach ( array( false, true ) as $object ) {
					$cases[] = compact( 'row', 'requested', 'object' );
					$expected[] = $requested && '' === $unrequested ? 'With Insurance' : $unrequested;
				}
			}
		}
		$labels = $this->run_label_fixture( $cases );
		foreach ( $cases as $index => $case ) {
			$this->assertSame( $expected[$index], $labels[$index], json_encode( $case, JSON_THROW_ON_ERROR ) );
		}
	}

	private function run_label_fixture( array $cases ): array {
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/checkout-rate-presentation-runtime.php' ) . ' ' . escapeshellarg( json_encode( $cases, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public static function requested_states(): iterable {
		yield 'insurance off' => array( false );
		yield 'insurance requested' => array( true );
	}

	#[DataProvider( 'requested_states' )]
	public function test_all_native_rates_include_service_and_capability_before_courier_selection( bool $insured ): void {
		$input = array( 'insured' => $insured, 'ninja_cod' => true, 'presentation' => array(
			'ninja' => array( 'insurance_supported' => true, 'insurance' => 0 ),
			'tiki' => array( 'setting' => array( 'allow_insurance' => false ), 'insurance' => 0 ),
			'jne' => array( 'insurance' => 0 ),
		) );
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/buyer-courier-switch-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
		$this->assertSame( array(), $result['warnings'] );
		$this->assertSame( 0, $result['network_calls'] );
		$this->assertCount( 2, $result['history'] );
		$this->assertArrayNotHasKey( 'chosen_shipping_methods', $result['history'][0]['session'] );
		$this->assertSame( $result['history'][0]['rates'], $result['history'][1]['rates'], 'Selecting JNE must not change the other rates or their labels.' );
		$expected = array(
			'ninja_Standard' => array( 'NINJA Standard', 'Standard service • ' . ( $insured ? 'With Insurance' : 'Additional Insurance Supported' ), 17000 ),
			'tiki_REG' => array( 'TIKI REG', 'Regular service • No Insurance Support', 21500 ),
			'jne_REG' => array( 'JNE REG', 'Regular service' . ( $insured ? ' • With Insurance' : '' ), 24000 ),
		);
		$this->assertCount( 3, $result['history'][0]['rates'] );
		foreach ( $expected as $key => list( $base, $description, $cost ) ) {
			$rate = $result['history'][0]['rates']['kiriminaja-official_' . $key];
			$this->assertSame( $description, $rate['meta_data']['kiriof_rate_description'] );
			$this->assertSame( $base . ' — ' . $description, $rate['label'] );
			$this->assertSame( $cost, $rate['cost'], 'Presentation must not modify API-zero insurance prices.' );
			$this->assertSame( strip_tags( $rate['label'] ), $rate['label'], 'Woo core receives plain labels, not HTML.' );
		}
		$this->assertCount( 1, $result['pricing_payloads'] );
	}
}
