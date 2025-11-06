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

        wp_localize_script(
            'myjs',
            'myjs',
            array(
                'ajaxurl' => admin_url( 'admin-ajax.php' )
            )
        );
        
        // Use local Select2 library (WordPress.org requirement - no external CDNs)
        wp_enqueue_script( 'select2', $this->plugin_url . 'assets/vendor/select2/js/select2.min.js', array('jquery'), '4.1.0-rc.0', true );
        wp_enqueue_style( 'select2', $this->plugin_url . 'assets/vendor/select2/css/select2.min.css', array(), '4.1.0-rc.0' );

        wp_enqueue_style('kiriminPluginStyle', $this->plugin_url.'assets/wp/css/kj-wp-style.css',array(),wp_rand(),'all');

        // Option 1: Manually enqueue the wp-util library.
        wp_enqueue_script( 'wp-util' );
        // Option 2: Make wp-util a dependency of your script (usually better).
        wp_enqueue_script('kiriminPluginScript', $this->plugin_url.'assets/wp/js/kj-wp-script.js', [ 'wp-util' ], KJ_PLUGIN_VERSION, true);
    }
    
    function enqueueAdmin(){

        $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS);
        if (!in_array($page,[
            'kiriminaja-konfigurasi',
            'kiriminaja-transaction-process',
            'kiriminaja-request-pickup'])){return;}

        wp_localize_script(
            'myjs',
            'myjs',
            array(
                'ajaxurl' => admin_url( 'admin-ajax.php' )
            )
        );
        wp_enqueue_style('kiriminPluginStyle', $this->plugin_url.'assets/admin/css/kj-admin-style.css',array(),KJ_PLUGIN_VERSION,'all');
        wp_enqueue_script('kiriminPluginScript', $this->plugin_url.'assets/admin/js/kj-admin-script.js',array(),KJ_PLUGIN_VERSION,true);

        
        wp_enqueue_style('BSGridStyle', $this->plugin_url.'assets/admin/css/bootstrap-grid.css', array(), KJ_PLUGIN_VERSION);


        wp_enqueue_style('kj'.'wc_5', $this->plugin_url.'assets/admin/css/kj-wc-style/app.style.css', array(), KJ_PLUGIN_VERSION);
        wp_enqueue_style('kj'.'wc_5.5', $this->plugin_url.'assets/admin/css/kj-wc-style/app-custom.style.css', array(), KJ_PLUGIN_VERSION);
        wp_enqueue_style('kj'.'wc_1', $this->plugin_url.'assets/admin/css/kj-wc-style/3538.style.css', array(), KJ_PLUGIN_VERSION);
        wp_enqueue_style('kj'.'wc_2', $this->plugin_url.'assets/admin/css/kj-wc-style/5502.style.css', array(), KJ_PLUGIN_VERSION);
        wp_enqueue_style('kj'.'wc_3', $this->plugin_url.'assets/admin/css/kj-wc-style/8597.style.css', array(), KJ_PLUGIN_VERSION);




        /** QR CODE */
        wp_enqueue_script('qrcode', $this->plugin_url.'assets/admin/js/qrcode.min.js', array(), KJ_PLUGIN_VERSION, true);
        /** print */
        wp_enqueue_style('printCss', $this->plugin_url.'assets/admin/css/print.min.css', array(), KJ_PLUGIN_VERSION);
        wp_enqueue_script('printJs', $this->plugin_url.'assets/admin/js/print.min.js', array(), KJ_PLUGIN_VERSION, true);
        
        /** Select 2 - Use local library (WordPress.org requirement - no external CDNs) */
        wp_enqueue_style( 'select2-css', $this->plugin_url . 'assets/vendor/select2/css/select2.min.css', array(), '4.1.0-rc.0');
        wp_enqueue_script( 'select2-js', $this->plugin_url . 'assets/vendor/select2/js/select2.min.js', array('jquery'), '4.1.0-rc.0', true);

   
    }
    
}