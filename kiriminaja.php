<?php
/**
 * Plugin Name:     KiriminAja
 * Plugin URI:      https://kiriminaja.com
 * Description:     Hitung ongkos kirim seluruh Indonesia (JNE, POS, Tiki, JNT, Wahana, Lion Parcel, Sicepat, dll)
 * Version:         0.0.22
 * Author:          KiriminAja
 * Author URI:      https://kiriminaja.com
 * License:         GPL
 * Text Domain:     kiriminaja
 * Domain Path:     /languages
 * WC requires at least: 5.0.0
 * WC tested up to: 7.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// constants.
define( 'KIRIMINAJA_VERSION', '0.0.22' );
define( 'KIRIMINAJA_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'KIRIMINAJA_PLUGIN_URL', plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) );
define( 'KIRIMINAJA_SETTING_URL', admin_url( 'admin.php?page=wc-settings&tab=shipping&section=kiriminaja' ) );
// define( 'KIRIMINAJA_DEV', true );

// load files.
require_once KIRIMINAJA_PLUGIN_PATH . 'inc/class-kiriminaja-setting.php';
require_once KIRIMINAJA_PLUGIN_PATH . 'inc/class-kiriminaja-helper.php';
require_once KIRIMINAJA_PLUGIN_PATH . 'inc/class-kiriminaja-api.php';
require_once KIRIMINAJA_PLUGIN_PATH . 'inc/class-kiriminaja-core.php';
require_once KIRIMINAJA_PLUGIN_PATH . 'inc/class-kiriminaja-pickup-request.php';
// require_once KIRIMINAJA_PLUGIN_PATH . 'inc/class-pok-hooks-product.php';
require_once KIRIMINAJA_PLUGIN_PATH . 'inc/class-kiriminaja-admin.php';
require_once KIRIMINAJA_PLUGIN_PATH . 'inc/class-kiriminaja-ajax.php';
require_once KIRIMINAJA_PLUGIN_PATH . 'inc/class-kiriminaja-hooks-product.php';
require_once KIRIMINAJA_PLUGIN_PATH . 'inc/class-kiriminaja-hooks-order.php';
require_once KIRIMINAJA_PLUGIN_PATH . 'inc/class-kiriminaja-hooks-addresses.php';
require_once KIRIMINAJA_PLUGIN_PATH . 'inc/class-kiriminaja-callback-handler.php';

if ( ! class_exists( 'KiriminAja' ) ) {

	/**
	 * KiriminAja Main Class
	 */
	class KiriminAja {
		/**
		 * @var string
		 */
		private $plugin_slug;
		/**
		 * @var string
		 */
		private $cache_key;
		/**
		 * @var false
		 */
		private $cache_allowed;

		/**
		 * Constructor
		 */
		public function __construct() {
			global $kiriminaja_helper;
			global $kiriminaja_core;
			$kiriminaja_helper = new KiriminAja_Helper();
			$kiriminaja_core   = new KiriminAja_Core();
			$this->helper 	   = $kiriminaja_helper;

			$this->plugin_slug = plugin_basename( __DIR__ );
			$this->cache_key = 'kaj_upd';
			$this->cache_allowed = false;

			register_activation_hook( __FILE__, array( $this, 'on_plugin_activation' ) );
			add_action( 'admin_init', array( $this, 'on_admin_init' ) );
			add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
			add_action( 'woocommerce_shipping_init', array( $this, 'load_shipping_method' ) );
			add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_method' ) );

			//Updater
			add_filter( 'plugins_api', array( $this, 'info' ), 20, 3 );
			add_filter( 'site_transient_update_plugins', array( $this, 'update' ) );
			add_action( 'upgrader_process_complete', array( $this, 'purge' ), 10, 2 );
		}

		/**
		 * Actions when plugin activated
		 */
		public function on_plugin_activation() {
			set_transient( 'kiriminaja_activation', 'active', HOUR_IN_SECONDS );
		}

		/**
		 * Actions when admin initialized
		 */
		public function on_admin_init() {
			if ( false !== get_transient( 'kiriminaja_activation' ) ) {
				delete_transient( 'kiriminaja_activation' );
				if ( ! $this->helper->is_token_set() || ! $this->helper->is_store_set() ) {
					wp_safe_redirect( KIRIMINAJA_SETTING_URL );
					exit;
				}
			}
		}

		/**
		 * Actions when all plugins loaded
		 */
		public function on_plugins_loaded() {
			load_plugin_textdomain( 'kiriminaja', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
			new KiriminAja_Admin();
			new KiriminAja_Ajax();
			new KiriminAja_Pickup_Request();
			new KiriminAja_Hooks_Product();
			new KiriminAja_Hooks_Order();
			new KiriminAja_Hooks_Addresses();
			new KiriminAja_Callback_Handler();

			$installed_version = get_option( 'kiriminaja_version', KIRIMINAJA_VERSION );
			if ( version_compare( $installed_version, KIRIMINAJA_VERSION, '<' ) ) {
				update_option( 'kiriminaja_version', KIRIMINAJA_VERSION, true );
			}
		}

		/**
		 * Load KiriminAja Shipping method
		 */
		public function load_shipping_method() {
			require_once KIRIMINAJA_PLUGIN_PATH . '/inc/class-kiriminaja-shipping-method.php';
		}

		/**
		 * Register KiriminAja Shipping Method
		 *
		 * @param  array $methods Currently registered methods.
		 * @return array          Registered methods.
		 */
		public function register_shipping_method( $methods ) {
			$methods['kiriminaja'] = 'KiriminAja_Shipping_Method';
			return $methods;
		}


		/**
		 * Updater
		 */

		public function request(){

			$remote = get_transient( $this->cache_key );

			if( false === $remote || ! $this->cache_allowed ) {

				$remote = wp_remote_get("https://storage.googleapis.com/tprt0ezsggqjornc7nf1wwluvgulhr/wp/woocommerce.json",
					array(
						'timeout' => 10,
						'headers' => array(
							'Accept' => 'application/json'
						)
					)
				);

				if(
					is_wp_error( $remote )
					|| 200 !== wp_remote_retrieve_response_code( $remote )
					|| empty( wp_remote_retrieve_body( $remote ) )
				) {
					return false;
				}

				set_transient( $this->cache_key, $remote, DAY_IN_SECONDS );

			}

			$remote = json_decode( wp_remote_retrieve_body( $remote ) );

			return $remote;

		}


		function info( $res, $action, $args ) {

			// print_r( $action );
			// print_r( $args );

			// do nothing if you're not getting plugin information right now
			if( 'plugin_information' !== $action ) {
				return $res;
			}

			// do nothing if it is not our plugin
			if( $this->plugin_slug !== $args->slug ) {
				return $res;
			}

			// get updates
			$remote = $this->request();

			if( ! $remote ) {
				return $res;
			}

			$res = new stdClass();

			$res->name = $remote->name;
			$res->slug = $remote->slug;
			$res->version = $remote->version;
			$res->tested = $remote->tested;
			$res->requires = $remote->requires;
			$res->author = $remote->author;
			$res->author_profile = $remote->author_profile;
			$res->download_link = $remote->download_url;
			$res->trunk = $remote->download_url;
			$res->requires_php = $remote->requires_php;
			$res->last_updated = $remote->last_updated;

			$res->sections = array(
				'description' => $remote->sections->description,
				'installation' => $remote->sections->installation,
				'changelog' => $remote->sections->changelog
			);

			if( ! empty( $remote->banners ) ) {
				$res->banners = array(
					'low' => $remote->banners->low,
					'high' => $remote->banners->high
				);
			}

			return $res;

		}

		public function update( $transient ) {

			if ( empty($transient->checked ) ) {
				return $transient;
			}

			$remote = $this->request();

			if(
				$remote
				&& version_compare( KIRIMINAJA_VERSION, $remote->version, '<' )
				&& version_compare( $remote->requires, get_bloginfo( 'version' ), '<=' )
				&& version_compare( $remote->requires_php, PHP_VERSION, '<' )
			) {
				$res = new stdClass();
				$res->slug = $this->plugin_slug;
				$res->plugin = plugin_basename( __FILE__ ); // misha-update-plugin/misha-update-plugin.php
				$res->new_version = $remote->version;
				$res->tested = $remote->tested;
				$res->package = $remote->download_url;

				$transient->response[ $res->plugin ] = $res;

			}

			return $transient;

		}

		public function purge( $upgrader, $options ){

			if (
				$this->cache_allowed
				&& 'update' === $options['action']
				&& 'plugin' === $options[ 'type' ]
			) {
				// just clean the cache when new plugin version is installed
				delete_transient( $this->cache_key );
			}

		}

	}

	// Initiate!.
	new KiriminAja();
}
