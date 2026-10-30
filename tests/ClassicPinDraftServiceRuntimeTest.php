<?php
use PHPUnit\Framework\TestCase;

final class ClassicPinDraftServiceRuntimeTest extends TestCase {
	private function runFixture( array $input ): array {
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/classic-pin-draft-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_foreign_null_clear_succeeds_and_a_later_indonesian_save_can_promote(): void {
		$clear = array( 'address_scope' => 'billing', 'country' => 'US', 'coordinates' => null );
		$session = array(
			'kiriof_classic_pin_drafts' => array( 'billing' => $this->save()['coordinates'] ),
			'kiriof_classic_pin_scope' => 'billing',
			'kiriof_buyer_destination' => array( 'version' => 2 ),
			'kiriof_buyer_destination_coordinates' => $this->save()['coordinates'],
			'kiriof_instant_checkout_quotes' => array( 'old' ),
			'kiriof_instant_checkout_status' => array( 'old' ),
		);
		$result = $this->runFixture( array( 'saves' => array( $clear ), 'session' => $session ) );
		$this->assertSame( array(), $result['errors'] );
		foreach ( array( 'kiriof_classic_pin_drafts', 'kiriof_classic_pin_scope', 'kiriof_buyer_destination', 'kiriof_buyer_destination_coordinates' ) as $key ) {
			$this->assertNull( $result['session'][ $key ], $key );
		}
		$this->assertSame( array(), $result['session']['kiriof_instant_checkout_quotes'] );
		$this->assertSame( array(), $result['session']['kiriof_instant_checkout_status'] );
		$this->assertSame( array(), $result['calls'] );
		$result = $this->runFixture( array( 'saves' => array( $clear, $this->save() ), 'session' => $session, 'form' => $this->form() ) );
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( '31554', $result['session']['kiriof_buyer_destination']['district_id'] );
	}

	public function test_foreign_nonnull_and_malformed_or_omitted_countries_are_rejected(): void {
		foreach ( array( 'US', '', 'us', 'USA', ' US', null, false, array() ) as $country ) {
			$save = array_replace( $this->save(), array( 'country' => $country ) );
			foreach ( array( $save, array_replace( $save, array( 'coordinates' => null ) ) ) as $request ) {
				if ( 'US' === $country && null === $request['coordinates'] ) {
					continue;
				}
				$result = $this->runFixture( array( 'saves' => array( $this->save(), $request ) ) );
				$this->assertSame( array( 4 ), $result['errors'] );
				$this->assertNull( $result['session']['kiriof_classic_pin_drafts'] );
			}
		}
		foreach ( array( 'country', 'coordinates' ) as $missing ) {
			$save = array( 'address_scope' => 'billing', 'country' => 'US', 'coordinates' => null );
			unset( $save[ $missing ] );
			$this->assertSame( array( 4 ), $this->runFixture( array( 'saves' => array( $save ) ) )['errors'] );
		}
	}

	public function test_review_rebinds_the_same_fixed_precision_point_to_a_new_postcode_without_legacy_history(): void {
		$save = array_replace( $this->save(), array( 'coordinates' => array( 'latitude' => '-7.7500000', 'longitude' => '110.4000000' ) ) );
		$changed = array_replace( $this->form(), array( 'kiriof_destination_area' => '31555', 'billing_postcode' => '55582' ) );
		$result = $this->runFixture( array(
			'saves' => array( $save ),
			'reviews' => array( $this->form(), $changed ),
			'session' => array( 'destination_id' => 1, 'shipping_destination_id' => 2 ),
			'rows_by_postcode' => array( '55582' => array( array( 'id' => 31555, 'text' => 'New Village, New District, Sleman, DI Yogyakarta, 55582' ) ) ),
		) );
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( array( '55581', '55582' ), $result['calls'] );
		foreach ( $result['reviews'] as $review ) {
			$this->assertSame( 2, $review['kiriof_buyer_destination']['version'] );
			$this->assertSame( '-7.75', $review['kiriof_buyer_destination']['destination_latitude'] );
			$this->assertSame( '110.4', $review['kiriof_buyer_destination']['destination_longitude'] );
			$this->assertSame( $review['kiriof_classic_pin_drafts']['billing'], $review['kiriof_buyer_destination_coordinates'] );
			$this->assertSame( 1, $review['destination_id'] );
			$this->assertSame( 2, $review['shipping_destination_id'] );
		}
		$destination = $result['session']['kiriof_buyer_destination'];
		$this->assertSame( '31555', $destination['district_id'] );
		$this->assertSame( '55582', $destination['postcode'] );
		$this->assertSame( '55582', $destination['shipping_address']['postcode'] );
		$this->assertSame( 'New Village, New District, Sleman, DI Yogyakarta, 55582', $destination['district_label'] );
		parse_str( $result['post']['post_data'], $post );
		$this->assertSame( $destination, json_decode( $post['kiriof_buyer_destination_snapshot'], true, 512, JSON_THROW_ON_ERROR ) );
	}

	private function save( string $scope = 'billing' ): array {
		return array( 'address_scope' => $scope, 'country' => 'ID', 'coordinates' => array( 'latitude' => '-7.75', 'longitude' => '110.4' ) );
	}

	private function form( string $scope = 'billing' ): array {
		$key = 'shipping' === $scope ? 'kiriof_shipping_destination_area' : 'kiriof_destination_area';
		return array( $scope . '_country' => 'ID', $scope . '_postcode' => '55581', $scope . '_city' => 'Sleman', $scope . '_state' => 'YO', $scope . '_address_1' => "O'Neil Street", $scope . '_address_2' => '', $key => '31554', $key . '_name' => 'Forged client label', 'ship_to_different_address' => 'shipping' === $scope ? '1' : '0' );
	}

	public function test_coordinates_save_without_any_address_district_lookup_or_quote(): void {
		$result = $this->runFixture( array( 'saves' => array( $this->save() ) ) );
		$this->assertSame( array( 'billing' => $this->save()['coordinates'] ), $result['session']['kiriof_classic_pin_drafts'] );
		$this->assertArrayNotHasKey( 'kiriof_buyer_destination', $result['session'] );
		$this->assertSame( array(), $result['calls'] );
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( 8, $result['hooks']['woocommerce_checkout_update_order_review'] );
	}

	public function test_invalid_coordinates_country_scope_and_virtual_cart_cannot_create_a_pin(): void {
		foreach ( array( array( 'latitude' => 'NaN', 'longitude' => '110' ), array( 'latitude' => '91', 'longitude' => '110' ), array( 'latitude' => '', 'longitude' => '' ), array( 'latitude' => true, 'longitude' => '110' ), array( 'latitude' => '1', 'longitude' => array() ), array( 'latitude' => '1', 'longitude' => '181' ) ) as $coordinates ) {
			$result = $this->runFixture( array( 'saves' => array( array_replace( $this->save(), array( 'coordinates' => $coordinates ) ) ) ) );
			$this->assertSame( array( 5 ), $result['errors'] );
			$this->assertArrayNotHasKey( 'kiriof_classic_pin_drafts', $result['session'] );
		}
		foreach ( array( array( 'address_scope' => 'other' ), array( 'country' => 'US' ) ) as $override ) {
			$result = $this->runFixture( array( 'saves' => array( array_replace( $this->save(), $override ) ) ) );
			$this->assertCount( 1, $result['errors'] );
		}
		$result = $this->runFixture( array( 'saves' => array( $this->save() ), 'virtual' => true ) );
		$this->assertSame( array( 10 ), $result['errors'] );
	}

	public function test_current_form_promotes_with_canonical_label_not_stale_legacy_district(): void {
		foreach ( array( 'review', 'final' ) as $mode ) {
			$result = $this->runFixture( array( 'saves' => array( $this->save() ), 'form' => $this->form(), 'mode' => $mode, 'session' => array( 'destination_id' => 1 ), 'repeat' => 'review' === $mode ) );
			$destination = $result['session']['kiriof_buyer_destination'];
			$this->assertSame( 2, $destination['version'] );
			$this->assertSame( '31554', $destination['district_id'] );
			$this->assertSame( 'Sari Harjo, Ngaglik, Sleman, DI Yogyakarta, 55581', $destination['district_label'] );
			$this->assertSame( "O'Neil Street", $destination['shipping_address']['address_1'] );
			$this->assertNotContains( 'kiriof_instant_checkout_quotes', $result['repeat_writes'] );
		}
	}

	public function test_incomplete_explicit_or_unverified_form_never_uses_history(): void {
		foreach ( array( 'billing_country', 'billing_city', 'billing_state', 'billing_postcode', 'billing_address_1', 'kiriof_destination_area' ) as $key ) {
			$form = $this->form();
			unset( $form[ $key ] );
			$result = $this->runFixture( array( 'saves' => array( $this->save() ), 'form' => $form, 'session' => array( 'destination_id' => 31554 ) ) );
			$this->assertNull( $result['session']['kiriof_buyer_destination'] ?? null );
		}
		foreach ( array( array( 'rest' => true ), array( 'hook' => 'unverified' ), array( 'mode' => 'final', 'skip_process' => true ) ) as $option ) {
			$result = $this->runFixture( $option + array( 'saves' => array( $this->save() ), 'form' => $this->form() ) );
			$this->assertArrayNotHasKey( 'kiriof_buyer_destination', $result['session'] );
		}
	}

	public function test_lookup_failure_unknown_duplicate_or_wrong_postcode_district_remains_draft_only(): void {
		$row = array( 'id' => 31554, 'text' => 'Village, District, Sleman, DI Yogyakarta, 55581' );
		foreach ( array( array( 'throws' => true ), array( 'rows' => array() ), array( 'rows' => array( $row, $row ) ), array( 'rows' => array( array_replace( $row, array( 'text' => 'Village, District, City, State, 11111' ) ) ) ) ) as $option ) {
			$result = $this->runFixture( $option + array( 'saves' => array( $this->save() ), 'form' => $this->form() ) );
			$this->assertNull( $result['session']['kiriof_buyer_destination'] ?? null );
			$this->assertSame( $this->save()['coordinates'], $result['session']['kiriof_classic_pin_drafts']['billing'] );
		}
	}

	public function test_clear_foreign_country_and_scope_switch_cannot_resurrect_a_pin(): void {
		$result = $this->runFixture( array( 'saves' => array( $this->save(), array_replace( $this->save(), array( 'coordinates' => null ) ) ), 'form' => $this->form() ) );
		$this->assertNull( $result['session']['kiriof_buyer_destination'] ?? null );
		$result = $this->runFixture( array( 'saves' => array( $this->save() ), 'form' => array_replace( $this->form(), array( 'billing_country' => 'US' ) ) ) );
		$this->assertNull( $result['session']['kiriof_classic_pin_drafts'] );
		$result = $this->runFixture( array( 'saves' => array( $this->save(), $this->save( 'shipping' ) ), 'form' => $this->form( 'shipping' ) ) );
		$this->assertArrayNotHasKey( 'billing', $result['session']['kiriof_classic_pin_drafts'] );
		$this->assertSame( 'shipping', $result['session']['kiriof_classic_pin_scope'] );
		$this->assertSame( 2, $result['session']['kiriof_buyer_destination']['version'] );
	}

	public function test_switching_away_and_back_discards_the_previous_scope_draft(): void {
		$result = $this->runFixture( array( 'saves' => array( $this->save() ), 'reviews' => array( $this->form(), $this->form( 'shipping' ), $this->form() ) ) );
		$this->assertSame( 2, $result['reviews'][0]['kiriof_buyer_destination']['version'] );
		foreach ( array( 1, 2 ) as $round ) {
			$this->assertNull( $result['reviews'][ $round ]['kiriof_buyer_destination'] );
			$this->assertNull( $result['reviews'][ $round ]['kiriof_buyer_destination_coordinates'] );
			$this->assertSame( array(), $result['reviews'][ $round ]['kiriof_classic_pin_drafts'] );
		}
	}

	public function test_cleared_and_foreign_drafts_remove_an_older_submitted_snapshot(): void {
		foreach ( array( 'review', 'final' ) as $mode ) {
			foreach ( array( false, true ) as $foreign ) {
				$form = $this->form() + array( 'kiriof_buyer_destination_snapshot' => '{"version":2}' );
				if ( $foreign ) { $form['billing_country'] = 'US'; }
				$saves = array( $this->save() );
				if ( ! $foreign ) { $saves[] = array_replace( $this->save(), array( 'coordinates' => null ) ); }
				$result = $this->runFixture( array( 'saves' => $saves, 'form' => $form, 'mode' => $mode ) );
				$post = $result['post'];
				if ( 'review' === $mode ) { parse_str( $post['post_data'], $post ); }
				$this->assertArrayNotHasKey( 'kiriof_buyer_destination_snapshot', $post, $mode . ( $foreign ? ' foreign' : ' clear' ) );
				$this->assertNull( $result['session']['kiriof_buyer_destination'] ?? null );
			}
		}
	}
}
