<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExpressCheckoutControllerRuntimeTest extends TestCase {
    private function runCase( string $mode ): array {
        $output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/express-checkout-controller-runtime.php' ) . ' ' . escapeshellarg( $mode ) );
        return json_decode( (string) $output, true, 512, JSON_THROW_ON_ERROR );
    }

    #[Test]
    public function update_then_processed_pins_real_validation_without_repricing_or_customer_save(): void {
        $result = $this->runCase( 'success' );
        $this->assertArrayNotHasKey( 'error', $result );
        $this->assertSame( 1, $result['calls'] );
        $this->assertCount( 1, $result['transactions'] );
        $transaction = $result['transactions'][0];
        $this->assertTrue( $transaction['blocks_validated'] );
        $this->assertSame( '123', $transaction['kiriof_destination_area'] );
        $this->assertSame( 'Verified district', $transaction['kiriof_destination_area_name'] );
        $this->assertSame( 'jne_REG', $transaction['kiriof_expedition'] );
        $this->assertSame( $result['validated_meta']['_kiriof_express_validated']['validated_calculation'], $transaction['validated_calculation'] );
        $this->assertSame( $result['amount_before'], $result['amount_after'] );
        $this->assertSame( 0, $result['customer_saves'] );
        $this->assertArrayHasKey( '_kiriof_express_validated', $result['meta'] );
        $this->assertArrayNotHasKey( '_kiriof_checkout_expedition', $result['meta'] );
        $this->assertSame( 1, $result['saves'] );
        $this->assertStringNotContainsString( 'private-', json_encode( $transaction ) );
    }

    #[Test]
    public function transaction_failure_propagates_503_and_keeps_context_until_success(): void {
        foreach ( array( 'failure', 'throw' ) as $mode ) {
            $result = $this->runCase( $mode );
            $this->assertSame( 503, $result['error']['status'], $mode );
            $this->assertSame( 'kiriof_express_transaction_failed', $result['error']['code'] );
            $this->assertStringNotContainsString( 'private', $result['error']['message'] );
            $this->assertSame( $result['validated_meta'], $result['meta'] );
            $this->assertSame( 'stale_REG', $result['session']['kiriof_expedition'] );
            $this->assertSame( 0, $result['saves'] );
            $this->assertSame( 0, $result['customer_saves'] );
        }
    }

    #[Test]
    public function pending_transaction_replay_is_verified_before_success_cleanup(): void {
        $result = $this->runCase( 'pending' );
        $this->assertArrayNotHasKey( 'error', $result );
        $this->assertCount( 1, $result['transactions'] );
        $this->assertTrue( $result['transactions'][0]['blocks_validated'] );
        $this->assertSame( 1, $result['saves'] );
        $this->assertSame( $result['amount_before'], $result['amount_after'] );
    }

    #[Test]
    public function pending_transaction_cannot_hide_a_verification_conflict(): void {
        $result = $this->runCase( 'pending-conflict' );
        $this->assertSame( 503, $result['error']['status'] );
        $this->assertSame( 'kiriof_express_transaction_failed', $result['error']['code'] );
        $this->assertCount( 1, $result['transactions'] );
        $this->assertSame( $result['validated_meta'], $result['meta'] );
        $this->assertSame( 0, $result['saves'] );
    }

    #[Test]
    public function retry_reconstructs_context_from_durable_snapshots_after_transient_cleanup(): void {
        $result = $this->runCase( 'retry' );
        $this->assertSame( 503, $result['error']['status'] );
        $this->assertArrayNotHasKey( 'retry_error', $result );
        $this->assertCount( 2, $result['transactions'] );
        $retry = $result['transactions'][1];
        $this->assertSame( '123', $retry['kiriof_destination_area'] );
        $this->assertSame( 'Verified district', $retry['kiriof_destination_area_name'] );
        $this->assertSame( 'jne_REG', $retry['kiriof_expedition'] );
        $this->assertSame( 1, $retry['is_insurance'] );
        $this->assertFalse( $retry['is_cod'] );
        $this->assertSame( $result['transactions'][0]['validated_calculation'], $retry['validated_calculation'] );
        $this->assertSame( 1, $result['calls'] );
        $this->assertSame( 0, $result['customer_saves'] );
        $this->assertSame( 1, $result['saves'] );
    }

    #[Test]
    public function instant_and_third_party_shipping_are_ignored_by_express_hooks(): void {
        foreach ( array( 'instant', 'third-party' ) as $mode ) {
            $result = $this->runCase( $mode );
            $this->assertArrayNotHasKey( 'error', $result, $mode );
            $this->assertSame( 0, $result['calls'] );
            $this->assertSame( array(), $result['transactions'] );
            $this->assertArrayNotHasKey( '_kiriof_express_validated', $result['meta'] );
            $this->assertSame( 0, $result['customer_saves'] );
        }
    }
}
