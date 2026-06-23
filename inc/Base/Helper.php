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

    private function getCourierNameMap() {
        return array(
            'jne'          => __( 'JNE Express', 'kiriminaja-official' ),
            'tiki'         => __( 'Tiki', 'kiriminaja-official' ),
            'sicepat'      => __( 'Sicepat Express', 'kiriminaja-official' ),
            'jnt'          => __( 'J&T Express', 'kiriminaja-official' ),
            'jtcargo'      => __( 'J&T Cargo', 'kiriminaja-official' ),
            'anteraja'     => __( 'AnterAja', 'kiriminaja-official' ),
            'pos'          => __( 'Pos Indonesia', 'kiriminaja-official' ),
            'posindonesia' => __( 'Pos Indonesia', 'kiriminaja-official' ),
            'rpx'          => __( 'RPX Logistics', 'kiriminaja-official' ),
            'lion'         => __( 'Lion Parcel', 'kiriminaja-official' ),
            'paxel'        => __( 'Paxel', 'kiriminaja-official' ),
            'sap'          => __( 'SAPX Express', 'kiriminaja-official' ),
            'ninja'        => __( 'Ninja', 'kiriminaja-official' ),
            'idexpress'    => __( 'ID Express', 'kiriminaja-official' ),
            'idx'          => __( 'ID Express', 'kiriminaja-official' ),
            'ncs'          => __( 'NCS Courier', 'kiriminaja-official' ),
            'borzo'        => __( 'Borzo', 'kiriminaja-official' ),
            'grab'         => __( 'Grab Express', 'kiriminaja-official' ),
            'grab_express' => __( 'Grab Express', 'kiriminaja-official' ),
            'gosend'       => __( 'GoSend', 'kiriminaja-official' ),
            'sentral'      => __( 'Sentral Cargo', 'kiriminaja-official' ),
            'spx'          => __( 'SPX Express', 'kiriminaja-official' ),
        );
    }

    public function getCourierDisplayName( $serviceCode ) {
        $serviceCode = strtolower( trim( (string) $serviceCode ) );
        $map         = $this->getCourierNameMap();
        if ( isset( $map[ $serviceCode ] ) ) {
            return $map[ $serviceCode ];
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

        // Check for partial word overlap between courier name and service name.
        // This catches cases like "J&T EZ" where "J&T" is common with
        // "J&T Express", or "POS REGULER" where "POS" overlaps with
        // "Pos Indonesia". Words of 3+ characters avoid matching noise like
        // "of", "ID", etc.
        $courierWords = explode( ' ', $needle );
        foreach ( $courierWords as $word ) {
            $word = trim( $word );
            if ( strlen( $word ) > 2 && strpos( $serviceNameLower, $word ) !== false ) {
                return $serviceName;
            }
        }

        return $courierName . ' ' . $serviceName;
    }
}