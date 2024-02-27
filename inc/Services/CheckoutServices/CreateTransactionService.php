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
            if (!$checkoutCalc['status']){ return self::error([],$checkoutCalc['message']);}
            $requiredPostMeta = self::getRequiredPostMeta();
            if (!$requiredPostMeta['status']){ return self::error([],$requiredPostMeta['message']);}

            (new \Inc\Base\BaseInit())->logThis('$checkoutCalc',[$checkoutCalc]);
            (new \Inc\Base\BaseInit())->logThis('$requiredPostMeta',[$requiredPostMeta]);
            (new \Inc\Base\BaseInit())->logThis('payload',[$this->payload]);
            
            
            /** Generating Payload*/
            $payload = [
                'order_id'                      => (new \Inc\Services\KiriminAja\GenerateOrderId())->call(),
                'shipping_info'                 => json_encode($requiredPostMeta['result']),
                'destination_sub_district_id'   => $this->payload['kj_destination_area'],
                'destination_sub_district'      => $this->payload['kj_destination_area_name'],
                'status'                        => 'new',
                'service'                       => explode('_',@$this->payload['kj_expedition'])[0] ?? '',
                'service_name'                  => explode('_',@$this->payload['kj_expedition'])[1] ?? '',
                'weight'                        => 1000,
                'width'                         => 20,
                'height'                        => 11,
                'length'                        => 100,
                'shipping_cost'                 => $checkoutCalc['result']['calculation_result']['ongkir_fee_amt'],
                'insurance_cost'                => $checkoutCalc['result']['calculation_result']['insurance_amt'],
                'cod_fee'                       => $checkoutCalc['result']['calculation_result']['cod_amt'],
                'transaction_value'             => $checkoutCalc['result']['calculation_result']['cart_total_amt'],
                'created_at'                    => date('Y-m-d H:i:s',strtotime("now")),
                'wp_wc_order_stat_order_id'     => $this->payload['order_id'],

            ];

            $createTransactionRepo = (new \Inc\Repositories\TransactionRepository())->createTransaction($payload);
            if (!$createTransactionRepo){
                return self::error([],'fail creating transaction');
            }
            return self::success([],'success');
        }catch (\Throwable $th){
            (new \Inc\Base\BaseInit())->logThis('err',[$th->getMessage()]);
            return self::error([],'fail creating transaction');
        }
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
                'result'    => $returnArr
            ];            
        }catch (\Throwable $th){
            return [
                'status'    => false,
                'msg'       => $th->getMessage(),
                'result'    => []
            ];
        }
    }
    
    private function getCheckoutCalculation(){
        $service = (new \Inc\Services\CheckoutServices\CheckoutCalculationService([
            'destination_area_id'   => $this->payload['kj_destination_area'],
            'expedition'            => $this->payload['kj_expedition'],
            'insurance'             => $this->payload['insurance'],
            'payment_method'        => $this->payload['payment_method'],
            'wc_cart_contents'      => $this->payload['wc_cart_contents'],
        ]))->call();
        if ($service->status !== 200){
            return [
                'status'    => false,
                'msg'       => @$service->message ?? 'Something is wrong',
                'result'    => []
            ];
        }

        return [
            'status'    => true,
            'msg'       => 'success',
            'result'    => $service->data
        ];

    }
    
    
}