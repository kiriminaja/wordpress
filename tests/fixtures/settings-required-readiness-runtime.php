<?php
namespace KiriminAjaOfficial\Repositories {
	class SettingRepository {
		public function getSettingByKey( $key ) {
			$value = ( 'couriers' === $GLOBALS['missing'] && 'origin_whitelist_expedition_id' === $key )
				|| ( 'account' === $GLOBALS['missing'] && 'api_key' === $key ) ? '' : 'configured';
			return (object) array( 'value' => $value );
		}
		public function getSettingByArray( $keys ) {
			return array_map( static fn( $key ) => (object) array( 'key' => $key, 'value' => 'address' === $GLOBALS['missing'] ? '' : 'configured' ), $keys );
		}
		public function getCourierServiceSelection() {
			return null;
		}
	}
}
namespace KiriminAjaOfficial\Services {
	class WooCommerceShippingMethodRegistrationService {
		public function hasEnabledMethod(): bool {
			return 'shipping' !== $GLOBALS['missing'];
		}
	}
}
namespace {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	$GLOBALS['missing'] = $argv[1] ?? '';
	$GLOBALS['tracking_calls'] = 0;
	function __( $text, $domain ) {
		return $text;
	}
	function get_option( $key, $default = false ) {
		return 'locations' === $GLOBALS['missing'] ? 'disabled' : $default;
	}
	function WC() {
		return (object) array( 'countries' => new class {
			public function get_shipping_countries() {
				return array( 'ID' => 'Indonesia' );
			}
		} );
	}
	function kiriof_get_tracking_page_id() {
		++$GLOBALS['tracking_calls'];
		return 0;
	}
	$wpdb = new class {
		public string $posts = 'wp_posts';
		public string $postmeta = 'wp_postmeta';
		public int $queries = 0;
		public function get_var( $query ) {
			++$this->queries;
			return str_contains( $query, 'weight_meta' ) ? 0 : 4;
		}
	};
	require_once ABSPATH . 'inc/Contracts/ProductVolumetricReadinessRepositoryInterface.php';
	require_once ABSPATH . 'inc/Repositories/ProductVolumetricReadinessRepository.php';
	require_once ( $argv[2] ?? ABSPATH . 'inc/Services/OnboardingSetupStateService.php' );
	$service = new \KiriminAjaOfficial\Services\OnboardingSetupStateService();
	$complete = $service->is_required_complete();
	$gate_queries = $wpdb->queries;
	$gate_tracking_calls = $GLOBALS['tracking_calls'];
	$steps = $service->get_steps();
	$first = $service->get_first_incomplete_step();
	$service->get_steps();
	$service->is_required_complete();
	echo json_encode( array(
		'complete' => $complete,
		'gate_queries' => $gate_queries,
		'gate_tracking_calls' => $gate_tracking_calls,
		'full_queries' => $wpdb->queries,
		'full_tracking_calls' => $GLOBALS['tracking_calls'],
		'steps' => $steps,
		'first' => $first,
	), JSON_THROW_ON_ERROR );
}
