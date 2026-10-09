<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap kj-wrap kiriof-workspace-shell" data-kiriof-transaction-detail-page>
	<div data-kiriof-transaction-detail-root aria-busy="true" aria-live="polite" aria-label="<?php echo esc_attr__( 'Loading transaction details…', 'kiriminaja-official' ); ?>"><?php include KIRIOF_DIR . 'templates/_workspace-boot.php'; ?></div>
	<script type="application/json" data-kiriof-transaction-detail-payload><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is hex-escaped for a non-executable data block. ?><?php echo wp_json_encode( $kiriof_transaction_detail_bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
</div>
