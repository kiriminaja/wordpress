<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'kiriminaja-official' ) );
}

class Kiriof_SettingIndex {
    function __construct(){
        /** WP Setting language*/
        $locale = get_locale();
        
        /** Check if  setup key exist*/
        $approvedSetupKey = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('setup_key');

        /** Load all settings for the card-based layout */
        $shippingRepo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByArray(['origin_name','origin_phone','origin_address','origin_latitude','origin_longitude','origin_sub_district_id','origin_sub_district_name','origin_zip_code','origin_whitelist_expedition_id','origin_whitelist_expedition_name']);
        $advancedRepo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByArray(['callback_url']);

        $inputValueArr = [];
        // Merge shipping + advanced settings into one array
        foreach (array_merge($shippingRepo, $advancedRepo) as $obj){
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
            if ( empty( $shippingRepo[$i]->value ?? null ) ){
                $isOriginShippingDataReady=false;
                break;
            }
        }
    
        /** Return vars and view*/
        if ( ! empty( $approvedSetupKey->value ?? null ) ){
            include 'setuped/index.php';
            return;
        }
        include 'unsetuped/index.php';
    }
}


new Kiriof_SettingIndex();







?>