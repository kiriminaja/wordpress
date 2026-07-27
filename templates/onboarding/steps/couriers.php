<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="kiriof-onboarding__step" data-step-panel="couriers">
	<p class="kiriof-onboarding__step-number"><?php echo esc_html__( 'Step 3 of 4', 'kiriminaja-official' ); ?></p>
	<h2><?php echo esc_html( $steps['couriers']['title'] ); ?></h2>
	<p><?php echo esc_html( $steps['couriers']['description'] ); ?></p>
	<div class="kiriof-onboarding__actions kiriof-onboarding__courier-actions">
		<div class="kiriof-onboarding__segmented-actions" role="group" aria-label="<?php echo esc_attr__( 'Courier bulk actions', 'kiriminaja-official' ); ?>">
			<button type="button" class="button" data-couriers-all><?php echo esc_html__( 'Enable all', 'kiriminaja-official' ); ?></button>
			<button type="button" class="button" data-couriers-none><?php echo esc_html__( 'Disable all', 'kiriminaja-official' ); ?></button>
		</div>
		<span class="kiriof-onboarding__courier-count" data-courier-count></span>
	</div>
	<div class="kiriof-onboarding__couriers" data-courier-list><span class="spinner is-active"></span> <?php echo esc_html__( 'Loading couriers…', 'kiriminaja-official' ); ?></div>
	<div class="kiriof-onboarding__message" data-step-message="couriers" role="alert"></div>
</section>
