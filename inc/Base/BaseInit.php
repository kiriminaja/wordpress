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
        $this->plugin = $plugin . "/" . $plugin . ".php";
    }

    public function logThis($test='log',$loggedItem=[]){
//        error_log(date("[Y-m-d H:i:s]").' '.json_encode([$test, $loggedItem])."\n", 3, plugin_dir_path(__DIR__)."/logs/debug.log");
        error_log(date("[Y-m-d H:i:s]").' '.json_encode([$test, $loggedItem])."\n", 3, $this->plugin_path."debug.log");
    }
}