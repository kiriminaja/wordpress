<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CourierPolicyPerformanceRuntimeTest extends TestCase {
	public function test_repeated_policy_reads_preserve_raw_value_and_table_semantics(): void {
		$output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/courier-policy-performance.php' ) );
		$result = json_decode( $output, true, 512, JSON_THROW_ON_ERROR );

		self::assertTrue( $result['passed'] );
		self::assertSame( 11, $result['cases'] );
		self::assertSame( 33, $result['retrievals'] );
		self::assertSame( 150, $result['enabled_rates'] );
	}
}
