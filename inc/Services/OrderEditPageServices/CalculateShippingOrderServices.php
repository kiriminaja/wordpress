<?php
namespace Inc\Services\OrderEditPageServices;

use Inc\Base\BaseService;

class CalculateShippingOrderServices extends BaseService{
    public $wcOrderId = 0;
    public $items = array();
    public $itemsMeta = array();
    
    public function payloads($payloads){
        $this->wcOrderId = $payloads['wcOrderId'];
        $this->items = $payloads['items'];
        return $this;
    }
    
    public function getOrderData() {
        return wc_get_order($this->wcOrderId);
    }

    public function getlineItems(){
        return $this->items;
    }

    public function call(){
        
        $order = $this->getOrderData();
        if (!$order) { return self::error([],'Not Found');}

        return $this->calculateShippingOrder($order);
    }

    public function calculateShippingOrder($order){

        $items = $this->getlineItems();

        if( !$items ) { return self::error([],'Not Found'); }

        //get line items
        $expediton_cost = $items['kj_expedition_cost'] ?? 0;    
        $cek_insurance  = $items['insuranceChecklist'] ?? '';
        $cek_cod        = $items['codSelected'] ?? '';
        
        /**
         * get Quantity, Subtotal Woocommerce
         */
        $qty = 0; $subtotal = 0;
        foreach( $order->get_items() as $item_id_shipping => $item ) {
            $qty += (int) $item->get_quantity();
            $subtotal += $item->get_total();
        }
        
        $item_price     = (int) $expediton_cost * $qty;

        $fee_insurance  = !empty($cek_insurance) ? (float)$items['kj_insurancefee_hidden'] : 0;
        $fee_cod        = ($cek_cod === 'cod') ? (float)$items['kj_codfee_hidden'] : 0;
        
        ( new \Inc\Controllers\EditOrderController())->kj_calculationAdminOrder([
            'order_id' => $this->wcOrderId,
            'kj_subdistrict'=>$items['kj_subdistrict'],
            'kj_subdistrict_name'=>$items['kj_subdistrict_name'],
            'kj_expedition'=>$items['kj_expedition'],
            'kj_expedition_name'=>$items['kj_expedition_name'],
            'kj_expedition_cost'=>$item_price,
            'kj_insurancefee_hidden'=> $fee_insurance,
        ]);


        foreach( $order->get_items('shipping') as $item_id_shipping => $item ) {
            
            if( isset($items['kj_expedition_name']) ){
                $item->set_method_title( $items['kj_expedition_name'] ); 
                $item->set_name( $items['kj_expedition_name'] );
            }

            if( isset($item_price) ){
                $item->set_total( $item_price );
            }

            $item->calculate_taxes();
            $item->save();

        }   

        $order->calculate_shipping();
        $order->calculate_totals();

        //get fee woocommerce
        $wc_fee         = (float)$order->get_total_fees();            
        $order_total    = (float)$subtotal + (float)$fee_cod + (float)$fee_insurance;
        $order_totals   = (float)$item_price + $order_total + $wc_fee;
       
        $this->itemsMeta = [
            'order_totals' => $order_totals,
            'kj_subdistrict' => $items['kj_subdistrict'],
            'kj_subdistrict_name' => $items['kj_subdistrict_name'],
            'kj_expedition' => $items['kj_expedition'],
            'kj_expedition_name' => $items['kj_expedition_name'],
            'kj_expedition_cost' => $items['kj_expedition_cost'],
            'kj_insurancefee_hidden' => $items['kj_insurancefee_hidden'],
            'kj_codfee_hidden' => $items['kj_codfee_hidden'],
            'shipping_cost' => $item_price,
            'insurance_cost' => $fee_insurance,
            'cod_fee' => $fee_cod,
            'transaction_value' => ( $subtotal + $wc_fee ),
        ];

        self::updateMeta( $this->itemsMeta );
        self::updateStoreDBtransaction( $this->itemsMeta );
    }

    private function updateMeta(array $itemsMeta){
        $order_id = $this->wcOrderId;

        /* Update Total Order*/
        update_post_meta($order_id,'_order_total', $itemsMeta['order_totals']);
                    
        /* Simpan Di Post Meta */
        update_post_meta($order_id,'_kj_subdistrict_id',$itemsMeta['kj_subdistrict']);
        update_post_meta($order_id,'_kj_subdistrict_name',$itemsMeta['kj_subdistrict_name']);
        
        update_post_meta($order_id,'_kj_expedition_code',$itemsMeta['kj_expedition']);
        update_post_meta($order_id,'_kj_expedition_name',$itemsMeta['kj_expedition_name']);
        update_post_meta($order_id,'_kj_expedition_cost',$itemsMeta['kj_expedition_cost']);
                
        update_post_meta($order_id,'_kj_insurance_fee',$itemsMeta['kj_insurancefee_hidden']);
        update_post_meta($order_id,'_kj_cod_fee',$itemsMeta['kj_codfee_hidden']);
    }

    private function updateStoreDBtransaction(array $itemsMeta){

         /** Validasi Transaction*/
         $courier = explode('_',$itemsMeta['kj_expedition'] );
         $insurance = get_post_meta($this->wcOrderId,'_billing_kj_insurance',true);

         $transaction = (new \Inc\Repositories\TransactionRepository())->getTransactionByWCOrderId($this->wcOrderId);
         if( empty($transaction) ) return true;

         $payloads = [
             'destination_sub_district_id' =>(int) $itemsMeta['kj_subdistrict'],
             'destination_sub_district' => $itemsMeta['kj_subdistrict_name'],
             'service' =>$courier[0],
             'service_name' =>$courier[1],
             'shipping_cost' => $itemsMeta['shipping_cost'],
             'insurance_cost' => $itemsMeta['insurance_cost'],
             'cod_fee' => $itemsMeta['cod_fee'],
             'transaction_value' => $itemsMeta['transaction_value'],
             'wp_wc_order_stat_order_id'=>$this->wcOrderId,
         ];

         $updateTransactionRepo = (new \Inc\Repositories\TransactionRepository())->updateTransaction($payloads);

         (new \Inc\Base\BaseInit())->logThis('saveorder_updateTransactionRepo',[$updateTransactionRepo]);

    }
}

?>