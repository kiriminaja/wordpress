<?php

namespace Inc\Base;

class Helper extends  BaseInit {
    public function transactionStatusLabel($status = ''){
        switch ($status){
            case "new":
                return "Baru";
            break;
            case "request_pickup":
                return "Req Pickup";
            break;
            case "pending":
                return "Pending";
            break;
            case "finished":
                return "Paket Terkirim";
            break;
            case "shipped":
                return "Proses Pengiriman";
            break;
            case "return":
                return "Proses Pengembalian";
            break;
            case "returned":
                return "Paket Selesai Dikembalikan";
            break;
            case "rejected":
                return "Paket Ditolak";
            break;
            default;
            return "-";
        }
    }
    
    public function transactionStatusClass($status = ''){
        
        switch ($status){
            case "new":
                return "kj-badge warning";
                break;
            case "request_pickup":
                return "kj-badge warning";
                break;
            case "pending":
                return "kj-badge warning";
                break;
            case "finished":
                return "kj-badge success";
                break;
            case "shipped":
                return "kj-badge processing";
                break;
            case "return":
                return "kj-badge";
                break;
            case "returned":
                return "kj-badge";
                break;
            case "rejected":
                return "kj-badge";
                break;
            default;
            return "kj-badge processing";
        }
    }
    
    public function tlThis($text='',$lang='en_US'){
        switch ($lang){
            case "id_ID":
                $string = file_get_contents($this->plugin_path."/lang/id_ID.json");
                break;
            default :
                $string = file_get_contents($this->plugin_path."/lang/en_US.json");
                break;
        }
        $langLib = (array) json_decode($string);
        return @$langLib[$text] ?? $text;
    }
    
    public function devForceTrue(){
        return  @$_GET['devForceTrue'] && strlen(@$_GET['devForceTrue']) > 0;
    }
}