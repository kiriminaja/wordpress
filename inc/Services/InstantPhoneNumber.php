<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/PhoneNumberNormalizer.php';

/** Instant checkout phone normalization and validation compatibility facade. */
final class InstantPhoneNumber {
    public static function normalize( $value ): string {
        return PhoneNumberNormalizer::normalize( $value );
    }
}
