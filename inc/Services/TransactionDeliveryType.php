<?php

namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Shared classification for persisted transactions and legacy rows. */
final class TransactionDeliveryType {
    /** Unknown or malformed request values belong to the Express partition. */
    public static function normalize( $value ): string {
        return 'instant' === $value ? 'instant' : 'express';
    }

    /** Accept only the vehicle codes supported by the Instant API. */
    public static function normalizeVehicle( $value ): ?string {
        return in_array( $value, array( 'motor', 'mobil' ), true ) ? $value : null;
    }

    /**
     * Resolve an array or object transaction, never a display/service-name guess.
     * Documented Instant courier codes override the legacy Express column default.
     *
     * @param array|object|null $row Transaction with delivery_type and service fields.
     */
    public static function resolve( $row ): string {
        if ( is_object( $row ) ) {
            $row = get_object_vars( $row );
        }
        if ( ! is_array( $row ) ) {
            return 'express';
        }
        $service = $row['service'] ?? null;
        if ( is_string( $service ) && in_array( strtolower( trim( $service ) ), array( 'gosend', 'grab_express', 'borzo' ), true ) ) {
            return 'instant';
        }
        return self::normalize( $row['delivery_type'] ?? null );
    }
}
