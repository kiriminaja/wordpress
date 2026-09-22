<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SendRequestPickupDependenciesTest extends TestCase
{
    #[Test]
    public function service_receives_dependencies_from_composition_root(): void
    {
        $service = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php' );
        $init    = file_get_contents( PLUGIN_DIR . '/inc/Init.php' );

        $this->assertStringContainsString( 'TransactionRepository $transactionRepository', $service );
        $this->assertStringContainsString( 'PaymentRepository $paymentRepository', $service );
        $this->assertStringContainsString( 'SettingRepository $settingRepository', $service );
        $this->assertStringContainsString( 'KiriminajaApiRepository $apiRepository', $service );
        $this->assertStringNotContainsString( 'new \\KiriminAjaOfficial\\Repositories\\', $service );
        $this->assertStringContainsString( 'new Repositories\PaymentRepository()', $init );
        $this->assertStringContainsString( 'new Repositories\SettingRepository()', $init );
        $this->assertStringContainsString( 'new Repositories\KiriminajaApiRepository()', $init );
    }
}
