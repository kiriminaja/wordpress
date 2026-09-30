<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'kiriminaja-official' ) );
}

?>
<div class="wrap kj-wrap kiriof-workspace-shell" data-kiriof-transactions-page>
	<div data-kiriof-transactions-root aria-busy="true" aria-live="polite" aria-label="<?php echo esc_attr__( 'Loading transactions…', 'kiriminaja-official' ); ?>"><?php include KIRIOF_DIR . 'templates/_workspace-boot.php'; ?></div>
	<script type="application/json" data-kiriof-transactions-payload><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is hex-escaped for a non-executable data block. ?><?php echo wp_json_encode( $kiriof_transactions_bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
</div>
