<?php

namespace Inc\Services\KiriminAja;

use Inc\Base\BaseService;

class GenerateOrderId extends BaseService{
    private $prefix = '';
    
    
    public function call(){
        $repo = (new \Inc\Repositories\SettingRepository())->getSettingByKey('oid_prefix');
        $this->prefix = @$repo->value ?? '';
        return $this->getOrderId();
    }
    
    public function getOrderId(){
        $orderId = $this->generateOrderId();
        $searchTransaction = (new \Inc\Repositories\TransactionRepository())->getTransactionByOrderId($orderId);
        if ($searchTransaction){
            return $this->getOrderId();
        }
        return $orderId;
    }
    
    public function generateOrderId(){
        return $this->prefix.rand(1000000000,9999999999);
    }
    
    
}