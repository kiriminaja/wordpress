<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'kiriminaja-official' ) );
}

$kiriof_current_user = wp_get_current_user();
$kiriof_pin_cache_ttl = (int) apply_filters( 'kiriof_pin_cache_ttl', 15 * MINUTE_IN_SECONDS, $kiriof_current_user );
$kiriof_pin_cache_ttl = max( MINUTE_IN_SECONDS, $kiriof_pin_cache_ttl );

wp_localize_script(
	'kiriof-transaction-process',
	'kiriofTransactionProcess',
	array(
		'urls' => array(
			'pickup' => esc_url_raw( admin_url( 'admin.php?page=kiriminaja-request-pickup' ) ),
		),
		'pinCache' => array(
			'key'      => 'kiriof_pin_cache_' . get_current_blog_id() . '_' . (int) get_current_user_id(),
			'ttl'      => $kiriof_pin_cache_ttl,
			'userHash' => hash_hmac( 'sha256', (string) get_current_user_id(), wp_salt( 'auth' ) ),
			'siteHash' => hash_hmac( 'sha256', home_url( '/' ), wp_salt( 'auth' ) ),
		),
		'i18n' => array(
			'requestPickup'        => __( 'Request Pickup', 'kiriminaja-official' ),
			'print'                => __( 'Print', 'kiriminaja-official' ),
			'pickSchedule'         => __( 'Pick Schedule', 'kiriminaja-official' ),
			'confirmPin'           => __( 'Confirm PIN', 'kiriminaja-official' ),
			'validate'             => __( 'Validate', 'kiriminaja-official' ),
			'noSelectedTransaction'=> __( 'There is no selected transaction.', 'kiriminaja-official' ),
			'genericError'         => __( 'An error occurred.', 'kiriminaja-official' ),
			'noSchedule'           => __( 'No pickup schedule is available.', 'kiriminaja-official' ),
			'codOnlyNoPayment'     => __( 'COD-only pickups do not require a payment method.', 'kiriminaja-official' ),
			'topNoPayment'         => __( 'TOP merchant uses published rates. Payment method is not required for this pickup.', 'kiriminaja-official' ),
			'pinNotConfigured'     => __( 'PIN is not configured.', 'kiriminaja-official' ),
			'configurePin'         => __( 'Configure PIN', 'kiriminaja-official' ),
			'insufficientCredit'   => __( 'Insufficient credit.', 'kiriminaja-official' ),
			'topUpNow'             => __( 'Top Up Now', 'kiriminaja-official' ),
			'noPrintSelection'     => __( 'Please select at least one order to print.', 'kiriminaja-official' ),
			'back'                 => __( 'Back', 'kiriminaja-official' ),
			'pinWait'              => __( 'You have entered the wrong code three times. Please wait', 'kiriminaja-official' ),
			'toTryAgain'           => __( 'to try again.', 'kiriminaja-official' ),
			'pinLocked'            => __( 'You have entered the wrong code too many times. Please try again later.', 'kiriminaja-official' ),
			'tooManyAttempts'      => __( 'Too Many Attempts', 'kiriminaja-official' ),
			'pinRemainingPrefix'   => __( 'You still have', 'kiriminaja-official' ),
			'pinRemainingSuffix'   => __( 'chances to enter the PIN.', 'kiriminaja-official' ),
			'incorrectPin'         => __( 'Incorrect PIN', 'kiriminaja-official' ),
			'checkPin'             => __( 'Please check the PIN code you entered again.', 'kiriminaja-official' ),
			'insufficientBalance'  => __( 'Insufficient credit balance. Please top up or use QRIS.', 'kiriminaja-official' ),
			'somethingWrong'       => __( 'Something went wrong.', 'kiriminaja-official' ),
			'pinMaxAttempts'       => __( 'PIN max attempts reached. Please try again later.', 'kiriminaja-official' ),
			'incorrectPinPeriod'   => __( 'Incorrect PIN.', 'kiriminaja-official' ),
			'sixDigitPin'          => __( 'Please enter a 6-digit PIN.', 'kiriminaja-official' ),
			'selectSchedule'       => __( 'Please select a pickup schedule.', 'kiriminaja-official' ),
			'selectPayment'        => __( 'Please select a payment method.', 'kiriminaja-official' ),
			'reasonMin'            => __( '*Alasan minimal 5 karakter', 'kiriminaja-official' ),
			'reasonMax'            => __( '*Alasan maksimal 200 karakter', 'kiriminaja-official' ),
			'confirmCancel'        => __( 'Are you sure you want to cancel this transaction?', 'kiriminaja-official' ),
			'errorOccurred'        => __( 'Terjadi kesalahan', 'kiriminaja-official' ),
			'cancelSuccess'        => __( 'Transaction cancelled successfully.', 'kiriminaja-official' ),
			'pinRemembered'        => __( 'Saved PIN will be reused until it expires on this browser.', 'kiriminaja-official' ),
			'pinExpired'           => __( 'Saved PIN expired. Please enter your PIN again.', 'kiriminaja-official' ),
			'pinInvalidated'       => __( 'Saved PIN was cleared. Please enter your latest PIN again.', 'kiriminaja-official' ),
			'pinUnsupported'       => __( 'Browser secure storage is unavailable. PIN will not be remembered.', 'kiriminaja-official' ),
		),
	)
);
?>
<div class="wrap kj-wrap kiriof-workspace-shell" data-kiriof-transactions-page>
	<div data-kiriof-transactions-root aria-busy="true"></div>
	<script type="application/json" data-kiriof-transactions-payload><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is hex-escaped for a non-executable data block. ?><?php echo wp_json_encode( $kiriof_transactions_bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
	<form id="kiriof-print-bulk-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" target="_blank" hidden>
		<input type="hidden" name="action" value="kiriof_resi_print_bulk">
		<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'kiriof_resi_print_bulk' ) ); ?>">
	</form>
</div>
