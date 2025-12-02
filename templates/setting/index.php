<?php
// Check if Vue app should handle this page
$use_vue = file_exists(KJ_DIR . 'assets/.vite/manifest.json') || (defined('WP_DEBUG') && WP_DEBUG);

if ($use_vue) {
    // Let Vue handle the configuration page - mount point is added by Enqueue class
    ?>
    <div class="wrap">
        <div id="kiriminaja-admin-root"></div>
    </div>
    <?php
    return;
}

// Legacy PHP rendering below
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
        $approvedSetupKey = (new Inc\Repositories\SettingRepository())->getSettingByKey('setup_key');

        /** data value query*/
        $arrayParam = [];
        $repo = [];
        $shippingRepo = (new \Inc\Repositories\SettingRepository())->getSettingByArray(['origin_name','origin_phone','origin_address','origin_latitude','origin_longitude','origin_sub_district_id','origin_sub_district_name','origin_zip_code','origin_whitelist_expedition_id','origin_whitelist_expedition_name']);
        // @codingStandardsIgnoreLine
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