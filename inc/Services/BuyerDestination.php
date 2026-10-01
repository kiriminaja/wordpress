<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Shipping district snapshot only; coordinates are a separate, future contract. */
final class BuyerDestination {
    public const META_KEY = '_kiriof_buyer_destination';

    /** @return array{district_id:string,district_label:string,postcode:string,country:string,address_type:string,version:int} */
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
        if ( 'shipping' !== $value['address_type'] || 1 !== $value['version'] || ! preg_match( '/^[A-Z]{2}$/D', $country )
            || ( '' !== (string) $id && ( '' === $label || is_numeric( $label ) || '' === $postcode ) )
            || ( '' === (string) $id && '' !== $label ) ) {
            throw new \InvalidArgumentException( 'Invalid shipping destination.' );
        }
        return array( 'district_id' => (string) $id, 'district_label' => $label, 'postcode' => $postcode, 'country' => $country, 'address_type' => 'shipping', 'version' => 1 );
    }

    public static function postcode( string $postcode ): string {
        return strtoupper( preg_replace( '/\s+/', '', $postcode ) );
    }

    public static function schema(): array {
        return array(
            'description' => 'Buyer shipping district snapshot (no coordinates).',
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
                'version' => array( 'type' => 'integer', 'enum' => array( 1 ) ),
            ),
        );
    }
}
