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

        $whereCondition = '';
        if (!empty($_GET['key'] ?? '')){
            $whereCondition .= " AND `".$wcOrderTable."`.order_id LIKE '%".$_GET['key']."%' ";
        }
        if (!empty($_GET['month'] ?? '')){
            $whereCondition.=" AND `".$wcOrderTable."`.date_created LIKE '%".$_GET['month']."%' ";
        }


        /** Main Query*/
        // $query = "(
        //     SELECT 
        //     ".$wcOrderTable.".order_id as wc_order_id,
        //     ".$wcOrderTable.".date_created as wc_date_created,
        //     ".$wcOrderTable.".status as wc_status,
        //     ".$postTable.".post_status,
        //     ".$transactionTable.".*
        //     FROM ".$wcOrderTable."
        //     INNER JOIN ".$transactionTable."
        //     ON ".$wcOrderTable.".order_id = ".$transactionTable.".wp_wc_order_stat_order_id
        //     INNER JOIN ".$postTable."
        //     ON ".$wcOrderTable.".order_id = ".$postTable.".ID
        //     WHERE ".$wcOrderTable.".status = 'wc-processing'
        //     AND ".$transactionTable.".status = 'new'
        //     AND ".$postTable.".post_status != 'trash' 
        //     ".$whereCondition."
        //     GROUP BY ".$wcOrderTable.".order_id
        //     ORDER BY ".$wcOrderTable.".date_created DESC
        //     )";

        $results = $wpdb->get_results( 
            $wpdb->prepare(
                "(
                    SELECT 
                    {$wcOrderTable}.order_id as wc_order_id,
                    {$wcOrderTable}.date_created as wc_date_created,
                    {$wcOrderTable}.status as wc_status,
                    {$postTable}.post_status,
                    {$transactionTable}.*
                    FROM {$wcOrderTable}
                    INNER JOIN {$transactionTable}
                    ON {$wcOrderTable}.order_id = {$transactionTable}.wp_wc_order_stat_order_id
                    INNER JOIN {$postTable}
                    ON {$wcOrderTable}.order_id = {$postTable}.ID
                    WHERE {$wcOrderTable}.status = 'wc-processing'
                    AND {$transactionTable}.status = 'new'
                    AND {$postTable}.post_status != 'trash' 
                    {$whereCondition}
                    GROUP BY {$wcOrderTable}.order_id
                    ORDER BY {$wcOrderTable}.date_created DESC
                )"
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
