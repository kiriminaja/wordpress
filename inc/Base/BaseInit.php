<?php

namespace Inc\Base;

class BaseInit{
    public $plugin_path;
    public $plugin_url;
    public $plugin;

    public function __construct() {
        /** this file path and wnt to ancestor 2 times*/
        $this->plugin_path =  plugin_dir_path(dirname(__FILE__,2));
        $this->plugin_url = plugin_dir_url(dirname(__FILE__,2));
        $plugin = plugin_basename( dirname( __FILE__, 3 ) );
        $this->plugin = $plugin . "/" . 'kiriminaja.php' ;
    }

    public function logThis($test='log',$loggedItem=[]){

        $actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        if (!strpos($actual_link,'localhost')) { return; }
        
        $bt = debug_backtrace();
        $caller = array_shift($bt);
        error_log(gmdate("[Y-m-d H:i:s] ").wp_json_encode([
            'log_name'      => $test, 
            'log_result'    => $loggedItem,
            'file'          => $caller['file'],
            'line'          => $caller['line']
            ])."\n", 3, $this->plugin_path."debug.log");
    }
}