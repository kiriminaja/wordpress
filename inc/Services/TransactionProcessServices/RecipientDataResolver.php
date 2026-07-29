<?php
namespace KiriminAjaOfficial\Services\TransactionProcessServices;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Resolves the recipient submitted to KiriminAja from the current WooCommerce order.
 *
 * Transaction shipping_info is a checkout snapshot and is only used for legacy
 * transactions whose WooCommerce order no longer contains a value.
 */
class RecipientDataResolver {
    public function resolve( $order, $shipping_info, $transaction ): array {
        $shipping = $this->get_order_address( $order, 'shipping' );
        $billing  = $this->get_order_address( $order, 'billing' );

        $shipping_snapshot = $this->get_snapshot_address( $shipping_info, 'shipping' );
        $billing_snapshot  = $this->get_snapshot_address( $shipping_info, 'billing' );

        $recipient = array(
            'first_name' => $this->first_value( $shipping['first_name'], $billing['first_name'], $shipping_snapshot['first_name'], $billing_snapshot['first_name'] ),
            'last_name'  => $this->first_value( $shipping['last_name'], $billing['last_name'], $shipping_snapshot['last_name'], $billing_snapshot['last_name'] ),
            'address_1'  => $this->first_value( $shipping['address_1'], $billing['address_1'], $shipping_snapshot['address_1'], $billing_snapshot['address_1'] ),
            'address_2'  => $this->first_value( $shipping['address_2'], $billing['address_2'], $shipping_snapshot['address_2'], $billing_snapshot['address_2'] ),
            'city'       => $this->first_value( $shipping['city'], $billing['city'], $shipping_snapshot['city'], $billing_snapshot['city'] ),
            'state'      => $this->first_value( $shipping['state'], $billing['state'], $shipping_snapshot['state'], $billing_snapshot['state'] ),
            'country'    => $this->first_value( $shipping['country'], $billing['country'], $shipping_snapshot['country'], $billing_snapshot['country'] ),
            'postcode'   => $this->first_value( $shipping['postcode'], $billing['postcode'], $shipping_snapshot['postcode'], $billing_snapshot['postcode'], $this->get_order_postcode_meta( $order ) ),
            'phone'      => $this->first_value( $shipping['phone'], $billing['phone'], $shipping_snapshot['phone'], $billing_snapshot['phone'] ),
        );

        if ( '' === $recipient['postcode'] ) {
            $recipient['postcode'] = $this->extract_postcode_from_destination( $transaction->destination_sub_district ?? '' );
        }

        return $recipient;
    }

    private function get_order_address( $order, string $type ): array {
        $address = array();
        if ( $order && method_exists( $order, 'get_address' ) ) {
            $address = (array) $order->get_address( $type );
        }

        $fields = array( 'first_name', 'last_name', 'address_1', 'address_2', 'city', 'state', 'country', 'postcode', 'phone' );
        $result = array();
        foreach ( $fields as $field ) {
            $value = trim( (string) ( $address[ $field ] ?? '' ) );
            if ( '' === $value && $order && method_exists( $order, 'get_' . $type . '_' . $field ) ) {
                $value = trim( (string) $order->{ 'get_' . $type . '_' . $field }() );
            }
            $result[ $field ] = $value;
        }

        return $result;
    }

    private function get_snapshot_address( $shipping_info, string $type ): array {
        $fallback_prefix = 'shipping' === $type ? 'billing' : '';
        $fields = array( 'first_name', 'last_name', 'address_1', 'address_2', 'city', 'state', 'country', 'postcode', 'phone' );
        $result = array();

        foreach ( $fields as $field ) {
            $keys = array( '_' . $type . '_' . $field, $type . '_' . $field );
            if ( '' !== $fallback_prefix ) {
                $keys[] = '_' . $fallback_prefix . '_' . $field;
                $keys[] = $fallback_prefix . '_' . $field;
            }
            if ( 'postcode' === $field ) {
                $keys[] = '_kiriof_checkout_postcode';
                $keys[] = 'kiriof_checkout_postcode';
            }
            if ( 'billing' === $type ) {
                $keys[] = $field;
            }
            $result[ $field ] = $this->read_snapshot_value( $shipping_info, $keys );
        }

        return $result;
    }

    private function get_order_postcode_meta( $order ): string {
        if ( ! $order || ! method_exists( $order, 'get_meta' ) ) {
            return '';
        }

        foreach ( array( '_shipping_postcode', 'shipping_postcode', '_billing_postcode', 'billing_postcode', '_kiriof_checkout_postcode', 'kiriof_checkout_postcode' ) as $key ) {
            $value = trim( (string) $order->get_meta( $key, true ) );
            if ( '' !== $value ) {
                return $value;
            }
        }

        return '';
    }

    private function read_snapshot_value( $shipping_info, array $keys ): string {
        foreach ( $keys as $key ) {
            if ( is_object( $shipping_info ) && isset( $shipping_info->$key ) ) {
                $value = trim( (string) $shipping_info->$key );
                if ( '' !== $value ) {
                    return $value;
                }
            }
        }

        return '';
    }

    private function first_value( ...$values ): string {
        foreach ( $values as $value ) {
            $value = trim( (string) $value );
            if ( '' !== $value ) {
                return $value;
            }
        }

        return '';
    }

    private function extract_postcode_from_destination( $destination ): string {
        if ( preg_match( '/(?:^|,\s*)(\d{5})\s*$/', (string) $destination, $matches ) ) {
            return $matches[1];
        }

        return '';
    }
}
