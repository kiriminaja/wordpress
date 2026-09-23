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
	private TransactionListViewModelFactory $view_model_factory;

	public function __construct( TransactionListQueryInterface $query, ?TransactionListViewModelFactory $view_model_factory = null ) {
        $this->query = $query;
		$this->view_model_factory = $view_model_factory ?? new TransactionListViewModelFactory();
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

		$kiriof_transactions_bootstrap = array(
			'toolbar'      => array(
				'logoUrl' => KIRIOF_URL . 'assets/admin/img/icon-128x128.png',
				'title'   => __( 'Transactions', 'kiriminaja-official' ),
			),
			'filters'      => $filters,
			'statusOptions'=> array(
				array( 'value' => 'all', 'label' => __( 'All', 'kiriminaja-official' ), 'count' => (int) ( $kiriof_statusCounts['all'] ?? 0 ) ),
				array( 'value' => 'wc-processing', 'label' => __( 'New / Waiting for Shipment', 'kiriminaja-official' ), 'count' => (int) ( $kiriof_statusCounts['wc-processing'] ?? 0 ) ),
				array( 'value' => 'wc-on-hold', 'label' => __( 'On Hold', 'kiriminaja-official' ), 'count' => (int) ( $kiriof_statusCounts['wc-on-hold'] ?? 0 ) ),
				array( 'value' => 'wc-pending', 'label' => __( 'Pending Payment', 'kiriminaja-official' ), 'count' => (int) ( $kiriof_statusCounts['wc-pending'] ?? 0 ) ),
				array( 'value' => 'processed', 'label' => __( 'Processed', 'kiriminaja-official' ), 'count' => (int) ( $kiriof_statusCounts['processed'] ?? 0 ) ),
				array( 'value' => 'wc-cancelled', 'label' => __( 'Cancelled', 'kiriminaja-official' ), 'count' => (int) ( $kiriof_statusCounts['wc-cancelled'] ?? 0 ) ),
				array( 'value' => 'order-issue', 'label' => __( 'Order Issue', 'kiriminaja-official' ), 'count' => (int) ( $kiriof_statusCounts['order-issue'] ?? 0 ) ),
			),
			'monthOptions' => $kiriof_monthOptions,
			'couriers'     => array_map(
				static function ( $courier ) {
					return array( 'value' => (string) $courier->service, 'label' => (string) $courier->label );
				},
				$kiriof_couriers
			),
			'pagination'   => array(
				'page'       => $kiriof_current_page,
				'totalPages' => $kiriof_total_pages,
				'total'      => $kiriof_total,
				'perPage'    => $kiriof_per_page,
			),
			'rows'         => $this->view_model_factory->createRows( $kiriof_results, $kiriof_status_filter ),
			'bulk'         => array(
				'showPrint'   => in_array( $kiriof_status_filter, array( 'all', 'processed' ), true ),
				'printAction' => admin_url( 'admin-post.php' ),
				'printNonce'  => wp_create_nonce( 'kiriof_resi_print_bulk' ),
			),
			'i18n'         => array(
				'search'       => __( 'Search order…', 'kiriminaja-official' ),
				'orderNumber'  => __( 'Order Number', 'kiriminaja-official' ),
				'kaOrderId'    => __( 'KA Order ID', 'kiriminaja-official' ),
				'awb'          => __( 'AWB', 'kiriminaja-official' ),
				'allDates'     => __( 'All Dates', 'kiriminaja-official' ),
				'allPayment'   => __( 'All Payment', 'kiriminaja-official' ),
				'cod'          => __( 'COD', 'kiriminaja-official' ),
				'nonCod'       => __( 'Non-COD', 'kiriminaja-official' ),
				'allCouriers'  => __( 'All Couriers', 'kiriminaja-official' ),
				'allPrints'    => __( 'All Prints', 'kiriminaja-official' ),
				'printed'      => __( 'Printed', 'kiriminaja-official' ),
				'unprinted'    => __( 'Unprinted', 'kiriminaja-official' ),
				'apply'        => __( 'Apply', 'kiriminaja-official' ),
				'items'        => __( 'items', 'kiriminaja-official' ),
				'pageOf'       => __( 'of', 'kiriminaja-official' ),
				'status'       => __( 'All Status', 'kiriminaja-official' ),
				'requestPickup'=> __( 'Request Pickup', 'kiriminaja-official' ),
				'print'        => __( 'Print Labels', 'kiriminaja-official' ),
				'clear'        => __( 'Clear filters', 'kiriminaja-official' ),
				'order'        => __( 'Order / Transaction', 'kiriminaja-official' ),
				'expedition'   => __( 'Expedition & Service', 'kiriminaja-official' ),
				'airwaybill'   => __( 'Airwaybill / Order ID', 'kiriminaja-official' ),
				'route'        => __( 'Shipment Route', 'kiriminaja-official' ),
				'packages'     => __( 'Packages & Fee', 'kiriminaja-official' ),
				'action'       => __( 'Action', 'kiriminaja-official' ),
				'notFound'     => __( 'No transactions found.', 'kiriminaja-official' ),
				'detail'       => __( 'Detail', 'kiriminaja-official' ),
				'changeOrigin' => __( 'Change Origin', 'kiriminaja-official' ),
				'adjustDeficit'=> __( 'Adjust Deficit', 'kiriminaja-official' ),
				'cancel'       => __( 'Cancel', 'kiriminaja-official' ),
				'printedLabel' => __( 'Printed', 'kiriminaja-official' ),
				'unprintedLabel'=> __( 'Unprinted', 'kiriminaja-official' ),
			),
		);

		include KIRIOF_DIR . 'templates/transaction-process/app.php';
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
