<?php

namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

use KiriminAjaOfficial\Services\TransactionProcessServices\SendRequestPickupTransactionService;
use KiriminAjaOfficial\Services\TransactionProcessServices\CancelTransactionService;

class TransactionProcessController
{
    public function register()
    {
        /** getPaymentForm */
        add_action('wp_ajax_kiriof_request_pickup_schedule', array($this, 'getRequestPickupSchedule'));
        add_action('wp_ajax_kiriof_request_pickup_transaction', array($this, 'sendRequestPickupTransaction'));
        add_action('wp_ajax_kiriof_cancel_transaction', array($this, 'cancelTransaction'));
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
            $service = (new \KiriminAjaOfficial\Services\TransactionProcessServices\SendRequestPickupTransactionService())
                ->orderIds($order_ids)
                ->schedule($schedule)
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

        public function renderWooActionModalTemplatesForKiriofPage()
        {
            if (! $this->isTransactionProcessPage() && ! $this->isOrderEditScreen()) {
                return;
            }
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
                                                <?php esc_html_e('Shipping Discount', 'kiriminaja-official'); ?>
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

                                    <div class="kiriof-backbone-section">
                                        <h2 class="kiriof-backbone-section-title"><?php esc_html_e('Available Schedules', 'kiriminaja-official'); ?></h2>
                                        <div class="kiriof-schedule-opt-list"></div>
                                    </div>

                                    <p class="kiriof-backbone-inline-error err_msg" style="display:none;"></p>
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
                    $ship_label = __('Shipping Discount', 'kiriminaja-official');
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
