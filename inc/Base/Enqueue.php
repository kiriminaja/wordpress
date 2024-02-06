<?php

namespace Inc\Base;

use \Inc\Base\BaseInit;

class Enqueue extends BaseInit{
    
    public function register(){
        /** enqueue js & CSS */
        /* admin */
        add_action('admin_enqueue_scripts', array($this,'enqueueAdmin'));
        /* WP */
        add_action('wp_enqueue_scripts', array($this,'enqueueWp'));
    }

    /** Add Enqueue CSS & JS*/
    function enqueueWp(){
        wp_enqueue_style('kiriminPluginStyle', $this->plugin_url.'assets/wp/css/kj-wp-style.css');
        wp_enqueue_script('kiriminPluginScript', $this->plugin_url.'assets/wp/js/kj-wp-script.js');
    }
    function enqueueAdmin(){
        wp_enqueue_style('kiriminPluginStyle', $this->plugin_url.'assets/admin/css/kj-admin-style.css');
        wp_enqueue_script('kiriminPluginScript', $this->plugin_url.'assets/admin/js/kj-admin-script.js');

        /** QR CODE */
        wp_enqueue_script('qrcode', $this->plugin_url.'assets/admin/js/qrcode.min.js');
        /** print */
        wp_enqueue_style('printCss', $this->plugin_url.'assets/admin/css/print.min.css');
        wp_enqueue_script('printJs', $this->plugin_url.'assets/admin/js/print.min.js');
        
        /** Select 2*/
        //Add the Select2 CSS file
        wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0');
        //Add the Select2 JavaScript file
        wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', 'jquery', '4.1.0-rc.0');
   
        //Add a JavaScript file to initialize the Select2 elements
//        wp_enqueue_script( 'select2-init', '/wp-content/plugins/select-2-tutorial/select2-init.js', 'jquery', '4.1.0-rc.0');
        //Open api
//        wp_enqueue_script( 'sdssdsddsds', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.5.1/leaflet.js');
        
    }
    
}