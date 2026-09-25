<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CodAdjustmentMinimumTest extends TestCase
{
    #[Test]
    public function cod_adjustment_minimum_is_the_live_shipment_charge_total_not_a_stale_database_value(): void
    {
        $controller = file_get_contents( PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php' );
        $list = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionListViewModelFactory.php' );
        $detail = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionDetailPageData.php' );

        $this->assertStringContainsString( '$localMinimum = $shippingCost + $insuranceFee + $originalCodFee + $adminFee;', $controller );
        $this->assertStringContainsString( '$minimumCod   = $localMinimum;', $controller );
        $this->assertStringContainsString( '$newCodMinimum = max( $localMinimum, (float) ( $apiResult[0]->minimum_custom_cod ?? 0 ) );', $controller );
        $this->assertStringNotContainsString( '$dbCodMinimum', $controller );
        $this->assertStringContainsString( '$deficit_minimum       = $shipping_cost + $insurance_cost + $cod_fee;', $list );
        $this->assertStringContainsString( '"codMinimum" => $shipping + $insurance + $cod_fee', $detail );
    }
}
