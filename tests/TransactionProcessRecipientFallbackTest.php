<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionProcessRecipientFallbackTest extends TestCase
{
    #[Test]
    public function transaction_process_view_uses_the_shared_recipient_resolver(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/TransactionListViewModelFactory.php');

        $this->assertStringContainsString(
            "wc_get_order( \$row->wc_order_id )",
            $content,
            'Transaction process rows should load the WooCommerce order so recipient data still renders when shipping_info is sparse'
        );

        $this->assertStringContainsString(
            'RecipientDataResolver',
            $content,
            'Transaction process rows must use the shared WooCommerce-first recipient resolver'
        );

        $this->assertStringContainsString(
            '$this->recipient_resolver->resolve( $wc_order, $shipping_info, $row )',
            $content,
            'Transaction process rows must resolve recipient data from the current WooCommerce order before legacy shipping_info'
        );
    }

    #[Test]
    public function transaction_process_view_renders_resolved_recipient_name_and_phone(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/TransactionListViewModelFactory.php');
		$app = file_get_contents(PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte');

        $this->assertStringContainsString(
            "'customer'",
            $content,
            'Order column should render the resolved billing name instead of raw shipping_info fields only'
        );

        $this->assertStringContainsString(
            "'destination'",
            $content,
            'Ship-to column should render the resolved shipping recipient name instead of raw shipping_info fields only'
        );

        $this->assertStringContainsString(
            'href={`tel:${row.customer.phone}`}',
            $app,
            'Order column should render the resolved recipient phone when one can be recovered from WooCommerce'
        );
    }
}
