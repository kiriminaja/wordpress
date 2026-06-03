<?php
namespace KiriminAjaOfficial\Repositories;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ShippingDiscountRegionRepository {
    private $wpdb;
    private string $provincesTable;
    private string $citiesTable;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->provincesTable = $wpdb->prefix . 'kiriminaja_provinces';
        $this->citiesTable = $wpdb->prefix . 'kiriminaja_cities';
    }

    public function getProvinces(): array {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $this->wpdb->get_results( "SELECT id, name, updated_at FROM {$this->provincesTable} ORDER BY name ASC" );
        return is_array( $results ) ? $results : array();
    }

    public function getCitiesByProvinceId( $provinceId ): array {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT id, province_id, name, updated_at FROM {$this->citiesTable} WHERE province_id = %d ORDER BY name ASC",
                absint( $provinceId )
            )
        );
        return is_array( $results ) ? $results : array();
    }

    public function getCities(): array {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $this->wpdb->get_results( "SELECT id, province_id, name, updated_at FROM {$this->citiesTable} ORDER BY province_id ASC, name ASC" );
        return is_array( $results ) ? $results : array();
    }

    public function getCitiesByProvinceIds( array $provinceIds ): array {
        $provinceIds = array_values( array_filter( array_map( 'absint', $provinceIds ) ) );
        if ( empty( $provinceIds ) ) {
            return array();
        }

        $placeholders = implode( ', ', array_fill( 0, count( $provinceIds ), '%d' ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT id, province_id, name, updated_at FROM {$this->citiesTable} WHERE province_id IN ({$placeholders}) ORDER BY province_id ASC, name ASC",
                $provinceIds
            )
        );

        return is_array( $results ) ? $results : array();
    }

    public function upsertProvinces( array $provinces ): bool {
        $timestamp = gmdate( 'Y-m-d H:i:s' );
        foreach ( $provinces as $province ) {
            if ( empty( $province['id'] ) || empty( $province['name'] ) ) {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->wpdb->replace(
                $this->provincesTable,
                array(
                    'id' => absint( $province['id'] ),
                    'name' => sanitize_text_field( $province['name'] ),
                    'updated_at' => $timestamp,
                ),
                array( '%d', '%s', '%s' )
            );
        }

        return empty( $this->wpdb->last_error );
    }

    public function upsertCities( $provinceId, array $cities ): bool {
        $timestamp = gmdate( 'Y-m-d H:i:s' );
        foreach ( $cities as $city ) {
            if ( empty( $city['id'] ) || empty( $city['name'] ) ) {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->wpdb->replace(
                $this->citiesTable,
                array(
                    'id' => absint( $city['id'] ),
                    'province_id' => absint( $provinceId ),
                    'name' => sanitize_text_field( $city['name'] ),
                    'updated_at' => $timestamp,
                ),
                array( '%d', '%d', '%s', '%s' )
            );
        }

        return empty( $this->wpdb->last_error );
    }

    public function isCacheStale( int $ttl = DAY_IN_SECONDS ): bool {
        $provinceCount = $this->getProvinceCount();
        $cityCount = $this->getCityCount();
        if ( $provinceCount < 1 || $cityCount < 1 ) {
            return true;
        }

        $latestUpdatedAt = $this->getLatestUpdatedAt();
        if ( empty( $latestUpdatedAt ) ) {
            return true;
        }

        return ( time() - strtotime( $latestUpdatedAt ) ) > $ttl;
    }

    public function getProvinceCount(): int {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->provincesTable}" );
        return (int) $count;
    }

    public function getCityCount(): int {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->citiesTable}" );
        return (int) $count;
    }

    public function getLatestUpdatedAt(): ?string {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $provinceUpdatedAt = $this->wpdb->get_var( "SELECT MAX(updated_at) FROM {$this->provincesTable}" );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $cityUpdatedAt = $this->wpdb->get_var( "SELECT MAX(updated_at) FROM {$this->citiesTable}" );

        $candidates = array_filter( array( $provinceUpdatedAt, $cityUpdatedAt ) );
        if ( empty( $candidates ) ) {
            return null;
        }

        rsort( $candidates );
        return (string) $candidates[0];
    }
}