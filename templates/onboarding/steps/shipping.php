<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="kiriof-onboarding__step" data-step-panel="shipping">
	<p class="kiriof-onboarding__step-number"><?php echo esc_html__( 'Step 4 of 4', 'kiriminaja-official' ); ?></p>
	<h2><?php echo esc_html( $steps['shipping']['title'] ); ?></h2>
	<p><?php echo esc_html( $steps['shipping']['description'] ); ?></p>
	<div class="kiriof-onboarding__checks">
		<div class="kiriof-onboarding__check <?php echo $steps['shipping']['shipping_ready'] ? 'is-done' : 'is-pending'; ?>" data-shipping-status>
			<span class="dashicons <?php echo $steps['shipping']['shipping_ready'] ? 'dashicons-yes-alt' : 'dashicons-clock'; ?>" aria-hidden="true"></span>
			<div>
				<strong><?php echo esc_html__( 'KiriminAja shipping method enabled', 'kiriminaja-official' ); ?></strong>
				<p><?php echo esc_html__( 'Adds KiriminAja as an available WooCommerce shipping method for checkout rates.', 'kiriminaja-official' ); ?></p>
			</div>
		</div>
		<div class="kiriof-onboarding__check <?php echo $steps['shipping']['locations_ready'] ? 'is-done' : 'is-pending'; ?>" data-location-status>
			<span class="dashicons <?php echo $steps['shipping']['locations_ready'] ? 'dashicons-yes-alt' : 'dashicons-clock'; ?>" aria-hidden="true"></span>
			<div>
				<strong><?php echo esc_html__( 'WooCommerce shipping locations configured', 'kiriminaja-official' ); ?></strong>
				<p><?php echo esc_html__( 'Confirms WooCommerce can show shipping choices to customers in supported regions.', 'kiriminaja-official' ); ?></p>
			</div>
		</div>
	</div>
	<p class="kiriof-onboarding__shipping-help" data-location-help><?php echo esc_html__( 'Shipping locations must be enabled in WooCommerce before rates can appear at checkout.', 'kiriminaja-official' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ); ?>"><?php echo esc_html__( 'Open shipping settings', 'kiriminaja-official' ); ?></a></p>
	<div class="kiriof-onboarding__message" data-step-message="shipping" role="alert"></div>
</section>
