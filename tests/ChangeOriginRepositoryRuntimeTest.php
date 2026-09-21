<?php

use KiriminAjaOfficial\Repositories\TransactionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

require_once PLUGIN_DIR . '/inc/Repositories/TransactionRepository.php';

final class ChangeOriginRepositoryRuntimeTest extends TestCase {
    private $previousWpdb;

    protected function setUp(): void {
        global $wpdb;
        $this->previousWpdb = $wpdb ?? null;
    }

    protected function tearDown(): void {
        global $wpdb;
        $wpdb = $this->previousWpdb;
    }

    #[Test]
    public function change_origin_persists_only_allowed_fields(): void {
        global $wpdb;
        $wpdb = new ChangeOriginWpdbFake(
            1,
            (object) array( 'order_id' => 'KA-1' )
        );

        $result = ( new TransactionRepository() )->updateTransactionShipmentLocation(
            'KA-1',
            22,
            '{"id":22}',
            array(
                'service'         => 'jne',
                'service_name'    => 'REG',
                'shipping_cost'   => 24000,
                'discount_amount' => 4000,
                'awb'             => 'MUST-NOT-BE-UPDATED',
                'status'          => 'processed',
            )
        );

        $this->assertTrue( $result );
        $this->assertSame(
            array(
                'shipment_location_id'       => 22,
                'shipment_location_snapshot' => '{"id":22}',
                'service'                    => 'jne',
                'service_name'               => 'REG',
                'shipping_cost'              => 24000,
                'discount_amount'            => 4000,
            ),
            $wpdb->lastChanges
        );
        $this->assertSame( array( 'order_id' => 'KA-1' ), $wpdb->lastCondition );
    }

    #[Test]
    public function failed_database_write_is_reported_as_failure(): void {
        global $wpdb;
        $wpdb = new ChangeOriginWpdbFake( false, null );

        $result = ( new TransactionRepository() )->updateTransactionShipmentLocation(
            'KA-2',
            3,
            '{"id":3}',
            array( 'service' => 'jnt', 'service_name' => 'EZ', 'shipping_cost' => 18000 )
        );

        $this->assertFalse( $result );
    }

    #[Test]
    public function zero_row_write_is_success_when_every_value_already_matches(): void {
        global $wpdb;
        $wpdb = new ChangeOriginWpdbFake(
            0,
            (object) array(
                'order_id'                   => 'KA-3',
                'shipment_location_id'       => 9,
                'shipment_location_snapshot' => '{"id":9}',
                'service'                    => 'sicepat',
                'service_name'               => 'REG',
                'shipping_cost'              => '17000',
                'discount_amount'            => '2000',
            )
        );

        $result = ( new TransactionRepository() )->updateTransactionShipmentLocation(
            'KA-3',
            9,
            '{"id":9}',
            array(
                'service'         => 'sicepat',
                'service_name'    => 'REG',
                'shipping_cost'   => 17000,
                'discount_amount' => 2000,
            )
        );

        $this->assertTrue( $result );
    }

    #[Test]
    public function zero_row_write_fails_for_missing_or_partially_mismatched_transaction(): void {
        global $wpdb;
        $wpdb = new ChangeOriginWpdbFake( 0, null );

        $repository = new TransactionRepository();
        $this->assertFalse(
            $repository->updateTransactionShipmentLocation(
                'KA-MISSING',
                5,
                '{"id":5}',
                array( 'service' => 'jne', 'service_name' => 'REG', 'shipping_cost' => 10000 )
            )
        );

        $wpdb->row = (object) array(
            'order_id'                   => 'KA-4',
            'shipment_location_id'       => 5,
            'shipment_location_snapshot' => '{"id":5}',
            'service'                    => 'jne',
            'service_name'               => 'REG',
            'shipping_cost'              => '9999',
        );

        $this->assertFalse(
            $repository->updateTransactionShipmentLocation(
                'KA-4',
                5,
                '{"id":5}',
                array( 'service' => 'jne', 'service_name' => 'REG', 'shipping_cost' => 10000 )
            )
        );
    }
}

final class ChangeOriginWpdbFake {
    public string $prefix = 'wp_';
    public string $last_error = '';
    public array $lastChanges = array();
    public array $lastCondition = array();
    public $row;
    private $updateResult;

    public function __construct( $updateResult, $row ) {
        $this->updateResult = $updateResult;
        $this->row          = $row;
    }

    public function update( $table, $changes, $condition ) {
        $this->lastChanges   = $changes;
        $this->lastCondition = $condition;
        return $this->updateResult;
    }

    public function prepare( $query, ...$values ) {
        foreach ( $values as $value ) {
            $replacement = is_numeric( $value ) ? (string) $value : "'" . (string) $value . "'";
            $query = preg_replace( '/%[dsf]/', $replacement, $query, 1 );
        }
        return $query;
    }

    public function get_row( $query ) {
        return $this->row;
    }
}
