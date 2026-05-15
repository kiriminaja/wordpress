<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Validates the cancel transaction feature implementation:
 * - CancelTransactionService exists with proper structure
 * - KiriminajaApiRepository has cancelShipment method
 * - TransactionProcessController has cancel AJAX handler with security
 * - TransactionProcessController hooks into woocommerce_order_status_cancelled
 * - Cancel modal template exists with required elements
 * - Transaction list template includes cancel button and modal
 */
final class CancelTransactionFeatureTest extends TestCase
{
    // ------------------------------------------------------------------
    // Service layer
    // ------------------------------------------------------------------

    #[Test]
    public function cancel_transaction_service_file_exists(): void
    {
        $this->assertFileExists(
            PLUGIN_DIR . '/inc/Services/TransactionProcessServices/CancelTransactionService.php'
        );
    }

    #[Test]
    public function cancel_transaction_service_has_correct_namespace(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Services/TransactionProcessServices/CancelTransactionService.php'
        );

        $this->assertStringContainsString(
            'namespace KiriminAjaOfficial\\Services\\TransactionProcessServices;',
            $content,
            'CancelTransactionService must use correct namespace'
        );
    }

    #[Test]
    public function cancel_transaction_service_extends_base_service(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Services/TransactionProcessServices/CancelTransactionService.php'
        );

        $this->assertStringContainsString(
            'extends BaseService',
            $content,
            'CancelTransactionService must extend BaseService'
        );
    }

    #[Test]
    public function cancel_transaction_service_has_required_methods(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Services/TransactionProcessServices/CancelTransactionService.php'
        );

        $requiredMethods = ['orderId', 'reason', 'call'];
        foreach ($requiredMethods as $method) {
            $this->assertMatchesRegularExpression(
                '/public\s+function\s+' . preg_quote($method, '/') . '\s*\(/',
                $content,
                "CancelTransactionService must have {$method}() method"
            );
        }
    }

    #[Test]
    public function cancel_transaction_service_validates_reason_length(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Services/TransactionProcessServices/CancelTransactionService.php'
        );

        $this->assertStringContainsString(
            'mb_strlen( $this->reason ) < 5',
            $content,
            'CancelTransactionService must validate minimum reason length (5 chars)'
        );

        $this->assertStringContainsString(
            'mb_strlen( $this->reason ) > 200',
            $content,
            'CancelTransactionService must validate maximum reason length (200 chars)'
        );
    }

    #[Test]
    public function cancel_transaction_service_checks_non_cancelable_statuses(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Services/TransactionProcessServices/CancelTransactionService.php'
        );

        $nonCancelable = ['shipped', 'finished', 'returned', 'return', 'canceled'];
        foreach ($nonCancelable as $status) {
            $this->assertStringContainsString(
                "'" . $status . "'",
                $content,
                "CancelTransactionService must block cancellation for status: {$status}"
            );
        }
    }

    #[Test]
    public function cancel_transaction_service_has_abspath_check(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Services/TransactionProcessServices/CancelTransactionService.php'
        );

        $this->assertTrue(
            str_contains($content, "defined( 'ABSPATH' )") || str_contains($content, "defined('ABSPATH')"),
            'CancelTransactionService must have ABSPATH check'
        );
    }

    #[Test]
    public function cancel_transaction_service_requires_awb(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Services/TransactionProcessServices/CancelTransactionService.php'
        );

        $this->assertStringContainsString(
            'empty( $transaction->awb )',
            $content,
            'CancelTransactionService must require AWB to be present'
        );
    }

    #[Test]
    public function cancel_transaction_service_calls_cancel_shipment_api(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Services/TransactionProcessServices/CancelTransactionService.php'
        );

        $this->assertStringContainsString(
            'cancelShipment',
            $content,
            'CancelTransactionService must call cancelShipment on the API repository'
        );
    }

    #[Test]
    public function cancel_transaction_service_updates_transaction_status(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Services/TransactionProcessServices/CancelTransactionService.php'
        );

        $this->assertStringContainsString(
            "'status'      => 'canceled'",
            $content,
            'CancelTransactionService must update transaction status to canceled'
        );
    }

    // ------------------------------------------------------------------
    // API Repository
    // ------------------------------------------------------------------

    #[Test]
    public function api_repository_has_cancel_shipment_method(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Repositories/KiriminajaApiRepository.php'
        );

        $this->assertMatchesRegularExpression(
            '/public\s+function\s+cancelShipment\s*\(/',
            $content,
            'KiriminajaApiRepository must have cancelShipment() method'
        );
    }

    #[Test]
    public function api_repository_cancel_uses_correct_endpoint(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Repositories/KiriminajaApiRepository.php'
        );

        $this->assertStringContainsString(
            '/api/mitra/v3/cancel_shipment',
            $content,
            'cancelShipment must use the /api/mitra/v3/cancel_shipment endpoint'
        );
    }

    #[Test]
    public function api_repository_cancel_sends_awb_and_reason(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Repositories/KiriminajaApiRepository.php'
        );

        // Extract the cancelShipment method body
        preg_match('/function\s+cancelShipment\s*\([^)]*\)\s*\{(.*?)\}/s', $content, $matches);
        $this->assertNotEmpty($matches, 'cancelShipment method body not found');

        $methodBody = $matches[1];
        $this->assertStringContainsString("'awb'", $methodBody, 'cancelShipment must send awb parameter');
        $this->assertStringContainsString("'reason'", $methodBody, 'cancelShipment must send reason parameter');
    }

    // ------------------------------------------------------------------
    // Controller — AJAX handler
    // ------------------------------------------------------------------

    #[Test]
    public function controller_registers_cancel_ajax_action(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php'
        );

        $this->assertStringContainsString(
            "wp_ajax_kiriof_cancel_transaction",
            $content,
            'Controller must register kiriof_cancel_transaction AJAX action'
        );
    }

    #[Test]
    public function cancel_ajax_handler_checks_capability(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php'
        );

        preg_match('/function\s+cancelTransaction\s*\(/s', $content, $_, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($_, 'cancelTransaction() method not found');

        $methodBody = substr($content, (int) $_[0][1], 2500);
        $this->assertStringContainsString(
            "current_user_can( 'manage_woocommerce' )",
            $methodBody,
            'cancelTransaction() must check manage_woocommerce capability'
        );
    }

    #[Test]
    public function cancel_ajax_handler_verifies_nonce(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php'
        );

        preg_match('/function\s+cancelTransaction\s*\(/s', $content, $_, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($_, 'cancelTransaction() method not found');

        $methodBody = substr($content, (int) $_[0][1], 2500);
        $this->assertStringContainsString(
            'wp_verify_nonce',
            $methodBody,
            'cancelTransaction() must verify nonce'
        );
    }

    #[Test]
    public function cancel_ajax_handler_sanitizes_inputs(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php'
        );

        preg_match('/function\s+cancelTransaction\s*\(/s', $content, $_, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($_, 'cancelTransaction() method not found');

        $methodBody = substr($content, (int) $_[0][1], 2500);
        $this->assertStringContainsString(
            'sanitize_text_field',
            $methodBody,
            'cancelTransaction() must sanitize order_id with sanitize_text_field'
        );
        $this->assertStringContainsString(
            'sanitize_textarea_field',
            $methodBody,
            'cancelTransaction() must sanitize reason with sanitize_textarea_field'
        );
    }

    // ------------------------------------------------------------------
    // Controller — WC order status hook
    // ------------------------------------------------------------------

    #[Test]
    public function controller_hooks_into_wc_order_status_cancelled(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php'
        );

        $this->assertStringContainsString(
            'woocommerce_order_status_cancelled',
            $content,
            'Controller must hook into woocommerce_order_status_cancelled'
        );
    }

    #[Test]
    public function handle_wc_order_cancelled_method_exists(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php'
        );

        $this->assertMatchesRegularExpression(
            '/public\s+function\s+handleWcOrderCancelled\s*\(/',
            $content,
            'Controller must have handleWcOrderCancelled() method'
        );
    }

    #[Test]
    public function handle_wc_order_cancelled_uses_cancel_service(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php'
        );

        preg_match('/function\s+handleWcOrderCancelled\s*\(/s', $content, $_, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($_, 'handleWcOrderCancelled() method not found');

        $methodBody = substr($content, (int) $_[0][1], 2500);
        $this->assertStringContainsString(
            'CancelTransactionService',
            $methodBody,
            'handleWcOrderCancelled() must use CancelTransactionService'
        );
    }

    #[Test]
    public function handle_wc_order_cancelled_provides_default_reason(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php'
        );

        preg_match('/function\s+handleWcOrderCancelled\s*\(/s', $content, $_, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($_, 'handleWcOrderCancelled() method not found');

        $methodBody = substr($content, (int) $_[0][1], 2500);
        $this->assertStringContainsString(
            'Pesanan dibatalkan dari WooCommerce',
            $methodBody,
            'handleWcOrderCancelled() must provide a default cancellation reason'
        );
    }

    #[Test]
    public function handle_wc_order_cancelled_skips_terminal_statuses(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php'
        );

        preg_match('/function\s+handleWcOrderCancelled\s*\(/s', $content, $_, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($_, 'handleWcOrderCancelled() method not found');

        $methodBody = substr($content, (int) $_[0][1], 2500);
        $this->assertStringContainsString(
            'terminalStatuses',
            $methodBody,
            'handleWcOrderCancelled() must check for terminal statuses to prevent race conditions'
        );
    }

    #[Test]
    public function handle_wc_order_cancelled_skips_when_no_awb(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php'
        );

        preg_match('/function\s+handleWcOrderCancelled\s*\(/s', $content, $_, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($_, 'handleWcOrderCancelled() method not found');

        $methodBody = substr($content, (int) $_[0][1], 2500);
        $this->assertStringContainsString(
            'empty( $transaction->awb )',
            $methodBody,
            'handleWcOrderCancelled() must skip when no AWB exists'
        );
    }

    // ------------------------------------------------------------------
    // Webhook callback race condition guard
    // ------------------------------------------------------------------

    #[Test]
    public function webhook_canceled_packages_unhooks_wc_listener(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Services/CallbackHandlerService.php'
        );

        preg_match('/function\s+canceledPackages\s*\(/s', $content, $_, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($_, 'canceledPackages() method not found');

        $methodBody = substr($content, (int) $_[0][1], 3000);
        $this->assertStringContainsString(
            'remove_action',
            $methodBody,
            'canceledPackages() must remove the WC order status hook to prevent a loop back to the Mitra API'
        );
    }

    #[Test]
    public function webhook_canceled_packages_checks_order_status_before_update(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/inc/Services/CallbackHandlerService.php'
        );

        preg_match('/function\s+canceledPackages\s*\(/s', $content, $_, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($_, 'canceledPackages() method not found');

        $methodBody = substr($content, (int) $_[0][1], 3000);
        $this->assertStringContainsString(
            "get_status() !== 'cancelled'",
            $methodBody,
            'canceledPackages() must check WC order status before updating to avoid redundant writes'
        );
    }

    // ------------------------------------------------------------------
    // Cancel modal template
    // ------------------------------------------------------------------

    #[Test]
    public function cancel_modal_template_exists(): void
    {
        $this->assertFileExists(
            PLUGIN_DIR . '/templates/transaction-process/view/modal-cancel.php'
        );
    }

    #[Test]
    public function cancel_modal_has_abspath_check(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/templates/transaction-process/view/modal-cancel.php'
        );

        $this->assertTrue(
            str_contains($content, "defined( 'ABSPATH' )") || str_contains($content, "defined('ABSPATH')"),
            'Cancel modal template must have ABSPATH check'
        );
    }

    #[Test]
    public function cancel_modal_has_reason_input(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/templates/transaction-process/view/modal-cancel.php'
        );

        $this->assertStringContainsString(
            'cancel-reason',
            $content,
            'Cancel modal must have a reason textarea (id="cancel-reason")'
        );
    }

    #[Test]
    public function cancel_modal_has_order_id_hidden_field(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/templates/transaction-process/view/modal-cancel.php'
        );

        $this->assertStringContainsString(
            'cancel-order-id',
            $content,
            'Cancel modal must have a hidden order ID field'
        );
    }

    #[Test]
    public function cancel_modal_has_character_counter(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/templates/transaction-process/view/modal-cancel.php'
        );

        $this->assertStringContainsString(
            'cancel-reason-count',
            $content,
            'Cancel modal must have a character counter element'
        );
    }

    #[Test]
    public function cancel_modal_has_submit_button(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/templates/transaction-process/view/modal-cancel.php'
        );

        $this->assertStringContainsString(
            'Cancel Shipment',
            $content,
            'Cancel modal must have a submit button labelled Cancel Shipment'
        );
    }

    // ------------------------------------------------------------------
    // Transaction list template integration
    // ------------------------------------------------------------------

    #[Test]
    public function transaction_list_includes_cancel_modal(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/templates/transaction-process/view/index.php'
        );

        $this->assertStringContainsString(
            "include 'modal-cancel.php'",
            $content,
            'Transaction list must include the cancel modal template'
        );
    }

    #[Test]
    public function transaction_list_has_cancel_button(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/templates/transaction-process/view/index.php'
        );

        $this->assertStringContainsString(
            'kjShowCancelModal',
            $content,
            'Transaction list must have cancel buttons calling kjShowCancelModal()'
        );
    }

    #[Test]
    public function transaction_list_has_actions_column(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/templates/transaction-process/view/index.php'
        );

        $this->assertStringContainsString(
            'Actions',
            $content,
            'Transaction list table must have an Actions column header'
        );
    }

    #[Test]
    public function transaction_list_js_has_cancel_modal_function(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/templates/transaction-process/view/index.php'
        );

        $this->assertStringContainsString(
            'window.kjShowCancelModal',
            $content,
            'Transaction list JS must define kjShowCancelModal function'
        );
    }

    #[Test]
    public function transaction_list_js_has_cancel_process_function(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/templates/transaction-process/view/index.php'
        );

        $this->assertStringContainsString(
            'window.kjCancelTransactionProcess',
            $content,
            'Transaction list JS must define kjCancelTransactionProcess function'
        );
    }

    #[Test]
    public function cancel_js_sends_correct_ajax_action(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/templates/transaction-process/view/index.php'
        );

        $this->assertStringContainsString(
            'action: "kiriof_cancel_transaction"',
            $content,
            'Cancel JS must send the kiriof_cancel_transaction AJAX action'
        );
    }

    #[Test]
    public function cancel_js_sends_nonce(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/templates/transaction-process/view/index.php'
        );

        // Look specifically in the cancel AJAX section
        preg_match('/kjCancelTransactionProcess.*?ajax\s*\(\s*\{(.*?)\}\s*\)/s', $content, $matches);
        $this->assertNotEmpty($matches, 'kjCancelTransactionProcess AJAX call not found');

        $this->assertStringContainsString(
            'nonce: kiriofAjax.nonce',
            $matches[1],
            'Cancel AJAX call must send the nonce'
        );
    }

    #[Test]
    public function cancel_js_validates_reason_min_length(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/templates/transaction-process/view/index.php'
        );

        // Look in the kjCancelTransactionProcess function
        preg_match('/window\.kjCancelTransactionProcess\s*=\s*function.*?\n\s*\};/s', $content, $matches);
        $this->assertNotEmpty($matches, 'kjCancelTransactionProcess function not found');

        $this->assertStringContainsString(
            'reason.length < 5',
            $matches[0],
            'Cancel JS must validate minimum reason length'
        );
    }

    #[Test]
    public function cancel_js_has_confirmation_prompt(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/templates/transaction-process/view/index.php'
        );

        preg_match('/window\.kjCancelTransactionProcess\s*=\s*function.*?\n\s*\};/s', $content, $matches);
        $this->assertNotEmpty($matches, 'kjCancelTransactionProcess function not found');

        $this->assertStringContainsString(
            'confirm(',
            $matches[0],
            'Cancel JS must show a confirmation prompt before proceeding'
        );
    }

    #[Test]
    public function cancel_button_only_shown_when_awb_exists(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/templates/transaction-process/view/index.php'
        );

        $this->assertStringContainsString(
            '! empty( $kiriof_row->awb )',
            $content,
            'Cancel button must only show when AWB exists'
        );
    }

    #[Test]
    public function cancel_button_only_shown_for_cancelable_statuses(): void
    {
        $content = file_get_contents(
            PLUGIN_DIR . '/templates/transaction-process/view/index.php'
        );

        // The cancel button should be behind a status check
        $nonCancelable = ['shipped', 'finished', 'returned', 'return', 'canceled'];
        foreach ($nonCancelable as $status) {
            $this->assertStringContainsString(
                "'" . $status . "'",
                $content,
                "Transaction list must check for non-cancelable status: {$status}"
            );
        }
    }
}
