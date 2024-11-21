<?php

namespace Inc\Repositories;

class SettingRepository{
    
    public $table;
    public function __construct(){
        global $wpdb;
        $this->table = $wpdb->prefix . 'kiriminaja_settings';
    }
    
    public function getIntegrationData(){
        global $wpdb;
        $query = $wpdb->get_results( "SELECT * FROM `".$this->table."` WHERE `key` IN ('oid_prefix','setup_key')" );
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
        
        $wpdb->update($this->table, array('value' => @$payload['api_key']), array('key' => 'api_key'));
        $wpdb->update($this->table, array('value' => @$payload['oid_prefix']), array('key' => 'oid_prefix'));
        $wpdb->update($this->table, array('value' => @$payload['setup_key']), array('key' => 'setup_key'));
        $wpdb->update($this->table, array('value' => @$payload['callback_url']), array('key' => 'callback_url'));
    
        return true;
    }
    
    public function disconnectIntegration(){
        global $wpdb;
        
        $wpdb->update($this->table, array('value' => null), array('key' => 'api_key'));
        $wpdb->update($this->table, array('value' => null), array('key' => 'oid_prefix'));
        $wpdb->update($this->table, array('value' => null), array('key' => 'setup_key'));
        $wpdb->update($this->table, array('value' => null), array('key' => 'callback_url'));
        return true;
    }

    public function getOriginData(){
        global $wpdb;

        $table = $wpdb->prefix . 'kiriminaja_settings';
        
        $query = $wpdb->get_results( "SELECT * FROM `".$this->table."` WHERE `key` IN ('origin_name','origin_phone','origin_address','origin_sub_district_id','origin_sub_district_name','origin_latitude','origin_longitude','origin_zip_code')" );
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
            !$payload['origin_latitude']
            ||
            !$payload['origin_longitude']
            ||  
            !$payload['origin_sub_district_id']
            || 
            !$payload['origin_sub_district_name']
        ){throw new \Exception('payload err');}

        $wpdb->update($this->table, array('value' => @$payload['origin_name']), array('key' => 'origin_name'));
        $wpdb->update($this->table, array('value' => @$payload['origin_phone']), array('key' => 'origin_phone'));
        $wpdb->update($this->table, array('value' => @$payload['origin_address']), array('key' => 'origin_address'));
        $wpdb->update($this->table, array('value' => @$payload['origin_sub_district_id']), array('key' => 'origin_sub_district_id'));
        $wpdb->update($this->table, array('value' => @$payload['origin_sub_district_name']), array('key' => 'origin_sub_district_name'));
        $wpdb->update($this->table, array('value' => @$payload['origin_latitude']), array('key' => 'origin_latitude'));
        $wpdb->update($this->table, array('value' => @$payload['origin_longitude']), array('key' => 'origin_longitude'));
        $wpdb->update($this->table, array('value' => @$payload['origin_zip_code']), array('key' => 'origin_zip_code'));
        
        if( empty($wpdb->get_row("SELECT * FROM $this->table WHERE `key`='origin_whitelist_expedition_id'") ) ){
            $wpdb->insert(
                $this->table, 
                array(
                    'key' => 'origin_whitelist_expedition_id',
                    'value' => @$payload['origin_whitelist_expedition_id']
                ),
                array(
                    '%s',
                    '%s',
                ) 
            );

            $wpdb->insert(
                $this->table, 
                array(
                    'key' => 'origin_whitelist_expedition_name',
                    'value' => @$payload['origin_whitelist_expedition_name']
                ),
                array(
                    '%s',
                    '%s',
                ) 
            );
        }

        $wpdb->update($this->table, array('value' => @$payload['origin_whitelist_expedition_id']), array('key' => 'origin_whitelist_expedition_id'));
        $wpdb->update($this->table, array('value' => @$payload['origin_whitelist_expedition_name']), array('key' => 'origin_whitelist_expedition_name'));            
        
        return true;
    }

    public function getCallbackData(){
        global $wpdb;
        $query = $wpdb->get_results( "SELECT * FROM `".$this->table."` WHERE `key` IN ('callback_url')" );
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
        $wpdb->update($this->table, array('value' => @$payload['callback_url']), array('key' => 'callback_url'));
        return true;
    }


    public function getSettingByKey($key){
        global $wpdb;
        $query = $wpdb->get_row( "SELECT * FROM `".$this->table."` WHERE `key`  = '".$key."'");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }

    public function getSettingByArray($array){
        global $wpdb;
        $keywords_imploded = implode("','",$array);
        $query = $wpdb->get_results( "SELECT * FROM `".$this->table."` WHERE `key` IN ('$keywords_imploded')" );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }

    public function validateWhiteListExpedition($data){
        global $wpdb;

        $origin_whitelist_expedition_id = $wpdb->get_row( "SELECT `value` FROM `".$this->table."` WHERE `key`  = 'origin_whitelist_expedition_id' " );
        
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        $datas = [];
        
        if( !empty($origin_whitelist_expedition_id ) && !empty($origin_whitelist_expedition_id->value) ){
            $arr_origin_whitelist_expedition_id = explode(',', $origin_whitelist_expedition_id->value);
            foreach( $data as $row ){
                if( !in_array($row->service,$arr_origin_whitelist_expedition_id) ){
                    continue;
                }
                $datas[]=$row;

            }
        }else{
            $datas = $data;
        }

        
        return $datas;
        
    }

}