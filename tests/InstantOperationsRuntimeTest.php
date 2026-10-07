<?php

declare(strict_types=1);

// Isolated fixture exercises the real monotonic state merger, never an SDK/network.
if (in_array('--fixture', $argv ?? [], true)) {
    define('ABSPATH', __DIR__);
    function __($text, $domain) { return $text; }
    function esc_html__($text, $domain) { return $text; }
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
    function wc_get_order($id) { return $GLOBALS['woo']; }
    eval('namespace KiriminAjaOfficial\\Repositories;
        class TransactionRepository {
            public $rows = []; public $writes = []; public $race; public $claimRace; public $claims = [];
            public function getTransactionByOrderId($id) { return isset($this->rows[$id]) ? clone $this->rows[$id] : false; }
            public function claimInstantCancellation(string $id, int $previous_code): bool {
                $this->claims[] = [$id, $previous_code];
                if ($this->claimRace !== null) { foreach ($this->claimRace as $key => $value) { $this->rows[$id]->{$key} = $value; } $this->claimRace = null; }
                $row = $this->rows[$id] ?? null;
                if (!$row || $row->delivery_type !== "instant" || !in_array($row->service, ["gosend","grab_express"], true)
                    || !in_array($row->status, ["pending","request_pickup"], true)
                    || !in_array($previous_code, [100,101,105,110], true)
                    || (int) $row->instant_status_code !== $previous_code || empty($row->instant_payment_id)
                    || $row->instant_payment_status === "refunded") { return false; }
                $row->instant_status_code = 350;
                $row->rejected_reason = "Instant cancellation requires reconciliation.";
                $this->writes[] = ["condition"=>["order_id"=>$id,"instant_status_code"=>$previous_code], "changes"=>["instant_status_code"=>350,"rejected_reason"=>"Instant cancellation requires reconciliation."]];
                return true;
            }
            public function compareAndSwapInstant(string $id, array $condition, array $changes): bool {
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
            public $calls = []; public $deleteCodes = []; public $tracking; public $cancellation; public $throw = false; public $onTracking;
            public function tracking($id) { $this->calls[] = ["GET",$id]; if ($this->onTracking !== null) { $hook = $this->onTracking; $this->onTracking = null; $hook(); } return $this->tracking; }
            public function cancel($id) { $this->calls[] = ["DELETE",$id]; $this->deleteCodes[] = $GLOBALS["repo"]->rows[$id]->instant_status_code; if ($this->throw) { throw new \\RuntimeException("private driver address 08123456789"); } return $this->cancellation; }
            public function book($payload) { throw new \\LogicException("Booking forbidden"); }
        }');
    require __DIR__ . '/../inc/Services/TransactionDeliveryType.php';
    require __DIR__ . '/../inc/Services/InstantShipmentState.php';
    require __DIR__ . '/../inc/Services/InstantOperationsService.php';
    $input = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
    $GLOBALS['now'] = time();
    $GLOBALS['initial_now'] = $GLOBALS['now'];
    $GLOBALS['owner_sequence'] = 0;
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
    $api = new KiriminAjaOfficial\Repositories\InstantDeliveryApiRepository();
    $tracking = ['status'=>true,'data'=>['status'=>true,'code'=>0,'result'=>['order_id'=>'KA-1','service'=>'gosend','service_type'=>'instant','tracking_code'=>'AWB-1','live_tracking_url'=>'https://tracking.example.test/KA-1','date'=>['allocated_at'=>'2025-01-01 10:00:00'],'driver'=>['name'=>'private driver','phone'=>'08123456789'],'destination'=>['address'=>'private address']]]];
    $cancel = ['status'=>true,'operation_accepted'=>true,'data'=>['status'=>true,'code'=>0,'result'=>['payment_id'=>'PAY-1','packages'=>[['order_id'=>'KA-1','service'=>'gosend','status'=>105]]]]];
    $api->tracking = $input['tracking'] ?? $tracking;
    $api->cancellation = $input['cancel'] ?? $cancel;
    foreach (['tracking','cancellation'] as $field) { if (isset($api->{$field}['data']) && is_array($api->{$field}['data'])) { $api->{$field}['data'] = json_decode(json_encode($api->{$field}['data'])); } }
    $api->throw = $input['throw'] ?? false;
    $service = new KiriminAjaOfficial\Services\InstantOperationsService($repo, $api, new KiriminAjaOfficial\Services\InstantShipmentState($repo));
    $results = [];
    $nestedResults = [];
    if (isset($input['concurrent'])) {
        $api->onTracking = static function () use ($service, $input, &$nestedResults) {
            $GLOBALS['now'] += $input['concurrent']['advance'] ?? 0;
            $nestedResults[] = $service->{$input['concurrent']['method']}(['KA-1']);
        };
    }
    foreach ($input['operations'] ?? [['method'=>'reconcile']] as $operation) {
        $GLOBALS['now'] += $operation['advance'] ?? 0;
        if ($operation['expire_throttle'] ?? false) { $GLOBALS['now'] += 41; }
        if ($operation['expire_report'] ?? false) { foreach (array_keys($GLOBALS['cache']) as $key) { if (str_contains($key, '_report_')) { unset($GLOBALS['cache'][$key]); } } }
        try { $results[] = $service->{$operation['method']}($operation['ids'] ?? ['KA-1']); }
        catch (Throwable $error) { $results[] = ['error'=>get_class($error)]; }
    }
    echo json_encode(['results'=>$results,'rows'=>$repo->rows,'writes'=>$repo->writes,'calls'=>$api->calls,'claims'=>$repo->claims,'delete_codes'=>$api->deleteCodes,'cache'=>$GLOBALS['cache'],'options'=>array_map('unserialize', $GLOBALS['options']),'queries'=>$GLOBALS['wpdb']->queries,'cache_deletes'=>$GLOBALS['cache_deletes'],'now'=>$GLOBALS['now'],'initial_now'=>$GLOBALS['initial_now'],'nested_results'=>$nestedResults,'woo'=>$GLOBALS['woo']], JSON_THROW_ON_ERROR);
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
        $this->assertSame('reconciled', $r['results'][0]['rows'][0]['status']);
        $this->assertSame('AWB-1', $r['rows']['KA-1']['awb']);
        $this->assertNull($r['rows']['KA-1']['instant_payment_id']);
        $this->assertSame('pending', $r['rows']['KA-1']['instant_payment_status']);
        $this->assertNull($r['rows']['KA-1']['instant_status_code']);
        $this->assertSame('pending', $r['rows']['KA-1']['status']);
        $this->assertSame([['GET','KA-1']], $r['calls']);
        $this->assertStringNotContainsString('private', json_encode($r['results']) . json_encode($r['cache']));
    }

    public function test_not_found_keeps_unknown_claim_untouched_and_never_rebooks(): void {
        $r = $this->fixture(['row'=>['status'=>'pending','instant_status_code'=>null,'rejected_reason'=>'Check remote state before retrying'],'tracking'=>['status'=>false,'not_found'=>true,'data'=>'private address']]);
        $this->assertSame('not_found', $r['results'][0]['rows'][0]['status']);
        $this->assertSame('pending', $r['rows']['KA-1']['status']);
        $this->assertSame('Check remote state before retrying', $r['rows']['KA-1']['rejected_reason']);
        $this->assertSame([], $r['writes']);
    }

    public function test_selection_is_strict_and_batch_validation_precedes_network(): void {
        foreach ([[], ['KA-1','KA-1'], [1], ['../KA-1'], ['KA-1','missing'], array_fill(0, 11, 'KA-1')] as $ids) {
            $r = $this->fixture(['operations'=>[['method'=>'reconcile','ids'=>$ids]]]);
            $this->assertSame(InvalidArgumentException::class, $r['results'][0]['error']);
            $this->assertSame([], $r['calls']);
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
        $this->assertSame([], $r['calls']);
        $this->assertSame('tracked', $r['results'][0]['rows'][0]['status']);
        $r = $this->fixture(['operations'=>[['method'=>'reconcile'],['method'=>'track'],['method'=>'reconcile'],['method'=>'reconcile','expire_report'=>true]]]);
        $this->assertCount(1, $r['calls']);
        $this->assertSame('unknown', $r['results'][3]['rows'][0]['status']);
        foreach ($r['cache'] as $entry) { $this->assertSame(10, $entry['ttl']); }
        $this->assertSame($r['initial_now'] + 40, array_values($r['options'])[0]['expires']);
        foreach (['javascript:alert(1)', 'https://user:pass@example.test/', "https://example.test/\nsecret"] as $url) {
            $r = $this->fixture(['tracking'=>$this->tracking(['live_tracking_url'=>$url])]);
            $this->assertSame('', $r['results'][0]['rows'][0]['tracking_url']);
        }
    }

    public function test_atomic_insert_loser_and_unknown_expiry_fail_closed(): void {
        foreach (['legacy-owner', ['owner'=>'old'], ['owner'=>'old','expires'=>'1'], ['expires'=>1], ['owner'=>'active','expires_in'=>40]] as $claim) {
            $r = $this->fixture(['existing_claim'=>$claim, 'operations'=>[['method'=>'reconcile'], ['method'=>'cancel']]]);
            $this->assertSame([], $r['calls']);
            $this->assertSame([], $r['queries']);
            $this->assertSame([], $r['claims']);
        }
        $r = $this->fixture(['add_race'=>['owner'=>'winner','expires_in'=>40]]);
        $this->assertSame([], $r['calls']);
        $this->assertSame('winner', array_values($r['options'])[0]['owner']);
        $this->assertSame([], $r['cache_deletes']);
    }

    public function test_active_owner_blocks_concurrent_modes_even_after_transport_timeout(): void {
        foreach (['track','reconcile','cancel'] as $method) {
            $r = $this->fixture(['concurrent'=>['method'=>$method,'advance'=>26]]);
            $this->assertSame([['GET','KA-1']], $r['calls']);
            $this->assertSame('unknown', $r['nested_results'][0]['rows'][0]['status']);
            $this->assertSame([], $r['claims']);
            $this->assertSame([], $r['queries']);
        }
        $r = $this->fixture(['operations'=>[['method'=>'cancel']], 'concurrent'=>['method'=>'cancel','advance'=>26]]);
        $this->assertSame([['GET','KA-1'],['DELETE','KA-1']], $r['calls']);
        $this->assertSame('unknown', $r['nested_results'][0]['rows'][0]['status']);
        $this->assertSame([350], $r['delete_codes']);
        $this->assertCount(1, $r['claims']);
    }

    public function test_expired_owner_cleanup_is_exact_and_preserves_replacement_owner(): void {
        $expired = ['owner'=>'expired','expires_in'=>-1];
        $r = $this->fixture(['existing_claim'=>$expired]);
        $this->assertSame([['GET','KA-1']], $r['calls']);
        $this->assertCount(1, $r['queries']);
        $this->assertSame(serialize(['owner'=>'expired','expires'=>$r['initial_now'] - 1]), $r['queries'][0][1]);
        $this->assertSame([[$r['queries'][0][0], 'options']], $r['cache_deletes']);
        $this->assertSame('fixture-owner-1', array_values($r['options'])[0]['owner']);
        $this->assertSame($r['initial_now'] + 40, array_values($r['options'])[0]['expires']);
        foreach (['reconcile','cancel'] as $method) {
            $r = $this->fixture(['existing_claim'=>$expired, 'delete_race'=>['owner'=>'replacement','expires_in'=>40], 'operations'=>[['method'=>$method]]]);
            $this->assertSame([], $r['calls']);
            $this->assertSame([], $r['cache_deletes']);
            $this->assertSame([], $r['claims']);
            $this->assertSame('replacement', array_values($r['options'])[0]['owner']);
        }
    }

    public function test_report_cache_expires_before_cooldown_and_only_expired_claim_is_reclaimed(): void {
        $r = $this->fixture(['tracking'=>['status'=>false,'not_found'=>true], 'operations'=>[
            ['method'=>'reconcile'], ['method'=>'track','advance'=>9], ['method'=>'reconcile','advance'=>2],
            ['method'=>'reconcile','advance'=>28], ['method'=>'reconcile','advance'=>1],
        ]]);
        $this->assertSame([['GET','KA-1'],['GET','KA-1']], $r['calls']);
        $this->assertSame('not_found', $r['results'][1]['rows'][0]['status']);
        $this->assertSame('unknown', $r['results'][2]['rows'][0]['status']);
        $this->assertSame('unknown', $r['results'][3]['rows'][0]['status']);
        $this->assertSame('not_found', $r['results'][4]['rows'][0]['status']);
        $this->assertCount(1, $r['queries']);
        foreach ($r['options'] as $key=>$value) {
            $this->assertMatchesRegularExpression('/^kiriof_instant_ops_throttle_[a-f0-9]{64}$/', $key);
            $this->assertSame(['owner','expires'], array_keys($value));
        }
        $this->assertStringNotContainsString('private', json_encode($r['options']));
    }

    public function test_remote_identity_and_awb_mismatches_never_write(): void {
        foreach ([['order_id'=>'OTHER'], ['service'=>'grab_express'], ['service_type'=>'same_day'], ['tracking_code'=>'OTHER'], ['status'=>true], ['status'=>999]] as $extra) {
            $r = $this->fixture(['tracking'=>$this->tracking($extra)]);
            $this->assertSame('unknown', $r['results'][0]['rows'][0]['status']);
            $this->assertSame([], $r['writes']);
        }
    }

    public function test_authoritative_lifecycle_dates_and_stale_cas_cannot_regress_terminal_state(): void {
        foreach (['finished_at'=>['finished',200], 'canceled_at'=>['canceled',300]] as $date=>$expected) {
            $r = $this->fixture(['tracking'=>$this->tracking(['date'=>[$date=>'2025-01-01 10:00:00']])]);
            $this->assertSame($expected[0], $r['rows']['KA-1']['status']);
            $this->assertSame($expected[1], $r['rows']['KA-1']['instant_status_code']);
        }
        $r = $this->fixture(['race'=>['status'=>'finished','instant_status_code'=>200], 'tracking'=>$this->tracking(['status'=>105])]);
        $this->assertSame('finished', $r['rows']['KA-1']['status']);
        $this->assertSame(200, $r['rows']['KA-1']['instant_status_code']);
    }

    public function test_cancel_refresh_blocks_pickup_and_uncertain_local_bookings(): void {
        $r = $this->fixture(['tracking'=>$this->tracking(['status'=>106]),'operations'=>[['method'=>'cancel']]]);
        $this->assertSame([['GET','KA-1']], $r['calls']);
        $this->assertSame('shipped', $r['rows']['KA-1']['status']);
        foreach ([['instant_payment_id'=>null], ['instant_status_code'=>350], ['status'=>'finished'], ['awb'=>null]] as $row) {
            $r = $this->fixture(['row'=>$row,'operations'=>[['method'=>'cancel']]]);
            $this->assertSame([], $r['calls']);
        }
    }

    public function test_cancel_acceptance_is_not_terminal_and_timeout_blocks_repeated_delete(): void {
        $r = $this->fixture(['operations'=>[['method'=>'cancel'],['method'=>'cancel']]]);
        $this->assertSame('cancel_requested', $r['results'][0]['rows'][0]['status']);
        $this->assertSame(350, $r['rows']['KA-1']['instant_status_code']);
        $this->assertSame('request_pickup', $r['rows']['KA-1']['status']);
        $this->assertSame([['GET','KA-1'],['DELETE','KA-1']], $r['calls']);
        $this->assertSame('processing', $r['woo']['status']);
        $this->assertSame([], $r['woo']['notes']);
        foreach ([['throw'=>true], ['cancel'=>['status'=>false,'data'=>'private address']]] as $input) {
            $r = $this->fixture($input + ['operations'=>[['method'=>'cancel'],['method'=>'cancel']]]);
            $this->assertSame(350, $r['rows']['KA-1']['instant_status_code']);
            $this->assertCount(2, $r['calls']);
            $this->assertSame([], $r['woo']['notes']);
            $this->assertStringNotContainsString('private', json_encode($r['results']));
        }
    }

    public function test_cancel_strict_identity_and_actual_terminal_confirmation(): void {
        foreach (['gosend','grab_express'] as $service) {
            $cancel = ['status'=>true,'operation_accepted'=>true,'data'=>['status'=>true,'code'=>0,'result'=>['payment_id'=>'PAY-1','packages'=>[['order_id'=>'KA-1','service'=>$service,'status'=>300]]]]];
            $r = $this->fixture(['row'=>['service'=>$service], 'tracking'=>$this->tracking(['service'=>$service]), 'cancel'=>$cancel,'operations'=>[['method'=>'cancel']]]);
            $this->assertSame('canceled', $r['results'][0]['rows'][0]['status']);
            $this->assertSame('processing', $r['woo']['status']);
            $this->assertCount(1, $r['woo']['notes']);
        }
        $cancel['data']['result']['packages'][0]['order_id'] = 'OTHER';
        $r = $this->fixture(['cancel'=>$cancel,'tracking'=>$this->tracking([]),'operations'=>[['method'=>'cancel']]]);
        $this->assertSame('unknown', $r['results'][0]['rows'][0]['status']);
        $this->assertSame(350, $r['rows']['KA-1']['instant_status_code']);
    }

    public function test_cancellation_claim_is_durable_before_delete_and_competitors_win(): void {
        $r = $this->fixture(['operations'=>[['method'=>'cancel'],['method'=>'cancel','expire_report'=>true,'expire_throttle'=>true]]]);
        $this->assertSame([350], $r['delete_codes']);
        $this->assertSame([['KA-1',100]], $r['claims']);
        $this->assertSame([['GET','KA-1'],['DELETE','KA-1']], $r['calls']);
        foreach ([['instant_status_code'=>350], ['status'=>'shipped','instant_status_code'=>106]] as $race) {
            $r = $this->fixture(['claim_race'=>$race,'operations'=>[['method'=>'cancel']]]);
            $this->assertSame([['GET','KA-1']], $r['calls']);
            $this->assertSame([], $r['delete_codes']);
            $this->assertSame($race['instant_status_code'], $r['rows']['KA-1']['instant_status_code']);
            $this->assertSame('unknown', $r['results'][0]['rows'][0]['status']);
        }
    }

    public function test_dateless_tracking_preserves_pending_and_cancel_claim_without_woo_side_effects(): void {
        foreach ([['status'=>'pending','instant_status_code'=>null,'instant_payment_id'=>null,'awb'=>null], ['instant_status_code'=>350]] as $row) {
            $r = $this->fixture(['row'=>$row,'operations'=>[['method'=>'track']]]);
            $this->assertSame('tracked', $r['results'][0]['rows'][0]['status']);
            $this->assertSame($row['instant_status_code'], $r['rows']['KA-1']['instant_status_code']);
            $this->assertSame($row['status'] ?? 'request_pickup', $r['rows']['KA-1']['status']);
            $this->assertSame([], $r['woo']['notes']);
            $this->assertSame('processing', $r['woo']['status']);
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
            $this->assertSame(200, $r['rows']['KA-1']['instant_status_code']);
            $this->assertSame('2025-01-01 10:00:00', $r['rows']['KA-1']['finished_at']);
        }
        foreach (['2025-02-30 10:00:00', '2099-01-01 10:00:00', true, ['date'=>'2025-01-01 10:00:00']] as $date) {
            $r = $this->fixture(['tracking'=>$this->tracking(['date'=>['finished_at'=>$date]])]);
            $this->assertSame(100, $r['rows']['KA-1']['instant_status_code']);
            $this->assertSame('request_pickup', $r['rows']['KA-1']['status']);
        }
        $r = $this->fixture(['tracking'=>$this->tracking(['status'=>105,'date'=>['canceled_at'=>'2025-01-01 10:00:00']])]);
        $this->assertSame(105, $r['rows']['KA-1']['instant_status_code']);
    }

    public function test_malformed_or_mismatched_delete_response_cannot_clear_claim(): void {
        $base = ['order_id'=>'KA-1','service'=>'gosend','status'=>300];
        foreach ([['order_id'=>'OTHER'], ['service'=>'grab_express'], ['service_type'=>'same_day'], ['awb'=>'OTHER'], ['status'=>true], ['status'=>999]] as $extra) {
            $cancel = ['status'=>true,'operation_accepted'=>true,'data'=>['status'=>true,'code'=>0,'result'=>['packages'=>[array_replace($base,$extra)]]]];
            $r = $this->fixture(['cancel'=>$cancel,'operations'=>[['method'=>'cancel'],['method'=>'cancel','expire_report'=>true,'expire_throttle'=>true]]]);
            $this->assertSame(350, $r['rows']['KA-1']['instant_status_code']);
            $this->assertSame('unknown', $r['results'][0]['rows'][0]['status']);
            $this->assertSame([350], $r['delete_codes']);
            $this->assertSame([], $r['woo']['notes']);
        }
    }
}
