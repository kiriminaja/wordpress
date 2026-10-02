<?php

use KiriminAjaOfficial\Infrastructure\InstantApiTransport;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) );
}

/** Real bounded request handling with an offline PSR-18 client. */
final class InstantApiTransportDiagnosticsTest extends TestCase {
	protected function tearDown(): void {
		\KiriminAja\Base\Config\Cache\Cache::resetStore();
	}

	private function transport( array $queue ): InstantApiTransport {
		\KiriminAja\Base\Config\Cache\Cache::setStore( new class() implements \KiriminAja\Contracts\CacheStoreContract {
			private array $values = array();
			public function get( string $key ): mixed { return $this->values[ $key ] ?? null; }
			public function put( string $key, mixed $value, int $expiry ): bool { $this->values[ $key ] = $value; return true; }
			public function remove( string $key ): bool { unset( $this->values[ $key ] ); return true; }
		} );
		\KiriminAja\Base\Config\KiriminAjaConfig::setApiTokenKey( 'secret-token' );
		$client = new class( $queue ) implements ClientInterface {
			private array $queue;
			public function __construct( array $queue ) { $this->queue = $queue; }
			public function sendRequest( RequestInterface $request ): ResponseInterface {
				$response = array_shift( $this->queue );
				if ( $response instanceof \Throwable ) {
					throw $response;
				}
				if ( ! $response instanceof ResponseInterface ) {
					throw new \LogicException( 'Unexpected request or retry.' );
				}
				return $response;
			}
		};
		$transport = new class() extends InstantApiTransport {
			public static ClientInterface $client;
			public static bool $throw_on_creation = false;
			public string $scheme = 'https';
			protected static function createClient( array $options ): ClientInterface {
				if ( self::$throw_on_creation ) { throw new \RuntimeException( 'secret-client-creation' ); }
				return self::$client;
			}
			protected function url( $endpoint ): string { return $this->scheme . '://secret-endpoint.invalid/' . $endpoint; }
		};
		$transport::$client = $client;
		$transport::$throw_on_creation = false;
		return $transport;
	}

	private function assertDiagnostics( InstantApiTransport $transport, string $code, ?int $http_status, bool $submitted = true ): void {
		$diagnostics = $transport->diagnostics();
		$this->assertSame( array( 'code', 'http_status', 'elapsed_ms', 'submitted' ), array_keys( array_diff_key( $diagnostics, array( 'error_body' => true ) ) ) );
		$this->assertSame( $code, $diagnostics['code'] );
		$this->assertSame( $http_status, $diagnostics['http_status'] );
		$this->assertSame( $submitted, $diagnostics['submitted'] );
		$this->assertIsInt( $diagnostics['elapsed_ms'] );
		$this->assertGreaterThanOrEqual( 0, $diagnostics['elapsed_ms'] );
		$this->assertStringNotContainsString( 'secret', json_encode( $diagnostics ) );
	}

	public function test_diagnostics_are_empty_initially_reset_on_reuse_and_never_disclose_secrets(): void {
		$transport = $this->transport( array(
			new Response( 500, array( 'X-Secret' => 'secret-header' ), 'secret-upstream-body' ),
			new Response( 200, array(), '{secret-malformed-json' ),
			new Response( 201, array(), '{"status":true}' ),
			new \RuntimeException( 'secret-exception secret-token secret-pin' ),
			new Response( 202, array(), '{"status":false}' ),
		) );
		$this->assertSame( array(), $transport->diagnostics() );
		$failure = array( false, 'Instant network request failed.' );
		foreach ( array( array( 'http_failure', 500 ), array( 'invalid_json', 200 ) ) as $expected ) {
			$this->assertSame( $failure, $transport->post( 'secret-endpoint', array( 'pin' => 'secret-pin' ) ) );
			$this->assertDiagnostics( $transport, $expected[0], $expected[1] );
		}
		$this->assertSame( array( true, array( 'status' => true ) ), $transport->post( 'secret-endpoint', array() ) );
		$this->assertDiagnostics( $transport, 'transport_success', 201 );
		$snapshot = $transport->diagnostics();
		$snapshot['code'] = 'secret-mutation';
		$this->assertDiagnostics( $transport, 'transport_success', 201 );
		$this->assertSame( $failure, $transport->post( 'secret-endpoint', array( 'pin' => 'secret-pin' ) ) );
		$this->assertDiagnostics( $transport, 'transport_exception', null );
		$this->assertSame( array( true, array( 'status' => false ) ), $transport->postWithQuery( 'secret-endpoint' ) );
		$this->assertDiagnostics( $transport, 'transport_success', 202 );
		$this->assertSame( $failure, $transport->put( 'secret-endpoint', array() ) );
		$this->assertDiagnostics( $transport, 'method_unsupported', null, false );
		$transport->scheme = 'http';
		$this->assertSame( $failure, $transport->get( 'secret-endpoint' ) );
		$this->assertDiagnostics( $transport, 'insecure_url', null, false );
		$transport->scheme = 'https';
		$transport::$throw_on_creation = true;
		$this->assertSame( $failure, $transport->delete( 'secret-endpoint' ) );
		$this->assertDiagnostics( $transport, 'transport_exception', null, false );
	}

	public function test_response_rejection_branches_preserve_the_failure_tuple(): void {
		$stalled = $this->createMock( StreamInterface::class );
		$stalled->method( 'eof' )->willReturn( false );
		$stalled->method( 'read' )->willReturn( '' );
		$cases = array(
			array( new Response( 200, array( 'Content-Length' => 'secret-invalid-size' ), '{}' ), 'invalid_content_length' ),
			array( new Response( 200, array( 'Content-Length' => '2097153' ), '{}' ), 'invalid_content_length' ),
			array( new Response( 200, array(), $stalled ), 'stalled_response' ),
			array( new Response( 200, array(), str_repeat( 'x', 2097153 ) ), 'oversized_response' ),
			array( new Response( 200, array(), '"secret-scalar"' ), 'invalid_json_shape' ),
			array( new \JsonException( 'secret-json-exception' ), 'invalid_json' ),
		);
		foreach ( $cases as $case ) {
			$transport = $this->transport( array( $case[0] ) );
			$this->assertSame( array( false, 'Instant network request failed.' ), $transport->get( 'secret-endpoint' ) );
			$this->assertDiagnostics( $transport, $case[1], $case[0] instanceof ResponseInterface ? 200 : null );
		}
	}

	public function test_http_error_summary_is_whitelisted_bounded_and_cleared_on_reuse(): void {
		$body = array( 'status'=>false, 'result'=>array(), 'message'=>'Invalid PIN 123456 secret-token insufficient credit balance', 'errors'=>array(
			'packages.0.destination.phone'=>array('private 081234567890'),
			'packages'=>array(array('items'=>array(array('weight'=>array('private value'))))),
			'packages[0].shipping_cost'=>array('secret pricing'),
			'pin'=>array('secret-pin'),
			'private-customer-address'=>array('secret-token'),
		), 'payment_id'=>'secret-payment' );
		$transport = $this->transport( array( new Response(400, array(), json_encode($body)), new Response(200, array(), '{"status":true}') ) );
		$this->assertSame(array(false, 'Instant network request failed.'), $transport->post('secret-endpoint', array()));
		$this->assertSame($body, $transport->errorResponse());
		$this->assertDiagnostics($transport, 'http_failure', 400);
		$summary = $transport->diagnostics()['error_body'];
		$this->assertSame('json_object', $summary['format']);
		$this->assertFalse($summary['status']);
		$this->assertTrue($summary['message_present']);
		$this->assertSame(array('pin', 'credit_balance', 'authentication', 'validation'), $summary['message_categories']);
		$this->assertContains('packages.destination.phone', $summary['validation_fields']);
		$this->assertContains('packages.shipping_cost', $summary['validation_fields']);
		$this->assertContains('packages.items.weight', $summary['validation_fields']);
		$this->assertContains('pin', $summary['validation_fields']);
		foreach(array('123456','081234567890','private','secret') as $secret) {
			$this->assertStringNotContainsString($secret, json_encode($transport->diagnostics()));
		}
		$transport->post('secret-endpoint', array());
		$this->assertNull($transport->errorResponse());
		$this->assertArrayNotHasKey('error_body', $transport->diagnostics());
		foreach(array(array('body'=>'null','format'=>'unexpected_json_shape'), array('body'=>'not-json secret-token','format'=>'invalid_json')) as $case) {
			$transport = $this->transport(array(new Response(400, array(), $case['body'])));
			$this->assertSame(array(false, 'Instant network request failed.'), $transport->post('secret-endpoint', array()));
			$this->assertNull($transport->errorResponse());
			$this->assertDiagnostics($transport,'http_failure',400);
			$this->assertSame($case['format'], $transport->diagnostics()['error_body']['format']);
		}
		foreach(array(
			array(new Response(400, array('Content-Length'=>'2097153'), '{}'), 'invalid_content_length'),
			array(new Response(422, array(), str_repeat('x',2097153)), 'oversized_response'),
		) as $case) {
			$transport = $this->transport(array($case[0]));
			$this->assertSame(array(false, 'Instant network request failed.'), $transport->post('secret-endpoint', array()));
			$this->assertNull($transport->errorResponse());
			$this->assertDiagnostics($transport, $case[1], $case[0]->getStatusCode());
		}
	}
}
