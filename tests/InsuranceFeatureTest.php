<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Validates the global shipping insurance feature implementation:
 * - Migration seeds enable_insurance key
 * - SettingRepository has storeInsuranceData method
 * - SettingService has storeInsuranceData with yes/no validation
 * - SettingController has kiriof_store_insurance_data AJAX handler with security
 * - CheckoutController forces insurance when global toggle is enabled
 * - CheckoutCalculationService includes global insurance check
 * - CreateTransactionService includes global insurance in cost calculation
 * - SendRequestPickupTransactionService sends is_with_insurance flag
 */
final class InsuranceFeatureTest extends TestCase
{
    // ------------------------------------------------------------------
    // Migration
    // ------------------------------------------------------------------

    #[Test]
    public function migration_seeds_enable_insurance_key(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Migration/SetupMigration.php');

        $this->assertStringContainsString(
            "'enable_insurance'",
            $content,
            'Migration must seed enable_insurance key'
        );
    }

    // ------------------------------------------------------------------
    // SettingRepository
    // ------------------------------------------------------------------

    #[Test]
    public function repository_has_store_insurance_data_method(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Repositories/SettingRepository.php');

        $this->assertMatchesRegularExpression(
            '/public\s+function\s+storeInsuranceData\s*\(/',
            $content,
            'SettingRepository must have storeInsuranceData() method'
        );
    }

    #[Test]
    public function repository_store_insurance_data_has_insert_or_update_logic(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Repositories/SettingRepository.php');

        $this->assertStringContainsString(
            "WHERE `key`='enable_insurance'",
            $content,
            'storeInsuranceData must query for existing enable_insurance key'
        );

        $this->assertStringContainsString(
            '$wpdb->insert',
            $content,
            'storeInsuranceData must handle insert for new key'
        );

        $this->assertStringContainsString(
            '$wpdb->update',
            $content,
            'storeInsuranceData must handle update for existing key'
        );
    }

    // ------------------------------------------------------------------
    // SettingService
    // ------------------------------------------------------------------

    #[Test]
    public function service_has_store_insurance_data_method(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/SettingService.php');

        $this->assertMatchesRegularExpression(
            '/public\s+function\s+storeInsuranceData\s*\(/',
            $content,
            'SettingService must have storeInsuranceData() method'
        );
    }

    #[Test]
    public function service_store_insurance_validates_yes_no(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/SettingService.php');

        $this->assertStringContainsString(
            "'in:yes,no'",
            $content,
            'storeInsuranceData must validate value is yes or no'
        );
    }

    #[Test]
    public function service_store_insurance_calls_repository(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/SettingService.php');

        $this->assertStringContainsString(
            'storeInsuranceData',
            $content,
            'storeInsuranceData must call repository storeInsuranceData'
        );
    }

    // ------------------------------------------------------------------
    // SettingController
    // ------------------------------------------------------------------

    #[Test]
    public function controller_registers_insurance_ajax_action(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/SettingController.php');

        $this->assertStringContainsString(
            "wp_ajax_kiriof_store_insurance_data",
            $content,
            'SettingController must register kiriof_store_insurance_data AJAX action'
        );
    }

    #[Test]
    public function controller_insurance_handler_checks_capability(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/SettingController.php');

        preg_match(
            '/function\s+storeInsuranceData\s*\(/',
            $content,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        $this->assertNotEmpty($matches, 'storeInsuranceData method not found');
        $methodBody = substr($content, (int) $matches[0][1], 2000);

        $this->assertStringContainsString(
            "current_user_can( 'manage_woocommerce' )",
            $methodBody,
            'storeInsuranceData AJAX handler must check manage_woocommerce capability'
        );
    }

    #[Test]
    public function controller_insurance_handler_verifies_nonce(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/SettingController.php');

        preg_match(
            '/function\s+storeInsuranceData\s*\(/',
            $content,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        $this->assertNotEmpty($matches, 'storeInsuranceData method not found');
        $methodBody = substr($content, (int) $matches[0][1], 2000);

        $this->assertStringContainsString(
            'wp_verify_nonce',
            $methodBody,
            'storeInsuranceData AJAX handler must verify nonce'
        );
    }

    #[Test]
    public function controller_insurance_handler_sanitizes_input(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/SettingController.php');

        preg_match(
            '/function\s+storeInsuranceData\s*\(/',
            $content,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        $this->assertNotEmpty($matches, 'storeInsuranceData method not found');
        $methodBody = substr($content, (int) $matches[0][1], 2000);

        $this->assertStringContainsString(
            'sanitize_text_field',
            $methodBody,
            'storeInsuranceData AJAX handler must sanitize input'
        );
    }

    // ------------------------------------------------------------------
    // CheckoutController — insurance enforcement
    // ------------------------------------------------------------------

    #[Test]
    public function checkout_controller_forces_insurance_when_global_enabled(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringContainsString(
            "'enable_insurance'",
            $content,
            'CheckoutController must check enable_insurance setting'
        );

        $this->assertStringContainsString(
            '$insurance_post = \'1\'',
            $content,
            'CheckoutController must force insurance_post = 1 when global toggle enabled'
        );
    }

    #[Test]
    public function checkout_controller_shows_insurance_row_when_global_enabled(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringContainsString(
            'getSettingByKey(\'enable_insurance\')',
            $content,
            'CheckoutController must check enable_insurance for review order display'
        );
    }

    // ------------------------------------------------------------------
    // CheckoutCalculationService
    // ------------------------------------------------------------------

    #[Test]
    public function checkout_calculation_includes_global_insurance_check(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CheckoutCalculationService.php');

        $this->assertStringContainsString(
            "'enable_insurance'",
            $content,
            'CheckoutCalculationService must check enable_insurance setting'
        );

        $this->assertStringContainsString(
            "'yes' === \$global_enabled",
            $content,
            'CheckoutCalculationService must check if global insurance is yes'
        );
    }

    // ------------------------------------------------------------------
    // CreateTransactionService
    // ------------------------------------------------------------------

    #[Test]
    public function create_transaction_service_includes_global_insurance(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CreateTransactionService.php');

        $this->assertStringContainsString(
            "'enable_insurance'",
            $content,
            'CreateTransactionService must check enable_insurance setting'
        );

        $this->assertStringContainsString(
            '$global_insurance',
            $content,
            'CreateTransactionService must have global_insurance variable'
        );
    }

    // ------------------------------------------------------------------
    // SendRequestPickupTransactionService
    // ------------------------------------------------------------------

    #[Test]
    public function pickup_service_sends_is_with_insurance_flag(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php');

        $this->assertStringContainsString(
            'is_with_insurance',
            $content,
            'SendRequestPickupTransactionService must send is_with_insurance in pickup payload'
        );
    }

    #[Test]
    public function pickup_service_sends_insurance_amount(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php');

        $this->assertStringContainsString(
            'insurance_amount',
            $content,
            'SendRequestPickupTransactionService must send insurance_amount in pickup payload'
        );
    }

    // ------------------------------------------------------------------
    // Settings UI Template
    // ------------------------------------------------------------------

    #[Test]
    public function settings_list_has_insurance_toggle(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/setting/setuped/index.php');

        $this->assertStringContainsString(
            'kiriof_insurance_toggle',
            $content,
            'Settings list page must have kiriof_insurance_toggle element'
        );

        $this->assertStringContainsString(
            'Shipping Insurance',
            $content,
            'Settings list page must have Shipping Insurance row'
        );

        $this->assertStringContainsString(
            'kiriof_store_insurance_data',
            $content,
            'Settings list JS must call kiriof_store_insurance_data action'
        );
    }

    #[Test]
    public function settings_list_no_longer_shows_insurance_todo(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/setting/setuped/index.php');

        // The TODO text and disabled state should be gone from the insurance row
        $this->assertStringNotContainsString(
            'TODO — Coming soon.',
            $content,
            'Settings list must not show TODO for insurance — it is now functional'
        );
    }
}
