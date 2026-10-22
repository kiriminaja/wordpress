<?php

use PHPUnit\Framework\TestCase;

final class UniqueTransactionLookupRuntimeTest extends TestCase {
    public function test_unique_lookup_contract_rejects_ambiguity_and_database_errors(): void {
        $output = array();
        $status = 0;
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/unique-transaction-lookup-runtime.php' ) . ' 2>&1', $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        $result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( 4, $result['queries'] );
    }
}
