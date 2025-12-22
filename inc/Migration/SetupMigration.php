<?php
namespace KiriminAjaOfficial\Migration;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SetupMigration {
    
    public $suffix = '';
    
    public function register(){
        self::settingsTable();
        self::transactionsTable();
        self::paymentsTable();
    }
    
    private function settingsTable(){
        global $wpdb;
        /** Settings Table*/
        $table_name = $wpdb->prefix.'kiriminaja_settings'.$this->suffix;
        
        /** Only create table if not exist */
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if(!($wpdb->get_var( "show tables like '$table_name'" ) == $table_name)){
            //phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
            $sql = "CREATE TABLE ".$table_name."(
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            `key` varchar(255) NULL,
            `value` varchar(255) NULL,
            UNIQUE KEY id (id)
            );";
            require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
            dbDelta($sql);
            /** Settings Table Value*/
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching	
            $wpdb->query(
                $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "INSERT INTO $table_name (`key`, `value`) VALUES
                    (%s, %s),
                    (%s, %s),
                    (%s, %s),
                    (%s, %s),
                    (%s, %s),
                    (%s, %s),
                    (%s, %s),
                    (%s, %s),
                    (%s, %s),
                    (%s, %s),
                    (%s, %s),
                    (%s, %s)",
                    'api_key', null,
                    'setup_key', null,
                    'oid_prefix', null,
                    'origin_name', null,
                    'origin_phone', null,
                    'origin_address', null,
                    'origin_sub_district_id', null,
                    'origin_sub_district_name', null,
                    'origin_latitude', null,
                    'origin_longitude', null,
                    'callback_url', null,
                    'origin_zip_code', null
                )
            );
        }
        
        /** Alters*/
    }
    
    /**
     * Updates 
     * [+] add Coloumn canceled_at
     * [+] add value cancaled in status
     */
    private function transactionsTable(){
        global $wpdb;
        /** Transactions Table*/
        $table_name = $wpdb->prefix.'kiriminaja_transactions'.$this->suffix;
        /** Only create table if not exist */
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if(!($wpdb->get_var( "show tables like '$table_name'" ) == $table_name)){
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
            $sql = "CREATE TABLE ".$table_name."(
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                `order_id` varchar(100) DEFAULT NULL,
                `shipping_info` text DEFAULT NULL,
                `destination_sub_district_id` int(11) DEFAULT NULL,
                `destination_sub_district` varchar(255) DEFAULT NULL,
                `pickup_number` varchar(100) DEFAULT NULL,
                `status` enum('new','request_pickup','pending','finished','shipped','return','returned','rejected','canceled') NOT NULL DEFAULT 'new',
                `service` varchar(50) DEFAULT NULL,
                `service_name` varchar(50) DEFAULT NULL,
                `awb` varchar(100) DEFAULT NULL,
                `rejected_reason` varchar(255) DEFAULT NULL,
                `weight` int(11) DEFAULT NULL,
                `width` double NOT NULL DEFAULT 0,
                `height` double NOT NULL DEFAULT 0,
                `length` double NOT NULL DEFAULT 0,
                `shipping_cost` double DEFAULT NULL,
                `insurance_cost` double DEFAULT NULL,
                `cod_fee` double DEFAULT NULL,
                `transaction_value` double DEFAULT NULL,
                `discount_amount` double DEFAULT NULL,
                `discount_percentage` double DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT NULL,
                `request_pickup_at` timestamp NULL DEFAULT NULL,
                `shipped_at` timestamp NULL DEFAULT NULL,
                `return_finished_at` timestamp NULL DEFAULT NULL,
                `finished_at` timestamp NULL DEFAULT NULL,
                `rejected_at` timestamp NULL DEFAULT NULL,
                `returned_at` timestamp NULL DEFAULT NULL,
                `canceled_at` timestamp NULL DEFAULT NULL,
                `wp_wc_order_stat_order_id` int(11) DEFAULT NULL,
                UNIQUE KEY id (id)
            );";
            require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
        }else{
             /** Alters*/
             // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching	
            $columns = $wpdb->get_col("DESCRIBE $table_name", 0);
            // Add 'canceled_at' column if it doesn't exist
            if (!in_array('canceled_at', $columns)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Migration: one-time schema modification, no caching needed
                $wpdb->query("ALTER TABLE $table_name ADD canceled_at timestamp NULL DEFAULT NULL");
            }
            // Ensure 'status' column has the correct enum values
            if (in_array('status', $columns)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Migration: one-time schema modification, no caching needed
                $wpdb->query("ALTER TABLE $table_name MODIFY COLUMN status enum('new','request_pickup','pending','finished','shipped','return','returned','rejected','canceled') NOT NULL DEFAULT 'new'");
            }
            // Add 'discount_amount' column if it doesn't exist
            if (!in_array('discount_amount', $columns)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Migration: one-time schema modification, no caching needed
                $wpdb->query("ALTER TABLE $table_name ADD discount_amount double DEFAULT NULL");
            }
            // Add 'discount_percentage' column if it doesn't exist
            if (!in_array('discount_percentage', $columns)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Migration: one-time schema modification, no caching needed
                $wpdb->query("ALTER TABLE $table_name ADD discount_percentage double DEFAULT NULL");
            }
            
        }
        /** Alters*/
    }
    
    private function paymentsTable(){
        global $wpdb;
        /** Payments Table*/
        $table_name = $wpdb->prefix.'kiriminaja_payments'.$this->suffix;
        
        /** Only create table if not exist */
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if(!($wpdb->get_var( "show tables like '$table_name'" ) == $table_name)){
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
            $sql = "CREATE TABLE ".$table_name."(
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            `pickup_number` varchar(100) DEFAULT NULL,
            `status` enum('paid','unpaid') DEFAULT NULL,
            `method` varchar(50) DEFAULT NULL,
            `order_amt` int(11) DEFAULT 1,
            `pickup_schedule` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT NULL,
            UNIQUE KEY id (id)
            );";
            require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        /** Alters*/
    }
}