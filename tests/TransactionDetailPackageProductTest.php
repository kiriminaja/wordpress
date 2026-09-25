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
