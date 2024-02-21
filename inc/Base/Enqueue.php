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
        wp_enqueue_script( 'select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true );
        wp_enqueue_style( 'select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', array(), '4.0.13' );
        wp_enqueue_style('kiriminPluginStyle', $this->plugin_url.'assets/wp/css/kj-wp-style.css');
        wp_enqueue_script('kiriminPluginScript', $this->plugin_url.'assets/wp/js/kj-wp-script.js');
    }
    
    function enqueueAdmin(){

        if (!in_array(@$_GET['page'],['kiriminaja-konfigurasi','kiriminaja-request-pickup'])){return;}
        
        wp_enqueue_style('kiriminPluginStyle', $this->plugin_url.'assets/admin/css/kj-admin-style.css');
        wp_enqueue_script('kiriminPluginScript', $this->plugin_url.'assets/admin/js/kj-admin-script.js');
        wp_enqueue_style('BSGridStyle', $this->plugin_url.'assets/admin/css/bootstrap-grid.css');


        wp_enqueue_style('kj'.'wc_4', $this->plugin_url.'assets/admin/css/kj-wc-style/admin-layout.style.css');
        wp_enqueue_style('kj'.'wc_5', $this->plugin_url.'assets/admin/css/kj-wc-style/app.style.css');
        wp_enqueue_style('kj'.'wc_5.5', $this->plugin_url.'assets/admin/css/kj-wc-style/app-custom.style.css');
        wp_enqueue_style('kj'.'wc_6', $this->plugin_url.'assets/admin/css/kj-wc-style/components.style.css');
        wp_enqueue_style('kj'.'wc_7', $this->plugin_url.'assets/admin/css/kj-wc-style/customer-effort-score.style.css');
        wp_enqueue_style('kj'.'wc_8', $this->plugin_url.'assets/admin/css/kj-wc-style/experimental.style.css');
        wp_enqueue_style('kj'.'wc_9', $this->plugin_url.'assets/admin/css/kj-wc-style/onboarding.style.css');
        wp_enqueue_style('kj'.'wc_10', $this->plugin_url.'assets/admin/css/kj-wc-style/product-editor.style.css');
        wp_enqueue_style('kj'.'wc_11', $this->plugin_url.'assets/admin/css/kj-wc-style/load.style.css');
        wp_enqueue_style('kj'.'wc_1', $this->plugin_url.'assets/admin/css/kj-wc-style/3538.style.css');
        wp_enqueue_style('kj'.'wc_2', $this->plugin_url.'assets/admin/css/kj-wc-style/5502.style.css');
        wp_enqueue_style('kj'.'wc_3', $this->plugin_url.'assets/admin/css/kj-wc-style/8597.style.css');




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