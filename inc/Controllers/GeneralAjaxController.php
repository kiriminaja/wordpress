<?php

namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if (! \defined('ABSPATH')) {
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
                // phpcs:ignore-start WordPress.Security.ValidatedSanitizedInput.MissingUnslash
                $data = (isset($_POST['data']) && !empty($_POST['data']))
                    ? array_map('sanitize_text_field', $_POST['data']) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
                    : [];
                // phpcs:ignore-end

                if (empty($data['search'])) {
                    $data['search'] = sanitize_text_field($data['term']);
                }
                $kiriminajaSubDistrictSearch = (new \KiriminAjaOfficial\Services\KiriminajaApiService())->sub_district_search($data['search']);

                if ($kiriminajaSubDistrictSearch->status !== 200) {
                    wp_send_json_success([]);
                }
                wp_send_json_success($kiriminajaSubDistrictSearch->data);
            } else {
                wp_send_json_error(['code' => '401', 'msg' => wc_add_notice('Security Check Kiriminaja', "error")]);
            }
            wp_die();
        } catch (\Throwable $e) {
            wp_send_json_success([]);
            wp_die();
        }
    }

    function kj_getDestinationArea()
    {
        // Check for nonce security - fail early
        if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'kiriof-destination')) {
            wp_send_json_error(array('code' => '401', 'msg' => wc_add_notice('Security Check Kiriminaja', 'error')));
            wp_die();
        }

        if (is_checkout()) {
            $country = isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '';
            if (empty($country) || $country != 'ID') {
                wp_send_json_success(['code' => '400', 'msg' => wc_add_notice('Please Country/Region Indonesia', "error")]);
                wp_die();
            }
        }
        $destination_id = isset($_POST['val']) ? (int) $_POST['val'] : 0;
        $payment = isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : null;
        $text = isset($_POST['text']) ? sanitize_text_field(wp_unslash($_POST['text'])) : '';
        $insurance = isset($_POST['insurance']) ? sanitize_text_field(wp_unslash($_POST['insurance'])) : '';
        $different_address = ! empty($_POST['different_address']);

        if ($different_address) {
            WC()->session->set('shipping_destination_id', $destination_id);
            WC()->session->set('shipping_destination_name', $text);
        }
        // Set the data (the value can be also an indexed array)
        WC()->session->set('destination_id', $destination_id);
        WC()->session->set('destination_name', $text);
        WC()->session->set('kiriof_payment_method', $payment);
        WC()->session->set('kiriof_insurance', $insurance);
        WC()->cart->calculate_totals();
        wp_send_json_success(['code' => '200', 'msg' => 'Success']);
    }
    public function kj_getDataAfterUpdateCheckout()
    {
        // Check for nonce security - fail early
        if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'kiriof-update-checkout')) {
            wp_send_json_error(array('code' => '401', 'msg' => wc_add_notice('Security Check Kiriminaja', 'error')));
            wp_die();
        }

        $shipping_metode_id = isset($_POST['shipping_metode_id']) ? sanitize_text_field(wp_unslash($_POST['shipping_metode_id'])) : '';
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : '';
        $destination_id = isset($_POST['destination_id']) ? (int) $_POST['destination_id'] : 0;
        $insurance_input = isset($_POST['insurance']) ? (int) $_POST['insurance'] : 0;

        $ex_shipping = explode('_', $shipping_metode_id);
        $datas = [];
        if (!empty($shipping_metode_id) && $ex_shipping[0] == 'kiriminaja-official') {
            $insurance = empty($insurance_input) ? 0 : 1;
            $payload = [
                'destination_area_id'   => $destination_id,
                'expedition'            => substr($shipping_metode_id, 11),
                'is_insurance'          => $insurance,
                'is_cod'                => $payment_method === 'cod',
                'wc_cart_contents'      => WC()->cart->cart_contents,
            ];
            $service = (new \KiriminAjaOfficial\Services\CheckoutServices\CheckoutCalculationService($payload))->call();

            if (!empty($service->data)) {

                if (!empty($payment_method)) {
                    $datas['cod_fee'] = wc_price($service->data['calculation_result']['cod_amt']) ??  0;
                    $datas['is_cod_amt'] = $service->data['calculation_result']['cod_amt'];
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
                WC()->cart->calculate_totals();

                wp_send_json_success($datas);
            } else {

                WC()->cart->calculate_totals();
                wp_send_json_error(['is_insurance' => 0, 'is_cod_amt' => 0]);
            }
        }
        WC()->cart->calculate_totals();
        wp_send_json_error(['is_insurance' => 0, 'is_cod_amt' => 0]);
    }
}
