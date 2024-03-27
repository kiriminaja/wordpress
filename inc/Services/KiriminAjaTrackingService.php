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
        $transactionRepo = (new \Inc\Repositories\TransactionRepository())->getTransactionByWCOrderNumberForTracking($this->order_number);
        if (!$transactionRepo){
            return self::error([],'Transaksi tidak ditemukan');
        }
        $repo = (new \Inc\Repositories\KiriminajaApiRepository())->getTracking([
            'order_id' => $transactionRepo->order_id
        ]);

        (new \Inc\Base\BaseInit())->logThis('pload',[
            '$transactionRepo' => $transactionRepo
        ]);
        (new \Inc\Base\BaseInit())->logThis('$repo',[$repo]);
        
        $histories = (array) (@$repo['data']->histories ?? []);
        
        if (@$transactionRepo->wc_date_paid && $transactionRepo->cod_fee == 0){
            $histories[] = (object)[
                "status"=> "Transaksi dikonfirmasi & diproses",
                "status_code"=> 100,
                "created_at"=> @$transactionRepo->wc_date_paid,
                "driver"=> "",
                "receiver"=> ""
            ];
        }
        
        $histories[] = (object)[
            "status"=> "Transaksi berhasil Check Out dengan metode pembayaran ".($transactionRepo->cod_fee>0 ? 'COD' : 'NON COD'),
            "status_code"=> 100,
            "created_at"=> $transactionRepo->created_at,
            "driver"=> "",
            "receiver"=> ""
        ];
        

        
        
        $response = (object)[
            'histories'=>self::filteringHistories($histories)
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