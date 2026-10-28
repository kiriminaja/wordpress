<?php

use PHPUnit\Framework\TestCase;

final class TransactionPhoneIssueCacheRuntimeTest extends TestCase {
	public function test_cache_reuses_complete_scans_and_invalidates_live_dependencies(): void {
		$command = kiriof_test_fixture_php_shell( array( __DIR__ . '/fixtures/transaction-phone-issue-cache-runtime.php' ) );
		exec( $command . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
		$this->assertSame( array( 1, 205 ), $result['cold'] );
		$this->assertSame( 2, $result['cold_scans'] );
		$this->assertSame( 205, $result['cold_reads'] );
		$this->assertSame( 2, $result['warm_scans'] );
		$this->assertSame( 205, $result['warm_reads'] );
		$this->assertSame( array( 205 ), $result['corrected'] );
		$this->assertSame( array(), $result['snapshot_corrected'] );
		$this->assertSame( array(), $result['booked'] );
		$this->assertSame( array( 206 ), $result['created'] );
		$this->assertSame( array( 206 ), $result['refreshed'] );
		$this->assertSame( 2, $result['retained_keys'] );
		$this->assertTrue( $result['hooks_invalidated'] );
		$this->assertTrue( $result['race_discarded'] );
		$this->assertTrue( $result['error_not_cached'] );
		$this->assertTrue( $result['minimal_fields'] );
	}
}
