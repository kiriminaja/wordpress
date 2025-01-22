<?php


class TransactionProcessIndex{
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
        $pageQuery = self::pageQuery();
        $results = $pageQuery['results'];
        (new \Inc\Base\BaseInit())->logThis('$results',[$results]);

        /** Month Options*/
        $monthOptions = self::getTransactionsDateFilterOptionArray();
        (new \Inc\Base\BaseInit())->logThis('$monthOptions',[$monthOptions]);
        
        /** Return vars and view*/
        include 'view/index.php';
    }

    private function pageQuery(){
        global $wpdb;

        /** Tables*/
        $wcOrderTable = $wpdb->prefix . 'wc_order_stats';
        $transactionTable = $wpdb->prefix . 'kiriminaja_transactions';
        $postTable = $wpdb->prefix . 'posts';

        // @codingStandardsIgnoreLine
        $whereCondition = '';
        if (!empty($_GET['key'] ?? '')){ // @codingStandardsIgnoreLine
            $whereCondition .= " AND `".$wcOrderTable."`.order_id LIKE '%".$_GET['key']."%' "; // @codingStandardsIgnoreLine
        }
        if (!empty($_GET['month'] ?? '')){ // @codingStandardsIgnoreLine
            $whereCondition.=" AND `".$wcOrderTable."`.date_created LIKE '%".$_GET['month']."%' "; // @codingStandardsIgnoreLine
        }


        /** Main Query*/
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results( 
            $wpdb->prepare(
                //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared	
                "(
                    SELECT 
                    {$wpdb->prefix}wc_order_stats.order_id as wc_order_id,
                    {$wpdb->prefix}wc_order_stats.date_created as wc_date_created,
                    {$wpdb->prefix}wc_order_stats.status as wc_status,
                    {$wpdb->prefix}posts.post_status,
                    {$wpdb->prefix}kiriminaja_transactions.*
                    FROM {$wpdb->prefix}wc_order_stats
                    INNER JOIN {$wpdb->prefix}kiriminaja_transactions
                    ON {$wpdb->prefix}wc_order_stats.order_id = {$wpdb->prefix}kiriminaja_transactions.wp_wc_order_stat_order_id
                    INNER JOIN {$wpdb->prefix}posts
                    ON {$wpdb->prefix}wc_order_stats.order_id = {$wpdb->prefix}posts.ID
                    WHERE {$wpdb->prefix}wc_order_stats.status = 'wc-processing'
                    AND {$wpdb->prefix}kiriminaja_transactions.status = 'new'
                    AND {$wpdb->prefix}posts.post_status != 'trash' 
                    {$whereCondition}
                    GROUP BY {$wpdb->prefix}wc_order_stats.order_id
                    ORDER BY {$wpdb->prefix}wc_order_stats.date_created DESC
                )" // @codingStandardsIgnoreLine
            )
        );

        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis('last_error',@$wpdb->last_error);
        }

        return [
            'results'=>$results
        ];
        
    }

    private function getTransactionsDateFilterOptionArray(){
        $oldestTransactionDateQuery = (new \Inc\Repositories\TransactionRepository())->getTransactionByOldestDate();

        (new \Inc\Base\BaseInit())->logThis('$oldestTransactionDateQuery',[$oldestTransactionDateQuery]);
        
        $oldestMonth= gmdate('Y-m-d',strtotime($oldestTransactionDateQuery->created_at ?? "now"));
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

new TransactionProcessIndex();
