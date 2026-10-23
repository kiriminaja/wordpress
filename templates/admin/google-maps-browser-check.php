<?php
/** Isolated, protected browser-origin map check. Config is never parent settings data. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="robots" content="noindex,nofollow">
	<meta name="referrer" content="strict-origin-when-cross-origin">
	<title><?php esc_html_e( 'Google Maps browser check', 'kiriminaja-official' ); ?></title>
	<style>
		html, body { margin: 0; height: 100%; font-family: sans-serif; }
		body { display: flex; flex-direction: column; }
		#kiriof-google-maps-check-map { width: 100%; min-height: 260px; flex: 1; }
		#kiriof-google-maps-check-status { margin: 0; padding: 12px; }
	</style>
</head>
<body>
	<div id="kiriof-google-maps-check-map" role="region" aria-label="<?php esc_attr_e( 'Sample map of Jakarta for Google Maps browser check', 'kiriminaja-official' ); ?>"></div>
	<p id="kiriof-google-maps-check-status" role="status" aria-live="polite"><?php echo esc_html( $config['i18n']['loading'] ); ?></p>
	<script id="kiriof-google-maps-check-config" type="application/json"><?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON in a non-executable script; HEX flags prevent HTML/script breakout without corrupting JSON.
		echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
	?></script>
	<script src="<?php echo esc_url( $script_url ); ?>" defer></script>
</body>
</html>
