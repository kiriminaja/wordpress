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
                return __( "New", 'kiriminaja-official' );
            break;
            case "request_pickup":
                return __( "Request Pickup", 'kiriminaja-official' );
            break;
            case "pending":
                return __( "Pending", 'kiriminaja-official' );
            break;
            case "finished":
                return __( "Delivered", 'kiriminaja-official' );
            break;
            case "shipped":
                return __( "In Transit", 'kiriminaja-official' );
            break;
            case "return":
                return __( "Returning", 'kiriminaja-official' );
            break;
            case "returned":
                return __( "Returned", 'kiriminaja-official' );
            break;
            case "rejected":
                return __( "Rejected", 'kiriminaja-official' );
            break;
            case "canceled":
                return __( "Canceled", 'kiriminaja-official' );
            break;
            default:
            return "-";
        }
    }

    public function wcStatusLabel($postStatus = ''){
        $postStatus = str_replace('wc-', '', (string) $postStatus);
        switch ($postStatus){
            case "processing":
                return __( "Processing", 'kiriminaja-official' );
            case "on-hold":
                return __( "On Hold", 'kiriminaja-official' );
            case "pending":
                return __( "Pending Payment", 'kiriminaja-official' );
            case "completed":
                return __( "Completed", 'kiriminaja-official' );
            case "cancelled":
            case "canceled":
                return __( "Canceled", 'kiriminaja-official' );
            case "refunded":
                return __( "Refunded", 'kiriminaja-official' );
            case "failed":
                return __( "Failed", 'kiriminaja-official' );
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

    const COURIER_NAME_MAP = array(
        'jne'          => 'JNE Express',
        'tiki'         => 'Tiki',
        'sicepat'      => 'Sicepat Express',
        'jnt'          => 'J&T Express',
        'jtcargo'      => 'J&T Cargo',
        'anteraja'     => 'AnterAja',
        'pos'          => 'Pos Indonesia',
        'posindonesia' => 'Pos Indonesia',
        'rpx'          => 'RPX Logistics',
        'lion'         => 'Lion Parcel',
        'paxel'        => 'Paxel',
        'sap'          => 'SAPX Express',
        'ninja'        => 'Ninja',
        'idexpress'    => 'ID Express',
        'idx'          => 'ID Express',
        'ncs'          => 'NCS Courier',
        'borzo'        => 'Borzo',
        'grab'         => 'Grab Express',
        'grab_express' => 'Grab Express',
        'gosend'       => 'GoSend',
        'sentral'      => 'Sentral Cargo',
        'spx'          => 'SPX Express',
    );

    public function getCourierDisplayName( $serviceCode ) {
        $serviceCode = strtolower( trim( (string) $serviceCode ) );
        if ( isset( self::COURIER_NAME_MAP[ $serviceCode ] ) ) {
            return self::COURIER_NAME_MAP[ $serviceCode ];
        }
        return strtoupper( $serviceCode );
    }

    public function formatServiceName( $service, $serviceName ) {
        $service     = strtolower( trim( (string) $service ) );
        $serviceName = trim( (string) $serviceName );

        if ( '' === $serviceName ) {
            return $this->getCourierDisplayName( $service );
        }

        $courierName = $this->getCourierDisplayName( $service );
        $needle      = strtolower( $courierName );

        // If service_name already contains the courier display name or the raw
        // service code, the API has already formatted it (e.g. "JNE Express Flat").
        // Use it as-is.
        $serviceNameLower = strtolower( $serviceName );
        if ( strpos( $serviceNameLower, $needle ) !== false
            || strpos( $serviceNameLower, $service ) !== false
        ) {
            return $serviceName;
        }

        return $courierName . ' ' . $serviceName;
    }
}