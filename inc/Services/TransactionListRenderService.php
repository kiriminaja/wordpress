<?php

namespace KiriminAjaOfficial\Services;

use DateTime;
use KiriminAjaOfficial\Contracts\TransactionListQueryInterface;
use KiriminAjaOfficial\Queries\WordPressTransactionListQuery;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit();
}

/**
 * Prepares and renders the Transactions admin page.
 */
class TransactionListRenderService
{
    private TransactionListQueryInterface $query;
    private TransactionListViewModelFactory $view_model_factory;

    public function __construct(
        TransactionListQueryInterface $query,
        ?TransactionListViewModelFactory $view_model_factory = null,
    ) {
        $this->query = $query;
        $this->view_model_factory =
            $view_model_factory ?? new TransactionListViewModelFactory();
    }

    /**
     * Composition root used by the legacy Admin page callback.
     */
    public static function renderDefault(): void
    {
        $service = new self(new WordPressTransactionListQuery());
        $service->render();
    }

    /**
     * Prepare the existing view variables and include the view.
     */
    public function render(): void
    {
        $locale = get_locale();
        $user = wp_get_current_user();
        $kiriof_per_page = (int) get_user_meta(
            $user->ID,
            "kiriof_transactions_per_page",
            true,
        );
        if ($kiriof_per_page < 1) {
            $kiriof_per_page = 25;
        }
        $kiriof_per_page = min($kiriof_per_page, 100);

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list preference.
        $kiriof_per_page_get = isset( $_GET['per_page'] )
            ? (int) sanitize_text_field( wp_unslash( $_GET['per_page'] ) )
            : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if (
            $kiriof_per_page_get > 0 &&
            $kiriof_per_page_get !== $kiriof_per_page
        ) {
            $kiriof_per_page = min($kiriof_per_page_get, 100);
            update_user_meta(
                $user->ID,
                "kiriof_transactions_per_page",
                $kiriof_per_page,
            );
        }

        $filters = $this->getFilters();
        $page_data = $this->query->getPage(
            $filters,
            $this->getRequestedPage(),
            $kiriof_per_page,
        );
        $kiriof_results = $page_data["results"];
        $kiriof_total = $page_data["total"];
        $kiriof_current_page = $page_data["page"];
        $kiriof_per_page = $page_data["items_per_page"];
        $kiriof_total_pages = $page_data["total_pages"];
        $kiriof_statusCounts = $this->query->getStatusCounts();
        $kiriof_monthOptions = $this->getMonthOptions();
        $kiriof_status_filter = $filters["status"];
        $kiriof_cod_filter = $filters["cod"];
        $kiriof_courier_filter = $filters["courier"];
        $kiriof_print_status_filter = $filters["print_status"];
        $kiriof_month_filter = $filters["month"];

        if (class_exists(\KiriminAjaOfficial\Base\BaseInit::class)) {
            ( new \KiriminAjaOfficial\Base\BaseInit() )->logThis(
                '$kiriof_results',
                [$kiriof_results],
            );
            ( new \KiriminAjaOfficial\Base\BaseInit() )->logThis(
                '$kiriof_monthOptions',
                [$kiriof_monthOptions],
            );
        }

        $courier_name_map = ( new KiriminajaApiService() )->getCourierNameMap();
        $kiriof_couriers = array_map(static function ($row) use (
            $courier_name_map,
        ) {
            $code = strtolower((string) $row->service);
            $label = $courier_name_map[$code] ?? strtoupper($code);
            return (object) ["service" => $row->service, "label" => $label];
        }, $this->query->getCouriers());

        $toolbar = [
            "logoUrl" => KIRIOF_URL . "assets/admin/img/icon-128x128.png",
            "rootUrl" => admin_url("admin.php?page=kiriminaja-setting"),
            "rootLabel" => __("Transactions", "kiriminaja-official"),
            "title" => __("Transactions", "kiriminaja-official"),
        ];
        $kiriof_shipment_locations = array_map(function ($location) {
            return [
                "id" => (int) ($location->id ?? 0),
                "name" =>
                    (string) ($location->name ??
                        ($location->location_name ?? "")),
                "address" => ( new ShipmentLocationService() )->formatAddress(
                    $location,
                ),
            ];
        }, ( new ShipmentLocationService() )->repository()->getAll( true ));
        $toolbar_update = ( new PluginUpdateNoticeService() )->get_toolbar_update();
        if ($toolbar_update) {
            $toolbar["update"] = $toolbar_update;
        }
        $toolbar["menu"] = [
            "label" => __("More actions", "kiriminaja-official"),
            "items" => [
                [
                    "label" => __("Get Help", "kiriminaja-official"),
                    "href" => "https://help.kiriminaja.com/category/plugin",
                ],
                [
                    "label" => __("Go to Dashboard", "kiriminaja-official"),
                    "href" => "https://app.kiriminaja.com",
                ],
            ],
        ];
        $toolbar = RevampAnnouncementService::attach_announcement($toolbar);

        $kiriof_transactions_bootstrap = [
            "toolbar" => $toolbar,
            "filters" => $filters,
            "statusOptions" => [
                [
                    "value" => "all",
                    "label" => __("All", "kiriminaja-official"),
                    "count" => (int) ($kiriof_statusCounts["all"] ?? 0),
                ],
                [
                    "value" => "wc-processing",
                    "label" => __(
                        "Waiting for Shipment",
                        "kiriminaja-official",
                    ),
                    "count" =>
                        (int) ($kiriof_statusCounts["wc-processing"] ?? 0),
                ],
                [
                    "value" => "wc-on-hold",
                    "label" => __("On Hold", "kiriminaja-official"),
                    "count" => (int) ($kiriof_statusCounts["wc-on-hold"] ?? 0),
                ],
                [
                    "value" => "wc-pending",
                    "label" => __("Pending Payment", "kiriminaja-official"),
                    "count" => (int) ($kiriof_statusCounts["wc-pending"] ?? 0),
                ],
                [
                    "value" => "processed",
                    "label" => __("Processed", "kiriminaja-official"),
                    "count" => (int) ($kiriof_statusCounts["processed"] ?? 0),
                ],
                [
                    "value" => "wc-cancelled",
                    "label" => __("Cancelled", "kiriminaja-official"),
                    "count" =>
                        (int) ($kiriof_statusCounts["wc-cancelled"] ?? 0),
                ],
                [
                    "value" => "order-issue",
                    "label" => __("Order Issue", "kiriminaja-official"),
                    "count" => (int) ($kiriof_statusCounts["order-issue"] ?? 0),
                ],
            ],
            "monthOptions" => $kiriof_monthOptions,
            "couriers" => array_map(static function ($courier) {
                return [
                    "value" => (string) $courier->service,
                    "label" => (string) $courier->label,
                ];
            }, $kiriof_couriers),
            "pagination" => [
                "page" => $kiriof_current_page,
                "totalPages" => $kiriof_total_pages,
                "total" => $kiriof_total,
                "perPage" => $kiriof_per_page,
            ],
            "rows" => $this->view_model_factory->createRows(
                $kiriof_results,
                in_array("processed", explode(",", $kiriof_status_filter), true) ? "processed" : $kiriof_status_filter,
            ),
            "shipmentLocations" => $kiriof_shipment_locations,
            "locationsUrl" => admin_url(
                "admin.php?page=wc-settings&tab=kiriminaja_warehouses",
            ),
            "bulk" => [
                "showPrint" => "express" === $filters["delivery_type"] && ("all" === $kiriof_status_filter || in_array("processed", explode(",", $kiriof_status_filter), true)),
                "printAction" => admin_url("admin-post.php"),
                "printNonce" => wp_create_nonce("kiriof_resi_print_bulk"),
                "printPreviewNonce" => wp_create_nonce("kiriof_resi_print"),
                "ajaxUrl" => admin_url("admin-ajax.php"),
                "nonce" => wp_create_nonce(KIRIOF_NONCE),
                "pickupUrl" => admin_url(
                    "admin.php?page=kiriminaja-request-pickup",
                ),
            ],
            "i18n" => [
                "regularDelivery" => __("Regular Delivery", "kiriminaja-official"),
                "instantDelivery" => __("Instant Delivery", "kiriminaja-official"),
                "orderIssue" => __("Order Issue", "kiriminaja-official"),
                "transactionScope" => __("Transaction scope", "kiriminaja-official"),
                "vehicle" => __("Vehicle", "kiriminaja-official"),
                "vehicleUnavailable" => __("Not specified", "kiriminaja-official"),
                "processShipment" => __("Process Shipment", "kiriminaja-official"),
                "instantReviewDescription" => __("Review selected shipments and current courier prices.", "kiriminaja-official"),
                "instantRatesTitle" => __("Instant Rates", "kiriminaja-official"),
                "instantRatesNotice" => __("Courier prices may change before booking.", "kiriminaja-official"),
                "instantOrderInformation" => __("Order Information", "kiriminaja-official"),
                "instantOrdersChanged" => __("orders changed", "kiriminaja-official"),
                "instantCourierService" => __("Courier / service", "kiriminaja-official"),
                "instantOrigin" => __("Origin", "kiriminaja-official"),
                "instantRecipient" => __("Recipient", "kiriminaja-official"),
                "instantUnavailable" => __("Unavailable", "kiriminaja-official"),
                "instantChanged" => __("Price changed", "kiriminaja-official"),
                "instantShippingInformation" => __("Shipping Information", "kiriminaja-official"),
                "instantSelectedOrders" => __("selected shipments", "kiriminaja-official"),
                "instantPriceGap" => __("Price Gap", "kiriminaja-official"),
                "instantPriceIncrease" => __("Price increase", "kiriminaja-official"),
                "instantPriceDecrease" => __("Price decrease", "kiriminaja-official"),
                "instantTotalShipment" => __("Total Shipment", "kiriminaja-official"),
                "instantTotalScope" => __("Totals include selected eligible shipments only and compare carrier shipping charges. Checkout insurance and customer admin fees are not included.", "kiriminaja-official"),
                "instantQuoteRefreshFailed" => __("Prices could not be refreshed. Review prices again to continue; no shipment has been booked.", "kiriminaja-official"),
                "instantRefreshingPrices" => __("Refreshing prices…", "kiriminaja-official"),
                "instantContinuePayment" => __("Confirm & continue to payment", "kiriminaja-official"),
                "instantQuoteExpired" => __("Prices have expired. They will refresh automatically before you can confirm.", "kiriminaja-official"),
                "instantBefore" => __("Saved price", "kiriminaja-official"),
                "instantAfter" => __("Current price", "kiriminaja-official"),
                "instantPin" => __("KA Credit PIN (6 digits)", "kiriminaja-official"),
                "instantUnknown" => __("Unable to confirm the Instant booking. Contact support before trying again.", "kiriminaja-official"),
                "instantRefreshPayment" => __("Refresh payment status", "kiriminaja-official"),
                "instantClose" => __("Close", "kiriminaja-official"),
                "instantReview" => __("Review prices again", "kiriminaja-official"),
                "instantCredit" => __("KA Credit", "kiriminaja-official"),
                "creditDescription" => __("Remaining Credit", "kiriminaja-official"),
                "creditUnavailable" => __("Unable to verify credit balance.", "kiriminaja-official"),
                "creditInsufficient" => __("Insufficient credit balance for this pickup.", "kiriminaja-official"),
                "creditNoPin" => __("Set a PIN on your KiriminAja profile to pay with credit.", "kiriminaja-official"),
                "instantCreditBalance" => __("KA Credit balance remaining", "kiriminaja-official"),
                "instantCreditUnavailable" => __("Unable to verify KA Credit balance. Refresh prices to check again.", "kiriminaja-official"),
                "instantCreditInsufficient" => __("Insufficient KA Credit balance for these shipments.", "kiriminaja-official"),
                "instantQrisDescription" => __("Scan the QR code after booking to pay for these shipments.", "kiriminaja-official"),
                "instantPinStep" => __("Input PIN & Validation", "kiriminaja-official"),
                "instantPinDescription" => __("Enter your six-digit KA Credit PIN. The server checks your PIN and balance before creating a shipment.", "kiriminaja-official"),
                "instantPinInvalid" => __("Unable to validate KA Credit payment. Check your PIN and try again.", "kiriminaja-official"),
                "instantValidatePin" => __("Validate PIN & process", "kiriminaja-official"),
                "instantPinValidating" => __("Validating KA Credit payment…", "kiriminaja-official"),
                "instantBackSummary" => __("Back to Summary", "kiriminaja-official"),
                "instantTop" => __("TOP", "kiriminaja-official"),
                "instantQris" => __("QRIS", "kiriminaja-official"),
                "scanToPay" => __("Scan to Pay", "kiriminaja-official"),
                "instantQrLabel" => __("Scan this QRIS code to pay", "kiriminaja-official"),
                "instantResult_booked" => __("Booked", "kiriminaja-official"),
                "instantResult_failed" => __("Failed", "kiriminaja-official"),
                "instantResult_unknown" => __("Unknown outcome", "kiriminaja-official"),
                "instantResult_skipped" => __("Skipped", "kiriminaja-official"),
                "instantPayment_unpaid" => __("Unpaid", "kiriminaja-official"),
                "instantPayment_refunded" => __("Refunded", "kiriminaja-official"),
                "instantPayment_paid" => __("Paid", "kiriminaja-official"),
                "instantPayment_pending" => __("Pending payment", "kiriminaja-official"),
                "instantPayment_failed" => __("Payment failed", "kiriminaja-official"),
                "instantPayment_expired" => __("Payment expired", "kiriminaja-official"),
                "instantPayment_unknown" => __("Unknown payment state", "kiriminaja-official"),
                "liveTracking" => __("Live Tracking", "kiriminaja-official"),
            "instantCancel" => __("Cancel Instant shipment", "kiriminaja-official"),
            "instantRecheck" => __("Recheck booking", "kiriminaja-official"),
            "instantRecheckDescription" => __("Check whether this uncertain booking exists. This does not create another shipment or clear the retry guard. If no shipment is found, contact support to confirm the booking was not accepted.", "kiriminaja-official"),
            "instantResult_reconciled" => __("Booking evidence checked", "kiriminaja-official"),
            "instantCancelTerms" => __("Confirm cancellation of this Instant shipment only. This does not cancel the WooCommerce order or issue a WooCommerce refund. A cancellation request may still be pending.", "kiriminaja-official"),
            "instantOperationDescription" => __("Review this shipment before continuing.", "kiriminaja-official"),
            "instantOperationUnknown" => __("Unable to confirm the operation. Contact support before trying again.", "kiriminaja-official"),
            "instantResult_tracked" => __("Tracking available", "kiriminaja-official"),
            "instantResult_not_found" => __("Shipment not found", "kiriminaja-official"),
            "instantResult_cancel_requested" => __("Cancellation requested", "kiriminaja-official"),
            "instantResult_canceled" => __("Shipment canceled", "kiriminaja-official"),
            "instantPaymentPollTimeout" => __("Automatic payment checks stopped after the time limit. Refresh payment status manually.", "kiriminaja-official"),
                "instantPaymentPollExpired" => __("This payment has expired. Automatic payment checks stopped.", "kiriminaja-official"),
                "instantPaymentPollError" => __("Automatic payment checks stopped after repeated errors. Refresh payment status manually.", "kiriminaja-official"),
                "instantPaymentChecking" => __("Checking payment status…", "kiriminaja-official"),
                "instantIssue" => __("Instant order issue", "kiriminaja-official"),
                "paymentMethod" => __("Payment method", "kiriminaja-official"),
                "paymentStatus" => __("Payment status", "kiriminaja-official"),
                "paymentId" => __("Payment ID", "kiriminaja-official"),
                "search" => __("Search order…", "kiriminaja-official"),
                "orderNumber" => __("Order Number", "kiriminaja-official"),
                "kaOrderId" => __("KA Order ID", "kiriminaja-official"),
                "awb" => __("AWB", "kiriminaja-official"),
                "allDates" => __("All Dates", "kiriminaja-official"),
                "allPayment" => __("All Payment", "kiriminaja-official"),
                "cod" => __("COD", "kiriminaja-official"),
                "nonCod" => __("Non-COD", "kiriminaja-official"),
                "allCouriers" => __("All Couriers", "kiriminaja-official"),
                "allPrints" => __("All Prints", "kiriminaja-official"),
                "printed" => __("Printed", "kiriminaja-official"),
                "unprinted" => __("Unprinted", "kiriminaja-official"),
                "apply" => __("Apply", "kiriminaja-official"),
                "searchStatuses" => __("Search statuses…", "kiriminaja-official"),
                "searchCouriers" => __("Search couriers…", "kiriminaja-official"),
                "noFilterOptions" => __("No matching options.", "kiriminaja-official"),
                /* translators: %s: number of selected filter options. */
                "selectedFilters" => __("%s selected", "kiriminaja-official"),
                "items" => __("items", "kiriminaja-official"),
                "pageOf" => __("of", "kiriminaja-official"),
                "status" => __("All Status", "kiriminaja-official"),
                "requestPickup" => __("Request Pickup", "kiriminaja-official"),
                "print" => __("Print Labels", "kiriminaja-official"),
                "clear" => __("Clear filters", "kiriminaja-official"),
                "order" => __("Order / Transaction", "kiriminaja-official"),
                "expedition" => __(
                    "Expedition & Service",
                    "kiriminaja-official",
                ),
                "airwaybill" => __(
                    "Airwaybill / Order ID",
                    "kiriminaja-official",
                ),
                "route" => __("Shipment Route", "kiriminaja-official"),
                "packages" => __("Packages & Fee", "kiriminaja-official"),
                "action" => __("Action", "kiriminaja-official"),
                "notFound" => __(
                    "No transactions found.",
                    "kiriminaja-official",
                ),
                "detail" => __("Detail", "kiriminaja-official"),
                "changeOrigin" => __("Change Origin", "kiriminaja-official"),
                "currentShipmentOrigin" => __(
                    "Current shipment origin",
                    "kiriminaja-official",
                ),
                "changeToShipmentOrigin" => __(
                    "Change to shipment origin",
                    "kiriminaja-official",
                ),
                "selectShipmentOrigin" => __(
                    "Select shipment origin",
                    "kiriminaja-official",
                ),
                "manageShipmentLocations" => __(
                    "Manage shipment locations",
                    "kiriminaja-official",
                ),
                "selectedCourier" => __(
                    "Selected courier",
                    "kiriminaja-official",
                ),
                "selectCourier" => __("Select courier", "kiriminaja-official"),
                "orderBreakdown" => __("Order summary", "kiriminaja-official"),
                "orderTotal" => __("Order total", "kiriminaja-official"),
                "blocked" => __("Blocked", "kiriminaja-official"),
                "codPaidByBuyer" => __(
                    "COD Paid By Buyer",
                    "kiriminaja-official",
                ),
                "estimatedCodPayout" => __(
                    "Estimated COD Payout",
                    "kiriminaja-official",
                ),
                "adjustDeficit" => __("Adjust Deficit", "kiriminaja-official"),
                "cancel" => __("Cancel", "kiriminaja-official"),
                "printedLabel" => __("Printed", "kiriminaja-official"),
                "unprintedLabel" => __("Not Printed", "kiriminaja-official"),
                "autoRefresh" => __(
                    "Auto Refresh Timer",
                    "kiriminaja-official",
                ),
                "refreshLabels" => [
                    "60" => __("1 minute", "kiriminaja-official"),
                    "180" => __("3 minutes", "kiriminaja-official"),
                    "300" => __("5 minutes", "kiriminaja-official"),
                ],
                "pickupDate" => __("Pickup Date", "kiriminaja-official"),
                "pickupTime" => __("Pickup Time", "kiriminaja-official"),
                "weight" => __("Weight", "kiriminaja-official"),
                "actualShipping" => __(
                    "Actual Shipping",
                    "kiriminaja-official",
                ),
                "shippingCost" => __("Shipping", "kiriminaja-official"),
                "insurance" => __("Insurance", "kiriminaja-official"),
                "codFee" => __("COD Fee", "kiriminaja-official"),
                "itemDiscount" => __("Item Discount", "kiriminaja-official"),
                "shippingDiscount" => __(
                    "Shipping Discount",
                    "kiriminaja-official",
                ),
                "copyAwb" => __("Copy AWB", "kiriminaja-official"),
                "copyKaOrderId" => __(
                    "Copy KA Order ID",
                    "kiriminaja-official",
                ),
                "copied" => __("Copied", "kiriminaja-official"),
                "selectDatePlaceholder" => __(
                    "Select a pickup date",
                    "kiriminaja-official",
                ),
                "selectTimePlaceholder" => __(
                    "Select a pickup time",
                    "kiriminaja-official",
                ),
                "changeShipmentOrigin" => __(
                    "Change Shipment Origin",
                    "kiriminaja-official",
                ),
                "changeOriginDescription" => __(
                    "Choose another active shipment origin, then review the available courier before confirming.",
                    "kiriminaja-official",
                ),
                "shipmentOrigin" => __(
                    "Shipment origin",
                    "kiriminaja-official",
                ),
                "noShipmentOrigins" => __(
                    "No alternate shipment origin is available.",
                    "kiriminaja-official",
                ),
                "checkingShipping" => __(
                    "Checking available couriers…",
                    "kiriminaja-official",
                ),
                "courierConsent" => __(
                    "I agree to replace the unavailable courier with the selected service.",
                    "kiriminaja-official",
                ),
                "confirm" => __("Confirm change", "kiriminaja-official"),
                "processing" => __("Processing…", "kiriminaja-official"),
                "actionError" => __(
                    "Unable to complete this action.",
                    "kiriminaja-official",
                ),
                "cancelDeficit" => __(
                    "Cancel deficit order",
                    "kiriminaja-official",
                ),
                "confirmProcess" => __(
                    "Confirm & process",
                    "kiriminaja-official",
                ),
                "cancelShipment" => __(
                    "Cancel shipment",
                    "kiriminaja-official",
                ),
                "cancelShipmentDescription" => __(
                    "Provide a reason before cancelling this shipment.",
                    "kiriminaja-official",
                ),
                "cancelReason" => __(
                    "Cancellation reason",
                    "kiriminaja-official",
                ),
                "cancelReasonHint" => __(
                    "Enter at least 4 characters.",
                    "kiriminaja-official",
                ),
                "cancelReasonInvalid" => __(
                    "Enter at least 4 characters.",
                    "kiriminaja-official",
                ),
            ],
        ];

        include KIRIOF_DIR . "templates/transaction-process/app.php";
    }

    /**
     * Read, sanitize, and normalize list filters.
     *
     * @return array{key:string,month:string,status:string,cod:string,courier:string,print_status:string,delivery_type:string}
     */
    private function getFilters(): array
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin list filters.
        $filters = [];
        foreach (["key", "month", "status", "cod", "courier", "print_status", "delivery_type"] as $name) {
            $filters[$name] = isset( $_GET[ $name ] ) && is_string( $_GET[ $name ] )
                ? sanitize_text_field( wp_unslash( $_GET[ $name ] ) )
                : "";
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $status = WordPressTransactionListQuery::normalizeStatusFilter($filters["status"]);
        $filters["status"] = is_array($status) ? implode(",", $status) : $status;
        $filters["delivery_type"] = "order-issue" === $filters["status"]
            ? "express"
            : TransactionDeliveryType::normalize($filters["delivery_type"]);
        if ("instant" === $filters["delivery_type"]) {
            $filters["cod"] = "";
            $filters["print_status"] = "";
        }
        $filters["courier"] = implode(",", WordPressTransactionListQuery::normalizeCourierFilter($filters["courier"]));
        if (!in_array($filters["print_status"], ["0", "1"], true)) {
            $filters["print_status"] = "";
        }

        return $filters;
    }

    private function getRequestedPage(): int
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination value.
        return isset($_GET["cpage"]) ? max(1, (int) $_GET["cpage"]) : 1;
    }

    /**
     * Build month choices from the oldest transaction through the current month.
     */
    private function getMonthOptions(): array
    {
        $oldest_month = new DateTime(
            $this->query->getOldestCreatedAt() ?? "now",
        );
        $current_month = new DateTime("now");
        $interval = $oldest_month->diff($current_month);
        $total_months = $interval->y * 12 + $interval->m + 1;
        $options = [];

        for ($index = 0; $index < $total_months; $index++) {
            $date = (clone $current_month)->modify("-{$index} months");
            $options[$date->format("Y-m")] = $date->format("Y F");
        }

        return $options;
    }
}
