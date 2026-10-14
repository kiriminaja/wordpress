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

	public function test_pin_requires_boolean_envelope_success_and_honors_explicit_invalid(): void {
		foreach ( array( 'array( "status" => "true" )', 'array( "status" => 1 )', 'array( "data" => array( "valid" => true ) )', 'array( "status" => true, "data" => array( "valid" => false ) )' ) as $body ) {
			$fixture = $this->run_fixture( '$GLOBALS["transport"] = array( true, ' . $body . ' ); $result = $repository->validateCredit( "123456", 1 );' );
			$this->assertFalse( $fixture['result']['status'] );
			$this->assertSame( array( 'profile', 'post' ), array_column( $fixture['calls'], 0 ) );
			$this->assertSame( array(), $fixture['logs'] );
		}
	}

	public function test_credit_accepts_actual_sdk_normalized_and_wrapped_four_billion_balances_once(): void {
		foreach ( array( 'array( "balance" => 4000724100 )', 'array( "results" => array( "balance" => "4000724100" ) )', '(object) array( "data" => (object) array( "balance" => 4000724100 ) )' ) as $payload ) {
			$fixture = $this->run_fixture( '$GLOBALS["balance_payload"] = ' . $payload . '; $result = $repository->validateCredit( "123456", 4000724100 );' );
			$this->assertTrue( $fixture['result']['status'] );
			$this->assertEquals( 4000724100, $fixture['result']['data']['balance'] );
			$this->assertSame( array( 'profile', 'post', 'balance' ), array_column( $fixture['calls'], 0 ) );
			$this->assertSame( array(), $fixture['logs'] );
		}
	}

	public function test_credit_unknown_is_not_insufficient_and_logs_only_fixed_diagnostics(): void {
		foreach ( array( 'array()', 'array( "balance" => -1 )', 'array( "balance" => "4,000,724,100" )', 'array( "status" => false, "balance" => 4000724100 )' ) as $payload ) {
			$fixture = $this->run_fixture( '$GLOBALS["balance_payload"] = ' . $payload . '; $result = $repository->validateCredit( "123456", 1 );' );
			$this->assertSame( array( 'status' => false, 'data' => 'Unable to verify credit balance.' ), $fixture['result'] );
			$this->assertSame( array( 'profile', 'post', 'balance' ), array_column( $fixture['calls'], 0 ) );
			$this->assertStringNotContainsString( '123456', json_encode( array( $fixture['result'], $fixture['logs'] ) ) );
		}
	}

	public function test_booking_always_supplies_notes_preserves_explicit_notes_and_accepts_qris(): void {
		foreach ( array( '', '$book["address_note"]="Pickup entrance"; $book["packages"][0]["destination"]["address_note"]="Gedung A Lantai 5";' ) as $notes ) {
			$r = $this->run_fixture( '$book["payment_method"]="qris"; unset($book["pin"]); ' . $notes . '$result=$repository->book($book);' );
			$this->assertTrue( $r['result']['status'] );
			$payload = $r['calls'][0][2];
			$this->assertSame( 'qris', $payload['payment_method'] );
			$this->assertSame( '' === $notes ? 'Origin address' : 'Pickup entrance', $payload['address_note'] );
			$this->assertSame( '' === $notes ? 'Destination address' : 'Gedung A Lantai 5', $payload['packages'][0]['destination']['address_note'] );
			$this->assertArrayNotHasKey( 'insurance_type', $payload['packages'][0] );
			$this->assertArrayNotHasKey( 'pin', $payload );
		}
		foreach ( array( '$book["address_note"]=array("bad");', '$book["packages"][0]["destination"]["address_note"]=42;' ) as $invalid ) {
			$r = $this->run_fixture( $invalid . '$result=$repository->book($book);' );
			$this->assertFalse( $r['result']['status'] );
			$this->assertTrue( $r['result']['operation_not_submitted'] );
			$this->assertSame( array(), $r['calls'] );
		}
	}

	public function test_booking_requires_strict_transport_proof_of_non_submission(): void {
		foreach ( array( 'array()', 'array( "submitted" => null )', 'array( "submitted" => 0 )', 'array( "submitted" => "false" )', 'array( "submitted" => true )', 'array( "submitted" => false )' ) as $diagnostics ) {
			$fixture = $this->run_fixture( '$GLOBALS["diagnostics"] = ' . $diagnostics . '; $GLOBALS["transport"] = array( false, "offline" ); $result = $repository->book( $book );' );
			$expected = array( 'status' => false, 'data' => 'Instant booking failed.' );
			if ( 'array( "submitted" => false )' === $diagnostics ) {
				$expected['operation_not_submitted'] = true;
			}
			$this->assertSame( $expected, $fixture['result'] );
			$this->assertSame( array(), $fixture['logs'] );
		}
		// A successful transport with an ambiguous body cannot assert non-submission.
		$fixture = $this->run_fixture( '$GLOBALS["diagnostics"] = array( "submitted" => false ); $GLOBALS["transport"] = array( true, array( "status" => false ) ); $result = $repository->book( $book );' );
		$this->assertSame( array( 'status' => false, 'data' => 'Instant booking failed.' ), $fixture['result'] );
	}

	public function test_booking_preserves_only_proven_empty_negative_results_without_secrets(): void {
		foreach ( array( 'array()', '(object) array()' ) as $empty ) {
			foreach ( array( 'result', 'results' ) as $field ) {
				$fixture = $this->run_fixture( '$GLOBALS["transport"] = array( true, array( "status" => false, "code" => 400, "message" => "PIN 123456 private-token", "text" => "private address", "' . $field . '" => ' . $empty . ' ) ); $response = $repository->book( $book ); $result = array( "response" => $response, "object" => is_object( $response["data"] ), "empty_object" => is_object( $response["data"]->result ) );' );
				$this->assertSame( array( 'status' => false, 'data' => array( 'status' => false, 'result' => array() ), 'operation_rejected' => true ), $fixture['result']['response'] );
				$this->assertTrue( $fixture['result']['object'] );
				$this->assertTrue( $fixture['result']['empty_object'] );
				$this->assertCount( 1, $fixture['calls'] );
				$this->assertSame( array(), $fixture['logs'] );
			}
		}
	}

	public function test_booking_ambiguous_negatives_remain_unknown_and_sanitized(): void {
		foreach ( array(
			'array( "status" => false )',
			'array( "status" => false, "code" => 400, "result" => null )',
			'array( "status" => "false", "result" => array() )',
			'array( "status" => 0, "result" => array() )',
			'array( "status" => false, "result" => "123456" )',
			'array( "status" => false, "result" => array( "payment_id" => "payment-1" ) )',
			'array( "status" => false, "result" => array( "packages" => array( array( "order_id" => "order-1" ) ) ) )',
			'array( "status" => false, "result" => array(), "results" => null )',
			'array( "status" => false, "result" => array(), "results" => array( "id" => "remote-id" ) )',
			'array( "status" => false, "result" => array(), "payment_id" => "remote-id" )',
			'array( "status" => false, "result" => array(), "extra" => array( "id" => "remote-id" ) )',
		) as $body ) {
			$fixture = $this->run_fixture( '$GLOBALS["transport"] = array( true, ' . $body . ' ); $result = $repository->book( $book );' );
			$this->assertSame( array( 'status' => false, 'data' => 'Instant booking failed.' ), $fixture['result'] );
			$this->assertCount( 1, $fixture['calls'] );
			$this->assertSame( array(), $fixture['logs'] );
		}
		$fixture = $this->run_fixture( '$GLOBALS["transport"] = array( false, array( "status" => false, "result" => array() ) ); $result = $repository->book( $book );' );
		$this->assertSame( array( 'status' => false, 'data' => 'Instant booking failed.' ), $fixture['result'] );
	}

	public function test_booking_accepts_cash_and_omitted_top_account_method_without_a_pin(): void {
		foreach ( array( '$book["payment_method"] = "cash";', '$book["payment_method"] = "qris";', 'unset( $book["payment_method"] );' ) as $mutation ) {
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
			return new \KiriminAja\Responses\ServiceResponse( $GLOBALS['balance_status'] ?? true, 'Secret upstream error 123456', $GLOBALS['balance_payload'] ?? array( 'balance' => $GLOBALS['balance'] ) );
		}
		public static function requestPickupInstant( ...$args ) { throw new \RuntimeException( 'Legacy booking forbidden' ); }
	}
}
namespace KiriminAjaOfficial\Infrastructure {
	class InstantApiTransport extends \KiriminAja\Base\Api\Api {
		public function diagnostics(): array { return $GLOBALS['diagnostics'] ?? array( 'code' => 'transport_success', 'http_status' => 200, 'elapsed_ms' => 1, 'submitted' => true ); }
	}
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
		$this->assertSame(
			array(
				'status' => true,
				'shipping_costs' => 12000,
				'calls count' => 1,
				'calls' => 'price',
				'service' => array( 'gosend', 'grab_express' ),
				'weight' => 1000,
				'timezone' => 'WIB',
				'lat' => -7.8,
			),
			array(
				'status' => $fixture['result']['status'],
				'shipping_costs' => $fixture['result']['data']['result'][0]['costs'][0]['price']['shipping_costs'],
				'calls count' => count( $fixture['calls'] ),
				'calls' => $fixture['calls'][0][0],
				'service' => $fixture['calls'][0][1]['service'],
				'weight' => $fixture['calls'][0][1]['weight'],
				'timezone' => $fixture['calls'][0][1]['timezone'],
				'lat' => $fixture['calls'][0][1]['origin']['lat'],
			)
		);
	}

	public function test_booking_uses_literal_v62_endpoint_preserves_payload_and_never_legacy_sdk(): void {
		$fixture = $this->run_fixture( '$book["packages"] = array_fill( 0, 10, $package ); $result = $repository->book( $book );' );
		$this->assertSame(
			array(
				'status' => true,
				'calls count' => 1,
				'calls' => 'api/mitra/v6.2/instant/request_pickup',
				'packages count' => 10,
			),
			array(
				'status' => $fixture['result']['status'],
				'calls count' => count( $fixture['calls'] ),
				'calls' => $fixture['calls'][0][1],
				'packages count' => count( $fixture['calls'][0][2]['packages'] ),
			)
		);
		foreach ( array( 'address', 'phone', 'latitude', 'longitude', 'name' ) as $field ) {
			$this->assertArrayHasKey( $field, $fixture['calls'][0][2] );
		}
		$this->assertSame(
			array(
				'origin present' => false,
				'schedule present' => false,
				'payment_method' => 'credit',
				'service present' => false,
				'pin' => '123456',
				'payment_id' => 'instant-id',
				'logs' => array(),
				'items' => array( array( 'name' => 'item', 'price' => 10000, 'weight' => 1000 ) ),
			),
			array(
				'origin present' => array_key_exists( 'origin', $fixture['calls'][0][2] ),
				'schedule present' => array_key_exists( 'schedule', $fixture['calls'][0][2] ),
				'payment_method' => $fixture['calls'][0][2]['payment_method'],
				'service present' => array_key_exists( 'service', $fixture['calls'][0][2] ),
				'pin' => $fixture['calls'][0][2]['pin'],
				'payment_id' => $fixture['result']['data']['result']['payment_id'],
				'logs' => $fixture['logs'],
				'items' => $fixture['calls'][0][2]['packages'][0]['items'],
			)
		);
	}

	public function test_booking_preserves_full_success_body_as_an_object(): void {
		$fixture = $this->run_fixture( '$body = array( "status" => true, "text" => "created", "result" => array( "payment_id" => "instant-id" ), "packages" => array( array( "order_id" => "order-1" ) ), "extra" => array( "value" => 42 ) ); $GLOBALS["transport"] = array( true, $body ); $response = $repository->book( $book ); $result = array( "is_object" => is_object( $response["data"] ), "body" => $response["data"] );' );
		$this->assertSame(
			array(
				'is_object' => true,
				'body' => array( 'status' => true, 'text' => 'created', 'result' => array( 'payment_id' => 'instant-id' ), 'packages' => array( array( 'order_id' => 'order-1' ) ), 'extra' => array( 'value' => 42 ) ),
				'calls count' => 1,
			),
			array(
				'is_object' => $fixture['result']['is_object'],
				'body' => $fixture['result']['body'],
				'calls count' => count( $fixture['calls'] ),
			)
		);
	}

	public function test_booking_limits_unsupported_couriers_and_coordinates_fail_without_transport(): void {
		foreach ( array( '$book = array( "origin" => $book );',
			'unset( $book["name"] );',
			'$book["phone"] = " ";',
			'unset( $book["packages"][0]["destination"]["name"] );',
			'$book["packages"][0]["destination"]["phone"] = " ";',
			'$book["payment_method"] = "unknown";',
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
			$this->assertSame(
				array(
					'status' => false,
					'operation_not_submitted' => true,
					'calls' => array(),
				),
				array(
					'status' => $fixture['result']['status'],
					'operation_not_submitted' => $fixture['result']['operation_not_submitted'],
					'calls' => $fixture['calls'],
				)
			);
		}
	}

	public function test_booking_needs_transport_and_explicit_api_status_and_does_not_retry(): void {
		foreach ( array( 'array( false, "offline" )', 'array( true, array( "status" => false, "text" => "rejected" ) )', 'array( true, array( "result" => array() ) )', 'array( true, array( "status" => 1 ) )', 'array( true, array( "status" => "true" ) )', 'array( true, (object) array( "status" => true ) )', 'array( true, "PIN 123456" )', 'array( false, "PIN 123456" )' ) as $response ) {
			$fixture = $this->run_fixture( '$GLOBALS["transport"] = ' . $response . '; $result = $repository->book( $book );' );
			$this->assertSame(
				array(
					'calls count' => 1,
					'result' => array( 'status' => false, 'data' => 'Instant booking failed.' ),
					'logs' => array(),
				),
				array(
					'calls count' => count( $fixture['calls'] ),
					'result' => $fixture['result'],
					'logs' => $fixture['logs'],
				)
			);
		}
	}

	public function test_booking_accepts_zero_coordinates_and_individual_supported_couriers(): void {
		$fixture = $this->run_fixture( '$book["latitude"] = 0; $book["longitude"] = 0; $package["service"] = "grab_express"; $package["destination"]["latitude"] = 0; $package["destination"]["longitude"] = 0; $book["packages"][] = $package; $result = $repository->book( $book );' );
		$this->assertSame(
			array(
				'status' => true,
				'calls count' => 1,
				'packages' => array( 'gosend', 'grab_express' ),
			),
			array(
				'status' => $fixture['result']['status'],
				'calls count' => count( $fixture['calls'] ),
				'packages' => array_column( $fixture['calls'][0][2]['packages'], 'service' ),
			)
		);
	}

	public function test_booking_transport_exception_is_sanitized_and_never_logged_or_retried(): void {
		$fixture = $this->run_fixture( '$GLOBALS["throw_transport"] = true; $result = $repository->book( $book );' );
		$this->assertSame(
			array(
				'result' => array( 'status' => false, 'data' => 'Instant booking failed.' ),
				'calls count' => 1,
				'logs' => array(),
			),
			array(
				'result' => $fixture['result'],
				'calls count' => count( $fixture['calls'] ),
				'logs' => $fixture['logs'],
			)
		);
	}

	public function test_payment_selects_instant_and_keeps_numeric_state_without_guessing_paid(): void {
		$fixture = $this->run_fixture( '$GLOBALS["sdk"] = new \KiriminAja\Responses\ServiceResponse( true, "loaded", array( "status_code" => 9, "qr_content" => "qr", "paid_at" => null ) ); $result = $repository->payment( "payment-id" );' );
		$this->assertSame(
			array(
				'calls' => array( 'payment', 'payment-id', true ),
				'status_code' => 9,
				'paid_at' => null,
			),
			array(
				'calls' => $fixture['calls'][0],
				'status_code' => $fixture['result']['data']['result']['status_code'],
				'paid_at' => $fixture['result']['data']['result']['paid_at'],
			)
		);
	}

	public function test_profile_keeps_sdk_profile_data(): void {
		$fixture = $this->run_fixture( '$result = $repository->profile();' );
		$this->assertSame(
			array(
				'name' => 'merchant',
				'payment_method' => 'QRIS',
			),
			array(
				'name' => $fixture['result']['data']['results']['name'],
				'payment_method' => $fixture['result']['data']['results']['metadata']['payment_method'],
			)
		);
	}

	public function test_credit_validates_once_and_checks_balance(): void {
		$fixture = $this->run_fixture( '$result = $repository->validateCredit( "123456", 20000 );' );
		$this->assertSame(
			array(
				'status' => true,
				'calls' => array( 'profile', 'post', 'balance' ),
				'calls check 2' => 'api/mitra/v6.2/pin/validate',
				'logs' => array(),
			),
			array(
				'status' => $fixture['result']['status'],
				'calls' => array_column( $fixture['calls'], 0 ),
				'calls check 2' => $fixture['calls'][1][1],
				'logs' => $fixture['logs'],
			)
		);
		$fixture = $this->run_fixture( '$result = $repository->validateCredit( "123456", 20001.5 );' );
		$this->assertSame(
			array(
				'status' => false,
				'calls count' => 3,
			),
			array(
				'status' => $fixture['result']['status'],
				'calls count' => count( $fixture['calls'] ),
			)
		);
	}

	public function test_top_and_invalid_pin_never_use_credit(): void {
		$fixture = $this->run_fixture( '$GLOBALS["merchant"] = "TOP"; $result = $repository->validateCredit( "123456", 1 );' );
		$this->assertSame(
			array(
				'status' => false,
				'calls' => array( array( 'profile' ) ),
			),
			array(
				'status' => $fixture['result']['status'],
				'calls' => $fixture['calls'],
			)
		);
		foreach ( array( '12345', '1234567', '12345a', '123456\n' ) as $pin ) {
			$fixture = $this->run_fixture( '$result = $repository->validateCredit( ' . var_export( $pin, true ) . ', 1 );' );
			$this->assertSame(
				array(
					'status' => false,
					'calls' => array(),
				),
				array(
					'status' => $fixture['result']['status'],
					'calls' => $fixture['calls'],
				)
			);
		}
	}

	public function test_failed_pin_is_remote_authoritative_not_retried_or_logged(): void {
		$fixture = $this->run_fixture( '$GLOBALS["transport"] = array( true, array( "status" => false, "text" => "123456 locked", "attempt" => 3, "max_attempt" => 3, "lock_until" => "tomorrow" ) ); $result = $repository->validateCredit( "123456", 1 );' );
		$this->assertSame(
			array(
				'status' => false,
				'calls' => array( 'profile', 'post' ),
				'logs' => array(),
				'redacts 123456' => false,
			),
			array(
				'status' => $fixture['result']['status'],
				'calls' => array_column( $fixture['calls'], 0 ),
				'logs' => $fixture['logs'],
				'redacts 123456' => str_contains( json_encode( $fixture['result'] ), '123456' ),
			)
		);
	}

	public function test_pin_transport_exception_is_sanitized_without_logging_or_retry(): void {
		$fixture = $this->run_fixture( '$GLOBALS["throw_transport"] = true; $result = $repository->validateCredit( "123456", 1 );' );
		$this->assertSame(
			array(
				'result' => array( 'status' => false, 'data' => 'PIN validation failed.' ),
				'calls' => array( 'profile', 'post' ),
				'logs' => array(),
			),
			array(
				'result' => $fixture['result'],
				'calls' => array_column( $fixture['calls'], 0 ),
				'logs' => $fixture['logs'],
			)
		);
	}

	public function test_sdk_failure_uses_standard_error_shape(): void {
		$fixture = $this->run_fixture( '$GLOBALS["sdk"] = new \KiriminAja\Responses\ServiceResponse( false, "remote rejected", null ); $result = $repository->price( $price );' );
		$this->assertSame(
			array(
				'result' => array( 'status' => false, 'data' => 'remote rejected' ),
				'calls count' => 1,
			),
			array(
				'result' => $fixture['result'],
				'calls count' => count( $fixture['calls'] ),
			)
		);
	}
}
