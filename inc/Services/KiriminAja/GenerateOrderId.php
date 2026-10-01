<?php
namespace KiriminAjaOfficial\Services\KiriminAja;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;
use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Repositories\TransactionRepository;
class GenerateOrderId extends BaseService{
    private $prefix = '';
    private SettingRepository $setting_repository;
    private TransactionRepository $transaction_repository;

    public function __construct(
        SettingRepository $setting_repository,
        TransactionRepository $transaction_repository
    ) {
        $this->setting_repository     = $setting_repository;
        $this->transaction_repository = $transaction_repository;
    }
    
    
    public function call(){
        $repo = $this->setting_repository->getSettingByKey('oid_prefix');
        $this->prefix = @$repo->value ?? '';
        return $this->getOrderId();
    }
    
    public function getOrderId(){
        $orderId = $this->generateOrderId();
        $searchTransaction = $this->transaction_repository->getTransactionByOrderId($orderId);
        if ($searchTransaction){
            return $this->getOrderId();
        }
        return $orderId;
    }
    
    public function generateOrderId(){
        return $this->prefix.wp_rand(1000000000,9999999999);
    }
}