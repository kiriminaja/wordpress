<?php
namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Services\CustomerDistrictService;

class AccountAddressController {
    public function register(): void {
        add_filter( 'woocommerce_address_to_edit', array( $this, 'addDistrictFields' ), 20, 2 );
        add_action( 'woocommerce_after_save_address_validation', array( $this, 'validateDistrict' ), 10, 4 );
        add_action( 'woocommerce_customer_save_address', array( $this, 'saveDistrict' ), 10, 2 );
    }

    public function addDistrictFields( array $fields, string $address_type ): array {
        if ( ! $this->isEditAddressRequest( $address_type ) ) {
            return $fields;
        }

        $fields = $this->removeBlocksDistrictFields( $fields, $address_type );
        $district = ( new CustomerDistrictService() )->get( get_current_user_id(), $address_type );
        $field_key = $address_type . '_kiriof_destination_area';
        $name_key = $address_type . '_kiriof_destination_area_name';
        $options = array( '' => __( 'Select Option', 'kiriminaja-official' ) );
        if ( '' !== $district['id'] && '' !== $district['name'] ) {
            $options[ $district['id'] ] = $district['name'];
        }

        $field = array(
            'label'    => __( 'District', 'kiriminaja-official' ),
            'required' => true,
            'class'    => array( 'form-row-wide' ),
            'type'     => 'select',
            'options'  => $options,
            'value'    => $district['id'],
            'priority' => 61,
        );
        $name_field = array(
            'type'     => 'hidden',
            'value'    => $district['name'],
            'priority' => 62,
        );

        $fields = $this->insertAfterPostcode( $fields, $field_key, $field );
        $fields = $this->insertAfterKey( $fields, $field_key, $name_key, $name_field );

        return $fields;
    }

    private function removeBlocksDistrictFields( array $fields, string $address_type ): array {
        $canonical_key = $address_type . '_kiriof_destination_area';
        foreach ( array_keys( $fields ) as $key ) {
            if ( $key !== $canonical_key && false !== strpos( (string) $key, 'kiriof_destination_area' ) ) {
                unset( $fields[ $key ] );
            }
        }

        return $fields;
    }

    public function validateDistrict( int $user_id, string $address_type, array $address, $customer ): void {
        unset( $user_id, $address, $customer );
        if ( ! in_array( $address_type, array( 'billing', 'shipping' ), true ) ) {
            return;
        }

        $district_id = $this->postedDistrictId( $address_type );
        $district_name = $this->postedDistrictName( $address_type );
        if ( $district_id < 1 || '' === $district_name ) {
            wc_add_notice( __( 'Please select a District.', 'kiriminaja-official' ), 'error' );
        }
    }

    public function saveDistrict( int $user_id, string $address_type ): void {
        if ( ! in_array( $address_type, array( 'billing', 'shipping' ), true ) ) {
            return;
        }

        ( new CustomerDistrictService() )->save(
            $user_id,
            $address_type,
            $this->postedDistrictId( $address_type ),
            $this->postedDistrictName( $address_type )
        );
    }

    private function isEditAddressRequest( string $address_type = '' ): bool {
        if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'edit-address' ) ) {
            return false;
        }
        if ( '' === $address_type ) {
            return true;
        }

        $requested_type = sanitize_key( (string) get_query_var( 'edit-address' ) );
        return '' === $requested_type || $requested_type === $address_type;
    }

    private function postedDistrictId( string $address_type ): int {
        $key = $address_type . '_kiriof_destination_area';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the account address nonce before this hook.
        return isset( $_POST[ $key ] ) ? absint( wp_unslash( $_POST[ $key ] ) ) : 0;
    }

    private function postedDistrictName( string $address_type ): string {
        $key = $address_type . '_kiriof_destination_area_name';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the account address nonce before this hook.
        return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
    }

    private function insertAfterPostcode( array $fields, string $field_key, array $field ): array {
        $postcode_key = 0 === strpos( $field_key, 'shipping_' ) ? 'shipping_postcode' : 'billing_postcode';
        return $this->insertAfterKey( $fields, $postcode_key, $field_key, $field );
    }

    private function insertAfterKey( array $fields, string $after_key, string $field_key, array $field ): array {
        $result = array();
        foreach ( $fields as $key => $value ) {
            $result[ $key ] = $value;
            if ( $after_key === $key ) {
                $result[ $field_key ] = $field;
            }
        }
        if ( ! isset( $result[ $field_key ] ) ) {
            $result[ $field_key ] = $field;
        }
        return $result;
    }
}
