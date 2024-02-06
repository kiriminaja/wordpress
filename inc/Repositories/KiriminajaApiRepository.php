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

    public function processSetupKey($setupKey){
        $dummyResp = '{
          "status": true,
          "data": {
            "status": true,
            "text": "Load Data Successfully",
            "data": {
              "api_key": "8d1261174b7c37159477d50c73ee738de236c39e22090ecd5cf7d087d271ce23",
              "oid_prefix": "NKP-"
            }
          }
        }';
        sleep(1);
        return json_decode($dummyResp);
    }
    
}