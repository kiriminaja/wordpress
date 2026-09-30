<?php

namespace KiriminAjaOfficial\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Read model used by the Payments admin list.
 */
interface PaymentListQueryInterface {
    /**
     * Fetch a filtered and paginated payment list.
     *
     * @param array{key:string,month:string,status:string} $filters List filters.
     * @param int                                         $page Requested page.
     * @param int                                         $items_per_page Page size.
     * @return array{results:array,page:int,items_per_page:int,total_pages:int}
     */
    public function getPage( array $filters, int $page, int $items_per_page ): array;

    /**
     * Get stable totals for the payment status filter pills.
     *
     * @return array{all:int,unpaid:int,paid:int}
     */
    public function getStatusCounts(): array;

    /**
     * Get the oldest payment creation date, if one exists.
     */
    public function getOldestCreatedAt(): ?string;
}
