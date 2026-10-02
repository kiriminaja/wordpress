<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantDispatchRuntimeTest extends TestCase {
    #[Test]
    public function quote_returns_actual_numeric_credit_balance_or_null_without_invalidating_quotes(): void {
        foreach (array(4000724100, '4000724100', '4,000,724,100', -1) as $balance) {
            $r = $this->runFixture(array('quote_only'=>true, 'profile'=>'CREDIT', 'credit_balance'=>$balance));
            $this->assertTrue($r['quote']['rows'][0]['eligible']);
            $this->assertArrayHasKey('credit_balance', $r['quote']);
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
        foreach (array('12345', '1234567', 'abcdef', '１２３４５６', "123456\n", '') as $pin) {
            $r = $this->runFixture(array('validate_credit'=>true, 'quote_only'=>true, 'profile'=>'CREDIT', 'pin'=>$pin, 'retry_validation'=>true));
            $this->assertSame('Unable to validate KA Credit payment.', $r['validation_error']);
            $this->assertSame('InvalidArgumentException', $r['validation_exception']);
            $this->assertSame(array('valid'=>true), $r['validation_retry']);
            $this->assertCount(1, $r['credits']);
            $this->assertReadOnlyValidation($r);
        }
        foreach (array('credit_fail', 'credit_throw') as $failure) {
            $r = $this->runFixture(array('validate_credit'=>true, 'quote_only'=>true, 'profile'=>'CREDIT', 'pin'=>'123456', $failure=>true, 'retry_validation'=>true));
            $this->assertSame('Unable to validate KA Credit payment.', $r['validation_error']);
            $this->assertSame('InvalidArgumentException', $r['validation_exception']);
            $this->assertStringNotContainsString('123456', $r['validation_error']);
            $this->assertSame(array('valid'=>true), $r['validation_retry']);
            $this->assertReadOnlyValidation($r);
        }
        $r = $this->runFixture(array('validate_credit'=>true, 'quote_only'=>true, 'profile'=>'CREDIT', 'pin'=>'123456', 'count'=>2, 'price'=>2000362050, 'credit_balance'=>4000724100));
        $this->assertSame(array('valid'=>true), $r['validation']);
        $this->assertSame(4000724100, $r['quote']['credit_balance']);
        $this->assertSame(array(array('amount'=>4000724100, 'valid_pin'=>true)), $r['credits']);
        $this->assertReadOnlyValidation($r);
        $r = $this->runFixture(array('validate_credit'=>true, 'quote_only'=>true, 'profile'=>'CREDIT', 'pin'=>'123456', 'count'=>2, 'dispatch_ids'=>array('KA-2')));
        $this->assertSame(18000, $r['credits'][0]['amount']);
        $this->assertReadOnlyValidation($r);
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
        foreach (array(array('user_change'=>true), array('expire'=>true), array('stale'=>true), array('dispatch_ids'=>array('KA-2')), array('validate_token'=>'invalid'), array('profile_after'=>'TOP'), array('profile'=>'TOP'), array('quote_price'=>1.5), array('quote_price'=>'18000'), array('quote_price'=>-1), array('count'=>2, 'quote_price'=>PHP_INT_MAX)) as $input) {
            $r = $this->runFixture($input + array('validate_credit'=>true, 'quote_only'=>true, 'profile'=>'CREDIT', 'pin'=>'123456'));
            $this->assertNotEmpty($r['validation_error'], json_encode($input));
            $this->assertSame(array(), $r['credits']);
            $this->assertReadOnlyValidation($r);
        }
        $r = $this->runFixture(array('validate_credit'=>true, 'profile'=>'CREDIT', 'method'=>'credit', 'pin'=>'123456'));
        $this->assertSame(array('valid'=>true), $r['validation']);
        $this->assertReadOnlyValidation($r);
        $this->assertCount(2, $r['credits']);
        $this->assertSame(18000, $r['credits'][1]['amount']);
        $this->assertSame('booked', $r['dispatch']['rows'][0]['status']);
        $r = $this->runFixture(array('validate_credit'=>true, 'profile'=>'CREDIT', 'method'=>'credit', 'pin'=>'123456', 'credit_fail_after_validation'=>true));
        $this->assertSame(array('valid'=>true), $r['validation']);
        $this->assertSame('Unable to validate KA Credit payment.', $r['error']);
        $this->assertSame(array(), $r['claims']);
        $this->assertSame(array(), $r['books']);
        $this->assertCount(1, $r['transients']);
    }

    #[Test]
    public function working_qris_response_confirms_without_awb_handles_service_case_and_persists_valid_route(): void {
        foreach (array('', 'https://tracking.example.test/booking') as $url) {
            $r = $this->runFixture(array('working_sample'=>true,'row'=>array('service_name'=>'instant'),'price_service'=>'instant','sample_url'=>$url,'retry'=>true));
            $this->assertSame('', $r['error']);
            $this->assertSame('qris', $r['books'][0]['payment_method']);
            $this->assertSame('Pickup note', $r['books'][0]['address_note']);
            $this->assertSame('Gedung A Lantai 5', $r['books'][0]['packages'][0]['destination']['address_note']);
            $this->assertSame('booked', $r['dispatch']['rows'][0]['status']);
            $this->assertSame('', $r['dispatch']['rows'][0]['awb']);
            $this->assertSame('request_pickup', $r['rows'][0]['status']);
            $this->assertSame(110, $r['rows'][0]['instant_status_code']);
            $this->assertSame('EPR-5487434915', $r['rows'][0]['instant_payment_id']);
            $this->assertSame('unpaid', $r['rows'][0]['instant_payment_status']);
            $this->assertSame('qris', $r['rows'][0]['instant_payment_method']);
            $this->assertSame($url, $r['rows'][0]['live_tracking_url'] ?? '');
            $this->assertSame(31500, $r['dispatch']['payments'][0]['amount']);
            $this->assertSame('some-random-qr-string', $r['dispatch']['payments'][0]['qr_content']);
            $this->assertSame(array('KA-1'), $r['dispatch']['payments'][0]['order_ids']);
            $snapshot = json_decode($r['rows'][0]['shipping_info'], true);
            $this->assertSame('Gedung A Lantai 5', $snapshot['_shipping_address_2']);
            $this->assertEquals(array(array(38.5,-120.2),array(40.7,-120.95),array(43.252,-126.453)), $snapshot['instant_route_points']);
            $this->assertArrayNotHasKey('poly_line', $snapshot);
            $this->assertArrayNotHasKey('_kiriof_instant_prepared', $snapshot);
            $this->assertSame('instant', $r['rows'][0]['service_name']);
            $this->assertNotEmpty($r['retry_error']);
            $this->assertCount(1, $r['books']);
            $this->assertSame(0, $r['woo'][1]['completions']);
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
    public function unknown_outcomes_have_safe_correlated_diagnostics_without_weakening_claims(): void {
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
            $this->assertSame('error', $log[0]);
            $this->assertSame('kiriminaja_instant', $log[3]);
            $this->assertFalse($log[2]['backtrace']);
            $this->assertSame($code, $log[2]['code']);
            $this->assertSame($stage, $log[2]['stage']);
            $this->assertMatchesRegularExpression('/\A[a-f0-9]{16}\z/', $log[2]['reference']);
            $this->assertSame($r['logs'][0][2]['reference'], $log[2]['reference']);
            $this->assertStringContainsString('Reference: ' . $log[2]['reference'], $r['dispatch']['rows'][0]['message']);
            $this->assertStringContainsString('WooCommerce → Status → Logs', $r['dispatch']['rows'][0]['message']);
            $this->assertSame('unknown', $r['dispatch']['rows'][0]['status']);
            $this->assertSame('pending', $r['rows'][0]['status']);
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
        $this->assertSame('', $r['error']);
        $this->assertSame('unknown', $r['dispatch']['rows'][0]['status']);
        $this->assertSame('pending', $r['rows'][0]['status']);
        $r = $this->runFixture(array('logger_throw'=>true));
        $this->assertSame('booked', $r['dispatch']['rows'][0]['status']);
    }

    #[Test]
    public function definite_rejection_restores_all_unsubmitted_rows_and_public_prices(): void {
        $r = $this->runFixture(['count'=>11, 'definite_rejection'=>true]);
        $this->assertSame('', $r['error']);
        $this->assertSame(array_merge(array_fill(0, 10, 'failed'), array('skipped')), array_column($r['dispatch']['rows'], 'status'));
        $this->assertSame(array_fill(0, 11, true), array_column($r['dispatch']['rows'], 'retryable'));
        $this->assertSame($r['initial_rows'], $r['rows']);
        $this->assertCount(1, $r['books']);
        foreach ($r['rows'] as $row) {
            $this->assertSame('new', $row['status']);
            $this->assertSame(15000, $row['shipping_cost']);
            $this->assertSame('{"_shipping_city":"Saved City","custom":"keep"}', $row['shipping_info']);
            $this->assertEmpty($row['instant_payment_id'] ?? null);
            $this->assertEmpty($row['rejected_reason'] ?? null);
            $this->assertEmpty($row['instant_payment_method'] ?? null);
        }
        $this->assertSame([], $r['options']);
        $this->assertStringNotContainsString('Secret upstream error', json_encode($r));
    }

    #[Test]
    public function safe_failures_restore_exact_data_and_permit_a_fresh_user_initiated_attempt(): void {
        foreach (array('definite_rejection', 'not_submitted', 'validation_rejection') as $failure) {
            $r = $this->runFixture(array($failure=>true, 'count'=>2, 'can_select'=>true));
            $this->assertSame('', $r['error']);
            $this->assertSame($r['initial_rows'], $r['rows']);
            $this->assertSame(array(true, true), $r['can_select']);
            $this->assertSame(array(true, true), array_column($r['dispatch']['rows'], 'retryable'));
            $this->assertSame(array(), $r['dispatch']['payments']);
            foreach ($r['woo'] as $order) {
                $this->assertSame('processing', $order['status']);
                $this->assertSame(0, $order['completions']);
                $this->assertSame(array(), $order['meta']);
            }
            $this->assertSame(array(), $r['options']);
            $r = $this->runFixture(array($failure=>true, 'retry_restored'=>true));
            $this->assertArrayNotHasKey('restored_retry_error', $r);
            $this->assertSame('booked', $r['restored_retry']['rows'][0]['status']);
            $this->assertCount(2, $r['books']);
        }
        foreach (array(array('quote_only'=>true), array('profile'=>'CREDIT', 'method'=>'credit', 'pin'=>'123456', 'credit_fail'=>true), array('claim_fail'=>2), array('snapshot_fail_id'=>'KA-2'), array('snapshot_throw_id'=>'KA-2'), array('consume_fail'=>true)) as $failure) {
            $r = $this->runFixture($failure + array('count'=>2));
            $this->assertSame($r['initial_rows'], $r['rows'], json_encode($failure));
            $this->assertSame(array(), $r['books']);
            $this->assertSame(array(), $r['options']);
        }
    }

    #[Test]
    public function rollback_failure_or_callback_never_advertises_retry_or_clears_authoritative_state(): void {
        foreach (array('rollback_fail', 'rollback_throw', 'rollback_callback') as $failure) {
            $r = $this->runFixture(array('definite_rejection'=>true, $failure=>true, 'can_select'=>true));
            $this->assertSame('', $r['error']);
            $this->assertSame('unknown', $r['dispatch']['rows'][0]['status']);
            $this->assertFalse($r['dispatch']['rows'][0]['retryable']);
            $this->assertSame(array(false), $r['can_select']);
            $this->assertStringContainsString('Unable to verify restoration', $r['dispatch']['rows'][0]['message']);
            $this->assertSame('booking_rollback_failed', $r['logs'][2][2]['code']);
            $this->assertStringNotContainsString('Secret rollback error', json_encode($r));
            $this->assertCount(1, $r['books']);
            if ('rollback_callback' === $failure) {
                $this->assertSame('request_pickup', $r['rows'][0]['status']);
                $this->assertSame(100, $r['rows'][0]['instant_status_code']);
            }
        }
        $r = $this->runFixture(array('count'=>2, 'claim_fail'=>2, 'release_fail'=>true));
        $this->assertStringContainsString('Unable to verify restoration', $r['error']);
        $this->assertSame('pending', $r['rows'][0]['status']);
        $this->assertSame('new', $r['rows'][1]['status']);
        $this->assertSame(array(), $r['books']);
        $this->assertSame(array(), $r['options']);
    }

    #[Test]
    public function later_rejected_group_preserves_prior_successes_payments_and_unsubmitted_rows(): void {
        $r = $this->runFixture(array('count'=>21, 'reject_group'=>2, 'can_select'=>true));
        $this->assertSame('', $r['error']);
        $this->assertCount(2, $r['books']);
        $this->assertSame(array_merge(array_fill(0, 10, 'booked'), array_fill(0, 10, 'failed'), array('skipped')), array_column($r['dispatch']['rows'], 'status'));
        $this->assertSame(array_merge(array_fill(0, 10, false), array_fill(0, 11, true)), array_column($r['dispatch']['rows'], 'retryable'));
        $this->assertCount(1, $r['dispatch']['payments']);
        $this->assertCount(10, $r['dispatch']['payments'][0]['order_ids']);
        $this->assertSame('PAY-1', $r['dispatch']['payments'][0]['id']);
        $this->assertSame(array_slice($r['initial_rows'], 10), array_slice($r['rows'], 10));
        $this->assertSame(array_merge(array_fill(0, 10, false), array_fill(0, 11, true)), $r['can_select']);
    }

    #[Test]
    public function unexpected_exception_cleans_only_unsubmitted_groups_and_diagnostics_cannot_strand_rows(): void {
        $r = $this->runFixture(array('count'=>11, 'fail_later_reference'=>true));
        $this->assertNotEmpty($r['error']);
        $this->assertCount(1, $r['books']);
        $this->assertSame($r['initial_rows'][10], $r['rows'][10]);
        $this->assertSame(array_fill(0, 10, 'request_pickup'), array_column(array_slice($r['rows'], 0, 10), 'status'));
        $this->assertSame(array(), $r['options']);
        $r = $this->runFixture(array('count'=>11, 'timeout'=>true, 'diagnostics_throw'=>true));
        $this->assertSame('', $r['error']);
        $this->assertCount(2, $r['books']);
        $this->assertSame(array_fill(0, 11, 'unknown'), array_column($r['dispatch']['rows'], 'status'));
        $this->assertStringNotContainsString('Secret diagnostics failure', json_encode($r));
    }

    #[Test]
    public function ambiguous_results_keep_only_private_recovery_evidence_not_visible_prepared_fields(): void {
        $r = $this->runFixture(array('timeout'=>true, 'row'=>array('vehicle'=>'mobil', 'instant_payment_method'=>'top', 'shipment_location_snapshot'=>'{"name":"Original Origin"}')));
        $row = $r['rows'][0];
        $this->assertSame('mobil', $row['vehicle']);
        $this->assertSame('top', $row['instant_payment_method']);
        $this->assertSame('{"name":"Original Origin"}', $row['shipment_location_snapshot']);
        $snapshot = json_decode($row['shipping_info'], true);
        $metadata = $snapshot['_kiriof_instant_prepared']['metadata'];
        unset($snapshot['_kiriof_instant_prepared']);
        $this->assertSame(json_decode($r['initial_rows'][0]['shipping_info'], true), $snapshot);
        $this->assertSame('qris', $metadata['instant_payment_method']);
        $this->assertSame(15000, $row['shipping_cost']);
        $this->assertFalse($r['dispatch']['rows'][0]['retryable']);
    }

    #[Test]
    public function saved_database_decimal_prices_are_returned_as_numeric_json(): void {
        $r = $this->runFixture( array( 'quote_only' => true, 'row' => array( 'shipping_cost' => '15000.00' ) ) );
        $this->assertSame( 15000, $r['quote']['rows'][0]['before'] );
        $this->assertTrue( $r['quote']['rows'][0]['eligible'] );
        foreach ( array( 'NaN', -1, true, null ) as $invalid ) {
            $r = $this->runFixture( array( 'quote_only' => true, 'row' => array( 'shipping_cost' => $invalid ) ) );
            $this->assertNull( $r['quote']['rows'][0]['before'] );
            $this->assertFalse( $r['quote']['rows'][0]['eligible'] );
            $this->assertSame( array(), $r['books'] );
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
        $this->assertSame('KA-2', $r['quote']['rows'][1]['id']);
        $this->assertSame('Second Sender', $r['quote']['rows'][1]['origin_label']);
        $this->assertSame('Second Recipient', $r['quote']['rows'][1]['destination_label']);
        $this->assertSame($r['quote']['rows'], $r['repeat_quote']['rows']);
        $this->assertNotSame($r['quote']['token'], $r['repeat_quote']['token']);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $r['quote']['token']);
        $this->assertEqualsWithDelta(120, $r['quote']['expires_at'] - $r['quoted_at'], 1);
        $this->assertSame([120,120], array_values($r['transient_ttls']));
        $this->assertSame(4, $r['prices']);
        $this->assertSame(2, $r['profiles']);
        $this->assertSame([], $r['books']);
        $this->assertSame([], $r['claims']);
        $this->assertSame([], $r['writes']);
        $display = json_encode($r['quote']);
        foreach (['fingerprint','contexts','pricing','phone','latitude','longitude','0812345678','Complete recipient street','Long enough original warehouse address','items','timezone','metadata','pin'] as $private) {
            $this->assertStringNotContainsString($private, $display);
        }
        $r = $this->runFixture(['quote_only'=>true, 'repeat_quote'=>true, 'profile'=>'TOP']);
        $this->assertSame(['top'], $r['quote']['payment_methods']);
        $this->assertSame(['top'], $r['repeat_quote']['payment_methods']);
        $this->assertSame([], $r['books']);
    }

    #[Test]
    public function review_labels_are_plain_text_optional_and_only_follow_valid_contexts(): void {
        $r = $this->runFixture(['quote_only'=>true, 'order_number'=>"<b>SHOP-42</b>\n", 'origin_name'=>'<b>Warehouse</b>', 'destination_name'=>'<i>Recipient</i>']);
        $row = $r['quote']['rows'][0];
        $this->assertSame('SHOP-42', $row['wc_order_number']);
        $this->assertSame('Warehouse', $row['origin_label']);
        $this->assertSame('Recipient', $row['destination_label']);
        $this->assertSame('KA-1', $row['id']);
        $r = $this->runFixture(['quote_only'=>true, 'origin_name'=>'', 'destination_name'=>'']);
        $this->assertSame('Long enough original warehouse address', $r['quote']['rows'][0]['origin_label']);
        $this->assertArrayNotHasKey('destination_label', $r['quote']['rows'][0]);
        foreach (['order_missing','order_number_missing_method','order_number_throw'] as $case) {
            $r = $this->runFixture(['quote_only'=>true, $case=>true]);
            $this->assertSame('1', $r['quote']['rows'][0]['wc_order_number']);
            $this->assertTrue($r['quote']['rows'][0]['eligible']);
            $this->assertStringNotContainsString('Secret order lookup error', json_encode($r['quote']));
        }
        foreach ([null, 0, -1, true, 'invalid', 1.5] as $id) {
            $r = $this->runFixture(['quote_only'=>true, 'row'=>['wp_wc_order_stat_order_id'=>$id]]);
            $this->assertArrayNotHasKey('wc_order_number', $r['quote']['rows'][0]);
        }
        foreach (['context_validation','context_runtime','ineligible_last'] as $case) {
            $r = $this->runFixture(['quote_only'=>true, $case=>true]);
            $row = $r['quote']['rows'][0];
            $this->assertFalse($row['eligible']);
            $this->assertSame('KA-1', $row['id']);
            $this->assertSame('SHOP-1001', $row['wc_order_number']);
            foreach (['courier','service','origin_label','destination_label'] as $field) {
                $this->assertArrayNotHasKey($field, $row);
            }
            $this->assertSame(0, $r['prices']);
        }
    }

    #[Test]
    public function fresh_exact_price_and_snapshots_are_persisted_once(): void {
        $r = $this->runFixture(['retry'=>true,'row'=>['rejected_reason'=>'Check remote state before retrying']]);
        $this->assertSame('', $r['error']);
        $this->assertSame(18000, $r['quote']['rows'][0]['after']);
        $this->assertTrue($r['quote']['rows'][0]['changed']);
        $this->assertSame('booked', $r['dispatch']['rows'][0]['status']);
        $this->assertNull($r['rows'][0]['rejected_reason']);
        $this->assertArrayHasKey('rejected_reason', $r['writes'][1]['changes']);
        $this->assertNull($r['writes'][1]['changes']['rejected_reason']);
        $this->assertSame('AWB-KA-1', $r['dispatch']['rows'][0]['awb']);
        $this->assertSame('unpaid', $r['rows'][0]['instant_payment_status']);
        $this->assertSame(18000, $r['rows'][0]['shipping_cost']);
        $this->assertSame(1, $r['prices']);
        $this->assertCount(1, $r['books']);
        $this->assertNotEmpty($r['retry_error']);
        $this->assertSame([], $r['transients']);
        $this->assertSame([], $r['options']);
        $snapshot = json_decode($r['rows'][0]['shipping_info'], true);
        $this->assertSame('keep', $snapshot['custom']);
        $this->assertSame('Booked Full Name', $snapshot['_shipping_first_name']);
        $this->assertSame('ID', $snapshot['_shipping_country']);
        $this->assertSame('WIB', json_decode($r['rows'][0]['shipment_location_snapshot'], true)['timezone']);
        $this->assertArrayNotHasKey('schedule', $r['books'][0]);
        $this->assertArrayNotHasKey('origin', $r['books'][0]);
        foreach (['address','phone','latitude','longitude','name','packages'] as $field) {
            $this->assertArrayHasKey($field, $r['books'][0]);
        }
        $this->assertSame('qris', $r['books'][0]['payment_method']);
        $this->assertSame('qris', $r['rows'][0]['instant_payment_method']);
        $this->assertSame('https://example.com/tracking', $r['rows'][0]['live_tracking_url']);
        $this->assertArrayNotHasKey('pin', $r['books'][0]);
    }

    #[Test]
    public function token_user_expiry_selection_and_context_fail_closed_before_booking(): void {
        foreach ([['user_change'=>true], ['expire'=>true], ['stale'=>true], ['dispatch_ids'=>['KA-2']], ['ids'=>['KA-1','KA-1']], ['ids'=>[]], ['row'=>['service'=>'borzo']], ['lock'=>true], ['profile_fail'=>true], ['profile_after'=>'TOP'], ['method'=>'top']] as $input) {
            $r = $this->runFixture($input);
            $this->assertNotEmpty($r['error'], json_encode($input));
            $this->assertSame([], $r['books']);
            $this->assertSame([], $r['claims']);
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
        $this->assertFalse($r['quote']['rows'][1]['eligible']);
        $this->assertSame('booked',$r['dispatch']['rows'][0]['status']);
    }

    #[Test]
    public function compatible_origins_split_at_ten_and_match_remote_ids_not_positions(): void {
        $r = $this->runFixture(['count'=>11]);
        $this->assertSame(2, $r['quote']['batch_count']);
        $this->assertCount(2, $r['books']);
        $this->assertCount(10, $r['books'][0]['packages']);
        $this->assertCount(1, $r['books'][1]['packages']);
        $this->assertCount(11, $r['claims']);
        foreach ($r['dispatch']['rows'] as $row) { $this->assertSame('AWB-' . $row['id'], $row['awb']); }
        $r = $this->runFixture(['count'=>2,'other_origin'=>true]);
        $this->assertCount(2, $r['books']);
    }

    #[Test]
    public function ambiguous_remote_results_and_write_failures_keep_claims_and_never_retry(): void {
        foreach ([['timeout'=>true], ['false'=>true], ['response_status'=>1], ['response_status'=>'true'], ['missing'=>true], ['duplicate'=>true], ['position_only'=>true], ['no_payment'=>true], ['remote_status'=>'invalid'], ['remote_status'=>0], ['remote_status'=>9], ['remote_service'=>'grab_express'], ['remote_service_type'=>'instant'], ['remote_missing_identity'=>true], ['write_fail'=>true]] as $input) {
            $r = $this->runFixture($input + ['retry'=>true,'can_select'=>true]);
            $this->assertSame('', $r['error']);
            $this->assertSame('unknown', $r['dispatch']['rows'][0]['status'], json_encode($input));
            $this->assertStringStartsWith('Unable to confirm the Instant booking. Contact support before trying again. Reference: ', $r['dispatch']['rows'][0]['message']);
            $this->assertSame('pending', $r['rows'][0]['status']);
            $this->assertSame([false], $r['can_select']);
            $this->assertSame([], $r['dispatch']['payments']);
            $this->assertArrayNotHasKey('instant_payment_id', $r['rows'][0]);
            $this->assertArrayNotHasKey('instant_status_code', $r['rows'][0]);
            $this->assertSame(15000, $r['rows'][0]['shipping_cost']);
            $this->assertArrayNotHasKey('rejected_reason', $r['rows'][0]);
            $this->assertStringNotContainsString('Secret upstream error', json_encode($r));
            $this->assertStringNotContainsString('Secret issue write error', json_encode($r));
            $this->assertSame([], $r['releases']);
            $this->assertCount(1, $r['books']);
            $this->assertSame(['KA-1'], $r['claims']);
            $this->assertNotEmpty($r['retry_error']);
        }
        $r = $this->runFixture(['count'=>2,'missing'=>true]);
        $this->assertSame(['unknown','booked'], array_column($r['dispatch']['rows'],'status'));
        $this->assertSame(['KA-2'], $r['dispatch']['payments'][0]['order_ids']);
        $this->assertArrayNotHasKey('rejected_reason', $r['rows'][0]);
        $this->assertArrayNotHasKey('instant_payment_id', $r['rows'][0]);
        $this->assertArrayNotHasKey('rejected_reason', $r['rows'][1]);
    }

    #[Test]
    public function issue_write_exceptions_are_nonfatal_and_never_release_or_complete_unknown_bookings(): void {
        $r = $this->runFixture(['timeout'=>true,'issue_write_throw'=>true,'retry'=>true,'can_select'=>true]);
        $this->assertSame('', $r['error']);
        $this->assertSame('unknown', $r['dispatch']['rows'][0]['status']);
        $this->assertStringStartsWith('Unable to confirm the Instant booking. Contact support before trying again. Reference: ', $r['dispatch']['rows'][0]['message']);
        $this->assertSame('pending', $r['rows'][0]['status']);
        $this->assertSame([false], $r['can_select']);
        $this->assertArrayNotHasKey('rejected_reason', $r['rows'][0]);
        $this->assertArrayNotHasKey('instant_payment_id', $r['rows'][0]);
        $this->assertCount(1, $r['writes']);
        $this->assertCount(1, $r['books']);
        $this->assertSame([], $r['dispatch']['payments']);
        $this->assertSame([], $r['releases']);
        $this->assertSame(['KA-1'], $r['claims']);
        $this->assertNotEmpty($r['retry_error']);
        $this->assertStringNotContainsString('Secret upstream error', json_encode($r));
        $this->assertStringNotContainsString('Secret issue write error', json_encode($r));
    }

    #[Test]
    public function payments_include_only_verified_bookings_in_each_group(): void {
        $r = $this->runFixture(['count'=>11,'missing'=>true,'malformed_package'=>true]);
        $this->assertSame('', $r['error']);
        $this->assertCount(2, $r['books']);
        $this->assertCount(1, $r['dispatch']['payments']);
        $this->assertSame('PAY-1', $r['dispatch']['payments'][0]['id']);
        $this->assertSame(['KA-2','KA-3','KA-4','KA-5','KA-6','KA-7','KA-8','KA-9','KA-10'], $r['dispatch']['payments'][0]['order_ids']);
        $this->assertSame('unknown', $r['dispatch']['rows'][10]['status']);
        $r = $this->runFixture(['count'=>2,'other_origin'=>true,'write_fail_id'=>'KA-1']);
        $this->assertSame(['unknown','booked'], array_column($r['dispatch']['rows'],'status'));
        $this->assertCount(1, $r['dispatch']['payments']);
        $this->assertSame('PAY-2', $r['dispatch']['payments'][0]['id']);
        $this->assertSame(['KA-2'], $r['dispatch']['payments'][0]['order_ids']);
        foreach ([['missing'=>true], ['write_fail'=>true]] as $input) {
            $r = $this->runFixture($input + ['refresh'=>true]);
            $this->assertSame([], $r['dispatch']['payments']);
            $this->assertNotEmpty($r['error']);
            $this->assertSame(0, $r['payments_called']);
            $this->assertSame('pending', $r['rows'][0]['status']);
            $this->assertSame([], $r['releases']);
        }
    }

    #[Test]
    public function leases_recover_only_expired_owners_and_never_release_crash_claims(): void {
        $r = $this->runFixture(['lease'=>'active']);
        $this->assertNotEmpty($r['error']);
        $this->assertSame([], $r['books']);
        $this->assertSame([], $r['claims']);
        $this->assertSame('old-owner', array_values($r['options'])[0]['owner']);
        $r = $this->runFixture(['lease'=>'expired']);
        $this->assertSame('', $r['error']);
        $this->assertSame('booked', $r['dispatch']['rows'][0]['status']);
        $this->assertSame([], $r['options']);
        $r = $this->runFixture(['lease'=>'expired','crash_pending'=>true]);
        $this->assertNotEmpty($r['error']);
        $this->assertSame([], $r['books']);
        $this->assertSame([], $r['claims']);
        $this->assertSame([], $r['releases']);
        $this->assertSame('pending', $r['rows'][0]['status']);
        $this->assertSame([], $r['options']);
        $r = $this->runFixture(['lease'=>'expired','lease_delete_race'=>true]);
        $this->assertNotEmpty($r['error']);
        $this->assertSame([], $r['books']);
        $this->assertSame('replacement-owner', array_values($r['options'])[0]['owner']);
        $r = $this->runFixture(['replace_lease_during_book'=>true]);
        $this->assertSame('', $r['error']);
        $this->assertSame('booked', $r['dispatch']['rows'][0]['status']);
        $this->assertSame('replacement-owner', array_values($r['options'])[0]['owner']);
    }

    #[Test]
    public function failed_atomic_claim_releases_only_pre_api_successes(): void {
        $r = $this->runFixture(['count'=>2,'claim_fail'=>2]);
        $this->assertNotEmpty($r['error']);
        $this->assertSame(['KA-1'],$r['claims']);
        $this->assertSame(['KA-1'],$r['releases']);
        $this->assertSame([], $r['books']);
        $this->assertSame([], $r['options']);
        $this->assertNotEmpty($r['transients']);
        $this->assertSame(['new','new'],array_column($r['rows'],'status'));
    }

    #[Test]
    public function credit_is_validated_once_and_pin_never_persisted_top_is_not_inferred_paid(): void {
        $r = $this->runFixture(['count'=>11,'method'=>'credit','pin'=>'654321','echo_pin'=>true,'echo_pin_qr'=>true]);
        $this->assertSame([['amount'=>198000,'valid_pin'=>true]], $r['credits']);
        $this->assertStringNotContainsString('654321',json_encode($r));
        foreach ($r['books'] as $book) {
            $this->assertSame('credit', $book['payment_method']);
            $this->assertTrue($book['valid_credit_pin']);
            $this->assertArrayNotHasKey('origin', $book);
        }
        $this->assertSame('credit', $r['rows'][0]['instant_payment_method']);
        foreach ([['pin'=>'123'], ['pin'=>'abcdef'], ['pin'=>'654321','credit_fail'=>true]] as $input) {
            $r = $this->runFixture($input + ['method'=>'credit']);
            $this->assertNotEmpty($r['error']);
            $this->assertSame([], $r['books']);
        }
        $r = $this->runFixture(['profile'=>'TOP','method'=>'top','payment_status'=>'weird']);
        $this->assertSame(['top'],$r['quote']['payment_methods']);
        $this->assertSame('pending',$r['rows'][0]['instant_payment_status']);
        $this->assertSame('top',$r['rows'][0]['instant_payment_method']);
        $this->assertArrayNotHasKey('payment_method',$r['books'][0]);
        $this->assertArrayNotHasKey('pin',$r['books'][0]);
        $r = $this->runFixture(['profile'=>'TOP','method'=>'credit','pin'=>'654321']);
        $this->assertSame([], $r['credits']);
    }

    #[Test]
    public function payment_refresh_verifies_all_selected_ids_before_remote_and_only_updates_status(): void {
        $r = $this->runFixture(['refresh'=>true]);
        $this->assertSame('paid',$r['refresh']['status']);
        $this->assertSame(['instant_payment_status'=>'paid'],$r['writes'][2]['changes']);
        $r = $this->runFixture(['refresh'=>true,'refresh_pid'=>'OTHER']);
        $this->assertNotEmpty($r['error']);
        $this->assertSame(0,$r['payments_called']);
        $r = $this->runFixture(['refresh'=>true,'refresh_status'=>'unknown']);
        $this->assertSame('unpaid',$r['refresh']['status']);
        $this->assertCount(2,$r['writes']);
    }

    #[Test]
    public function an_actual_awb_is_optional_but_payment_and_remote_status_are_not_guessed(): void {
        $r = $this->runFixture(['no_awb'=>true,'payment_status'=>0]);
        $this->assertSame('booked', $r['dispatch']['rows'][0]['status']);
        $this->assertArrayNotHasKey('awb', $r['writes'][0]['changes']);
        $this->assertSame('paid', $r['rows'][0]['instant_payment_status']);
        $r = $this->runFixture(['profile'=>'TOP','method'=>'top','no_payment'=>true]);
        $this->assertSame('unknown', $r['dispatch']['rows'][0]['status']);
        $this->assertSame('pending', $r['rows'][0]['status']);
    }

    #[Test]
    public function v62_status_codes_take_precedence_and_sdk_nested_results_are_unwrapped(): void {
        foreach ([0=>'paid',9=>'unpaid'] as $code=>$expected) {
            foreach ([$code,(string)$code] as $value) {
                $r = $this->runFixture(['payment_status'=>$value,'payment_legacy_status'=>'refunded','nested_booking'=>true,'refresh'=>true,'nested_payment'=>true,'refresh_status'=>$value,'refresh_legacy_status'=>'refunded']);
                $this->assertSame('booked',$r['dispatch']['rows'][0]['status']);
                $this->assertSame($expected,$r['dispatch']['payments'][0]['status']);
                $this->assertSame($expected,$r['refresh']['status']);
                $this->assertSame($expected,$r['rows'][0]['instant_payment_status']);
            }
        }
        $r = $this->runFixture(['refresh'=>true,'refresh_status'=>42,'refresh_legacy_status'=>'paid']);
        $this->assertSame('unpaid',$r['refresh']['status']);
        $this->assertSame('unpaid',$r['rows'][0]['instant_payment_status']);
        $this->assertCount(2,$r['writes']);
        foreach ([0,9] as $code) {
            $r = $this->runFixture(['profile'=>'TOP','method'=>'top','payment_status'=>$code]);
            $this->assertSame(0 === $code ? 'paid' : 'unpaid',$r['rows'][0]['instant_payment_status']);
            $this->assertArrayNotHasKey('payment_method',$r['books'][0]);
        }
        foreach ([false,1,'true'] as $status) {
            $r = $this->runFixture(['refresh'=>true,'refresh_response_status'=>$status]);
            $this->assertSame('Unable to refresh the Instant payment.',$r['error']);
            $this->assertCount(2,$r['writes']);
            $this->assertSame('unpaid',$r['rows'][0]['instant_payment_status']);
        }
    }

    #[Test]
    public function only_local_context_validation_messages_are_exposed_in_quote_errors(): void {
        $r = $this->runFixture(['context_validation'=>true,'quote_only'=>true]);
        $this->assertFalse($r['quote']['rows'][0]['eligible']);
        $this->assertSame('Valid origin and destination coordinates are required for Instant delivery.',$r['quote']['rows'][0]['error']);
        $this->assertSame(0,$r['prices']);
        $r = $this->runFixture(['context_runtime'=>true,'quote_only'=>true]);
        $this->assertSame('This Instant shipment could not be quoted. Check its addresses, items and courier service.',$r['quote']['rows'][0]['error']);
        $this->assertStringNotContainsString('123456',json_encode($r));
        $this->assertSame([], $r['books']);
    }
    #[Test]
    public function callbacks_during_booking_preserve_lifecycle_payment_and_attach_request_snapshots(): void {
        foreach ([106=>'shipped', 200=>'finished', 350=>'pending', 300=>'canceled', 401=>'return', 400=>'returned'] as $code=>$expected) {
            foreach (['paid', 'refunded'] as $payment) {
                $r = $this->runFixture(['callback_code'=>$code, 'callback_payment'=>$payment, 'callback_no_pid'=>true, 'retry'=>true]);
                $this->assertSame('', $r['error']);
                $this->assertSame('booked', $r['dispatch']['rows'][0]['status']);
                $this->assertSame($expected, $r['rows'][0]['status']);
                $this->assertSame($expected, $r['dispatch']['rows'][0]['shipment_status']);
                $this->assertSame($code, $r['rows'][0]['instant_status_code']);
                $this->assertSame($payment, $r['rows'][0]['instant_payment_status']);
                $this->assertSame($payment, $r['dispatch']['payments'][0]['status']);
                $this->assertSame('PAY-1', $r['rows'][0]['instant_payment_id']);
                $this->assertSame('AWB-KA-1', $r['dispatch']['rows'][0]['awb']);
                $this->assertArrayHasKey('instant_items', json_decode($r['rows'][0]['shipping_info'], true));
                $this->assertSame('WIB', json_decode($r['rows'][0]['shipment_location_snapshot'], true)['timezone']);
                foreach (['shipping_info','shipment_location_snapshot','shipping_cost','vehicle','instant_payment_method'] as $field) {
                    $this->assertSame('shipping_cost' === $field ? 18000 : $this->preparedMetadata($r['prepared_at_book'][0]['KA-1'])[$field], $r['rows'][0][$field]);
                }
                $this->assertCount(1, $r['books']);
                $this->assertSame([], $r['releases']);
                $this->assertSame(200 === $code ? 'completed' : 'processing', $r['woo'][1]['status']);
                $this->assertSame(200 === $code ? 1 : 0, $r['woo'][1]['completions']);
            }
        }
    }

    #[Test]
    public function refresh_reports_effective_monotonic_payment_status_and_rejects_mismatched_identity(): void {
        foreach (['paid', 'refunded'] as $previous) {
            foreach ([9, 'pending', 'unknown', 0] as $remote) {
                $r = $this->runFixture(['payment_status'=>$previous, 'refresh'=>true, 'refresh_status'=>$remote]);
                $this->assertSame('', $r['error']);
                $this->assertSame($previous, $r['rows'][0]['instant_payment_status']);
                $this->assertSame($previous, $r['refresh']['status']);
                $this->assertCount(2, $r['writes']);
                $this->assertSame(1, $r['payments_called']);
            }
        }
        $r = $this->runFixture(['count'=>2, 'refresh'=>true, 'refresh_status'=>'unknown', 'before_refresh_statuses'=>['paid', 'refunded']]);
        $this->assertSame('refunded', $r['refresh']['status']);
        $this->assertSame(['paid', 'refunded'], array_column($r['rows'], 'instant_payment_status'));
        $this->assertCount(4, $r['writes']);
        $r = $this->runFixture(['count'=>11, 'refresh'=>true]);
        $this->assertNotEmpty($r['error']);
        $this->assertSame(0, $r['payments_called']);
        $r = $this->runFixture(['refresh'=>true, 'response_pid'=>'OTHER']);
        $this->assertSame('The Instant payment response is invalid.', $r['error']);
        $this->assertSame('unpaid', $r['rows'][0]['instant_payment_status']);
        $this->assertCount(2, $r['writes']);
    }

    #[Test]
    public function booking_requires_valid_woo_link_and_known_shipment_code_not_payment_code_zero(): void {
        foreach ([0, '0', 9, '9', true, 105.5, 999] as $code) {
            $r = $this->runFixture(['remote_status'=>$code, 'payment_status'=>0]);
            $this->assertSame('unknown', $r['dispatch']['rows'][0]['status']);
            $this->assertSame('pending', $r['rows'][0]['status']);
            $this->assertArrayNotHasKey('instant_status_code', $r['rows'][0]);
            $this->assertSame([], $r['dispatch']['payments']);
        }
        $r = $this->runFixture(['row'=>['wp_wc_order_stat_order_id'=>null]]);
        $this->assertSame('unknown', $r['dispatch']['rows'][0]['status']);
        $this->assertSame('pending', $r['rows'][0]['status']);
        $r = $this->runFixture(['remote_status'=>200]);
        $this->assertSame('finished', $r['rows'][0]['status']);
        $this->assertSame('completed', $r['woo'][1]['status']);
        $this->assertSame(1, $r['woo'][1]['completions']);
    }

    #[Test]
    public function ambiguous_booking_does_not_erase_a_callback_issue_note(): void {
        $r = $this->runFixture(['callback_code'=>500, 'missing'=>true]);
        $this->assertSame('', $r['error']);
        $this->assertSame('unknown', $r['dispatch']['rows'][0]['status']);
        $this->assertSame('pending', $r['rows'][0]['status']);
        $this->assertSame(500, $r['rows'][0]['instant_status_code']);
        $this->assertSame('There is a problem with this Instant shipment.', $r['rows'][0]['rejected_reason']);
        $this->assertCount(2, $r['writes']);
        $this->assertCount(1, $r['books']);
        $this->assertSame([], $r['releases']);
    }

    #[Test]
    public function every_reviewed_snapshot_is_durable_before_the_first_outbound_booking(): void {
        $r = $this->runFixture(['count'=>11, 'method'=>'credit', 'pin'=>'654321']);
        $this->assertSame('', $r['error']);
        $this->assertCount(11, $r['prepared_at_book'][0]);
        foreach ($r['prepared_at_book'][0] as $id=>$row) {
            $this->assertSame('pending', $row['status']);
            $this->assertSame(15000, $row['shipping_cost']);
            $metadata = $this->preparedMetadata($row);
            $this->assertArrayNotHasKey('instant_payment_method', $row);
            $this->assertSame('credit', $metadata['instant_payment_method']);
            $this->assertSame('motor', $row['vehicle']);
            $this->assertSame('WIB', json_decode($metadata['shipment_location_snapshot'], true)['timezone']);
            $private = json_decode($row['shipping_info'], true);
            unset($private['_kiriof_instant_prepared']);
            $this->assertSame(json_decode($r['initial_rows'][array_search($id, array_keys($r['prepared_at_book'][0]), true)]['shipping_info'], true), $private);
            $this->assertArrayNotHasKey('shipment_location_snapshot', $row);
            $snapshot = json_decode($metadata['shipping_info'], true);
            $this->assertSame('Booked Full Name', $snapshot['_shipping_first_name']);
            $this->assertSame([['name'=>'Item', 'qty'=>1]], $snapshot['instant_items']);
            foreach (['request_pickup_at','instant_payment_id','instant_status_code','pin'] as $field) {
                $this->assertArrayNotHasKey($field, $row);
                $this->assertArrayNotHasKey($field, $r['writes'][array_search($id, array_keys($r['prepared_at_book'][0]), true)]['changes']);
            }
        }
        $this->assertStringNotContainsString('654321', json_encode($r));
    }

    #[Test]
    public function prepared_snapshot_failures_release_the_whole_batch_without_consuming_quote_or_booking(): void {
        foreach (['snapshot_fail_id','snapshot_throw_id'] as $failure) {
            $r = $this->runFixture(['count'=>2, $failure=>'KA-2']);
            $this->assertSame('Unable to save the prepared Instant shipment.', $r['error']);
            $this->assertSame(['KA-1','KA-2'], $r['claims']);
            $this->assertSame(['KA-2'], $r['releases']);
            $this->assertSame(['new','new'], array_column($r['rows'], 'status'));
            $this->assertSame([], $r['books']);
            $this->assertSame([], $r['options']);
            $this->assertNotEmpty($r['transients']);
            $this->assertArrayNotHasKey('instant_items', json_decode($r['rows'][0]['shipping_info'], true));
            $this->assertArrayNotHasKey('instant_items', json_decode($r['rows'][1]['shipping_info'], true));
            $this->assertSame([false,false], $r['can_print']);
            $this->assertStringNotContainsString('Secret snapshot error', json_encode($r));
        }
    }

    #[Test]
    public function ambiguous_booking_recovers_using_prepared_snapshots_only_after_verified_lifecycle(): void {
        $r = $this->runFixture(['timeout'=>true, 'retry'=>true, 'new_retry'=>true, 'recover'=>true]);
        $this->assertSame('', $r['error']);
        $this->assertSame('unknown', $r['dispatch']['rows'][0]['status']);
        $this->assertNotEmpty($r['retry_error']);
        $this->assertNotEmpty($r['new_retry_error']);
        $this->assertCount(1, $r['books']);
        $this->assertSame('pending', $r['metadata_recovery_row']['status']);
        $this->assertSame('RECOVERED-AWB', $r['metadata_recovery_row']['awb']);
        $this->assertFalse($r['metadata_recovery_can_print']);
        $this->assertArrayNotHasKey('instant_status_code', $r['metadata_recovery_row']);
        $this->assertArrayNotHasKey('request_pickup_at', $r['metadata_recovery_row']);
        $this->assertSame('request_pickup', $r['rows'][0]['status']);
        $this->assertSame([true], $r['can_print']);
        foreach (['shipping_info','shipment_location_snapshot','shipping_cost','vehicle','instant_payment_method'] as $field) {
            $this->assertSame('shipping_cost' === $field ? 18000 : $this->preparedMetadata($r['prepared_at_book'][0]['KA-1'])[$field], $r['rows'][0][$field]);
        }
        $this->assertArrayNotHasKey('instant_payment_id', $r['rows'][0]);
        $this->assertSame([], $r['releases']);
    }

}
