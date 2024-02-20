<?php

namespace Inc\Repositories;

use Inc\Base\KiriminAjaApi;

class KiriminajaApiRepository extends KiriminAjaApi{

    public function sub_district_search($search)
    {
        return $this->get('/api/mitra/kelurahan_by_name?search='.$search);
    }

    public function setCallback($callbackUrl)
    {
        return $this->post('/api/mitra/set_callback',[
            'url'    => $callbackUrl,
            'status' => '1'
        ]);
    }

    public function processSetupKey($setupPayload){
        return $this->post('/api/service/api-request/integrate',[
            'setup_key'     => $setupPayload['setup_key'],
            'callback_url'  => $setupPayload['callback_url']
        ]);
    }

    public function getPayment($setupPayload){
        return [
            "status"=> true,
            "data"=> (object) [
                "status"=> true,
                "text"=> "Success",
                "method"=> "payment",
                "data"=> (object)[
                    "payment_id"=> "XID-5732095327",
                    "qr_content"=> "https://kiriminaja.com/",
                    "method"=> "08",
                    "pay_time"=> "20210712103644",
                    "status"=> "Billing berhasil dibuat",
                    "status_code"=> "9",
                    "amount"=> 65000,
                    "paid_at"=> null,
                    "created_at"=> "2021-07-12T03:35:42.000000Z"
                ]
            ]
        ];
        
        
        return $this->post('/api/mitra/v2/get_payment',[
            'payment_id'     => $setupPayload['payment_id']
        ]);
    }
    
}