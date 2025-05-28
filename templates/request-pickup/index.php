<?php


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
        (new \Inc\Base\BaseInit())->logThis('$monthOptions',[$monthOptions]);
        
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

        // @codingStandardsIgnoreLine
        $page = @$_GET['cpage'] ?? 1;
        $offset = ( $page * $items_per_page ) - $items_per_page;
        
        $whereCount = 0;
        $whereCondition = '';
        
        // @codingStandardsIgnoreLine
        if (!empty(@$_GET['key'])){
            $whereCount+=1;
            $whereCondition.=($whereCount===0 ? "WHERE" : "AND")." `".$paymentTable."`.pickup_number LIKE '%".@$_GET['key']."%'"; // @codingStandardsIgnoreLine
        }
        // @codingStandardsIgnoreLine
        if (!empty(@$_GET['month'])){
            $whereCount+=1;
            $whereCondition.=($whereCount===0 ? "WHERE" : "AND")." `".$paymentTable."`.created_at LIKE '%".@$_GET['month']."%'"; // @codingStandardsIgnoreLine
        }
        // @codingStandardsIgnoreLine
        if (!empty(@$_GET['status'])){
            $whereCount+=1;
            $whereCondition.=($whereCount===0 ? "WHERE" : "AND")." `".$paymentTable."`.status = '".@$_GET['status']."'"; // @codingStandardsIgnoreLine 
        }

        /** Main Query*/
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results( 
            $wpdb->prepare(
                "(
                    SELECT 
                    {$wpdb->prefix}kiriminaja_payments.*, 
                    SUM(CASE WHEN {$wpdb->prefix}kiriminaja_transactions.cod_fee = 0 THEN {$wpdb->prefix}kiriminaja_transactions.shipping_cost + {$wpdb->prefix}kiriminaja_transactions.insurance_cost ELSE 0 END) AS cost
                    FROM {$wpdb->prefix}kiriminaja_payments 
                    INNER JOIN {$wpdb->prefix}kiriminaja_transactions
                    ON {$wpdb->prefix}kiriminaja_payments.pickup_number = {$wpdb->prefix}kiriminaja_transactions.pickup_number
                    {$whereCondition}
                    GROUP BY {$wpdb->prefix}kiriminaja_payments.pickup_number
                    ORDER BY {$wpdb->prefix}kiriminaja_payments.created_at DESC
                ) LIMIT %d, %d", $offset, $items_per_page
            )
        );
        
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis('last_error',@$wpdb->last_error);
        }

        /** Pagination Query*/
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $totalQuery = $wpdb->get_results( 
            $wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "(
                    SELECT 
                    {$wpdb->prefix}kiriminaja_payments.id,{$wpdb->prefix}kiriminaja_payments.pickup_number
                    FROM {$wpdb->prefix}kiriminaja_payments 
                    INNER JOIN {$wpdb->prefix}kiriminaja_transactions
                    ON {$wpdb->prefix}kiriminaja_payments.pickup_number = {$wpdb->prefix}kiriminaja_transactions.pickup_number
                    {$whereCondition}
                    GROUP BY {$wpdb->prefix}kiriminaja_payments.pickup_number
                )" 
            )
        );
        $total = count($totalQuery);
        $total_pages = ceil($total/$items_per_page);

        /** Paginate*/
        $next_page_link = @home_url().'/wp-admin/admin.php?';
        $prev_page_link = @home_url().'/wp-admin/admin.php?';
        // @codingStandardsIgnoreLine
        foreach ($_GET as $key => $value){
            if ($key!=='cpage'){
                $next_page_link.=$key.'='.$value.'&';
                $prev_page_link.=$key.'='.$value.'&';
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
        $oldestPaymentDateQuery = (new \Inc\Repositories\PaymentRepository())->getPaymentByOldestDate();
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
