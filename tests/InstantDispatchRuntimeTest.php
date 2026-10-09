<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantDispatchRuntimeTest extends TestCase {
    #[Test]
    public function quote_returns_actual_numeric_credit_balance_or_null_without_invalidating_quotes(): void {
        foreach (array(4000724100, '4000724100', '4,000,724,100', -1) as $balance) {
            $r = $this->runFixture(array('quote_only'=>true, 'profile'=>'CREDIT', 'credit_balance'=>$balance));
            $this->assertSame(
                [
                    'quote.rows.0.eligible' => true,
                    'present fields: r.quote, .credit_balance\' => true' => ['credit_balance'],
                ],
                [
                    'quote.rows.0.eligible' => $r['quote']['rows'][0]['eligible'],
                    'present fields: r.quote, .credit_balance\' => true' => array_keys(array_intersect_key($r['quote'], ['credit_balance' => true])),
                ],
                __FUNCTION__
            );
            if ($balance === 4000724100 || $balance === '4000724100') {
                $this->assertEquals(4000724100, $r['quote']['credit_balance']);
            } else {
                $this->assertNull($r['quote']['credit_balance']);
            }
        }
        $r = $this->runFixture(array('quote_only'=>true, 'profile'=>'TOP'));
        $this->assertNull($r['quote']['credit_balance']);
    }

    #[Test]
    public function dedicated_credit_validation_is_read_only_retryable_and_uses_selected_integer_total(): void {
        $expectedContracts = [];
        $actualContracts = [];

        foreach (array('12345', '1234567', 'abcdef', '１２３４５６', "123456\n", '') as $pin) {
            $r = $this->runFixture(array('validate_credit'=>true, 'quote_only'=>true, 'profile'=>'CREDIT', 'pin'=>$pin, 'retry_validation'=>true));
            $case = (__FUNCTION__) . ' #' . count($expectedContracts);
            $expectedContracts[$case] = [
                    'validation_error' => 'Unable to validate KA Credit payment.',
                    'validation_exception' => 'InvalidArgumentException',
                    'validation_retry' => array('valid'=>true),
                    'count(r.credits)' => 1,
                ];
            $actualContracts[$case] = [
                    'validation_error' => $r['validation_error'],
                    'validation_exception' => $r['validation_exception'],
                    'validation_retry' => $r['validation_retry'],
                    'count(r.credits)' => count($r['credits']),
                ];
            $this->assertReadOnlyValidation($r);
        }
        foreach (array('credit_fail', 'credit_throw') as $failure) {
            $r = $this->runFixture(array('validate_credit'=>true, 'quote_only'=>true, 'profile'=>'CREDIT', 'pin'=>'123456', $failure=>true, 'retry_validation'=>true));
            $case = (__FUNCTION__) . ' #' . count($expectedContracts);
            $expectedContracts[$case] = [
                    'validation_error' => 'Unable to validate KA Credit payment.',
                    'validation_exception' => 'InvalidArgumentException',
                    'redaction: r.validation_error, \'123456\'' => 0,
                    'validation_retry' => array('valid'=>true),
                ];
            $actualContracts[$case] = [
                    'validation_error' => $r['validation_error'],
                    'validation_exception' => $r['validation_exception'],
                    'redaction: r.validation_error, \'123456\'' => substr_count($r['validation_error'], '123456'),
                    'validation_retry' => $r['validation_retry'],
                ];
            $this->assertReadOnlyValidation($r);
        }
        $r = $this->runFixture(array('validate_credit'=>true, 'quote_only'=>true, 'profile'=>'CREDIT', 'pin'=>'123456', 'count'=>2, 'price'=>2000362050, 'credit_balance'=>4000724100));
        $case = (__FUNCTION__) . ' #' . count($expectedContracts);
        $expectedContracts[$case] = [
                'validation' => array('valid'=>true),
                'quote.credit_balance' => 4000724100,
                'credits' => array(array('amount'=>4000724100, 'valid_pin'=>true)),
            ];
        $actualContracts[$case] = [
                'validation' => $r['validation'],
                'quote.credit_balance' => $r['quote']['credit_balance'],
                'credits' => $r['credits'],
            ];
        $this->assertReadOnlyValidation($r);
        $r = $this->runFixture(array('validate_credit'=>true, 'quote_only'=>true, 'profile'=>'CREDIT', 'pin'=>'123456', 'count'=>2, 'dispatch_ids'=>array('KA-2')));
        $case = (__FUNCTION__) . ' #' . count($expectedContracts);
        $expectedContracts[$case] = 18000;
        $actualContracts[$case] = $r['credits'][0]['amount'];
        $this->assertReadOnlyValidation($r);
    
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
}

    private function assertReadOnlyValidation(array $r): void {
        $this->assertSame($r['before_validation_rows'], $r['after_validation']['rows']);
        foreach (array('claims', 'writes', 'books', 'options') as $key) {
            $this->assertSame(array(), $r['after_validation'][$key]);
        }
        $this->assertCount(1, $r['after_validation']['transients']);
    }

    #[Test]
    public function dedicated_credit_validation_rejects_stale_quotes_and_dispatch_revalidates_credit(): void {
        $expectedContracts = [];
        $actualContracts = [];

        foreach (array(array('user_change'=>true), array('expire'=>true), array('stale'=>true), array('dispatch_ids'=>array('KA-2')), array('validate_token'=>'invalid'), array('profile_after'=>'TOP'), array('profile'=>'TOP'), array('quote_price'=>1.5), array('quote_price'=>'18000'), array('quote_price'=>-1), array('count'=>2, 'quote_price'=>PHP_INT_MAX)) as $input) {
            $r = $this->runFixture($input + array('validate_credit'=>true, 'quote_only'=>true, 'profile'=>'CREDIT', 'pin'=>'123456'));
            $this->assertNotEmpty($r['validation_error'], json_encode($input));
            $case = (__FUNCTION__) . ' #' . count($expectedContracts);
            $expectedContracts[$case] = array();
            $actualContracts[$case] = $r['credits'];
            $this->assertReadOnlyValidation($r);
        }
        $r = $this->runFixture(array('validate_credit'=>true, 'profile'=>'CREDIT', 'method'=>'credit', 'pin'=>'123456'));
        $case = (__FUNCTION__) . ' #' . count($expectedContracts);
        $expectedContracts[$case] = array('valid'=>true);
        $actualContracts[$case] = $r['validation'];
        $this->assertReadOnlyValidation($r);
        $case = (__FUNCTION__) . ' #' . count($expectedContracts);
        $expectedContracts[$case] = [
                'count(r.credits)' => 2,
                'credits.1.amount' => 18000,
                'dispatch.rows.0.status' => 'booked',
            ];
        $actualContracts[$case] = [
                'count(r.credits)' => count($r['credits']),
                'credits.1.amount' => $r['credits'][1]['amount'],
                'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
            ];
        $r = $this->runFixture(array('validate_credit'=>true, 'profile'=>'CREDIT', 'method'=>'credit', 'pin'=>'123456', 'credit_fail_after_validation'=>true));
        $case = (__FUNCTION__) . ' #' . count($expectedContracts);
        $expectedContracts[$case] = [
                'validation' => array('valid'=>true),
                'error' => 'Unable to validate KA Credit payment.',
                'claims' => array(),
                'books' => array(),
                'count(r.transients)' => 1,
            ];
        $actualContracts[$case] = [
                'validation' => $r['validation'],
                'error' => $r['error'],
                'claims' => $r['claims'],
                'books' => $r['books'],
                'count(r.transients)' => count($r['transients']),
            ];
    
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
}

    #[Test]
    public function working_qris_response_confirms_without_awb_handles_service_case_and_persists_valid_route(): void {
        foreach (array('', 'https://tracking.example.test/booking') as $url) {
            $r = $this->runFixture(array('working_sample'=>true,'row'=>array('service_name'=>'instant'),'price_service'=>'instant','sample_url'=>$url,'retry'=>true));
            $this->assertSame(
                [
                    'error' => '',
                    'books.0.payment_method' => 'qris',
                    'books.0.address_note' => 'Pickup note',
                    'books.0.packages.0.destination.address_note' => 'Gedung A Lantai 5',
                    'dispatch.rows.0.status' => 'booked',
                    'dispatch.rows.0.awb' => '',
                    'rows.0.status' => 'request_pickup',
                    'rows.0.instant_status_code' => 110,
                    'rows.0.instant_payment_id' => 'EPR-5487434915',
                    'rows.0.instant_payment_status' => 'unpaid',
                    'rows.0.instant_payment_method' => 'qris',
                    'rows.0.live_tracking_url ?? \'\'' => $url,
                    'dispatch.payments.0.amount' => 31500,
                    'dispatch.payments.0.qr_content' => 'some-random-qr-string',
                    'dispatch.payments.0.order_ids' => array('KA-1'),
                ],
                [
                    'error' => $r['error'],
                    'books.0.payment_method' => $r['books'][0]['payment_method'],
                    'books.0.address_note' => $r['books'][0]['address_note'],
                    'books.0.packages.0.destination.address_note' => $r['books'][0]['packages'][0]['destination']['address_note'],
                    'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                    'dispatch.rows.0.awb' => $r['dispatch']['rows'][0]['awb'],
                    'rows.0.status' => $r['rows'][0]['status'],
                    'rows.0.instant_status_code' => $r['rows'][0]['instant_status_code'],
                    'rows.0.instant_payment_id' => $r['rows'][0]['instant_payment_id'],
                    'rows.0.instant_payment_status' => $r['rows'][0]['instant_payment_status'],
                    'rows.0.instant_payment_method' => $r['rows'][0]['instant_payment_method'],
                    'rows.0.live_tracking_url ?? \'\'' => $r['rows'][0]['live_tracking_url'] ?? '',
                    'dispatch.payments.0.amount' => $r['dispatch']['payments'][0]['amount'],
                    'dispatch.payments.0.qr_content' => $r['dispatch']['payments'][0]['qr_content'],
                    'dispatch.payments.0.order_ids' => $r['dispatch']['payments'][0]['order_ids'],
                ],
                __FUNCTION__
            );
            $snapshot = json_decode($r['rows'][0]['shipping_info'], true);
            $this->assertSame('Gedung A Lantai 5', $snapshot['_shipping_address_2']);
            $this->assertEquals(array(array(38.5,-120.2),array(40.7,-120.95),array(43.252,-126.453)), $snapshot['instant_route_points']);
            $this->assertSame(
                [
                    'absent fields: snapshot, .poly_line\' => true' => [],
                    'absent fields: snapshot, ._kiriof_instant_prepared\' => true' => [],
                    'rows.0.service_name' => 'instant',
                ],
                [
                    'absent fields: snapshot, .poly_line\' => true' => array_intersect_key($snapshot, ['poly_line' => true]),
                    'absent fields: snapshot, ._kiriof_instant_prepared\' => true' => array_intersect_key($snapshot, ['_kiriof_instant_prepared' => true]),
                    'rows.0.service_name' => $r['rows'][0]['service_name'],
                ],
                __FUNCTION__
            );
            $this->assertNotEmpty($r['retry_error']);
            $this->assertSame(
                [
                    'count(r.books)' => 1,
                    'woo.1.completions' => 0,
                ],
                [
                    'count(r.books)' => count($r['books']),
                    'woo.1.completions' => $r['woo'][1]['completions'],
                ],
                __FUNCTION__
            );
        }
        foreach (array('INSTANT', 'Instant') as $type) {
            $r = $this->runFixture(array('remote_service_type'=>$type,'row'=>array('service_name'=>'instant'),'price_service'=>'instant'));
            $this->assertSame('booked', $r['dispatch']['rows'][0]['status']);
        }
        foreach (array('sameday', ' Instant', 'instant-extra') as $type) {
            $r = $this->runFixture(array('remote_service_type'=>$type,'row'=>array('service_name'=>'instant'),'price_service'=>'instant'));
            $this->assertSame('unknown', $r['dispatch']['rows'][0]['status']);
        }
    }

    #[Test]
    public function qris_confirmation_preserves_authoritative_historical_origin_snapshot(): void {
        $origin = json_encode(array(
            'origin_name'=>'Historical warehouse sender', 'origin_phone'=>'0812345678',
            'origin_address'=>'Long enough original warehouse address', 'origin_zip_code'=>'12345',
            'origin_latitude'=>'-6.2', 'origin_longitude'=>'106.8',
            'origin_country'=>'ID', 'origin_sub_district_id'=>123, 'origin_timezone'=>'WIB',
        ));
        $r = $this->runFixture(array('working_sample'=>true, 'row'=>array('service_name'=>'instant','shipment_location_snapshot'=>$origin),'price_service'=>'instant'));
        $this->assertSame(
            [
                'dispatch.rows.0.status' => 'booked',
                'rows.0.shipment_location_snapshot' => $origin,
                'rows.0.instant_payment_status' => 'unpaid',
            ],
            [
                'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                'rows.0.shipment_location_snapshot' => $r['rows'][0]['shipment_location_snapshot'],
                'rows.0.instant_payment_status' => $r['rows'][0]['instant_payment_status'],
            ],
            __FUNCTION__
        );
        $this->assertNotEmpty($r['dispatch']['payments'][0]['qr_content']);
        $this->assertSame('', $r['dispatch']['rows'][0]['awb']);
    }

    #[Test]
    public function confirmation_diagnostics_explain_invalid_status_and_origin_conflict_without_values(): void {
        foreach (array(
            array(array('remote_status'=>999), 'package_status_unsupported'),
            array(array('remote_status'=>110,'remote_status_alias'=>100), 'package_status_alias_conflict'),
            array(array('origin_after_book'=>'{"name":"Changed confidential origin"}'), 'origin_snapshot_conflict'),
        ) as [$input, $reason]) {
            $r = $this->runFixture($input + array('new_retry'=>true));
            $this->assertSame(
                [
                    'dispatch.rows.0.status' => 'unknown',
                    'logs.1.2.validation_reason' => $reason,
                    'rows.0.status' => 'pending',
                    'count(r.books)' => 1,
                ],
                [
                    'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                    'logs.1.2.validation_reason' => $r['logs'][1][2]['validation_reason'],
                    'rows.0.status' => $r['rows'][0]['status'],
                    'count(r.books)' => count($r['books']),
                ],
                __FUNCTION__
            );
            $this->assertNotEmpty($r['new_retry_error']);
            $this->assertStringNotContainsString('Changed confidential origin', json_encode($r['logs']));
        }
    }

    #[Test]
    public function unknown_outcomes_have_safe_correlated_diagnostics_without_weakening_claims(): void {
        $expectedContracts = [];
        $actualContracts = [];

        foreach (array(
            array(array('timeout'=>true), 'booking_call_exception', 'response_matching'),
            array(array('false'=>true), 'booking_unacknowledged', 'response_matching'),
            array(array('missing'=>true), 'package_match_not_unique', 'response_matching'),
            array(array('duplicate'=>true), 'package_match_not_unique', 'response_matching'),
            array(array('no_payment'=>true), 'payment_id_missing_or_invalid', 'response_matching'),
            array(array('remote_service'=>'grab_express'), 'booking_identity_mismatch', 'booking_identity'),
            array(array('remote_status'=>'invalid'), 'booking_response_invalid', 'confirm_booking'),
            array(array('write_fail'=>true), 'local_confirmation_failed', 'confirm_booking'),
        ) as [$input, $code, $stage]) {
            $r = $this->runFixture($input + array('new_retry'=>true));
            $this->assertCount(2, $r['logs']);
            $log = $r['logs'][1];
            $case = (__FUNCTION__) . ' #' . count($expectedContracts);
            $expectedContracts[$case] = [
                    'log.0' => 'error',
                    'log.3' => 'kiriminaja_instant',
                    'log.2.backtrace' => false,
                    'log.2.code' => $code,
                    'log.2.stage' => $stage,
                ];
            $actualContracts[$case] = [
                    'log.0' => $log[0],
                    'log.3' => $log[3],
                    'log.2.backtrace' => $log[2]['backtrace'],
                    'log.2.code' => $log[2]['code'],
                    'log.2.stage' => $log[2]['stage'],
                ];
            if (isset($input['remote_status'])) {
                $case = (__FUNCTION__) . ' #' . count($expectedContracts);
                $expectedContracts[$case] = 'package_status_shape_invalid';
                $actualContracts[$case] = $log[2]['validation_reason'];
            }
            $case = (__FUNCTION__) . ' #' . count($expectedContracts);
            $expectedContracts[$case] = [
                    'format: \'/\\\\\\\\A.a-f0-9{16}\\\\\\\\z/\', log.2.reference' => 1,
                    'log.2.reference' => $r['logs'][0][2]['reference'],
                    'message fragment: r.dispatch.rows.0.message' => 'Reference: ' . $log[2]['reference'],
                    'message fragment: r.dispatch.rows.0.message' => 'WooCommerce → Status → Logs',
                    'dispatch.rows.0.status' => 'unknown',
                    'rows.0.status' => 'pending',
                ];
            $actualContracts[$case] = [
                    'format: \'/\\\\\\\\A.a-f0-9{16}\\\\\\\\z/\', log.2.reference' => preg_match('/\A[a-f0-9]{16}\z/', $log[2]['reference']),
                    'log.2.reference' => $log[2]['reference'],
                    'message fragment: r.dispatch.rows.0.message' => substr($r['dispatch']['rows'][0]['message'], strpos($r['dispatch']['rows'][0]['message'], 'Reference: ' . $log[2]['reference']) === false ? strlen($r['dispatch']['rows'][0]['message']) : strpos($r['dispatch']['rows'][0]['message'], 'Reference: ' . $log[2]['reference']), strlen('Reference: ' . $log[2]['reference'])),
                    'message fragment: r.dispatch.rows.0.message' => substr($r['dispatch']['rows'][0]['message'], strpos($r['dispatch']['rows'][0]['message'], 'WooCommerce → Status → Logs') === false ? strlen($r['dispatch']['rows'][0]['message']) : strpos($r['dispatch']['rows'][0]['message'], 'WooCommerce → Status → Logs'), strlen('WooCommerce → Status → Logs')),
                    'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                    'rows.0.status' => $r['rows'][0]['status'],
                ];
            $this->assertNotEmpty($r['new_retry_error']);
            $this->assertCount(1, $r['books']);
            foreach (array('Secret upstream error', 'Secret issue write error', '123456', 'KA-1', 'Complete recipient street', 'Long enough original warehouse address', 'PAY-1', '000201-QR', '0812345678') as $secret) {
                $this->assertStringNotContainsString($secret, json_encode($r['logs']));
            }
        }
        $r = $this->runFixture(array('count'=>11));
        $references = array_unique(array_column(array_map(static fn($log)=>$log[2], $r['logs']), 'reference'));
        $this->assertCount(2, $references);
        $r = $this->runFixture(array('logger_throw'=>true, 'timeout'=>true));
        $case = (__FUNCTION__) . ' #' . count($expectedContracts);
        $expectedContracts[$case] = [
                'error' => '',
                'dispatch.rows.0.status' => 'unknown',
                'rows.0.status' => 'pending',
            ];
        $actualContracts[$case] = [
                'error' => $r['error'],
                'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                'rows.0.status' => $r['rows'][0]['status'],
            ];
        $r = $this->runFixture(array('logger_throw'=>true));
        $case = (__FUNCTION__) . ' #' . count($expectedContracts);
        $expectedContracts[$case] = 'booked';
        $actualContracts[$case] = $r['dispatch']['rows'][0]['status'];
    
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
}

    #[Test]
    public function definite_rejection_restores_all_unsubmitted_rows_and_public_prices(): void {
        $r = $this->runFixture(['count'=>11, 'definite_rejection'=>true]);
        $this->assertSame(
            [
                'error' => '',
                'array_column(r.dispatch.rows, \'status\')' => array_merge(array_fill(0, 10, 'failed'), array('skipped')),
                'array_column(r.dispatch.rows, \'retryable\')' => array_fill(0, 11, true),
                'rows' => $r['initial_rows'],
                'count(r.books)' => 1,
            ],
            [
                'error' => $r['error'],
                'array_column(r.dispatch.rows, \'status\')' => array_column($r['dispatch']['rows'], 'status'),
                'array_column(r.dispatch.rows, \'retryable\')' => array_column($r['dispatch']['rows'], 'retryable'),
                'rows' => $r['rows'],
                'count(r.books)' => count($r['books']),
            ],
            __FUNCTION__
        );
        foreach ($r['rows'] as $row) {
            $this->assertSame(
                [
                    'status' => 'new',
                    'shipping_cost' => 15000,
                    'shipping_info' => '{"_shipping_city":"Saved City","custom":"keep"}',
                ],
                [
                    'status' => $row['status'],
                    'shipping_cost' => $row['shipping_cost'],
                    'shipping_info' => $row['shipping_info'],
                ],
                __FUNCTION__
            );
            $this->assertEmpty($row['instant_payment_id'] ?? null);
            $this->assertEmpty($row['rejected_reason'] ?? null);
            $this->assertEmpty($row['instant_payment_method'] ?? null);
        }
        $this->assertSame(
            [
                'options' => [],
                'redaction: json_encode(r), \'Secret upstream error\'' => 0,
            ],
            [
                'options' => $r['options'],
                'redaction: json_encode(r), \'Secret upstream error\'' => substr_count(json_encode($r), 'Secret upstream error'),
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function safe_failures_restore_exact_data_and_permit_a_fresh_user_initiated_attempt(): void {
        foreach (array('definite_rejection', 'not_submitted', 'validation_rejection') as $failure) {
            $r = $this->runFixture(array($failure=>true, 'count'=>2, 'can_select'=>true));
            $this->assertSame(
                [
                    'error' => '',
                    'rows' => $r['initial_rows'],
                    'can_select' => array(true, true),
                    'array_column(r.dispatch.rows, \'retryable\')' => array(true, true),
                    'dispatch.payments' => array(),
                ],
                [
                    'error' => $r['error'],
                    'rows' => $r['rows'],
                    'can_select' => $r['can_select'],
                    'array_column(r.dispatch.rows, \'retryable\')' => array_column($r['dispatch']['rows'], 'retryable'),
                    'dispatch.payments' => $r['dispatch']['payments'],
                ],
                __FUNCTION__
            );
            foreach ($r['woo'] as $order) {
                $this->assertSame(
                    [
                        'status' => 'processing',
                        'completions' => 0,
                        'meta' => array(),
                    ],
                    [
                        'status' => $order['status'],
                        'completions' => $order['completions'],
                        'meta' => $order['meta'],
                    ],
                    __FUNCTION__
                );
            }
            $this->assertSame(array(), $r['options']);
            $r = $this->runFixture(array($failure=>true, 'retry_restored'=>true));
            $this->assertSame(
                [
                    'absent fields: r, .restored_retry_error\' => true' => [],
                    'restored_retry.rows.0.status' => 'booked',
                    'count(r.books)' => 2,
                ],
                [
                    'absent fields: r, .restored_retry_error\' => true' => array_intersect_key($r, ['restored_retry_error' => true]),
                    'restored_retry.rows.0.status' => $r['restored_retry']['rows'][0]['status'],
                    'count(r.books)' => count($r['books']),
                ],
                __FUNCTION__
            );
        }
        foreach (array(array('quote_only'=>true), array('profile'=>'CREDIT', 'method'=>'credit', 'pin'=>'123456', 'credit_fail'=>true), array('claim_fail'=>2), array('snapshot_fail_id'=>'KA-2'), array('snapshot_throw_id'=>'KA-2'), array('consume_fail'=>true)) as $failure) {
            $r = $this->runFixture($failure + array('count'=>2));
            $this->assertSame(
                [
                    'rows' => $r['initial_rows'],
                    'books' => array(),
                    'options' => array(),
                ],
                [
                    'rows' => $r['rows'],
                    'books' => $r['books'],
                    'options' => $r['options'],
                ],
                json_encode($failure)
            );
        }
    }

    #[Test]
    public function rollback_failure_or_callback_never_advertises_retry_or_clears_authoritative_state(): void {
        foreach (array('rollback_fail', 'rollback_throw', 'rollback_callback') as $failure) {
            $r = $this->runFixture(array('definite_rejection'=>true, $failure=>true, 'can_select'=>true));
            $this->assertSame(
                [
                    'error' => '',
                    'dispatch.rows.0.status' => 'unknown',
                    'dispatch.rows.0.retryable' => false,
                    'can_select' => array(false),
                    'message fragment: r.dispatch.rows.0.message' => 'Unable to verify restoration',
                    'logs.2.2.code' => 'booking_rollback_failed',
                    'redaction: json_encode(r), \'Secret rollback error\'' => 0,
                    'count(r.books)' => 1,
                ],
                [
                    'error' => $r['error'],
                    'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                    'dispatch.rows.0.retryable' => $r['dispatch']['rows'][0]['retryable'],
                    'can_select' => $r['can_select'],
                    'message fragment: r.dispatch.rows.0.message' => substr($r['dispatch']['rows'][0]['message'], strpos($r['dispatch']['rows'][0]['message'], 'Unable to verify restoration') === false ? strlen($r['dispatch']['rows'][0]['message']) : strpos($r['dispatch']['rows'][0]['message'], 'Unable to verify restoration'), strlen('Unable to verify restoration')),
                    'logs.2.2.code' => $r['logs'][2][2]['code'],
                    'redaction: json_encode(r), \'Secret rollback error\'' => substr_count(json_encode($r), 'Secret rollback error'),
                    'count(r.books)' => count($r['books']),
                ],
                __FUNCTION__
            );
            if ('rollback_callback' === $failure) {
                $this->assertSame(
                    [
                        'rows.0.status' => 'request_pickup',
                        'rows.0.instant_status_code' => 100,
                    ],
                    [
                        'rows.0.status' => $r['rows'][0]['status'],
                        'rows.0.instant_status_code' => $r['rows'][0]['instant_status_code'],
                    ],
                    __FUNCTION__
                );
            }
        }
        $r = $this->runFixture(array('count'=>2, 'claim_fail'=>2, 'release_fail'=>true));
        $this->assertSame(
            [
                'message fragment: r.error' => 'Unable to verify restoration',
                'rows.0.status' => 'pending',
                'rows.1.status' => 'new',
                'books' => array(),
                'options' => array(),
            ],
            [
                'message fragment: r.error' => substr($r['error'], strpos($r['error'], 'Unable to verify restoration') === false ? strlen($r['error']) : strpos($r['error'], 'Unable to verify restoration'), strlen('Unable to verify restoration')),
                'rows.0.status' => $r['rows'][0]['status'],
                'rows.1.status' => $r['rows'][1]['status'],
                'books' => $r['books'],
                'options' => $r['options'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function later_rejected_group_preserves_prior_successes_payments_and_unsubmitted_rows(): void {
        $r = $this->runFixture(array('count'=>21, 'reject_group'=>2, 'can_select'=>true));
        $this->assertSame(
            [
                'error' => '',
                'count(r.books)' => 2,
                'array_column(r.dispatch.rows, \'status\')' => array_merge(array_fill(0, 10, 'booked'), array_fill(0, 10, 'failed'), array('skipped')),
                'array_column(r.dispatch.rows, \'retryable\')' => array_merge(array_fill(0, 10, false), array_fill(0, 11, true)),
                'count(r.dispatch.payments)' => 1,
                'count(r.dispatch.payments.0.order_ids)' => 10,
                'dispatch.payments.0.id' => 'PAY-1',
                'array_slice(r.rows, 10)' => array_slice($r['initial_rows'], 10),
                'can_select' => array_merge(array_fill(0, 10, false), array_fill(0, 11, true)),
            ],
            [
                'error' => $r['error'],
                'count(r.books)' => count($r['books']),
                'array_column(r.dispatch.rows, \'status\')' => array_column($r['dispatch']['rows'], 'status'),
                'array_column(r.dispatch.rows, \'retryable\')' => array_column($r['dispatch']['rows'], 'retryable'),
                'count(r.dispatch.payments)' => count($r['dispatch']['payments']),
                'count(r.dispatch.payments.0.order_ids)' => count($r['dispatch']['payments'][0]['order_ids']),
                'dispatch.payments.0.id' => $r['dispatch']['payments'][0]['id'],
                'array_slice(r.rows, 10)' => array_slice($r['rows'], 10),
                'can_select' => $r['can_select'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function unexpected_exception_cleans_only_unsubmitted_groups_and_diagnostics_cannot_strand_rows(): void {
        $r = $this->runFixture(array('count'=>11, 'fail_later_reference'=>true));
        $this->assertNotEmpty($r['error']);
        $this->assertSame(
            [
                'count(r.books)' => 1,
                'rows.10' => $r['initial_rows'][10],
                'array_column(array_slice(r.rows, 0, 10), \'status\')' => array_fill(0, 10, 'request_pickup'),
                'options' => array(),
            ],
            [
                'count(r.books)' => count($r['books']),
                'rows.10' => $r['rows'][10],
                'array_column(array_slice(r.rows, 0, 10), \'status\')' => array_column(array_slice($r['rows'], 0, 10), 'status'),
                'options' => $r['options'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(array('count'=>11, 'timeout'=>true, 'diagnostics_throw'=>true));
        $this->assertSame(
            [
                'error' => '',
                'count(r.books)' => 2,
                'array_column(r.dispatch.rows, \'status\')' => array_fill(0, 11, 'unknown'),
                'redaction: json_encode(r), \'Secret diagnostics failure\'' => 0,
            ],
            [
                'error' => $r['error'],
                'count(r.books)' => count($r['books']),
                'array_column(r.dispatch.rows, \'status\')' => array_column($r['dispatch']['rows'], 'status'),
                'redaction: json_encode(r), \'Secret diagnostics failure\'' => substr_count(json_encode($r), 'Secret diagnostics failure'),
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function ambiguous_results_keep_only_private_recovery_evidence_not_visible_prepared_fields(): void {
        $r = $this->runFixture(array('timeout'=>true, 'row'=>array('vehicle'=>'mobil', 'instant_payment_method'=>'top', 'shipment_location_snapshot'=>'{"name":"Original Origin"}')));
        $row = $r['rows'][0];
        $this->assertSame(
            [
                'vehicle' => 'mobil',
                'instant_payment_method' => 'top',
                'shipment_location_snapshot' => '{"name":"Original Origin"}',
            ],
            [
                'vehicle' => $row['vehicle'],
                'instant_payment_method' => $row['instant_payment_method'],
                'shipment_location_snapshot' => $row['shipment_location_snapshot'],
            ],
            __FUNCTION__
        );
        $snapshot = json_decode($row['shipping_info'], true);
        $metadata = $snapshot['_kiriof_instant_prepared']['metadata'];
        unset($snapshot['_kiriof_instant_prepared']);
        $this->assertSame(
            [
                'value' => json_decode($r['initial_rows'][0]['shipping_info'], true),
                'metadata.instant_payment_method' => 'qris',
                'shipping_cost' => 15000,
                'dispatch.rows.0.retryable' => false,
            ],
            [
                'value' => $snapshot,
                'metadata.instant_payment_method' => $metadata['instant_payment_method'],
                'shipping_cost' => $row['shipping_cost'],
                'dispatch.rows.0.retryable' => $r['dispatch']['rows'][0]['retryable'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function saved_database_decimal_prices_are_returned_as_numeric_json(): void {
        $r = $this->runFixture( array( 'quote_only' => true, 'row' => array( 'shipping_cost' => '15000.00' ) ) );
        $this->assertSame(
            [
                'quote.rows.0.before' => 15000,
                'quote.rows.0.eligible' => true,
            ],
            [
                'quote.rows.0.before' => $r['quote']['rows'][0]['before'],
                'quote.rows.0.eligible' => $r['quote']['rows'][0]['eligible'],
            ],
            __FUNCTION__
        );
        foreach ( array( 'NaN', -1, true, null ) as $invalid ) {
            $r = $this->runFixture( array( 'quote_only' => true, 'row' => array( 'shipping_cost' => $invalid ) ) );
            $this->assertSame(
                [
                    'quote.rows.0.before' => null,
                    'quote.rows.0.eligible' => false,
                    'books' => array(),
                ],
                [
                    'quote.rows.0.before' => $r['quote']['rows'][0]['before'],
                    'quote.rows.0.eligible' => $r['quote']['rows'][0]['eligible'],
                    'books' => $r['books'],
                ],
                __FUNCTION__
            );
        }
    }

    private function preparedMetadata(array $row): array {
        return json_decode($row['shipping_info'], true)['_kiriof_instant_prepared']['metadata'];
    }

    private function runFixture(array $input = []): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/instant-dispatch-runtime.php') . ' ' . escapeshellarg(json_encode($input, JSON_THROW_ON_ERROR)));
        return json_decode((string)$output, true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function review_is_an_allowlisted_local_display_with_fresh_two_minute_quotes(): void {
        $r = $this->runFixture(['quote_only'=>true, 'repeat_quote'=>true, 'count'=>2, 'distinct_last'=>true]);
        $this->assertSame('', $r['error']);
        $this->assertSame([
            'id'=>'KA-1', 'before'=>15000, 'after'=>18000, 'changed'=>true, 'eligible'=>true, 'error'=>'',
            'wc_order_number'=>'SHOP-1001', 'courier'=>'gosend', 'service'=>'sameday',
            'origin_label'=>'Test Sender', 'destination_label'=>'Booked Full Name',
        ], $r['quote']['rows'][0]);
        $this->assertSame(
            [
                'quote.rows.1.id' => 'KA-2',
                'quote.rows.1.origin_label' => 'Second Sender',
                'quote.rows.1.destination_label' => 'Second Recipient',
                'repeat_quote.rows' => $r['quote']['rows'],
                '(r.quote.token === r.repeat_quote.token)' => false,
                'format: \'/\\\\\\\\A.a-f0-9{32}\\\\\\\\z/\', r.quote.token' => 1,
            ],
            [
                'quote.rows.1.id' => $r['quote']['rows'][1]['id'],
                'quote.rows.1.origin_label' => $r['quote']['rows'][1]['origin_label'],
                'quote.rows.1.destination_label' => $r['quote']['rows'][1]['destination_label'],
                'repeat_quote.rows' => $r['repeat_quote']['rows'],
                '(r.quote.token === r.repeat_quote.token)' => ($r['quote']['token'] === $r['repeat_quote']['token']),
                'format: \'/\\\\\\\\A.a-f0-9{32}\\\\\\\\z/\', r.quote.token' => preg_match('/\A[a-f0-9]{32}\z/', $r['quote']['token']),
            ],
            __FUNCTION__
        );
        $this->assertEqualsWithDelta(120, $r['quote']['expires_at'] - $r['quoted_at'], 1);
        $this->assertSame(
            [
                'array_values(r.transient_ttls)' => [120,120],
                'prices' => 4,
                'profiles' => 2,
                'books' => [],
                'claims' => [],
                'writes' => [],
            ],
            [
                'array_values(r.transient_ttls)' => array_values($r['transient_ttls']),
                'prices' => $r['prices'],
                'profiles' => $r['profiles'],
                'books' => $r['books'],
                'claims' => $r['claims'],
                'writes' => $r['writes'],
            ],
            __FUNCTION__
        );
        $display = json_encode($r['quote']);
        foreach (['fingerprint','contexts','pricing','phone','latitude','longitude','0812345678','Complete recipient street','Long enough original warehouse address','items','timezone','metadata','pin'] as $private) {
            $this->assertStringNotContainsString($private, $display);
        }
        $r = $this->runFixture(['quote_only'=>true, 'repeat_quote'=>true, 'profile'=>'TOP']);
        $this->assertSame(
            [
                'quote.payment_methods' => ['top'],
                'repeat_quote.payment_methods' => ['top'],
                'books' => [],
            ],
            [
                'quote.payment_methods' => $r['quote']['payment_methods'],
                'repeat_quote.payment_methods' => $r['repeat_quote']['payment_methods'],
                'books' => $r['books'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function review_labels_are_plain_text_optional_and_only_follow_valid_contexts(): void {
        $r = $this->runFixture(['quote_only'=>true, 'order_number'=>"<b>SHOP-42</b>\n", 'origin_name'=>'<b>Warehouse</b>', 'destination_name'=>'<i>Recipient</i>']);
        $row = $r['quote']['rows'][0];
        $this->assertSame(
            [
                'wc_order_number' => 'SHOP-42',
                'origin_label' => 'Warehouse',
                'destination_label' => 'Recipient',
                'id' => 'KA-1',
            ],
            [
                'wc_order_number' => $row['wc_order_number'],
                'origin_label' => $row['origin_label'],
                'destination_label' => $row['destination_label'],
                'id' => $row['id'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['quote_only'=>true, 'origin_name'=>'', 'destination_name'=>'']);
        $this->assertSame(
            [
                'quote.rows.0.origin_label' => 'Long enough original warehouse address',
                'absent fields: r.quote.rows.0, .destination_label\' => true' => [],
            ],
            [
                'quote.rows.0.origin_label' => $r['quote']['rows'][0]['origin_label'],
                'absent fields: r.quote.rows.0, .destination_label\' => true' => array_intersect_key($r['quote']['rows'][0], ['destination_label' => true]),
            ],
            __FUNCTION__
        );
        foreach (['order_missing','order_number_missing_method','order_number_throw'] as $case) {
            $r = $this->runFixture(['quote_only'=>true, $case=>true]);
            $this->assertSame(
                [
                    'quote.rows.0.wc_order_number' => '1',
                    'quote.rows.0.eligible' => true,
                    'redaction: json_encode(r.quote), \'Secret order lookup error\'' => 0,
                ],
                [
                    'quote.rows.0.wc_order_number' => $r['quote']['rows'][0]['wc_order_number'],
                    'quote.rows.0.eligible' => $r['quote']['rows'][0]['eligible'],
                    'redaction: json_encode(r.quote), \'Secret order lookup error\'' => substr_count(json_encode($r['quote']), 'Secret order lookup error'),
                ],
                __FUNCTION__
            );
        }
        foreach ([null, 0, -1, true, 'invalid', 1.5] as $id) {
            $r = $this->runFixture(['quote_only'=>true, 'row'=>['wp_wc_order_stat_order_id'=>$id]]);
            $this->assertArrayNotHasKey('wc_order_number', $r['quote']['rows'][0]);
        }
        foreach (['context_validation','context_runtime','ineligible_last'] as $case) {
            $r = $this->runFixture(['quote_only'=>true, $case=>true]);
            $row = $r['quote']['rows'][0];
            $this->assertSame(
                [
                    'eligible' => false,
                    'id' => 'KA-1',
                    'wc_order_number' => 'SHOP-1001',
                ],
                [
                    'eligible' => $row['eligible'],
                    'id' => $row['id'],
                    'wc_order_number' => $row['wc_order_number'],
                ],
                __FUNCTION__
            );
            foreach (['courier','service','origin_label','destination_label'] as $field) {
                $this->assertArrayNotHasKey($field, $row);
            }
            $this->assertSame(0, $r['prices']);
        }
    }

    #[Test]
    public function merchant_funds_provider_quote_without_subtracting_buyer_shipping_discount(): void {
        $receipt = array(
            '_shipping_city'=>'Saved City',
            '_kiriof_instant_shipping_cost'=>18000,
            '_kiriof_instant_shipping_total'=>19000,
            '_kiriof_instant_admin_fee'=>1000,
            '_kiriof_instant_customer_shipping_cost'=>16200,
            '_kiriof_instant_customer_shipping_discount'=>1800,
            '_kiriof_instant_customer_shipping_total'=>17200,
        );
        $row = array('shipping_cost'=>18000, 'discount_amount'=>1800, 'discount_percentage'=>0, 'shipping_info'=>json_encode($receipt, JSON_THROW_ON_ERROR));
        // Both runs are isolated fixture bookings, not paid provider requests. The
        // second quote represents provider repricing, not another buyer discount.
        foreach (array(18000, 16000) as $provider_price) {
            $r = $this->runFixture(array('row'=>$row, 'price'=>$provider_price, 'validate_credit'=>true, 'profile'=>'CREDIT', 'method'=>'credit', 'pin'=>'123456'));
            $this->assertSame(
                [
                    'error' => '',
                    'quote.rows.0.eligible' => true,
                    'quote.rows.0.before' => 18000,
                    'quote.rows.0.after' => $provider_price,
                    'quote.rows.0.changed' => $provider_price !== 18000,
                ],
                [
                    'error' => $r['error'],
                    'quote.rows.0.eligible' => $r['quote']['rows'][0]['eligible'],
                    'quote.rows.0.before' => $r['quote']['rows'][0]['before'],
                    'quote.rows.0.after' => $r['quote']['rows'][0]['after'],
                    'quote.rows.0.changed' => $r['quote']['rows'][0]['changed'],
                ],
                __FUNCTION__
            );
            $quoted = $r['after_validation']['transients']['kiriof_instant_quote_' . $r['quote']['token']]['contexts']['KA-1'];
            $this->assertSame(
                [
                    'quoted.price' => $provider_price,
                    'validation' => array('valid'=>true),
                ],
                [
                    'quoted.price' => $quoted['price'],
                    'validation' => $r['validation'],
                ],
                __FUNCTION__
            );
            $this->assertReadOnlyValidation($r);
            $this->assertSame(
                [
                    'credits' => array_fill(0, 2, array('amount'=>$provider_price, 'valid_pin'=>true)),
                    'dispatch.rows.0.status' => 'booked',
                    'count(r.books)' => 1,
                    'books.0.payment_method' => 'credit',
                    'books.0.valid_credit_pin' => true,
                    'count(r.books.0.packages)' => 1,
                ],
                [
                    'credits' => $r['credits'],
                    'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                    'count(r.books)' => count($r['books']),
                    'books.0.payment_method' => $r['books'][0]['payment_method'],
                    'books.0.valid_credit_pin' => $r['books'][0]['valid_credit_pin'],
                    'count(r.books.0.packages)' => count($r['books'][0]['packages']),
                ],
                __FUNCTION__
            );
            $this->assertSame(array(
                'order_id'=>'KA-1',
                'destination'=>array('name'=>'Booked Full Name', 'phone'=>'0812345678', 'address'=>'Complete recipient street, City, 12345', 'latitude'=>-6.3, 'longitude'=>106.9),
                'service'=>'gosend', 'service_type'=>'sameday', 'vehicle'=>'motor',
                'shipping_cost'=>$provider_price, 'items'=>array(array('name'=>'Item', 'qty'=>1)), 'package_type_id'=>7,
            ), $r['books'][0]['packages'][0]);
            foreach (array('coupon', 'coupon_code', 'discount_amount', 'discount_percentage', 'cost', 'admin_fee', 'metadata', 'shipping_info') as $field) {
                $this->assertSame(
                    [
                        'absent fields: r.books.0, .field => true' => [],
                        'absent fields: r.books.0.packages.0, .field => true' => [],
                    ],
                    [
                        'absent fields: r.books.0, .field => true' => array_intersect_key($r['books'][0], [$field => true]),
                        'absent fields: r.books.0.packages.0, .field => true' => array_intersect_key($r['books'][0]['packages'][0], [$field => true]),
                    ],
                    __FUNCTION__
                );
            }
            $this->assertSame(
                [
                    'rows.0.shipping_cost' => $provider_price,
                    'rows.0.discount_amount' => 1800,
                    'rows.0.discount_percentage' => 0,
                ],
                [
                    'rows.0.shipping_cost' => $r['rows'][0]['shipping_cost'],
                    'rows.0.discount_amount' => $r['rows'][0]['discount_amount'],
                    'rows.0.discount_percentage' => $r['rows'][0]['discount_percentage'],
                ],
                __FUNCTION__
            );
            $snapshot = json_decode($r['rows'][0]['shipping_info'], true, 512, JSON_THROW_ON_ERROR);
            foreach ($receipt as $field=>$value) {
                $this->assertSame($value, $snapshot[$field], $field);
            }
            $this->assertSame(
                [
                    'instant_shipping_cost' => $provider_price,
                    'dispatch.payments.0.amount' => 18000,
                    'prices' => 1,
                ],
                [
                    'instant_shipping_cost' => $snapshot['instant_shipping_cost'],
                    'dispatch.payments.0.amount' => $r['dispatch']['payments'][0]['amount'],
                    'prices' => $r['prices'],
                ],
                __FUNCTION__
            );
            // The mocked QRIS response deliberately returns 18000 even when
            // the quote is 16000: remote QR payment amounts remain authoritative.
            $qris = $this->runFixture(array('row'=>$row, 'price'=>$provider_price));
            $this->assertSame(
                [
                    'qris.error' => '',
                    'qris.dispatch.rows.0.status' => 'booked',
                    'qris.books.0.payment_method' => 'qris',
                    'qris.books.0.packages.0.shipping_cost' => $provider_price,
                    'qris.dispatch.payments.0.amount' => 18000,
                    'qris.dispatch.payments.0.qr_content' => '000201-QR',
                ],
                [
                    'qris.error' => $qris['error'],
                    'qris.dispatch.rows.0.status' => $qris['dispatch']['rows'][0]['status'],
                    'qris.books.0.payment_method' => $qris['books'][0]['payment_method'],
                    'qris.books.0.packages.0.shipping_cost' => $qris['books'][0]['packages'][0]['shipping_cost'],
                    'qris.dispatch.payments.0.amount' => $qris['dispatch']['payments'][0]['amount'],
                    'qris.dispatch.payments.0.qr_content' => $qris['dispatch']['payments'][0]['qr_content'],
                ],
                __FUNCTION__
            );
        }
    }

    #[Test]
    public function fresh_exact_price_and_snapshots_are_persisted_once(): void {
        $r = $this->runFixture(['retry'=>true,'row'=>['rejected_reason'=>'Check remote state before retrying']]);
        $this->assertSame(
            [
                'error' => '',
                'quote.rows.0.after' => 18000,
                'quote.rows.0.changed' => true,
                'dispatch.rows.0.status' => 'booked',
                'rows.0.rejected_reason' => null,
                'present fields: r.writes.1.changes, .rejected_reason\' => true' => ['rejected_reason'],
                'writes.1.changes.rejected_reason' => null,
                'dispatch.rows.0.awb' => 'AWB-KA-1',
                'rows.0.instant_payment_status' => 'unpaid',
                'rows.0.shipping_cost' => 18000,
                'prices' => 1,
                'count(r.books)' => 1,
            ],
            [
                'error' => $r['error'],
                'quote.rows.0.after' => $r['quote']['rows'][0]['after'],
                'quote.rows.0.changed' => $r['quote']['rows'][0]['changed'],
                'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                'rows.0.rejected_reason' => $r['rows'][0]['rejected_reason'],
                'present fields: r.writes.1.changes, .rejected_reason\' => true' => array_keys(array_intersect_key($r['writes'][1]['changes'], ['rejected_reason' => true])),
                'writes.1.changes.rejected_reason' => $r['writes'][1]['changes']['rejected_reason'],
                'dispatch.rows.0.awb' => $r['dispatch']['rows'][0]['awb'],
                'rows.0.instant_payment_status' => $r['rows'][0]['instant_payment_status'],
                'rows.0.shipping_cost' => $r['rows'][0]['shipping_cost'],
                'prices' => $r['prices'],
                'count(r.books)' => count($r['books']),
            ],
            __FUNCTION__
        );
        $this->assertNotEmpty($r['retry_error']);
        $this->assertSame(
            [
                'array_keys(r.transients)' => ['kiriof_instant_payment_qr_' . hash('sha256', 'PAY-1')],
                'reset(r.transients).qr_content' => '000201-QR',
                'options' => [],
            ],
            [
                'array_keys(r.transients)' => array_keys($r['transients']),
                'reset(r.transients).qr_content' => reset($r['transients'])['qr_content'],
                'options' => $r['options'],
            ],
            __FUNCTION__
        );
        $snapshot = json_decode($r['rows'][0]['shipping_info'], true);
        $this->assertSame(
            [
                'custom' => 'keep',
                '_shipping_first_name' => 'Booked Full Name',
                '_shipping_country' => 'ID',
                'json_decode(r.rows.0.shipment_location_snapshot, true).timezone' => 'WIB',
                'absent fields: r.books.0, .schedule\' => true' => [],
                'absent fields: r.books.0, .origin\' => true' => [],
            ],
            [
                'custom' => $snapshot['custom'],
                '_shipping_first_name' => $snapshot['_shipping_first_name'],
                '_shipping_country' => $snapshot['_shipping_country'],
                'json_decode(r.rows.0.shipment_location_snapshot, true).timezone' => json_decode($r['rows'][0]['shipment_location_snapshot'], true)['timezone'],
                'absent fields: r.books.0, .schedule\' => true' => array_intersect_key($r['books'][0], ['schedule' => true]),
                'absent fields: r.books.0, .origin\' => true' => array_intersect_key($r['books'][0], ['origin' => true]),
            ],
            __FUNCTION__
        );
        foreach (['address','phone','latitude','longitude','name','packages'] as $field) {
            $this->assertArrayHasKey($field, $r['books'][0]);
        }
        $this->assertSame(
            [
                'books.0.payment_method' => 'qris',
                'rows.0.instant_payment_method' => 'qris',
                'rows.0.live_tracking_url' => 'https://example.com/tracking',
                'absent fields: r.books.0, .pin\' => true' => [],
            ],
            [
                'books.0.payment_method' => $r['books'][0]['payment_method'],
                'rows.0.instant_payment_method' => $r['rows'][0]['instant_payment_method'],
                'rows.0.live_tracking_url' => $r['rows'][0]['live_tracking_url'],
                'absent fields: r.books.0, .pin\' => true' => array_intersect_key($r['books'][0], ['pin' => true]),
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function token_user_expiry_selection_and_context_fail_closed_before_booking(): void {
        foreach ([['user_change'=>true], ['expire'=>true], ['stale'=>true], ['dispatch_ids'=>['KA-2']], ['ids'=>['KA-1','KA-1']], ['ids'=>[]], ['row'=>['service'=>'borzo']], ['lock'=>true], ['profile_fail'=>true], ['profile_after'=>'TOP'], ['method'=>'top']] as $input) {
            $r = $this->runFixture($input);
            $this->assertNotEmpty($r['error'], json_encode($input));
            $this->assertSame(
                [
                    'books' => [],
                    'claims' => [],
                ],
                [
                    'books' => $r['books'],
                    'claims' => $r['claims'],
                ],
                __FUNCTION__
            );
        }
        $r = $this->runFixture(['count'=>2,'dispatch_ids'=>['KA-2'],'quote_only'=>false]);
        $this->assertSame('KA-2', $r['dispatch']['rows'][0]['id']);
    }

    #[Test]
    public function unsupported_exact_costs_and_bad_numeric_prices_are_ineligible_but_can_be_skipped(): void {
        foreach ([['price_service'=>'instant'], ['price'=>-1], ['price'=>1.2], ['price'=>'NaN'], ['price'=>true]] as $input) {
            $r = $this->runFixture($input);
            $this->assertFalse($r['quote']['rows'][0]['eligible']);
            $this->assertNotEmpty($r['error']);
            $this->assertSame([], $r['books']);
            foreach (['courier','service','origin_label','destination_label'] as $field) {
                $this->assertArrayNotHasKey($field, $r['quote']['rows'][0]);
            }
        }
        $r = $this->runFixture(['count'=>2,'ineligible_last'=>true,'dispatch_ids'=>['KA-1']]);
        $this->assertSame(
            [
                'quote.rows.1.eligible' => false,
                'dispatch.rows.0.status' => 'booked',
            ],
            [
                'quote.rows.1.eligible' => $r['quote']['rows'][1]['eligible'],
                'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function compatible_origins_split_at_ten_and_match_remote_ids_not_positions(): void {
        $r = $this->runFixture(['count'=>11]);
        $this->assertSame(
            [
                'quote.batch_count' => 2,
                'count(r.books)' => 2,
                'count(r.books.0.packages)' => 10,
                'count(r.books.1.packages)' => 1,
                'count(r.claims)' => 11,
            ],
            [
                'quote.batch_count' => $r['quote']['batch_count'],
                'count(r.books)' => count($r['books']),
                'count(r.books.0.packages)' => count($r['books'][0]['packages']),
                'count(r.books.1.packages)' => count($r['books'][1]['packages']),
                'count(r.claims)' => count($r['claims']),
            ],
            __FUNCTION__
        );
        foreach ($r['dispatch']['rows'] as $row) { $this->assertSame('AWB-' . $row['id'], $row['awb']); }
        $r = $this->runFixture(['count'=>2,'other_origin'=>true]);
        $this->assertCount(2, $r['books']);
    }

    #[Test]
    public function ambiguous_remote_results_and_write_failures_keep_claims_and_never_retry(): void {
        foreach ([['timeout'=>true], ['false'=>true], ['response_status'=>1], ['response_status'=>'true'], ['missing'=>true], ['duplicate'=>true], ['position_only'=>true], ['no_payment'=>true], ['remote_status'=>'invalid'], ['remote_status'=>0], ['remote_status'=>9], ['remote_service'=>'grab_express'], ['remote_service_type'=>'instant'], ['remote_missing_identity'=>true], ['write_fail'=>true]] as $input) {
            $r = $this->runFixture($input + ['retry'=>true,'can_select'=>true]);
            $this->assertSame(
                [
                    'error' => '',
                    'dispatch.rows.0.status' => 'unknown',
                ],
                [
                    'error' => $r['error'],
                    'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                ],
                json_encode($input)
            );
            $this->assertStringStartsWith('Unable to confirm the Instant booking. Contact support before trying again. Reference: ', $r['dispatch']['rows'][0]['message']);
            $this->assertSame(
                [
                    'rows.0.status' => 'pending',
                    'can_select' => [false],
                    'dispatch.payments' => [],
                    'absent fields: r.rows.0, .instant_payment_id\' => true' => [],
                    'absent fields: r.rows.0, .instant_status_code\' => true' => [],
                    'rows.0.shipping_cost' => 15000,
                    'absent fields: r.rows.0, .rejected_reason\' => true' => [],
                    'redaction: json_encode(r), \'Secret upstream error\'' => 0,
                    'redaction: json_encode(r), \'Secret issue write error\'' => 0,
                    'releases' => [],
                    'count(r.books)' => 1,
                    'claims' => ['KA-1'],
                ],
                [
                    'rows.0.status' => $r['rows'][0]['status'],
                    'can_select' => $r['can_select'],
                    'dispatch.payments' => $r['dispatch']['payments'],
                    'absent fields: r.rows.0, .instant_payment_id\' => true' => array_intersect_key($r['rows'][0], ['instant_payment_id' => true]),
                    'absent fields: r.rows.0, .instant_status_code\' => true' => array_intersect_key($r['rows'][0], ['instant_status_code' => true]),
                    'rows.0.shipping_cost' => $r['rows'][0]['shipping_cost'],
                    'absent fields: r.rows.0, .rejected_reason\' => true' => array_intersect_key($r['rows'][0], ['rejected_reason' => true]),
                    'redaction: json_encode(r), \'Secret upstream error\'' => substr_count(json_encode($r), 'Secret upstream error'),
                    'redaction: json_encode(r), \'Secret issue write error\'' => substr_count(json_encode($r), 'Secret issue write error'),
                    'releases' => $r['releases'],
                    'count(r.books)' => count($r['books']),
                    'claims' => $r['claims'],
                ],
                __FUNCTION__
            );
            $this->assertNotEmpty($r['retry_error']);
        }
        $r = $this->runFixture(['count'=>2,'missing'=>true]);
        $this->assertSame(
            [
                'array_column(r.dispatch.rows,\'status\')' => ['unknown','booked'],
                'dispatch.payments.0.order_ids' => ['KA-2'],
                'absent fields: r.rows.0, .rejected_reason\' => true' => [],
                'absent fields: r.rows.0, .instant_payment_id\' => true' => [],
                'absent fields: r.rows.1, .rejected_reason\' => true' => [],
            ],
            [
                'array_column(r.dispatch.rows,\'status\')' => array_column($r['dispatch']['rows'],'status'),
                'dispatch.payments.0.order_ids' => $r['dispatch']['payments'][0]['order_ids'],
                'absent fields: r.rows.0, .rejected_reason\' => true' => array_intersect_key($r['rows'][0], ['rejected_reason' => true]),
                'absent fields: r.rows.0, .instant_payment_id\' => true' => array_intersect_key($r['rows'][0], ['instant_payment_id' => true]),
                'absent fields: r.rows.1, .rejected_reason\' => true' => array_intersect_key($r['rows'][1], ['rejected_reason' => true]),
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function issue_write_exceptions_are_nonfatal_and_never_release_or_complete_unknown_bookings(): void {
        $r = $this->runFixture(['timeout'=>true,'issue_write_throw'=>true,'retry'=>true,'can_select'=>true]);
        $this->assertSame(
            [
                'error' => '',
                'dispatch.rows.0.status' => 'unknown',
            ],
            [
                'error' => $r['error'],
                'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
            ],
            __FUNCTION__
        );
        $this->assertStringStartsWith('Unable to confirm the Instant booking. Contact support before trying again. Reference: ', $r['dispatch']['rows'][0]['message']);
        $this->assertSame(
            [
                'rows.0.status' => 'pending',
                'can_select' => [false],
                'absent fields: r.rows.0, .rejected_reason\' => true' => [],
                'absent fields: r.rows.0, .instant_payment_id\' => true' => [],
                'count(r.writes)' => 1,
                'count(r.books)' => 1,
                'dispatch.payments' => [],
                'releases' => [],
                'claims' => ['KA-1'],
            ],
            [
                'rows.0.status' => $r['rows'][0]['status'],
                'can_select' => $r['can_select'],
                'absent fields: r.rows.0, .rejected_reason\' => true' => array_intersect_key($r['rows'][0], ['rejected_reason' => true]),
                'absent fields: r.rows.0, .instant_payment_id\' => true' => array_intersect_key($r['rows'][0], ['instant_payment_id' => true]),
                'count(r.writes)' => count($r['writes']),
                'count(r.books)' => count($r['books']),
                'dispatch.payments' => $r['dispatch']['payments'],
                'releases' => $r['releases'],
                'claims' => $r['claims'],
            ],
            __FUNCTION__
        );
        $this->assertNotEmpty($r['retry_error']);
        $this->assertSame(
            [
                'redaction: json_encode(r), \'Secret upstream error\'' => 0,
                'redaction: json_encode(r), \'Secret issue write error\'' => 0,
            ],
            [
                'redaction: json_encode(r), \'Secret upstream error\'' => substr_count(json_encode($r), 'Secret upstream error'),
                'redaction: json_encode(r), \'Secret issue write error\'' => substr_count(json_encode($r), 'Secret issue write error'),
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function payments_include_only_verified_bookings_in_each_group(): void {
        $r = $this->runFixture(['count'=>11,'missing'=>true,'malformed_package'=>true]);
        $this->assertSame(
            [
                'error' => '',
                'count(r.books)' => 2,
                'count(r.dispatch.payments)' => 1,
                'dispatch.payments.0.id' => 'PAY-1',
                'dispatch.payments.0.order_ids' => ['KA-2','KA-3','KA-4','KA-5','KA-6','KA-7','KA-8','KA-9','KA-10'],
                'dispatch.rows.10.status' => 'unknown',
            ],
            [
                'error' => $r['error'],
                'count(r.books)' => count($r['books']),
                'count(r.dispatch.payments)' => count($r['dispatch']['payments']),
                'dispatch.payments.0.id' => $r['dispatch']['payments'][0]['id'],
                'dispatch.payments.0.order_ids' => $r['dispatch']['payments'][0]['order_ids'],
                'dispatch.rows.10.status' => $r['dispatch']['rows'][10]['status'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['count'=>2,'other_origin'=>true,'write_fail_id'=>'KA-1']);
        $this->assertSame(
            [
                'array_column(r.dispatch.rows,\'status\')' => ['unknown','booked'],
                'count(r.dispatch.payments)' => 1,
                'dispatch.payments.0.id' => 'PAY-2',
                'dispatch.payments.0.order_ids' => ['KA-2'],
            ],
            [
                'array_column(r.dispatch.rows,\'status\')' => array_column($r['dispatch']['rows'],'status'),
                'count(r.dispatch.payments)' => count($r['dispatch']['payments']),
                'dispatch.payments.0.id' => $r['dispatch']['payments'][0]['id'],
                'dispatch.payments.0.order_ids' => $r['dispatch']['payments'][0]['order_ids'],
            ],
            __FUNCTION__
        );
        foreach ([['missing'=>true], ['write_fail'=>true]] as $input) {
            $r = $this->runFixture($input + ['refresh'=>true]);
            $this->assertSame([], $r['dispatch']['payments']);
            $this->assertNotEmpty($r['error']);
            $this->assertSame(
                [
                    'payments_called' => 0,
                    'rows.0.status' => 'pending',
                    'releases' => [],
                ],
                [
                    'payments_called' => $r['payments_called'],
                    'rows.0.status' => $r['rows'][0]['status'],
                    'releases' => $r['releases'],
                ],
                __FUNCTION__
            );
        }
    }

    #[Test]
    public function leases_recover_only_expired_owners_and_never_release_crash_claims(): void {
        $r = $this->runFixture(['lease'=>'active']);
        $this->assertNotEmpty($r['error']);
        $this->assertSame(
            [
                'books' => [],
                'claims' => [],
                'array_values(r.options).0.owner' => 'old-owner',
            ],
            [
                'books' => $r['books'],
                'claims' => $r['claims'],
                'array_values(r.options).0.owner' => array_values($r['options'])[0]['owner'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['lease'=>'expired']);
        $this->assertSame(
            [
                'error' => '',
                'dispatch.rows.0.status' => 'booked',
                'options' => [],
            ],
            [
                'error' => $r['error'],
                'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                'options' => $r['options'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['lease'=>'expired','crash_pending'=>true]);
        $this->assertNotEmpty($r['error']);
        $this->assertSame(
            [
                'books' => [],
                'claims' => [],
                'releases' => [],
                'rows.0.status' => 'pending',
                'options' => [],
            ],
            [
                'books' => $r['books'],
                'claims' => $r['claims'],
                'releases' => $r['releases'],
                'rows.0.status' => $r['rows'][0]['status'],
                'options' => $r['options'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['lease'=>'expired','lease_delete_race'=>true]);
        $this->assertNotEmpty($r['error']);
        $this->assertSame(
            [
                'books' => [],
                'array_values(r.options).0.owner' => 'replacement-owner',
            ],
            [
                'books' => $r['books'],
                'array_values(r.options).0.owner' => array_values($r['options'])[0]['owner'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['replace_lease_during_book'=>true]);
        $this->assertSame(
            [
                'error' => '',
                'dispatch.rows.0.status' => 'booked',
                'array_values(r.options).0.owner' => 'replacement-owner',
            ],
            [
                'error' => $r['error'],
                'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                'array_values(r.options).0.owner' => array_values($r['options'])[0]['owner'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function failed_atomic_claim_releases_only_pre_api_successes(): void {
        $r = $this->runFixture(['count'=>2,'claim_fail'=>2]);
        $this->assertNotEmpty($r['error']);
        $this->assertSame(
            [
                'claims' => ['KA-1'],
                'releases' => ['KA-1'],
                'books' => [],
                'options' => [],
            ],
            [
                'claims' => $r['claims'],
                'releases' => $r['releases'],
                'books' => $r['books'],
                'options' => $r['options'],
            ],
            __FUNCTION__
        );
        $this->assertNotEmpty($r['transients']);
        $this->assertSame(['new','new'],array_column($r['rows'],'status'));
    }

    #[Test]
    public function credit_is_validated_once_and_pin_never_persisted_top_is_not_inferred_paid(): void {
        $r = $this->runFixture(['count'=>11,'method'=>'credit','pin'=>'654321','echo_pin'=>true,'echo_pin_qr'=>true]);
        $this->assertSame(
            [
                'credits' => [['amount'=>198000,'valid_pin'=>true]],
                'redaction: json_encode(r), \'654321\'' => 0,
            ],
            [
                'credits' => $r['credits'],
                'redaction: json_encode(r), \'654321\'' => substr_count(json_encode($r), '654321'),
            ],
            __FUNCTION__
        );
        foreach ($r['books'] as $book) {
            $this->assertSame(
                [
                    'book.payment_method' => 'credit',
                    'book.valid_credit_pin' => true,
                    'absent fields: book, .origin\' => true' => [],
                ],
                [
                    'book.payment_method' => $book['payment_method'],
                    'book.valid_credit_pin' => $book['valid_credit_pin'],
                    'absent fields: book, .origin\' => true' => array_intersect_key($book, ['origin' => true]),
                ],
                __FUNCTION__
            );
        }
        $this->assertSame('credit', $r['rows'][0]['instant_payment_method']);
        foreach ([['pin'=>'123'], ['pin'=>'abcdef'], ['pin'=>'654321','credit_fail'=>true]] as $input) {
            $r = $this->runFixture($input + ['method'=>'credit']);
            $this->assertNotEmpty($r['error']);
            $this->assertSame([], $r['books']);
        }
        $r = $this->runFixture(['profile'=>'TOP','method'=>'top','payment_status'=>'weird']);
        $this->assertSame(
            [
                'quote.payment_methods' => ['top'],
                'rows.0.instant_payment_status' => 'pending',
                'rows.0.instant_payment_method' => 'top',
                'absent fields: r.books.0, .payment_method\' => true' => [],
                'absent fields: r.books.0, .pin\' => true' => [],
            ],
            [
                'quote.payment_methods' => $r['quote']['payment_methods'],
                'rows.0.instant_payment_status' => $r['rows'][0]['instant_payment_status'],
                'rows.0.instant_payment_method' => $r['rows'][0]['instant_payment_method'],
                'absent fields: r.books.0, .payment_method\' => true' => array_intersect_key($r['books'][0], ['payment_method' => true]),
                'absent fields: r.books.0, .pin\' => true' => array_intersect_key($r['books'][0], ['pin' => true]),
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['profile'=>'TOP','method'=>'credit','pin'=>'654321']);
        $this->assertSame([], $r['credits']);
    }

    #[Test]
    public function payment_refresh_verifies_all_selected_ids_before_remote_and_only_updates_status(): void {
        $r = $this->runFixture(['refresh'=>true]);
        $this->assertSame(
            [
                'refresh.status' => 'paid',
                'writes.2.changes' => ['instant_payment_status'=>'paid'],
            ],
            [
                'refresh.status' => $r['refresh']['status'],
                'writes.2.changes' => $r['writes'][2]['changes'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['refresh'=>true,'refresh_pid'=>'OTHER']);
        $this->assertNotEmpty($r['error']);
        $this->assertSame(0,$r['payments_called']);
        $r = $this->runFixture(['refresh'=>true,'refresh_status'=>'unknown']);
        $this->assertSame(
            [
                'refresh.status' => 'unpaid',
                'count(r.writes)' => 2,
            ],
            [
                'refresh.status' => $r['refresh']['status'],
                'count(r.writes)' => count($r['writes']),
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function an_actual_awb_is_optional_but_payment_and_remote_status_are_not_guessed(): void {
        $r = $this->runFixture(['no_awb'=>true,'payment_status'=>0]);
        $this->assertSame(
            [
                'dispatch.rows.0.status' => 'booked',
                'absent fields: r.writes.0.changes, .awb\' => true' => [],
                'rows.0.instant_payment_status' => 'paid',
            ],
            [
                'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                'absent fields: r.writes.0.changes, .awb\' => true' => array_intersect_key($r['writes'][0]['changes'], ['awb' => true]),
                'rows.0.instant_payment_status' => $r['rows'][0]['instant_payment_status'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['profile'=>'TOP','method'=>'top','no_payment'=>true]);
        $this->assertSame(
            [
                'dispatch.rows.0.status' => 'unknown',
                'rows.0.status' => 'pending',
            ],
            [
                'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                'rows.0.status' => $r['rows'][0]['status'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function v62_status_codes_take_precedence_and_sdk_nested_results_are_unwrapped(): void {
        foreach ([0=>'paid',9=>'unpaid'] as $code=>$expected) {
            foreach ([$code,(string)$code] as $value) {
                $r = $this->runFixture(['payment_status'=>$value,'payment_legacy_status'=>'refunded','nested_booking'=>true,'refresh'=>true,'nested_payment'=>true,'refresh_status'=>$value,'refresh_legacy_status'=>'refunded']);
                $this->assertSame(
                    [
                        'dispatch.rows.0.status' => 'booked',
                        'dispatch.payments.0.status' => $expected,
                        'refresh.status' => $expected,
                        'rows.0.instant_payment_status' => $expected,
                    ],
                    [
                        'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                        'dispatch.payments.0.status' => $r['dispatch']['payments'][0]['status'],
                        'refresh.status' => $r['refresh']['status'],
                        'rows.0.instant_payment_status' => $r['rows'][0]['instant_payment_status'],
                    ],
                    __FUNCTION__
                );
            }
        }
        $r = $this->runFixture(['refresh'=>true,'refresh_status'=>42,'refresh_legacy_status'=>'paid']);
        $this->assertSame(
            [
                'refresh.status' => 'unpaid',
                'rows.0.instant_payment_status' => 'unpaid',
                'count(r.writes)' => 2,
            ],
            [
                'refresh.status' => $r['refresh']['status'],
                'rows.0.instant_payment_status' => $r['rows'][0]['instant_payment_status'],
                'count(r.writes)' => count($r['writes']),
            ],
            __FUNCTION__
        );
        foreach ([0,9] as $code) {
            $r = $this->runFixture(['profile'=>'TOP','method'=>'top','payment_status'=>$code]);
            $this->assertSame(
                [
                    'rows.0.instant_payment_status' => 0 === $code ? 'paid' : 'unpaid',
                    'absent fields: r.books.0, .payment_method\' => true' => [],
                ],
                [
                    'rows.0.instant_payment_status' => $r['rows'][0]['instant_payment_status'],
                    'absent fields: r.books.0, .payment_method\' => true' => array_intersect_key($r['books'][0], ['payment_method' => true]),
                ],
                __FUNCTION__
            );
        }
        foreach ([false,1,'true'] as $status) {
            $r = $this->runFixture(['refresh'=>true,'refresh_response_status'=>$status]);
            $this->assertSame(
                [
                    'error' => 'Unable to refresh the Instant payment.',
                    'count(r.writes)' => 2,
                    'rows.0.instant_payment_status' => 'unpaid',
                ],
                [
                    'error' => $r['error'],
                    'count(r.writes)' => count($r['writes']),
                    'rows.0.instant_payment_status' => $r['rows'][0]['instant_payment_status'],
                ],
                __FUNCTION__
            );
        }
    }

    #[Test]
    public function only_local_context_validation_messages_are_exposed_in_quote_errors(): void {
        $r = $this->runFixture(['context_validation'=>true,'quote_only'=>true]);
        $this->assertSame(
            [
                'quote.rows.0.eligible' => false,
                'quote.rows.0.error' => 'Valid origin and destination coordinates are required for Instant delivery.',
                'prices' => 0,
            ],
            [
                'quote.rows.0.eligible' => $r['quote']['rows'][0]['eligible'],
                'quote.rows.0.error' => $r['quote']['rows'][0]['error'],
                'prices' => $r['prices'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['context_runtime'=>true,'quote_only'=>true]);
        $this->assertSame(
            [
                'quote.rows.0.error' => 'This Instant shipment could not be quoted. Check its addresses, items and courier service.',
                'redaction: json_encode(r), \'123456\'' => 0,
                'books' => [],
            ],
            [
                'quote.rows.0.error' => $r['quote']['rows'][0]['error'],
                'redaction: json_encode(r), \'123456\'' => substr_count(json_encode($r), '123456'),
                'books' => $r['books'],
            ],
            __FUNCTION__
        );
    }
    #[Test]
    public function callbacks_during_booking_preserve_lifecycle_payment_and_attach_request_snapshots(): void {
        $expectedContracts = [];
        $actualContracts = [];

        foreach ([106=>'shipped', 200=>'finished', 350=>'pending', 300=>'canceled', 401=>'return', 400=>'returned'] as $code=>$expected) {
            foreach (['paid', 'refunded'] as $payment) {
                $r = $this->runFixture(['callback_code'=>$code, 'callback_payment'=>$payment, 'callback_no_pid'=>true, 'retry'=>true]);
                $case = (__FUNCTION__) . ' #' . count($expectedContracts);
                $expectedContracts[$case] = [
                        'error' => '',
                        'dispatch.rows.0.status' => 'booked',
                        'rows.0.status' => $expected,
                        'dispatch.rows.0.shipment_status' => $expected,
                        'rows.0.instant_status_code' => $code,
                        'rows.0.instant_payment_status' => $payment,
                        'dispatch.payments.0.status' => $payment,
                        'rows.0.instant_payment_id' => 'PAY-1',
                        'dispatch.rows.0.awb' => 'AWB-KA-1',
                        'present fields: json_decode(r.rows.0.shipping_info, true), .instant_items\' => true' => ['instant_items'],
                        'json_decode(r.rows.0.shipment_location_snapshot, true).timezone' => 'WIB',
                    ];
                $actualContracts[$case] = [
                        'error' => $r['error'],
                        'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                        'rows.0.status' => $r['rows'][0]['status'],
                        'dispatch.rows.0.shipment_status' => $r['dispatch']['rows'][0]['shipment_status'],
                        'rows.0.instant_status_code' => $r['rows'][0]['instant_status_code'],
                        'rows.0.instant_payment_status' => $r['rows'][0]['instant_payment_status'],
                        'dispatch.payments.0.status' => $r['dispatch']['payments'][0]['status'],
                        'rows.0.instant_payment_id' => $r['rows'][0]['instant_payment_id'],
                        'dispatch.rows.0.awb' => $r['dispatch']['rows'][0]['awb'],
                        'present fields: json_decode(r.rows.0.shipping_info, true), .instant_items\' => true' => array_keys(array_intersect_key(json_decode($r['rows'][0]['shipping_info'], true), ['instant_items' => true])),
                        'json_decode(r.rows.0.shipment_location_snapshot, true).timezone' => json_decode($r['rows'][0]['shipment_location_snapshot'], true)['timezone'],
                    ];
                foreach (['shipping_info','shipment_location_snapshot','shipping_cost','vehicle','instant_payment_method'] as $field) {
                    $case = (__FUNCTION__) . ' #' . count($expectedContracts);
                    $expectedContracts[$case] = 'shipping_cost' === $field ? 18000 : $this->preparedMetadata($r['prepared_at_book'][0]['KA-1'])[$field];
                    $actualContracts[$case] = $r['rows'][0][$field];
                }
                $case = (__FUNCTION__) . ' #' . count($expectedContracts);
                $expectedContracts[$case] = [
                        'count(r.books)' => 1,
                        'releases' => [],
                        'woo.1.status' => 200 === $code ? 'completed' : 'processing',
                        'woo.1.completions' => 200 === $code ? 1 : 0,
                    ];
                $actualContracts[$case] = [
                        'count(r.books)' => count($r['books']),
                        'releases' => $r['releases'],
                        'woo.1.status' => $r['woo'][1]['status'],
                        'woo.1.completions' => $r['woo'][1]['completions'],
                    ];
            }
        }
    
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
}

    #[Test]
    public function refresh_reports_effective_monotonic_payment_status_and_rejects_mismatched_identity(): void {
        foreach (['paid', 'refunded'] as $previous) {
            foreach ([9, 'pending', 'unknown', 0] as $remote) {
                $r = $this->runFixture(['payment_status'=>$previous, 'refresh'=>true, 'refresh_status'=>$remote]);
                $this->assertSame(
                    [
                        'error' => '',
                        'rows.0.instant_payment_status' => $previous,
                        'refresh.status' => $previous,
                        'count(r.writes)' => 2,
                        'payments_called' => 1,
                    ],
                    [
                        'error' => $r['error'],
                        'rows.0.instant_payment_status' => $r['rows'][0]['instant_payment_status'],
                        'refresh.status' => $r['refresh']['status'],
                        'count(r.writes)' => count($r['writes']),
                        'payments_called' => $r['payments_called'],
                    ],
                    __FUNCTION__
                );
            }
        }
        $r = $this->runFixture(['count'=>2, 'refresh'=>true, 'refresh_status'=>'unknown', 'before_refresh_statuses'=>['paid', 'refunded']]);
        $this->assertSame(
            [
                'refresh.status' => 'refunded',
                'array_column(r.rows, \'instant_payment_status\')' => ['paid', 'refunded'],
                'count(r.writes)' => 4,
            ],
            [
                'refresh.status' => $r['refresh']['status'],
                'array_column(r.rows, \'instant_payment_status\')' => array_column($r['rows'], 'instant_payment_status'),
                'count(r.writes)' => count($r['writes']),
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['count'=>11, 'refresh'=>true]);
        $this->assertNotEmpty($r['error']);
        $this->assertSame(0, $r['payments_called']);
        $r = $this->runFixture(['refresh'=>true, 'response_pid'=>'OTHER']);
        $this->assertSame(
            [
                'error' => 'The Instant payment response is invalid.',
                'rows.0.instant_payment_status' => 'unpaid',
                'count(r.writes)' => 2,
            ],
            [
                'error' => $r['error'],
                'rows.0.instant_payment_status' => $r['rows'][0]['instant_payment_status'],
                'count(r.writes)' => count($r['writes']),
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function booking_requires_valid_woo_link_and_known_shipment_code_not_payment_code_zero(): void {
        foreach ([0, '0', 9, '9', true, 105.5, 999] as $code) {
            $r = $this->runFixture(['remote_status'=>$code, 'payment_status'=>0]);
            $this->assertSame(
                [
                    'dispatch.rows.0.status' => 'unknown',
                    'rows.0.status' => 'pending',
                    'absent fields: r.rows.0, .instant_status_code\' => true' => [],
                    'dispatch.payments' => [],
                ],
                [
                    'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                    'rows.0.status' => $r['rows'][0]['status'],
                    'absent fields: r.rows.0, .instant_status_code\' => true' => array_intersect_key($r['rows'][0], ['instant_status_code' => true]),
                    'dispatch.payments' => $r['dispatch']['payments'],
                ],
                __FUNCTION__
            );
        }
        $r = $this->runFixture(['row'=>['wp_wc_order_stat_order_id'=>null]]);
        $this->assertSame(
            [
                'dispatch.rows.0.status' => 'unknown',
                'rows.0.status' => 'pending',
            ],
            [
                'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                'rows.0.status' => $r['rows'][0]['status'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['remote_status'=>200]);
        $this->assertSame(
            [
                'rows.0.status' => 'finished',
                'woo.1.status' => 'completed',
                'woo.1.completions' => 1,
            ],
            [
                'rows.0.status' => $r['rows'][0]['status'],
                'woo.1.status' => $r['woo'][1]['status'],
                'woo.1.completions' => $r['woo'][1]['completions'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function ambiguous_booking_does_not_erase_a_callback_issue_note(): void {
        $r = $this->runFixture(['callback_code'=>500, 'missing'=>true]);
        $this->assertSame(
            [
                'error' => '',
                'dispatch.rows.0.status' => 'unknown',
                'rows.0.status' => 'pending',
                'rows.0.instant_status_code' => 500,
                'rows.0.rejected_reason' => 'There is a problem with this Instant shipment.',
                'count(r.writes)' => 2,
                'count(r.books)' => 1,
                'releases' => [],
            ],
            [
                'error' => $r['error'],
                'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
                'rows.0.status' => $r['rows'][0]['status'],
                'rows.0.instant_status_code' => $r['rows'][0]['instant_status_code'],
                'rows.0.rejected_reason' => $r['rows'][0]['rejected_reason'],
                'count(r.writes)' => count($r['writes']),
                'count(r.books)' => count($r['books']),
                'releases' => $r['releases'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function every_reviewed_snapshot_is_durable_before_the_first_outbound_booking(): void {
        $expectedContracts = [];
        $actualContracts = [];

        $r = $this->runFixture(['count'=>11, 'method'=>'credit', 'pin'=>'654321']);
        $case = (__FUNCTION__) . ' #' . count($expectedContracts);
        $expectedContracts[$case] = [
                'error' => '',
                'count(r.prepared_at_book.0)' => 11,
            ];
        $actualContracts[$case] = [
                'error' => $r['error'],
                'count(r.prepared_at_book.0)' => count($r['prepared_at_book'][0]),
            ];
        foreach ($r['prepared_at_book'][0] as $id=>$row) {
            $case = (__FUNCTION__) . ' #' . count($expectedContracts);
            $expectedContracts[$case] = [
                    'status' => 'pending',
                    'shipping_cost' => 15000,
                ];
            $actualContracts[$case] = [
                    'status' => $row['status'],
                    'shipping_cost' => $row['shipping_cost'],
                ];
            $metadata = $this->preparedMetadata($row);
            $case = (__FUNCTION__) . ' #' . count($expectedContracts);
            $expectedContracts[$case] = [
                    'absent fields: row, .instant_payment_method\' => true' => [],
                    'metadata.instant_payment_method' => 'credit',
                    'vehicle' => 'motor',
                    'json_decode(metadata.shipment_location_snapshot, true).timezone' => 'WIB',
                ];
            $actualContracts[$case] = [
                    'absent fields: row, .instant_payment_method\' => true' => array_intersect_key($row, ['instant_payment_method' => true]),
                    'metadata.instant_payment_method' => $metadata['instant_payment_method'],
                    'vehicle' => $row['vehicle'],
                    'json_decode(metadata.shipment_location_snapshot, true).timezone' => json_decode($metadata['shipment_location_snapshot'], true)['timezone'],
                ];
            $private = json_decode($row['shipping_info'], true);
            unset($private['_kiriof_instant_prepared']);
            $case = (__FUNCTION__) . ' #' . count($expectedContracts);
            $expectedContracts[$case] = [
                    'private' => json_decode($r['initial_rows'][array_search($id, array_keys($r['prepared_at_book'][0]), true)]['shipping_info'], true),
                    'absent fields: row, .shipment_location_snapshot\' => true' => [],
                ];
            $actualContracts[$case] = [
                    'private' => $private,
                    'absent fields: row, .shipment_location_snapshot\' => true' => array_intersect_key($row, ['shipment_location_snapshot' => true]),
                ];
            $snapshot = json_decode($metadata['shipping_info'], true);
            $case = (__FUNCTION__) . ' #' . count($expectedContracts);
            $expectedContracts[$case] = [
                    '_shipping_first_name' => 'Booked Full Name',
                    'instant_items' => [['name'=>'Item', 'qty'=>1]],
                ];
            $actualContracts[$case] = [
                    '_shipping_first_name' => $snapshot['_shipping_first_name'],
                    'instant_items' => $snapshot['instant_items'],
                ];
            foreach (['request_pickup_at','instant_payment_id','instant_status_code','pin'] as $field) {
                $case = (__FUNCTION__) . ' #' . count($expectedContracts);
                $expectedContracts[$case] = [
                        'absent fields: row, .field => true' => [],
                        'absent fields: r.writes.array_search(id, array_keys(r.prepared_at_book.0), true).changes, .field => true' => [],
                    ];
                $actualContracts[$case] = [
                        'absent fields: row, .field => true' => array_intersect_key($row, [$field => true]),
                        'absent fields: r.writes.array_search(id, array_keys(r.prepared_at_book.0), true).changes, .field => true' => array_intersect_key($r['writes'][array_search($id, array_keys($r['prepared_at_book'][0]), true)]['changes'], [$field => true]),
                    ];
            }
        }
        $this->assertStringNotContainsString('654321', json_encode($r));
    
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
}

    #[Test]
    public function prepared_snapshot_failures_release_the_whole_batch_without_consuming_quote_or_booking(): void {
        foreach (['snapshot_fail_id','snapshot_throw_id'] as $failure) {
            $r = $this->runFixture(['count'=>2, $failure=>'KA-2']);
            $this->assertSame(
                [
                    'error' => 'Unable to save the prepared Instant shipment.',
                    'claims' => ['KA-1','KA-2'],
                    'releases' => ['KA-2'],
                    'array_column(r.rows, \'status\')' => ['new','new'],
                    'books' => [],
                    'options' => [],
                ],
                [
                    'error' => $r['error'],
                    'claims' => $r['claims'],
                    'releases' => $r['releases'],
                    'array_column(r.rows, \'status\')' => array_column($r['rows'], 'status'),
                    'books' => $r['books'],
                    'options' => $r['options'],
                ],
                __FUNCTION__
            );
            $this->assertNotEmpty($r['transients']);
            $this->assertSame(
                [
                    'absent fields: json_decode(r.rows.0.shipping_info, true), .instant_items\' => true' => [],
                    'absent fields: json_decode(r.rows.1.shipping_info, true), .instant_items\' => true' => [],
                    'can_print' => [false,false],
                    'redaction: json_encode(r), \'Secret snapshot error\'' => 0,
                ],
                [
                    'absent fields: json_decode(r.rows.0.shipping_info, true), .instant_items\' => true' => array_intersect_key(json_decode($r['rows'][0]['shipping_info'], true), ['instant_items' => true]),
                    'absent fields: json_decode(r.rows.1.shipping_info, true), .instant_items\' => true' => array_intersect_key(json_decode($r['rows'][1]['shipping_info'], true), ['instant_items' => true]),
                    'can_print' => $r['can_print'],
                    'redaction: json_encode(r), \'Secret snapshot error\'' => substr_count(json_encode($r), 'Secret snapshot error'),
                ],
                __FUNCTION__
            );
        }
    }

    #[Test]
    public function ambiguous_booking_recovers_using_prepared_snapshots_only_after_verified_lifecycle(): void {
        $r = $this->runFixture(['timeout'=>true, 'retry'=>true, 'new_retry'=>true, 'recover'=>true]);
        $this->assertSame(
            [
                'error' => '',
                'dispatch.rows.0.status' => 'unknown',
            ],
            [
                'error' => $r['error'],
                'dispatch.rows.0.status' => $r['dispatch']['rows'][0]['status'],
            ],
            __FUNCTION__
        );
        $this->assertNotEmpty($r['retry_error']);
        $this->assertNotEmpty($r['new_retry_error']);
        $this->assertSame(
            [
                'count(r.books)' => 1,
                'metadata_recovery_row.status' => 'pending',
                'metadata_recovery_row.awb' => 'RECOVERED-AWB',
                'metadata_recovery_can_print' => false,
                'absent fields: r.metadata_recovery_row, .instant_status_code\' => true' => [],
                'absent fields: r.metadata_recovery_row, .request_pickup_at\' => true' => [],
                'rows.0.status' => 'request_pickup',
                'can_print' => [true],
            ],
            [
                'count(r.books)' => count($r['books']),
                'metadata_recovery_row.status' => $r['metadata_recovery_row']['status'],
                'metadata_recovery_row.awb' => $r['metadata_recovery_row']['awb'],
                'metadata_recovery_can_print' => $r['metadata_recovery_can_print'],
                'absent fields: r.metadata_recovery_row, .instant_status_code\' => true' => array_intersect_key($r['metadata_recovery_row'], ['instant_status_code' => true]),
                'absent fields: r.metadata_recovery_row, .request_pickup_at\' => true' => array_intersect_key($r['metadata_recovery_row'], ['request_pickup_at' => true]),
                'rows.0.status' => $r['rows'][0]['status'],
                'can_print' => $r['can_print'],
            ],
            __FUNCTION__
        );
        foreach (['shipping_info','shipment_location_snapshot','shipping_cost','vehicle','instant_payment_method'] as $field) {
            $this->assertSame('shipping_cost' === $field ? 18000 : $this->preparedMetadata($r['prepared_at_book'][0]['KA-1'])[$field], $r['rows'][0][$field]);
        }
        $this->assertSame(
            [
                'absent fields: r.rows.0, .instant_payment_id\' => true' => [],
                'releases' => [],
            ],
            [
                'absent fields: r.rows.0, .instant_payment_id\' => true' => array_intersect_key($r['rows'][0], ['instant_payment_id' => true]),
                'releases' => $r['releases'],
            ],
            __FUNCTION__
        );
    }

}
