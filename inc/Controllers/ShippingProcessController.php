<?php
namespace Inc\Controllers;

use Inc\Services\ShippingProcessServices\GetShippingProcessDetailService;
use Dompdf\Dompdf;
use Dompdf\Options;

class ShippingProcessController{
    public function register(){
        /** getIntegrationData*/
        add_action('wp_ajax_kj_get_shipping_process_detail', array($this,'getShippingProcessDetail'));
        add_action('wp_ajax_nopriv_kj_get_shipping_process_detail', array($this,'getShippingProcessDetail'));
        
        /** Resi Print*/
        add_action( 'init', function (){
            add_feed( 'transaction-resi-print', array($this,'resiPrint') );
        } );
        
    }
    function getShippingProcessDetail() {
        try {
            $service = (new GetShippingProcessDetailService())->paymentId($_POST['data']['payment_id'])->call();
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,$e->getMessage()]);
        }
    }
    
    function resiPrint() {
        // instantiate and use the dompdf class
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(self::printResiHtml());
        $dompdf->setPaper(array(0, 0, 283.465, 425.197), 'portrait');
        
        // Render the HTML as PDF
        $dompdf->render();
        ob_end_clean();
        // Output the generated PDF to Browser
        $dompdf->stream("kj-resi-print.pdf");
    }
    
    function printResiHtml(){

        $packages = [];
        for ($i=1;$i<10;$i++){
            $packages[]=$i;
        }
        $data = 'test';
        ob_start();
        include plugin_dir_path(dirname(__FILE__,2)) . 'templates/print/print-pdf-new.php';
        return ob_get_clean();
    }
}