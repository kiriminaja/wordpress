<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Durable account destinations; never reads addresses from the mutable checkout customer. */
final class CustomerShippingDestinationService {
    public const ADDRESS_META_KEY = '_kiriof_buyer_destination_address';
    private const SESSION_KEY = 'kiriof_buyer_destination';
    private const MISSING = '__kiriof_destination_missing__';

    public function register(): void {
        add_action( 'kiriof_buyer_destination_synced', array( $this, 'syncCheckout' ), 20, 2 );
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'classicOrderProcessed' ), 30, 3 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'orderProcessed' ), 30, 1 );
        add_action( 'woocommerce_init', array( $this, 'hydrateSession' ), 30, 0 );
    }

    /** Read only the authenticated user's persisted shipping profile. */
    public function address( $customer_or_id ): array {
        $user_id = $this->userId( $customer_or_id );
        if ( ! $this->owns( $user_id ) ) {
            throw new \InvalidArgumentException( 'Invalid shipping customer.' );
        }
        $address = array();
        foreach ( BuyerDestination::ADDRESS_FIELDS as $field ) {
            $value = get_user_meta( $user_id, 'shipping_' . $field, true );
            $address[$field] = $value;
        }
        return BuyerDestination::address( $address );
    }

    public function get( $customer_or_id ): ?array {
        $user_id = $this->userId( $customer_or_id );
        if ( ! $this->owns( $user_id ) ) {
            return null;
        }
        try {
            $address = $this->address( $user_id );
            $stored = get_user_meta( $user_id, BuyerDestination::META_KEY, true );
            if ( '' !== $stored && null !== $stored ) {
                $destination = BuyerDestination::normalize( $stored );
                if ( ! $this->matches( $destination, $address, false ) ) {
                    return null;
                }
                $binding = get_user_meta( $user_id, self::ADDRESS_META_KEY, true );
                if ( '' !== $binding && null !== $binding && ! $this->matches( $destination, BuyerDestination::address( $binding ), false ) ) {
                    return null;
                }
                if ( 2 === $destination['version'] && $destination['shipping_address'] !== $address ) {
                    $destination = $this->withoutPin( $destination );
                }
                return $destination;
            }
            // Legacy metadata has no pin binding and is useful only for an Indonesian postcode.
            if ( 'ID' !== $address['country'] || ! preg_match( '/^[0-9]{5}$/D', $address['postcode'] ) ) {
                return null;
            }
            $id = $this->legacyValue( $user_id, 'kiriof_destination_area' );
            $name = $this->legacyValue( $user_id, 'kiriof_destination_area_name' );
            if ( '' === $id ) {
                return null;
            }
            return BuyerDestination::normalize( array( 'district_id' => $id, 'district_label' => $name, 'postcode' => $address['postcode'], 'country' => 'ID', 'address_type' => 'shipping', 'version' => 1 ) );
        } catch ( \InvalidArgumentException $error ) {
            return null;
        }
    }

    /** Caller validates district identity with the API before saving. Does not edit user addresses. */
    public function save( $user_id, array $destination, array $address ): void {
        $user_id = $this->userId( $user_id );
        if ( ! $this->owns( $user_id ) ) {
            throw new \InvalidArgumentException( 'Invalid shipping customer.' );
        }
        $destination = BuyerDestination::normalize( $destination );
        $address = BuyerDestination::address( $address );
        if ( ! $this->matches( $destination, $address, true ) ) {
            throw new \InvalidArgumentException( 'Invalid shipping destination.' );
        }
        if ( '' === $destination['district_id'] ) {
            $destination = $this->withoutPin( $destination );
        }
        update_user_meta( $user_id, BuyerDestination::META_KEY, $destination );
        update_user_meta( $user_id, self::ADDRESS_META_KEY, $address );
        if ( 2 === $destination['version'] && '' !== $destination['district_id'] ) {
            update_user_meta( $user_id, BuyerDestination::COORDINATE_META_KEY, $this->coordinates( $destination ) );
        } else {
            delete_user_meta( $user_id, BuyerDestination::COORDINATE_META_KEY );
        }
        ( new CustomerDistrictService() )->save( $user_id, 'shipping', $destination['district_id'], $destination['district_label'] );
    }

    /** Checkout may persist a district only when it belongs to the saved account address. */
    public function syncCheckout( array $destination, ?array $checkout_address = null ): void {
        $user_id = get_current_user_id();
        if ( ! $this->owns( $user_id ) ) {
            return;
        }
        try {
            $profile = $this->address( $user_id );
            if ( null !== $checkout_address && BuyerDestination::address( $checkout_address ) !== $profile ) {
                return;
            }
            $this->save( $user_id, $destination, $profile );
        } catch ( \InvalidArgumentException $error ) {
            // A different checkout address must not mutate the account profile.
        }
    }

    /** Explicit session arrays (including clears and malformed arrays) always win. */
    public function forCheckout( $session ): ?array {
        $value = is_object( $session ) && is_callable( array( $session, 'get' ) ) ? $session->get( self::SESSION_KEY, self::MISSING ) : self::MISSING;
        if ( is_array( $value ) ) {
            try {
                return BuyerDestination::normalize( $value );
            } catch ( \InvalidArgumentException $error ) {
                return null;
            }
        }
        if ( self::MISSING !== $value && null !== $value ) {
            return null;
        }
        return $this->get( get_current_user_id() );
    }

    public function hydrateSession(): void {
        if ( ! function_exists( 'WC' ) || ! $this->owns( get_current_user_id() ) ) {
            return;
        }
        $wc = WC();
        if ( ! $wc || ! isset( $wc->customer, $wc->session ) || ! is_callable( array( $wc->customer, 'get_id' ) )
            || (int) $wc->customer->get_id() !== (int) get_current_user_id()
            || ! is_callable( array( $wc->session, 'get' ) ) || ! is_callable( array( $wc->session, 'set' ) )
            || self::MISSING !== $wc->session->get( self::SESSION_KEY, self::MISSING ) ) {
            return;
        }
        $destination = $this->get( get_current_user_id() );
        if ( null === $destination ) {
            return;
        }
        $session = $wc->session;
        $session->set( self::SESSION_KEY, $destination );
        $session->set( 'kiriof_buyer_destination_coordinates', 2 === $destination['version'] && '' !== $destination['district_id'] ? $this->coordinates( $destination ) : null );
        foreach ( array( 'destination_id', 'shipping_destination_id', 'kiriof_destination_area' ) as $key ) {
            $session->set( $key, $destination['district_id'] );
        }
        foreach ( array( 'destination_name', 'shipping_destination_name', 'kiriof_destination_area_name' ) as $key ) {
            $session->set( $key, $destination['district_label'] );
        }
        $session->set( 'kiriof_checkout_postcode', $destination['postcode'] );
        $session->set( 'kiriof_checkout_token', '' !== $destination['district_id'] ? '1' : '' );
        if ( '' !== $destination['district_id'] ) {
            $history = (array) $session->get( 'kiriof_destination_postcode_map', array() );
            $history[$destination['postcode']] = array( 'destination_id' => $destination['district_id'], 'destination_name' => $destination['district_label'] );
            $session->set( 'kiriof_destination_postcode_map', $history );
        }
    }

    public function classicOrderProcessed( $order_id, $posted_data, $order ): void {
        $this->orderProcessed( $order );
    }

    public function orderProcessed( $order ): void {
        if ( ! is_object( $order ) || ! is_callable( array( $order, 'get_user_id' ) ) || ! is_callable( array( $order, 'get_meta' ) )
            || ! $this->owns( (int) $order->get_user_id() ) ) {
            return;
        }
        $destination = $order->get_meta( BuyerDestination::META_KEY, true );
        if ( is_array( $destination ) ) {
            try {
                $destination = BuyerDestination::normalize( $destination );
                $address = array();
                foreach ( BuyerDestination::ADDRESS_FIELDS as $field ) {
                    $getter = 'get_shipping_' . $field;
                    if ( ! is_callable( array( $order, $getter ) ) ) {
                        return;
                    }
                    $address[$field] = $order->$getter();
                }
                if ( $this->matches( $destination, BuyerDestination::address( $address ), true ) ) {
                    $this->syncCheckout( $destination, $address );
                }
            } catch ( \InvalidArgumentException $error ) {
                // Invalid order snapshots cannot become durable account data.
            }
        }
    }

    private function userId( $customer_or_id ): int {
        if ( is_object( $customer_or_id ) ) {
            return is_callable( array( $customer_or_id, 'get_id' ) ) ? (int) $customer_or_id->get_id() : 0;
        }
        return is_int( $customer_or_id ) || ( is_string( $customer_or_id ) && ctype_digit( $customer_or_id ) ) ? (int) $customer_or_id : 0;
    }

    private function owns( int $user_id ): bool {
        return $user_id > 0 && (int) get_current_user_id() === $user_id;
    }

    private function matches( array $destination, array $address, bool $include_pin ): bool {
        return $destination['postcode'] === $address['postcode'] && $destination['country'] === $address['country']
            && ( ! $include_pin || 2 !== $destination['version'] || $destination['shipping_address'] === $address );
    }

    private function withoutPin( array $destination ): array {
        unset( $destination['destination_latitude'], $destination['destination_longitude'], $destination['shipping_address'] );
        $destination['version'] = 1;
        return $destination;
    }

    private function coordinates( array $destination ): array {
        return array( 'latitude' => $destination['destination_latitude'], 'longitude' => $destination['destination_longitude'] );
    }

    private function legacyValue( int $user_id, string $field ) {
        foreach ( array( 'shipping_' . $field, 'shipping_kiriminaja-official/' . $field, '_wc_shipping/kiriminaja-official/' . $field ) as $key ) {
            $value = get_user_meta( $user_id, $key, true );
            if ( '' !== $value && null !== $value ) {
                return $value;
            }
        }
        return '';
    }
}
