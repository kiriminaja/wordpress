<?php
class AdminWoocommerceSettings 
{
    function __construct(){
        $this->hook();
    }

    public function hook(){
        /** Add Column Shoporder List Woocommerce */
        add_filter('manage_edit-shop_order_columns', [$this,'kj_new_order_column'] );
        add_filter('manage_edit-shop_order_columns', [$this,'kj_add_order_column_header']);
        add_action( 'manage_shop_order_posts_custom_column', [$this,'kj_add_order_column_content'] );

    }

    public function kj_new_order_column($columns){
        $columns['payment_method'] = 'Payment';
        $columns['shipping_method'] = 'Shipping';
        $columns['is_insurance'] = 'Insurance';

        return $columns;
    }

    public function kj_add_order_column_header($columns){
        $new_columns = array();
        foreach ($columns as $column_name => $column_info) {
            $new_columns[$column_name] = $column_info;
            if ('order_status' === $column_name) {
                $new_columns['shipping_method'] = __('Shipping', 'kiriminaja');
                $new_columns['payment_method'] = __('Payment', 'kiriminaja');
                $new_columns['is_insurance'] = __('Insurance', 'kiriminaja');
            }
        }
        return $new_columns;

    }

    function kj_add_order_column_content( $column ) {
        global $post;
        
        $order    = wc_get_order( $post->ID );
        $transactionKiriminaja = (new \Inc\Repositories\TransactionRepository())->getTransactionByWCOrderNumber($order->get_id());
    
        if ( 'shipping_method' === $column ) {
            echo $order->get_shipping_method();
        }
        if ( 'payment_method' === $column ) {
            echo $order->get_payment_method();
            if($order->get_payment_method() == 'cod'){
                echo '<br/>Fee: '.  (!$transactionKiriminaja ? '-': wc_price($transactionKiriminaja->cod_fee));
            }
        }

        if( 'is_insurance' === $column ) {
            echo $order->get_meta('_kj_insurance') ? 'Yes':'No';
            if($order->get_meta('_kj_insurance')){
                echo '<br/>Cost: '.  (!$transactionKiriminaja ? '-': wc_price($transactionKiriminaja->insurance_cost));
            }
        }
    }
}

new AdminWoocommerceSettings();