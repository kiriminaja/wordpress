<?php

use PHPUnit\Framework\TestCase;

final class ExpressTransactionRecoveryRuntimeTest extends TestCase {
    public function test_failed_insert_concurrent_worker_and_duplicate_replay_are_safe(): void {
        $output = array();
        $status = 0;
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/express-transaction-recovery-runtime.php' ) . ' 2>&1', $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        $result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( 2, $result['attempts'] );
        $this->assertSame( 1, $result['invoices'] );
        $this->assertSame( 1, $result['rows'] );
        $this->assertSame( 503, $result['race_status'] );
        $this->assertCount( 5, $result['rounding_cases'] );
        foreach ( $result['rounding_cases'] as $case ) {
            if ( $case['accepted'] ) {
                $this->assertSame( 200, $case['status'] );
                $this->assertSame( array(), $case['logs'] );
            } else {
                $this->assertSame( 503, $case['status'] );
                $this->assertSame( array( array( 'warning', 'Express shipment persistence failed.', array( 'operation' => 'save_express_transaction', 'code' => 'order_amount_mismatch', 'backtrace' => false ), 'kiriminaja_checkout' ) ), $case['logs'] );
            }
        }
    }
}
