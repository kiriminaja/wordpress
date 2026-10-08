<?php
/** Isolated logger spy for coupon pricing and region cache paths. */
namespace KiriminAjaOfficial\Repositories {
	class ShippingDiscountRegionRepository {
		public bool $save = true;
		public function upsertProvinces( $rows ) { return $this->save; }
		public function upsertCities( $id, $rows ) { return $this->save; }
		public function getProvinceCount() { return 1; }
		public function getCityCount() { return 1; }
		public function getLatestUpdatedAt() { return '2026-01-01'; }
		public function getLastError() { return 'write failed'; }
	}
}
namespace KiriminAjaOfficial\Services {
	class KiriminajaApiService {
		public bool $fail = false;
		public bool $malformed = false;
		public function getProvinces() {
			return (object) array( 'status' => $this->fail ? 500 : 200, 'message' => 'API unavailable', 'data' => $this->malformed ? array() : array( array( 'id' => 1, 'name' => 'Province' ) ) );
		}
		public function getCitiesByProvinceId( $id ) { return $this->getProvinces(); }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	$root = dirname( __DIR__, 2 );
	$GLOBALS['logs'] = array();
	$GLOBALS['options'] = array();
	$GLOBALS['pending'] = false;
	function kiriof_log( ...$args ) { $GLOBALS['logs'][] = $args; }
	function __( $text, $domain = '' ) { return $text; }
	function sanitize_text_field( $text ) { return $text; }
	function sanitize_key( $text ) { return strtolower( $text ); }
	function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
	function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; }
	function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
	function wp_schedule_single_event( $time, $hook ) { $GLOBALS['pending'] = true; }
	function wp_next_scheduled( $hook ) { return $GLOBALS['pending']; }
	function get_post_meta( $id, $key, $single ) { return '_kiriof_coupon_couriers' === $key ? $GLOBALS['couriers'] : array(); }
	function WC() { return $GLOBALS['wc']; }
	class WC_Coupon {
		public float $amount = 10;
		public function get_discount_type() { return 'kiriof_fixed_shipping_discount'; }
		public function get_id() { return 1; }
		public function get_code() { return 'TEST'; }
		public function get_amount() { return $this->amount; }
	}
	require $root . '/inc/Utils/ServiceResponse.php';
	require $root . '/inc/Base/BaseService.php';
	require $root . '/inc/Services/CourierServiceCatalog.php';
	require $root . '/inc/Services/ShippingDiscountCouponService.php';
	require $root . '/inc/Services/ShippingDiscountRegionCacheService.php';
	class LoggingCouponService extends \KiriminAjaOfficial\Services\ShippingDiscountCouponService {
		public bool $valid = true;
		public function hasActiveFreeShippingCoupon(): bool { return false; }
		public function validateCouponForCart( $coupon, bool $requireSelectedShipping = true, bool $requireSelectedCourier = true ): array {
			return array( 'valid' => $this->valid, 'message' => 'Expected ineligible coupon' );
		}
	}
	$scenario = $argv[1];
	if ( 0 === strpos( $scenario, 'coupon_' ) ) {
		$coupon = new WC_Coupon();
		$cart = new class {
			public array $coupons = array();
			public function get_coupons() { return $this->coupons; }
		};
		$GLOBALS['wc'] = (object) array( 'cart' => $cart );
		$GLOBALS['couriers'] = 'coupon_mismatch' === $scenario ? array( 'jne' ) : array();
		$cart->coupons = 'coupon_none' === $scenario ? array() : array( $coupon );
		$coupon->amount = 'coupon_zero_amount' === $scenario ? 0 : 10;
		$service = new LoggingCouponService();
		$service->valid = 'coupon_invalid' !== $scenario;
		$result = $service->getAdjustedRatePricing( (object) array( 'service' => 'jnt' ), 'coupon_zero_cost' === $scenario ? 0 : 100 );
	} else {
		$repo = new \KiriminAjaOfficial\Repositories\ShippingDiscountRegionRepository();
		$api = new \KiriminAjaOfficial\Services\KiriminajaApiService();
		$api->fail = in_array( $scenario, array( 'fallback', 'city_failure' ), true );
		$api->malformed = 'malformed' === $scenario;
		$repo->save = 'db_failure' !== $scenario;
		$service = new \KiriminAjaOfficial\Services\ShippingDiscountRegionCacheService( $repo, $api );
		switch ( $scenario ) {
			case 'schedule':
				$result = array( $service->scheduleRefresh(), $service->scheduleRefresh(), $service->getStatus()['state'] );
				break;
			case 'seed': $result = $service->seedFromBundledData( $repo ); break;
			case 'city_success': $result = $service->refreshProvinceCities( 1 ); break;
			case 'city_failure': $result = $service->refreshProvinceCities( 1 ); break;
			case 'invalid_province': $result = $service->refreshProvinceCities( 0 ); break;
			default: $result = $service->refreshAll(); break;
		}
	}
	echo json_encode( array( 'result' => $result, 'logs' => $GLOBALS['logs'], 'options' => $GLOBALS['options'] ), JSON_THROW_ON_ERROR );
}
