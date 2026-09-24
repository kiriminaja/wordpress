<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap kj-wrap kiriof-workspace-shell" data-kiriof-pickup-detail-page>
	<div data-kiriof-pickup-detail-root aria-busy="true" aria-live="polite" aria-label="<?php echo esc_attr__( 'Loading pickup details…', 'kiriminaja-official' ); ?>"><?php include KIRIOF_DIR . 'templates/_workspace-boot.php'; ?></div>
	<script type="application/json" data-kiriof-pickup-detail-payload><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is hex-escaped for a non-executable data block. ?><?php echo wp_json_encode( $kiriof_pickup_detail_bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
</div>
