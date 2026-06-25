<?php
namespace KiriminAjaOfficial\Services;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooCommerceShippingMethodRegistrationService {
    private const METHOD_ID = 'kiriminaja-official';

    public function register(): bool {
        if ( ! class_exists( '\WC_Shipping_Zones' ) || ! class_exists( '\WC_Shipping_Zone' ) ) {
            return false;
        }

        try {
            $zone = $this->getIndonesiaZone();
            if ( ! $zone ) {
                $zone = $this->createIndonesiaZone();
            }

            if ( ! $zone ) {
                return false;
            }

            $existing_instance_id = $this->getMethodInstanceId( $zone, false );
            if ( $existing_instance_id > 0 ) {
                $this->enableMethodInstance( $existing_instance_id, (int) $zone->get_id() );
                return true;
            }

            $instance_id = (int) $zone->add_shipping_method( self::METHOD_ID );
            if ( $instance_id <= 0 ) {
                return false;
            }

            $this->enableMethodInstance( $instance_id, (int) $zone->get_id() );
            return true;
        } catch ( \Throwable $th ) {
            if ( function_exists( 'kiriof_log' ) ) {
                kiriof_log(
                    'warning',
                    'KiriminAja shipping method could not be registered automatically.',
                    array( 'error' => $th->getMessage() ),
                    'kiriminaja_settings'
                );
            }
            return false;
        }
    }

    public function hasEnabledMethod(): bool {
        if ( ! class_exists( '\WC_Shipping_Zones' ) ) {
            return false;
        }

        foreach ( \WC_Shipping_Zones::get_zones() as $zone_data ) {
            $zone_id = isset( $zone_data['zone_id'] ) ? absint( $zone_data['zone_id'] ) : 0;
            if ( $zone_id > 0 && $this->zoneHasEnabledMethod( new \WC_Shipping_Zone( $zone_id ) ) ) {
                return true;
            }
        }

        return false;
    }

    private function getIndonesiaZone(): ?\WC_Shipping_Zone {
        foreach ( \WC_Shipping_Zones::get_zones() as $zone_data ) {
            $zone_id = isset( $zone_data['zone_id'] ) ? absint( $zone_data['zone_id'] ) : 0;
            if ( $zone_id <= 0 ) {
                continue;
            }

            $zone = new \WC_Shipping_Zone( $zone_id );
            if ( $this->zoneCoversIndonesia( $zone ) ) {
                return $zone;
            }
        }

        return null;
    }

    private function createIndonesiaZone(): ?\WC_Shipping_Zone {
        $zone = new \WC_Shipping_Zone();
        $zone->set_zone_name( __( 'Indonesia', 'kiriminaja-official' ) );
        $zone->add_location( 'ID', 'country' );
        $zone->save();

        return $zone->get_id() > 0 ? $zone : null;
    }

    private function zoneCoversIndonesia( \WC_Shipping_Zone $zone ): bool {
        foreach ( $zone->get_zone_locations() as $location ) {
            $code = isset( $location->code ) ? (string) $location->code : '';
            if ( 'ID' === strtoupper( $code ) || 0 === strpos( strtoupper( $code ), 'ID:' ) ) {
                return true;
            }
        }

        return false;
    }

    private function zoneHasEnabledMethod( \WC_Shipping_Zone $zone ): bool {
        return $this->getMethodInstanceId( $zone, true ) > 0;
    }

    private function getMethodInstanceId( \WC_Shipping_Zone $zone, bool $enabled_only ): int {
        foreach ( $zone->get_shipping_methods( $enabled_only ) as $instance_id => $method ) {
            if ( isset( $method->id ) && self::METHOD_ID === $method->id ) {
                return absint( $instance_id );
            }
        }

        return 0;
    }

    private function enableMethodInstance( int $instance_id, int $zone_id ): void {
        $option_key = 'woocommerce_' . self::METHOD_ID . '_' . $instance_id . '_settings';
        $settings   = get_option( $option_key, array() );
        $settings   = is_array( $settings ) ? $settings : array();

        $settings['enabled'] = 'yes';
        if ( empty( $settings['title'] ) ) {
            $settings['title'] = __( 'KiriminAja', 'kiriminaja-official' );
        }

        update_option( $option_key, $settings );

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(
            $wpdb->prefix . 'woocommerce_shipping_zone_methods',
            array( 'is_enabled' => 1 ),
            array( 'instance_id' => $instance_id ),
            array( '%d' ),
            array( '%d' )
        );

        if ( false !== $updated ) {
            do_action( 'woocommerce_shipping_zone_method_status_toggled', $instance_id, self::METHOD_ID, $zone_id, 1 );
            if ( class_exists( '\WC_Cache_Helper' ) ) {
                \WC_Cache_Helper::get_transient_version( 'shipping', true );
            }
        }
    }
}
