<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Local courier artwork and display-only rate identity. Never infer from labels. */
final class CourierLogoAssets {
	/** @return array<string, string> Canonical courier codes and bundled filenames. */
	private static function files(): array {
		return array(
			'anteraja'     => 'anteraja.png',
			'borzo'        => 'borzo.png',
			'gosend'       => 'gosend.png',
			'grab_express' => 'grab-express.png',
			'idx'          => 'id-express.png',
			'jnt'          => 'jnt.png',
			'jnt_cargo'    => 'jnt-cargo.png',
			'jne'          => 'jne.png',
			'lalamove'     => 'lalamove.png',
			'lion'         => 'lion.png',
			'ncs'          => 'ncs.png',
			'ninja'        => 'ninja.png',
			'ninja_inter'  => 'ninja-inter.png',
			'pos'          => 'pos.png',
			'paxel'        => 'paxel.png',
			'rpx'          => 'rpx.png',
			'sap'          => 'sap.png',
			'sentral'      => 'sentral.png',
			'sicepat'      => 'sicepat.png',
			'spx'          => 'shopee-express.png',
			'tiki'         => 'tiki.png',
		);
	}

	/** @return array<string, string> Explicit API aliases, not courier name guesses. */
	private static function aliases(): array {
		return array(
			'idexpress' => 'idx',
			'id_express' => 'idx',
			'jntcargo' => 'jnt_cargo',
			'jtcargo' => 'jnt_cargo',
			'lionparcel' => 'lion',
			'posindonesia' => 'pos',
			'sapx' => 'sap',
			'sentral_cargo' => 'sentral',
			'shopee-express' => 'spx',
			'shopee_express' => 'spx',
		);
	}

	/** @return array<string, string> All artwork is shipped with the plugin. */
	public static function urls(): array {
		$urls = array();
		foreach ( self::files() as $code => $file ) {
			$urls[ $code ] = KIRIOF_URL . 'assets/buyer/img/couriers/' . $file;
		}
		return $urls;
	}

	/** Only exact whitelist entries may become data attributes or asset map keys. */
	private static function canonical( $code ): string {
		if ( ! is_string( $code ) ) {
			return '';
		}
		$code = strtolower( trim( $code ) );
		$code = self::aliases()[ $code ] ?? $code;
		return isset( self::files()[ $code ] ) ? $code : '';
	}

	/**
	 * Return a safe courier code for this plugin's rates, or an empty fallback.
	 *
	 * @param object $rate WooCommerce shipping rate (or compatible fixture).
	 */
	public static function forRate( $rate ): string {
		if ( ! is_object( $rate ) ) {
			return '';
		}
		$id = is_callable( array( $rate, 'get_id' ) ) ? $rate->get_id() : ( $rate->id ?? '' );
		$id = is_string( $id ) ? $id : '';
		$has_method = is_callable( array( $rate, 'get_method_id' ) );
		$method = $has_method ? $rate->get_method_id() : null;
		if ( $has_method && ! in_array( $method, array( 'kiriminaja-official', 'kiriminaja-instant' ), true ) ) {
			return '';
		}
		$parts = array();
		$has_plugin_id = 1 === preg_match( '/^(kiriminaja-official|kiriminaja-instant)(?::[0-9]+)?[:_](.+)$/D', $id, $parts );
		if ( null === $method ) {
			if ( ! $has_plugin_id ) {
				return '';
			}
			$method = $parts[1];
		}
		if ( is_callable( array( $rate, 'get_meta' ) ) ) {
			$code = $rate->get_meta( 'kiriminaja-instant' === $method ? 'kiriof_instant_courier' : 'kiriof_rate_service', true );
			if ( '' !== $code && null !== $code ) {
				return self::canonical( $code );
			}
		}
		if ( ! $has_plugin_id || $parts[1] !== $method ) {
			return '';
		}
		// Support historical underscore IDs and zone-instance colon IDs. Longest
		// whitelist token wins, so grab_express and jnt_cargo are never truncated.
		$suffix = preg_replace( '/^[0-9]+[:_]/', '', $parts[2] );
		$codes = array_merge( array_keys( self::files() ), array_keys( self::aliases() ) );
		usort( $codes, static function ( $left, $right ) {
			return strlen( $right ) <=> strlen( $left );
		} );
		foreach ( $codes as $code ) {
			if ( 1 === preg_match( '/^' . preg_quote( $code, '/' ) . '[:_][a-z0-9][a-z0-9_-]*$/iD', $suffix ) ) {
				return self::canonical( $code );
			}
		}
		return '';
	}
}
