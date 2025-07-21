<?php

namespace Inc\Repositories;

class TransactionRepository{

    public $table;
    public function __construct(){
        global $wpdb;
        $this->table = $wpdb->prefix . 'kiriminaja_transactions';
    }
    
    public function getTransactionByOrderIds($orderIds){
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($orderIds), '%s'));
        
        $table = esc_sql($this->table);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                "SELECT * FROM {$wpdb->prefix}kiriminaja_transactions WHERE order_id IN (".implode(',', array_fill(0, count($orderIds), '%s')).")",
                ...$orderIds  // Meneruskan nilai $orderIds sebagai parameter untuk query
            )
        );

        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getTransactionByOrderId($orderId){
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_row( 
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE `order_id` = %s",
                $orderId // %s
            )
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getTransactionByWCOrderNumber($wp_wc_order_stat_order_id){
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE `wp_wc_order_stat_order_id`= %s",
                $wp_wc_order_stat_order_id
            )
        );
        
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getTransactionByWCOrderNumberForTracking($wp_wc_order_stat_order_id){
        global $wpdb;
        $transactionTable = $wpdb->prefix . 'kiriminaja_transactions';
        $wcTransactionTable = $wpdb->prefix . 'wc_order_stats';
        $postTable = $wpdb->prefix . 'posts';
        
        (new \Inc\Base\BaseInit())->logThis('$wp_wc_order_stat_order_id',[$wp_wc_order_stat_order_id]);
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT 
                    {$wpdb->prefix}kiriminaja_transactions.*, 
                    {$wpdb->prefix}wc_order_stats.date_paid as wc_date_paid,
                    {$wpdb->prefix}.post_status as wc_post_status
                FROM {$wpdb->prefix}kiriminaja_transactions
                INNER JOIN {$wpdb->prefix}wc_order_stats
                ON {$wpdb->prefix}kiriminaja_transactions.wp_wc_order_stat_order_id = {$wpdb->prefix}wc_order_stats.order_id
                INNER JOIN {$wpdb->prefix}.post_status
                ON {$wpdb->prefix}kiriminaja_transactions.wp_wc_order_stat_order_id = {$wpdb->prefix}.post_status.ID
                WHERE {$wpdb->prefix}kiriminaja_transactions.wp_wc_order_stat_order_id = %d
                AND {$wpdb->prefix}.post_status.post_status != %s
                GROUP BY {$wpdb->prefix}kiriminaja_transactions.wp_wc_order_stat_order_id",
                $wp_wc_order_stat_order_id,
                'trash'
            )
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }

    public function getTransactionByAWBforTracking($awb){
        global $wpdb;
        $transactionTable = $wpdb->prefix . 'kiriminaja_transactions';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $get_wc_orderid = $wpdb->get_row( 
            $wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT wp_wc_order_stat_order_id FROM `$transactionTable` WHERE `awb` LIKE %s OR `wp_wc_order_stat_order_id` LIKE %s",
                '%' . $awb . '%',
                '%' . $awb . '%'
            )
        );

        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        
        $wc_order_id = is_null($get_wc_orderid) ? '' : $get_wc_orderid->wp_wc_order_stat_order_id;

        $query = $this->getTransactionByWCOrderNumberForTracking( $wc_order_id );

        return $query;
    }
    
    public function getTransactionByPickupNumber($pickupNumber){
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_results( 
            $wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE pickup_number = %s",
                $pickupNumber
            )
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function updateTransactionByCallback($payloads){
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, $payloads['changes'], $payloads['condition']);
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return true;
    }
    
    public function getTransactionDataByPickupNumber($pickupNumber){
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_results( 
            $wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE pickup_number = %s",
                $pickupNumber
            )
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }

    public function getTransactionByWCOrderId($WCOrderId){
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_row( 
            $wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE wp_wc_order_stat_order_id = %d",
                $WCOrderId
            )
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function createTransaction($payload){
        /** Transaction Table Insert*/
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
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
                    `wp_wc_order_stat_order_id`
                ) 
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
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
                $payload['wp_wc_order_stat_order_id']
            )
        );

        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return true;
    }

    public function getTransactionByOldestDate(){
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_row( 
            $wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE created_at IS NOT NULL ORDER BY created_at ASC"
            )
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }

    public function getTransactionByOrderIdsForResiPrint($orderIds){
        global $wpdb;

        $transactionTable = $wpdb->prefix . 'kiriminaja_transactions';
        $wpPostTable = $wpdb->prefix . 'posts';
        $wcOrderProductLookupTable = $wpdb->prefix . 'wc_order_product_lookup';
        
        $placeholders = implode(', ', array_fill(0, count($orderIds), '%d'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_results( 
            $wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                "SELECT 
                    {$wpdb->prefix}kiriminaja_transactions.*,
                    {$wpdb->prefix}posts.post_excerpt as checkout_note,
                    count({$wpdb->prefix}wc_order_product_lookup.product_id) as item_count
                FROM {$wpdb->prefix}kiriminaja_transactions
                INNER JOIN {$wpdb->prefix}posts
                    ON {$wpdb->prefix}kiriminaja_transactions.wp_wc_order_stat_order_id = {$wpdb->prefix}posts.ID
                INNER JOIN {$wpdb->prefix}wc_order_product_lookup
                    ON {$wpdb->prefix}kiriminaja_transactions.wp_wc_order_stat_order_id = {$wpdb->prefix}wc_order_product_lookup.order_id
                WHERE {$wpdb->prefix}kiriminaja_transactions.order_id IN (".implode(', ', array_fill(0, count($orderIds), '%d')).")
                GROUP BY {$wpdb->prefix}kiriminaja_transactions.wp_wc_order_stat_order_id
                ",
                ...$orderIds // Masukkan nilai $orderIds ke dalam placeholder
            )
         );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }

    public function getTransctionByOrderIds($orderIds){
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_results($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            "SELECT * FROM {$wpdb->prefix}kiriminaja_transactions WHERE order_id IN (" . implode(', ', array_fill(0, count($orderIds), '%s')) . ")",
            ...$orderIds
        ));
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }

    public function getCountTransactionProcessNew(){
        global $wpdb;

        /** update query */
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_var(
            $wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared	
                "SELECT count(*) 
                FROM {$wpdb->prefix}kiriminaja_transactions tp 
                INNER JOIN {$wpdb->prefix}posts p 
                ON p.ID = tp.wp_wc_order_stat_order_id
                WHERE tp.status = %s AND p.post_status = %s",
                'new',          // Placeholder untuk tp.status
                'wc-processing' // Placeholder untuk p.post_status
            ) 
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }

    public function updateTransaction($payload){
        global $wpdb; 

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->query(
            $wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared	
                "UPDATE {$this->table} SET 
                    destination_sub_district_id = %d,
                    destination_sub_district = %s,
                    service = %s,
                    service_name = %s,
                    shipping_cost = %f,
                    insurance_cost = %f,
                    cod_fee = %f
                WHERE wp_wc_order_stat_order_id = %d",
                $payload['destination_sub_district_id'],
                $payload['destination_sub_district'],
                $payload['service'],
                $payload['service_name'],
                $payload['shipping_cost'],
                $payload['insurance_cost'],
                $payload['cod_fee'],
                $payload['wp_wc_order_stat_order_id']
            )
        );

        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return true;
    }
}