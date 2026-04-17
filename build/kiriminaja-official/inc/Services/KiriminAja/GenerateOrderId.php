<?php
namespace KiriminAjaOfficial\Services\KiriminAja;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;
class GenerateOrderId extends BaseService{
    private $prefix = '';
    
    
    public function call(){
        $repo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('oid_prefix');
        $this->prefix = @$repo->value ?? '';
        return $this->getOrderId();
    }
    
    public function getOrderId(){
        $orderId = $this->generateOrderId();
        $searchTransaction = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getTransactionByOrderId($orderId);
        if ($searchTransaction){
            return $this->getOrderId();
        }
        return $orderId;
    }
    
    public function generateOrderId(){
        return $this->prefix.wp_rand(1000000000,9999999999);
    }
}