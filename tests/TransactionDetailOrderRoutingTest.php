<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TransactionDetailOrderRoutingTest extends TestCase {
    private function route( array $input ): array {
        $output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/transaction-detail-order-routing-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) );
        $this->assertNotNull( $output );
        return json_decode( $output, true, 512, JSON_THROW_ON_ERROR );
    }
    private function rows(): array {
        return array( array( 'id' => 104, 'wp_wc_order_stat_order_id' => 504 ), array( 'id' => 504, 'wp_wc_order_stat_order_id' => 700 ) );
    }
    public function test_woocommerce_id_opens_matching_order_not_overlapping_internal_id(): void {
        $r = $this->route( array( 'id' => '504', 'rows' => $this->rows() ) );
        $this->assertSame( 'rendered', $r['outcome'] );
        $this->assertSame( array( 504 ), $r['lookups'] );
        $this->assertSame( array( 'id' => 104, 'orderId' => 504 ), $r['bootstrap']['transaction'] );
        $r = $this->route( array( 'id' => '104', 'rows' => $this->rows() ) );
        $this->assertSame( 'redirect', $r['outcome'] );
        $this->assertNull( $r['bootstrap'] );
    }
    public function test_missing_duplicate_or_malformed_order_ids_fail_closed(): void {
        foreach ( array( '', '0', '-504', '0504', '504x', '504.0', ' 504', '999999999999999999999999', array( '504' ) ) as $id ) {
            $r = $this->route( array( 'id' => $id, 'rows' => $this->rows() ) );
            $this->assertSame( 'redirect', $r['outcome'] );
            $this->assertSame( array(), $r['lookups'] );
        }
        foreach ( array( array(), array_merge( $this->rows(), array( array( 'id' => 105, 'wp_wc_order_stat_order_id' => 504 ) ) ) ) as $rows ) {
            $this->assertSame( 'redirect', $this->route( array( 'id' => '504', 'rows' => $rows ) )['outcome'] );
        }
    }
    public function test_permission_precedes_lookup_and_fallback_logs_both_ids_correctly(): void {
        $r = $this->route( array( 'authorized' => false, 'id' => '504', 'rows' => $this->rows() ) );
        $this->assertSame( 'denied', $r['outcome'] );
        $this->assertSame( array(), $r['lookups'] );
        $r = $this->route( array( 'id' => '504', 'rows' => $this->rows(), 'bootstrap_fail' => true ) );
        $this->assertSame( 'rendered', $r['outcome'] );
        $this->assertSame( 504, $r['logs'][0]['wc_order_id'] );
        $this->assertSame( 104, $r['logs'][0]['transaction_id'] );
        $this->assertSame( 504, $r['bootstrap']['transaction']['orderId'] );
    }
    public function test_list_detail_links_use_woocommerce_id_for_regular_and_instant(): void {
        foreach ( array( 'express', 'instant' ) as $type ) {
            $input = array( 'id' => 104, 'wc_order_id' => 504, 'wp_wc_order_stat_order_id' => 504, 'delivery_type' => $type );
            $output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/transaction-instant-ui-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) );
            $row = json_decode( (string) $output, true, 512, JSON_THROW_ON_ERROR );
            $this->assertSame( 'admin.php?page=kiriminaja-transaction-detail&id=504', $row['detailUrl'] );
            $this->assertSame( 104, $row['id'] );
        }
    }
}
