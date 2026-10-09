<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantDeliveryControllerRuntimeTest extends TestCase {
	private const AJAX = array( 'quote', 'validateCredit', 'dispatch', 'payment', 'labelPreview', 'tracking', 'reconcile', 'cancel' );
	private const INVALID = 'Invalid Instant request parameters.';

	private function run_controller( array $input = array() ): array {
		$output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/instant-delivery-controller-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) );
		return json_decode( (string) $output, true, 512, JSON_THROW_ON_ERROR );
	}

	private function assert_rejected( array $input, string $message = self::INVALID, string $case = '' ): array {
		$result = $this->run_controller( $input );
		$context = ( $input['operation'] ?? 'quote' ) . ': ' . ( $case ?: json_encode( $input, JSON_THROW_ON_ERROR ) );
		$labels = 'labels' === ( $input['operation'] ?? '' );
		$expected = array(
			'calls'     => array(),
			'html'      => '',
			'headers'   => array(),
			'responses' => $labels ? array( array( 'die' => $message ) ) : array( array( 'success' => false, 'data' => array( 'status' => 400, 'message' => $message ) ) ),
			'sentinel'  => $labels ? 'die' : 'json-error',
		);
		if ( ! $labels ) {
			$expected['http_status'] = 400;
		}
		$actual = array();
		foreach ( $expected as $key => $value ) {
			$actual[ $key ] = $result[ $key ];
		}
		$this->assertSame( $expected, $actual, $context . ' rejects without service calls or rendering' );
		return $result;
	}

	#[Test]
	public function carrier_preview_metadata_includes_a_separately_nonced_local_fallback(): void {
		$metadata = array( 'url' => 'https://storage.googleapis.com/labels/awb.pdf?sig=123', 'type' => 'pdf', 'provider' => 'carrier', 'carrier_available' => true );
		$result = $this->run_controller( array( 'operation' => 'labelPreview', 'service_result' => $metadata ) );
		$this->assertSame( array( array( 'preview', array( array( 'KA-1', 2 ) ) ) ), $result['calls'] );
		$data = $result['responses'][0]['data']['data'];
		foreach ( $metadata as $key => $value ) { $this->assertSame( $value, $data[$key] ); }
		parse_str( parse_url( $data['local_url'], PHP_URL_QUERY ), $query );
		$this->assertSame( array( 'action' => 'kiriof_instant_labels', 'oids' => 'KA-1,2', '_wpnonce' => 'valid:kiriof_instant_labels' ), $query );
	}

	#[Test]
	public function init_constructs_state_with_the_required_repository_even_as_a_direct_service(): void {
		$result = $this->run_controller( array( 'operation' => 'state_composition' ) );
		$this->assertSame(
		    [
		        'state' => 'KiriminAjaOfficial\\Services\\InstantShipmentState',
		        'repository' => 'KiriminAjaOfficial\\Repositories\\TransactionRepository',
		        'required_dependencies' => 1,
		    ],
		    [
		        'state' => $result['state'],
		        'repository' => $result['repository'],
		        'required_dependencies' => $result['required_dependencies'],
		    ],
		    __FUNCTION__
		);
	}

	#[Test]
	public function init_injects_exact_required_dependencies_and_only_authenticated_hooks(): void {
		$result = $this->run_controller();
		$this->assertSame( array(
			'controller' => 'KiriminAjaOfficial\\Controllers\\InstantDeliveryController',
			'dispatch' => 'KiriminAjaOfficial\\Services\\InstantDispatchService',
			'label' => 'KiriminAjaOfficial\\Services\\InstantLabelService',
			'operations' => 'KiriminAjaOfficial\\Services\\InstantOperationsService',
			'dispatch_dependencies' => array( 'KiriminAjaOfficial\\Repositories\\TransactionRepository', 'KiriminAjaOfficial\\Repositories\\InstantDeliveryApiRepository', 'KiriminAjaOfficial\\Services\\InstantShipmentContext' ),
			'label_dependencies' => array( 'KiriminAjaOfficial\\Repositories\\TransactionRepository' ),
			'operations_dependencies' => array( 'KiriminAjaOfficial\\Repositories\\TransactionRepository', 'KiriminAjaOfficial\\Repositories\\InstantDeliveryApiRepository', 'KiriminAjaOfficial\\Services\\InstantShipmentState' ),
			'shared_repository' => true,
			'shared_api' => true,
			'shared_state_repository' => true,
			'required_dependencies' => 3,
			'listed_count' => 1,
		), $result['composition'] );
		$this->assertSame( array(
			array( 'wp_ajax_kiriof_instant_quote', $result['composition']['controller'], 'quote' ),
			array( 'wp_ajax_kiriof_instant_validate_credit', $result['composition']['controller'], 'validateCredit' ),
			array( 'wp_ajax_kiriof_instant_dispatch', $result['composition']['controller'], 'dispatch' ),
			array( 'wp_ajax_kiriof_instant_payment', $result['composition']['controller'], 'payment' ),
			array( 'wp_ajax_kiriof_instant_label_preview', $result['composition']['controller'], 'labelPreview' ),
			array( 'wp_ajax_kiriof_instant_tracking', $result['composition']['controller'], 'tracking' ),
			array( 'wp_ajax_kiriof_instant_reconcile', $result['composition']['controller'], 'reconcile' ),
			array( 'wp_ajax_kiriof_instant_cancel', $result['composition']['controller'], 'cancel' ),
			array( 'admin_post_kiriof_instant_labels', $result['composition']['controller'], 'labels' ),
		), $result['hooks'] );
	}

	#[Test]
	public function every_ajax_route_checks_permissions_nonce_payload_and_ids_before_services(): void {
		foreach ( self::AJAX as $operation ) {
			$result = $this->assert_rejected( array( 'operation' => $operation, 'capable' => false, 'data' => null ), 'Insufficient permissions', 'permission precedes malformed payload' );
			$this->assertSame( array( array( 'capability', 'manage_woocommerce' ) ), $result['events'], $operation . ' permission precedes nonce' );
			$this->assert_rejected( array( 'operation' => $operation, 'unset' => array( 'nonce' ) ), 'Security check failed', 'missing nonce' );
			$this->assert_rejected( array( 'operation' => $operation, 'fields' => array( 'nonce' => 'wrong' ) ), 'Security check failed', 'wrong nonce' );
			$this->assert_rejected( array( 'operation' => $operation, 'data' => 'data' ), self::INVALID, 'non-array payload' );
			$this->assert_rejected( array( 'operation' => $operation, 'fields' => array( 'order_ids' => '["A/B"]' ) ), self::INVALID, 'unsafe ID' );
		}
	}

	#[Test]
	public function shared_ajax_nonce_validation_rejects_non_strings_wrong_actions_and_sanitized_lookalikes(): void {
		// All AJAX routes use ajax(); labels has a separate authorization path below.
		foreach ( array(
			'null' => null, 'empty' => '', 'wrong token' => 'wrong', 'label action' => 'valid:kiriof_instant_labels',
			'array' => array( 'valid:kiriof_ajax' ), 'integer' => 123, 'markup' => '<b>valid:kiriof_ajax</b>',
			'whitespace' => ' valid:kiriof_ajax ', 'literal backslash' => 'valid:kiriof_ajax\\',
		) as $case => $nonce ) {
			$this->assert_rejected( array( 'operation' => 'quote', 'fields' => array( 'nonce' => $nonce ) ), 'Security check failed', $case );
		}
	}

	#[Test]
	public function shared_ajax_payload_and_posted_ids_require_an_array_payload_and_a_json_list(): void {
		foreach ( array( 'null' => null, 'boolean' => true, 'string' => 'data', 'integer' => 42 ) as $case => $data ) {
			$this->assert_rejected( array( 'operation' => 'quote', 'data' => $data ), self::INVALID, $case . ' payload' );
		}
		foreach ( array(
			'null field' => null, 'native array' => array( 'KA-1' ), 'boolean field' => true, 'integer field' => 42,
			'empty JSON' => '', 'broken JSON' => '[', 'object' => '{}', 'numeric-key object' => '{"0":"KA-1"}',
			'JSON string' => '"KA-1"', 'JSON null' => 'null', 'JSON boolean' => 'true', 'JSON number' => '1',
		) as $case => $ids ) {
			$this->assert_rejected( array( 'operation' => 'quote', 'fields' => array( 'order_ids' => $ids ) ), self::INVALID, $case );
		}
		$this->assert_rejected( array( 'operation' => 'quote', 'unset' => array( 'order_ids' ) ), self::INVALID, 'missing IDs' );
	}

	#[Test]
	public function shared_id_validation_rejects_unsafe_types_duplicates_and_out_of_bounds_batches(): void {
		// postedIds() and labels() converge on validateIds(); exercise its full boundaries once.
		$invalid = array( 'empty batch' => array(), 'duplicate strings' => array( 'KA-1', 'KA-1' ), 'integer-string collision' => array( 1, '1' ), '51 IDs' => range( 1, 51 ) );
		foreach ( array(
			'empty ID' => '', 'leading whitespace' => ' KA-1', 'trailing whitespace' => 'KA-1 ',
			'leading hyphen' => '-KA', 'leading underscore' => '_KA', 'dot' => 'A.B', 'slash' => 'A/B',
			'backslash' => 'A\\B', 'double backslash' => 'A\\\\B', 'comma' => 'A,B', 'newline' => "A\nB",
			'markup' => '<b>A</b>', 'non-ASCII' => 'é', '101 characters' => str_repeat( 'a', 101 ),
			'null' => null, 'true' => true, 'false' => false, 'float' => 1.2,
			'nested array' => array( 'KA' ), 'object' => (object) array( 'id' => 'KA' ),
		) as $case => $id ) { $invalid[$case] = array( $id ); }
		foreach ( $invalid as $case => $ids ) {
			$this->assert_rejected( array( 'operation' => 'quote', 'fields' => array( 'order_ids' => json_encode( $ids ) ) ), self::INVALID, $case );
		}
	}

	#[Test]
	public function shared_id_validation_preserves_integer_string_and_maximum_length_and_batch_boundaries(): void {
		foreach ( array( 'one integer' => array( 1 ), 'zero and safe punctuation and 100 characters' => array( '0', 'KA_2-x', str_repeat( 'a', 100 ) ), '50 IDs' => range( 1, 50 ) ) as $case => $ids ) {
			$result = $this->run_controller( array( 'operation' => 'quote', 'fields' => array( 'order_ids' => json_encode( $ids ) ) ) );
			$this->assertTrue( $result['responses'][0]['success'], $case );
			$this->assertSame( array( array( 'quote', array( $ids ) ) ), $result['calls'], $case . ' exact forwarding' );
		}
	}

	#[Test]
	public function lifecycle_routes_enforce_string_ids_limits_and_explicit_single_cancel_consent(): void {
		foreach ( array( 'tracking', 'reconcile', 'cancel' ) as $operation ) {
			foreach ( array( array( 1 ), array( 'KA-1', 2 ), array_map( 'strval', range( 1, 11 ) ) ) as $ids ) {
				$this->assert_rejected( array( 'operation' => $operation, 'fields' => array( 'order_ids' => json_encode( $ids ) ) ) );
			}
		}
		foreach ( array( null, true, false, 1, 'true', 'YES', ' yes', 'yes ', '<b>yes</b>', array( 'yes' ), array( 'confirmed' => 'yes' ) ) as $confirmed ) {
			$this->assert_rejected( array( 'operation' => 'cancel', 'fields' => array( 'confirmed' => $confirmed ) ) );
		}
		$this->assert_rejected( array( 'operation' => 'cancel', 'unset' => array( 'confirmed' ) ) );
		$this->assert_rejected( array( 'operation' => 'cancel', 'fields' => array( 'order_ids' => '["KA-1","KA-2"]' ) ) );
		foreach ( array( 'tracking' => 'track', 'reconcile' => 'reconcile', 'cancel' => 'cancel' ) as $operation => $method ) {
			$result = $this->run_controller( array( 'operation' => $operation, 'unset' => array( 'token', 'method', 'pin', 'payment_id' ) ) );
			$this->assertSame( array( array( $method, array( array( 'KA-1' ) ) ) ), $result['calls'], $operation . ' exact service arguments' );
			$this->assertSame( array( array( 'capability', 'manage_woocommerce' ), array( 'nonce', 'valid:kiriof_ajax', 'kiriof_ajax' ), array( 'service', $method ) ), $result['events'], $operation . ' guard and service ordering' );
			$this->assertSame( array( array( 'success' => true, 'data' => array( 'status' => 200, 'data' => array( 'spy_result' => $method ) ) ) ), $result['responses'], $operation . ' response contract' );
		}
	}

	#[Test]
	public function lifecycle_routes_forward_safe_string_ids_and_accept_ten_but_cancel_accepts_only_one(): void {
		foreach ( array( 'tracking' => 'track', 'reconcile' => 'reconcile', 'cancel' => 'cancel' ) as $operation => $method ) {
			$valid = 'cancel' === $operation ? array( array( 'KA_2-x' ) ) : array( array( '0', 'KA_2-x', str_repeat( 'a', 100 ) ), array_map( 'strval', range( 1, 10 ) ) );
			foreach ( $valid as $ids ) {
				$result = $this->run_controller( array( 'operation' => $operation, 'fields' => array( 'order_ids' => json_encode( $ids ) ) ) );
				$this->assertTrue( $result['responses'][0]['success'], $operation . ' accepts valid lifecycle batch' );
				$this->assertSame( array( array( $method, array( $ids ) ) ), $result['calls'], $operation . ' preserves string IDs' );
			}
		}
	}

	#[Test]
	public function lifecycle_unknown_reports_are_returned_as_success_without_retries_or_other_services(): void {
		$report = array( 'rows' => array( array( 'id' => 'KA-1', 'status' => 'unknown', 'tracking_url' => '', 'message' => 'Reconciliation required.' ) ) );
		foreach ( array( 'tracking', 'reconcile', 'cancel' ) as $operation ) {
			$result = $this->run_controller( array( 'operation' => $operation, 'service_result' => $report ) );
			$this->assertCount( 1, $result['calls'], $operation . ' invokes only one service' );
			$this->assertSame( array( array( 'success' => true, 'data' => array( 'status' => 200, 'data' => $report ) ) ), $result['responses'], $operation . ' returns unknown report unchanged' );
		}
	}

	#[Test]
	public function dispatch_requires_literal_yes_and_valid_scalar_token_method_and_optional_pin(): void {
		foreach ( array( null, true, false, 1, 'true', 'YES', ' yes', 'yes ', '<b>yes</b>', array( 'yes' ) ) as $confirmed ) {
			$this->assert_rejected( array( 'operation' => 'dispatch', 'fields' => array( 'confirmed' => $confirmed ) ), 'Review and confirm the Instant shipping costs before dispatch.' );
		}
		$this->assert_rejected( array( 'operation' => 'dispatch', 'unset' => array( 'confirmed' ) ), 'Review and confirm the Instant shipping costs before dispatch.' );
		foreach ( array( 'token', 'method', 'pin' ) as $key ) {
			foreach ( array( array( 'value' ), null, true, 42, ' value ', '<b>value</b>' ) as $value ) {
				$this->assert_rejected( array( 'operation' => 'dispatch', 'fields' => array( $key => $value ) ) );
			}
			if ( 'pin' !== $key ) {
				$this->assert_rejected( array( 'operation' => 'dispatch', 'fields' => array( $key => '' ) ) );
				$this->assert_rejected( array( 'operation' => 'dispatch', 'unset' => array( $key ) ) );
			}
		}
		foreach ( array( array( 'unset' => array( 'pin' ) ), array( 'fields' => array( 'pin' => '' ) ) ) as $input ) {
			$result = $this->run_controller( array_merge( array( 'operation' => 'dispatch' ), $input ) );
			$this->assertSame( array( array( 'dispatch', array( 'quote-token', array( 'KA-1', 2 ), 'credit', '' ) ) ), $result['calls'] );
		}
	}

	#[Test]
	public function credit_validation_requires_exact_token_and_pin_without_dispatch_consent(): void {
		foreach ( array( 'token', 'pin' ) as $key ) {
			foreach ( array( null, true, 42, array( 'value' ), ' value ', '<b>value</b>' ) as $value ) {
				$this->assert_rejected( array( 'operation' => 'validateCredit', 'fields' => array( $key => $value ) ) );
			}
			$this->assert_rejected( array( 'operation' => 'validateCredit', 'unset' => array( $key ) ) );
		}
		$this->assert_rejected( array( 'operation' => 'validateCredit', 'fields' => array( 'token' => '' ) ) );
		$result = $this->run_controller( array( 'operation' => 'validateCredit', 'unset' => array( 'confirmed', 'method' ), 'fields' => array( 'pin' => '123456' ), 'service_result' => array( 'valid' => true ) ) );
		$this->assertSame( array( array( 'validateCredit', array( array( 'KA-1', 2 ), 'quote-token', '123456' ) ) ), $result['calls'], 'validateCredit exact service arguments' );
		$this->assertSame( array( array( 'success' => true, 'data' => array( 'status' => 200, 'data' => array( 'valid' => true ) ) ) ), $result['responses'], 'credit validation response' );
	}

	#[Test]
	public function valid_routes_forward_exact_arguments_and_emit_one_success_outside_the_service_try(): void {
		foreach ( array( 'quote' => array( 'quote', array( array( 'KA-1', 2 ) ) ), 'validateCredit' => array( 'validateCredit', array( array( 'KA-1', 2 ), 'quote-token', '1234' ) ), 'dispatch' => array( 'dispatch', array( 'quote-token', array( 'KA-1', 2 ), 'credit', '1234' ) ), 'payment' => array( 'refreshPayment', array( array( 'KA-1', 2 ), 'PAY-1' ) ), 'labelPreview' => array( 'preview', array( array( 'KA-1', 2 ) ) ) ) as $operation => $call ) {
			$result = $this->run_controller( array( 'operation' => $operation, 'fields' => array( 'ignored_field' => array( 'untrusted' ) ) ) );
			$this->assertSame( array( $call ), $result['calls'], $operation . ' exact service arguments' );
			$this->assertCount( 1, $result['responses'], $operation . ' emits one response' );
			$this->assertSame( 'json-success', $result['sentinel'], $operation . ' success terminates outside service try' );
			$this->assertSame( 200, $result['responses'][0]['data']['status'], $operation . ' success status' );
			$this->assertTrue( $result['responses'][0]['success'], $operation . ' success response' );
			if ( 'labelPreview' === $operation ) {
				$data = $result['responses'][0]['data']['data'];
				$this->assertSame( 'html', $data['type'] );
				$this->assertSame( 'local', $data['provider'] );
				$this->assertFalse( $data['carrier_available'] );
				$this->assertStringContainsString( 'not a courier-issued label', $data['fallback_reason'] );
				parse_str( parse_url( $data['url'], PHP_URL_QUERY ), $query );
				$this->assertSame( array( 'action' => 'kiriof_instant_labels', 'oids' => 'KA-1,2', '_wpnonce' => 'valid:kiriof_instant_labels' ), $query );
			} else {
				$this->assertSame( array( 'spy_result' => $call[0] ), $result['responses'][0]['data']['data'] );
			}
		}
	}

	#[Test]
	public function scalar_fields_preserve_literal_backslashes_after_one_unslash(): void {
		$fields = array( 'token' => 'quote\\token', 'method' => 'credit\\method', 'pin' => '12\\34' );
		$result = $this->run_controller( array( 'operation' => 'dispatch', 'fields' => $fields ) );
		$this->assertSame( array( array( 'dispatch', array( $fields['token'], array( 'KA-1', 2 ), $fields['method'], $fields['pin'] ) ) ), $result['calls'] );
		$result = $this->run_controller( array( 'operation' => 'payment', 'fields' => array( 'payment_id' => 'PAY\\1' ) ) );
		$this->assertSame( array( array( 'refreshPayment', array( array( 'KA-1', 2 ), 'PAY\\1' ) ) ), $result['calls'] );
	}

	#[Test]
	public function payment_id_is_validated_only_for_payment_and_other_endpoints_ignore_it(): void {
		foreach ( array( null, array( 'PAY' ), 123, true, '', ' PAY ', '<b>PAY</b>' ) as $value ) {
			$this->assert_rejected( array( 'operation' => 'payment', 'fields' => array( 'payment_id' => $value ) ) );
		}
		$this->assert_rejected( array( 'operation' => 'payment', 'unset' => array( 'payment_id' ) ) );
		foreach ( array( 'quote', 'dispatch', 'labelPreview' ) as $operation ) {
			$this->assertTrue( $this->run_controller( array( 'operation' => $operation, 'fields' => array( 'payment_id' => array( 'unknown' ) ) ) )['responses'][0]['success'] );
		}
		$result = $this->run_controller( array( 'operation' => 'payment', 'fields' => array( 'payment_id' => 'UNKNOWN-PAYMENT' ) ) );
		$this->assertSame( 'UNKNOWN-PAYMENT', $result['calls'][0][1][1] );
	}

	#[Test]
	public function label_render_requires_dedicated_nonce_and_cannot_use_legacy_bypass(): void {
		$result = $this->assert_rejected( array( 'operation' => 'labels', 'capable' => false ), 'Insufficient permissions' );
		$this->assertSame( array( array( 'capability', 'manage_woocommerce' ) ), $result['events'] );
		foreach ( array( 'null' => null, 'empty' => '', 'wrong token' => 'wrong', 'AJAX action' => 'valid:kiriof_ajax', 'array' => array( 'valid:kiriof_instant_labels' ), 'markup' => '<b>valid:kiriof_instant_labels</b>', 'whitespace' => ' valid:kiriof_instant_labels ', 'literal backslash' => 'valid:kiriof_instant_labels\\' ) as $case => $nonce ) {
			$this->assert_rejected( array( 'operation' => 'labels', 'legacy_bypass' => true, 'get' => array( '_wpnonce' => $nonce, 'oids' => 'KA-1' ) ), 'Security check failed', $case );
		}
		$this->assert_rejected( array( 'operation' => 'labels', 'legacy_bypass' => true, 'get' => array( 'oids' => 'KA-1' ) ), 'Security check failed', 'missing dedicated nonce' );
	}

	#[Test]
	public function render_validates_ids_then_prepares_and_renders_local_template_with_security_headers(): void {
		foreach ( array( 'null' => null, 'array' => array( 'KA' ), 'integer' => 1, 'empty' => '', 'duplicate IDs' => 'KA,KA', 'trailing empty ID' => 'KA,', 'leading empty ID' => ',KA', 'whitespace' => ' KA', 'literal backslash' => 'KA\\1', 'markup' => '<b>KA</b>', '51 IDs' => implode( ',', range( 1, 51 ) ) ) as $case => $oids ) {
			$this->assert_rejected( array( 'operation' => 'labels', 'get' => array( '_wpnonce' => 'valid:kiriof_instant_labels', 'oids' => $oids ) ), self::INVALID, $case );
		}
		$this->assert_rejected( array( 'operation' => 'labels', 'get' => array( '_wpnonce' => 'valid:kiriof_instant_labels' ) ) );
		foreach ( array( array( 'KA-1' ), array_map( 'strval', range( 1, 50 ) ) ) as $ids ) {
			$result = $this->run_controller( array( 'operation' => 'labels', 'get' => array( '_wpnonce' => 'valid:kiriof_instant_labels', 'oids' => implode( ',', $ids ) ) ) );
			$this->assertSame( array( array( 'prepare', array( $ids ) ) ), $result['calls'], 'labels ' . ' exact service arguments' );
			$this->assertSame( array( array( 'capability', 'manage_woocommerce' ), array( 'nonce', 'valid:kiriof_instant_labels', 'kiriof_instant_labels' ), array( 'nocache' ), array( 'service', 'prepare' ) ), $result['events'], 'labels ' . ' guard and service ordering' );
			$this->assertSame( 'LOCAL-TEMPLATE:{"spy_result":"prepare"}', $result['html'], 'html' );
			$this->assertSame( array(), $result['responses'], 'labels ' . ' response contract' );
			$this->assertSame( array( 'Content-Type: text/html; charset=UTF-8', 'X-Frame-Options: SAMEORIGIN', "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; frame-ancestors 'self'; base-uri 'none'; form-action 'none'" ), $result['headers'], 'headers' );
		}
	}

	#[Test]
	public function service_failures_emit_fixed_messages_without_rendering_or_leaking_remote_details(): void {
		foreach ( array_merge( self::AJAX, array( 'labels' ) ) as $operation ) {
			foreach ( array( 'validation' => 'Fixed validation message.', 'runtime' => 'Unable to complete the Instant request. Please try again.' ) as $error => $message ) {
				$result = $this->run_controller( array( 'operation' => $operation, 'service_error' => $error ) );
				$case = $operation . ': ' . $error . ' service failure';
				$this->assertCount( 1, $result['calls'], $case . ' no retries' );
				$this->assertCount( 1, $result['responses'], $case . ' one response' );
				$this->assertSame( '', $result['html'], $case . ' no rendering' );
				$this->assertSame( array(), $result['headers'], $case . ' no label headers' );
				$this->assertSame( $message, 'labels' === $operation ? $result['responses'][0]['die'] : $result['responses'][0]['data']['message'], $case . ' fixed public message' );
				if ( 'labels' !== $operation ) {
					$this->assertSame( 'validation' === $error ? 400 : 503, $result['http_status'], $case . ' HTTP status' );
					$this->assertSame( $result['http_status'], $result['responses'][0]['data']['status'], $case . ' response status' );
				}
				$this->assertSame( 'labels' === $operation ? 'die' : 'json-error', $result['sentinel'] );
			}
		}
	}
}
