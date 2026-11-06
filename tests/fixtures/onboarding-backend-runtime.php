<?php
/** Isolated WordPress, WooCommerce and remote-profile boundaries; page, state and SQL repositories are real. */
namespace KiriminAjaOfficial\Services {
	class KiriminajaApiService {
		public function getProfile() {
			++$GLOBALS['profile_reads'];
			if ( 'exception' === $GLOBALS['scenario'] ) {
				throw new \RuntimeException( 'Profile unavailable' );
			}
			return (object) array(
				'status' => 'profile-error' === $GLOBALS['scenario'] ? 503 : 200,
				'data'   => 'empty-profile' === $GLOBALS['scenario'] ? array() : (object) array(
					'name' => 'Merchant <Store>', 'email' => 'merchant@example.test', 'status' => 'active',
					'metadata' => (object) array( 'payment_method' => 'deposit' ),
				),
			);
		}
	}
}
namespace KiriminAjaOfficial\Pages {
	function filter_input( $type, $name, $filter ) {
		return $GLOBALS['request'][ $name ] ?? null;
	}
}
namespace {
	$root = dirname( __DIR__, 2 );
	define( 'ABSPATH', $root . '/' );
	define( 'KIRIOF_DIR', $root . '/' );
	define( 'KIRIOF_NONCE', 'backend-fixture' );
	$version       = $argv[1];
	$scenario      = $argv[2];
	$operation     = $argv[3] ?? 'page';
	$profile_reads = 0;
	$tracking_reads = 0;
	$options = array();
	$deleted = array();
	$request = array();
	$pagenow = 'index.php';
	function __( $text, $domain = '' ) { return $text; }
	function sanitize_key( $key ) { return strtolower( $key ); }
	function absint( $value ) { return abs( (int) $value ); }
	function admin_url( $path = '' ) { return 'https://fixture.test/shop/wp-admin/' . $path; }
	function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
	function plugin_dir_url( $file ) { return 'https://fixture.test/plugins/'; }
	function plugin_basename( $file ) { return basename( $file ); }
	function current_user_can( $capability ) { return 'forbidden' !== $GLOBALS['scenario']; }
	function esc_html__( $text, $domain ) { return $text; }
	function wp_die( $message ) { throw new RuntimeException( $message ); }
	function wp_create_nonce( $action ) { return 'fixture-nonce'; }
	function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags | JSON_THROW_ON_ERROR ); }
	function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
	function delete_option( $key ) { $GLOBALS['deleted'][] = $key; unset( $GLOBALS['options'][ $key ] ); }
	function is_admin() { return true; }
	function is_network_admin() { return false; }
	function wp_doing_ajax() { return false; }
	function wp_doing_cron() { return false; }
	function wp_safe_redirect( $url ) { throw new RuntimeException( $url ); }
	function kiriof_get_tracking_page_id() { ++$GLOBALS['tracking_reads']; return 'optional-complete' === $GLOBALS['scenario'] ? 42 : 0; }
	function WC() {
		return (object) array( 'countries' => new class {
			public function get_shipping_countries() { return 'no-countries' === $GLOBALS['scenario'] ? array() : array( 'ID' => 'Indonesia' ); }
		} );
	}
	class WC_Shipping_Zones {
		public static function get_zones() { return array( array( 'zone_id' => 1 ) ); }
	}
	class WC_Shipping_Zone {
		public function __construct( $id ) {}
		public function get_shipping_methods( $enabled_only = false ) {
			return 'no-method' === $GLOBALS['scenario'] ? array() : array( 7 => (object) array( 'id' => 'kiriminaja-official', 'enabled' => 'yes' ) );
		}
	}
	final class OnboardingBackendDb {
		public $prefix = 'fixture_';
		public $posts = 'fixture_posts';
		public $postmeta = 'fixture_postmeta';
		public $last_error = '';
		public $rows = array();
		public $queries = array();
		public function prepare( $sql, ...$args ) {
			if ( isset( $args[0] ) && is_array( $args[0] ) ) { $args = $args[0]; }
			return preg_replace_callback( '/%s/', static function () use ( &$args ) {
				return "'" . addslashes( (string) array_shift( $args ) ) . "'";
			}, $sql );
		}
		public function get_row( $sql ) {
			$this->queries[] = $sql;
			if ( ! preg_match( "/`key` = '([^']*)'/", $sql, $matches ) ) { throw new RuntimeException( 'Unexpected row SQL: ' . $sql ); }
			$key = $matches[1];
			return array_key_exists( $key, $this->rows ) ? (object) array( 'key' => $key, 'value' => $this->rows[ $key ] ) : null;
		}
		public function get_results( $sql ) {
			$this->queries[] = $sql;
			if ( false === strpos( $sql, 'WHERE `key` IN (' ) ) { throw new RuntimeException( 'Unexpected results SQL: ' . $sql ); }
			preg_match_all( "/'([^']*)'/", $sql, $matches );
			$rows = array();
			foreach ( $matches[1] as $key ) {
				if ( array_key_exists( $key, $this->rows ) ) { $rows[] = (object) array( 'key' => $key, 'value' => $this->rows[ $key ] ); }
			}
			return $rows;
		}
		public function get_var( $sql ) {
			$this->queries[] = $sql;
			if ( false === strpos( $sql, 'COUNT(DISTINCT p.ID)' ) ) { throw new RuntimeException( 'Unexpected scalar SQL: ' . $sql ); }
			return false === strpos( $sql, 'weight_meta' ) || 'products-ready' === $GLOBALS['scenario'] || 'optional-complete' === $GLOBALS['scenario'] ? 3 : 1;
		}
	}
	$wpdb = new OnboardingBackendDb();
	$wpdb->rows = array(
		'setup_key' => 'private-setup', 'api_key' => 'private-api', 'origin_name' => 'Warehouse <A>',
		'origin_phone' => '08123456789', 'origin_address' => 'Jl. Fixture', 'origin_latitude' => '-7.8',
		'origin_longitude' => '110.4', 'origin_sub_district_id' => '123', 'origin_sub_district_name' => 'District',
		'origin_zip_code' => '55111', 'origin_whitelist_expedition_id' => 'jne',
	);
	switch ( $scenario ) {
		case 'missing-setup': unset( $wpdb->rows['setup_key'] ); break;
		case 'missing-api': $wpdb->rows['api_key'] = ''; break;
		case 'missing-address': unset( $wpdb->rows['origin_zip_code'] ); break;
		case 'empty-address': $wpdb->rows['origin_phone'] = ''; break;
		case 'no-couriers': $wpdb->rows['origin_whitelist_expedition_id'] = ''; break;
		case 'deny-policy': $wpdb->rows['origin_whitelist_expedition_services'] = '{}'; break;
		case 'disabled-locations': $options['woocommerce_ship_to_countries'] = 'disabled'; break;
	}
	require $root . '/vendor/autoload.php';
	$source = 'baseline' === $version ? __DIR__ . '/onboarding-backend-baseline/' : $root . '/inc/Services/';
	require $source . 'OnboardingSetupStateService.php';
	require ( 'baseline' === $version ? __DIR__ . '/onboarding-backend-baseline/' : $root . '/inc/Pages/' ) . 'Onboarding.php';
	$result = array();
	if ( 'page' === $operation ) {
		ob_start();
		try { ( new \KiriminAjaOfficial\Pages\Onboarding() )->render_page(); }
		catch ( RuntimeException $error ) { $result['error'] = $error->getMessage(); }
		$html = ob_get_clean();
		if ( preg_match( '/data-kiriof-onboarding-payload>(.*?)<\/script>/s', $html, $matches ) ) {
			$result['bootstrap'] = json_decode( $matches[1], true, 512, JSON_THROW_ON_ERROR );
		}
	} elseif ( 'redirect' === $operation ) {
		$options['kiriof_onboarding_activation_redirect'] = true;
		$options['kiriof_onboarding_update_redirect'] = true;
		if ( 'onboarding-page' === $scenario ) { $request['page'] = 'kiriminaja-onboarding'; }
		if ( 'shipping-settings' === $scenario ) { $request = array( 'page' => 'wc-settings', 'tab' => 'shipping' ); }
		if ( 'plugins-page' === $scenario ) { $pagenow = 'plugins.php'; }
		try { ( new \KiriminAjaOfficial\Pages\Onboarding() )->redirect_incomplete_setup(); }
		catch ( RuntimeException $error ) { $result['redirect'] = $error->getMessage(); }
		$result['deleted'] = $deleted;
		$result['options'] = $options;
	} else {
		$service = new \KiriminAjaOfficial\Services\OnboardingSetupStateService();
		if ( 'required' === $operation ) {
			$result['steps'] = $service->get_required_steps();
			$result['complete'] = $service->is_required_complete();
			$result['first'] = $service->get_first_incomplete_step( true );
			$result['repeated'] = $service->get_required_steps();
		} else {
			$result['steps'] = $service->get_steps();
			$result['first'] = $service->get_first_incomplete_step();
			$result['repeated'] = $service->get_steps();
		}
	}
	$result['sql_queries'] = count( $wpdb->queries );
	$result['product_queries'] = count( array_filter( $wpdb->queries, static function ( $sql ) { return false !== strpos( $sql, 'fixture_posts' ); } ) );
	$result['tracking_reads'] = $tracking_reads;
	$result['profile_reads'] = $profile_reads;
	echo json_encode( $result, JSON_THROW_ON_ERROR );
}
