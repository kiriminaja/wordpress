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
//        return [
//            "status"=> true,
//            "data"=> (object)[
//                "status"=> true,
//                "text"=> "Delivered to BAGUS | 14-07-2021 16=>00 | YOGYAKARTA ",
//                "method"=> "shTracking",
//                "status_code"=> 200,
//                "details"=> (object)[
//                    "awb"=> "DEVEL-000000004",
//                    "signature_code"=> "C1OJWQAG",
//                    "order_id"=> "OID-8793949106",
//                    "status_code"=> null,
//                    "estimation"=> "-",
//                    "service"=> "jne",
//                    "service_name"=> "REG",
//                    "drop"=> false,
//                    "shipped_at"=> "2021-07-13 17=>44=>04",
//                    "delivered"=> true,
//                    "delivered_at"=> "2021-10-17 16=>53=>00",
//                    "refunded"=> false,
//                    "refunded_at"=> "",
//                    "images"=> (object)[
//                        "camera_img"=> "https=>//s3-ap-southeast-1.amazonaws.com/pod.paket.id/1626253243482P||1411922100004643.jpeg",
//                        "signature_img"=> "https=>//s3-ap-southeast-1.amazonaws.com/pod.paket.id/1626253255242S||1411922100004643.jpeg",
//                        "pop_img"=> null
//                    ],
//                    "costs"=> (object)[
//                        "add_cost"=> 0,
//                        "currency"=> "IDR",
//                        "cod"=> 0,
//                        "insurance_amount"=> 0,
//                        "insurance_percent"=> 0,
//                        "discount_amount"=> 0,
//                        "subsidi_amount"=> 0,
//                        "shipping_cost"=> 10000,
//                        "correction"=> 0
//                    ],
//                    "origin"=> (object)[
//                        "name"=> "KiriminAja",
//                        "address"=> "Jl. Utara Stadion No.8, Jetis, Wedomartani",
//                        "phone"=> "628000000",
//                        "city"=> "Kabupaten Sleman",
//                        "zip_code"=> "55283"
//                    ],
//                    "destination"=> (object)[
//                        "name"=> "Zainal Arifin",
//                        "address"=> "Ngaglik RT. 32 Pendowoharjo Sewon Bantul Yogyakarta 55185",
//                        "phone"=> "6287839087416",
//                        "city"=> "Kabupaten Bantul",
//                        "zip_code"=> "55715"
//                    ]
//                ],
//                "histories"=> (object)[
//                    (object)[
//                        "created_at"=> "2021-07-14 16:00:00",
//                        "status"=> "Delivered to BAGUS | 14-07-2021 16=>00 | YOGYAKARTA ",
//                        "status_code"=> 200,
//                        "driver"=> "",
//                        "receiver"=> "BAGUS"
//                    ],
//                    (object)[
//                        "created_at"=> "2021-07-14 09:53:00",
//                        "status"=> "With delivery courier YOGYAKARTA",
//                        "status_code"=> 100,
//                        "driver"=> "",
//                        "receiver"=> ""
//                    ],
//                    (object)[
//                        "created_at"=> "2021-07-14 00:02:00",
//                        "status"=> "Received at inbound station YOGYAKARTA - KP. GAMBIRAN",
//                        "status_code"=> 100,
//                        "driver"=> "",
//                        "receiver"=> ""
//                    ],
//                    (object)[
//                        "created_at"=> "2021-07-13 20:44:00",
//                        "status"=> "Shipment forwarded to destination YOGYAKARTA - KP. GAMBIRAN",
//                        "status_code"=> 100,
//                        "driver"=> "",
//                        "receiver"=> ""
//                    ],
//                    (object)[
//                        "created_at"=> "2021-07-13 18:34:00",
//                        "status"=> "Received at sorting center YOGYAKARTA",
//                        "status_code"=> 100,
//                        "driver"=> "",
//                        "receiver"=> ""
//                    ],
//                    (object)[
//                        "created_at"=> "2021-07-13 17:44:00",
//                        "status"=> "Shipment received by jne counter officer at YOGYAKARTA",
//                        "status_code"=> 100,
//                        "driver"=> "",
//                        "receiver"=> ""
//                    ],
//                    (object)[
//                        "status"=> "Paket dibuat oleh KiriminAja",
//                        "status_code"=> 100,
//                        "created_at"=> "2021-07-07 14:40:42",
//                        "driver"=> "",
//                        "receiver"=> ""
//                    ]
//                ]
//            ]
//        ];
        
        
        return $this->post('/api/mitra/tracking',[
            'order_id'     => $payload['order_id']
        ]);
    }
    
    public function getPricing($payload){
        return $this->post('/api/mitra/v5/shipping_price',[
            'subdistrict_origin'            => $payload['subdistrict_origin'],
            'subdistrict_destination'       => $payload['subdistrict_destination'],
            'weight'                        => $payload['weight'],
            'insurance'                     => $payload['insurance'],
            'item_value'                    => $payload['item_value'],
            'courier'                       => $payload['courier']
        ]);
    }
    
    public function getRequestPickupSchedule(){
        return $this->post('/api/mitra/v2/schedules',);
    }

    public function sendPickupRequest($payload){
        return $this->post('/api/wordpress/v1/request_pickup',$payload);
    }

    public function get_couriers(){
        return $this->post('/api/mitra/couriers');
    }    
}