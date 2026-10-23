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
		#kiriof-google-maps-check-map { width: 100%; height: 100%; flex: 1; }
		#kiriof-google-maps-check-status { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
	</style>
</head>
<body>
	<div id="kiriof-google-maps-check-map" role="region" aria-label="<?php esc_attr_e( 'Sample map of Jakarta for Google Maps browser check', 'kiriminaja-official' ); ?>"></div>
	<p id="kiriof-google-maps-check-status" aria-hidden="true"><?php echo esc_html( $config['i18n']['loading'] ); ?></p>
	<script id="kiriof-google-maps-check-config" type="application/json"><?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON in a non-executable script; HEX flags prevent HTML/script breakout without corrupting JSON.
		echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
	?></script>
	<?php wp_print_scripts( 'kiriof-google-maps-browser-check' ); ?>
</body>
</html>
