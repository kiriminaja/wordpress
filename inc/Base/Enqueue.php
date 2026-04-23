<?php
namespace KiriminAjaOfficial\Base;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use \KiriminAjaOfficial\Base\BaseInit;
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
        wp_enqueue_script( 'kiriof-select2-script', $this->plugin_url . 'assets/lib/select2/select2.min.js', array( 'jquery' ), '4.1.0-rc.0', true );
        wp_enqueue_style( 'kiriof-select2-style', $this->plugin_url . 'assets/lib/select2/select2.min.css', array(), '4.1.0-rc.0' );
        wp_enqueue_style( 'kiriof-style', $this->plugin_url . 'assets/wp/css/kj-wp-style.css', array(), KIRIOF_VERSION, 'all' );
        
        // Option 1: Manually enqueue the wp-util library.
        wp_enqueue_script( 'wp-util' );
        // Option 2: Make wp-util a dependency of your script (usually better).
        wp_enqueue_script( 'kiriof-script', $this->plugin_url . 'assets/wp/js/kj-wp-script.js', array( 'wp-util', 'jquery' ), KIRIOF_VERSION, true );
        
        // Localize script to pass ajax URL and nonce
        wp_localize_script(
            'kiriof-script',
            'kiriofAjax',
            array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( KIRIOF_NONCE ),
                'destination_nonce' => wp_create_nonce( 'kiriof-destination' ),
                'update_checkout_nonce' => wp_create_nonce( 'kiriof-update-checkout' ),
            )
        );
    }
    
    function enqueueAdmin(){
        $page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS );
        if ( ! in_array( $page, array(
            'kiriminaja-konfigurasi',
            'kiriminaja-transaction-process',
            'kiriminaja-request-pickup',
        ), true ) ) {
            return;
        }
        
        wp_enqueue_style( 'kiriof-style', $this->plugin_url . 'assets/admin/css/kj-admin-style.css', array(), KIRIOF_VERSION, 'all' );
        wp_enqueue_script( 'kiriof-script', $this->plugin_url . 'assets/admin/js/kj-admin-script.js', array( 'jquery' ), KIRIOF_VERSION, true );
        
        // Localize script to pass ajax URL and nonce
        wp_localize_script(
            'kiriof-script',
            'kiriofAjax',
            array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( KIRIOF_NONCE ),
                'destination_nonce' => wp_create_nonce( 'kiriof-destination' ),
                'update_checkout_nonce' => wp_create_nonce( 'kiriof-update-checkout' ),
            )
        );
        
        wp_enqueue_style( 'kiriof-grid-style', $this->plugin_url . 'assets/admin/css/bootstrap-grid.css', array(), KIRIOF_VERSION );
        wp_enqueue_style( 'kiriof-wc-app', $this->plugin_url . 'assets/admin/css/kj-wc-style/app.style.css', array(), KIRIOF_VERSION );
        wp_enqueue_style( 'kiriof-wc-app-custom', $this->plugin_url . 'assets/admin/css/kj-wc-style/app-custom.style.css', array(), KIRIOF_VERSION );
        wp_enqueue_style( 'kiriof-wc-3538', $this->plugin_url . 'assets/admin/css/kj-wc-style/3538.style.css', array(), KIRIOF_VERSION );
        wp_enqueue_style( 'kiriof-wc-5502', $this->plugin_url . 'assets/admin/css/kj-wc-style/5502.style.css', array(), KIRIOF_VERSION );
        wp_enqueue_style( 'kiriof-wc-8597', $this->plugin_url . 'assets/admin/css/kj-wc-style/8597.style.css', array(), KIRIOF_VERSION );

        /** print */
        wp_enqueue_style( 'kiriof-print-style', $this->plugin_url . 'assets/admin/css/print.min.css', array(), KIRIOF_VERSION );
        wp_enqueue_script( 'kiriof-print-script', $this->plugin_url . 'assets/admin/js/print.min.js', array(), KIRIOF_VERSION, true );
        
        /** Select 2 - bundled locally */
        wp_enqueue_style( 'kiriof-select2-style', $this->plugin_url . 'assets/lib/select2/select2.min.css', array(), '4.1.0-rc.0' );
        wp_enqueue_script( 'kiriof-select2-script', $this->plugin_url . 'assets/lib/select2/select2.min.js', array( 'jquery' ), '4.1.0-rc.0', true );
   
    }   
}