<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionDetailPackageProductTest extends TestCase
{

    #[Test]
    public function detail_ui_merges_package_products_and_order_link_into_one_card(): void
    {
        $detail = file_get_contents( PLUGIN_DIR . '/src/lib/transaction-detail/TransactionDetail.svelte' );

        $this->assertStringContainsString( 'detailPackageProduct', $detail );
        $this->assertStringContainsString( 'transaction.package.weight', $detail );
        $this->assertStringContainsString( 'transaction.package.length', $detail );
        $this->assertStringNotContainsString( 'item.imageUrl', $detail );
        $this->assertStringContainsString( 'orderDetail', $detail );
        $this->assertStringContainsString( 'variant="outline" size="sm" href={transaction.orderUrl}', $detail );
    }
}

final class TransactionDetailLegacyOrderSafetyTest extends TestCase
{
    #[Test]
    public function detail_product_resolution_handles_deleted_products_and_logs_bootstrap_failures(): void
    {
        $service = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionDetailPageData.php' );
        $template = file_get_contents( PLUGIN_DIR . '/templates/transaction-detail/index.php' );

        $this->assertStringContainsString( 'method_exists($item, "get_product")', $service );
        $this->assertStringContainsString( 'method_exists($product, "get_sku")', $service );
        $this->assertStringContainsString( 'Transaction detail bootstrap failed.', $template );
        $this->assertStringContainsString( 'transaction_id', $template );
    }
}

final class TransactionDetailFallbackBootstrapTest extends TestCase
{
    #[Test]
    public function detail_page_falls_back_to_a_minimal_payload_instead_of_dying(): void
    {
        $service = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionDetailPageData.php' );
        $template = file_get_contents( PLUGIN_DIR . '/templates/transaction-detail/index.php' );
        $detail = file_get_contents( PLUGIN_DIR . '/src/lib/transaction-detail/TransactionDetail.svelte' );

        $this->assertStringContainsString( 'public function prepareFallback', $service );
        $this->assertStringContainsString( 'prepareFallback(', $template );
        $this->assertStringNotContainsString( "wp_die( esc_html__( 'Unable to load transaction details", $template );
        $this->assertStringContainsString( 'bootstrap.bootstrapError', $detail );
    }
}
