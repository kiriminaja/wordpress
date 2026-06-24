<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionProcessRecipientFallbackTest extends TestCase
{
    #[Test]
    public function transaction_process_view_falls_back_to_wc_order_recipient_fields(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/transaction-process/view/index.php');

        $this->assertStringContainsString(
            "wc_get_order(\$kiriof_row->wc_order_id)",
            $content,
            'Transaction process rows should load the WooCommerce order so recipient data still renders when shipping_info is sparse'
        );

        $this->assertStringContainsString(
            "get_address('billing')",
            $content,
            'Billing address fallback should read the WooCommerce billing address array'
        );

        $this->assertStringContainsString(
            "get_address('shipping')",
            $content,
            'Shipping address fallback should read the WooCommerce shipping address array'
        );

        $this->assertStringContainsString(
            'get_billing_phone()',
            $content,
            'Recipient phone fallback should read the WooCommerce billing phone'
        );

        $this->assertStringContainsString(
            'get_formatted_billing_full_name()',
            $content,
            'Billing recipient fallback should read the formatted WooCommerce billing full name'
        );

        $this->assertStringContainsString(
            'get_formatted_shipping_full_name()',
            $content,
            'Ship-to recipient fallback should read the formatted WooCommerce shipping full name'
        );
    }

    #[Test]
    public function transaction_process_view_renders_resolved_recipient_name_and_phone(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/transaction-process/view/index.php');

        $this->assertStringContainsString(
            'esc_html($kiriofBillingName)',
            $content,
            'Order column should render the resolved billing name instead of raw shipping_info fields only'
        );

        $this->assertStringContainsString(
            'esc_html($kiriofShippingName)',
            $content,
            'Ship-to column should render the resolved shipping recipient name instead of raw shipping_info fields only'
        );

        $this->assertStringContainsString(
            '? \'<a href="tel:\' . esc_attr($kiriof_shippingPhone)',
            $content,
            'Order column should render the resolved recipient phone when one can be recovered from WooCommerce'
        );
    }
}
