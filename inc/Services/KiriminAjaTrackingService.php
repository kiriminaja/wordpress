<?php

namespace Inc\Services;

use \Inc\Base\BaseService;

class KiriminAjaTrackingService extends BaseService{
    
    public $order_number = '';
    
    function order_number($order_number){
        $this->order_number = $order_number;
        return $this;
    }
    
    public function call(){
        $transactionRepo = (new \Inc\Repositories\TransactionRepository())->getTransactionByWCOrderNumber($this->order_number);
        if (!$transactionRepo){
            return self::error([],'Transaksi tidak ditemukan');
        }
        $repo = (new \Inc\Repositories\KiriminajaApiRepository())->getTracking([
            'order_id' => $transactionRepo->order_id
        ]);
        
        if (!@$repo['status'] || !@$repo['data']->status){
            return self::error([],@$repo['data']->text ?? 'Something is wrong');
        }
        $response = (object)[
            'histories'=>self::filteringHistories((array) $repo['data']->histories)
        ];
        return self::success($response);
    }
    
    public function filteringHistories($histories){
        return array_map(function ($obj){
            $obj->created_at = date('d F Y',strtotime($obj->created_at));
            return $obj;
        },$histories);
    }
    
}