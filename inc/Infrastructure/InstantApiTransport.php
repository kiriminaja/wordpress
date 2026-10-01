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
		if ( ! in_array( $method, array( 'GET', 'POST', 'DELETE' ), true ) ) {
			return $failure;
		}

		try {
			$request = $this->createRequest( $method, $endpoint, $data, $query_only );
			if ( 'https' !== strtolower( $request->getUri()->getScheme() ) ) {
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
			$response = static::createClient( $options )->sendRequest( $request );
			if ( $response->getStatusCode() < 200 || $response->getStatusCode() >= 300 ) {
				return $failure;
			}

			$length = $response->getHeaderLine( 'Content-Length' );
			if ( '' !== $length && ( ! ctype_digit( $length ) || (float) $length > self::MAX_BODY_BYTES ) ) {
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
					return $failure;
				}
				$body .= $chunk;
				if ( strlen( $body ) > self::MAX_BODY_BYTES ) {
					return $failure;
				}
			}
			$decoded = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );
			return is_array( $decoded ) ? array( true, $decoded ) : $failure;
		} catch ( \Throwable $throwable ) {
			// Never log or return exception text, credentials, PINs, or response bodies.
			return $failure;
		}
	}
}
