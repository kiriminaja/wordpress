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
			$is_instant = 'instant' === ( $row->delivery_type ?? 'express' );
			$payment_id = (string) ( $row->instant_payment_id ?? '' );
			$identity = $is_instant ? $payment_id : $pickup_number;
			$actions = array();
			$order_ids = $is_instant ? array_values( (array) ( $row->order_ids ?? array() ) ) : array();
			if ( $is_instant && 'qris' === $method && in_array( $status, array( 'pending', 'unpaid' ), true )
				&& 1 === preg_match( '/\A[A-Za-z0-9][A-Za-z0-9_-]{0,99}\z/', $payment_id )
				&& count( $order_ids ) > 0 && count( $order_ids ) <= 50 && count( $order_ids ) === (int) ( $row->order_amt ?? 0 ) ) {
				$actions[] = array( 'type' => 'pay', 'label' => __( 'Pay', 'kiriminaja-official' ) );
			}
			if ( ! $is_instant && 'paid' !== $status && 'top' !== $method ) {
				$actions[] = array(
					'type'  => strtotime( (string) ( $row->pickup_schedule ?? '' ) ) > time() ? 'pay' : 'reschedule',
					'label' => strtotime( (string) ( $row->pickup_schedule ?? '' ) ) > time() ? __( 'Pay', 'kiriminaja-official' ) : __( 'Reschedule', 'kiriminaja-official' ),
				);
			}
			$actions[] = array(
				'type'  => 'details',
				'label' => __( 'Details', 'kiriminaja-official' ),
				'href'  => add_query_arg( $is_instant ? array( 'key' => 'ipid:' . $payment_id, 'delivery_type' => 'instant', 'status' => 'all' ) : array( 'key' => 'pid:' . $pickup_number ), admin_url( 'admin.php?page=kiriminaja-transaction' ) ),
			);

			$rows[] = array(
				'number'       => $index + ( ( $page - 1 ) * $items_per_page ) + 1,
				'rowKey'       => ( $is_instant ? 'instant:' : 'express:' ) . $identity,
				'deliveryType' => $is_instant ? 'instant' : 'express',
				'identity'     => $identity,
				'orderIds'     => $order_ids,
				'pickupNumber' => $pickup_number,
				'requestedAt'  => wp_date( 'Y/m/d H:i', strtotime( (string) ( $row->created_at ?? '' ) ) ),
				'schedule'     => $is_instant ? '—' : gmdate( 'Y/m/d H:i', strtotime( (string) ( $row->pickup_schedule ?? '' ) ) ) . ' WIB',
				'fees'         => 'Rp. ' . kiriof_money_format( $row->cost ?? 0 ),
				'orders'       => (int) ( $row->order_amt ?? 0 ),
				'method'       => '' !== $method ? strtoupper( $method ) : ( $is_instant ? '—' : 'QRIS' ),
				'status'       => $is_instant ? ( in_array( $status, array( 'paid', 'unpaid', 'pending', 'refunded' ), true ) ? $status : 'pending' ) : ( 'paid' === $status || 'top' === $method ? 'paid' : 'unpaid' ),
				'actions'      => $actions,
			);
		}
		$toolbar = array(
			'logoUrl'   => KIRIOF_URL . 'assets/admin/img/icon-128x128.png',
			'rootUrl'   => admin_url( 'admin.php?page=kiriminaja-setting' ),
			'rootLabel' => __( 'Payments', 'kiriminaja-official' ),
			'title'     => __( 'Payments', 'kiriminaja-official' ),
		);
		$toolbar_update = ( new PluginUpdateNoticeService() )->get_toolbar_update();
		if ( $toolbar_update ) {
			$toolbar['update'] = $toolbar_update;
		}
		$toolbar['menu'] = array(
			'label' => __( 'More actions', 'kiriminaja-official' ),
			'items' => array(
				array(
					'label' => __( 'Get Help', 'kiriminaja-official' ),
					'href'  => 'https://help.kiriminaja.com/category/plugin',
				),
				array(
					'label' => __( 'Go to Dashboard', 'kiriminaja-official' ),
					'href'  => 'https://app.kiriminaja.com',
				),
			),
		);
		$toolbar = RevampAnnouncementService::attach_announcement( $toolbar );

		return array(
			'toolbar'      => $toolbar,
			'rows'         => $rows,
			'filters'      => $filters,
			'monthOptions' => $month_options,
			'statusTabs'   => array(
				array( 'value' => '', 'label' => __( 'All', 'kiriminaja-official' ), 'count' => (int) ( $status_counts['all'] ?? 0 ) ),
				array( 'value' => 'unpaid', 'label' => __( 'Waiting for Payment', 'kiriminaja-official' ), 'count' => (int) ( $status_counts['unpaid'] ?? 0 ) ),
				array( 'value' => 'paid', 'label' => __( 'Paid', 'kiriminaja-official' ), 'count' => (int) ( $status_counts['paid'] ?? 0 ) ),
				array( 'value' => 'pending', 'label' => __( 'Pending', 'kiriminaja-official' ), 'count' => (int) ( $status_counts['pending'] ?? 0 ) ),
				array( 'value' => 'refunded', 'label' => __( 'Refunded', 'kiriminaja-official' ), 'count' => (int) ( $status_counts['refunded'] ?? 0 ) ),
			),
			'pagination'   => array( 'page' => $page, 'totalPages' => $total_pages, 'total' => $total, 'perPage' => $items_per_page ),
			'ajax'         => array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( KIRIOF_NONCE ) ),
			'i18n'         => array(
				'search'        => __( 'Search payment…', 'kiriminaja-official' ),
				'allDates'      => __( 'All Dates', 'kiriminaja-official' ),
				'apply'         => __( 'Apply', 'kiriminaja-official' ),
				'pickupNumber'  => __( 'Pickup / Payment ID', 'kiriminaja-official' ),
				'schedule'      => __( 'Schedule', 'kiriminaja-official' ),
				'fees'          => __( 'Fees', 'kiriminaja-official' ),
				'orders'        => __( 'Orders', 'kiriminaja-official' ),
				'paymentMethod' => __( 'Payment Method', 'kiriminaja-official' ),
				'paymentStatus' => __( 'Payment Status', 'kiriminaja-official' ),
				'action'        => __( 'Action', 'kiriminaja-official' ),
				'deliveryType' => __( 'Delivery Type', 'kiriminaja-official' ),
				'regular'      => __( 'Regular', 'kiriminaja-official' ),
				'instant'      => __( 'Instant', 'kiriminaja-official' ),
				'requested'     => __( 'Requested', 'kiriminaja-official' ),
				'order'         => __( 'Order', 'kiriminaja-official' ),
				'no'            => __( 'No', 'kiriminaja-official' ),
				'empty'         => __( 'Not Found', 'kiriminaja-official' ),
				'pageOf'        => __( 'of', 'kiriminaja-official' ),
				'items'         => __( 'items', 'kiriminaja-official' ),
				'autoRefresh'   => __( 'Auto Refresh Timer', 'kiriminaja-official' ),
				'refreshLabels' => array(
					'60'  => __( '1 minute', 'kiriminaja-official' ),
					'180' => __( '3 minutes', 'kiriminaja-official' ),
					'300' => __( '5 minutes', 'kiriminaja-official' ),
				),
			),
			'modals'       => array(
				'scanToPay'    => __( 'Scan to Pay', 'kiriminaja-official' ),
				'scanDescription' => __( 'Scan this QR with your banking or e-wallet app. Payment status is checked automatically.', 'kiriminaja-official' ),
				'waitingForPayment' => __( 'Waiting for payment…', 'kiriminaja-official' ),
				'checkingPayment' => __( 'Checking payment…', 'kiriminaja-official' ),
				'paymentExpired' => __( 'This payment QR is no longer available. Refresh to check for a new QR.', 'kiriminaja-official' ),
				'code'         => __( 'Code', 'kiriminaja-official' ),
				'codCharges'   => __( 'COD Package Charges', 'kiriminaja-official' ),
				'nonCodCharges'=> __( 'Non-COD Package Charges', 'kiriminaja-official' ),
				'totalCharges' => __( 'Total Charges', 'kiriminaja-official' ),
				'expiresAt'    => __( 'QR will expire at', 'kiriminaja-official' ),
				'refresh'      => __( 'Refresh', 'kiriminaja-official' ),
				'error'        => __( 'Terjadi Kesalahan !', 'kiriminaja-official' ),
				'schedule'     => __( 'Schedule for Pickup', 'kiriminaja-official' ),
				'pickSchedule' => __( 'Pick Schedule', 'kiriminaja-official' ),
				'scheduleDescription' => __( 'Choose a new pickup time for this payment.', 'kiriminaja-official' ),
				'confirmSchedule' => __( 'Confirm schedule', 'kiriminaja-official' ),
				'cancel' => __( 'Cancel', 'kiriminaja-official' ),
				'processing' => __( 'Processing…', 'kiriminaja-official' ),
				'retry' => __( 'Retry', 'kiriminaja-official' ),
				'noSchedule' => __( 'No pickup schedule is available.', 'kiriminaja-official' ),
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
     * @return array<string,mixed>
     */
    private function getFilters(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin list filters.
        $filters = array();
        foreach ( array( 'key', 'month', 'status' ) as $name ) {
            $filters[ $name ] = isset( $_GET[ $name ] ) && is_string( $_GET[ $name ] )
                ? sanitize_text_field( wp_unslash( $_GET[ $name ] ) )
                : '';
        }
        foreach ( array( 'date_from', 'date_to' ) as $name ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Retained only to reject values altered by sanitization, never used in SQL.
            $original = isset( $_GET[ $name ] ) ? wp_unslash( $_GET[ $name ] ) : '';
            $clean = is_string( $original ) ? sanitize_text_field( $original ) : '';
            $filters[ $name ] = $original === $clean ? $clean : '';
            if ( ! is_string( $original ) || $original !== $clean ) {
                $filters['date_range_invalid'] = true;
            }
        }
        $dates = ListDateRangeFilter::normalize( $filters );
        return array_merge( $filters, $dates );
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
