<?php
namespace KiriminAjaOfficial\Base;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Helper extends  BaseInit {
    public function transactionStatusLabel($status = ''){
        switch ($status){
            case "new":
                return $this->tlThis("New", $this->getLocale());
            break;
            case "request_pickup":
                return $this->tlThis("Request Pickup", $this->getLocale());
            break;
            case "pending":
                return $this->tlThis("Pending", $this->getLocale());
            break;
            case "finished":
                return $this->tlThis("Delivered", $this->getLocale());
            break;
            case "shipped":
                return $this->tlThis("In Transit", $this->getLocale());
            break;
            case "return":
                return $this->tlThis("Returning", $this->getLocale());
            break;
            case "returned":
                return $this->tlThis("Returned", $this->getLocale());
            break;
            case "rejected":
                return $this->tlThis("Rejected", $this->getLocale());
            break;
            case "canceled":
                return $this->tlThis("Canceled", $this->getLocale());
            break;
            default:
            return "-";
        }
    }

    public function wcStatusLabel($postStatus = ''){
        $postStatus = str_replace('wc-', '', (string) $postStatus);
        switch ($postStatus){
            case "processing":
                return $this->tlThis("Processing", $this->getLocale());
            case "on-hold":
                return $this->tlThis("On Hold", $this->getLocale());
            case "pending":
                return $this->tlThis("Pending Payment", $this->getLocale());
            case "completed":
                return $this->tlThis("Completed", $this->getLocale());
            case "cancelled":
            case "canceled":
                return $this->tlThis("Canceled", $this->getLocale());
            case "refunded":
                return $this->tlThis("Refunded", $this->getLocale());
            case "failed":
                return $this->tlThis("Failed", $this->getLocale());
            default:
                return ucwords(str_replace('-', ' ', $postStatus));
        }
    }
    
    public function transactionStatusClass($status = ''){
        
        switch ($status){
            case "new":
                return "kj-badge primary";
                break;
            case "request_pickup":
                return "kj-badge info";
                break;
            case "pending":
                return "kj-badge warning";
                break;
            case "finished":
                return "kj-badge success";
                break;
            case "shipped":
                return "kj-badge teal";
                break;
            case "return":
                return "kj-badge orange";
                break;
            case "returned":
                return "kj-badge slate";
                break;
            case "rejected":
                return "kj-badge danger";
                break;
            case "canceled":
                return "kj-badge rose";
                break;
            default:
            return "kj-badge";
        }
    }

    public function wcStatusClass($postStatus = ''){
        $postStatus = str_replace('wc-', '', (string) $postStatus);
        switch ($postStatus){
            case "processing":
                return "kj-badge processing";
            case "on-hold":
            case "pending":
                return "kj-badge warning";
            case "completed":
                return "kj-badge success";
            case "cancelled":
            case "canceled":
            case "refunded":
            case "failed":
                return "kj-badge danger";
            default:
                return "kj-badge";
        }
    }

    private function getLocale() {
        return function_exists('get_locale') ? get_locale() : 'en_US';
    }
    
    public function tlThis($text='',$lang='en_US'){
        switch ($lang){
            case "id_ID":
                $string = file_get_contents($this->plugin_path."/lang/id_ID.json"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
                break;
            default :
                $string = file_get_contents($this->plugin_path."/lang/en_US.json"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
                break;
        }
        $langLib = (array) json_decode($string);
        return @$langLib[$text] ?? $text;
    }
    
    public function devForceTrue() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['devForceTrue'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $devForceTrue = sanitize_text_field(wp_unslash($_GET['devForceTrue']));
            return !empty($devForceTrue);
        }
        return false;
    }
    public function minAmount($value, $minAmount = 1){
        $theValue = intval($value ?? 0);
        return $theValue >= $minAmount ? $theValue : $minAmount;
    }
    public function kjCountTransactionProcess(){
        return (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getCountTransactionProcessNew();
    }
    public function kjCountShipmentUnpaid(){
        return (new \KiriminAjaOfficial\Repositories\PaymentRepository())->getCountUnpaid();
    }
    public function dateConvertGMT($tgl) {
        $timezone = new \DateTimeZone("Asia/Bangkok");
    
        $date = new \DateTime($tgl, new \DateTimeZone('UTC'));
    
        $date->setTimezone($timezone);
    
        return $date->format("Y-m-d H:i:s");
    }
}