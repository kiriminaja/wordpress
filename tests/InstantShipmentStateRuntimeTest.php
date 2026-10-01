<?php

declare(strict_types=1);

// Self-contained subprocess fixture: no WordPress/WooCommerce or real SQL is needed.
if (in_array('--fixture', $argv ?? [], true)) {
    define('ABSPATH', __DIR__);
    function esc_html__($text, $domain) { return $text; }
    function wp_parse_url($url) { return parse_url($url); }
    function esc_url_raw($url, $protocols = []) { return $url; }
    function wc_get_order($id) { return $GLOBALS['missing_order'] ? false : $GLOBALS['order']; }
    eval('namespace KiriminAjaOfficial\\Repositories;
        class TransactionRepository {
            public $row; public $writes = []; public $reads = 0; public $race; public $fail = false;
            public function getTransactionByOrderId($id) { ++$this->reads; return clone $this->row; }
            public function compareAndSwapInstant($id, array $expected, array $changes): bool {
                $payload = ["condition"=>$expected,"changes"=>$changes];
                if ($id !== $this->row->order_id) { return false; }
                $this->writes[] = $payload;
                if ($this->fail) { return false; }
                if ($this->race !== null) { foreach ($this->race as $key => $value) { $this->row->{$key} = $value; } $this->race = null; return false; }
                foreach ($payload["condition"] as $key => $value) { if (($this->row->{$key} ?? null) !== $value) { return false; } }
                foreach ($payload["changes"] as $key => $value) { $this->row->{$key} = $value; }
                return true;
            }
        }');
    require __DIR__ . '/../inc/Services/TransactionDeliveryType.php';
    require __DIR__ . '/../inc/Services/InstantShipmentState.php';
    require __DIR__ . '/../inc/Services/InstantWebhookService.php';
    $input = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
    $repo = new KiriminAjaOfficial\Repositories\TransactionRepository();
    $repo->row = (object) array_replace(['order_id'=>'KA-1', 'delivery_type'=>'instant', 'service'=>'gosend', 'service_name'=>'instant', 'status'=>'request_pickup', 'instant_status_code'=>100, 'instant_payment_id'=>'PAY-1', 'instant_payment_status'=>'paid', 'awb'=>'AWB-1', 'live_tracking_url'=>'', 'wp_wc_order_stat_order_id'=>1], $input['row'] ?? []);
    $repo->race = $input['race'] ?? null;
    $repo->fail = $input['fail'] ?? false;
    $GLOBALS['order'] = new class {
        public $status = 'processing'; public $notes = []; public $meta = []; public $fail = false; public $note_fail = false;
        public function get_status() { return $this->status; }
        public function update_status($status, $note) { if ($this->fail) { $this->fail = false; throw new RuntimeException('Woo failure'); } $this->status = $status; $this->notes[] = $note; }
        public function get_meta($key, $single) { return $this->meta[$key] ?? ''; }
        public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
        public function save() { return 1; }
        public function add_order_note($note) { if ($this->note_fail) { $this->note_fail = false; return 0; } $this->notes[] = $note; return count($this->notes); }
    };
    $GLOBALS['missing_order'] = $input['missing_order'] ?? false;
    $GLOBALS['order']->note_fail = $input['note_fail'] ?? false;
    $GLOBALS['order']->status = $input['woo_status'] ?? 'processing';
    $GLOBALS['order']->fail = $input['woo_fail'] ?? false;
    $state = new KiriminAjaOfficial\Services\InstantShipmentState($repo);
    $results = [];
    foreach ($input['events'] ?? [['package'=>['order_id'=>'KA-1','status'=>106]]] as $event) {
        try {
            if (($event['mode'] ?? '') === 'booking') { $results[] = $state->confirmBooking('KA-1', $event['package'], $event['payment'] ?? [], $event['metadata'] ?? []); }
            elseif (($event['mode'] ?? '') === 'metadata') { $results[] = $state->mergeTrackingMetadata('KA-1', $event['package']); }
            elseif (($event['mode'] ?? '') === 'payment') { $results[] = $state->mergePayment('KA-1', $event['payment']); }
            else { $results[] = $state->apply('KA-1', $event['package'], $event['payment'] ?? [], $event['event'] ?? null); }
        }
        catch (Throwable $error) { $results[] = ['error'=>get_class($error), 'code'=>$error->getCode()]; }
    }
    $webhook = isset($input['webhook']) ? (new KiriminAjaOfficial\Services\InstantWebhookService($state))->handle(json_decode(json_encode($input['webhook'])), [$repo->row]) : null;
    echo json_encode(['webhook'=>$webhook, 'results'=>$results, 'row'=>$repo->row, 'writes'=>$repo->writes, 'reads'=>$repo->reads, 'woo'=>$GLOBALS['order'], 'cancelable'=>KiriminAjaOfficial\Services\InstantShipmentState::canCancel($repo->row)], JSON_THROW_ON_ERROR);
    exit;
}

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantShipmentStateRuntimeTest extends TestCase {
    private function runFixture(array $input = []): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --fixture ' . escapeshellarg(json_encode($input, JSON_THROW_ON_ERROR)));
        return json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
    }

    private function event($code, array $extra = [], array $payment = []): array {
        return ['package'=>array_replace(['order_id'=>'KA-1', 'status'=>$code], $extra), 'payment'=>$payment];
    }

    #[Test]
    public function shipment_progress_is_monotonic_and_duplicates_have_no_notes(): void {
        $r = $this->runFixture(['events'=>[$this->event(106), $this->event(101), $this->event(200), $this->event(200), $this->event(300)]]);
        $this->assertSame('finished', $r['row']['status']);
        $this->assertSame(200, $r['row']['instant_status_code']);
        $this->assertFalse($r['results'][1]['changed']);
        $this->assertFalse($r['results'][3]['changed']);
        $this->assertFalse($r['results'][4]['changed']);
        $this->assertSame('completed', $r['woo']['status']);
        $this->assertCount(1, $r['woo']['notes']);
        foreach ([[300,200], [200,300], [400,200], [200,400]] as $codes) {
            $r = $this->runFixture(['events'=>[$this->event($codes[0]), $this->event($codes[1])]]);
            $this->assertSame($codes[0], $r['row']['instant_status_code']);
        }
    }

    #[Test]
    public function invalid_identity_and_unknown_codes_never_write(): void {
        foreach ([['order_id'=>1], ['order_id'=>'KA-2'], ['awb'=>'OTHER'], ['awb'=>'<script>'], ['service'=>'borzo'], ['service_type'=>'same_day'], ['status'=>0], ['status'=>true], ['status'=>999], ['status'=>null]] as $extra) {
            $r = $this->runFixture(['events'=>[$this->event(106, $extra)]]);
            $this->assertSame([], $r['writes'], json_encode($extra));
            $this->assertSame(InvalidArgumentException::class, $r['results'][0]['error']);
        }
        foreach ([['status'=>'new'], ['service'=>'borzo']] as $row) {
            $r = $this->runFixture(['row'=>$row]);
            $this->assertSame([], $r['writes']);
        }
        foreach ([['id'=>null], ['id'=>'WRONG'], ['id'=>0]] as $payment) {
            $r = $this->runFixture(['events'=>[$this->event(106, [], $payment)]]);
            $this->assertSame([], $r['writes']);
        }
    }

    #[Test]
    public function cas_reloads_and_null_codes_and_exhaustion_are_explicit(): void {
        $r = $this->runFixture(['race'=>['status'=>'finished','instant_status_code'=>200]]);
        $this->assertSame(2, $r['reads']);
        $this->assertSame('finished', $r['row']['status']);
        $this->assertFalse($r['results'][0]['changed']);
        $r = $this->runFixture(['row'=>['status'=>'pending','instant_status_code'=>null, 'instant_payment_id'=>null], 'events'=>[$this->event(100, [], ['id'=>'PAY-2','status_code'=>0])]]);
        $this->assertNull($r['writes'][0]['condition']['instant_status_code']);
        $this->assertSame('request_pickup', $r['row']['status']);
        $this->assertSame('PAY-2', $r['row']['instant_payment_id']);
        $r = $this->runFixture(['fail'=>true]);
        $this->assertCount(3, $r['writes']);
        $this->assertSame(503, $r['results'][0]['code']);
        $this->assertSame([], $r['woo']['notes']);
    }

    #[Test]
    public function safe_metadata_and_payment_do_not_regress_on_stale_events(): void {
        $r = $this->runFixture(['events'=>[$this->event(106, ['live_tracking_url'=>'https://example.com/first']), $this->event(100, ['live_tracking_url'=>'https://example.com/stale'], ['id'=>'PAY-1','status_code'=>9])]]);
        $this->assertSame('https://example.com/first', $r['row']['live_tracking_url']);
        $this->assertSame('paid', $r['row']['instant_payment_status']);
        foreach (['javascript:alert(1)', 'https://user:pass@example.com/', "https://example.com/\nsecret", 'https://example.com\\evil'] as $url) {
            $r = $this->runFixture(['events'=>[$this->event(106, ['live_tracking_url'=>$url,'pin'=>'123456','qr_content'=>'secret'])]]);
            $this->assertSame('', $r['row']['live_tracking_url']);
            $this->assertArrayNotHasKey('pin', $r['row']);
            $this->assertArrayNotHasKey('qr_content', $r['row']);
        }
        $r = $this->runFixture(['events'=>[$this->event(100, [], ['id'=>'PAY-1','status'=>'refunded']), $this->event(100, [], ['id'=>'PAY-1','status_code'=>0])]]);
        $this->assertSame('refunded', $r['row']['instant_payment_status']);
        $r = $this->runFixture(['row'=>['status'=>'shipped','instant_status_code'=>106], 'events'=>[$this->event(100, ['tracking_url'=>'https://example.com/fill'])]]);
        $this->assertSame('https://example.com/fill', $r['row']['live_tracking_url']);
        $this->assertSame(106, $r['row']['instant_status_code']);
    }

    #[Test]
    public function authoritative_dates_issues_and_cancelability_are_conservative(): void {
        $r = $this->runFixture(['events'=>[['package'=>['order_id'=>'KA-1','date'=>'2025-01-02T10:30:00+07:00'], 'event'=>'shipped_packages']]]);
        $this->assertSame('2025-01-02 03:30:00', $r['row']['shipped_at']);
        $r = $this->runFixture(['events'=>[$this->event(106, ['date'=>0])]]);
        $this->assertNotSame('1970-01-01 00:00:00', $r['row']['shipped_at']);
        $r = $this->runFixture(['events'=>[['package'=>['order_id'=>'KA-1','status'=>null], 'event'=>'finished_packages']]]);
        $this->assertArrayHasKey('error', $r['results'][0]);
        foreach ([350,500,555,701,702,703,704,303,301,333] as $code) {
            $r = $this->runFixture(['events'=>[$this->event($code)]]);
            $this->assertSame('request_pickup', $r['row']['status']);
            $this->assertSame($code, $r['row']['instant_status_code']);
            $this->assertFalse($r['cancelable']);
        }
        $r = $this->runFixture(['events'=>[$this->event(100)]]);
        $this->assertTrue($r['cancelable']);
        $this->assertFalse($r['results'][0]['changed']);
    }

    #[Test]
    public function woo_failure_is_retryable_after_commit_and_cancel_is_shipment_only(): void {
        $r = $this->runFixture(['woo_fail'=>true, 'events'=>[$this->event(200), $this->event(200)]]);
        $this->assertSame(503, $r['results'][0]['code']);
        $this->assertFalse($r['results'][1]['changed']);
        $this->assertSame('completed', $r['woo']['status']);
        $this->assertCount(1, $r['woo']['notes']);
        $r = $this->runFixture(['events'=>[$this->event(300), $this->event(300)]]);
        $this->assertSame('processing', $r['woo']['status']);
        $this->assertCount(1, $r['woo']['notes']);
        foreach (['cancelled', 'refunded', 'completed'] as $status) {
            $r = $this->runFixture(['woo_status'=>$status,'events'=>[$this->event(200)]]);
            $this->assertSame($status, $r['woo']['status']);
            $this->assertSame([], $r['woo']['notes']);
        }
    }
    #[Test]
    public function real_service_name_is_authoritative_with_legacy_fallback(): void {
        $r = $this->runFixture(['events'=>[$this->event(106, ['service'=>'gosend', 'service_type'=>'instant'])]]);
        $this->assertSame('shipped', $r['row']['status']);
        $r = $this->runFixture(['row'=>['service_name'=>'same_day', 'service_type'=>'instant'], 'events'=>[$this->event(106, ['service_type'=>'instant'])]]);
        $this->assertSame([], $r['writes']);
        $this->assertSame(InvalidArgumentException::class, $r['results'][0]['error']);
        $r = $this->runFixture(['row'=>['service_name'=>null, 'service_type'=>'instant'], 'events'=>[$this->event(106, ['service_type'=>'instant'])]]);
        $this->assertSame('shipped', $r['row']['status']);
    }

    #[Test]
    public function direct_event_application_rejects_every_contradictory_triple(): void {
        foreach (['shipped_packages'=>[105, 200, 300], 'finished_packages'=>[105, 106, 302], 'canceled_packages'=>[105, 106, 200], 'unknown'=>[106]] as $method=>$codes) {
            foreach ($codes as $code) {
                $r = $this->runFixture(['events'=>[array_merge($this->event($code), ['event'=>$method])]]);
                $this->assertSame([], $r['writes']);
                $this->assertSame(InvalidArgumentException::class, $r['results'][0]['error']);
            }
        }
        foreach (['shipped_packages'=>[106], 'finished_packages'=>[200], 'canceled_packages'=>[300,302]] as $method=>$codes) {
            foreach ($codes as $code) {
                $r = $this->runFixture(['events'=>[array_merge($this->event($code), ['event'=>$method])]]);
                $this->assertSame($code, $r['row']['instant_status_code']);
            }
        }
    }

    #[Test]
    public function canonical_api_microseconds_are_accepted_but_invalid_dates_are_bounded(): void {
        $r = $this->runFixture(['events'=>[$this->event(106, ['date'=>'2025-01-02T10:30:00.123456Z'])]]);
        $this->assertSame('2025-01-02 10:30:00', $r['row']['shipped_at']);
        foreach (['2025-02-30T10:30:00.123456Z', '2099-01-02T10:30:00.123456Z', '1999-01-02T10:30:00.123456Z', '2025-01-02T10:30:00.123Z', '2025-01-02T10:30:00.123456Zjunk'] as $date) {
            $before = gmdate('Y-m-d H:i:s');
            $r = $this->runFixture(['events'=>[$this->event(106, ['date'=>$date])]]);
            $this->assertGreaterThanOrEqual($before, $r['row']['shipped_at']);
            $this->assertLessThanOrEqual(gmdate('Y-m-d H:i:s'), $r['row']['shipped_at']);
        }
    }

    #[Test]
    public function null_awb_is_omission_and_cancel_does_not_require_awb(): void {
        $r = $this->runFixture(['events'=>[$this->event(105, ['awb'=>null])]]);
        $this->assertSame('AWB-1', $r['row']['awb']);
        $this->assertTrue($r['cancelable']);
        foreach ([null, ''] as $awb) {
            $r = $this->runFixture(['row'=>['awb'=>$awb], 'events'=>[$this->event(105, ['awb'=>null])]]);
            $this->assertTrue($r['cancelable']);
            $this->assertSame(105, $r['row']['instant_status_code']);
            $this->assertSame('request_pickup', $r['row']['status']);
            // Only the caller of an accepted DELETE maps the operation to code 300.
            $r = $this->runFixture(['row'=>['awb'=>$awb], 'events'=>[$this->event(300, ['awb'=>null])]]);
            $this->assertSame('canceled', $r['row']['status']);
        }
        $r = $this->runFixture(['row'=>['awb'=>'bad awb'], 'events'=>[]]);
        $this->assertFalse($r['cancelable']);
    }

    #[Test]
    public function failed_cancel_note_retries_on_duplicate_and_dead_orders_fail_closed(): void {
        $r = $this->runFixture(['note_fail'=>true, 'events'=>[$this->event(300), $this->event(300), $this->event(300)]]);
        $this->assertSame(503, $r['results'][0]['code']);
        $this->assertFalse($r['results'][1]['changed']);
        $this->assertCount(1, $r['woo']['notes']);
        $this->assertSame('canceled', $r['woo']['meta']['_kiriof_instant_lifecycle_status']);
        foreach ([['missing_order'=>true], ['row'=>['wp_wc_order_stat_order_id'=>null]], ['row'=>['wp_wc_order_stat_order_id'=>0]], ['row'=>['wp_wc_order_stat_order_id'=>'invalid']]] as $input) {
            $r = $this->runFixture($input);
            $this->assertSame(503, $r['results'][0]['code']);
            $this->assertSame([], $r['writes']);
        }
    }

    #[Test]
    public function terminal_stale_metadata_is_guarded_and_never_overwrites_tracking_or_payment(): void {
        $r = $this->runFixture(['row'=>['status'=>'finished', 'instant_status_code'=>200, 'live_tracking_url'=>'https://example.com/final'], 'events'=>[$this->event(105, ['awb'=>null, 'live_tracking_url'=>'https://example.com/stale'], ['id'=>'PAY-1','status_code'=>9])]]);
        $this->assertSame([], $r['writes']);
        $this->assertSame('https://example.com/final', $r['row']['live_tracking_url']);
        $this->assertSame('paid', $r['row']['instant_payment_status']);
        $r = $this->runFixture(['row'=>['status'=>'finished', 'instant_status_code'=>200, 'awb'=>null], 'events'=>[$this->event(105, ['awb'=>'AWB-NEW', 'live_tracking_url'=>'https://example.com/fill'])]]);
        $this->assertSame(200, $r['row']['instant_status_code']);
        foreach (['awb', 'live_tracking_url', 'instant_payment_id', 'instant_payment_status'] as $field) {
            $this->assertArrayHasKey($field, $r['writes'][0]['condition']);
        }
    }

    #[Test]
    public function documented_webhook_shape_runs_through_real_writer(): void {
        $body = ['method'=>'shipped_packages', 'data'=>[['order_id'=>'KA-1', 'shipped_at'=>'2025-01-02T10:30:00.123456Z', 'awb'=>null]], 'packages'=>[['order_id'=>'KA-1', 'service'=>'gosend', 'service_type'=>'instant', 'status'=>106, 'awb'=>null]], 'payment'=>['payment_id'=>'PAY-1','status_code'=>0]];
        $r = $this->runFixture(['events'=>[], 'webhook'=>$body]);
        $this->assertSame(200, $r['webhook']['http_status']);
        $this->assertSame('2025-01-02 10:30:00', $r['row']['shipped_at']);
        $this->assertSame('AWB-1', $r['row']['awb']);
        $r = $this->runFixture(['events'=>[], 'missing_order'=>true, 'webhook'=>$body]);
        $this->assertSame(503, $r['webhook']['http_status']);
        $this->assertSame([], $r['writes']);
        $r = $this->runFixture(['events'=>[], 'row'=>['status'=>'new'], 'webhook'=>$body]);
        $this->assertSame(400, $r['webhook']['http_status']);
        $this->assertSame([], $r['writes']);
        $r = $this->runFixture(['events'=>[], 'row'=>['service_name'=>'same_day', 'service_type'=>'instant'], 'webhook'=>$body]);
        $this->assertSame(400, $r['webhook']['http_status']);
        $this->assertSame([], $r['writes']);
    }

    #[Test]
    public function late_paid_is_independent_and_payment_refresh_requires_existing_exact_identity(): void {
        $r = $this->runFixture(['row'=>['status'=>'finished','instant_status_code'=>200,'instant_payment_status'=>'pending'], 'events'=>[$this->event(105, [], ['id'=>'PAY-1','status_code'=>0]), ['mode'=>'payment','payment'=>['id'=>'PAY-1','status'=>'pending']], ['mode'=>'payment','payment'=>['id'=>'PAY-1','status'=>'refunded']], ['mode'=>'payment','payment'=>['id'=>'PAY-1','status_code'=>0]]]]);
        $this->assertSame('finished', $r['row']['status']);
        $this->assertSame(200, $r['row']['instant_status_code']);
        $this->assertSame(['id'=>'PAY-1','status'=>'paid'], $r['results'][1]);
        $this->assertSame('refunded', $r['results'][3]['status']);
        foreach ([null, 'OTHER'] as $id) {
            $r = $this->runFixture(['row'=>['instant_payment_id'=>$id], 'events'=>[['mode'=>'payment','payment'=>['id'=>'PAY-1','status'=>0]]]]);
            $this->assertSame([], $r['writes']);
            $this->assertSame(InvalidArgumentException::class, $r['results'][0]['error']);
        }
        $r = $this->runFixture(['row'=>['status'=>'finished','instant_status_code'=>200,'instant_payment_id'=>null], 'events'=>[$this->event(105, [], ['id'=>'PAY-1','status'=>0])]]);
        $this->assertSame([], $r['writes']);
        $r = $this->runFixture(['row'=>['instant_payment_status'=>'pending'], 'events'=>[['mode'=>'payment','payment'=>['id'=>'PAY-1','status_code'=>2]]]]);
        $this->assertSame('pending', $r['results'][0]['status']);
    }

    #[Test]
    public function tracking_metadata_is_not_lifecycle_evidence_and_cancel_requested_is_absorbing_for_pickup(): void {
        $r = $this->runFixture(['row'=>['status'=>'pending','instant_status_code'=>null,'awb'=>null], 'missing_order'=>true, 'events'=>[['mode'=>'metadata','package'=>['order_id'=>'KA-1','awb'=>'AWB-2','status'=>300,'tracking_url'=>'https://example.com/live']]]]);
        $this->assertSame('pending', $r['row']['status']);
        $this->assertNull($r['row']['instant_status_code']);
        $this->assertSame('AWB-2', $r['row']['awb']);
        $this->assertSame([], $r['woo']['notes']);
        foreach ([100,101,105,110] as $code) {
            $r = $this->runFixture(['row'=>['instant_status_code'=>350], 'events'=>[$this->event($code, ['live_tracking_url'=>'https://example.com/live'])]]);
            $this->assertSame(350, $r['row']['instant_status_code']);
            $this->assertFalse($r['cancelable']);
        }
    }

    #[Test]
    public function late_booking_confirms_snapshots_and_payment_without_regressing_callback_lifecycle(): void {
        $metadata = ['shipping_info'=>'{"instant_items":[{"name":"Item"}]}', 'shipment_location_snapshot'=>'{"name":"Origin","timezone":"Asia/Jakarta"}', 'vehicle'=>'motor', 'shipping_cost'=>15000, 'instant_payment_method'=>'balance', 'request_pickup_at'=>'2025-01-02 10:30:00'];
        $booking = ['mode'=>'booking', 'package'=>['order_id'=>'KA-1','status'=>105,'service'=>'gosend','service_type'=>'instant','awb'=>'AWB-1'], 'payment'=>['id'=>'PAY-1','status'=>'pending'], 'metadata'=>$metadata];
        foreach (['pending','paid','refunded'] as $paymentStatus) {
            $r = $this->runFixture(['row'=>['status'=>'pending','instant_status_code'=>null,'instant_payment_id'=>null,'instant_payment_status'=>$paymentStatus], 'events'=>[$this->event(106), $this->event(200), $booking, $booking]]);
            $this->assertSame('finished', $r['row']['status']);
            $this->assertSame(200, $r['row']['instant_status_code']);
            $this->assertSame('PAY-1', $r['row']['instant_payment_id']);
            $this->assertSame($paymentStatus, $r['row']['instant_payment_status']);
            foreach ($metadata as $field=>$value) { $this->assertSame($value, $r['row'][$field]); }
            $this->assertFalse($r['results'][3]['changed']);
            $this->assertCount(1, $r['woo']['notes']);
        }
        $booking['payment']['status'] = 'paid';
        $r = $this->runFixture(['row'=>['status'=>'finished','instant_status_code'=>200,'instant_payment_id'=>null,'instant_payment_status'=>'pending'], 'events'=>[$booking]]);
        $this->assertSame('paid', $r['row']['instant_payment_status']);
        $this->assertSame('completed', $r['woo']['status']);
        $r = $this->runFixture(['race'=>['status'=>'finished','instant_status_code'=>200,'instant_payment_status'=>'refunded'], 'events'=>[$booking]]);
        $this->assertSame(2, $r['reads']);
        $this->assertSame('finished', $r['row']['status']);
        $this->assertSame('refunded', $r['row']['instant_payment_status']);
    }

    #[Test]
    public function late_booking_preserves_callback_issues_but_still_attaches_paid_metadata(): void {
        $metadata = ['shipping_info'=>'{"instant_items":[{"name":"Item"}]}', 'shipment_location_snapshot'=>'{"name":"Origin","timezone":"Asia/Jakarta"}', 'vehicle'=>'motor', 'shipping_cost'=>15000, 'instant_payment_method'=>'balance', 'request_pickup_at'=>'2025-01-02 10:30:00'];
        $booking = ['mode'=>'booking', 'package'=>['order_id'=>'KA-1','status'=>105,'service'=>'gosend','service_type'=>'instant','awb'=>'AWB-1','tracking_url'=>'https://example.com/booking'], 'payment'=>['id'=>'PAY-1','status'=>'paid'], 'metadata'=>$metadata];
        foreach (['pending'=>'paid', 'paid'=>'paid', 'refunded'=>'refunded'] as $previousPayment=>$expectedPayment) {
            $row = ['status'=>'pending','instant_status_code'=>500,'rejected_reason'=>'Callback reported a shipment problem.', 'instant_payment_id'=>null,'instant_payment_status'=>$previousPayment,'awb'=>null];
            $r = $this->runFixture(['row'=>$row, 'events'=>[$booking, $booking]]);
            $this->assertSame('pending', $r['row']['status']);
            $this->assertSame(500, $r['row']['instant_status_code']);
            $this->assertSame($row['rejected_reason'], $r['row']['rejected_reason']);
            $this->assertSame('PAY-1', $r['row']['instant_payment_id']);
            $this->assertSame($expectedPayment, $r['row']['instant_payment_status']);
            $this->assertSame('AWB-1', $r['row']['awb']);
            $this->assertSame('https://example.com/booking', $r['row']['live_tracking_url']);
            foreach ($metadata as $field=>$value) { $this->assertSame($value, $r['row'][$field]); }
            $this->assertTrue($r['results'][0]['changed']);
            $this->assertFalse($r['results'][1]['changed']);
            $this->assertFalse($r['cancelable']);
            $this->assertSame([], $r['woo']['notes']);
            $this->assertCount(1, $r['writes']);
            foreach ($row as $field=>$value) { $this->assertSame($value, $r['writes'][0]['condition'][$field]); }
            foreach (array_keys($metadata) as $field) { $this->assertNull($r['writes'][0]['condition'][$field]); }
            foreach (['status','instant_status_code','rejected_reason'] as $field) { $this->assertArrayNotHasKey($field, $r['writes'][0]['changes']); }
        }
    }

    #[Test]
    public function booking_issue_precedence_is_scoped_and_recomputed_after_cas_conflicts(): void {
        foreach ([405,500,555,701,702,703,704,303,301,333] as $issue) {
            foreach ([100,101,105,110] as $code) {
                $r = $this->runFixture(['row'=>['status'=>'pending','instant_status_code'=>(string) $issue,'rejected_reason'=>'Issue'], 'events'=>[array_merge($this->event($code), ['mode'=>'booking'])]]);
                $this->assertSame((string) $issue, $r['row']['instant_status_code']);
                $this->assertSame('pending', $r['row']['status']);
                $this->assertSame('Issue', $r['row']['rejected_reason']);
                $this->assertSame([], $r['writes']);
            }
            // Explicit authenticated tracking may recover even to a pre-pickup state.
            $r = $this->runFixture(['row'=>['status'=>'pending','instant_status_code'=>$issue,'rejected_reason'=>'Issue'], 'events'=>[$this->event(105)]]);
            $this->assertSame(105, $r['row']['instant_status_code']);
            $this->assertNull($r['row']['rejected_reason']);
            foreach ([106=>'shipped', 200=>'finished'] as $code=>$status) {
                $r = $this->runFixture(['row'=>['status'=>'pending','instant_status_code'=>$issue,'rejected_reason'=>'Issue'], 'events'=>[array_merge($this->event($code), ['event'=>106 === $code ? 'shipped_packages' : 'finished_packages'])]]);
                $this->assertSame($code, $r['row']['instant_status_code']);
                $this->assertSame($status, $r['row']['status']);
                $this->assertNull($r['row']['rejected_reason']);
            }
        }
        $r = $this->runFixture(['race'=>['status'=>'pending','instant_status_code'=>500,'rejected_reason'=>'Concurrent issue','instant_payment_status'=>'refunded'], 'events'=>[array_merge($this->event(105, [], ['id'=>'PAY-1','status'=>'paid']), ['mode'=>'booking','metadata'=>['vehicle'=>'motor']])]]);
        $this->assertSame(2, $r['reads']);
        $this->assertSame('pending', $r['row']['status']);
        $this->assertSame(500, $r['row']['instant_status_code']);
        $this->assertSame('Concurrent issue', $r['row']['rejected_reason']);
        $this->assertSame('refunded', $r['row']['instant_payment_status']);
        $this->assertSame('motor', $r['row']['vehicle']);
        $this->assertSame('Concurrent issue', $r['writes'][1]['condition']['rejected_reason']);
    }

    #[Test]
    public function booking_rejects_untrusted_fields_ambiguous_codes_and_changed_snapshots(): void {
        $base = ['mode'=>'booking','package'=>['order_id'=>'KA-1','status'=>105], 'payment'=>['id'=>'PAY-1','status'=>'paid'], 'metadata'=>[]];
        foreach ([['service'=>'grab_express'], ['service_type'=>'same_day'], ['service_name'=>'same_day'], ['awb'=>'OTHER'], ['live_tracking_url'=>'https://user:secret@example.com/'], ['status'=>0]] as $extra) {
            $event = $base; $event['package'] = array_replace($event['package'], $extra);
            $r = $this->runFixture(['events'=>[$event]]);
            $this->assertSame([], $r['writes']);
            $this->assertSame(InvalidArgumentException::class, $r['results'][0]['error']);
        }
        foreach (['null', '123', '"text"', '[]'] as $json) {
            $event = $base; $event['metadata']['shipping_info'] = $json;
            $r = $this->runFixture(['events'=>[$event]]);
            $this->assertSame([], $r['writes']);
        }
        $event = $base; $event['metadata'] = ['shipping_info'=>'{"instant_items":[]}'];
        $r = $this->runFixture(['row'=>['shipping_info'=>'{"instant_items":[1]}'], 'events'=>[$event]]);
        $this->assertSame([], $r['writes']);
        $event['metadata'] = ['shipment_location_snapshot'=>'{"name":"Other"}'];
        $r = $this->runFixture(['row'=>['shipment_location_snapshot'=>'{"name":"Origin"}'], 'events'=>[$event]]);
        $this->assertSame([], $r['writes']);
    }

}
