<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



class requestPickupIndex {
    function __construct(){
        global $results;
        global $page;
        global $items_per_page;
        global $total_pages;
        global $next_page_link;
        global $prev_page_link;
        global $monthOptions;
        global $locale;

        /** WP Setting langguage*/
        $locale = get_locale();
        
        /** Page Query*/
        // @codingStandardsIgnoreLine
        $pageQuery = self::pageQuery();
        $results = $pageQuery['results'];
        $page = $pageQuery['page'];
        $items_per_page = $pageQuery['items_per_page'];
        $total_pages = $pageQuery['total_pages'];
        $next_page_link = $pageQuery['next_page_link'];
        $prev_page_link = $pageQuery['prev_page_link'];

        /** Month Options*/
        $monthOptions = self::getPaymentsDateFilterOptionArray();
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$monthOptions',[$monthOptions]);
        
        /** Return vars and view*/
        include 'view/index.php';
    }
    
    private function pageQuery(){
        global $wpdb;
        
        /** Tables*/
        $paymentTable = $wpdb->prefix . 'kiriminaja_payments';
        $transactionTable = $wpdb->prefix . 'kiriminaja_transactions';

        /** PreRequrities*/
        $items_per_page = 20;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['cpage']) ? absint($_GET['cpage']) : 1;
        $offset = ( $page * $items_per_page ) - $items_per_page;
        
        $whereConditions = [];
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty(sanitize_text_field(wp_unslash($_GET['key'] ?? '')))) {
            $key = sanitize_text_field(wp_unslash($_GET['key'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $whereConditions[] = $wpdb->prepare("kiriminaja_payments.pickup_number LIKE %s", '%' . $wpdb->esc_like($key) . '%');
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty(sanitize_text_field(wp_unslash($_GET['month'] ?? '')))) {
            $month = sanitize_text_field(wp_unslash($_GET['month'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $whereConditions[] = $wpdb->prepare("kiriminaja_payments.created_at LIKE %s", '%' . $wpdb->esc_like($month) . '%');
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty(sanitize_text_field(wp_unslash($_GET['status'] ?? '')))) {
            $status = sanitize_text_field(wp_unslash($_GET['status'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $whereConditions[] = $wpdb->prepare("kiriminaja_payments.status = %s", $status);
        }

        $whereCondition = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        /** Main Query*/
        $baseQuery = "SELECT 
            kiriminaja_payments.*, 
            SUM(CASE WHEN kiriminaja_transactions.cod_fee = 0 THEN kiriminaja_transactions.shipping_cost + kiriminaja_transactions.insurance_cost ELSE 0 END) AS cost
            FROM {$wpdb->prefix}kiriminaja_payments as kiriminaja_payments
            INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions
            ON kiriminaja_payments.pickup_number = kiriminaja_transactions.pickup_number
            " . $whereCondition . "
            GROUP BY kiriminaja_payments.pickup_number
            ORDER BY kiriminaja_payments.created_at DESC
            LIMIT %d, %d";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( 
            $wpdb->prepare($baseQuery, $offset, $items_per_page) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('last_error',@$wpdb->last_error);
        }

        /** Pagination Query*/
        $countQuery = "SELECT 
            kiriminaja_payments.id, kiriminaja_payments.pickup_number
            FROM {$wpdb->prefix}kiriminaja_payments as kiriminaja_payments
            INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions
            ON kiriminaja_payments.pickup_number = kiriminaja_transactions.pickup_number
            " . $whereCondition . "
            GROUP BY kiriminaja_payments.pickup_number";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        $totalQuery = $wpdb->get_results($countQuery); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total = count($totalQuery);
        $total_pages = ceil($total/$items_per_page);

        /** Paginate*/
        $next_page_link = home_url() . '/wp-admin/admin.php?';
        $prev_page_link = home_url() . '/wp-admin/admin.php?';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        foreach ($_GET as $key => $value){
            if ($key !== 'cpage'){
                $next_page_link .= sanitize_key($key) . '=' . urlencode(sanitize_text_field($value)) . '&';
                $prev_page_link .= sanitize_key($key) . '=' . urlencode(sanitize_text_field($value)) . '&';
            }
        }
        $next_page_link= $page+1<=$total_pages ? $next_page_link.'cpage='.$page+1 : '';
        $prev_page_link= $page-1>0 ? $prev_page_link.'cpage='.$page-1 : '';
        
        return [
            'results'=>$results,
            'page'=>$page,
            'items_per_page'=>$items_per_page,
            'total_pages'=>$total_pages,
            'next_page_link'=>$next_page_link,
            'prev_page_link'=>$prev_page_link
        ];
        
    }
    
    private function getPaymentsDateFilterOptionArray(){
        $oldestPaymentDateQuery = (new \KiriminAjaOfficial\Repositories\PaymentRepository())->getPaymentByOldestDate();
        $oldestMonth= gmdate('Y-m-d',strtotime($oldestPaymentDateQuery->created_at ?? "now"));
        $currentMonth = gmdate('Y-m-d',strtotime("now"));
        $d1=new DateTime($oldestMonth);
        $d2=new DateTime($currentMonth);
        $Months = $d2->diff($d1);
        $howeverManyMonths = ((($Months->y) * 12) + ($Months->m)+1);
        $monthOptions = [];
        for($i=0;$i<=$howeverManyMonths;$i++){
            $theDate = "now"."-".$i." months";
            $monthOptions[gmdate('Y-m',strtotime($theDate))]=gmdate('Y F',strtotime($theDate));
        }
        return $monthOptions;
    }
}
new requestPickupIndex();

?>
