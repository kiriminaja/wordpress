<?php

namespace Inc\Repositories;

class WpWcOrderStatRepository{

    public function updateOrderByCallback($payloads){
        global $wpdb;
        $wpdb->update('wp_wc_order_stats', $payloads['changes'], $payloads['condition']);
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return true;
    }
    
}