<?php

use PHPUnit\Framework\TestCase;

final class OriginAddressRepairPricingRuntimeTest extends TestCase {
	public function test_saved_label_repairs_origin_and_prices_after_mapping_cache_expiry(): void {
		if ( ! extension_loaded( 'pdo_sqlite' ) ) {
			$this->markTestSkipped( 'Requires PDO SQLite for transactional persistence.' );
		}
		$output = array();
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/origin-address-repair-pricing-runtime.php' ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$this->assertSame( array( 'ok' ), $output );
	}
}
