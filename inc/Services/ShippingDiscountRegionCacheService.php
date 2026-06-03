<?php
namespace KiriminAjaOfficial\Services;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;
use KiriminAjaOfficial\Repositories\ShippingDiscountRegionRepository;

class ShippingDiscountRegionCacheService extends BaseService {
    public const CRON_HOOK = 'kiriof_refresh_coupon_regions_cache';
    private const STATUS_OPTION = 'kiriof_region_cache_status';

    public function scheduleRefresh( bool $force = false ): bool {
        if ( ! function_exists( 'wp_schedule_single_event' ) ) {
            return false;
        }

        if ( ! $force && $this->isRefreshPending() ) {
            return false;
        }

        if ( $force && function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_unschedule_event' ) ) {
            $timestamp = wp_next_scheduled( self::CRON_HOOK );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, self::CRON_HOOK );
            }
        }

        $this->updateStatus( 'scheduled' );
        wp_schedule_single_event( time(), self::CRON_HOOK );

        return true;
    }

    public function isRefreshPending(): bool {
        $status = $this->getStatus();
        if ( in_array( $status['state'], array( 'scheduled', 'running' ), true ) ) {
            return true;
        }

        return function_exists( 'wp_next_scheduled' ) && (bool) wp_next_scheduled( self::CRON_HOOK );
    }

    public function getStatus(): array {
        $status = get_option( self::STATUS_OPTION, array() );

        return wp_parse_args(
            is_array( $status ) ? $status : array(),
            array(
                'state' => 'idle',
                'last_error' => '',
                'last_completed_at' => '',
            )
        );
    }

    public function refreshAll() {
        $this->updateStatus( 'running' );

        // Allow enough time for sequential API calls across all provinces.
        if ( function_exists( 'set_time_limit' ) ) {
            set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- needed to allow sufficient time for sequential per-province API calls
        }

        // Ensure the cache tables exist before writing (handles cases where
        // the plugin was updated without re-running the activation hook).
        if ( class_exists( '\KiriminAjaOfficial\Migration\SetupMigration' ) ) {
            ( new \KiriminAjaOfficial\Migration\SetupMigration() )->register();
        }

        $regionRepo      = new ShippingDiscountRegionRepository();
        $provinceService = ( new KiriminajaApiService() )->getProvinces();

        if ( 200 !== $provinceService->status ) {
            // API failed — try seeding from bundled JSON as fallback.
            $seeded = $this->seedFromBundledData( $regionRepo );
            if ( 200 === $seeded->status ) {
                $this->updateStatus( 'ready' );
                return $seeded;
            }
            $this->updateStatus( 'error', $provinceService->message ?? __( 'Failed to refresh province data.', 'kiriminaja-official' ) );
            return self::error( array(), $provinceService->message ?? __( 'Failed to refresh province data.', 'kiriminaja-official' ) );
        }

        $provinces = $this->normalizeRows( $provinceService->data, 'province' );
        if ( empty( $provinces ) ) {
            $message = __( 'Province data could not be normalized from the KiriminAja API response.', 'kiriminaja-official' );
            $this->updateStatus( 'error', $message );
            return self::error( array(), $message );
        }

        if ( ! $regionRepo->upsertProvinces( $provinces ) || $regionRepo->getProvinceCount() < 1 ) {
            global $wpdb;
            $dbErr   = ! empty( $wpdb->last_error ) ? ' DB: ' . $wpdb->last_error : '';
            $message = __( 'Failed to save province data to database.', 'kiriminaja-official' ) . $dbErr;
            $this->updateStatus( 'error', $message );
            return self::error( array(), $message );
        }

        foreach ( $provinces as $province ) {
            $cityRefresh = $this->refreshProvinceCities( (int) $province['id'] );
            if ( 200 !== $cityRefresh->status ) {
                $this->updateStatus( 'error', $cityRefresh->message ?? __( 'Failed to refresh city data.', 'kiriminaja-official' ) );
                return $cityRefresh;
            }
        }

        $this->updateStatus( 'ready' );

        return self::success(
            array(
                'province_count' => $regionRepo->getProvinceCount(),
                'city_count' => $regionRepo->getCityCount(),
                'updated_at' => $regionRepo->getLatestUpdatedAt(),
            ),
            __( 'Region cache updated.', 'kiriminaja-official' )
        );
    }

    public function refreshProvinceCities( int $provinceId ) {
        if ( $provinceId < 1 ) {
            return self::error( array(), __( 'Invalid province.', 'kiriminaja-official' ) );
        }

        $cityService = ( new KiriminajaApiService() )->getCitiesByProvinceId( $provinceId );
        if ( 200 !== $cityService->status ) {
            return self::error( array(), $cityService->message ?? __( 'Failed to refresh city data.', 'kiriminaja-official' ) );
        }

        $cities = $this->normalizeRows( $cityService->data, 'city' );
        if ( empty( $cities ) ) {
            return self::error(
                array(),
                sprintf(
                    // translators: %d is the province ID number.
                    __( 'City data for province %d could not be normalized from the KiriminAja API response.', 'kiriminaja-official' ),
                    $provinceId
                )
            );
        }

        $repo = new ShippingDiscountRegionRepository();
        if ( ! $repo->upsertCities( $provinceId, $cities ) ) {
            return self::error( array(), __( 'Failed to refresh city data.', 'kiriminaja-official' ) );
        }

        return self::success( array( 'city_count' => count( $cities ) ), __( 'City cache updated.', 'kiriminaja-official' ) );
    }

    private function updateStatus( string $state, string $lastError = '' ): void {
        update_option(
            self::STATUS_OPTION,
            array(
                'state' => $state,
                'last_error' => $lastError,
                'last_completed_at' => in_array( $state, array( 'ready', 'error' ), true ) ? gmdate( 'Y-m-d H:i:s' ) : '',
            ),
            false
        );
    }

    private function normalizeRows( $rows, string $kind ): array {
        $normalized = array();
        foreach ( (array) $rows as $row ) {
            $row = (object) $row;
            $idCandidates = array(
                $row->id ?? null,
                $row->{$kind . '_id'} ?? null,
                $row->province_id ?? null,
                $row->provinsi_id ?? null,
                $row->city_id ?? null,
                $row->kabupaten_id ?? null,
            );
            $id = 0;

            foreach ( $idCandidates as $candidate ) {
                if ( is_numeric( $candidate ) && (int) $candidate > 0 ) {
                    $id = (int) $candidate;
                    break;
                }
            }

            $nameCandidates = array(
                $row->name ?? null,
                $row->{$kind . '_name'} ?? null,
                $row->provinsi_name ?? null,
                $row->kabupaten_name ?? null,
                $row->text ?? null,
            );
            $name = '';

            foreach ( $nameCandidates as $candidate ) {
                if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
                    $name = $candidate;
                    break;
                }
            }

            if ( $id < 1 || '' === $name ) {
                continue;
            }

            $normalized[] = array(
                'id' => $id,
                'name' => $name,
            );
        }

        return $normalized;
    }

    /**
     * Seed provinces and cities from the bundled regions.json file.
     * Used as a fallback when the live API is unreachable.
     */
    public function seedFromBundledData( ShippingDiscountRegionRepository $regionRepo ): \KiriminAjaOfficial\Utils\ServiceResponse {
        $jsonPath = dirname( __DIR__ ) . '/Data/regions.json';
        if ( ! file_exists( $jsonPath ) ) {
            return self::error( array(), __( 'Bundled region data file not found.', 'kiriminaja-official' ) );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $raw = file_get_contents( $jsonPath );
        if ( false === $raw ) {
            return self::error( array(), __( 'Failed to read bundled region data.', 'kiriminaja-official' ) );
        }

        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) || empty( $data['provinces'] ) || empty( $data['cities'] ) ) {
            return self::error( array(), __( 'Bundled region data is invalid.', 'kiriminaja-official' ) );
        }

        if ( ! $regionRepo->upsertProvinces( $data['provinces'] ) ) {
            return self::error( array(), __( 'Failed to seed province data from bundle.', 'kiriminaja-official' ) );
        }

        // Group cities by province and upsert.
        $citiesByProvince = array();
        foreach ( $data['cities'] as $city ) {
            $pid = (int) ( $city['province_id'] ?? 0 );
            if ( $pid < 1 ) {
                continue;
            }
            $citiesByProvince[ $pid ][] = $city;
        }

        foreach ( $citiesByProvince as $pid => $cities ) {
            $regionRepo->upsertCities( $pid, $cities );
        }

        return self::success(
            array(
                'province_count' => $regionRepo->getProvinceCount(),
                'city_count'     => $regionRepo->getCityCount(),
                'source'         => 'bundled',
            ),
            __( 'Region data loaded from bundled file.', 'kiriminaja-official' )
        );
    }
}
