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
        
        $ka_id_shipping = 'kiriminaja';
        $shipping_method_id = array_shift( $order->get_shipping_methods() )['method_id'];


        if ( 'shipping_method' === $column ) {
            echo $order->get_shipping_method(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        if ( 'payment_method' === $column ) {

            if($order->get_payment_method() == 'cod'){
                
                echo $order->get_payment_method(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                
                if( $shipping_method_id != $ka_id_shipping ){
                    return false;
                }

                echo '<br/>Fee: '.  (!$transactionKiriminaja ? '-': wc_price($transactionKiriminaja->cod_fee)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                
                if( $order->get_meta( '_kj_ppn' )){
                    echo '<br/><em>('.esc_html__('include 11% Vat','kiriminaja').')</em>';
                }

            }else{
                echo 'Non Cod';
                echo '<br/>Method: '. $order->get_payment_method(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
        }

        if( 'is_insurance' === $column ) {
            $insurance_front = $order->get_meta('_kj_insurance');
            $insurance_admin_billing = ucfirst( $order->get_meta('_billing_kj_insurance') ) ?? '';
            $insurance_admin_shipping = Ucfirst( $order->get_meta('_shipping_kj_insurance') ) ?? '';

            if( $shipping_method_id != $ka_id_shipping ){
                echo '-';
                return false;
            }

            if( !empty($insurance_admin_billing) ){
                echo $insurance_admin_billing ? 'Yes':'No';
                if($insurance_admin_billing){
                    echo '<br/>Cost: '.  (!$transactionKiriminaja ? '-': wc_price($transactionKiriminaja->insurance_cost)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            }else if(!empty($insurance_admin_shipping)){
                echo $insurance_admin_shipping ? 'Yes':'No';
                if($insurance_admin_shipping){
                    echo '<br/>Cost: '.  (!$transactionKiriminaja ? '-': wc_price($transactionKiriminaja->insurance_cost)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            }else{ 
                echo $insurance_front ? 'Yes':'No';
                if($insurance_front){
                    echo '<br/>Cost: '.  (!$transactionKiriminaja ? '-': wc_price($transactionKiriminaja->insurance_cost)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            }

        }
    }
}

new AdminWoocommerceSettings();