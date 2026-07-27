<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="kiriof-onboarding__step" data-step-panel="account">
	<p class="kiriof-onboarding__step-number"><?php echo esc_html__( 'Step 1 of 4', 'kiriminaja-official' ); ?></p>
	<h2><?php echo esc_html( $steps['account']['title'] ); ?></h2>
	<p><?php echo esc_html( $steps['account']['description'] ); ?></p>
	<?php if ( ! empty( $kiriof_is_connected ) ) : ?>
		<div class="kiriof-onboarding__account-shell">
			<div class="kj-account-card" style="background:#fff;border:1px solid #c3c4c7;border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 1px 2px rgba(0,0,0,0.03);">
				<div style="font-size:14px;font-weight:600;color:#1d2327;margin-bottom:16px;"><?php echo esc_html__( 'Connection', 'kiriminaja-official' ); ?></div>
				<?php include KIRIOF_DIR . 'templates/setting/partials/account-connection-status.php'; ?>
			</div>
		</div>
	<?php else : ?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="kiriof-onboarding-setup-key"><?php echo esc_html__( 'Setup key', 'kiriminaja-official' ); ?></label></th>
					<td>
						<input id="kiriof-onboarding-setup-key" class="regular-text kiriof-onboarding__field" type="text" autocomplete="off" placeholder="<?php echo esc_attr__( 'Paste your setup key', 'kiriminaja-official' ); ?>">
						<p class="description"><?php echo esc_html__( 'Find this key in your KiriminAja application.', 'kiriminaja-official' ); ?> <a href="https://help.kiriminaja.com/article/setup-wordpress" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Learn how', 'kiriminaja-official' ); ?></a></p>
					</td>
				</tr>
			</tbody>
		</table>
	<?php endif; ?>
	<div class="kiriof-onboarding__message" data-step-message="account" role="alert"></div>
</section>
