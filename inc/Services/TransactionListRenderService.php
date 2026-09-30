<?php

namespace KiriminAjaOfficial\Services;

use DateTime;
use KiriminAjaOfficial\Contracts\TransactionListQueryInterface;
use KiriminAjaOfficial\Queries\WordPressTransactionListQuery;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Prepares and renders the Transactions admin page.
 */
class TransactionListRenderService {
    private TransactionListQueryInterface $query;

    public function __construct( TransactionListQueryInterface $query ) {
        $this->query = $query;
    }

    /**
     * Composition root used by the legacy Admin page callback.
     */
    public static function renderDefault(): void {
        $service = new self( new WordPressTransactionListQuery() );
        $service->render();
    }

    /**
     * Prepare the existing view variables and include the view.
     */
    public function render(): void {
        $locale          = get_locale();
        $user            = wp_get_current_user();
        $kiriof_per_page = (int) get_user_meta( $user->ID, 'kiriof_transactions_per_page', true );
        if ( $kiriof_per_page < 1 ) {
            $kiriof_per_page = 25;
        }
        $kiriof_per_page = min( $kiriof_per_page, 100 );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list preference.
        $kiriof_per_page_get = isset( $_GET['per_page'] ) ? (int) $_GET['per_page'] : 0;
        if ( $kiriof_per_page_get > 0 && $kiriof_per_page_get !== $kiriof_per_page ) {
            $kiriof_per_page = min( $kiriof_per_page_get, 100 );
            update_user_meta( $user->ID, 'kiriof_transactions_per_page', $kiriof_per_page );
        }

        $filters                 = $this->getFilters();
        $page_data               = $this->query->getPage( $filters, $this->getRequestedPage(), $kiriof_per_page );
        $kiriof_results          = $page_data['results'];
        $kiriof_total            = $page_data['total'];
        $kiriof_current_page     = $page_data['page'];
        $kiriof_per_page         = $page_data['items_per_page'];
        $kiriof_total_pages      = $page_data['total_pages'];
        $kiriof_statusCounts     = $this->query->getStatusCounts();
        $kiriof_monthOptions     = $this->getMonthOptions();
        $kiriof_search_by        = $filters['search_by'];
        $kiriof_status_filter    = $filters['status'];
        $kiriof_cod_filter       = $filters['cod'];
        $kiriof_courier_filter   = $filters['courier'];
        $kiriof_print_status_filter = $filters['print_status'];
        $kiriof_month_filter     = $filters['month'];

        if ( class_exists( \KiriminAjaOfficial\Base\BaseInit::class ) ) {
            ( new \KiriminAjaOfficial\Base\BaseInit() )->logThis( '$kiriof_results', array( $kiriof_results ) );
            ( new \KiriminAjaOfficial\Base\BaseInit() )->logThis( '$kiriof_monthOptions', array( $kiriof_monthOptions ) );
        }

        $courier_name_map = ( new KiriminajaApiService() )->getCourierNameMap();
        $kiriof_couriers  = array_map(
            static function ( $row ) use ( $courier_name_map ) {
                $code  = strtolower( (string) $row->service );
                $label = $courier_name_map[ $code ] ?? strtoupper( $code );
                return (object) array( 'service' => $row->service, 'label' => $label );
            },
            $this->query->getCouriers()
        );

        include KIRIOF_DIR . 'templates/transaction-process/view/index.php';
    }

    /**
     * Read, sanitize, and normalize list filters.
     *
     * @return array{key:string,month:string,status:string,cod:string,courier:string,print_status:string,search_by:string}
     */
    private function getFilters(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin list filters.
        $filters = array(
            'key'          => sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) ),
            'month'        => sanitize_text_field( wp_unslash( $_GET['month'] ?? '' ) ),
            'status'       => sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) ),
            'cod'          => sanitize_text_field( wp_unslash( $_GET['cod'] ?? '' ) ),
            'courier'      => sanitize_text_field( wp_unslash( $_GET['courier'] ?? '' ) ),
            'print_status' => sanitize_text_field( wp_unslash( $_GET['print_status'] ?? '' ) ),
            'search_by'    => sanitize_text_field( wp_unslash( $_GET['search_by'] ?? 'wc_order_id' ) ),
        );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ( ! in_array( $filters['status'], array( 'all', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-cancelled', 'processed', 'order-issue' ), true ) ) {
            $filters['status'] = 'all';
        }
        if ( ! in_array( $filters['search_by'], array( 'wc_order_id', 'ka_order_id', 'awb' ), true ) ) {
            $filters['search_by'] = 'wc_order_id';
        }
        if ( ! in_array( $filters['print_status'], array( '0', '1' ), true ) ) {
            $filters['print_status'] = '';
        }

        return $filters;
    }

    private function getRequestedPage(): int {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination value.
        return isset( $_GET['cpage'] ) ? max( 1, (int) $_GET['cpage'] ) : 1;
    }

    /**
     * Build month choices from the oldest transaction through the current month.
     */
    private function getMonthOptions(): array {
        $oldest_month = new DateTime( $this->query->getOldestCreatedAt() ?? 'now' );
        $current_month = new DateTime( 'now' );
        $interval      = $oldest_month->diff( $current_month );
        $total_months  = ( $interval->y * 12 ) + $interval->m + 1;
        $options       = array();

        for ( $index = 0; $index < $total_months; $index++ ) {
            $date = ( clone $current_month )->modify( "-{$index} months" );
            $options[ $date->format( 'Y-m' ) ] = $date->format( 'Y F' );
        }

        return $options;
    }
}
