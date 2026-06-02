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

    #[Test]
    public function settings_list_checks_woocommerce_shipping_locations(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/setting/setuped/index.php');

        $this->assertStringContainsString(
            'Shipping Locations',
            $content,
            'Settings wizard must surface WooCommerce shipping location configuration'
        );

        $this->assertStringContainsString(
            "get_option( 'woocommerce_ship_to_countries'",
            $content,
            'Settings wizard must inspect WooCommerce Shipping location(s)'
        );

        $this->assertStringContainsString(
            'get_shipping_countries()',
            $content,
            'Settings wizard must detect empty shipping countries when Ship to specific countries has no selection'
        );

        $this->assertStringContainsString(
            'admin.php?page=wc-settings',
            $content,
            'Settings wizard row must link merchants to WooCommerce general settings'
        );
    }

    #[Test]
    public function settings_list_checks_product_volumetric_configuration_progress(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/setting/setuped/index.php');

        $this->assertStringContainsString(
            'Product Volumetric Configurations',
            $content,
            'Settings wizard must surface product volumetric configuration progress'
        );

        $this->assertStringContainsString(
            "child_variation.post_parent = p.ID",
            $content,
            'Settings wizard must detect variable products with published variations'
        );

        $this->assertStringContainsString(
            "p.post_type = 'product_variation' AND p.post_status IN ('publish','private')",
            $content,
            'Settings wizard must count WooCommerce variation rows, which are commonly stored as private, as volumetric-required items'
        );

        $this->assertStringContainsString(
            "p.post_type = 'product' AND p.post_status = 'publish' AND child_variation.ID IS NULL",
            $content,
            'Settings wizard must count simple products but exclude variable parents that have variations'
        );

        $this->assertStringContainsString(
            "meta_key = '_weight'",
            $content,
            'Settings wizard must require product weight'
        );

        $this->assertStringContainsString(
            "meta_key = '_length'",
            $content,
            'Settings wizard must require product length'
        );

        $this->assertStringContainsString(
            "meta_key = '_width'",
            $content,
            'Settings wizard must require product width'
        );

        $this->assertStringContainsString(
            "meta_key = '_height'",
            $content,
            'Settings wizard must require product height'
        );

        $this->assertStringContainsString(
            'All Product Configured',
            $content,
            'Settings wizard must show the completed state when all products are configured'
        );
    }

    #[Test]
    public function setup_notice_includes_woocommerce_shipping_locations_step(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Pages/Admin.php');
        $methodStart = strpos($content, 'public function kiriof_setup_checklist_notice()');
        $this->assertNotFalse($methodStart, 'Setup checklist notice method must exist');
        $methodBody = substr($content, $methodStart, 13000);

        $this->assertStringContainsString(
            "get_option( 'woocommerce_ship_to_countries'",
            $methodBody,
            'Setup notice must inspect WooCommerce Shipping location(s)'
        );

        $this->assertStringContainsString(
            'get_shipping_countries()',
            $methodBody,
            'Setup notice must detect when WooCommerce has no shipping countries available'
        );

        $this->assertStringContainsString(
            'Shipping Locations',
            $methodBody,
            'Setup notice must include WooCommerce Shipping Locations in the checklist'
        );

        $this->assertStringContainsString(
            "'shipping_locations' => admin_url( 'admin.php?page=wc-settings' )",
            $methodBody,
            'WooCommerce Shipping Locations checklist item must link to WooCommerce general settings'
        );

        $this->assertStringContainsString(
            '<a href="<?php echo esc_url( $step[\'url\'] ); ?>"',
            $methodBody,
            'Setup checklist labels must be clickable so merchants know where to finish each step'
        );
    }

    #[Test]
    public function setup_notice_includes_product_volumetric_progress_step(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Pages/Admin.php');
        $methodStart = strpos($content, 'public function kiriof_setup_checklist_notice()');
        $this->assertNotFalse($methodStart, 'Setup checklist notice method must exist');
        $methodBody = substr($content, $methodStart, 9000);

        $this->assertStringContainsString(
            "child_variation.post_parent = p.ID",
            $methodBody,
            'Setup notice must detect variable products with published variations'
        );

        $this->assertStringContainsString(
            "p.post_type = 'product_variation' AND p.post_status IN ('publish','private')",
            $methodBody,
            'Setup notice must count WooCommerce variation rows, which are commonly stored as private, as volumetric-required items'
        );

        $this->assertStringContainsString(
            "p.post_type = 'product' AND p.post_status = 'publish' AND child_variation.ID IS NULL",
            $methodBody,
            'Setup notice must count simple products but exclude variable parents that have variations'
        );

        $this->assertStringContainsString(
            "meta_key = '_weight'",
            $methodBody,
            'Setup notice must require product weight'
        );

        $this->assertStringContainsString(
            "meta_key = '_length'",
            $methodBody,
            'Setup notice must require product length'
        );

        $this->assertStringContainsString(
            "meta_key = '_width'",
            $methodBody,
            'Setup notice must require product width'
        );

        $this->assertStringContainsString(
            "meta_key = '_height'",
            $methodBody,
            'Setup notice must require product height'
        );

        $this->assertStringContainsString(
            'All Product Configured',
            $methodBody,
            'Setup notice must show a completed product volumetric state'
        );

        $this->assertStringContainsString(
            '%1$d / %2$d Product Volumetric Configurations',
            $methodBody,
            'Setup notice must show configured product progress'
        );
    }

    #[Test]
    public function product_list_has_volumetric_configuration_label_column(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ProductController.php');

        $this->assertStringContainsString(
            'manage_edit-product_columns',
            $content,
            'Product list must register a custom volumetric column'
        );

        $this->assertStringContainsString(
            'manage_product_posts_custom_column',
            $content,
            'Product list must render the volumetric column label'
        );

        $this->assertStringContainsString(
            'kiriof_volumetric',
            $content,
            'Product list must include the KiriminAja volumetric column key'
        );

        $this->assertStringContainsString(
            'All Product Configured',
            $content,
            'Product list must show a completed volumetric label'
        );

        $this->assertStringContainsString(
            '%1$d / %2$d Configured',
            $content,
            'Product list must show configured product and variation progress'
        );

        $this->assertStringContainsString(
            '$product->get_children()',
            $content,
            'Product list must inspect variations when checking variable products'
        );

        $this->assertStringContainsString(
            "\$product_ids = array_map( 'intval', \$product->get_children() );",
            $content,
            'Variable product readiness must use variation children only, not require parent product dimensions'
        );
    }
}
