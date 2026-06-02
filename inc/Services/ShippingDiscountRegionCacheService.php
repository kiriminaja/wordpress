<?php
namespace KiriminAjaOfficial\Services;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;
use KiriminAjaOfficial\Repositories\ShippingDiscountRegionRepository;

class ShippingDiscountRegionCacheService extends BaseService {
    public function refreshAll() {
        $regionRepo = new ShippingDiscountRegionRepository();
        $provinceService = ( new KiriminajaApiService() )->getProvinces();

        if ( 200 !== $provinceService->status ) {
            return self::error( array(), $provinceService->message ?? __( 'Failed to refresh province data.', 'kiriminaja-official' ) );
        }

        $provinces = $this->normalizeRows( $provinceService->data, 'province' );
        $regionRepo->upsertProvinces( $provinces );

        foreach ( $provinces as $province ) {
            $this->refreshProvinceCities( (int) $province['id'] );
        }

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