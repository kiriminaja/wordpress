<?php

namespace KiriminAjaOfficial\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Repositories\ShipmentLocationRepository;

/**
 * Handles secure shipment-location administration actions.
 */
class ShipmentLocationController {
    const PAGE_SLUG = 'kiriminaja-shipment-locations';

    public function register() {
        add_action( 'admin_post_kiriof_save_shipment_location', array( $this, 'save' ) );
        add_action( 'admin_post_kiriof_shipment_location_action', array( $this, 'action' ) );
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
     * Do not add fields or a native Save changes button to this CRUD section.
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
     * Render the management UI in WooCommerce > Settings > Shipping.
     *
     * WooCommerce's settings filter must return an array. Rendering from the
     * corresponding action keeps its settings API contract intact.
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
     * authoritative page lives in WooCommerce's Shipping settings.
     *
     * @return void
     */
    public function redirectLegacyPage() {
        wp_safe_redirect( $this->getSettingsUrl() );
        exit;
    }

    public function save() {
        $this->verifyRequest();

        $id   = isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0;
        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        if ( '' === $name ) {
            $this->redirect( array( 'error' => 'missing-name', 'edit' => $id ) );
        }

        $data = array(
            'name'            => $name,
            'phone'           => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
            'address'         => isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '',
            'sub_district_id' => isset( $_POST['sub_district_id'] ) ? absint( $_POST['sub_district_id'] ) : 0,
            'zip_code'        => isset( $_POST['zip_code'] ) ? sanitize_text_field( wp_unslash( $_POST['zip_code'] ) ) : '',
            'latitude'        => isset( $_POST['latitude'] ) ? sanitize_text_field( wp_unslash( $_POST['latitude'] ) ) : '',
            'longitude'       => isset( $_POST['longitude'] ) ? sanitize_text_field( wp_unslash( $_POST['longitude'] ) ) : '',
            'is_active'       => isset( $_POST['is_active'] ) ? 1 : 0,
        );

        $repository = new ShipmentLocationRepository();
        if ( $id > 0 ) {
            $saved = $repository->update( $id, $data );
        } else {
            $data['is_default'] = 0 === $repository->count() ? 1 : 0;
            $saved              = $repository->insert( $data );
        }

        $this->redirect( array( $saved ? 'updated' : 'error' => $saved ? '1' : 'save-failed' ) );
    }

    public function action() {
        $this->verifyRequest();

        $id     = isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0;
        $action = isset( $_POST['location_action'] ) ? sanitize_key( wp_unslash( $_POST['location_action'] ) ) : '';
        $repo   = new ShipmentLocationRepository();
        $row    = $repo->getById( $id );

        if ( ! $row ) {
            $this->redirect( array( 'error' => 'not-found' ) );
        }

        if ( 'default' === $action ) {
            $repo->update( $id, array( 'is_active' => 1 ) );
            $saved = $repo->setDefault( $id );
        } elseif ( 'toggle' === $action ) {
            if ( (int) $row->is_default === 1 && (int) $row->is_active === 1 ) {
                $this->redirect( array( 'error' => 'default-active' ) );
            }
            $saved = $repo->update( $id, array( 'is_active' => (int) ! $row->is_active ) );
        } elseif ( 'delete' === $action ) {
            if ( (int) $row->is_default === 1 ) {
                $this->redirect( array( 'error' => 'default-delete' ) );
            }
            $saved = $repo->delete( $id );
        } else {
            $this->redirect( array( 'error' => 'invalid-action' ) );
        }

        $repo->ensureDefaultExists();
        $this->redirect( array( $saved ? 'updated' : 'error' => $saved ? '1' : 'action-failed' ) );
    }

    private function verifyRequest() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage shipment locations.', 'kiriminaja-official' ) );
        }

        check_admin_referer( 'kiriof_manage_shipment_location' );
    }

    private function redirect( array $args ) {
        wp_safe_redirect( add_query_arg( $args, $this->getSettingsUrl() ) );
        exit;
    }

    /**
     * Get the native WooCommerce Shipping settings URL for this screen.
     *
     * @return string
     */
    public function getSettingsUrl() {
        return admin_url( 'admin.php?page=wc-settings&tab=shipping&section=kiriminaja_shipment_locations' );
    }
}
