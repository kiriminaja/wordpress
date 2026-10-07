<?php
namespace KiriminAjaOfficial\Infrastructure;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Plain-text diagnostic messages only; never a request/response serialization. */
final class InstantDiagnosticRedactor {
	private const MAX_MESSAGE_BYTES = 2048;

	/** Redact the entire decoded message before applying the diagnostic byte limit. */
	public static function redact( string $text, array $request, string $token ): string {
		$text = self::plainText( $text );
		$candidates = array();
		if ( '' !== $token ) {
			$candidates[] = $token;
		}
		self::collectCandidates( $request, $candidates );
		$variants = array();
		foreach ( $candidates as $candidate ) {
			foreach ( array( $candidate, rawurlencode( $candidate ), urlencode( $candidate ), substr( (string) json_encode( $candidate ), 1, -1 ) ) as $variant ) {
				$variant = self::plainText( $variant );
				if ( '' !== $variant ) {
					$variants[ $variant ] = '[redacted]';
				}
			}
		}
		// Longest first so overlapping names, addresses, and tokens cannot leak suffixes.
		uksort( $variants, static function ( $left, $right ): int {
			return strlen( (string) $right ) <=> strlen( (string) $left );
		} );
		$text = strtr( $text, $variants );

		$patterns = array(
			// Arbitrary contact details and links, including query credentials.
			'~\b(?:https?://|www\.)[^\s<>"\']+~i',
			'~[a-z0-9.!#$%&\'*+/=?^_`{|}\~-]+@[a-z0-9.-]+\.[a-z]{2,}~i',
			'~\bBearer\s+[^\s,"\'<>]+~i',
		);
		foreach ( $patterns as $pattern ) {
			$text = preg_replace( $pattern, '[redacted]', $text ) ?? '';
		}
		// Assignment syntax (including echoed JSON), not explanatory "PIN is invalid" prose.
		$label = '(?:api[_ -]?key|key|access[_ -]?token|token|authorization|pin|password|secret)';
		$text = preg_replace( '~\b(' . $label . ')(["\']?\s*[:=]\s*)(?:"[^"]*"|\'[^\']*\'|[^\s,;}\]]+)~i', '$1$2[redacted]', $text ) ?? '';
		$text = preg_replace( '~\b(' . $label . ')(\s+)(?:"[^"]*"|\'[^\']*\'|[0-9]+\b|(?=[a-z0-9._-]*[0-9._-])[a-z0-9._-]+)~i', '$1$2[redacted]', $text ) ?? '';
		$text = preg_replace( '~\b((?:api[_ -]?key|key|access[_ -]?token|token|password|secret))(\s+)(?!(?:is|was|has|not|invalid|required|expired|missing)\b)[a-z0-9._-]+~i', '$1$2[redacted]', $text ) ?? '';
		// Coordinates, contiguous identifiers, and commonly formatted phone numbers.
		$text = preg_replace( '~(?<![\w.])-?\d{1,3}\.\d+(?![\w.])|(?<!\w)\+?\d(?:[\s().-]*\d){7,}(?!\w)|\d{5,}~', '[redacted]', $text ) ?? '';
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) ?? '' );
		$text = substr( $text, 0, self::MAX_MESSAGE_BYTES );
		// Never leave a partial UTF-8 character at the byte boundary.
		while ( '' !== $text && 1 !== preg_match( '//u', $text ) ) {
			$text = substr( $text, 0, -1 );
		}
		return $text;
	}

	/** Flatten only values, never field names or generic service/payment vocabulary. */
	private static function collectCandidates( array $request, array &$candidates ): void {
		$generic = array( 'service', 'service_type', 'payment_method', 'package_type_id', 'vehicle', 'method', 'courier', 'courier_code' );
		foreach ( $request as $key => $value ) {
			if ( is_array( $value ) ) {
				self::collectCandidates( $value, $candidates );
				continue;
			}
			if ( in_array( strtolower( (string) $key ), $generic, true ) || ! is_scalar( $value ) || is_bool( $value ) ) {
				continue;
			}
			$identity = preg_match( '/(?:^|_)(?:name|address|phone|email|latitude|longitude|lat|lng|lon|pin|order_id|description|token|key|password|zipcode|price|amount|shipping_cost)(?:$|_)/i', (string) $key );
			if ( $identity || ( is_string( $value ) && strlen( $value ) >= 4 ) ) {
				$candidates[] = (string) $value;
			}
		}
	}

	/** Remove markup/control characters and normalize escaped JSON echoes. */
	private static function plainText( string $text ): string {
		$text = str_replace( array( '\\"', '\\/' ), array( '"', '/' ), $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = strip_tags( $text );
		return preg_replace( '/[\x00-\x20\x7f]+/', ' ', $text ) ?? '';
	}
}
