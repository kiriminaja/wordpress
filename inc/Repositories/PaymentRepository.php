<?php
namespace KiriminAjaOfficial\Repositories;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PaymentRepository{
    public $table;
    public function __construct(){
        global $wpdb;
        $this->table = $wpdb->prefix . 'kiriminaja_payments';
    }
    public function getPaymentById($id){
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM `{$this->table}` WHERE `id` = %d",
                $id
            ) 
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getPaymentByPaymentId($paymentId){
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_row( 
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE pickup_number = %s", 
                $paymentId
            )
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getPaymentByOldestDate(){
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_row( 
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE created_at IS NOT NULL ORDER BY created_at ASC"
            )
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    public function updatePaymentByCallback($payloads){
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, $payloads['changes'], $payloads['condition']);
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return true;
    }

    /**
     * Count of "Waiting for Payment" rows shown on the Shipment Process page.
     *
     * Mirrors the list query in templates/request-pickup/index.php which
     * groups by pickup_number, so we use COUNT(DISTINCT pickup_number) to
     * match what the merchant sees in the table when the "Waiting for
     * Payment" tab is selected (kiriminaja_payments.status = 'unpaid').
     */
    public function getCountUnpaid(){
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT COUNT(DISTINCT pickup_number) FROM {$this->table} WHERE status = %s",
                'unpaid'
            )
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(@$wpdb->last_error);
            return 0;
        }
        return (int) $count;
    }
    public function createPayment($payload){
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "INSERT INTO {$this->table} (
                `pickup_number`, 
                `status`, 
                `method`, 
                `order_amt`, 
                `pickup_schedule`, 
                `created_at`
                ) VALUES (%s, %s, %s, %s, %s, %s)",
                $payload['pickup_number'],  // %s
                $payload['status'],        // %s
                $payload['method'],        // %s
                $payload['order_amt'], // %s
                $payload['pickup_schedule'], // %s
                $payload['created_at']      // %s
            )
        );
        
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return true;
    }
}
