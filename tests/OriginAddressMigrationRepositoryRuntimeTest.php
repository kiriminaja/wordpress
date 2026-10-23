<?php

use PHPUnit\Framework\TestCase;

/** Production repository SQL against a real transactional SQLite database. */
final class OriginAddressMigrationRepositoryRuntimeTest extends TestCase {
    public function test_atomic_origin_repair_and_merchant_concurrency_guards(): void {
        if ( ! extension_loaded( 'pdo_sqlite' ) ) {
            $this->markTestSkipped( 'The runtime SQL fixture requires pdo_sqlite.' );
        }
        $output = array();
        $status = 1;
        // Keep the normal PHP extension profile: the fixture needs PDO SQLite.
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/origin-address-migration-runtime.php' ) . ' 2>&1', $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        $scenarios = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
        $this->assertCount( 60, $scenarios );
		$this->assertContains( 'oversized-canonical', $scenarios );
		$this->assertContains( 'snapshot-conflict', $scenarios );
        foreach ( array( 'wp_kiriminaja_shipment_location', 'wp_kiriminaja_settings', 'wp_options' ) as $table ) {
            foreach ( array( 'myisam', 'missing-engine', 'missing-table', 'status-failure', 'wrong-name' ) as $failure ) {
                $this->assertContains( 'engine-' . $table . '-' . $failure, $scenarios );
            }
        }
        $this->assertContains( 'engine-custom-unrelated-myisam', $scenarios );
        foreach ( array( 'paging', 'custom', 'default', 'noop', 'missing', 'duplicates', 'merchant-settings', 'merchant-option', 'default-switch', 'write-failure', 'commit-failure', 'lock-failure', 'blank-label-edit', 'divergent-id', 'blank-label', 'blank-label-empty-zip', 'divergent-zip', 'divergent-label', 'mirror-delete', 'missing-insert', 'option-delete', 'option-insert', 'metadata-column', 'read-failure-kiriminaja_shipment_location', 'read-failure-kiriminaja_settings', 'read-failure-wp_options', 'concurrent-address', 'concurrent-latitude', 'concurrent-updated_at' ) as $scenario ) {
            $this->assertContains( $scenario, $scenarios );
        }
    }
}
