<?php
/** Offline real service -> real repository -> SDK/PSR-18, with persistence and Woo doubles. */
namespace KiriminAjaOfficial\Repositories {
	class PaymentRepository {
		public function createPayment( $payload ) { $GLOBALS['created'] = $payload; }
	}
	class TransactionRepository {
		public function getTransactionByPickupNumber( $id ) { return array( $GLOBALS['transaction'] ); }
		public function getTransactionByOrderIds( $ids ) {
			$transactions = array( $GLOBALS['transaction'] );
			foreach ( $GLOBALS['input']['additional_rows'] ?? array() as $row ) { $transactions[] = (object) array_merge( (array) $GLOBALS['transaction'], $row ); }
			return $transactions;
		}
		public function updateTransactionByCallback( $payload ) { $GLOBALS['writes'][] = $payload; }
	}
	class SettingRepository {}
}
namespace KiriminAjaOfficial\Services {
	class SettingService { public function isTopPaymentMethod() { return ! empty( $GLOBALS['input']['top'] ); } }
	class KiriminajaApiService { public function getProfile() { return (object) array(); } }
	class ShipmentLocationService {}
}
namespace {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	$input = json_decode( $argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR );
	define( 'KIRIOF_ENABLE_KA_CREDIT', $input['credit_enabled'] ?? true );
	require ABSPATH . 'vendor/autoload.php';
	function wp_timezone() { return new \DateTimeZone( 'UTC' ); }
	function __( $text, $domain = '' ) { return $text; }
	function get_home_url() { return 'https://example.test'; }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function kiriof_log( ...$args ) { $GLOBALS['logs'][] = $args; }
	function kiriof_helper() { return new class() { public function minAmount( $value ) { return max( 1, $value ); } }; }
	function wc_get_order( $id ) {
		return new class( $id ) {
			public function __construct( private int $id ) {}
			public function get_address( $type ) {
				$default = 'shipping' === $type ? array( 'first_name' => 'Private', 'last_name' => 'Buyer', 'address_1' => 'private-address', 'phone' => $GLOBALS['input']['recipient_phone'] ?? '08123456789', 'postcode' => '54321', 'city' => 'City', 'country' => 'ID' ) : array();
				return $GLOBALS['input']['addresses'][ $this->id ][ $type ] ?? $default;
			}
			public function get_items() {
				if ( ! empty( $GLOBALS['input']['empty_items'] ) ) {
					return array();
				}
				$quantities = $GLOBALS['input']['item_quantities'] ?? array( 1 );
				return array_map(
					static function ( $quantity ) {
						return new class( $quantity ) {
							private int $quantity;

							public function __construct( $quantity ) { $this->quantity = (int) $quantity; }
							public function get_name() { return 'Purchased variation'; }
							public function get_quantity() { return $this->quantity; }
							public function get_product() { return false; }
						};
					},
					$quantities
				);
			}
			public function get_payment_method() { return ! empty( $GLOBALS['input']['cod'] ) ? 'cod' : 'bacs'; }
		};
	}
	$logs = $writes = array();
	$created = null;
	// Existing checkout rows need not persist coordinates or per-item product dimensions.
	$transaction = (object) array(
		'order_id' => 'ORDER-1', 'wp_wc_order_stat_order_id' => 1, 'awb' => '', 'cod_fee' => 0,
		'shipping_cost' => 10000, 'transaction_value' => 12346, 'destination_sub_district_id' => 2,
		'discount_amount' => 1000, 'insurance_cost' => 0, 'weight' => 100, 'width' => 1, 'height' => 1, 'length' => 1,
		'service' => 'jne', 'service_name' => 'REG',
		'shipment_location_snapshot' => json_encode( array( 'location_id' => 1, 'origin_sub_district_id' => 1, 'origin_address' => 'private-origin', 'origin_name' => 'Original Store', 'origin_phone' => '08123456789', 'origin_zip_code' => '12345' ) ),
	);
	foreach ( $input['row'] ?? array() as $key => $value ) { $transaction->$key = $value; }
	\KiriminAja\Base\Config\Cache\Cache::setStore( new class() implements \KiriminAja\Contracts\CacheStoreContract {
		private array $values = array();
		public function get( string $key ): mixed { return $this->values[ $key ] ?? null; }
		public function put( string $key, mixed $value, int $expiry ): bool { $this->values[ $key ] = $value; return true; }
		public function remove( string $key ): bool { unset( $this->values[ $key ] ); return true; }
	} );
	\KiriminAja\Base\Config\KiriminAjaConfig::setBaseUrl( 'https://express-api.invalid' );
	\KiriminAja\Base\Config\KiriminAjaConfig::setApiTokenKey( 'fixture-express-token' );
	$http = new class() implements \Psr\Http\Client\ClientInterface {
		public array $requests = array();
		public function sendRequest( \Psr\Http\Message\RequestInterface $request ): \Psr\Http\Message\ResponseInterface {
			$this->requests[] = $request;
			$input = $GLOBALS['input'];
			if ( ! empty( $input['exception'] ) ) { throw new \RuntimeException( 'Offline failure' ); }
			$body = $input['response'] ?? array( 'status' => true, 'pickup_number' => 'PICKUP-1', 'payment_status' => $input['remote_status'] ?? 'paid' );
			return new \Nyholm\Psr7\Response( $input['http_status'] ?? 200, array(), json_encode( $body ) );
		}
	};
	$client = new \KiriminAjaOfficial\Infrastructure\WordPressApiClient( $http );
	$api = new class( $client ) extends \KiriminAjaOfficial\Repositories\KiriminajaApiRepository {
		private \KiriminAjaOfficial\Infrastructure\WordPressApiClient $client;
		public function __construct( $client ) { $this->client = $client; }
		protected function create_api_client(): \KiriminAjaOfficial\Infrastructure\WordPressApiClient { return $this->client; }
	};
	$service = new \KiriminAjaOfficial\Services\TransactionProcessServices\SendRequestPickupTransactionService(
		new \KiriminAjaOfficial\Repositories\TransactionRepository(), new \KiriminAjaOfficial\Repositories\PaymentRepository(),
		new \KiriminAjaOfficial\Repositories\SettingRepository(), $api, new \KiriminAjaOfficial\Services\ShipmentLocationService(),
		new \KiriminAjaOfficial\Services\SettingService(), new \KiriminAjaOfficial\Services\KiriminajaApiService(),
		new \KiriminAjaOfficial\Services\TransactionProcessServices\RecipientDataResolver()
	);
	$pin = $input['pin'] ?? '012345';
	$response = $service->orderIds( array( 'ORDER-1' ) )->schedule( gmdate( 'Y-m-d', strtotime( '+2 days' ) ) . ' 11:00:00' )->paymentMethod( $input['payment'] ?? 'credit' )->pin( $pin )->call();
	$requests = array();
	foreach ( $http->requests as $request ) {
		$payload = json_decode( (string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR );
		$pin_present = array_key_exists( 'pin', $payload );
		$pin_unchanged = $pin_present && is_string( $payload['pin'] ) && $pin === $payload['pin'];
		unset( $payload['pin'] );
		$requests[] = array( 'url' => (string) $request->getUri(), 'method' => $request->getMethod(), 'payload' => $payload, 'pin_present' => $pin_present, 'pin_unchanged' => $pin_unchanged );
	}
	echo json_encode( array( 'requests' => $requests, 'writes' => $writes, 'created' => $created, 'status' => $response->status, 'data' => $response->data, 'logs' => $logs, 'diagnostic_payload_json' => $api->pickupRequestPayloadJson() ), JSON_THROW_ON_ERROR );
}
