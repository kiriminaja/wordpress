<?php

namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GeneralAjaxController
{
    public function register()
    {
        add_action('wp_ajax_kiriminaja_subdistrict_search', array($this, 'kiriminajaSubdistrictSearch'));
        add_action('wp_ajax_nopriv_kiriminaja_subdistrict_search', array($this, 'kiriminajaSubdistrictSearch'));

        add_action('wp_ajax_kiriof_get_destination_area', array($this, 'kiriof_getDestinationArea'));
        add_action('wp_ajax_nopriv_kiriof_get_destination_area', array($this, 'kiriof_getDestinationArea'));
        add_action('wp_ajax_kiriof_get_data_after_update_checkout', array($this, 'kiriof_getDataAfterUpdateCheckout'));
        add_action('wp_ajax_nopriv_kiriof_get_data_after_update_checkout', array($this, 'kiriof_getDataAfterUpdateCheckout'));
    }

    public function kiriminajaSubdistrictSearch()
    {
        try {
            if (
                isset($_POST['nonce']) &&
                wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), KIRIOF_NONCE)
            ) {
                $data = ( isset( $_POST['data'] ) && is_array( $_POST['data'] ) )
                    ? map_deep( wp_unslash( $_POST['data'] ), 'sanitize_text_field' )
                    : array();

                if (empty($data['search'])) {
                    $data['search'] = isset( $data['term'] ) ? sanitize_text_field( (string) $data['term'] ) : '';
                }
                if ( empty( $data['search'] ) ) {
                    $data['search'] = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
                }
                if ( empty( $data['search'] ) ) {
                    $data['search'] = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
                }
                $kiriminajaSubDistrictSearch = (new \KiriminAjaOfficial\Services\KiriminajaApiService())->sub_district_search($data['search']);

                if ($kiriminajaSubDistrictSearch->status !== 200) {
                    wp_send_json_success([]);
                }
                wp_send_json_success($kiriminajaSubDistrictSearch->data);
            } else {
                wp_send_json_error(['code' => '401', 'msg' => wc_add_notice( __( 'Security Check Kiriminaja', 'kiriminaja-official' ), "error" )]);
            }
            wp_die();
        } catch (\Throwable $e) {
            wp_send_json_success([]);
            wp_die();
        }
    }

    function kiriof_getDestinationArea()
    {
        // Check for nonce security - fail early
        if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'kiriof-destination')) {
            wp_send_json_error(array('code' => '401', 'msg' => wc_add_notice( __( 'Security Check Kiriminaja', 'kiriminaja-official' ), 'error' )));
            wp_die();
        }

        if (is_checkout()) {
            $country = isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '';
            if (empty($country) || $country != 'ID') {
                wp_send_json_success(['code' => '400', 'msg' => wc_add_notice( __( 'Please Country/Region Indonesia', 'kiriminaja-official' ), "error" )]);
                wp_die();
            }
        }
        $destination_id = isset($_POST['val']) ? (int) $_POST['val'] : 0;
        $payment = isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : null;
        $text = isset($_POST['text']) ? sanitize_text_field(wp_unslash($_POST['text'])) : '';
        $insurance = isset($_POST['insurance']) ? sanitize_text_field(wp_unslash($_POST['insurance'])) : '';
        $different_address = ! empty($_POST['different_address']);
        $postcode = isset($_POST['postcode']) ? sanitize_text_field(wp_unslash($_POST['postcode'])) : '';
        $postcode = trim( preg_replace( '/\s+/', '', (string) $postcode ) );

        if ($different_address) {
            WC()->session->set('shipping_destination_id', $destination_id);
            WC()->session->set('shipping_destination_name', $text);
        } else {
            WC()->session->set('shipping_destination_id', $destination_id);
            WC()->session->set('shipping_destination_name', $text);
        }
        // Set the data (the value can be also an indexed array)
        WC()->session->set('destination_id', $destination_id);
        WC()->session->set('destination_name', $text);
        WC()->session->set('kiriof_payment_method', $payment);
        WC()->session->set('kiriof_insurance', $insurance);
        WC()->session->set('kiriof_checkout_postcode', $postcode);

        // Save postcode→district mapping so the district auto-restores on next page load.
        if ( $destination_id > 0 && '' !== $postcode ) {
            $saved_map = (array) WC()->session->get( 'kiriof_destination_postcode_map', array() );
            $saved_map[ $postcode ] = array(
                'destination_id'   => $destination_id,
                'destination_name' => $text,
            );
            WC()->session->set( 'kiriof_destination_postcode_map', $saved_map );
        }
        wp_send_json_success(['code' => '200', 'msg' => __( 'Success', 'kiriminaja-official' )]);
    }
    public function kiriof_getDataAfterUpdateCheckout()
    {
        // Check for nonce security - fail early
        if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'kiriof-update-checkout')) {
            wp_send_json_error(array('code' => '401', 'msg' => wc_add_notice( __( 'Security Check Kiriminaja', 'kiriminaja-official' ), 'error' )));
            wp_die();
        }

        $shipping_metode_id = isset($_POST['shipping_metode_id']) ? sanitize_text_field(wp_unslash($_POST['shipping_metode_id'])) : '';
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : '';
        $destination_id = isset($_POST['destination_id']) ? (int) $_POST['destination_id'] : 0;
        $insurance_input = isset($_POST['insurance']) ? (int) $_POST['insurance'] : 0;

        $ex_shipping = explode('_', $shipping_metode_id);
        // Block checkout may use colon separator
        if (count($ex_shipping) === 1 && str_contains($shipping_metode_id, ':')) {
            $ex_shipping = explode(':', $shipping_metode_id);
        }
        $datas = [];
        if (!empty($shipping_metode_id) && $ex_shipping[0] == 'kiriminaja-official') {
            WC()->session->set( 'kiriof_chosen_shipping_methods', array( $shipping_metode_id ) );
            // WooCommerce reads this core session key when re-rendering the shipping radio list.
            WC()->session->set( 'chosen_shipping_methods', array( $shipping_metode_id ) );

            $insurance = empty($insurance_input) ? 0 : 1;
            $expedition = $shipping_metode_id;
            if (str_starts_with($shipping_metode_id, 'kiriminaja-official_')) {
                $expedition = substr($shipping_metode_id, strlen('kiriminaja-official_'));
            } elseif (str_starts_with($shipping_metode_id, 'kiriminaja-official:')) {
                $expedition = substr($shipping_metode_id, strlen('kiriminaja-official:'));
            }
            WC()->session->set( 'kiriof_expedition', $expedition );

            $payload = [
                'destination_area_id'   => $destination_id,
                'expedition'            => $expedition,
                'is_insurance'          => $insurance,
                'is_cod'                => $payment_method === 'cod',
                'wc_cart_contents'      => WC()->cart->cart_contents,
            ];
            $service = (new \KiriminAjaOfficial\Services\CheckoutServices\CheckoutCalculationService($payload))->call();

            if (!empty($service->data)) {

                if ('cod' === $payment_method) {
                    $datas['cod_fee'] = wc_price($service->data['calculation_result']['cod_amt']) ??  0;
                    $datas['is_cod_amt'] = $service->data['calculation_result']['cod_amt'];
                } else {
                    $datas['cod_fee'] = wc_price(0);
                    $datas['is_cod_amt'] = 0;
                }

                if (!empty($shipping_metode_id)) {
                    $datas['insurance_fee'] = wc_price($service->data['calculation_result']['insurance_amt']) ?? 0;
                    $datas['is_insurance'] = $service->data['calculation_result']['insurance_amt'];
                }
                if (!empty($payment_method) || !empty($shipping_metode_id)) {
                    $cod_amt = (float) ($service->data['calculation_result']['cod_amt'] ?? 0);
                    $ongkir_amt = (float) ($service->data['calculation_result']['ongkir_fee_amt'] ?? 0);
                    $insurance_amt = (float) ($service->data['calculation_result']['insurance_amt'] ?? 0);
                    $order_total = (float) ($service->data['calculation_result']['cart_total_amt'] ?? 0);
                    $datas['price_total'] = wc_price($cod_amt + $insurance_amt + $order_total + $ongkir_amt);
                }
                $datas['force_insurance'] = $service->data['calculation_result']['selected_expedition']->force_insurance == false ? 0 : 1;
                $datas['services'] = $service->data;

                // Cache fees in session so woocommerce_cart_calculate_fees can add them
                // as WC cart fees (works on both traditional and block checkout).
                WC()->session->set( 'kiriof_cached_insurance_amt', $datas['is_insurance'] );
                WC()->session->set( 'kiriof_cached_cod_amt', $datas['is_cod_amt'] );
                WC()->session->set(
                    'kiriof_cached_fee_context',
                    $this->kiriof_get_fee_cache_context( $shipping_metode_id, $destination_id, $payment_method, $insurance )
                );

                wp_send_json_success($datas);
            } else {

                wp_send_json_error(['is_insurance' => 0, 'is_cod_amt' => 0]);
            }
        }
        wp_send_json_error(['is_insurance' => 0, 'is_cod_amt' => 0]);
    }

    private function kiriof_get_fee_cache_context( $shipping_method, $destination_id, $payment_method, $insurance ) {
        $discount_context = $this->kiriof_get_cart_discount_context();

        return array(
            'shipping_method' => $shipping_method,
            'destination_id'  => $destination_id,
            'payment_method'  => $payment_method,
            'insurance'       => $insurance,
            'coupon_codes'    => $discount_context['coupon_codes'],
            'discount_total'  => $discount_context['discount_total'],
            'discount_tax'    => $discount_context['discount_tax'],
        );
    }

    private function kiriof_get_cart_discount_context() {
        $context = array(
            'coupon_codes'   => '',
            'discount_total' => 0,
            'discount_tax'   => 0,
        );

        if ( ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->cart ) || ! WC()->cart ) {
            return $context;
        }

        if ( method_exists( WC()->cart, 'get_coupons' ) ) {
            $coupon_codes = array_keys( (array) WC()->cart->get_coupons() );
            if ( is_array( $coupon_codes ) && ! empty( $coupon_codes ) ) {
                $coupon_codes = array_filter( array_map( 'sanitize_text_field', $coupon_codes ) );
                sort( $coupon_codes );
                $context['coupon_codes'] = implode( ',', $coupon_codes );
            }
        }
        if ( method_exists( WC()->cart, 'get_discount_total' ) ) {
            $context['discount_total'] = (float) WC()->cart->get_discount_total();
        }
        if ( method_exists( WC()->cart, 'get_discount_tax' ) ) {
            $context['discount_tax'] = (float) WC()->cart->get_discount_tax();
        }

        return $context;
    }
}
