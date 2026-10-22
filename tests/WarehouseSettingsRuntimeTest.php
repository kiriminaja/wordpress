<?php
use PHPUnit\Framework\TestCase;

final class WarehouseSettingsRuntimeTest extends TestCase {
	private function runCase( array $input ): array {
		$output = shell_exec( kiriof_test_fixture_php_shell( array( PLUGIN_DIR . '/tests/fixtures/warehouse-settings-runtime.php', json_encode( $input, JSON_THROW_ON_ERROR ) ) ) );
		return json_decode( $output, true, 512, JSON_THROW_ON_ERROR );
	}

	private function valid( array $changes = array() ): array {
		return array_merge( array( 'nonce' => 'valid', 'id' => '2', 'name' => 'Updated warehouse', 'sender_phone' => '081234', 'address' => 'New road', 'address_2' => 'Floor 3', 'city' => 'Bandung', 'country_state' => 'ID:JB', 'district' => '123', 'district_label' => 'Area', 'zip' => '12345', 'lat' => '-6', 'lng' => '106' ), $changes );
	}

	public function test_bootstrap_serializes_existing_schema_and_managed_routing(): void {
		$result = $this->runCase( array( 'operation' => 'bootstrap', 'query' => array( 'kiriof_location' => '2' ) ) )['result'];
		self::assertSame( 'warehouses', $result['view'] );
		self::assertSame( '2', $result['editingKey'] );
		self::assertSame( array( 'id', 'name', 'sender_name', 'sender_phone', 'address', 'address_2', 'city', 'country_state', 'zip', 'district', 'district_label', 'lat', 'lng', 'is_active', 'is_default' ), array_keys( $result['locations'][0] ) );
		self::assertSame( 'ID:JK', $result['locations'][0]['country_state'] );
		self::assertSame( 'Default warehouse', $result['locations'][0]['sender_name'] );
		self::assertTrue( $result['locations'][0]['is_default'] );
		self::assertSame( 1, $result['customCount'] );
		self::assertSame( 5, $result['customLimit'] );
		self::assertSame( 'leaflet', $result['map']['provider'] );
	}

	public function test_bootstrap_uses_woo_country_state_boundary_and_plain_text_labels(): void {
		$output = $this->runCase( array( 'operation' => 'bootstrap', 'countries' => array( 'ID' => '<b>Indonesia</b>', 'CI' => 'C&ocirc;te d&#039;Ivoire', 'SG' => 'Singapore' ), 'states' => array( 'ID' => array( 'JK' => 'Jakarta &amp; Islands', 'JB' => '&lt;b&gt;Jawa Barat&lt;/b&gt;' ), 'CI' => array(), 'SG' => false ) ) );
		self::assertSame( array(
			array( 'value' => 'ID', 'label' => 'Indonesia' ),
			array( 'value' => 'ID:JK', 'label' => 'Indonesia / Jakarta & Islands' ),
			array( 'value' => 'ID:JB', 'label' => 'Indonesia / Jawa Barat' ),
			array( 'value' => 'CI', 'label' => "Côte d'Ivoire" ),
			array( 'value' => 'SG', 'label' => 'Singapore' ),
		), $output['result']['countryStates'] );
		self::assertSame( array( 'get_allowed_countries', 'get_states:ID', 'get_states:CI', 'get_states:SG' ), $output['country_calls'] );
		$output = $this->runCase( array( 'operation' => 'bootstrap', 'states' => array( 'ID' => false ) ) );
		self::assertContains( array( 'value' => 'ID', 'label' => 'Indonesia' ), $output['result']['countryStates'] );
		self::assertSame( 'ID:JK', $output['result']['locations'][0]['country_state'] );
		self::assertSame( array(), $output['writes'] );
		foreach ( array(
			'countryState' => 'Country / State', 'loading' => 'Searching…', 'noResults' => 'No areas found.',
			'typeMore' => 'Type at least 3 characters to search.',
			'searchFailed' => 'Could not search subdistricts. Check the KiriminAja connection and try again.',
			'mapUnavailable' => 'Map or current location unavailable. Please try again.',
			'pinHint' => 'Click or move the map to select the pickup location.',
			'pinSelected' => 'Pickup location selected.',
		) as $key => $label ) {
			self::assertSame( $label, $output['result']['i18n'][$key] );
		}
	}

	public function test_country_state_structure_and_district_integer_overflow_are_validated_without_external_lookup(): void {
		foreach ( array( '', 'Indonesia', 'ID:', 'ID:JB:JK', 'ID:bad/state', 'ID:???' ) as $country_state ) {
			$output = $this->runCase( array( 'operation' => 'save', 'data' => $this->valid( array( 'country_state' => $country_state ) ) ) );
			self::assertSame( 400, $output['result']['http'], $country_state );
			self::assertSame( array(), $output['writes'] );
		}
		foreach ( array( 'ID', 'ID:JB', 'ID:Jawa Barat', "CI:Val d'Oise", 'JP:東京都', 'ZZ:Legacy Province' ) as $country_state ) {
			$output = $this->runCase( array( 'operation' => 'save', 'data' => $this->valid( array( 'country_state' => $country_state ) ) ) );
			self::assertTrue( $output['result']['success'], $country_state );
			self::assertSame( $country_state, $output['result']['data']['location']['country_state'] );
		}
		foreach ( array( '0', '01', '-1', '1e3', (string) PHP_INT_MAX . '0' ) as $district ) {
			$output = $this->runCase( array( 'operation' => 'save', 'data' => $this->valid( array( 'district' => $district ) ) ) );
			self::assertSame( 400, $output['result']['http'], $district );
			self::assertSame( array(), $output['writes'] );
		}
		$data = $this->valid();
		unset( $data['country_state'], $data['name'], $data['sender_phone'], $data['district'], $data['zip'], $data['lat'], $data['lng'], $data['district_label'] );
		$data += array( 'sender_name' => 'Legacy sender', 'phone' => '08123', 'sub_district_id' => '456', 'sub_district_name' => 'Legacy area', 'zip_code' => '54321', 'latitude' => '-7', 'longitude' => '107' );
		$output = $this->runCase( array( 'operation' => 'save', 'data' => $data ) );
		self::assertTrue( $output['result']['success'] );
		self::assertSame( 'ID:JK', $output['result']['data']['location']['country_state'] );
		self::assertSame( 'Legacy sender', $output['rows'][1]['name'] );
		self::assertSame( 456, $output['rows'][1]['sub_district_id'] );
	}

	public function test_repository_failures_return_generic_json_errors(): void {
		foreach ( array(
			array( 'save', 'getById', array() ), array( 'delete', 'getById', array() ), array( 'toggle', 'getById', array() ),
			array( 'save', 'count', array( 'id' => 'new' ) ), array( 'save', 'countCustomLocations', array( 'id' => 'new' ) ),
			array( 'save', 'insert', array( 'id' => 'new' ) ), array( 'save', 'update', array() ),
			array( 'delete', 'delete', array() ), array( 'toggle', 'update', array( 'is_active' => '0' ) ),
			array( 'save', 'mirror', array( 'id' => '1' ) ),
		) as [ $operation, $method, $changes ] ) {
			foreach ( array( 'throw', 'false' ) as $failure ) {
				if ( 'false' === $failure && in_array( $method, array( 'getById', 'count', 'countCustomLocations' ), true ) ) { continue; }
				$output = $this->runCase( array( 'operation' => $operation, 'data' => $this->valid( $changes ), 'failures' => array( $method => $failure ) ) );
				self::assertFalse( $output['result']['success'], $operation . ':' . $method );
				self::assertSame( 500, $output['result']['http'] );
				self::assertStringNotContainsString( 'Private', $output['result']['data']['message'] );
			}
		}
		foreach ( array( 'throw', 'false' ) as $failure ) {
			$output = $this->runCase( array( 'operation' => 'save', 'data' => $this->valid(), 'failures' => array( 'getById' => array( 3 => $failure ) ) ) );
			self::assertSame( 500, $output['result']['http'] );
		}
	}

	public function test_real_mirror_repository_propagates_insert_update_and_read_failures_but_accepts_zero(): void {
		foreach ( array( 'db_insert', 'db_update', 'db_read' ) as $method ) {
			$output = $this->runCase( array( 'operation' => 'mirror', 'mirror_payload' => array( 'origin_name' => 'Name' ), 'settings' => 'db_update' === $method ? array( 'origin_name' => 'Before' ) : array(), 'failures' => array( $method => 'false' ) ) );
			self::assertFalse( $output['result'], $method );
		}
		$output = $this->runCase( array( 'operation' => 'mirror', 'mirror_payload' => array( 'origin_name' => 'Same' ), 'settings' => array( 'origin_name' => 'Same' ) ) );
		self::assertTrue( $output['result'] );
	}

	public function test_partial_real_mirror_failure_restores_exact_snapshots_before_row_write(): void {
		foreach ( array( 'db_insert', 'db_update' ) as $method ) {
			$settings = 'db_update' === $method ? array( 'origin_name' => 'Old name', 'origin_phone' => 'Old phone' ) : array();
			$options = array( 'woocommerce_store_address' => 'Old road', 'kiriof_wc_origin_name' => 'Old option' );
			$output = $this->runCase( array( 'operation' => 'save', 'data' => $this->valid( array( 'id' => '1' ) ), 'settings' => $settings, 'options' => $options, 'failures' => array( $method => array( 2 => 'false' ) ) ) );
			self::assertFalse( $output['result']['success'] );
			self::assertSame( 500, $output['result']['http'] );
			self::assertSame( $settings, $output['settings'] );
			self::assertSame( $options, $output['options'] );
			self::assertSame( 'Default warehouse', $output['rows'][0]['name'] );
			self::assertSame( array(), $output['writes'] );
			self::assertStringNotContainsString( 'Private', $output['result']['data']['message'] );
		}
	}

	public function test_option_failures_restore_snapshots_and_unchanged_false_is_valid(): void {
		$options = array( 'woocommerce_store_address' => 'Old road' );
		$output = $this->runCase( array( 'operation' => 'save', 'data' => $this->valid( array( 'id' => '1' ) ), 'options' => $options, 'failures' => array( 'option:woocommerce_store_address' => array( 1 => 'false' ) ) ) );
		self::assertFalse( $output['result']['success'] );
		self::assertSame( $options, $output['options'] );
		self::assertSame( array(), $output['writes'] );
		$success = $this->runCase( array( 'operation' => 'save', 'data' => $this->valid( array( 'id' => '1' ) ) ) );
		$output = $this->runCase( array( 'operation' => 'save', 'data' => $this->valid( array( 'id' => '1' ) ), 'options' => $success['options'], 'settings' => $success['settings'] ) );
		self::assertTrue( $output['result']['success'] );
	}

	public function test_row_failure_compensates_mirrors_and_reports_unavailable_compensation(): void {
		foreach ( array( 'false', 'throw' ) as $failure ) {
			$output = $this->runCase( array( 'operation' => 'save', 'data' => $this->valid( array( 'id' => '1' ) ), 'failures' => array( 'update' => array( 1 => $failure ) ) ) );
			self::assertFalse( $output['result']['success'] );
			self::assertSame( array(), $output['settings'] );
			self::assertSame( array(), $output['options'] );
			self::assertSame( 'Default warehouse', $output['rows'][0]['name'] );
		}
		$output = $this->runCase( array( 'operation' => 'save', 'data' => $this->valid( array( 'id' => '1' ) ), 'failures' => array( 'db_insert' => array( 2 => 'false' ), 'db_delete' => 'false' ) ) );
		self::assertFalse( $output['result']['success'] );
		self::assertStringContainsString( 'could not be fully restored', $output['result']['data']['message'] );
		self::assertStringNotContainsString( 'Private', $output['result']['data']['message'] );
		$output = $this->runCase( array( 'operation' => 'save', 'rows' => array(), 'data' => $this->valid( array( 'id' => 'new' ) ), 'failures' => array( 'db_insert' => array( 2 => 'false' ) ) ) );
		self::assertFalse( $output['result']['success'] );
		self::assertSame( array(), $output['rows'] );
		self::assertSame( array(), $output['settings'] );
		self::assertSame( array(), $output['options'] );
	}

	public function test_saved_row_read_failure_restores_default_or_removes_new_default(): void {
		$output = $this->runCase( array( 'operation' => 'save', 'data' => $this->valid( array( 'id' => '1' ) ), 'failures' => array( 'getById' => array( 3 => 'false' ) ) ) );
		self::assertFalse( $output['result']['success'] );
		self::assertSame( 'Default warehouse', $output['rows'][0]['name'] );
		self::assertSame( array(), $output['settings'] );
		self::assertSame( array(), $output['options'] );
		$output = $this->runCase( array( 'operation' => 'save', 'rows' => array(), 'data' => $this->valid( array( 'id' => 'new' ) ), 'failures' => array( 'getById' => array( 1 => 'false' ) ) ) );
		self::assertFalse( $output['result']['success'] );
		self::assertSame( array(), $output['rows'] );
		self::assertSame( array(), $output['settings'] );
		self::assertSame( array(), $output['options'] );
	}

	public function test_seeding_only_occurs_for_an_empty_book_with_existing_store_data(): void {
		$output = $this->runCase( array( 'operation' => 'bootstrap', 'rows' => array() ) );
		self::assertSame( array(), $output['rows'] );
		self::assertSame( array(), $output['writes'] );
		$output = $this->runCase( array( 'operation' => 'bootstrap', 'rows' => array(), 'options' => array( 'woocommerce_store_address' => 'Store road', 'woocommerce_store_postcode' => '12345', 'woocommerce_default_country' => 'ID:JK' ) ) );
		self::assertSame( 'Store road', $output['rows'][0]['address'] );
		self::assertSame( 1, $output['rows'][0]['is_default'] );
		self::assertSame( 'ID:JK', $output['result']['locations'][0]['country_state'] );
		$output = $this->runCase( array( 'operation' => 'bootstrap' ) );
		self::assertSame( array(), $output['writes'] );
	}

	public function test_mutations_reject_permission_nonce_malformed_and_missing_ids_without_writes(): void {
		foreach ( array( 'save', 'delete', 'toggle' ) as $operation ) {
			foreach ( array( array( 'allowed' => false, 'data' => $this->valid() ), array( 'data' => $this->valid( array( 'nonce' => 'bad' ) ) ), array( 'data' => $this->valid( array( 'id' => '-2' ) ) ), array( 'data' => $this->valid( array( 'id' => '2x' ) ) ), array( 'data' => $this->valid( array( 'id' => '999' ) ) ), array( 'data' => $this->valid( array( 'id' => array( '2' ) ) ) ), array( 'data' => array( 'nonce' => 'valid' ) ) ) as $case ) {
				$output = $this->runCase( array_merge( array( 'operation' => $operation ), $case ) );
				self::assertFalse( $output['result']['success'], $operation );
				self::assertSame( array(), $output['writes'], $operation );
			}
		}
	}

	public function test_save_maps_canonical_form_and_default_mirrors_without_status_overwrite(): void {
		$output = $this->runCase( array( 'operation' => 'save', 'data' => $this->valid( array( 'id' => '1', 'is_active' => '0' ) ) ) );
		self::assertTrue( $output['result']['success'] );
		self::assertSame( 1, $output['rows'][0]['is_active'] );
		self::assertSame( 'ID', $output['rows'][0]['country'] );
		self::assertSame( 'JB', $output['rows'][0]['state'] );
		self::assertSame( 'New road', $output['options']['woocommerce_store_address'] );
		self::assertSame( 'Floor 3', $output['options']['woocommerce_store_address_2'] );
		self::assertSame( 'Bandung', $output['options']['woocommerce_store_city'] );
		self::assertSame( '12345', $output['options']['woocommerce_store_postcode'] );
		self::assertSame( 'ID:JB', $output['options']['woocommerce_default_country'] );
		self::assertSame( '081234', $output['options']['kiriof_wc_origin_phone'] );
		self::assertSame( '123', $output['options']['kiriof_wc_origin_area'] );
		self::assertSame( 'Updated warehouse', $output['mirrors'][0]['origin_name'] );
		self::assertSame( '-6', $output['mirrors'][0]['origin_latitude'] );
		self::assertSame( '106', $output['mirrors'][0]['origin_longitude'] );
		self::assertSame( 'Area', $output['mirrors'][0]['origin_sub_district_name'] );
		$output = $this->runCase( array( 'operation' => 'save', 'rows' => array(), 'data' => $this->valid( array( 'id' => 'new' ) ) ) );
		self::assertTrue( $output['result']['success'] );
		self::assertSame( 1, $output['rows'][0]['is_default'] );
		self::assertSame( 'ID:JB', $output['options']['woocommerce_default_country'] );
		self::assertCount( 1, $output['mirrors'] );
	}

	public function test_toggle_only_updates_status_and_default_is_protected(): void {
		$output = $this->runCase( array( 'operation' => 'toggle', 'data' => array( 'nonce' => 'valid', 'id' => '2', 'is_active' => '0', 'address' => 'Stale address' ) ) );
		self::assertTrue( $output['result']['success'] );
		self::assertSame( array( 'is_active' => 0 ), $output['writes'][0]['data'] );
		self::assertSame( 'Main road', $output['rows'][1]['address'] );
		$output = $this->runCase( array( 'operation' => 'save', 'data' => $this->valid( array( 'is_active' => '0' ) ) ) );
		self::assertSame( 1, $output['rows'][1]['is_active'] );
		self::assertSame( array(), $output['mirrors'] );
		self::assertSame( array(), $output['options'] );
		foreach ( array( 'delete', 'toggle' ) as $operation ) {
			$output = $this->runCase( array( 'operation' => $operation, 'data' => array( 'nonce' => 'valid', 'id' => '1', 'is_active' => '0' ) ) );
			self::assertFalse( $output['result']['success'] );
			self::assertSame( array(), $output['writes'] );
		}
	}

	public function test_create_limit_validation_and_default_mutations_are_guarded(): void {
		$bootstrap = $this->runCase( array( 'operation' => 'bootstrap' ) );
		$rows = array_fill( 0, 6, $bootstrap['rows'][1] );
		foreach ( $rows as $key => &$row ) { $row['id'] = $key + 1; } unset( $row );
		$output = $this->runCase( array( 'operation' => 'save', 'rows' => $rows, 'data' => $this->valid( array( 'id' => 'new' ) ) ) );
		self::assertSame( 409, $output['result']['data']['status'] );
		foreach ( array( array( 'lat' => '91' ), array( 'lng' => 'bad' ), array( 'district' => '1abc' ), array( 'is_default' => '1' ), array( 'address' => '' ) ) as $invalid ) {
			$output = $this->runCase( array( 'operation' => 'save', 'data' => $this->valid( $invalid ) ) );
			self::assertFalse( $output['result']['success'] );
			self::assertSame( array(), $output['writes'] );
		}
		$output = $this->runCase( array( 'operation' => 'save', 'data' => $this->valid( array( 'id' => 'new' ) ) ) );
		self::assertTrue( $output['result']['success'] );
		self::assertSame( 0, $output['writes'][0]['insert']['is_default'] );
		$output = $this->runCase( array( 'operation' => 'delete', 'data' => array( 'nonce' => 'valid', 'id' => '2' ) ) );
		self::assertTrue( $output['result']['success'] );
	}

	public function test_legacy_get_redirect_preserves_dialog_but_post_and_denied_requests_do_not_redirect(): void {
		$query = array( 'page' => 'wc-settings', 'tab' => 'kiriminaja_warehouses', 'kiriof_location' => '2' );
		$output = $this->runCase( array( 'operation' => 'legacy', 'query' => $query ) );
		self::assertSame( 'https://example.test/wp-admin/admin.php?page=kiriminaja-setting&section=warehouses&kiriof_location=2', $output['redirect'] );
		foreach ( array( array( 'method' => 'POST' ), array( 'allowed' => false ) ) as $extra ) {
			$output = $this->runCase( array_merge( array( 'operation' => 'legacy', 'query' => $query ), $extra ) );
			self::assertTrue( $output['result']['notRedirected'] );
		}
		$hooks = $this->runCase( array( 'operation' => 'register' ) )['result'];
		self::assertContains( 'wp_ajax_kiriof_toggle_shipment_location', $hooks );
		$controller = file_get_contents( PLUGIN_DIR . '/inc/Controllers/SettingController.php' );
		self::assertStringNotContainsString( "add_filter( 'woocommerce_settings_tabs_array'", $controller );
		self::assertStringContainsString( "'warehouses' === \$section", file_get_contents( PLUGIN_DIR . '/templates/setting/index.php' ) );
	}
}
