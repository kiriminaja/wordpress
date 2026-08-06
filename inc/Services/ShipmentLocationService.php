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
 * Business logic for the multi-origin "Shipment Locations" feature:
 *  - CRUD pass-through to the repository
 *  - Seeding/migration of the default location from the legacy global origin
 *  - Resolving the effective origin for a product / variation / cart item
 *    (variation override -> parent product -> active default location)
 *  - Grouping a WooCommerce package into per-origin sub-packages
 *  - Mapping a location row to the origin array shape the pricing and
 *    transaction services already understand.
 */
class ShipmentLocationService
{
    /**
     * Order item / product meta key that stores the bound shipment location ID.
     */
    const META_KEY = '_kiriminaja_shipment_location_id';

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

    /** Register WooCommerce integration hooks. */
    public function register()
    {
        add_filter('woocommerce_cart_shipping_packages', array($this, 'splitCartShippingPackages'), 20);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'storeOrderItemLocation'), 10, 4);
    }

    /**
     * Split WooCommerce shipping packages by their resolved origin location.
     *
     * @param array $packages Shipping packages.
     * @return array
     */
    public function splitCartShippingPackages($packages)
    {
        $split = array();
        foreach ((array) $packages as $package) {
            foreach ($this->splitPackageByOrigin($package) as $originPackage) {
                $locationId = isset($originPackage['location_id']) ? (int) $originPackage['location_id'] : 0;
                $originPackage['kiriof_shipment_location_id'] = $locationId;
                $originPackage['kiriof_shipment_location'] = $this->getLocationOrDefault($locationId);
                $split[] = $originPackage;
            }
        }

        return empty($split) ? $packages : $split;
    }

    /**
     * Persist the resolved origin so later fulfillment is independent from
     * subsequent product setting changes.
     *
     * @param \WC_Order_Item_Product $item Order item.
     * @param string $cartItemKey Cart item key.
     * @param array $values Cart item values.
     * @param \WC_Order $order Order.
     * @return void
     */
    public function storeOrderItemLocation($item, $cartItemKey, $values, $order)
    {
        if (empty($values['data']) || !is_object($values['data'])) {
            return;
        }

        $productId = method_exists($values['data'], 'get_parent_id') ? (int) $values['data']->get_parent_id() : (int) $values['data']->get_id();
        $variationId = method_exists($values['data'], 'get_parent_id') ? (int) $values['data']->get_id() : 0;
        $location = $this->resolveProductLocation($productId, $variationId);
        if ($location) {
            $item->add_meta_data(self::META_KEY, (int) $location->id, true);
        }
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

        if (!$hasOrigin) {
            return false;
        }

        return $this->repository->insert(array(
            'name'            => !empty($origin['origin_name']) ? $origin['origin_name'] : __('Default Location', 'kiriminaja-official'),
            'phone'           => isset($origin['origin_phone']) ? $origin['origin_phone'] : '',
            'address'         => isset($origin['origin_address']) ? $origin['origin_address'] : '',
            'sub_district_id' => isset($origin['origin_sub_district_id']) ? (int) $origin['origin_sub_district_id'] : 0,
            'zip_code'        => isset($origin['origin_zip_code']) ? $origin['origin_zip_code'] : '',
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
     * Get a location row by ID, falling back to the default when missing.
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
     * Resolve the effective shipment location ID for a product/variation.
     *
     * Precedence: variation meta -> parent product meta -> 0 (use default).
     *
     * @param int $productId   Product or variation ID.
     * @param int $variationId Variation ID when the cart item is a variation.
     * @return int Location ID or 0 when none is explicitly bound.
     */
    public function resolveProductLocationId($productId, $variationId = 0)
    {
        $variationId = (int) $variationId;
        $productId   = (int) $productId;

        if ($variationId > 0) {
            $bound = (int) get_post_meta($variationId, self::META_KEY, true);
            if ($bound > 0) {
                return $bound;
            }
        }

        if ($productId > 0) {
            $bound = (int) get_post_meta($productId, self::META_KEY, true);
            if ($bound > 0) {
                return $bound;
            }
        }

        return 0;
    }

    /**
     * Resolve the effective shipment location row for a product/variation.
     *
     * @param int $productId
     * @param int $variationId
     * @return object|null
     */
    public function resolveProductLocation($productId, $variationId = 0)
    {
        $locationId = $this->resolveProductLocationId($productId, $variationId);
        return $this->getLocationOrDefault($locationId);
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
            'origin_name'            => (string) $location->name,
            'origin_phone'           => (string) $location->phone,
            'origin_address'         => (string) $location->address,
            'origin_sub_district_id' => (int) $location->sub_district_id,
            'origin_zip_code'        => (string) $location->zip_code,
            'origin_latitude'        => (string) $location->latitude,
            'origin_longitude'       => (string) $location->longitude,
        );
    }

    /**
     * Group a WooCommerce shipping package's contents into per-origin
     * sub-packages keyed by location ID.
     *
     * Each sub-package preserves the original package destination and carries
     * only the items bound to that origin plus its resolved origin array.
     *
     * @param array $package WooCommerce package (must contain `contents`).
     * @return array[] List of sub-packages: [ 'location_id', 'origin', 'contents', 'contents_cost', destination keys... ]
     */
    public function splitPackageByOrigin(array $package)
    {
        $contents = isset($package['contents']) && is_array($package['contents']) ? $package['contents'] : array();
        $groups   = array();

        foreach ($contents as $itemKey => $item) {
            $productId   = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            $variationId = isset($item['variation_id']) ? (int) $item['variation_id'] : 0;

            $location = $this->resolveProductLocation($productId, $variationId);
            $origin   = $this->locationToOrigin($location);
            $groupKey = !empty($origin['location_id']) ? (int) $origin['location_id'] : 0;

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = array(
                    'location_id' => $groupKey,
                    'origin'      => $origin,
                    'contents'    => array(),
                );
            }

            $groups[$groupKey]['contents'][$itemKey] = $item;
        }

        // Build final sub-package payloads, inheriting destination and other
        // package-level keys from the source package.
        $subPackages = array();
        foreach ($groups as $group) {
            $sub = $package;
            $sub['contents']      = $group['contents'];
            $sub['location_id']   = $group['location_id'];
            $sub['origin']        = $group['origin'];
            $sub['contents_cost'] = $this->sumContentsCost($group['contents']);
            $subPackages[]        = $sub;
        }

        return $subPackages;
    }

    /**
     * Sum the line totals for a set of cart contents.
     *
     * @param array $contents
     * @return float
     */
    private function sumContentsCost(array $contents)
    {
        $cost = 0.0;
        foreach ($contents as $item) {
            if (isset($item['line_total'])) {
                $cost += (float) $item['line_total'];
            }
        }
        return $cost;
    }

    /**
     * Whether more than one distinct origin is represented in a package.
     *
     * @param array $package
     * @return bool
     */
    public function isMultiOriginPackage(array $package)
    {
        return count($this->splitPackageByOrigin($package)) > 1;
    }
}
