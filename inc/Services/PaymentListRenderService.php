<?php

namespace KiriminAjaOfficial\Services;

use DateTime;
use KiriminAjaOfficial\Contracts\PaymentListQueryInterface;
use KiriminAjaOfficial\Queries\WordPressPaymentListQuery;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Prepares and renders the Payments admin page.
 */
class PaymentListRenderService {
    private PaymentListQueryInterface $query;

    public function __construct( PaymentListQueryInterface $query ) {
        $this->query = $query;
    }

    /**
     * Composition root used by the legacy Admin page callback.
     */
    public static function renderDefault(): void {
        $service = new self( new WordPressPaymentListQuery() );
        $service->render();
    }

    /**
     * Prepare the existing view variables and include the view.
     */
    public function render(): void {
        $locale         = get_locale();
        $filters        = $this->getFilters();
        $items_per_page = 20;
        $page_data      = $this->query->getPage( $filters, $this->getRequestedPage(), $items_per_page );
        $results        = $page_data['results'];
        $page           = $page_data['page'];
        $items_per_page = $page_data['items_per_page'];
        $total_pages    = $page_data['total_pages'];
        $next_page_link = $this->getPaginationLink( $page + 1, $page < $total_pages );
        $prev_page_link = $this->getPaginationLink( $page - 1, $page > 1 );
        $monthOptions   = $this->getMonthOptions();
        $kiriof_statusCounts = $this->query->getStatusCounts();

        include KIRIOF_DIR . 'templates/request-pickup/view/index.php';
    }

    /**
     * Read and sanitize list filters.
     *
     * @return array{key:string,month:string,status:string}
     */
    private function getFilters(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin list filters.
        return array(
            'key'    => sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) ),
            'month'  => sanitize_text_field( wp_unslash( $_GET['month'] ?? '' ) ),
            'status' => sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) ),
        );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
    }

    /**
     * Get the requested page number.
     */
    private function getRequestedPage(): int {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination value.
        return isset( $_GET['cpage'] ) ? max( 1, absint( $_GET['cpage'] ) ) : 1;
    }

    /**
     * Preserve the existing query string while changing the list page.
     */
    private function getPaginationLink( int $target_page, bool $available ): string {
        if ( ! $available ) {
            return '';
        }

        $link = admin_url( 'admin.php?' );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination link building.
        foreach ( $_GET as $key => $value ) {
            if ( 'cpage' === $key || ! is_scalar( $value ) ) {
                continue;
            }

            $link .= sanitize_key( $key ) . '=' . urlencode( sanitize_text_field( wp_unslash( (string) $value ) ) ) . '&';
        }

        return esc_url( $link . 'cpage=' . $target_page );
    }

    /**
     * Build month choices from the oldest payment through the current month.
     */
    private function getMonthOptions(): array {
        $oldest_month  = gmdate( 'Y-m-d', strtotime( $this->query->getOldestCreatedAt() ?? 'now' ) );
        $current_month = gmdate( 'Y-m-d', strtotime( 'now' ) );
        $oldest_date   = new DateTime( $oldest_month );
        $current_date  = new DateTime( $current_month );
        $difference    = $current_date->diff( $oldest_date );
        $month_count   = ( $difference->y * 12 ) + $difference->m + 1;
        $options       = array();

        for ( $index = 0; $index <= $month_count; $index++ ) {
            $date = 'now-' . $index . ' months';
            $options[ gmdate( 'Y-m', strtotime( $date ) ) ] = gmdate( 'Y F', strtotime( $date ) );
        }

        return $options;
    }
}
