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
	 * @param array<int, object>   $results       Payment rows.
	 * @param array<string,string> $filters       Current filters.
	 * @param array<string,string> $month_options Month filter options.
	 * @param array<string,int>    $status_counts Payment status counts.
	 * @return array<string, mixed>
	 */
	private function prepareSvelteBootstrap( array $results, array $filters, int $page, int $total_pages, int $total, int $items_per_page, array $month_options, array $status_counts ): array {
		$rows = array();
		foreach ( $results as $index => $row ) {
			$method = strtolower( trim( (string) ( $row->method ?? '' ) ) );
			$status = (string) ( $row->status ?? '' );
			$pickup_number = (string) ( $row->pickup_number ?? '' );
			$actions = array();
			if ( 'paid' !== $status && 'top' !== $method ) {
				$actions[] = array(
					'type'  => strtotime( (string) ( $row->pickup_schedule ?? '' ) ) > time() ? 'pay' : 'reschedule',
					'label' => strtotime( (string) ( $row->pickup_schedule ?? '' ) ) > time() ? __( 'Pay', 'kiriminaja-official' ) : __( 'Reschedule', 'kiriminaja-official' ),
				);
			}
			$actions[] = array(
				'type'  => 'details',
				'label' => __( 'Details', 'kiriminaja-official' ),
				'href'  => admin_url( 'admin.php?page=kiriminaja-request-pickup-detail&pickup_number=' . rawurlencode( $pickup_number ) ),
			);

			$rows[] = array(
				'number'       => $index + ( ( $page - 1 ) * $items_per_page ) + 1,
				'pickupNumber' => $pickup_number,
				'requestedAt'  => wp_date( 'Y/m/d H:i', strtotime( (string) ( $row->created_at ?? '' ) ) ),
				'schedule'     => gmdate( 'Y/m/d H:i', strtotime( (string) ( $row->pickup_schedule ?? '' ) ) ) . ' WIB',
				'fees'         => 'Rp. ' . kiriof_money_format( $row->cost ?? 0 ),
				'orders'       => (int) ( $row->order_amt ?? 0 ),
				'method'       => '' !== $method ? strtoupper( $method ) : 'QRIS',
				'status'       => 'paid' === $status || 'top' === $method ? 'paid' : 'unpaid',
				'actions'      => $actions,
			);
		}

		return array(
			'toolbar'      => array(
				'logoUrl'   => KIRIOF_URL . 'assets/admin/img/icon-128x128.png',
				'rootUrl'   => admin_url( 'admin.php?page=kiriminaja-konfigurasi' ),
				'rootLabel' => __( 'KiriminAja', 'kiriminaja-official' ),
				'title'     => __( 'Payments', 'kiriminaja-official' ),
			),
			'rows'         => $rows,
			'filters'      => $filters,
			'monthOptions' => $month_options,
			'statusTabs'   => array(
				array( 'value' => '', 'label' => __( 'All', 'kiriminaja-official' ), 'count' => (int) ( $status_counts['all'] ?? 0 ) ),
				array( 'value' => 'unpaid', 'label' => __( 'Waiting for Payment', 'kiriminaja-official' ), 'count' => (int) ( $status_counts['unpaid'] ?? 0 ) ),
				array( 'value' => 'paid', 'label' => __( 'Paid', 'kiriminaja-official' ), 'count' => (int) ( $status_counts['paid'] ?? 0 ) ),
			),
			'pagination'   => array( 'page' => $page, 'totalPages' => $total_pages, 'total' => $total, 'perPage' => $items_per_page ),
			'i18n'         => array(
				'search'        => __( 'Search payment…', 'kiriminaja-official' ),
				'allDates'      => __( 'All Dates', 'kiriminaja-official' ),
				'apply'         => __( 'Apply', 'kiriminaja-official' ),
				'pickupNumber'  => __( 'Pickup Number', 'kiriminaja-official' ),
				'schedule'      => __( 'Schedule', 'kiriminaja-official' ),
				'fees'          => __( 'Fees', 'kiriminaja-official' ),
				'orders'        => __( 'Orders', 'kiriminaja-official' ),
				'paymentMethod' => __( 'Payment Method', 'kiriminaja-official' ),
				'paymentStatus' => __( 'Payment Status', 'kiriminaja-official' ),
				'action'        => __( 'Action', 'kiriminaja-official' ),
				'requested'     => __( 'Requested', 'kiriminaja-official' ),
				'order'         => __( 'Order', 'kiriminaja-official' ),
				'no'            => __( 'No', 'kiriminaja-official' ),
				'empty'         => __( 'Not Found', 'kiriminaja-official' ),
				'pageOf'        => __( 'of', 'kiriminaja-official' ),
				'items'         => __( 'items', 'kiriminaja-official' ),
			),
			'modals'       => array(
				'scanToPay'    => __( 'Scan to Pay', 'kiriminaja-official' ),
				'code'         => __( 'Code', 'kiriminaja-official' ),
				'codCharges'   => __( 'COD Package Charges', 'kiriminaja-official' ),
				'nonCodCharges'=> __( 'Non-COD Package Charges', 'kiriminaja-official' ),
				'totalCharges' => __( 'Total Charges', 'kiriminaja-official' ),
				'expiresAt'    => __( 'QR will expire at', 'kiriminaja-official' ),
				'refresh'      => __( 'Refresh', 'kiriminaja-official' ),
				'error'        => __( 'Terjadi Kesalahan !', 'kiriminaja-official' ),
				'schedule'     => __( 'Schedule for Pickup', 'kiriminaja-official' ),
				'pickSchedule' => __( 'Pick Schedule', 'kiriminaja-official' ),
			),
		);
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
        $filters        = $this->getFilters();
        $items_per_page = 20;
        $page_data      = $this->query->getPage( $filters, $this->getRequestedPage(), $items_per_page );
        $results        = $page_data['results'];
        $page           = $page_data['page'];
        $items_per_page = $page_data['items_per_page'];
        $total_pages    = $page_data['total_pages'];
		$total          = $page_data['total'];
        $monthOptions   = $this->getMonthOptions();
        $kiriof_statusCounts = $this->query->getStatusCounts();
		$kiriof_payments_bootstrap = $this->prepareSvelteBootstrap(
			$results,
			$filters,
			$page,
			$total_pages,
			$total,
			$items_per_page,
			$monthOptions,
			$kiriof_statusCounts
		);

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
