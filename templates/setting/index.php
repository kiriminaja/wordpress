<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class settingIndex {
    function __construct(){
        global $approvedSetupKey;
        global $inputValueArr;
        global $isOriginShippingDataReady;
        global $activeTab;
        global $locale;

        /** WP Setting langguage*/
        $locale = get_locale();
        
        /** Check if  setup key exist*/
        $approvedSetupKey = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('setup_key');

        /** data value query*/
        $arrayParam = [];
        $repo = [];
        $shippingRepo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByArray(['origin_name','origin_phone','origin_address','origin_latitude','origin_longitude','origin_sub_district_id','origin_sub_district_name','origin_zip_code','origin_whitelist_expedition_id','origin_whitelist_expedition_name']);
        // @codingStandardsIgnoreLine
        $activeTab = @$_GET['tab'] ?? 'tab-integration';
        if (@$activeTab==='tab-integration'){
            $repo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByArray(['oid_prefix']);
        } elseif (@$activeTab==='tab-shipping'){
            $repo = $shippingRepo;
        } elseif (@$activeTab==='tab-advanced'){
            $repo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByArray(['callback_url']);
        }
        $inputValueArr = [];
        foreach ($repo as $obj){
            $inputValueArr[$obj->key]=$obj->value;
        }

        /** check if origin shipping data completed*/
        $isOriginShippingDataReady=true;
        for ($i=0; $i<count($shippingRepo);$i++){
            
            if( 
                in_array(
                    $shippingRepo[$i]->key,
                    ['origin_whitelist_expedition_id','origin_whitelist_expedition_name'] 
                ) 
            ){
                continue;
            }
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
    }
}


new settingIndex();







?>