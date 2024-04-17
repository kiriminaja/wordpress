<?php

namespace Inc\Services\PluginInfoServices;

use Inc\Base\BaseInit;
use Inc\Base\BaseService;

class UpdateCheckService extends BaseService{
    
    public function call(){

        try {
            $response = self::callInfoJson();

            $latestVersion = @json_decode($response)->version ?? '0.0.0';
            $currentVersion = @thePluginData()['Version'] ?? '0.0.0';

            $versionInArray = [$latestVersion, $currentVersion];
            /** Sort the array by descending*/
            rsort($versionInArray);

            (new BaseInit())->logThis($latestVersion);

            return self::success([
                'require_update'    => $versionInArray[0] !== $currentVersion,
                'lastest_version'   => $latestVersion
            ]);
        }catch (\Throwable $th){
            return self::success([
                'require_update'    => false,
                'lastest_version'   => '0.0.0'
            ]);
        }

    }
    
    protected function callInfoJson(){
        $curl_handle=curl_init();
        curl_setopt($curl_handle, CURLOPT_URL,'https://analytics.kiriminaja.com/wooooo/woocommerce.json');
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_handle, CURLOPT_USERAGENT, 'Your application name');
        $response = curl_exec($curl_handle);
        curl_close($curl_handle);
        
        return $response;
    }
}