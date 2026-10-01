<?php
namespace KiriminAjaOfficial\Blocks;
use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use KiriminAjaOfficial\Base\Enqueue;
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Native Cart and Checkout asset/settings contract. */
final class BuyerCheckoutIntegration implements IntegrationInterface {
	public function get_name() { return 'kiriminaja-official-buyer'; }
	public function initialize() {
		( new Enqueue() )->register_buyer_checkout_assets( true );
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'kiriof-buyer-checkout' );
	}
	public function get_script_handles() { return array( 'kiriof-buyer-checkout' ); }
	public function get_editor_script_handles() { return array( 'kiriof-checkout-district-editor' ); }
	public function get_script_data() { return ( new Enqueue() )->buyer_checkout_config(); }
}
