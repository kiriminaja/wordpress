<?php
/** Real shipping method, settings, catalog, destination sync and cache; only Woo/API boundaries are faked. */
namespace {
	error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	require_once ABSPATH . 'vendor/autoload.php';
	$GLOBALS['buyer_switch_warnings'] = array();
	$GLOBALS['buyer_switch_network'] = 0;
	$GLOBALS['buyer_switch_constructions'] = 0;
	$GLOBALS['buyer_switch_requests'] = array();
	$GLOBALS['buyer_switch_coupon_rows'] = array();
	$GLOBALS['buyer_switch_lookups'] = array();
	$GLOBALS['buyer_switch_transients'] = array();
	set_error_handler( static function ( $severity, $message ) {
		if ( error_reporting() & $severity ) { $GLOBALS['buyer_switch_warnings'][] = $message; }
		return true;
	} );
	// Prepend to Composer: even constructing the Instant client is a boundary violation.
	spl_autoload_register( static function ( $class ) {
		if ( 'KiriminAjaOfficial\\Repositories\\InstantDeliveryApiRepository' === $class ) {
			++$GLOBALS['buyer_switch_constructions'];
			throw new \RuntimeException( 'Buyer Express checkout must not construct the Instant API repository.' );
		}
	}, true, true );

	final class BuyerSwitchDb {
		public $prefix = 'buyer_switch_';
		public $last_error = '';
		public $rows = array(
			'origin_sub_district_id' => '123',
			'origin_whitelist_expedition_id' => 'ninja,tiki,jne',
			'origin_whitelist_expedition_services' => '{"ninja":["Standard"],"tiki":["REG"],"jne":["REG"]}',
			'enable_insurance' => 'no',
		);
		public function prepare( $sql, $key ) { return $key; }
		public function get_row( $key ) { return isset( $this->rows[$key] ) ? (object) array( 'key' => $key, 'value' => $this->rows[$key] ) : null; }
	}
	final class BuyerSwitchSession {
		public $values = array();
		public function get( $key, $default = null ) { return $this->values[$key] ?? $default; }
		public function set( $key, $value ) { $this->values[$key] = $value; }
	}
	#[\AllowDynamicProperties]
	class WC_Shipping_Method {
		public $rates = array();
		public $settings = array();
		public function init_settings() { $this->settings = array( 'enabled' => 'yes' ); }
		public function get_option( $key, $default = '' ) { return $this->settings[$key] ?? $default; }
		public function add_rate( $rate ) { $this->rates[$rate['id']] = $rate; }
	}
	function WC() { return $GLOBALS['buyer_switch_wc']; }
	function add_action( ...$args ) {}
	function add_filter( ...$args ) {}
	function __( $text, $domain = '' ) { return $text; }
	function absint( $value ) { return abs( (int) $value ); }
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
	function wp_json_encode( $value ) { return json_encode( $value, JSON_THROW_ON_ERROR ); }
	function get_transient( $key ) { return $GLOBALS['buyer_switch_transients'][$key] ?? false; }
	function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['buyer_switch_transients'][$key] = $value; return true; }
	function kiriof_log( ...$args ) {}
	function wc_price( $value ) { return (string) $value; }
	function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
	function kiriof_money_format( $value ) { return number_format( $value ); }
	function kiriof_helper() { return new class {
		public function formatServiceName( $courier, $service ) { return strtoupper( $courier ) . ' ' . $service; }
	}; }
	function kiriof_setting_repository() { return new \KiriminAjaOfficial\Repositories\SettingRepository(); }
	function kiriof_checkout_service_factory() { return $GLOBALS['buyer_switch_factory']; }
	function kiriof_api_repository() { return $GLOBALS['buyer_switch_api']; }
	function buyer_switch_network( ...$args ) {
		++$GLOBALS['buyer_switch_network'];
		throw new \RuntimeException( 'Unexpected outgoing WordPress HTTP request.' );
	}
	function wp_remote_post( ...$args ) { return buyer_switch_network( ...$args ); }
	function wp_remote_get( ...$args ) { return buyer_switch_network( ...$args ); }
	function wp_remote_request( ...$args ) { return buyer_switch_network( ...$args ); }
	function wp_safe_remote_post( ...$args ) { return buyer_switch_network( ...$args ); }
	function wp_safe_remote_get( ...$args ) { return buyer_switch_network( ...$args ); }

	final class BuyerSwitchAttributes extends \KiriminAjaOfficial\Services\UtilServices\GetWCCartAttributeService {
		public function __construct() {}
		public function call() { return new \KiriminAjaOfficial\Utils\ServiceResponse( array( 'weight' => 1000, 'length' => 10, 'width' => 10, 'height' => 10, 'item_value' => 20000 ), 'success', 200 ); }
	}
	final class BuyerSwitchFactory extends \KiriminAjaOfficial\Services\CheckoutServiceFactory {
		public function __construct() {}
		public function districtSearch( string $postcode ): \KiriminAjaOfficial\Utils\ServiceResponse {
			$GLOBALS['buyer_switch_lookups'][] = $postcode;
			return new \KiriminAjaOfficial\Utils\ServiceResponse( array( array( 'id' => 456, 'text' => 'Canonical district' ) ), 'success', 200 );
		}
		public function cartAttributes( array $payload ): \KiriminAjaOfficial\Services\UtilServices\GetWCCartAttributeService { return new BuyerSwitchAttributes(); }
	}
	final class BuyerSwitchExpressApi extends \KiriminAjaOfficial\Repositories\KiriminajaApiRepository {
		public function __construct() {}
		public function getPricing( $payload ) {
			$GLOBALS['buyer_switch_requests'][] = $payload;
			return array( 'status' => true, 'data' => buyer_switch_mixed_pricing() );
		}
	}
	function buyer_switch_mixed_pricing() {
		$rows = array();
		foreach ( array(
			array( 'ninja', 'Standard', 'express', 18000, 1000, $GLOBALS['buyer_switch_input']['ninja_cod'] ),
			array( 'tiki', 'REG', 'express', 22000, 500, true ),
			array( 'jne', 'REG', 'express', 24000, 0, true ),
			array( 'jne', 'REG', 'instant', 100, 0, true ),
			array( 'gosend', 'instant', 'instant', 200, 0, true ),
		) as $row ) {
			// Deliberately omit setting/COD fees: Ninja capability comes ONLY from API cod.
			$rows[] = (object) array( 'service' => $row[0], 'service_type' => $row[1], 'service_name' => $row[1], 'type' => $row[2], 'cost' => $row[3], 'discount_amount' => $row[4], 'cod' => $row[5], 'etd' => '1-2' );
		}
		return (object) array( 'status' => true, 'results' => $rows );
	}
	$input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
	$GLOBALS['buyer_switch_input'] = $input;
	$wpdb = new BuyerSwitchDb();
	$GLOBALS['buyer_switch_factory'] = new BuyerSwitchFactory();
	$GLOBALS['buyer_switch_api'] = new BuyerSwitchExpressApi();
	$GLOBALS['buyer_switch_wc'] = (object) array(
		'session' => new BuyerSwitchSession(),
		'customer' => new class {
			public function get_shipping_country() { return 'ID'; }
			public function get_meta( $key ) { throw new \RuntimeException( 'Modern destination must not fall back to customer meta.' ); }
		},
		'cart' => new class {
			public function needs_shipping() { return true; }
			public function get_coupons() { return array(); }
			public function get_applied_coupons() { return array(); }
		},
	);
	$GLOBALS['buyer_switch_transients']['kiriof_couriers_all_v1'] = array_map( static function ( $code ) {
		return array( 'code' => $code, 'name' => strtoupper( $code ), 'type' => 'express', 'services' => array( array( 'code' => 'ninja' === $code ? 'Standard' : 'REG', 'name' => 'Regular' ) ) );
	}, array( 'ninja', 'tiki', 'jne' ) );
	$repo = kiriof_setting_repository();
	$controller = ( new \ReflectionClass( \KiriminAjaOfficial\Controllers\CheckoutController::class ) )->newInstanceWithoutConstructor();
	( new \ReflectionProperty( $controller, 'setting_repository' ) )->setValue( $controller, $repo );
	( new \ReflectionProperty( $controller, 'checkout_service_factory' ) )->setValue( $controller, $GLOBALS['buyer_switch_factory'] );
	$address = array( 'address_1' => 'Jalan Merdeka No. 123', 'address_2' => 'Unit 7', 'city' => 'Jakarta', 'state' => 'JK', 'postcode' => '12345', 'country' => 'ID' );
	$destination = array( 'district_id' => '456', 'district_label' => 'Untrusted display label', 'postcode' => '12345', 'country' => 'ID', 'address_type' => 'shipping', 'version' => 2, 'shipping_address' => $address, 'destination_latitude' => '-6.2', 'destination_longitude' => '106.8' );
	require_once ABSPATH . 'wc/KiriminajaShippingMethod.php';
	kiriof_shipping_method();
	$history = array();
	foreach ( array( array( 'ninja_Standard', 'bacs' ), array( 'tiki_REG', 'bacs' ), array( 'tiki_REG', 'cod' ), array( 'ninja_Standard', 'bacs' ) ) as $step ) {
		// Woo owns the current selection; a delayed extension request still carries the old one.
		WC()->session->set( 'chosen_shipping_methods', array( 'kiriminaja-official_' . $step[0] ) );
		$controller->kiriof_store_api_update_checkout( array(
			'action' => 'sync_checkout', 'destination' => $destination, 'payment_method' => $step[1],
			'insurance' => $input['insured'], 'force_insurance' => $input['insured'],
			'shipping_metode_id' => 'kiriminaja-official_jne_REG',
		) );
		// Each calculation is a fresh Woo rate collection, not stale add_rate entries.
		$method = new \Kiriof_Shipping_Method_Controller( 7 );
		$method->calculate_shipping( array( 'contents' => array(), 'destination' => $address ) );
		$history[] = array( 'rates' => $method->rates, 'session' => WC()->session->values, 'api_calls' => count( $GLOBALS['buyer_switch_requests'] ) );
	}
	// Invalid producer graphs must never poison another payload/cache entry.
	$invalid_results = array();
	foreach ( array( (object) array( 'status' => false, 'results' => buyer_switch_mixed_pricing()->results ),
		(object) array( 'status' => true, 'results' => 'invalid' ),
		(object) array( 'status' => true, 'results' => array( array( 'service' => 'ninja' ) ) ),
		array( 'status' => true, 'results' => array() ),
		(object) array( 'status' => true ) ) as $index => $invalid ) {
		$payload = $GLOBALS['buyer_switch_requests'][0];
		$payload['subdistrict_destination'] = 900 + $index;
		\KiriminAjaOfficial\Services\CheckoutServices\PricingCacheService::put( $payload, $invalid );
		$invalid_results[] = \KiriminAjaOfficial\Services\CheckoutServices\PricingCacheService::get( $payload );
	}
	echo json_encode( array( 'history' => $history, 'pricing_payloads' => $GLOBALS['buyer_switch_requests'],
		'lookup_calls' => $GLOBALS['buyer_switch_lookups'], 'invalid_cache_results' => $invalid_results,
		'network_calls' => $GLOBALS['buyer_switch_network'], 'instant_constructions' => $GLOBALS['buyer_switch_constructions'], 'warnings' => $GLOBALS['buyer_switch_warnings'],
	), JSON_THROW_ON_ERROR );
}
