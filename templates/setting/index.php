<?php
class settingIndex {
    function __construct(){
        global $approvedSetupKey;
        global $inputValueArr;
        global $isOriginShippingDataReady;
        global $activeTab;
        
        /** Check if  setup key exist*/
        $approvedSetupKey = (new Inc\Repositories\SettingRepository())->getSettingByKey('setup_key');

        /** data value query*/
        $arrayParam = [];
        $repo = [];
        $shippingRepo = (new \Inc\Repositories\SettingRepository())->getSettingByArray(['origin_name','origin_phone','origin_address','origin_sub_district_id','origin_sub_district_name','origin_zip_code']);
        $activeTab = @$_GET['tab'] ?? 'tab-integration';
        if (@$activeTab==='tab-integration'){
            $repo = (new \Inc\Repositories\SettingRepository())->getSettingByArray(['oid_prefix']);
        } elseif (@$activeTab==='tab-shipping'){
            $repo = $shippingRepo;
        } elseif (@$activeTab==='tab-advanced'){
            $repo = (new \Inc\Repositories\SettingRepository())->getSettingByArray(['callback_url']);
        }
        $inputValueArr = [];
        foreach ($repo as $obj){
            $inputValueArr[$obj->key]=$obj->value;
        }
        
        /** check if origin shipping data completed*/
        $isOriginShippingDataReady=true;
        for ($i=0; $i<count($shippingRepo);$i++){
            if (!@$shippingRepo[$i]->value){
                $isOriginShippingDataReady=false;
                break;
            }
        }
        
        /** Return vars and view*/
        if (@$approvedSetupKey->value){
            include 'setuped/index.php';
            return;
        }
        include 'unsetuped/index.php';
//        include dirname(__FILE__).'/../sample.php';
    }
}


new settingIndex();







?>