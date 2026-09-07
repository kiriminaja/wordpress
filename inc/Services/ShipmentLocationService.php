<?php

namespace KiriminAjaOfficial\Services;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Repositories\ShipmentLocationRepository;
use KiriminAjaOfficial\Repositories\SettingRepository;

/**
 * ShipmentLocationService
 *
 * Business logic for the "Shipment Locations" address book:
 *  - CRUD pass-through to the repository
 *  - Seeding/migration of the default location from the legacy global origin
 *  - Resolving the effective origin for a seller-selected pickup location,
 *    falling back to the active default location
 *  - Mapping a location row to the origin array shape the pricing and
 *    transaction services already understand.
 */
class ShipmentLocationService
{
    /**
     * @var ShipmentLocationRepository
     */
    private $repository;

    /**
     * In-request cache of resolved default location.
     *
     * @var object|null|false
     */
    private $defaultCache = null;

    public function __construct()
    {
        $this->repository = new ShipmentLocationRepository();
    }

    /**
     * @return ShipmentLocationRepository
     */
    public function repository()
    {
        return $this->repository;
    }

    /**
     * Whether at least one shipment location row exists.
     *
     * @return bool
     */
    public function hasLocations()
    {
        return $this->repository->count() > 0;
    }

    /**
     * Seed the default shipment location from the legacy global origin
     * settings when no locations exist yet. Idempotent.
     *
     * @return int|false Location ID on success, false otherwise.
     */
    public function seedDefaultFromGlobalOrigin()
    {
        if ($this->hasLocations()) {
            return false;
        }

        $settings = (new SettingRepository())->getSettingByArray(array(
            'origin_name',
            'origin_phone',
            'origin_address',
            'origin_sub_district_id',
            'origin_zip_code',
            'origin_latitude',
            'origin_longitude',
        ));

        $origin = array();
        if (is_array($settings)) {
            foreach ($settings as $setting) {
                $origin[$setting->key] = $setting->value;
            }
        }

        // Only seed when the merchant has actually configured an origin.
        $hasOrigin = !empty($origin['origin_address'])
            || !empty($origin['origin_sub_district_id'])
            || !empty($origin['origin_zip_code']);

        // Fall back to the native WooCommerce Store Address values so the
        // default location is always populated from the existing store data.
        if (!$hasOrigin) {
            $origin['origin_address']  = (string) get_option( 'woocommerce_store_address', '' );
            $origin['origin_zip_code'] = (string) get_option( 'woocommerce_store_postcode', '' );
            $hasOrigin                 = '' !== $origin['origin_address'] || '' !== $origin['origin_zip_code'];
        }

        // Always backfill the remaining native Store Address fields so the
        // seeded default location mirrors the full WooCommerce store address.
        $origin['origin_address_2'] = (string) get_option( 'woocommerce_store_address_2', '' );
        $origin['origin_city']      = (string) get_option( 'woocommerce_store_city', '' );
        $origin['origin_country']   = (string) get_option( 'woocommerce_default_country', '' );

        if (!$hasOrigin) {
            return false;
        }

        $seed_country_state = isset($origin['origin_country']) ? $origin['origin_country'] : '';
        $seed_country       = $seed_country_state;
        $seed_state         = '';
        if (false !== strpos($seed_country_state, ':')) {
            list($seed_country, $seed_state) = explode(':', $seed_country_state, 2);
        }

        return $this->repository->insert(array(
            'name'            => !empty($origin['origin_name']) ? $origin['origin_name'] : __('Default Location', 'kiriminaja-official'),
            'phone'           => isset($origin['origin_phone']) ? $origin['origin_phone'] : '',
            'address'         => isset($origin['origin_address']) ? $origin['origin_address'] : '',
            'sub_district_id' => isset($origin['origin_sub_district_id']) ? (int) $origin['origin_sub_district_id'] : 0,
            'zip_code'        => isset($origin['origin_zip_code']) ? $origin['origin_zip_code'] : '',
            'address_2'       => isset($origin['origin_address_2']) ? $origin['origin_address_2'] : '',
            'city'            => isset($origin['origin_city']) ? $origin['origin_city'] : '',
            'country'         => $seed_country,
            'state'           => $seed_state,
            'latitude'        => isset($origin['origin_latitude']) ? $origin['origin_latitude'] : '',
            'longitude'       => isset($origin['origin_longitude']) ? $origin['origin_longitude'] : '',
            'is_default'      => 1,
            'is_active'       => 1,
        ));
    }

    /**
     * Get the default location, seeding from the global origin if needed.
     *
     * @return object|null
     */
    public function getDefaultLocation()
    {
        if (null !== $this->defaultCache) {
            return $this->defaultCache ?: null;
        }

        $default = $this->repository->getDefault();
        if (!$default) {
            $this->seedDefaultFromGlobalOrigin();
            $default = $this->repository->getDefault();
        }

        $this->defaultCache = $default ? $default : false;
        return $default ? $default : null;
    }

    /**
     * Get a location row by ID, falling back to the default when missing,
     * inactive, or when no ID is provided.
     *
     * @param int $locationId
     * @return object|null
     */
    public function getLocationOrDefault($locationId)
    {
        $locationId = (int) $locationId;
        if ($locationId > 0) {
            $location = $this->repository->getById($locationId);
            if ($location && (int) $location->is_active === 1) {
                return $location;
            }
        }
        return $this->getDefaultLocation();
    }

    /**
     * Resolve the origin array for a seller-selected pickup location.
     *
     * @param int $locationId Selected location ID (0 = default).
     * @return array Origin array in the shape pricing/transaction services expect.
     */
    public function originForLocation($locationId)
    {
        return $this->locationToOrigin($this->getLocationOrDefault($locationId));
    }

    /**
     * Map a location row to the origin array shape used by pricing/transaction
     * services. Returns an empty array when the location is invalid.
     *
     * @param object|null $location
     * @return array
     */
    public function locationToOrigin($location)
    {
        if (!$location || !is_object($location)) {
            return array();
        }

        return array(
            'id'                     => (int) $location->id,
            'location_id'            => (int) $location->id,
            'location_name'          => (string) $location->name,
            'origin_name'            => (string) $location->name,
            'origin_phone'           => (string) $location->phone,
            'origin_address'         => (string) $location->address,
            'origin_address_2'       => (string) ( $location->address_2 ?? '' ),
            'origin_sub_district'    => (string) ( $location->sub_district_name ?? '' ),
            'origin_city'            => (string) ( $location->city ?? '' ),
            'origin_state'           => (string) ( $location->state ?? '' ),
            'origin_country'         => (string) ( $location->country ?? '' ),
            'origin_sub_district_id' => (int) $location->sub_district_id,
            'origin_zip_code'        => (string) $location->zip_code,
            'origin_latitude'        => (string) $location->latitude,
            'origin_longitude'       => (string) $location->longitude,
        );
    }

    /**
     * Build a compact human-readable address for a shipment location.
     *
     * @param object|array|null $location Location row or origin snapshot.
     * @return string
     */
    public function formatAddress($location)
    {
        $data = is_object($location) ? get_object_vars($location) : (array) $location;
        $lines = array_filter(array(
            trim((string) ($data['address'] ?? $data['origin_address'] ?? '')),
            trim((string) ($data['address_2'] ?? $data['origin_address_2'] ?? '')),
            implode(', ', array_filter(array(
                trim((string) ($data['sub_district_name'] ?? $data['origin_sub_district'] ?? '')),
                trim((string) ($data['city'] ?? $data['origin_city'] ?? '')),
                trim((string) ($data['state'] ?? $data['origin_state'] ?? '')),
                trim((string) ($data['zip_code'] ?? $data['origin_zip_code'] ?? '')),
            ))),
        ));

        return implode(' · ', $lines);
    }
}
