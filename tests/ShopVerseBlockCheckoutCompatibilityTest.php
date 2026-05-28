<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for React/block checkout themes such as ShopVerse.
 */
final class ShopVerseBlockCheckoutCompatibilityTest extends TestCase
{
    #[Test]
    public function shopverse_uses_woocommerce_checkout_blocks(): void
    {
        $content = file_get_contents('/tmp/wordpress-local/wp-content/themes/shopverse/patterns/template-checkout.php');

        $this->assertStringContainsString(
            'wp:woocommerce/checkout',
            $content,
            'ShopVerse checkout template must be treated as WooCommerce block checkout'
        );
    }

    #[Test]
    public function block_checkout_field_registration_uses_supported_select_field_without_invalid_before_attribute(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $start = strpos($content, 'public function kiriof_register_block_checkout_fields');
        $this->assertNotFalse($start, 'Block checkout registration method must exist');
        $methodBody = substr($content, $start, 2600);

        $this->assertStringContainsString(
            "'type'         => 'text'",
            $methodBody,
            'District must register as text for block checkout because WooCommerce Store API rejects dynamic select values not present in the original enum'
        );

        $this->assertStringNotContainsString(
            "'type'         => 'select'",
            $methodBody,
            'Dynamic postcode search cannot use block select registration without triggering Invalid kiriminaja-official/kiriof_destination_area provided'
        );

        $this->assertStringNotContainsString(
            "'options'      => \$options",
            $methodBody,
            'Block select options become a fixed Store API enum and reject AJAX-loaded district IDs'
        );

        $this->assertStringNotContainsString(
            "'before' => 'address_2'",
            $methodBody,
            'WooCommerce blocks reject the non-standard before attribute and emit doing_it_wrong notices'
        );
    }

    #[Test]
    public function frontend_block_checkout_script_uses_store_api_extension_cart_update_for_fees(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/front/form-billing-address.php');

        $this->assertStringContainsString(
            'extensionCartUpdate',
            $content,
            'React/block checkout must refresh cart totals through Store API extensionCartUpdate, not only jQuery update_checkout'
        );

        $this->assertStringContainsString(
            "namespace: 'kiriminaja-official'",
            $content,
            'Store API cart update must be namespaced to this plugin'
        );
    }

    #[Test]
    public function classic_checkout_keeps_live_fee_placeholder_rows(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringContainsString(
            'kiriof_cart_item_cod_fee',
            $content,
            'Classic checkout needs the COD Fee placeholder row so AJAX can show it after courier/payment changes'
        );

        $this->assertStringContainsString(
            'kiriof_cart_item_insurane',
            $content,
            'Classic checkout needs the Insurance placeholder row so AJAX can show it after courier/insurance changes'
        );
    }

    #[Test]
    public function classic_checkout_does_not_register_district_twice_through_default_address_fields(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringNotContainsString(
            "add_filter( 'woocommerce_default_address_fields'",
            $content,
            'Classic checkout already injects District via woocommerce_checkout_fields; adding it again through default address fields renders a duplicate prefixed billing_kiriof_destination_area field'
        );

        $this->assertStringContainsString(
            "add_filter('woocommerce_checkout_fields'",
            $content,
            'Classic checkout must keep the original checkout_fields injection path for District and Insurance'
        );
    }

    #[Test]
    public function classic_checkout_uses_placeholder_rows_without_native_wc_fee_duplicates(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringContainsString(
            'kiriof_cart_item_cod_fee',
            $content,
            'Classic checkout still needs AJAX-updated COD Fee placeholder rows'
        );

        $this->assertStringContainsString(
            'kiriof_cart_item_insurane',
            $content,
            'Classic checkout still needs AJAX-updated Insurance placeholder rows'
        );

        $this->assertStringContainsString(
            'private function kiriof_is_block_checkout_request()',
            $content,
            'Native WooCommerce fees must be limited to block checkout requests so classic checkout does not render COD Fee/Insurance twice'
        );

        $this->assertStringContainsString(
            'if ( ! $this->kiriof_is_block_checkout_request() )',
            $content,
            'kiriof_add_checkout_fees must bail on classic checkout and only add native fees for block checkout'
        );
    }

    #[Test]
    public function cod_fee_calculation_matches_main_branch_amount_field(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CheckoutCalculationService.php');
        $start = strpos($content, 'private function getCalculateCODFee');
        $this->assertNotFalse($start, 'COD fee calculation method must exist');
        $methodBody = substr($content, $start, 900);

        $this->assertStringContainsString(
            'cod_fee_amount',
            $methodBody,
            'COD Fee must use API-provided cod_fee_amount like main branch'
        );

        $this->assertStringNotContainsString(
            'codRate',
            $methodBody,
            'COD Fee must not drift by recalculating locally from a rate'
        );
    }

    #[Test]
    public function php_registers_store_api_update_callback_for_block_checkout_fees(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringContainsString(
            'woocommerce_store_api_register_update_callback',
            $content,
            'Block checkout fee refresh needs a Store API cart/extensions callback'
        );

        $this->assertStringContainsString(
            "'namespace' => 'kiriminaja-official'",
            $content,
            'Store API update callback must use the same namespace as frontend extensionCartUpdate'
        );

        $this->assertStringContainsString(
            "WC()->session->set( 'chosen_shipping_methods'",
            $content,
            'Store API callback must persist selected shipping method before WC recalculates fees'
        );
    }
}
