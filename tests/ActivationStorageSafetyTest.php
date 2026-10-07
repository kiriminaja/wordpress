<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ActivationStorageSafetyTest extends TestCase {
    public function test_reactivation_preserves_hpos_and_legacy_storage_with_unsynced_orders_and_existing_pages(): void {
        foreach ( array( 'yes', 'no' ) as $hpos ) {
            $output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/activation-storage-safety-runtime.php' ) . ' ' . escapeshellarg( json_encode( array( 'hpos' => $hpos ), JSON_THROW_ON_ERROR ) ) );
            $this->assertNotNull( $output );
            $r = json_decode( $output, true, 512, JSON_THROW_ON_ERROR );
            $this->assertSame( '', $r['error'] );
            $this->assertSame( $hpos, $r['options']['woocommerce_custom_orders_table_enabled'] );
            $this->assertSame( 71, $r['options']['woocommerce_checkout_page_id'] );
            $this->assertSame( 72, $r['options']['woocommerce_cart_page_id'] );
            $this->assertSame( array(), $r['pages'] );
            $this->assertSame( 31, $r['options']['kiriof_tracking_page_id'] );
            $this->assertNotContains( 'woocommerce_custom_orders_table_enabled', $r['writes'] );
        }
    }
}
