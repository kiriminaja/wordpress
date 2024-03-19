<?php

namespace Inc\Repositories;

class PaymentRepository{

    public $table;
    public function __construct(){
        global $wpdb;
        $this->table = $wpdb->prefix . 'kiriminaja_payments';
    }


    public function getPaymentById($id){
        global $wpdb;
        $query = $wpdb->get_row( "SELECT * FROM `".$this->table."` WHERE `id`  = ".$id."");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getPaymentByPaymentId($paymentId){
        global $wpdb;
        $query = $wpdb->get_row( "SELECT * FROM `".$this->table."` WHERE pickup_number  = '".$paymentId."'");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getPaymentByOldestDate(){
        global $wpdb;
        $query = $wpdb->get_row( "SELECT * FROM `".$this->table."` WHERE created_at IS NOT NULL ORDER BY created_at ASC");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }

    public function updatePaymentByCallback($payloads){
        global $wpdb;
        $wpdb->update($this->table, $payloads['changes'], $payloads['condition']);
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return true;
    }

    public function createPayment($payload){
        global $wpdb;
        $wpdb->query("INSERT INTO ".$this->table."
            (
            `pickup_number`, 
            `status`, 
            `method`, 
            `order_amt`, 
            `pickup_schedule`, 
            `created_at`
            )
            VALUES
            (
            '".$payload['pickup_number']."',
            '".$payload['status']."',
            '".$payload['method']."',
            '".$payload['order_amt']."',
            '".$payload['pickup_schedule']."',
            '".$payload['created_at']."'
            )
            ");

        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return true;
    }
    
}