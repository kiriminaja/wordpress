<?php
namespace Inc\Controllers;

use Inc\Repositories\KiriminajaApiRepository;
use Inc\Services\KiriminajaApiService;
use Inc\Services\ShippingProcessServices\GetShippingProcessDetailService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Inc\Services\ShippingProcessServices\GetShippingProcessPayment;

class ShippingProcessController{
    public function register(){
        /** getShippingProcessDetail */
        add_action('wp_ajax_kj_get_shipping_process_detail', array($this,'getShippingProcessDetail'));
        add_action('wp_ajax_nopriv_kj_get_shipping_process_detail', array($this,'getShippingProcessDetail'));
        /** getPaymentForm */
        add_action('wp_ajax_kj_get_payment_form', array($this,'getPaymentForm'));
        add_action('wp_ajax_nopriv_kj_get_payment_form', array($this,'getPaymentForm'));
        
        /** Resi Print */
        add_action( 'init', function (){
            add_feed( 'transaction-resi-print', array($this,'resiPrint') );
        } );
    }
    
    function getShippingProcessDetail() {
        try {
            $service = (new GetShippingProcessDetailService())->paymentId(@$_POST['data']['payment_id'] ?? '')->call();
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,$e->getMessage()]);
        }
    }
    
    function getPaymentForm(){
        try {
            $service = (new GetShippingProcessPayment())->payment_id(@$_POST['data']['payment_id'] ?? '')->call();
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,$e->getMessage()]);
        }
    }
    
    function resiPrint() {
        // instantiate and use the dompdf class
        $orderIdsParam = @$_GET['oids'];
        $orderIds = array_unique(explode(',',$orderIdsParam) ?? []);
        if (count($orderIds)<1) return ''; 
        
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(self::printResiHtml($orderIds));
        $dompdf->setPaper(array(0, 0, 283.465, 425.197), 'portrait');
        
        // Render the HTML as PDF
        $dompdf->render();
        ob_end_clean();
        // Output the generated PDF to Browser
        $dompdf->stream($orderIds[0]."-".count($orderIds).".pdf");
    }
    
    function printResiHtml($orderIds){
        $transactions = (new \Inc\Repositories\TransactionRepository())->getTransactionByOrderIds($orderIds);
        (new \Inc\Base\BaseInit())->logThis('$transactions',[$transactions]);
        $shippingRepo = (new \Inc\Repositories\SettingRepository())->getSettingByArray(['origin_name','origin_phone','origin_address','origin_sub_district_id','origin_sub_district_name','origin_zip_code']);
        $originDataArr = [];
        foreach ($shippingRepo as $obj){
            $originDataArr[$obj->key]=$obj->value;
        }
        
        ob_start();
        include plugin_dir_path(dirname(__FILE__,2)) . 'templates/print/print-pdf-new.php';
        return ob_get_clean();
    }
}