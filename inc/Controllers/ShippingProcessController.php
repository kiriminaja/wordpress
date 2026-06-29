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
        add_action('admin_post_kiriof_resi_print', array($this, 'handleResiPrintAdminPost'));
        add_action('admin_post_kiriof_resi_print_bulk', array($this, 'handleResiPrintBulkAdminPost'));
    }

    private function sanitizeResiPrintOrderIds( $raw_oids )
    {
        if ( ! is_array( $raw_oids ) ) {
            $raw_oids = explode( ',', (string) $raw_oids );
        }

        $order_ids = array_map(
            static function ( $order_id ) {
                return sanitize_text_field( trim( (string) $order_id ) );
            },
            $raw_oids
        );

        $order_ids = array_filter(
            $order_ids,
            static function ( $order_id ) {
                return '' !== $order_id;
            }
        );

        return array_values( $order_ids );
    }

    private function buildResiPrintErrorRedirectUrl( $message = '' )
    {
        $url = admin_url( 'admin.php?page=kiriminaja-request-pickup' );
        if ( '' !== $message ) {
            $url = add_query_arg( 'kiriof_print_error', rawurlencode( $message ), $url );
        }
        return $url;
    }

    private function redirectResiPrintFailure( $message = '' )
    {
        wp_safe_redirect( $this->buildResiPrintErrorRedirectUrl( $message ) );
        exit;
    }

    public function handleResiPrintAdminPost()
    {
        // Verify nonce before accessing request data.
        if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'kiriof_resi_print' ) ) {
            $this->logResiPrintFailure( 'invalid_nonce', array(
                'has_nonce' => isset( $_REQUEST['_wpnonce'] ),
            ) );
            $this->redirectResiPrintFailure( __( 'Unable to print resi because the request is not authorized.', 'kiriminaja-official' ) );
        }

        $_REQUEST['_kiriof_resi_print_nonce_checked'] = '1';

        if ( isset( $_REQUEST['oids'] ) ) {
            $_REQUEST['oids'] = $this->sanitizeResiPrintOrderIds( sanitize_text_field( wp_unslash( $_REQUEST['oids'] ) ) );
        } else {
            $_REQUEST['oids'] = array();
        }

        $this->resiPrint();
    }

    public function handleResiPrintBulkAdminPost()
    {
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'kiriof_resi_print_bulk' ) ) {
            $this->logResiPrintFailure( 'invalid_bulk_nonce', array(
                'has_nonce' => isset( $_POST['_wpnonce'] ),
            ) );
            $this->redirectResiPrintFailure( __( 'Unable to print resi because the request is not authorized.', 'kiriminaja-official' ) );
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via sanitizeResiPrintOrderIds
        $orderIds = $this->sanitizeResiPrintOrderIds( isset( $_POST['oids'] ) ? wp_unslash( $_POST['oids'] ) : array() );
        $this->outputResiPrint( $orderIds );
    }

    private function markTransactionsPrinted( array $orderIds )
    {
        if ( empty( $orderIds ) ) {
            return;
        }

        global $wpdb;
        $placeholders = implode( ',', array_fill( 0, count( $orderIds ), '%s' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Print status is updated immediately after successful label fetch.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}kiriminaja_transactions
                SET is_printed = %d, printed_at = %s
                WHERE order_id IN ({$placeholders})",
                1,
                current_time( 'mysql' ),
                ...$orderIds
            )
        );
    }

    private function logResiPrintFailure( string $reason, array $context = array() ): void
    {
        kiriof_log(
            'warning',
            'KiriminAja resi print failed before label redirect.',
            array_merge(
                array(
                    'source' => 'kiriminaja_print',
                    'reason' => $reason,
                ),
                $context
            )
        );
    }

    private function resolvePrintAwbUrl( $response ): string
    {
        $data = is_array( $response ) ? ( $response['data'] ?? null ) : null;
        $candidates = array(
            is_string( $data ) ? $data : null,
            $data->data->url ?? null,
            $data->url ?? null,
            $data->data->link ?? null,
            $data->link ?? null,
            $response['url'] ?? null,
        );

        foreach ( $candidates as $candidate ) {
            $candidate = is_string( $candidate ) ? trim( $candidate ) : '';
            if ( '' !== $candidate && preg_match( '#^https?://#i', $candidate ) ) {
                return $candidate;
            }
        }

        return '';
    }

    private function outputResiPrint( array $orderIds )
    {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) ) {
            $this->logResiPrintFailure( 'unauthorized', array(
                'is_logged_in' => is_user_logged_in(),
                'user_id'      => get_current_user_id(),
            ) );
            $this->redirectResiPrintFailure( __( 'Unable to print resi because the request is not authorized.', 'kiriminaja-official' ) );
        }

        if ( count( $orderIds ) < 1 ) {
            $this->logResiPrintFailure( 'empty_order_ids' );
            $this->redirectResiPrintFailure( __( 'Unable to print resi because no order was selected.', 'kiriminaja-official' ) );
        }

        $transactions = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getTransctionByOrderIds($orderIds);
        if ( empty( $transactions ) ) {
            $this->logResiPrintFailure( 'transactions_not_found', array(
                'order_ids' => $orderIds,
            ) );
            $this->redirectResiPrintFailure( __( 'Unable to print resi because the shipment record was not found.', 'kiriminaja-official' ) );
        }

        $awbs = [];
        $printedOrderIds = [];
        $filename = '';
        foreach ($transactions as $transaction) {
            if (isset($transaction->awb) && !empty($transaction->awb)) {
                $awbs[] = $transaction->awb;
                $printedOrderIds[] = $transaction->order_id;
            }
            if (isset($transaction->pickup_number) && !empty($transaction->pickup_number)) {
                $filename = $transaction->pickup_number;
            }
        }

        if (count($awbs) < 1) {
            $this->logResiPrintFailure( 'empty_awb', array(
                'order_ids'          => $orderIds,
                'transaction_count'  => count( $transactions ),
                'transaction_status' => array_values( array_map(
                    static function ( $transaction ) {
                        return (string) ( $transaction->status ?? '' );
                    },
                    $transactions
                ) ),
            ) );
            $this->redirectResiPrintFailure( __( 'Unable to print resi because the shipment does not have an AWB yet.', 'kiriminaja-official' ) );
        }

        if (count($awbs) == 1) {
            $filename = $awbs[0] ?? 'resi';
        }
        $getAwbData = (new KiriminajaApiRepository())->getPrintAwb($awbs);
        $printAwbUrl = $this->resolvePrintAwbUrl( $getAwbData );
        if ( '' !== $printAwbUrl ) {
            $this->markTransactionsPrinted( $printedOrderIds );
            wp_redirect( esc_url_raw( $printAwbUrl ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Trusted label URL returned by KiriminAja API.
            exit;
        }

        $apiMessage = is_scalar( $getAwbData['data'] ?? null ) ? trim( (string) $getAwbData['data'] ) : '';
        $this->logResiPrintFailure( 'print_awb_url_missing', array(
            'order_ids'     => $orderIds,
            'awbs'          => $awbs,
            'api_status'    => $getAwbData['status'] ?? null,
            'api_response'  => is_scalar( $getAwbData['data'] ?? null ) ? substr( (string) $getAwbData['data'], 0, 300 ) : '',
            'api_attempts'  => $getAwbData['attempts'] ?? array(),
            'response_type' => is_object( $getAwbData['data'] ?? null ) ? get_class( $getAwbData['data'] ) : gettype( $getAwbData['data'] ?? null ),
        ) );
        /* translators: %s: Error message returned by KiriminAja when the shipping label cannot be printed. */
        $this->redirectResiPrintFailure( $apiMessage !== '' ? sprintf( __( 'Unable to print resi: %s', 'kiriminaja-official' ), $apiMessage ) : __( 'Unable to print resi because the AWB print URL was not returned by KiriminAja.', 'kiriminaja-official' ) );
    }

    function getShippingReschedulePickup()
    {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
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
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
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
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
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
            if ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) ) {
                $this->logResiPrintFailure( 'unauthorized_direct', array(
                    'is_logged_in' => is_user_logged_in(),
                    'user_id'      => get_current_user_id(),
                ) );
                wp_safe_redirect( home_url( '/404' ) );
                exit;
            }
            // Verify nonce to prevent CSRF on this privileged label download endpoint.
            $kiriof_nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
            if ( empty( $_REQUEST['_kiriof_resi_print_nonce_checked'] ) && ! wp_verify_nonce( $kiriof_nonce, 'kiriof_resi_print' ) ) {
                $this->logResiPrintFailure( 'invalid_nonce_direct', array(
                    'has_nonce' => '' !== $kiriof_nonce,
                ) );
                $this->redirectResiPrintFailure( __( 'Unable to print resi because the security token is invalid.', 'kiriminaja-official' ) );
            }
            // Keep raw oids as string/array and normalize in one place so
            // admin_post pre-processing (array) and direct requests (string)
            // both resolve correctly.
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via sanitizeResiPrintOrderIds
            $rawOrderIds = isset( $_REQUEST['oids'] ) ? wp_unslash( $_REQUEST['oids'] ) : array();
            $orderIds = $this->sanitizeResiPrintOrderIds( $rawOrderIds );
            $this->outputResiPrint( $orderIds );
        } catch (\Throwable $e) {
            $this->logResiPrintFailure( 'exception', array(
                'message' => $e->getMessage(),
            ) );
            /* translators: %s: Exception message explaining why the shipping label cannot be printed. */
            $this->redirectResiPrintFailure( sprintf( __( 'Unable to print resi: %s', 'kiriminaja-official' ), $e->getMessage() ) );
        }
    }
}
