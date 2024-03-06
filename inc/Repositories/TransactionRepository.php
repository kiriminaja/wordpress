<?php

namespace Inc\Repositories;

class TransactionRepository{
    
    public function getTransactionByOrderIds($orderIds){
        global $wpdb;
        $query = $wpdb->get_results( "SELECT * FROM wp_kiriminaja_transactions WHERE order_id IN ('".implode("', '", $orderIds)."')" );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getTransactionByOrderId($orderId){
        global $wpdb;
        $query = $wpdb->get_row( "SELECT * FROM wp_kiriminaja_transactions WHERE order_id  = '".$orderId."'");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getTransactionByWCOrderNumber($wp_wc_order_stat_order_id){
        global $wpdb;
        $query = $wpdb->get_row( "SELECT * FROM wp_kiriminaja_transactions WHERE wp_wc_order_stat_order_id  = '".$wp_wc_order_stat_order_id."'");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getTransactionByWCOrderNumberForTracking($wp_wc_order_stat_order_id){
        global $wpdb;
        $transactionTable = $wpdb->prefix . 'kiriminaja_transactions';
        $wcTransactionTable = $wpdb->prefix . 'wc_order_stats';
        $postTable = $wpdb->prefix . 'posts';
        
        (new \Inc\Base\BaseInit())->logThis('$wp_wc_order_stat_order_id',[$wp_wc_order_stat_order_id]);
        
        $query = $wpdb->get_row( "SELECT 
        `".$transactionTable."`.*,
         `".$wcTransactionTable."`.date_paid as wc_date_paid,
         `".$postTable."`.post_status as wc_post_status
        FROM `".$transactionTable."` 
        INNER JOIN `".$wcTransactionTable."`
        ON `".$transactionTable."`.wp_wc_order_stat_order_id = `".$wcTransactionTable."`.order_id
        INNER JOIN `".$postTable."`
        ON `".$transactionTable."`.wp_wc_order_stat_order_id = `".$postTable."`.ID
        WHERE `".$transactionTable."`.wp_wc_order_stat_order_id  = '".$wp_wc_order_stat_order_id."'
        AND `".$postTable."`.post_status != 'trash'
        GROUP BY `".$transactionTable."`.wp_wc_order_stat_order_id");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getTransactionByPickupNumber($pickupNumber){
        global $wpdb;
        $query = $wpdb->get_results( "SELECT * FROM wp_kiriminaja_transactions WHERE pickup_number = '".$pickupNumber."'" );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function updateTransactionByCallback($payloads){
        global $wpdb;
        $wpdb->update('wp_kiriminaja_transactions', $payloads['changes'], $payloads['condition']);
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return true;
    }
    
    public function getTransactionDataByPickupNumber($pickupNumber){
        global $wpdb;
        $query = $wpdb->get_results( "SELECT * FROM wp_kiriminaja_transactions WHERE pickup_number = '".$pickupNumber."'");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }

    public function getTransactionByWCOrderId($WCOrderId){
        global $wpdb;
        $query = $wpdb->get_row( "SELECT * FROM wp_kiriminaja_transactions WHERE wp_wc_order_stat_order_id = '".$WCOrderId."'");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function createTransaction($payload){
        /** Transaction Table Insert*/
        global $wpdb;
        $table_name = 'wp_kiriminaja_transactions';
        $wpdb->query("INSERT INTO ".$table_name."
            (
            `order_id`, 
            `shipping_info`, 
            `destination_sub_district_id`, 
            `destination_sub_district`, 
            `status`, 
            `service`, 
            `service_name`, 
            `weight`, 
            `width`, 
            `height`, 
            `length`, 
            `shipping_cost`, 
            `insurance_cost`, 
            `cod_fee`, 
            `transaction_value`, 
            `created_at`, 
            `wp_wc_order_stat_order_id`
            )
            VALUES
            (
            '".$payload['order_id']."',
            '".$payload['shipping_info']."',
            '".$payload['destination_sub_district_id']."',
            '".$payload['destination_sub_district']."',
            '".$payload['status']."',
            '".$payload['service']."',
            '".$payload['service_name']."',
            '".$payload['weight']."',
            '".$payload['width']."',
            '".$payload['height']."',
            '".$payload['length']."',
            '".$payload['shipping_cost']."',
            '".$payload['insurance_cost']."',
            '".$payload['cod_fee']."',
            '".$payload['transaction_value']."',
            '".$payload['created_at']."',
            '".$payload['wp_wc_order_stat_order_id']."'
            )
            ");

        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return true;
    }

    public function getTransactionByOldestDate(){
        global $wpdb;
        $transactionTable = $wpdb->prefix . 'kiriminaja_transactions';
        $query = $wpdb->get_row( "SELECT * FROM `".$transactionTable."` WHERE created_at IS NOT NULL ORDER BY created_at ASC");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }

    public function getTransactionByOrderIdsForResiPrint($orderIds){
        global $wpdb;
        $query = $wpdb->get_results( "SELECT * FROM wp_kiriminaja_transactions WHERE order_id IN ('".implode("', '", $orderIds)."')" );
//        $query2 = $wpdb->get_results( "
//                    SELECT * FROM wp_kiriminaja_transactions 
//                    WHERE order_id IN ('".implode("', '", $orderIds)."')
//                    " );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
}