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
            "'type'         => 'select'",
            $methodBody,
            'District must register as a select field for block checkout so React checkout renders a dropdown'
        );

        $this->assertStringContainsString(
            "'options'      => \$options",
            $methodBody,
            'Select fields in WooCommerce blocks must include options or registration is ignored'
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
