<?php
namespace KiriminAjaOfficial\Repositories;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WpWcOrderStatRepository{
    public $table;
    public function __construct(){
        global $wpdb;
        $this->table = $wpdb->prefix . 'wc_order_stats';
    }
    public function updateOrderByCallback($payloads){
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, $payloads['changes'], $payloads['condition']);
        if (strlen($wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis($wpdb->last_error);
            return false;
        }
        return true;
    }
}