<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PricingCacheIsolationRuntimeTest extends TestCase {
	private function run_fixture( array $input ): array {
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/pricing-cache-isolation-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
		$this->assertSame( array(), $result['warnings'] );
		return $result;
	}

	public static function boundaries(): iterable {
		foreach ( array( 'put', 'runtime', 'session', 'transient', 'compatible' ) as $tier ) {
			yield $tier => array( $tier );
		}
	}

	#[DataProvider( 'boundaries' )]
	public function test_mutable_results_are_detached_at_every_cache_boundary( string $tier ): void {
		$result = $this->run_fixture( array( 'scenario' => 'isolation', 'tier' => $tier ) );
		$this->assertTrue( $result['objects_preserved'] );
		$this->assertSame( array( 'jne', 'ninja' ), $result['services'] );
		$this->assertSame( 12000, $result['cost'] );
		$this->assertSame( 2500, $result['nested_fee'] );
		$this->assertSame( 'original', $result['nested_label'] );
		$this->assertTrue( $result['subset_hit'] );
	}

	public function test_courier_scope_reuse_is_directional_and_normalized(): void {
		$result = $this->run_fixture( array( 'scenario' => 'scope' ) );
		$this->assertSame( array( true, true, true, false, false, false ), $result['hits'] );
	}

	public function test_all_api_pricing_inputs_participate_in_cache_identity(): void {
		$result = $this->run_fixture( array( 'scenario' => 'identity' ) );
		$this->assertSame( array_fill( 0, 11, false ), $result['changed_hits'] );
		$this->assertTrue( $result['normalized_hit'] );
		$this->assertTrue( $result['pin_hit'] );
	}

	public function test_malformed_or_expired_entries_cannot_supply_rates_or_unrestricted_scope(): void {
		$result = $this->run_fixture( array( 'scenario' => 'invalid' ) );
		$this->assertSame( array_fill( 0, 9, false ), $result['hits'] );
		$this->assertSame( array_fill( 0, 5, false ), $result['put_hits'] );
	}
}
