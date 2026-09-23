<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_localize_script(
	'kiriof-request-pickup',
	'kiriofRequestPickupConfig',
	array(
		'redirectUrl' => admin_url( 'admin.php?page=kiriminaja-request-pickup' ),
		'i18n'        => array(
			'error'            => __( 'An error occurred.', 'kiriminaja-official' ),
			'codCharges'       => __( 'COD Package Charges', 'kiriminaja-official' ),
			'nonCodCharges'    => __( 'Non-COD Package Charges', 'kiriminaja-official' ),
			'totalCharges'     => __( 'Total Charges', 'kiriminaja-official' ),
		),
	)
);
?>
<div class="wrap kj-wrap kiriof-payments-shell" data-kiriof-payments-page>
	<div data-kiriof-payments-root aria-busy="true"></div>
	<script type="application/json" data-kiriof-payments-payload><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is hex-escaped for a non-executable data block. ?><?php echo wp_json_encode( $kiriof_payments_bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
	<div data-kiriof-payments-modals-root></div>
</div>
