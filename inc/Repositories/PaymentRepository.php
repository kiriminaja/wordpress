<?php

namespace Inc\Repositories;

class PaymentRepository{
    
    public function getPaymentById($id){
        global $wpdb;
        $paymentTable = $wpdb->prefix . 'kiriminaja_payments';
        $query = $wpdb->get_row( "SELECT * FROM `".$paymentTable."` WHERE `id`  = ".$id."");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getPaymentByPaymentId($paymentId){
        global $wpdb;
        $paymentTable = $wpdb->prefix . 'kiriminaja_payments';
        $query = $wpdb->get_row( "SELECT * FROM `".$paymentTable."` WHERE pickup_number  = '".$paymentId."'");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getPaymentByOldestDate(){
        global $wpdb;
        $paymentTable = $wpdb->prefix . 'kiriminaja_payments';
        $query = $wpdb->get_row( "SELECT * FROM `".$paymentTable."` ORDER BY created_at ASC");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
}