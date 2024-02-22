<?php

namespace Inc\Migration;

class SetupMigration {
    
    public $suffix = '_test';
    
    public function register(){
        self::settingsTable();
        self::transactionsTable();
        self::paymentsTable();
    }
    
    private function settingsTable(){

        global $wpdb;

        /** Settings Table*/
        $table_name = $wpdb->prefix.'kiriminaja_settings'.$this->suffix;
        /** Delete if table exist */
        if($wpdb->get_var( "show tables like '$table_name'" ) == $table_name ){
            $sql = "DROP TABLE IF EXISTS $table_name";
            $wpdb->query($sql);
            delete_option("my_plugin_db_version");
        }
        $sql = "CREATE TABLE ".$table_name."(
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            `key` varchar(255) NULL,
            `value` varchar(255) NULL,
            UNIQUE KEY id (id)
            );";
        require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
        dbDelta($sql);

        /** Settings Table Value*/
        $wpdb->query("INSERT INTO ".$table_name."
            (`key`, `value`)
            VALUES
            ('api_key', null),
            ('setup_key', null),
            ('oid_prefix', null),
            ('origin_name', null),
            ('origin_phone', null),
            ('origin_address', null),
            ('origin_sub_district_id', null),
            ('origin_sub_district_name', null),
            ('origin_latitude', null),
            ('origin_longitude', null),
            ('callback_url', null)
            ");
        
    }
    private function transactionsTable(){


        global $wpdb;

        /** Transactions Table*/
        $table_name = $wpdb->prefix.'kiriminaja_transactions'.$this->suffix;
        /** Delete if table exist */
        if($wpdb->get_var( "show tables like '$table_name'" ) == $table_name ){
            $sql = "DROP TABLE IF EXISTS $table_name";
            $wpdb->query($sql);
            delete_option("my_plugin_db_version");
        }
        $sql = "CREATE TABLE ".$table_name."(
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            `order_id` varchar(100) DEFAULT NULL,
            `shipping_info` text DEFAULT NULL,
            `pickup_number` varchar(100) DEFAULT NULL,
            `status` enum('pending','finished','shipped','return','returned','rejected') NOT NULL DEFAULT 'pending',
            `service` varchar(50) DEFAULT NULL,
            `service_name` varchar(50) DEFAULT NULL,
            `awb` varchar(100) DEFAULT NULL,
            `rejected_reason` varchar(255) DEFAULT NULL,
            `weight` int(11) DEFAULT NULL,
            `shipping_cost` double DEFAULT NULL,
            `insurance_cost` double DEFAULT NULL,
            `cod_fee` double DEFAULT NULL,
            `transaction_value` double DEFAULT NULL,
            `shipped_at` timestamp NULL DEFAULT NULL,
            `return_finished_at` timestamp NULL DEFAULT NULL,
            `finished_at` timestamp NULL DEFAULT NULL,
            `rejected_at` timestamp NULL DEFAULT NULL,
            `returned_at` timestamp NULL DEFAULT NULL,
            `wp_wc_order_stat_order_id` int(11) DEFAULT NULL,
            UNIQUE KEY id (id)
            );";
        require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
    }
    private function paymentsTable(){

        global $wpdb;

        /** Payments Table*/
        $table_name = $wpdb->prefix.'kiriminaja_payments'.$this->suffix;
        /** Delete if table exist */
        if($wpdb->get_var( "show tables like '$table_name'" ) == $table_name ){
            $sql = "DROP TABLE IF EXISTS $table_name";
            $wpdb->query($sql);
            delete_option("my_plugin_db_version");
        }
        $sql = "CREATE TABLE ".$table_name."(
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            pickup_number varchar(100) NULL,
            status varchar(50) NULL,
            `method` varchar(50) NULL,
            UNIQUE KEY id (id)
            );";
        require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
    }
    
}