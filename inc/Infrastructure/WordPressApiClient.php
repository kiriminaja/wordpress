<?php
namespace KiriminAjaOfficial\Infrastructure;

use KiriminAja\Base\Api\Api;
use Psr\Http\Message\RequestInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** SDK request construction with the WordPress integration identity. */
class WordPressApiClient extends Api {
	protected function createRequest( string $method, string $endpoint, mixed $data, bool $queryOnly = false ): RequestInterface {
		return parent::createRequest( $method, $endpoint, $data, $queryOnly )->withHeader( 'User-Agent', 'wordpress' );
	}
}
