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
        
        wp_enqueue_script( 'select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true );
        wp_enqueue_style( 'select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', array(), '4.0.13' );
        wp_enqueue_style('kiriminPluginStyle', $this->plugin_url.'assets/wp/css/kj-wp-style.css',array(),rand(),'all');

        // Option 1: Manually enqueue the wp-util library.
        wp_enqueue_script( 'wp-util' );
        // Option 2: Make wp-util a dependency of your script (usually better).
        wp_enqueue_script('kiriminPluginScript', $this->plugin_url.'assets/wp/js/kj-wp-script.js', [ 'wp-util' ]);
    }
    function is_order_meta_box_screen( $screen_id ) {
        $screen_id = str_replace( 'edit-', '', $screen_id );

        $types_with_metaboxes_screen_ids = array_filter(
            array_map(
                'wc_get_page_screen_id',
                wc_get_order_types( 'order-meta-boxes' )
            )
        );

        return in_array( $screen_id, $types_with_metaboxes_screen_ids, true );
    }
    
    function enqueueAdmin(){

        /** 
         * Js in Only Admin Shop Order 
         */
        $screen    = get_current_screen();
        $screen_id = $screen ? $screen->id : '';
        if( $this->is_order_meta_box_screen($screen_id) && get_current_screen()->post_type === 'shop_order' ) {
            
            wp_enqueue_style('kiriminaja-shoporder-css', $this->plugin_url.'assets/admin/css/kj-shop-order-style.css',array(),KJ_PLUGIN_VERSION,'all');
        
            wp_enqueue_script( 'kiriminaja-shop-order', $this->plugin_url.'assets/admin/js/kj-shop-order.js',array('jquery'),KJ_PLUGIN_VERSION,true);
            wp_localize_script( 'kiriminaja-shop-order', 'kj',
                array( 
                    'ajaxurl' => admin_url( 'admin-ajax.php' ),
                    'nonce' => wp_create_nonce('kj-nonce'),
                    'siteurl'=>site_url()
                )
            );
        }


        if (!in_array(@$_GET['page'],[
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

        
        wp_enqueue_style('BSGridStyle', $this->plugin_url.'assets/admin/css/bootstrap-grid.css');


        wp_enqueue_style('kj'.'wc_5', $this->plugin_url.'assets/admin/css/kj-wc-style/app.style.css');
        wp_enqueue_style('kj'.'wc_5.5', $this->plugin_url.'assets/admin/css/kj-wc-style/app-custom.style.css');
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
   
    }
    
}