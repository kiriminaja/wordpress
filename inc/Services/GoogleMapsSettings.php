<?php

namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stores the restricted public browser credential separately from integration secrets. */
class GoogleMapsSettings {
	public const OPTION = 'kiriof_google_maps_browser_key';
	public const NONCE_ACTION = 'kiriof_google_maps_settings';

	/** @return array{provider: string, apiKey: string} Public map-loader configuration, not settings UI data. */
	public function config(): array {
		$key = $this->storedKey();
		return array( 'provider' => '' === $key ? 'leaflet' : 'google', 'apiKey' => $key );
	}

	/** @return array{configured: bool, maskedKey: string} Never exposes the complete credential. */
	public function settingsSummary(): array {
		$key = $this->storedKey();
		return array(
			'configured' => '' !== $key,
			'maskedKey'  => '' === $key ? '' : substr( $key, 0, 4 ) . str_repeat( '*', max( 4, strlen( $key ) - 7 ) ) . substr( $key, -3 ),
		);
	}

	/**
	 * Accept an already-unslashed exact credential. Empty means keep the current setting.
	 *
	 * @param mixed $key Proposed browser credential.
	 * @return bool Whether the input was valid and persisted (or unchanged).
	 */
	public function save( $key ): bool {
		if ( ! is_string( $key ) ) {
			return false;
		}
		if ( '' === $key ) {
			return true;
		}
		if ( ! $this->validKey( $key ) ) {
			return false;
		}
		// Native option API, explicitly disabling autoload even when replacing an existing value.
		update_option( self::OPTION, $key, false );
		return $this->storedKey() === $key;
	}

	/** Removal is explicit; a blank replacement never removes a stored key. */
	public function remove(): bool {
		delete_option( self::OPTION );
		return '' === $this->storedKey();
	}

	private function storedKey(): string {
		$key = get_option( self::OPTION, '' );
		return is_string( $key ) && $this->validKey( $key ) ? $key : '';
	}

	private function validKey( string $key ): bool {
		return 1 === preg_match( '/\A[A-Za-z0-9_-]{8,256}\z/D', $key );
	}
}
