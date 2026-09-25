<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionDetailPackageProductTest extends TestCase
{
    #[Test]
    public function detail_data_includes_product_thumbnail_urls(): void
    {
        $data = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionDetailPageData.php' );

        $this->assertStringContainsString( 'wp_get_attachment_image_url', $data );
        $this->assertStringContainsString( "'imageUrl' => $image_url", $data );
    }

    #[Test]
    public function detail_ui_merges_package_products_and_order_link_into_one_card(): void
    {
        $detail = file_get_contents( PLUGIN_DIR . '/src/lib/transaction-detail/TransactionDetail.svelte' );

        $this->assertStringContainsString( 'detailPackageProduct', $detail );
        $this->assertStringContainsString( 'transaction.package.weight', $detail );
        $this->assertStringContainsString( 'transaction.package.length', $detail );
        $this->assertStringContainsString( 'item.imageUrl', $detail );
        $this->assertStringContainsString( 'orderDetail', $detail );
        $this->assertStringContainsString( 'variant="outline" size="sm" href={transaction.orderUrl}', $detail );
    }
}
