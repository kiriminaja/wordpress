<?php

namespace Inc\Repositories;

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
        $query = $wpdb->get_results( "
            SELECT ".$wc_order_product_lookup_table.".order_id, ".$wc_order_product_lookup_table.".product_qty, ".$wc_order_product_lookup_table.".product_gross_revenue , ".$wc_order_product_lookup_table.".product_id, ".$post_meta_table.".post_title as product_name 
            FROM ".$wc_order_product_lookup_table." 
            INNER JOIN `".$post_meta_table."`
            ON `".$wc_order_product_lookup_table."`.product_id = `".$post_meta_table."`.ID
            WHERE order_id = '".$orderId."'
            GROUP BY ".$wc_order_product_lookup_table.".product_id
            ");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
}