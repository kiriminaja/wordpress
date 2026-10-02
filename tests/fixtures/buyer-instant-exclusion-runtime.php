<?php
/** Isolated Woo boundary; destination sync, courier policy, cache and rate filters are production. */
namespace KiriminAjaOfficial\Services {
	// Only coupon arithmetic is replaced: this records every row admitted to pricing.
	class ShippingDiscountCouponService {
		public function getAdjustedRatePricing( $row, float $cost ): array {
			$GLOBALS['buyer_instant_coupon_rows'][] = array( 'service' => $row->service, 'service_type' => $row->service_type, 'type' => $row->type );
			return array( 'cost' => $cost - 500, 'original_cost' => $cost, 'discount_amount' => 500, 'notice' => 'Fixture coupon', 'badge' => 'Discount' );
		}
	}
}
namespace {
    function do_action( $hook, ...$args ) {}
	error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	require_once ABSPATH . 'vendor/autoload.php';
	$GLOBALS['buyer_instant_warnings'] = array();
	$GLOBALS['buyer_instant_network'] = 0;
	$GLOBALS['buyer_instant_constructions'] = 0;
	$GLOBALS['buyer_instant_requests'] = array();
	$GLOBALS['buyer_instant_coupon_rows'] = array();
	$GLOBALS['buyer_instant_lookups'] = array();
	$GLOBALS['buyer_instant_transients'] = array();
	set_error_handler( static function ( $severity, $message ) {
		if ( error_reporting() & $severity ) { $GLOBALS['buyer_instant_warnings'][] = $message; }
		return true;
	} );
	// Prepend to Composer: even constructing the Instant client is a boundary violation.
	spl_autoload_register( static function ( $class ) {
		if ( 'KiriminAjaOfficial\\Repositories\\InstantDeliveryApiRepository' === $class ) {
			++$GLOBALS['buyer_instant_constructions'];
			throw new \RuntimeException( 'Buyer Express checkout must not construct the Instant API repository.' );
		}
	}, true, true );

	final class BuyerInstantDb {
		public $prefix = 'buyer_instant_';
		public $last_error = '';
		public $rows = array(
			'origin_sub_district_id' => '123',
			'origin_whitelist_expedition_id' => 'jne,gosend,grab_express',
			'origin_whitelist_expedition_services' => '{"jne":["REG"],"gosend":["instant"],"grab_express":["instant"]}',
			'enable_insurance' => 'no',
		);
		public function prepare( $sql, $key ) { return $key; }
		public function get_row( $key ) { return isset( $this->rows[$key] ) ? (object) array( 'key' => $key, 'value' => $this->rows[$key] ) : null; }
	}
	final class BuyerInstantSession {
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
	function WC() { return $GLOBALS['buyer_instant_wc']; }
	function add_action( ...$args ) {}
	function add_filter( ...$args ) {}
	function __( $text, $domain = '' ) { return $text; }
	function absint( $value ) { return abs( (int) $value ); }
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
	function wp_json_encode( $value ) { return json_encode( $value, JSON_THROW_ON_ERROR ); }
	function get_transient( $key ) { return $GLOBALS['buyer_instant_transients'][$key] ?? false; }
	function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['buyer_instant_transients'][$key] = $value; return true; }
	function kiriof_log( ...$args ) {}
	function wc_price( $value ) { return (string) $value; }
	function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
	function kiriof_money_format( $value ) { return number_format( $value ); }
	function kiriof_helper() { return new class {
		public function formatServiceName( $courier, $service ) { return strtoupper( $courier ) . ' ' . $service; }
	}; }
	function kiriof_setting_repository() { return new \KiriminAjaOfficial\Repositories\SettingRepository(); }
	function kiriof_checkout_service_factory() { return $GLOBALS['buyer_instant_factory']; }
	function kiriof_api_repository() { return $GLOBALS['buyer_instant_api']; }
	function buyer_instant_network( ...$args ) {
		++$GLOBALS['buyer_instant_network'];
		throw new \RuntimeException( 'Unexpected outgoing WordPress HTTP request.' );
	}
	function wp_remote_post( ...$args ) { return buyer_instant_network( ...$args ); }
	function wp_remote_get( ...$args ) { return buyer_instant_network( ...$args ); }
	function wp_remote_request( ...$args ) { return buyer_instant_network( ...$args ); }
	function wp_safe_remote_post( ...$args ) { return buyer_instant_network( ...$args ); }
	function wp_safe_remote_get( ...$args ) { return buyer_instant_network( ...$args ); }

	final class BuyerInstantAttributes extends \KiriminAjaOfficial\Services\UtilServices\GetWCCartAttributeService {
		public function __construct() {}
		public function call() { return new \KiriminAjaOfficial\Utils\ServiceResponse( array( 'weight' => 1000, 'length' => 10, 'width' => 10, 'height' => 10, 'item_value' => 20000 ), 'success', 200 ); }
	}
	final class BuyerInstantFactory extends \KiriminAjaOfficial\Services\CheckoutServiceFactory {
		public function __construct() {}
		public function districtSearch( string $postcode ): \KiriminAjaOfficial\Utils\ServiceResponse {
			$GLOBALS['buyer_instant_lookups'][] = $postcode;
			return new \KiriminAjaOfficial\Utils\ServiceResponse( array( array( 'id' => 456, 'text' => 'Canonical district' ) ), 'success', 200 );
		}
		public function cartAttributes( array $payload ): \KiriminAjaOfficial\Services\UtilServices\GetWCCartAttributeService { return new BuyerInstantAttributes(); }
	}
	final class BuyerInstantExpressApi extends \KiriminAjaOfficial\Repositories\KiriminajaApiRepository {
		public function __construct() {}
		public function getPricing( $payload ) {
			$GLOBALS['buyer_instant_requests'][] = $payload;
			return array( 'status' => true, 'data' => buyer_instant_mixed_pricing() );
		}
	}
	function buyer_instant_mixed_pricing() {
		$rows = array();
		// Includes known Instant codes with a false Express type, and a valid JNE
		// code/service tagged Instant. None may survive a buyer Express filter.
		foreach ( array(
			array( 'jne', 'REG', 'express', 10000 ),
			array( 'gosend', 'instant', 'instant', 100 ),
			array( 'grab_express', 'instant', 'instant', 200 ),
			array( 'gosend', 'instant', 'express', 300 ),
			array( 'grab_express', 'instant', 'express', 400 ),
			array( 'borzo', 'instant', 'instant', 500 ),
			array( 'unknown_courier', 'instant', 'instant', 600 ),
			array( 'jne', 'REG', 'instant', 700 ),
		) as $row ) {
			$rows[] = (object) array( 'service' => $row[0], 'service_type' => $row[1], 'service_name' => $row[1], 'type' => $row[2], 'cost' => $row[3], 'discount_amount' => 0, 'cod' => true, 'etd' => '1-2' );
		}
		return (object) array( 'status' => true, 'results' => $rows );
	}

	$input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
	$wpdb = new BuyerInstantDb();
	$GLOBALS['buyer_instant_factory'] = new BuyerInstantFactory();
	$GLOBALS['buyer_instant_api'] = new BuyerInstantExpressApi();
	$GLOBALS['buyer_instant_wc'] = (object) array(
		'session' => new BuyerInstantSession(),
		'customer' => new class {
			public function get_shipping_country() { return 'ID'; }
			public function get_meta( $key ) { throw new \RuntimeException( 'Modern destination must not fall back to customer meta.' ); }
		},
		'cart' => new class {
			public function needs_shipping() { return true; }
			public function get_coupons() { return array(); }
			public function get_applied_coupons() { return array( 'fixture-discount' ); }
		},
	);
	// Account-authorized Instant catalog, not a synthetic policy-only entitlement.
	$GLOBALS['buyer_instant_transients']['kiriof_couriers_all_v1'] = array(
		array( 'code' => 'jne', 'name' => 'JNE', 'type' => 'express', 'services' => array( array( 'code' => 'REG', 'name' => 'Regular' ) ) ),
		array( 'code' => 'gosend', 'name' => 'GoSend', 'type' => 'instant' ),
		array( 'code' => 'grab_express', 'name' => 'GrabExpress', 'type' => 'instant' ),
	);
	$repo = kiriof_setting_repository();
	$controller = ( new \ReflectionClass( \KiriminAjaOfficial\Controllers\CheckoutController::class ) )->newInstanceWithoutConstructor();
	( new \ReflectionProperty( $controller, 'setting_repository' ) )->setValue( $controller, $repo );
	( new \ReflectionProperty( $controller, 'checkout_service_factory' ) )->setValue( $controller, $GLOBALS['buyer_instant_factory'] );
	$selected = array( 'kiriminaja-official_jne_REG' );
	WC()->session->set( 'chosen_shipping_methods', $selected );
	WC()->session->set( 'kiriof_chosen_shipping_methods', $selected );
	WC()->session->set( 'kiriof_expedition', 'jne_REG' );
	$address = array( 'address_1' => 'Jalan Merdeka No. 123', 'address_2' => 'Unit 7', 'city' => 'Jakarta', 'state' => 'JK', 'postcode' => '12345', 'country' => 'ID' );
	$base = array( 'district_id' => '456', 'district_label' => 'Untrusted display label', 'postcode' => '12345', 'country' => 'ID', 'address_type' => 'shipping', 'version' => 2, 'shipping_address' => $address );
	$updates = array(
		'A' => array_merge( $base, array( 'destination_latitude' => '-6.2', 'destination_longitude' => '106.8' ) ),
		'B' => array_merge( $base, array( 'destination_latitude' => 0, 'destination_longitude' => 0 ) ),
		'clear' => array_diff_key( array_merge( $base, array( 'version' => 1 ) ), array( 'shipping_address' => true ) ),
		'empty' => array( 'district_id' => '', 'district_label' => '', 'postcode' => '12345', 'country' => 'ID', 'address_type' => 'shipping', 'version' => 1 ),
	);
	$history = array();
	foreach ( $updates as $name => $destination ) {
		$controller->kiriof_store_api_update_checkout( array(
			'action' => 'sync_checkout', 'destination' => $destination,
			'payment_method' => $input['flags'] ? 'cod' : 'bacs',
			'insurance' => $input['flags'], 'force_insurance' => $input['flags'],
			// A delayed sync must not replace native courier selection.
			'shipping_metode_id' => 'kiriminaja-official_gosend_instant',
		) );
		$history[$name] = array( 'destination' => WC()->session->get( 'kiriof_buyer_destination' ), 'coordinates' => WC()->session->get( 'kiriof_buyer_destination_coordinates' ) );
		if ( $input['stage'] === $name ) { break; }
	}
	$payload = array( 'subdistrict_origin' => 123, 'subdistrict_destination' => '456', 'weight' => 1000, 'length' => 10, 'width' => 10, 'height' => 10, 'insurance' => (int) $input['flags'], 'item_value' => 20000, 'courier' => $repo->getWhitelistExpeditionIds() );
	if ( $input['cached'] ) {
		\KiriminAjaOfficial\Services\CheckoutServices\PricingCacheService::put( $payload, buyer_instant_mixed_pricing() );
	}
	require_once ABSPATH . 'wc/KiriminajaShippingMethod.php';
	kiriof_shipping_method();
	$method = new \Kiriof_Shipping_Method_Controller( 7 );
	// No subclass/filter override: exercise production coupon metadata and add_rate.
	$method->calculate_shipping( array( 'contents' => array(), 'destination' => $address ) );
	// Also exercise the other production Express rate consumer's private filter.
	$pricing = new \KiriminAjaOfficial\Services\CheckoutServices\OngkirPricingService(
		array( 'is_cod' => (bool) $input['flags'], 'destination_area_id' => 456 ),
		$repo, $GLOBALS['buyer_instant_api'], new \KiriminAjaOfficial\Repositories\WpPostMetaRepository()
	);
	$options = ( new \ReflectionMethod( $pricing, 'filterOptions' ) )->invoke( $pricing, buyer_instant_mixed_pricing() );
	echo json_encode( array(
		'rates' => array_values( $method->rates ), 'options' => $options,
		'pricing_payloads' => $GLOBALS['buyer_instant_requests'],
		'coupon_rows' => $GLOBALS['buyer_instant_coupon_rows'],
		'coupon_rate_meta' => WC()->session->get( 'kiriof_shipping_coupon_rate_meta', array() ),
		'history' => $history, 'session' => WC()->session->values,
		'lookup_calls' => $GLOBALS['buyer_instant_lookups'],
		'instant_enabled' => array( $repo->isCourierServiceEnabled( 'gosend', 'instant' ), $repo->isCourierServiceEnabled( 'grab_express', 'instant' ) ),
		'network_calls' => $GLOBALS['buyer_instant_network'], 'instant_constructions' => $GLOBALS['buyer_instant_constructions'],
		'warnings' => $GLOBALS['buyer_instant_warnings'],
	), JSON_THROW_ON_ERROR );
}
