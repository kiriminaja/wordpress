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
?>
<div class="kiriof-onboarding" data-kiriof-onboarding data-current-step="<?php echo esc_attr( $current_step ); ?>" data-account-complete="<?php echo ! empty( $steps['account']['done'] ) ? '1' : '0'; ?>">
	<header class="kiriof-onboarding__header">
		<a class="kiriof-onboarding__brand" href="<?php echo esc_url( admin_url() ); ?>" aria-label="<?php echo esc_attr__( 'WordPress Dashboard', 'kiriminaja-official' ); ?>">
			<img src="<?php echo esc_url( KIRIOF_URL . 'assets/admin/img/logo-tagline.svg' ); ?>" alt="<?php echo esc_attr__( 'KiriminAja', 'kiriminaja-official' ); ?>">
		</a>
		<nav class="kiriof-onboarding__progress" aria-label="<?php echo esc_attr__( 'Setup progress', 'kiriminaja-official' ); ?>">
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
	</main>
</div>
