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

final class InstantDispatchClaimRuntimeTest extends TestCase {
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
        $wpdb = new InstantDispatchClaimWpdbFake( $changes );
        return array( new InstantDispatchClaimRepositoryFake(), $wpdb );
    }

    #[Test]
    public function competing_claims_have_only_one_winner_and_pre_api_release_allows_retry(): void {
        foreach ( array( 'gosend', 'grab_express' ) as $service ) {
            foreach ( array( null, '' ) as $empty ) {
                [ $repository, $database ] = $this->repository( array( 'service' => $service, 'instant_payment_id' => $empty, 'awb' => $empty ) );
                $competitor = new InstantDispatchClaimRepositoryFake();
                $this->assertTrue( $repository->claimInstantDispatch( 'KA-1' ) );
                $this->assertFalse( $competitor->claimInstantDispatch( 'KA-1' ) );
                $this->assertSame( 'pending', $database->row['status'] );
                $this->assertSame( 1, $repository->invalidations );
                $this->assertSame( 0, $competitor->invalidations );
                $this->assertCount( 2, $database->queries );
                $this->assertTrue( $repository->releaseInstantDispatch( 'KA-1' ) );
                $this->assertSame( 'new', $database->row['status'] );
                $this->assertFalse( $repository->releaseInstantDispatch( 'KA-1' ) );
                $this->assertSame( 2, $repository->invalidations );
                $this->assertTrue( $competitor->claimInstantDispatch( 'KA-1' ) );
            }
        }
    }

    #[Test]
    public function closed_booked_express_and_unsupported_shipments_cannot_be_claimed_or_released(): void {
        $blocked = array(
            array( 'instant_payment_id' => 'PAY-1' ),
            array( 'instant_payment_id' => '0' ),
            array( 'awb' => 'AWB-1' ),
            array( 'awb' => '0' ),
            array( 'instant_status_code' => 0 ),
            array( 'instant_status_code' => '0' ),
            array( 'instant_status_code' => 100 ),
            array( 'delivery_type' => 'express' ),
            array( 'delivery_type' => null ),
            array( 'service' => 'jne' ),
            array( 'service' => 'unsupported' ),
        );
        foreach ( array( 'claimInstantDispatch' => 'new', 'releaseInstantDispatch' => 'pending' ) as $method => $status ) {
            foreach ( $blocked as $changes ) {
                [ $repository, $database ] = $this->repository( array_replace( array( 'status' => $status ), $changes ) );
                $before = $database->row;
                $this->assertFalse( $repository->$method( 'KA-1' ), json_encode( $changes ) );
                $this->assertSame( $before, $database->row );
                $this->assertSame( 0, $repository->invalidations );
                $this->assertCount( 1, $database->queries );
            }
            foreach ( array( 'new', 'pending', 'shipped', 'canceled', 'closed', 'completed' ) as $wrongStatus ) {
                if ( $wrongStatus === $status ) {
                    continue;
                }
                [ $repository ] = $this->repository( array( 'status' => $wrongStatus ) );
                $this->assertFalse( $repository->$method( 'KA-1' ) );
                $this->assertSame( 0, $repository->invalidations );
            }
        }
    }

    #[Test]
    public function booking_metadata_prevents_release_even_when_status_remains_pending(): void {
        foreach ( array( 'instant_payment_id' => 'PAY-1', 'awb' => 'AWB-1', 'instant_status_code' => 0 ) as $field => $value ) {
            [ $repository, $database ] = $this->repository();
            $this->assertTrue( $repository->claimInstantDispatch( 'KA-1' ) );
            $database->row[ $field ] = $value;
            $this->assertFalse( $repository->releaseInstantDispatch( 'KA-1' ) );
            $this->assertSame( 'pending', $database->row['status'] );
            $this->assertSame( 1, $repository->invalidations );
        }
    }

    #[Test]
    public function false_zero_multiple_rows_and_database_errors_never_report_success_or_invalidate(): void {
        foreach ( array( 'claimInstantDispatch' => 'new', 'releaseInstantDispatch' => 'pending' ) as $method => $status ) {
            foreach ( array( array( false, '' ), array( 0, '' ), array( 2, '' ), array( false, 'write failed' ), array( 1, 'write failed' ) ) as [ $result, $error ] ) {
                [ $repository, $database ] = $this->repository( array( 'status' => $status ) );
                $database->forcedResult = $result;
                $database->last_error = $error;
                $this->assertFalse( $repository->$method( 'KA-1' ) );
                $this->assertSame( 0, $repository->invalidations );
                $this->assertCount( 1, $database->queries );
            }
        }
    }

    #[Test]
    public function string_ids_are_prepared_without_coercion_and_placeholders_match(): void {
        foreach ( array( '000123', "KA-' OR 1=1 -- %s", 'KA-雪' ) as $id ) {
            [ $repository, $database ] = $this->repository( array( 'order_id' => $id ) );
            $this->assertFalse( $repository->claimInstantDispatch( 'different-id' ) );
            $this->assertTrue( $repository->claimInstantDispatch( $id ) );
            $this->assertTrue( $repository->releaseInstantDispatch( $id ) );
            foreach ( $database->prepared as [ $sql, $args ] ) {
                $this->assertSame( 1, preg_match_all( '/%[sdf]/', $sql ) );
                $this->assertCount( 1, $args );
                $this->assertStringContainsString( 'WHERE order_id = %s', $sql );
            }
            $this->assertSame( array( $id ), $database->prepared[1][1] );
            $this->assertStringContainsString( "WHERE order_id = '" . str_replace( "'", "''", $id ) . "'", $database->queries[1] );
            $this->assertSame( 2, $repository->invalidations );
        }
    }
}

/** Observe cache invalidation without redefining WordPress functions shared by other tests. */
final class InstantDispatchClaimRepositoryFake extends TransactionRepository {
    public int $invalidations = 0;

    public function invalidateCouriersCache() {
        ++$this->invalidations;
    }
}

/** A stateful fake enforcing the actual UPDATE predicates, not a read-then-write workflow. */
final class InstantDispatchClaimWpdbFake {
    public string $prefix = 'wp_';
    public string $last_error = '';
    public array $row;
    public array $prepared = array();
    public array $queries = array();
    public $forcedResult = null;

    public function __construct( array $changes ) {
        $this->row = array_replace( array(
            'order_id' => 'KA-1',
            'delivery_type' => 'instant',
            'service' => 'gosend',
            'status' => 'new',
            'instant_payment_id' => null,
            'awb' => null,
            'instant_status_code' => null,
        ), $changes );
    }

    public function prepare( string $sql, ...$args ): string {
        if ( 1 !== preg_match_all( '/%[sdf]/', $sql ) || 1 !== count( $args ) ) {
            throw new RuntimeException( 'Placeholder/replacement mismatch.' );
        }
        $this->prepared[] = array( $sql, $args );
        return preg_replace_callback( '/%s/', static function () use ( $args ) {
            return "'" . str_replace( "'", "''", $args[0] ) . "'";
        }, $sql );
    }

    public function query( string $sql ) {
        $this->queries[] = $sql;
        if ( ! preg_match( "/^UPDATE wp_kiriminaja_transactions\s+SET status = '(pending|new)'\s+WHERE order_id = '((?:''|[^'])*)'\s+AND delivery_type = 'instant'\s+AND service IN \('gosend', 'grab_express'\)\s+AND status = '(new|pending)'\s+AND \(instant_payment_id IS NULL OR instant_payment_id = ''\)\s+AND \(awb IS NULL OR awb = ''\)\s+AND instant_status_code IS NULL$/", $sql, $match ) ) {
            throw new RuntimeException( 'Unexpected SQL or missing eligibility predicate: ' . $sql );
        }
        if ( null !== $this->forcedResult ) {
            return $this->forcedResult;
        }
        if ( $this->row['order_id'] !== str_replace( "''", "'", $match[2] )
            || 'instant' !== $this->row['delivery_type']
            || ! in_array( $this->row['service'], array( 'gosend', 'grab_express' ), true )
            || $match[3] !== $this->row['status']
            || ! in_array( $this->row['instant_payment_id'], array( null, '' ), true )
            || ! in_array( $this->row['awb'], array( null, '' ), true )
            || null !== $this->row['instant_status_code'] ) {
            return 0;
        }
        $this->row['status'] = $match[1];
        return 1;
    }
}
