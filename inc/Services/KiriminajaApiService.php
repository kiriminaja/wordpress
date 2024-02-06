<?php

namespace Inc\Services;

use \Inc\Base\BaseService;
use Inc\Init;

class KiriminajaApiService extends BaseService{

    public function sub_district_search($search)
    {
        $repo = (new \Inc\Repositories\KiriminajaApiRepository())->sub_district_search($search);
        if (!@$repo['status'] || !@$repo['data']->status){
            return self::error([],@$repo['data']->text ?? 'Something is wrong');
        }
        return self::success($repo['data']->result);
    }
    
}