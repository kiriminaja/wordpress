<?php
namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Repositories\KiriminajaApiRepository;
use KiriminAjaOfficial\Services\ShippingProcessServices\GetShippingProcessDetailService;
use KiriminAjaOfficial\Services\ShippingProcessServices\GetShippingProcessPayment;
use Throwable;
class ShippingProcessController
{
    public function register()
    {
        /** getShippingProcessDetail */
        add_action('wp_ajax_kiriof_get_shipping_process_detail', array($this, 'getShippingProcessDetail'));
        /** getPaymentForm */
        add_action('wp_ajax_kiriof_get_payment_form', array($this, 'getPaymentForm'));
        add_action('wp_ajax_kiriof_get_shipping_reschedule_pickup', array($this, 'getShippingReschedulePickup'));
        /** Resi Print */
        add_action('init', function () {
            add_feed('transaction-resi-print', array($this, 'resiPrint'));
        });
    }
    function getShippingReschedulePickup()
    {
        if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
            wp_die();
        }
        // Check for nonce security - fail early
        if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
            wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
            wp_die();
        }
        $payment_id = isset($_POST['data']['payment_id']) ?  sanitize_text_field(wp_unslash($_POST['data']['payment_id'])) : '';
        $service = (new GetShippingProcessDetailService())->paymentId($payment_id)->call();
        if ($service->status !== 200) {
            wp_send_json_error($service);
        }
        $transactions_data = $service->data['transactions_data']; //array
        $order_ids = array_map(function ($transaction) {
            return $transaction->order_id;
        }, $transactions_data);
        $service_pickup = (new \KiriminAjaOfficial\Services\TransactionProcessServices\GetRequestPickupScheduleService())
            ->orderIds($order_ids)
            ->call();
        $service_pickup->data['transaction_summary']['order_id'] = $order_ids[0];
        wp_send_json_success($service_pickup);
    }
    function getShippingProcessDetail()
    {
        try {
            if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            // Check for nonce security - fail early
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }
            $payment_id = isset($_POST['data']['payment_id']) ?  sanitize_text_field(wp_unslash($_POST['data']['payment_id'])) : '';
            $service = (new GetShippingProcessDetailService())->paymentId($payment_id)->call();
            if ($service->status !== 200) {
                wp_send_json_error($service);
            }
            wp_send_json_success($service);
        } catch (Throwable $e) {
            wp_send_json_error(['status' => 400, $e->getMessage()]);
        }
    }
    function getPaymentForm()
    {
        try {
            if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            // Check for nonce security - fail early
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }
            $payment_id = isset($_POST['data']['payment_id']) ? sanitize_text_field(wp_unslash($_POST['data']['payment_id'])) : '';
            $service = (new GetShippingProcessPayment())->payment_id($payment_id)->call();
            if ($service->status !== 200) {
                wp_send_json_error($service);
            }
            wp_send_json_success($service);
        } catch (Throwable $e) {
            wp_send_json_error(['status' => 400, $e->getMessage()]);
        }
    }
    function resiPrint()
    {
        try {
            if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) || ! current_user_can( 'manage_woocommerce' ) ) {
                wp_safe_redirect( home_url( '/404' ) );
                exit;
            }
            // Verify nonce to prevent CSRF on this privileged label download endpoint.
            $kiriof_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $kiriof_nonce, 'kiriof_resi_print' ) ) {
                wp_safe_redirect( home_url( '/404' ) );
                exit;
            }
            $orderIdsParam = isset($_GET['oids']) ? sanitize_text_field(wp_unslash($_GET['oids'])) : '';
            $orderIds = array_unique(explode(',', $orderIdsParam) ?? []);
            if (count($orderIds) < 1) {
                wp_safe_redirect(home_url('/404'));
                exit;
            }
            $transactions = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getTransctionByOrderIds($orderIds);
            $awbs = [];
            $filename = '';
            foreach ($transactions as $transaction) {
                if (isset($transaction->awb) && !empty($transaction->awb)) {
                    $awbs[] = $transaction->awb;
                }
                if (isset($transaction->pickup_number) && !empty($transaction->pickup_number)) {
                    $filename = $transaction->pickup_number;
                }
            }
            if (count($awbs) == 1) {
                $filename = $awbs[0] ?? 'resi';
            }
            $getAwbData = (new KiriminajaApiRepository())->getPrintAwb($awbs);
            if (
                !isset($getAwbData['data']->data->url) ||
                empty($getAwbData['data']->data->url)
            ) {
                wp_safe_redirect(home_url('/404'));
                exit;
            }
            $pdfUrl = $getAwbData['data']->data->url;
            
            // Use WordPress HTTP API instead of file_get_contents
            $response = wp_remote_get( $pdfUrl, array(
                'timeout' => 30,
                'sslverify' => true,
            ) );
            
            if ( is_wp_error( $response ) ) {
                wp_safe_redirect( home_url( '/404' ) );
                exit;
            }
            
            $pdfContent = wp_remote_retrieve_body( $response );
            if ( empty( $pdfContent ) ) {
                wp_safe_redirect( home_url( '/404' ) );
                exit;
            }
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="print-resi-' . $filename . '.pdf"');
            header('Content-Length: ' . strlen($pdfContent));
            echo $pdfContent; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        } catch (\Throwable $e) {
            wp_safe_redirect(home_url('/404'));
            exit;
        }
    }
}