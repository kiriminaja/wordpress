<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Trigger this file on Plugin uninstall 
 * 
 * @package Saksenengmu
 */

if ( ! defined('WP_UNINSTALL_PLUGIN')){ die; }


/** Clear database storage data 
 * Suspend this feature 
 */

//global $wpdb;
//$table_kiriminaja_transactions  = $wpdb->prefix.'kiriminaja_transactions';
//$table_kiriminaja_settings      = $wpdb->prefix.'kiriminaja_settings';
//$table_kiriminaja_payments      = $wpdb->prefix.'kiriminaja_payments';
//
//$wpdb->query("DROP TABLE IF EXISTS $table_kiriminaja_transactions");
//$wpdb->query("DROP TABLE IF EXISTS $table_kiriminaja_settings");
//$wpdb->query("DROP TABLE IF EXISTS $table_kiriminaja_payments");
//delete_option("my_plugin_db_version");
//
///** solve chached route*/
//try {
//    flush_rewrite_rules();
//}catch (\Throwable $th){}
