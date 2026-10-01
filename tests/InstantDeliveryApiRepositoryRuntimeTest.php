<?php

use PHPUnit\Framework\TestCase;

/** Isolated runtime fixture: real base adapter/DTO, stub SDK facade and transport. */
final class InstantDeliveryApiRepositoryRuntimeTest extends TestCase {
	private function run_fixture( string $scenario ): array {
		$fixture = <<<'PHP'
namespace KiriminAja\Base\Api {
	class Api {
		public function post( $endpoint, $payload ) {
			$GLOBALS['calls'][] = array( 'post', $endpoint, $payload );
			if ( ! empty( $GLOBALS['throw_transport'] ) ) { throw new \RuntimeException( 'Remote echoed PIN 123456' ); }
			return $GLOBALS['transport'];
		}

	public function test_booking_accepts_cash_and_omitted_top_account_method_without_a_pin(): void {
		foreach ( array( '$book["payment_method"] = "cash";', 'unset( $book["payment_method"] );' ) as $mutation ) {
			$fixture = $this->run_fixture( $mutation . 'unset( $book["pin"] ); $result = $repository->book( $book );' );
			$this->assertTrue( $fixture['result']['status'] );
			$this->assertCount( 1, $fixture['calls'] );
			$this->assertArrayNotHasKey( 'pin', $fixture['calls'][0][2] );
			$this->assertArrayNotHasKey( 'origin', $fixture['calls'][0][2] );
		}
	}
	}
}
namespace KiriminAja\Services {
	class KiriminAja {
		public static function getPriceInstant( \KiriminAja\Models\ShippingPriceInstantData $data ) {
			$GLOBALS['calls'][] = array( 'price', $data->toArray() );
			return $GLOBALS['sdk'];
		}
		public static function getPayment( $id, $instant = false ) {
			$GLOBALS['calls'][] = array( 'payment', $id, $instant );
			return $GLOBALS['sdk'];
		}
		public static function getProfile() {
			$GLOBALS['calls'][] = array( 'profile' );
			return new \KiriminAja\Responses\ServiceResponse( true, 'loaded', array( 'metadata' => array( 'payment_method' => $GLOBALS['merchant'] ), 'name' => 'merchant' ) );
		}
		public static function getCreditBalance() {
			$GLOBALS['calls'][] = array( 'balance' );
			return new \KiriminAja\Responses\ServiceResponse( true, 'loaded', array( 'balance' => $GLOBALS['balance'] ) );
		}
		public static function requestPickupInstant( ...$args ) { throw new \RuntimeException( 'Legacy booking forbidden' ); }
	}
}
namespace KiriminAjaOfficial\Infrastructure {
	class InstantApiTransport extends \KiriminAja\Base\Api\Api {}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function kiriof_log( ...$args ) { $GLOBALS['logs'][] = $args; }
	require ROOT . '/vendor/autoload.php';
	require ROOT . '/inc/Base/KiriminAjaApi.php';
	require ROOT . '/inc/Repositories/InstantDeliveryApiRepository.php';
	class FixtureInstantRepository extends \KiriminAjaOfficial\Repositories\InstantDeliveryApiRepository {
		public function __construct() {}
		public function post( $endpoint, $body = array(), $log_context = array(), $request_args = array() ) { throw new \RuntimeException( 'Inherited logging transport forbidden' ); }
	}
	$GLOBALS['calls'] = array();
	$GLOBALS['logs'] = array();
	$GLOBALS['transport'] = array( true, array( 'status' => true, 'result' => array( 'payment_id' => 'instant-id' ) ) );
	$GLOBALS['sdk'] = new \KiriminAja\Responses\ServiceResponse( true, 'loaded', array( array( 'name' => 'gosend', 'costs' => array( array( 'price' => array( 'shipping_costs' => 12000, 'total_price' => 14000 ) ) ) ) ) );
	$GLOBALS['merchant'] = 'QRIS';
	$GLOBALS['balance'] = 20000;
	$repository = new FixtureInstantRepository();
	$location = array( 'address' => 'address', 'lat' => -7.8, 'long' => 110.3 );
	$price = array( 'service' => array( 'gosend', 'grab_express' ), 'origin' => $location, 'destination' => $location, 'item_price' => 10000, 'weight' => 1000, 'vehicle' => 'motor', 'timezone' => 'WIB' );
	$destination = array( 'name' => 'Recipient', 'phone' => '081234567890', 'address' => 'Destination address', 'latitude' => -7.9, 'longitude' => 110.4 );
	$package = array( 'order_id' => 'order-1', 'destination' => $destination, 'shipping_cost' => 12000, 'service' => 'gosend', 'service_type' => 'instant', 'package_type_id' => 7, 'vehicle' => 'motor', 'items' => array( array( 'name' => 'item', 'price' => 10000, 'weight' => 1000 ) ) );
	$book = array( 'name' => 'Sender', 'phone' => '081234567891', 'address' => 'Origin address', 'zipcode' => '55111', 'latitude' => -7.8, 'longitude' => 110.3, 'payment_method' => 'credit', 'pin' => '123456', 'packages' => array( $package ) );
PHP;
		$code = 'namespace { define( "ROOT", ' . var_export( PLUGIN_DIR, true ) . ' ); }' . $fixture . "\n" . $scenario . "\necho json_encode( array( 'result' => \$result, 'calls' => \$GLOBALS['calls'], 'logs' => \$GLOBALS['logs'] ) );\n}";
		exec( escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $code ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_price_maps_real_sdk_dto_and_retains_all_result_rows(): void {
		$fixture = $this->run_fixture( '$result = $repository->price( $price );' );
		$this->assertTrue( $fixture['result']['status'] );
		$this->assertSame( 12000, $fixture['result']['data']['result'][0]['costs'][0]['price']['shipping_costs'] );
		$this->assertCount( 1, $fixture['calls'] );
		$this->assertSame( 'price', $fixture['calls'][0][0] );
		$this->assertSame( array( 'gosend', 'grab_express' ), $fixture['calls'][0][1]['service'] );
		$this->assertSame( 1000, $fixture['calls'][0][1]['weight'] );
		$this->assertSame( 'WIB', $fixture['calls'][0][1]['timezone'] );
		$this->assertSame( -7.8, $fixture['calls'][0][1]['origin']['lat'] );
	}

	public function test_booking_uses_literal_v62_endpoint_preserves_payload_and_never_legacy_sdk(): void {
		$fixture = $this->run_fixture( '$book["packages"] = array_fill( 0, 10, $package ); $result = $repository->book( $book );' );
		$this->assertTrue( $fixture['result']['status'] );
		$this->assertCount( 1, $fixture['calls'] );
		$this->assertSame( 'api/mitra/v6.2/instant/request_pickup', $fixture['calls'][0][1] );
		$this->assertCount( 10, $fixture['calls'][0][2]['packages'] );
		foreach ( array( 'address', 'phone', 'latitude', 'longitude', 'name', 'packages' ) as $field ) {
			$this->assertArrayHasKey( $field, $fixture['calls'][0][2] );
		}
		$this->assertArrayNotHasKey( 'origin', $fixture['calls'][0][2] );
		$this->assertArrayNotHasKey( 'schedule', $fixture['calls'][0][2] );
		$this->assertSame( 'credit', $fixture['calls'][0][2]['payment_method'] );
		$this->assertArrayNotHasKey( 'service', $fixture['calls'][0][2] );
		$this->assertSame( '123456', $fixture['calls'][0][2]['pin'] );
		$this->assertSame( 'instant-id', $fixture['result']['data']['result']['payment_id'] );
		$this->assertSame( array(), $fixture['logs'] );
		$this->assertSame( array( array( 'name' => 'item', 'price' => 10000, 'weight' => 1000 ) ), $fixture['calls'][0][2]['packages'][0]['items'] );
	}

	public function test_booking_preserves_full_success_body_as_an_object(): void {
		$fixture = $this->run_fixture( '$body = array( "status" => true, "text" => "created", "result" => array( "payment_id" => "instant-id" ), "packages" => array( array( "order_id" => "order-1" ) ), "extra" => array( "value" => 42 ) ); $GLOBALS["transport"] = array( true, $body ); $response = $repository->book( $book ); $result = array( "is_object" => is_object( $response["data"] ), "body" => $response["data"] );' );
		$this->assertTrue( $fixture['result']['is_object'] );
		$this->assertSame( array( 'status' => true, 'text' => 'created', 'result' => array( 'payment_id' => 'instant-id' ), 'packages' => array( array( 'order_id' => 'order-1' ) ), 'extra' => array( 'value' => 42 ) ), $fixture['result']['body'] );
		$this->assertCount( 1, $fixture['calls'] );
	}

	public function test_booking_limits_unsupported_couriers_and_coordinates_fail_without_transport(): void {
		foreach ( array( '$book = array( "origin" => $book );',
			'unset( $book["name"] );',
			'$book["phone"] = " ";',
			'unset( $book["packages"][0]["destination"]["name"] );',
			'$book["packages"][0]["destination"]["phone"] = " ";',
			'$book["payment_method"] = "qris";',
			'$book["payment_method"] = "top";',
			'$book["pin"] = "12345";',
			'$book["pin"] = 123456;',
			'$book["packages"] = array();', '$book["packages"] = array_fill( 0, 11, $package );', '$book["packages"][0]["service"] = "borzo";',
			'$book["packages"][0]["service"] = array( "gosend" );',
			'unset( $book["latitude"] );',
			'$book["address"] = "  ";',
			'$book["longitude"] = INF;',
			'$book["latitude"] = NAN;',
			'$book["packages"][0]["destination"]["latitude"] = 91;',
			'$book["packages"][0]["destination"]["longitude"] = -181;',
			'$book["packages"][0]["destination"]["longitude"] = INF;',
			'$book["packages"][0]["destination"]["latitude"] = NAN;',
			'$book["packages"][0]["destination"]["address"] = "";',
			'unset( $book["packages"][0]["destination"]["latitude"] );',
			'$book["packages"] = array( $package, array_replace( $package, array( "service" => "borzo" ) ) );',
			'$book = array( "service" => "gosend", "packages" => array( array( "origin_address" => "origin", "origin_lat" => 0, "origin_long" => 0, "destination_address" => "destination", "destination_lat" => 0, "destination_long" => 0 ) ) );' ) as $mutation ) {
			$fixture = $this->run_fixture( $mutation . '$result = $repository->book( $book );' );
			$this->assertFalse( $fixture['result']['status'] );
			$this->assertSame( array(), $fixture['calls'] );
		}
	}

	public function test_booking_needs_transport_and_explicit_api_status_and_does_not_retry(): void {
		foreach ( array( 'array( false, "offline" )', 'array( true, array( "status" => false, "text" => "rejected" ) )', 'array( true, array( "result" => array() ) )', 'array( true, array( "status" => 1 ) )', 'array( true, array( "status" => "true" ) )', 'array( true, (object) array( "status" => true ) )', 'array( true, "PIN 123456" )', 'array( false, "PIN 123456" )' ) as $response ) {
			$fixture = $this->run_fixture( '$GLOBALS["transport"] = ' . $response . '; $result = $repository->book( $book );' );
			$this->assertFalse( $fixture['result']['status'] );
			$this->assertCount( 1, $fixture['calls'] );
			$this->assertSame( array( 'status' => false, 'data' => 'Instant booking failed.' ), $fixture['result'] );
			$this->assertSame( array(), $fixture['logs'] );
		}
	}

	public function test_booking_accepts_zero_coordinates_and_individual_supported_couriers(): void {
		$fixture = $this->run_fixture( '$book["latitude"] = 0; $book["longitude"] = 0; $package["service"] = "grab_express"; $package["destination"]["latitude"] = 0; $package["destination"]["longitude"] = 0; $book["packages"][] = $package; $result = $repository->book( $book );' );
		$this->assertTrue( $fixture['result']['status'] );
		$this->assertCount( 1, $fixture['calls'] );
		$this->assertSame( array( 'gosend', 'grab_express' ), array_column( $fixture['calls'][0][2]['packages'], 'service' ) );
	}

	public function test_booking_transport_exception_is_sanitized_and_never_logged_or_retried(): void {
		$fixture = $this->run_fixture( '$GLOBALS["throw_transport"] = true; $result = $repository->book( $book );' );
		$this->assertSame( array( 'status' => false, 'data' => 'Instant booking failed.' ), $fixture['result'] );
		$this->assertCount( 1, $fixture['calls'] );
		$this->assertSame( array(), $fixture['logs'] );
	}

	public function test_payment_selects_instant_and_keeps_numeric_state_without_guessing_paid(): void {
		$fixture = $this->run_fixture( '$GLOBALS["sdk"] = new \KiriminAja\Responses\ServiceResponse( true, "loaded", array( "status_code" => 9, "qr_content" => "qr", "paid_at" => null ) ); $result = $repository->payment( "payment-id" );' );
		$this->assertSame( array( 'payment', 'payment-id', true ), $fixture['calls'][0] );
		$this->assertSame( 9, $fixture['result']['data']['result']['status_code'] );
		$this->assertNull( $fixture['result']['data']['result']['paid_at'] );
	}

	public function test_profile_keeps_sdk_profile_data(): void {
		$fixture = $this->run_fixture( '$result = $repository->profile();' );
		$this->assertSame( 'merchant', $fixture['result']['data']['results']['name'] );
		$this->assertSame( 'QRIS', $fixture['result']['data']['results']['metadata']['payment_method'] );
	}

	public function test_credit_validates_once_and_checks_balance(): void {
		$fixture = $this->run_fixture( '$result = $repository->validateCredit( "123456", 20000 );' );
		$this->assertTrue( $fixture['result']['status'] );
		$this->assertSame( array( 'profile', 'post', 'balance' ), array_column( $fixture['calls'], 0 ) );
		$this->assertSame( 'api/mitra/v6.2/pin/validate', $fixture['calls'][1][1] );
		$this->assertSame( array(), $fixture['logs'] );
		$fixture = $this->run_fixture( '$result = $repository->validateCredit( "123456", 20001.5 );' );
		$this->assertFalse( $fixture['result']['status'] );
		$this->assertCount( 3, $fixture['calls'] );
	}

	public function test_top_and_invalid_pin_never_use_credit(): void {
		$fixture = $this->run_fixture( '$GLOBALS["merchant"] = "TOP"; $result = $repository->validateCredit( "123456", 1 );' );
		$this->assertFalse( $fixture['result']['status'] );
		$this->assertSame( array( array( 'profile' ) ), $fixture['calls'] );
		foreach ( array( '12345', '1234567', '12345a', '123456\n' ) as $pin ) {
			$fixture = $this->run_fixture( '$result = $repository->validateCredit( ' . var_export( $pin, true ) . ', 1 );' );
			$this->assertFalse( $fixture['result']['status'] );
			$this->assertSame( array(), $fixture['calls'] );
		}
	}

	public function test_failed_pin_is_remote_authoritative_not_retried_or_logged(): void {
		$fixture = $this->run_fixture( '$GLOBALS["transport"] = array( true, array( "status" => false, "text" => "123456 locked", "attempt" => 3, "max_attempt" => 3, "lock_until" => "tomorrow" ) ); $result = $repository->validateCredit( "123456", 1 );' );
		$this->assertFalse( $fixture['result']['status'] );
		$this->assertSame( array( 'profile', 'post' ), array_column( $fixture['calls'], 0 ) );
		$this->assertSame( array(), $fixture['logs'] );
		$this->assertStringNotContainsString( '123456', json_encode( $fixture['result'] ) );
	}

	public function test_pin_transport_exception_is_sanitized_without_logging_or_retry(): void {
		$fixture = $this->run_fixture( '$GLOBALS["throw_transport"] = true; $result = $repository->validateCredit( "123456", 1 );' );
		$this->assertSame( array( 'status' => false, 'data' => 'PIN validation failed.' ), $fixture['result'] );
		$this->assertSame( array( 'profile', 'post' ), array_column( $fixture['calls'], 0 ) );
		$this->assertSame( array(), $fixture['logs'] );
	}

	public function test_sdk_failure_uses_standard_error_shape(): void {
		$fixture = $this->run_fixture( '$GLOBALS["sdk"] = new \KiriminAja\Responses\ServiceResponse( false, "remote rejected", null ); $result = $repository->price( $price );' );
		$this->assertSame( array( 'status' => false, 'data' => 'remote rejected' ), $fixture['result'] );
		$this->assertCount( 1, $fixture['calls'] );
	}
}
