<?php
use KiriminAjaOfficial\Repositories\PaymentRepository;
use KiriminAjaOfficial\Repositories\TransactionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

require_once PLUGIN_DIR . '/inc/Repositories/TransactionRepository.php';
require_once PLUGIN_DIR . '/inc/Repositories/PaymentRepository.php';

final class CallbackRepositoryRuntimeTest extends TestCase {
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
    public function transaction_zero_row_update_is_valid_when_state_already_matches(): void {
        global $wpdb;
        $wpdb = new CallbackWpdbFake( 0, (object) array( 'order_id' => 'ORDER-1', 'awb' => 'AWB-1' ) );

        $verified = ( new TransactionRepository() )->updateTransactionByCallbackVerified(
            array(
                'changes'   => array( 'awb' => 'AWB-1' ),
                'condition' => array( 'order_id' => 'ORDER-1' ),
            )
        );

        $this->assertTrue( $verified );
    }

    #[Test]
    public function transaction_zero_row_update_fails_when_state_does_not_match(): void {
        global $wpdb;
        $wpdb = new CallbackWpdbFake( 0, (object) array( 'order_id' => 'ORDER-1', 'awb' => '' ) );

        $verified = ( new TransactionRepository() )->updateTransactionByCallbackVerified(
            array(
                'changes'   => array( 'awb' => 'AWB-1' ),
                'condition' => array( 'order_id' => 'ORDER-1' ),
            )
        );

        $this->assertFalse( $verified );
    }

    #[Test]
    public function payment_zero_row_update_is_valid_when_state_already_matches(): void {
        global $wpdb;
        $wpdb = new CallbackWpdbFake( 0, (object) array( 'pickup_number' => 'PICKUP-1', 'status' => 'paid' ) );

        $verified = ( new PaymentRepository() )->updatePaymentByCallbackVerified(
            array(
                'changes'   => array( 'status' => 'paid' ),
                'condition' => array( 'pickup_number' => 'PICKUP-1' ),
            )
        );

        $this->assertTrue( $verified );
    }
}

final class CallbackWpdbFake {
    public string $prefix = 'wp_';
    public string $last_error = '';
    private int $updateResult;
    private object $row;

    public function __construct( $updateResult, $row ) {
        $this->updateResult = $updateResult;
        $this->row          = $row;
    }

    public function update( $table, $changes, $condition ) {
        return $this->updateResult;
    }

    public function prepare( $query, ...$values ) {
        return $query;
    }

    public function get_row( $query ) {
        return $this->row;
    }
}
