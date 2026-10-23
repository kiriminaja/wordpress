<?php
namespace KiriminAjaOfficial\Services;

use KiriminAjaOfficial\Repositories\OriginAddressMigrationRepository;
use KiriminAjaOfficial\Repositories\SettingRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Background, versioned repair of saved warehouse identities; never runs lookup during checkout. */
final class OriginAddressMigrationService {
	public const HOOK = 'kiriof_resolve_saved_origin_addresses';
	public const STATUS_OPTION = 'kiriof_origin_address_migration';
	private const VERSION = 1;
	private const LOCK_OPTION = 'kiriof_origin_address_migration_lock';
	private const LEASE = 120;
	private const RETRY_DELAY = 300;
	private const MAX_ATTEMPTS = 3;

	private ?OriginAddressMigrationRepository $repository;
	private ?SettingRepository $settings;
	private $lookup;
	private ?string $owner = null;

	/**
	 * @param OriginAddressMigrationRepository|null $repository Snapshot/atomic persistence boundary.
	 * @param SettingRepository|null               $settings   Credential availability boundary.
	 * @param callable|null                        $lookup     Authoritative postcode search; offline seam.
	 */
	public function __construct( ?OriginAddressMigrationRepository $repository = null, ?SettingRepository $settings = null, ?callable $lookup = null ) {
		$this->repository = $repository;
		$this->settings = $settings;
		$this->lookup = $lookup;
	}

	/** Register both upgrade detection and the bounded worker after schema/seeding. */
	public function register(): void {
		add_action( 'init', array( $this, 'schedule' ), 20, 0 );
		add_action( self::HOOK, array( $this, 'run' ), 10, 0 );
	}

	/** Schedule only; activation and normal page requests never perform an API lookup. */
	public function schedule(): void {
		if ( ! $this->configured() ) {
			return;
		}
		$state = $this->state();
		if ( in_array( $state['state'], array( 'complete', 'needs_review' ), true ) ) {
			return;
		}
		$this->enqueue( max( time(), (int) $state['next_after'] ) );
	}

	/** Process at most one warehouse and one postcode request per cron invocation. */
	public function run(): void {
		if ( ! $this->configured() ) {
			return;
		}
		$owner = $this->acquire();
		if ( null === $owner ) {
			$this->enqueue( time() + self::LEASE );
			return;
		}
		$this->owner = $owner;
		try {
			$state = $this->state();
			if ( in_array( $state['state'], array( 'complete', 'needs_review' ), true ) ) {
				return;
			}
			if ( $state['next_after'] > time() ) {
				$this->enqueue( $state['next_after'] );
				return;
			}
			$state['state'] = 'running';
			$state['next_after'] = time() + self::LEASE;
			add_option( self::STATUS_OPTION, $this->state(), '', false );
			$this->save( $state );
			// The current single cron event is already consumed. Leave crash recovery armed.
			$this->enqueue( $state['next_after'] );
			$this->repository = $this->repository ?? new OriginAddressMigrationRepository();
			try {
				$location = $this->repository->next( $state['cursor'] );
			} catch ( \Throwable $error ) {
				$state['state'] = 'retrying';
				$state['next_after'] = time() + self::RETRY_DELAY;
				$this->save( $state );
				$this->enqueue( $state['next_after'] );
				$this->log( 0, 'snapshot_unavailable' );
				return;
			}
			if ( null === $location ) {
				$state['state'] = $state['unresolved'] > 0 ? 'needs_review' : 'complete';
				$state['next_after'] = 0;
				$this->save( $state );
				return;
			}
			$id = (int) $location->id;
			$source = $this->source( $location );
			$code = isset( $location->_origin_error ) ? 'snapshot_conflict' : 'address_unmatched';
			$retry = false;
			$canonical = null;
			if ( null !== $source ) {
				try {
					$response = $this->lookup( $source['postcode'] );
					$body = $response['data'] ?? null;
					$rows = is_object( $body ) ? ( $body->result ?? null ) : null;
					if ( true !== ( $response['status'] ?? null ) || ! is_object( $body ) || true !== ( $body->status ?? null ) || ! is_array( $rows ) || ! array_is_list( $rows ) ) {
						$code = 'lookup_unavailable';
						$retry = true;
					} else {
						$canonical = OriginAddressMatcher::match( $location->sub_district_id ?? '', $source['label'], $source['postcode'], $rows );
					}
				} catch ( \Throwable $error ) {
					$code = 'lookup_unavailable';
					$retry = true;
				}
			}
			if ( null !== $canonical ) {
				if ( ! $this->ownsLease() ) {
					return;
				}
				try {
					if ( ! $this->repository->repair( $location, $canonical ) ) {
						$code = 'origin_changed_or_write_failed';
						$retry = true;
					} else {
						$state['resolved']++;
						if ( class_exists( '\WC_Cache_Helper' ) ) {
							\WC_Cache_Helper::get_transient_version( 'shipping', true );
						}
					}
				} catch ( \Throwable $error ) {
					$code = 'origin_changed_or_write_failed';
					$retry = true;
				}
			}
			if ( $retry && ++$state['attempts'] < self::MAX_ATTEMPTS ) {
				$state['state'] = 'retrying';
				$state['next_after'] = time() + self::RETRY_DELAY;
			} else {
				if ( null === $canonical || $retry ) {
					$state['unresolved']++;
					$this->log( $id, $code );
				}
				$state['cursor'] = $id;
				$state['attempts'] = 0;
				$state['state'] = 'scheduled';
				$state['next_after'] = time() + 1;
			}
			$this->save( $state );
			$this->enqueue( $state['next_after'] );
		} catch ( \Throwable $error ) {
			// The armed watchdog retries interruptions; a superseded worker publishes nothing.
			if ( $this->ownsLease() ) {
				$this->log( 0, 'migration_interrupted' );
			}
		} finally {
			$this->release( $owner );
			$this->owner = null;
		}
	}

	/** Return only a compatible saved label/postcode; never infer an origin from the street. */
	private function source( object $location ): ?array {
		if ( isset( $location->_origin_error ) ) {
			return null;
		}
		$country = $location->country ?? '';
		if ( ! is_string( $country ) || ! in_array( strtoupper( trim( $country ) ), array( '', 'ID' ), true ) ) {
			return null;
		}
		$label = $location->sub_district_name ?? '';
		$zip = $location->zip_code ?? '';
		$mirror = $location->_origin_mirror ?? null;
		if ( '' === $label && 1 === (int) ( $location->is_default ?? 0 ) && is_array( $mirror )
			&& (string) ( $mirror['origin_sub_district_id'] ?? '' ) === (string) ( $location->sub_district_id ?? '' )
			&& ( '' === $zip || (string) ( $mirror['origin_zip_code'] ?? '' ) === (string) $zip ) ) {
			$label = $mirror['origin_sub_district_name'] ?? '';
		}
		$postcode = OriginAddressMatcher::postcode( $label, $zip );
		if ( is_array( $mirror ) && null !== ( $mirror['origin_zip_code'] ?? null ) && '' !== $mirror['origin_zip_code']
			&& OriginAddressMatcher::postcode( $label, $mirror['origin_zip_code'] ) !== $postcode ) {
			return null;
		}
		return null === $postcode ? null : array( 'label' => $label, 'postcode' => $postcode );
	}

	private function configured(): bool {
		$this->settings = $this->settings ?? new SettingRepository();
		$key = $this->settings->getSettingByKey( 'api_key' );
		return is_object( $key ) && is_string( $key->value ?? null ) && '' !== trim( $key->value );
	}

	private function lookup( string $postcode ): array {
		if ( null !== $this->lookup ) {
			return ( $this->lookup )( $postcode );
		}
		return ( new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository() )->sub_district_search( $postcode );
	}

	/** Only fixed counters/cursor and scheduling metadata are stored, never address or API data. */
	private function state(): array {
		$state = get_option( self::STATUS_OPTION, array() );
		$defaults = array( 'version' => self::VERSION, 'state' => 'scheduled', 'cursor' => 0, 'attempts' => 0, 'resolved' => 0, 'unresolved' => 0, 'next_after' => 0 );
		if ( ! is_array( $state ) || ( $state['version'] ?? null ) !== self::VERSION ) {
			return $defaults;
		}
		foreach ( array( 'cursor', 'attempts', 'resolved', 'unresolved', 'next_after' ) as $key ) {
			if ( ! is_int( $state[ $key ] ?? null ) || $state[ $key ] < 0 ) {
				return $defaults;
			}
		}
		return array_merge( $defaults, array_intersect_key( $state, $defaults ) );
	}

	private function save( array $state ): void {
		global $wpdb;
		if ( ! $this->ownsLease() ) {
			throw new \RuntimeException( 'Origin migration lease expired.' );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fence progress writes against the exact current lease owner.
		$written = $wpdb->query( $wpdb->prepare(
			'UPDATE %i AS progress INNER JOIN %i AS lease ON lease.option_name = %s AND lease.option_value = %s SET progress.option_value = %s WHERE progress.option_name = %s',
			$wpdb->options, $wpdb->options, self::LOCK_OPTION, $this->owner, maybe_serialize( $state ), self::STATUS_OPTION
		) );
		wp_cache_delete( self::STATUS_OPTION, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		if ( false === $written || ( 0 === $written && ( ! $this->ownsLease() || get_option( self::STATUS_OPTION ) !== $state ) ) ) {
			throw new \RuntimeException( 'Origin migration status could not be saved.' );
		}
	}

	/** Read the lease directly: cached options must not authorize a superseded worker. */
	private function ownsLease(): bool {
		global $wpdb;
		if ( null === $this->owner || (int) explode( ':', $this->owner, 2 )[0] <= time() ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lease ownership is a live concurrency condition.
		return $this->owner === $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, self::LOCK_OPTION ) );
	}

	private function enqueue( int $when ): void {
		$scheduled = wp_next_scheduled( self::HOOK );
		if ( $scheduled && $scheduled > $when ) {
			wp_unschedule_event( $scheduled, self::HOOK );
			$scheduled = false;
		}
		if ( ! $scheduled ) {
			wp_schedule_single_event( $when, self::HOOK );
		}
	}

	private function acquire(): ?string {
		$owner = ( time() + self::LEASE ) . ':' . wp_generate_uuid4();
		if ( add_option( self::LOCK_OPTION, $owner, '', false ) ) {
			return $owner;
		}
		$old = get_option( self::LOCK_OPTION, '' );
		if ( is_string( $old ) && preg_match( '/\A([0-9]+):.+\z/', $old, $match ) && (int) $match[1] < time()
			&& $this->release( $old ) && add_option( self::LOCK_OPTION, $owner, '', false ) ) {
			return $owner;
		}
		return null;
	}

	/** Atomic owner check prevents an expired worker from releasing its successor's lease. */
	private function release( string $owner ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Options API has no compare-and-delete lease operation.
		$removed = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s AND option_value = %s', $wpdb->options, self::LOCK_OPTION, $owner ) );
		if ( 1 === $removed ) {
			wp_cache_delete( self::LOCK_OPTION, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
		}
		return 1 === $removed;
	}

	private function log( int $id, string $code ): void {
		if ( function_exists( 'kiriof_log' ) ) {
			kiriof_log( 'warning', 'Saved warehouse address needs verification.', array( 'operation' => 'migrate_origin_address', 'location_id' => $id, 'code' => $code, 'backtrace' => false ), 'kiriminaja_shipping' );
		}
	}
}
