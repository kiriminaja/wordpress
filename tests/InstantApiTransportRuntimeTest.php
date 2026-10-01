<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) );
}

/** Installed SDK and real Guzzle middleware; never makes an actual network request. */
final class InstantApiTransportRuntimeTest extends TestCase {
	protected function tearDown(): void {
		\KiriminAja\Base\Config\Cache\Cache::resetStore();
	}

	private function transport( array $queue, array &$history ): \KiriminAjaOfficial\Infrastructure\InstantApiTransport {
		\KiriminAja\Base\Config\Cache\Cache::setStore( new class() implements \KiriminAja\Contracts\CacheStoreContract {
			private array $values = array();
			public function get( string $key ): mixed { return $this->values[ $key ] ?? null; }
			public function put( string $key, mixed $value, int $expiry ): bool { $this->values[ $key ] = $value; return true; }
			public function remove( string $key ): bool { unset( $this->values[ $key ] ); return true; }
		} );
		\KiriminAja\Base\Config\KiriminAjaConfig::setApiTokenKey( 'private-token' );
		$stack = HandlerStack::create( new MockHandler( $queue ) );
		$stack->push( Middleware::history( $history ) );
		$transport = new class() extends \KiriminAjaOfficial\Infrastructure\InstantApiTransport {
			public static Client $mock_client;
			protected static function createClient(): Client {
				return self::$mock_client;
			}
			protected function url( $endpoint ): string {
				return 'https://example.invalid/' . $endpoint;
			}
		};
		$transport::$mock_client = new Client( array( 'handler' => $stack, 'timeout' => 0, 'verify' => false, 'allow_redirects' => true, 'http_errors' => true ) );
		return $transport;
	}

	public function test_post_uses_bounded_options_and_preserves_success_acknowledgement(): void {
		$history = array();
		$transport = $this->transport( array( new Response( 201, array(), '{"status":true,"result":{"id":"created"}}' ) ), $history );
		ob_start();
		$result = $transport->post( 'api/mitra/v6.2/instant/request_pickup', array( 'pin' => '123456' ) );
		$this->assertSame( '', ob_get_clean() );
		$this->assertSame( array( true, array( 'status' => true, 'result' => array( 'id' => 'created' ) ) ), $result );
		$this->assertCount( 1, $history );
		$options = $history[0]['options'];
		$this->assertSame( 25, $options['timeout'] );
		$this->assertSame( 5, $options['connect_timeout'] );
		$this->assertFalse( $options['allow_redirects'] );
		$this->assertFalse( $options['http_errors'] );
		$this->assertTrue( $options['verify'] );
		$this->assertSame( 'POST', $history[0]['request']->getMethod() );
		$this->assertSame( 'Bearer private-token', $history[0]['request']->getHeaderLine( 'Authorization' ) );
		$this->assertSame( '{"pin":"123456"}', (string) $history[0]['request']->getBody() );
	}

	public function test_get_query_delete_body_and_upstream_negative_ack_are_preserved(): void {
		$history = array();
		$transport = $this->transport( array( new Response( 200, array(), '{"status":false,"code":2,"result":null}' ), new Response( 202, array(), '{"status":true}' ) ), $history );
		$this->assertSame( array( true, array( 'status' => false, 'code' => 2, 'result' => null ) ), $transport->get( 'tracking/order-1', array( 'id' => 'order-1' ) ) );
		$this->assertSame( 'id=order-1', $history[0]['request']->getUri()->getQuery() );
		$this->assertSame( '', (string) $history[0]['request']->getBody() );
		$this->assertSame( array( true, array( 'status' => true ) ), $transport->delete( 'void/order-1', array( 'id' => 'order-1' ) ) );
		$this->assertSame( 'DELETE', $history[1]['request']->getMethod() );
		$this->assertSame( '{"id":"order-1"}', (string) $history[1]['request']->getBody() );
	}

	public function test_failures_are_fixed_silent_and_never_retried_or_redirected(): void {
		$responses = array(
			new Response( 302, array( 'Location' => 'https://private.invalid' ), '{"status":true}' ),
			new Response( 400, array(), '{"pin":"123456"}' ),
			new Response( 500, array(), 'private-token' ),
			new Response( 204 ),
			new Response( 200, array(), 'null' ),
			new Response( 200, array(), 'true' ),
			new Response( 200, array(), '"123456"' ),
			new Response( 200, array(), '{bad json private-token' ),
			new Response( 200, array(), '{"data":"' . str_repeat( 'x', 2097152 ) . '"}' ),
			new ConnectException( 'PIN 123456 Bearer private-token', new Request( 'POST', 'https://example.invalid' ) ),
		);
		foreach ( $responses as $response ) {
			$history = array();
			$transport = $this->transport( array( $response ), $history );
			ob_start();
			$result = $transport->post( 'pin/validate', array( 'pin' => '123456' ) );
			$this->assertSame( '', ob_get_clean() );
			$this->assertSame( array( false, 'Instant network request failed.' ), $result );
			$this->assertCount( 1, $history );
		}
	}

	public function test_body_boundary_and_download_guards(): void {
		$history = array();
		$body = '{"data":"' . str_repeat( 'x', 2097152 - 11 ) . '"}';
		$this->assertSame( 2097152, strlen( $body ) );
		$transport = $this->transport( array( new Response( 200, array(), $body ) ), $history );
		$this->assertTrue( $transport->get( 'tracking/order-1' )[0] );
		$options = $history[0]['options'];
		foreach ( array(
			static fn() => $options['on_headers']( new Response( 200, array( 'Content-Length' => '2097153' ) ) ),
			static fn() => $options['progress']( 0, 2097153 ),
			static fn() => $options['progress']( 2097153, 0 ),
		) as $guard ) {
			try {
				$guard();
				$this->fail( 'Expected a bounded download rejection.' );
			} catch ( \RuntimeException $exception ) {
				$this->assertSame( 'Instant response exceeds limit.', $exception->getMessage() );
			}
		}
		$this->assertSame( array( false, 'Instant network request failed.' ), $transport->put( 'unsupported', array() ) );
		$this->assertCount( 1, $history );
	}
}
