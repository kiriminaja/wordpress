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
        $query = $wpdb->get_row( "SELECT * FROM ".$this->table." WHERE created_at IS NOT NULL ORDER BY created_at ASC");
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
        /** update query */
        $query = $wpdb->get_var( 
            "SELECT count(*) FROM ".$this->table." tp 
            INNER JOIN ".$wpdb->prefix."posts p ON p.ID = tp.wp_wc_order_stat_order_id
            WHERE tp.status ='new' AND p.post_status = 'wc-processing'
        ");
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
                cod_fee ='".$payload['cod_fee']."',
                transaction_value ='".$payload['transaction_value']."'
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

    public function getHistoryPackageDatatable(array $payloads){
        global $wpdb;

        $search_value = $payloads['search_value'];
        $start        = $payloads['start'];
        $length       = $payloads['length'];
        $status       = $payloads['status'];
        $advancedsearch = $payloads['advancedsearch'];
        $total_query = '';

        $table_name = $this->table;
        
        $query = "SELECT * FROM $table_name WHERE destination_sub_district != '0' ";

        if (!empty($search_value)) {
            $query .= $wpdb->prepare(" AND (order_id LIKE %s OR destination_sub_district LIKE %s)", '%' . $wpdb->esc_like($search_value) . '%', '%' . $wpdb->esc_like($search_value) . '%');
        }
        
        if($status != 'all'){
            $query .= " AND status='$status' ";
            $total_query = " AND status='$status' ";
        }

        $sql_total = "SELECT COUNT(*) FROM $table_name WHERE destination_sub_district != '0' $total_query";

        if(!empty($advancedsearch)){
            if( isset( $advancedsearch['stext'] ) && !empty($advancedsearch['stext']) ){
                switch ($advancedsearch['prefix']) {
                    case 'oid':
                        $query.= $wpdb->prepare(" AND order_id LIKE %s", '%'. $wpdb->esc_like($advancedsearch['stext']). '%');
                        $sql_total .= $wpdb->prepare(" AND order_id LIKE %s", '%'. $wpdb->esc_like($advancedsearch['stext']). '%');
                        break;
                    case 'awb':
                        $query.= $wpdb->prepare(" AND awb LIKE %s", '%'. $wpdb->esc_like($advancedsearch['stext']). '%');
                        $sql_total .= $wpdb->prepare(" AND awb LIKE %s", '%'. $wpdb->esc_like($advancedsearch['stext']). '%');
                        break;
                }
                
            }
            if( isset( $advancedsearch['expedition'] ) && !empty($advancedsearch['expedition']) ){
                $query.= $wpdb->prepare(" AND service = %s", $advancedsearch['expedition']);
                $sql_total .= $wpdb->prepare(" AND service = %s", $advancedsearch['expedition']);
            }

            if( isset( $advancedsearch['payment'] ) && !empty($advancedsearch['payment']) ){
                if ($advancedsearch['payment'] == 'cod') {
                    $sql_total .= $wpdb->prepare(" AND cod_fee > %d", 0);
                    $query .= $wpdb->prepare(" AND cod_fee > %d", 0);
                }else{
                    $sql_total .= $wpdb->prepare(" AND cod_fee = %d", 0);
                    $query .= $wpdb->prepare(" AND cod_fee = %d", 0);
                }
            }
        }

        $query .= " ORDER BY created_at DESC"; 
        
        $total_count = $wpdb->get_var($sql_total);

        if ($length != -1) {
            $query .= $wpdb->prepare(" LIMIT %d, %d", $start, $length);
        }

        $results = $wpdb->get_results($query,'ARRAY_A');

        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        
        return compact(
            'results',
            'total_count',
        );

    }

    public function getCountTabHistory($status='all'){
        global $wpdb;
        
        $andWhere = ( $status != 'all' ) ? " AND status = '$status' " : '';

        $query = $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table." WHERE destination_sub_district != '0' $andWhere");
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \Inc\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
}