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
    
}