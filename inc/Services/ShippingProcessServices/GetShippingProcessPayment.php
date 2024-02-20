<?php

namespace Inc\Services\ShippingProcessServices;

use Inc\Base\BaseService;

class GetShippingProcessPayment extends BaseService{
    
    public $payment_id = 0;
    private $transactionsSummary;
    
    public function payment_id($payment_id){
        $this->payment_id = $payment_id;
        return $this;
    }
    
    public function call(){
        $getPaymentRepo = (new \Inc\Repositories\KiriminajaApiRepository())->getPayment([
            'payment_id'=>$this->payment_id
        ]);
        self::transactionsSummaryProccess();
        
        return self::success([
            'payment_data'=>@$getPaymentRepo['data']->data,
            'count_cod'=>@$this->transactionsSummary['count_cod'],
            'sum_fee_cod'=>@$this->transactionsSummary['sum_fee_cod'],
            'sum_fee_non_cod'=>@$this->transactionsSummary['sum_fee_non_cod'],
            'expired_at'=>date('Y-m-d H:i:s',strtotime(@$getPaymentRepo['data']->data->created_at.'+5minutes')),
        ],'');
    }
    
    private function transactionsSummaryProccess(){
        $transactionRepo = (new \Inc\Repositories\TransactionRepository())->getTransactionByPickupNumber($this->payment_id);

        $count_cod = 0;
        $count_non_cod = 0;
        $sum_fee_cod = 0;
        $sum_fee_non_cod = 0;
        foreach ($transactionRepo as $transaction){
            if ($transaction->cod_fee===0){
                $count_cod+=1;
            }else{
                $count_non_cod+=1;
                $sum_fee_non_cod+=$transaction->shipping_cost;
                $sum_fee_non_cod+=$transaction->insurance_cost;
            }
        }
        
        $this->transactionsSummary['count_cod']=$count_cod;
        $this->transactionsSummary['count_non_cod']=$count_non_cod;
        $this->transactionsSummary['sum_fee_cod']=$sum_fee_cod;
        $this->transactionsSummary['sum_fee_non_cod']=$sum_fee_non_cod;
    }
    
    
}