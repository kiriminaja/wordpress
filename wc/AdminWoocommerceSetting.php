<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Kiriof_AdminWoocommerceSettings 
{
    function __construct(){
        $this->hook();
    }

    public function hook(){
        /** Add Column Shoporder List Woocommerce */
        add_filter('manage_edit-shop_order_columns', [$this,'kiriof_new_order_column'] );
        add_filter('manage_edit-shop_order_columns', [$this,'kiriof_add_order_column_header']);
        add_action( 'manage_shop_order_posts_custom_column', [$this,'kiriof_add_order_column_content'] );
        add_action( 'manage_shop_order_posts_custom_column', [$this,'kiriof_render_deficit_badge_in_status_column'], 20 );

        // HPOS support.
        add_action( 'woocommerce_shop_order_list_table_custom_column_content', [$this,'kiriof_render_deficit_badge_hpos'], 20, 2 );
    }

    public function kiriof_new_order_column($columns){
        $columns['payment_method'] = 'Payment';
        $columns['shipping_method'] = 'Shipping';
        $columns['kiriminaja_info'] = 'KiriminAja';
        $columns['is_insurance'] = 'Insurance';

        return $columns;
    }

    public function kiriof_add_order_column_header($columns){
        $new_columns = array();
        foreach ($columns as $column_name => $column_info) {
            $new_columns[$column_name] = $column_info;
            if ('order_status' === $column_name) {
                $new_columns['shipping_method'] = __('Shipping', 'kiriminaja-official');
                $new_columns['kiriminaja_info'] = __('KiriminAja', 'kiriminaja-official');
                $new_columns['payment_method'] = __('Payment', 'kiriminaja-official');
                $new_columns['is_insurance'] = __('Insurance', 'kiriminaja-official');
            }
        }
        return $new_columns;

    }

    function kiriof_add_order_column_content( $column ) {
        global $post;
        
        $order    = wc_get_order( $post->ID );
        if ( ! $order ) {
            return;
        }

        $transactionKiriminaja = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getTransactionByWCOrderNumber($order->get_id());
        
        $shipping_methods = $order->get_shipping_methods();
        $shipping_method    = array_shift( $shipping_methods );
        $shipping_method_id = '';

        if ( is_object( $shipping_method ) && method_exists( $shipping_method, 'get_method_id' ) ) {
            $shipping_method_id = (string) $shipping_method->get_method_id();
        } elseif ( is_array( $shipping_method ) && isset( $shipping_method['method_id'] ) ) {
            $shipping_method_id = (string) $shipping_method['method_id'];
        }

        $is_kiriminaja_shipping = false;

        if ( '' !== $shipping_method_id && false !== strpos( $shipping_method_id, 'kiriminaja-official' ) ) {
            $is_kiriminaja_shipping = true;
        }

        if ( ! $is_kiriminaja_shipping && $transactionKiriminaja ) {
            $is_kiriminaja_shipping = true;
        }


        if ( 'shipping_method' === $column ) {
            echo esc_html( $order->get_shipping_method() );
        }

        if ( 'kiriminaja_info' === $column ) {
            if ( ! $transactionKiriminaja ) {
                echo '-';
                return;
            }

            $has_output = false;

            if ( ! empty( $transactionKiriminaja->order_id ) ) {
                echo wp_kses_post(
                    '<div><span style="color:#50575e">KA Order ID: </span><span style="font-weight:700">' . esc_html( $transactionKiriminaja->order_id ) . '</span></div>'
                );
                $has_output = true;
            }

            if ( ! empty( $transactionKiriminaja->awb ) ) {
                echo wp_kses_post(
                    '<div><span style="color:#50575e">AWB: </span><span style="font-weight:700">' . esc_html( $transactionKiriminaja->awb ) . '</span></div>'
                );
                $has_output = true;
            }

            if ( ! $has_output ) {
                echo '-';
            }
        }

        if ( 'payment_method' === $column ) {

            if($order->get_payment_method() == 'cod'){
                
                echo esc_html( $order->get_payment_method() );
                
                if( ! $is_kiriminaja_shipping ){
                    return false;
                }

                echo wp_kses_post( '<br/>Fee: ' .  (!$transactionKiriminaja ? '-': wc_price($transactionKiriminaja->cod_fee) ) );
                
                if( $order->get_meta( '_kiriof_ppn' )){
                    echo '<br/><em>('.esc_html__('include 11% Vat','kiriminaja-official').')</em>';
                }

            }else{
                echo esc_html( 'Non Cod' );
                echo wp_kses_post( '<br/>Method: ' . esc_html( $order->get_payment_method() ) );
            }
        }

        if( 'is_insurance' === $column ) {
            $insurance_front = $order->get_meta('_kiriof_insurance');
            $insurance_admin_billing = ucfirst( $order->get_meta('_billing_kiriof_insurance') ) ?? '';
            $insurance_admin_shipping = Ucfirst( $order->get_meta('_shipping_kiriof_insurance') ) ?? '';

            if( ! $is_kiriminaja_shipping ){
                echo '-';
                return false;
            }

            if( !empty($insurance_admin_billing) ){
                echo esc_html( $insurance_admin_billing ? 'Yes':'No' );
                if($insurance_admin_billing){
                    echo wp_kses_post( '<br/>Cost: ' .  (!$transactionKiriminaja ? '-': wc_price($transactionKiriminaja->insurance_cost) ) );
                }
            }else if(!empty($insurance_admin_shipping)){
                echo esc_html( $insurance_admin_shipping ? 'Yes':'No' );
                if($insurance_admin_shipping){
                    echo wp_kses_post( '<br/>Cost: ' .  (!$transactionKiriminaja ? '-': wc_price($transactionKiriminaja->insurance_cost) ) );
                }
            }else{ 
                echo esc_html( $insurance_front ? 'Yes':'No' );
                if($insurance_front){
                    echo wp_kses_post( '<br/>Cost: ' .  (!$transactionKiriminaja ? '-': wc_price($transactionKiriminaja->insurance_cost) ) );
                }
            }

        }
    }

    /**
     * Add a "COD Deficit" badge in the order_status column for legacy WC (non-HPOS).
     */
    public function kiriof_render_deficit_badge_in_status_column( $column ) {
        if ( 'order_status' !== $column ) {
            return;
        }
        global $post;
        $order = $post ? wc_get_order( $post->ID ) : null;
        if ( ! $order ) {
            return;
        }
        $transaction = ( new \KiriminAjaOfficial\Repositories\TransactionRepository() )->getTransactionByWCOrderNumber( $order->get_id() );
        if ( ! $transaction || empty( $transaction->is_deficit ) ) {
            return;
        }
        echo '<mark class="order-status tips" style="background:#d63638;color:#fff;display:inline-flex;line-height:1;padding:2px 6px;border-radius:3px;font-size:11px;font-weight:600;margin-top:4px;white-space:nowrap;" data-tip="' . esc_attr__( 'COD Deficit', 'kiriminaja-official' ) . '"><span>' . esc_html__( 'COD Deficit', 'kiriminaja-official' ) . '</span></mark>';
    }

    /**
     * Add a "COD Deficit" badge in the order_status column for HPOS.
     *
     * @param string    $column Column ID.
     * @param \WC_Order $order  Order object.
     */
    public function kiriof_render_deficit_badge_hpos( $column, $order ) {
        if ( 'order_status' !== $column ) {
            return;
        }
        if ( ! $order instanceof \WC_Order ) {
            return;
        }
        $transaction = ( new \KiriminAjaOfficial\Repositories\TransactionRepository() )->getTransactionByWCOrderNumber( $order->get_id() );
        if ( ! $transaction || empty( $transaction->is_deficit ) ) {
            return;
        }
        echo '<mark class="order-status tips" style="background:#d63638;color:#fff;display:inline-flex;line-height:1;padding:2px 6px;border-radius:3px;font-size:11px;font-weight:600;margin-top:4px;white-space:nowrap;" data-tip="' . esc_attr__( 'COD Deficit', 'kiriminaja-official' ) . '"><span>' . esc_html__( 'COD Deficit', 'kiriminaja-official' ) . '</span></mark>';
    }
}

new Kiriof_AdminWoocommerceSettings();
