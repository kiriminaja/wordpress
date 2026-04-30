<?php
namespace KiriminAjaOfficial\Repositories;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- All queries use wpdb::prepare() correctly, table names must be interpolated
class TransactionRepository{
    public $table;
    private $wpdb;
    
    public function __construct(){
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'kiriminaja_transactions';
    }
    
    /**
     * Check for database errors and log them
     * @return bool
     */
    private function hasError(){
        if (!empty($this->wpdb->last_error)) {
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis($this->wpdb->last_error);
            return true;
        }
        return false;
    }
    
    public function getTransactionByOrderIds($orderIds){
        if (empty($orderIds)) {
            return [];
        }
        
        $count = count($orderIds);
        $placeholders = implode(',', array_fill(0, $count, '%s'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $query = $this->wpdb->get_results(
            $this->wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE order_id IN ({$placeholders})",
                ...$orderIds
            )
        );
        return $this->hasError() ? false : $query;
    }
    
    public function getTransactionByOrderId($orderId){
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $query = $this->wpdb->get_row( 
            $this->wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE `order_id` = %s",
                $orderId
            )
        );
        
        return $this->hasError() ? false : $query;
    }
    
    public function getTransactionByWCOrderNumber($wp_wc_order_stat_order_id){
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $query = $this->wpdb->get_row(
            $this->wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE `wp_wc_order_stat_order_id` = %d",
                $wp_wc_order_stat_order_id
            )
        );
        
        return $this->hasError() ? false : $query;
    }
    
    public function getTransactionByWCOrderNumberForTracking($wp_wc_order_stat_order_id){
        $wp_wc_order_stat_order_id = absint( $wp_wc_order_stat_order_id );
        if ( 0 === $wp_wc_order_stat_order_id ) {
            return false;
        }

        $transactions_table = esc_sql( $this->table );
        $wc_stats_table     = esc_sql( $this->wpdb->prefix . 'wc_order_stats' );
        $posts_table        = esc_sql( $this->wpdb->posts );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT 
                    t.*, 
                    w.date_paid as wc_date_paid,
                    p.post_status as wc_post_status
                FROM {$transactions_table} t
                INNER JOIN {$wc_stats_table} w ON t.wp_wc_order_stat_order_id = w.order_id
                INNER JOIN {$posts_table} p ON w.order_id = p.ID
                WHERE t.wp_wc_order_stat_order_id = %d AND p.post_status != %s
                GROUP BY t.wp_wc_order_stat_order_id",
                $wp_wc_order_stat_order_id,
                'trash'
            )
        );
        
        return $this->hasError() ? false : $query;
    }
    public function getTransactionByAWBforTracking($awb){
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $get_wc_orderid = $this->wpdb->get_row( 
            $this->wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT wp_wc_order_stat_order_id FROM {$this->table} WHERE `awb` LIKE %s OR `wp_wc_order_stat_order_id` LIKE %s",
                '%' . $this->wpdb->esc_like($awb) . '%',
                '%' . $this->wpdb->esc_like($awb) . '%'
            )
        );
        if ($this->hasError() || !$get_wc_orderid) {
            return false;
        }
        return $this->getTransactionByWCOrderNumberForTracking($get_wc_orderid->wp_wc_order_stat_order_id);
    }
    
    public function getTransactionByPickupNumber($pickupNumber){
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $query = $this->wpdb->get_results( 
            $this->wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE pickup_number = %s",
                $pickupNumber
            )
        );
        
        return $this->hasError() ? false : $query;
    }
    
    public function updateTransactionByCallback($payloads){
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $this->wpdb->update($this->table, $payloads['changes'], $payloads['condition']);
        
        return !$this->hasError();
    }
    
    /**
     * Alias for getTransactionByPickupNumber
     * @deprecated Use getTransactionByPickupNumber instead
     */
    public function getTransactionDataByPickupNumber($pickupNumber){
        return $this->getTransactionByPickupNumber($pickupNumber);
    }
    public function getTransactionByWCOrderId($WCOrderId){
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $query = $this->wpdb->get_row( 
            $this->wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE wp_wc_order_stat_order_id = %d",
                $WCOrderId
            )
        );
        
        return $this->hasError() ? false : $query;
    }
    
    public function createTransaction($payload){
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $this->wpdb->query(
            $this->wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "INSERT INTO {$this->table} 
                (
                    `order_id`, 
                    `shipping_info`, 
                    `destination_sub_district_id`, 
                    `destination_sub_district`, 
                    `status`, 
                    `service`, 
                    `service_name`, 
                    `weight`, 
                    `width`, 
                    `height`, 
                    `length`, 
                    `shipping_cost`, 
                    `insurance_cost`, 
                    `cod_fee`, 
                    `transaction_value`, 
                    `created_at`, 
                    `wp_wc_order_stat_order_id`,
                    `discount_amount`,
                    `discount_percentage`
                ) 
                VALUES (%s, %s, %d, %s, %s, %s, %s, %d, %f, %f, %f, %f, %f, %f, %f, %s, %d, %f, %f)",
                $payload['order_id'],
                $payload['shipping_info'],
                $payload['destination_sub_district_id'],
                $payload['destination_sub_district'],
                $payload['status'],
                $payload['service'],
                $payload['service_name'],
                $payload['weight'],
                $payload['width'],
                $payload['height'],
                $payload['length'],
                $payload['shipping_cost'],
                $payload['insurance_cost'],
                $payload['cod_fee'],
                $payload['transaction_value'],
                $payload['created_at'],
                $payload['wp_wc_order_stat_order_id'],
                $payload['discount_amount'] ?? null,
                $payload['discount_percentage'] ?? null
            )
        );
        return !$this->hasError();
    }
    public function getTransactionByOldestDate(){
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = $this->wpdb->get_row(
            "SELECT * FROM {$this->table} WHERE created_at IS NOT NULL ORDER BY created_at ASC LIMIT 1"
        );
        
        return $this->hasError() ? false : $query;
    }
    public function getTransactionByOrderIdsForResiPrint($orderIds){
        if (empty($orderIds)) {
            return [];
        }
        
        $prefix = $this->wpdb->prefix;
        $count = count($orderIds);
        $placeholders = implode(', ', array_fill(0, $count, '%d'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $query = $this->wpdb->get_results( 
            $this->wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT 
                    t.*,
                    p.post_excerpt as checkout_note,
                    COUNT(w.product_id) as item_count
                FROM {$this->table} t
                INNER JOIN {$prefix}posts p ON t.wp_wc_order_stat_order_id = p.ID
                INNER JOIN {$prefix}wc_order_product_lookup w ON t.wp_wc_order_stat_order_id = w.order_id
                WHERE t.order_id IN ({$placeholders})
                GROUP BY t.wp_wc_order_stat_order_id",
                ...$orderIds
            )
         );
         
        return $this->hasError() ? false : $query;
    }
    /**
     * @deprecated Typo in method name, use getTransactionByOrderIds instead
     */
    public function getTransctionByOrderIds($orderIds){
        return $this->getTransactionByOrderIds($orderIds);
    }
    public function getCountTransactionProcessNew(){
        return $this->getCountByPostStatus( 'wc-processing' );
    }

    /**
     * Distinct order count for the Transactions list, restricted to rows
     * with `kiriminaja_transactions.status = 'new'` (i.e. not yet picked
     * up). Pass null/empty/'all' to count every status — used by the "All"
     * pill on the Transactions page so the badge stays in sync with the
     * rendered rows.
     */
    public function getCountByPostStatus( $postStatus ){
        $prefix = $this->wpdb->prefix;

        if ( null === $postStatus || '' === $postStatus || 'all' === $postStatus ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $query = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "SELECT COUNT(DISTINCT p.ID)
                    FROM {$prefix}posts p
                    INNER JOIN {$this->table} t
                        ON p.ID = t.wp_wc_order_stat_order_id
                    WHERE p.post_type = %s
                        AND p.post_status NOT IN ('trash','auto-draft')
                        AND t.status = %s",
                    'shop_order',
                    'new'
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $query = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "SELECT COUNT(DISTINCT p.ID)
                    FROM {$prefix}posts p
                    INNER JOIN {$this->table} t
                        ON p.ID = t.wp_wc_order_stat_order_id
                    WHERE p.post_status = %s
                        AND t.status = %s",
                    $postStatus,
                    'new'
                )
            );
        }

        return $this->hasError() ? 0 : (int) $query;
    }
    public function updateTransaction($payload){
        $updateData = [
            'destination_sub_district_id' => $payload['destination_sub_district_id'],
            'destination_sub_district' => $payload['destination_sub_district'],
            'service' => $payload['service'],
            'service_name' => $payload['service_name'],
            'shipping_cost' => $payload['shipping_cost'],
            'insurance_cost' => $payload['insurance_cost'],
            'cod_fee' => $payload['cod_fee'],
        ];
        
        // Add discount fields if provided
        if (isset($payload['discount_amount'])) {
            $updateData['discount_amount'] = $payload['discount_amount'];
        }
        if (isset($payload['discount_percentage'])) {
            $updateData['discount_percentage'] = $payload['discount_percentage'];
        }
        
        $where = ['wp_wc_order_stat_order_id' => $payload['wp_wc_order_stat_order_id']];
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $this->wpdb->update($this->table, $updateData, $where);
        return !$this->hasError();
    }
}