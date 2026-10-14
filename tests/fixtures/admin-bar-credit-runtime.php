<?php
/** Isolated doubles for the real admin-bar callback. */
namespace KiriminAjaOfficial\Base {
	class BaseInit {}
}
namespace KiriminAjaOfficial\Services {
	class SettingService {
		public function isTopPaymentMethod(): bool {
			$GLOBALS['top_checks']++;
			return $GLOBALS['scenario']['is_top'];
		}
	}
}
namespace {
	$scenario = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
	$top_checks = 0;
	$balance_reads = 0;
	define('ABSPATH', dirname(__DIR__, 2) . '/');
	function is_admin_bar_showing() { return $GLOBALS['scenario']['show_bar']; }
	function current_user_can($capability) { return $GLOBALS['scenario']['authorized']; }
	function kiriof_check_woocommerce() { return true; }
	function get_transient($key) {
		$GLOBALS['balance_reads']++;
		return 9907800;
	}
	function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES); }
	function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES); }
	function esc_html__($text, $domain) { return esc_html($text); }
	function esc_attr__($text, $domain) { return esc_attr($text); }
	function kiriof_money_format($balance) { return number_format($balance, 0, ',', '.'); }
	function admin_url($path) { return '/wp-admin/' . $path; }
	require_once ABSPATH . 'inc/Pages/Admin.php';
	$admin = ( new \ReflectionClass(\KiriminAjaOfficial\Pages\Admin::class) )->newInstanceWithoutConstructor();
	$bar = new class {
		public array $nodes = array();
		public function add_node($node): void { $this->nodes[] = $node; }
	};
	$admin->kiriof_add_credit_balance_admin_bar($bar);
	echo json_encode(array('nodes' => $bar->nodes, 'balance_reads' => $balance_reads, 'top_checks' => $top_checks), JSON_THROW_ON_ERROR);
}
