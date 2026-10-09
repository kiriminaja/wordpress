<?php

use KiriminAjaOfficial\Infrastructure\InstantApiTransport;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) { return json_encode( $value ); }
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
		$text = strip_tags( $text );
		return trim( $remove_breaks ? preg_replace( '/[\\r\\n\\t ]+/', ' ', $text ) : $text );
	}
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) );
}
require_once dirname( __DIR__ ) . '/inc/Infrastructure/InstantDiagnosticRedactor.php';

/** Real bounded request handling with an offline PSR-18 client. */
final class InstantApiTransportDiagnosticsTest extends TestCase {
	public function test_redaction_removes_script_style_and_decoded_markup_without_leaking_secrets(): void {
		$message = \KiriminAjaOfficial\Infrastructure\InstantDiagnosticRedactor::redact(
			'&lt;script&gt;private-script-token&lt;/script&gt;<style>private-style-token</style><b>PIN is invalid</b> token=private-account-token https://carrier.invalid/?key=private-url-token',
			array(),
			'private-account-token'
		);
		$this->assertSame(
			array(
				'contains PIN is invalid' => true,
				'redacts private-' => false,
				'redacts <' => false,
				'contains [redacted]' => true,
			),
			array(
				'contains PIN is invalid' => str_contains( $message, 'PIN is invalid' ),
				'redacts private-' => str_contains( $message, 'private-' ),
				'redacts <' => str_contains( $message, '<' ),
				'contains [redacted]' => str_contains( $message, '[redacted]' ),
			)
		);
	}

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
		$this->assertSame(
			array(
				'diagnostics' => array( 'code', 'http_status', 'elapsed_ms', 'submitted' ),
				'code' => $code,
				'http_status' => $http_status,
				'submitted' => $submitted,
				'elapsed_ms integer' => true,
				'elapsed_ms bound' => true,
				'redacts secret' => false,
			),
			array(
				'diagnostics' => array_keys( array_diff_key( $diagnostics, array( 'error_body' => true ) ) ),
				'code' => $diagnostics['code'],
				'http_status' => $diagnostics['http_status'],
				'submitted' => $diagnostics['submitted'],
				'elapsed_ms integer' => is_int( $diagnostics['elapsed_ms'] ),
				'elapsed_ms bound' => $diagnostics['elapsed_ms'] >= 0,
				'redacts secret' => str_contains( json_encode( $diagnostics ), 'secret' ),
			)
		);
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
                'packages.0.destination.phone'=>array('invalid 081234567890'),
                'packages'=>array(array('items'=>array(array('weight'=>array('invalid value'))))),
                'packages[0].shipping_cost'=>array('invalid pricing'),
			'pin'=>array('secret-pin'),
			'private-customer-address'=>array('secret-token'),
		), 'payment_id'=>'secret-payment' );
		$transport = $this->transport( array( new Response(400, array(), json_encode($body)), new Response(200, array(), '{"status":true}') ) );
		$this->assertSame(
			array(
				'transport' => array(false, 'Instant network request failed.'),
				'subsequent transport' => $body,
			),
			array(
				'transport' => $transport->post('secret-endpoint', array()),
				'subsequent transport' => $transport->errorResponse(),
			)
		);
		$this->assertDiagnostics($transport, 'http_failure', 400);
		$summary = $transport->diagnostics()['error_body'];
		$this->assertSame(
			array(
				'format' => 'json_object',
				'status' => false,
				'message_present' => true,
				'message_categories' => array('pin', 'credit_balance', 'authentication', 'validation'),
				'phone validation' => true,
				'shipping cost validation' => true,
				'item weight validation' => true,
				'PIN validation' => true,
			),
			array(
				'format' => $summary['format'],
				'status' => $summary['status'],
				'message_present' => $summary['message_present'],
				'message_categories' => $summary['message_categories'],
				'phone validation' => in_array( 'packages.destination.phone', $summary['validation_fields'] ),
				'shipping cost validation' => in_array( 'packages.shipping_cost', $summary['validation_fields'] ),
				'item weight validation' => in_array( 'packages.items.weight', $summary['validation_fields'] ),
				'PIN validation' => in_array( 'pin', $summary['validation_fields'] ),
			)
		);
		$redactions = array();
		foreach ( array('123456','081234567890','private','secret') as $secret ) {
			$redactions[ $secret ] = str_contains( json_encode($transport->diagnostics()), $secret );
		}
		$this->assertSame( array_fill_keys( array_keys( $redactions ), false ), $redactions, 'Diagnostic redaction contract' );
		$transport->post('secret-endpoint', array());
		$this->assertSame(
			array(
				'transport' => null,
				'error_body present' => false,
			),
			array(
				'transport' => $transport->errorResponse(),
				'error_body present' => array_key_exists( 'error_body', $transport->diagnostics() ),
			)
		);
		foreach(array(array('body'=>'null','format'=>'unexpected_json_shape'), array('body'=>'not-json secret-token','format'=>'invalid_json')) as $case) {
			$transport = $this->transport(array(new Response(400, array(), $case['body'])));
			$this->assertSame(
				array(
					'transport' => array(false, 'Instant network request failed.'),
					'subsequent transport' => null,
				),
				array(
					'transport' => $transport->post('secret-endpoint', array()),
					'subsequent transport' => $transport->errorResponse(),
				)
			);
			$this->assertDiagnostics($transport,'http_failure',400);
			$this->assertSame($case['format'], $transport->diagnostics()['error_body']['format']);
		}
		foreach(array(
			array(new Response(400, array('Content-Length'=>'2097153'), '{}'), 'invalid_content_length'),
			array(new Response(422, array(), str_repeat('x',2097153)), 'oversized_response'),
		) as $case) {
			$transport = $this->transport(array($case[0]));
			$this->assertSame(
				array(
					'transport' => array(false, 'Instant network request failed.'),
					'subsequent transport' => null,
				),
				array(
					'transport' => $transport->post('secret-endpoint', array()),
					'subsequent transport' => $transport->errorResponse(),
				)
			);
			$this->assertDiagnostics($transport, $case[1], $case[0]->getStatusCode());
		}
	}

	public function test_real_explanations_are_preserved_without_echoed_request_values(): void {
		$payload = array(
			'name' => 'Nadia Kusuma',
			'address' => 'Jalan Melati Raya Nomor Dua',
			'phone' => '+62 (812) 3456-7890',
			'latitude' => -7.81,
			'longitude' => 110.32,
			'pin' => '4532',
			'payment_method' => 'credit',
			'packages' => array( array( 'order_id' => 'order-echo-alpha', 'service' => 'gosend', 'description' => 'Fragile parcel violet', 'items' => array( array( 'name' => 'Ceramic violet cup', 'price' => 725 ) ) ) ),
		);
		$prose = 'Akun Anda belum aktif untuk layanan instant. payment_method is not available for this account. PIN is invalid.';
		$body = array(
			'message' => '<b>' . $prose . '</b> ' . json_encode( $payload ),
			'text' => 'PIN: "otherpin" token labeltoken key=anotherkey Bearer unknowncredential customer@example.test https://example.test/private',
			'statusMessage' => 'secret-token Request denied.',
		);
		foreach ( array( false, true ) as $query ) {
			$transport = $this->transport( array( new Response( 422, array(), json_encode( $body ) ) ) );
			$result = $query ? $transport->postWithQuery( 'booking', $payload ) : $transport->post( 'booking', $payload );
			$this->assertSame( array( false, 'Instant network request failed.' ), $result );
			$messages = $transport->diagnostics()['error_body']['messages'];
			$this->assertSame(
				array(
					'contains $prose' => true,
					'contains gosend' => true,
					'contains credit' => true,
					'statusMessage' => '[redacted] Request denied.',
				),
				array(
					'contains $prose' => str_contains( $messages['message'], $prose ),
					'contains gosend' => str_contains( $messages['message'], 'gosend' ),
					'contains credit' => str_contains( $messages['message'], 'credit' ),
					'statusMessage' => $messages['statusMessage'],
				)
			);
			$redactions = array();
			foreach ( array( 'Nadia', 'Melati', '3456', '-7.81', '110.32', '4532', 'order-echo-alpha', 'Fragile parcel violet', 'Ceramic violet cup', '725', 'otherpin', 'labeltoken', 'anotherkey', 'unknowncredential', 'customer@example.test', 'https://example.test/private', 'secret-token' ) as $secret ) {
				$redactions[ $secret ] = str_contains( json_encode( $transport->diagnostics() ), $secret );
			}
			$this->assertSame( array_fill_keys( array_keys( $redactions ), false ), $redactions, 'Diagnostic redaction contract' );
		}
	}

	public function test_redaction_precedes_truncation_even_for_long_overlapping_secrets(): void {
		$secret = 'private-start-' . str_repeat( 's', 5000 ) . '-private-end';
		$body = array( 'message' => str_repeat( 'x', 2035 ) . $secret . ' Explanation.' );
		$transport = $this->transport( array( new Response( 400, array(), json_encode( $body ) ) ) );
		$transport->post( 'booking', array( 'address' => $secret ) );
		$message = $transport->diagnostics()['error_body']['messages']['message'];
		$this->assertSame(
			array(
				'message bound' => true,
				'redacts private' => false,
				'contains [redacted]' => true,
			),
			array(
				'message bound' => strlen( $message ) <= 2048,
				'redacts private' => str_contains( $message, 'private' ),
				'contains [redacted]' => str_contains( $message, '[redacted]' ),
			)
		);
	}
}
