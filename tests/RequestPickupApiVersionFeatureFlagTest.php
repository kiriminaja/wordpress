<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestPickupApiVersionFeatureFlagTest extends TestCase {
	#[Test]
	public function ka_credit_and_pin_are_controlled_by_a_plugin_constant(): void {
		$plugin     = file_get_contents( PLUGIN_DIR . '/kiriminaja.php' );
		$controller = file_get_contents( PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php' );
		$admin      = file_get_contents( PLUGIN_DIR . '/inc/Pages/Admin.php' );
		$service    = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php' );
		$repository = file_get_contents( PLUGIN_DIR . '/inc/Repositories/KiriminajaApiRepository.php' );

		$this->assertStringContainsString( "define( 'KIRIOF_ENABLE_KA_CREDIT', false );", $plugin );
		$this->assertStringContainsString( 'KIRIOF_ENABLE_KA_CREDIT', $controller );
		$this->assertStringContainsString( 'if ( KIRIOF_ENABLE_KA_CREDIT ) {', $admin );
		$this->assertStringContainsString( "add_action( 'admin_bar_menu', array( \$this, 'kiriof_add_credit_balance_admin_bar' ), 61 );", $admin );
		$this->assertStringContainsString( '$isKaCreditEnabled = KIRIOF_ENABLE_KA_CREDIT;', $service );
		$this->assertStringContainsString( 'sendPickupRequest($payload)', $service );
		$this->assertStringNotContainsString( 'sendPickupRequestWithFeatureFlag', $repository );
	}
}
