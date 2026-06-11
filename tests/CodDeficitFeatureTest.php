<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Validates the COD Deficit handling feature implementation:
 *
 * p5-unit-deficit  — CodDeficitService structure
 * p5-unit-repo     — CodFeeApiRepository structure
 * p5-unit-ajax     — CodAdjustmentController structure & security
 * p5-feature-create — CreateTransactionService wires CodDeficitService
 * p5-feature-cancel — CodAdjustmentController cancel deficit flow
 * p5-feature-validation — ValidationCodCalculationService uses settings
 * p5-security       — AccessControlTest coverage for new AJAX endpoints
 */
final class CodDeficitFeatureTest extends TestCase
{
    // =========================================================================
    // p5-unit-deficit — CodDeficitService
    // =========================================================================

    #[Test]
    public function cod_deficit_service_file_exists(): void
    {
        $this->assertFileExists(
            PLUGIN_DIR . '/inc/Services/CheckoutServices/CodDeficitService.php',
            'CodDeficitService.php must exist'
        );
    }

    #[Test]
    public function cod_deficit_service_has_correct_namespace(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CodDeficitService.php');
        $this->assertStringContainsString(
            'namespace KiriminAjaOfficial\\Services\\CheckoutServices;',
            $content
        );
    }

    #[Test]
    public function cod_deficit_service_extends_base_service(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CodDeficitService.php');
        $this->assertStringContainsString('extends BaseService', $content);
    }

    #[Test]
    public function cod_deficit_service_has_detect_method(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CodDeficitService.php');
        $this->assertStringContainsString('public function detect(', $content);
    }

    #[Test]
    public function cod_deficit_service_has_api_detection(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CodDeficitService.php');
        $this->assertStringContainsString('private function detectViaApi(', $content);
    }

    #[Test]
    public function cod_deficit_service_has_fallback_detection(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CodDeficitService.php');
        $this->assertStringContainsString('private function detectFallback(', $content);
    }

    #[Test]
    public function cod_deficit_service_uses_two_api_calls(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CodDeficitService.php');
        // Call 1: custom_cod = 1 to get minimum_custom_cod
        $this->assertStringContainsString("'custom_cod'                    => 1,", $content);
        // Call 2: custom_cod = actual amount (floored)
        $this->assertStringContainsString('$customCodForFee', $content);
    }

    #[Test]
    public function cod_deficit_service_floors_custom_cod_at_100k(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CodDeficitService.php');
        $this->assertStringContainsString('max( (int) $totalCod, (int) $itemPrice, 100000 )', $content);
    }

    #[Test]
    public function cod_deficit_service_uses_kiriof_max_cod_amount_constant(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CodDeficitService.php');
        $this->assertStringContainsString('KIRIOF_MAX_COD_AMOUNT', $content);
    }

    #[Test]
    public function cod_deficit_service_passes_discount_amount_to_api(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CodDeficitService.php');
        $this->assertStringContainsString("'discount_amount'", $content);
    }

    // =========================================================================
    // p5-unit-repo — CodFeeApiRepository
    // =========================================================================

    #[Test]
    public function cod_fee_api_repository_file_exists(): void
    {
        $this->assertFileExists(
            PLUGIN_DIR . '/inc/Repositories/CodFeeApiRepository.php',
            'CodFeeApiRepository.php must exist'
        );
    }

    #[Test]
    public function cod_fee_api_repository_has_correct_namespace(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Repositories/CodFeeApiRepository.php');
        $this->assertStringContainsString(
            'namespace KiriminAjaOfficial\\Repositories;',
            $content
        );
    }

    #[Test]
    public function cod_fee_api_repository_extends_kiriminaja_api(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Repositories/CodFeeApiRepository.php');
        $this->assertStringContainsString('extends KiriminAjaApi', $content);
    }

    #[Test]
    public function cod_fee_api_repository_has_calculate_bulk_cod_method(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Repositories/CodFeeApiRepository.php');
        $this->assertStringContainsString('public function calculateBulkCod(', $content);
    }

    #[Test]
    public function cod_fee_api_repository_uses_correct_endpoint(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Repositories/CodFeeApiRepository.php');
        $this->assertStringContainsString('/api/mitra/calculations/cod', $content);
    }

    #[Test]
    public function cod_fee_api_repository_reads_results_key_from_response(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Repositories/CodFeeApiRepository.php');
        // Response shape: $response['data']->results (not ->data->results)
        $this->assertStringContainsString('$response[\'data\']->results', $content);
    }

    #[Test]
    public function cod_fee_api_repository_does_not_send_member_id(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Repositories/CodFeeApiRepository.php');
        $this->assertStringNotContainsString("'member_id'", $content);
    }

    #[Test]
    public function cod_fee_api_repository_sends_couriers_array(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Repositories/CodFeeApiRepository.php');
        $this->assertStringContainsString("'data'", $content);
        $this->assertStringContainsString('$couriers', $content);
    }

    // =========================================================================
    // p5-unit-ajax — CodAdjustmentController
    // =========================================================================

    #[Test]
    public function cod_adjustment_controller_file_exists(): void
    {
        $this->assertFileExists(
            PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php',
            'CodAdjustmentController.php must exist'
        );
    }

    #[Test]
    public function cod_adjustment_controller_has_correct_namespace(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php');
        $this->assertStringContainsString(
            'namespace KiriminAjaOfficial\\Controllers;',
            $content
        );
    }

    #[Test]
    public function cod_adjustment_controller_registers_ajax_actions(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php');
        $this->assertStringContainsString('wp_ajax_kiriof_cod_adjust', $content);
        $this->assertStringContainsString('wp_ajax_kiriof_cancel_deficit', $content);
    }

    #[Test]
    public function cod_adjustment_controller_handle_adjust_checks_capability(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php');
        $this->assertStringContainsString("current_user_can( 'manage_woocommerce' )", $content);
    }

    #[Test]
    public function cod_adjustment_controller_handle_adjust_verifies_nonce(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php');
        $this->assertStringContainsString('wp_verify_nonce', $content);
        $this->assertStringContainsString('KIRIOF_NONCE', $content);
    }

    #[Test]
    public function cod_adjustment_controller_checks_is_deficit_flag(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php');
        $this->assertStringContainsString('is_deficit', $content);
    }

    #[Test]
    public function cod_adjustment_controller_recalculates_via_api(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php');
        $this->assertStringContainsString('CodFeeApiRepository', $content);
        $this->assertStringContainsString('calculateBulkCod', $content);
    }

    #[Test]
    public function cod_adjustment_controller_updates_wc_order(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php');
        $this->assertStringContainsString('wc_get_order', $content);
        $this->assertStringContainsString('calculate_totals', $content);
    }

    #[Test]
    public function cod_adjustment_controller_is_registered_in_init(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Init.php');
        $this->assertStringContainsString('CodAdjustmentController', $content);
    }

    // =========================================================================
    // p5-feature-create — CreateTransactionService wires CodDeficitService
    // =========================================================================

    #[Test]
    public function create_transaction_service_calls_cod_deficit_detect(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CreateTransactionService.php');
        $this->assertStringContainsString('CodDeficitService', $content);
        $this->assertStringContainsString('->detect(', $content);
    }

    #[Test]
    public function create_transaction_service_stores_is_deficit(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CreateTransactionService.php');
        $this->assertStringContainsString("'is_deficit'", $content);
    }

    #[Test]
    public function create_transaction_service_stores_cod_minimum(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CreateTransactionService.php');
        $this->assertStringContainsString("'cod_minimum'", $content);
    }

    #[Test]
    public function create_transaction_service_passes_discount_amount_to_deficit_detect(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CreateTransactionService.php');
        $this->assertStringContainsString("'discount_amount'", $content);
    }

    // =========================================================================
    // p5-feature-cancel — Cancel deficit flow in CodAdjustmentController
    // =========================================================================

    #[Test]
    public function cancel_deficit_handler_requires_awb(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php');
        $this->assertStringContainsString('AWB not found', $content);
    }

    #[Test]
    public function cancel_deficit_handler_calls_cancel_shipment_api(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php');
        $this->assertStringContainsString('cancelShipment(', $content);
    }

    #[Test]
    public function cancel_deficit_handler_updates_wc_order_to_cancelled(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php');
        $this->assertStringContainsString("update_status( 'cancelled' )", $content);
    }

    #[Test]
    public function cancel_deficit_handler_clears_deficit_flag_in_db(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php');
        $this->assertStringContainsString("'is_deficit' => 0", $content);
    }

    // =========================================================================
    // p5-feature-validation — ValidationCodCalculationService uses settings
    // =========================================================================

    #[Test]
    public function validation_cod_service_file_exists(): void
    {
        $this->assertFileExists(
            PLUGIN_DIR . '/inc/Services/CheckoutServices/ValidationCodCalculationService.php'
        );
    }

    #[Test]
    public function validation_cod_service_uses_setting_repository_for_min(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/ValidationCodCalculationService.php');
        $this->assertStringContainsString('SettingRepository', $content);
        $this->assertStringContainsString('min_cod_threshold', $content);
    }

    #[Test]
    public function validation_cod_service_uses_kiriof_max_cod_constant_as_default(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/ValidationCodCalculationService.php');
        $this->assertStringContainsString('KIRIOF_MAX_COD_AMOUNT', $content);
    }

    #[Test]
    public function validation_cod_service_validates_only_for_kiriminaja_shipping(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/ValidationCodCalculationService.php');
        $this->assertStringContainsString('kiriminaja-official', $content);
    }

    #[Test]
    public function validation_cod_service_validates_only_for_cod_payment(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/ValidationCodCalculationService.php');
        $this->assertStringContainsString("'cod'", $content);
    }

    // =========================================================================
    // p5-security — AJAX endpoints have capability + nonce checks
    // =========================================================================

    #[Test]
    public function cod_adjustment_ajax_handlers_check_capability_before_logic(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php');
        $handlers = ['handleAdjust', 'handleCancelDeficit'];

        foreach ($handlers as $handler) {
            // Find the method body
            $start = strpos($content, "public function {$handler}()");
            $this->assertNotFalse($start, "Method {$handler} not found");

            $slice = substr($content, $start, 400);
            $this->assertStringContainsString(
                'manage_woocommerce',
                $slice,
                "{$handler}() must check manage_woocommerce capability near top of method"
            );
        }
    }

    #[Test]
    public function cod_adjustment_ajax_handlers_verify_nonce_before_logic(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php');
        $handlers = ['handleAdjust', 'handleCancelDeficit'];

        foreach ($handlers as $handler) {
            $start = strpos($content, "public function {$handler}()");
            $this->assertNotFalse($start, "Method {$handler} not found");

            $slice = substr($content, $start, 600);
            $this->assertStringContainsString(
                'wp_verify_nonce',
                $slice,
                "{$handler}() must verify nonce near top of method"
            );
        }
    }

    #[Test]
    public function kiriof_max_cod_amount_constant_is_defined(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/kiriminaja.php');
        $this->assertStringContainsString("define( 'KIRIOF_MAX_COD_AMOUNT'", $content);
    }

    // =========================================================================
    // is_top / merchant type — SettingService + SettingRepository + Migration
    // =========================================================================

    #[Test]
    public function setting_service_resolves_is_top_from_profile_api(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/SettingService.php');
        $this->assertStringContainsString('resolveIsTop', $content);
        $this->assertStringContainsString('getProfile', $content);
        $this->assertStringContainsString("'TOP'", $content);
    }

    #[Test]
    public function setting_repository_stores_is_top_on_integration(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Repositories/SettingRepository.php');
        $this->assertStringContainsString("'is_top'", $content);
    }

    #[Test]
    public function migration_ensures_is_top_row_exists(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Migration/SetupMigration.php');
        $this->assertStringContainsString("'is_top'", $content);
    }

    #[Test]
    public function shipping_discount_coupon_service_gates_top_merchants(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/ShippingDiscountCouponService.php');
        $this->assertStringContainsString('isMerchantTop()', $content);
        $this->assertStringContainsString('private function isMerchantTop()', $content);
    }

    #[Test]
    public function shipping_discount_coupon_service_blocks_free_shipping_stacking(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/ShippingDiscountCouponService.php');
        $this->assertStringContainsString('cannot be combined with a free shipping coupon', $content);
    }
}
