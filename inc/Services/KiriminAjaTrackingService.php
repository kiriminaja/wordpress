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
 
        $transactionRepo = (new \Inc\Repositories\TransactionRepository())->getTransactionByAWBforTracking($this->order_number);

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
        
        $details = (array) ($repo['data']->details ?? $this->getDetailWcOrder($this->order_number) );
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
            'number_order'=>$transactionRepo->wp_wc_order_stat_order_id,
            'details' => $details,
            'histories'=>self::filteringHistories($histories)
        ];

        return self::success($response);
    }
    
    public function filteringHistories($histories){
        return array_map(function ($obj){
            $obj->created_at = gmdate('d F Y H:i',strtotime($obj->created_at));
            return $obj;
        },$histories);
    }

    public function getDetailWcOrder($order_number){
        $order = wc_get_order($order_number);

        if (!$order){
            return self::error([],'Transaksi tidak ditemukan');
        }


        if( !empty($order->get_meta('_shipping_kj_destination_name')) ){
            
            $destionation = explode(',', $order->get_meta('_shipping_kj_destination_name'));
            $city = $destionation[0].','.$destionation['1'].','.$destionation['2'];
            $province = $destionation['3'];
            
            $response = (object)[
                'awb' => '-',
                'service' => $order->get_shipping_method(),
                'destination'=>[
                    'name' => $order->get_shipping_first_name().' '.$order->get_shipping_last_name(),
                    'city'=>$city,
                    'province'=>$province,
                ],
            ];
        }else{
            $destionation = explode(',', $order->get_meta('_billing_kj_destination_name'));
            $city = $destionation[0].','.$destionation['1'].','.$destionation['2'];
            $province = $destionation['3'];
            
            $response = (object)[
                'awb' => '-',
                'service' => $order->get_shipping_method(),
                'destination'=>[
                    'name' => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
                    'city'=>$city,
                    'province'=>$province,
                ],
            ];
        }

        return $response;

    }
    
}