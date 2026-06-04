<?php
namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Services\TransactionProcessServices\SendRequestPickupTransactionService;
use KiriminAjaOfficial\Services\TransactionProcessServices\CancelTransactionService;
class TransactionProcessController{
    public function register(){
        /** getPaymentForm */
        add_action('wp_ajax_kiriof_request_pickup_schedule', array($this,'getRequestPickupSchedule'));
        add_action('wp_ajax_kiriof_request_pickup_transaction', array($this,'sendRequestPickupTransaction'));
        add_action('wp_ajax_kiriof_cancel_transaction', array($this,'cancelTransaction'));
        add_filter( 'woocommerce_admin_order_preview_get_order_details', array( $this, 'extendWooOrderPreviewDetails' ), 10, 2 );
        add_action( 'woocommerce_admin_order_preview_end', array( $this, 'renderWooOrderPreviewKiriminajaDetails' ) );
        add_action( 'admin_footer', array( $this, 'renderWooOrderPreviewKiriminajaRelocatorScript' ) );
        add_action( 'admin_footer', array( $this, 'renderWooOrderPreviewTemplateForKiriofPage' ) );
        add_action( 'admin_footer', array( $this, 'renderWooActionModalTemplatesForKiriofPage' ) );

        /** Auto-cancel KA transaction when WC order is cancelled */
        add_action('woocommerce_order_status_cancelled', array($this, 'handleWcOrderCancelled'), 10, 1);
    }
    
    public function getRequestPickupSchedule(){
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
            wp_die();
        }
        // Check for nonce security - fail early
        if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
            wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
            wp_die();
        }
        $order_ids = ( isset($_POST['data']['order_ids']) && !empty($_POST['data']['order_ids']) 
            ? array_map('sanitize_text_field', wp_unslash($_POST['data']['order_ids']) ) 
            : [] 
        );
        $service = (new \KiriminAjaOfficial\Services\TransactionProcessServices\GetRequestPickupScheduleService())
            ->orderIds($order_ids)
            ->call();
        wp_send_json_success($service);
    }
    
    public function sendRequestPickupTransaction(){
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
            $order_ids = ( isset($_POST['data']['order_ids']) && !empty($_POST['data']['order_ids']) 
                ? array_map('sanitize_text_field', wp_unslash($_POST['data']['order_ids']) ) 
                : [] 
            );
            $schedule = ( isset($_POST['data']['schedule']) && !empty($_POST['data']['schedule']) 
                ? sanitize_text_field( wp_unslash( $_POST['data']['schedule'] ))  
                : '' 
            );
            $service = (new \KiriminAjaOfficial\Services\TransactionProcessServices\SendRequestPickupTransactionService())
                ->orderIds( $order_ids )
                ->schedule( $schedule )
                ->call();
            wp_send_json_success($service);
        }catch (\Throwable $th){
            wp_send_json_success([
                'status'    => 400,
                'message'   => $th->getMessage(),
            ]);
        }
       
    }
    public function cancelTransaction(){
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            // Check for nonce security
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }

            $order_id = isset( $_POST['data']['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['data']['order_id'] ) ) : '';
            $reason   = isset( $_POST['data']['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['data']['reason'] ) ) : '';

            $service = ( new CancelTransactionService() )
                ->orderId( $order_id )
                ->reason( $reason )
                ->call();

            wp_send_json_success( $service );
        } catch ( \Throwable $th ) {
            wp_send_json_success( [
                'status'  => 400,
                'message' => $th->getMessage(),
            ] );
        }
    }

    public function handleWcOrderCancelled( $order_id ) {
        try {
            $transactionRepo = new \KiriminAjaOfficial\Repositories\TransactionRepository();
            $transaction     = $transactionRepo->getTransactionByWCOrderId( $order_id );

            if ( ! $transaction ) {
                return;
            }

            // Skip if already canceled or in a terminal status (e.g. webhook already handled it)
            $terminalStatuses = [ 'shipped', 'finished', 'returned', 'return', 'canceled' ];
            if ( in_array( $transaction->status, $terminalStatuses, true ) ) {
                return;
            }

            // Skip if no AWB — nothing to cancel on Mitra side
            if ( empty( $transaction->awb ) ) {
                return;
            }

            $reason = __( 'Pesanan dibatalkan dari WooCommerce', 'kiriminaja-official' );

            ( new CancelTransactionService() )
                ->orderId( $transaction->order_id )
                ->reason( $reason )
                ->call();
        } catch ( \Throwable $th ) {
            ( new \KiriminAjaOfficial\Base\BaseInit() )->logThis( 'handleWcOrderCancelled error', [ $th->getMessage() ] );
        }
    }

    public function extendWooOrderPreviewDetails( $order_details, $order ) {
        if ( ! $order instanceof \WC_Order || empty( $order_details['item_html'] ) ) {
            return $order_details;
        }

        $transaction = ( new \KiriminAjaOfficial\Repositories\TransactionRepository() )
            ->getTransactionByWCOrderNumber( $order->get_id() );

        if ( ! $transaction ) {
            return $order_details;
        }

        $summary_rows_html = $this->getWooOrderPreviewSummaryRowsHtml( $order, $transaction );

        if ( '' === $summary_rows_html ) {
            $order_details['kiriof_ka_order_id'] = $transaction->order_id ?? '';
            $order_details['kiriof_awb']         = $transaction->awb ?? '';
            $order_details['kiriof_status_label']   = kiriof_helper()->transactionStatusLabel( @$transaction->status );
            $order_details['kiriof_status_classes'] = kiriof_helper()->transactionStatusClass( @$transaction->status );
            return $order_details;
        }

        $order_details['item_html'] = str_replace(
            '</tbody>',
            $summary_rows_html . '</tbody>',
            $order_details['item_html']
        );

        $order_details['kiriof_ka_order_id'] = $transaction->order_id ?? '';
        $order_details['kiriof_awb']         = $transaction->awb ?? '';
        $order_details['kiriof_status_label']   = kiriof_helper()->transactionStatusLabel( @$transaction->status );
        $order_details['kiriof_status_classes'] = kiriof_helper()->transactionStatusClass( @$transaction->status );

        if ( ! empty( $transaction->awb ) && ! empty( $transaction->order_id ) ) {
            $print_url = admin_url( 'admin-post.php?action=kiriof_resi_print&oids=' . urlencode( $transaction->order_id ) . '&_wpnonce=' . wp_create_nonce( 'kiriof_resi_print' ) );

            $order_details['actions_html'] .= ' <a class="button button-large" href="' . esc_url( $print_url ) . '" target="_blank">' . esc_html__( 'Print', 'kiriminaja-official' ) . '</a>';
        }

        return $order_details;
    }

    public function renderWooOrderPreviewKiriminajaDetails() {
        ?>
        <# if ( data.kiriof_ka_order_id || data.kiriof_awb ) { #>
            <div
                class="kiriof-order-preview-shipment-details kiriof-order-preview-status-source"
                data-kiriof-status-label="{{ data.kiriof_status_label }}"
                data-kiriof-status-class="{{ data.kiriof_status_classes }}"
            >
                <# if ( data.kiriof_ka_order_id ) { #>
                    <strong><?php esc_html_e( 'KA Order ID', 'kiriminaja-official' ); ?></strong>
                    {{ data.kiriof_ka_order_id }}
                <# } #>

                <# if ( data.kiriof_awb ) { #>
                    <strong><?php esc_html_e( 'AWB', 'kiriminaja-official' ); ?></strong>
                    {{ data.kiriof_awb }}
                <# } #>
            </div>
        <# } #>
        <?php
    }

    public function renderWooOrderPreviewKiriminajaRelocatorScript() {
        ?>
        <script>
            jQuery(function($) {
                function kiriofPreviewStatusPalette(statusClass) {
                    if ((statusClass || '').indexOf('primary') !== -1) {
                        return { background: '#2563eb', color: '#ffffff' };
                    }
                    if ((statusClass || '').indexOf('info') !== -1) {
                        return { background: '#0891b2', color: '#ffffff' };
                    }
                    if ((statusClass || '').indexOf('warning') !== -1) {
                        return { background: '#f59e0b', color: '#1f2937' };
                    }
                    if ((statusClass || '').indexOf('success') !== -1) {
                        return { background: '#16a34a', color: '#ffffff' };
                    }
                    if ((statusClass || '').indexOf('teal') !== -1) {
                        return { background: '#0f766e', color: '#ffffff' };
                    }
                    if ((statusClass || '').indexOf('orange') !== -1) {
                        return { background: '#ea580c', color: '#ffffff' };
                    }
                    if ((statusClass || '').indexOf('slate') !== -1) {
                        return { background: '#475569', color: '#ffffff' };
                    }
                    if ((statusClass || '').indexOf('rose') !== -1 || (statusClass || '').indexOf('danger') !== -1) {
                        return { background: '#e11d48', color: '#ffffff' };
                    }

                    return { background: '#334155', color: '#ffffff' };
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

    public function renderWooOrderPreviewTemplateForKiriofPage() {
        if ( ! $this->isTransactionProcessPage() ) {
            return;
        }

        if ( class_exists( '\Automattic\WooCommerce\Internal\Admin\Orders\ListTable' ) ) {
            $list_table = new \Automattic\WooCommerce\Internal\Admin\Orders\ListTable();
            echo $list_table->get_order_preview_template(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        if ( class_exists( '\WC_Admin_List_Table_Orders' ) ) {
            $legacy_list_table = new \WC_Admin_List_Table_Orders();
            $legacy_list_table->order_preview_template();
        }
    }

    public function renderWooActionModalTemplatesForKiriofPage() {
        if ( ! $this->isTransactionProcessPage() ) {
            return;
        }
        ?>
        <script type="text/template" id="tmpl-kiriof-modal-request-pickup">
            <div class="wc-backbone-modal kiriof-backbone-modal kiriof-request-pickup-modal">
                <div class="wc-backbone-modal-content" style="max-width:640px;width:calc(100vw - 48px);margin:5vh auto 0;">
                    <section class="wc-backbone-modal-main" role="main">
                        <header class="wc-backbone-modal-header">
                            <h1><?php esc_html_e( 'Schedule for Pickup', 'kiriminaja-official' ); ?></h1>
                            <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                                <span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'kiriminaja-official' ); ?></span>
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
                                    <p class="kiriof-backbone-modal-error-text"><?php esc_html_e( 'An error occurred.', 'kiriminaja-official' ); ?></p>
                                </div>

                                <div class="kiriof-modal-state kiriof-modal-state-content" style="display:none;">
                                    <div class="kiriof-backbone-summary">
                                        <div class="kiriof-backbone-summary-row">
                                            <span><?php esc_html_e( 'COD Package Charges', 'kiriminaja-official' ); ?></span>
                                            <strong class="kiriof-summary-cod">Rp0</strong>
                                        </div>
                                        <div class="kiriof-backbone-summary-row">
                                            <span><?php esc_html_e( 'Non-COD Package Charges', 'kiriminaja-official' ); ?></span>
                                            <strong class="kiriof-summary-non-cod">Rp0</strong>
                                        </div>
                                        <div class="kiriof-backbone-summary-row">
                                            <span><?php esc_html_e( 'Total Charges', 'kiriminaja-official' ); ?></span>
                                            <strong class="kiriof-summary-total">Rp0</strong>
                                        </div>
                                    </div>

                                    <div class="kiriof-backbone-section">
                                        <h2 class="kiriof-backbone-section-title"><?php esc_html_e( 'Available Schedules', 'kiriminaja-official' ); ?></h2>
                                        <div class="kiriof-schedule-opt-list"></div>
                                    </div>

                                    <p class="kiriof-backbone-inline-error err_msg" style="display:none;"></p>
                                </div>
                            </form>
                        </article>
                        <footer>
                            <div class="inner">
                                <button class="button button-large modal-close"><?php esc_html_e( 'Close', 'kiriminaja-official' ); ?></button>
                                <button class="button button-primary button-large" id="btn-next" disabled><?php esc_html_e( 'Pick Schedule', 'kiriminaja-official' ); ?></button>
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
                            <h1><?php esc_html_e( 'Cancel Shipment', 'kiriminaja-official' ); ?></h1>
                            <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                                <span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'kiriminaja-official' ); ?></span>
                            </button>
                        </header>
                        <article class="kiriof-backbone-modal-body">
                            <form>
                                <input type="hidden" name="order_id" value="{{ data.order_id }}">
                                <div class="kiriof-backbone-field">
                                    <label for="kiriof-cancel-reason" class="kiriof-backbone-label">
                                        <?php esc_html_e( 'Reason for Cancellation', 'kiriminaja-official' ); ?> <span class="required">*</span>
                                    </label>
                                    <textarea id="kiriof-cancel-reason" class="kiriof-cancel-reason" name="reason" rows="4" maxlength="200" placeholder="<?php echo esc_attr__( 'Enter reason (min 5, max 200 characters)', 'kiriminaja-official' ); ?>"></textarea>
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
                                <button class="button button-large modal-close"><?php esc_html_e( 'Close', 'kiriminaja-official' ); ?></button>
                                <button class="button button-primary button-large kiriof-button-danger" id="btn-next"><?php esc_html_e( 'Cancel Shipment', 'kiriminaja-official' ); ?></button>
                            </div>
                        </footer>
                    </section>
                </div>
            </div>
            <div class="wc-backbone-modal-backdrop modal-close"></div>
        </script>
        <?php
    }

    private function getWooOrderPreviewSummaryRowsHtml( \WC_Order $order, $transaction ) {
        $price_args = array(
            'currency' => $order->get_currency(),
        );
        $colspan    = wc_tax_enabled() ? 3 : 2;
        $rows       = array();

        $rows[] = $this->buildWooOrderPreviewSummaryRow(
            __( 'Sub Total', 'kiriminaja-official' ),
            wc_price( (float) ( $transaction->transaction_value ?? 0 ), $price_args ),
            $colspan
        );

        $rows[] = $this->buildWooOrderPreviewSummaryRow(
            __( 'Shipping Fee', 'kiriminaja-official' ),
            wc_price( (float) ( $transaction->shipping_cost ?? 0 ), $price_args ),
            $colspan
        );

        if ( (float) ( $transaction->cod_fee ?? 0 ) > 0 ) {
            $rows[] = $this->buildWooOrderPreviewSummaryRow(
                __( 'COD Fee', 'kiriminaja-official' ),
                wc_price( (float) $transaction->cod_fee, $price_args ),
                $colspan
            );
        }

        if ( (float) ( $transaction->insurance_cost ?? 0 ) > 0 ) {
            $rows[] = $this->buildWooOrderPreviewSummaryRow(
                __( 'Insurance Fee', 'kiriminaja-official' ),
                wc_price( (float) $transaction->insurance_cost, $price_args ),
                $colspan
            );
        }

        $total_amount = (float) ( $transaction->transaction_value ?? 0 )
            + (float) ( $transaction->shipping_cost ?? 0 )
            + (float) ( $transaction->cod_fee ?? 0 )
            + (float) ( $transaction->insurance_cost ?? 0 );

        $rows[] = $this->buildWooOrderPreviewSummaryRow(
            __( 'Total', 'kiriminaja-official' ),
            wc_price( $total_amount, $price_args ),
            $colspan,
            true
        );

        return implode( '', $rows );
    }

    private function buildWooOrderPreviewSummaryRow( $label, $amount_html, $colspan, $is_total = false ) {
        $label = esc_html( wp_strip_all_tags( (string) $label ) );
        $style = $is_total ? ' style="border-top:1px solid #dcdcde;"' : '';

        return sprintf(
            '<tr class="kiriof-order-preview-summary-row"%1$s><td colspan="%2$d" style="text-align:right;"><strong>%3$s</strong></td><td><strong>%4$s</strong></td></tr>',
            $style,
            (int) $colspan,
            $label,
            $amount_html
        );
    }

    private function isTransactionProcessPage() {
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page slug check, no data processed

        return 'kiriminaja-transaction-process' === $page;
    }
}
