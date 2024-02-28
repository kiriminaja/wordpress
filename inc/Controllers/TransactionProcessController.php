<?php
namespace Inc\Controllers;

use Inc\Services\TransactionProcessServices\SendRequestPickupTransactionService;

class TransactionProcessController{

    public function register(){
        /** getPaymentForm */
        add_action('wp_ajax_kj_request_pickup_schedule', array($this,'getRequestPickupSchedule'));
        add_action('wp_ajax_kj_request_pickup_transaction', array($this,'sendRequestPickupTransaction'));
    }
    
    public function getRequestPickupSchedule(){
        $service = (new \Inc\Services\TransactionProcessServices\GetRequestPickupScheduleService())
            ->orderIds(@$_POST['data']['order_ids'])
            ->call();
        wp_send_json_success($service);
    }
    
    public function sendRequestPickupTransaction(){
        try {
            $service = (new \Inc\Services\TransactionProcessServices\SendRequestPickupTransactionService())
                ->orderIds(@$_POST['data']['order_ids'])
                ->schedule(@$_POST['data']['schedule'] ?? '')
                ->call();
            wp_send_json_success($service);
        }catch (\Throwable $th){
            wp_send_json_success([
                'status'    => 400,
                'message'   => $th->getMessage(),
            ]);
        }
       
    }
    
}