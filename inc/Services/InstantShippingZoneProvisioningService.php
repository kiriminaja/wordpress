<?php
namespace KiriminAjaOfficial\Services;

use KiriminAjaOfficial\Repositories\SettingRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Adds Instant companions without changing a merchant's existing zone methods. */
class InstantShippingZoneProvisioningService {
	private const EXPRESS_ID = 'kiriminaja-official';
	private const INSTANT_ID = 'kiriminaja-instant';
	private const LOCK_TTL = 120;

	private ?SettingRepository $settings;
	private bool $running = false;

	public function __construct( ?SettingRepository $settings = null ) {
		$this->settings = $settings;
	}

	public function register(): void {
		add_action( 'kiriof_courier_settings_saved', array( $this, 'sync' ), 10, 0 );
		add_action( 'woocommerce_shipping_zone_method_added', array( $this, 'methodAdded' ), 10, 3 );
		add_action( 'woocommerce_shipping_zone_method_status_toggled', array( $this, 'methodActivated' ), 10, 4 );
	}

	public function sync(): array {
		return $this->synchronize( null );
	}

	public function methodAdded( $instance_id, $method_id, $zone_id ): void {
		if ( self::EXPRESS_ID === $method_id ) {
			$this->synchronize( (int) $zone_id );
		}
	}

	public function methodActivated( $instance_id, $method_id, $zone_id, $status ): void {
		if ( self::EXPRESS_ID === $method_id && 1 === (int) $status ) {
			$this->synchronize( (int) $zone_id );
		}
	}

	private function synchronize( ?int $only_zone ): array {
		$report = array( 'added' => array(), 'skipped' => array() );
		if ( $this->running || ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return $report;
		}
		if ( ! class_exists( '\WC_Shipping_Zones' ) || ! class_exists( '\WC_Shipping_Zone' ) || ! function_exists( 'apply_filters' ) || ! function_exists( 'add_option' ) ) {
			return $report;
		}

		$this->running = true;
		try {
			$methods = apply_filters( 'woocommerce_shipping_methods', array() );
			if ( ! is_array( $methods ) || empty( $methods[ self::INSTANT_ID ] ) || ! $this->hasInstantServices() ) {
				return $report;
			}
			$zone_ids = array();
			if ( null !== $only_zone ) {
				if ( $only_zone < 0 ) {
					return $report;
				}
				$zone_ids[] = $only_zone;
			} else {
				foreach ( \WC_Shipping_Zones::get_zones() as $key => $data ) {
					$zone_ids[] = (int) ( $data['zone_id'] ?? $key );
				}
				$zone_ids[] = 0;
			}
			foreach ( array_unique( $zone_ids ) as $zone_id ) {
				$this->syncZone( $zone_id, $report );
			}
			if ( ! empty( $report['added'] ) && class_exists( '\WC_Cache_Helper' ) ) {
				\WC_Cache_Helper::get_transient_version( 'shipping', true );
			}
		} catch ( \Throwable $error ) {
			$this->log( $only_zone ?? 0, 0, 'sync_failed' );
		} finally {
			$this->running = false;
		}
		return $report;
	}

	private function hasInstantServices(): bool {
		$this->settings = $this->settings ?? new SettingRepository();
		$selection = $this->settings->getCourierServiceSelection();
		foreach ( array( 'gosend', 'grab_express' ) as $courier ) {
			foreach ( (array) ( $selection[ $courier ] ?? array() ) as $service ) {
				if ( is_string( $service ) && '' !== trim( $service ) && false === strpos( $service, '*' ) ) {
					return true;
				}
			}
		}
		return false;
	}

	private function syncZone( int $zone_id, array &$report ): void {
		$lock_key = 'kiriof_instant_zone_lock_' . $zone_id;
		$owner = null;
		$instance_id = 0;
		try {
			$code = $this->zoneSkipCode( new \WC_Shipping_Zone( $zone_id ) );
			if ( null !== $code ) {
				$report['skipped'][ $zone_id ] = $code;
				return;
			}
			$owner = $this->acquireLock( $lock_key );
			if ( null === $owner ) {
				$report['skipped'][ $zone_id ] = 'busy';
				return;
			}
			// Another request may have added a companion before this lock was acquired.
			$zone = new \WC_Shipping_Zone( $zone_id );
			$code = $this->zoneSkipCode( $zone );
			if ( null !== $code ) {
				$report['skipped'][ $zone_id ] = $code;
				return;
			}
			$instance_id = (int) $zone->add_shipping_method( self::INSTANT_ID );
			if ( $instance_id <= 0 ) {
				$report['skipped'][ $zone_id ] = 'add_failed';
				$this->log( $zone_id, 0, 'add_failed' );
				return;
			}
			// Woo owns enabled defaults, ordering, instance settings and cache eviction.
			$report['added'][ $zone_id ] = $instance_id;
			foreach ( ( new \WC_Shipping_Zone( $zone_id ) )->get_shipping_methods( false ) as $key => $method ) {
				if ( self::INSTANT_ID === ( $method->id ?? '' ) && $instance_id === (int) ( $method->instance_id ?? $key ) && $this->isEnabled( $method ) ) {
					return;
				}
			}
			$report['skipped'][ $zone_id ] = 'added_not_enabled';
			$this->log( $zone_id, $instance_id, 'added_not_enabled' );
		} catch ( \Throwable $error ) {
			$report['skipped'][ $zone_id ] = 'zone_failed';
			$this->log( $zone_id, $instance_id, 'zone_failed' );
		} finally {
			if ( null !== $owner ) {
				$this->compareDeleteLock( $lock_key, $owner );
			}
		}
	}

	private function zoneSkipCode( \WC_Shipping_Zone $zone ): ?string {
		$express = false;
		$instant = null;
		foreach ( $zone->get_shipping_methods( false ) as $method ) {
			if ( self::EXPRESS_ID === ( $method->id ?? '' ) && $this->isEnabled( $method ) ) {
				$express = true;
			}
			if ( self::INSTANT_ID === ( $method->id ?? '' ) ) {
				$instant = $this->isEnabled( $method ) ? 'instant_exists' : 'instant_disabled';
			}
		}
		return $express ? $instant : 'express_disabled_or_missing';
	}

	private function isEnabled( $method ): bool {
		// Zone rows override this property; is_enabled() can apply buyer-specific gates.
		return 'yes' === ( $method->enabled ?? 'no' );
	}

	private function acquireLock( string $key ): ?string {
		$owner = (string) ( time() + self::LOCK_TTL ) . ':' . wp_generate_uuid4();
		if ( add_option( $key, $owner, '', false ) ) {
			return $owner;
		}
		$existing = get_option( $key, '' );
		if ( is_string( $existing ) && preg_match( '/^([0-9]+):.+$/', $existing, $match ) && (int) $match[1] < time() ) {
			// Compare-and-delete prevents an expired owner from deleting a successor.
			if ( $this->compareDeleteLock( $key, $existing ) && add_option( $key, $owner, '', false ) ) {
				return $owner;
			}
		}
		return null;
	}

	private function compareDeleteLock( string $key, string $owner ): bool {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_callable( array( $wpdb, 'query' ) ) || ! is_callable( array( $wpdb, 'prepare' ) ) ) {
			return false;
		}
		// Options API has no atomic conditional delete. The lock is non-autoloaded.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				$key,
				$owner
			)
		);
		if ( $deleted ) {
			wp_cache_delete( $key, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
		}
		return 1 === $deleted;
	}

	private function log( int $zone_id, int $instance_id, string $code ): void {
		if ( function_exists( 'kiriof_log' ) ) {
			kiriof_log( 'warning', 'Instant shipping zone provisioning failed.', array( 'zone_id' => $zone_id, 'instance_id' => $instance_id, 'code' => $code ), 'kiriminaja_instant' );
		}
	}
}
