<?php
namespace KiriminAjaOfficial\Infrastructure;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use GuzzleHttp\Client;
use KiriminAja\Base\Api\Api;
use Psr\Http\Message\ResponseInterface;

/** Bounded transport for direct Instant operations, without upstream error disclosure. */
class InstantApiTransport extends Api {
	private const MAX_BODY_BYTES = 2097152;

	/** Factory seam for offline transport tests; SDK configuration remains inherited. */
	protected static function createClient(): Client {
		return new Client();
	}

	/** Preserve the SDK response tuple, not its private method/dataOption state. */
	protected function request( $method, $endpoint, $data ): array {
		$failure = array( false, 'Instant network request failed.' );
		if ( ! in_array( $method, array( 'GET', 'POST', 'DELETE' ), true ) ) {
			return $failure;
		}

		try {
			$options = array(
				'headers'         => self::getHeaders(),
				'timeout'         => 25,
				'connect_timeout' => 5,
				'allow_redirects' => false,
				'http_errors'     => false,
				'verify'          => true,
				'on_headers'      => static function ( ResponseInterface $response ): void {
					$length = $response->getHeaderLine( 'Content-Length' );
					if ( '' !== $length && ( ! ctype_digit( $length ) || (float) $length > self::MAX_BODY_BYTES ) ) {
						throw new \RuntimeException( 'Instant response exceeds limit.' );
					}
				},
				'progress'        => static function ( $download_total, $downloaded ): void {
					if ( $download_total > self::MAX_BODY_BYTES || $downloaded > self::MAX_BODY_BYTES ) {
						throw new \RuntimeException( 'Instant response exceeds limit.' );
					}
				},
			);
			$options[ 'GET' === $method ? 'query' : 'json' ] = $data;
			$response = static::createClient()->request( $method, $this->url( $endpoint ), $options );
			if ( $response->getStatusCode() < 200 || $response->getStatusCode() >= 300 ) {
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
