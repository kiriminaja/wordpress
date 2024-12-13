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
            case "canceled":
                return "Paket Batal";
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
            case "canceled":
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

    public function minAmount($value, $minAmount = 1){
        $theValue = intval($value ?? 0);
        return $theValue >= $minAmount ? $theValue : $minAmount;
    }

    public function kjCountTransactionProcess(){
        return (new \Inc\Repositories\TransactionRepository())->getCountTransactionProcessNew();
    }

    public function dateConvertGMT($tgl) {
        $timezone = new \DateTimeZone("Asia/Bangkok");
    
        $date = new \DateTime($tgl, new \DateTimeZone('UTC'));
    
        $date->setTimezone($timezone);
    
        return $date->format("Y-m-d H:i:s");
    }

    public function getTransactionStatus($status):string{

        switch ($status){
            case "new":
                return "Req Pickup";
                break;
            case "request_pickup":
                return "Request Pickup";
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
                return "Paket Selesai di Kembalikan";
                break;
            case "rejected":
                return "Paket Ditolak";
                break;
            case "canceled":
                return "Paket Batal";
                break;
            default;
            return "-";
        }
      
    }

    public function changeDateFormat($datetime,$format = "Y-m-d H:i:s"){
        $date = date_create($datetime);
        return date_format($date,$format);
    }
}