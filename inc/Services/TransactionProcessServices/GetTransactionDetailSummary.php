<?php

namespace Inc\Services\TransactionProcessServices;

use Inc\Base\BaseService;

class GetTransactionDetailSummary extends BaseService{
    
    private int $wcOrderId = 0;
    public function wcOrderId($wcOrderId){
        $this->wcOrderId = $wcOrderId;
        return $this;
    }
    
    public function call(){
        $transactionRepo = (new \Inc\Repositories\TransactionRepository())->getTransactionByWCOrderNumber($this->wcOrderId);
        if (!$transactionRepo){ return self::error([],'Terjadi Kesalahan'); }
        
        $cartProductRepo = (new \Inc\Repositories\WpWcOrderProductLookup())->getProductsCartDataByOrderId($this->wcOrderId);
        if (!$cartProductRepo){ return self::error([],'Terjadi Kesalahan'); }

        $shippingRepo = (new \Inc\Repositories\SettingRepository())->getSettingByArray(['origin_name','origin_phone','origin_address','origin_sub_district_id','origin_sub_district_name','origin_zip_code']);
        $originDataArr = [];
        foreach ($shippingRepo as $obj){
            $originDataArr[$obj->key]=$obj->value;
        }
        
        return self::success([
            'checkout_data'         => json_decode($transactionRepo->shipping_info),
            'payment'               => intval($transactionRepo->cod_fee) > 0 ? 'COD' : 'Transfer', 
            'expedition_service'    => strtoupper($transactionRepo->service).' '.strtoupper($transactionRepo->service_name),
            'cart_data'             => $cartProductRepo,
            'transaction_data'      => $transactionRepo,
            'status_label'          => kjHelper()->transactionStatusLabel(@$transactionRepo->status),
            'status_classes'        => kjHelper()->transactionStatusClass(@$transactionRepo->status),
        ]);
    }
    
    
}
