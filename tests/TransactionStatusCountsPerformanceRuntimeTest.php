<?php

declare( strict_types=1 );

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TransactionStatusCountsPerformanceRuntimeTest extends TestCase {
    private function execute( array $options ): array {
        if ( ! extension_loaded( 'pdo_sqlite' ) ) {
            $this->markTestSkipped( 'Status count execution requires pdo_sqlite.' );
        }
        $options += array( 'warmups' => 0, 'samples' => 1 );
        $command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/transaction-status-counts-performance.php' ) . ' ' . escapeshellarg( json_encode( $options, JSON_THROW_ON_ERROR ) );
        return json_decode( (string) shell_exec( $command ), true, 512, JSON_THROW_ON_ERROR );
    }

    private function counts( array $values ): array {
        return array_combine( array( 'all', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-cancelled', 'processed', 'order-issue' ), $values );
    }

    #[Test]
    public function executed_counts_match_the_seven_query_baseline_in_both_storages_and_partitions(): void {
        // Golden values measured from HEAD before the aggregate replaced seven queries.
        foreach ( array( false, true ) as $empty_phone_cache ) {
            $express = $empty_phone_cache ? array( 11, 1, 1, 1, 3, 7, 3 ) : array( 8, 0, 1, 1, 3, 5, 6 );
            $instant = array( 12, 2, 1, 1, 4, 3, $empty_phone_cache ? 3 : 6 );
            foreach ( array( 40, 2000 ) as $orders ) {
                foreach ( array( false, true ) as $duplicates ) {
                    $result = $this->execute( compact( 'orders', 'duplicates', 'empty_phone_cache' ) );
                    foreach ( $result['results'] as $scope => $measurement ) {
                        $expected = str_ends_with( $scope, '_instant' ) ? $instant : $express;
                        $expected = array_map( static fn ( int $count ): int => $count * intdiv( $orders, 40 ), $expected );
                        $this->assertSame( $this->counts( $expected ), $measurement['counts'], $scope );
                        $this->assertSame( array( 1 ), $measurement['query_counts'], $scope );
                        $sql = $measurement['queries'][0];
                        $this->assertSame( 7, substr_count( $sql, 'COUNT(DISTINCT CASE WHEN' ) );
                        $this->assertStringNotContainsString( 'JOIN wp_kiriminaja_payments', $sql );
                        $this->assertStringNotContainsString( '%s', $sql );
                        $this->assertStringNotContainsString( '%d', $sql );
                    }
                }
            }
        }
    }

    #[Test]
    public function empty_tables_return_integer_zero_for_every_status_with_one_query(): void {
        foreach ( $this->execute( array( 'orders' => 0 ) )['results'] as $measurement ) {
            $this->assertSame( $this->counts( array_fill( 0, 7, 0 ) ), $measurement['counts'] );
            $this->assertSame( array( 1 ), $measurement['query_counts'] );
        }
    }
}
