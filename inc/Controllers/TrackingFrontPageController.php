<?php
namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Throwable;
class TrackingFrontPageController{
    public function register(){
        /** Adding New Route*/
        add_shortcode('kiriminaja-tracking-front-page', array($this,'trackingFrontPage'));
        /** Add Tracking Ajax*/
        add_action('wp_ajax_kiriof-tracking-ajax', array($this,'trackingAjaxHandler'));
        add_action('wp_ajax_nopriv_kiriof-tracking-ajax', array($this,'trackingAjaxHandler'));
    }
    
    public function trackingFrontPage(){
        ob_start();
        require_once (plugin_dir_path(dirname(__FILE__,2)). 'templates/front/tracking.php');
        return ob_get_clean();
    }
    
    public function trackingAjaxHandler(){
        try {
            // Public tracking lookup endpoint (read-only by order number); no nonce required.
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $order_number = isset( $_POST['order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['order_number'] ) ) : '';
            $service = (new \KiriminAjaOfficial\Services\KiriminAjaTrackingService())->order_number($order_number)->call();
            if ($service->status!==200){wp_send_json_success($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_success(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
}