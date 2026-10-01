<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantDispatchRuntimeTest extends TestCase {
    private function runFixture(array $input = []): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/instant-dispatch-runtime.php') . ' ' . escapeshellarg(json_encode($input, JSON_THROW_ON_ERROR)));
        return json_decode((string)$output, true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function fresh_exact_price_and_snapshots_are_persisted_once(): void {
        $r = $this->runFixture(['retry'=>true,'row'=>['rejected_reason'=>'Check remote state before retrying']]);
        $this->assertSame('', $r['error']);
        $this->assertSame(18000, $r['quote']['rows'][0]['after']);
        $this->assertTrue($r['quote']['rows'][0]['changed']);
        $this->assertSame('booked', $r['dispatch']['rows'][0]['status']);
        $this->assertNull($r['rows'][0]['rejected_reason']);
        $this->assertArrayHasKey('rejected_reason', $r['writes'][0]['changes']);
        $this->assertNull($r['writes'][0]['changes']['rejected_reason']);
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
        $this->assertSame('cash', $r['books'][0]['payment_method']);
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
        foreach ([['timeout'=>true], ['false'=>true], ['response_status'=>1], ['response_status'=>'true'], ['missing'=>true], ['duplicate'=>true], ['position_only'=>true], ['no_payment'=>true], ['remote_status'=>'invalid'], ['write_fail'=>true]] as $input) {
            $r = $this->runFixture($input + ['retry'=>true,'can_select'=>true]);
            $this->assertSame('', $r['error']);
            $this->assertSame('unknown', $r['dispatch']['rows'][0]['status'], json_encode($input));
            $this->assertSame('Check remote state before retrying', $r['dispatch']['rows'][0]['message']);
            $this->assertSame('pending', $r['rows'][0]['status']);
            $this->assertSame([false], $r['can_select']);
            $this->assertSame([], $r['dispatch']['payments']);
            $this->assertArrayNotHasKey('instant_payment_id', $r['rows'][0]);
            $this->assertArrayNotHasKey('instant_status_code', $r['rows'][0]);
            $issueWrite = $r['writes'][count($r['writes']) - 1];
            $this->assertSame(['condition'=>['order_id'=>'KA-1','status'=>'pending'],'changes'=>['rejected_reason'=>'Check remote state before retrying']], $issueWrite);
            $this->assertCount(!empty($input['write_fail']) ? 2 : 1, $r['writes']);
            if (empty($input['write_fail'])) {
                $this->assertSame('Check remote state before retrying', $r['rows'][0]['rejected_reason']);
            } else {
                $this->assertArrayNotHasKey('rejected_reason', $r['rows'][0]);
            }
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
        $this->assertSame('Check remote state before retrying', $r['rows'][0]['rejected_reason']);
        $this->assertArrayNotHasKey('instant_payment_id', $r['rows'][0]);
        $this->assertNull($r['rows'][1]['rejected_reason']);
    }

    #[Test]
    public function issue_write_exceptions_are_nonfatal_and_never_release_or_complete_unknown_bookings(): void {
        $r = $this->runFixture(['timeout'=>true,'issue_write_throw'=>true,'retry'=>true,'can_select'=>true]);
        $this->assertSame('', $r['error']);
        $this->assertSame('unknown', $r['dispatch']['rows'][0]['status']);
        $this->assertSame('Check remote state before retrying', $r['dispatch']['rows'][0]['message']);
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
        $this->assertSame(['instant_payment_status'=>'paid'],$r['writes'][1]['changes']);
        $r = $this->runFixture(['refresh'=>true,'refresh_pid'=>'OTHER']);
        $this->assertNotEmpty($r['error']);
        $this->assertSame(0,$r['payments_called']);
        $r = $this->runFixture(['refresh'=>true,'refresh_status'=>'unknown']);
        $this->assertSame('pending',$r['refresh']['status']);
        $this->assertCount(1,$r['writes']);
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
        $this->assertSame('pending',$r['refresh']['status']);
        $this->assertSame('unpaid',$r['rows'][0]['instant_payment_status']);
        $this->assertCount(1,$r['writes']);
        foreach ([0,9] as $code) {
            $r = $this->runFixture(['profile'=>'TOP','method'=>'top','payment_status'=>$code]);
            $this->assertSame(0 === $code ? 'paid' : 'unpaid',$r['rows'][0]['instant_payment_status']);
            $this->assertArrayNotHasKey('payment_method',$r['books'][0]);
        }
        foreach ([false,1,'true'] as $status) {
            $r = $this->runFixture(['refresh'=>true,'refresh_response_status'=>$status]);
            $this->assertSame('Unable to refresh the Instant payment.',$r['error']);
            $this->assertCount(1,$r['writes']);
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
}
