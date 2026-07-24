<?php
namespace KiriminAjaOfficial\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomerDistrictService {
    private const FIELD_ID = 'kiriof_destination_area';
    private const FIELD_NAME = 'kiriof_destination_area_name';

    /**
     * Read a customer's saved District, including legacy Blocks metadata.
     *
     * @param \WC_Customer|int $customer Customer object or user ID.
     * @param string           $address_type Billing or shipping.
     * @return array{id:string,name:string}
     */
    public function get( $customer, string $address_type ): array {
        $address_type = $this->normalizeAddressType( $address_type );
        $id = $this->readMeta( $customer, $address_type . '_' . self::FIELD_ID );
        $name = $this->readMeta( $customer, $address_type . '_' . self::FIELD_NAME );

        if ( '' === $id ) {
            $id = $this->readMeta( $customer, $address_type . '_kiriminaja-official/' . self::FIELD_ID );
        }
        if ( '' === $name ) {
            $name = $this->readMeta( $customer, $address_type . '_kiriminaja-official/' . self::FIELD_NAME );
        }

        return array(
            'id'   => sanitize_text_field( $id ),
            'name' => sanitize_text_field( $name ),
        );
    }

    /**
     * Persist canonical and Blocks-compatible District metadata.
     *
     * @param \WC_Customer|int $customer Customer object or user ID.
     * @param string           $address_type Billing or shipping.
     * @param string|int       $district_id KiriminAja District ID.
     * @param string           $district_name District display label.
     */
    public function save( $customer, string $address_type, $district_id, string $district_name ): void {
        $address_type = $this->normalizeAddressType( $address_type );
        $district_id = absint( $district_id );
        $district_name = sanitize_text_field( $district_name );
        $values = array(
            $address_type . '_' . self::FIELD_ID => $district_id > 0 ? (string) $district_id : '',
            $address_type . '_' . self::FIELD_NAME => $district_name,
            $address_type . '_kiriminaja-official/' . self::FIELD_ID => $district_id > 0 ? (string) $district_id : '',
            $address_type . '_kiriminaja-official/' . self::FIELD_NAME => $district_name,
        );

        if ( $customer instanceof \WC_Customer ) {
            foreach ( $values as $key => $value ) {
                $customer->update_meta_data( $key, $value );
            }
            $customer->save_meta_data();
            return;
        }

        $user_id = absint( $customer );
        if ( $user_id < 1 ) {
            return;
        }
        foreach ( $values as $key => $value ) {
            update_user_meta( $user_id, $key, $value );
        }
    }

    private function readMeta( $customer, string $key ): string {
        if ( $customer instanceof \WC_Customer ) {
            return (string) $customer->get_meta( $key, true );
        }

        $user_id = absint( $customer );
        return $user_id > 0 ? (string) get_user_meta( $user_id, $key, true ) : '';
    }

    private function normalizeAddressType( string $address_type ): string {
        return 'shipping' === $address_type ? 'shipping' : 'billing';
    }
}
