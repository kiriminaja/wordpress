<?php

namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

use KiriminAjaOfficial\Services\TransactionProcessServices\SendRequestPickupTransactionService;
use KiriminAjaOfficial\Services\TransactionProcessServices\CancelTransactionService;
use KiriminAjaOfficial\Services\TransactionProcessServices\GetCreditBalanceService;
use KiriminAjaOfficial\Services\TransactionProcessServices\ValidatePinService;

class TransactionProcessController
{
    public function register()
    {
        /** getPaymentForm */
        add_action('wp_ajax_kiriof_request_pickup_schedule', array($this, 'getRequestPickupSchedule'));
        add_action('wp_ajax_kiriof_request_pickup_transaction', array($this, 'sendRequestPickupTransaction'));
        add_action('wp_ajax_kiriof_cancel_transaction', array($this, 'cancelTransaction'));
        add_action('wp_ajax_kiriof_change_origin_check', array($this, 'changeOriginCheck'));
        add_action('wp_ajax_kiriof_change_origin', array($this, 'changeOrigin'));
        add_action('wp_ajax_kiriof_get_credit_balance', array($this, 'getCreditBalance'));
        add_action('wp_ajax_kiriof_validate_pin', array($this, 'validatePin'));
        add_action('wp_ajax_kiriof_get_payment_method_config', array($this, 'getPaymentMethodConfig'));
        add_filter('woocommerce_admin_order_preview_get_order_details', array($this, 'extendWooOrderPreviewDetails'), 10, 2);
        add_action('woocommerce_admin_order_preview_end', array($this, 'renderWooOrderPreviewKiriminajaDetails'));
        add_action('admin_footer', array($this, 'renderWooOrderPreviewKiriminajaRelocatorScript'));
        add_action('admin_footer', array($this, 'renderWooOrderPreviewTemplateForKiriofPage'));
        add_action('admin_footer', array($this, 'renderWooActionModalTemplatesForKiriofPage'));

        /** Auto-cancel KA transaction when WC order is cancelled */
        add_action('woocommerce_order_status_cancelled', array($this, 'handleWcOrderCancelled'), 10, 1);
    }

    public function getRequestPickupSchedule()
    {
        if (! current_user_can( 'manage_woocommerce' )) {
            wp_send_json_error(array('status' => 403, 'message' => __('Insufficient permissions', 'kiriminaja-official')));
            wp_die();
        }
        // Check for nonce security - fail early
        if (! isset($_POST['data']['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['data']['nonce'])), KIRIOF_NONCE)) {
            wp_send_json_error(array('status' => 403, 'message' => __('Security check failed', 'kiriminaja-official')));
            wp_die();
        }
        $order_ids = (isset($_POST['data']['order_ids']) && !empty($_POST['data']['order_ids'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['data']['order_ids']))
            : []
        );
        $service = (new \KiriminAjaOfficial\Services\TransactionProcessServices\GetRequestPickupScheduleService())
            ->orderIds($order_ids)
            ->call();
        wp_send_json_success($service);
    }

    public function sendRequestPickupTransaction()
    {
        try {
            if (! current_user_can( 'manage_woocommerce' )) {
                wp_send_json_error(array('status' => 403, 'message' => __('Insufficient permissions', 'kiriminaja-official')));
                wp_die();
            }

            // Check for nonce security - fail early
            if (! isset($_POST['data']['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['data']['nonce'])), KIRIOF_NONCE)) {
                wp_send_json_error(array('status' => 403, 'message' => __('Security check failed', 'kiriminaja-official')));
                wp_die();
            }
            $order_ids = (isset($_POST['data']['order_ids']) && !empty($_POST['data']['order_ids'])
                ? array_map('sanitize_text_field', wp_unslash($_POST['data']['order_ids']))
                : []
            );
            $schedule = (isset($_POST['data']['schedule']) && !empty($_POST['data']['schedule'])
                ? sanitize_text_field(wp_unslash($_POST['data']['schedule']))
                : ''
            );
            $payment_method = (isset($_POST['data']['payment_method']) && !empty($_POST['data']['payment_method'])
                ? sanitize_text_field(wp_unslash($_POST['data']['payment_method']))
                : ''
            );
            $pin = (isset($_POST['data']['pin']) && !empty($_POST['data']['pin'])
                ? sanitize_text_field(wp_unslash($_POST['data']['pin']))
                : ''
            );
            $location_id = (isset($_POST['data']['location_id']) && !empty($_POST['data']['location_id'])
                ? (int) $_POST['data']['location_id']
                : 0
            );

            if ($payment_method === 'credit' && ! KIRIOF_ENABLE_KA_CREDIT) {
                wp_send_json_success(
                    \KiriminAjaOfficial\Base\BaseService::error([], __('KA Credit is temporarily unavailable.', 'kiriminaja-official'))
                );
                return;
            }

            if ($payment_method === 'credit') {
                $pinValidation = (new ValidatePinService())->pin($pin)->call();
                if ($pinValidation->status !== 200 || empty($pinValidation->data['valid'])) {
                    wp_send_json_success($pinValidation);
                    return;
                }
            }

            $service = (new \KiriminAjaOfficial\Services\TransactionProcessServices\SendRequestPickupTransactionService())
                ->orderIds($order_ids)
                ->schedule($schedule)
                ->paymentMethod($payment_method)
                ->pin($pin)
                ->locationId($location_id)
                ->call();
            wp_send_json_success($service);
        } catch (\Throwable $th) {
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(
                'sendRequestPickupTransaction exception',
                [
                    'message' => $th->getMessage(),
                    'order_ids' => $order_ids ?? [],
                    'schedule' => $schedule ?? '',
                ]
            );
            wp_send_json_error([
                'status'    => 400,
                'message'   => $th->getMessage(),
            ]);
        }
    }
    public function cancelTransaction()
    {
        try {
            if (! current_user_can( 'manage_woocommerce' )) {
                wp_send_json_error(array('status' => 403, 'message' => __('Insufficient permissions', 'kiriminaja-official')));
                wp_die();
            }
            // Check for nonce security
            if (! isset($_POST['data']['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['data']['nonce'])), KIRIOF_NONCE)) {
                wp_send_json_error(array('status' => 403, 'message' => __('Security check failed', 'kiriminaja-official')));
                wp_die();
            }

            $order_id = isset($_POST['data']['order_id']) ? sanitize_text_field(wp_unslash($_POST['data']['order_id'])) : '';
            $reason   = isset($_POST['data']['reason']) ? sanitize_textarea_field(wp_unslash($_POST['data']['reason'])) : '';

            $service = (new CancelTransactionService())
                ->orderId($order_id)
                ->reason($reason)
                ->call();

            wp_send_json_success($service);
        } catch (\Throwable $th) {
            wp_send_json_success([
                'status'  => 400,
                'message' => $th->getMessage(),
            ]);
        }
    }

    public function handleWcOrderCancelled($order_id)
    {
        try {
            $transactionRepo = new \KiriminAjaOfficial\Repositories\TransactionRepository();
            $transaction     = $transactionRepo->getTransactionByWCOrderId($order_id);

            if (! $transaction) {
                return;
            }

            // Skip if already canceled or in a terminal status (e.g. webhook already handled it)
            $terminalStatuses = ['shipped', 'finished', 'returned', 'return', 'canceled'];
            if (in_array($transaction->status, $terminalStatuses, true)) {
                return;
            }

            // Skip if no AWB — nothing to cancel on Mitra side
            if (empty( $transaction->awb )) {
                return;
            }

            $reason = __('Pesanan dibatalkan dari WooCommerce', 'kiriminaja-official');

            (new CancelTransactionService())
                ->orderId($transaction->order_id)
                ->reason($reason)
                ->call();
        } catch (\Throwable $th) {
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('handleWcOrderCancelled error', [$th->getMessage()]);
        }
    }

    public function getCreditBalance()
    {
        if (! current_user_can( 'manage_woocommerce' )) {
            wp_send_json_error(array('status' => 403, 'message' => __('Insufficient permissions', 'kiriminaja-official')));
            wp_die();
        }
        if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), KIRIOF_NONCE)) {
            wp_send_json_error(array('status' => 403, 'message' => __('Security check failed', 'kiriminaja-official')));
            wp_die();
        }

        if (! KIRIOF_ENABLE_KA_CREDIT) {
            wp_send_json_success(\KiriminAjaOfficial\Base\BaseService::error([], __('KA Credit is temporarily unavailable.', 'kiriminaja-official')));
            return;
        }

        $service = (new GetCreditBalanceService())->call();
        wp_send_json_success($service);
    }

    public function validatePin()
    {
        if (! current_user_can( 'manage_woocommerce' )) {
            wp_send_json_error(array('status' => 403, 'message' => __('Insufficient permissions', 'kiriminaja-official')));
            wp_die();
        }
        if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), KIRIOF_NONCE)) {
            wp_send_json_error(array('status' => 403, 'message' => __('Security check failed', 'kiriminaja-official')));
            wp_die();
        }

        if (! KIRIOF_ENABLE_KA_CREDIT) {
            wp_send_json_success(\KiriminAjaOfficial\Base\BaseService::error([], __('KA Credit is temporarily unavailable.', 'kiriminaja-official')));
            return;
        }

        $pin = isset($_POST['pin']) ? sanitize_text_field(wp_unslash($_POST['pin'])) : '';
        $service = (new ValidatePinService())->pin($pin)->call();
        wp_send_json_success($service);
    }

    public function getPaymentMethodConfig()
    {
        if (! current_user_can( 'manage_woocommerce' )) {
            wp_send_json_error(array('status' => 403, 'message' => __('Insufficient permissions', 'kiriminaja-official')));
            wp_die();
        }
        if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), KIRIOF_NONCE)) {
            wp_send_json_error(array('status' => 403, 'message' => __('Security check failed', 'kiriminaja-official')));
            wp_die();
        }

        $settingService = new \KiriminAjaOfficial\Services\SettingService();
        $isTop = $settingService->isTopPaymentMethod();

        $hasPin = false;
        if (KIRIOF_ENABLE_KA_CREDIT) {
            try {
            $profile = (new \KiriminAjaOfficial\Services\KiriminajaApiService())->getProfile();
            if (! empty($profile->data)) {
                $hasPin = (bool) ($profile->data->metadata->has_pin ?? false);
                $profilePaymentMethod = strtoupper((string) ($profile->data->metadata->payment_method ?? ''));
                if ($profilePaymentMethod !== '') {
                    $isTop = $profilePaymentMethod === 'TOP';
                }
            }
            } catch (\Throwable $th) {
                (new \KiriminAjaOfficial\Base\BaseInit())->logThis('getPaymentMethodConfig profile error', [$th->getMessage()]);
            }
        }

        wp_send_json_success([
            'status'  => 200,
            'message' => 'success',
            'data'    => [
                'is_top'   => $isTop,
                'has_pin'  => $hasPin,
                'ka_credit_enabled' => KIRIOF_ENABLE_KA_CREDIT,
            ],
        ]);
    }

    public function extendWooOrderPreviewDetails($order_details, $order)
    {
        if (! $order instanceof \WC_Order || empty($order_details['item_html'])) {
            return $order_details;
        }

        $transaction = (new \KiriminAjaOfficial\Repositories\TransactionRepository())
            ->getTransactionByWCOrderNumber($order->get_id());

        if (! $transaction) {
            return $order_details;
        }

        $summary_rows_html = $this->getWooOrderPreviewSummaryRowsHtml($order, $transaction);

        if ('' === $summary_rows_html) {
            $order_details['kiriof_ka_order_id'] = $transaction->order_id ?? '';
            $order_details['kiriof_awb']         = $transaction->awb ?? '';
            if (! empty($transaction->is_deficit)) {
                $order_details['kiriof_status_label']   = __('COD Deficit', 'kiriminaja-official');
                $order_details['kiriof_status_classes'] = 'badge-danger';
            } else {
                $order_details['kiriof_status_label']   = kiriof_helper()->transactionStatusLabel(@$transaction->status);
                $order_details['kiriof_status_classes'] = kiriof_helper()->transactionStatusClass(@$transaction->status);
            }
            return $order_details;
        }

        $order_details['item_html'] = str_replace(
            '</tbody>',
            $summary_rows_html . '</tbody>',
            $order_details['item_html']
        );

        $order_details['kiriof_ka_order_id'] = $transaction->order_id ?? '';
        $order_details['kiriof_awb']         = $transaction->awb ?? '';

        if (! empty($transaction->is_deficit)) {
            $order_details['kiriof_status_label']   = __('COD Deficit', 'kiriminaja-official');
            $order_details['kiriof_status_classes'] = 'badge-danger';
        } else {
            $order_details['kiriof_status_label']   = kiriof_helper()->transactionStatusLabel(@$transaction->status);
            $order_details['kiriof_status_classes'] = kiriof_helper()->transactionStatusClass(@$transaction->status);
        }

        if (! empty($transaction->awb) && ! empty($transaction->order_id)) {
            $print_url = admin_url('admin-post.php?action=kiriof_resi_print&oids=' . urlencode($transaction->order_id) . '&_wpnonce=' . wp_create_nonce('kiriof_resi_print'));

            $order_details['actions_html'] .= ' <a class="button button-large" href="' . esc_url($print_url) . '" target="_blank">' . esc_html__('Print', 'kiriminaja-official') . '</a>';
        }

        return $order_details;
    }

    public function renderWooOrderPreviewKiriminajaDetails()
    {
?>
        <# if ( data.kiriof_ka_order_id || data.kiriof_awb ) { #>
            <div
                class="kiriof-order-preview-shipment-details kiriof-order-preview-status-source"
                data-kiriof-status-label="{{ data.kiriof_status_label }}"
                data-kiriof-status-class="{{ data.kiriof_status_classes }}">
                <# if ( data.kiriof_ka_order_id ) { #>
                    <strong><?php esc_html_e('KA Order ID', 'kiriminaja-official'); ?></strong>
                    {{ data.kiriof_ka_order_id }}
                    <# } #>

                        <# if ( data.kiriof_awb ) { #>
                            <strong><?php esc_html_e('AWB', 'kiriminaja-official'); ?></strong>
                            {{ data.kiriof_awb }}
                            <# } #>
            </div>
            <# } #>
            <?php
        }

        public function renderWooOrderPreviewKiriminajaRelocatorScript()
        {
            ?>
                <script>
                    jQuery(function($) {
                        function kiriofPreviewStatusPalette(statusClass) {
                            if ((statusClass || '').indexOf('primary') !== -1) {
                                return {
                                    background: '#2563eb',
                                    color: '#ffffff'
                                };
                            }
                            if ((statusClass || '').indexOf('info') !== -1) {
                                return {
                                    background: '#0891b2',
                                    color: '#ffffff'
                                };
                            }
                            if ((statusClass || '').indexOf('warning') !== -1) {
                                return {
                                    background: '#f59e0b',
                                    color: '#1f2937'
                                };
                            }
                            if ((statusClass || '').indexOf('success') !== -1) {
                                return {
                                    background: '#16a34a',
                                    color: '#ffffff'
                                };
                            }
                            if ((statusClass || '').indexOf('teal') !== -1) {
                                return {
                                    background: '#0f766e',
                                    color: '#ffffff'
                                };
                            }
                            if ((statusClass || '').indexOf('orange') !== -1) {
                                return {
                                    background: '#ea580c',
                                    color: '#ffffff'
                                };
                            }
                            if ((statusClass || '').indexOf('slate') !== -1) {
                                return {
                                    background: '#475569',
                                    color: '#ffffff'
                                };
                            }
                            if ((statusClass || '').indexOf('rose') !== -1 || (statusClass || '').indexOf('danger') !== -1) {
                                return {
                                    background: '#e11d48',
                                    color: '#ffffff'
                                };
                            }

                            return {
                                background: '#334155',
                                color: '#ffffff'
                            };
                        }

                        $(document.body).on('wc_backbone_modal_loaded', function(event, target) {
                            if (target !== 'wc-modal-view-order') {
                                return;
                            }

                            var $modal = $('.wc-backbone-modal.wc-order-preview');
                            var $shipmentDetails = $modal.find('.kiriof-order-preview-shipment-details');
                            var $header = $modal.find('.wc-backbone-modal-header');

                            if (!$shipmentDetails.length) {
                                $shipmentDetails = $();
                            }

                            var $shippingPanel = $modal.find('.wc-order-preview-addresses .wc-order-preview-address').eq(1);

                            if (!$shippingPanel.length) {
                                $shippingPanel = $modal.find('.wc-order-preview-addresses .wc-order-preview-address').eq(0);
                            }

                            if (!$shippingPanel.length) {
                                $shippingPanel = $();
                            }

                            if ($shipmentDetails.length && $shippingPanel.length) {
                                $shipmentDetails.appendTo($shippingPanel);
                            }

                            var $existingStatus = $header.find('.kiriof-order-preview-status');
                            if ($existingStatus.length) {
                                $existingStatus.remove();
                            }

                            var kiriofStatusLabel = $modal.find('.kiriof-order-preview-shipment-details').data('kiriof-status-label');
                            var kiriofStatusClass = $modal.find('.kiriof-order-preview-shipment-details').data('kiriof-status-class');

                            if (!kiriofStatusLabel || !kiriofStatusClass) {
                                kiriofStatusLabel = $modal.find('.kiriof-order-preview-status-source').data('kiriof-status-label');
                                kiriofStatusClass = $modal.find('.kiriof-order-preview-status-source').data('kiriof-status-class');
                            }

                            if (!kiriofStatusLabel || !kiriofStatusClass) {
                                return;
                            }

                            var $wcStatus = $header.find('.order-status').first();
                            var palette = kiriofPreviewStatusPalette(kiriofStatusClass);
                            var $kiriofStatus = $(
                                '<mark class="order-status kiriof-order-preview-status" ' +
                                'style="margin-left:6px;margin-right:10px;background:' + palette.background + ';color:' + palette.color + ';vertical-align:middle;box-shadow:inset 0 0 0 1px rgba(255,255,255,.18);">' +
                                '<span style="color:inherit;">' + kiriofStatusLabel + '</span>' +
                                '</mark>'
                           );

                            if ($wcStatus.length) {
                                $kiriofStatus.insertAfter($wcStatus);
                            } else {
                                $header.prepend($kiriofStatus);
                            }
                        });
                    });
                </script>
            <?php
        }

        public function renderWooOrderPreviewTemplateForKiriofPage()
        {
            if (! $this->isTransactionProcessPage()) {
                return;
            }

            if (class_exists('\Automattic\WooCommerce\Internal\Admin\Orders\ListTable')) {
                $list_table = new \Automattic\WooCommerce\Internal\Admin\Orders\ListTable();
                echo $list_table->get_order_preview_template(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                return;
            }

            if (class_exists('\WC_Admin_List_Table_Orders')) {
                $legacy_list_table = new \WC_Admin_List_Table_Orders();
                $legacy_list_table->order_preview_template();
            }
        }

        private function isOrderEditScreen()
        {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if (! $screen) {
                return false;
            }
            // Legacy: post.php/post-new.php with shop_order post type.
            if (in_array($screen->base, array('post', 'post-new'), true) && 'shop_order' === $screen->post_type) {
                return true;
            }
            // HPOS: woocommerce_page_wc-orders.
            if ('woocommerce_page_wc-orders' === $screen->id) {
                return true;
            }
            return false;
        }

        /**
         * AJAX: validate that a transaction can be shipped from a different origin.
         */
        public function changeOriginCheck()
        {
            if (! current_user_can( 'manage_woocommerce' )) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            if (! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), KIRIOF_NONCE )) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $order_id    = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
            $location_id = isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0;
            $courier_service      = isset( $_POST['courier_service'] ) ? sanitize_text_field( wp_unslash( $_POST['courier_service'] ) ) : '';
            $courier_service_name = isset( $_POST['courier_service_name'] ) ? sanitize_text_field( wp_unslash( $_POST['courier_service_name'] ) ) : '';
            $courier_price        = isset( $_POST['courier_price'] ) ? (float) $_POST['courier_price'] : 0;
            $courier_consent      = ! empty( $_POST['courier_consent'] );
            if ('' === $order_id || $location_id < 1) {
                wp_send_json_error( array( 'status' => 422, 'message' => __( 'Invalid request payload.', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $kiriof_location_service = new \KiriminAjaOfficial\Services\ShipmentLocationService();
            $kiriof_location         = $kiriof_location_service->repository()->getById( $location_id );
            if (empty( $kiriof_location ) || empty( $kiriof_location->is_active )) {
                wp_send_json_error( array( 'status' => 422, 'message' => __( 'Selected shipment location is not available.', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $kiriof_transaction_repo = new \KiriminAjaOfficial\Repositories\TransactionRepository();
            $kiriof_transaction      = $kiriof_transaction_repo->getTransactionByOrderId( $order_id );
            if (empty( $kiriof_transaction )) {
                wp_send_json_error( array( 'status' => 404, 'message' => __( 'Transaction not found.', 'kiriminaja-official' ) ) );
                wp_die();
            }
            if ('new' !== (string) $kiriof_transaction->status || ! empty( $kiriof_transaction->awb )) {
                wp_send_json_error( array( 'status' => 422, 'message' => __( 'Origin can only be changed before pickup is requested.', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $kiriof_previous_location = null;
            if ( ! empty( $kiriof_transaction->shipment_location_id ) ) {
                $kiriof_previous_location = $kiriof_location_service->repository()->getById( (int) $kiriof_transaction->shipment_location_id );
            }
            $kiriof_previous_origin_name = $kiriof_previous_location
                ? (string) $kiriof_previous_location->name
                : __( 'Default location', 'kiriminaja-official' );

            $kiriof_origin = $kiriof_location_service->originForLocation( $location_id );
            if (empty( $kiriof_origin['origin_sub_district_id'] )) {
                wp_send_json_error( array( 'status' => 422, 'message' => __( 'Selected shipment location has no serviceable area yet.', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $kiriof_pricing = (new \KiriminAjaOfficial\Services\CheckoutServices\OngkirPricingService( array(
                'destination_area_id'    => (int) $kiriof_transaction->destination_sub_district_id,
                'origin_sub_district_id' => (int) $kiriof_origin['origin_sub_district_id'],
                'package_overrides'      => array(
                    'weight'     => (int) $kiriof_transaction->weight,
                    'length'     => (float) $kiriof_transaction->length,
                    'width'      => (float) $kiriof_transaction->width,
                    'height'     => (float) $kiriof_transaction->height,
                    'item_value' => (float) ( $kiriof_transaction->transaction_value ?? 0 ),
                ),
                'is_cod'                 => ( (float) ( $kiriof_transaction->cod_fee ?? 0 ) > 0 ),
            ) ))->call();

            if ( 200 !== $kiriof_pricing->status() ) {
                wp_send_json_error( array(
                    'status'  => 422,
                    'message' => sprintf(
                        /* translators: %s: shipping check error message. */
                        __( 'Route is not serviceable from the selected origin: %s', 'kiriminaja-official' ),
                        $kiriof_pricing->message()
                    ),
                ) );
                wp_die();
            }

            $kiriof_pricing_data = $kiriof_pricing->data();
            $kiriof_options      = is_array( $kiriof_pricing_data['options'] ?? null ) ? $kiriof_pricing_data['options'] : array();
            $kiriof_previous_service = (string) ( $kiriof_transaction->service ?? '' );
            $kiriof_previous_name    = kiriof_helper()->formatServiceName( $kiriof_previous_service, (string) ( $kiriof_transaction->service_name ?? '' ) );
            $kiriof_previous_price   = max( 0, (float) ( $kiriof_transaction->shipping_cost ?? 0 ) - (float) ( $kiriof_transaction->discount_amount ?? 0 ) );
            $kiriof_previous_raw_shipping = (float) ( $kiriof_transaction->shipping_cost ?? 0 );
            $kiriof_previous_discount = max( 0, $kiriof_previous_raw_shipping - $kiriof_previous_price );
            $kiriof_order = ! empty( $kiriof_transaction->wp_wc_order_stat_order_id ) ? wc_get_order( (int) $kiriof_transaction->wp_wc_order_stat_order_id ) : false;
            $kiriof_previous_paid_shipping = $kiriof_order ? (float) $kiriof_order->get_shipping_total() : $kiriof_previous_price;
            $kiriof_previous_total = $kiriof_order ? (float) $kiriof_order->get_total() : 0;
            $kiriof_matched_option   = null;
            $kiriof_normalized       = array();
            $kiriof_normalize_code   = static function ( $code ) {
                return strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $code ) );
            };
            $kiriof_previous_code = $kiriof_normalize_code( $kiriof_previous_service );
            foreach ( $kiriof_options as $kiriof_option ) {
                $kiriof_normalized[] = array(
                    'courier' => (string) ( $kiriof_option['courier'] ?? '' ),
                    'service' => (string) ( $kiriof_option['service'] ?? '' ),
                    'service_code' => (string) ( $kiriof_option['service_code'] ?? '' ),
                    'service_name' => (string) ( $kiriof_option['service_name'] ?? '' ),
                    'price'   => wp_strip_all_tags( wc_price( (float) ( $kiriof_option['price'] ?? 0 ) ) ),
                    'raw_price' => (float) ( $kiriof_option['price'] ?? 0 ),
                    'etd'     => (string) ( $kiriof_option['etd'] ?? '' ),
                );
                if ( $kiriof_previous_code !== ''
                    && $kiriof_normalize_code( $kiriof_option['service_code'] ?? '' ) === $kiriof_previous_code ) {
                    $kiriof_matched_option = end( $kiriof_normalized );
                }
            }

            $kiriof_change_label = __( 'Courier is unchanged.', 'kiriminaja-official' );
            $kiriof_comparison   = null;
            if ( $kiriof_matched_option ) {
                $kiriof_delta = (float) $kiriof_matched_option['raw_price'] - $kiriof_previous_price;
                if ( $kiriof_delta > 0 ) {
                    $kiriof_change_label = sprintf( __( '%1$s price increases by %2$s.', 'kiriminaja-official' ), $kiriof_previous_name, wp_strip_all_tags( wc_price( $kiriof_delta ) ) );
                } elseif ( $kiriof_delta < 0 ) {
                    $kiriof_change_label = sprintf( __( '%1$s price decreases by %2$s.', 'kiriminaja-official' ), $kiriof_previous_name, wp_strip_all_tags( wc_price( abs( $kiriof_delta ) ) ) );
                }
                $kiriof_comparison = array(
                    'available'    => true,
                    'label'       => $kiriof_change_label,
                    'old_price'   => wc_price( $kiriof_previous_price ),
                    'new_price'   => $kiriof_matched_option['price'],
                    'raw_price'   => $kiriof_matched_option['raw_price'],
                    'service_code' => $kiriof_matched_option['service_code'],
                    'service_name' => $kiriof_matched_option['service_name'],
                    'previous_raw_shipping' => $kiriof_previous_raw_shipping,
                    'previous_discount' => $kiriof_previous_discount,
                    'previous_paid_shipping' => $kiriof_previous_paid_shipping,
                    'previous_total' => $kiriof_previous_total,
                    'previous_courier' => $kiriof_previous_name,
                    'new_courier' => kiriof_helper()->formatServiceName( (string) $kiriof_matched_option['service_code'], (string) $kiriof_matched_option['service_name'] ),
                       'new_raw_shipping' => (float) $kiriof_matched_option['raw_price'],
                    'new_discount' => min( $kiriof_previous_discount, $kiriof_matched_option['raw_price'] ),
                    'new_paid_shipping' => max( 0, $kiriof_matched_option['raw_price'] - min( $kiriof_previous_discount, $kiriof_matched_option['raw_price'] ) ),
                    'previous_subtotal' => $kiriof_order ? (float) $kiriof_order->get_subtotal() : 0,
                    'previous_total_shipping' => $kiriof_previous_raw_shipping + (float) ( $kiriof_transaction->insurance_cost ?? 0 ) + (float) ( $kiriof_transaction->cod_fee ?? 0 ),
                    'previous_discounted_shipping' => $kiriof_previous_paid_shipping,
                    'new_total_shipping' => (float) $kiriof_matched_option['raw_price'] + (float) ( $kiriof_transaction->insurance_cost ?? 0 ) + (float) ( $kiriof_transaction->cod_fee ?? 0 ),
                    'new_discounted_shipping' => max( 0, $kiriof_matched_option['raw_price'] - min( $kiriof_previous_discount, $kiriof_matched_option['raw_price'] ) ),
                 );
                $kiriof_comparison['total_delta'] = $kiriof_comparison['new_paid_shipping'] - $kiriof_previous_paid_shipping;
                $kiriof_comparison['new_total'] = $kiriof_previous_total + $kiriof_comparison['total_delta'];
            } else {
                $kiriof_comparison = array(
                    'available'     => false,
                    'label'        => sprintf( __( '%s is not available from the selected origin.', 'kiriminaja-official' ), $kiriof_previous_name ),
                    'old_price'    => wc_price( $kiriof_previous_price ),
                    'service_code' => $kiriof_previous_service,
                    'service_name' => (string) ( $kiriof_transaction->service_name ?? '' ),
                    'previous_raw_shipping' => $kiriof_previous_raw_shipping,
                    'previous_discount' => $kiriof_previous_discount,
                    'previous_paid_shipping' => $kiriof_previous_paid_shipping,
                    'previous_total' => $kiriof_previous_total,
                    'previous_courier' => $kiriof_previous_name,
                    'new_courier' => '',
                );
            }

            wp_send_json_success( array(
                'message' => __( 'Shipping check passed. Review the courier and price impact before confirming.', 'kiriminaja-official' ),
                'comparison' => $kiriof_comparison,
                'options' => array_slice( $kiriof_normalized, 0, 3 ),
                'replacement_options' => array_values( array_filter( $kiriof_normalized, static function ( $option ) use ( $kiriof_previous_code, $kiriof_normalize_code ) {
                    return $kiriof_normalize_code( $option['service_code'] ) !== $kiriof_previous_code;
                } ) ),
            ) );
            wp_die();
        }

        /**
         * AJAX: persist the chosen pickup origin on a transaction.
         */
        public function changeOrigin()
        {
            if (! current_user_can( 'manage_woocommerce' )) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            if (! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), KIRIOF_NONCE )) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $order_id    = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
            $location_id = isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0;
            if ('' === $order_id || $location_id < 1) {
                wp_send_json_error( array( 'status' => 422, 'message' => __( 'Invalid request payload.', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $kiriof_location_service = new \KiriminAjaOfficial\Services\ShipmentLocationService();
            $kiriof_location         = $kiriof_location_service->repository()->getById( $location_id );
            if (empty( $kiriof_location ) || empty( $kiriof_location->is_active )) {
                wp_send_json_error( array( 'status' => 422, 'message' => __( 'Selected shipment location is not available.', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $kiriof_transaction_repo = new \KiriminAjaOfficial\Repositories\TransactionRepository();
            $kiriof_transaction      = $kiriof_transaction_repo->getTransactionByOrderId( $order_id );
            if (empty( $kiriof_transaction )) {
                wp_send_json_error( array( 'status' => 404, 'message' => __( 'Transaction not found.', 'kiriminaja-official' ) ) );
                wp_die();
            }
            if ('new' !== (string) $kiriof_transaction->status || ! empty( $kiriof_transaction->awb )) {
                wp_send_json_error( array( 'status' => 422, 'message' => __( 'Origin can only be changed before pickup is requested.', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $courier_service      = isset( $_POST['courier_service'] ) ? sanitize_text_field( wp_unslash( $_POST['courier_service'] ) ) : '';
            $courier_service_name = isset( $_POST['courier_service_name'] ) ? sanitize_text_field( wp_unslash( $_POST['courier_service_name'] ) ) : '';
            $courier_price        = isset( $_POST['courier_price'] ) ? (float) $_POST['courier_price'] : 0;
            $courier_consent      = ! empty( $_POST['courier_consent'] );
            $kiriof_courier_update = array();
            if ( $courier_service || $courier_service_name ) {
                if ( ! $courier_consent ) {
                    wp_send_json_error( array( 'status' => 422, 'message' => __( 'Courier replacement requires your consent.', 'kiriminaja-official' ) ) );
                    wp_die();
                }
                $kiriof_courier_update = array(
                    'service'       => $courier_service,
                    'service_name'  => $courier_service_name,
                    'shipping_cost' => $courier_price,
                );
            }

            $kiriof_previous_location = ! empty( $kiriof_transaction->shipment_location_id )
                ? $kiriof_location_service->repository()->getById( (int) $kiriof_transaction->shipment_location_id )
                : null;
            $kiriof_previous_origin_name = $kiriof_previous_location
                ? (string) $kiriof_previous_location->name
                : __( 'Default location', 'kiriminaja-official' );

            $kiriof_snapshot = wp_json_encode( array(
                'id'          => (int) $kiriof_location->id,
                'name'        => (string) $kiriof_location->name,
                'sender_name' => (string) $kiriof_location->sender_name,
                'address'     => (string) $kiriof_location->address,
                'city'        => (string) ( $kiriof_location->city ?? '' ),
            ) );

            $kiriof_updated = $kiriof_transaction_repo->updateTransactionShipmentLocation( $order_id, $location_id, $kiriof_snapshot, $kiriof_courier_update );
            if (! $kiriof_updated) {
                wp_send_json_error( array( 'status' => 500, 'message' => __( 'Failed to update the shipment origin.', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $kiriof_wc_order = ! empty( $kiriof_transaction->wp_wc_order_stat_order_id )
                ? wc_get_order( (int) $kiriof_transaction->wp_wc_order_stat_order_id )
                : false;
            if ( $kiriof_wc_order ) {
                $kiriof_current_user = wp_get_current_user();
                $kiriof_actor         = $kiriof_current_user instanceof \WP_User && $kiriof_current_user->exists()
                    ? $kiriof_current_user->display_name
                    : __( 'store administrator', 'kiriminaja-official' );
                $kiriof_wc_order->add_order_note(
                    sprintf(
                        /* translators: 1: previous origin, 2: new origin, 3: administrator name. */
                        __( 'Shipment origin changed from %1$s to %2$s by %3$s.', 'kiriminaja-official' ),
                        $kiriof_previous_origin_name,
                        (string) $kiriof_location->name,
                        $kiriof_actor
                    )
                );
                if ( $kiriof_courier_update ) {
                    $kiriof_wc_order->add_order_note(
                        sprintf(
                            /* translators: 1: courier service name. */
                            __( 'Courier changed with seller consent to %s.', 'kiriminaja-official' ),
                            $courier_service_name
                        )
                    );
                }
            }

            wp_send_json_success( array( 'message' => __( 'Shipment origin updated.', 'kiriminaja-official' ) ) );
            wp_die();
        }

        public function renderWooActionModalTemplatesForKiriofPage()
        {
            if (! $this->isTransactionProcessPage() && ! $this->isOrderEditScreen()) {
                return;
            }

            $kiriof_location_service   = new \KiriminAjaOfficial\Services\ShipmentLocationService();
            $kiriof_shipment_locations = $kiriof_location_service->repository()->getAll( true );

            $kiriof_pin_cache_ttl = (int) apply_filters(
                'kiriof_pin_cache_ttl',
                15 * MINUTE_IN_SECONDS,
                wp_get_current_user()
            );
            $kiriof_pin_cache_ttl = max( MINUTE_IN_SECONDS, $kiriof_pin_cache_ttl );
            $kiriof_pin_cache_label = sprintf(
                /* translators: %d: cached PIN duration in minutes. */
                __( 'Remember PIN on this browser for %d minutes', 'kiriminaja-official' ),
                (int) ceil( $kiriof_pin_cache_ttl / MINUTE_IN_SECONDS )
            );
            ?>
                <script type="text/template" id="tmpl-kiriof-modal-cod-adjustment">
                    <div class="wc-backbone-modal kiriof-backbone-modal kiriof-cod-adjustment-modal">
                <div class="wc-backbone-modal-content" style="max-width:500px;width:calc(100vw - 48px);margin:5vh auto 0;">
                    <section class="wc-backbone-modal-main" role="main">
                        <header class="wc-backbone-modal-header">
                            <h1><?php esc_html_e('Adjust COD Deficit Order', 'kiriminaja-official'); ?></h1>
                            <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                                <span class="screen-reader-text"><?php esc_html_e('Close modal panel', 'kiriminaja-official'); ?></span>
                            </button>
                        </header>
                        <article class="kiriof-backbone-modal-body">
                            <form>
                                <input type="hidden" name="order_package_id" value="{{ data.order_id }}">
                                <input type="hidden" name="nonce" value="{{ data.nonce }}">
                                <input type="hidden" class="kiriof-adj-raw-min" value="{{ data.cod_minimum }}">
                                <input type="hidden" class="kiriof-adj-raw-max" value="{{ data.cod_maximum }}">
                                <input type="hidden" class="kiriof-adj-raw-shipping" value="{{ data.shipping_cost }}">
                                <input type="hidden" class="kiriof-adj-raw-insurance" value="{{ data.insurance_cost }}">
                                <input type="hidden" class="kiriof-adj-raw-cod-fee" value="{{ data.cod_fee }}">

                                <div class="kiriof-backbone-field">
                                    <label for="kiriof-adj-cod-input" class="kiriof-backbone-label">
                                        <?php esc_html_e('COD Value', 'kiriminaja-official'); ?> <span class="required">*</span>
                                    </label>
                                    <input type="number" id="kiriof-adj-cod-input" class="kiriof-adj-cod-input" name="cod_value" value="{{ data.current_cod }}" min="{{ data.cod_minimum }}" max="{{ data.cod_maximum }}" step="1" style="width:100%;">
                                    <p class="kiriof-adj-hint" style="font-size:11px;color:#d63638;margin:4px 0 0;min-height:16px;"></p>
                                </div>

                                <table class="kiriof-backbone-summary">
                                    <tr>
                                        <td><?php esc_html_e('Sub Total', 'kiriminaja-official'); ?></td>
                                        <td style="text-align:right;">{{ data.sub_total_fmt }}</td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('Total Shipping', 'kiriminaja-official'); ?></td>
                                        <td style="text-align:right;" class="kiriof-adj-total-shipping">{{ data.total_shipping_fmt }}</td>
                                    </tr>
                                    <tr style="color:#50575e;">
                                        <td>&nbsp;&nbsp;&nbsp;<?php esc_html_e('Shipping', 'kiriminaja-official'); ?></td>
                                        <td style="text-align:right;">{{ data.shipping_fmt }}</td>
                                    </tr>
                                    <# if ( data.insurance_cost > 0 ) { #>
                                    <tr style="color:#50575e;">
                                        <td>&nbsp;&nbsp;&nbsp;<?php esc_html_e('Insurance', 'kiriminaja-official'); ?></td>
                                        <td style="text-align:right;">{{ data.insurance_fmt }}</td>
                                    </tr>
                                    <# } #>
                                    <# if ( data.cod_fee > 0 ) { #>
                                    <tr style="color:#50575e;">
                                        <td>&nbsp;&nbsp;&nbsp;<?php esc_html_e('COD Fee', 'kiriminaja-official'); ?></td>
                                        <td style="text-align:right;">{{ data.cod_fee_fmt }}</td>
                                    </tr>
                                    <# } #>
                                    <# if ( data.item_discount > 0 ) { #>
                                    <tr style="color:#d63638;">
                                        <td>
                                            <# if ( data.item_coupon ) { #>
                                                {{ data.item_coupon }} <span style="color:#8c8f94;font-size:11px;"><?php esc_html_e('Item', 'kiriminaja-official'); ?></span>
                                            <# } else { #>
                                                <?php esc_html_e('Discount', 'kiriminaja-official'); ?>
                                            <# } #>
                                        </td>
                                        <td style="text-align:right;">{{ data.item_discount_fmt }}</td>
                                    </tr>
                                    <# } #>
                                    <# if ( data.shipping_discount > 0 ) { #>
                                    <tr style="color:#d63638;">
                                        <td>
                                            <# if ( data.shipping_coupon ) { #>
                                                {{ data.shipping_coupon }} <span style="color:#8c8f94;font-size:11px;"><?php esc_html_e('Shipping', 'kiriminaja-official'); ?></span>
                                            <# } else { #>
                                                <?php esc_html_e('Shipping Discount (from KiriminAja)', 'kiriminaja-official'); ?>
                                            <# } #>
                                        </td>
                                        <td style="text-align:right;">{{ data.shipping_discount_fmt }}</td>
                                    </tr>
                                    <# } #>
                                    <tr>
                                        <td><strong><?php esc_html_e('COD Paid By Buyer', 'kiriminaja-official'); ?></strong></td>
                                        <td style="text-align:right;"><strong class="kiriof-adj-cod-paid">{{ data.current_cod_fmt }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e('Estimated COD Payout', 'kiriminaja-official'); ?></strong></td>
                                        <td style="text-align:right;"><strong class="kiriof-adj-payout" style="{{ data.payout_color }}">{{ data.payout_fmt }}</strong></td>
                                    </tr>
                                </table>

                                <p class="kiriof-backbone-inline-error err_msg" style="display:none;"></p>
                                <div class="kiriof-modal-state kiriof-modal-state-loading" style="display:none;">
                                    <div class="kiriof-backbone-modal-loader">
                                        <span class="spinner is-active"></span>
                                    </div>
                                </div>
                            </form>
                        </article>
                        <footer>
                            <div class="inner">
                                <button class="button button-large modal-close"><?php esc_html_e('Close', 'kiriminaja-official'); ?></button>
                                <button class="button button-primary button-large" id="btn-next"><?php esc_html_e('Confirm & Process', 'kiriminaja-official'); ?></button>
                            </div>
                        </footer>
                    </section>
                </div>
            </div>
            <div class="wc-backbone-modal-backdrop modal-close"></div>
        </script>

                <script type="text/template" id="tmpl-kiriof-modal-cancel-deficit">
                    <div class="wc-backbone-modal kiriof-backbone-modal kiriof-cancel-deficit-modal">
                <div class="wc-backbone-modal-content" style="max-width:420px;width:calc(100vw - 48px);margin:5vh auto 0;">
                    <section class="wc-backbone-modal-main" role="main">
                        <header class="wc-backbone-modal-header">
                            <h1><?php esc_html_e('Cancel Deficit Order', 'kiriminaja-official'); ?></h1>
                            <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                                <span class="screen-reader-text"><?php esc_html_e('Close modal panel', 'kiriminaja-official'); ?></span>
                            </button>
                        </header>
                        <article class="kiriof-backbone-modal-body">
                            <form>
                                <input type="hidden" name="order_package_id" value="{{ data.order_id }}">
                                <input type="hidden" name="nonce" value="{{ data.nonce }}">
                                <p><?php esc_html_e('Are you sure you want to cancel this deficit COD order? This cannot be undone.', 'kiriminaja-official'); ?></p>
                                <p class="kiriof-backbone-inline-error err_msg" style="display:none;"></p>
                                <div class="kiriof-modal-state kiriof-modal-state-loading" style="display:none;">
                                    <div class="kiriof-backbone-modal-loader">
                                        <span class="spinner is-active"></span>
                                    </div>
                                </div>
                            </form>
                        </article>
                        <footer>
                            <div class="inner">
                                <button class="button button-large modal-close"><?php esc_html_e('Close', 'kiriminaja-official'); ?></button>
                                <button class="button button-primary button-large kiriof-button-danger" id="btn-next"><?php esc_html_e('Cancel Deficit Order', 'kiriminaja-official'); ?></button>
                            </div>
                        </footer>
                    </section>
                </div>
            </div>
            <div class="wc-backbone-modal-backdrop modal-close"></div>
        </script>

                <?php if ($this->isTransactionProcessPage()) : ?>
                    <script type="text/template" id="tmpl-kiriof-modal-request-pickup">
                        <div class="wc-backbone-modal kiriof-backbone-modal kiriof-request-pickup-modal">
                <div class="wc-backbone-modal-content" style="max-width:640px;width:calc(100vw - 48px);margin:5vh auto 0;">
                    <section class="wc-backbone-modal-main" role="main">
                        <header class="wc-backbone-modal-header">
                            <h1><?php esc_html_e('Schedule for Pickup', 'kiriminaja-official'); ?></h1>
                            <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                                <span class="screen-reader-text"><?php esc_html_e('Close modal panel', 'kiriminaja-official'); ?></span>
                            </button>
                        </header>
                        <article class="kiriof-backbone-modal-body">
                            <form>
                                <div class="kiriof-modal-state kiriof-modal-state-loading">
                                    <div class="kiriof-backbone-modal-loader">
                                        <span class="spinner is-active"></span>
                                    </div>
                                </div>

                                <div class="kiriof-modal-state kiriof-modal-state-error" style="display:none;">
                                    <p class="kiriof-backbone-modal-error-text"><?php esc_html_e('An error occurred.', 'kiriminaja-official'); ?></p>
                                </div>

                                <div class="kiriof-modal-state kiriof-modal-state-content" style="display:none;">
                                    <div class="kiriof-backbone-summary">
                                        <div class="kiriof-backbone-summary-row">
                                            <span><?php esc_html_e('COD Package Charges', 'kiriminaja-official'); ?></span>
                                            <strong class="kiriof-summary-cod">Rp0</strong>
                                        </div>
                                        <div class="kiriof-backbone-summary-row">
                                            <span><?php esc_html_e('Non-COD Package Charges', 'kiriminaja-official'); ?></span>
                                            <strong class="kiriof-summary-non-cod">Rp0</strong>
                                        </div>
                                        <div class="kiriof-backbone-summary-row">
                                            <span><?php esc_html_e('Total Charges', 'kiriminaja-official'); ?></span>
                                            <strong class="kiriof-summary-total">Rp0</strong>
                                        </div>
                                    </div>

                                    <div class="kiriof-pm-warning kiriof-pm-state-banner" style="display:none;"></div>

                                    <div class="kiriof-backbone-section kiriof-payment-method-section" style="display:none;">
                                        <h2 class="kiriof-backbone-section-title"><?php esc_html_e('Payment Method', 'kiriminaja-official'); ?> <span class="required">*</span></h2>
                                        <div class="kiriof-payment-methods">
                                            <div class="kiriof-payment-method-option" data-method="credit">
                                                <div class="kiriof-payment-method-radio">
                                                    <input type="radio" id="kiriof-pm-credit" name="payment_method" value="credit">
                                                    <label for="kiriof-pm-credit">
                                                        <strong><?php esc_html_e('KA Credit', 'kiriminaja-official'); ?></strong>
                                                        <span class="kiriof-pm-balance"><?php esc_html_e('Loading balance...', 'kiriminaja-official'); ?></span>
                                                    </label>
                                                </div>
                                                <div class="kiriof-pm-warning kiriof-pm-credit-warning" style="display:none;"></div>
                                            </div>
                                            <div class="kiriof-payment-method-option" data-method="qris">
                                                <div class="kiriof-payment-method-radio">
                                                    <input type="radio" id="kiriof-pm-qris" name="payment_method" value="qris">
                                                    <label for="kiriof-pm-qris">
                                                        <strong><?php esc_html_e('QRIS', 'kiriminaja-official'); ?></strong>
                                                        <span class="kiriof-pm-max"><?php esc_html_e('Max Rp10.000.000', 'kiriminaja-official'); ?></span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="kiriof-backbone-section">
                                        <h2 class="kiriof-backbone-section-title"><?php esc_html_e('Available Schedules', 'kiriminaja-official'); ?></h2>
                                        <select class="kiriof-schedule-select" name="schedule_opt" style="width:100%;">
                                            <option value=""><?php esc_html_e('-- Select schedule --', 'kiriminaja-official'); ?></option>
                                        </select>
                                    </div>

                                    <div class="kiriof-backbone-section">
                                        <h2 class="kiriof-backbone-section-title"><?php esc_html_e('Ship From', 'kiriminaja-official'); ?></h2>
                                        <select class="kiriof-shipment-location-select" name="location_id" style="width:100%;">
                                            <?php
                                            $kiriof_ship_locations = (new \KiriminAjaOfficial\Services\ShipmentLocationService())->repository()->getAll();
                                            $kiriof_ship_default_id = 0;
                                            foreach ($kiriof_ship_locations as $kiriof_ship_loc) {
                                                if (!empty($kiriof_ship_loc->is_default)) {
                                                    $kiriof_ship_default_id = (int) $kiriof_ship_loc->id;
                                                }
                                            }
                                            foreach ($kiriof_ship_locations as $kiriof_ship_loc) {
                                                if (empty($kiriof_ship_loc->is_active)) {
                                                    continue;
                                                }
                                                printf(
                                                    '<option value="%1$d"%2$s>%3$s</option>',
                                                    (int) $kiriof_ship_loc->id,
                                                    selected((int) $kiriof_ship_loc->id, $kiriof_ship_default_id, false),
                                                    esc_html($kiriof_ship_loc->name)
                                                );
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <p class="kiriof-backbone-inline-error err_msg" style="display:none;"></p>
                                </div>

                                <div class="kiriof-modal-state kiriof-modal-state-pin" style="display:none;">
                                    <div class="kiriof-backbone-section kiriof-pin-section">
                                        <h2 class="kiriof-backbone-section-title"><?php esc_html_e('Enter PIN', 'kiriminaja-official'); ?></h2>
                                        <p style="font-size:12px;color:#50575e;margin:0 0 8px;"><?php esc_html_e('Enter the 6-digit PIN that was set on your profile page.', 'kiriminaja-official'); ?></p>
                                        <pin-input id="kiriof-pin-widget" class="kiriof-pin-widget" length="6" pattern="[0-9]" autocomplete="one-time-code" inputmode="numeric" mask aria-label="<?php esc_attr_e('Enter 6-digit PIN', 'kiriminaja-official'); ?>"></pin-input>
                                        <input type="password" id="kiriof-pin-fallback" class="kiriof-pin-fallback" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="------" autocomplete="one-time-code" style="display:none;">
                                        <input type="hidden" id="kiriof-pin-input" name="pin" value="">
                                        <label for="kiriof-pin-remember" class="kiriof-pin-remember">
                                            <input type="checkbox" id="kiriof-pin-remember" name="remember_pin" value="1">
                                            <span><?php echo esc_html($kiriof_pin_cache_label); ?></span>
                                        </label>
                                        <p class="kiriof-pin-cache-notice" style="display:none;font-size:12px;color:#2271b1;margin:8px 0 0;"></p>
                                        <p class="kiriof-pin-error err_msg" style="display:none;"></p>
                                    </div>
                                </div>
                            </form>
                        </article>
                        <footer>
                            <div class="inner">
                                <button class="button button-large modal-close"><?php esc_html_e('Close', 'kiriminaja-official'); ?></button>
                                <button class="button button-primary button-large" id="btn-next" disabled><?php esc_html_e('Pick Schedule', 'kiriminaja-official'); ?></button>
                            </div>
                        </footer>
                    </section>
                </div>
            </div>
            <div class="wc-backbone-modal-backdrop modal-close"></div>
        </script>

                    <script type="text/template" id="tmpl-kiriof-modal-cancel-transaction">
                        <div class="wc-backbone-modal kiriof-backbone-modal kiriof-cancel-transaction-modal">
                <div class="wc-backbone-modal-content" style="max-width:420px;width:calc(100vw - 48px);margin:5vh auto 0;">
                    <section class="wc-backbone-modal-main" role="main">
                        <header class="wc-backbone-modal-header">
                            <h1><?php esc_html_e('Cancel Shipment', 'kiriminaja-official'); ?></h1>
                            <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                                <span class="screen-reader-text"><?php esc_html_e('Close modal panel', 'kiriminaja-official'); ?></span>
                            </button>
                        </header>
                        <article class="kiriof-backbone-modal-body">
                            <form>
                                <input type="hidden" name="order_id" value="{{ data.order_id }}">
                                <input type="hidden" name="current_location_id" value="{{ data.current_location_id }}">
                                <input type="hidden" name="current_location_id" value="{{ data.current_location_id }}">
                                <div class="kiriof-backbone-field">
                                    <label for="kiriof-cancel-reason" class="kiriof-backbone-label">
                                        <?php esc_html_e('Reason for Cancellation', 'kiriminaja-official'); ?> <span class="required">*</span>
                                    </label>
                                    <textarea id="kiriof-cancel-reason" class="kiriof-cancel-reason" name="reason" rows="4" maxlength="200" placeholder="<?php echo esc_attr__('Enter reason (min 5, max 200 characters)', 'kiriminaja-official'); ?>"></textarea>
                                    <div class="kiriof-backbone-counter"><span class="kiriof-cancel-reason-count">0</span>/200</div>
                                </div>

                                <p class="kiriof-backbone-inline-error err_msg" style="display:none;"></p>

                                <div class="kiriof-modal-state kiriof-modal-state-loading" style="display:none;">
                                    <div class="kiriof-backbone-modal-loader">
                                        <span class="spinner is-active"></span>
                                    </div>
                                </div>
                            </form>
                        </article>
                        <footer>
                            <div class="inner">
                                <button class="button button-large modal-close"><?php esc_html_e('Close', 'kiriminaja-official'); ?></button>
                                <button class="button button-primary button-large kiriof-button-danger" id="btn-next"><?php esc_html_e('Cancel Shipment', 'kiriminaja-official'); ?></button>
                            </div>
                        </footer>
                    </section>
                </div>
            </div>
            <div class="wc-backbone-modal-backdrop modal-close"></div>
        </script>
        <script type="text/template" id="tmpl-kiriof-modal-change-origin">
            <div class="wc-backbone-modal kiriof-backbone-modal kiriof-change-origin-modal">
                <div class="wc-backbone-modal-content kiriof-change-origin-modal-content">
                    <section class="wc-backbone-modal-main" role="main">
                        <header class="wc-backbone-modal-header">
                            <h1><?php esc_html_e( 'Change Shipment Origin', 'kiriminaja-official' ); ?></h1>
                            <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                                <span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'kiriminaja-official' ); ?></span>
                            </button>
                        </header>
                        <article class="kiriof-backbone-modal-body">
                            <form>
                                <input type="hidden" name="order_id" value="{{ data.order_id }}">
                                <div class="kiriof-backbone-field">
									<label class="kiriof-backbone-label"><?php esc_html_e( 'Current shipment origin', 'kiriminaja-official' ); ?></label>
                                    <div class="kiriof-change-origin-current kiriof-origin-card">
                                        <strong>{{ data.current_origin }}</strong>
                                        <span>{{ data.current_origin_address }}</span>
                                    </div>
                                </div>
                                <div class="kiriof-backbone-field">
                                    <label for="kiriof-change-origin-to" class="kiriof-backbone-label">
										<?php esc_html_e( 'New shipment origin', 'kiriminaja-official' ); ?> <span class="required">*</span>
                                    </label>
                                    <select id="kiriof-change-origin-to" name="location_id" class="wc-enhanced-select" data-placeholder="<?php esc_attr_e( 'Select shipment location', 'kiriminaja-official' ); ?>" data-current-origin="{{ data.current_origin }}" data-current-location-id="{{ data.current_location_id }}">
                                        <option value=""></option>
                                        <?php foreach ( $kiriof_shipment_locations as $kiriof_location_option ) : ?>
                                            <?php
                                            $kiriof_location_address = $kiriof_location_service->formatAddress( $kiriof_location_option );
                                            $kiriof_location_label   = (string) $kiriof_location_option->name;
                                            if ( '' !== $kiriof_location_address ) {
                                                $kiriof_location_label .= ' — ' . $kiriof_location_address;
                                            }
                                            ?>
                                            <option value="<?php echo esc_attr( $kiriof_location_option->id ); ?>"><?php echo esc_html( $kiriof_location_label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description kiriof-change-origin-manage">
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=kiriminaja_warehouses' ) ); ?>"><?php esc_html_e( 'Manage shipment locations', 'kiriminaja-official' ); ?></a>
                                    </p>
                                    <div class="kiriof-change-origin-empty" style="display:none;">
                                        <p><?php esc_html_e( 'No alternative shipment locations are available.', 'kiriminaja-official' ); ?></p>
                                        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=kiriminaja_warehouses' ) ); ?>"><?php esc_html_e( 'Add shipment location', 'kiriminaja-official' ); ?></a>
                                    </div>
                                </div>
                                <div class="kiriof-change-origin-loading" aria-live="polite" style="display:none;">
                                    <span class="spinner" style="float:none;"></span>
                                    <span><?php esc_html_e( 'Checking shipping route...', 'kiriminaja-official' ); ?></span>
                                </div>
                                <div class="kiriof-change-origin-result notice inline" style="display:none;"></div>
                                <div class="kiriof-change-origin-replacement" style="display:none;"></div>
                                <div class="kiriof-change-origin-breakdown kiriof-change-origin-card" style="display:none;"></div>
                            </form>
                        </article>
                        <footer>
                            <div class="inner">
                                <button class="button button-large modal-close"><?php esc_html_e( 'Close', 'kiriminaja-official' ); ?></button>
                                <button class="button button-primary button-large" id="kiriof-change-origin-confirm" disabled><?php esc_html_e( 'Confirm', 'kiriminaja-official' ); ?></button>
                            </div>
                        </footer>
                    </section>
                </div>
            </div>
            <div class="wc-backbone-modal-backdrop modal-close"></div>
        </script>
                <?php endif; ?>
        <?php
        }

        private function getWooOrderPreviewSummaryRowsHtml(\WC_Order $order, $transaction)
        {
            $price_args  = array('currency' => $order->get_currency());
            $total_cols  = wc_tax_enabled() ? 4 : 3;

            $shipping_cost  = (float) ($transaction->shipping_cost ?? 0);
            $insurance_cost = (float) ($transaction->insurance_cost ?? 0);
            $cod_fee        = (float) ($transaction->cod_fee ?? 0);
            $is_cod         = $cod_fee > 0 || 'cod' === strtolower((string) $order->get_payment_method());

            $sub_total      = (float) $order->get_subtotal();
            $total_shipping = $shipping_cost + $insurance_cost + $cod_fee;
            $cod_paid       = (float) $order->get_total();

            // Compute discount breakdown from WC order (mirrors metabox logic).
            $wc_item_discount     = (float) $order->get_discount_total();
            $wc_shipping_discount = max(0.0, $shipping_cost - (float) $order->get_shipping_total());
            $discounted_shipping  = max(0.0, $shipping_cost - $wc_shipping_discount);
            $wc_coupon_codes      = $order->get_coupon_codes();
            $coupon_service       = new \KiriminAjaOfficial\Services\ShippingDiscountCouponService();
            $coupon_scopes        = $coupon_service->splitCouponCodesByScope( (array) $wc_coupon_codes );
            $first_coupon         = $coupon_scopes['item'][0] ?? '';
            $second_coupon        = $coupon_scopes['shipping'][0] ?? '';

            $inner = '';

            $inner .= $this->buildCompactPreviewRow(__('Sub Total', 'kiriminaja-official'), wc_price($sub_total, $price_args));
            $inner .= $this->buildCompactPreviewRow(__('Total Shipping', 'kiriminaja-official'), wc_price($total_shipping, $price_args));
            $inner .= $this->buildCompactPreviewRow('&nbsp;&nbsp;&nbsp;' . __('Shipping', 'kiriminaja-official'), wc_price($shipping_cost, $price_args), 'color:#50575e;');

            if ($insurance_cost > 0) {
                $inner .= $this->buildCompactPreviewRow('&nbsp;&nbsp;&nbsp;' . __('Insurance', 'kiriminaja-official'), wc_price($insurance_cost, $price_args), 'color:#50575e;');
            }

            if ($is_cod && $cod_fee > 0) {
                $inner .= $this->buildCompactPreviewRow('&nbsp;&nbsp;&nbsp;' . __('COD Fee', 'kiriminaja-official'), wc_price($cod_fee, $price_args), 'color:#50575e;');
            }

            // Item discount row: "CODE  Item" (or plain "Discount" if no coupon).
            if ($wc_item_discount > 0) {
                if ($first_coupon) {
                    $item_label = $first_coupon . ' <span style="color:#8c8f94;font-size:11px;">' . esc_html__('Item', 'kiriminaja-official') . '</span>';
                } else {
                    $item_label = __('Discount', 'kiriminaja-official');
                }
                $inner .= $this->buildCompactPreviewRow($item_label, wc_price(-$wc_item_discount, $price_args), 'color:#d63638;');
            }

            // Shipping discount row: "CODE  Shipping" (or plain "Shipping Discount" if no coupon).
            if ($wc_shipping_discount > 0) {
                if ($second_coupon) {
                    $ship_label = $second_coupon . ' <span style="color:#8c8f94;font-size:11px;">' . esc_html__('Shipping', 'kiriminaja-official') . '</span>';
                } else {
                    $ship_label = __('Shipping Discount (from KiriminAja)', 'kiriminaja-official');
                }
                $inner .= $this->buildCompactPreviewRow($ship_label, wc_price(-$wc_shipping_discount, $price_args), 'color:#d63638;');
                $inner .= $this->buildCompactPreviewRow(__('Discounted Shipping', 'kiriminaja-official'), wc_price($discounted_shipping, $price_args));
            }

            if ($is_cod) {
                $inner .= $this->buildCompactPreviewRow(__('COD Paid By Buyer', 'kiriminaja-official'), wc_price($cod_paid, $price_args), '', $wc_shipping_discount <= 0);
                $payout  = $cod_paid - $shipping_cost - $insurance_cost - $cod_fee;
                $inner  .= $this->buildCompactPreviewRow(__('Estimated COD Payout', 'kiriminaja-official'), wc_price($payout, $price_args), $payout < 0 ? 'color:#d63638;' : 'color:#007017;', false, true);
            } else {
                $inner .= $this->buildCompactPreviewRow(__('Total', 'kiriminaja-official'), wc_price($cod_paid, $price_args), '', true);
            }

            // Inline styles on the wrapper cell and inner table defeat WC's high-specificity td padding rules.
            $wrap_style  = 'padding:16px;border-top:1px solid #eee!important;border-bottom:0!important;';
            $table_style = 'width:100%;border-collapse:collapse;font-size:13px;';

            return sprintf(
                '<tr><td class="kiriof-order-preview-summary-wrap-cell" colspan="%d" style="%s"><table class="kiriof-order-preview-compact-summary" style="%s"><tbody>%s</tbody></table></td></tr>',
                (int) $total_cols,
                esc_attr($wrap_style),
                esc_attr($table_style),
                $inner
            );
        }

        private function buildCompactPreviewRow($label, $amount_html, $value_style = '', $is_separator = false, $is_bold = false)
        {
            $label_html  = wp_kses_post((string) $label);
            $value_style = esc_attr($value_style);
            $b_open      = ($is_separator || $is_bold) ? '<strong>' : '';
            $b_close     = ($is_separator || $is_bold) ? '</strong>' : '';

            // Inline td styles ensure WC's `.wc-order-preview-table td { padding:1em 1.5em }` cannot override.
            $td_base     = 'padding:4px 0;vertical-align:middle;border:0;text-align:left;';
            $td_val      = $td_base . 'text-align:right;white-space:nowrap;width:1%;' . $value_style;

            if ($is_separator) {
                $td_base .= 'border-top:1px solid #dcdcde;padding-top:8px;';
                $td_val  .= 'border-top:1px solid #dcdcde;padding-top:8px;';
            }

            return sprintf(
                '<tr><td style="%s">%s%s%s</td><td style="%s">%s%s%s</td></tr>',
                esc_attr($td_base),
                $b_open,
                $label_html,
                $b_close,
                esc_attr($td_val),
                $b_open,
                $amount_html,
                $b_close
            );
        }

        private function isTransactionProcessPage()
        {
            $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page slug check, no data processed

            return 'kiriminaja-transaction-process' === $page;
        }
    }
