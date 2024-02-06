<?php

namespace Inc\Services\ShippingProcessServices;

use Inc\Base\BaseService;
use Inc\Repositories\PaymentRepository;
use Inc\Repositories\TransactionRepository;

class GetShippingProcessDetailService extends BaseService{
    
    public $paymentId = 0;
    public $paymentRepo;
    public $transactionRepo;
    
    public $paymentCalcData = [
        'cod_count' => 0,
        'cod_sum' => 0,
        'non_cod_count' => 0,
        'non_cod_sum' => 0,
        'payment_amount' => 0,
    ];
    
    public function paymentId($paymentId){
        $this->paymentId=$paymentId;
        return $this;
    }
    
    public function call(){
        $this->paymentRepo = (new PaymentRepository())->getPaymentById($this->paymentId);
        if (!$this->paymentRepo) {
            return self::error([],'Server Error');
        }
        $this->transactionRepo = (new TransactionRepository())->getTransactionByPickupNumber($this->paymentRepo->pickup_number);
        if (!$this->transactionRepo) {
            return self::error([],'Server Error');
        }

        self::paymentSum();
        
        return self::success([
            'payment_data'=>[
                'pickup_number' =>  @$this->paymentRepo->pickup_number,
                'status'        =>  @$this->paymentRepo->status,
                'cod_count'   =>  $this->paymentCalcData['cod_count'],
                'cod_sum'   =>  $this->paymentCalcData['cod_sum'],
                'non_cod_count'   =>  $this->paymentCalcData['non_cod_count'],
                'non_cod_sum'   =>  $this->paymentCalcData['non_cod_sum'],
                'payment_amount'   =>  $this->paymentCalcData['payment_amount'],
            ],
            'transactions_data'=>$this->transactionRepo,
        ],'');
    }
    
    private function paymentSum(){
        if (count($this->transactionRepo)<=0){ return 0; }
        foreach ($this->transactionRepo as $transaction){
            if (intval(@$transaction->cod_fee) == 0){
                $this->paymentCalcData['non_cod_sum'] +=  intval(@$transaction->shipping_cost);
                $this->paymentCalcData['non_cod_sum'] +=  intval(@$transaction->insurance_cost);
                $this->paymentCalcData['non_cod_count'] += 1;
            }else{
                $this->paymentCalcData['cod_count'] += 1;
            }
        }
        $this->paymentCalcData['payment_amount'] = $this->paymentCalcData['non_cod_sum'];
    }
    
}