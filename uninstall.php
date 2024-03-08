<?php

/**
 * Trigger this file on Plugin uninstall 
 * 
 * @package Saksenengmu
 */

if ( ! defined('WP_UNINSTALL_PLUGIN')){ die; }


// Clear database storage data

global $wpdb;
$table_kiriminaja_transactions = $wpdb->prefix.'kiriminaja_transactions';
$table_kiriminaja_settings = $wpdb->prefix.'kiriminaja_settings';
$table_kiriminaja_transactions = $wpdb->prefix.'kiriminaja_transactions';

$wpdb->query("DROP TABLE IF EXISTS $table_kiriminaja_transactions");
$wpdb->query("DROP TABLE IF EXISTS $table_kiriminaja_settings");
$wpdb->query("DROP TABLE IF EXISTS $table_kiriminaja_transactions");
delete_option("my_plugin_db_version");

