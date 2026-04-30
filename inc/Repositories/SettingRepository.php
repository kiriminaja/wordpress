<?php
namespace KiriminAjaOfficial\Repositories;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SettingRepository{
    
    public $table;
    public function __construct(){
        global $wpdb;
        $this->table = $wpdb->prefix . 'kiriminaja_settings';
    }
    
    public function getIntegrationData(){
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_results( 
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE `key` IN (%s, %s)",
                'oid_prefix',
                'setup_key'
            )
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(@$wpdb->last_error);
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
        
        $wpdb->update($this->table, array('value' => @$payload['api_key']), array('key' => 'api_key')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['oid_prefix']), array('key' => 'oid_prefix')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['setup_key']), array('key' => 'setup_key')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['callback_url']), array('key' => 'callback_url')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    
        return true;
    }
    
    public function disconnectIntegration(){
        global $wpdb;
        
        $wpdb->update($this->table, array('value' => null), array('key' => 'api_key')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => null), array('key' => 'oid_prefix')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => null), array('key' => 'setup_key')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => null), array('key' => 'callback_url')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return true;
    }
    public function getOriginData(){
        global $wpdb;
        $table = $wpdb->prefix . 'kiriminaja_settings';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_results( "SELECT * FROM {$this->table} WHERE `key` IN ('origin_name','origin_phone','origin_address','origin_sub_district_id','origin_sub_district_name','origin_latitude','origin_longitude','origin_zip_code')" );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(@$wpdb->last_error);
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
        $wpdb->update($this->table, array('value' => @$payload['origin_name']), array('key' => 'origin_name')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['origin_phone']), array('key' => 'origin_phone')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['origin_address']), array('key' => 'origin_address')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['origin_sub_district_id']), array('key' => 'origin_sub_district_id')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['origin_sub_district_name']), array('key' => 'origin_sub_district_name')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['origin_latitude']), array('key' => 'origin_latitude')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['origin_longitude']), array('key' => 'origin_longitude')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['origin_zip_code']), array('key' => 'origin_zip_code')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
        $wpdb->update($this->table, array('value' => @$payload['origin_whitelist_expedition_id']), array('key' => 'origin_whitelist_expedition_id')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['origin_whitelist_expedition_name']), array('key' => 'origin_whitelist_expedition_name')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        
        return true;
    }
    public function getCallbackData(){
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_results( "SELECT * FROM {$this->table} WHERE `key` IN ('callback_url')" );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(@$wpdb->last_error);
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
        $wpdb->update($this->table, array('value' => @$payload['callback_url']), array('key' => 'callback_url')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return true;
    }
    public function getSettingByKey($key){
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_row( 
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE `key` = %s",
                $key
            )
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    public function getSettingByArray( $array ) {
        global $wpdb;

        if ( empty( $array ) || ! is_array( $array ) ) {
            return [];
        }

        $keys = array_values( array_unique( array_filter( array_map( 'sanitize_key', $array ) ) ) );
        if ( empty( $keys ) ) {
            return [];
        }

        $placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE `key` IN ({$placeholders})", $keys ) );
        
        if ( strlen( $wpdb->last_error ?? '' ) > 0 ) {
            ( new \KiriminAjaOfficial\Base\BaseInit() )->logThis( $wpdb->last_error );
            return false;
        }
        return $query;
    }
    public function validateWhiteListExpedition($data){
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $origin_whitelist_expedition_id = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT `value` FROM {$this->table} WHERE `key` = %s",
                'origin_whitelist_expedition_id' // %s
            ) 
        );
        
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(@$wpdb->last_error);
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