<?php
namespace Inc\Controllers;

class TransactionProcessController{

    public function register(){
        /** getPaymentForm */
        add_action('wp_ajax_kj_request_pickup', array($this,'requestPickup'));
    }
    
    public function requestPickup(){
        wp_send_json_success(@$_POST);
        
    }
    
}