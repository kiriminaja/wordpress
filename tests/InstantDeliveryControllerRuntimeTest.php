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

	private array $rejectedExpected = [];
	private array $rejectedActual = [];

	private function assert_rejected( array $input, string $message = self::INVALID ): array {
		$result = $this->run_controller( $input );
		$labels = 'labels' === ( $input['operation'] ?? '' );
		$case = json_encode($input, JSON_THROW_ON_ERROR) . ' #' . count($this->rejectedExpected);
		$this->rejectedExpected[$case] = [
			'calls' => [], 'html' => '', 'headers' => [],
			'responses' => $labels ? [['die' => $message]] : [['success' => false, 'data' => ['status' => 400, 'message' => $message]]],
			'sentinel' => $labels ? 'die' : 'json-error',
		];
		$this->rejectedActual[$case] = [
			'calls' => $result['calls'], 'html' => $result['html'], 'headers' => $result['headers'],
			'responses' => $result['responses'], 'sentinel' => $result['sentinel'],
		];
		if (!$labels) {
			$this->rejectedExpected[$case]['http_status'] = 400;
			$this->rejectedActual[$case]['http_status'] = $result['http_status'];
		}
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
	public function all_ajax_routes_authorize_before_nonce_and_service_calls(): void {
        $expectedContracts = [];
        $actualContracts = [];
        $this->rejectedExpected = [];
        $this->rejectedActual = [];

		foreach ( self::AJAX as $operation ) {
			$result = $this->assert_rejected( array( 'operation' => $operation, 'capable' => false, 'data' => null ), 'Insufficient permissions' );
			$case = (__FUNCTION__) . ' #' . count($expectedContracts);
			$expectedContracts[$case] = array( array( 'capability', 'manage_woocommerce' ) );
			$actualContracts[$case] = $result['events'];
			foreach ( array( null, '', 'wrong', 'valid:kiriof_instant_labels', array( 'valid:kiriof_ajax' ), 123, '<b>valid:kiriof_ajax</b>', ' valid:kiriof_ajax ', 'valid:kiriof_ajax\\' ) as $nonce ) {
				$this->assert_rejected( array( 'operation' => $operation, 'fields' => array( 'nonce' => $nonce ) ), 'Security check failed' );
			}
			$this->assert_rejected( array( 'operation' => $operation, 'unset' => array( 'nonce' ) ), 'Security check failed' );
		}
	
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
        $this->assertSame($this->rejectedExpected, $this->rejectedActual, __FUNCTION__ . ' rejection matrix');
}

	#[Test]
	public function every_ajax_route_rejects_malformed_payloads_and_json_without_scalar_coercion(): void {
        $expectedContracts = [];
        $actualContracts = [];
        $this->rejectedExpected = [];
        $this->rejectedActual = [];

		foreach ( self::AJAX as $operation ) {
			foreach ( array( null, true, 'data', 42 ) as $data ) {
				$this->assert_rejected( array( 'operation' => $operation, 'data' => $data ) );
			}
			foreach ( array( null, array( 'KA-1' ), true, 42, '', '[', '{}', '{"0":"KA-1"}', '"KA-1"', 'null', 'true', '1' ) as $ids ) {
				$this->assert_rejected( array( 'operation' => $operation, 'fields' => array( 'order_ids' => $ids ) ) );
			}
			$this->assert_rejected( array( 'operation' => $operation, 'unset' => array( 'order_ids' ) ) );
		}
	
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
        $this->assertSame($this->rejectedExpected, $this->rejectedActual, __FUNCTION__ . ' rejection matrix');
}

	#[Test]
	public function id_batches_enforce_one_to_fifty_unique_exact_safe_string_or_integer_ids(): void {
        $expectedContracts = [];
        $actualContracts = [];
        $this->rejectedExpected = [];
        $this->rejectedActual = [];

		$invalid = array( array(), array( 'KA-1', 'KA-1' ), array( 1, '1' ), range( 1, 51 ) );
		foreach ( array( '', ' KA-1', 'KA-1 ', '-KA', '_KA', 'A.B', 'A/B', 'A\\B', 'A\\\\B', 'A,B', "A\nB", '<b>A</b>', 'é', str_repeat( 'a', 101 ), null, true, false, 1.2, array( 'KA' ), (object) array( 'id' => 'KA' ) ) as $id ) { $invalid[] = array( $id ); }
		foreach ( self::AJAX as $operation ) {
			foreach ( $invalid as $ids ) {
				$this->assert_rejected( array( 'operation' => $operation, 'fields' => array( 'order_ids' => json_encode( $ids ) ) ) );
			}
			$valid = array( array( 1 ), array( '0', 'KA_2-x', str_repeat( 'a', 100 ) ), range( 1, 50 ) );
			if ( in_array( $operation, array( 'tracking', 'reconcile', 'cancel' ), true ) ) {
				$valid = 'cancel' === $operation ? array( array( 'KA_2-x' ) ) : array( array( '0', 'KA_2-x', str_repeat( 'a', 100 ) ), array_map( 'strval', range( 1, 10 ) ) );
			}
			foreach ( $valid as $ids ) {
				$result = $this->run_controller( array( 'operation' => $operation, 'fields' => array( 'order_ids' => json_encode( $ids ) ) ) );
				$case = (__FUNCTION__) . ' #' . count($expectedContracts);
				$expectedContracts[$case] = [
				        'responses.0.success' => true,
				        'calls.0.1. \'dispatch\' === operation ? 1 : 0 ' => $ids,
				    ];
				$actualContracts[$case] = [
				        'responses.0.success' => $result['responses'][0]['success'],
				        'calls.0.1. \'dispatch\' === operation ? 1 : 0 ' => $result['calls'][0][1][ 'dispatch' === $operation ? 1 : 0 ],
				    ];
			}
		}
	
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
        $this->assertSame($this->rejectedExpected, $this->rejectedActual, __FUNCTION__ . ' rejection matrix');
}

	#[Test]
	public function lifecycle_routes_enforce_string_ids_limits_and_explicit_single_cancel_consent(): void {
        $expectedContracts = [];
        $actualContracts = [];
        $this->rejectedExpected = [];
        $this->rejectedActual = [];

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
			$case = (__FUNCTION__) . ' #' . count($expectedContracts);
			$expectedContracts[$case] = [
			        'calls' => array( array( $method, array( array( 'KA-1' ) ) ) ),
			        'events' => array( array( 'capability', 'manage_woocommerce' ), array( 'nonce', 'valid:kiriof_ajax', 'kiriof_ajax' ), array( 'service', $method ) ),
			        'responses' => array( array( 'success' => true, 'data' => array( 'status' => 200, 'data' => array( 'spy_result' => $method ) ) ) ),
			    ];
			$actualContracts[$case] = [
			        'calls' => $result['calls'],
			        'events' => $result['events'],
			        'responses' => $result['responses'],
			    ];
		}
	
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
        $this->assertSame($this->rejectedExpected, $this->rejectedActual, __FUNCTION__ . ' rejection matrix');
}

	#[Test]
	public function lifecycle_unknown_reports_are_returned_as_success_without_retries_or_other_services(): void {
		$report = array( 'rows' => array( array( 'id' => 'KA-1', 'status' => 'unknown', 'tracking_url' => '', 'message' => 'Reconciliation required.' ) ) );
		foreach ( array( 'tracking', 'reconcile', 'cancel' ) as $operation ) {
			$result = $this->run_controller( array( 'operation' => $operation, 'service_result' => $report ) );
			$this->assertSame(
			    [
			        'count(result.calls)' => 1,
			        'responses' => array( array( 'success' => true, 'data' => array( 'status' => 200, 'data' => $report ) ) ),
			    ],
			    [
			        'count(result.calls)' => count($result['calls']),
			        'responses' => $result['responses'],
			    ],
			    __FUNCTION__
			);
		}
	}

	#[Test]
	public function dispatch_requires_literal_yes_and_valid_scalar_token_method_and_optional_pin(): void {
        $expectedContracts = [];
        $actualContracts = [];
        $this->rejectedExpected = [];
        $this->rejectedActual = [];

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
			$case = (__FUNCTION__) . ' #' . count($expectedContracts);
			$expectedContracts[$case] = array( array( 'dispatch', array( 'quote-token', array( 'KA-1', 2 ), 'credit', '' ) ) );
			$actualContracts[$case] = $result['calls'];
		}
	
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
        $this->assertSame($this->rejectedExpected, $this->rejectedActual, __FUNCTION__ . ' rejection matrix');
}

	#[Test]
	public function credit_validation_requires_exact_token_and_pin_without_dispatch_consent(): void {
        $expectedContracts = [];
        $actualContracts = [];
        $this->rejectedExpected = [];
        $this->rejectedActual = [];

		foreach ( array( 'token', 'pin' ) as $key ) {
			foreach ( array( null, true, 42, array( 'value' ), ' value ', '<b>value</b>' ) as $value ) {
				$this->assert_rejected( array( 'operation' => 'validateCredit', 'fields' => array( $key => $value ) ) );
			}
			$this->assert_rejected( array( 'operation' => 'validateCredit', 'unset' => array( $key ) ) );
		}
		$this->assert_rejected( array( 'operation' => 'validateCredit', 'fields' => array( 'token' => '' ) ) );
		$result = $this->run_controller( array( 'operation' => 'validateCredit', 'unset' => array( 'confirmed', 'method' ), 'fields' => array( 'pin' => '123456' ), 'service_result' => array( 'valid' => true ) ) );
		$case = (__FUNCTION__) . ' #' . count($expectedContracts);
		$expectedContracts[$case] = [
		        'calls' => array( array( 'validateCredit', array( array( 'KA-1', 2 ), 'quote-token', '123456' ) ) ),
		        'responses' => array( array( 'success' => true, 'data' => array( 'status' => 200, 'data' => array( 'valid' => true ) ) ) ),
		    ];
		$actualContracts[$case] = [
		        'calls' => $result['calls'],
		        'responses' => $result['responses'],
		    ];
	
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
        $this->assertSame($this->rejectedExpected, $this->rejectedActual, __FUNCTION__ . ' rejection matrix');
}

	#[Test]
	public function valid_routes_forward_exact_arguments_and_emit_one_success_outside_the_service_try(): void {
		foreach ( array( 'quote' => array( 'quote', array( array( 'KA-1', 2 ) ) ), 'validateCredit' => array( 'validateCredit', array( array( 'KA-1', 2 ), 'quote-token', '1234' ) ), 'dispatch' => array( 'dispatch', array( 'quote-token', array( 'KA-1', 2 ), 'credit', '1234' ) ), 'payment' => array( 'refreshPayment', array( array( 'KA-1', 2 ), 'PAY-1' ) ), 'labelPreview' => array( 'preview', array( array( 'KA-1', 2 ) ) ) ) as $operation => $call ) {
			$result = $this->run_controller( array( 'operation' => $operation, 'fields' => array( 'ignored_field' => array( 'untrusted' ) ) ) );
			$this->assertSame(
			    [
			        'calls' => array( $call ),
			        'count(result.responses)' => 1,
			        'sentinel' => 'json-success',
			        'responses.0.data.status' => 200,
			        'responses.0.success' => true,
			    ],
			    [
			        'calls' => $result['calls'],
			        'count(result.responses)' => count($result['responses']),
			        'sentinel' => $result['sentinel'],
			        'responses.0.data.status' => $result['responses'][0]['data']['status'],
			        'responses.0.success' => $result['responses'][0]['success'],
			    ],
			    __FUNCTION__
			);
			if ( 'labelPreview' === $operation ) {
				$data = $result['responses'][0]['data']['data'];
				$this->assertSame(
				    [
				        'type' => 'html',
				        'provider' => 'local',
				        'carrier_available' => false,
				        'message fragment: data.fallback_reason' => 'not a courier-issued label',
				    ],
				    [
				        'type' => $data['type'],
				        'provider' => $data['provider'],
				        'carrier_available' => $data['carrier_available'],
				        'message fragment: data.fallback_reason' => substr($data['fallback_reason'], strpos($data['fallback_reason'], 'not a courier-issued label') === false ? strlen($data['fallback_reason']) : strpos($data['fallback_reason'], 'not a courier-issued label'), strlen('not a courier-issued label')),
				    ],
				    __FUNCTION__
				);
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
        $expectedContracts = [];
        $actualContracts = [];
        $this->rejectedExpected = [];
        $this->rejectedActual = [];

		foreach ( array( null, array( 'PAY' ), 123, true, '', ' PAY ', '<b>PAY</b>' ) as $value ) {
			$this->assert_rejected( array( 'operation' => 'payment', 'fields' => array( 'payment_id' => $value ) ) );
		}
		$this->assert_rejected( array( 'operation' => 'payment', 'unset' => array( 'payment_id' ) ) );
		foreach ( array( 'quote', 'dispatch', 'labelPreview' ) as $operation ) {
			$this->assertTrue( $this->run_controller( array( 'operation' => $operation, 'fields' => array( 'payment_id' => array( 'unknown' ) ) ) )['responses'][0]['success'] );
		}
		$result = $this->run_controller( array( 'operation' => 'payment', 'fields' => array( 'payment_id' => 'UNKNOWN-PAYMENT' ) ) );
		$case = (__FUNCTION__) . ' #' . count($expectedContracts);
		$expectedContracts[$case] = 'UNKNOWN-PAYMENT';
		$actualContracts[$case] = $result['calls'][0][1][1];
	
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
        $this->assertSame($this->rejectedExpected, $this->rejectedActual, __FUNCTION__ . ' rejection matrix');
}

	#[Test]
	public function label_render_requires_dedicated_nonce_and_cannot_use_legacy_bypass(): void {
        $expectedContracts = [];
        $actualContracts = [];
        $this->rejectedExpected = [];
        $this->rejectedActual = [];

		$result = $this->assert_rejected( array( 'operation' => 'labels', 'capable' => false ), 'Insufficient permissions' );
		$case = (__FUNCTION__) . ' #' . count($expectedContracts);
		$expectedContracts[$case] = array( array( 'capability', 'manage_woocommerce' ) );
		$actualContracts[$case] = $result['events'];
		foreach ( array( null, '', 'wrong', 'valid:kiriof_ajax', array( 'valid:kiriof_instant_labels' ), '<b>valid:kiriof_instant_labels</b>', ' valid:kiriof_instant_labels ', 'valid:kiriof_instant_labels\\' ) as $nonce ) {
			$this->assert_rejected( array( 'operation' => 'labels', 'legacy_bypass' => true, 'get' => array( '_wpnonce' => $nonce, 'oids' => 'KA-1' ) ), 'Security check failed' );
		}
		$this->assert_rejected( array( 'operation' => 'labels', 'legacy_bypass' => true, 'get' => array( 'oids' => 'KA-1' ) ), 'Security check failed' );
	
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
        $this->assertSame($this->rejectedExpected, $this->rejectedActual, __FUNCTION__ . ' rejection matrix');
}

	#[Test]
	public function render_validates_ids_then_prepares_and_renders_local_template_with_security_headers(): void {
        $expectedContracts = [];
        $actualContracts = [];
        $this->rejectedExpected = [];
        $this->rejectedActual = [];

		foreach ( array( null, array( 'KA' ), 1, '', 'KA,KA', 'KA,', ',KA', ' KA', 'KA\\1', '<b>KA</b>', implode( ',', range( 1, 51 ) ) ) as $oids ) {
			$this->assert_rejected( array( 'operation' => 'labels', 'get' => array( '_wpnonce' => 'valid:kiriof_instant_labels', 'oids' => $oids ) ) );
		}
		$this->assert_rejected( array( 'operation' => 'labels', 'get' => array( '_wpnonce' => 'valid:kiriof_instant_labels' ) ) );
		foreach ( array( array( 'KA-1' ), array_map( 'strval', range( 1, 50 ) ) ) as $ids ) {
			$result = $this->run_controller( array( 'operation' => 'labels', 'get' => array( '_wpnonce' => 'valid:kiriof_instant_labels', 'oids' => implode( ',', $ids ) ) ) );
			$case = (__FUNCTION__) . ' #' . count($expectedContracts);
			$expectedContracts[$case] = [
			        'calls' => array( array( 'prepare', array( $ids ) ) ),
			        'events' => array( array( 'capability', 'manage_woocommerce' ), array( 'nonce', 'valid:kiriof_instant_labels', 'kiriof_instant_labels' ), array( 'nocache' ), array( 'service', 'prepare' ) ),
			        'html' => 'LOCAL-TEMPLATE:{"spy_result":"prepare"}',
			        'responses' => array(),
			        'headers' => array( 'Content-Type: text/html; charset=UTF-8', 'X-Frame-Options: SAMEORIGIN', "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; frame-ancestors 'self'; base-uri 'none'; form-action 'none'" ),
			    ];
			$actualContracts[$case] = [
			        'calls' => $result['calls'],
			        'events' => $result['events'],
			        'html' => $result['html'],
			        'responses' => $result['responses'],
			        'headers' => $result['headers'],
			    ];
		}
	
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
        $this->assertSame($this->rejectedExpected, $this->rejectedActual, __FUNCTION__ . ' rejection matrix');
}

	#[Test]
	public function service_failures_emit_fixed_messages_without_rendering_or_leaking_remote_details(): void {
		foreach ( array_merge( self::AJAX, array( 'labels' ) ) as $operation ) {
			foreach ( array( 'validation' => 'Fixed validation message.', 'runtime' => 'Unable to complete the Instant request. Please try again.' ) as $error => $message ) {
				$result = $this->run_controller( array( 'operation' => $operation, 'service_error' => $error ) );
				$this->assertSame(
				    [
				        'count(result.calls)' => 1,
				        'count(result.responses)' => 1,
				        'html' => '',
				        'headers' => array(),
				        '\'labels\' === operation ? result.responses.0.die : result.responses.0.data.message' => $message,
				    ],
				    [
				        'count(result.calls)' => count($result['calls']),
				        'count(result.responses)' => count($result['responses']),
				        'html' => $result['html'],
				        'headers' => $result['headers'],
				        '\'labels\' === operation ? result.responses.0.die : result.responses.0.data.message' => 'labels' === $operation ? $result['responses'][0]['die'] : $result['responses'][0]['data']['message'],
				    ],
				    __FUNCTION__
				);
				if ( 'labels' !== $operation ) {
					$this->assertSame(
					    [
					        'http_status' => 'validation' === $error ? 400 : 503,
					        'responses.0.data.status' => $result['http_status'],
					    ],
					    [
					        'http_status' => $result['http_status'],
					        'responses.0.data.status' => $result['responses'][0]['data']['status'],
					    ],
					    __FUNCTION__
					);
				}
				$this->assertSame( 'labels' === $operation ? 'die' : 'json-error', $result['sentinel'] );
			}
		}
	}
}
