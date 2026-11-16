<?php
use PHPUnit\Framework\TestCase;

final class ForcedBillingRecipientSyncRuntimeTest extends TestCase {
	private function runFixture( array $input ): array {
		exec( kiriof_test_fixture_php_shell( array( __DIR__ . '/fixtures/classic-checkout-recipient-sync-runtime.php', json_encode( $input, JSON_THROW_ON_ERROR ) ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	private function form(): array {
		return array(
			'_wfacp_post_id' => '123',
			'billing_country' => 'ID',
			'shipping_country' => 'ID',
			'billing_first_name' => 'Buyer',
			'billing_last_name' => "O'Neil",
			'billing_phone' => '81229917900',
			'kiriof_funnelkit_phone_context' => '{"billing":{"value":"81229917900","iso2":"id","dialCode":"62"}}',
		);
	}

	public function test_current_and_legacy_widget_metadata_normalize_only_shipping_identity(): void {
		$current = $this->form();
		$legacy = $current;
		unset( $legacy['kiriof_funnelkit_phone_context'] );
		$legacy['wfacp_input_phone_field'] = '{"billing":{"code":"62","number":"81229917900","hidden":"no"}}';
		foreach ( array( $current, $legacy ) as $form ) {
			$result = $this->runFixture( array( 'billing_only' => true, 'forms' => array( $form, $form ), 'final' => $form ) );
			$this->assertSame( '6281229917900', $result['steps'][0]['recipient']['phone'] );
			$this->assertSame( '6281229917900', $result['steps'][1]['customer']['shipping_phone'] );
			$this->assertSame( array(), $result['steps'][1]['writes'] );
			$this->assertSame( '81229917900', $result['steps'][1]['customer']['billing_phone'] );
			$this->assertSame( "O'Neil", $result['steps'][1]['customer']['billing_last_name'] );
			$this->assertSame( '6281229917900', $result['posted']['shipping_phone'] );
			$this->assertSame( '81229917900', $result['posted']['billing_phone'] );
			$this->assertSame( "O'Neil", $result['posted']['billing_last_name'] );
			$this->assertSame( array( 'kiriminaja-instant:11:gosend:instant' ), $result['rates'] );
		}
	}

	public function test_prefixed_values_survive_unavailable_widget_metadata(): void {
		foreach ( array( '081229917900' => '081229917900', '+6281229917900' => '6281229917900' ) as $raw => $expected ) {
			foreach ( array( '{}', '{"billing":{"code":"","number":"","hidden":""}}' ) as $metadata ) {
				$form = $this->form();
				unset( $form['kiriof_funnelkit_phone_context'] );
				$form['billing_phone'] = $raw;
				$form['wfacp_input_phone_field'] = $metadata;
				$result = $this->runFixture( array( 'billing_only' => true, 'forms' => array( $form ), 'final' => $form ) );
				$this->assertSame( $expected, $result['steps'][0]['recipient']['phone'] );
				$this->assertSame( $expected, $result['posted']['shipping_phone'] );
				$this->assertSame( $raw, $result['steps'][0]['customer']['billing_phone'] );
			}
		}
	}

	public function test_invalid_current_context_clears_shipping_without_billing_fallback(): void {
		foreach ( array(
			array( 'billing_phone' => '' ),
			array( 'billing_phone' => '81229917901' ),
			array( 'kiriof_funnelkit_phone_context' => '{' ),
			array( 'kiriof_funnelkit_phone_context' => array( 'billing' => 'invalid' ) ),
			array( 'kiriof_funnelkit_phone_context' => '[]' ),
			array( 'kiriof_funnelkit_phone_context' => '{"billing":{"value":"81229917900","iso2":"au","dialCode":"61"}}' ),
			array( 'kiriof_funnelkit_phone_context' => '{"billing":{"value":"81229917900","iso2":"id","dialCode":"1"}}' ),
		) as $override ) {
			$form = array_replace( $this->form(), $override );
			$result = $this->runFixture( array( 'billing_only' => true, 'forms' => array( $this->form(), $form ), 'final' => $form ) );
			$this->assertSame( '', $result['steps'][1]['customer']['shipping_phone'] );
			$this->assertSame( '', $result['steps'][1]['recipient']['phone'] );
			$this->assertSame( '', $result['posted']['shipping_phone'] );
			$this->assertSame( $form['billing_phone'], $result['steps'][1]['customer']['billing_phone'] );
			$this->assertSame( array(), $result['rates'] );
		}
	}

	public function test_invalid_legacy_metadata_does_not_fallback_to_prefixed_billing_phone(): void {
		foreach ( array( '{', '[]', '{"billing":{"code":"61","number":"81229917900","hidden":"no"}}', '{"billing":{"code":"62","number":"81229917901","hidden":"no"}}' ) as $metadata ) {
			$form = $this->form();
			unset( $form['kiriof_funnelkit_phone_context'] );
			$form['billing_phone'] = '081229917900';
			$form['wfacp_input_phone_field'] = $metadata;
			$result = $this->runFixture( array( 'billing_only' => true, 'forms' => array( $form ), 'final' => $form ) );
			$this->assertSame( '', $result['steps'][0]['recipient']['phone'] );
			$this->assertSame( '', $result['posted']['shipping_phone'] );
			$this->assertSame( '081229917900', $result['steps'][0]['customer']['billing_phone'] );
		}
	}

	public function test_raw_malformed_phone_cannot_be_salvaged_by_sanitizing_before_validation(): void {
		foreach ( array( array( '81229917900' ), '<b>81229917900</b>', "81229917900\n" ) as $raw ) {
			$form = array_replace( $this->form(), array( 'billing_phone' => $raw ) );
			$result = $this->runFixture( array( 'billing_only' => true, 'forms' => array( $form ), 'final' => $form ) );
			$this->assertSame( '', $result['steps'][0]['recipient']['phone'] );
			$this->assertSame( '', $result['posted']['shipping_phone'] );
			$this->assertSame( $raw, $result['posted']['billing_phone'] );
		}
	}

	public function test_final_plus62_change_is_revalidated_without_rewriting_billing(): void {
		$final = array_replace( $this->form(), array( 'billing_phone' => '+6281229917900' ) );
		$result = $this->runFixture( array( 'billing_only' => true, 'forms' => array( $this->form() ), 'final' => $final ) );
		$this->assertSame( '6281229917900', $result['posted']['shipping_phone'] );
		$this->assertSame( '+6281229917900', $result['posted']['billing_phone'] );
	}

	public function test_force_country_and_metadata_gates_leave_native_behavior_untouched(): void {
		$base = $this->form();
		unset( $base['_wfacp_post_id'] );
		$cases = array( array( false, $base ) );
		$without_metadata = $base;
		unset( $without_metadata['kiriof_funnelkit_phone_context'] );
		$cases[] = array( true, $without_metadata );
		foreach ( array( 'billing_country', 'shipping_country' ) as $key ) {
			foreach ( array( 'US', 'id', array( 'ID' ), null ) as $country ) {
				$cases[] = array( true, array_replace( $base, array( $key => $country ) ) );
			}
		}
		foreach ( $cases as list( $forced, $form ) ) {
			$result = $this->runFixture( array( 'billing_only' => $forced, 'forms' => array( $form ), 'final' => $form, 'posted' => array( 'shipping_phone' => 'native', 'billing_phone' => $form['billing_phone'] ) ) );
			$this->assertSame( '81229917900', $result['steps'][0]['customer']['shipping_phone'] );
			$this->assertSame( 'native', $result['posted']['shipping_phone'] );
		}
	}

	public function test_separate_shipping_and_unverified_final_lifecycle_are_unchanged(): void {
		$form = $this->form();
		unset( $form['_wfacp_post_id'] );
		$form['ship_to_different_address'] = '1';
		$form['shipping_phone'] = '087777777777';
		$result = $this->runFixture( array( 'forms' => array( $form ), 'final' => $form ) );
		$this->assertSame( '087777777777', $result['steps'][0]['recipient']['phone'] );
		$this->assertSame( '087777777777', $result['posted']['shipping_phone'] );
		foreach ( array( array( 'process' => false ), array( 'process_hook' => 'other' ), array( 'posted_hook' => 'other' ), array( 'rest' => true ) ) as $scope ) {
			$result = $this->runFixture( $scope + array( 'billing_only' => true, 'final' => $this->form(), 'posted' => array( 'shipping_phone' => 'native' ) ) );
			$this->assertSame( array( 'shipping_phone' => 'native' ), $result['posted'] );
		}
	}
}
