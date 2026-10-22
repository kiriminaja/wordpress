<?php
/** Managed shipment-location settings bootstrap. */
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WarehousesPageData {
	private ShipmentLocationService $locations;

	public function __construct( ?ShipmentLocationService $locations = null ) {
		$this->locations = $locations ?? new ShipmentLocationService();
	}

	/** The existing database has one name field, used as both warehouse and sender name. */
	public static function serializeLocation( object $row ): array {
		return array(
			'id' => (int) $row->id,
			'name' => (string) ( $row->name ?? '' ),
			'sender_name' => (string) ( $row->name ?? '' ),
			'sender_phone' => (string) ( $row->phone ?? '' ),
			'address' => (string) ( $row->address ?? '' ),
			'address_2' => (string) ( $row->address_2 ?? '' ),
			'city' => (string) ( $row->city ?? '' ),
			'country_state' => (string) ( $row->country ?? '' ) . ( empty( $row->state ) ? '' : ':' . $row->state ),
			'zip' => (string) ( $row->zip_code ?? '' ),
			'district' => (string) ( $row->sub_district_id ?? '' ),
			'district_label' => (string) ( $row->sub_district_name ?? '' ),
			'lat' => (string) ( $row->latitude ?? '' ),
			'lng' => (string) ( $row->longitude ?? '' ),
			'is_active' => 1 === (int) $row->is_active,
			'is_default' => 1 === (int) $row->is_default,
		);
	}

	/** WooCommerce's allowed countries and native states, with country-only legacy values. */
	private function countryStates(): array {
		$options = array();
		$countries = WC()->countries;
		foreach ( $countries->get_allowed_countries() as $country => $label ) {
			$label = sanitize_text_field( html_entity_decode( (string) $label, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			$options[] = array( 'value' => (string) $country, 'label' => $label );
			$states = $countries->get_states( $country );
			foreach ( is_array( $states ) ? $states : array() as $state => $state_label ) {
				$state_label = sanitize_text_field( html_entity_decode( (string) $state_label, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				$options[] = array( 'value' => $country . ':' . $state, 'label' => $label . ' / ' . $state_label );
			}
		}
		return $options;
	}

	public function prepare(): array {
		$rows = $this->locations->repository()->getAll();
		if ( empty( $rows ) ) {
			$this->locations->seedDefaultFromGlobalOrigin();
			$rows = $this->locations->repository()->getAll();
		}
		$limit = defined( 'KIRIOF_MAX_CUSTOM_SHIPMENT_LOCATIONS' ) ? max( 0, (int) KIRIOF_MAX_CUSTOM_SHIPMENT_LOCATIONS ) : 5;
		$count = count( array_filter( $rows, static function ( $row ) { return empty( $row->is_default ); } ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only dialog navigation.
		$editing = isset( $_GET['kiriof_location'] ) && is_string( $_GET['kiriof_location'] ) ? wp_unslash( $_GET['kiriof_location'] ) : '';
		$editing = 'new' === $editing || preg_match( '/\A[1-9][0-9]*\z/D', $editing ) ? $editing : '';
		$toolbar = SettingsPageData::settingsToolbar( __( 'Manage Locations', 'kiriminaja-official' ) );
		return array(
			'view' => 'warehouses',
			'toolbar' => $toolbar,
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( KIRIOF_NONCE ),
			'locations' => array_map( array( self::class, 'serializeLocation' ), $rows ),
			'countryStates' => $this->countryStates(),
			'editingKey' => $editing,
			'customCount' => $count,
			'customLimit' => $limit,
			'canCreate' => empty( $rows ) || $count < $limit,
			'actions' => array( 'save' => 'kiriof_save_shipment_location', 'delete' => 'kiriof_delete_shipment_location', 'toggle' => 'kiriof_toggle_shipment_location' ),
			'map' => InstantDetailMapData::mapConfig(),
			'i18n' => array(
				'title' => __( 'Manage Locations', 'kiriminaja-official' ),
				'description' => __( 'Manage every pickup address used for shipping calculations. The default location is used when no other rule applies.', 'kiriminaja-official' ),
				'add' => __( 'Add Shipment Location', 'kiriminaja-official' ),
				'edit' => __( 'Edit Shipment Location', 'kiriminaja-official' ),
				'name' => __( 'Warehouse name', 'kiriminaja-official' ),
				'region' => __( 'Region(s)', 'kiriminaja-official' ),
				'status' => __( 'Status', 'kiriminaja-official' ),
				'active' => __( 'Active', 'kiriminaja-official' ),
				'inactive' => __( 'Inactive', 'kiriminaja-official' ),
				'default' => __( 'Default', 'kiriminaja-official' ),
				'empty' => __( 'No shipment locations yet.', 'kiriminaja-official' ),
				'senderName' => __( 'Sender Name', 'kiriminaja-official' ),
				'senderPhone' => __( 'Sender Phone', 'kiriminaja-official' ),
				'address' => __( 'Address', 'kiriminaja-official' ),
				'address2' => __( 'Address line 2', 'kiriminaja-official' ),
				'city' => __( 'City', 'kiriminaja-official' ),
				'countryState' => __( 'Country / State', 'kiriminaja-official' ),
				'loading' => __( 'Searching…', 'kiriminaja-official' ),
				'noResults' => __( 'No areas found.', 'kiriminaja-official' ),
				'typeMore' => __( 'Type at least 3 characters to search.', 'kiriminaja-official' ),
				'searchFailed' => __( 'Could not search subdistricts. Check the KiriminAja connection and try again.', 'kiriminaja-official' ),
				'mapUnavailable' => __( 'Map or current location unavailable. Please try again.', 'kiriminaja-official' ),
				'pinHint' => __( 'Click or move the map to select the pickup location.', 'kiriminaja-official' ),
				'pinSelected' => __( 'Pickup location selected.', 'kiriminaja-official' ),
				'area' => __( 'Area', 'kiriminaja-official' ),
				'zip' => __( 'Zip', 'kiriminaja-official' ),
				'pin' => __( 'Pin Location', 'kiriminaja-official' ),
				'myLocation' => __( 'My Location', 'kiriminaja-official' ),
				'save' => __( 'Save changes', 'kiriminaja-official' ),
				'saving' => __( 'Saving…', 'kiriminaja-official' ),
				'cancel' => __( 'Cancel', 'kiriminaja-official' ),
				'delete' => __( 'Delete', 'kiriminaja-official' ),
				'confirmDelete' => __( 'Are you sure you want to delete this shipment location?', 'kiriminaja-official' ),
				'saveFailed' => __( 'Shipment location could not be saved. Check all required fields and try again.', 'kiriminaja-official' ),
				'limitReached' => __( 'The custom shipment address limit has been reached. Edit or delete an existing custom address first.', 'kiriminaja-official' ),
				/* translators: 1: current custom location count, 2: custom location limit. */
				'limit' => __( '%1$d of %2$d custom shipment addresses used. The default origin is not counted.', 'kiriminaja-official' ),
			),
		);
	}
}
