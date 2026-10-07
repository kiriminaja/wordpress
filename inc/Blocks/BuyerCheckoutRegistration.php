<?php
namespace KiriminAjaOfficial\Blocks;
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Safe bootstrap even when WooCommerce Blocks is unavailable. */
final class BuyerCheckoutRegistration {
	public function register(): void {
		add_action( 'init', array( $this, 'register_assets' ), 5 );
		add_action( 'woocommerce_blocks_cart_block_registration', array( $this, 'register_integration' ) );
		add_action( 'woocommerce_blocks_checkout_block_registration', array( $this, 'register_integration' ) );
	}
	public function register_assets(): void {
		( new \KiriminAjaOfficial\Base\Enqueue() )->register_buyer_checkout_assets();
	}
	public function register_integration( $registry ): void {
		if ( ! interface_exists( '\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) { return; }
		$registry->register( new BuyerCheckoutIntegration() );
	}
}
