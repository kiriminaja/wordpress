<?php

namespace Inc\Repositories;

class WpPostMetaRepository{
    
    public function getRequiredRowsByPostId($post_id){
        global $wpdb;
        $query = $wpdb->get_results( "SELECT * FROM wp_postmeta WHERE post_id  = '".$post_id."'");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getRequiredRowsByPostIdsAndMetaKeys($post_ids, $meta_keys){
        global $wpdb;
        $query = $wpdb->get_results( "SELECT * FROM wp_postmeta WHERE post_id IN ('".implode("', '", $post_ids)."') 
        AND meta_key IN ('".implode("', '", $meta_keys)."')");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
}