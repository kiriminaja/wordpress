<?php
use PHPUnit\Framework\TestCase;

final class ForcedBillingAddressSyncRuntimeTest extends TestCase {
	private const LABEL = 'Sari Harjo, Ngaglik, Sleman, DI Yogyakarta, 55581';

	private function form(): array {
		return array(
			'_wfacp_post_id' => '123', 'ship_to_different_address' => '1',
			'billing_country' => 'ID', 'shipping_country' => 'ID',
			'billing_address_1' => "Jalan O'Neil 123", 'shipping_address_1' => "Jalan O'Neil 123",
			'billing_city' => '', 'billing_state' => '', 'billing_postcode' => '',
			'shipping_city' => 'Sleman', 'shipping_state' => 'YO', 'shipping_postcode' => '55581',
			'kiriof_shipping_destination_area' => '31554',
			'kiriof_shipping_destination_area_name' => 'Untrusted, Label, City, Province, 55581',
			'shipping_method' => array( 'kiriminaja-instant:7:gosend:instant' ),
		);
	}

	private function runFixture( array $input ): array {
		exec( kiriof_test_fixture_php_shell( array( __DIR__ . '/fixtures/classic-district-address-sync-runtime.php', json_encode( $input + array( 'billing_only' => true ), JSON_THROW_ON_ERROR ) ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	private function assertRecovered( array $data ): void {
		foreach ( array( 'billing_city' => 'Sleman', 'billing_state' => 'YO', 'billing_postcode' => '55581', 'kiriof_destination_area' => '31554', 'kiriof_destination_area_name' => self::LABEL ) as $key => $value ) {
			$this->assertSame( $value, $data[ $key ] ?? null, $key );
		}
	}

	public function test_normalize_review_and_final_recover_only_verified_missing_billing_fields(): void {
		foreach ( array( 'absent', 'empty', 'marked', 'removed' ) as $marker ) {
			$form = $this->form();
			if ( 'absent' !== $marker ) {
				$form['kiriof_billing_derived_address'] = 'marked' === $marker ? 'city,state,postcode' : '';
			}
			if ( 'removed' === $marker ) {
				unset( $form['billing_city'], $form['billing_state'], $form['billing_postcode'] );
			}
			foreach ( array( '', 'review', 'checkout' ) as $mode ) {
				$input = array( 'form' => $form, 'mode' => $mode );
				if ( 'review' === $mode ) {
					$input['post'] = array( 'country' => 'ID', 'address' => $form['billing_address_1'], 'address_2' => '', 'city' => '', 'state' => '', 'postcode' => '' );
				}
				$result = $this->runFixture( $input );
				$this->assertRecovered( $result['data'] );
				$this->assertSame( array( '55581' ), $result['calls'] );
				if ( 'review' === $mode ) {
					$this->assertSame( 'Sleman', $result['unslashed']['city'] );
					$this->assertSame( 'YO', $result['unslashed']['s_state'] );
				}
				if ( 'checkout' === $mode ) {
					$this->assertRecovered( $result['unslashed'] );
				}
			}
		}
	}

	public function test_final_missing_fieldset_and_copied_shipping_do_not_hide_original_selection(): void {
		foreach ( array( array(), array( 'billing_city' => '', 'billing_state' => '', 'billing_postcode' => '', 'shipping_city' => '', 'shipping_state' => '', 'shipping_postcode' => '' ) ) as $posted ) {
			$result = $this->runFixture( array( 'mode' => 'checkout', 'form' => $this->form(), 'posted' => $posted ) );
			$this->assertRecovered( $result['data'] );
			$this->assertSame( 'Sleman', $result['data']['shipping_city'] );
		}
	}

	public function test_conflicts_and_malformed_fields_never_enable_forced_recovery_even_with_shared_flag(): void {
		foreach ( array(
			array( 'billing_address_1' => 'Different street' ), array( 'billing_address_1' => '' ),
			array( 'billing_address_2' => 'Unit 2' ), array( 'shipping_address_2' => 'Unit 2' ),
			array( 'billing_country' => 'US' ), array( 'shipping_country' => 'US' ),
			array( 'billing_city' => 'Other' ), array( 'billing_state' => 'JK' ), array( 'billing_postcode' => '11111' ),
			array( 'billing_city' => array( '' ) ), array( 'billing_state' => null ), array( 'billing_address_2' => array() ),
			array( 'kiriof_destination_area' => '999' ), array( 'kiriof_destination_area' => 'invalid' ), array( 'kiriof_destination_area' => array() ),
			array( 'kiriof_destination_area_name' => 'Conflicting label' ), array( 'kiriof_destination_area_name' => array() ),
			array( 'shipping_city' => 'Other' ), array( 'shipping_state' => 'JK' ),
			array( 'shipping_method' => array( 'local_pickup:1' ) ), array( 'shipping_method' => array( 'pickup_location:1' ) ),
			array( 'shipping_method' => 'local_pickup:1' ), array( '_wfacp_post_id' => 'bad' ),
		) as $override ) {
			$form = array_replace( $this->form(), array( 'wfacp_billing_same_as_shipping' => '0' ), $override );
			foreach ( array( '', 'review', 'checkout' ) as $mode ) {
				// URL-encoded review forms cannot represent null; PHP omits that key.
				if ( 'review' === $mode && ( in_array( null, $override, true ) || in_array( array(), $override, true ) ) ) {
					continue;
				}
				$result = $this->runFixture( array( 'form' => $form, 'mode' => $mode, 'post' => $form ) );
				$this->assertSame( $form['billing_postcode'], $result['data']['billing_postcode'], json_encode( array( $override, $mode ) ) );
				$this->assertSame( $form['kiriof_destination_area'] ?? null, $result['data']['kiriof_destination_area'] ?? null );
			}
		}
	}

	public function test_original_review_root_alias_conflicts_block_form_and_alias_recovery(): void {
		foreach ( array( 'country' => 'US', 'address' => 'Different street', 'address_2' => 'Unit 2', 'city' => 'Other', 'state' => 'JK', 'postcode' => '11111', 's_city' => 'Other', 's_address' => 'Different street', 's_country' => 'US', 'state_malformed' => array( '' ) ) as $alias => $value ) {
			$alias = 'state_malformed' === $alias ? 'state' : $alias;
			$post = array( $alias => $value );
			$result = $this->runFixture( array( 'form' => $this->form(), 'mode' => 'review', 'post' => $post ) );
			$this->assertSame( '', $result['data']['billing_city'] );
			$this->assertArrayNotHasKey( 'kiriof_destination_area', $result['data'] );
			$this->assertSame( $value, $result['unslashed'][ $alias ] );
		}
	}

	public function test_final_synthetic_blank_context_does_not_mask_original_or_effective_conflicts(): void {
		foreach ( array( 'billing_city' => 'Other', 'billing_state' => 'JK', 'billing_postcode' => '11111', 'kiriof_destination_area' => '999', 'kiriof_destination_area_name' => 'Bad' ) as $key => $value ) {
			$form = array_replace( $this->form(), array( $key => $value, 'kiriof_billing_derived_address' => 'city,state,postcode' ) );
			$result = $this->runFixture( array( 'form' => $form, 'mode' => 'checkout', 'posted' => array() ) );
			$this->assertArrayNotHasKey( 'billing_city', $result['data'] );
			$this->assertArrayNotHasKey( 'kiriof_destination_area', $result['data'] );
		}
		foreach ( array( 'billing_address_1' => 'Different', 'billing_address_2' => 'Unit', 'billing_country' => 'US', 'billing_city' => 'Other', 'billing_state' => array(), 'kiriof_destination_area' => '999' ) as $key => $value ) {
			$result = $this->runFixture( array( 'form' => $this->form(), 'mode' => 'checkout', 'posted' => array( $key => $value ) ) );
			$this->assertArrayNotHasKey( 'billing_postcode', $result['data'] );
			$this->assertSame( $value, $result['data'][ $key ] );
		}
	}

	public function test_lookup_failures_ambiguity_and_invalid_hierarchy_never_recover(): void {
		$row = array( 'id' => 31554, 'text' => self::LABEL, 'zip_code' => '55581' );
		foreach ( array(
			array( 'rows' => array() ), array( 'rows' => array( $row, $row ) ), array( 'throws' => true ), array( 'status' => 500 ),
			array( 'rows' => array( array_replace( $row, array( 'id' => 999 ) ) ) ),
			array( 'rows' => array( array_replace( $row, array( 'text' => 'Bad label' ) ) ) ),
			array( 'rows' => array( array_replace( $row, array( 'zip_code' => '11111' ) ) ) ),
			array( 'states' => array() ),
		) as $input ) {
			foreach ( array( '', 'review', 'checkout' ) as $mode ) {
				$result = $this->runFixture( $input + array( 'form' => $this->form(), 'mode' => $mode ) );
				$this->assertSame( '', $result['data']['billing_city'] );
				$this->assertArrayNotHasKey( 'kiriof_destination_area', $result['data'] );
			}
		}
	}

	public function test_nonforced_mode_and_unverified_checkout_do_not_gain_address_recovery(): void {
		foreach ( array( array( 'billing_only' => false ), array( 'mode' => 'checkout', 'skip_process' => true ) ) as $input ) {
			$result = $this->runFixture( $input + array( 'form' => $this->form() ) );
			$this->assertSame( '', $result['data']['billing_city'] );
			$this->assertArrayNotHasKey( 'kiriof_destination_area', $result['data'] );
		}
	}

	public function test_forced_single_address_does_not_require_optional_billing_toggle_fields(): void {
		$form = $this->form();
		unset( $form['ship_to_different_address'] );
		foreach ( array( '', 'review', 'checkout' ) as $mode ) {
			$result = $this->runFixture( array( 'form' => $form, 'mode' => $mode ) );
			$this->assertRecovered( $result['data'] );
		}
	}

	public function test_complete_serialized_address_repairs_missing_review_aliases(): void {
		$form = array_replace( $this->form(), array(
			'billing_city' => 'Sleman', 'billing_state' => 'YO', 'billing_postcode' => '55581',
			'kiriof_destination_area' => '31554', 'kiriof_destination_area_name' => self::LABEL,
		) );
		foreach ( array( array(), array( 'city' => '', 'state' => '', 'postcode' => '', 's_city' => '', 's_state' => '', 's_postcode' => '' ) ) as $post ) {
			$result = $this->runFixture( array( 'form' => $form, 'mode' => 'review', 'post' => $post ) );
			foreach ( array( 'city' => 'Sleman', 'state' => 'YO', 'postcode' => '55581' ) as $field => $value ) {
				$this->assertSame( $value, $result['unslashed'][ $field ] ?? null );
				$this->assertSame( $value, $result['unslashed'][ 's_' . $field ] ?? null );
			}
		}
	}

	public function test_final_already_repaired_billing_still_completes_missing_shipping_fields(): void {
		$billing = array( 'billing_city' => 'Sleman', 'billing_state' => 'YO', 'billing_postcode' => '55581', 'kiriof_destination_area' => '31554', 'kiriof_destination_area_name' => self::LABEL );
		foreach ( array( $this->form(), array_replace( $this->form(), $billing ) ) as $form ) {
			$result = $this->runFixture( array( 'mode' => 'checkout', 'form' => $form, 'posted' => $billing + array( 'shipping_city' => '', 'shipping_state' => '', 'shipping_postcode' => '' ) ) );
			$this->assertRecovered( $result['data'] );
			foreach ( array( 'city' => 'Sleman', 'state' => 'YO', 'postcode' => '55581' ) as $field => $value ) {
				$this->assertSame( $value, $result['data'][ 'shipping_' . $field ] );
			}
		}
	}
}
