<?php
/**
 * Bounded WordPress.org slug lookups for diagnostic exports.
 *
 * @package KiriminAjaOfficial
 */

namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Directory presence is not proof of the provenance of installed code. */
final class LogDirectoryStatus {
	/** @var float Shared deadline for this export, including both directories. */
	private $deadline;

	/** @var int Number of cold requests in this export. */
	private $requests = 0;

	/** @var array Request-local results, including failures. */
	private $results = array();

	/** Start the shared four-second lookup budget. */
	public function __construct() {
		$this->deadline = microtime( true ) + 4.0;
	}

	/**
	 * Describe a plugin using only its installed path, never its display name.
	 *
	 * @param string $file Plugin basename relative to the plugins directory.
	 * @param string $update_uri Update URI header.
	 * @param bool   $must_use Whether this is a must-use plugin.
	 * @return string Plain-text metadata; escape at the output boundary.
	 */
	public function describe_plugin( string $file, string $update_uri = '', bool $must_use = false ): string {
		$parts = explode( '/', $file );
		$slug  = count( $parts ) > 1 ? $parts[0] : preg_replace( '/\.php$/i', '', $file );
		$basis = count( $parts ) > 1 ? 'plugin folder' : 'single-file basename';
		if ( $must_use ) {
			return $this->describe( 'plugin', $slug, $basis, $update_uri, 'must-use plugin; directory lookup not applicable' );
		}
		return $this->describe( 'plugin', $slug, $basis, $update_uri );
	}

	/**
	 * Describe a theme independently of its parent theme.
	 *
	 * @param string $stylesheet Installed theme stylesheet directory.
	 * @param string $update_uri Update URI header.
	 * @return string Plain-text metadata.
	 */
	public function describe_theme( string $stylesheet, string $update_uri = '' ): string {
		return $this->describe( 'theme', $stylesheet, 'theme stylesheet directory', $update_uri );
	}

	/**
	 * Format the slug basis and conservative directory outcome.
	 *
	 * @param string $kind Directory kind.
	 * @param string $slug Path-derived candidate.
	 * @param string $basis Slug basis.
	 * @param string $update_uri Update URI header.
	 * @param string $reason Optional lookup exclusion.
	 * @return string Plain-text status.
	 */
	private function describe( string $kind, string $slug, string $basis, string $update_uri, string $reason = '' ): string {
		if ( ! preg_match( '/^[a-z0-9][a-z0-9_-]{0,199}$/D', $slug ) ) {
			return 'WordPress.org: unknown; basis: ' . $basis . '; unsupported directory slug';
		}
		if ( '' === $reason && '' !== trim( $update_uri ) && ! preg_match( '~^https://(?:wordpress\.org|w\.org)/' . ( 'plugin' === $kind ? 'plugins' : 'themes' ) . '/' . preg_quote( $slug, '~' ) . '/?$~D', trim( $update_uri ) ) ) {
			$reason = 'external update source';
		}
		$result = '' === $reason ? $this->lookup( $kind, $slug ) : array( 'status' => 'unknown', 'reason' => $reason );
		return 'WordPress.org: ' . $result['status'] . ' for slug ' . $slug . '; basis: ' . $basis . ( '' !== $result['reason'] ? '; ' . $result['reason'] : '' );
	}

	/**
	 * Query fixed official endpoints with short-lived caching of uncertain results.
	 *
	 * @param string $kind Directory kind.
	 * @param string $slug Validated slug.
	 * @return array{status:string,reason:string} Directory observation.
	 */
	private function lookup( string $kind, string $slug ): array {
		$key = 'kiriof_log_dir_v1_' . md5( $kind . ':' . $slug );
		if ( isset( $this->results[ $key ] ) ) {
			return $this->results[ $key ];
		}
		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['status'], $cached['reason'] ) && in_array( $cached['status'], array( 'listed', 'not found', 'unknown' ), true ) && is_string( $cached['reason'] ) ) {
			$this->results[ $key ] = $cached;
			return $cached;
		}
		if ( $this->requests >= 8 || microtime( true ) + 1.0 > $this->deadline ) {
			return array( 'status' => 'unknown', 'reason' => 'lookup budget exhausted' );
		}
		++$this->requests;
		$url = 'https://api.wordpress.org/' . ( 'plugin' === $kind ? 'plugins' : 'themes' ) . '/info/1.2/?' . http_build_query(
			array(
				'action'  => $kind . '_information',
				'request' => array( 'slug' => $slug ),
			),
			'',
			'&'
		);
		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => 1,
				'redirection'         => 0,
				'limit_response_size' => 262144,
				'user-agent'          => 'DirectoryMetadata/1.0',
				'headers'             => array( 'Accept' => 'application/json' ),
				'cookies'             => array(),
				'sslverify'           => true,
			)
		);
		$result = array( 'status' => 'unknown', 'reason' => 'directory unavailable or unrecognized response' );
		if ( ! is_wp_error( $response ) ) {
			$code = wp_remote_retrieve_response_code( $response );
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( 200 === $code && is_array( $data ) && ! isset( $data['error'] ) && ( $data['slug'] ?? null ) === $slug && isset( $data['name'], $data['version'] ) && is_string( $data['name'] ) && '' !== $data['name'] && is_string( $data['version'] ) ) {
				$result = array( 'status' => 'listed', 'reason' => '' );
			} elseif ( in_array( $code, array( 200, 404 ), true ) && is_array( $data ) && ( $data['error'] ?? null ) === ( 'plugin' === $kind ? 'Plugin not found.' : 'Theme not found' ) ) {
				$result = array( 'status' => 'not found', 'reason' => '' );
			}
		}
		set_transient( $key, $result, 'unknown' === $result['status'] ? 300 : 86400 );
		$this->results[ $key ] = $result;
		return $result;
	}
}
