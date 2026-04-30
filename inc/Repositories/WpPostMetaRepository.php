<?php
namespace KiriminAjaOfficial\Repositories;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WpPostMetaRepository{
    public $table;
    public function __construct(){
        global $wpdb;
        $this->table = $wpdb->prefix . 'postmeta';
    }
    
    public function getRequiredRowsByPostId($post_id){
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_results( 
            $wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared	
                "SELECT * FROM {$this->table} WHERE post_id = %d",
                $post_id
            )
        );
        if (strlen($wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis($wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getRequiredRowsByPostIdsAndMetaKeys($post_ids, $meta_keys){
        global $wpdb;
        
        $post_ids_placeholders = implode(', ', array_fill(0, count($post_ids), '%d'));
        $meta_keys_placeholders = implode(', ', array_fill(0, count($meta_keys), '%s'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_results( 
            $wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                "SELECT * 
                FROM {$wpdb->prefix}postmeta 
                WHERE post_id IN (".implode(', ', array_fill(0, count($post_ids), '%d')).") 
                AND meta_key IN (".implode(', ', array_fill(0, count($meta_keys), '%s')).")
                ",
                ...$post_ids, // Masukkan nilai post_ids
                ...$meta_keys // Masukkan nilai meta_keys
            )
        );
        if (strlen($wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis($wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getRequiredRowsByPostIdAndMetaKey($post_id, $meta_key){
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_row( 
            $wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared	
                "SELECT * FROM {$this->table} WHERE post_id = %d AND meta_key = %s",
                $post_id,
                $meta_key
            )
        );
        if (strlen($wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis($wpdb->last_error);
            return false;
        }
        return $query;
    }
}