<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Buyer-safe Cart status from current native rates; no API call or stale-rate revival. */
final class InstantCheckoutStatusService {
    public function data(): array {
        $empty = array( 'eligible' => false, 'code' => 'inactive', 'message' => '', 'expires_at' => 0 );
        $wc = function_exists( 'WC' ) ? WC() : null;
        if ( ! $wc || ! isset( $wc->session ) || ! $wc->session || ! is_callable( array( $wc, 'shipping' ) ) ) { return $empty; }
        $packages = $wc->shipping()->get_packages();
        if ( ! is_array( $packages ) || ! $packages ) { return $empty; }
        if ( class_exists( '\WC_Shipping_Zones' ) ) {
            $configured = false;
            foreach ( $packages as $package ) {
                $zone = \WC_Shipping_Zones::get_zone_matching_package( $package );
                foreach ( $zone->get_shipping_methods( true ) as $method ) {
                    if ( 'kiriminaja-instant' === $method->id && 'yes' === $method->enabled ) { $configured = true; }
                }
            }
            if ( ! $configured ) { return $empty; }
        }
        if ( isset( $wc->cart ) && is_callable( array( $wc->cart, 'get_shipping_packages' ) ) && count( $wc->cart->get_shipping_packages() ) > 1 ) {
            return array( 'eligible' => false, 'code' => 'packages_invalid', 'message' => __( 'Instant delivery is available only for a single shipping package. Please choose another shipping method.', 'kiriminaja-official' ), 'expires_at' => 0 );
        }
        if ( function_exists( 'get_woocommerce_currency' ) && 'IDR' !== get_woocommerce_currency() ) {
            return array( 'eligible' => false, 'code' => 'currency_unsupported', 'message' => __( 'Instant delivery supports IDR checkout currency only. Please choose another shipping method.', 'kiriminaja-official' ), 'expires_at' => 0 );
        }
        $expires = array();
        foreach ( $packages as $package ) {
            foreach ( $package['rates'] ?? array() as $rate ) {
                if ( ! is_object( $rate ) || ! is_callable( array( $rate, 'get_method_id' ) ) || 'kiriminaja-instant' !== $rate->get_method_id() ) { continue; }
                $meta = $rate->get_meta_data();
                $expiry = $meta['kiriof_instant_quote_expires'] ?? 0;
                if ( is_numeric( $expiry ) && (int) $expiry > time() ) { $expires[] = (int) $expiry; }
            }
        }
        if ( $expires ) { return array( 'eligible' => true, 'code' => 'available', 'message' => '', 'expires_at' => min( $expires ) ); }
        $rows = $wc->session->get( 'kiriof_instant_checkout_status', array() );
        $current_keys = array();
        foreach ( $packages as $package ) { $current_keys[] = hash( 'sha256', wp_json_encode( array_keys( $package['contents'] ?? array() ) ) ); }
        $messages = array(
            'packages_invalid' => __( 'Instant delivery is available only for a single shipping package. Please choose another shipping method.', 'kiriminaja-official' ),
            'currency_unsupported' => __( 'Instant delivery supports IDR checkout currency only. Please choose another shipping method.', 'kiriminaja-official' ),
            'outside_instant_radius' => __( 'Instant delivery is available only within 40 km of the pickup origin. You can use Express delivery for this address.', 'kiriminaja-official' ),
            'cod_not_supported' => __( 'Instant delivery does not support COD.', 'kiriminaja-official' ),
            'cod_unsupported' => __( 'Instant delivery does not support COD.', 'kiriminaja-official' ),
            'destination_required' => __( 'Choose a shipping address and map pin for Instant delivery.', 'kiriminaja-official' ),
            'destination_invalid' => __( 'Choose a shipping address and map pin for Instant delivery.', 'kiriminaja-official' ),
            'recipient_invalid' => __( 'Complete the shipping recipient name and phone number for Instant delivery.', 'kiriminaja-official' ),
            'items_invalid' => __( 'The items in this cart are not eligible for Instant delivery.', 'kiriminaja-official' ),
            'quote_failed' => __( 'Instant delivery rates are temporarily unavailable. Please try again.', 'kiriminaja-official' ),
            'quote_failed_or_unavailable' => __( 'Instant delivery rates are temporarily unavailable. Please try again.', 'kiriminaja-official' ),
            'quote_empty_or_unavailable' => __( 'Instant delivery is unavailable for this shipment.', 'kiriminaja-official' ),
            'available' => __( 'Instant prices have expired. Refresh your shipping quote before placing the order.', 'kiriminaja-official' ),
        );
        $latest = null;
        foreach ( is_array( $rows ) ? $rows : array() as $key => $row ) {
            $parts = explode( ':', (string) $key, 2 );
            if ( ! is_array( $row ) || ! in_array( $parts[1] ?? '', $current_keys, true ) || time() - (int) ( $row['updated'] ?? 0 ) > 180 ) { continue; }
            if ( ! isset( $messages[$row['code'] ?? ''] ) ) { continue; }
            if ( null === $latest || (int) $row['updated'] > (int) $latest['updated'] ) { $latest = $row; }
        }
        if ( null === $latest ) { return $empty; }
        return array( 'eligible' => false, 'code' => $latest['code'], 'message' => $messages[$latest['code']], 'expires_at' => 0 );
    }
}
