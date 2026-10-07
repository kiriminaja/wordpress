<?php

declare(strict_types=1);

use KiriminAjaOfficial\Repositories\TransactionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}
require_once PLUGIN_DIR . '/inc/Contracts/TransactionPrintRepositoryInterface.php';
require_once PLUGIN_DIR . '/inc/Repositories/TransactionRepository.php';

final class InstantLifecycleRepositoryRuntimeTest extends TestCase {
    private $previousWpdb;

    protected function setUp(): void {
        global $wpdb;
        $this->previousWpdb = $wpdb ?? null;
    }

    protected function tearDown(): void {
        global $wpdb;
        $wpdb = $this->previousWpdb;
    }

    private function repository( array $changes = array() ): array {
        global $wpdb;
        $wpdb = new InstantLifecycleWpdbFake( $changes );
        return array( new InstantLifecycleRepositoryCacheSpy(), $wpdb );
    }

    private function expected(): array {
        return array( 'status' => 'pending', 'instant_status_code' => 100, 'instant_payment_status' => 'paid', 'instant_payment_id' => 'PAY-1', 'awb' => null );
    }

    #[Test]
    public function metadata_only_zero_row_write_cannot_bypass_any_snapshot_guard(): void {
        foreach ( array( 'status' => 'canceled', 'instant_status_code' => 350, 'instant_payment_status' => 'refunded', 'instant_payment_id' => 'PAY-2', 'awb' => '' ) as $field => $value ) {
            [ $repo, $db ] = $this->repository( array( $field => $value, 'rejected_reason' => 'already applied' ) );
            $this->assertFalse( $repo->compareAndSwapInstant( 'KA-1', $this->expected(), array( 'rejected_reason' => 'already applied' ) ), $field );
            $this->assertCount( 1, $db->updates );
            $this->assertSame( 1, $db->reads );
        }
    }

    #[Test]
    public function overwritten_expectations_allow_only_the_exact_idempotent_final_state(): void {
        [ $repo, $db ] = $this->repository();
        $changes = array( 'status' => 'request_pickup', 'instant_status_code' => '000101', 'instant_payment_status' => ' PAID ' );
        $this->assertTrue( $repo->compareAndSwapInstant( 'KA-1', $this->expected(), $changes ) );
        $this->assertSame( 0, $db->reads );
        $this->assertSame( 101, $db->updates[0][0]['instant_status_code'] );
        $db->row['instant_status_code'] = '101';
        $this->assertTrue( $repo->compareAndSwapInstant( 'KA-1', $this->expected(), $changes ) );
        $db->row['status'] = 'shipped';
        $this->assertFalse( $repo->compareAndSwapInstant( 'KA-1', $this->expected(), $changes ) );
        $this->assertSame( 'instant', $db->updates[0][1]['delivery_type'] );
    }

    #[Test]
    public function null_and_blank_are_distinct_for_changes_as_well_as_expectations(): void {
        foreach ( array( array( null, '' ), array( '', null ) ) as [ $stored, $incoming ] ) {
            [ $repo, $db ] = $this->repository( array( 'rejected_reason' => $stored ) );
            $db->forcedResult = 0;
            $this->assertFalse( $repo->compareAndSwapInstant( 'KA-1', $this->expected(), array( 'rejected_reason' => $incoming ) ) );
        }
    }

    #[Test]
    public function invalid_incoming_metadata_and_identity_edits_never_write(): void {
        $invalid = array(
            array( 'instant_status_code' => '1e2' ), array( 'instant_status_code' => -1 ),
            array( 'instant_status_code' => '2147483648' ), array( 'instant_payment_status' => 'unknown' ),
            array( 'instant_payment_method' => 'cash' ), array( 'instant_payment_id' => str_repeat( 'x', 101 ) ),
            array( 'live_tracking_url' => 'javascript:alert(1)' ), array( 'shipping_info' => array() ),
            array( 'order_id' => 'other' ), array( 'delivery_type' => 'express' ), array( 'service' => 'jne' ),
            array( 'wp_wc_order_stat_order_id' => 1 ), array( 'destination_latitude' => 1 ),
        );
        foreach ( $invalid as $changes ) {
            [ $repo, $db ] = $this->repository();
            $this->assertFalse( $repo->compareAndSwapInstant( 'KA-1', $this->expected(), $changes ) );
            $this->assertSame( array(), $db->updates );
            $this->assertSame( 0, $db->reads );
        }
        [ $repo, $db ] = $this->repository();
        $this->assertFalse( $repo->compareAndSwapInstant( 'KA-1', array( 'status' => 'pending' ), array( 'rejected_reason' => null ) ) );
        $this->assertSame( array(), $db->updates );
    }

    #[Test]
    public function expected_snapshot_is_not_normalized_and_express_never_matches(): void {
        [ $repo, $db ] = $this->repository();
        $expected = array_replace( $this->expected(), array( 'instant_payment_status' => ' PAID ' ) );
        $this->assertFalse( $repo->compareAndSwapInstant( 'KA-1', $expected, array( 'rejected_reason' => null ) ) );
        $this->assertSame( ' PAID ', $db->updates[0][1]['instant_payment_status'] );
        [ $repo ] = $this->repository( array( 'delivery_type' => 'express' ) );
        $this->assertFalse( $repo->compareAndSwapInstant( 'KA-1', $this->expected(), array( 'rejected_reason' => null ) ) );
    }

    #[Test]
    public function cancellation_is_one_atomic_claim_and_second_caller_cannot_send_delete(): void {
        foreach ( array( 'gosend', 'grab_express' ) as $service ) {
            foreach ( array( 100, 101, 105, 110 ) as $code ) {
                foreach ( array( null, '', 'AWB-1' ) as $awb ) {
                    [ $repo, $db ] = $this->repository( array( 'service' => $service, 'instant_status_code' => $code, 'awb' => $awb ) );
                    $competitor = new InstantLifecycleRepositoryCacheSpy();
                    $this->assertTrue( $repo->claimInstantCancellation( 'KA-1', $code ) );
                    $this->assertFalse( $competitor->claimInstantCancellation( 'KA-1', $code ) );
                    $this->assertSame( 350, $db->row['instant_status_code'] );
                    $this->assertSame( 'Instant cancellation requires reconciliation.', $db->row['rejected_reason'] );
                    $this->assertSame( 0, $db->reads );
                    $this->assertSame( 1, $repo->invalidations );
                    $this->assertSame( 0, $competitor->invalidations );
                }
            }
        }
    }

    #[Test]
    public function cancellation_requires_confirmed_booked_nonrefunded_eligible_state(): void {
        $blocked = array(
            array( 'status' => 'new' ), array( 'status' => 'shipped' ), array( 'status' => 'canceled' ),
            array( 'instant_status_code' => null ), array( 'instant_status_code' => 0 ), array( 'instant_status_code' => 350 ),
            array( 'instant_status_code' => 999 ), array( 'instant_payment_id' => null ), array( 'instant_payment_id' => '' ),
            array( 'instant_payment_status' => 'refunded' ), array( 'delivery_type' => 'express' ), array( 'service' => 'jne' ),
        );
        foreach ( $blocked as $changes ) {
            [ $repo, $db ] = $this->repository( $changes );
            $before = $db->row;
            $this->assertFalse( $repo->claimInstantCancellation( 'KA-1', 100 ) );
            $this->assertSame( $before, $db->row );
            $this->assertSame( 0, $repo->invalidations );
        }
        [ $repo ] = $this->repository();
        $this->assertFalse( $repo->claimInstantCancellation( 'KA-1', 101 ) );
        [ $repo ] = $this->repository( array( 'status' => 'request_pickup' ) );
        $this->assertTrue( $repo->claimInstantCancellation( 'KA-1', 100 ) );
    }

    #[Test]
    public function database_failures_and_non_single_claim_results_fail_closed(): void {
        foreach ( array( false, 0, 2, '1' ) as $result ) {
            [ $repo, $db ] = $this->repository();
            $db->forcedResult = $result;
            $this->assertFalse( $repo->claimInstantCancellation( 'KA-1', 100 ) );
            $this->assertSame( 0, $repo->invalidations );
        }
        [ $repo, $db ] = $this->repository();
        $db->forcedResult = false;
        $this->assertFalse( $repo->compareAndSwapInstant( 'KA-1', $this->expected(), array( 'rejected_reason' => null ) ) );
        $this->assertSame( 0, $db->reads );
    }

    #[Test]
    public function ids_are_exact_and_sql_placeholders_are_prepared_even_for_injection_strings(): void {
        foreach ( array( '000123', "KA-' OR 1=1 -- %s", 'KA-雪' ) as $id ) {
            [ $repo, $db ] = $this->repository( array( 'order_id' => $id ) );
            $this->assertFalse( $repo->claimInstantCancellation( 'different-id', 100 ) );
            $this->assertTrue( $repo->claimInstantCancellation( $id, 100 ) );
            $this->assertSame( array( $id, 100 ), $db->prepared[1][1] );
            foreach ( $db->prepared as [ $sql, $args ] ) {
                $this->assertSame( count( $args ), preg_match_all( '/%[sd]/', $sql ) );
            }
            [ $repo, $db ] = $this->repository( array( 'order_id' => $id ) );
            $this->assertTrue( $repo->compareAndSwapInstant( $id, $this->expected(), array( 'rejected_reason' => 'updated' ) ) );
            $this->assertSame( $id, $db->updates[0][1]['order_id'] );
            $this->assertFalse( $repo->compareAndSwapInstant( 'different-id', $this->expected(), array( 'rejected_reason' => 'updated' ) ) );
        }
    }
}

final class InstantLifecycleRepositoryCacheSpy extends TransactionRepository {
    public int $invalidations = 0;

    public function invalidateCouriersCache() {
        ++$this->invalidations;
    }
}

/** Stateful fake: enforce actual SQL shape and guards before changing any row. */
final class InstantLifecycleWpdbFake {
    public string $prefix = 'wp_';
    public string $last_error = '';
    public array $row;
    public array $updates = array();
    public array $prepared = array();
    public int $reads = 0;
    public $forcedResult = null;

    public function __construct( array $changes ) {
        $this->row = array_replace( array(
            'order_id' => 'KA-1', 'delivery_type' => 'instant', 'service' => 'gosend',
            'status' => 'pending', 'instant_status_code' => 100, 'instant_payment_status' => 'paid',
            'instant_payment_id' => 'PAY-1', 'awb' => null, 'rejected_reason' => null,
        ), $changes );
    }

    public function update( $table, array $changes, array $where ) {
        $this->updates[] = array( $changes, $where );
        if ( null !== $this->forcedResult ) {
            return $this->forcedResult;
        }
        foreach ( $where as $field => $value ) {
            if ( ! array_key_exists( $field, $this->row ) || ! $this->equal( $this->row[ $field ], $value ) ) {
                return 0;
            }
        }
        $changed = false;
        foreach ( $changes as $field => $value ) {
            $changed = $changed || ! array_key_exists( $field, $this->row ) || ! $this->equal( $this->row[ $field ], $value );
            $this->row[ $field ] = $value;
        }
        return $changed ? 1 : 0;
    }

    private function equal( $left, $right ): bool {
        return null === $left || null === $right ? $left === $right : (string) $left === (string) $right;
    }

    public function prepare( string $sql, ...$args ): string {
        if ( count( $args ) !== preg_match_all( '/%[sd]/', $sql ) ) {
            throw new RuntimeException( 'Placeholder/replacement mismatch.' );
        }
        $this->prepared[] = array( $sql, $args );
        $offset = 0;
        return preg_replace_callback( '/%[sd]/', static function ( $match ) use ( $args, &$offset ) {
            $value = $args[ $offset++ ];
            return '%d' === $match[0] ? (string) (int) $value : "'" . str_replace( "'", "''", $value ) . "'";
        }, $sql );
    }

    public function get_row( string $sql ) {
        ++$this->reads;
        if ( ! preg_match( "/WHERE `order_id` = '((?:''|[^'])*)'$/", $sql, $match ) ) {
            throw new RuntimeException( 'Unexpected read SQL.' );
        }
        return $this->row['order_id'] === str_replace( "''", "'", $match[1] ) ? (object) $this->row : null;
    }

    public function query( string $sql ) {
        $pattern = "/^UPDATE wp_kiriminaja_transactions\s+SET instant_status_code = 350,\s+rejected_reason = 'Instant cancellation requires reconciliation\.'\s+WHERE order_id = '((?:''|[^'])*)'\s+AND delivery_type = 'instant'\s+AND service IN \('gosend', 'grab_express'\)\s+AND status IN \('pending', 'request_pickup'\)\s+AND instant_status_code IN \(100, 101, 105, 110\)\s+AND instant_status_code = (-?[0-9]+)\s+AND instant_payment_id IS NOT NULL\s+AND instant_payment_id <> ''\s+AND \(instant_payment_status IS NULL OR instant_payment_status <> 'refunded'\)$/";
        if ( ! preg_match( $pattern, $sql, $match ) ) {
            throw new RuntimeException( 'Unexpected cancellation SQL or missing guard.' );
        }
        if ( null !== $this->forcedResult ) {
            return $this->forcedResult;
        }
        if ( $this->row['order_id'] !== str_replace( "''", "'", $match[1] )
            || 'instant' !== $this->row['delivery_type']
            || ! in_array( $this->row['service'], array( 'gosend', 'grab_express' ), true )
            || ! in_array( $this->row['status'], array( 'pending', 'request_pickup' ), true )
            || null === $this->row['instant_status_code']
            || ! in_array( (int) $this->row['instant_status_code'], array( 100, 101, 105, 110 ), true )
            || (int) $this->row['instant_status_code'] !== (int) $match[2]
            || in_array( $this->row['instant_payment_id'], array( null, '' ), true )
            || 'refunded' === $this->row['instant_payment_status'] ) {
            return 0;
        }
        $this->row['instant_status_code'] = 350;
        $this->row['rejected_reason'] = 'Instant cancellation requires reconciliation.';
        return 1;
    }
}
