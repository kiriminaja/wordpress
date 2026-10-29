<?php
/** Execute the production preload hook with WordPress URL/enqueue boundaries stubbed. */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'KIRIOF_DIR', ABSPATH );
require ABSPATH . 'vendor/autoload.php';

function wp_script_is( $handle, $status ) {
	return 'kiriof-admin-workspace' === $handle && 'enqueued' === $status && 'yes' === $GLOBALS['argv'][2];
}
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
}
function wp_unslash( $value ) {
	return stripslashes( $value );
}
function esc_url( $value ) {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}
function add_query_arg( $key, $value, $url ) {
	return $url . '?' . rawurlencode( $key ) . '=' . rawurlencode( $value );
}

$_GET = array( 'page' => $argv[1], 'section' => $argv[3] ?? '' );
$enqueue = ( new ReflectionClass( \KiriminAjaOfficial\Base\Enqueue::class ) )->newInstanceWithoutConstructor();
$enqueue->plugin_url = 'https://fixture.test/shop/wp-content/plugins/kiriminaja-official/';
$enqueue->preload_workspace_modules();
