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

        kiriof_log(
            'debug',
            'Legacy debug instrumentation emitted a development log entry.',
            array(
                'source'  => 'kiriminaja_debug',
                'tag'     => $test,
                'payload' => $loggedItem,
            )
        );
    }
}