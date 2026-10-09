<?php
/** Isolated service doubles: never loaded into the shared PHPUnit process. */
namespace KiriminAjaOfficial\Services {
	class SettingService {
		public function isTopPaymentMethod(): bool {
			$GLOBALS['setting_calls']++;
			return $GLOBALS['scenario']['stored_top'];
		}
	}
	class KiriminajaApiService {
		public function getProfile() {
			$GLOBALS['profile_calls']++;
			if (! empty($GLOBALS['scenario']['throws'])) {
				throw new \RuntimeException('Profile unavailable');
			}
			$data = $GLOBALS['scenario']['data'];
			if (empty($GLOBALS['scenario']['array_data'])) {
				$data = json_decode(json_encode($data));
			}
			return new \KiriminAjaOfficial\Utils\ServiceResponse($data, 'fixture', $GLOBALS['scenario']['status']);
		}
	}
}
namespace KiriminAjaOfficial\Base {
	class BaseInit {
		public function logThis($message, $context) {
			$GLOBALS['logs'][] = $message;
		}
	}
}
namespace {
	$scenario = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
	$profile_calls = 0;
	$setting_calls = 0;
	$logs = array();
	define('ABSPATH', dirname(__DIR__, 2) . '/');
	define('KIRIOF_NONCE', 'pickup-nonce');
	define('KIRIOF_ENABLE_KA_CREDIT', $scenario['credit_enabled']);
	class PickupPaymentJsonSent extends \RuntimeException {
		public function __construct(public array $response) {
			parent::__construct('JSON sent');
		}
	}
	function current_user_can($capability) {
		return 'manage_woocommerce' === $capability && $GLOBALS['scenario']['authorized'];
	}
	function wp_verify_nonce($nonce, $action) {
		return 'valid-nonce' === $nonce && KIRIOF_NONCE === $action;
	}
	function sanitize_text_field($value) { return trim($value); }
	function wp_unslash($value) { return stripslashes($value); }
	function __($text, $domain = '') { return $text; }
	function wp_send_json_error($payload) {
		throw new PickupPaymentJsonSent(array('success' => false, 'data' => $payload));
	}
	function wp_send_json_success($payload) {
		throw new PickupPaymentJsonSent(array('success' => true, 'data' => $payload));
	}
	function wp_die() { throw new \RuntimeException('Unexpected wp_die'); }
	require_once ABSPATH . 'inc/Utils/ServiceResponse.php';
	require_once ABSPATH . 'inc/Controllers/TransactionProcessController.php';
	$_POST = array();
	if (null !== $scenario['nonce']) {
		$_POST['nonce'] = $scenario['nonce'];
	}
	// This endpoint uses no constructor dependencies. Invoke the real controller method.
	$controller = (new \ReflectionClass(\KiriminAjaOfficial\Controllers\TransactionProcessController::class))->newInstanceWithoutConstructor();
	try {
		$controller->getPaymentMethodConfig();
		throw new \RuntimeException('Endpoint did not send JSON');
	} catch (PickupPaymentJsonSent $response) {
		echo json_encode(array('response' => $response->response, 'profile_calls' => $profile_calls, 'setting_calls' => $setting_calls, 'logs' => $logs), JSON_THROW_ON_ERROR);
	}
}
