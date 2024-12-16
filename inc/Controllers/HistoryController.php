<?php
namespace Inc\Controllers;

class HistoryController
{

    public function register(){
        $this->ajax();
    }

    public function ajax(){
        add_action('wp_ajax_get_history_package',array($this,'getHistoryPackage'));
    }

    public function getHistoryPackage():void{
        
        global $wpdb;

        $search_value = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = intval($_POST['length']);
        $draw = intval($_POST['draw']);
        $status = $_POST['status'] ?? '';

        $service = (new \Inc\Services\HistoryPackageServices\GetListHistoryPackageServices(
            compact('search_value','start','length','draw','status')
        ))->call();

        echo json_encode( $service );
        
        wp_die();
    }
}
?>