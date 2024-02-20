<?php

namespace Inc\Repositories;

class TransactionRepository{
    
    public function getTransactionByOrderIds($orderIds){
        global $wpdb;
        $query = $wpdb->get_results( "SELECT * FROM wp_kiriminaja_transactions WHERE order_id IN ('".implode("', '", $orderIds)."')" );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getTransactionByOrderId($orderId){
        global $wpdb;
        $query = $wpdb->get_row( "SELECT * FROM wp_kiriminaja_transactions WHERE order_id  = '".$orderId."'");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getTransactionByPickupNumber($pickupNumber){
        global $wpdb;
        $query = $wpdb->get_results( "SELECT * FROM wp_kiriminaja_transactions WHERE pickup_number = '".$pickupNumber."'" );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function updateTransactionByCallback($payloads){
        global $wpdb;
        $wpdb->update('wp_kiriminaja_transactions', $payloads['changes'], $payloads['condition']);
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return true;
    }
    
    public function getTransactionDataByPickupNumber($pickupNumber){
        global $wpdb;
        $query = $wpdb->get_results( "SELECT * FROM wp_kiriminaja_transactions WHERE pickup_number = '".$pickupNumber."'");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
}