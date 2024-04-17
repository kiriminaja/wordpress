<?php

namespace Inc\Services\PluginInfoServices;

use Inc\Base\BaseInit;
use Inc\Base\BaseService;

class UpdateCheckService extends BaseService{
    
    public function call(){

        $response = file_get_contents('https://analytics.kiriminaja.com/wooooo/woocommerce.json');
        $latestVersion = @json_decode($response)->version ?? '0.0.0';
        $currentVersion = @thePluginData()['Version'] ?? '0.0.0';
        
        $versionInArray = [$latestVersion, $currentVersion];
        /** Sort the array by descending*/
        rsort($versionInArray);
        
        return self::success([
            'require_update'    => $versionInArray[0] !== $currentVersion,
            'lastest_version'   => '0.1.0'
        ]);
    }
}