<?php

namespace Inc\Repositories;

class WpPostMetaRepository{

    public $table;
    public function __construct(){
        global $wpdb;
        $this->table = $wpdb->prefix . 'postmeta';
    }
    
    public function getRequiredRowsByPostId($post_id){
        global $wpdb;
        $query = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE post_id = %d",
                $post_id
            )
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getRequiredRowsByPostIdsAndMetaKeys($post_ids, $meta_keys){
        global $wpdb;
        
        $post_ids_placeholders = implode(', ', array_fill(0, count($post_ids), '%d'));
        $meta_keys_placeholders = implode(', ', array_fill(0, count($meta_keys), '%s'));

        $query = $wpdb->get_results( 
            $wpdb->prepare(
                "
                SELECT * 
                FROM {$this->table} 
                WHERE post_id IN ($post_ids_placeholders) 
                AND meta_key IN ($meta_keys_placeholders)
                ",
                ...$post_ids, // Masukkan nilai post_ids
                ...$meta_keys // Masukkan nilai meta_keys
            )
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getRequiredRowsByPostIdAndMetaKey($post_id, $meta_key){
        global $wpdb;
        $query = $wpdb->get_row( 
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE post_id = %d AND meta_key = %s",
                $post_id,
                $meta_key
            )
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
}