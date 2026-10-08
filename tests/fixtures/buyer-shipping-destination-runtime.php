<?php
/** Isolated Woo boundary: exercise the production shipping destination/rate path. */
error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require_once ABSPATH . 'vendor/autoload.php';
require_once ABSPATH . 'inc/Services/CourierServiceCatalog.php';

$GLOBALS['buyer_shipping_warnings'] = array();
set_error_handler( static function ( $severity, $message ) {
	if ( error_reporting() & $severity ) {
		$GLOBALS['buyer_shipping_warnings'][] = $message;
	}
	return true;
} );

final class BuyerShippingDb {
	public $settings = array( 'origin_whitelist_expedition_id' => 'jne', 'origin_sub_district_id' => '123' );
	public $prefix = 'buyer_shipping_';
	public $last_error = '';
	public function prepare( $sql, $key ) { return $key; }
	public function get_row( $key ) {
		$settings = $this->settings;
		return isset( $settings[ $key ] ) ? (object) array( 'key' => $key, 'value' => $settings[ $key ] ) : null;
	}
}
final class BuyerShippingSession {
	public $values = array();
	public function get( $key, $default = null ) { return $this->values[ $key ] ?? $default; }
	public function set( $key, $value ) { $this->values[ $key ] = $value; }
}
final class BuyerShippingCustomer {
	public $meta_reads = array();
	public function get_meta( $key ) {
		$this->meta_reads[] = $key;
		return 'shipping_kiriof_destination_area' === $key ? '789' : '';
	}
	public function get_meta_data() { return array(); }
}
#[AllowDynamicProperties]
class WC_Shipping_Method {
	public $rates = array();
	public $settings = array();
	public function init_settings() { $this->settings = array( 'enabled' => 'yes' ); }
	public function get_option( $key, $default = '' ) { return $this->settings[ $key ] ?? $default; }
	public function add_rate( $rate ) { $this->rates[ $rate['id'] ] = $rate; }
}
function WC() { return $GLOBALS['buyer_shipping_wc']; }
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function __( $text, $domain = '' ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function wp_json_encode( $value ) { return json_encode( $value, JSON_THROW_ON_ERROR ); }
function get_transient( $key ) { return false; }
function set_transient( ...$args ) { return true; }
function kiriof_log( ...$args ) {}
function wc_price( $value ) { return (string) $value; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function kiriof_setting_repository() { return new \KiriminAjaOfficial\Repositories\SettingRepository(); }
function kiriof_helper() {
	return new class {
		public function formatServiceName( $courier, $service ) { return strtoupper( $courier ) . ' ' . $service; }
	};
}
function kiriof_checkout_service_factory() {
	return new class {
		public function cartAttributes( $payload ) {
			return new class {
				public function call() {
					return (object) array( 'data' => array( 'weight' => 1000, 'length' => 10, 'width' => 10, 'height' => 10, 'item_value' => 10000 ) );
				}
			};
		}
	};
}
function kiriof_api_repository() {
	return new class {
		public function getPricing( $payload ) {
			$GLOBALS['buyer_shipping_pricing'][] = $payload;
			return $GLOBALS['buyer_shipping_response'];
		}
	};
}
function wp_remote_post( ...$args ) { throw new RuntimeException( 'Unexpected network request' ); }
function wp_remote_get( ...$args ) { throw new RuntimeException( 'Unexpected network request' ); }

$wpdb = new BuyerShippingDb();
$GLOBALS['buyer_shipping_pricing'] = array();
$GLOBALS['buyer_shipping_response'] = array( 'status' => true, 'data' => (object) array( 'results' => array( (object) array( 'service' => 'jne', 'service_type' => 'REG', 'service_name' => 'REG', 'cost' => 10000, 'discount_amount' => 1000, 'cod' => true, 'etd' => '2-3', 'insurance' => true, 'insurance_cost' => 500 ) ) ) );
$GLOBALS['buyer_shipping_wc'] = (object) array(
	'session' => new BuyerShippingSession(),
	'customer' => new BuyerShippingCustomer(),
	'cart' => new class {
		public $free_shipping = false;
		public function get_coupons() {
			return $this->free_shipping ? array( new class {
				public function get_free_shipping() { return true; }
			} ) : array();
		}
	},
);
require_once ABSPATH . 'wc/KiriminajaShippingMethod.php';
kiriof_shipping_method();
// Destination resolution, pricing/cache invocation and add_rate remain production.
// Coupon/display filtering is unrelated to this regression and is deterministic here.
final class BuyerDestinationShippingMethod extends Kiriof_Shipping_Method_Controller {
	public function filterOptions( $pricingData, $quantity, $kiriof_insurance = null ) {
		return array_map( static function ( $row ) {
			return array( 'key' => $row->service . '_' . $row->service_type, 'value' => 'JNE REG', 'cost' => $row->cost );
		}, $pricingData->results );
	}
}

$package = array( 'contents' => array(), 'destination' => array( 'address_1' => 'Jalan Merdeka No. 123', 'postcode' => '12345', 'country' => 'ID' ) );
$snapshot = array( 'district_id' => '456', 'postcode' => '12345', 'country' => 'ID' );
WC()->session->set( 'shipping_destination_id', '111' );
WC()->session->set( 'destination_id', '222' );
WC()->session->set( 'kiriof_shipping_coupon_rate_meta', array( 'kiriminaja-official_ninja_STANDARD' => array( 'cost' => 9000 ) ) );
switch ( $argv[1] ?? '' ) {
	case 'free_services':
	case 'free_cod':
	case 'free_enabled_service':
	case 'free_disabled':
	case 'free_empty':
	case 'free_unsupported':
	case 'free_failed':
	case 'free_missing_destination':
		WC()->cart->free_shipping = true;
		WC()->session->set( 'kiriof_buyer_destination', $snapshot );
		WC()->session->set( 'kiriof_insurance', 1 );
		$second = clone $GLOBALS['buyer_shipping_response']['data']->results[0];
		$second->service_type = 'YES';
		$second->service_name = 'YES';
		$second->cost = 20000;
		$second->cod = false;
		$GLOBALS['buyer_shipping_response']['data']->results[] = $second;
		if ( 'free_cod' === $argv[1] ) {
			WC()->session->set( 'chosen_payment_method', 'cod' );
		} elseif ( 'free_enabled_service' === $argv[1] ) {
			$wpdb->settings['origin_whitelist_expedition_services'] = '{"jne":["REG"]}';
		} elseif ( 'free_disabled' === $argv[1] ) {
			$wpdb->settings['origin_whitelist_expedition_services'] = '{}';
		} elseif ( 'free_empty' === $argv[1] ) {
			$GLOBALS['buyer_shipping_response']['data']->results = array();
		} elseif ( 'free_unsupported' === $argv[1] ) {
			foreach ( $GLOBALS['buyer_shipping_response']['data']->results as $row ) { $row->service = 'gosend'; }
		} elseif ( 'free_failed' === $argv[1] ) {
			$GLOBALS['buyer_shipping_response']['status'] = false;
		} elseif ( 'free_missing_destination' === $argv[1] ) {
			$snapshot['district_id'] = '';
			WC()->session->set( 'kiriof_buyer_destination', $snapshot );
		}
		break;
	case 'modern_empty':
		$snapshot['district_id'] = '';
		WC()->session->set( 'kiriof_buyer_destination', $snapshot );
		break;
	case 'modern_positive':
		WC()->session->set( 'kiriof_buyer_destination', $snapshot );
		break;
	case 'postcode_mismatch':
		WC()->session->set( 'kiriof_buyer_destination', $snapshot );
		$package['destination']['postcode'] = '54321';
		break;
	case 'country_mismatch':
		WC()->session->set( 'kiriof_buyer_destination', $snapshot );
		$package['destination']['country'] = 'SG';
		break;
	case 'legacy_shipping_session':
		break;
	case 'legacy_session':
		WC()->session->set( 'shipping_destination_id', '' );
		break;
	case 'legacy_customer':
		WC()->session->set( 'shipping_destination_id', '' );
		WC()->session->set( 'destination_id', '' );
		break;
	default:
		throw new InvalidArgumentException( 'Unknown fixture action' );
}
$method = str_starts_with( $argv[1], 'free_' ) ? new Kiriof_Shipping_Method_Controller( 7 ) : new BuyerDestinationShippingMethod( 7 );
$method->calculate_shipping( $package );
echo json_encode( array(
	'rates' => array_values( $method->rates ),
	'pricing_payloads' => $GLOBALS['buyer_shipping_pricing'],
	'customer_meta_reads' => WC()->customer->meta_reads,
	'coupon_rate_meta' => WC()->session->get( 'kiriof_shipping_coupon_rate_meta' ),
	'warnings' => $GLOBALS['buyer_shipping_warnings'],
	'raw_response' => $GLOBALS['buyer_shipping_response'],
), JSON_THROW_ON_ERROR );
