<?php

namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Calendar-day bounds in the existing stored timestamp domain (no timezone conversion). */
final class ListDateRangeFilter {
	/**
	 * Explicit nonempty endpoints override the legacy month. Malformed endpoints or
	 * inverted ranges fail closed, rather than silently widening the admin list.
	 */
	public static function normalize( array $filters ): array {
		$from = $filters['date_from'] ?? '';
		$to   = $filters['date_to'] ?? '';
		$explicit = '' !== $from || '' !== $to;
		$invalid = ! empty( $filters['date_range_invalid'] );
		foreach ( array( $from, $to ) as $value ) {
			if ( '' !== $value && ! self::isDate( $value ) ) {
				$invalid = true;
			}
		}
		if ( ! $invalid && '' !== $from && '' !== $to && $from > $to ) {
			$invalid = true;
		}
		$month = $filters['month'] ?? '';
		if ( $explicit || $invalid ) {
			$month = '';
		} elseif ( '' !== $month && ( ! is_string( $month ) || ! preg_match( '/\A[0-9]{4}-[0-9]{2}\z/', $month ) || ! self::isDate( $month . '-01' ) ) ) {
			$month = '';
			$invalid = true;
		}
		return array(
			'month' => $month,
			'date_from' => self::isDate( $from ) ? $from : '',
			'date_to' => self::isDate( $to ) ? $to : '',
			'date_range_invalid' => $invalid,
		);
	}

	private static function isDate( $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value )
			&& checkdate( (int) substr( $value, 5, 2 ), (int) substr( $value, 8, 2 ), (int) substr( $value, 0, 4 ) );
	}

	/** Column identifiers must be fixed internal mappings, never request values. */
	public static function sql( $wpdb, string $column, array $filters ): string {
		$range = self::normalize( $filters );
		if ( $range['date_range_invalid'] ) {
			return ' AND 1 = 0';
		}
		$sql = '';
		if ( '' !== $range['date_from'] ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal column mapping.
			$sql .= $wpdb->prepare( " AND {$column} >= %s", $range['date_from'] . ' 00:00:00' );
		}
		if ( '' !== $range['date_to'] && '9999-12-31' !== $range['date_to'] ) {
			$next = ( new \DateTimeImmutable( $range['date_to'], new \DateTimeZone( 'UTC' ) ) )->modify( '+1 day' );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal column mapping.
			$sql .= $wpdb->prepare( " AND {$column} < %s", $next->format( 'Y-m-d' ) . ' 00:00:00' );
		}
		// The maximum SQL DATETIME day has no representable successor. All
		// non-null stored DATETIME values are already at or below that day.
		if ( '9999-12-31' === $range['date_to'] ) {
			$sql .= " AND {$column} IS NOT NULL";
		}
		return $sql;
	}
}
