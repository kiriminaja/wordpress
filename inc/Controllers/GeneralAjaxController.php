<?php
namespace Inc\Controllers;

class GeneralAjaxController{

    public function register(){
        add_action('wp_ajax_kiriminaja_subdistrict_search', array($this,'kiriminajaSubdistrictSearch'));
        add_action('wp_ajax_nopriv_kiriminaja_subdistrict_search', array($this,'kiriminajaSubdistrictSearch'));
    }
    
    function kiriminajaSubdistrictSearch() {
        $data = $_POST['data'];
        try {
            $kiriminajaSubDistrictSearch = (new \Inc\Services\KiriminajaApiService())->sub_district_search($data['search']);
            if ($kiriminajaSubDistrictSearch->status!==200){wp_send_json_success([]);}
            wp_send_json_success($kiriminajaSubDistrictSearch->data);
        }catch (Throwable $e){
            wp_send_json_success([]);
        }
    }
    
}