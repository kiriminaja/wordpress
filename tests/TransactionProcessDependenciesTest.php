<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionProcessDependenciesTest extends TestCase
{
    #[Test]
    public function controller_uses_injected_transaction_dependencies(): void
    {
        $controller = file_get_contents( PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php' );
        $init       = file_get_contents( PLUGIN_DIR . '/inc/Init.php' );

        $this->assertIsString( $controller );
        $this->assertIsString( $init );
        $this->assertStringContainsString( 'DatabaseTransactionManagerInterface $transactionManager', $controller );
        $this->assertStringContainsString( 'TransactionRepository $transactionRepository', $controller );
        $this->assertStringNotContainsString( 'global $wpdb', $controller );
        $this->assertStringNotContainsString( "'START TRANSACTION'", $controller );
        $this->assertStringNotContainsString( "'COMMIT'", $controller );
        $this->assertStringNotContainsString( "'ROLLBACK'", $controller );
        $this->assertStringNotContainsString( 'new \\KiriminAjaOfficial\\Repositories\\TransactionRepository', $controller );
        $this->assertStringContainsString( 'new Infrastructure\\WordPressDatabaseTransactionManager()', $init );
    }

    #[Test]
    public function transaction_manager_contract_and_implementation_remain_narrow(): void
    {
        $contract       = file_get_contents( PLUGIN_DIR . '/inc/Contracts/DatabaseTransactionManagerInterface.php' );
        $implementation = file_get_contents( PLUGIN_DIR . '/inc/Infrastructure/WordPressDatabaseTransactionManager.php' );

        $this->assertIsString( $contract );
        $this->assertIsString( $implementation );
        $this->assertSame( 3, substr_count( $contract, 'public function ' ) );
        $this->assertStringContainsString( 'implements DatabaseTransactionManagerInterface', $implementation );
    }
}
