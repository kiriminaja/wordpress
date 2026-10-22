<?php
/** Isolated WordPress/map boundaries for the production onboarding bootstrap. */
namespace KiriminAjaOfficial\Base {
	class BaseInit {}
}
namespace KiriminAjaOfficial\Services {
	class InstantDetailMapData {
		public static function mapConfig(): array {
			return array( 'enabled' => false, 'provider' => 'leaflet' );
		}
	}
}
namespace {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	define( 'KIRIOF_NONCE', 'onboarding-navigation-fixture' );
	$admin_base = $argv[1] ?? 'https://fixture.test/wp-admin/';
	function admin_url( $path = '' ) {
		return $GLOBALS['admin_base'] . ltrim( $path, '/' );
	}
	function __( $text, $domain ) {
		return $text;
	}
	function wp_create_nonce( $action ) {
		return 'fixture-nonce';
	}
	function esc_url( $url ) {
		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}
	require_once ABSPATH . 'inc/Services/CourierServiceUiData.php';
	require_once ABSPATH . 'inc/Pages/Onboarding.php';
	$steps = array();
	foreach ( array( 'account', 'address', 'couriers', 'shipping' ) as $key ) {
		$steps[ $key ] = array(
			'key'             => $key,
			'title'           => ucfirst( $key ),
			'description'     => '',
			'required'        => true,
			'done'            => true,
			'shipping_ready'  => true,
			'locations_ready' => true,
		);
	}
	$page = new \KiriminAjaOfficial\Pages\Onboarding();
	$method = new \ReflectionMethod( $page, 'build_bootstrap' );
	$bootstrap = $method->invoke( $page, $steps, array(), 'complete', true, null, false );
	echo json_encode( $bootstrap, JSON_THROW_ON_ERROR );
}
