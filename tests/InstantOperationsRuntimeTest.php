<?php

declare(strict_types=1);

// Isolated fixture exercises the real monotonic state merger, never an SDK/network.
if (in_array('--fixture', $argv ?? [], true)) {
    define('ABSPATH', __DIR__);
    function __($text, $domain) { return $text; }
    function esc_html__($text, $domain) { return $text; }
    function wp_json_encode($value) { return json_encode($value); }
    function wp_strip_all_tags($text) { return strip_tags($text); }
    function wp_parse_url($url) { return parse_url($url); }
    function esc_url_raw($url, $protocols = []) { return $url; }
    function get_transient($key) { return ($GLOBALS['cache'][$key]['expires'] ?? 0) > $GLOBALS['now'] ? $GLOBALS['cache'][$key]['value'] : false; }
    function set_transient($key, $value, $ttl) { $GLOBALS['cache'][$key] = ['value'=>$value, 'ttl'=>$ttl, 'expires'=>$GLOBALS['now'] + $ttl]; return true; }
    function wp_generate_uuid4() { return 'fixture-owner-' . ++$GLOBALS['owner_sequence']; }
    function maybe_serialize($value) { return is_array($value) || is_object($value) ? serialize($value) : $value; }
    function get_option($key) { return isset($GLOBALS['options'][$key]) ? unserialize($GLOBALS['options'][$key]) : false; }
    function add_option($key, $value, $deprecated = '', $autoload = true) {
        if ($GLOBALS['add_race'] !== null) {
            $GLOBALS['options'][$key] = serialize($GLOBALS['add_race']);
            $GLOBALS['add_race'] = null;
        }

        if (isset($GLOBALS['options'][$key])) { return false; }
        if ($autoload !== false) { throw new LogicException('Cooldown must not autoload'); }
        $GLOBALS['options'][$key] = serialize($value);
        return true;
    }
    function wp_cache_delete($key, $group) { $GLOBALS['cache_deletes'][] = [$key, $group]; return true; }
    // Deliberately no delete_option stub: unconditional deletion must never be used.
    class InstantOperationsFixtureWpdb {
        public $options = 'wp_options'; public $queries = []; private $prepared = [];
        public function prepare($query, ...$args) {
            if ($query !== 'DELETE FROM wp_options WHERE option_name = %s AND BINARY option_value = %s' || count($args) !== 2) {
                throw new LogicException('Expiry cleanup requires an exact name/value CAS');
            }
            $token = 'prepared-' . count($this->prepared);
            $this->prepared[$token] = $args;
            return $token;
        }
        public function query($query) {
            if (!isset($this->prepared[$query])) { throw new LogicException('Unprepared cleanup'); }
            [$key, $value] = $this->prepared[$query];
            $this->queries[] = [$key, $value];
            if ($GLOBALS['delete_race'] !== null) {
                $GLOBALS['options'][$key] = serialize($GLOBALS['delete_race']);
                $GLOBALS['delete_race'] = null;
            }
            if (($GLOBALS['options'][$key] ?? null) !== $value) { return 0; }
            unset($GLOBALS['options'][$key]);
            return 1;
        }
    }


    eval('namespace KiriminAjaOfficial\\Services; function time() { return $GLOBALS["now"]; }');
    function kiriof_log($level, $message, $context = [], $channel = '') {
        $GLOBALS['logs'][] = compact('level', 'message', 'context', 'channel');
        if ($GLOBALS['log_throw']) { throw new RuntimeException('logging unavailable'); }
    }
    function wc_get_order($id) { return $GLOBALS['woo']; }
    eval('namespace KiriminAjaOfficial\\Repositories;
        class TransactionRepository {
            public $rows = []; public $writes = []; public $race; public $releaseRace; public $claimRace; public $claims = [];
            public function getTransactionByOrderId($id) { return isset($this->rows[$id]) ? clone $this->rows[$id] : false; }
            public function claimInstantCancellation(string $id, int $previous_code, string $attempt_id = ""): bool {
                $this->claims[] = [$id, $previous_code];
                if ($this->claimRace !== null) { foreach ($this->claimRace as $key => $value) { $this->rows[$id]->{$key} = $value; } $this->claimRace = null; }
                $row = $this->rows[$id] ?? null;
                if (!$row || $row->delivery_type !== "instant" || !in_array($row->service, ["gosend","grab_express"], true)
                    || !in_array($row->status, ["pending","request_pickup"], true)
                    || !in_array($previous_code, [100,105,110], true)
                    || (int) $row->instant_status_code !== $previous_code || empty($row->instant_payment_id)
                    || \KiriminAjaOfficial\Services\InstantShipmentState::hasCancellationFence($row)
                    || $row->instant_payment_status !== "paid" || empty($row->awb)) { return false; }
                $row->rejected_reason = "Instant cancellation requires reconciliation." . ($attempt_id !== "" ? ":" . $attempt_id : "");
                $this->writes[] = ["condition"=>["order_id"=>$id,"instant_status_code"=>$previous_code], "changes"=>["rejected_reason"=>$row->rejected_reason]];
                return true;
            }
            public function compareAndSwapInstant(string $id, array $condition, array $changes): bool {
                if ($changes === ["rejected_reason"=>null] && $this->releaseRace !== null) { foreach ($this->releaseRace as $key => $value) { $this->rows[$id]->{$key} = $value; } $this->releaseRace = null; }
                if (empty($changes) || !array_key_exists("status", $condition) || !array_key_exists("instant_status_code", $condition)) { return false; }
                if (isset($condition["order_id"]) && $condition["order_id"] !== $id) { return false; }
                $condition = array_merge(["order_id"=>$id,"delivery_type"=>"instant"], $condition);
                return $this->updateTransactionByCallbackVerified(["condition"=>$condition,"changes"=>$changes]);
            }
            public function updateTransactionByCallbackVerified($payload) {
                $this->writes[] = $payload;
                $id = $payload["condition"]["order_id"];
                if ($this->race !== null) { foreach ($this->race as $key => $value) { $this->rows[$id]->{$key} = $value; } $this->race = null; return false; }
                foreach ($payload["condition"] as $key => $value) { if (($this->rows[$id]->{$key} ?? null) !== $value) { return false; } }
                foreach ($payload["changes"] as $key => $value) { $this->rows[$id]->{$key} = $value; }
                return true;
            }
        }
        class InstantDeliveryApiRepository {
            public $calls = []; public $deleteCodes = []; public $deleteMarkers = []; public $tracking; public $cancellation; public $throw = false; public $onTracking; public $onDelete; public $diagnostics = [];
            public function tracking($id) { $this->calls[] = ["GET",$id]; if ($this->onTracking !== null) { $hook = $this->onTracking; $this->onTracking = null; $hook(); } return $this->tracking; }
            public function cancel($id) { $this->calls[] = ["DELETE",$id]; $this->deleteCodes[] = $GLOBALS["repo"]->rows[$id]->instant_status_code; $this->deleteMarkers[] = $GLOBALS["repo"]->rows[$id]->rejected_reason ?? null; if ($this->onDelete !== null) { $hook = $this->onDelete; $this->onDelete = null; $hook(); } foreach ($GLOBALS["webhook_during_delete"] as $key => $value) { $GLOBALS["repo"]->rows[$id]->{$key} = $value; } if ($this->throw) { throw new \\RuntimeException("private driver address 08123456789"); } return $this->cancellation; }
            public function cancellationDiagnostics(): array { return $this->diagnostics; }
            public function book($payload) { throw new \\LogicException("Booking forbidden"); }
        }');
    require __DIR__ . '/../inc/Services/TransactionDeliveryType.php';
    require __DIR__ . '/../inc/Services/InstantDeliveryStatus.php';
    require __DIR__ . '/../inc/Services/InstantShipmentState.php';
    require __DIR__ . '/../inc/Infrastructure/InstantDiagnosticRedactor.php';
    require __DIR__ . '/../inc/Services/InstantOperationsService.php';
    $input = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
    $GLOBALS['now'] = time();
    $GLOBALS['initial_now'] = $GLOBALS['now'];
    $GLOBALS['owner_sequence'] = 0;
    $GLOBALS['logs'] = [];
    $GLOBALS['log_throw'] = $input['log_throw'] ?? false;
    $GLOBALS['cache'] = [];
    $GLOBALS['options'] = [];
    $GLOBALS['cache_deletes'] = [];
    $GLOBALS['wpdb'] = new InstantOperationsFixtureWpdb();
    $throttleKey = 'kiriof_instant_ops_throttle_' . hash('sha256', 'KA-1');
    $claim = static function ($value) {
        if (is_array($value) && isset($value['expires_in'])) { $value['expires'] = $GLOBALS['now'] + $value['expires_in']; unset($value['expires_in']); }
        return $value;
    };
    if (array_key_exists('existing_claim', $input)) { $GLOBALS['options'][$throttleKey] = serialize($claim($input['existing_claim'])); }
    $GLOBALS['add_race'] = isset($input['add_race']) ? $claim($input['add_race']) : null;
    $GLOBALS['delete_race'] = isset($input['delete_race']) ? $claim($input['delete_race']) : null;
    $GLOBALS['woo'] = new class {
        public $status = 'processing'; public $notes = []; public $meta = [];
        public function get_status() { return $this->status; }
        public function update_status($status, $note) { $this->status = $status; $this->notes[] = $note; }
        public function get_meta($key, $single) { return $this->meta[$key] ?? ''; }
        public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
        public function save() { return 1; }
        public function add_order_note($note) { $this->notes[] = $note; return count($this->notes); }
    };
    $repo = new KiriminAjaOfficial\Repositories\TransactionRepository();
    $GLOBALS['repo'] = $repo;
    $defaults = ['order_id'=>'KA-1','delivery_type'=>'instant','service'=>'gosend','service_name'=>'instant','status'=>'request_pickup','instant_status_code'=>100,'instant_payment_id'=>'PAY-1','instant_payment_status'=>'paid','awb'=>'AWB-1','live_tracking_url'=>'','wp_wc_order_stat_order_id'=>1];
    $repo->rows['KA-1'] = (object) array_replace($defaults, $input['row'] ?? []);
    if (isset($input['second'])) { $repo->rows['KA-2'] = (object) array_replace($defaults, ['order_id'=>'KA-2'], $input['second']); }
    $repo->race = $input['race'] ?? null;
    $repo->claimRace = $input['claim_race'] ?? null;
    $repo->releaseRace = $input['release_race'] ?? null;
    $api = new KiriminAjaOfficial\Repositories\InstantDeliveryApiRepository();
    $tracking = ['status'=>true,'data'=>['status'=>true,'code'=>0,'result'=>['order_id'=>'KA-1','service'=>'gosend','service_type'=>'instant','tracking_code'=>'AWB-1','live_tracking_url'=>'https://tracking.example.test/KA-1','date'=>['allocated_at'=>'2025-01-01 10:00:00'],'driver'=>['name'=>'private driver','phone'=>'08123456789'],'destination'=>['address'=>'private address']]]];
    $cancel = ['status'=>true,'operation_accepted'=>true,'data'=>['status'=>true,'code'=>0,'result'=>['payment_id'=>'PAY-1','packages'=>[['order_id'=>'KA-1','service'=>'gosend','status'=>105]]]]];
    $api->tracking = $input['tracking'] ?? $tracking;
    $api->cancellation = $input['cancel'] ?? $cancel;
    foreach (['tracking','cancellation'] as $field) { if (isset($api->{$field}['data']) && is_array($api->{$field}['data'])) { $api->{$field}['data'] = json_decode(json_encode($api->{$field}['data'])); } }
    $api->throw = $input['throw'] ?? false;
    $api->diagnostics = array_replace(['reason'=>'transport_failure','http_status'=>504,'submitted'=>true,'elapsed_ms'=>25000,'driver'=>'private driver','phone'=>'08123456789','address'=>'private address','raw_response'=>['token'=>'secret-token'],'order_id'=>'KA-1'], $input['diagnostics'] ?? []);
    $GLOBALS['webhook_during_delete'] = $input['webhook_during_delete'] ?? [];
    $service = new KiriminAjaOfficial\Services\InstantOperationsService($repo, $api, new KiriminAjaOfficial\Services\InstantShipmentState($repo));
    $results = [];
    $snapshots = [];
    $nestedResults = [];
    if (isset($input['concurrent'])) {
        $api->onTracking = static function () use ($service, $input, &$nestedResults) {
            $GLOBALS['now'] += $input['concurrent']['advance'] ?? 0;
            $nestedResults[] = $service->{$input['concurrent']['method']}(['KA-1']);
        };
    }
    if (isset($input['webhook_during_tracking'])) {
        $api->onTracking = static function () use ($repo, $input) { foreach ($input['webhook_during_tracking'] as $key=>$value) { $repo->rows['KA-1']->{$key}=$value; } };
    }
    if (isset($input['replacement_response'])) {
        $api->onTracking = static function () use ($api, $service, $input, &$nestedResults) {
            $original = $api->tracking;
            $GLOBALS['now'] += 41;
            $api->tracking = json_decode(json_encode($input['replacement_response']), true);
            $api->tracking['data'] = json_decode(json_encode($api->tracking['data']));
            $nestedResults[] = $service->track(array('KA-1'));
            $api->tracking = $original;
        };
    }
    if (isset($input['replacement_during_delete'])) {
        $api->onDelete = static function () use ($api, $service, $input, &$nestedResults) {
            $original = $api->cancellation;
            $GLOBALS['now'] += 91;
            $api->cancellation = ['status'=>false];
            $nestedResults[] = $service->cancel(['KA-1']);
            $api->cancellation = $original;
        };
    }
    if (isset($input['expire_during_tracking'])) {
        $api->onTracking = static function () { $GLOBALS['now'] += 91; };
    }
    if (isset($input['expire_during_delete'])) {
        $api->onDelete = static function () { $GLOBALS['now'] += 91; };
    }
    foreach ($input['operations'] ?? [['method'=>'reconcile']] as $operation) {
        $GLOBALS['now'] += $operation['advance'] ?? 0;
        if ($operation['expire_throttle'] ?? false) { $GLOBALS['now'] += 41; }
        if ($operation['expire_report'] ?? false) { foreach (array_keys($GLOBALS['cache']) as $key) { if (str_contains($key, '_report_')) { unset($GLOBALS['cache'][$key]); } } }
        if (isset($operation['tracking'])) { $api->tracking = $operation['tracking']; $api->tracking['data'] = json_decode(json_encode($api->tracking['data'])); }
        if (array_key_exists('cancel', $operation)) { $api->cancellation = $operation['cancel']; if (is_array($api->cancellation['data'] ?? null)) { $api->cancellation['data'] = json_decode(json_encode($api->cancellation['data'])); } }
        if (array_key_exists('throw', $operation)) { $api->throw = $operation['throw']; }
        if (isset($operation['diagnostics'])) { $api->diagnostics = array_replace($api->diagnostics, $operation['diagnostics']); }
        try { $results[] = $service->{$operation['method']}($operation['ids'] ?? ['KA-1']); }
        catch (Throwable $error) { $results[] = ['error'=>get_class($error)]; }
        $snapshots[] = clone $repo->rows['KA-1'];
    }
    $results_presentation = KiriminAjaOfficial\Services\InstantDeliveryStatus::describe($repo->rows['KA-1']);
    $repo->rows['KA-1']->fixture_presentation = $results_presentation;
    echo json_encode(['snapshots'=>$snapshots,'logs'=>$GLOBALS['logs'],'results'=>$results,'rows'=>$repo->rows,'writes'=>$repo->writes,'calls'=>$api->calls,'claims'=>$repo->claims,'delete_codes'=>$api->deleteCodes,'delete_markers'=>$api->deleteMarkers,'cache'=>$GLOBALS['cache'],'options'=>array_map('unserialize', $GLOBALS['options']),'queries'=>$GLOBALS['wpdb']->queries,'cache_deletes'=>$GLOBALS['cache_deletes'],'now'=>$GLOBALS['now'],'initial_now'=>$GLOBALS['initial_now'],'nested_results'=>$nestedResults,'woo'=>$GLOBALS['woo']], JSON_THROW_ON_ERROR);
    exit;
}

use PHPUnit\Framework\TestCase;

final class InstantOperationsRuntimeTest extends TestCase {
    private function fixture(array $input = []): array {
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --fixture ' . escapeshellarg(json_encode($input, JSON_THROW_ON_ERROR)) . ' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    private function tracking(array $result): array {
        return ['status'=>true,'data'=>['status'=>true,'code'=>0,'result'=>array_replace(['order_id'=>'KA-1','service'=>'gosend','service_type'=>'instant','tracking_code'=>'AWB-1'], $result)]];
    }

    public function test_pending_booking_evidence_recovers_without_inventing_payment_or_pickup(): void {
        $r = $this->fixture(['row'=>['status'=>'pending','awb'=>null,'instant_payment_id'=>null,'instant_payment_status'=>'pending','instant_status_code'=>null]]);
        $this->assertSame(
        	array(
        		'status' => 'reconciled',
        		'awb' => 'AWB-1',
        		'instant_payment_id' => null,
        		'instant_payment_status' => 'pending',
        		'instant_status_code' => null,
        		'shipment status' => 'pending',
        		'calls' => [['GET','KA-1']],
        		'redacts private' => false,
        	),
        	array(
        		'status' => $r['results'][0]['rows'][0]['status'],
        		'awb' => $r['rows']['KA-1']['awb'],
        		'instant_payment_id' => $r['rows']['KA-1']['instant_payment_id'],
        		'instant_payment_status' => $r['rows']['KA-1']['instant_payment_status'],
        		'instant_status_code' => $r['rows']['KA-1']['instant_status_code'],
        		'shipment status' => $r['rows']['KA-1']['status'],
        		'calls' => $r['calls'],
        		'redacts private' => str_contains( json_encode($r['results']) . json_encode($r['cache']), 'private' ),
        	)
        );
    }

    public function test_not_found_keeps_unknown_claim_untouched_and_never_rebooks(): void {
        $r = $this->fixture(['row'=>['status'=>'pending','instant_status_code'=>null,'rejected_reason'=>'Check remote state before retrying'],'tracking'=>['status'=>false,'not_found'=>true,'data'=>'private address']]);
        $this->assertSame(
        	array(
        		'status' => 'not_found',
        		'shipment status' => 'pending',
        		'rejected_reason' => 'Check remote state before retrying',
        		'writes' => [],
        	),
        	array(
        		'status' => $r['results'][0]['rows'][0]['status'],
        		'shipment status' => $r['rows']['KA-1']['status'],
        		'rejected_reason' => $r['rows']['KA-1']['rejected_reason'],
        		'writes' => $r['writes'],
        	)
        );
    }

    public function test_selection_is_strict_and_batch_validation_precedes_network(): void {
        foreach ([[], ['KA-1','KA-1'], [1], ['../KA-1'], ['KA-1','missing'], array_fill(0, 11, 'KA-1')] as $ids) {
            $r = $this->fixture(['operations'=>[['method'=>'reconcile','ids'=>$ids]]]);
            $this->assertSame(
            	array(
            		'error' => InvalidArgumentException::class,
            		'calls' => [],
            	),
            	array(
            		'error' => $r['results'][0]['error'],
            		'calls' => $r['calls'],
            	)
            );
        }
        foreach ([['status'=>'new'], ['service'=>'borzo'], ['service'=>'jne','delivery_type'=>'express']] as $row) {
            $r = $this->fixture(['row'=>$row]);
            $this->assertSame([], $r['calls']);
        }
        $r = $this->fixture(['second'=>[], 'operations'=>[['method'=>'cancel','ids'=>['KA-1','KA-2']]]]);
        $this->assertSame([], $r['calls']);
    }

    public function test_tracking_prefers_safe_local_url_but_reconcile_verifies_and_backoff_is_shared(): void {
        $r = $this->fixture(['row'=>['live_tracking_url'=>'https://tracking.example.test/local'],'operations'=>[['method'=>'track']]]);
        $this->assertSame(
        	array(
        		'calls' => [],
        		'status' => 'tracked',
        	),
        	array(
        		'calls' => $r['calls'],
        		'status' => $r['results'][0]['rows'][0]['status'],
        	)
        );
        $r = $this->fixture(['operations'=>[['method'=>'reconcile'],['method'=>'track'],['method'=>'reconcile'],['method'=>'reconcile','expire_report'=>true]]]);
        $this->assertSame(
        	array(
        		'calls count' => 1,
        		'status' => 'unknown',
        	),
        	array(
        		'calls count' => count( $r['calls'] ),
        		'status' => $r['results'][3]['rows'][0]['status'],
        	)
        );
        foreach ($r['cache'] as $entry) { $this->assertSame(10, $entry['ttl']); }
        $this->assertSame($r['initial_now'] + 40, array_values($r['options'])[0]['expires']);
        foreach (['javascript:alert(1)', 'https://user:pass@example.test/', "https://example.test/\nsecret"] as $url) {
            $r = $this->fixture(['tracking'=>$this->tracking(['live_tracking_url'=>$url])]);
            $this->assertSame('', $r['results'][0]['rows'][0]['tracking_url']);
        }
    }

    public function test_tracking_url_only_is_presentation_not_booking_or_payment_confirmation(): void {
        foreach (array('live_tracking_url','live_track_url','tracking_url','live_tracking') as $alias) {
            $package = array('order_id'=>'KA-1','service'=>'gosend','service_type'=>'instant', $alias=>'https://courier.test/live?signature=fixture%2B1');
            $response = array('status'=>true,'data'=>array('status'=>true,'code'=>0,'result'=>$package));
            $result = $this->fixture(array('tracking'=>$response,'operations'=>array(array('method'=>'track'))));
            $this->assertSame(array('tracked','https://courier.test/live?signature=fixture%2B1'), array($result['results'][0]['rows'][0]['status'],$result['results'][0]['rows'][0]['tracking_url']));
            $this->assertSame(array(),$result['writes']);
            $result = $this->fixture(array('tracking'=>$response,'operations'=>array(array('method'=>'reconcile'))));
            $this->assertSame('unknown',$result['results'][0]['rows'][0]['status']);
        }
    }

    public function test_manual_tracking_cooldown_is_not_a_provider_error_or_reconciliation_proof(): void {
        $response = array('status'=>true,'data'=>array('status'=>true,'code'=>0,'result'=>array('order_id'=>'KA-1','service'=>'gosend','service_type'=>'instant')));
        $result = $this->fixture(array('tracking'=>$response,'operations'=>array(array('method'=>'track'),array('method'=>'reconcile'),array('method'=>'track','advance'=>11),array('method'=>'track','advance'=>30))));
        $this->assertSame(array('tracking_unavailable','unknown','tracking_pending','tracking_unavailable'), array_map(static fn($result)=>$result['rows'][0]['status'],$result['results']));
        $this->assertSame(array(array('GET','KA-1'),array('GET','KA-1')),$result['calls']);
        $this->assertSame(array(),$result['writes']);
    }

    public function test_webhook_url_wins_over_inflight_missing_link_without_a_new_state_write(): void {
        $package = array('order_id'=>'KA-1','service'=>'gosend','service_type'=>'instant','live_tracking_url'=>null);
        $result = $this->fixture(array('tracking'=>array('status'=>true,'data'=>array('status'=>true,'code'=>0,'result'=>$package)), 'webhook_during_tracking'=>array('live_tracking_url'=>'https://courier.test/new'), 'operations'=>array(array('method'=>'track'))));
        $this->assertSame(array('tracked','https://courier.test/new'),array($result['results'][0]['rows'][0]['status'],$result['results'][0]['rows'][0]['tracking_url']));
        $this->assertSame(array(),$result['writes']);
    }

    public function test_expired_tracking_request_cannot_overwrite_replacement_report(): void {
        $package = array('order_id'=>'KA-1','service'=>'gosend','service_type'=>'instant');
        $response = static fn($package)=>array('status'=>true,'data'=>array('status'=>true,'code'=>0,'result'=>$package));
        $result = $this->fixture(array('tracking'=>$response($package), 'replacement_response'=>$response($package+array('live_tracking_url'=>'https://courier.test/new')), 'operations'=>array(array('method'=>'track'))));
        $this->assertSame(array(array('GET','KA-1'),array('GET','KA-1')),$result['calls']);
        $this->assertSame('https://courier.test/new',$result['results'][0]['rows'][0]['tracking_url']);
        $this->assertSame('https://courier.test/new',array_values($result['cache'])[0]['value']['tracking_url']);
        $this->assertSame(array(),$result['writes']);
    }

    public function test_terminal_dates_without_url_or_awb_still_apply_authoritative_lifecycle(): void {
        foreach (array('finished_at'=>array(200,'finished'),'canceled_at'=>array(300,'canceled')) as $field=>$expected) {
            $response = array('status'=>true,'data'=>array('status'=>true,'code'=>0,'result'=>array('order_id'=>'KA-1','service'=>'gosend','service_type'=>'instant','date'=>array($field=>'2025-01-01T10:00:00Z'))));
            $result = $this->fixture(array('tracking'=>$response,'operations'=>array(array('method'=>'track'))));
            $this->assertSame($expected,array($result['rows']['KA-1']['instant_status_code'],$result['rows']['KA-1']['status']));
            $this->assertSame('tracking_unavailable',$result['results'][0]['rows'][0]['status']);
        }
    }

    public function test_valid_identity_without_tracking_link_or_awb_is_unavailable_not_unknown(): void {
        foreach (array(array(), array('live_tracking_url'=>null, 'tracking_code'=>null), array('live_tracking_url'=>'', 'tracking_code'=>''), array('live_tracking_url'=>null, 'awb'=>null), array('live_tracking_url'=>'', 'awb'=>'')) as $metadata) {
            $package = array_merge(array('order_id'=>'KA-1', 'service'=>'gosend', 'service_type'=>'instant'), $metadata);
            $response = array('status'=>true, 'data'=>array('status'=>true, 'code'=>0, 'result'=>$package));
            $result = $this->fixture(array('tracking'=>$response, 'operations'=>array(array('method'=>'track'))));
            $this->assertSame(array('tracking_unavailable',''), array($result['results'][0]['rows'][0]['status'], $result['results'][0]['rows'][0]['tracking_url']));
            $this->assertSame(array(), $result['writes']);
            $this->assertSame(array('request_pickup',100,'AWB-1'), array($result['rows']['KA-1']['status'], $result['rows']['KA-1']['instant_status_code'], $result['rows']['KA-1']['awb']));
            $result = $this->fixture(array('tracking'=>$response, 'operations'=>array(array('method'=>'reconcile'))));
            $this->assertSame('unknown', $result['results'][0]['rows'][0]['status']);
        }
        foreach (array(array('order_id'=>'OTHER'), array('service'=>'grab_express'), array('service_type'=>'sameday'), array('tracking_code'=>array()), array('live_tracking_url'=>array())) as $invalid) {
            $package = array_replace(array('order_id'=>'KA-1', 'service'=>'gosend', 'service_type'=>'instant'), $invalid);
            $result = $this->fixture(array('tracking'=>array('status'=>true, 'data'=>array('status'=>true, 'code'=>0, 'result'=>$package)), 'operations'=>array(array('method'=>'track'))));
            $this->assertSame('unknown', $result['results'][0]['rows'][0]['status']);
            $this->assertSame(array(), $result['writes']);
        }
    }

    public function test_tracking_requires_usable_url_and_normalizes_cached_reconciliation(): void {
        $result = $this->fixture(array('tracking'=>$this->tracking(array('status'=>105, 'live_tracking_url'=>null, 'tracking_code'=>null)), 'operations'=>array(array('method'=>'track'))));
        $this->assertSame('tracking_unavailable', $result['results'][0]['rows'][0]['status']);
        $this->assertSame('AWB-1', $result['rows']['KA-1']['awb']);
        foreach (array(null, '', 'javascript:alert(1)', 'https://user:pass@courier.test/map') as $url) {
            $result = $this->fixture(array('tracking'=>$this->tracking(array('live_tracking_url'=>$url)), 'operations'=>array(array('method'=>'track'))));
            $this->assertSame(array('tracking_unavailable', ''), array($result['results'][0]['rows'][0]['status'], $result['results'][0]['rows'][0]['tracking_url']));
            $this->assertSame(array(array('GET','KA-1')), $result['calls']);
            $this->assertSame('request_pickup', $result['rows']['KA-1']['status']);
        }
        foreach (array(null, 'https://courier.test/map') as $url) {
            $result = $this->fixture(array('tracking'=>$this->tracking(array('live_tracking_url'=>$url)), 'operations'=>array(array('method'=>'reconcile'), array('method'=>'track'))));
            $this->assertSame('reconciled', $result['results'][0]['rows'][0]['status']);
            $this->assertSame(null === $url ? 'tracking_unavailable' : 'tracked', $result['results'][1]['rows'][0]['status']);
            $this->assertSame($url ?? '', $result['results'][1]['rows'][0]['tracking_url']);
            $this->assertCount(1, $result['calls']);
        }
    }

    public function test_atomic_insert_loser_and_unknown_expiry_fail_closed(): void {
        foreach (['legacy-owner', ['owner'=>'old'], ['owner'=>'old','expires'=>'1'], ['expires'=>1], ['owner'=>'active','expires_in'=>40]] as $claim) {
            $r = $this->fixture(['existing_claim'=>$claim, 'operations'=>[['method'=>'reconcile'], ['method'=>'cancel']]]);
            $this->assertSame(
            	array(
            		'calls' => [],
            		'queries' => [],
            		'claims' => [],
            	),
            	array(
            		'calls' => $r['calls'],
            		'queries' => $r['queries'],
            		'claims' => $r['claims'],
            	)
            );
        }
        $r = $this->fixture(['add_race'=>['owner'=>'winner','expires_in'=>40]]);
        $this->assertSame(
        	array(
        		'calls' => [],
        		'owner' => 'winner',
        		'cache_deletes' => [],
        	),
        	array(
        		'calls' => $r['calls'],
        		'owner' => array_values($r['options'])[0]['owner'],
        		'cache_deletes' => $r['cache_deletes'],
        	)
        );
    }

    public function test_active_owner_blocks_concurrent_modes_even_after_transport_timeout(): void {
        foreach (['track','reconcile','cancel'] as $method) {
            $r = $this->fixture(['concurrent'=>['method'=>$method,'advance'=>26]]);
            $this->assertSame(
            	array(
            		'calls' => [['GET','KA-1']],
				'status' => 'track' === $method ? 'tracking_pending' : 'unknown',
            		'claims' => [],
            		'queries' => [],
            	),
            	array(
            		'calls' => $r['calls'],
            		'status' => $r['nested_results'][0]['rows'][0]['status'],
            		'claims' => $r['claims'],
            		'queries' => $r['queries'],
            	)
            );
        }
        $r = $this->fixture(['operations'=>[['method'=>'cancel']], 'concurrent'=>['method'=>'cancel','advance'=>26]]);
        $this->assertSame(
        	array(
        		'calls' => [['GET','KA-1'],['DELETE','KA-1']],
        		'status' => 'unknown',
                'delete_codes' => [100],
        		'claims count' => 1,
        	),
        	array(
        		'calls' => $r['calls'],
        		'status' => $r['nested_results'][0]['rows'][0]['status'],
        		'delete_codes' => $r['delete_codes'],
        		'claims count' => count( $r['claims'] ),
        	)
        );
    }

    public function test_expired_owner_cleanup_is_exact_and_preserves_replacement_owner(): void {
        $expired = ['owner'=>'expired','expires_in'=>-1];
        $r = $this->fixture(['existing_claim'=>$expired]);
        $this->assertSame(
        	array(
        		'calls' => [['GET','KA-1']],
        		'queries count' => 1,
        		'queries' => serialize(['owner'=>'expired','expires'=>$r['initial_now'] - 1]),
        		'cache_deletes' => [[$r['queries'][0][0], 'options']],
        		'owner' => 'fixture-owner-1',
        		'expires' => $r['initial_now'] + 40,
        	),
        	array(
        		'calls' => $r['calls'],
        		'queries count' => count( $r['queries'] ),
        		'queries' => $r['queries'][0][1],
        		'cache_deletes' => $r['cache_deletes'],
        		'owner' => array_values($r['options'])[0]['owner'],
        		'expires' => array_values($r['options'])[0]['expires'],
        	)
        );
        foreach (['reconcile','cancel'] as $method) {
            $r = $this->fixture(['existing_claim'=>$expired, 'delete_race'=>['owner'=>'replacement','expires_in'=>40], 'operations'=>[['method'=>$method]]]);
            $this->assertSame(
            	array(
            		'calls' => [],
            		'cache_deletes' => [],
            		'claims' => [],
            		'owner' => 'replacement',
            	),
            	array(
            		'calls' => $r['calls'],
            		'cache_deletes' => $r['cache_deletes'],
            		'claims' => $r['claims'],
            		'owner' => array_values($r['options'])[0]['owner'],
            	)
            );
        }
    }

    public function test_report_cache_expires_before_cooldown_and_only_expired_claim_is_reclaimed(): void {
        $r = $this->fixture(['tracking'=>['status'=>false,'not_found'=>true], 'operations'=>[
            ['method'=>'reconcile'], ['method'=>'track','advance'=>9], ['method'=>'reconcile','advance'=>2],
            ['method'=>'reconcile','advance'=>28], ['method'=>'reconcile','advance'=>1],
        ]]);
        $this->assertSame(
        	array(
        		'calls' => [['GET','KA-1'],['GET','KA-1']],
        		'status' => 'not_found',
        		'shipment status' => 'unknown',
        		'WooCommerce status' => 'unknown',
        		'rows status' => 'not_found',
        		'queries count' => 1,
        	),
        	array(
        		'calls' => $r['calls'],
        		'status' => $r['results'][1]['rows'][0]['status'],
        		'shipment status' => $r['results'][2]['rows'][0]['status'],
        		'WooCommerce status' => $r['results'][3]['rows'][0]['status'],
        		'rows status' => $r['results'][4]['rows'][0]['status'],
        		'queries count' => count( $r['queries'] ),
        	)
        );
        foreach ($r['options'] as $key=>$value) {
            $this->assertSame(
            	array(
            		'key format' => 1,
            		'value' => ['owner','expires'],
            	),
            	array(
            		'key format' => preg_match( '/^kiriof_instant_ops_throttle_[a-f0-9]{64}$/', $key ),
            		'value' => array_keys($value),
            	)
            );
        }
        $this->assertStringNotContainsString('private', json_encode($r['options']));
    }

    public function test_remote_identity_and_awb_mismatches_never_write(): void {
        foreach ([['order_id'=>'OTHER'], ['service'=>'grab_express'], ['service_type'=>'same_day'], ['tracking_code'=>'OTHER'], ['status'=>true], ['status'=>999]] as $extra) {
            $r = $this->fixture(['tracking'=>$this->tracking($extra)]);
            $this->assertSame(
            	array(
            		'status' => 'unknown',
            		'writes' => [],
            	),
            	array(
            		'status' => $r['results'][0]['rows'][0]['status'],
            		'writes' => $r['writes'],
            	)
            );
        }
    }

    public function test_authoritative_lifecycle_dates_and_stale_cas_cannot_regress_terminal_state(): void {
        foreach (['finished_at'=>['finished',200], 'canceled_at'=>['canceled',300]] as $date=>$expected) {
            $r = $this->fixture(['tracking'=>$this->tracking(['date'=>[$date=>'2025-01-01 10:00:00']])]);
            $this->assertSame(
            	array(
            		'status' => $expected[0],
            		'instant_status_code' => $expected[1],
            	),
            	array(
            		'status' => $r['rows']['KA-1']['status'],
            		'instant_status_code' => $r['rows']['KA-1']['instant_status_code'],
            	)
            );
        }
        $r = $this->fixture(['race'=>['status'=>'finished','instant_status_code'=>200], 'tracking'=>$this->tracking(['status'=>105])]);
        $this->assertSame(
        	array(
        		'status' => 'finished',
        		'instant_status_code' => 200,
        	),
        	array(
        		'status' => $r['rows']['KA-1']['status'],
        		'instant_status_code' => $r['rows']['KA-1']['instant_status_code'],
        	)
        );
    }

    public function test_driver_not_found_cannot_cancel_locally_or_after_provider_refresh(): void {
        foreach (array('gosend', 'grab_express') as $service) {
            foreach (array(101, '101') as $code) {
                $result = $this->fixture(array('row'=>array('service'=>$service, 'instant_status_code'=>$code), 'operations'=>array(array('method'=>'cancel'))));
                $this->assertSame(array(), $result['calls']);
                $this->assertSame(array(), $result['claims']);
                $this->assertSame($code, $result['rows']['KA-1']['instant_status_code']);
                $result = $this->fixture(array('row'=>array('service'=>$service), 'tracking'=>$this->tracking(array('service'=>$service, 'status'=>$code)), 'operations'=>array(array('method'=>'cancel'))));
                $this->assertSame(array(array('GET','KA-1')), $result['calls']);
                $this->assertSame(array(), $result['claims']);
                $this->assertSame(101, $result['rows']['KA-1']['instant_status_code']);
                $this->assertSame('request_pickup', $result['rows']['KA-1']['status']);
            }
        }
    }

    public function test_driver_not_found_webhook_during_get_cannot_be_overwritten_to_allow_delete(): void {
        foreach (['pending', 'request_pickup'] as $status) {
            foreach (['webhook_during_tracking', 'race'] as $race) {
                $r = $this->fixture(['row'=>['status'=>$status], $race=>['instant_status_code'=>101], 'tracking'=>$this->tracking(['status'=>105]), 'operations'=>[['method'=>'cancel']]]);
                $this->assertSame([['GET','KA-1']], $r['calls']);
                $this->assertSame(101, $r['rows']['KA-1']['instant_status_code']);
                $this->assertSame([], $r['claims']);
                $this->assertSame([], $r['woo']['notes']);
            }
        }
    }

    public function test_failed_delete_fence_survives_reconciliation_until_explicit_fresh_retry(): void {
        foreach ([['throw'=>true], ['cancel'=>['status'=>false]], ['cancel'=>['status'=>true,'operation_accepted'=>false,'data'=>['status'=>true,'code'=>0,'result'=>['packages'=>[]]]]]] as $failure) {
            $r = $this->fixture($failure + ['operations'=>[
                ['method'=>'cancel'],
                ['method'=>'reconcile','expire_report'=>true,'advance'=>91,'tracking'=>$this->tracking(['status'=>500])],
                ['method'=>'reconcile','expire_report'=>true,'expire_throttle'=>true,'tracking'=>$this->tracking(['status'=>105])],
                ['method'=>'cancel','expire_report'=>true,'advance'=>91],
            ]]);
            $this->assertSame([100,105], $r['delete_codes']);
            $this->assertCount(2, $r['delete_markers']);
            foreach ($r['delete_markers'] as $marker) { $this->assertStringStartsWith('Instant cancellation requires reconciliation.:', $marker); }
            $this->assertNotSame($r['delete_markers'][0], $r['delete_markers'][1]);
            $this->assertSame(105, $r['rows']['KA-1']['instant_status_code']);
            $this->assertSame($r['delete_markers'] ? end($r['delete_markers']) : 'Instant cancellation requires reconciliation.', $r['rows']['KA-1']['rejected_reason']);
            $this->assertSame([['GET','KA-1'],['DELETE','KA-1'],['GET','KA-1'],['GET','KA-1'],['GET','KA-1'],['DELETE','KA-1']], $r['calls']);
            $this->assertSame([], array_values(array_filter($r['writes'], static fn($write) => 350 === ($write['changes']['instant_status_code'] ?? null))));
            $this->assertSame('processing', $r['woo']['status']);
            $this->assertSame([], $r['woo']['notes']);
        }
    }

    public function test_driver_not_found_race_before_claim_never_sends_delete(): void {
        $result = $this->fixture(array('claim_race'=>array('instant_status_code'=>101), 'operations'=>array(array('method'=>'cancel'))));
        $this->assertSame(array(array('GET','KA-1')), $result['calls']);
        $this->assertSame(101, $result['rows']['KA-1']['instant_status_code']);
        $this->assertSame('request_pickup', $result['rows']['KA-1']['status']);
        $this->assertSame(array(), array_values(array_filter($result['writes'], static fn($write) => 350 === ($write['changes']['instant_status_code'] ?? null))));
    }

    public function test_cancel_refresh_blocks_pickup_and_uncertain_local_bookings(): void {
        $r = $this->fixture(['tracking'=>$this->tracking(['status'=>106]),'operations'=>[['method'=>'cancel']]]);
        $this->assertSame(
        	array(
        		'calls' => [['GET','KA-1']],
        		'status' => 'shipped',
        	),
        	array(
        		'calls' => $r['calls'],
        		'status' => $r['rows']['KA-1']['status'],
        	)
        );
        foreach ([['instant_payment_id'=>null], ['instant_status_code'=>350], ['status'=>'finished'], ['awb'=>null]] as $row) {
            $r = $this->fixture(['row'=>$row,'operations'=>[['method'=>'cancel']]]);
            $this->assertSame([], $r['calls']);
        }
    }

    public function test_cancel_acceptance_is_not_terminal_and_timeout_blocks_repeated_delete(): void {
        $r = $this->fixture(['operations'=>[['method'=>'cancel'],['method'=>'cancel']]]);
        $this->assertSame(
        	array(
        		'status' => 'cancel_requested',
        		'instant_status_code' => 350,
        		'shipment status' => 'request_pickup',
        		'calls' => [['GET','KA-1'],['DELETE','KA-1']],
        		'WooCommerce status' => 'processing',
        		'notes' => [],
        	),
        	array(
        		'status' => $r['results'][0]['rows'][0]['status'],
        		'instant_status_code' => $r['rows']['KA-1']['instant_status_code'],
        		'shipment status' => $r['rows']['KA-1']['status'],
        		'calls' => $r['calls'],
        		'WooCommerce status' => $r['woo']['status'],
        		'notes' => $r['woo']['notes'],
        	)
        );
        foreach ([['throw'=>true], ['cancel'=>['status'=>false,'data'=>'private address']]] as $input) {
            $r = $this->fixture($input + ['operations'=>[['method'=>'cancel'],['method'=>'cancel']]]);
            $this->assertSame(
            	array(
                    'instant_status_code' => 100,
            		'calls count' => 2,
            		'notes' => [],
            		'redacts private' => false,
            	),
            	array(
            		'instant_status_code' => $r['rows']['KA-1']['instant_status_code'],
            		'calls count' => count( $r['calls'] ),
            		'notes' => $r['woo']['notes'],
            		'redacts private' => str_contains( json_encode($r['results']), 'private' ),
            	)
            );
        }
    }

    public function test_failed_delete_preserves_each_last_confirmed_code(): void {
        foreach ([100, 105, 110] as $code) {
            foreach ([['throw'=>true], ['cancel'=>['status'=>false]], ['cancel'=>['status'=>true,'operation_accepted'=>true,'data'=>['status'=>false]]]] as $failure) {
                $r = $this->fixture($failure + ['row'=>['instant_status_code'=>$code], 'operations'=>[['method'=>'cancel'], ['method'=>'cancel','expire_report'=>true,'advance'=>91]]]);
                $this->assertSame($code, $r['rows']['KA-1']['instant_status_code']);
                $this->assertSame('Ready for Shipment', $r['rows']['KA-1']['fixture_presentation']['label']);
                $this->assertSame([$code], $r['delete_codes']);
                $this->assertCount(1, $r['delete_markers']);
        $this->assertStringStartsWith('Instant cancellation requires reconciliation.:', $r['delete_markers'][0]);
                $this->assertSame($r['delete_markers'] ? end($r['delete_markers']) : 'Instant cancellation requires reconciliation.', $r['rows']['KA-1']['rejected_reason']);
                $this->assertSame([['GET','KA-1'],['DELETE','KA-1'],['GET','KA-1']], $r['calls']);
                $this->assertSame('processing', $r['woo']['status']);
                $this->assertSame([], $r['woo']['notes']);
            }
        }
    }

    public function test_cancel_strict_identity_and_actual_terminal_confirmation(): void {
        foreach (['gosend','grab_express'] as $service) {
            foreach ([300, 302] as $code) {
                $cancel = ['status'=>true,'operation_accepted'=>true,'data'=>['status'=>true,'code'=>0,'result'=>['payment_id'=>'PAY-1','packages'=>[['order_id'=>'KA-1','service'=>$service,'status'=>$code]]]]];
                $r = $this->fixture(['row'=>['service'=>$service], 'tracking'=>$this->tracking(['service'=>$service]), 'cancel'=>$cancel,'operations'=>[['method'=>'cancel']]]);
                $this->assertSame($code, $r['rows']['KA-1']['instant_status_code']);
                $this->assertSame(
                    array(
                        'status' => 'canceled',
                        'shipment status' => 'processing',
                        'notes count' => 1,
                    ),
                    array(
                        'status' => $r['results'][0]['rows'][0]['status'],
                        'shipment status' => $r['woo']['status'],
                        'notes count' => count( $r['woo']['notes'] ),
                    )
                );
            }
        }
        $cancel['data']['result']['packages'][0]['order_id'] = 'OTHER';
        $r = $this->fixture(['cancel'=>$cancel,'tracking'=>$this->tracking([]),'operations'=>[['method'=>'cancel']]]);
        $this->assertSame(
        	array(
        		'status' => 'unknown',
                'instant_status_code' => 100,
        	),
        	array(
        		'status' => $r['results'][0]['rows'][0]['status'],
        		'instant_status_code' => $r['rows']['KA-1']['instant_status_code'],
        	)
        );
    }

    public function test_cancellation_claim_is_durable_before_delete_and_competitors_win(): void {
        $r = $this->fixture(['operations'=>[['method'=>'cancel'],['method'=>'cancel','expire_report'=>true,'advance'=>91]]]);
        $this->assertSame(
        	array(
                'delete_codes' => [100],
        		'claims' => [['KA-1',100]],
        		'calls' => [['GET','KA-1'],['DELETE','KA-1']],
        	),
        	array(
        		'delete_codes' => $r['delete_codes'],
        		'claims' => $r['claims'],
        		'calls' => $r['calls'],
        	)
        );
        $this->assertCount(1, $r['delete_markers']);
        $this->assertStringStartsWith('Instant cancellation requires reconciliation.:', $r['delete_markers'][0]);
        foreach ([['instant_status_code'=>350], ['instant_status_code'=>100,'rejected_reason'=>'Instant cancellation requires reconciliation.'], ['status'=>'shipped','instant_status_code'=>106]] as $race) {
            $r = $this->fixture(['claim_race'=>$race,'operations'=>[['method'=>'cancel']]]);
            $this->assertSame(
            	array(
            		'calls' => [['GET','KA-1']],
            		'delete_codes' => [],
            		'instant_status_code' => $race['instant_status_code'],
            		'status' => 'unknown',
            	),
            	array(
            		'calls' => $r['calls'],
            		'delete_codes' => $r['delete_codes'],
            		'instant_status_code' => $r['rows']['KA-1']['instant_status_code'],
            		'status' => $r['results'][0]['rows'][0]['status'],
            	)
            );
        }
    }

    public function test_dateless_tracking_preserves_pending_and_cancel_claim_without_woo_side_effects(): void {
        foreach ([['status'=>'pending','instant_status_code'=>null,'instant_payment_id'=>null,'awb'=>null], ['instant_status_code'=>350]] as $row) {
            $r = $this->fixture(['row'=>$row,'operations'=>[['method'=>'track']]]);
            $this->assertSame(
            	array(
            		'status' => 'tracked',
            		'instant_status_code' => $row['instant_status_code'],
            		'shipment status' => $row['status'] ?? 'request_pickup',
            		'notes' => [],
            		'WooCommerce status' => 'processing',
            	),
            	array(
            		'status' => $r['results'][0]['rows'][0]['status'],
            		'instant_status_code' => $r['rows']['KA-1']['instant_status_code'],
            		'shipment status' => $r['rows']['KA-1']['status'],
            		'notes' => $r['woo']['notes'],
            		'WooCommerce status' => $r['woo']['status'],
            	)
            );
        }
        foreach ([['status'=>100], ['status'=>false,'not_found'=>true], ['status'=>false,'data'=>['status'=>false,'code'=>2]]] as $remote) {
            $input = ['row'=>['instant_status_code'=>350]];
            $input['tracking'] = (isset($remote['not_found']) || isset($remote['data'])) ? $remote : $this->tracking($remote);
            $r = $this->fixture($input);
            $this->assertSame(350, $r['rows']['KA-1']['instant_status_code']);
        }
    }

    public function test_terminal_dates_are_strict_normalized_utc_and_explicit_codes_take_precedence(): void {
        foreach (['2025-01-01T10:00:00.123456Z', '2025-01-01T12:00:00+02:00'] as $date) {
            $r = $this->fixture(['tracking'=>$this->tracking(['date'=>['finished_at'=>$date]])]);
            $this->assertSame(
            	array(
            		'instant_status_code' => 200,
            		'finished_at' => '2025-01-01 10:00:00',
            	),
            	array(
            		'instant_status_code' => $r['rows']['KA-1']['instant_status_code'],
            		'finished_at' => $r['rows']['KA-1']['finished_at'],
            	)
            );
        }
        foreach (['2025-02-30 10:00:00', '2099-01-01 10:00:00', true, ['date'=>'2025-01-01 10:00:00']] as $date) {
            $r = $this->fixture(['tracking'=>$this->tracking(['date'=>['finished_at'=>$date]])]);
            $this->assertSame(
            	array(
            		'instant_status_code' => 100,
            		'status' => 'request_pickup',
            	),
            	array(
            		'instant_status_code' => $r['rows']['KA-1']['instant_status_code'],
            		'status' => $r['rows']['KA-1']['status'],
            	)
            );
        }
        $r = $this->fixture(['tracking'=>$this->tracking(['status'=>105,'date'=>['canceled_at'=>'2025-01-01 10:00:00']])]);
        $this->assertSame(105, $r['rows']['KA-1']['instant_status_code']);
    }

    public function test_malformed_or_mismatched_delete_response_cannot_clear_claim(): void {
        $base = ['order_id'=>'KA-1','service'=>'gosend','status'=>300];
        foreach ([['order_id'=>'OTHER'], ['service'=>'grab_express'], ['service_type'=>'same_day'], ['awb'=>'OTHER'], ['status'=>true], ['status'=>999]] as $extra) {
            $cancel = ['status'=>true,'operation_accepted'=>true,'data'=>['status'=>true,'code'=>0,'result'=>['packages'=>[array_replace($base,$extra)]]]];
            $r = $this->fixture(['cancel'=>$cancel,'operations'=>[['method'=>'cancel'],['method'=>'cancel','expire_report'=>true,'advance'=>91]]]);
            $this->assertSame(
            	array(
                    'instant_status_code' => 100,
            		'status' => 'unknown',
                    'delete_codes' => [100],
            		'notes' => [],
            	),
            	array(
            		'instant_status_code' => $r['rows']['KA-1']['instant_status_code'],
            		'status' => $r['results'][0]['rows'][0]['status'],
            		'delete_codes' => $r['delete_codes'],
            		'notes' => $r['woo']['notes'],
            	)
            );
        }
    }
    public function test_discarded_tracking_snapshot_never_authorizes_delete(): void {
        foreach (['webhook_during_tracking', 'race'] as $race) {
            foreach ([106, 200] as $code) {
                $r = $this->fixture([$race=>['instant_status_code'=>105], 'tracking'=>$this->tracking(['status'=>$code]), 'operations'=>[['method'=>'cancel']]]);
                $this->assertSame([['GET','KA-1']], $r['calls']);
                $this->assertSame(105, $r['rows']['KA-1']['instant_status_code']);
                $this->assertSame([], $r['claims']);
                $this->assertSame('unknown', $r['results'][0]['rows'][0]['status']);
            }
        }
    }

    public function test_inconsistent_success_cannot_invent_cancellation_progress(): void {
        foreach ([100, 101, 106, 110, 200, 500] as $code) {
            $r = $this->fixture(['cancel'=>['status'=>true,'operation_accepted'=>true,'data'=>['status'=>true,'code'=>0,'result'=>['packages'=>[['order_id'=>'KA-1','service'=>'gosend','status'=>$code]]]]], 'operations'=>[['method'=>'cancel']]]);
            $this->assertSame(100, $r['rows']['KA-1']['instant_status_code']);
            $this->assertSame('unknown', $r['results'][0]['rows'][0]['status']);
            $this->assertSame('request_pickup', $r['rows']['KA-1']['status']);
            $this->assertSame([], $r['woo']['notes']);
        }
    }


    public function test_late_delete_success_does_not_overwrite_a_shipped_callback(): void {
        $r = $this->fixture(['webhook_during_delete'=>['status'=>'shipped', 'instant_status_code'=>106], 'operations'=>[['method'=>'cancel']]]);
        $this->assertSame([['GET','KA-1'],['DELETE','KA-1']], $r['calls']);
        $this->assertSame(106, $r['rows']['KA-1']['instant_status_code']);
        $this->assertSame('shipped', $r['rows']['KA-1']['status']);
        $this->assertSame('unknown', $r['results'][0]['rows'][0]['status']);
        $this->assertSame([], array_values(array_filter($r['writes'], static fn($write) => 350 === ($write['changes']['instant_status_code'] ?? null))));
    }

    public function test_marker_ready_rows_allow_manual_retry_only_with_fresh_ready_lifecycle(): void {
        foreach ([100, 105, 110] as $code) {
            $r = $this->fixture(['row'=>['instant_status_code'=>$code, 'rejected_reason'=>'Instant cancellation requires reconciliation.'],
                'existing_claim'=>['owner'=>'earlier-cancel','expires_in'=>90], 'operations'=>[
                    ['method'=>'cancel'],
                    ['method'=>'cancel','advance'=>90,'tracking'=>$this->tracking(['status'=>$code])],
                    ['method'=>'cancel','advance'=>91],
                ]]);
            $this->assertSame('unknown', $r['results'][0]['rows'][0]['status']);
            $this->assertSame('cancel_requested', $r['results'][1]['rows'][0]['status']);
            $this->assertSame(InvalidArgumentException::class, $r['results'][2]['error']);
            $this->assertSame([['GET','KA-1'],['DELETE','KA-1']], $r['calls']);
            $this->assertSame([$code], $r['delete_codes']);
            $this->assertSame([['KA-1',$code]], $r['claims']);
            $release = array_values(array_filter($r['writes'], static fn($write) => $write['changes'] === ['rejected_reason'=>null]));
            $this->assertCount(1, $release);
            $this->assertSame(['order_id'=>'KA-1','delivery_type'=>'instant','status'=>'request_pickup','instant_status_code'=>$code,'instant_payment_id'=>'PAY-1','instant_payment_status'=>'paid','awb'=>'AWB-1','rejected_reason'=>'Instant cancellation requires reconciliation.'], $release[0]['condition']);
            $this->assertSame(350, $r['rows']['KA-1']['instant_status_code']);
        }
    }

    public function test_timeout_keeps_original_eligible_data_and_retries_after_91_seconds(): void {
        foreach ([100,105,110] as $code) {
            $r = $this->fixture(['row'=>['instant_status_code'=>$code], 'tracking'=>$this->tracking([]), 'operations'=>[
                ['method'=>'cancel','throw'=>true],
                ['method'=>'cancel'],
                ['method'=>'cancel','advance'=>91,'throw'=>false,'tracking'=>$this->tracking(['status'=>105])],
            ]]);
            $original = ['order_id'=>'KA-1','delivery_type'=>'instant','service'=>'gosend','service_name'=>'instant','status'=>'request_pickup','instant_status_code'=>$code,'instant_payment_id'=>'PAY-1','instant_payment_status'=>'paid','awb'=>'AWB-1','live_tracking_url'=>'','wp_wc_order_stat_order_id'=>1,'rejected_reason'=>'Instant cancellation requires reconciliation.'];
            $original['rejected_reason'] = $r['delete_markers'][0];
            $this->assertSame($original, $r['snapshots'][0]);
            $this->assertSame($r['snapshots'][0], $r['snapshots'][1]);
            $this->assertSame('unknown', $r['results'][0]['rows'][0]['status']);
            $this->assertSame('unknown', $r['results'][1]['rows'][0]['status']);
            $this->assertStringContainsString('90 seconds', $r['results'][1]['rows'][0]['message']);
            $this->assertSame('cancel_requested', $r['results'][2]['rows'][0]['status']);
            $this->assertSame([['GET','KA-1'],['DELETE','KA-1'],['GET','KA-1'],['DELETE','KA-1']], $r['calls']);
            $this->assertSame([$code,105], $r['delete_codes']);
            $this->assertCount(2, $r['delete_markers']);
            foreach ($r['delete_markers'] as $marker) { $this->assertStringStartsWith('Instant cancellation requires reconciliation.:', $marker); }
            $this->assertNotSame($r['delete_markers'][0], $r['delete_markers'][1]);
            $this->assertSame($r['initial_now'] + 181, array_values($r['options'])[0]['expires']);
        }
    }

    public function test_fenced_retry_rejects_metadata_identity_mismatch_and_nonready_lifecycle(): void {
        foreach ([[], ['order_id'=>'OTHER','status'=>105], ['tracking_code'=>'OTHER','status'=>105], ['status'=>101], ['status'=>106], ['status'=>350]] as $remote) {
            $r = $this->fixture(['row'=>['rejected_reason'=>'Instant cancellation requires reconciliation.'], 'operations'=>[
                ['method'=>'cancel','tracking'=>$this->tracking($remote)],
            ]]);
            $this->assertSame([['GET','KA-1']], $r['calls']);
            $this->assertSame([], $r['claims']);
            $this->assertSame([], $r['delete_codes']);
            $this->assertSame($r['delete_markers'] ? end($r['delete_markers']) : 'Instant cancellation requires reconciliation.', $r['rows']['KA-1']['rejected_reason']);
            $this->assertSame('unknown', $r['results'][0]['rows'][0]['status']);
        }
    }

    public function test_definitive_rejection_releases_snapshot_fence_and_next_explicit_retry_succeeds(): void {
        foreach ([100,105,110] as $code) {
            $r = $this->fixture(['row'=>['instant_status_code'=>$code], 'operations'=>[
                ['method'=>'cancel','cancel'=>['status'=>false,'operation_rejected'=>true,'data'=>'private address'], 'diagnostics'=>['reason'=>'api_rejection','http_status'=>422]],
                ['method'=>'cancel','advance'=>91,'cancel'=>['status'=>true,'operation_accepted'=>true,'data'=>['status'=>true,'code'=>0,'result'=>['payment_id'=>'PAY-1','packages'=>[['order_id'=>'KA-1','service'=>'gosend','status'=>105]]]]]],
            ]]);
            $this->assertSame('cancel_rejected', $r['results'][0]['rows'][0]['status']);
            $this->assertSame($code, $r['snapshots'][0]['instant_status_code']);
            $this->assertNull($r['snapshots'][0]['rejected_reason']);
            $this->assertSame('request_pickup', $r['snapshots'][0]['status']);
            $this->assertSame('cancel_requested', $r['results'][1]['rows'][0]['status']);
            $this->assertSame([$code,$code], $r['delete_codes']);
            $this->assertSame('warning', $r['logs'][0]['level']);
            $this->assertSame('cancel_rejected', $r['logs'][0]['context']['outcome']);
            $this->assertSame('api_rejection', $r['logs'][0]['context']['reason']);
            $this->assertSame(422, $r['logs'][0]['context']['http_status']);
            $this->assertSame('info', $r['logs'][1]['level']);
            $this->assertSame([], $r['woo']['notes']);
        }
    }

    public function test_paid_and_awb_eligibility_is_enforced_before_network_and_again_at_claim(): void {
        foreach ([['awb'=>null], ['awb'=>''], ['instant_payment_status'=>'pending'], ['instant_payment_status'=>'refunded']] as $row) {
            $r = $this->fixture(['row'=>$row,'operations'=>[['method'=>'cancel']]]);
            $this->assertSame([], $r['calls']);
            $this->assertSame(InvalidArgumentException::class, $r['results'][0]['error']);
            $this->assertSame('ineligible', $r['logs'][0]['context']['outcome']);
            $this->assertFalse($r['logs'][0]['context']['submitted']);
            $r = $this->fixture(['claim_race'=>$row,'operations'=>[['method'=>'cancel']]]);
            $this->assertSame([['GET','KA-1']], $r['calls']);
            $this->assertSame('claim_conflict', $r['logs'][0]['context']['outcome']);
        }
    }

    public function test_concurrent_cancel_cannot_bypass_90_second_get_delete_lease(): void {
        foreach ([0,41,89] as $advance) {
            $r = $this->fixture(['concurrent'=>['method'=>'cancel','advance'=>$advance], 'operations'=>[['method'=>'cancel']]]);
            $this->assertSame([['GET','KA-1'],['DELETE','KA-1']], $r['calls']);
            $this->assertSame('unknown', $r['nested_results'][0]['rows'][0]['status']);
            $this->assertSame($r['initial_now'] + 90, array_values($r['options'])[0]['expires']);
            $this->assertSame('cooldown', $r['logs'][0]['context']['outcome']);
            $this->assertSame('cancel_requested', $r['results'][0]['rows'][0]['status']);
        }
    }

    public function test_unknown_failure_logs_only_safe_diagnostic_whitelist(): void {
        foreach ([['cancel'=>['status'=>false,'data'=>'private address']], ['throw'=>true]] as $failure) {
            $r = $this->fixture($failure + ['operations'=>[['method'=>'cancel']]]);
            $this->assertCount(1, $r['logs']);
            $log = $r['logs'][0];
            $this->assertSame('warning', $log['level']);
            $this->assertSame('kiriminaja_instant', $log['channel']);
            $this->assertSame('Instant cancellation diagnostics.', $log['message']);
            $expected = ['operation'=>'instant_cancel','outcome'=>'unknown','order_reference'=>substr(hash('sha256','KA-1'),0,16),'submitted'=>true,'backtrace'=>false];
            if (!isset($failure['throw'])) { $expected += ['reason'=>'transport_failure','http_status'=>504,'elapsed_ms'=>25000]; }
            $this->assertSame($expected, $log['context']);
            foreach (['private','08123456789','secret-token','KA-1','PAY-1','AWB-1'] as $secret) {
                $this->assertStringNotContainsString($secret, json_encode($r['logs']));
            }
            $this->assertSame(100, $r['rows']['KA-1']['instant_status_code']);
            $this->assertSame($r['delete_markers'] ? end($r['delete_markers']) : 'Instant cancellation requires reconciliation.', $r['rows']['KA-1']['rejected_reason']);
        }
    }

    public function test_logging_failure_does_not_change_success_or_repeat_delete(): void {
        $r = $this->fixture(['log_throw'=>true, 'operations'=>[['method'=>'cancel'],['method'=>'cancel','advance'=>91]]]);
        $this->assertSame('cancel_requested', $r['results'][0]['rows'][0]['status']);
        $this->assertSame(350, $r['rows']['KA-1']['instant_status_code']);
        $this->assertSame([['GET','KA-1'],['DELETE','KA-1']], $r['calls']);
        $this->assertSame('info', $r['logs'][0]['level']);
    }

    public function test_callback_racing_fence_release_blocks_retry_or_rejection_release(): void {
        foreach ([['status'=>'shipped','instant_status_code'=>106], ['instant_payment_status'=>'refunded'], ['awb'=>'REPLACEMENT']] as $race) {
            $r = $this->fixture(['row'=>['rejected_reason'=>'Instant cancellation requires reconciliation.'], 'release_race'=>$race,
                'tracking'=>$this->tracking(['status'=>105]), 'operations'=>[['method'=>'cancel']]]);
            $this->assertSame([['GET','KA-1']], $r['calls']);
            $this->assertSame([], $r['claims']);
            $this->assertSame('retry_unconfirmed', $r['logs'][0]['context']['outcome']);
            $this->assertSame($r['delete_markers'] ? end($r['delete_markers']) : 'Instant cancellation requires reconciliation.', $r['rows']['KA-1']['rejected_reason']);
            foreach ($race as $key=>$value) { $this->assertSame($value, $r['rows']['KA-1'][$key]); }
            $r = $this->fixture(['release_race'=>$race,'cancel'=>['status'=>false,'operation_rejected'=>true], 'operations'=>[['method'=>'cancel']]]);
            $this->assertSame('unknown', $r['results'][0]['rows'][0]['status']);
            $this->assertSame($r['delete_markers'] ? end($r['delete_markers']) : 'Instant cancellation requires reconciliation.', $r['rows']['KA-1']['rejected_reason']);
        }
    }

    public function test_diagnostics_reject_invalid_fact_types_and_respect_not_submitted(): void {
        $r = $this->fixture(['cancel'=>['status'=>false], 'diagnostics'=>[
            'reason'=>'private provider failure', 'http_status'=>'504', 'elapsed_ms'=>-1, 'submitted'=>false,
        ], 'operations'=>[['method'=>'cancel']]]);
        $this->assertSame([
            'operation'=>'instant_cancel', 'outcome'=>'unknown', 'order_reference'=>substr(hash('sha256','KA-1'),0,16),
            'submitted'=>false, 'backtrace'=>false,
        ], $r['logs'][0]['context']);
        $this->assertSame($r['delete_markers'] ? end($r['delete_markers']) : 'Instant cancellation requires reconciliation.', $r['rows']['KA-1']['rejected_reason']);
        $this->assertSame(100, $r['rows']['KA-1']['instant_status_code']);
    }

    public function test_expired_delete_cannot_clear_or_overwrite_new_attempt_with_identical_business_snapshot(): void {
        foreach ([['status'=>true,'operation_accepted'=>true,'data'=>['status'=>true,'code'=>0,'result'=>['payment_id'=>'PAY-1','packages'=>[['order_id'=>'KA-1','service'=>'gosend','status'=>300]]]]], ['status'=>false,'operation_rejected'=>true]] as $response) {
            $r = $this->fixture(['tracking'=>$this->tracking(['status'=>105]), 'replacement_during_delete'=>true,
                'cancel'=>$response, 'operations'=>[['method'=>'cancel']]]);
            $this->assertSame([['GET','KA-1'],['DELETE','KA-1'],['GET','KA-1'],['DELETE','KA-1']], $r['calls']);
            $this->assertSame(['Instant cancellation requires reconciliation.:fixture-owner-1', 'Instant cancellation requires reconciliation.:fixture-owner-2'], $r['delete_markers']);
            $this->assertSame('Instant cancellation requires reconciliation.:fixture-owner-2', $r['rows']['KA-1']['rejected_reason']);
            $this->assertSame(105, $r['rows']['KA-1']['instant_status_code']);
            $this->assertSame('request_pickup', $r['rows']['KA-1']['status']);
            $this->assertSame('unknown', $r['results'][0]['rows'][0]['status']);
            $this->assertSame('unknown', $r['nested_results'][0]['rows'][0]['status']);
            $this->assertSame('response_superseded', end($r['logs'])['context']['outcome']);
            $this->assertSame([], $r['woo']['notes']);
            $releases = array_values(array_filter($r['writes'], static fn($write)=>$write['changes'] === ['rejected_reason'=>null]));
            $this->assertCount(1, $releases);
            $this->assertSame($r['delete_markers'][0], $releases[0]['condition']['rejected_reason']);
            $this->assertCount(1, $r['cache']);
            $this->assertSame('unknown', array_values($r['cache'])[0]['value']['status']);
        }
    }

    public function test_expiry_without_replacement_also_revokes_write_authority(): void {
        $r = $this->fixture(['expire_during_tracking'=>true, 'tracking'=>$this->tracking(['status'=>105]), 'operations'=>[['method'=>'cancel']]]);
        $this->assertSame([['GET','KA-1']], $r['calls']);
        $this->assertSame([], $r['claims']);
        foreach ([['status'=>false,'operation_rejected'=>true], ['status'=>true,'operation_accepted'=>true,'data'=>['status'=>true,'code'=>0,'result'=>['packages'=>[['order_id'=>'KA-1','service'=>'gosend','status'=>300]]]]]] as $response) {
            $r = $this->fixture(['expire_during_delete'=>true, 'cancel'=>$response, 'operations'=>[['method'=>'cancel']]]);
            $this->assertSame(100, $r['rows']['KA-1']['instant_status_code']);
            $this->assertSame($r['delete_markers'][0], $r['rows']['KA-1']['rejected_reason']);
            $this->assertSame('unknown', $r['results'][0]['rows'][0]['status']);
            $this->assertSame([], $r['cache']);
        }
    }

    public function test_synthetic_400_gosend_rejection_forwards_safe_provider_diagnostics(): void {
        $r = $this->fixture(['cancel'=>['status'=>false,'operation_rejected'=>true,'data'=>['message'=>'RAW PRIVATE BODY']],
            'diagnostics'=>['reason'=>'api_rejection','http_status'=>400,'provider_code'=>2,'provider_message'=>'cannot cancel gosend'],
            'operations'=>[['method'=>'cancel']]]);
        $report = $r['results'][0]['rows'][0];
        $context = $r['logs'][0]['context'];
        $this->assertSame('cancel_rejected', $report['status']);
        $this->assertSame('cannot cancel gosend', $report['rejection_reason']);
        $this->assertSame(2, $context['provider_code']);
        $this->assertSame('cannot cancel gosend', $context['provider_message']);
        $this->assertSame(400, $context['http_status']);
        $this->assertStringNotContainsString('RAW PRIVATE BODY', json_encode([$r['logs'], $r['results'], $r['cache']]));
        $this->assertNull($r['rows']['KA-1']['rejected_reason']);
    }

    public function test_provider_text_is_redacted_again_for_local_identities_and_never_forwarded_for_other_reasons(): void {
        $text = '<b>Cannot cancel</b> KA-1 PAY-1 AWB-1 token=secret-token driver@example.test https://private.test/?key=abc 08123456789';
        $r = $this->fixture(['cancel'=>['status'=>false,'operation_rejected'=>true],
            'diagnostics'=>['reason'=>'api_rejection','provider_code'=>'2','provider_message'=>$text], 'operations'=>[['method'=>'cancel']]]);
        $safe = $r['results'][0]['rows'][0]['rejection_reason'];
        $this->assertStringContainsString('Cannot cancel', $safe);
        $this->assertSame($safe, $r['logs'][0]['context']['provider_message']);
        foreach (['KA-1','PAY-1','AWB-1','secret-token','driver@example.test','private.test','08123456789','<b>'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode([$r['logs'], $safe]));
        }
        foreach (['transport_failure','invalid_response','request_not_submitted','accepted','untrusted'] as $reason) {
            $r = $this->fixture(['cancel'=>['status'=>false,'operation_rejected'=>true],
                'diagnostics'=>['reason'=>$reason,'provider_code'=>2,'provider_message'=>$text], 'operations'=>[['method'=>'cancel']]]);
            $this->assertArrayNotHasKey('provider_code', $r['logs'][0]['context']);
            $this->assertArrayNotHasKey('provider_message', $r['logs'][0]['context']);
            $this->assertArrayNotHasKey('rejection_reason', $r['results'][0]['rows'][0]);
        }
        foreach ([null, [], true, 2.5, '02', '+2', '2.0', str_repeat('2',65)] as $code) {
            $r = $this->fixture(['cancel'=>['status'=>false,'operation_rejected'=>true],
                'diagnostics'=>['reason'=>'api_rejection','provider_code'=>$code,'provider_message'=>['private']], 'operations'=>[['method'=>'cancel']]]);
            $this->assertArrayNotHasKey('provider_code', $r['logs'][0]['context']);
            $this->assertArrayNotHasKey('provider_message', $r['logs'][0]['context']);
            $this->assertArrayNotHasKey('rejection_reason', $r['results'][0]['rows'][0]);
        }
    }

}
