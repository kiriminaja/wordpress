<?php
use KiriminAjaOfficial\Services\CallbackHandlerService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

if ( ! function_exists( 'kiriof_log' ) ) {
    function kiriof_log( $level, $message, $context = array(), $source = null ) {
        $GLOBALS['kiriof_callback_test_logs'][] = compact( 'level', 'message', 'context', 'source' );
        return true;
    }
}

if ( ! function_exists( 'kiriof_helper' ) ) {
    function kiriof_helper() {
        return new CallbackHelperFake();
    }
}

if ( ! function_exists( 'wc_get_order' ) ) {
    function wc_get_order( $orderId ) {
        return $GLOBALS['kiriof_callback_test_orders'][ $orderId ] ?? false;
    }
}

if ( ! function_exists( 'remove_action' ) ) {
    function remove_action( $hook, $callback ) {
        $GLOBALS['kiriof_callback_test_hooks'][] = array( 'remove', $hook, $callback );
        return true;
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook, $callback ) {
        $GLOBALS['kiriof_callback_test_hooks'][] = array( 'add', $hook, $callback );
        return true;
    }
}

require_once PLUGIN_DIR . '/inc/Utils/ServiceResponse.php';
require_once PLUGIN_DIR . '/inc/Base/BaseService.php';
require_once PLUGIN_DIR . '/inc/Services/CallbackHandlerService.php';

final class CallbackHandlerRuntimeTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['kiriof_callback_test_logs'] = array();
        $GLOBALS['kiriof_callback_test_hooks'] = array();
        $GLOBALS['kiriof_callback_test_orders'] = array();
    }

    #[Test]
    public function processed_packages_updates_each_distinct_pickup(): void {
        $transactionRepository = new CallbackTransactionRepositoryFake(
            array(
                $this->transaction( 'ORDER-1', 'PICKUP-1' ),
                $this->transaction( 'ORDER-2', 'PICKUP-2' ),
            )
        );
        $paymentRepository = new CallbackPaymentRepositoryFake(
            array(
                'PICKUP-1' => $this->payment( 'PICKUP-1', 'cod', 'unpaid' ),
                'PICKUP-2' => $this->payment( 'PICKUP-2', 'top', 'unpaid' ),
            )
        );

        $response = $this->service( $transactionRepository, $paymentRepository )->call();

        $this->assertSame( 200, $response->status );
        $this->assertSame( array( 'ORDER-1', 'ORDER-2' ), $transactionRepository->updatedOrderIds );
        $this->assertSame( array( 'PICKUP-1', 'PICKUP-2' ), $paymentRepository->updatedPickupNumbers );
        $this->assertSame( 'paid', $paymentRepository->payments['PICKUP-1']->status );
        $this->assertSame( 'paid', $paymentRepository->payments['PICKUP-2']->status );
    }

    #[Test]
    public function processed_packages_returns_retryable_failure_for_partial_match(): void {
        $transactionRepository = new CallbackTransactionRepositoryFake(
            array( $this->transaction( 'ORDER-1', 'PICKUP-1' ) )
        );
        $paymentRepository = new CallbackPaymentRepositoryFake(
            array( 'PICKUP-1' => $this->payment( 'PICKUP-1', 'cod', 'unpaid' ) )
        );

        $response = $this->service( $transactionRepository, $paymentRepository )->call();

        $this->assertSame( 503, $response->status );
        $this->assertSame( array( 'ORDER-2' ), $response->data['unmatched_order_ids'] );
        $this->assertSame( array(), $transactionRepository->updatedOrderIds );
        $this->assertSame( array(), $paymentRepository->updatedPickupNumbers );
    }

    #[Test]
    public function processed_packages_returns_retryable_failure_when_write_is_not_verified(): void {
        $transactionRepository = new CallbackTransactionRepositoryFake(
            array(
                $this->transaction( 'ORDER-1', 'PICKUP-1' ),
                $this->transaction( 'ORDER-2', 'PICKUP-2' ),
            )
        );
        $transactionRepository->failedOrderIds = array( 'ORDER-2' );
        $paymentRepository = new CallbackPaymentRepositoryFake(
            array(
                'PICKUP-1' => $this->payment( 'PICKUP-1', 'cod', 'unpaid' ),
                'PICKUP-2' => $this->payment( 'PICKUP-2', 'cod', 'unpaid' ),
            )
        );

        $response = $this->service( $transactionRepository, $paymentRepository )->call();

        $this->assertSame( 503, $response->status );
        $this->assertSame( array( 'ORDER-2' ), $response->data['failed_order_ids'] );
        $this->assertSame( array(), $paymentRepository->updatedPickupNumbers );
        $this->assertSame( 'error', $GLOBALS['kiriof_callback_test_logs'][0]['level'] );
        $this->assertSame( array( 'ORDER-2' ), $GLOBALS['kiriof_callback_test_logs'][0]['context']['failed_order_ids'] );
    }

    #[Test]
    public function processed_packages_keeps_unpaid_qris_waiting(): void {
        $transactionRepository = new CallbackTransactionRepositoryFake(
            array(
                $this->transaction( 'ORDER-1', 'PICKUP-1' ),
                $this->transaction( 'ORDER-2', 'PICKUP-1' ),
            )
        );
        $paymentRepository = new CallbackPaymentRepositoryFake(
            array( 'PICKUP-1' => $this->payment( 'PICKUP-1', 'qris', 'unpaid' ) )
        );

        $response = $this->service( $transactionRepository, $paymentRepository )->call();

        $this->assertSame( 200, $response->status );
        $this->assertSame( 'unpaid', $paymentRepository->payments['PICKUP-1']->status );
        $this->assertSame( array(), $paymentRepository->updatedPickupNumbers );
    }

    #[Test]
    public function processed_packages_accepts_verified_idempotent_updates(): void {
        $transactionRepository = new CallbackTransactionRepositoryFake(
            array(
                $this->transaction( 'ORDER-1', 'PICKUP-1', 'AWB-1' ),
                $this->transaction( 'ORDER-2', 'PICKUP-2', 'AWB-2' ),
            )
        );
        $paymentRepository = new CallbackPaymentRepositoryFake(
            array(
                'PICKUP-1' => $this->payment( 'PICKUP-1', 'cod', 'paid' ),
                'PICKUP-2' => $this->payment( 'PICKUP-2', 'cod', 'paid' ),
            )
        );

        $response = $this->service( $transactionRepository, $paymentRepository )->call();

        $this->assertSame( 200, $response->status );
        $this->assertSame( array( 'ORDER-1', 'ORDER-2' ), $transactionRepository->updatedOrderIds );
        $this->assertSame( array( 'PICKUP-1', 'PICKUP-2' ), $paymentRepository->updatedPickupNumbers );
    }

    #[Test]
    public function processed_packages_returns_retryable_failure_when_payment_write_fails(): void {
        $transactionRepository = new CallbackTransactionRepositoryFake(
            array(
                $this->transaction( 'ORDER-1', 'PICKUP-1' ),
                $this->transaction( 'ORDER-2', 'PICKUP-1' ),
            )
        );
        $paymentRepository = new CallbackPaymentRepositoryFake(
            array( 'PICKUP-1' => $this->payment( 'PICKUP-1', 'cod', 'unpaid' ) )
        );
        $paymentRepository->failedPickupNumbers = array( 'PICKUP-1' );

        $response = $this->service( $transactionRepository, $paymentRepository )->call();

        $this->assertSame( 503, $response->status );
        $this->assertSame( array( 'PICKUP-1' ), $response->data['pickup_numbers'] );
    }

    #[Test]
    public function authorization_header_is_case_insensitive(): void {
        $transactionRepository = new CallbackTransactionRepositoryFake(
            array(
                $this->transaction( 'ORDER-1', 'PICKUP-1' ),
                $this->transaction( 'ORDER-2', 'PICKUP-1' ),
            )
        );
        $paymentRepository = new CallbackPaymentRepositoryFake(
            array( 'PICKUP-1' => $this->payment( 'PICKUP-1', 'qris', 'unpaid' ) )
        );
        $service = $this->service( $transactionRepository, $paymentRepository );
        $service->header( array( 'authorization' => 'secret' ) );

        $this->assertSame( 200, $service->call()->status );
    }

    #[Test]
    public function invalid_authorization_returns_non_retryable_unauthorized_status(): void {
        $transactionRepository = new CallbackTransactionRepositoryFake( array() );
        $paymentRepository     = new CallbackPaymentRepositoryFake( array() );
        $service               = $this->service( $transactionRepository, $paymentRepository );
        $service->header( array( 'Authorization' => 'Bearer wrong-secret' ) );

        $response = $service->call();

        $this->assertSame( 401, $response->status );
        $this->assertSame( 'Authorization failed', $response->message );
    }

    #[Test]
    #[DataProvider( 'packageEventProvider' )]
    public function package_event_updates_expected_transaction_and_order(
        string $method,
        array $packageData,
        array $expectedChanges,
        ?string $expectedOrderStatus,
        bool $expectsCancelHooks
    ): void {
        $transactionRepository = new CallbackTransactionRepositoryFake(
            array( $this->transaction( 'ORDER-1', 'PICKUP-1' ) )
        );
        $paymentRepository = new CallbackPaymentRepositoryFake( array() );
        $order = new CallbackOrderFake( 'processing' );
        $GLOBALS['kiriof_callback_test_orders'][1] = $order;

        $response = $this->eventService(
            $transactionRepository,
            $paymentRepository,
            $method,
            array( (object) array_merge( array( 'order_id' => 'ORDER-1' ), $packageData ) )
        )->call();

        $this->assertSame( 200, $response->status );
        foreach ( $expectedChanges as $field => $value ) {
            $this->assertSame( $value, $transactionRepository->transactions['ORDER-1']->{$field} );
        }
        $this->assertSame( $expectedOrderStatus, $order->updatedStatus );
        $this->assertCount( $expectsCancelHooks ? 2 : 0, $GLOBALS['kiriof_callback_test_hooks'] );
    }

    public static function packageEventProvider(): array {
        return array(
            'return finished' => array(
                'return_finished_packages',
                array( 'date' => '2026-07-30 10:00:00' ),
                array( 'return_finished_at' => '2026-07-30 10:00:00', 'status' => 'returned' ),
                'cancelled',
                false,
            ),
            'shipped' => array(
                'shipped_packages',
                array( 'shipped_at' => '2026-07-30 11:00:00' ),
                array( 'shipped_at' => '2026-07-30 11:00:00', 'status' => 'shipped' ),
                null,
                false,
            ),
            'finished' => array(
                'finished_packages',
                array( 'finished_at' => '2026-07-30 12:00:00' ),
                array( 'finished_at' => '2026-07-30 12:00:00', 'status' => 'finished' ),
                'completed',
                false,
            ),
            'returned' => array(
                'returned_packages',
                array( 'returned_at' => '2026-07-30 13:00:00' ),
                array( 'returned_at' => '2026-07-30 13:00:00', 'status' => 'return' ),
                null,
                false,
            ),
            'validated' => array(
                'validated_packages',
                array( 'shipping_cost' => '25000' ),
                array( 'shipping_cost' => '25000' ),
                null,
                false,
            ),
            'rejected' => array(
                'rejected_packages',
                array( 'rejected_at' => '2026-07-30 14:00:00', 'reason' => 'Invalid address' ),
                array( 'rejected_at' => '2026-07-30 14:00:00', 'rejected_reason' => 'Invalid address', 'status' => 'rejected' ),
                null,
                false,
            ),
            'canceled' => array(
                'canceled_packages',
                array( 'canceled_at' => '2026-07-30 15:00:00' ),
                array( 'canceled_at' => '2026-07-30 15:00:00', 'status' => 'canceled' ),
                'cancelled',
                true,
            ),
        );
    }

    private function service( $transactionRepository, $paymentRepository ): CallbackHandlerService {
        return $this->eventService(
            $transactionRepository,
            $paymentRepository,
            'processed_packages',
            array(
                (object) array( 'order_id' => 'ORDER-1', 'awb' => 'AWB-1' ),
                (object) array( 'order_id' => 'ORDER-2', 'awb' => 'AWB-2' ),
            )
        );
    }

    private function eventService( $transactionRepository, $paymentRepository, $method, $packages ): CallbackHandlerService {
        $body = (object) array(
            'method' => $method,
            'data'   => $packages,
        );

        return ( new CallbackHandlerService( $transactionRepository, $paymentRepository, 'secret' ) )
            ->header( array( 'Authorization' => 'Bearer secret' ) )
            ->body( $body );
    }

    private function transaction( $orderId, $pickupNumber, $awb = '' ): object {
        return (object) array(
            'order_id'                    => $orderId,
            'pickup_number'               => $pickupNumber,
            'awb'                         => $awb,
            'wp_wc_order_stat_order_id'   => 1,
        );
    }

    private function payment( $pickupNumber, $method, $status ): object {
        return (object) array(
            'pickup_number' => $pickupNumber,
            'method'        => $method,
            'status'        => $status,
        );
    }
}

final class CallbackHelperFake {
    public function dateConvertGMT( $date ) {
        return $date;
    }
}

final class CallbackOrderFake {
    public ?string $updatedStatus = null;
    private string $status;

    public function __construct( $status ) {
        $this->status = $status;
    }

    public function get_status() {
        return $this->status;
    }

    public function update_status( $status ) {
        $this->status        = $status;
        $this->updatedStatus = $status;
    }
}

final class CallbackTransactionRepositoryFake {
    public array $transactions;
    public array $failedOrderIds = array();
    public array $updatedOrderIds = array();

    public function __construct( $transactions ) {
        $this->transactions = array();
        foreach ( $transactions as $transaction ) {
            $this->transactions[ $transaction->order_id ] = $transaction;
        }
    }

    public function getTransactionByOrderIds( $orderIds ) {
        return array_values( array_intersect_key( $this->transactions, array_flip( $orderIds ) ) );
    }

    public function updateTransactionByCallbackVerified( $payload ) {
        $orderId = $payload['condition']['order_id'];
        if ( in_array( $orderId, $this->failedOrderIds, true ) ) {
            return false;
        }
        foreach ( $payload['changes'] as $field => $value ) {
            $this->transactions[ $orderId ]->{$field} = $value;
        }
        $this->updatedOrderIds[] = $orderId;
        return true;
    }
}

final class CallbackPaymentRepositoryFake {
    public array $payments;
    public array $failedPickupNumbers = array();
    public array $updatedPickupNumbers = array();

    public function __construct( $payments ) {
        $this->payments = $payments;
    }

    public function getPaymentByPaymentId( $pickupNumber ) {
        return $this->payments[ $pickupNumber ] ?? false;
    }

    public function updatePaymentByCallbackVerified( $payload ) {
        $pickupNumber = $payload['condition']['pickup_number'];
        if ( in_array( $pickupNumber, $this->failedPickupNumbers, true ) ) {
            return false;
        }
        foreach ( $payload['changes'] as $field => $value ) {
            $this->payments[ $pickupNumber ]->{$field} = $value;
        }
        $this->updatedPickupNumbers[] = $pickupNumber;
        return true;
    }
}
