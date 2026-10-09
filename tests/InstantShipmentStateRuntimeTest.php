<?php

declare(strict_types=1);

// Self-contained subprocess fixture: no WordPress/WooCommerce or real SQL is needed.
if (in_array('--fixture', $argv ?? [], true)) {
    define('ABSPATH', __DIR__);
    function esc_html__($text, $domain) { return $text; }
    function wp_parse_url($url) { return parse_url($url); }
    function esc_url_raw($url, $protocols = []) { return $url; }
    function wp_json_encode($value) { return json_encode($value); }
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
    require __DIR__ . '/../inc/Services/InstantTrackingPresentation.php';
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
    #[Test]
    public function official_instant_webhook_examples_persist_expected_lifecycle_without_raw_payloads(): void {
        $examples=json_decode(file_get_contents(__DIR__.'/fixtures/instant-webhook-official-examples.json'),true,512,JSON_THROW_ON_ERROR);
        $expected=array('processed_shipment'=>array(105,'request_pickup'),'shipped'=>array(106,'shipped'),'canceled'=>array(300,'canceled'),'finished'=>array(200,'finished'));
        foreach($examples as $name=>$body) {
            $r=$this->runFixture(array('events'=>array(),'row'=>array('awb'=>null,'instant_status_code'=>110,'instant_payment_status'=>'unpaid'),'webhook'=>$body));
            $this->assertSame(
                [
                    'webhook.http_status' => 200,
                    'row.instant_status_code' => $expected[$name][0],
                    'row.status' => $expected[$name][1],
                    'row.instant_payment_status' => 'paid',
                    'row.awb' => 'AWB-1',
                    'woo.status' => 'finished'===$name?'completed':'processing',
                    'redaction: json_encode(r.row), \'Fixture street\'' => 0,
                    'redaction: json_encode(r.row), \'some-random-qr-string\'' => 0,
                ],
                [
                    'webhook.http_status' => $r['webhook']['http_status'],
                    'row.instant_status_code' => $r['row']['instant_status_code'],
                    'row.status' => $r['row']['status'],
                    'row.instant_payment_status' => $r['row']['instant_payment_status'],
                    'row.awb' => $r['row']['awb'],
                    'woo.status' => $r['woo']['status'],
                    'redaction: json_encode(r.row), \'Fixture street\'' => substr_count(json_encode($r['row']), 'Fixture street'),
                    'redaction: json_encode(r.row), \'some-random-qr-string\'' => substr_count(json_encode($r['row']), 'some-random-qr-string'),
                ],
                $name
            );
            if('shipped'===$name) $this->assertSame('2025-08-05 07:00:05',$r['row']['shipped_at']);
            if('finished'===$name) $this->assertSame('2025-08-05 07:00:43',$r['row']['finished_at']);
            if('processed_shipment'===$name) {
                $snapshot=json_decode($r['row']['shipping_info'],true);
                $this->assertSame(
                    [
                        'count(snapshot.instant_route_points)' => 3,
                        'absent fields: snapshot, .poly_line\' => true' => [],
                    ],
                    [
                        'count(snapshot.instant_route_points)' => count($snapshot['instant_route_points']),
                        'absent fields: snapshot, .poly_line\' => true' => array_intersect_key($snapshot, ['poly_line' => true]),
                    ],
                    __FUNCTION__
                );
            }
        }
    }

    #[Test]
    public function all_documented_methods_accept_minimal_and_nullable_payment_without_inventing_payment(): void {
        foreach(array('processed_packages'=>105,'shipped_packages'=>106,'canceled_packages'=>300,'finished_packages'=>200) as $method=>$code) {
            foreach(array(false,true) as $null) {
                $body=array('method'=>$method,'data'=>array(array('order_id'=>'KA-1','date'=>'2025-08-05T07:00:05.123456Z')));
                if($null) $body['payment']=null;
                $r=$this->runFixture(array('events'=>array(),'row'=>array('instant_status_code'=>110,'instant_payment_status'=>'unpaid'),'webhook'=>$body));
                $this->assertSame(
                    [
                        'webhook.http_status' => 200,
                        'row.instant_status_code' => $code,
                        'row.instant_payment_status' => 'unpaid',
                    ],
                    [
                        'webhook.http_status' => $r['webhook']['http_status'],
                        'row.instant_status_code' => $r['row']['instant_status_code'],
                        'row.instant_payment_status' => $r['row']['instant_payment_status'],
                    ],
                    __FUNCTION__
                );
            }
        }
        $body=array('method'=>'processed_packages','data'=>array(array('order_id'=>'KA-1')),'payment'=>array('payment_id'=>'PAY-1','status_code'=>'9'));
        $r=$this->runFixture(array('events'=>array(),'row'=>array('instant_payment_status'=>'unpaid'),'webhook'=>$body));
        $this->assertSame(
            [
                'webhook.http_status' => 200,
                'row.instant_payment_status' => 'unpaid',
            ],
            [
                'webhook.http_status' => $r['webhook']['http_status'],
                'row.instant_payment_status' => $r['row']['instant_payment_status'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function webhook_route_updates_are_bounded_stale_safe_and_compare_and_swap_preserves_local_snapshot(): void {
        $event=$this->event(106,array('poly_line'=>'_p~iF~ps|U_ulLnnqC_mqNvxq`@'));
        $event['event']='shipped_packages';
        $r=$this->runFixture(array('row'=>array('shipping_info'=>'{"local":"preserved"}'),'events'=>array($event,$event)));
        $snapshot=json_decode($r['row']['shipping_info'],true);
        $this->assertSame(
            [
                'local' => 'preserved',
                'count(snapshot.instant_route_points)' => 3,
                'results.1.changed' => false,
            ],
            [
                'local' => $snapshot['local'],
                'count(snapshot.instant_route_points)' => count($snapshot['instant_route_points']),
                'results.1.changed' => $r['results'][1]['changed'],
            ],
            __FUNCTION__
        );
        $stale=$event; $stale['package']['status']=105; $stale['package']['poly_line']='~pfn@c|t`TZw@z@`@'; $stale['event']='processed_packages';
        $r=$this->runFixture(array('row'=>array('shipping_info'=>'{"local":"preserved"}'),'events'=>array($event,$stale)));
        $this->assertSame($snapshot['instant_route_points'],json_decode($r['row']['shipping_info'],true)['instant_route_points']);
        $r=$this->runFixture(array('row'=>array('shipping_info'=>'{"local":"before"}'),'race'=>array('shipping_info'=>'{"local":"raced"}'),'events'=>array($event)));
        $this->assertSame('raced',json_decode($r['row']['shipping_info'],true)['local']);
        foreach(array(null,'bad',str_repeat('?',100001)) as $route) {
            $event['package']['poly_line']=$route;
            $r=$this->runFixture(array('row'=>array('shipping_info'=>'{"local":"preserved"}'),'events'=>array($event)));
            $this->assertSame(
                [
                    'row.status' => 'shipped',
                    'row.shipping_info' => '{"local":"preserved"}',
                ],
                [
                    'row.status' => $r['row']['status'],
                    'row.shipping_info' => $r['row']['shipping_info'],
                ],
                __FUNCTION__
            );
        }
    }
    #[Test]
    public function processed_webhook_confirms_ready_and_paid_without_completing_order_and_replays_monotonically(): void {
        $package = array('order_id'=>'KA-1','service'=>'gosend','service_type'=>'Instant','status'=>105,'awb'=>'AWB-1','live_tracking_url'=>null);
        $payment = array('payment_id'=>'PAY-1','status_code'=>0,'amount'=>12000,'pay_time'=>'2026-10-05T03:12:23.446201Z');
        $body = array('method'=>'processed_packages','data'=>array(array('order_id'=>'KA-1','awb'=>'AWB-1','date'=>'2026-10-05T03:12:22Z')),'payment'=>$payment,'packages'=>array($package));
        $r = $this->runFixture(array('row'=>array('awb'=>null,'instant_status_code'=>110,'instant_payment_status'=>'unpaid'),'events'=>array(),'webhook'=>$body));
        $this->assertSame(
            [
                'webhook.http_status' => 200,
                'row.status' => 'request_pickup',
                'row.instant_status_code' => 105,
                'row.instant_payment_status' => 'paid',
                'row.awb' => 'AWB-1',
                'woo.status' => 'processing',
            ],
            [
                'webhook.http_status' => $r['webhook']['http_status'],
                'row.status' => $r['row']['status'],
                'row.instant_status_code' => $r['row']['instant_status_code'],
                'row.instant_payment_status' => $r['row']['instant_payment_status'],
                'row.awb' => $r['row']['awb'],
                'woo.status' => $r['woo']['status'],
            ],
            __FUNCTION__
        );
        $event = array('package'=>$package,'payment'=>$payment,'event'=>'processed_packages');
        $r = $this->runFixture(array('row'=>array('instant_status_code'=>110,'instant_payment_status'=>'unpaid'),'events'=>array($event,$event)));
        $this->assertFalse($r['results'][1]['changed']);
        $r = $this->runFixture(array('row'=>array('status'=>'shipped','instant_status_code'=>106),'events'=>array($event)));
        $this->assertSame(
            [
                'row.status' => 'shipped',
                'row.instant_status_code' => 106,
            ],
            [
                'row.status' => $r['row']['status'],
                'row.instant_status_code' => $r['row']['instant_status_code'],
            ],
            __FUNCTION__
        );
    }
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
        $this->assertSame(
            [
                'row.status' => 'finished',
                'row.instant_status_code' => 200,
                'results.1.changed' => false,
                'results.3.changed' => false,
                'results.4.changed' => false,
                'woo.status' => 'completed',
                'count(r.woo.notes)' => 1,
            ],
            [
                'row.status' => $r['row']['status'],
                'row.instant_status_code' => $r['row']['instant_status_code'],
                'results.1.changed' => $r['results'][1]['changed'],
                'results.3.changed' => $r['results'][3]['changed'],
                'results.4.changed' => $r['results'][4]['changed'],
                'woo.status' => $r['woo']['status'],
                'count(r.woo.notes)' => count($r['woo']['notes']),
            ],
            __FUNCTION__
        );
        foreach ([[300,200], [200,300], [400,200], [200,400]] as $codes) {
            $r = $this->runFixture(['events'=>[$this->event($codes[0]), $this->event($codes[1])]]);
            $this->assertSame($codes[0], $r['row']['instant_status_code']);
        }
    }

    #[Test]
    public function invalid_identity_and_unknown_codes_never_write(): void {
        foreach ([['order_id'=>1], ['order_id'=>'KA-2'], ['awb'=>'OTHER'], ['awb'=>'<script>'], ['service'=>'borzo'], ['service_type'=>'same_day'], ['status'=>0], ['status'=>true], ['status'=>999], ['status'=>null]] as $extra) {
            $r = $this->runFixture(['events'=>[$this->event(106, $extra)]]);
            $this->assertSame(
                [
                    'writes' => [],
                    'results.0.error' => InvalidArgumentException::class,
                ],
                [
                    'writes' => $r['writes'],
                    'results.0.error' => $r['results'][0]['error'],
                ],
                json_encode($extra)
            );
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
        $this->assertSame(
            [
                'reads' => 2,
                'row.status' => 'finished',
                'results.0.changed' => false,
            ],
            [
                'reads' => $r['reads'],
                'row.status' => $r['row']['status'],
                'results.0.changed' => $r['results'][0]['changed'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['row'=>['status'=>'pending','instant_status_code'=>null, 'instant_payment_id'=>null], 'events'=>[$this->event(100, [], ['id'=>'PAY-2','status_code'=>0])]]);
        $this->assertSame(
            [
                'writes.0.condition.instant_status_code' => null,
                'row.status' => 'request_pickup',
                'row.instant_payment_id' => 'PAY-2',
            ],
            [
                'writes.0.condition.instant_status_code' => $r['writes'][0]['condition']['instant_status_code'],
                'row.status' => $r['row']['status'],
                'row.instant_payment_id' => $r['row']['instant_payment_id'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['fail'=>true]);
        $this->assertSame(
            [
                'count(r.writes)' => 3,
                'results.0.code' => 503,
                'woo.notes' => [],
            ],
            [
                'count(r.writes)' => count($r['writes']),
                'results.0.code' => $r['results'][0]['code'],
                'woo.notes' => $r['woo']['notes'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function safe_metadata_and_payment_do_not_regress_on_stale_events(): void {
        $r = $this->runFixture(['events'=>[$this->event(106, ['live_tracking_url'=>'https://example.com/first']), $this->event(100, ['live_tracking_url'=>'https://example.com/stale'], ['id'=>'PAY-1','status_code'=>9])]]);
        $this->assertSame(
            [
                'row.live_tracking_url' => 'https://example.com/first',
                'row.instant_payment_status' => 'paid',
            ],
            [
                'row.live_tracking_url' => $r['row']['live_tracking_url'],
                'row.instant_payment_status' => $r['row']['instant_payment_status'],
            ],
            __FUNCTION__
        );
        foreach (['javascript:alert(1)', 'https://user:pass@example.com/', "https://example.com/\nsecret", 'https://example.com\\evil'] as $url) {
            $r = $this->runFixture(['events'=>[$this->event(106, ['live_tracking_url'=>$url,'pin'=>'123456','qr_content'=>'secret'])]]);
            $this->assertSame(
                [
                    'row.live_tracking_url' => '',
                    'absent fields: r.row, .pin\' => true' => [],
                    'absent fields: r.row, .qr_content\' => true' => [],
                ],
                [
                    'row.live_tracking_url' => $r['row']['live_tracking_url'],
                    'absent fields: r.row, .pin\' => true' => array_intersect_key($r['row'], ['pin' => true]),
                    'absent fields: r.row, .qr_content\' => true' => array_intersect_key($r['row'], ['qr_content' => true]),
                ],
                __FUNCTION__
            );
        }
        $r = $this->runFixture(['events'=>[$this->event(100, [], ['id'=>'PAY-1','status'=>'refunded']), $this->event(100, [], ['id'=>'PAY-1','status_code'=>0])]]);
        $this->assertSame('refunded', $r['row']['instant_payment_status']);
        $r = $this->runFixture(['row'=>['status'=>'shipped','instant_status_code'=>106], 'events'=>[$this->event(100, ['tracking_url'=>'https://example.com/fill'])]]);
        $this->assertSame(
            [
                'row.live_tracking_url' => 'https://example.com/fill',
                'row.instant_status_code' => 106,
            ],
            [
                'row.live_tracking_url' => $r['row']['live_tracking_url'],
                'row.instant_status_code' => $r['row']['instant_status_code'],
            ],
            __FUNCTION__
        );
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
            $this->assertSame(
                [
                    'row.status' => 'request_pickup',
                    'row.instant_status_code' => $code,
                    'cancelable' => false,
                ],
                [
                    'row.status' => $r['row']['status'],
                    'row.instant_status_code' => $r['row']['instant_status_code'],
                    'cancelable' => $r['cancelable'],
                ],
                __FUNCTION__
            );
        }
        $r = $this->runFixture(['events'=>[$this->event(100)]]);
        $this->assertSame(
            [
                'cancelable' => true,
                'results.0.changed' => false,
            ],
            [
                'cancelable' => $r['cancelable'],
                'results.0.changed' => $r['results'][0]['changed'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function woo_failure_is_retryable_after_commit_and_cancel_is_shipment_only(): void {
        $r = $this->runFixture(['woo_fail'=>true, 'events'=>[$this->event(200), $this->event(200)]]);
        $this->assertSame(
            [
                'results.0.code' => 503,
                'results.1.changed' => false,
                'woo.status' => 'completed',
                'count(r.woo.notes)' => 1,
            ],
            [
                'results.0.code' => $r['results'][0]['code'],
                'results.1.changed' => $r['results'][1]['changed'],
                'woo.status' => $r['woo']['status'],
                'count(r.woo.notes)' => count($r['woo']['notes']),
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['events'=>[$this->event(300), $this->event(300)]]);
        $this->assertSame(
            [
                'woo.status' => 'processing',
                'count(r.woo.notes)' => 1,
            ],
            [
                'woo.status' => $r['woo']['status'],
                'count(r.woo.notes)' => count($r['woo']['notes']),
            ],
            __FUNCTION__
        );
        foreach (['cancelled', 'refunded', 'completed'] as $status) {
            $r = $this->runFixture(['woo_status'=>$status,'events'=>[$this->event(200)]]);
            $this->assertSame(
                [
                    'woo.status' => $status,
                    'woo.notes' => [],
                ],
                [
                    'woo.status' => $r['woo']['status'],
                    'woo.notes' => $r['woo']['notes'],
                ],
                __FUNCTION__
            );
        }
    }
    #[Test]
    public function real_service_name_is_authoritative_with_legacy_fallback(): void {
        $r = $this->runFixture(['events'=>[$this->event(106, ['service'=>'gosend', 'service_type'=>'instant'])]]);
        $this->assertSame('shipped', $r['row']['status']);
        $r = $this->runFixture(['row'=>['service_name'=>'same_day', 'service_type'=>'instant'], 'events'=>[$this->event(106, ['service_type'=>'instant'])]]);
        $this->assertSame(
            [
                'writes' => [],
                'results.0.error' => InvalidArgumentException::class,
            ],
            [
                'writes' => $r['writes'],
                'results.0.error' => $r['results'][0]['error'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['row'=>['service_name'=>null, 'service_type'=>'instant'], 'events'=>[$this->event(106, ['service_type'=>'instant'])]]);
        $this->assertSame('shipped', $r['row']['status']);
    }

    #[Test]
    public function direct_event_application_rejects_every_contradictory_triple(): void {
        foreach (['shipped_packages'=>[105, 200, 300], 'finished_packages'=>[105, 106, 302], 'canceled_packages'=>[105, 106, 200], 'unknown'=>[106]] as $method=>$codes) {
            foreach ($codes as $code) {
                $r = $this->runFixture(['events'=>[array_merge($this->event($code), ['event'=>$method])]]);
                $this->assertSame(
                    [
                        'writes' => [],
                        'results.0.error' => InvalidArgumentException::class,
                    ],
                    [
                        'writes' => $r['writes'],
                        'results.0.error' => $r['results'][0]['error'],
                    ],
                    __FUNCTION__
                );
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
        $this->assertSame(
            [
                'row.awb' => 'AWB-1',
                'cancelable' => true,
            ],
            [
                'row.awb' => $r['row']['awb'],
                'cancelable' => $r['cancelable'],
            ],
            __FUNCTION__
        );
        foreach ([null, ''] as $awb) {
            $r = $this->runFixture(['row'=>['awb'=>$awb], 'events'=>[$this->event(105, ['awb'=>null])]]);
            $this->assertSame(
                [
                    'cancelable' => true,
                    'row.instant_status_code' => 105,
                    'row.status' => 'request_pickup',
                ],
                [
                    'cancelable' => $r['cancelable'],
                    'row.instant_status_code' => $r['row']['instant_status_code'],
                    'row.status' => $r['row']['status'],
                ],
                __FUNCTION__
            );
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
        $this->assertSame(
            [
                'results.0.code' => 503,
                'results.1.changed' => false,
                'count(r.woo.notes)' => 1,
                'woo.meta._kiriof_instant_lifecycle_status' => 'canceled',
            ],
            [
                'results.0.code' => $r['results'][0]['code'],
                'results.1.changed' => $r['results'][1]['changed'],
                'count(r.woo.notes)' => count($r['woo']['notes']),
                'woo.meta._kiriof_instant_lifecycle_status' => $r['woo']['meta']['_kiriof_instant_lifecycle_status'],
            ],
            __FUNCTION__
        );
        foreach ([['missing_order'=>true], ['row'=>['wp_wc_order_stat_order_id'=>null]], ['row'=>['wp_wc_order_stat_order_id'=>0]], ['row'=>['wp_wc_order_stat_order_id'=>'invalid']]] as $input) {
            $r = $this->runFixture($input);
            $this->assertSame(
                [
                    'results.0.code' => 503,
                    'writes' => [],
                ],
                [
                    'results.0.code' => $r['results'][0]['code'],
                    'writes' => $r['writes'],
                ],
                __FUNCTION__
            );
        }
    }

    #[Test]
    public function terminal_stale_metadata_is_guarded_and_never_overwrites_tracking_or_payment(): void {
        $r = $this->runFixture(['row'=>['status'=>'finished', 'instant_status_code'=>200, 'live_tracking_url'=>'https://example.com/final'], 'events'=>[$this->event(105, ['awb'=>null, 'live_tracking_url'=>'https://example.com/stale'], ['id'=>'PAY-1','status_code'=>9])]]);
        $this->assertSame(
            [
                'writes' => [],
                'row.live_tracking_url' => 'https://example.com/final',
                'row.instant_payment_status' => 'paid',
            ],
            [
                'writes' => $r['writes'],
                'row.live_tracking_url' => $r['row']['live_tracking_url'],
                'row.instant_payment_status' => $r['row']['instant_payment_status'],
            ],
            __FUNCTION__
        );
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
        $this->assertSame(
            [
                'webhook.http_status' => 200,
                'row.shipped_at' => '2025-01-02 10:30:00',
                'row.awb' => 'AWB-1',
            ],
            [
                'webhook.http_status' => $r['webhook']['http_status'],
                'row.shipped_at' => $r['row']['shipped_at'],
                'row.awb' => $r['row']['awb'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['events'=>[], 'missing_order'=>true, 'webhook'=>$body]);
        $this->assertSame(
            [
                'webhook.http_status' => 503,
                'writes' => [],
            ],
            [
                'webhook.http_status' => $r['webhook']['http_status'],
                'writes' => $r['writes'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['events'=>[], 'row'=>['status'=>'new'], 'webhook'=>$body]);
        $this->assertSame(
            [
                'webhook.http_status' => 400,
                'writes' => [],
            ],
            [
                'webhook.http_status' => $r['webhook']['http_status'],
                'writes' => $r['writes'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['events'=>[], 'row'=>['service_name'=>'same_day', 'service_type'=>'instant'], 'webhook'=>$body]);
        $this->assertSame(
            [
                'webhook.http_status' => 400,
                'writes' => [],
            ],
            [
                'webhook.http_status' => $r['webhook']['http_status'],
                'writes' => $r['writes'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function late_paid_is_independent_and_payment_refresh_requires_existing_exact_identity(): void {
        $r = $this->runFixture(['row'=>['status'=>'finished','instant_status_code'=>200,'instant_payment_status'=>'pending'], 'events'=>[$this->event(105, [], ['id'=>'PAY-1','status_code'=>0]), ['mode'=>'payment','payment'=>['id'=>'PAY-1','status'=>'pending']], ['mode'=>'payment','payment'=>['id'=>'PAY-1','status'=>'refunded']], ['mode'=>'payment','payment'=>['id'=>'PAY-1','status_code'=>0]]]]);
        $this->assertSame(
            [
                'row.status' => 'finished',
                'row.instant_status_code' => 200,
                'results.1' => ['id'=>'PAY-1','status'=>'paid'],
                'results.3.status' => 'refunded',
            ],
            [
                'row.status' => $r['row']['status'],
                'row.instant_status_code' => $r['row']['instant_status_code'],
                'results.1' => $r['results'][1],
                'results.3.status' => $r['results'][3]['status'],
            ],
            __FUNCTION__
        );
        foreach ([null, 'OTHER'] as $id) {
            $r = $this->runFixture(['row'=>['instant_payment_id'=>$id], 'events'=>[['mode'=>'payment','payment'=>['id'=>'PAY-1','status'=>0]]]]);
            $this->assertSame(
                [
                    'writes' => [],
                    'results.0.error' => InvalidArgumentException::class,
                ],
                [
                    'writes' => $r['writes'],
                    'results.0.error' => $r['results'][0]['error'],
                ],
                __FUNCTION__
            );
        }
        $r = $this->runFixture(['row'=>['status'=>'finished','instant_status_code'=>200,'instant_payment_id'=>null], 'events'=>[$this->event(105, [], ['id'=>'PAY-1','status'=>0])]]);
        $this->assertSame([], $r['writes']);
        $r = $this->runFixture(['row'=>['instant_payment_status'=>'pending'], 'events'=>[['mode'=>'payment','payment'=>['id'=>'PAY-1','status_code'=>2]]]]);
        $this->assertSame('pending', $r['results'][0]['status']);
    }

    #[Test]
    public function tracking_metadata_is_not_lifecycle_evidence_and_cancel_requested_is_absorbing_for_pickup(): void {
        $r = $this->runFixture(['row'=>['status'=>'pending','instant_status_code'=>null,'awb'=>null], 'missing_order'=>true, 'events'=>[['mode'=>'metadata','package'=>['order_id'=>'KA-1','awb'=>'AWB-2','status'=>300,'tracking_url'=>'https://example.com/live']]]]);
        $this->assertSame(
            [
                'row.status' => 'pending',
                'row.instant_status_code' => null,
                'row.awb' => 'AWB-2',
                'woo.notes' => [],
            ],
            [
                'row.status' => $r['row']['status'],
                'row.instant_status_code' => $r['row']['instant_status_code'],
                'row.awb' => $r['row']['awb'],
                'woo.notes' => $r['woo']['notes'],
            ],
            __FUNCTION__
        );
        foreach ([100,101,105,110] as $code) {
            $r = $this->runFixture(['row'=>['instant_status_code'=>350], 'events'=>[$this->event($code, ['live_tracking_url'=>'https://example.com/live'])]]);
            $this->assertSame(
                [
                    'row.instant_status_code' => 350,
                    'cancelable' => false,
                ],
                [
                    'row.instant_status_code' => $r['row']['instant_status_code'],
                    'cancelable' => $r['cancelable'],
                ],
                __FUNCTION__
            );
        }
    }

    #[Test]
    public function late_booking_confirms_snapshots_and_payment_without_regressing_callback_lifecycle(): void {
        $metadata = ['shipping_info'=>'{"instant_items":[{"name":"Item"}]}', 'shipment_location_snapshot'=>'{"name":"Origin","timezone":"Asia/Jakarta"}', 'vehicle'=>'motor', 'shipping_cost'=>15000, 'instant_payment_method'=>'balance', 'request_pickup_at'=>'2025-01-02 10:30:00'];
        $booking = ['mode'=>'booking', 'package'=>['order_id'=>'KA-1','status'=>105,'service'=>'gosend','service_type'=>'instant','awb'=>'AWB-1'], 'payment'=>['id'=>'PAY-1','status'=>'pending'], 'metadata'=>$metadata];
        foreach (['pending','paid','refunded'] as $paymentStatus) {
            $r = $this->runFixture(['row'=>['status'=>'pending','instant_status_code'=>null,'instant_payment_id'=>null,'instant_payment_status'=>$paymentStatus], 'events'=>[$this->event(106), $this->event(200), $booking, $booking]]);
            $this->assertSame(
                [
                    'row.status' => 'finished',
                    'row.instant_status_code' => 200,
                    'row.instant_payment_id' => 'PAY-1',
                    'row.instant_payment_status' => $paymentStatus,
                ],
                [
                    'row.status' => $r['row']['status'],
                    'row.instant_status_code' => $r['row']['instant_status_code'],
                    'row.instant_payment_id' => $r['row']['instant_payment_id'],
                    'row.instant_payment_status' => $r['row']['instant_payment_status'],
                ],
                __FUNCTION__
            );
            foreach ($metadata as $field=>$value) { $this->assertSame($value, $r['row'][$field]); }
            $this->assertSame(
                [
                    'results.3.changed' => false,
                    'count(r.woo.notes)' => 1,
                ],
                [
                    'results.3.changed' => $r['results'][3]['changed'],
                    'count(r.woo.notes)' => count($r['woo']['notes']),
                ],
                __FUNCTION__
            );
        }
        $booking['payment']['status'] = 'paid';
        $r = $this->runFixture(['row'=>['status'=>'finished','instant_status_code'=>200,'instant_payment_id'=>null,'instant_payment_status'=>'pending'], 'events'=>[$booking]]);
        $this->assertSame(
            [
                'row.instant_payment_status' => 'paid',
                'woo.status' => 'completed',
            ],
            [
                'row.instant_payment_status' => $r['row']['instant_payment_status'],
                'woo.status' => $r['woo']['status'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['race'=>['status'=>'finished','instant_status_code'=>200,'instant_payment_status'=>'refunded'], 'events'=>[$booking]]);
        $this->assertSame(
            [
                'reads' => 2,
                'row.status' => 'finished',
                'row.instant_payment_status' => 'refunded',
            ],
            [
                'reads' => $r['reads'],
                'row.status' => $r['row']['status'],
                'row.instant_payment_status' => $r['row']['instant_payment_status'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function late_booking_preserves_callback_issues_but_still_attaches_paid_metadata(): void {
        $expectedContracts = [];
        $actualContracts = [];

        $metadata = ['shipping_info'=>'{"instant_items":[{"name":"Item"}]}', 'shipment_location_snapshot'=>'{"name":"Origin","timezone":"Asia/Jakarta"}', 'vehicle'=>'motor', 'shipping_cost'=>15000, 'instant_payment_method'=>'balance', 'request_pickup_at'=>'2025-01-02 10:30:00'];
        $booking = ['mode'=>'booking', 'package'=>['order_id'=>'KA-1','status'=>105,'service'=>'gosend','service_type'=>'instant','awb'=>'AWB-1','tracking_url'=>'https://example.com/booking'], 'payment'=>['id'=>'PAY-1','status'=>'paid'], 'metadata'=>$metadata];
        foreach (['pending'=>'paid', 'paid'=>'paid', 'refunded'=>'refunded'] as $previousPayment=>$expectedPayment) {
            $row = ['status'=>'pending','instant_status_code'=>500,'rejected_reason'=>'Callback reported a shipment problem.', 'instant_payment_id'=>null,'instant_payment_status'=>$previousPayment,'awb'=>null];
            $r = $this->runFixture(['row'=>$row, 'events'=>[$booking, $booking]]);
            $case = (__FUNCTION__) . ' #' . count($expectedContracts);
            $expectedContracts[$case] = [
                    'row.status' => 'pending',
                    'row.instant_status_code' => 500,
                    'row.rejected_reason' => $row['rejected_reason'],
                    'row.instant_payment_id' => 'PAY-1',
                    'row.instant_payment_status' => $expectedPayment,
                    'row.awb' => 'AWB-1',
                    'row.live_tracking_url' => 'https://example.com/booking',
                ];
            $actualContracts[$case] = [
                    'row.status' => $r['row']['status'],
                    'row.instant_status_code' => $r['row']['instant_status_code'],
                    'row.rejected_reason' => $r['row']['rejected_reason'],
                    'row.instant_payment_id' => $r['row']['instant_payment_id'],
                    'row.instant_payment_status' => $r['row']['instant_payment_status'],
                    'row.awb' => $r['row']['awb'],
                    'row.live_tracking_url' => $r['row']['live_tracking_url'],
                ];
            foreach ($metadata as $field=>$value) { $case = (__FUNCTION__) . ' #' . count($expectedContracts);
        $expectedContracts[$case] = $value;
        $actualContracts[$case] = $r['row'][$field]; }
            $case = (__FUNCTION__) . ' #' . count($expectedContracts);
            $expectedContracts[$case] = [
                    'results.0.changed' => true,
                    'results.1.changed' => false,
                    'cancelable' => false,
                    'woo.notes' => [],
                    'count(r.writes)' => 1,
                ];
            $actualContracts[$case] = [
                    'results.0.changed' => $r['results'][0]['changed'],
                    'results.1.changed' => $r['results'][1]['changed'],
                    'cancelable' => $r['cancelable'],
                    'woo.notes' => $r['woo']['notes'],
                    'count(r.writes)' => count($r['writes']),
                ];
            foreach ($row as $field=>$value) { $case = (__FUNCTION__) . ' #' . count($expectedContracts);
        $expectedContracts[$case] = $value;
        $actualContracts[$case] = $r['writes'][0]['condition'][$field]; }
            foreach (array_keys($metadata) as $field) { $this->assertNull($r['writes'][0]['condition'][$field]); }
            foreach (['status','instant_status_code','rejected_reason'] as $field) { $this->assertArrayNotHasKey($field, $r['writes'][0]['changes']); }
        }
    
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
}

    #[Test]
    public function booking_issue_precedence_is_scoped_and_recomputed_after_cas_conflicts(): void {
        $expectedContracts = [];
        $actualContracts = [];

        foreach ([405,500,555,701,702,703,704,303,301,333] as $issue) {
            foreach ([100,101,105,110] as $code) {
                $r = $this->runFixture(['row'=>['status'=>'pending','instant_status_code'=>(string) $issue,'rejected_reason'=>'Issue'], 'events'=>[array_merge($this->event($code), ['mode'=>'booking'])]]);
                $case = (__FUNCTION__) . ' #' . count($expectedContracts);
                $expectedContracts[$case] = [
                        'row.instant_status_code' => (string) $issue,
                        'row.status' => 'pending',
                        'row.rejected_reason' => 'Issue',
                        'writes' => [],
                    ];
                $actualContracts[$case] = [
                        'row.instant_status_code' => $r['row']['instant_status_code'],
                        'row.status' => $r['row']['status'],
                        'row.rejected_reason' => $r['row']['rejected_reason'],
                        'writes' => $r['writes'],
                    ];
            }
            // Explicit authenticated tracking may recover even to a pre-pickup state.
            $r = $this->runFixture(['row'=>['status'=>'pending','instant_status_code'=>$issue,'rejected_reason'=>'Issue'], 'events'=>[$this->event(105)]]);
            $case = (__FUNCTION__) . ' #' . count($expectedContracts);
            $expectedContracts[$case] = [
                    'row.instant_status_code' => 105,
                    'row.rejected_reason' => null,
                ];
            $actualContracts[$case] = [
                    'row.instant_status_code' => $r['row']['instant_status_code'],
                    'row.rejected_reason' => $r['row']['rejected_reason'],
                ];
            foreach ([106=>'shipped', 200=>'finished'] as $code=>$status) {
                $r = $this->runFixture(['row'=>['status'=>'pending','instant_status_code'=>$issue,'rejected_reason'=>'Issue'], 'events'=>[array_merge($this->event($code), ['event'=>106 === $code ? 'shipped_packages' : 'finished_packages'])]]);
                $case = (__FUNCTION__) . ' #' . count($expectedContracts);
                $expectedContracts[$case] = [
                        'row.instant_status_code' => $code,
                        'row.status' => $status,
                        'row.rejected_reason' => null,
                    ];
                $actualContracts[$case] = [
                        'row.instant_status_code' => $r['row']['instant_status_code'],
                        'row.status' => $r['row']['status'],
                        'row.rejected_reason' => $r['row']['rejected_reason'],
                    ];
            }
        }
        $r = $this->runFixture(['race'=>['status'=>'pending','instant_status_code'=>500,'rejected_reason'=>'Concurrent issue','instant_payment_status'=>'refunded'], 'events'=>[array_merge($this->event(105, [], ['id'=>'PAY-1','status'=>'paid']), ['mode'=>'booking','metadata'=>['vehicle'=>'motor']])]]);
        $case = (__FUNCTION__) . ' #' . count($expectedContracts);
        $expectedContracts[$case] = [
                'reads' => 2,
                'row.status' => 'pending',
                'row.instant_status_code' => 500,
                'row.rejected_reason' => 'Concurrent issue',
                'row.instant_payment_status' => 'refunded',
                'row.vehicle' => 'motor',
                'writes.1.condition.rejected_reason' => 'Concurrent issue',
            ];
        $actualContracts[$case] = [
                'reads' => $r['reads'],
                'row.status' => $r['row']['status'],
                'row.instant_status_code' => $r['row']['instant_status_code'],
                'row.rejected_reason' => $r['row']['rejected_reason'],
                'row.instant_payment_status' => $r['row']['instant_payment_status'],
                'row.vehicle' => $r['row']['vehicle'],
                'writes.1.condition.rejected_reason' => $r['writes'][1]['condition']['rejected_reason'],
            ];
    
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
}

    #[Test]
    public function booking_rejects_untrusted_fields_ambiguous_codes_and_changed_snapshots(): void {
        $base = ['mode'=>'booking','package'=>['order_id'=>'KA-1','status'=>105], 'payment'=>['id'=>'PAY-1','status'=>'paid'], 'metadata'=>[]];
        foreach ([['service'=>'grab_express'], ['service_type'=>'same_day'], ['service_name'=>'same_day'], ['awb'=>'OTHER'], ['live_tracking_url'=>'https://user:secret@example.com/'], ['status'=>0]] as $extra) {
            $event = $base; $event['package'] = array_replace($event['package'], $extra);
            $r = $this->runFixture(['events'=>[$event]]);
            $this->assertSame(
                [
                    'writes' => [],
                    'results.0.error' => InvalidArgumentException::class,
                ],
                [
                    'writes' => $r['writes'],
                    'results.0.error' => $r['results'][0]['error'],
                ],
                __FUNCTION__
            );
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
