<?php
namespace KiriminAjaOfficial\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves a transaction's origin from its snapshot and canonical shipment
 * location. Partial legacy snapshots are overlaid on the active location so
 * the workspace always has a name, full address, and current location ID.
 */
class TransactionOriginResolver {
	private ShipmentLocationService $location_service;

	public function __construct( ?ShipmentLocationService $location_service = null ) {
		$this->location_service = $location_service ?? new ShipmentLocationService();
	}

	/**
	 * @param object $transaction Transaction database row.
	 * @return array{name:string,phone:string,address:string,addressLines:array<int,string>,locationId:int}
	 */
	public function resolve( object $transaction ): array {
		$snapshot = json_decode( (string) ( $transaction->shipment_location_snapshot ?? '{}' ), true );
		$snapshot = is_array( $snapshot ) ? $snapshot : array();

		$location_id = (int) ( $snapshot['location_id'] ?? $snapshot['id'] ?? $transaction->shipment_location_id ?? 0 );
		$location    = $this->location_service->getLocationOrDefault( $location_id );
		$source      = $this->location_service->locationToOrigin( $location );
		$source      = is_array( $source ) ? $source : array();
		$source      = $this->mergeSnapshot( $source, $snapshot );

		$resolved_location_id = (int) ( $source['location_id'] ?? $source['id'] ?? ( $location->id ?? 0 ) );
		$name                 = trim( (string) ( $source['origin_name'] ?? $source['location_name'] ?? $source['name'] ?? '' ) );
		$address              = $this->location_service->formatAddress( $source );
		$address_lines        = array_values( array_filter( array_map( 'trim', explode( ' · ', $address ) ) ) );

		return array(
			'name'       => '' !== $name ? $name : __( 'Default origin', 'kiriminaja-official' ),
			'phone'      => trim( (string) ( $source['origin_phone'] ?? $source['phone'] ?? '' ) ),
			'address'      => $address,
			'addressLines' => $address_lines,
			'locationId'   => $resolved_location_id,
		);
	}

	/**
	 * @param array<string,mixed> $source Canonical location origin data.
	 * @param array<string,mixed> $snapshot Stored transaction snapshot.
	 * @return array<string,mixed>
	 */
	private function mergeSnapshot( array $source, array $snapshot ): array {
		$aliases = array(
			'location_id'          => array( 'location_id', 'id' ),
			'origin_name'          => array( 'origin_name', 'location_name', 'name' ),
			'origin_phone'         => array( 'origin_phone', 'phone' ),
			'origin_address'       => array( 'origin_address', 'address', 'address_1' ),
			'origin_address_2'     => array( 'origin_address_2', 'address_2' ),
			'origin_sub_district'  => array( 'origin_sub_district', 'sub_district_name', 'district' ),
			'origin_city'          => array( 'origin_city', 'city', 'city_name' ),
			'origin_state'         => array( 'origin_state', 'state', 'province', 'province_name' ),
			'origin_country'       => array( 'origin_country', 'country' ),
			'origin_zip_code'      => array( 'origin_zip_code', 'zip_code', 'postcode' ),
		);

		foreach ( $aliases as $target => $keys ) {
			foreach ( $keys as $key ) {
				$value = $snapshot[ $key ] ?? null;
				if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
					$source[ $target ] = $value;
					break;
				}
			}
		}

		return $source;
	}
}
