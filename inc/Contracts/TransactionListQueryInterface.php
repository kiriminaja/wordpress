<?php

namespace KiriminAjaOfficial\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Read model used by the Transactions admin list.
 */
interface TransactionListQueryInterface {
    /**
     * Fetch a filtered and paginated transaction list.
     *
     * @param array{key:string,month:string,status:string,cod:string,courier:string,print_status:string,search_by:string} $filters List filters.
     * @param int $page Requested page.
     * @param int $items_per_page Page size.
     * @return array{results:array,total:int,page:int,items_per_page:int,total_pages:int}
     */
    public function getPage( array $filters, int $page, int $items_per_page ): array;

    /**
     * Get stable totals for the transaction status filter pills.
     *
     * @return array<string,int>
     */
    public function getStatusCounts(): array;

    /**
     * Get distinct courier codes present in shippable transactions.
     *
     * @return array<int,object>
     */
    public function getCouriers(): array;

    /**
     * Get the oldest shippable transaction creation date, if one exists.
     */
    public function getOldestCreatedAt(): ?string;
}
