<?php
/** Isolated transport and service seams for production checkout registration. */
namespace KiriminAjaOfficial\Base {
	class BaseInit {
		public $plugin_url = 'https://shop.example/plugin/';
	}
}
namespace KiriminAjaOfficial\Repositories {
	class SettingRepository {}
}
namespace KiriminAjaOfficial\Services {
	class GoogleMapsSettings {
		public function config(): array {
			return array( 'provider' => 'leaflet' );
		}
	}
	class CustomerShippingDestinationService {
		public function forCheckout( $session ) {
			return null;
		}
	}
	class ShipmentLocationService {}
	class InstantCheckoutQuoteService {
		public function __construct( $settings, $locations ) {}
		public function enabledInstant(): bool {
			return true;
		}
	}
}
namespace {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	define( 'KIRIOF_DIR', ABSPATH );
	define( 'KIRIOF_VERSION', 'test' );
	define( 'KIRIOF_NONCE', 'test' );

	$GLOBALS['scripts'] = array();
	$GLOBALS['styles'] = array();
	$GLOBALS['localized'] = array();
	$GLOBALS['translations'] = array();
	$GLOBALS['transport'] = new class {
		public $data = array();
		public function get_data( $handle, $key ) {
			return $this->data[ $handle ] ?? false;
		}
	};

	function wp_scripts() {
		return $GLOBALS['transport'];
	}
	function wp_script_is( $handle, $status ) {
		return isset( $GLOBALS['scripts'][ $handle ] );
	}
	function wp_style_is( $handle, $status ) {
		return isset( $GLOBALS['styles'][ $handle ] );
	}
	function wp_register_script( $handle, ...$args ) {
		$GLOBALS['scripts'][ $handle ] = $args;
	}
	function wp_register_style( $handle, ...$args ) {
		$GLOBALS['styles'][ $handle ] = $args;
	}
	function wp_localize_script( $handle, $name, $config ) {
		$GLOBALS['localized'][ $name ] = $config;
		$data = wp_scripts()->get_data( $handle, 'data' );
		wp_scripts()->data[ $handle ] = ( is_string( $data ) ? $data : '' ) . 'var ' . $name . ' = ' . json_encode( $config, JSON_THROW_ON_ERROR ) . ';';
	}
	function wp_set_script_translations( $handle, $domain, $path ) {
		$GLOBALS['translations'][] = $handle;
	}
	function __( $value, $domain ) {
		return $value;
	}
	function admin_url( $path ) {
		return 'https://shop.example/wp-admin/' . $path;
	}
	function wp_create_nonce( $action ) {
		return 'test';
	}
	function WC() {
		return false;
	}

	require $argv[2] ?? ABSPATH . 'inc/Base/Enqueue.php';

	class BuyerConfigPerformanceEnqueue extends \KiriminAjaOfficial\Base\Enqueue {
		public $map_calls = 0;
		public $buyer_calls = 0;
		public $package_context = 'origin-a';

		public function map_checkout_config(): array {
			++$this->map_calls;
			return array(
				'provider' => 'leaflet',
				'coverage' => array( 'origin' => $this->package_context ),
			);
		}

		public function buyer_checkout_config(): array {
			++$this->buyer_calls;
			return parent::buyer_checkout_config();
		}
	}

	$mode = $argv[1];
	$handle = 'blocks' === $mode ? 'kiriof-buyer-blocks' : 'kiriof-classic-checkout';
	$buyer_name = 'blocks' === $mode ? 'kiriofBuyerCheckoutConfig' : 'kiriofClassicCheckoutConfig';
	$method = 'blocks' === $mode ? 'register_buyer_checkout_assets' : 'register_classic_checkout_assets';
	$enqueue = new BuyerConfigPerformanceEnqueue();
	$results = array();
	$cases = array(
		'register_only' => array( 'reset' => false, 'localize' => false ),
		'initial' => array( 'reset' => false, 'localize' => true ),
		'repeated' => array( 'reset' => false, 'localize' => true ),
		'buyer_only_missing' => array( 'reset' => true, 'localize' => true, 'seed' => 'var kiriofMapCheckoutConfig = {};', 'context' => 'origin-b' ),
		'map_only_missing' => array( 'reset' => true, 'localize' => true, 'seed' => 'var ' . $buyer_name . ' = {};', 'context' => 'origin-c' ),
		'changed_context' => array( 'reset' => true, 'localize' => true, 'context' => 'origin-d' ),
		'non_string_data' => array( 'reset' => true, 'localize' => true, 'seed' => array(), 'context' => 'origin-e' ),
	);
	foreach ( $cases as $name => $case ) {
		if ( $case['reset'] ) {
			wp_scripts()->data[ $handle ] = $case['seed'] ?? false;
		}
		$enqueue->package_context = $case['context'] ?? $enqueue->package_context;
		$GLOBALS['localized'] = array();
		$map_before = $enqueue->map_calls;
		$buyer_before = $enqueue->buyer_calls;
		$enqueue->$method( $case['localize'] );
		$results[ $name ] = array(
			'map_calls' => $enqueue->map_calls - $map_before,
			'buyer_calls' => $enqueue->buyer_calls - $buyer_before,
			'localized' => $GLOBALS['localized'],
		);
	}
	echo json_encode( array( 'cases' => $results, 'translations' => $GLOBALS['translations'] ), JSON_THROW_ON_ERROR );
}
