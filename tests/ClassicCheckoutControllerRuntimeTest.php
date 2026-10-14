<?php
use PHPUnit\Framework\TestCase;

final class ClassicCheckoutControllerRuntimeTest extends TestCase {
    private function runCase( string $mode ): array {
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/classic-checkout-controller-runtime.php' ) . ' ' . escapeshellarg( $mode ) . ' 2>&1', $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
    }

    public function test_native_classic_hooks_validate_effective_address_without_plugin_nonce(): void {
        foreach ( array( 'success', 'billing', 'shipping', 'pin', 'session-pin' ) as $mode ) {
            $result = $this->runCase( $mode );
            $this->assertArrayNotHasKey( 'error', $result, $mode );
            $this->assertSame( 1, $result['calls'] );
            $this->assertCount( 1, $result['transactions'] );
            $this->assertTrue( $result['transactions'][0]['blocks_validated'] );
            $this->assertSame( '123', $result['transactions'][0]['kiriof_destination_area'] );
            $this->assertSame( $result['amount_before'], $result['amount_after'] );
            $this->assertArrayHasKey( '_kiriof_express_validated', $result['meta'] );
            if ( in_array( $mode, array( 'pin', 'session-pin' ), true ) ) {
                $this->assertSame( 2, $result['meta']['_kiriof_buyer_destination']['version'] );
                $this->assertArrayHasKey( '_kiriof_buyer_destination_coordinates', $result['meta'] );
            }
        }
    }

    public function test_clear_and_tampered_address_or_fees_cannot_create_transactions(): void {
        foreach ( array( 'clear', 'tamper', 'pin-tamper', 'fee' ) as $mode ) {
            $result = $this->runCase( $mode );
            $this->assertArrayHasKey( 'error', $result, $mode );
            $this->assertCount( 0, $result['transactions'] );
            $this->assertArrayNotHasKey( '_kiriof_express_validated', $result['meta'] );
        }
    }

    public function test_unchecked_overrides_stale_session_consent(): void {
        $result = $this->runCase( 'unchecked' );
        $this->assertArrayNotHasKey( 'error', $result );
        $this->assertSame( 0, $result['validated_meta']['_kiriof_checkout_billing_insurance'] );
    }

    public function test_failed_transaction_replays_durable_quote_without_repricing(): void {
        $result = $this->runCase( 'retry' );
        $this->assertSame( 503, $result['error']['status'] );
        $this->assertArrayNotHasKey( 'retry_error', $result );
        $this->assertCount( 2, $result['transactions'] );
        $this->assertSame( $result['transactions'][0]['validated_calculation'], $result['transactions'][1]['validated_calculation'] );
        $this->assertSame( 1, $result['calls'] );
        $this->assertSame( $result['amount_before'], $result['amount_after'] );
    }
}
