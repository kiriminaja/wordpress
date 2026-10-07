<?php

use Http\Client\Exception\NetworkException;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use PHPUnit\Framework\TestCase;
use KiriminAjaOfficial\Infrastructure\BoundedResponseStream;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) );
}

/** Installed SDK request creation and Nyholm messages; no live HTTP requests. */
final class InstantApiTransportRuntimeTest extends TestCase {
	protected function tearDown(): void {
		\KiriminAja\Base\Config\Cache\Cache::resetStore();
	}

	private function transport( array $queue, array &$history, array &$options ): \KiriminAjaOfficial\Infrastructure\InstantApiTransport {
		\KiriminAja\Base\Config\Cache\Cache::setStore( new class() implements \KiriminAja\Contracts\CacheStoreContract {
			private array $values = array();
			public function get( string $key ): mixed { return $this->values[ $key ] ?? null; }
			public function put( string $key, mixed $value, int $expiry ): bool { $this->values[ $key ] = $value; return true; }
			public function remove( string $key ): bool { unset( $this->values[ $key ] ); return true; }
		} );
		\KiriminAja\Base\Config\KiriminAjaConfig::setApiTokenKey( 'private-token' );
		$client = new class( $queue, $history ) implements ClientInterface {
			private array $queue;
			private $history;
			public function __construct( array $queue, array &$history ) { $this->queue = $queue; $this->history =& $history; }
			public function sendRequest( RequestInterface $request ): ResponseInterface {
				$this->history[] = $request;
				$response = array_shift( $this->queue );
				if ( $response instanceof \Throwable ) { throw $response; }
				if ( ! $response instanceof ResponseInterface ) { throw new \RuntimeException( 'Unexpected retry.' ); }
				return $response;
			}
		};
		$transport = new class() extends \KiriminAjaOfficial\Infrastructure\InstantApiTransport {
			public static ClientInterface $mock_client;
			public static $options;
			protected static function createClient( array $options ): ClientInterface { self::$options[] = $options; return self::$mock_client; }
			protected function url( $endpoint ): string { return 'https://example.invalid/' . $endpoint; }
		};
		$transport::$mock_client = $client;
		$transport::$options =& $options;
		return $transport;
	}

	public function test_post_uses_sdk_messages_bounded_curl_options_and_preserves_acknowledgement(): void {
		$history = $options = array();
		$transport = $this->transport( array( new Response( 201, array(), '{"status":true,"result":{"id":"created"}}' ) ), $history, $options );
		ob_start();
		$result = $transport->post( 'api/mitra/v6.2/instant/request_pickup', array( 'pin' => '123456' ) );
		$this->assertSame( '', ob_get_clean() );
		$this->assertSame( array( true, array( 'status' => true, 'result' => array( 'id' => 'created' ) ) ), $result );
		$this->assertCount( 1, $history );
		$this->assertCount( 1, $options );
		$this->assertInstanceOf( Request::class, $history[0] );
		$this->assertSame( 25, $options[0][CURLOPT_TIMEOUT] );
		$this->assertSame( 5, $options[0][CURLOPT_CONNECTTIMEOUT] );
		$this->assertFalse( $options[0][CURLOPT_FOLLOWLOCATION] );
		$this->assertSame( 0, $options[0][CURLOPT_MAXREDIRS] );
		$this->assertFalse( $options[0][CURLOPT_FAILONERROR] );
		$this->assertTrue( $options[0][CURLOPT_SSL_VERIFYPEER] );
		$this->assertSame( 2, $options[0][CURLOPT_SSL_VERIFYHOST] );
		$this->assertSame( CURLPROTO_HTTPS, $options[0][CURLOPT_PROTOCOLS] );
		$this->assertSame( CURLPROTO_HTTPS, $options[0][CURLOPT_REDIR_PROTOCOLS] );
		$this->assertFalse( $options[0][CURLOPT_NOPROGRESS] );
		$this->assertSame( 'POST', $history[0]->getMethod() );
		$this->assertSame( 'Bearer private-token', $history[0]->getHeaderLine( 'Authorization' ) );
		$this->assertSame( 'application/json', $history[0]->getHeaderLine( 'Content-Type' ) );
		$this->assertSame( '{"pin":"123456"}', (string) $history[0]->getBody() );
	}

	public function test_get_query_delete_body_and_upstream_negative_ack_are_preserved(): void {
		$history = $options = array();
		$transport = $this->transport( array( new Response( 200, array(), '{"status":false,"code":2,"result":null}' ), new Response( 202, array(), '{"status":true}' ), new Response( 200, array(), '{}' ) ), $history, $options );
		$this->assertSame( array( true, array( 'status' => false, 'code' => 2, 'result' => null ) ), $transport->get( 'tracking/order-1', array( 'id' => 'order 1', 'nested' => array( 'zero' => 0 ) ) ) );
		$this->assertSame( 'id=order%201&nested%5Bzero%5D=0', $history[0]->getUri()->getQuery() );
		$this->assertSame( '', (string) $history[0]->getBody() );
		$this->assertSame( array( true, array( 'status' => true ) ), $transport->delete( 'void/order-1', array( 'id' => 'order-1' ) ) );
		$this->assertSame( 'DELETE', $history[1]->getMethod() );
		$this->assertSame( '{"id":"order-1"}', (string) $history[1]->getBody() );
		$this->assertSame( array( true, array() ), $transport->postWithQuery( 'query', array( 'id' => 'order 1' ) ) );
		$this->assertSame( 'POST', $history[2]->getMethod() );
		$this->assertSame( 'id=order%201', $history[2]->getUri()->getQuery() );
		$this->assertSame( '', (string) $history[2]->getBody() );
		$this->assertCount( 3, $options );
	}

	public function test_failures_are_fixed_silent_and_never_retried_or_redirected(): void {
		$responses = array(
			new Response( 302, array( 'Location' => 'https://private.invalid' ), '{"status":true}' ),
			new Response( 400, array(), '{"pin":"123456"}' ),
			new Response( 500, array(), 'private-token' ),
			new Response( 204 ), new Response( 200, array(), 'null' ), new Response( 200, array(), 'true' ),
			new Response( 200, array(), '"123456"' ), new Response( 200, array(), '{bad json private-token' ),
			new Response( 200, array(), '{"data":"' . str_repeat( 'x', 2097152 ) . '"}' ),
			new Response( 200, array( 'Content-Length' => '2097153' ), '{}' ),
			new Response( 200, array( 'Content-Length' => 'not-a-size' ), '{}' ),
			new NetworkException( 'PIN 123456 Bearer private-token', new Request( 'POST', 'https://example.invalid' ) ),
		);
		foreach ( $responses as $response ) {
			$history = $options = array();
			$transport = $this->transport( array( $response ), $history, $options );
			ob_start();
			$result = $transport->post( 'pin/validate', array( 'pin' => '123456' ) );
			$this->assertSame( '', ob_get_clean() );
			$this->assertSame( array( false, 'Instant network request failed.' ), $result );
			$this->assertCount( 1, $history );
			$this->assertCount( 1, $options );
		}
	}

	public function test_exact_body_boundary_progress_guards_and_unsupported_methods(): void {
		$history = $options = array();
		$body = '{"data":"' . str_repeat( 'x', 2097152 - 11 ) . '"}';
		$this->assertSame( 2097152, strlen( $body ) );
		$transport = $this->transport( array( new Response( 200, array(), $body ) ), $history, $options );
		$this->assertTrue( $transport->get( 'tracking/order-1' )[0] );
		$progress = $options[0][CURLOPT_PROGRESSFUNCTION];
		$this->assertSame( 0, $progress( null, 2097152, 2097152, 0, 0 ) );
		$this->assertSame( 1, $progress( null, 0, 2097153, 0, 0 ) );
		$this->assertSame( 1, $progress( null, 2097153, 0, 0, 0 ) );
		$this->assertSame( array( false, 'Instant network request failed.' ), $transport->put( 'unsupported', array() ) );
		$this->assertCount( 1, $history );
	}

	public function test_actual_sdk_curl_client_uses_a_bounded_nyholm_response_sink(): void {
		$factory = new \ReflectionMethod( \KiriminAjaOfficial\Infrastructure\InstantApiTransport::class, 'createClient' );
		$client = $factory->invoke( null, array( CURLOPT_TIMEOUT => 25, CURLOPT_FOLLOWLOCATION => false ) );
		$this->assertInstanceOf( \Http\Client\Curl\Client::class, $client );
		$property = new \ReflectionProperty( $client, 'streamFactory' );
		$sink = $property->getValue( $client )->createStreamFromFile( 'php://temp', 'w+b' );
		$this->assertInstanceOf( BoundedResponseStream::class, $sink );
		$this->assertSame( 2097152, $sink->write( str_repeat( 'x', 2097152 ) ) );
		try { $sink->write( 'x' ); $this->fail( 'Chunked download must fail before writing beyond the cap.' ); }
		catch ( \RuntimeException $error ) { $this->assertSame( 2097152, $sink->getSize() ); }
		$sink->close();
	}

	public function test_real_curl_response_builder_keeps_download_bounds_when_it_installs_callbacks(): void {
		$history = $options = array();
		$transport = $this->transport( array( new Response( 200, array(), '{}' ) ), $history, $options );
		$transport->get( 'bounded' );
		$factory = new \ReflectionMethod( \KiriminAjaOfficial\Infrastructure\InstantApiTransport::class, 'createClient' );
		$client = $factory->invoke( null, $options[0] );
		$builder_method = new \ReflectionMethod( $client, 'createResponseBuilder' );
		$builder = $builder_method->invoke( $client );
		$prepare = new \ReflectionMethod( $client, 'prepareRequestOptions' );
		$installed = $prepare->invoke( $client, $history[0], $builder );
		$this->assertSame( 25, $installed[CURLOPT_TIMEOUT] );
		$this->assertFalse( $installed[CURLOPT_FOLLOWLOCATION] );
		$this->assertSame( 1, $installed[CURLOPT_PROGRESSFUNCTION]( null, 0, 2097153, 0, 0 ) );
		$this->assertSame( strlen( "HTTP/1.1 200 OK\r\n" ), $installed[CURLOPT_HEADERFUNCTION]( null, "HTTP/1.1 200 OK\r\n" ) );
		$this->assertSame( 2097152, $installed[CURLOPT_WRITEFUNCTION]( null, str_repeat( 'x', 2097152 ) ) );
		try {
			$installed[CURLOPT_WRITEFUNCTION]( null, 'x' );
			$this->fail( 'SDK-installed callback must reject unknown-length overflow.' );
		} catch ( \RuntimeException $error ) {
			$this->assertSame( 2097152, $builder->getResponse()->getBody()->getSize() );
		}
	}

	public function test_non_https_endpoints_never_reach_the_http_client(): void {
		$transport = new class() extends \KiriminAjaOfficial\Infrastructure\InstantApiTransport {
			protected function url( $endpoint ): string { return 'http://example.invalid/' . $endpoint; }
			protected static function createClient( array $options ): ClientInterface { throw new \LogicException( 'Unexpected HTTP request.' ); }
		};
		$this->assertSame( array( false, 'Instant network request failed.' ), $transport->post( 'private', array() ) );
	}

	public function test_stream_cap_applies_before_write_with_seek_and_delegates_psr7_operations(): void {
		$stream = new BoundedResponseStream( ( new Psr17Factory() )->createStream(), 4 );
		$this->assertTrue( $stream->isWritable() ); $this->assertTrue( $stream->isReadable() ); $this->assertTrue( $stream->isSeekable() );
		$this->assertSame( 4, $stream->write( 'abcd' ) );
		$stream->rewind(); $this->assertSame( 'ab', $stream->read( 2 ) );
		$this->assertSame( 2, $stream->tell() ); $this->assertSame( 'cd', $stream->getContents() );
		$this->assertTrue( $stream->eof() ); $stream->rewind();
		$this->assertSame( 'abcd', (string) $stream ); $this->assertIsArray( $stream->getMetadata() );
		$stream->seek( 3 ); $this->assertSame( 1, $stream->write( 'z' ) );
		try { $stream->write( 'x' ); $this->fail( 'Must reject overflow.' ); } catch ( \RuntimeException $error ) { $this->assertSame( 4, $stream->getSize() ); }
		$resource = $stream->detach(); $this->assertIsResource( $resource ); fclose( $resource );
		try { new BoundedResponseStream( ( new Psr17Factory() )->createStream( 'too long' ), 4 ); $this->fail( 'Reject already oversized streams.' ); } catch ( \RuntimeException $error ) { $this->assertNotEmpty( $error->getMessage() ); }
	}

	public function test_stream_cap_rejects_sparse_file_write_before_storing_bytes(): void {
		// PHP 8.1/8.2 memory streams reject seeks beyond EOF. A real temporary
		// file supports sparse seeks on every supported runtime and tests our cap.
		$resource = tmpfile();
		$this->assertIsResource( $resource );
		$stream = new BoundedResponseStream( ( new Psr17Factory() )->createStreamFromResource( $resource ), 4 );
		try {
			$this->assertSame( 4, $stream->write( 'abcd' ) );
			$stream->seek( 100 );
			$this->assertSame( 100, $stream->tell() );
			try {
				$stream->write( 'x' );
				$this->fail( 'Must reject sparse overflow.' );
			} catch ( \RuntimeException $error ) {
				$this->assertSame( 'Instant response exceeds limit.', $error->getMessage() );
				$this->assertSame( 4, $stream->getSize() );
				$this->assertSame( 100, $stream->tell() );
				$stream->rewind();
				$this->assertSame( 'abcd', $stream->getContents() );
			}
		} finally {
			$stream->close();
		}
	}
}
