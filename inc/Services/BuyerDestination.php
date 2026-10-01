<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Shipping district snapshot with optional version 2 destination coordinates. */
final class BuyerDestination {
    public const META_KEY = '_kiriof_buyer_destination';
    public const COORDINATE_META_KEY = '_kiriof_buyer_destination_coordinates';

    public const ADDRESS_FIELDS = array( 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' );

    /** Version 1 deliberately retains its original six-field wire shape. */
    public static function normalize( $value ): array {
        if ( ! is_array( $value ) || array_diff( array( 'district_id', 'district_label', 'postcode', 'country', 'address_type', 'version' ), array_keys( $value ) ) ) {
            throw new \InvalidArgumentException( 'Invalid shipping destination.' );
        }
        $id = $value['district_id'];
        if ( ! ( is_string( $id ) || is_int( $id ) ) || ( '' !== $id && ! preg_match( '/^[1-9][0-9]*$/D', (string) $id ) ) ) {
            throw new \InvalidArgumentException( 'Invalid shipping destination.' );
        }
        foreach ( array( 'district_label', 'postcode', 'country' ) as $key ) {
            if ( ! is_string( $value[$key] ) || strlen( $value[$key] ) > 255 || preg_match( '/[<>\x00-\x1f\x7f]/', $value[$key] ) ) {
                throw new \InvalidArgumentException( 'Invalid shipping destination.' );
            }
        }
        $label = trim( $value['district_label'] );
        $postcode = self::postcode( $value['postcode'] );
        $country = strtoupper( trim( $value['country'] ) );
        $latitude = self::coordinate( $value['destination_latitude'] ?? '', 90 );
        $longitude = self::coordinate( $value['destination_longitude'] ?? '', 180 );
        if ( 'shipping' !== $value['address_type'] || ( 1 !== $value['version'] && 2 !== $value['version'] ) || ! preg_match( '/^[A-Z]{2}$/D', $country )
            || ( '' !== (string) $id && ( '' === $label || is_numeric( $label ) || '' === $postcode ) )
            || ( '' === (string) $id && '' !== $label ) ) {
            throw new \InvalidArgumentException( 'Invalid shipping destination.' );
        }
        if ( ( '' !== $latitude && '' === $longitude ) || ( '' === $latitude && '' !== $longitude ) ) {
            throw new \InvalidArgumentException( 'Invalid shipping destination.' );
        }
        if ( 1 === (int) $value['version'] && ( '' !== $latitude || '' !== $longitude ) ) {
            throw new \InvalidArgumentException( 'Invalid shipping destination.' );
        }
        if ( 2 === (int) $value['version'] && ( '' === $latitude || '' === $longitude ) ) {
            throw new \InvalidArgumentException( 'Invalid shipping destination.' );
        }
        $destination = array( 'district_id' => (string) $id, 'district_label' => $label, 'postcode' => $postcode, 'country' => $country, 'address_type' => 'shipping', 'version' => $value['version'] );
        if ( 2 === $value['version'] ) {
            $address = self::address( $value['shipping_address'] ?? null );
            if ( $postcode !== $address['postcode'] || $country !== $address['country'] ) {
                throw new \InvalidArgumentException( 'Invalid shipping destination.' );
            }
            $destination['destination_latitude'] = $latitude;
            $destination['destination_longitude'] = $longitude;
            $destination['shipping_address'] = $address;
        }
        return $destination;
    }

    /** A pin is bound to the complete shipping address, not just its postcode. */
    public static function address( $value ): array {
        if ( ! is_array( $value ) || array_diff( self::ADDRESS_FIELDS, array_keys( $value ) ) ) {
            throw new \InvalidArgumentException( 'Invalid shipping destination.' );
        }
        $address = array();
        foreach ( self::ADDRESS_FIELDS as $field ) {
            if ( ! is_string( $value[$field] ) || strlen( $value[$field] ) > 255 ) {
                throw new \InvalidArgumentException( 'Invalid shipping destination.' );
            }
            $address[$field] = sanitize_text_field( $value[$field] );
        }
        $address['postcode'] = self::postcode( $address['postcode'] );
        $address['country'] = strtoupper( trim( $address['country'] ) );
        return $address;
    }

    public static function postcode( string $postcode ): string {
        return strtoupper( preg_replace( '/\s+/', '', $postcode ) );
    }

    public static function coordinate( $value, float $limit ): string {
        if ( null === $value || '' === $value ) {
            return '';
        }
        if ( ! is_string( $value ) && ! is_int( $value ) && ! is_float( $value ) ) {
            throw new \InvalidArgumentException( 'Invalid shipping destination.' );
        }
        if ( ( is_string( $value ) && ! preg_match( '/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', $value ) ) || ! is_finite( (float) $value ) ) {
            throw new \InvalidArgumentException( 'Invalid shipping destination.' );
        }
        if ( abs( (float) $value ) > $limit ) {
            throw new \InvalidArgumentException( 'Invalid shipping destination.' );
        }
        $text = rtrim( rtrim( number_format( (float) $value, 7, '.', '' ), '0' ), '.' );
        return '-0' === $text ? '0' : $text;
    }

    public static function schema(): array {
        return array(
            'description' => 'Buyer shipping district snapshot with optional destination coordinates.',
            'type' => 'object',
            'context' => array( 'view', 'edit' ),
            'readonly' => false,
            'required' => array( 'district_id', 'district_label', 'postcode', 'country', 'address_type', 'version' ),
            'properties' => array(
                'district_id' => array( 'type' => array( 'string', 'integer' ), 'minimum' => 1, 'pattern' => '^([1-9][0-9]*)?$' ),
                'district_label' => array( 'type' => 'string', 'maxLength' => 255 ),
                'postcode' => array( 'type' => 'string', 'maxLength' => 255 ),
                'country' => array( 'type' => 'string', 'pattern' => '^[A-Za-z]{2}$' ),
                'address_type' => array( 'type' => 'string', 'enum' => array( 'shipping' ) ),
                'destination_latitude' => array( 'type' => array( 'string', 'number' ), 'minimum' => -90, 'maximum' => 90, 'pattern' => '^(-?(90(\\.0+)?|([0-9]|[1-8][0-9])(\\.[0-9]+)?))?$' ),
                'destination_longitude' => array( 'type' => array( 'string', 'number' ), 'minimum' => -180, 'maximum' => 180, 'pattern' => '^(-?(180(\\.0+)?|(1[0-7][0-9]|[1-9][0-9]|[0-9])(\\.[0-9]+)?))?$' ),
                'shipping_address' => array(
                    'type' => 'object',
                    'required' => self::ADDRESS_FIELDS,
                    'properties' => array_fill_keys( self::ADDRESS_FIELDS, array( 'type' => 'string', 'maxLength' => 255 ) ),
                ),
                'version' => array( 'type' => 'integer', 'enum' => array( 1, 2 ) ),
            ),
        );
    }
}
