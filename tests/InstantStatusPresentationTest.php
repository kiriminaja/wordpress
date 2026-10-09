<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InstantStatusPresentationTest extends TestCase {
    public function test_express_and_instant_waiting_shipment_labels_match_without_changing_status_or_actions(): void {
        $expectedCases = $actualCases = [];
        foreach ( array( 'express', 'instant' ) as $delivery_type ) {
            $payload = array(
                'delivery_type' => $delivery_type,
                'status'        => 'new',
                'service'       => 'express' === $delivery_type ? 'jne' : 'gosend',
                'awb'           => '',
                'order_id'      => '',
                'wc_order'      => array( 'status' => 'processing', 'paid' => true ),
            );
            foreach ( array( 'list', 'detail', 'fallback' ) as $mode ) {
                $row = $this->row( $payload + array( 'mode' => $mode ) );
                $contractCase = 'case ' . count( $expectedCases );
                $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                    '1: row[status][label]' => 'Waiting for Shipment',
                    '2: row[status][tone]' => 'info',
                    ];
                $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                    '1: row[status][label]' => $row['status']['label'],
                    '2: row[status][tone]' => $row['status']['tone'],
                    ];
                if ( 'list' === $mode ) {
                    $contractCase = 'case ' . count( $expectedCases );
                    $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                        '1: row[selection][canProcess]' => 'instant' === $delivery_type,
                        '2: row[actions][process]' => 'instant' === $delivery_type,
                        '3: row[selection][disabled]' => false,
                        '4: row[actions][changeOrigin]' => 'express' === $delivery_type,
                        ];
                    $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                        '1: row[selection][canProcess]' => $row['selection']['canProcess'],
                        '2: row[actions][process]' => $row['actions']['process'],
                        '3: row[selection][disabled]' => $row['selection']['disabled'],
                        '4: row[actions][changeOrigin]' => $row['actions']['changeOrigin'],
                        ];
                }
            }
        }
        $this->assertSame( $expectedCases, $actualCases );
    }

    public function test_on_hold_uses_warning_in_both_delivery_lists_even_with_a_new_shipment(): void {
        foreach ( array( 'express', 'instant' ) as $delivery_type ) {
            $row = $this->row( array(
                'delivery_type' => $delivery_type,
                'status'        => 'new',
                'post_status'   => 'wc-on-hold',
                'wc_order'      => array( 'status' => 'on-hold', 'paid' => false ),
            ) );
            $this->assertSame(
                [
                '1: row[status][tone]' => 'warning',
                '2: row[status][label]' => 'On Hold',
                ],
                [
                '1: row[status][tone]' => $row['status']['tone'],
                '2: row[status][label]' => $row['status']['label'],
                ]
            );
        }
    }

    public function test_instant_list_keeps_live_woocommerce_on_hold_without_changing_shipment_actions(): void {
        $expectedCases = $actualCases = [];
        foreach (['wc-processing', 'wc-on-hold'] as $query_status) {
            foreach (['new', 'request_pickup', 'shipped'] as $shipment_status) {
                $payload = [
                    'delivery_type' => 'instant', 'service' => 'gosend', 'vehicle' => 'motor',
                    'post_status' => $query_status, 'status' => $shipment_status,
                    'wc_order' => ['status' => 'on-hold', 'paid' => false],
                    'awb' => '', 'instant_status_code' => null,
                ];
                $row = $this->row($payload);
                $contractCase = 'case ' . count( $expectedCases );
                $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                    '1: row[status][label]' => 'On Hold',
                    '2: row[status][key]' => 'wc-on-hold',
                    '3: row[status][tone]' => 'warning',
                    '4: row[actions][process]' => false,
                    '5: row[selection][canProcess]' => false,
                    ];
                $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                    '1: row[status][label]' => $row['status']['label'],
                    '2: row[status][key]' => $row['status']['key'],
                    '3: row[status][tone]' => $row['status']['tone'],
                    '4: row[actions][process]' => $row['actions']['process'],
                    '5: row[selection][canProcess]' => $row['selection']['canProcess'],
                    ];
            }
        }
        $missing_order = $this->row(['delivery_type' => 'instant', 'post_status' => 'wc-on-hold']);
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = 'On Hold';
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $missing_order['status']['label'];
        $processing = $this->row(['delivery_type' => 'instant', 'post_status' => 'wc-on-hold', 'wc_order' => ['status' => 'processing']]);
        $contractCase = 'Live order state wins over a stale query row';
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = 'Waiting for Shipment';
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $processing['status']['label'];
        $remote = $this->row(['delivery_type' => 'instant', 'instant_status_code' => 101, 'wc_order' => ['status' => 'on-hold']]);
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: remote[status][label]' => 'On Hold',
            '2: ! empty( remote[status][tooltip] )' => true,
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: remote[status][label]' => $remote['status']['label'],
            '2: ! empty( remote[status][tooltip] )' => ! empty( $remote['status']['tooltip'] ),
            ];
        $issue = $this->row(['delivery_type' => 'instant', 'is_deficit' => 1, 'wc_order' => ['status' => 'on-hold']]);
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: issue[status][label]' => 'On Hold',
            '2: ! empty( issue[status][issue] )' => true,
            '3: issue[status][deficit]' => false,
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: issue[status][label]' => $issue['status']['label'],
            '2: ! empty( issue[status][issue] )' => ! empty( $issue['status']['issue'] ),
            '3: issue[status][deficit]' => $issue['status']['deficit'],
            ];
        $this->assertSame( $expectedCases, $actualCases );
    }

    private function row(array $payload): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/transaction-instant-ui-runtime.php') . ' ' . escapeshellarg(json_encode($payload, JSON_THROW_ON_ERROR)));

        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_private_booking_claim_is_waiting_and_not_selectable_or_processable(): void {
        $expectedCases = $actualCases = [];
        $payload = [
            'delivery_type' => 'instant', 'service' => 'gosend', 'status' => 'pending',
            'instant_status_code' => null, 'instant_payment_id' => '', 'awb' => '',
            'rejected_reason' => 'Check remote state before retrying',
            'shipping_info' => json_encode(['instant_items' => [['name' => 'Saved item', 'qty' => 1]]]),
            'wc_order' => ['status' => 'processing', 'paid' => true],
        ];
        foreach (['list', 'detail', 'fallback'] as $mode) {
            $row = $this->row($payload + ['mode' => $mode]);
            $contractCase = 'case ' . count( $expectedCases );
            $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                '1: row[status][label]' => 'Waiting for Shipment',
                '2: row[status][issue]' => '',
                '3: row[actions][reconcile]' => $mode !== 'fallback',
                '4: row[actions][track]' => false,
                ];
            $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                '1: row[status][label]' => $row['status']['label'],
                '2: row[status][issue]' => $row['status']['issue'],
                '3: row[actions][reconcile]' => $row['actions']['reconcile'],
                '4: row[actions][track]' => $row['actions']['track'],
                ];
            if ($mode === 'list') {
                $contractCase = 'case ' . count( $expectedCases );
                $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                    '1: row[selection][disabled]' => true,
                    '2: row[selection][canProcess]' => false,
                    '3: row[actions][process]' => false,
                    ];
                $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                    '1: row[selection][disabled]' => $row['selection']['disabled'],
                    '2: row[selection][canProcess]' => $row['selection']['canProcess'],
                    '3: row[actions][process]' => $row['actions']['process'],
                    ];
            }
        }
        $this->assertSame( $expectedCases, $actualCases );
    }

    public function test_supported_booking_metadata_and_status_are_preserved_in_list_detail_and_fallback(): void {
        $expectedCases = $actualCases = [];
        foreach ([100, 105, 106, 200, 300, 350] as $code) {
            $payload = [
                'delivery_type' => 'instant', 'service' => 'grab_express', 'vehicle' => 'motor',
                'status' => 'request_pickup', 'awb' => '', 'order_id' => 'KA-BOOKED',
                'instant_status_code' => $code, 'instant_payment_id' => 'PAY-BOOKED',
                'instant_payment_method' => 'qris', 'instant_payment_status' => 'paid',
                'shipping_cost' => 12000, 'live_tracking_url' => 'https://tracking.example.test/KA-BOOKED',
                'shipping_info' => json_encode(['_shipping_first_name' => 'Booked recipient', '_shipping_address_1' => 'Booked street', 'instant_items' => [['name' => 'Booked item', 'qty' => 2]]], JSON_THROW_ON_ERROR),
                'shipment_location_snapshot' => json_encode(['origin_name' => 'Booked warehouse', 'origin_address' => 'Booked origin'], JSON_THROW_ON_ERROR),
            ];
            $list = $this->row($payload);
            $contractCase = 'case ' . count( $expectedCases );
            $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                '1: list[instantPayment]' => ['method' => 'qris', 'status' => 'paid', 'id' => 'PAY-BOOKED'],
                '2: list[actions][cancel]' => in_array($code, [100, 105], true),
                ];
            $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                '1: list[instantPayment]' => $list['instantPayment'],
                '2: list[actions][cancel]' => $list['actions']['cancel'],
                ];
            foreach (['detail', 'fallback'] as $mode) {
                $detail = $this->row($payload + ['mode' => $mode]);
                foreach (['key', 'label', 'tone', 'tooltip', 'issue'] as $field) {
                    $contractCase = "$code/$mode/$field";
                    $expectedCases[$contractCase . " / " . count( $expectedCases )] = $list['status'][$field];
                    $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $detail['status'][$field];
                }
                $contractCase = 'case ' . count( $expectedCases );
                $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                    '1: detail[deliveryType]' => 'instant',
                    '2: detail[vehicle]' => 'motor',
                    '3: detail[orderId]' => 'KA-BOOKED',
                    '4: detail[shipment][trackingOrder]' => 'KA-BOOKED',
                    '5: detail[shipment][awb]' => '',
                    '6: detail[shipment][paymentId]' => 'PAY-BOOKED',
                    '7: detail[shipment][paymentMethod]' => 'qris',
                    '8: detail[shipment][paymentStatus]' => 'paid',
                    '9: detail[shipment][costs][actualShipping]' => 12000,
                    '10: detail[shipment][liveTrackingUrl]' => '',
                    '11: detail[actions][cancel]' => $mode === 'detail' && in_array($code, [100, 105], true),
                    '12: detail[actions][track]' => false,
                    '13: detail[actions][reconcile]' => false,
                    ];
                $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                    '1: detail[deliveryType]' => $detail['deliveryType'],
                    '2: detail[vehicle]' => $detail['vehicle'],
                    '3: detail[orderId]' => $detail['orderId'],
                    '4: detail[shipment][trackingOrder]' => $detail['shipment']['trackingOrder'],
                    '5: detail[shipment][awb]' => $detail['shipment']['awb'],
                    '6: detail[shipment][paymentId]' => $detail['shipment']['paymentId'],
                    '7: detail[shipment][paymentMethod]' => $detail['shipment']['paymentMethod'],
                    '8: detail[shipment][paymentStatus]' => $detail['shipment']['paymentStatus'],
                    '9: detail[shipment][costs][actualShipping]' => $detail['shipment']['costs']['actualShipping'],
                    '10: detail[shipment][liveTrackingUrl]' => $detail['shipment']['liveTrackingUrl'],
                    '11: detail[actions][cancel]' => $detail['actions']['cancel'],
                    '12: detail[actions][track]' => $detail['actions']['track'],
                    '13: detail[actions][reconcile]' => $detail['actions']['reconcile'],
                    ];
            }
        }
        $this->assertSame( $expectedCases, $actualCases );
    }

    public function test_instant_remote_status_is_shared_by_list_detail_and_fallback(): void {
        $expectedCases = $actualCases = [];
        foreach ([100, 101, 105, 106, 110, 200, 300, 350, 401, 400, 701, 999] as $code) {
            $payload = ['delivery_type' => 'instant', 'instant_status_code' => $code, 'instant_payment_status' => 'paid', 'vehicle' => 'mobil'];
            $list = $this->row($payload);
            $contractCase = 'case ' . count( $expectedCases );
            $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                '1: list[selection][canProcess]' => false,
                '2: list[selection][canPrint]' => false,
                '3: list[selection][disabled]' => true,
                ];
            $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                '1: list[selection][canProcess]' => $list['selection']['canProcess'],
                '2: list[selection][canPrint]' => $list['selection']['canPrint'],
                '3: list[selection][disabled]' => $list['selection']['disabled'],
                ];
            foreach (['detail', 'fallback'] as $mode) {
                $detail = $this->row($payload + ['mode' => $mode]);
                foreach (['key', 'label', 'tone', 'tooltip', 'issue'] as $field) {
                    $contractCase = "$code/$mode/$field";
                    $expectedCases[$contractCase . " / " . count( $expectedCases )] = $list['status'][$field];
                    $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $detail['status'][$field];
                }
                $contractCase = 'case ' . count( $expectedCases );
                $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                    '1: detail[deliveryType]' => 'instant',
                    '2: detail[vehicle]' => 'mobil',
                    '3: detail[steps]' => [],
                    '4: detail[supportsLiveTracking]' => false,
                    '5: detail[shipment][printUrl]' => '',
                    ];
                $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                    '1: detail[deliveryType]' => $detail['deliveryType'],
                    '2: detail[vehicle]' => $detail['vehicle'],
                    '3: detail[steps]' => $detail['steps'],
                    '4: detail[supportsLiveTracking]' => $detail['supportsLiveTracking'],
                    '5: detail[shipment][printUrl]' => $detail['shipment']['printUrl'],
                    ];
                foreach (['changeOrigin', 'adjustDeficit', 'cancelDeficit', 'cancel'] as $action) {
                    $this->assertFalse($detail['actions'][$action]);
                }
            }
        }
        $this->assertSame( $expectedCases, $actualCases );
    }

    public function test_instant_metadata_is_not_woocommerce_cod_payment(): void {
        $expectedCases = $actualCases = [];
        $list = $this->row(['delivery_type' => 'instant', 'instant_payment_method' => 'qris', 'instant_payment_status' => 'paid', 'instant_payment_id' => 'PAY-1']);
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = ['method' => 'qris', 'status' => 'paid', 'id' => 'PAY-1'];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $list['instantPayment'];
        foreach (['detail', 'fallback'] as $mode) {
            $row = $this->row(['mode' => $mode, 'delivery_type' => 'instant', 'cod_fee' => 100, 'instant_payment_method' => 'QRIS', 'instant_payment_status' => 'unpaid', 'instant_payment_id' => 'PAY-1', 'shipping_cost' => 12000]);
            $contractCase = 'case ' . count( $expectedCases );
            $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                '1: row[isCod]' => false,
                '2: row[paymentLabel]' => 'QRIS',
                '3: row[shipment][paymentMethod]' => 'QRIS',
                '4: row[shipment][paymentStatus]' => 'unpaid',
                '5: row[shipment][paymentId]' => 'PAY-1',
                '6: row[orderId]' => 'KA-1',
                '7: row[shipment][awb]' => 'AWB-1',
                '8: row[shipment][costs][actualShipping]' => 12000,
                ];
            $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                '1: row[isCod]' => $row['isCod'],
                '2: row[paymentLabel]' => $row['paymentLabel'],
                '3: row[shipment][paymentMethod]' => $row['shipment']['paymentMethod'],
                '4: row[shipment][paymentStatus]' => $row['shipment']['paymentStatus'],
                '5: row[shipment][paymentId]' => $row['shipment']['paymentId'],
                '6: row[orderId]' => $row['orderId'],
                '7: row[shipment][awb]' => $row['shipment']['awb'],
                '8: row[shipment][costs][actualShipping]' => $row['shipment']['costs']['actualShipping'],
                ];
        }
        $this->assertSame( $expectedCases, $actualCases );
    }

    public function test_local_instant_issue_never_becomes_cod_deficit(): void {
        $expectedCases = $actualCases = [];
        foreach (['list', 'detail', 'fallback'] as $mode) {
            $row = $this->row(['mode' => $mode, 'delivery_type' => 'instant', 'is_deficit' => 1, 'vehicle' => 'invalid']);
            $contractCase = 'case ' . count( $expectedCases );
            $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                '1: row[status][label]' => 'Waiting for Shipment',
                '2: ! empty( row[status][issue] )' => true,
                '3: row[vehicle]' => null,
                ];
            $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                '1: row[status][label]' => $row['status']['label'],
                '2: ! empty( row[status][issue] )' => ! empty( $row['status']['issue'] ),
                '3: row[vehicle]' => $row['vehicle'],
                ];
        }
        $express = $this->row(['is_deficit' => 1]);
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: express[status][label]' => 'COD Deficit',
            '2: express[status][deficit]' => true,
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: express[status][label]' => $express['status']['label'],
            '2: express[status][deficit]' => $express['status']['deficit'],
            ];
        $this->assertSame( $expectedCases, $actualCases );
    }

    public function test_find_new_driver_is_informational_and_no_express_timeline_is_rendered(): void {
        $row = $this->row(['delivery_type' => 'instant', 'instant_status_code' => 101]);
        $this->assertSame(
            [
            '1: row[status][label]' => 'Find New Driver',
            '2: ! empty( row[status][tooltip] )' => true,
            '3: empty(row[status][issue])' => true,
            ],
            [
            '1: row[status][label]' => $row['status']['label'],
            '2: ! empty( row[status][tooltip] )' => ! empty( $row['status']['tooltip'] ),
            '3: empty(row[status][issue])' => empty($row['status']['issue']),
            ]
        );
    }
}
