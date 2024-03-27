<?php

namespace Inc\Services\ShippingProcessServices;

use DateTime;
use DateTimeZone;
use Inc\Base\BaseService;

class GetShippingProcessPayment extends BaseService{
    
    public $payment_id = 0;
    private $transactionsSummary;
    private $timeZone = '';
    
    public function __construct(){
        $this->timeZone = wp_timezone_string();
    }
    
    public function payment_id($payment_id){
        $this->payment_id = $payment_id;
        return $this;
    }
    
    public function call(){
        $getKjPayment = (new \Inc\Repositories\KiriminajaApiRepository())->getPayment([
            'payment_id'=>$this->payment_id
        ]);
        if (!$getKjPayment['status']){ return  self::error([],@$getKjPayment['data'] ?? 'Terjadi Kesalahan');}
        
        $getPayment = (new \Inc\Repositories\PaymentRepository())->getPaymentByPaymentId($this->payment_id);
        
        self::transactionsSummaryProccess();
        return self::success([
            'payment_data'          =>  @$getKjPayment['data']->data,
            'payment_in_wc_data'    =>  @$getPayment,
            'count_cod'             =>  @$this->transactionsSummary['count_cod'],
            'sum_fee_cod'           =>  @$this->transactionsSummary['sum_fee_cod'],
            'sum_fee_non_cod'       =>  @$this->transactionsSummary['sum_fee_non_cod'],
            'created_at'            =>  date('Y-m-d H:i:s',strtotime(self::convertTimeToSettingTimezone(@$getKjPayment['data']->data->pay_time))),
            'expired_at'            =>  date('Y-m-d H:i:s',strtotime(self::convertTimeToSettingTimezone(@$getKjPayment['data']->data->pay_time).'+5minutes')),
        ],'');
    }
    
    private function transactionsSummaryProccess(){
        $transactionRepo = (new \Inc\Repositories\TransactionRepository())->getTransactionByPickupNumber($this->payment_id);

        $count_cod = 0;
        $count_non_cod = 0;
        $sum_fee_cod = 0;
        $sum_fee_non_cod = 0;
        foreach ($transactionRepo as $transaction){
            if (intval($transaction->cod_fee) > 0){
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
    
    private function convertTimeToSettingTimezone($dateTime){
        $dt = new DateTime("now", new DateTimeZone($this->timeZone));
        $dt->setTimestamp(strtotime($dateTime));
        $date = $dt->format('Y-m-d H:i:s');

        (new \Inc\Base\BaseInit())->logThis('$tz',[$this->timeZone]);
        (new \Inc\Base\BaseInit())->logThis('$dt',[$dt->format('Y-m-d H:i:s')]);
        
        return $date;
    }
    
    
}