


<?php
class requestPickupIndex {
    function __construct(){
        global $wpdb;
        global $items_per_page;
        global $page;
        global $offset;
        global $results;
        global $total_pages;
        global $next_page_link;
        global $prev_page_link;
        $paymentTable = $wpdb->prefix . 'kiriminaja_payments';
        $transactionTable = $wpdb->prefix . 'kiriminaja_transactions';
        $query = "(
            SELECT 
            `".$paymentTable."`.*
            ,sum(CASE WHEN `".$transactionTable."`.cod_fee = 0 THEN `".$transactionTable."`.shipping_cost+`".$transactionTable."`.insurance_cost ELSE 0 END) as cost
            FROM `".$paymentTable."` 
            INNER JOIN `".$transactionTable."`
            ON `".$paymentTable."`.pickup_number = `".$transactionTable."`.pickup_number
            GROUP BY `".$paymentTable."`.pickup_number
            )";

        $totalQuery = $wpdb->get_results( "SELECT id FROM `".$paymentTable."`" );
        $total = count($totalQuery);

        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis('last_error',@$wpdb->last_error);
        }
        $items_per_page = 3;
        $page = @$_GET['cpage'] ?? 1;
        $offset = ( $page * $items_per_page ) - $items_per_page;
        $results = $wpdb->get_results( $query . " ORDER BY id LIMIT ${offset}, ${items_per_page}" );
        $total_pages = ceil($total/$items_per_page);


        $next_page_link = @home_url().'/wp-admin/admin.php?';
        $prev_page_link = @home_url().'/wp-admin/admin.php?';
        
        foreach ($_GET as $key => $value){
            if ($key!=='cpage'){
                $next_page_link.=$key.'='.$value.'&';
                $prev_page_link.=$key.'='.$value.'&';                
            }
        }
        
        $next_page_link= $page+1<=$total_pages ? $next_page_link.'cpage='.$page+1 : '';
        $prev_page_link= $page-1>0 ? $prev_page_link.'cpage='.$page-1 : '';
        
        /** Return vars and view*/
        include 'view/index.php';
    }
}


new requestPickupIndex();







?>
