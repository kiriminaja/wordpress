<?php

namespace KiriminAjaOfficial\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Keeps the Shipment Locations navigation entry and points it at the
 * authoritative manager in WooCommerce > Settings > General > Store Address.
 */
class ShipmentLocationController {
    const PAGE_SLUG = 'kiriminaja-shipment-locations';

    public function register() {
        add_filter( 'woocommerce_get_sections_shipping', array( $this, 'addShippingSection' ) );
        add_filter( 'woocommerce_get_settings_shipping', array( $this, 'getShippingSettings' ), 10, 2 );
        add_action( 'woocommerce_settings_shipping', array( $this, 'renderShippingSection' ) );
    }

    /**
     * Add shipment locations beside WooCommerce's native shipping settings.
     *
     * @param array $sections Shipping settings sections.
     * @return array
     */
    public function addShippingSection( $sections ) {
        $sections['kiriminaja_shipment_locations'] = __( 'Shipment Locations', 'kiriminaja-official' );

        return $sections;
    }

    /**
     * Do not add fields or a native Save changes button to this pointer section.
     *
     * @param array  $settings Shipping settings.
     * @param string $section  Active shipping settings section.
     * @return array
     */
    public function getShippingSettings( $settings, $section ) {
        if ( 'kiriminaja_shipment_locations' !== $section ) {
            return $settings;
        }

        return array();
    }

    /**
     * Render a pointer notice. Locations are edited in General > Store Address.
     *
     * @return void
     */
    public function renderShippingSection() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
        $section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
        if ( 'kiriminaja_shipment_locations' !== $section ) {
            return;
        }

        require KIRIOF_DIR . 'templates/shipment-location/index.php';
    }

    /**
     * Legacy KiriminAja menu callback. Keep saved bookmarks working while the
     * authoritative page lives in WooCommerce's General settings.
     *
     * @return void
     */
    public function redirectLegacyPage() {
        wp_safe_redirect( $this->getSettingsUrl() );
        exit;
    }

    /**
     * Get the WooCommerce General settings URL that hosts the manager.
     *
     * @return string
     */
    public function getSettingsUrl() {
        return admin_url( 'admin.php?page=wc-settings&tab=general#kiriof-shipment-locations' );
    }
}
