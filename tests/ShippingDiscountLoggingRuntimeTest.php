<?php

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class ShippingDiscountLoggingRuntimeTest extends TestCase {
	private function runScenario( string $scenario ): array {
		$command = escapeshellarg( PHP_BINARY ) . ' -d error_reporting=24575 ' . escapeshellarg( __DIR__ . '/fixtures/shipping-discount-logging-runtime.php' ) . ' ' . escapeshellarg( $scenario );
		exec( $command . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_coupon_pricing_success_and_expected_rejections_do_not_write_logs(): void {
		foreach ( array( 'coupon_none', 'coupon_invalid', 'coupon_mismatch', 'coupon_zero_amount', 'coupon_zero_cost', 'coupon_applied' ) as $scenario ) {
			$result = $this->runScenario( $scenario );
			$this->assertSame( array(), $result['logs'], $scenario );
			$this->assertSame( 'coupon_applied' === $scenario ? 10 : 0, $result['result']['discount_amount'], $scenario );
		}
	}

	public function test_region_cache_success_and_pending_schedule_do_not_write_logs(): void {
		foreach ( array( 'schedule', 'refresh', 'seed', 'city_success' ) as $scenario ) {
			$result = $this->runScenario( $scenario );
			$this->assertSame( array(), $result['logs'], $scenario );
			if ( 'schedule' === $scenario ) {
				$this->assertSame( array( true, false, 'scheduled' ), $result['result'] );
			} else {
				$this->assertSame( 200, $result['result']['status'], $scenario );
			}
		}
	}

	public function test_region_cache_faults_keep_explicit_warning_and_error_logs(): void {
		foreach ( array( 'fallback' => 'warning', 'invalid_province' => 'warning', 'city_failure' => 'warning', 'malformed' => 'error', 'db_failure' => 'error' ) as $scenario => $level ) {
			$result = $this->runScenario( $scenario );
			$this->assertCount( 1, $result['logs'], $scenario );
			$this->assertSame( $level, $result['logs'][0][0], $scenario );
			$this->assertSame( 'kiriminaja_import', $result['logs'][0][2]['source'], $scenario );
			$this->assertSame( 'fallback' === $scenario ? 200 : 400, $result['result']['status'], $scenario );
		}
	}
}
