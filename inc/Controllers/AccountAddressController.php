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

        $address_2_key = $address_type . '_address_2';
        if ( isset( $fields[ $address_2_key ]['value'] ) && '' !== $district['id'] && (string) $fields[ $address_2_key ]['value'] === $district['id'] ) {
            $fields[ $address_2_key ]['value'] = '';
        }

        $fields = $this->hideBlockMirrorDistrictFields( $fields, $address_type, $district );

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
        $this->clearPollutedAddress2Post( $address_type, $district_id );
        if ( $district_id < 1 || '' === $district_name ) {
            wc_add_notice( __( 'Please select a District.', 'kiriminaja-official' ), 'error' );
        }
    }

    public function saveDistrict( int $user_id, string $address_type ): void {
        if ( ! in_array( $address_type, array( 'billing', 'shipping' ), true ) ) {
            return;
        }

        $district_id = $this->postedDistrictId( $address_type );
        $district_name = $this->postedDistrictName( $address_type );
        if ( $district_id > 0 && '' === $district_name ) {
            $saved_district = ( new CustomerDistrictService() )->get( $user_id, $address_type );
            $district_name = $saved_district['name'];
        }
        ( new CustomerDistrictService() )->save(
            $user_id,
            $address_type,
            $district_id,
            $district_name
        );
        $this->syncCheckoutSession( $address_type, $district_id, $district_name );
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

    private function clearPollutedAddress2Post( string $address_type, int $district_id ): void {
        if ( $district_id < 1 ) {
            return;
        }

        $key = $address_type . '_address_2';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the account address nonce before this hook.
        $address_2 = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
        if ( (string) $district_id === $address_2 ) {
            $_POST[ $key ] = '';
        }
    }

    private function hideBlockMirrorDistrictFields( array $fields, string $address_type, array $district ): array {
        $prefix = '_wc_' . $address_type . '/kiriminaja-official/kiriof_destination_area';

        foreach ( $fields as $key => $field ) {
            if ( ! is_string( $key ) || 0 !== strpos( $key, $prefix ) ) {
                continue;
            }

            $fields[ $key ]['type'] = 'hidden';
            $fields[ $key ]['required'] = false;
            $fields[ $key ]['label'] = '';
            $fields[ $key ]['class'] = array( 'kiriof-hidden-district-mirror' );
            $fields[ $key ]['value'] = false !== strpos( $key, '_name' ) ? $district['name'] : $district['id'];
        }

        return $fields;
    }

    private function syncCheckoutSession( string $address_type, int $district_id, string $district_name ): void {
        if ( ! function_exists( 'WC' ) || ! WC()->session ) {
            return;
        }

        $id_key = 'shipping' === $address_type ? 'shipping_destination_id' : 'destination_id';
        $name_key = 'shipping' === $address_type ? 'shipping_destination_name' : 'destination_name';
        WC()->session->set( $id_key, $district_id > 0 ? $district_id : '' );
        WC()->session->set( $name_key, $district_name );

        $postcode_key = $address_type . '_postcode';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the account address nonce before this hook.
        $postcode = isset( $_POST[ $postcode_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $postcode_key ] ) ) : '';
        $postcode = trim( preg_replace( '/\s+/', '', $postcode ) );
        if ( '' === $postcode ) {
            return;
        }

        $saved_map = (array) WC()->session->get( 'kiriof_destination_postcode_map', array() );
        if ( $district_id > 0 ) {
            $saved_map[ $postcode ] = array(
                'destination_id'   => (string) $district_id,
                'destination_name' => $district_name,
            );
        } else {
            unset( $saved_map[ $postcode ] );
        }
        WC()->session->set( 'kiriof_destination_postcode_map', $saved_map );
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
