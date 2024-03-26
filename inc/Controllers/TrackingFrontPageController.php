<?php
namespace Inc\Controllers;

use Throwable;

class TrackingFrontPageController{

    public function register(){
        /** Adding New Route*/
        add_shortcode('wp-tracking-front-page', array($this,'trackingFrontPage'));

        /** Add Tracking Ajax*/
        add_action('wp_ajax_kj-tracking-ajax', array($this,'trackingAjaxHandler'));
        add_action('wp_ajax_nopriv_kj-tracking-ajax', array($this,'trackingAjaxHandler'));
    }
    
    public function trackingFrontPage(){
        ob_start();
        require_once (plugin_dir_path(dirname(__FILE__,2)). 'templates/front/tracking.php');
        return ob_get_clean();
    }
    
    public function trackingAjaxHandler(){
        try {
            $service = (new \Inc\Services\KiriminAjaTrackingService())->order_number($_POST['order_number'])->call();
            if ($service->status!==200){wp_send_json_success($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_success(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
}