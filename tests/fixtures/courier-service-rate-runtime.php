<?php
/** Isolated WP/WC boundary stubs; policy and rate consumers are production classes. */
// Production BaseService has legacy nullable signatures deprecated on PHP 8.4+.
// Keep warnings/errors visible, but prevent unrelated deprecations corrupting JSON.
error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require_once ABSPATH . 'vendor/autoload.php';

final class CourierRateDb {
	public $prefix = 'rate_';
	public $last_error = '';
	public $posts = 'rate_posts';
	public $postmeta = 'rate_postmeta';
	public $rows = array( 'origin_whitelist_expedition_id' => 'jne', 'origin_sub_district_id' => '123' );
	public function prepare( $sql, $keys ) { return $keys; }
	public function get_row( $key ) { return isset( $this->rows[ $key ] ) ? (object) array( 'key' => $key, 'value' => $this->rows[ $key ] ) : null; }
	public function get_results( $keys ) {
		return array_values( array_filter( array_map( array( $this, 'get_row' ), $keys ) ) );
	}
	public function get_var( $sql ) { return 0; }
	public function query( $sql ) { return true; }
	public function insert( $table, $data, $formats ) { $this->rows[ $data['key'] ] = $data['value']; return 1; }
	public function update( $table, $data, $where, ...$formats ) { $this->rows[ $where['key'] ] = $data['value']; return 1; }
}
final class CourierRateSession {
	private $values = array( 'destination_id' => 456, 'chosen_payment_method' => 'bacs' );
	public function get( $key, $default = null ) { return $this->values[ $key ] ?? $default; }
	public function set( $key, $value ) { $this->values[ $key ] = $value; }
}
final class CourierRateCoupon {
	public function get_free_shipping() { return true; }
}
final class CourierRateCart {
	public $coupon_reads = 0;
	public $free_shipping = true;
	public $fees = array();
	public function needs_shipping() { return true; }
	public function get_cart() { return array(); }
	public function add_fee( $name, $amount, $taxable = false ) { $this->fees[] = array( 'name' => $name, 'amount' => $amount ); }
	public function get_cart_hash() { return 'unchanged-cart'; }
	public function get_coupons() { ++$this->coupon_reads; return $this->free_shipping ? array( 'free' => new CourierRateCoupon() ) : array(); }
	public function get_shipping_total() { return 0; }
	public function get_cart_contents_total() { return 10000; }
	public function get_discount_total() { return 0; }
	public function get_discount_tax() { return 0; }
}
final class CourierRateCountries {
	public function get_shipping_countries() { return array( 'ID' => 'Indonesia' ); }
}
function WC() { return $GLOBALS['rate_wc']; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return strtolower( $value ); }
function wp_json_encode( $value ) { return json_encode( $value, JSON_THROW_ON_ERROR ); }
function get_transient( $key ) { return false; }
function get_option( $key, $default = false ) { return $default; }
function __( $text, $domain = '' ) { return $text; }
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function kiriof_log( ...$args ) {}
function plugin_dir_path( $path ) { return dirname( $path ) . '/'; }
function plugin_dir_url( $path ) { return ''; }
function plugin_basename( $path ) { return basename( $path ); }
function home_url() { return 'https://example.test'; }
function set_transient( ...$args ) { return true; }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function wc_price( $amount ) { return (string) $amount; }
class WC_Shipping_Method {
	public $id = 'kiriminaja-official';
	public $rates = array();
	public function add_rate( $rate ) { $this->rates[ $rate['id'] ] = $rate; }
}
function kiriof_get_tracking_page_id() { return 0; }
function kiriof_money_format( $value ) { return number_format( $value ); }
function kiriof_helper() {
	return new class {
		public function formatServiceName( $courier, $service ) { return strtoupper( $courier ) . ' ' . $service; }
	};
}
function wp_remote_post( ...$args ) { ++$GLOBALS['rate_network_calls']; throw new RuntimeException( 'Unexpected network request' ); }
function wp_remote_get( ...$args ) { ++$GLOBALS['rate_network_calls']; throw new RuntimeException( 'Unexpected network request' ); }

foreach ( array( 'Utils/ServiceResponse', 'Utils/Volumetric', 'Base/BaseService', 'Services/CourierServiceCatalog', 'Repositories/SettingRepository', 'Services/UtilServices/GetWCCartAttributeService', 'Services/CheckoutServices/CheckoutCalculationService', 'Services/CheckoutServices/OngkirPricingService', 'Services/OnboardingSetupStateService', 'Controllers/CheckoutController', 'Controllers/GeneralAjaxController' ) as $file ) {
	require_once ABSPATH . 'inc/' . $file . '.php';
}

use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Services\CheckoutServices\CheckoutCalculationService;
use KiriminAjaOfficial\Services\CheckoutServices\OngkirPricingService;
use KiriminAjaOfficial\Controllers\CheckoutController;
use KiriminAjaOfficial\Services\OnboardingSetupStateService;

$wpdb = new CourierRateDb();
$GLOBALS['rate_wc'] = (object) array( 'session' => new CourierRateSession(), 'cart' => new CourierRateCart(), 'countries' => new CourierRateCountries() );
$GLOBALS['rate_network_calls'] = 0;
$repo = new SettingRepository();
// API construction configures the SDK; none of these scenarios needs remote rates.
// Inject an explicit fail-fast API boundary rather than silently allowing network calls.
$GLOBALS['rate_api'] = new class extends \KiriminAjaOfficial\Repositories\KiriminajaApiRepository {
    public function __construct() {}
    public function get_pricing( $payload ) { return (object) array( 'status' => false, 'results' => array() ); }
};
$GLOBALS['rate_meta'] = new \KiriminAjaOfficial\Repositories\WpPostMetaRepository();
function rate_service( string $class, array $payload ) {
    return new $class( $payload, $GLOBALS['repo'], $GLOBALS['rate_api'], $GLOBALS['rate_meta'] );
}
function rate_factory(): \KiriminAjaOfficial\Services\CheckoutServiceFactory {
    $transactions = new \KiriminAjaOfficial\Repositories\TransactionRepository();
    $cod = new class extends \KiriminAjaOfficial\Repositories\CodFeeApiRepository {
        public function __construct() {}
    };
    $factory = new \KiriminAjaOfficial\Services\CheckoutServiceFactory(
        $GLOBALS['repo'], $transactions, $GLOBALS['rate_meta'], $GLOBALS['rate_api'], $cod,
        new \KiriminAjaOfficial\Services\ShipmentLocationService( null, $GLOBALS['repo'] )
    );
    return $factory;
}
function rate_controller(): CheckoutController {
    return new CheckoutController( $GLOBALS['repo'], new \KiriminAjaOfficial\Repositories\TransactionRepository(), $GLOBALS['rate_meta'], rate_factory() );
}
function kiriof_setting_repository() { return $GLOBALS['repo']; }
function kiriof_checkout_service_factory() { return rate_factory(); }
function kiriof_api_repository() { return $GLOBALS['rate_api']; }
function rate_policy( $selection ) {
	( new SettingRepository() )->storeCourierWhitelist( array( 'origin_whitelist_expedition_id' => 'jne', 'service_selection' => $selection ) );
}
function rate_rows() {
	return array(
		(object) array( 'id' => 'raw-allowed', 'service' => 'jne', 'service_type' => 'REG23', 'service_name' => 'JNE Reguler Display', 'cost' => 10000, 'discount_amount' => 2000 ),
		(object) array( 'id' => 'raw-disabled', 'service' => 'jne', 'service_type' => 'YES', 'service_name' => 'REG', 'cost' => 20000 ),
		(object) array( 'id' => 'legacy-name', 'service' => 'jne', 'service_name' => 'REG' ),
		array( 'id' => 'array-row', 'service' => 'jne', 'service_type' => 'REG', 'service_name' => 'Display' ),
		(object) array( 'id' => 'other-courier', 'service' => 'jnt', 'service_type' => 'EZ' ),
	);
}
function rate_selected( $expedition, $rows ) {
	$service = rate_service( CheckoutCalculationService::class, array( 'expedition' => $expedition ) );
	( new ReflectionProperty( $service, 'pricingData' ) )->setValue( $service, (object) array( 'results' => $rows ) );
	return ( new ReflectionMethod( $service, 'getSelectedExpedition' ) )->invoke( $service );
}
function rate_calculation( $expedition ) {
	return ( rate_service( CheckoutCalculationService::class, array( 'expedition' => $expedition, 'is_cod' => true, 'is_insurance' => true ) ) )->call();
}
function rate_checkout_fees( $method, $legacy_context = false ) {
	WC()->cart->fees = array();
	WC()->session->set( 'chosen_shipping_methods', array( $method ) );
	WC()->session->set( 'chosen_payment_method', 'cod' );
	WC()->session->set( 'kiriof_insurance', 1 );
	$context = ( new ReflectionMethod( \KiriminAjaOfficial\Controllers\GeneralAjaxController::class, 'kiriof_get_fee_cache_context' ) )->invoke(
		new \KiriminAjaOfficial\Controllers\GeneralAjaxController( rate_factory() ), $method, 456, 'cod', 1
	);
	if ( $legacy_context ) {
		unset( $context['courier_services'] );
	}
	WC()->session->set( 'kiriof_cached_insurance_amt', 1500 );
	WC()->session->set( 'kiriof_cached_cod_amt', 2500 );
	WC()->session->set( 'kiriof_cached_fee_context', $context );
	( rate_controller() )->kiriof_shipping_method_update();
	return array(
		'fees' => WC()->cart->fees,
		'insurance' => WC()->session->get( 'kiriof_cached_insurance_amt' ),
		'cod' => WC()->session->get( 'kiriof_cached_cod_amt' ),
		'context' => WC()->session->get( 'kiriof_cached_fee_context' ),
	);
}

switch ( $argv[1] ?? '' ) {
	case 'validator':
		$rows = rate_rows();
		$result['legacy'] = array_column( $repo->validateWhiteListExpedition( $rows ), 'id' );
		rate_policy( '{"jne":["REG"]}' );
		$result['explicit'] = array_column( $repo->validateWhiteListExpedition( $rows ), 'id' );
		rate_policy( '{}' );
		$result['deny_all'] = $repo->validateWhiteListExpedition( $rows );
		break;
	case 'options':
		$service = rate_service( OngkirPricingService::class, array( 'is_cod' => false, 'destination_area_id' => 456 ) );
		$filter = new ReflectionMethod( $service, 'filterOptions' );
		$pricing = (object) array( 'results' => array_slice( rate_rows(), 0, 2 ) );
		rate_policy( '{"jne":["REG"]}' );
		$result['regular'] = $filter->invoke( $service, $pricing );
		rate_policy( '{"jne":["YES"]}' );
		$result['express'] = $filter->invoke( $service, $pricing );
		rate_policy( '{}' );
		$result['deny_all'] = $filter->invoke( $service, $pricing );
		break;
	case 'selected':
		rate_policy( '{"jne":["REG"]}' );
		$rows = array_slice( rate_rows(), 0, 2 );
		$result['disabled'] = rate_selected( 'jne_YES', $rows );
		$result['allowed'] = rate_selected( 'JNE_reg23', $rows );
		rate_policy( '{}' );
		$result['deny_all'] = rate_selected( 'jne_REG23', $rows );
		break;
	case 'calculation':
		rate_policy( '{"jne":["REG"]}' );
		$result['disabled'] = rate_calculation( 'jne_YES' );
		rate_policy( '{}' );
		$result['deny_all'] = rate_calculation( 'jne_REG23' );
		$result['deny_all_without_service'] = rate_calculation( 'jne' );
		$result['guard_coupon_reads'] = WC()->cart->coupon_reads;
		rate_policy( '{"jne":["REG"]}' );
		$result['allowed'] = rate_calculation( 'jne_REG23' );
		break;
	case 'cache':
		$controller = rate_controller();
		$package = array( array( 'destination' => array( 'country' => 'ID', 'postcode' => '12345' ) ) );
		rate_policy( '{"jne":["REG"]}' );
		$result['couriers_before'] = $repo->getWhitelistExpeditionIds();
		$regular = $controller->kiriof_shipping_rate_cache_invalidation( $package );
		$result['regular'] = $regular[0]['rate_cache'];
		$result['unchanged'] = $controller->kiriof_shipping_rate_cache_invalidation( $package )[0]['rate_cache'];
		rate_policy( '{"jne":["YES"]}' );
		$result['couriers_after'] = $repo->getWhitelistExpeditionIds();
		$result['express'] = $controller->kiriof_shipping_rate_cache_invalidation( $package )[0]['rate_cache'];
		rate_policy( '{}' );
		$result['deny_all'] = $controller->kiriof_shipping_rate_cache_invalidation( $package )[0]['rate_cache'];
		$result['destination'] = $package[0]['destination'];
		$result['returned_destination'] = $regular[0]['destination'];
		$legacy_context = array(
			'cart_hash' => WC()->cart->get_cart_hash(),
			'destination' => $package[0]['destination'],
			'destination_id' => 456,
			'insurance' => 0,
			'payment_method' => ( new ReflectionMethod( $controller, 'kiriof_get_checkout_payment_method' ) )->invoke( $controller ),
			'coupon_context' => ( new ReflectionMethod( $controller, 'kiriof_get_cart_discount_context' ) )->invoke( $controller ),
			'courier_filter' => array( 'jne' ),
			'courier_services' => array( 'jne' => array( 'REG' ) ),
		);
		$result['legacy_country_policy'] = md5( wp_json_encode( $legacy_context ) );
		$package[0]['destination']['country'] = 'IE';
		$result['foreign_country'] = $controller->kiriof_shipping_rate_cache_invalidation( $package )[0]['rate_cache'];
		$result['network_calls'] = $GLOBALS['rate_network_calls'];
		break;
	case 'recipient_cache':
        $controller = rate_controller();
        $address = array('address_1' => 'Buyer street', 'address_2' => '', 'city' => 'Jakarta', 'state' => 'JK', 'postcode' => '12345', 'country' => 'ID');
        WC()->customer = new class($address) {
            public array $billing;
            public function __construct($address) { $this->billing = $address + array('first_name' => '', 'last_name' => '', 'phone' => ''); }
            public function __call($name, $args) { return str_starts_with($name, 'get_billing_') ? ($this->billing[substr($name, 12)] ?? '') : ''; }
        };
        $package = array(array('destination' => $address));
        $result['invalid'] = $controller->kiriof_shipping_rate_cache_invalidation($package)[0]['rate_cache'];
        WC()->customer->billing['first_name'] = 'Buyer';
        $result['named'] = $controller->kiriof_shipping_rate_cache_invalidation($package)[0]['rate_cache'];
        WC()->customer->billing['phone'] = '081234567890';
        $result['complete'] = $controller->kiriof_shipping_rate_cache_invalidation($package)[0]['rate_cache'];
        $package[0]['destination']['phone'] = '';
        $result['explicit_empty'] = $controller->kiriof_shipping_rate_cache_invalidation($package)[0]['rate_cache'];
        WC()->customer->billing['phone'] = '081234567899';
        $result['explicit_empty_again'] = $controller->kiriof_shipping_rate_cache_invalidation($package)[0]['rate_cache'];
        $result['returned_destination'] = $controller->kiriof_shipping_rate_cache_invalidation($package)[0]['destination'];
        $result['network_calls'] = $GLOBALS['rate_network_calls'];
        break;
	case 'fees':
		rate_policy( '{"jne":["REG"]}' );
		$result['disabled_legacy'] = rate_checkout_fees( 'kiriminaja-official_jne_YES', true );
		$result['disabled_current'] = rate_checkout_fees( 'kiriminaja-official_jne_YES' );
		$result['allowed_alias'] = rate_checkout_fees( 'kiriminaja-official_JNE_reg23' );
		$result['allowed_colon_alias'] = rate_checkout_fees( 'kiriminaja-official:jne_REG23' );
		$result['allowed_legacy_context'] = rate_checkout_fees( 'kiriminaja-official_jne_REG23', true );
		rate_policy( '{}' );
		$result['deny_all'] = rate_checkout_fees( 'kiriminaja-official_jne_REG23' );
		rate_policy( '{"ninja":["*"]}' );
		$result['international'] = rate_checkout_fees( 'kiriminaja-official_ninja_inter_Standard' );
		$result['network_calls'] = $GLOBALS['rate_network_calls'];
		break;
	case 'readiness':
		$result['legacy'] = ( new OnboardingSetupStateService() )->get_steps()['couriers']['done'];
		rate_policy( '{}' );
		$result['deny_all'] = ( new OnboardingSetupStateService() )->get_steps()['couriers']['done'];
		rate_policy( '{"jne":[]}' );
		$result['empty_services'] = ( new OnboardingSetupStateService() )->get_steps()['couriers']['done'];
		rate_policy( '{"jne":["REG"]}' );
		$result['enabled'] = ( new OnboardingSetupStateService() )->get_steps()['couriers']['done'];
		break;
	case 'destination_country':
		require_once ABSPATH . 'inc/Services/CheckoutServices/PricingCacheService.php';
		require_once ABSPATH . 'inc/Services/ShippingDiscountCouponService.php';
		require_once ABSPATH . 'wc/KiriminajaShippingMethod.php';
		kiriof_shipping_method();
		$payload = array(
			'subdistrict_origin' => 123,
			'subdistrict_destination' => 456,
			'weight' => 0,
			'length' => 0,
			'width' => 0,
			'height' => 0,
			'insurance' => 0,
			'item_value' => 10000,
			'courier' => array( 'jne' ),
		);
		$pricing = (object) array( 'status' => true, 'results' => array( (object) array(
			'service' => 'jne',
			'service_type' => 'REG',
			'service_name' => 'Regular',
			'cost' => 12000,
			'discount_amount' => 0,
		) ) );
		\KiriminAjaOfficial\Services\CheckoutServices\PricingCacheService::put( $payload, $pricing );
		WC()->session->set( 'shipping_destination_id', 456 );
		WC()->customer = new class {
			public function get_shipping_country() { return 'ID'; }
			public function get_billing_country() { return 'IE'; }
		};
		foreach ( array( false, true ) as $free_shipping ) {
			WC()->cart->free_shipping = $free_shipping;
			foreach ( array( 'indonesia' => 'ID', 'ireland' => 'IE', 'us' => 'US', 'empty' => '', 'missing' => null, 'malformed' => array( 'ID' ) ) as $name => $country ) {
				$destination = array( 'address_1' => 'Jalan Jakarta Nomor 123' );
				if ( null !== $country ) {
					$destination['country'] = $country;
				}
				WC()->session->set( 'kiriof_shipping_coupon_rate_meta', array( 'stale' => array( 'cost' => 12000 ) ) );
				WC()->cart->coupon_reads = 0;
				$method = ( new ReflectionClass( 'Kiriof_Shipping_Method_Controller' ) )->newInstanceWithoutConstructor();
				$method->calculate_shipping( array( 'contents' => array(), 'destination' => $destination ) );
				$result[ $free_shipping ? 'free' : 'paid' ][ $name ] = array(
					'rates' => array_values( $method->rates ),
					'meta' => WC()->session->get( 'kiriof_shipping_coupon_rate_meta' ),
					'coupon_reads' => WC()->cart->coupon_reads,
				);
			}
		}
		WC()->cart->free_shipping = false;
		$method = ( new ReflectionClass( 'Kiriof_Shipping_Method_Controller' ) )->newInstanceWithoutConstructor();
		foreach ( array( 'ID', 'IE', 'ID' ) as $country ) {
			$method->rates = array();
			$method->calculate_shipping( array( 'contents' => array(), 'destination' => array( 'country' => $country, 'address_1' => 'Jalan Jakarta Nomor 123' ) ) );
			$result['transition'][] = array_keys( $method->rates );
		}
		$result['network_calls'] = $GLOBALS['rate_network_calls'];
		break;
	default:
		throw new InvalidArgumentException( 'Unknown fixture action' );
}
echo json_encode( $result, JSON_THROW_ON_ERROR );
