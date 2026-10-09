<?php
namespace KiriminAjaOfficial\Controllers;

use KiriminAjaOfficial\Base\Enqueue;
use KiriminAjaOfficial\Services\BuyerDestination;
use KiriminAjaOfficial\Services\CheckoutServiceFactory;
use KiriminAjaOfficial\Services\CustomerShippingDestinationService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Extend native My Account shipping forms; WooCommerce retains address-save ownership. */
final class AccountShippingDestinationController {
    private CustomerShippingDestinationService $destinations;
    private $lookup;
    private array $validated = array();

    public function __construct( ?CustomerShippingDestinationService $destinations = null, ?CheckoutServiceFactory $lookup = null ) {
        $this->destinations = $destinations ?? new CustomerShippingDestinationService();
        $this->lookup = $lookup;
    }

    /** Native Woo fields and names stay intact; only the shipping editor layout changes. */
    public function shippingFields( array $fields, string $type ): array {
        if ( 'shipping' !== $type || get_current_user_id() < 1 || ! is_account_page() || ! is_wc_endpoint_url( 'edit-address' ) || 'shipping' !== get_query_var( 'edit-address' ) ) {
            return $fields;
        }
        // Some Woo versions/themes omit shipping phone from account address fields.
        if ( ! isset( $fields['shipping_phone'] ) ) {
            $fields['shipping_phone'] = array(
                'type' => 'tel', 'label' => __( 'Phone', 'kiriminaja-official' ),
                'required' => true, 'validate' => array( 'phone' ),
                'autocomplete' => 'tel', 'value' => get_user_meta( get_current_user_id(), 'shipping_phone', true ),
            );
        }
        $pairs = array( 'shipping_city' => 'first', 'shipping_state' => 'last', 'shipping_postcode' => 'first', 'shipping_phone' => 'last' );
        foreach ( $pairs as $key => $position ) {
            if ( ! isset( $fields[$key] ) ) {
                continue;
            }
            $classes = array_diff( (array) ( $fields[$key]['class'] ?? array() ), array( 'form-row-wide', 'form-row-first', 'form-row-last' ) );
            $classes[] = 'form-row-' . $position;
            $classes[] = 'kiriof-account-pair-' . $position;
            $fields[$key]['class'] = array_values( $classes );
            $fields[$key]['priority'] = 70 + 10 * array_search( $key, array_keys( $pairs ), true );
        }
        // Keep each requested pair adjacent regardless of a country's default order.
        $ordered = array();
        $paired_keys = array_keys( $pairs );
        foreach ( $fields as $key => $field ) {
            if ( in_array( $key, $paired_keys, true ) ) {
                continue;
            }
            $ordered[$key] = $field;
        }
        foreach ( $paired_keys as $key ) {
            if ( isset( $fields[$key] ) ) {
                $ordered[$key] = $fields[$key];
            }
        }
        return $ordered;
    }

    public function register(): void {
        add_filter( 'woocommerce_address_to_edit', array( $this, 'shippingFields' ), 30, 2 );
        add_action( 'woocommerce_my_account_after_my_address', array( $this, 'badges' ), 20, 1 );
        add_action( 'woocommerce_after_edit_address_form_shipping', array( $this, 'form' ), 20, 0 );
        add_action( 'woocommerce_after_save_address_validation', array( $this, 'validate' ), 20, 4 );
        add_action( 'woocommerce_customer_save_address', array( $this, 'saved' ), 20, 4 );
        add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 30, 0 );
    }

    public function badges( $type ): void {
        if ( 'shipping' !== $type || get_current_user_id() < 1 ) {
            return;
        }
        $snapshot = $this->destinations->get( get_current_user_id() );
        $district = null !== $snapshot && '' !== $snapshot['district_id'];
        $pin = $district && 2 === $snapshot['version'];
        echo '<div class="kiriof-address-status" role="status" aria-live="polite" aria-atomic="true">';
        if ( ! $district ) {
            $this->badge( __( 'Subdistrict Not Set', 'kiriminaja-official' ), false );
        }
        $this->badge( $pin ? __( 'Pin Location', 'kiriminaja-official' ) : __( 'Need Pin Location', 'kiriminaja-official' ), $pin );
        echo '</div>';
    }

    private function badge( string $text, bool $complete ): void {
        echo '<span class="kiriof-address-status__badge ' . ( $complete ? 'is-complete' : 'is-warning' ) . '" title="' . esc_attr__( 'Optional for Express. Required for Instant delivery.', 'kiriminaja-official' ) . '">';
        echo '<span aria-hidden="true">' . ( $complete ? '&#10003;' : '&#9888;' ) . '</span> ' . esc_html( $text ) . '</span>';
    }

    private function posted( string $name ): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reads do not persist; validate() verifies our nonce before staging a save.
        return isset( $_POST[$name] ) && is_string( $_POST[$name] ) ? sanitize_text_field( wp_unslash( $_POST[$name] ) ) : '';
    }

    public function form(): void {
        if ( get_current_user_id() < 1 ) {
            return;
        }
        $snapshot = $this->destinations->get( get_current_user_id() );
        $posted = $this->posted( 'kiriof_account_destination' );
        if ( '' !== $posted ) {
            try { $snapshot = BuyerDestination::normalize( json_decode( $posted, true ) ); }
            catch ( \InvalidArgumentException $error ) { /* Malformed submitted data is not revived. */ $snapshot = null; }
        }
        $strings = ( new Enqueue() )->buyer_checkout_config()['i18n'];
        $field = 'kiriof-account-district';
        echo '<div class="kiriof-account-shipping">';
        echo '<div class="kiriof-account-shipping-status">';
        $this->badges( 'shipping' );
        echo '</div>';
        wp_nonce_field( 'kiriof_save_account_destination', 'kiriof_account_destination_nonce' );
        echo '<input type="hidden" name="kiriof_account_destination" value="' . esc_attr( null !== $snapshot ? wp_json_encode( $snapshot ) : '' ) . '" />';
        $choices = array( '' => $strings['selectDistrict'] );
        if ( null !== $snapshot && '' !== $snapshot['district_id'] ) {
            $choices[$snapshot['district_id']] = $snapshot['district_label'];
        }
        woocommerce_form_field( 'kiriof_account_district', array(
            'id' => $field, 'type' => 'select', 'label' => $strings['district'], 'required' => false,
            'class' => array( 'form-row-wide' ), 'options' => $choices,
            'custom_attributes' => array( 'aria-required' => 'true', 'aria-describedby' => 'kiriof-account-district-status' ),
        ), $snapshot['district_id'] ?? '' );
        echo '<p id="kiriof-account-district-status" role="status" aria-live="polite"></p>';
        echo '<button type="button" id="kiriof-account-district-retry" hidden>' . esc_html( $strings['retry'] ) . '</button>';
        echo '<section class="kiriof-buyer-map" aria-label="' . esc_attr( $strings['mapTitle'] ) . '">';
        echo '<h3 class="kiriof-buyer-map__title">' . esc_html( $strings['mapTitle'] ) . '</h3><p>' . esc_html( $strings['mapOptional'] ) . '</p>';
        echo '<p class="kiriof-buyer-map__coverage-legend" role="note" hidden></p>';
        echo '<p class="kiriof-buyer-map__coverage-warning" role="note" aria-live="polite" hidden></p>';
        echo '<div class="kiriof-buyer-map__viewport" hidden><div class="kiriof-buyer-map__canvas" aria-label="' . esc_attr( $strings['mapHelp'] ) . '" aria-description="' . esc_attr( $strings['mapKeyboard'] ) . '"></div>';
        echo '<span class="kiriof-buyer-map__indicator" aria-hidden="true"><svg viewBox="0 0 32 44" focusable="false"><path fill="currentColor" stroke="white" stroke-width="2" d="M16 1C7.7 1 1 7.7 1 16c0 11 15 26 15 26s15-15 15-26C31 7.7 24.3 1 16 1Z"/><circle cx="16" cy="16" r="5" fill="white"/></svg></span>';
        echo '<button type="button" class="kiriof-buyer-map__locate" title="' . esc_attr( $strings['mapPermission'] ) . '">' . esc_html( $strings['mapLocate'] ) . '</button></div>';
        echo '<p class="kiriof-buyer-map__status" role="status" aria-live="polite"></p></section></div>';
    }

    public function assets(): void {
        if ( get_current_user_id() < 1 || ! function_exists( 'is_account_page' ) || ! is_account_page() || ! is_wc_endpoint_url( 'edit-address' ) ) {
            return;
        }
        $url = plugin_dir_url( KIRIOF_DIR . 'kiriminaja.php' );
        wp_enqueue_style( 'kiriof-account-destination', $url . 'assets/buyer/css/kiriof-buyer-checkout.css', array(), (string) filemtime( KIRIOF_DIR . 'assets/buyer/css/kiriof-buyer-checkout.css' ) );
        if ( 'shipping' !== get_query_var( 'edit-address' ) ) {
            return;
        }
        $enqueue = new Enqueue();
        $config = $enqueue->buyer_checkout_config();
        $enqueue->register_map_provider_assets();
        if ( 'leaflet' === $config['map']['provider'] ) {
            wp_enqueue_style( 'kiriof-leaflet' );
        }
        // The account IIFE imports shared map helpers without registering Blocks stores.
        $account_path = 'assets/buyer/dist/kiriminaja-buyer-account-shipping.js';
        wp_enqueue_script( 'kiriof-account-shipping', $url . $account_path, array( 'kiriof-map-provider' ), file_exists( KIRIOF_DIR . $account_path ) ? (string) filemtime( KIRIOF_DIR . $account_path ) : KIRIOF_VERSION, true );
        wp_localize_script( 'kiriof-account-shipping', 'kiriofAccountShippingConfig', array( 'ajaxUrl' => $config['ajaxUrl'], 'nonce' => $config['nonce'], 'map' => $config['map'], 'i18n' => $config['i18n'] ) );
    }

    /** Woo has already applied posted shipping fields to this candidate customer. */
    public function validate( $user_id, $type, $fields, $customer ): void {
        unset( $this->validated[$user_id] );
        if ( 'shipping' !== $type || (int) $user_id < 1 || (int) $user_id !== get_current_user_id() ) {
            return;
        }
        $nonce = $this->posted( 'kiriof_account_destination_nonce' );
        // An older/custom template without our fields remains usable; no plugin data is written.
        if ( '' === $nonce && '' === $this->posted( 'kiriof_account_destination' ) && '' === $this->posted( 'kiriof_account_district' ) ) {
            return;
        }
        if ( ! wp_verify_nonce( $nonce, 'kiriof_save_account_destination' ) ) {
            wc_add_notice( __( 'Could not verify your shipping subdistrict. Please try again.', 'kiriminaja-official' ), 'error' );
            return;
        }
        try {
            $address = array();
            foreach ( BuyerDestination::ADDRESS_FIELDS as $field ) {
                $getter = 'get_shipping_' . $field;
                $address[$field] = $customer->$getter();
            }
            $address = BuyerDestination::address( $address );
            if ( 'ID' !== $address['country'] ) {
                $this->validated[$user_id] = array( 'district_id' => '', 'district_label' => '', 'postcode' => $address['postcode'], 'country' => $address['country'], 'address_type' => 'shipping', 'version' => 1 );
                return;
            }
            if ( ! preg_match( '/^[0-9]{5}$/D', $address['postcode'] ) ) {
                throw new \InvalidArgumentException();
            }
            $raw = $this->posted( 'kiriof_account_destination' );
            $destination = '' !== $raw ? BuyerDestination::normalize( json_decode( $raw, true ) ) : null;
            $id = $this->posted( 'kiriof_account_district' );
            if ( null === $destination ) {
                $destination = array( 'district_id' => $id, 'district_label' => '', 'postcode' => $address['postcode'], 'country' => 'ID', 'address_type' => 'shipping', 'version' => 1 );
            }
            if ( ! preg_match( '/^[1-9][0-9]*$/D', $id ) || $id !== $destination['district_id'] || $destination['postcode'] !== $address['postcode'] || $destination['country'] !== $address['country']
                || ( 2 === $destination['version'] && $destination['shipping_address'] !== $address ) ) {
                throw new \InvalidArgumentException();
            }
            $lookup = $this->lookup;
            if ( null === $lookup ) {
                // Keep the fallback constructor complete when loaded outside Init.
                $settings = new \KiriminAjaOfficial\Repositories\SettingRepository();
                $lookup = new CheckoutServiceFactory(
                    $settings,
                    new \KiriminAjaOfficial\Repositories\TransactionRepository(),
                    new \KiriminAjaOfficial\Repositories\WpPostMetaRepository(),
                    new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository(),
                    new \KiriminAjaOfficial\Repositories\CodFeeApiRepository(),
                    new \KiriminAjaOfficial\Services\ShipmentLocationService( null, $settings )
                );
            }
            $result = $lookup->districtSearch( $address['postcode'] );
            if ( ! is_object( $result ) || 200 !== ( $result->status ?? null ) || ! is_array( $result->data ) ) {
                throw new \RuntimeException();
            }
            foreach ( $result->data as $row ) {
                $row = (object) $row;
                if ( $id === (string) ( $row->id ?? '' ) ) {
                    $destination['district_label'] = $row->text ?? '';
                    $this->validated[$user_id] = BuyerDestination::normalize( $destination );
                    return;
                }
            }
            throw new \InvalidArgumentException();
        } catch ( \InvalidArgumentException $error ) {
            wc_add_notice( __( 'Please select your Subdistrict to view shipping options.', 'kiriminaja-official' ), 'error' );
        } catch ( \Throwable $error ) {
            wc_add_notice( __( 'Could not verify your shipping subdistrict. Please try again.', 'kiriminaja-official' ), 'error' );
        }
    }

    public function saved( $user_id, $type, $fields = array(), $customer = null ): void {
        if ( 'shipping' !== $type || (int) $user_id !== get_current_user_id() || ! isset( $this->validated[$user_id] ) || wc_notice_count( 'error' ) > 0 ) {
            return;
        }
        try {
            $destination = $this->validated[$user_id];
            unset( $this->validated[$user_id] );
            $this->destinations->save( $user_id, $destination, $this->destinations->address( $user_id ) );
            $wc = function_exists( 'WC' ) ? WC() : null;
            if ( $wc && $wc->session && isset( $wc->customer ) && is_callable( array( $wc->customer, 'get_id' ) ) && (int) $wc->customer->get_id() === (int) $user_id ) {
                // A successful explicit account save supersedes old checkout aliases.
                foreach ( array( 'kiriof_buyer_destination', 'kiriof_buyer_destination_coordinates', 'shipping_destination_id', 'shipping_destination_name', 'destination_id', 'destination_name', 'kiriof_destination_area', 'kiriof_destination_area_name', 'kiriof_checkout_postcode', 'kiriof_checkout_token', 'kiriof_destination_postcode_map' ) as $key ) {
                    $wc->session->__unset( $key );
                }
                $this->destinations->hydrateSession();
            }
        } catch ( \Throwable $error ) {
            wc_add_notice( __( 'Could not verify your shipping subdistrict. Please try again.', 'kiriminaja-official' ), 'error' );
        }
    }
}
