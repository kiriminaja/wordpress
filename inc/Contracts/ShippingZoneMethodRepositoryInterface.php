<?php

namespace KiriminAjaOfficial\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface ShippingZoneMethodRepositoryInterface
{
    /**
     * Enable a WooCommerce shipping zone method instance.
     *
     * @param int $instance_id Shipping method instance ID.
     * @return bool
     */
    public function enable( int $instance_id ): bool;
}
