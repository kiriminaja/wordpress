<?php
namespace Inc\Services\TransactionProcessServices;
use Inc\Base\BaseService;

class GetRequestPickupScheduleService extends BaseService {
    
    public array $orderIds = [];
    
    public function orderIds($orderIds){
        $this->orderIds = $orderIds;
        return $this;
    }
    
    public function call(){
        $scheduleRepo = (new \Inc\Repositories\KiriminajaApiRepository())->getRequestPickupSchedule();
        if (!@$scheduleRepo['status'] || !@$scheduleRepo['data']->status){
            return self::error([],@$scheduleRepo['data']->text ?? 'Something is wrong');
        }
        

        $transactionSummaryData = self::transactionSummaryData();
        
        return self::success([
            'schedules'                 => self::scheduleOptionFormatter(@$scheduleRepo['data']->schedules ?? []),
            'transaction_summary'       => $transactionSummaryData
        ],'success');
    }
    
    private function transactionSummaryData(){
        $transactions = (new \Inc\Repositories\TransactionRepository())->getTransactionByOrderIds($this->orderIds);
                
        $count_cod = 0;
        $count_non_cod = 0;
        $sum_fee_cod = 0;
        $sum_fee_non_cod = 0;
        foreach ($transactions as $transaction){
            if ((float)$transaction->cod_fee>0){
                $count_cod+=1;
            }else{
                $count_non_cod+=1;
                $sum_fee_non_cod    +=  $transaction->shipping_cost;
                $sum_fee_non_cod    +=  $transaction->insurance_cost;
            }
        }

        $array = [];
        $array['count_cod']         =   $count_cod;
        $array['count_non_cod']     =   $count_non_cod;
        $array['sum_fee_cod']       =   $sum_fee_cod;
        $array['sum_fee_non_cod']   =   $sum_fee_non_cod;
        return $array;
    }
    
    private function scheduleOptionFormatter($schedules){
        return array_map(function ($schedule){
            $schedule->label = gmdate('l, Y-m-d H:i',strtotime($schedule->clock));
            return $schedule;
        },$schedules);
    }
    
}