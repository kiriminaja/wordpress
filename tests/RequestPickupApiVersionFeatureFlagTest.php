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

		$this->assertStringContainsString( "define( 'KIRIOF_ENABLE_KA_CREDIT', true );", $plugin );
		$this->assertStringContainsString( 'KIRIOF_ENABLE_KA_CREDIT', $controller );
		$this->assertStringContainsString( "add_action( 'admin_bar_menu', array( \$this, 'kiriof_add_credit_balance_admin_bar' ), 61 );", $admin );
		$this->assertStringContainsString( 'kiriof_add_credit_balance_admin_bar', $admin );
		$this->assertStringContainsString( 'isTopPaymentMethod()', $admin );
		$this->assertStringContainsString( 'kiriof_admin_bar_credit_balance', $admin );
		$this->assertStringContainsString( 'kiriof-ka-credit-balance', $admin );
		$this->assertStringContainsString( "add_action('wp_ajax_kiriof_get_credit_balance'", $controller );
		$this->assertStringContainsString( '$isKaCreditEnabled = KIRIOF_ENABLE_KA_CREDIT;', $service );
		$this->assertStringContainsString( 'sendPickupRequest($payload)', $service );
		$this->assertStringNotContainsString( 'sendPickupRequestWithFeatureFlag', $repository );
	}
}
