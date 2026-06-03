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
        wp_schedule_single_event( time() + 5, self::CRON_HOOK );

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
        $regionRepo = new ShippingDiscountRegionRepository();
        $provinceService = ( new KiriminajaApiService() )->getProvinces();

        if ( 200 !== $provinceService->status ) {
            $this->updateStatus( 'error', $provinceService->message ?? __( 'Failed to refresh province data.', 'kiriminaja-official' ) );
            return self::error( array(), $provinceService->message ?? __( 'Failed to refresh province data.', 'kiriminaja-official' ) );
        }

        $provinces = $this->normalizeRows( $provinceService->data, 'province' );
        $regionRepo->upsertProvinces( $provinces );

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
        $repo = new ShippingDiscountRegionRepository();
        $repo->upsertCities( $provinceId, $cities );

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
            $id = (int) ( $row->id ?? $row->{$kind . '_id'} ?? 0 );
            $name = (string) ( $row->name ?? $row->{$kind . '_name'} ?? $row->text ?? '' );

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
}