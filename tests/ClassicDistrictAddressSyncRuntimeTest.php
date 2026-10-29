<?php
use PHPUnit\Framework\TestCase;

final class ClassicDistrictAddressSyncRuntimeTest extends TestCase {
	private function runFixture( array $input ): array {
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/classic-district-address-sync-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_billing_only_setting_overrides_separate_flag_for_shipping_aliases(): void {
		$form = $this->form() + array( 'ship_to_different_address' => '1' );
		$result = $this->runFixture( array( 'form' => $form, 'mode' => 'review', 'billing_only' => true ) );
		$this->assertSame( 'Sleman', $result['unslashed']['s_city'] );
		$this->assertSame( 'YO', $result['unslashed']['s_state'] );
		$this->assertSame( '55581', $result['unslashed']['s_postcode'] );
	}

	public function test_absent_markers_or_country_do_not_infer_session_context(): void {
		foreach ( array( 'kiriof_billing_derived_address', 'billing_country' ) as $key ) {
			$form = $this->form();
			unset( $form[ $key ] );
			$result = $this->runFixture( array( 'form' => $form ) );
			$this->assertSame( $form, $result['data'] );
			$this->assertSame( array(), $result['calls'] );
		}
	}

	public function test_review_repairs_empty_or_missing_aliases_without_changing_populated_native_fields(): void {
		$form = array_replace( $this->form(), array( 'billing_city' => 'Client city', 'billing_state' => 'JK', 'billing_postcode' => '11111', 'custom' => array( 'keep' => "O'Neil \\ entrance" ) ) );
		foreach ( array( false, true ) as $missing ) {
			$post = $missing ? array() : array( 'city' => '', 'state' => ' ', 'postcode' => '', 's_city' => '', 's_state' => '', 's_postcode' => '' );
			$post['billing_city'] = 'Keep root native';
			$result = $this->runFixture( array( 'form' => $form, 'post' => $post, 'mode' => 'review' ) );
			foreach ( array( 'city' => 'Sleman', 'state' => 'YO', 'postcode' => '55581' ) as $field => $value ) {
				$this->assertSame( $value, $result['unslashed'][ $field ] );
				$this->assertSame( $value, $result['unslashed'][ 's_' . $field ] );
			}
			$this->assertSame( 'Keep root native', $result['unslashed']['billing_city'] );
			$this->assertSame( $form, $result['data'] );
			$this->assertSame( http_build_query( $form ), $result['unslashed']['post_data'] );
			$this->assertSame( array( '55581' ), $result['calls'] );
		}
	}

	public function test_review_alias_verification_preserves_nonempty_malformed_and_unmarked_aliases(): void {
		$form = array_replace( $this->form(), array( 'billing_city' => 'Client city', 'billing_state' => 'JK', 'billing_postcode' => '11111', 'kiriof_billing_derived_address' => 'city' ) );
		$post = array( 'city' => '', 'state' => '', 'postcode' => '', 's_city' => '', 's_state' => '', 's_postcode' => '' );
		$result = $this->runFixture( array( 'form' => $form, 'post' => $post, 'mode' => 'review' ) );
		$this->assertSame( 'Sleman', $result['unslashed']['city'] );
		$this->assertSame( 'Sleman', $result['unslashed']['s_city'] );
		foreach ( array( 'state', 'postcode', 's_state', 's_postcode' ) as $alias ) {
			$this->assertSame( '', $result['unslashed'][ $alias ] );
		}
		$post = array( 'city' => 'Manual', 'state' => array( '' ), 'postcode' => null, 's_city' => array( '' ), 's_state' => 0, 's_postcode' => 'Manual postcode' );
		$result = $this->runFixture( array( 'form' => $form, 'post' => $post, 'mode' => 'review' ) );
		$this->assertSame( $post + array( 'post_data' => http_build_query( $form ) ), $result['unslashed'] );
		$this->assertSame( array(), $result['calls'] );
	}

	public function test_review_populated_native_fields_do_not_bypass_alias_evidence_checks(): void {
		$form = array_replace( $this->form(), array( 'billing_city' => 'Client city', 'billing_state' => 'JK', 'billing_postcode' => '11111' ) );
		$post = array( 'city' => '', 'state' => '', 'postcode' => '', 's_city' => '', 's_state' => '', 's_postcode' => '' );
		foreach ( array(
			array( 'billing_country' => 'US' ), array( 'billing_country' => '' ),
			array( 'kiriof_billing_derived_address' => '' ), array( 'kiriof_billing_derived_address' => 'city,city' ),
			array( 'kiriof_destination_area' => '-1' ), array( 'kiriof_destination_area_name' => 'Bad label' ),
		) as $overrides ) {
			$candidate = array_replace( $form, $overrides );
			$result = $this->runFixture( array( 'form' => $candidate, 'post' => $post, 'mode' => 'review' ) );
			$this->assertSame( $candidate, $result['data'] );
			$this->assertSame( $post + array( 'post_data' => http_build_query( $candidate ) ), $result['unslashed'] );
			$this->assertSame( array(), $result['calls'] );
		}
		$result = $this->runFixture( array( 'form' => $form, 'post' => $post, 'mode' => 'review', 'rows' => array() ) );
		$this->assertSame( $post + array( 'post_data' => http_build_query( $form ) ), $result['unslashed'] );
		unset( $form['kiriof_billing_derived_address'] );
		$result = $this->runFixture( array( 'form' => $form, 'post' => $post, 'mode' => 'review' ) );
		$this->assertSame( $post + array( 'post_data' => http_build_query( $form ) ), $result['unslashed'] );
		$this->assertSame( array(), $result['calls'] );
	}

	public function test_review_aliases_verify_the_effective_billing_or_separate_shipping_scope(): void {
		foreach ( array( false, true ) as $separate ) {
			$form = $this->form() + $this->form( 'shipping' ) + array( 'ship_to_different_address' => $separate ? '1' : '0' );
			foreach ( array( 'billing', 'shipping' ) as $scope ) {
				foreach ( array( 'city', 'state', 'postcode' ) as $field ) {
					$form[ $scope . '_' . $field ] = 'Client value';
				}
			}
			$form['kiriof_shipping_destination_area'] = '31555';
			$post = array( 'city' => '', 'state' => '', 'postcode' => '', 's_city' => '', 's_state' => '', 's_postcode' => '' );
			$rows = array(
				array( 'id' => 31554, 'text' => 'Village, District, Sleman, DI Yogyakarta, 55581' ),
				array( 'id' => 31555, 'text' => 'Village, District, Jakarta, DKI Jakarta, 55581' ),
			);
			$result = $this->runFixture( array( 'form' => $form, 'post' => $post, 'mode' => 'review', 'rows' => $rows ) );
			$this->assertSame( 'Sleman', $result['unslashed']['city'] );
			$this->assertSame( 'YO', $result['unslashed']['state'] );
			$this->assertSame( $separate ? 'Jakarta' : 'Sleman', $result['unslashed']['s_city'] );
			$this->assertSame( $separate ? 'JK' : 'YO', $result['unslashed']['s_state'] );
			$this->assertSame( '55581', $result['unslashed']['postcode'] );
			$this->assertSame( '55581', $result['unslashed']['s_postcode'] );
			$this->assertSame( $form, $result['data'] );
			$result = $this->runFixture( array( 'form' => $form, 'post' => $post, 'mode' => 'review', 'rows' => $rows, 'billing_only' => true ) );
			$this->assertSame( 'Sleman', $result['unslashed']['s_city'] );
			$this->assertSame( 'YO', $result['unslashed']['s_state'] );
			$form['billing_country'] = 'US';
			$result = $this->runFixture( array( 'form' => $form, 'post' => $post, 'mode' => 'review', 'rows' => $rows ) );
			$this->assertSame( '', $result['unslashed']['city'] );
			$this->assertSame( $separate ? 'Jakarta' : '', $result['unslashed']['s_city'] );
		}
	}

	public function test_unknown_province_does_not_invent_a_woocommerce_state_code(): void {
		$result = $this->runFixture( array( 'form' => $this->form(), 'states' => array( 'JK' => 'DKI Jakarta' ) ) );
		$this->assertSame( '', $result['data']['billing_state'] );
		$this->assertSame( 'Sleman', $result['data']['billing_city'] );
	}

	public function test_canonical_province_aliases_match_woocommerce_names_without_fuzzy_matching(): void {
		foreach ( array( 'DI Yogyakarta' => 'YO', 'D.I. Yogyakarta' => 'YO', 'DKI Jakarta' => 'JK', 'Yogyakarta' => '' ) as $province => $code ) {
			$result = $this->runFixture(
				array(
					'form' => $this->form(),
					'states' => array( 'YO' => 'Daerah Istimewa Yogyakarta', 'JK' => 'Daerah Khusus Ibukota Jakarta' ),
					'rows' => array( array( 'id' => 31554, 'text' => 'Sari Harjo, Ngaglik, Sleman, ' . $province . ', 55581' ) ),
				)
			);
			$this->assertSame( $code, $result['data']['billing_state'] );
		}
	}

	private function form( string $scope = 'billing' ): array {
		$key = 'shipping' === $scope ? 'kiriof_shipping_destination_area' : 'kiriof_destination_area';
		return array( 'kiriof_' . $scope . '_derived_address' => 'city,state,postcode', $scope . '_country' => 'ID', $scope . '_city' => '', $scope . '_state' => '', $scope . '_postcode' => '', $key => '31554', $key . '_name' => 'Client village, Client district, Fake city, Fake state, 55581' );
	}

	public function test_only_canonical_values_are_used_and_lookups_are_memoized(): void {
		$result = $this->runFixture( array( 'form' => $this->form() ) );
		$this->assertSame( 'Sleman', $result['data']['billing_city'] );
		$this->assertSame( 'YO', $result['data']['billing_state'] );
		$this->assertSame( '55581', $result['data']['billing_postcode'] );
		$this->assertSame( array( '55581' ), $result['calls'] );
	}

	public function test_international_missing_country_manual_and_malformed_inputs_do_not_lookup(): void {
		foreach ( array(
			array( 'billing_country' => 'US' ), array( 'billing_country' => '' ), array( 'billing_country' => array( 'ID' ) ),
			array( 'kiriof_billing_derived_address' => '' ), array( 'kiriof_billing_derived_address' => 'city, city' ),
			array( 'kiriof_billing_derived_address' => 'city,city' ), array( 'kiriof_billing_derived_address' => 'city,unknown' ),
			array( 'kiriof_billing_derived_address' => array( 'city' ) ), array( 'billing_city' => array( '' ) ),
			array( 'kiriof_destination_area' => '-1' ), array( 'kiriof_destination_area' => array( '31554' ) ),
			array( 'kiriof_destination_area_name' => 'Too, few, 55581' ), array( 'kiriof_destination_area_name' => array( '55581' ) ),
			array( 'billing_city' => 'Manual', 'billing_state' => 'JK', 'billing_postcode' => '11111' ),
		) as $overrides ) {
			$form = array_replace( $this->form(), $overrides );
			$result = $this->runFixture( array( 'form' => $form ) );
			$this->assertSame( $form, $result['data'] );
			$this->assertSame( array(), $result['calls'] );
		}
	}

	public function test_subset_marker_never_overwrites_manual_or_unmarked_fields(): void {
		$form = array_replace( $this->form(), array( 'kiriof_billing_derived_address' => 'city,postcode', 'billing_city' => 'Manual' ) );
		$result = $this->runFixture( array( 'form' => $form ) );
		$this->assertSame( 'Manual', $result['data']['billing_city'] );
		$this->assertSame( '', $result['data']['billing_state'] );
		$this->assertSame( '55581', $result['data']['billing_postcode'] );
	}

	public function test_missing_native_fields_and_encoded_state_names_are_supported(): void {
		$form = $this->form();
		unset( $form['billing_city'] );
		$result = $this->runFixture( array( 'form' => $form, 'states' => array( 'YO' => '  di Yogyakart&#97;  ' ) ) );
		$this->assertSame( 'Sleman', $result['data']['billing_city'] );
		$this->assertSame( 'YO', $result['data']['billing_state'] );
	}

	public function test_nonunique_wrong_id_wrong_postcode_and_unavailable_results_fail_closed(): void {
		$row = array( 'id' => 31554, 'text' => 'Sari Harjo, Ngaglik, Sleman, DI Yogyakarta, 55581' );
		foreach ( array(
			array( 'rows' => array() ), array( 'rows' => array( $row, $row ) ),
			array( 'rows' => array( array_replace( $row, array( 'id' => 1 ) ) ) ),
			array( 'rows' => array( array_replace( $row, array( 'text' => 'Sari Harjo, Ngaglik, Sleman, DI Yogyakarta, 11111' ) ) ) ),
			array( 'rows' => array( array_replace( $row, array( 'text' => 'Bad, format, 55581' ) ) ) ),
			array( 'rows' => array( array_replace( $row, array( 'zip_code' => '11111' ) ) ) ),
			array( 'status' => 500 ), array( 'throws' => true ),
		) as $options ) {
			$result = $this->runFixture( $options + array( 'form' => $this->form() ) );
			$this->assertSame( $this->form(), $result['data'] );
			$this->assertCount( 1, $result['calls'] );
		}
	}

	public function test_review_repairs_native_and_effective_ajax_aliases_with_slashes_preserved(): void {
		foreach ( array( false, true ) as $separate ) {
			$form = $this->form() + $this->form( 'shipping' ) + array( 'ship_to_different_address' => $separate ? '1' : '0', 'order_comments' => "O'Neil \\ entrance", 'custom' => array( 'keep' => 'value' ) );
			$result = $this->runFixture( array( 'form' => $form, 'mode' => 'review', 'rows' => array( array( 'id' => 31554, 'text' => "Village, District, O'City, DI Yogyakarta, 55581" ) ) ) );
			foreach ( array( 'billing_city', 'shipping_city', 'city', 's_city' ) as $key ) { $this->assertSame( "O'City", $result['unslashed'][ $key ] ); }
			$this->assertSame( "O\\'City", $result['post']['city'] );
			$this->assertSame( $form['order_comments'], $result['data']['order_comments'] );
			$this->assertSame( $form['custom'], $result['data']['custom'] );
			$this->assertSame( 5, $result['hooks']['woocommerce_checkout_update_order_review'] );
		}
	}

	public function test_shipping_repairs_cannot_change_billing_aliases_or_inactive_shipping_aliases(): void {
		foreach ( array( false, true ) as $separate ) {
			$form = $this->form( 'shipping' ) + array( 'ship_to_different_address' => $separate ? '1' : '0' );
			$result = $this->runFixture( array( 'form' => $form, 'mode' => 'review', 'post' => array( 'city' => 'Manual billing', 's_city' => 'Original shipping' ) ) );
			$this->assertSame( 'Manual billing', $result['unslashed']['city'] );
			$this->assertSame( $separate ? 'Sleman' : 'Original shipping', $result['unslashed']['s_city'] );
		}
	}

	public function test_final_checkout_process_and_filter_handle_unregistered_fields(): void {
		$result = $this->runFixture( array( 'mode' => 'checkout', 'form' => $this->form(), 'posted' => array( 'billing_city' => '', 'billing_state' => '', 'billing_postcode' => '', 'order_comments' => 'Keep' ) ) );
		$this->assertSame( 'Sleman', $result['unslashed']['billing_city'] );
		$this->assertSame( 'Sleman', $result['data']['billing_city'] );
		$this->assertSame( 'YO', $result['data']['billing_state'] );
		$this->assertSame( 'Keep', $result['data']['order_comments'] );
		$this->assertArrayNotHasKey( 'kiriof_billing_derived_address', $result['data'] );
		$this->assertCount( 1, $result['calls'] );
	}

	public function test_rest_unverified_filter_and_wrong_hooks_have_no_effect(): void {
		foreach ( array( array( 'rest' => true ), array( 'hook' => 'unverified' ) ) as $options ) {
			$result = $this->runFixture( $options + array( 'form' => $this->form(), 'mode' => 'review' ) );
			$this->assertSame( $this->form(), $result['data'] );
			$this->assertSame( array(), $result['calls'] );
		}
		$result = $this->runFixture( array( 'form' => $this->form(), 'mode' => 'checkout', 'skip_process' => true ) );
		$this->assertSame( $this->form(), $result['data'] );
		$this->assertSame( array(), $result['calls'] );
	}

	public function test_final_filter_repairs_shipping_copied_before_billing_builder_filters_are_repaired(): void {
		foreach ( array( false, true ) as $omitted ) {
			$posted = array( 'ship_to_different_address' => false );
			if ( ! $omitted ) {
				foreach ( array( 'billing', 'shipping' ) as $scope ) {
					foreach ( array( 'city', 'state', 'postcode' ) as $field ) {
						$posted[ $scope . '_' . $field ] = '';
					}
				}
			}
			$result = $this->runFixture( array( 'mode' => 'checkout', 'form' => $this->form(), 'posted' => $posted ) );
			foreach ( array( 'city' => 'Sleman', 'state' => 'YO', 'postcode' => '55581' ) as $field => $value ) {
				$this->assertSame( $value, $result['data'][ 'billing_' . $field ] );
				$this->assertSame( $value, $result['data'][ 'shipping_' . $field ] );
			}
		}
	}

	public function test_final_filter_preserves_nonempty_and_separate_shipping_values(): void {
		foreach ( array( false, true ) as $separate ) {
			$posted = array( 'ship_to_different_address' => $separate, 'billing_city' => '', 'shipping_city' => 'Manual shipping', 'shipping_state' => '' );
			$result = $this->runFixture( array( 'mode' => 'checkout', 'form' => $this->form(), 'posted' => $posted ) );
			$this->assertSame( 'Sleman', $result['data']['billing_city'] );
			$this->assertSame( 'Manual shipping', $result['data']['shipping_city'] );
			$this->assertSame( $separate ? '' : 'YO', $result['data']['shipping_state'] );
		}
	}

	public function test_inactive_shipping_marker_cannot_replace_repaired_billing_destination(): void {
		$form = $this->form() + $this->form( 'shipping' );
		$form['kiriof_shipping_destination_area'] = '31555';
		$rows = array(
			array( 'id' => 31554, 'text' => 'Sari Harjo, Ngaglik, Sleman, DI Yogyakarta, 55581' ),
			array( 'id' => 31555, 'text' => 'Village, District, Other city, DI Yogyakarta, 55581' ),
		);
		$result = $this->runFixture( array( 'mode' => 'checkout', 'form' => $form, 'rows' => $rows, 'posted' => array( 'ship_to_different_address' => false, 'billing_city' => '', 'shipping_city' => '' ) ) );
		$this->assertSame( 'Sleman', $result['data']['billing_city'] );
		$this->assertSame( 'Sleman', $result['data']['shipping_city'] );
	}
}
