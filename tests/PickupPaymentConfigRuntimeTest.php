<?php

use PHPUnit\Framework\TestCase;

/** Runtime coverage of the AJAX endpoint, not source-string assertions. */
final class PickupPaymentConfigRuntimeTest extends TestCase {
	private function run_scenario(array $overrides = array()): array {
		$scenario = array_replace(array(
			'stored_top' => true,
			'credit_enabled' => true,
			'authorized' => true,
			'nonce' => 'valid-nonce',
			'status' => 200,
			'data' => array('metadata' => array('payment_method' => 'balance', 'has_pin' => true)),
		), $overrides);
		$output = array();
		$status = 0;
		exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/pickup-payment-config-runtime.php') . ' ' . escapeshellarg(json_encode($scenario, JSON_THROW_ON_ERROR)) . ' 2>&1', $output, $status);
		$this->assertSame(0, $status, implode("\n", $output));
		$result = json_decode(implode("\n", $output), true);
		$this->assertIsArray($result, implode("\n", $output));
		return $result;
	}

	private function assert_config(array $result, bool $is_top, bool $has_pin, bool $credit_enabled): void {
		$this->assertTrue($result['response']['success']);
		$this->assertSame(200, $result['response']['data']['status']);
		$this->assertSame(array('is_top' => $is_top, 'has_pin' => $has_pin, 'ka_credit_enabled' => $credit_enabled), $result['response']['data']['data']);
		$this->assertSame(1, $result['profile_calls']);
	}

	public function test_successful_non_top_profile_overrides_stale_top_with_credit_enabled_or_disabled(): void {
		foreach (array(true, false) as $credit_enabled) {
			$this->assert_config($this->run_scenario(array('credit_enabled' => $credit_enabled)), false, $credit_enabled, $credit_enabled);
		}
	}

	public function test_top_profile_is_trimmed_case_normalized_and_respected_for_object_and_array_metadata(): void {
		foreach (array(true, false) as $array_data) {
			foreach (array(true, false) as $credit_enabled) {
				$result = $this->run_scenario(array(
					'stored_top' => false,
					'array_data' => $array_data,
					'credit_enabled' => $credit_enabled,
					'data' => array('metadata' => array('payment_method' => " \tToP\n", 'has_pin' => true)),
				));
				$this->assert_config($result, true, $credit_enabled, $credit_enabled);
			}
		}
	}

	public function test_successful_profile_without_top_metadata_does_not_keep_stale_top(): void {
		$this->assert_config($this->run_scenario(array('data' => array('name' => 'Merchant'))), false, false, true);
	}

	public function test_failed_or_empty_profile_preserves_setting_and_does_not_trust_failure_metadata(): void {
		foreach (array(true, false) as $stored_top) {
			foreach (array(array('status' => 400), array('data' => array()), array('throws' => true)) as $failure) {
				$result = $this->run_scenario(array_replace($failure, array('stored_top' => $stored_top)));
				$this->assert_config($result, $stored_top, false, true);
			}
		}
	}

	public function test_missing_pin_defaults_to_boolean_false(): void {
		$this->assert_config($this->run_scenario(array('data' => array('metadata' => array('payment_method' => 'BALANCE')))), false, false, true);
	}

	public function test_permission_and_nonce_rejections_do_not_fetch_settings_or_profile(): void {
		foreach (array(array('authorized' => false), array('nonce' => 'invalid'), array('nonce' => null)) as $rejected) {
			$result = $this->run_scenario($rejected);
			$this->assertFalse($result['response']['success']);
			$this->assertSame(403, $result['response']['data']['status']);
			$this->assertSame(0, $result['profile_calls']);
			$this->assertSame(0, $result['setting_calls']);
		}
	}
}
