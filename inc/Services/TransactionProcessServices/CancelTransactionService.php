<?php
namespace KiriminAjaOfficial\Services\TransactionProcessServices;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;

class CancelTransactionService extends BaseService {

    private string $orderId = '';
    private string $reason  = '';

    public function orderId( string $orderId ) {
        $this->orderId = $orderId;
        return $this;
    }

    public function reason( string $reason ) {
        $this->reason = $reason;
        return $this;
    }

    public function call() {
        try {
            if ( empty( $this->orderId ) ) {
                return self::error( [], 'Order ID is required' );
            }

            if ( empty( $this->reason ) || mb_strlen( $this->reason ) < 5 ) {
                return self::error( [], 'Reason is required (minimum 5 characters)' );
            }

            if ( mb_strlen( $this->reason ) > 200 ) {
                return self::error( [], 'Reason is too long (maximum 200 characters)' );
            }

            $transactionRepo = new \KiriminAjaOfficial\Repositories\TransactionRepository();
            $transaction     = $transactionRepo->getTransactionByOrderId( $this->orderId );

            if ( ! $transaction ) {
                return self::error( [], 'Transaction not found' );
            }

            // Only allow cancel for transactions that haven't been shipped/finished/canceled yet
            $nonCancelableStatuses = [ 'shipped', 'finished', 'returned', 'return', 'canceled' ];
            if ( in_array( $transaction->status, $nonCancelableStatuses, true ) ) {
                return self::error( [], 'Transaction cannot be canceled (status: ' . $transaction->status . ')' );
            }

            // AWB is required — cancel is only for post-pickup transactions
            if ( empty( $transaction->awb ) ) {
                return self::error( [], 'Transaction has no AWB yet, cannot cancel shipment' );
            }

            // Call Mitra API to cancel the shipment
            $apiRepo  = new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository();
            $response = $apiRepo->cancelShipment( $transaction->awb, $this->reason );

            ( new \KiriminAjaOfficial\Base\BaseInit() )->logThis( 'cancelShipment', [ $response ] );

            if ( empty( $response['status'] ) ) {
                return self::error( [], $response['data'] ?? 'Failed to cancel shipment via API' );
            }

            // Update KA transaction table
            $canceledAt = gmdate( 'Y-m-d H:i:s' );
            $transactionRepo->updateTransactionByCallback( [
                'changes'   => [
                    'canceled_at' => kiriof_helper()->dateConvertGMT( $canceledAt ),
                    'status'      => 'canceled',
                ],
                'condition' => [
                    'order_id' => $this->orderId,
                ],
            ] );

            // Update WC order status to cancelled
            if ( ! empty( $transaction->wp_wc_order_stat_order_id ) ) {
                $order = wc_get_order( $transaction->wp_wc_order_stat_order_id );
                if ( $order && $order->get_status() !== 'cancelled' ) {
                    // Remove the hook temporarily to avoid infinite loop
                    remove_action( 'woocommerce_order_status_cancelled', [ 'KiriminAjaOfficial\\Controllers\\TransactionProcessController', 'handleWcOrderCancelled' ] );
                    $order->update_status( 'cancelled', __( 'Order cancelled via KiriminAja.', 'kiriminaja-official' ) );
                    add_action( 'woocommerce_order_status_cancelled', [ 'KiriminAjaOfficial\\Controllers\\TransactionProcessController', 'handleWcOrderCancelled' ] );
                }
            }

            return self::success( [
                'order_id' => $this->orderId,
            ], 'Transaction cancelled successfully' );

        } catch ( \Throwable $th ) {
            return self::error( [], $th->getMessage() );
        }
    }
}
