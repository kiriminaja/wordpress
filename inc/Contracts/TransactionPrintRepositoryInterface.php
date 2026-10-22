<?php

namespace KiriminAjaOfficial\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface TransactionPrintRepositoryInterface
{
    /**
     * Mark transactions as printed by KiriminAja order ID.
     *
     * @param string[] $order_ids KiriminAja order IDs.
     * @return bool
     */
    public function markPrintedByOrderIds( array $order_ids ): bool;
}
