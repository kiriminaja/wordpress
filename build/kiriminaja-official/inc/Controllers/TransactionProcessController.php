<?php
namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Services\TransactionProcessServices\SendRequestPickupTransactionService;
class TransactionProcessController{
    public function register(){
        /** getPaymentForm */
        add_action('wp_ajax_kj_request_pickup_schedule', array($this,'getRequestPickupSchedule'));
        add_action('wp_ajax_kj_request_pickup_transaction', array($this,'sendRequestPickupTransaction'));
        add_action('wp_ajax_kj_transaction-detail-summary', array($this,'getTransactionDetailSummary'));
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
}