<?php
/** Managed warehouse mutations. Legacy WooCommerce form handlers remain supported. */
namespace KiriminAjaOfficial\Controllers;

use KiriminAjaOfficial\Services\ShipmentLocationService;
use KiriminAjaOfficial\Services\WarehousesPageData;
use KiriminAjaOfficial\Repositories\SettingRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WarehouseSettingsController {
	private ShipmentLocationService $locations;
	private SettingRepository $settings;

	public function __construct( ?ShipmentLocationService $locations = null, ?SettingRepository $settings = null ) {
		$this->locations = $locations ?? new ShipmentLocationService();
		$this->settings = $settings ?? new SettingRepository();
	}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'redirectLegacyRoute' ), 1 );
		add_action( 'wp_ajax_kiriof_save_shipment_location', array( $this, 'save' ) );
		add_action( 'wp_ajax_kiriof_delete_shipment_location', array( $this, 'delete' ) );
		add_action( 'wp_ajax_kiriof_toggle_shipment_location', array( $this, 'toggle' ) );
	}

	/** Only GET navigation redirects: never replay or redirect a legacy mutation. */
	public function redirectLegacyRoute(): void {
		if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only legacy navigation.
		if ( ! isset( $_GET['page'], $_GET['tab'] ) || ! is_string( $_GET['page'] ) || ! is_string( $_GET['tab'] ) || 'wc-settings' !== wp_unslash( $_GET['page'] ) || 'kiriminaja_warehouses' !== wp_unslash( $_GET['tab'] ) ) {
			return;
		}
		$url = admin_url( 'admin.php?page=kiriminaja-setting&section=warehouses' );
		$key = isset( $_GET['kiriof_location'] ) && is_string( $_GET['kiriof_location'] ) ? wp_unslash( $_GET['kiriof_location'] ) : '';
		if ( 'new' === $key || preg_match( '/\A[1-9][0-9]*\z/D', $key ) ) {
			$url = add_query_arg( 'kiriof_location', $key, $url );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		wp_safe_redirect( $url );
		exit;
	}

	/** @return array<string, string>|null Sanitized scalar payload; arrays are never coerced. */
	private function payload(): ?array {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			$this->error( 403, __( 'Insufficient permissions', 'kiriminaja-official' ) );
			return null;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified before returning any input.
		$raw = isset( $_POST['data'] ) && is_array( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array();
		if ( ! isset( $raw['nonce'] ) || ! is_string( $raw['nonce'] ) || ! wp_verify_nonce( $raw['nonce'], KIRIOF_NONCE ) ) {
			$this->error( 403, __( 'Security check failed', 'kiriminaja-official' ) );
			return null;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$data = array();
		foreach ( $raw as $key => $value ) {
			if ( ! is_scalar( $value ) && null !== $value ) {
				$this->error( 400 );
				return null;
			}
			$data[ $key ] = 'address' === $key ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );
		}
		return $data;
	}

	private function error( int $status, string $message = '' ): void {
		wp_send_json_error( array( 'status' => $status, 'message' => $message ?: __( 'Shipment location could not be saved. Check all required fields and try again.', 'kiriminaja-official' ) ), $status );
	}

	/** Invalid or missing identifiers must never silently create or mutate another row. */
	private function existing( array $data ): ?object {
		if ( ! isset( $data['id'] ) || ! preg_match( '/\A[1-9][0-9]*\z/D', $data['id'] ) || (string) (int) $data['id'] !== $data['id'] ) {
			$this->error( 400 );
			return null;
		}
		try {
			$row = $this->locations->repository()->getById( (int) $data['id'] );
		} catch ( \Throwable $exception ) {
			$this->error( 500 );
			return null;
		}
		if ( ! $row ) {
			$this->error( 404, __( 'Shipment location not found.', 'kiriminaja-official' ) );
			return null;
		}
		return $row;
	}

	public function save(): void {
		$data = $this->payload();
		if ( null === $data ) {
			return;
		}
		// Creation is explicit. Missing ID is a malformed edit, not a request to create.
		$new = isset( $data['id'] ) && in_array( $data['id'], array( 'new', '0' ), true );
		$row = $new ? null : $this->existing( $data );
		if ( ! $new && ! $row ) {
			return;
		}
		$repository = $this->locations->repository();
		try {
			$first = $new && 0 === $repository->count();
			$at_limit = $new && ! $first && ! $repository->canCreateCustomLocation();
		} catch ( \Throwable $exception ) {
			$this->error( 500 );
			return;
		}
		if ( $at_limit ) {
			$this->error( 409, __( 'The custom shipment address limit has been reached. Edit or delete an existing custom address first.', 'kiriminaja-official' ) );
			return;
		}
		// name and sender_name alias the same persisted name; do not invent an independent field.
		$name = $data['name'] ?? $data['sender_name'] ?? '';
		$phone = $data['sender_phone'] ?? $data['phone'] ?? '';
		$district = $data['district'] ?? $data['sub_district_id'] ?? '';
		$zip = $data['zip'] ?? $data['zip_code'] ?? '';
		$lat = $data['lat'] ?? $data['latitude'] ?? '';
		$lng = $data['lng'] ?? $data['longitude'] ?? '';
		if ( '' === $name || '' === $phone || empty( $data['address'] ) || ! preg_match( '/\A[1-9][0-9]*\z/D', $district ) || (string) (int) $district !== $district || '' === $zip || ! is_numeric( $lat ) || ! is_numeric( $lng ) || ! is_finite( (float) $lat ) || ! is_finite( (float) $lng ) || abs( (float) $lat ) > 90 || abs( (float) $lng ) > 180 ) {
			$this->error( 400 );
			return;
		}
		$country_state = $data['country_state'] ?? ( $row ? (string) ( $row->country ?? '' ) . ( empty( $row->state ) ? '' : ':' . $row->state ) : 'ID' );
		// State codes or legacy plain-text names remain valid; reject malformed composites.
		if ( ! preg_match( '/\A[A-Z]{2}(?::[\p{L}\p{N}][\p{L}\p{N} .’\x{0027}()_-]*)?\z/uD', $country_state ) ) {
			$this->error( 400 );
			return;
		}
		$parts = explode( ':', $country_state, 2 );
		$values = array(
			'name' => $name, 'phone' => $phone, 'address' => $data['address'],
			'address_2' => $data['address_2'] ?? '', 'city' => $data['city'] ?? '',
			'country' => $parts[0], 'state' => $parts[1] ?? '',
			'sub_district_id' => (int) $district,
			'sub_district_name' => $data['district_label'] ?? $data['sub_district_name'] ?? '',
			'zip_code' => $zip, 'latitude' => $lat, 'longitude' => $lng,
		);
		// Default changes belong to the existing default-management workflow. Never
		// promote a custom row or demote/deactivate the default from a full-row save.
		if ( isset( $data['is_default'] ) && ! in_array( $data['is_default'], array( '0', '1' ), true ) ) {
			$this->error( 400 );
			return;
		}
		if ( isset( $data['is_default'] ) && (int) $data['is_default'] !== ( $first || ( $row && ! empty( $row->is_default ) ) ? 1 : 0 ) ) {
			$this->error( 409 );
			return;
		}
		// Options can use an external cache: SQL transactions cannot make this save atomic.
		// Snapshot before any mutation, write/verify mirrors first, then publish the row.
		$default = $first || ( $row && ! empty( $row->is_default ) );
		$origin_snapshot = null;
		$option_snapshot = array();
		$id = false;
		$row_attempted = false;
		try {
			if ( $default ) {
				$origin_snapshot = $this->settings->snapshotOriginMirrorData();
				foreach ( $this->defaultOptions( (object) $values ) as $key => $value ) {
					$missing = new \stdClass();
					$before = get_option( $key, $missing );
					$option_snapshot[ $key ] = array( 'exists' => $before !== $missing, 'value' => $before );
				}
				$this->mirrorDefault( (object) $values );
			}
			$row_attempted = true;
			if ( $new ) {
				$values['is_default'] = $first ? 1 : 0;
				$values['is_active'] = 1;
				$id = $repository->insert( $values );
			} else {
				$id = $repository->update( (int) $row->id, $values ) ? (int) $row->id : false;
			}
			$saved = $id ? $repository->getById( $id ) : null;
			if ( ! $saved || array_intersect_key( (array) $saved, $values ) != $values ) {
				throw new \RuntimeException( 'Shipment location could not be saved.' );
			}
		} catch ( \Throwable $exception ) {
			$restored = true;
			if ( $default && null !== $origin_snapshot ) {
				// Attempt every independent compensation even if another one fails.
				try {
					$restored = $this->settings->restoreOriginMirrorData( $origin_snapshot );
				} catch ( \Throwable $rollback_error ) {
					$restored = false;
				}
				foreach ( $option_snapshot as $key => $before ) {
					try {
						if ( $before['exists'] ) {
							update_option( $key, $before['value'] );
							$ok = get_option( $key ) === $before['value'];
						} else {
							delete_option( $key );
							$missing = new \stdClass();
							$ok = get_option( $key, $missing ) === $missing;
						}
						$restored = $ok && $restored;
					} catch ( \Throwable $rollback_error ) {
						$restored = false;
					}
				}
				if ( $row_attempted ) {
					try {
						if ( $row ) {
							$before = array_intersect_key( (array) $row, $values );
							$ok = $repository->update( (int) $row->id, $before );
							$actual = $repository->getById( (int) $row->id );
							$ok = $ok && $actual && array_intersect_key( (array) $actual, $before ) == $before;
						} else {
							// A newly created default cannot be deleted through the public guarded API.
							global $wpdb;
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$ok = $id && false !== $wpdb->delete( $repository->getTableName(), array( 'id' => $id ), array( '%d' ) );
							$ok = $ok && ( ! $id || ! $repository->getById( $id ) );
						}
						$restored = $ok && $restored;
					} catch ( \Throwable $rollback_error ) {
						$restored = false;
					}
				}
			}
			$this->error( 500, $restored ? '' : __( 'Shipment location save failed and could not be fully restored. Review the default location and store address before retrying.', 'kiriminaja-official' ) );
			return;
		}
		wp_send_json_success( array( 'status' => 200, 'id' => $id, 'location' => $saved ? WarehousesPageData::serializeLocation( $saved ) : null ) );
	}

	public function delete(): void {
		$data = $this->payload();
		if ( null === $data ) {
			return;
		}
		$row = $this->existing( $data );
		if ( ! $row ) {
			return;
		}
		if ( ! empty( $row->is_default ) ) {
			$this->error( 409, __( 'The default location cannot be deleted or disabled.', 'kiriminaja-official' ) );
			return;
		}
		try {
			$success = $this->locations->repository()->delete( (int) $row->id );
		} catch ( \Throwable $exception ) {
			$this->error( 500 );
			return;
		}
		if ( ! $success ) {
			$this->error( 500 );
			return;
		}
		wp_send_json_success( array( 'status' => 200, 'id' => (int) $row->id ) );
	}

	/** Partial status update: no stale address fields can overwrite newer data. */
	public function toggle(): void {
		$data = $this->payload();
		if ( null === $data ) {
			return;
		}
		$row = $this->existing( $data );
		if ( ! $row ) {
			return;
		}
		if ( ! isset( $data['is_active'] ) || ! in_array( $data['is_active'], array( '0', '1' ), true ) ) {
			$this->error( 400 );
			return;
		}
		if ( ! empty( $row->is_default ) ) {
			$this->error( 409, __( 'The default location cannot be deleted or disabled.', 'kiriminaja-official' ) );
			return;
		}
		try {
			$success = $this->locations->repository()->update( (int) $row->id, array( 'is_active' => (int) $data['is_active'] ) );
		} catch ( \Throwable $exception ) {
			$this->error( 500 );
			return;
		}
		if ( ! $success ) {
			$this->error( 500 );
			return;
		}
		wp_send_json_success( array( 'status' => 200, 'id' => (int) $row->id, 'is_active' => '1' === $data['is_active'] ) );
	}

	private function defaultOptions( object $row ): array {
		return array_map( 'strval', array( 'kiriof_wc_origin_name' => $row->name, 'kiriof_wc_origin_phone' => $row->phone, 'kiriof_wc_origin_area' => $row->sub_district_id, 'woocommerce_store_address' => $row->address, 'woocommerce_store_address_2' => $row->address_2 ?? '', 'woocommerce_store_city' => $row->city ?? '', 'woocommerce_store_postcode' => $row->zip_code, 'woocommerce_default_country' => ( $row->country ?? '' ) . ( empty( $row->state ) ? '' : ':' . $row->state ) ) );
	}

	/** Keep the legacy origin and native WooCommerce store address synchronized. */
	private function mirrorDefault( object $row ): void {
		$payload = array(
			'origin_name' => (string) $row->name, 'origin_phone' => (string) $row->phone,
			'origin_address' => (string) $row->address, 'origin_zip_code' => (string) $row->zip_code,
			'origin_sub_district_id' => (string) $row->sub_district_id,
			'origin_sub_district_name' => (string) ( $row->sub_district_name ?? '' ),
			'origin_latitude' => (string) $row->latitude, 'origin_longitude' => (string) $row->longitude,
		);
		foreach ( $this->defaultOptions( $row ) as $key => $value ) {
			update_option( $key, $value );
			// false from update_option also means unchanged; verify the observable value.
			if ( get_option( $key ) !== $value ) {
				throw new \RuntimeException( 'Store address could not be saved.' );
			}
		}
		if ( false === $this->settings->storeOriginMirrorData( $payload ) ) {
			throw new \RuntimeException( 'Origin mirror could not be saved.' );
		}
		$actual = $this->settings->snapshotOriginMirrorData();
		foreach ( $payload as $key => $value ) {
			if ( ! isset( $actual[ $key ] ) || (string) $actual[ $key ]['value'] !== $value ) {
				throw new \RuntimeException( 'Origin mirror could not be verified.' );
			}
		}
	}
}
