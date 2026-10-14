<?php

use KiriminAja\Base\Config\Cache\Cache;
use KiriminAja\Base\Config\KiriminAjaConfig;
use KiriminAja\Contracts\CacheStoreContract;
use KiriminAjaOfficial\Infrastructure\WordPressApiClient;
use KiriminAjaOfficial\Repositories\KiriminajaApiRepository;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
		return json_encode( $value, $flags, $depth );
	}
}

final class WordPressApiClientRuntimeTest extends TestCase {
	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		Cache::setStore( new class() implements CacheStoreContract {
			private array $values = array();
			public function get( string $key ): mixed { return $this->values[ $key ] ?? null; }
			public function put( string $key, mixed $value, int $expiry ): bool { $this->values[ $key ] = $value; return true; }
			public function remove( string $key ): bool { unset( $this->values[ $key ] ); return true; }
		} );
		KiriminAjaConfig::setBaseUrl( 'https://print-api.invalid' );
		KiriminAjaConfig::setApiTokenKey( 'fixture-print-token' );
	}

	protected function tearDown(): void {
		Cache::resetStore();
		parent::tearDown();
	}

	public function test_installed_sdk_print_request_matches_awb_contract_and_wordpress_user_agent(): void {
		$http = Mockery::mock( ClientInterface::class );
		$url = 'https://storage.googleapis.com/kiriminaja/label.pdf?signature=fixture';
		$http->shouldReceive( 'sendRequest' )->once()->with( Mockery::on( static function ( RequestInterface $request ): bool {
			return 'POST' === $request->getMethod()
				&& 'https://print-api.invalid/api/mitra/v6.1/awb/print' === (string) $request->getUri()
				&& 'wordpress' === $request->getHeaderLine( 'User-Agent' )
				&& 'Bearer fixture-print-token' === $request->getHeaderLine( 'Authorization' )
				&& 'application/json' === $request->getHeaderLine( 'Content-Type' )
				&& 'application/json' === $request->getHeaderLine( 'Accept' )
				&& '' === $request->getHeaderLine( 'Cookie' )
				&& array( 'awb' => array( 'GK-11-3776291' ) ) === json_decode( (string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR );
		} ) )->andReturn( new Response( 200, array(), json_encode( array( 'status' => true, 'data' => array( 'url' => $url ) ), JSON_THROW_ON_ERROR ) ) );
		$client = new WordPressApiClient( $http );
		$repository = new class( $client ) extends KiriminajaApiRepository {
			private WordPressApiClient $client;
			public function __construct( WordPressApiClient $client ) { $this->client = $client; }
			protected function create_api_client(): WordPressApiClient {
				return $this->client;
			}
		};
		$result = $repository->getPrintAwb( array( 'GK-11-3776291' ) );
		$this->assertTrue( $result['status'] );
		$this->assertSame( $url, $result['data']->data->url );
		$this->assertCount( 1, $result['attempts'] );
	}
}
