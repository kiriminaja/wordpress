<?php

use KiriminAja\Base\Config\Cache\Cache;
use KiriminAja\Base\Config\KiriminAjaConfig;
use KiriminAja\Contracts\CacheStoreContract;
use KiriminAjaOfficial\Infrastructure\WordPressApiClient;
use KiriminAjaOfficial\Repositories\InstantDeliveryApiRepository;
use KiriminAjaOfficial\Repositories\KiriminajaApiRepository;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
		return json_encode( $value, $flags, $depth );
	}
}

final class UtilityApiRuntimeTest extends TestCase {
	protected function setUp(): void {
		Cache::setStore( new class() implements CacheStoreContract {
			private array $values = array();
			public function get( string $key ): mixed { return $this->values[ $key ] ?? null; }
			public function put( string $key, mixed $value, int $expiry ): bool { $this->values[ $key ] = $value; return true; }
			public function remove( string $key ): bool { unset( $this->values[ $key ] ); return true; }
		} );
		KiriminAjaConfig::setBaseUrl( 'https://utility-api.invalid' );
		KiriminAjaConfig::setApiTokenKey( 'fixture-utility-token' );
	}

	protected function tearDown(): void {
		Cache::resetStore();
		parent::tearDown();
	}

	public static function utilities(): iterable {
		$payment = array( 'payment_id' => 'PAY-1', 'payment_status' => 'unpaid', 'status_code' => 9, 'qr_content' => 'fixture-qr' );
		$profile = array( 'name' => 'Merchant', 'metadata' => array( 'payment_method' => 'QRIS' ) );
		yield 'couriers' => array( false, 'get_couriers', array(), 'POST', 'api/mitra/couriers', array(), array( 'datas' => array( array( 'code' => 'jne', 'name' => 'JNE' ), array( 'code' => 'gosend', 'type' => 'instant' ) ) ) );
		yield 'provinces' => array( false, 'getProvinces', array(), 'POST', 'api/mitra/province', array(), array( 'datas' => array( array( 'id' => 5, 'provinsi_name' => 'DI Yogyakarta' ) ) ) );
		yield 'cities' => array( false, 'getCitiesByProvinceId', array( 5 ), 'POST', 'api/mitra/city', array( 'provinsi_id' => 5 ), array( 'datas' => array( array( 'id' => 419, 'kabupaten_name' => 'Sleman' ) ) ) );
		yield 'schedules' => array( false, 'getRequestPickupSchedule', array(), 'POST', 'api/mitra/v2/schedules', array(), array( 'schedules' => array( array( 'clock' => '2026-10-22 17:00:00', 'until' => '18:00', 'expired' => 0, 'libur' => false ) ) ) );
		yield 'profile' => array( false, 'getProfile', array(), 'GET', 'api/mitra/v6.2/profile', array(), array( 'results' => $profile ) );
		yield 'balance' => array( false, 'getCreditBalance', array(), 'GET', 'api/mitra/v6.2/credit/balance', array(), array( 'results' => array( 'balance' => '4000724100' ) ) );
		yield 'payment' => array( false, 'getPayment', array( array( 'payment_id' => 'PAY-1' ) ), 'POST', 'api/mitra/v2/get_payment', array( 'payment_id' => 'PAY-1' ), array( 'data' => $payment ) );
		yield 'tracking' => array( false, 'getTracking', array( array( 'order_id' => 'ORDER-1' ) ), 'POST', 'api/mitra/tracking', array( 'order_id' => 'ORDER-1' ), array( 'status_code' => 100, 'details' => array( 'awb' => 'AWB-1' ), 'histories' => array( array( 'status' => 'picked_up' ) ) ) );
		yield 'instant profile' => array( true, 'profile', array(), 'GET', 'api/mitra/v6.2/profile', array(), array( 'results' => $profile ) );
		yield 'instant balance' => array( true, 'creditBalance', array(), 'GET', 'api/mitra/v6.2/credit/balance', array(), array( 'results' => array( 'balance' => '4000724100' ) ) );
		yield 'instant payment' => array( true, 'payment', array( 'PAY-1' ), 'POST', 'api/mitra/v2/get_payment', array( 'payment_id' => 'PAY-1' ), array( 'result' => $payment ) );
	}

	private function repository( bool $instant, $body, array &$requests, int $status = 200 ) {
		$http = new class( $body, $requests, $status ) implements ClientInterface {
			private array $requests;
			public function __construct( private $body, array &$requests, private int $status ) { $this->requests =& $requests; }
			public function sendRequest( RequestInterface $request ): ResponseInterface {
				$this->requests[] = $request;
				if ( $this->body instanceof Throwable ) { throw $this->body; }
				return new Response( $this->status, array(), is_array( $this->body ) ? json_encode( $this->body, JSON_THROW_ON_ERROR ) : $this->body );
			}
		};
		$client = new WordPressApiClient( $http );
		if ( $instant ) {
			return new class( $client ) extends InstantDeliveryApiRepository {
				public function __construct( private WordPressApiClient $client ) {}
				protected function create_api_client(): WordPressApiClient { return $this->client; }
				protected function call_sdk( callable $callback, callable $formatter ): array { throw new RuntimeException( 'Utility bypassed the WordPress client.' ); }
			};
		}
		return new class( $client ) extends KiriminajaApiRepository {
			public function __construct( private WordPressApiClient $client ) {}
			protected function create_api_client(): WordPressApiClient { return $this->client; }
			protected function call_sdk( callable $callback, callable $formatter ): array { throw new RuntimeException( 'Utility bypassed the WordPress client.' ); }
		};
	}

	#[DataProvider( 'utilities' )]
	public function test_utility_uses_wordpress_sdk_transport_and_preserves_api_contract( bool $instant, string $method, array $arguments, string $verb, string $endpoint, array $payload, array $data ): void {
		$requests = array();
		$body = array_merge( array( 'status' => true, 'text' => 'loaded', 'method' => 'fixture' ), $data );
		$result = $this->repository( $instant, $body, $requests )->$method( ...$arguments );
		$this->assertTrue( $result['status'] );
		$this->assertCount( 1, $requests );
		$request = $requests[0];
		$this->assertSame( $verb, $request->getMethod() );
		$this->assertSame( 'https://utility-api.invalid/' . $endpoint, (string) $request->getUri() );
		$this->assertSame( 'wordpress', $request->getHeaderLine( 'User-Agent' ) );
		$this->assertSame( 'Bearer fixture-utility-token', $request->getHeaderLine( 'Authorization' ) );
		$this->assertSame( 'application/json', $request->getHeaderLine( 'Accept' ) );
		$this->assertSame( 'application/json', $request->getHeaderLine( 'Content-Type' ) );
		if ( 'GET' === $verb ) {
			$this->assertSame( '', (string) $request->getBody() );
		} else {
			$this->assertSame( $payload, json_decode( (string) $request->getBody(), true ) );
		}
		if ( $instant && 'creditBalance' === $method ) {
			$this->assertEquals( 4000724100, $result['data']->balance );
		} else {
			$this->assertEquals( json_decode( json_encode( $body ) ), $result['data'] );
		}
	}

	#[DataProvider( 'utilities' )]
	public function test_utility_rejects_transport_api_and_malformed_success_responses( bool $instant, string $method, array $arguments, string $verb, string $endpoint, array $payload, array $data ): void {
		foreach ( array( false, 1, 'true', null ) as $status ) {
			$requests = array();
			$body = array_merge( array( 'status' => $status, 'text' => 'PRIVATE fixture-token' ), $data );
			$result = $this->repository( $instant, $body, $requests )->$method( ...$arguments );
			$this->assertFalse( $result['status'] );
			$this->assertStringNotContainsString( 'PRIVATE', json_encode( $result ) );
			$this->assertCount( 1, $requests );
		}
		foreach ( array( array(), array( 'status' => true ), 'not-json', 'null', new RuntimeException( 'PRIVATE fixture-token' ) ) as $body ) {
			$requests = array();
			$result = $this->repository( $instant, $body, $requests )->$method( ...$arguments );
			$this->assertFalse( $result['status'] );
			$this->assertStringNotContainsString( 'PRIVATE', json_encode( $result ) );
		}
		$requests = array();
		$result = $this->repository( $instant, array_merge( array( 'status' => true ), $data ), $requests, 503 )->$method( ...$arguments );
		$this->assertFalse( $result['status'] );
	}

	public function test_list_utilities_distinguish_empty_lists_from_missing_or_malformed_data(): void {
		foreach ( array( 'get_couriers' => array( array(), 'datas' ), 'getProvinces' => array( array(), 'datas' ), 'getCitiesByProvinceId' => array( array( 5 ), 'datas' ), 'getRequestPickupSchedule' => array( array(), 'schedules' ) ) as $method => [ $arguments, $field ] ) {
			foreach ( array( array(), null, 'bad', array( 'code' => 'jne' ), array( null ), array( 'bad' ) ) as $rows ) {
				$requests = array();
				$result = $this->repository( false, array( 'status' => true, $field => $rows ), $requests )->$method( ...$arguments );
				$this->assertSame( array() === $rows, $result['status'], $method );
			}
			$requests = array();
			$this->assertFalse( $this->repository( false, array( 'status' => true ), $requests )->$method( ...$arguments )['status'] );
		}
	}

	public function test_profiles_accept_sdk_wrapper_variants_and_top_level_profiles(): void {
		$profile = array( 'name' => 'Merchant', 'metadata' => array( 'payment_method' => 'TOP' ) );
		foreach ( array( false, true ) as $instant ) {
			foreach ( array( 'results', 'result', 'data', null ) as $wrapper ) {
				foreach ( array( false, true ) as $with_status ) {
					$body = null === $wrapper ? $profile : array( $wrapper => $profile );
					if ( $with_status ) { $body['status'] = true; }
					$requests = array();
					$repository = $this->repository( $instant, $body, $requests );
					$result = $instant ? $repository->profile() : $repository->getProfile();
					$this->assertTrue( $result['status'] );
					$this->assertSame( 'TOP', $result['data']->results->metadata->payment_method );
					$this->assertCount( 1, $requests );
				}
			}
		}
	}

	public function test_balances_preserve_supported_wrappers_and_reject_invalid_numbers(): void {
		foreach ( array( false, true ) as $instant ) {
			foreach ( array( 'results', 'result', 'data', 'payload', null ) as $wrapper ) {
				foreach ( array( 0, '4000724100', '-1', '+10', '1e6', '4,000', true, null ) as $number ) {
					$payload = array( 'balance' => $number );
					$body = null === $wrapper ? $payload : array( $wrapper => $payload );
					$requests = array();
					$repository = $this->repository( $instant, $body, $requests );
					$result = $instant ? $repository->creditBalance() : $repository->getCreditBalance();
					$this->assertSame( in_array( $number, array( 0, '4000724100' ), true ), $result['status'] );
					$this->assertCount( 1, $requests );
				}
			}
		}
	}
}
