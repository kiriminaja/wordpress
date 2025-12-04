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
        add_action('wp_ajax_kaj_transactions', [$this, 'getTransactions']);
    }
    
    public function getRequestPickupSchedule(){
        // Check for nonce security      
        if ( isset($_POST['data']['nonce']) && ! wp_verify_nonce(  sanitize_text_field( wp_unslash($_POST['data']['nonce'])), KJ_NONCE ) ) {
            wp_send_json_error(['status'=>400,'message'=>wc_add_notice('Security Check Kiriminaja', "error")]);
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
            // Check for nonce security      
            if ( isset($_POST['data']['nonce']) && ! wp_verify_nonce(  sanitize_text_field( wp_unslash($_POST['data']['nonce'])), KJ_NONCE ) ) {
                wp_send_json_error(['status'=>400,'message'=>wc_add_notice('Security Check Kiriminaja', "error")]);
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
            // Check for nonce security      
            if ( isset($_POST['data']['nonce']) && ! wp_verify_nonce(  sanitize_text_field( wp_unslash($_POST['data']['nonce'])), KJ_NONCE ) ) {
                wp_send_json_error(['status'=>400,'message'=>wc_add_notice('Security Check Kiriminaja', "error")]);
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

    public function getTransactions(){
        try {
            // Check for nonce security      
            if ( isset($_POST['data']['nonce']) && ! wp_verify_nonce(  sanitize_text_field( wp_unslash($_POST['data']['nonce'])), KJ_NONCE ) ) {
                wp_send_json_error(['status'=>400,'message'=>wc_add_notice('Security Check Kiriminaja', "error")]);
                wp_die();
            }

            $service = $this->pageQuery();

            wp_send_json_success($service);
        }catch (\Throwable $th){
            wp_send_json_success([
                'status'    => 400,
                'message'   => $th->getMessage(),
            ]);
        }
    }    

    private function pageQuery()
    {
        global $wpdb;

        /** Where Condition */
        $whereConditions = [];
        if (!empty(sanitize_text_field(wp_unslash($_GET['key'] ?? '')))) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $key = sanitize_text_field(wp_unslash($_GET['key'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $whereConditions[] = $wpdb->prepare("wc_order_stats.order_id LIKE %s", '%' . $wpdb->esc_like($key) . '%');
        }
        if (!empty(sanitize_text_field(wp_unslash($_GET['month'] ?? '')))) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $month = sanitize_text_field(wp_unslash($_GET['month'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $whereConditions[] = $wpdb->prepare("wc_order_stats.date_created LIKE %s", '%' . $wpdb->esc_like($month) . '%');
        }
        $whereCondition = !empty($whereConditions) ? ' AND ' . implode(' AND ', $whereConditions) : '';

        /** Main Query */
        $query = $wpdb->prepare("
            SELECT 
                wc_order_stats.order_id as wc_order_id,
                wc_order_stats.date_created as wc_date_created,
                wc_order_stats.status as wc_status,
                posts.post_status,
                kiriminaja_transactions.*
            FROM {$wpdb->prefix}wc_order_stats as wc_order_stats
            INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions
                ON wc_order_stats.order_id = kiriminaja_transactions.wp_wc_order_stat_order_id
            INNER JOIN {$wpdb->prefix}posts as posts
                ON wc_order_stats.order_id = posts.ID
            WHERE wc_order_stats.status = %s
                AND kiriminaja_transactions.status = %s
                AND posts.post_status != %s
                " . $whereCondition . "
            GROUP BY wc_order_stats.order_id
            ORDER BY wc_order_stats.date_created DESC
        ", 'wc-processing', 'new', 'trash');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results($query);

        if (!empty($wpdb->last_error)) {
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('last_error', $wpdb->last_error);
        }

        return ['results' => $results];
    }
}