<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'kiriminaja-official' ) );
}

$kiriof_required_steps = array_filter( $steps, static function ( $kiriof_step ) { return $kiriof_step['required']; } );
$kiriof_step_number    = 0;
$kiriof_progress_steps  = array_values(
	array_map(
		static function ( $kiriof_step ) {
			return array(
				'key'   => $kiriof_step['key'],
				'label' => $kiriof_step['nav_title'] ?? $kiriof_step['title'],
				'done'  => (bool) $kiriof_step['done'],
			);
		},
		$kiriof_required_steps
	)
);
$kiriof_onboarding_bootstrap = array(
	'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
	'nonce'       => wp_create_nonce( KIRIOF_NONCE ),
	'shippingUrl' => admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
	'i18n'        => array(
		'back'                     => __( 'Back', 'kiriminaja-official' ),
		'continue'                 => __( 'Continue', 'kiriminaja-official' ),
		'finish'                   => __( 'Finish setup', 'kiriminaja-official' ),
		'accountRequired'           => __( 'Connect your KiriminAja account before continuing.', 'kiriminaja-official' ),
		'saveFailed'               => __( 'Could not save this step.', 'kiriminaja-official' ),
		'networkError'             => __( 'Network error. Please try again.', 'kiriminaja-official' ),
		'disconnectConfirm'        => __( 'Disconnect KiriminAja integration?', 'kiriminaja-official' ),
		'disconnectFailed'         => __( 'Disconnect failed.', 'kiriminaja-official' ),
		'subdistrictLoading'       => __( 'Searching subdistricts...', 'kiriminaja-official' ),
		'subdistrictNoResults'     => __( 'No subdistricts found.', 'kiriminaja-official' ),
		'subdistrictTypeMore'      => __( 'Type at least 3 characters.', 'kiriminaja-official' ),
		'subdistrictSearchFailed'  => __( 'Could not search subdistricts. Check the KiriminAja connection and try again.', 'kiriminaja-official' ),
		'currentLocation'          => __( 'Use current location', 'kiriminaja-official' ),
		'currentLocationUnavailable'=> __( 'Current location is not available in this browser.', 'kiriminaja-official' ),
		'currentLocationFailed'    => __( 'Could not detect your current location.', 'kiriminaja-official' ),
		'shippingAddressRequired'  => __( 'Complete all address fields and set the map pin.', 'kiriminaja-official' ),
		'courierRequired'          => __( 'Select at least one courier service.', 'kiriminaja-official' ),
		'shippingPrerequisite'     => __( 'Complete previous required steps before finishing.', 'kiriminaja-official' ),
		'addressSaved'             => __( 'Shipping address saved.', 'kiriminaja-official' ),
		'accountConnected'         => __( 'Account connected.', 'kiriminaja-official' ),
		'couriersSaved'            => __( 'Courier services saved.', 'kiriminaja-official' ),
		'shippingLocationsRequired' => __( 'Enable WooCommerce shipping locations before finishing.', 'kiriminaja-official' ),
	),
	'account'   => array(
		'connected'    => (bool) $kiriof_is_connected,
		'profileError' => (bool) $kiriof_profile_err,
		'profile'      => $kiriof_profile ? array(
			'name'          => (string) ( $kiriof_profile->name ?? '' ),
			'email'         => (string) ( $kiriof_profile->email ?? '' ),
			'status'        => (string) ( $kiriof_profile->status ?? '' ),
			'paymentMethod' => (string) ( $kiriof_profile->metadata->payment_method ?? '' ),
		) : null,
		'title'        => $steps['account']['title'],
		'description'  => $steps['account']['description'],
		'helpUrl'      => 'https://help.kiriminaja.com/article/setup-wordpress',
		'i18n'         => array(
			'connection'          => __( 'Connection', 'kiriminaja-official' ),
			'setupKey'            => __( 'Setup key', 'kiriminaja-official' ),
			'setupKeyPlaceholder' => __( 'Paste your setup key', 'kiriminaja-official' ),
			'findKey'             => __( 'Find this key in your KiriminAja application.', 'kiriminaja-official' ),
			'learnHow'            => __( 'Learn how', 'kiriminaja-official' ),
			'disconnect'          => __( 'Disconnect', 'kiriminaja-official' ),
			'unavailable'         => __( 'Unable to load account information. Your integration may be incomplete.', 'kiriminaja-official' ),
		),
	),
	'address'   => array(
		'title'       => $steps['address']['title'],
		'description' => $steps['address']['description'],
		'values'      => array_map( 'strval', $origin_values ),
		'i18n'        => array(
			'senderName'        => __( 'Sender name', 'kiriminaja-official' ),
			'senderPhone'       => __( 'Sender phone', 'kiriminaja-official' ),
			'address'           => __( 'Address', 'kiriminaja-official' ),
			'zipcode'           => __( 'Zipcode', 'kiriminaja-official' ),
			'subdistrict'       => __( 'Subdistrict', 'kiriminaja-official' ),
			'searchSubdistrict' => __( 'Search subdistrict', 'kiriminaja-official' ),
			'mapHelp'           => __( 'Move the map to place the pin at your pickup location.', 'kiriminaja-official' ),
		),
	),
	'couriers'  => array(
		'title'       => $steps['couriers']['title'],
		'description' => $steps['couriers']['description'],
		'i18n'        => array(
			'enableAll'  => __( 'Enable all', 'kiriminaja-official' ),
			'disableAll' => __( 'Disable all', 'kiriminaja-official' ),
			'loading'    => __( 'Loading couriers…', 'kiriminaja-official' ),
			'empty'      => __( 'No courier services are available.', 'kiriminaja-official' ),
			'enabled'    => __( 'enabled', 'kiriminaja-official' ),
			'enable'     => __( 'Enable', 'kiriminaja-official' ),
		),
	),
	'shipping'  => array(
		'title'          => $steps['shipping']['title'],
		'description'    => $steps['shipping']['description'],
		'shippingReady'  => (bool) $steps['shipping']['shipping_ready'],
		'locationsReady' => (bool) $steps['shipping']['locations_ready'],
		'settingsUrl'    => admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
		'i18n'           => array(
			'methodTitle'          => __( 'KiriminAja shipping method enabled', 'kiriminaja-official' ),
			'methodDescription'    => __( 'Adds KiriminAja as an available WooCommerce shipping method for checkout rates.', 'kiriminaja-official' ),
			'locationsTitle'       => __( 'WooCommerce shipping locations configured', 'kiriminaja-official' ),
			'locationsDescription' => __( 'Confirms WooCommerce can show shipping choices to customers in supported regions.', 'kiriminaja-official' ),
			'help'                 => __( 'Shipping locations must be enabled in WooCommerce before rates can appear at checkout.', 'kiriminaja-official' ),
			'openSettings'         => __( 'Open shipping settings', 'kiriminaja-official' ),
		),
	),
);
?>
<div class="kiriof-onboarding" data-kiriof-onboarding data-current-step="<?php echo esc_attr( $current_step ); ?>" data-account-complete="<?php echo ! empty( $steps['account']['done'] ) ? '1' : '0'; ?>">
	<header class="kiriof-onboarding__header">
		<a class="kiriof-onboarding__brand" href="<?php echo esc_url( admin_url() ); ?>" aria-label="<?php echo esc_attr__( 'WordPress Dashboard', 'kiriminaja-official' ); ?>">
			<img src="<?php echo esc_url( KIRIOF_URL . 'assets/admin/img/logo-tagline.svg' ); ?>" alt="<?php echo esc_attr__( 'KiriminAja', 'kiriminaja-official' ); ?>">
		</a>
		<nav class="kiriof-onboarding__progress kiriof-onboarding__progress-fallback" aria-label="<?php echo esc_attr__( 'Setup progress', 'kiriminaja-official' ); ?>">
			<?php foreach ( $kiriof_required_steps as $kiriof_key => $kiriof_step ) : $kiriof_step_number++; ?>
				<button type="button" class="kiriof-onboarding__progress-step <?php echo $kiriof_key === $current_step ? 'is-current' : ''; ?> <?php echo $kiriof_step['done'] ? 'is-done' : ''; ?>" data-step-target="<?php echo esc_attr( $kiriof_key ); ?>">
					<span class="kiriof-onboarding__progress-index" aria-hidden="true">
						<?php if ( $kiriof_step['done'] ) : ?>
							&#10003;
						<?php else : ?>
							<?php echo esc_html( $kiriof_step_number ); ?>
						<?php endif; ?>
					</span>
					<span><?php echo esc_html( $kiriof_step['nav_title'] ?? $kiriof_step['title'] ); ?></span>
				</button>
			<?php endforeach; ?>
		</nav>
		<div
			class="kiriof-onboarding__progress-host"
			data-kiriof-progress
			data-current-step="<?php echo esc_attr( $current_step ); ?>"
			data-navigation-label="<?php echo esc_attr__( 'Setup progress', 'kiriminaja-official' ); ?>"
			data-steps="<?php echo esc_attr( wp_json_encode( $kiriof_progress_steps ) ); ?>"
		></div>
		<script type="application/json" data-kiriof-onboarding-payload><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is hex-escaped for a non-executable data block. ?><?php echo wp_json_encode( $kiriof_onboarding_bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
		<div class="kiriof-onboarding__header-actions">
			<a class="kiriof-onboarding__icon-link" href="https://kiriminaja.com/solusi/plugin-woocommerce" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr__( 'Need help?', 'kiriminaja-official' ); ?>" title="<?php echo esc_attr__( 'Need help?', 'kiriminaja-official' ); ?>">
				<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
			</a>
			<a class="kiriof-onboarding__icon-link" href="<?php echo esc_url( admin_url() ); ?>" aria-label="<?php echo esc_attr__( 'Close setup', 'kiriminaja-official' ); ?>" title="<?php echo esc_attr__( 'Close setup', 'kiriminaja-official' ); ?>">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</a>
		</div>
	</header>

	<main class="kiriof-onboarding__main">
		<div data-kiriof-onboarding-app></div>
		<div data-kiriof-onboarding-fallback>
		<div class="kiriof-onboarding__panel" data-kiriof-panel>
			<?php include __DIR__ . '/steps/account.php'; ?>
			<?php include __DIR__ . '/steps/address.php'; ?>
			<?php include __DIR__ . '/steps/couriers.php'; ?>
			<?php include __DIR__ . '/steps/shipping.php'; ?>
			<section class="kiriof-onboarding__step" data-step-panel="complete">
				<div class="kiriof-onboarding__success-icon" aria-hidden="true">&#10003;</div>
				<h2><?php echo esc_html__( 'Your store is ready to ship', 'kiriminaja-official' ); ?></h2>
				<p><?php echo esc_html__( 'KiriminAja is configured. You can now manage shipments from your WordPress dashboard.', 'kiriminaja-official' ); ?></p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=kiriminaja-konfigurasi' ) ); ?>"><?php echo esc_html__( 'Go to KiriminAja', 'kiriminaja-official' ); ?></a>
			</section>
		</div>

		<div class="kiriof-onboarding__footer" data-kiriof-footer>
			<button type="button" class="button" data-kiriof-back><?php echo esc_html__( 'Back', 'kiriminaja-official' ); ?></button>
			<button type="button" class="button button-primary" data-kiriof-continue><?php echo esc_html__( 'Continue', 'kiriminaja-official' ); ?></button>
		</div>
		</div>
	</main>
</div>
