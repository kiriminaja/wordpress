<?php

namespace Inc\Services\CheckoutServices;

use Inc\Base\BaseService;

class CreateTransactionService extends BaseService{
    
    private $payload;
    private $pricingData;
    
    
    /**
    $payload array
    keys :
    order_id
    checkout_post_data
    kj_destination_area
    kj_destination_area_name
    kj_expedition
    insurance
    payment_method
    wc_cart_contents
     */
    public function __construct($payload){
        $this->payload = $payload;
        return $this;
    }
    
    
    public function call(){
        try {
            
            $checkoutCalc = self::getCheckoutCalculation();
            
            if (!$checkoutCalc['status']){ 
                return self::error([],$checkoutCalc['message']);
            }
            
            $requiredPostMeta = self::getRequiredPostMeta();

            if (!$requiredPostMeta['status']){ return self::error([],$requiredPostMeta['message']);}

            /** Generating Payload*/
            $payload = [
                'order_id'                      => (new \Inc\Services\KiriminAja\GenerateOrderId())->call(),
                'shipping_info'                 => wp_json_encode($requiredPostMeta['data']),
                'destination_sub_district_id'   => $this->payload['kj_destination_area'],
                'destination_sub_district'      => $this->payload['kj_destination_area_name'],
                'status'                        => 'new',
                'service'                       => explode('_',@$this->payload['kj_expedition'])[0] ?? '',
                'service_name'                  => explode('_',@$this->payload['kj_expedition'])[1] ?? '',
                'weight'                        => $checkoutCalc['data']['carts_attribute']['weight'],
                "length"                        => $checkoutCalc['data']['carts_attribute']['length'],
                "width"                         => $checkoutCalc['data']['carts_attribute']['width'],
                "height"                        => $checkoutCalc['data']['carts_attribute']['height'],
                'shipping_cost'                 => $checkoutCalc['data']['calculation_result']['ongkir_fee_amt'],
                'insurance_cost'                => $this->payload['checkout_post_data']['kj_insurance'] ? $checkoutCalc['data']['calculation_result']['insurance_amt'] : 0,
                'cod_fee'                       => $checkoutCalc['data']['calculation_result']['cod_amt'],
                'transaction_value'             => $checkoutCalc['data']['calculation_result']['cart_total_amt'],
                'created_at'                    => gmdate('Y-m-d H:i:s',strtotime("now")),
                'wp_wc_order_stat_order_id'     => $this->payload['order_id'],

            ];
            
            /** Update WC Total Order */
            self::updateWcTotalOrder();
            
            $createTransactionRepo = (new \Inc\Repositories\TransactionRepository())->createTransaction($payload);
            
            /** Save in Log Transaction*/
            update_post_meta( $this->payload['order_id'], 'log_after_checkout_order', compact('payload','createTransactionRepo') );
            
            if (!$createTransactionRepo){
                return self::error([],'fail creating transaction');
            }
            return self::success([],'success');
        }catch (\Throwable $th){
            (new \Inc\Base\BaseInit())->logThis('err',[$th->getMessage()]);
            return self::error([],'fail creating transaction');
        }
    }

    private function updateWcTotalOrder(){

        $order = wc_get_order($this->payload['order_id']);
        $total_order = $order->get_total();

        $checkoutCalc = self::getCheckoutCalculation();
            
        if (!$checkoutCalc['status']){ 
            return self::error([],$checkoutCalc['message']);
        }

        $is_insurance = $this->payload['is_insurance'] ?? 0;
        $is_cod = $this->payload['is_cod'] ?? 0;
        
        if( $is_cod ){
            $total_order += $checkoutCalc['data']['calculation_result']['cod_amt'];
        }

        if( $is_insurance  ){
            $total_order += $checkoutCalc['data']['calculation_result']['insurance_amt'];
        }

        $order->set_total($total_order);
        $order->save();
    }
    
    private function getRequiredPostMeta(){
        try {
            $postMetaRepo = (new \Inc\Repositories\WpPostMetaRepository())->getRequiredRowsByPostId($this->payload['order_id']);
            (new \Inc\Base\BaseInit())->logThis('$postMetaRepo',[$postMetaRepo]);
            
            $returnArr=[];
            foreach ($postMetaRepo as $postMeta){
                $returnArr[$postMeta->meta_key]=$postMeta->meta_value;
            }
            return [
                'status'    => true,
                'msg'       => 'success',
                'data'    => $returnArr
            ];            
        }catch (\Throwable $th){
            return [
                'status'    => false,
                'msg'       => $th->getMessage(),
                'data'    => []
            ];
        }
    }
    
    private function getCheckoutCalculation(){
        $service = (new \Inc\Services\CheckoutServices\CheckoutCalculationService([
            'destination_area_id'   => $this->payload['kj_destination_area'],
            'expedition'            => $this->payload['kj_expedition'],
            'is_insurance'          => $this->payload['kj_insurance'] ?? 0,
            'is_cod'                => $this->payload['is_cod'],
            'wc_cart_contents'      => $this->payload['wc_cart_contents'],
        ]))->call();
        if ($service->status !== 200){
            return [
                'status'    => false,
                'msg'       => @$service->message ?? 'Something is wrong',
                'data'    => []
            ];
        }

        return [
            'status'    => true,
            'msg'       => 'success',
            'data'    => $service->data
        ];

    }
    
    
}