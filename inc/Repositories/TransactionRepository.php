<?php

namespace Inc\Repositories;

class TransactionRepository{

    public $table;
    public function __construct(){
        global $wpdb;
        $this->table = $wpdb->prefix . 'kiriminaja_transactions';
    }
    
    public function getTransactionByOrderIds($orderIds){
        global $wpdb;
        $query = $wpdb->get_results( "SELECT * FROM `".$this->table."` WHERE order_id IN ('".implode("', '", $orderIds)."')" );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getTransactionByOrderId($orderId){
        global $wpdb;
        $query = $wpdb->get_row( "SELECT * FROM `".$this->table."` WHERE order_id  = '".$orderId."'");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function getTransactionByWCOrderNumber($wp_wc_order_stat_order_id){
        global $wpdb;
        $query = $wpdb->get_row( "SELECT * FROM `".$this->table."` WHERE wp_wc_order_stat_order_id  = '".$wp_wc_order_stat_order_id."'");
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

    public function getTransactionByAWBforTracking($awb){
        global $wpdb;
        $transactionTable = $wpdb->prefix . 'kiriminaja_transactions';
        
        $get_wc_orderid = $wpdb->get_row( 
            $wpdb->prepare(
                "SELECT wp_wc_order_stat_order_id FROM `$transactionTable` WHERE `awb` LIKE %s OR `wp_wc_order_stat_order_id` LIKE %s",
                '%' . $awb . '%',
                '%' . $awb . '%'
            )
        );

        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        
        $wc_order_id = is_null($get_wc_orderid) ? '' : $get_wc_orderid->wp_wc_order_stat_order_id;

        $query = $this->getTransactionByWCOrderNumberForTracking( $wc_order_id );

        return $query;
    }
    
    public function getTransactionByPickupNumber($pickupNumber){
        global $wpdb;
        $query = $wpdb->get_results( "SELECT * FROM `".$this->table."` WHERE pickup_number = '".$pickupNumber."'" );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function updateTransactionByCallback($payloads){
        global $wpdb;
        $wpdb->update($this->table, $payloads['changes'], $payloads['condition']);
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return true;
    }
    
    public function getTransactionDataByPickupNumber($pickupNumber){
        global $wpdb;
        $query = $wpdb->get_results( "SELECT * FROM `".$this->table."` WHERE pickup_number = '".$pickupNumber."'");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }

    public function getTransactionByWCOrderId($WCOrderId){
        global $wpdb;
        $query = $wpdb->get_row( "SELECT * FROM `".$this->table."` WHERE wp_wc_order_stat_order_id = '".$WCOrderId."'");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    
    public function createTransaction($payload){
        /** Transaction Table Insert*/
        global $wpdb;
        $wpdb->query("INSERT INTO ".$this->table."
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
        $query = $wpdb->get_row( "SELECT * FROM `".$this->table."` WHERE created_at IS NOT NULL ORDER BY created_at ASC");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }

    public function getTransactionByOrderIdsForResiPrint($orderIds){
        global $wpdb;

        $transactionTable = $wpdb->prefix . 'kiriminaja_transactions';
        $wpPostTable = $wpdb->prefix . 'posts';
        $wcOrderProductLookupTable = $wpdb->prefix . 'wc_order_product_lookup';
        
        $query = $wpdb->get_results( "
                    SELECT 
                    `".$transactionTable."`.* 
                    , `".$wpPostTable."`.post_excerpt as checkout_note
                    , count(`".$wcOrderProductLookupTable."`.product_id) as item_count
                    FROM `".$transactionTable."`
                    INNER JOIN `".$wpPostTable."`
                    ON `".$transactionTable."`.wp_wc_order_stat_order_id = `".$wpPostTable."`.ID
                    INNER JOIN `".$wcOrderProductLookupTable."`
                    ON `".$transactionTable."`.wp_wc_order_stat_order_id = `".$wcOrderProductLookupTable."`.order_id
                    WHERE `".$transactionTable."`.order_id IN ('".implode("', '", $orderIds)."')
                    GROUP BY `".$transactionTable."`.wp_wc_order_stat_order_id
                    " );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }

    public function getCountTransactionProcessNew(){
        global $wpdb;
        $query = $wpdb->get_var( "SELECT count(*) FROM `".$this->table."` WHERE status ='new'");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }

    public function updateTransaction($payload){
        global $wpdb; 
        $query = $wpdb->query(
            "UPDATE ".$this->table.
            " SET 
                destination_sub_district_id = '".$payload['destination_sub_district_id']."',
                destination_sub_district = '".$payload['destination_sub_district']."',
                service = '".$payload['service']."',
                service_name = '".$payload['service_name']."',
                shipping_cost ='".$payload['shipping_cost']."',
                insurance_cost ='".$payload['insurance_cost']."',
                cod_fee ='".$payload['cod_fee']."'
            WHERE 
                wp_wc_order_stat_order_id = '".$payload['wp_wc_order_stat_order_id']."'
            "
        );

        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return true;
    }
}