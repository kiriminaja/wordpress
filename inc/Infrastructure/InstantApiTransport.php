<?php
namespace KiriminAjaOfficial\Infrastructure;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Http\Client\Curl\Client;
use KiriminAja\Base\Api\Api;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/** Bounded transport for direct Instant operations, without upstream error disclosure. */
class InstantApiTransport extends Api {
	private const MAX_BODY_BYTES = 2097152;
	private array $diagnostics = array();
	private ?array $error_response = null;

	/** Internal rejection evidence only. Never log or return this body to the browser. */
	public function errorResponse(): ?array {
		return $this->error_response;
	}

	/** Safe metadata for the last request; never contains request or response content. */
	public function diagnostics(): array {
		return $this->diagnostics;
	}

	/** Factory seam for offline transport tests; SDK configuration remains inherited. */
	protected static function createClient( array $options ): ClientInterface {
		$factory        = new Psr17Factory();
		$stream_factory = new class( $factory ) implements StreamFactoryInterface {
			private Psr17Factory $factory;

			public function __construct( Psr17Factory $factory ) {
				$this->factory = $factory;
			}

			public function createStream( string $content = '' ): StreamInterface {
				return new BoundedResponseStream( $this->factory->createStream( $content ) );
			}

			public function createStreamFromFile( string $filename, string $mode = 'r' ): StreamInterface {
				return new BoundedResponseStream( $this->factory->createStreamFromFile( $filename, $mode ) );
			}

			public function createStreamFromResource( $resource ): StreamInterface {
				return new BoundedResponseStream( $this->factory->createStreamFromResource( $resource ) );
			}
		};

		// Curl owns its write/header callbacks; bound its response sink instead.
		return new Client( $factory, $stream_factory, $options );
	}

	/** Preserve the SDK response tuple, not its private method/dataOption state. */
	protected function request( $method, $endpoint, $data ): array {
		return $this->boundedRequest( $method, $endpoint, $data );
	}

	/** Keep the SDK's query-only POST entry point on the same bounded transport. */
	protected function requestWithQuery( string $method, string $endpoint, ?array $query = null ): array {
		return $this->boundedRequest( $method, $endpoint, $query, true );
	}

	/** Send once, with no redirect, middleware, retry, or upstream error disclosure. */
	private function boundedRequest( $method, $endpoint, $data, bool $query_only = false ): array {
		$failure = array( false, 'Instant network request failed.' );
		$started = hrtime( true );
		$this->error_response = null;
		$this->diagnostics = array(
			'code'        => 'transport_exception',
			'http_status' => null,
			'elapsed_ms'  => 0,
			'submitted'   => false,
		);

		try {
			if ( ! in_array( $method, array( 'GET', 'POST', 'DELETE' ), true ) ) {
				$this->diagnostics['code'] = 'method_unsupported';
				return $failure;
			}
			$request = $this->createRequest( $method, $endpoint, $data, $query_only );
			if ( 'https' !== strtolower( $request->getUri()->getScheme() ) ) {
				$this->diagnostics['code'] = 'insecure_url';
				return $failure;
			}

			$options = array(
				CURLOPT_TIMEOUT          => 25,
				CURLOPT_CONNECTTIMEOUT   => 5,
				CURLOPT_SSL_VERIFYPEER   => true,
				CURLOPT_SSL_VERIFYHOST   => 2,
				CURLOPT_FOLLOWLOCATION   => false,
				CURLOPT_MAXREDIRS        => 0,
				CURLOPT_FAILONERROR      => false,
				CURLOPT_PROTOCOLS        => CURLPROTO_HTTPS,
				CURLOPT_REDIR_PROTOCOLS  => CURLPROTO_HTTPS,
				CURLOPT_NOPROGRESS       => false,
				CURLOPT_PROGRESSFUNCTION => static function ( $handle, $download_total, $downloaded, $upload_total, $uploaded ): int {
					// Abort via cURL's return code; never throw from the progress callback.
					return $download_total > self::MAX_BODY_BYTES || $downloaded > self::MAX_BODY_BYTES ? 1 : 0;
				},
			);
			$client = static::createClient( $options );
			// Once sendRequest starts, even an exception cannot prove non-submission.
			$this->diagnostics['submitted'] = true;
			$response = $client->sendRequest( $request );
			$this->diagnostics['http_status'] = $response->getStatusCode();
			$http_failure = $response->getStatusCode() < 200 || $response->getStatusCode() >= 300;
			if ( $http_failure ) {
				$this->diagnostics['code'] = 'http_failure';
			}

			$length = $response->getHeaderLine( 'Content-Length' );
			if ( '' !== $length && ( ! ctype_digit( $length ) || (float) $length > self::MAX_BODY_BYTES ) ) {
				$this->diagnostics['code'] = 'invalid_content_length';
				return $failure;
			}

			// Do not trust Content-Length, including compressed or chunked responses.
			$stream = $response->getBody();
			$body   = '';
			while ( ! $stream->eof() ) {
				$chunk = $stream->read( min( 8192, self::MAX_BODY_BYTES + 1 - strlen( $body ) ) );
				if ( '' === $chunk ) {
					if ( $stream->eof() ) {
						break;
					}
					$this->diagnostics['code'] = 'stalled_response';
					return $failure;
				}
				$body .= $chunk;
				if ( strlen( $body ) > self::MAX_BODY_BYTES ) {
					$this->diagnostics['code'] = 'oversized_response';
					return $failure;
				}
			}
			$decoded = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );
			if ( $http_failure ) {
				$this->error_response = is_array( $decoded ) && ! array_is_list( $decoded ) ? $decoded : null;
				$this->diagnostics['error_body'] = $this->errorSummary( $decoded );
				return $failure;
			}
			$this->diagnostics['code'] = is_array( $decoded ) ? 'transport_success' : 'invalid_json_shape';
			return is_array( $decoded ) ? array( true, $decoded ) : $failure;
		} catch ( \Throwable $throwable ) {
			// Never log or return exception text, credentials, PINs, or response bodies.
			if ( 'http_failure' === $this->diagnostics['code'] && $throwable instanceof \JsonException ) {
				$this->diagnostics['error_body'] = array( 'format' => 'invalid_json' );
			} else {
				$this->diagnostics['code'] = $throwable instanceof \JsonException ? 'invalid_json' : 'transport_exception';
			}
			return $failure;
		} finally {
			$this->diagnostics['elapsed_ms'] = (int) ( ( hrtime( true ) - $started ) / 1000000 );
		}
	}

	/** Whitelisted schema facts/categories, never arbitrary upstream strings or values. */
	private function errorSummary( $body ): array {
		if ( ! is_array( $body ) || array_is_list( $body ) ) {
			return array( 'format' => 'unexpected_json_shape' );
		}
		$summary = array(
			'format' => 'json_object',
			'status_type' => gettype( $body['status'] ?? null ),
			'status' => is_bool( $body['status'] ?? null ) ? $body['status'] : null,
			'result_type' => gettype( $body['result'] ?? $body['results'] ?? null ),
			'message_present' => false,
			'message_categories' => array(),
			'validation_fields' => array(),
		);
		$messages = array();
		foreach ( array( 'message', 'text', 'statusMessage' ) as $key ) {
			if ( is_string( $body[ $key ] ?? null ) && '' !== $body[ $key ] ) {
				$summary['message_present'] = true;
				$messages[] = substr( $body[ $key ], 0, 4096 );
			}
		}
		// Unknown field names can contain a phone, address, or credential: omit them.
		$allowed = array( 'address', 'name', 'phone', 'latitude', 'longitude', 'zipcode', 'payment_method', 'pin', 'packages', 'packages.order_id', 'packages.destination', 'packages.destination.name', 'packages.destination.phone', 'packages.destination.latitude', 'packages.destination.longitude', 'packages.destination.address', 'packages.shipping_cost', 'packages.service', 'packages.service_type', 'packages.package_type_id', 'packages.vehicle', 'packages.items', 'packages.items.name', 'packages.items.price', 'packages.items.weight' );
		$stack = array( array( '', $body['errors'] ?? $body['validation_errors'] ?? array() ) );
		$visited = 0;
		while ( ! empty( $stack ) && ++$visited <= 200 ) {
			list( $prefix, $errors ) = array_pop( $stack );
			if ( ! is_array( $errors ) ) {
				continue;
			}
			foreach ( array_slice( $errors, 0, 50, true ) as $field => $value ) {
				$path = trim( $prefix . '.' . preg_replace( '/\[[0-9]+\]/', '', (string) $field ), '.' );
				$path = preg_replace( '/(?:^|\.)[0-9]+(?=\.|$)/', '', $path );
				$path = trim( $path, '.' );
				if ( in_array( $path, $allowed, true ) ) {
					$summary['validation_fields'][] = $path;
				}
				if ( is_array( $value ) ) {
					$stack[] = array( $path, $value );
				}
			}
		}
		$text = strtolower( implode( ' ', $messages ) );
		foreach ( array(
			'pin' => '/\bpin\b/',
			'credit_balance' => '/\b(balance|saldo|insufficient|kredit|credit)\b/',
			'authentication' => '/\b(unauthenticated|unauthorized|token|credential|authorization)\b/',
			'validation' => '/\b(validation|validasi|required|wajib|invalid|minimum|maximum)\b/',
			'coverage' => '/\b(coverage|distance|jangkauan|jarak)\b/',
			'duplicate_order' => '/\b(duplicate|duplikat|already exists)\b/',
		) as $category => $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				$summary['message_categories'][] = $category;
			}
		}
		$summary['validation_fields'] = array_values( array_unique( $summary['validation_fields'] ) );
		return $summary;
	}
}
