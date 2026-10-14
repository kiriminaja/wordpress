<?php
use PHPUnit\Framework\TestCase;

final class AdminBarCreditRuntimeTest extends TestCase {
	private function runScenario(array $overrides): array {
		$scenario = array_replace(array('is_top' => false, 'show_bar' => true, 'authorized' => true), $overrides);
		$output = array();
		$status = 0;
		exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/admin-bar-credit-runtime.php') . ' ' . escapeshellarg(json_encode($scenario, JSON_THROW_ON_ERROR)) . ' 2>&1', $output, $status);
		$this->assertSame(0, $status, implode("\n", $output));
		$result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
		return $result;
	}

	public function test_top_merchant_has_no_credit_node_or_balance_fetch(): void {
		$result = $this->runScenario(array('is_top' => true));
		$this->assertSame(array(), $result['nodes']);
		$this->assertSame(0, $result['balance_reads']);
		$this->assertSame(1, $result['top_checks']);
	}

	public function test_non_top_merchant_keeps_the_credit_balance_and_account_link(): void {
		$result = $this->runScenario(array('is_top' => false));
		$this->assertCount(1, $result['nodes']);
		$this->assertSame('kiriof-ka-credit-balance', $result['nodes'][0]['id']);
		$this->assertStringContainsString('KA Credit', $result['nodes'][0]['title']);
		$this->assertStringContainsString('9.907.800', $result['nodes'][0]['title']);
		$this->assertStringContainsString('section=account', $result['nodes'][0]['href']);
		$this->assertSame(1, $result['balance_reads']);
	}

	public function test_hidden_bar_and_unauthorized_users_do_not_trigger_merchant_or_balance_reads(): void {
		foreach (array(array('show_bar' => false), array('authorized' => false)) as $scenario) {
			$result = $this->runScenario($scenario);
			$this->assertSame(array(), $result['nodes']);
			$this->assertSame(0, $result['balance_reads']);
			$this->assertSame(0, $result['top_checks']);
		}
	}
}
