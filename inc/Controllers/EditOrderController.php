<?php
namespace Inc\Controllers;

class EditOrderController{


    public function register(){
        add_action( 'woocommerce_admin_order_totals_after_total', array($this,'addKjOrderDetail'));
    }

    public function addKjOrderDetail($order){
        /** This hook return when client side only, therefore cant do serverside php */
        
        $service = (new \Inc\Services\OrderEditPageServices\ShippingInfoServices())->wcOrderId($order)->call();
        if ($service->status !== 200){return;}
        
        $willBeReplaced = [
            '{$orderId}',
            '{$kjOrderData}'
        ];
        $replaceWith = [
            $order,
            json_encode($service->data)
        ];
        
        $content = file_get_contents(plugin_dir_path(dirname(__FILE__,2)). 'templates/order/edit.php');
        echo str_replace(
        $willBeReplaced, $replaceWith, $content);
    
    }
    
    
}