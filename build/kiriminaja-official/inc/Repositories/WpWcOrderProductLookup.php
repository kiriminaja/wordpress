<?php
namespace KiriminAjaOfficial\Repositories;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WpWcOrderProductLookup{
    public $table;
    public function __construct(){
        global $wpdb;
        $this->table = $wpdb->prefix . 'wc_order_product_lookup';
    }
    
    public function getProductsCartDataByOrderId($orderId){
        global $wpdb;
        $wc_order_product_lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
        $post_meta_table = $wpdb->prefix . 'posts';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT 
                    {$wpdb->prefix}wc_order_product_lookup.order_id, 
                    {$wpdb->prefix}wc_order_product_lookup.product_qty, 
                    {$wpdb->prefix}wc_order_product_lookup.product_gross_revenue,
                    {$wpdb->prefix}wc_order_product_lookup.product_id, 
                    {$wpdb->prefix}posts.post_title as product_name
                FROM {$wpdb->prefix}wc_order_product_lookup
                INNER JOIN {$wpdb->prefix}posts
                    ON {$wpdb->prefix}wc_order_product_lookup.product_id = {$wpdb->prefix}posts.ID
                WHERE order_id = %d
                GROUP BY {$wpdb->prefix}wc_order_product_lookup.product_id
                ",
                $orderId // Placeholder untuk order_id
            )
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
}