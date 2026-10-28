<?php
/** Route-specific Vite dependency discovery for the WordPress admin head. */

namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WorkspaceAssets {
	/**
	 * Return unique local JavaScript files, following static imports only.
	 *
	 * @param string $manifest_path Absolute generated manifest path.
	 * @param string $page          Sanitized admin page slug.
	 * @param string $section       Sanitized settings section.
	 * @return string[]
	 */
	public function modules( string $manifest_path, string $page, string $section = '' ): array {
		$routes = array(
			'kiriminaja-transaction'           => 'src/lib/transactions/TransactionsApp.svelte',
			'kiriminaja-transaction-detail'    => 'src/lib/transaction-detail/TransactionDetail.svelte',
			'kiriminaja-request-pickup'        => 'src/lib/payments/PaymentsList.svelte',
			'kiriminaja-request-pickup-detail' => 'src/lib/pickup-detail/PickupDetail.svelte',
			'kiriminaja-setting'               => 'src/lib/SettingsRoot.svelte',
		);
		if ( ! isset( $routes[ $page ] ) || ! is_readable( $manifest_path ) ) {
			return array();
		}

		$sections = array(
			'account'   => 'AccountSection',
			'couriers'  => 'CouriersSection',
			'tracking'  => 'TrackingSection',
			'technical' => 'TechnicalSection',
		);
		if ( 'kiriminaja-setting' === $page && isset( $sections[ $section ] ) ) {
			$routes[ $page ] = 'src/lib/settings/' . $sections[ $section ] . '.svelte';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local generated build metadata, not a remote request.
		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
		if ( ! is_array( $manifest ) ) {
			return array();
		}

		$seen  = array();
		$files = array();
		$visit = static function ( string $key ) use ( &$visit, &$seen, &$files, $manifest ): void {
			if ( isset( $seen[ $key ] ) || ! isset( $manifest[ $key ] ) || ! is_array( $manifest[ $key ] ) ) {
				return;
			}
			$seen[ $key ] = true;
			$chunk        = $manifest[ $key ];
			$file         = $chunk['file'] ?? '';
			if ( is_string( $file ) && preg_match( '/\A[a-zA-Z0-9_-][a-zA-Z0-9_\/-]*\.js\z/', $file ) && false === strpos( $file, '..' ) ) {
				$files[ $file ] = $file;
			}
			$imports = isset( $chunk['imports'] ) && is_array( $chunk['imports'] ) ? $chunk['imports'] : array();
			foreach ( $imports as $dependency ) {
				if ( is_string( $dependency ) ) {
					$visit( $dependency );
				}
			}
		};
		$visit( 'src/entries/admin-workspace.ts' );
		$visit( $routes[ $page ] );
		return array_values( $files );
	}
}
