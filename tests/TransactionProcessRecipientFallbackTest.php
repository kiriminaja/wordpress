<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionProcessRecipientFallbackTest extends TestCase
{
    #[Test]
    public function transaction_process_view_uses_the_shared_recipient_resolver(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/transaction-process/view/index.php');

        $this->assertStringContainsString(
            "wc_get_order(\$kiriof_row->wc_order_id)",
            $content,
            'Transaction process rows should load the WooCommerce order so recipient data still renders when shipping_info is sparse'
        );

        $this->assertStringContainsString(
            'RecipientDataResolver',
            $content,
            'Transaction process rows must use the shared WooCommerce-first recipient resolver'
        );

        $this->assertStringContainsString(
            '$kiriof_recipientResolver->resolve($kiriof_wcOrder, $kiriof_shippingData, $kiriof_row)',
            $content,
            'Transaction process rows must resolve recipient data from the current WooCommerce order before legacy shipping_info'
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
