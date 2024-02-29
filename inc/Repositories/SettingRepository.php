<?php

namespace Inc\Repositories;

class SettingRepository{
    
    public function getIntegrationData(){
        global $wpdb;
        $query = $wpdb->get_results( "SELECT * FROM wp_kiriminaja_settings WHERE `key` IN ('oid_prefix','setup_key')" );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false; 
        }
        return $query;
    }

    /**
     * @param $payload
     * $payload['api_key']
     * $payload['oid_prefix']
     * $payload['setup_key']
     * @return true
     */
    public function storeIntegrationData($payload){
        global $wpdb;
        if (!$payload['api_key'] || !$payload['oid_prefix'] || !$payload['setup_key']){throw new \Exception('payload err');}
        
        $wpdb->update('wp_kiriminaja_settings', array('value' => @$payload['api_key']), array('key' => 'api_key'));
        $wpdb->update('wp_kiriminaja_settings', array('value' => @$payload['oid_prefix']), array('key' => 'oid_prefix'));
        $wpdb->update('wp_kiriminaja_settings', array('value' => @$payload['setup_key']), array('key' => 'setup_key'));
        $wpdb->update('wp_kiriminaja_settings', array('value' => @$payload['callback_url']), array('key' => 'callback_url'));
        
        return true;
    }
    
    public function disconnectIntegration(){
        global $wpdb;
        $wpdb->update('wp_kiriminaja_settings', array('value' => null), array('key' => 'api_key'));
        $wpdb->update('wp_kiriminaja_settings', array('value' => null), array('key' => 'oid_prefix'));
        $wpdb->update('wp_kiriminaja_settings', array('value' => null), array('key' => 'setup_key'));
        $wpdb->update('wp_kiriminaja_settings', array('value' => null), array('key' => 'callback_url'));
        return true;
    }

    public function getOriginData(){
        global $wpdb;
        $query = $wpdb->get_results( "SELECT * FROM wp_kiriminaja_settings WHERE `key` IN ('origin_name','origin_phone','origin_address','origin_sub_district_id','origin_sub_district_name','origin_latitude','origin_longitude','origin_zip_code')" );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }

    /**
     * @param $payload
     * $payload['origin_name']
     * $payload['origin_phone']
     * $payload['origin_address']
     * $payload['origin_sub_district_id']
     * $payload['origin_sub_district_name']
     * $payload['origin_latitude']
     * $payload['origin_longitude']
     * @return true
     */
    public function storeOriginData($payload){
        global $wpdb;
        if (
            !$payload['origin_name'] 
            || 
            !$payload['origin_phone'] 
            || 
            !$payload['origin_address']
            || 
            !$payload['origin_sub_district_id']
            || 
            !$payload['origin_sub_district_name']
        ){throw new \Exception('payload err');}

        $wpdb->update('wp_kiriminaja_settings', array('value' => @$payload['origin_name']), array('key' => 'origin_name'));
        $wpdb->update('wp_kiriminaja_settings', array('value' => @$payload['origin_phone']), array('key' => 'origin_phone'));
        $wpdb->update('wp_kiriminaja_settings', array('value' => @$payload['origin_address']), array('key' => 'origin_address'));
        $wpdb->update('wp_kiriminaja_settings', array('value' => @$payload['origin_sub_district_id']), array('key' => 'origin_sub_district_id'));
        $wpdb->update('wp_kiriminaja_settings', array('value' => @$payload['origin_sub_district_name']), array('key' => 'origin_sub_district_name'));
        $wpdb->update('wp_kiriminaja_settings', array('value' => @$payload['origin_latitude']), array('key' => 'origin_latitude'));
        $wpdb->update('wp_kiriminaja_settings', array('value' => @$payload['origin_longitude']), array('key' => 'origin_longitude'));
        $wpdb->update('wp_kiriminaja_settings', array('value' => @$payload['origin_zip_code']), array('key' => 'origin_zip_code'));

        return true;
    }

    public function getCallbackData(){
        global $wpdb;
        $query = $wpdb->get_results( "SELECT * FROM wp_kiriminaja_settings WHERE `key` IN ('callback_url')" );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
        
    }

    /**
     * @param $payload
     * $payload['link_callback']
     * @return true
     */
    public function storeCallbackData($payload){
        global $wpdb;
        if (!$payload['callback_url']){throw new \Exception('payload err');}
        $wpdb->update('wp_kiriminaja_settings', array('value' => @$payload['callback_url']), array('key' => 'callback_url'));
        return true;
    }


    public function getSettingByKey($key){
        global $wpdb;
        $query = $wpdb->get_row( "SELECT * FROM wp_kiriminaja_settings WHERE `key`  = '".$key."'");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }

    public function getSettingByArray($array){
        global $wpdb;
        $keywords_imploded = implode("','",$array);
        $query = $wpdb->get_results( "SELECT * FROM wp_kiriminaja_settings WHERE `key` IN ('$keywords_imploded')" );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
}