<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CodAdjustmentDependenciesTest extends TestCase
{
    #[Test]
    public function controller_receives_repositories_from_composition_root(): void
    {
        $controller = file_get_contents( PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php' );
        $init       = file_get_contents( PLUGIN_DIR . '/inc/Init.php' );

        $this->assertStringContainsString( 'TransactionRepository $transaction_repository', $controller );
        $this->assertStringContainsString( 'CodFeeApiRepository $cod_fee_repository', $controller );
        $this->assertStringContainsString( 'KiriminajaApiRepository $api_repository', $controller );
        $this->assertStringNotContainsString( 'new \\KiriminAjaOfficial\\Repositories\\', $controller );
        $this->assertStringContainsString( 'Controllers\CodAdjustmentController::class === $class', $init );
    }
}
