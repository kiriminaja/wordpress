<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ );

$scenario = $argv[1] ?? 'core';
$root     = sys_get_temp_dir() . '/kiriof-template-' . bin2hex( random_bytes( 6 ) );
$core     = $root . '/woocommerce/templates';
$plugin   = $root . '/plugin/templates/woocommerce';
$theme    = $root . '/theme/woocommerce';
$vendor   = $root . '/vendor/woocommerce';

foreach ( array( $core . '/checkout', $plugin . '/checkout', $theme . '/checkout', $vendor . '/checkout' ) as $directory ) {
	mkdir( $directory, 0700, true );
}
file_put_contents( $core . '/checkout/review-order.php', '<?php echo "core";' );
file_put_contents( $plugin . '/checkout/review-order.php', '<?php echo "plugin";' );
file_put_contents( $theme . '/checkout/review-order.php', '<?php echo "theme";' );
file_put_contents( $vendor . '/checkout/review-order.php', '<?php echo "third-party";' );

define( 'WC_TEMPLATE_PATH', $core );

function plugin_dir_path( $file ) { return $GLOBALS['plugin_root'] . '/wc/'; }
function untrailingslashit( $value ) { return rtrim( $value, '/\\' ); }
function trailingslashit( $value ) { return rtrim( $value, '/\\' ) . '/'; }
function add_filter( $hook, $callback, $priority, $accepted_args ) { $GLOBALS['template_filter'] = $callback; }
function WC() { return null; }

$GLOBALS['plugin_root'] = $root . '/plugin';
require dirname( __DIR__, 2 ) . '/wc/OverwriteWoocommercePlugin.php';

$selected = array(
	'core'        => $core . '/checkout/review-order.php',
	'theme'       => $theme . '/checkout/review-order.php',
	'third-party' => $vendor . '/checkout/review-order.php',
)[ $scenario ] ?? $core . '/checkout/review-order.php';

$resolved = kiriof_override_woocommerce_template( $selected, 'checkout/review-order.php', 'woocommerce/' );
$contents = file_get_contents( $resolved );
$markers  = array( 'plugin', 'theme', 'third-party', 'core' );
$matched  = 'unknown';
foreach ( $markers as $marker ) {
	if ( false !== strpos( $contents, $marker ) ) {
		$matched = $marker;
		break;
	}
}

echo json_encode(
	array(
		'selected' => $matched,
	),
	JSON_THROW_ON_ERROR
);

unlink( $core . '/checkout/review-order.php' );
unlink( $plugin . '/checkout/review-order.php' );
unlink( $theme . '/checkout/review-order.php' );
unlink( $vendor . '/checkout/review-order.php' );
rmdir( $core . '/checkout' );
rmdir( $plugin . '/checkout' );
rmdir( $theme . '/checkout' );
rmdir( $vendor . '/checkout' );
rmdir( $core );
rmdir( $plugin );
rmdir( $theme );
rmdir( $vendor );
rmdir( $root . '/woocommerce' );
rmdir( $root . '/plugin/templates' );
rmdir( $root . '/theme' );
rmdir( $root . '/plugin' );
rmdir( $root . '/vendor' );
rmdir( $root );
