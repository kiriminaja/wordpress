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

    public function processSetupKey($payload){
        return $this->post('/api/service/api-request/integrate',[
            'setup_key'     => $payload['setup_key'],
            'callback_url'  => $payload['callback_url']
        ]);
    }

    public function getPayment($payload){
        return $this->post('/api/mitra/v2/get_payment',[
            'payment_id'     => $payload['payment_id']
        ]);
    }
    public function getTracking($payload){
        return $this->post('/api/mitra/tracking',[
            'order_id'     => $payload['order_id']
        ]);
    }
    
    public function getPricing($payload){
        return $this->post('/api/mitra/v6.1/shipping_price',[
            'subdistrict_origin'            => $payload['subdistrict_origin'],
            'subdistrict_destination'       => $payload['subdistrict_destination'],
            'weight'                        => $payload['weight'],
            'length'                        => $payload['length'],
            'width'                         => $payload['width'],
            'height'                        => $payload['height'],
            'insurance'                     => $payload['insurance'],
            'item_value'                    => $payload['item_value'],
            'courier'                       => $payload['courier']
        ]);
    }
    
    public function getRequestPickupSchedule(){
        return $this->post('/api/mitra/v2/schedules',);
    }

    public function sendPickupRequest($payload){
        return $this->post('/api/mitra/v6.1/request_pickup',$payload);
    }

    public function get_couriers(){
        return $this->post('/api/mitra/couriers');
    }    
}