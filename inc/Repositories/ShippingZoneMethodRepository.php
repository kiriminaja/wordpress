<?php

namespace KiriminAjaOfficial\Repositories;

use KiriminAjaOfficial\Contracts\ShippingZoneMethodRepositoryInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ShippingZoneMethodRepository implements ShippingZoneMethodRepositoryInterface
{
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Enable a WooCommerce shipping zone method instance.
     *
     * @param int $instance_id Shipping method instance ID.
     * @return bool
     */
    public function enable( int $instance_id ): bool
    {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WooCommerce has no public API for updating an existing zone method status without also firing side effects.
        $updated = $this->wpdb->update(
            $this->wpdb->prefix . 'woocommerce_shipping_zone_methods',
            array( 'is_enabled' => 1 ),
            array( 'instance_id' => $instance_id ),
            array( '%d' ),
            array( '%d' )
        );

        return false !== $updated;
    }
}
