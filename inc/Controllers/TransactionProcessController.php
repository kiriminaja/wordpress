<?php
namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Services\TransactionProcessServices\SendRequestPickupTransactionService;
use KiriminAjaOfficial\Services\TransactionProcessServices\CancelTransactionService;
class TransactionProcessController{
    public function register(){
        /** getPaymentForm */
        add_action('wp_ajax_kiriof_request_pickup_schedule', array($this,'getRequestPickupSchedule'));
        add_action('wp_ajax_kiriof_request_pickup_transaction', array($this,'sendRequestPickupTransaction'));
        add_action('wp_ajax_kiriof_transaction-detail-summary', array($this,'getTransactionDetailSummary'));
        add_action('wp_ajax_kiriof_cancel_transaction', array($this,'cancelTransaction'));

        /** Auto-cancel KA transaction when WC order is cancelled */
        add_action('woocommerce_order_status_cancelled', array($this, 'handleWcOrderCancelled'), 10, 1);
    }
    
    public function getRequestPickupSchedule(){
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
            wp_die();
        }
        // Check for nonce security - fail early
        if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
            wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
            wp_die();
        }
        $order_ids = ( isset($_POST['data']['order_ids']) && !empty($_POST['data']['order_ids']) 
            ? array_map('sanitize_text_field', wp_unslash($_POST['data']['order_ids']) ) 
            : [] 
        );
        $service = (new \KiriminAjaOfficial\Services\TransactionProcessServices\GetRequestPickupScheduleService())
            ->orderIds($order_ids)
            ->call();
        wp_send_json_success($service);
    }
    
    public function sendRequestPickupTransaction(){
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            // Check for nonce security - fail early
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }
            $order_ids = ( isset($_POST['data']['order_ids']) && !empty($_POST['data']['order_ids']) 
                ? array_map('sanitize_text_field', wp_unslash($_POST['data']['order_ids']) ) 
                : [] 
            );
            $schedule = ( isset($_POST['data']['schedule']) && !empty($_POST['data']['schedule']) 
                ? sanitize_text_field( wp_unslash( $_POST['data']['schedule'] ))  
                : '' 
            );
            $service = (new \KiriminAjaOfficial\Services\TransactionProcessServices\SendRequestPickupTransactionService())
                ->orderIds( $order_ids )
                ->schedule( $schedule )
                ->call();
            wp_send_json_success($service);
        }catch (\Throwable $th){
            wp_send_json_success([
                'status'    => 400,
                'message'   => $th->getMessage(),
            ]);
        }
       
    }
    
    
    public function getTransactionDetailSummary(){
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            // Check for nonce security - fail early
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }
            
            $wc_order_id = isset($_POST['data']['wc_order_id']) ? sanitize_text_field( wp_unslash($_POST['data']['wc_order_id']) ) : '';
            $service = (new \KiriminAjaOfficial\Services\TransactionProcessServices\GetTransactionDetailSummary())
                ->wcOrderId($wc_order_id)
                ->call();
            wp_send_json_success($service);
            
        }catch (\Throwable $th){
            wp_send_json_success([
                'status'    => 400,
                'message'   => $th->getMessage(),
            ]);
        }
    }

    public function cancelTransaction(){
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            // Check for nonce security
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $order_id = isset( $_POST['data']['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['data']['order_id'] ) ) : '';
            $reason   = isset( $_POST['data']['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['data']['reason'] ) ) : '';

            $service = ( new CancelTransactionService() )
                ->orderId( $order_id )
                ->reason( $reason )
                ->call();

            wp_send_json_success( $service );
        } catch ( \Throwable $th ) {
            wp_send_json_success( [
                'status'  => 400,
                'message' => $th->getMessage(),
            ] );
        }
    }

    public function handleWcOrderCancelled( $order_id ) {
        try {
            $transactionRepo = new \KiriminAjaOfficial\Repositories\TransactionRepository();
            $transaction     = $transactionRepo->getTransactionByWCOrderId( $order_id );

            if ( ! $transaction ) {
                return;
            }

            // Skip if already canceled or in a terminal status (e.g. webhook already handled it)
            $terminalStatuses = [ 'shipped', 'finished', 'returned', 'return', 'canceled' ];
            if ( in_array( $transaction->status, $terminalStatuses, true ) ) {
                return;
            }

            // Skip if no AWB — nothing to cancel on Mitra side
            if ( empty( $transaction->awb ) ) {
                return;
            }

            $reason = __( 'Pesanan dibatalkan dari WooCommerce', 'kiriminaja-official' );

            ( new CancelTransactionService() )
                ->orderId( $transaction->order_id )
                ->reason( $reason )
                ->call();
        } catch ( \Throwable $th ) {
            ( new \KiriminAjaOfficial\Base\BaseInit() )->logThis( 'handleWcOrderCancelled error', [ $th->getMessage() ] );
        }
    }
}