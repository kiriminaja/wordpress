<?php
namespace KiriminAjaOfficial\Base;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BaseInit{
    public $plugin_path;
    public $plugin_url;
    public $plugin;
    public function __construct() {
        /** this file path and wnt to ancestor 2 times*/
        $this->plugin_path = plugin_dir_path( dirname( __FILE__, 2 ) );
        $this->plugin_url  = plugin_dir_url( dirname( __FILE__, 2 ) );
        // Use the main plugin file path directly
        $this->plugin = plugin_basename( dirname( __FILE__, 3 ) . '/kiriminaja.php' );
    }
    public function logThis($test='log',$loggedItem=[]){
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if (!empty($host) && !empty($request_uri)) {
            $actual_link = "https://$host$request_uri";
        } else {
            $actual_link = home_url(); // URL fallback jika input tidak valid.
        }
        if (!strpos($actual_link,'localhost')) { return; }

        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
        $bt = debug_backtrace();
        $caller = array_shift($bt);
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log(gmdate("[Y-m-d H:i:s] ").wp_json_encode([
            'log_name'      => $test, 
            'log_result'    => $loggedItem,
            'file'          => $caller['file'],
            'line'          => $caller['line']
            ])."\n");
    }
}