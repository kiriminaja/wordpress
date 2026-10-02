<?php
namespace KiriminAjaOfficial\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Psr\Http\Client\ClientInterface;

/** Single-attempt SDK address transport; inherits TLS/body/redirect guards. */
class AddressApiTransport extends InstantApiTransport {
    public const REQUEST_TIMEOUT = 8;

    protected static function createClient( array $options ): ClientInterface {
        $options[ CURLOPT_TIMEOUT ] = self::REQUEST_TIMEOUT;
        $options[ CURLOPT_CONNECTTIMEOUT ] = 3;
        return static::createAddressClient( $options );
    }

    /** Offline seam observes final options without replacing the timeout policy. */
    protected static function createAddressClient( array $options ): ClientInterface {
        return parent::createClient( $options );
    }
}
