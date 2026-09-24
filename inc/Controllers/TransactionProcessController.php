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
use KiriminAjaOfficial\Contracts\DatabaseTransactionManagerInterface;
use KiriminAjaOfficial\Repositories\TransactionRepository;
use KiriminAjaOfficial\Services\CheckoutServiceFactory;

class TransactionProcessController
{
    private TransactionRepository $transactionRepository;
    private DatabaseTransactionManagerInterface $transactionManager;
    private SendRequestPickupTransactionService $requestPickupService;
    private CancelTransactionService $cancelTransactionService;
    private \KiriminAjaOfficial\Services\TransactionProcessServices\GetRequestPickupScheduleService $pickupScheduleService;
    private CheckoutServiceFactory $checkoutServiceFactory;

    public function __construct(
        TransactionRepository $transactionRepository,
        DatabaseTransactionManagerInterface $transactionManager,
        SendRequestPickupTransactionService $requestPickupService,
        CancelTransactionService $cancelTransactionService,
        \KiriminAjaOfficial\Services\TransactionProcessServices\GetRequestPickupScheduleService $pickupScheduleService,
        CheckoutServiceFactory $checkoutServiceFactory
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->transactionManager    = $transactionManager;
        $this->requestPickupService  = $requestPickupService;
        $this->cancelTransactionService = $cancelTransactionService;
        $this->pickupScheduleService     = $pickupScheduleService;
        $this->checkoutServiceFactory    = $checkoutServiceFactory;
    }

    public function register()
    {
        /** getPaymentForm */
        add_action('wp_ajax_kiriof_request_pickup_schedule', array($this, 'getRequestPickupSchedule'));
        add_action('wp_ajax_kiriof_request_pickup_summary', array($this, 'getRequestPickupSummary'));
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
        $service = $this->pickupScheduleService
            ->orderIds($order_ids)
            ->call();
        wp_send_json_success($service);
    }

    public function getRequestPickupSummary()
    {
        if (! current_user_can( 'manage_woocommerce' )) {
            wp_send_json_error(array('status' => 403, 'message' => __('Insufficient permissions', 'kiriminaja-official')));
            wp_die();
        }
        if (! isset($_POST['data']['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['data']['nonce'])), KIRIOF_NONCE)) {
            wp_send_json_error(array('status' => 403, 'message' => __('Security check failed', 'kiriminaja-official')));
            wp_die();
        }

        $order_ids = isset($_POST['data']['order_ids']) && ! empty($_POST['data']['order_ids'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['data']['order_ids']))
            : array();

        wp_send_json_success($this->pickupScheduleService->orderIds($order_ids)->summary());
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

            $service = $this->requestPickupService
                ->orderIds($order_ids)
                ->schedule($schedule)
                ->paymentMethod($payment_method)
                ->pin($pin)
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

            $service = $this->cancelTransactionService
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
            $transactionRepo = $this->transactionRepository;
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

            $this->cancelTransactionService
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

        $transaction = $this->transactionRepository->getTransactionByWCOrderNumber($order->get_id());

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
                $order_details['kiriof_status_tone']    = 'critical';
            } else {
                $order_details['kiriof_status_label']   = kiriof_helper()->transactionStatusLabel(@$transaction->status);
                $order_details['kiriof_status_classes'] = kiriof_helper()->transactionStatusClass(@$transaction->status);
                $order_details['kiriof_status_tone']    = kiriof_helper()->packageStatusTone((string) (@$transaction->status ?? ''));
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
            $order_details['kiriof_status_tone']    = 'critical';
        } else {
            $order_details['kiriof_status_label']   = kiriof_helper()->transactionStatusLabel(@$transaction->status);
            $order_details['kiriof_status_classes'] = kiriof_helper()->transactionStatusClass(@$transaction->status);
            $order_details['kiriof_status_tone']    = kiriof_helper()->packageStatusTone((string) (@$transaction->status ?? ''));
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
                data-kiriof-status-class="{{ data.kiriof_status_classes }}"
                data-kiriof-status-tone="{{ data.kiriof_status_tone }}">
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
            wp_enqueue_script(
                'kiriof-order-preview',
                KIRIOF_URL . 'assets/admin/js/kj-order-preview.js',
                array('jquery', 'wc-backbone-modal'),
                KIRIOF_VERSION,
                true
            );
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

            $kiriof_transaction_repo = $this->transactionRepository;
            $kiriof_transaction      = $kiriof_transaction_repo->getTransactionByOrderId( $order_id );
            if (empty( $kiriof_transaction )) {
                wp_send_json_error( array( 'status' => 404, 'message' => __( 'Transaction not found.', 'kiriminaja-official' ) ) );
                wp_die();
            }
            if ('new' !== (string) $kiriof_transaction->status || ! empty( $kiriof_transaction->awb )) {
                wp_send_json_error( array( 'status' => 422, 'message' => __( 'Origin can only be changed before pickup is requested.', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $kiriof_current_snapshot = json_decode( (string) ( $kiriof_transaction->shipment_location_snapshot ?? '' ), true );
            $kiriof_current_location_id = is_array( $kiriof_current_snapshot )
                ? (int) ( $kiriof_current_snapshot['location_id'] ?? $kiriof_current_snapshot['id'] ?? 0 )
                : 0;
            if ( $kiriof_current_location_id < 1 && ! empty( $kiriof_transaction->shipment_location_id ) ) {
                $kiriof_current_location_id = (int) $kiriof_transaction->shipment_location_id;
            }
            if ( $kiriof_current_location_id < 1 ) {
                $kiriof_current_location_id = (int) ( $kiriof_location_service->getDefaultLocation()->id ?? 0 );
            }
            if ( $kiriof_current_location_id > 0 && $location_id === $kiriof_current_location_id ) {
                wp_send_json_error( array( 'status' => 422, 'message' => __( 'Please select a different shipment origin.', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $kiriof_previous_location = $kiriof_current_location_id > 0
                ? $kiriof_location_service->repository()->getById( $kiriof_current_location_id )
                : null;
            $kiriof_previous_origin_name = $kiriof_previous_location
                ? (string) $kiriof_previous_location->name
                : __( 'Default location', 'kiriminaja-official' );

            $kiriof_origin = $kiriof_location_service->originForLocation( $location_id );
            if (empty( $kiriof_origin['origin_sub_district_id'] )) {
                wp_send_json_error( array( 'status' => 422, 'message' => __( 'The selected origin is not covered by KiriminAja yet. Please choose another shipment origin.', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $kiriof_pricing = $this->checkoutServiceFactory->pricing( array(
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
            ) )->call();

            if ( 200 !== $kiriof_pricing->status() ) {
                wp_send_json_error( array(
                    'status'  => 422,
                    'message' => __( 'The selected origin is not covered for this destination. Please choose another shipment origin.', 'kiriminaja-official' ),
                ) );
                wp_die();
            }

            $kiriof_pricing_data = $kiriof_pricing->data();
            $kiriof_options      = is_array( $kiriof_pricing_data['options'] ?? null ) ? $kiriof_pricing_data['options'] : array();
            if ( empty( $kiriof_options ) ) {
                wp_send_json_error( array(
                    'status'  => 422,
                    'message' => __( 'The selected origin is not covered for this destination. Please choose another shipment origin.', 'kiriminaja-official' ),
                ) );
                wp_die();
            }
            $kiriof_previous_service = (string) ( $kiriof_transaction->service ?? '' );
            $kiriof_previous_name    = kiriof_helper()->formatServiceName( $kiriof_previous_service, (string) ( $kiriof_transaction->service_name ?? '' ) );
            $kiriof_previous_raw_shipping = (float) ( $kiriof_transaction->shipping_cost ?? 0 );
            $kiriof_previous_discount = min( $kiriof_previous_raw_shipping, max( 0, (float) ( $kiriof_transaction->discount_amount ?? 0 ) ) );
            $kiriof_previous_price   = max( 0, $kiriof_previous_raw_shipping - $kiriof_previous_discount );
            $kiriof_order = ! empty( $kiriof_transaction->wp_wc_order_stat_order_id ) ? wc_get_order( (int) $kiriof_transaction->wp_wc_order_stat_order_id ) : false;
            $kiriof_previous_paid_shipping = $kiriof_previous_price;
            $kiriof_previous_total = $kiriof_order ? (float) $kiriof_order->get_total() : 0;
            $kiriof_previous_order_shipping = $kiriof_order ? max( 0, (float) $kiriof_order->get_shipping_total() ) : $kiriof_previous_paid_shipping;
            $kiriof_previous_non_shipping_total = $kiriof_previous_total - $kiriof_previous_order_shipping;
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
                    'raw_price' => (float) ( $kiriof_option['raw_price'] ?? $kiriof_option['price'] ?? 0 ),
                    'discount_amount' => (float) ( $kiriof_option['discount_amount'] ?? 0 ),
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
                $kiriof_new_raw_shipping = (float) $kiriof_matched_option['raw_price'];
                $kiriof_new_discount     = min( $kiriof_new_raw_shipping, max( 0, (float) $kiriof_matched_option['discount_amount'] ) );
                $kiriof_new_paid_shipping = max( 0, $kiriof_new_raw_shipping - $kiriof_new_discount );
                $kiriof_delta = $kiriof_new_paid_shipping - $kiriof_previous_paid_shipping;
                if ( $kiriof_delta > 0 ) {
                    /* translators: 1: courier service name, 2: formatted price increase. */
                    $kiriof_change_label = sprintf( __( '%1$s price increases by %2$s.', 'kiriminaja-official' ), $kiriof_previous_name, wp_strip_all_tags( wc_price( $kiriof_delta ) ) );
                } elseif ( $kiriof_delta < 0 ) {
                    /* translators: 1: courier service name, 2: formatted price decrease. */
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
                    'previous_order_shipping' => $kiriof_previous_order_shipping,
                    'previous_non_shipping_total' => $kiriof_previous_non_shipping_total,
                    'previous_courier' => $kiriof_previous_name,
                    'new_courier' => kiriof_helper()->formatServiceName( (string) $kiriof_matched_option['service_code'], (string) $kiriof_matched_option['service_name'] ),
                       'new_raw_shipping' => $kiriof_new_raw_shipping,
                    'new_discount' => $kiriof_new_discount,
                    'new_paid_shipping' => $kiriof_new_paid_shipping,
                    'previous_subtotal' => $kiriof_order ? (float) $kiriof_order->get_subtotal() : 0,
                    'previous_total_shipping' => $kiriof_previous_raw_shipping + (float) ( $kiriof_transaction->insurance_cost ?? 0 ) + (float) ( $kiriof_transaction->cod_fee ?? 0 ),
                    'previous_discounted_shipping' => $kiriof_previous_paid_shipping,
                    'new_total_shipping' => $kiriof_new_raw_shipping + (float) ( $kiriof_transaction->insurance_cost ?? 0 ) + (float) ( $kiriof_transaction->cod_fee ?? 0 ),
                    'new_discounted_shipping' => $kiriof_new_paid_shipping,
                 );
                $kiriof_raw_new_total = $kiriof_previous_non_shipping_total + $kiriof_comparison['new_paid_shipping'];
                $kiriof_comparison['total_was_clamped'] = $kiriof_raw_new_total < 0;
                $kiriof_comparison['is_total_blocked'] = $kiriof_raw_new_total < 0;
                $kiriof_comparison['required_refund'] = max( 0, $kiriof_previous_paid_shipping - $kiriof_comparison['new_paid_shipping'] );
                $kiriof_comparison['new_total'] = max( 0, $kiriof_raw_new_total );
                $kiriof_comparison['total_delta'] = $kiriof_comparison['new_total'] - $kiriof_previous_total;
            } else {
                /* translators: %s: courier service name. */
                $kiriof_unavailable_label = sprintf( __( '%s is not available from the selected origin.', 'kiriminaja-official' ), $kiriof_previous_name );
                $kiriof_comparison = array(
                    'available'     => false,
                    'label'        => $kiriof_unavailable_label,
                    'old_price'    => wc_price( $kiriof_previous_price ),
                    'service_code' => $kiriof_previous_service,
                    'service_name' => (string) ( $kiriof_transaction->service_name ?? '' ),
                    'previous_raw_shipping' => $kiriof_previous_raw_shipping,
                    'previous_discount' => $kiriof_previous_discount,
                    'previous_paid_shipping' => $kiriof_previous_paid_shipping,
                    'previous_total' => $kiriof_previous_total,
                    'previous_order_shipping' => $kiriof_previous_order_shipping,
                    'previous_non_shipping_total' => $kiriof_previous_non_shipping_total,
                    'previous_courier' => $kiriof_previous_name,
                    'new_courier' => '',
                    'previous_subtotal' => $kiriof_order ? (float) $kiriof_order->get_subtotal() : 0,
                    'insurance_cost' => (float) ( $kiriof_transaction->insurance_cost ?? 0 ),
                    'cod_fee' => (float) ( $kiriof_transaction->cod_fee ?? 0 ),
                );
            }

            wp_send_json_success( array(
                'message' => __( 'Shipping check passed. Review the courier and price impact before confirming.', 'kiriminaja-official' ),
                'comparison' => $kiriof_comparison,
                'options' => array_values( $kiriof_normalized ),
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

            $kiriof_transaction_repo = $this->transactionRepository;
            $kiriof_transaction      = $kiriof_transaction_repo->getTransactionByOrderId( $order_id );
            if (empty( $kiriof_transaction )) {
                wp_send_json_error( array( 'status' => 404, 'message' => __( 'Transaction not found.', 'kiriminaja-official' ) ) );
                wp_die();
            }
            if ('new' !== (string) $kiriof_transaction->status || ! empty( $kiriof_transaction->awb )) {
                wp_send_json_error( array( 'status' => 422, 'message' => __( 'Origin can only be changed before pickup is requested.', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $kiriof_current_snapshot = json_decode( (string) ( $kiriof_transaction->shipment_location_snapshot ?? '' ), true );
            $kiriof_current_location_id = is_array( $kiriof_current_snapshot )
                ? (int) ( $kiriof_current_snapshot['location_id'] ?? $kiriof_current_snapshot['id'] ?? 0 )
                : 0;
            if ( $kiriof_current_location_id < 1 && ! empty( $kiriof_transaction->shipment_location_id ) ) {
                $kiriof_current_location_id = (int) $kiriof_transaction->shipment_location_id;
            }
            if ( $kiriof_current_location_id < 1 ) {
                $kiriof_current_location_id = (int) ( $kiriof_location_service->getDefaultLocation()->id ?? 0 );
            }
            if ( $kiriof_current_location_id > 0 && $location_id === $kiriof_current_location_id ) {
                wp_send_json_error( array( 'status' => 422, 'message' => __( 'Please select a different shipment origin.', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $courier_service      = isset( $_POST['courier_service'] ) ? sanitize_text_field( wp_unslash( $_POST['courier_service'] ) ) : '';
            $courier_service_name = isset( $_POST['courier_service_name'] ) ? sanitize_text_field( wp_unslash( $_POST['courier_service_name'] ) ) : '';
            $courier_consent      = ! empty( $_POST['courier_consent'] );
            $kiriof_verified_rate = $this->getVerifiedChangeOriginRate( $kiriof_transaction, $location_id, $courier_service, $courier_service_name );
            if ( is_wp_error( $kiriof_verified_rate ) ) {
                wp_send_json_error( array( 'status' => 422, 'message' => $kiriof_verified_rate->get_error_message() ) );
                wp_die();
            }

            $courier_service      = (string) $kiriof_verified_rate['service_code'];
            $courier_service_name = (string) $kiriof_verified_rate['service_name'];
            $courier_price        = (float) $kiriof_verified_rate['raw_price'];
            $courier_discount     = min( $courier_price, max( 0, (float) $kiriof_verified_rate['discount_amount'] ) );
            $kiriof_wc_order_for_validation = ! empty( $kiriof_transaction->wp_wc_order_stat_order_id )
                ? wc_get_order( (int) $kiriof_transaction->wp_wc_order_stat_order_id )
                : false;
            if ( $kiriof_wc_order_for_validation ) {
                $kiriof_validated_paid_shipping = max( 0, $courier_price - $courier_discount );
                $kiriof_validated_non_shipping_total = (float) $kiriof_wc_order_for_validation->get_total()
                    - max( 0, (float) $kiriof_wc_order_for_validation->get_shipping_total() );
                $kiriof_adjusted_total = $kiriof_validated_non_shipping_total + $kiriof_validated_paid_shipping;
                if ( $kiriof_adjusted_total < 0 ) {
                    wp_send_json_error( array( 'status' => 422, 'message' => __( 'This courier change requires buyer refund reconciliation before it can be processed.', 'kiriminaja-official' ) ) );
                    wp_die();
                }
            }
            $kiriof_normalize_service = static function ( $service ) {
                return strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $service ) );
            };
            $kiriof_previous_service  = $kiriof_normalize_service( $kiriof_transaction->service ?? '' );
            $kiriof_selected_service  = $kiriof_normalize_service( $courier_service );
            $kiriof_is_replacement    = '' !== $kiriof_selected_service && $kiriof_selected_service !== $kiriof_previous_service;

            if ( $kiriof_is_replacement && ! $courier_consent ) {
                wp_send_json_error( array( 'status' => 422, 'message' => __( 'Courier replacement requires your consent.', 'kiriminaja-official' ) ) );
                wp_die();
            }
            $kiriof_courier_update = array(
                'service'         => $courier_service,
                'service_name'    => $courier_service_name,
                'shipping_cost'   => $courier_price,
                'discount_amount' => $courier_discount,
            );

            $kiriof_previous_location = $kiriof_current_location_id > 0
                ? $kiriof_location_service->repository()->getById( $kiriof_current_location_id )
                : null;
            $kiriof_previous_origin_name = $kiriof_previous_location
                ? (string) $kiriof_previous_location->name
                : __( 'Default location', 'kiriminaja-official' );

            $kiriof_snapshot = wp_json_encode( $kiriof_location_service->locationToOrigin( $kiriof_location ) );

            $kiriof_wc_order = ! empty( $kiriof_transaction->wp_wc_order_stat_order_id )
                ? wc_get_order( (int) $kiriof_transaction->wp_wc_order_stat_order_id )
                : false;
            if ( ! $kiriof_wc_order ) {
                wp_send_json_error( array( 'status' => 422, 'message' => __( 'WooCommerce order not found. No shipment data was changed.', 'kiriminaja-official' ) ) );
                wp_die();
            }

            // Keep the transaction row, WooCommerce metadata, and private note atomic on supported database engines.
            $this->transactionManager->begin();
            $kiriof_updated = $kiriof_transaction_repo->updateTransactionShipmentLocation( $order_id, $location_id, $kiriof_snapshot, $kiriof_courier_update );
            if (! $kiriof_updated) {
                $this->transactionManager->rollback();
                wp_send_json_error( array( 'status' => 500, 'message' => __( 'Failed to update the shipment origin.', 'kiriminaja-official' ) ) );
                wp_die();
            }

            try {
                $kiriof_current_user = wp_get_current_user();
                $kiriof_actor         = $kiriof_current_user instanceof \WP_User && $kiriof_current_user->exists()
                    ? $kiriof_current_user->display_name
                    : __( 'store administrator', 'kiriminaja-official' );

                $kiriof_previous_courier = kiriof_helper()->formatServiceName(
                    (string) ( $kiriof_transaction->service ?? '' ),
                    (string) ( $kiriof_transaction->service_name ?? '' )
                );
                $kiriof_new_courier = $kiriof_courier_update
                    ? kiriof_helper()->formatServiceName( $courier_service, $courier_service_name )
                    : $kiriof_previous_courier;

                $kiriof_previous_shipping = max( 0, (float) ( $kiriof_transaction->shipping_cost ?? 0 ) );
                $kiriof_new_shipping      = $kiriof_courier_update ? max( 0, $courier_price ) : $kiriof_previous_shipping;
                $kiriof_shipping_delta    = $kiriof_new_shipping - $kiriof_previous_shipping;

                $kiriof_previous_discount = max( 0, (float) ( $kiriof_transaction->discount_amount ?? 0 ) );
                $kiriof_new_discount      = min( $kiriof_new_shipping, $courier_discount );
                $kiriof_previous_paid     = max( 0, $kiriof_previous_shipping - $kiriof_previous_discount );
                $kiriof_new_paid          = max( 0, $kiriof_new_shipping - $kiriof_new_discount );
                $kiriof_previous_total    = (float) $kiriof_wc_order->get_total();

                $kiriof_shipping_items = $kiriof_wc_order->get_items( 'shipping' );
                $kiriof_shipping_item  = null;
                foreach ( $kiriof_shipping_items as $kiriof_candidate_shipping_item ) {
                    $kiriof_method_id = method_exists( $kiriof_candidate_shipping_item, 'get_method_id' )
                        ? (string) $kiriof_candidate_shipping_item->get_method_id()
                        : '';
                    if ( 0 === strpos( $kiriof_method_id, 'kiriminaja-official' ) ) {
                        $kiriof_shipping_item = $kiriof_candidate_shipping_item;
                        break;
                    }
                    if ( null === $kiriof_shipping_item ) {
                        $kiriof_shipping_item = $kiriof_candidate_shipping_item;
                    }
                }
                if ( ! $kiriof_shipping_item ) {
                    throw new \RuntimeException( __( 'WooCommerce shipping item not found.', 'kiriminaja-official' ) );
                }

                $kiriof_shipping_item->set_method_title( $kiriof_new_courier );
                $kiriof_shipping_item->set_name( $kiriof_new_courier );
                $kiriof_shipping_item->set_total( $kiriof_new_paid );
                $kiriof_shipping_item->calculate_taxes();
                if ( ! $kiriof_shipping_item->save() ) {
                    throw new \RuntimeException( __( 'Failed to save the WooCommerce shipping total.', 'kiriminaja-official' ) );
                }

                $kiriof_wc_order->calculate_totals( false );
                $kiriof_new_total   = (float) $kiriof_wc_order->get_total();
                $kiriof_total_delta = $kiriof_new_total - $kiriof_previous_total;

                $kiriof_format_price = static function ( $amount ) {
                    return wp_strip_all_tags( wc_price( max( 0, (float) $amount ) ) );
                };
                $kiriof_format_delta = static function ( $amount ) use ( $kiriof_format_price ) {
                    $kiriof_amount = (float) $amount;
                    if ( 0.0 === $kiriof_amount ) {
                        return __( 'no change', 'kiriminaja-official' );
                    }

                    return sprintf(
                        '%1$s%2$s',
                        $kiriof_amount > 0 ? '+' : '-',
                        $kiriof_format_price( abs( $kiriof_amount ) )
                    );
                };

                $kiriof_note_lines = array(
                    sprintf(
                        /* translators: %s: administrator name. */
                        __( 'Shipment fulfillment updated by %s.', 'kiriminaja-official' ),
                        $kiriof_actor
                    ),
                    sprintf(
                        /* translators: 1: previous origin, 2: new origin. */
                        __( 'Origin: %1$s → %2$s', 'kiriminaja-official' ),
                        $kiriof_previous_origin_name,
                        (string) $kiriof_location->name
                    ),
                    sprintf(
                        /* translators: 1: previous courier, 2: new courier. */
                        __( 'Courier: %1$s → %2$s', 'kiriminaja-official' ),
                        $kiriof_previous_courier,
                        $kiriof_new_courier
                    ),
                    sprintf(
                        /* translators: 1: previous shipping cost, 2: new shipping cost, 3: formatted difference. */
                        __( 'Shipping cost: %1$s → %2$s (%3$s)', 'kiriminaja-official' ),
                        $kiriof_format_price( $kiriof_previous_shipping ),
                        $kiriof_format_price( $kiriof_new_shipping ),
                        $kiriof_format_delta( $kiriof_shipping_delta )
                    ),
                );

                if ( $kiriof_previous_discount > 0 || $kiriof_new_discount > 0 ) {
                    $kiriof_note_lines[] = sprintf(
                        /* translators: 1: previous shipping discount, 2: new shipping discount. */
                        __( 'Shipping discount: %1$s → %2$s', 'kiriminaja-official' ),
                        $kiriof_format_price( $kiriof_previous_discount ),
                        $kiriof_format_price( $kiriof_new_discount )
                    );
                }

                $kiriof_note_lines[] = sprintf(
                    /* translators: 1: previous calculated order total, 2: new calculated order total, 3: formatted difference. */
                    __( 'Calculated order total: %1$s → %2$s (%3$s)', 'kiriminaja-official' ),
                    $kiriof_format_price( $kiriof_previous_total ),
                    $kiriof_format_price( $kiriof_new_total ),
                    $kiriof_format_delta( $kiriof_total_delta )
                );

                $kiriof_wc_order->update_meta_data( '_kiriof_expedition_code', $courier_service . '_' . $courier_service_name );
                $kiriof_wc_order->update_meta_data( '_kiriof_expedition_name', $kiriof_new_courier );
                $kiriof_wc_order->update_meta_data( '_kiriof_expedition_cost', $kiriof_new_shipping );
                if ( ! $kiriof_wc_order->save() ) {
                    throw new \RuntimeException( __( 'Failed to save the WooCommerce shipment data.', 'kiriminaja-official' ) );
                }

                $kiriof_note_id = $kiriof_wc_order->add_order_note(
                    implode( "\n", $kiriof_note_lines )
                );
                if ( ! $kiriof_note_id ) {
                    throw new \RuntimeException( __( 'Failed to create the shipment audit note.', 'kiriminaja-official' ) );
                }
            } catch ( \Throwable $throwable ) {
                $this->transactionManager->rollback();
                ( new \KiriminAjaOfficial\Base\BaseInit() )->logThis( 'changeOrigin rollback', array( $throwable->getMessage(), $order_id ) );
                wp_send_json_error( array( 'status' => 500, 'message' => __( 'Failed to update the shipment origin. No shipment data was changed.', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $this->transactionManager->commit();

            wp_send_json_success( array( 'message' => __( 'Shipment origin updated.', 'kiriminaja-official' ) ) );
            wp_die();
        }

        /**
         * Re-check the selected courier against the pricing API before saving.
         * Client-provided prices are intentionally ignored.
         *
         * @param object $transaction Transaction row.
         * @param int    $location_id Selected shipment location.
         * @param string $service_code Courier code selected by the seller.
         * @param string $service_name Courier service type selected by the seller.
         * @return array|\WP_Error
         */
        private function getVerifiedChangeOriginRate( $transaction, $location_id, $service_code, $service_name )
        {
            if ( '' === $service_code || '' === $service_name ) {
                return new \WP_Error( 'kiriof_missing_shipping_check', __( 'Please run the shipping check again before confirming.', 'kiriminaja-official' ) );
            }

            $location_service = new \KiriminAjaOfficial\Services\ShipmentLocationService();
            $origin           = $location_service->originForLocation( $location_id );
            if ( empty( $origin['origin_sub_district_id'] ) ) {
                return new \WP_Error( 'kiriof_invalid_origin_area', __( 'The selected origin is not covered by KiriminAja yet. Please choose another shipment origin.', 'kiriminaja-official' ) );
            }

            $pricing = $this->checkoutServiceFactory->pricing( array(
                'destination_area_id'    => (int) $transaction->destination_sub_district_id,
                'origin_sub_district_id' => (int) $origin['origin_sub_district_id'],
                'package_overrides'      => array(
                    'weight'     => (int) $transaction->weight,
                    'length'     => (float) $transaction->length,
                    'width'      => (float) $transaction->width,
                    'height'     => (float) $transaction->height,
                    'item_value' => (float) ( $transaction->transaction_value ?? 0 ),
                ),
                'is_cod'                 => ( (float) ( $transaction->cod_fee ?? 0 ) > 0 ),
            ) )->call();

            if ( 200 !== $pricing->status() ) {
                return new \WP_Error( 'kiriof_shipping_check_failed', __( 'The selected origin is not covered for this destination. Please choose another shipment origin.', 'kiriminaja-official' ) );
            }

            $pricing_data  = $pricing->data();
            $options       = is_array( $pricing_data['options'] ?? null ) ? $pricing_data['options'] : array();
            if ( empty( $options ) ) {
                return new \WP_Error( 'kiriof_origin_not_covered', __( 'The selected origin is not covered for this destination. Please choose another shipment origin.', 'kiriminaja-official' ) );
            }
            $normalize     = static function ( $value ) {
                return strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $value ) );
            };
            $expected_code = $normalize( $service_code );
            $expected_name = $normalize( $service_name );

            foreach ( $options as $option ) {
                $option_code = $normalize( $option['service_code'] ?? '' );
                $option_name = $normalize( $option['service_name'] ?? $option['service_type'] ?? '' );
                if ( $expected_code === $option_code && $expected_name === $option_name ) {
                    return array(
                        'service_code'    => (string) ( $option['service_code'] ?? '' ),
                        'service_name'    => (string) ( $option['service_name'] ?? $option['service_type'] ?? '' ),
                        'raw_price'       => max( 0, (float) ( $option['raw_price'] ?? $option['price'] ?? 0 ) ),
                        'discount_amount' => max( 0, (float) ( $option['discount_amount'] ?? 0 ) ),
                    );
                }
            }

            return new \WP_Error( 'kiriof_shipping_rate_changed', __( 'The selected courier is no longer available. Please run the shipping check again.', 'kiriminaja-official' ) );
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
                <template id="tmpl-kiriof-modal-cod-adjustment">
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
        </template>

                <template id="tmpl-kiriof-modal-cancel-deficit">
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
        </template>

                <?php if ($this->isTransactionProcessPage()) : ?>
                    <template id="tmpl-kiriof-modal-request-pickup">
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
        </template>

                    <template id="tmpl-kiriof-modal-cancel-transaction">
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
        </template>
        <template id="tmpl-kiriof-modal-change-origin">
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
                                    <span class="kiriof-backbone-label kiriof-origin-field-header">
										<span><?php esc_html_e( 'Shipment origin', 'kiriminaja-official' ); ?> <span class="required">*</span></span>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=kiriminaja_warehouses' ) ); ?>"><?php esc_html_e( 'Manage shipment locations', 'kiriminaja-official' ); ?></a>
                                    </span>
                                    <div class="kiriof-compact-selection kiriof-origin-selection-summary">
                                        <span class="kiriof-compact-selection-copy">
                                            <strong class="kiriof-origin-selection-name">{{ data.current_origin }}</strong>
                                            <small class="kiriof-origin-selection-address">{{ data.current_origin_address }}</small>
                                        </span>
                                        <button type="button" class="button button-small kiriof-origin-toggle"><?php esc_html_e( 'Change', 'kiriminaja-official' ); ?></button>
                                    </div>
                                    <div class="kiriof-choice-panel kiriof-origin-choice-panel" style="display:none;">
                                    <div class="kiriof-radio-card-group kiriof-origin-radio-group" role="radiogroup" aria-label="<?php esc_attr_e( 'Shipment origin', 'kiriminaja-official' ); ?>">
                                        <label class="kiriof-radio-card kiriof-radio-card-current">
                                            <input type="radio" name="location_id" value="{{ data.current_location_id }}" checked data-current="1">
                                            <span class="kiriof-radio-card-copy">
                                                <strong>{{ data.current_origin }}</strong>
                                                <small>{{ data.current_origin_address }}</small>
                                                <em><?php esc_html_e( 'Current origin', 'kiriminaja-official' ); ?></em>
                                            </span>
                                        </label>
                                        <?php foreach ( $kiriof_shipment_locations as $kiriof_location_option ) : ?>
                                            <?php
                                            $kiriof_location_address = $kiriof_location_service->formatAddress( $kiriof_location_option );
                                            $kiriof_location_label   = (string) $kiriof_location_option->name;
                                            if ( '' !== $kiriof_location_address ) {
                                                $kiriof_location_label .= ' — ' . $kiriof_location_address;
                                            }
                                            ?>
                                            <label class="kiriof-radio-card" data-location-id="<?php echo esc_attr( $kiriof_location_option->id ); ?>">
                                                <input type="radio" name="location_id" value="<?php echo esc_attr( $kiriof_location_option->id ); ?>">
                                                <span class="kiriof-radio-card-copy">
                                                    <strong><?php echo esc_html( $kiriof_location_option->name ); ?></strong>
                                                    <?php if ( '' !== $kiriof_location_address ) : ?>
                                                        <small><?php echo esc_html( $kiriof_location_address ); ?></small>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="button button-small kiriof-origin-collapse"><?php esc_html_e( 'Cancel', 'kiriminaja-official' ); ?></button>
                                    </div>
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
                                 <div class="kiriof-courier-selection-section" style="display:none;">
                                     <h2 class="kiriof-courier-radio-title"><?php esc_html_e( 'Courier', 'kiriminaja-official' ); ?></h2>
                                     <div class="kiriof-compact-selection kiriof-courier-selection-summary"></div>
                                 </div>
                                 <div class="kiriof-change-origin-replacement" style="display:none;"></div>
                                 <div class="kiriof-change-origin-breakdown kiriof-change-origin-card" style="display:none;" aria-live="polite"></div>
                                 <div class="kiriof-replacement-consent-wrap" style="display:none;">
                                     <label><input type="checkbox" class="kiriof-replacement-consent"> <?php esc_html_e( 'I consent to use this replacement courier.', 'kiriminaja-official' ); ?></label>
                                 </div>
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
            <div class="wc-backbone-modal-backdrop kiriof-change-origin-backdrop modal-close"></div>
        </template>
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

            $cod_paid       = (float) $order->get_total();

            // Compute discount breakdown from WC order (mirrors metabox logic).
            $wc_item_discount     = (float) $order->get_discount_total();
            $paid_shipping       = max(0.0, (float) $order->get_shipping_total());
            $wc_shipping_discount = max(0.0, $shipping_cost - $paid_shipping);
            $wc_coupon_codes      = $order->get_coupon_codes();
            $coupon_service       = new \KiriminAjaOfficial\Services\ShippingDiscountCouponService();
            $coupon_scopes        = $coupon_service->splitCouponCodesByScope( (array) $wc_coupon_codes );
            $first_coupon         = $coupon_scopes['item'][0] ?? '';
            $second_coupon        = $coupon_scopes['shipping'][0] ?? '';
            $inner = '';

            $inner .= $this->buildCompactPreviewRow(__('Sub Total', 'kiriminaja-official'), wc_price((float) $order->get_subtotal(), $price_args));
            $inner .= $this->buildCompactPreviewRow(__('Actual Shipping', 'kiriminaja-official'), wc_price($shipping_cost, $price_args));

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

            if ($wc_shipping_discount > 0) {
                $ship_label = __('Shipping Discount', 'kiriminaja-official');
                if ($second_coupon) {
                    $ship_label .= ' <span style="color:#8c8f94;font-size:11px;">' . esc_html($second_coupon) . '</span>';
                }
                $inner .= $this->buildCompactPreviewRow($ship_label, wc_price(-$wc_shipping_discount, $price_args), 'color:#d63638;');
                $inner .= $this->buildCompactPreviewRow(__('Shipping', 'kiriminaja-official'), wc_price($paid_shipping, $price_args));
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
