<?php
namespace KiriminAjaOfficial\Services;

use KiriminAjaOfficial\Services\TransactionProcessServices\RecipientDataResolver;

// Exit if accessed directly.
if (!defined("ABSPATH")) {
    exit();
}

/**
 * Converts one regular-delivery transaction into the Svelte detail workspace contract.
 */
class TransactionDetailPageData
{
    private RecipientDataResolver $recipient_resolver;
    private ShipmentLocationService $location_service;
    private ShippingDiscountCouponService $coupon_service;
    private TransactionOriginResolver $origin_resolver;

    public function __construct(
        ?RecipientDataResolver $recipient_resolver = null,
        ?ShipmentLocationService $location_service = null,
        ?ShippingDiscountCouponService $coupon_service = null,
        ?TransactionOriginResolver $origin_resolver = null,
    ) {
        $this->recipient_resolver =
            $recipient_resolver ?? new RecipientDataResolver();
        $this->location_service =
            $location_service ?? new ShipmentLocationService();
        $this->coupon_service =
            $coupon_service ?? new ShippingDiscountCouponService();
        $this->origin_resolver =
            $origin_resolver ??
            new TransactionOriginResolver($this->location_service);
    }

    /** @return array<int,array{id:int,name:string,address:string}> */
    private function shipment_locations(): array
    {
        return array_values(
            array_map(function ($location): array {
                return [
                    "id" => (int) ($location->id ?? 0),
                    "name" =>
                        (string) ($location->name ??
                            ($location->location_name ?? "")),
                    "address" => $this->location_service->formatAddress(
                        $location,
                    ),
                ];
            }, $this->location_service->repository()->getAll(true)),
        );
    }

    /**
     * @param object $transaction Transaction database row.
     * @return array<string,mixed>
     */
    public function prepare(object $transaction): array
    {
        $wc_order = function_exists("wc_get_order")
            ? wc_get_order((int) ($transaction->wp_wc_order_stat_order_id ?? 0))
            : false;
        $shipping_info = json_decode(
            (string) ($transaction->shipping_info ?? "{}"),
        );
        $recipient = $this->recipient_resolver->resolve(
            $wc_order,
            $shipping_info,
            $transaction,
        );
        $origin = $this->origin_resolver->resolve($transaction);
        $sender = [
            "name" => $origin["name"],
            "phone" => $origin["phone"],
            "address" => $origin["addressLines"],
        ];
        $shipping = (float) ($transaction->shipping_cost ?? 0);
        $insurance = (float) ($transaction->insurance_cost ?? 0);
        $cod_fee = (float) ($transaction->cod_fee ?? 0);
        $discount = max(0, (float) ($transaction->discount_amount ?? 0));
        $cod_value =
            $cod_fee > 0
                ? $shipping +
                    $insurance +
                    $cod_fee +
                    (float) ($transaction->transaction_value ?? 0)
                : 0.0;
        $payment_label =
            $cod_fee > 0
                ? __("COD", "kiriminaja-official")
                : __("Non-COD", "kiriminaja-official");
        $status = (string) ($transaction->status ?? "new");
        $awb = (string) ($transaction->awb ?? "");
        $is_deficit = !empty($transaction->is_deficit);
        $terminal_statuses = [
            "shipped",
            "finished",
            "returned",
            "return",
            "canceled",
        ];
        $can_cancel =
            !$is_deficit &&
            "" !== $awb &&
            !in_array($status, $terminal_statuses, true);
        $order_url =
            $wc_order && method_exists($wc_order, "get_edit_order_url")
                ? (string) $wc_order->get_edit_order_url()
                : "";
        $wc_status =
            $wc_order && method_exists($wc_order, "get_status")
                ? (string) $wc_order->get_status()
                : "";
        $payment_status =
            $cod_fee > 0
                ? ""
                : ("on-hold" === $wc_status
                    ? __("Unpaid", "kiriminaja-official")
                    : __("Paid", "kiriminaja-official"));
        $subtotal = $wc_order ? (float) $wc_order->get_subtotal() : 0.0;
        $order_total = $wc_order
            ? (float) $wc_order->get_total()
            : $subtotal +
                max(0.0, $shipping - $discount) +
                $insurance +
                $cod_fee;
        $shipping_discount = $wc_order
            ? max(0.0, $shipping - (float) $wc_order->get_shipping_total())
            : $discount;
        $paid_shipping = $wc_order
            ? max(0.0, (float) $wc_order->get_shipping_total())
            : max(0.0, $shipping - $shipping_discount);
        $total_shipping = $shipping + $insurance + $cod_fee;
        $courier_name = kiriof_helper()->formatServiceName(
            $transaction->service ?? "",
            $transaction->service_name ?? "",
        );
        $items = $this->items($wc_order);
        $notes = $this->notes($wc_order);
        $action_data = $this->action_data(
            $transaction,
            $wc_order,
            $origin,
            $shipping,
            $insurance,
            $cod_fee,
        );
        $print_url =
            "" !== (string) ($transaction->awb ?? "")
                ? admin_url(
                    "admin-post.php?action=kiriof_resi_print&oids=" .
                        rawurlencode((string) ($transaction->order_id ?? "")) .
                        "&_wpnonce=" .
                        wp_create_nonce("kiriof_resi_print"),
                )
                : "";

        $toolbar = [
            "logoUrl" => KIRIOF_URL . "assets/admin/img/icon-128x128.png",
            "rootUrl" => admin_url("admin.php?page=kiriminaja-transaction"),
            "rootLabel" => __("Transactions", "kiriminaja-official"),
            "title" =>
                "#" .
                (string) ($transaction->wp_wc_order_stat_order_id ??
                    $transaction->id),
            "menu" => $this->toolbar_menu(),
        ];
        $toolbar_update = ( new PluginUpdateNoticeService(  ) )->get_toolbar_update();
        if ($toolbar_update) {
            $toolbar["update"] = $toolbar_update;
        }
        if (class_exists(RevampAnnouncementService::class)) {
            $toolbar = RevampAnnouncementService::attach_announcement($toolbar);
        }

        return [
            "toolbar" => $toolbar,
            "shipmentLocations" => $this->shipment_locations(),
            "locationsUrl" => admin_url(
                "admin.php?page=wc-settings&tab=kiriminaja_warehouses",
            ),
            "transaction" => [
                "id" => (int) ($transaction->id ?? 0),
                "orderId" => (string) ($transaction->order_id ?? ""),
                "orderNumber" =>
                    "#" .
                    (string) ($transaction->wp_wc_order_stat_order_id ?? ""),
                "orderUrl" => $order_url,
                "createdAt" => $this->date($transaction->created_at ?? ""),
                "paymentLabel" => $payment_label,
                "isCod" => $cod_fee > 0,
                "supportsLiveTracking" => false,
                "pickupNumber" => (string) ($transaction->pickup_number ?? ""),
                "status" => [
                    "label" => $this->status_label($status),
                    "tone" => $this->status_tone($status),
                ],
                "steps" => $this->steps($transaction, $status),
                "sender" => $sender,
                "recipient" => [
                    "name" => trim(
                        (string) $recipient["first_name"] .
                            " " .
                            (string) $recipient["last_name"],
                    ),
                    "phone" => (string) $recipient["phone"],
                    "address" => array_values(
                        array_filter([
                            (string) $recipient["address_1"],
                            (string) $recipient["address_2"],
                            implode(
                                ", ",
                                array_filter([
                                    (string) ($transaction->destination_sub_district ??
                                        ""),
                                    (string) $recipient["city"],
                                    (string) $recipient["state"],
                                ]),
                            ),
                            implode(
                                ", ",
                                array_filter([
                                    (string) $recipient["postcode"],
                                    (string) $recipient["country"],
                                ]),
                            ),
                        ]),
                    ),
                ],
                "package" => [
                    "weight" => (int) ($transaction->weight ?? 0),
                    "length" => (float) ($transaction->length ?? 0),
                    "width" => (float) ($transaction->width ?? 0),
                    "height" => (float) ($transaction->height ?? 0),
                ],
                "items" => $items,
                "notes" => $notes,
                "shipment" => [
                    "courier" => [
                        "code" => strtolower(
                            (string) ($transaction->service ?? ""),
                        ),
                        "service" => $courier_name,
                    ],
                    "awb" => $awb,
                    "paymentStatus" => $payment_status,
                    "costs" => [
                        "orderTotal" => $order_total,
                        "subtotal" => $subtotal,
                        "totalShipping" => $total_shipping,
                        "actualShipping" => $shipping,
                        "shippingDiscount" => $shipping_discount,
                        "shipping" => $paid_shipping,
                        "insurance" => $insurance,
                        "codFee" => $cod_fee,
                        "itemDiscount" => $wc_order
                            ? (float) $wc_order->get_discount_total()
                            : 0.0,
                        "total" => max(
                            0,
                            $paid_shipping + $insurance + $cod_fee,
                        ),
                    ],
                    "codValue" => $cod_value,
                    "printUrl" => $print_url,
                    "trackingOrder" => (string) ($transaction->order_id ?? ""),
                ],
                "actions" => [
                    "changeOrigin" => "new" === $status,
                    "adjustDeficit" => $is_deficit,
                    "cancelDeficit" => $is_deficit,
                    "cancel" => $can_cancel,
                    "data" => $action_data,
                ],
            ],
            "ajax" => [
                "url" => admin_url("admin-ajax.php"),
                "nonce" => wp_create_nonce(KIRIOF_NONCE),
                "printPreviewNonce" => wp_create_nonce("kiriof_resi_print"),
            ],
            "i18n" => $this->i18n(),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function items($order): array
    {
        if (!$order || !method_exists($order, "get_items")) {
            return [];
        }
        $items = [];
        foreach ($order->get_items("line_item") as $item) {
            $product = method_exists($item, "get_product") ? $item->get_product() : false;
            $sku = $product && method_exists($product, "get_sku") ? $product->get_sku() : "";
            $items[] = [
                "name" => (string) $item->get_name(),
                "quantity" => (int) $item->get_quantity(),
                "total" => (float) $item->get_total(),
                "sku" => (string) $sku,
            ];
        }
        return $items;
    }

    /** @return array<int,array<string,string>> */
    private function notes($order): array
    {
        if (!$order) {
            return [];
        }
        $notes = [];
        $customer_note = method_exists($order, "get_customer_note")
            ? trim((string) $order->get_customer_note())
            : "";
        if ("" !== $customer_note) {
            $notes[] = [
                "label" => __("Customer note", "kiriminaja-official"),
                "content" => $customer_note,
            ];
        }
        if (
            function_exists("wc_get_order_notes") &&
            method_exists($order, "get_id")
        ) {
            foreach (
                wc_get_order_notes([
                    "order_id" => $order->get_id(),
                    "type" => "customer",
                ])
                as $note
            ) {
                $content = trim(
                    wp_strip_all_tags((string) ($note->content ?? "")),
                );
                if ("" !== $content && $content !== $customer_note) {
                    $notes[] = [
                        "label" => __("Order note", "kiriminaja-official"),
                        "content" => $content,
                    ];
                }
            }
        }
        return $notes;
    }

    /** @return array<string,mixed> */
    private function action_data(
        object $transaction,
        $order,
        array $origin,
        float $shipping,
        float $insurance,
        float $cod_fee,
    ): array {
        $shipping_discount = $order
            ? max(0, $shipping - (float) $order->get_shipping_total())
            : max(0, (float) ($transaction->discount_amount ?? 0));
        $coupon_scopes = $order
            ? $this->coupon_service->splitCouponCodesByScope(
                (array) $order->get_coupon_codes(),
            )
            : ["item" => [], "shipping" => []];
        return [
            "nonce" => wp_create_nonce(KIRIOF_NONCE),
            "kaOrderId" => (string) ($transaction->order_id ?? ""),
            "currentOrigin" => $origin["name"],
            "currentOriginAddress" => implode(", ", $origin["address"]),
            "currentLocationId" => $origin["locationId"],
            "currentCod" => $order ? (float) $order->get_total() : 0,
            "codMinimum" => $shipping + $insurance + $cod_fee,
            "codMaximum" => defined("KIRIOF_MAX_COD_AMOUNT")
                ? (float) KIRIOF_MAX_COD_AMOUNT
                : 3000000.0,
            "shippingCost" => $shipping,
            "insuranceFee" => $insurance,
            "codFee" => $cod_fee,
            "itemPrice" => $order ? (float) $order->get_subtotal() : 0,
            "itemDiscount" => $order ? (float) $order->get_discount_total() : 0,
            "shippingDiscount" => $shipping_discount,
            "itemCoupon" => (string) ($coupon_scopes["item"][0] ?? ""),
            "shippingCoupon" => (string) ($coupon_scopes["shipping"][0] ?? ""),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function steps(object $transaction, string $status): array
    {
        $stages = [
            [
                "key" => "created",
                "label" => __("Created", "kiriminaja-official"),
                "date" => $transaction->created_at ?? "",
            ],
            [
                "key" => "pickup",
                "label" => __("Shipment Request", "kiriminaja-official"),
                "date" => $transaction->request_pickup_at ?? "",
            ],
            [
                "key" => "shipping",
                "label" => __("Shipping", "kiriminaja-official"),
                "date" => $transaction->shipped_at ?? "",
            ],
            [
                "key" => "delivered",
                "label" => __("Delivered", "kiriminaja-official"),
                "date" => $transaction->finished_at ?? "",
            ],
        ];
        $completed = [
            "created" => true,
            "pickup" => in_array(
                $status,
                ["request_pickup", "pending", "shipped", "finished"],
                true,
            ),
            "shipping" => in_array($status, ["shipped", "finished"], true),
            "delivered" => "finished" === $status,
        ];
        foreach ($stages as &$stage) {
            $stage["completed"] = $completed[$stage["key"]];
            $stage["date"] = $this->date($stage["date"]);
            unset($stage["key"]);
        }
        return $stages;
    }

    private function date($value): string
    {
        return "" === (string) $value
            ? ""
            : wp_date("d M Y, H:i", strtotime((string) $value));
    }
    private function status_label(string $status): string
    {
        $labels = [
            "new" => __("New", "kiriminaja-official"),
            "request_pickup" => __("Request Pickup", "kiriminaja-official"),
            "pending" => __("Pending", "kiriminaja-official"),
            "shipped" => __("Shipped", "kiriminaja-official"),
            "finished" => __("Delivered", "kiriminaja-official"),
            "canceled" => __("Cancelled", "kiriminaja-official"),
            "rejected" => __("Rejected", "kiriminaja-official"),
            "return" => __("Return", "kiriminaja-official"),
            "returned" => __("Returned", "kiriminaja-official"),
        ];
        return $labels[$status] ?? ucfirst($status);
    }
    private function status_tone(string $status): string
    {
        if ("finished" === $status) {
            return "success";
        }
        if ("shipped" === $status) {
            return "teal";
        }
        if (
            in_array(
                $status,
                ["canceled", "rejected", "return", "returned"],
                true,
            )
        ) {
            return "danger";
        }
        if (in_array($status, ["request_pickup", "pending"], true)) {
            return "info";
        }
        return "primary";
    }
    /** @return array<string,mixed> */
    private function toolbar_menu(): array
    {
        return [
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
    }
    /** @return array<string,string> */
    private function i18n(): array
    {
        return [
            "pickupId" => __("Pickup ID", "kiriminaja-official"),
            "printLabel" => __("Print Label", "kiriminaja-official"),
            "liveTracking" => __("Live Tracking", "kiriminaja-official"),
            "sender" => __("Sender", "kiriminaja-official"),
            "recipient" => __("Recipient", "kiriminaja-official"),
            "contactCustomer" => __("Contact Customer", "kiriminaja-official"),
            "package" => __("Package", "kiriminaja-official"),
            "detailPackageProduct" => __(
                "Detail Package & Product",
                "kiriminaja-official",
            ),
            "orderDetail" => __("Order Detail", "kiriminaja-official"),
            "products" => __("Products", "kiriminaja-official"),
            "orderNotes" => __("Order notes", "kiriminaja-official"),
            "shipment" => __("Shipment", "kiriminaja-official"),
            "airwaybill" => __("Airwaybill", "kiriminaja-official"),
            "copyAwb" => __("Copy AWB", "kiriminaja-official"),
            "pickup" => __("Pickup", "kiriminaja-official"),
            "weight" => __("Weight", "kiriminaja-official"),
            "dimensions" => __("Dimensions", "kiriminaja-official"),
            "orderId" => __("Order ID", "kiriminaja-official"),
            "totalShipping" => __("Total Shipping", "kiriminaja-official"),
            "orderSubtotal" => __("Order Subtotal", "kiriminaja-official"),
            "actualShipping" => __("Actual Shipping", "kiriminaja-official"),
            "shippingDiscount" => __(
                "Shipping Discount",
                "kiriminaja-official",
            ),
            "itemDiscount" => __("Item Discount", "kiriminaja-official"),
            "shipping" => __("Shipping", "kiriminaja-official"),
            "insurance" => __("Insurance", "kiriminaja-official"),
            "codFee" => __("COD Fee", "kiriminaja-official"),
            "discount" => __("Discount", "kiriminaja-official"),
            "total" => __("Total", "kiriminaja-official"),
            "codValue" => __("COD value", "kiriminaja-official"),
            "tracking" => __("Tracking history", "kiriminaja-official"),
            "trackingEmpty" => __(
                "No tracking history is available yet.",
                "kiriminaja-official",
            ),
            "trackingError" => __(
                "Unable to load tracking history.",
                "kiriminaja-official",
            ),
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
            "selectedCourier" => __("Selected courier", "kiriminaja-official"),
            "selectCourier" => __("Select courier", "kiriminaja-official"),
            "orderBreakdown" => __("Order summary", "kiriminaja-official"),
            "orderTotal" => __("Order total", "kiriminaja-official"),
            "blocked" => __("Blocked", "kiriminaja-official"),
            "codPaidByBuyer" => __("COD Paid By Buyer", "kiriminaja-official"),
            "estimatedCodPayout" => __(
                "Estimated COD Payout",
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
            "shipmentOrigin" => __("Shipment origin", "kiriminaja-official"),
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
            "adjustDeficit" => __("Adjust Deficit", "kiriminaja-official"),
            "cancelDeficit" => __(
                "Cancel deficit order",
                "kiriminaja-official",
            ),
            "confirmProcess" => __("Confirm & process", "kiriminaja-official"),
            "cancelShipment" => __("Cancel shipment", "kiriminaja-official"),
            "cancelShipmentDescription" => __(
                "Provide a reason before cancelling this shipment.",
                "kiriminaja-official",
            ),
            "cancelReason" => __("Cancellation reason", "kiriminaja-official"),
            "cancelReasonHint" => __(
                "Enter at least 4 characters.",
                "kiriminaja-official",
            ),
            "cancelReasonInvalid" => __(
                "Enter at least 4 characters.",
                "kiriminaja-official",
            ),
            "cancel" => __("Cancel", "kiriminaja-official"),
        ];
    }
}
