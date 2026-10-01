<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
final class BuyerBlocksIntegrationRuntimeTest extends TestCase {
	#[Test]
	public function registration_without_interface_does_not_load_integration(): void {
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/buyer-blocks-integration-runtime.php' ), $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$this->assertSame( array( 'registered' => 0, 'loaded' => false ), json_decode( implode( "\n", $output ), true ) );
	}

	#[Test]
	public function block_service_is_registered_for_native_checkout(): void {
		$content = file_get_contents( dirname( __DIR__ ) . '/inc/Init.php' );
		$this->assertStringContainsString( 'Blocks\BuyerCheckoutRegistration::class', $content, 'Native Cart/Checkout asset registration must be loaded through plugin services' );
	}

	#[Test]
	public function editor_only_mentions_registered_handles(): void {
		$content = file_get_contents( dirname( __DIR__ ) . '/inc/Blocks/BuyerCheckoutIntegration.php' );
		$this->assertStringContainsString( "'kiriof-checkout-district-editor'", $content );
		$this->assertStringNotContainsString( 'kiriof-checkout-district-placement', $content, 'Disabled editor handles must not reference a removed asset' );
	}
}
