<?php
namespace KiriminAjaOfficial\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Small cross-request guard for diagnostics caused by repeated upstream failures. */
final class LogThrottle {
	/** @var array<string, int> */
	private static array $memory = array();

	public static function allow( string $key, int $ttl = 900 ): bool {
		$ttl = max( 1, $ttl );
		$transient_key = 'kiriof_log_throttle_' . hash( 'sha256', $key );
		$now           = time();

		if ( function_exists( 'get_transient' ) ) {
			$last = get_transient( $transient_key );
			if ( is_int( $last ) && $last > $now ) {
				return false;
			}
		}

		if ( isset( self::$memory[ $transient_key ] ) && self::$memory[ $transient_key ] > $now ) {
			return false;
		}

		$expires = $now + $ttl;
		self::$memory[ $transient_key ] = $expires;
		if ( function_exists( 'set_transient' ) ) {
			set_transient( $transient_key, $expires, $ttl );
		}

		return true;
	}
}
