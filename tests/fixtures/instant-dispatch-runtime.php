<?php
// Isolated runtime: use real types, injecting subclasses without constructing WP/SDK infrastructure.
define( 'ABSPATH', __DIR__ );
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return htmlspecialchars( __( $text, $domain ), ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw($url, $protocols = []) { return $url; }
function sanitize_text_field($text) { return is_scalar($text) ? trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string)$text))) : ''; }
class DispatchWooOrder {
    public string $status = 'processing'; public array $meta = []; public int $completions = 0;
    public function get_status() { return $this->status; }
    public function get_order_number() { if (!empty($GLOBALS['input']['order_number_throw'])) { throw new RuntimeException('Secret order lookup error'); } return $GLOBALS['input']['order_number'] ?? 'SHOP-1001'; }
    public function update_status($status, $note = '') { $this->status = $status; ++$this->completions; }
    public function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
    public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
    public function add_order_note($note) { return 1; }
    public function save() { return 1; }
}
function wc_get_order($id) { if (!$id || !empty($GLOBALS['input']['order_missing'])) { return false; } if (!empty($GLOBALS['input']['order_number_missing_method'])) { return new stdClass(); } return $GLOBALS['woo'][$id] ??= new DispatchWooOrder(); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function kiriof_log( ...$args ) { if (!empty($GLOBALS['input']['logger_throw'])) { throw new RuntimeException('Secret logging failure'); } $GLOBALS['logs'][] = $args; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function maybe_serialize( $value ) { return is_array($value) || is_object($value) ? serialize($value) : $value; }
function wp_cache_delete( $key, $group = '' ) { return true; }
function get_option( $key ) { return $GLOBALS['options'][$key] ?? false; }
function get_current_user_id() { return $GLOBALS['user']; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][$key] = $value; $GLOBALS['transient_ttls'][$key] = $ttl; return true; }
function get_transient( $key ) { return $GLOBALS['transients'][$key] ?? false; }
function delete_transient( $key ) { if (!empty($GLOBALS['input']['consume_fail']) || !isset($GLOBALS['transients'][$key])) { return false; } unset($GLOBALS['transients'][$key]); return true; }
function add_option( $key, $value, $deprecated = '', $autoload = false ) { if (isset($GLOBALS['options'][$key])) { return false; } $GLOBALS['options'][$key] = $value; return true; }
function delete_option( $key ) { unset($GLOBALS['options'][$key]); return true; }
class DispatchLockDb {
    public string $options = 'wp_options';
    public function prepare($sql, ...$args) { return $args; }
    public function query($args) {
        [$key, $expected] = $args;
        if (!empty($GLOBALS['input']['lease_delete_race']) && empty($GLOBALS['lease_raced'])) {
            $GLOBALS['lease_raced'] = true;
            $GLOBALS['options'][$key] = ['owner'=>'replacement-owner','expires'=>time()+300];
        }
        if (maybe_serialize($GLOBALS['options'][$key] ?? false) !== $expected) { return 0; }
        unset($GLOBALS['options'][$key]); return 1;
    }
}
$GLOBALS['wpdb'] = new DispatchLockDb();
$root = dirname(__DIR__, 2);
foreach (['Contracts/TransactionPrintRepositoryInterface','Utils/CreditBalance','Services/TransactionDeliveryType','Repositories/TransactionRepository','Base/KiriminAjaApi','Repositories/InstantDeliveryApiRepository','Services/InstantShipmentContext','Services/InstantShipmentState','Services/InstantTrackingPresentation','Services/InstantDispatchService','Services/TransactionProcessServices/RecipientDataResolver','Services/InstantLabelService'] as $file) { require $root . '/inc/' . $file . '.php'; }
class DispatchRepo extends \KiriminAjaOfficial\Repositories\TransactionRepository {
    public array $rows = [];
    public array $claims = [];
    public array $releases = [];
    public array $writes = [];
    public array $gets = [];
    public function __construct() {}
    public function getTransactionByOrderId($id) { $this->gets[] = $id; return isset($this->rows[$id]) ? clone $this->rows[$id] : false; }
    public function compareAndSwapInstant(string $id, array $condition, array $changes): bool {
        $this->writes[] = ['condition'=>$condition, 'changes'=>$changes];
        if (($changes['status'] ?? '') === 'new') {
            if (!empty($GLOBALS['input']['rollback_throw'])) { throw new RuntimeException('Secret rollback error'); }
            if (!empty($GLOBALS['input']['rollback_fail'])) { return false; }
            if (!empty($GLOBALS['input']['rollback_callback'])) { $this->rows[$id]->status = 'request_pickup'; $this->rows[$id]->instant_status_code = 100; return false; }
        }
        if (!empty($GLOBALS['input']['issue_write_throw']) && array_keys($changes) === ['rejected_reason']) { throw new RuntimeException('Secret issue write error 123456'); }
        if (isset($changes['shipping_info']) && str_contains($changes['shipping_info'], '_kiriof_instant_prepared')) {
            if (($GLOBALS['input']['snapshot_fail_id'] ?? '') === $id) { return false; }
            if (($GLOBALS['input']['snapshot_throw_id'] ?? '') === $id) { throw new RuntimeException('Secret snapshot error'); }
        } elseif (!empty($GLOBALS['input']['write_fail']) || ($GLOBALS['input']['write_fail_id'] ?? '') === $id) { return false; }
        $row = $this->rows[$id] ?? null;
        if (!$row) { return false; }
        foreach ($condition as $key=>$value) { if (($row->$key ?? null) !== $value) { return false; } }
        foreach ($changes as $key=>$value) { $row->$key = $value; }
        return true;
    }
    public function getTransactionByOrderIds($ids) { return array_values(array_intersect_key($this->rows, array_flip($ids))); }
    public function claimInstantDispatch(string $id): bool {
        if ('KA-' . ($GLOBALS['input']['claim_fail'] ?? 0) === $id) { return false; }
        foreach ($this->rows as $row) { if ($row->order_id === $id && $row->status === 'new') { $row->status = 'pending'; $this->claims[] = $id; return true; } }
        return false;
    }
    public function releaseInstantDispatch(string $id): bool {
        $this->releases[] = $id;
        if (!empty($GLOBALS['input']['release_fail'])) { return false; }
        foreach ($this->rows as $row) { if ($row->order_id === $id && $row->status === 'pending') { $row->status = 'new'; } }
        return true;
    }
    public function updateTransactionByCallbackVerified($payloads) {
        $this->writes[] = $payloads;
        if (!empty($GLOBALS['input']['issue_write_throw']) && array_keys($payloads['changes']) === ['rejected_reason']) { throw new RuntimeException('Secret issue write error 123456'); }
        if (!empty($GLOBALS['input']['write_fail']) || ($GLOBALS['input']['write_fail_id'] ?? '') === $payloads['condition']['order_id']) { return false; }
        foreach ($payloads['condition'] as $key=>$value) { if (($this->rows[$payloads['condition']['order_id']]->$key ?? null) !== $value) { return false; } }
        foreach ($payloads['changes'] as $key=>$value) { $this->rows[$payloads['condition']['order_id']]->$key = $value; }
        return true;
    }
}
class DispatchContext extends \KiriminAjaOfficial\Services\InstantShipmentContext {
    public function __construct() {}
    public function build(object $row): array {
        if (!empty($GLOBALS['input']['context_validation'])) { throw new InvalidArgumentException(__('Valid origin and destination coordinates are required for Instant delivery.', 'kiriminaja-official')); }
        if (!empty($GLOBALS['input']['context_runtime'])) { throw new RuntimeException('Secret upstream error 123456'); }
        if (!self::canProcess($row) || !empty($row->ineligible)) { throw new RuntimeException('Invalid context'); }
        $ctx = ['origin'=>['name'=>'Test Sender','phone'=>'0812345678','address'=>$row->origin ?? 'Long enough original warehouse address','zipcode'=>'12345','latitude'=>-6.2,'longitude'=>106.8], 'package'=>['order_id'=>$row->order_id,'destination'=>['name'=>'Booked Full Name','phone'=>'0812345678','address'=>'Complete recipient street, City, 12345','latitude'=>-6.3,'longitude'=>106.9],'service'=>$row->service,'service_type'=>$row->service_name,'vehicle'=>'motor','shipping_cost'=>$row->shipping_cost,'items'=>[['name'=>'Item','qty'=>1]],'package_type_id'=>7], 'pricing'=>['timezone'=>'WIB','id'=>$row->order_id]];
        if (!empty($GLOBALS['input']['working_sample'])) { $ctx['origin']['address_note'] = 'Pickup note'; $ctx['package']['destination']['address_note'] = 'Gedung A Lantai 5'; }
        foreach (['origin_name'=>['origin','name'], 'destination_name'=>['package','destination','name']] as $key=>$path) {
            if (array_key_exists($key, $GLOBALS['input'])) {
                if (count($path) === 2) { $ctx[$path[0]][$path[1]] = $GLOBALS['input'][$key]; }
                else { $ctx[$path[0]][$path[1]][$path[2]] = $GLOBALS['input'][$key]; }
            }
        }
        if (!empty($GLOBALS['input']['distinct_last']) && 'KA-2' === $row->order_id) { $ctx['origin']['name'] = 'Second Sender'; $ctx['package']['destination']['name'] = 'Second Recipient'; }
        $ctx['fingerprint'] = hash('sha256', json_encode([$ctx, $row->mutation ?? '']));
        return $ctx;
    }
}
class DispatchApi extends \KiriminAjaOfficial\Repositories\InstantDeliveryApiRepository {
    public array $books = [];
    public array $preparedAtBook = [];
    public int $prices = 0;
    public int $profiles = 0;
    public array $credits = [];
    public int $payments = 0;
    public function bookingDiagnostics(): array { if (!empty($GLOBALS['input']['diagnostics_throw'])) { throw new RuntimeException('Secret diagnostics failure'); } return []; }
    public function __construct() {}
    public function profile(): array {
        ++$this->profiles;
        $in = $GLOBALS['input'];
        $method = $this->profiles > 1 ? ($in['profile_after'] ?? $in['profile'] ?? 'QRIS') : ($in['profile'] ?? 'QRIS');
        return ['status'=>empty($in['profile_fail']), 'data'=>(object)['results'=>(object)['metadata'=>(object)['payment_method'=>$method]]]];
    }
    public function price(array $payload): array {
        ++$this->prices;
        $in = $GLOBALS['input'];
        return ['status'=>true, 'data'=>(object)['result'=>[['name'=>'gosend','costs'=>[['service_type'=>'other','price'=>['shipping_costs'=>1]],['service_type'=>$in['price_service'] ?? 'sameday','price'=>['shipping_costs'=>$in['price'] ?? 18000]]]]]]];
    }
    public function validateCredit(string $pin, int|float $amount): array { $this->credits[] = ['amount'=>$amount,'valid_pin'=>strlen($pin) === 6]; if (!empty($GLOBALS['input']['credit_throw'])) { throw new RuntimeException('Secret PIN ' . $pin); } return ['status'=>empty($GLOBALS['input']['credit_fail'])]; }
    public function creditBalance(): array { return ['status'=>true, 'data'=>(object)['balance'=>$GLOBALS['input']['credit_balance'] ?? 4000724100]]; }
    public function book(array $payload): array {
        $this->preparedAtBook[] = array_map(static fn($row)=>get_object_vars($row), $GLOBALS['repo']->rows);
        $stored = $payload; $stored['valid_credit_pin'] = isset($payload['pin']) && 1 === preg_match('/\A[0-9]{6}\z/', $payload['pin']); unset($stored['pin']); $this->books[] = $stored;
        $in = $GLOBALS['input'];
        if (!empty($in['replace_lease_during_book'])) { foreach ($GLOBALS['options'] as $key=>$value) { $GLOBALS['options'][$key] = ['owner'=>'replacement-owner','expires'=>time()+300]; } }
        if (!empty($in['not_submitted'])) { return ['status'=>false, 'operation_not_submitted'=>true]; }
        if (!empty($in['validation_rejection'])) { return ['status'=>false,'operation_rejected'=>true,'data'=>(object)['status'=>false,'result'=>(object)[]]]; }
        if (!empty($in['timeout'])) { throw new RuntimeException('Secret upstream error 123456'); }
        if (!empty($in['definite_rejection']) || count($this->books) === ($in['reject_group'] ?? 0)) { return ['status'=>false, 'data'=>(object)['result'=>(object)['message'=>'Secret upstream error']]]; }
        if (!empty($in['false'])) { return ['status'=>false]; }
        if (!empty($in['working_sample'])) {
            return ['status'=>true, 'data'=>(object)['message'=>'success', 'status'=>true, 'results'=>[
                'payment'=>['payment_id'=>'EPR-5487434915','amount'=>31500,'status_code'=>'unpaid','qr_content'=>'some-random-qr-string','pay_time'=>null],
                'origin'=>$payload,
                'packages'=>array_map(static fn($p)=>['order_id'=>$p['order_id'],'service'=>'gosend','service_type'=>'Instant','status'=>110,'live_track_url'=>$in['sample_url'] ?? '', 'poly_line'=>'_p~iF~ps|U_ulLnnqC_mqNvxq`@','destination'=>$p['destination']], $payload['packages'])
            ]]];
        }
        $packages = array_reverse(array_map(static fn($p)=>['order_id'=>$p['order_id'],'service'=>$in['remote_service'] ?? $p['service'],'service_type'=>$in['remote_service_type'] ?? $p['service_type'],'status'=>$in['remote_status'] ?? 100,'awb'=>'AWB-' . $p['order_id'],'tracking_url'=>'https://example.com/tracking'], $payload['packages']));
        if (!empty($in['remote_missing_identity'])) { foreach ($packages as &$package) { unset($package['service'], $package['service_type']); } unset($package); }
        if (!empty($in['no_awb'])) { foreach ($packages as &$package) { unset($package['awb']); } unset($package); }
        if (!empty($in['missing'])) { array_pop($packages); }
        if (!empty($in['malformed_package'])) { $packages[] = ['order_id'=>['unexpected'],'status'=>['invalid']]; }
        if (!empty($in['duplicate'])) { $packages[] = $packages[0]; }
        if (!empty($in['position_only'])) { foreach ($packages as &$package) { unset($package['order_id']); } unset($package); }
        if (isset($in['callback_code'])) {
            $state = new \KiriminAjaOfficial\Services\InstantShipmentState($GLOBALS['repo']);
            foreach ($payload['packages'] as $p) {
                $state->apply($p['order_id'], ['order_id'=>$p['order_id'], 'service'=>$p['service'], 'service_type'=>$p['service_type'], 'status'=>$in['callback_code']], empty($in['callback_no_pid']) ? ['id'=>'PAY-' . count($this->books), 'status'=>$in['callback_payment'] ?? 'paid'] : []);
                if (!empty($in['callback_no_pid']) && isset($in['callback_payment'])) { $GLOBALS['repo']->rows[$p['order_id']]->instant_payment_status = $in['callback_payment']; }
            }
        }
        $data = ['packages'=>$packages];
        if (empty($in['no_payment'])) { $data['payment'] = ['payment_id'=>'PAY-' . count($this->books),'status_code'=>$in['payment_status'] ?? 9,'amount'=>count($payload['packages']) * 18000,'qr_content'=>'000201-QR']; }
        if (array_key_exists('payment_legacy_status', $in) && isset($data['payment'])) { $data['payment']['status'] = $in['payment_legacy_status']; }
        if (!empty($in['echo_pin'])) { $data['pin'] = $payload['pin'] ?? ''; }
        if (!empty($in['echo_pin_qr'])) { $data['payment']['qr_content'] = $payload['pin'] ?? ''; }
        if (!empty($in['nested_booking'])) { $data = ['result'=>$data]; }
        return ['status'=>$in['response_status'] ?? true,'data'=>(object)['results'=>$data]];
    }
    public function payment(string $id): array {
        ++$this->payments;
        $in = $GLOBALS['input'];
        foreach (($in['before_refresh_statuses'] ?? []) as $index=>$status) { $GLOBALS['repo']->rows['KA-' . ($index + 1)]->instant_payment_status = $status; }
        $data = ['payment_id'=>$in['response_pid'] ?? $id,'status_code'=>$in['refresh_status'] ?? 0,'amount'=>18000,'qr_content'=>'QR'];
        if (array_key_exists('refresh_legacy_status', $in)) { $data['status'] = $in['refresh_legacy_status']; }
        if (!empty($in['nested_payment'])) { $data = ['result'=>$data]; }
        return ['status'=>$in['refresh_response_status'] ?? true,'data'=>(object)['result'=>$data]];
    }
}
$GLOBALS['input'] = json_decode($argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
eval('namespace KiriminAjaOfficial\\Services; function random_bytes($length) { if ($length === 8 && !empty($GLOBALS["input"]["fail_later_reference"]) && !empty($GLOBALS["api"]->books)) { throw new \\RuntimeException("Reference creation failed"); } return \\random_bytes($length); }');
$in = $GLOBALS['input']; $GLOBALS['user'] = 1; $GLOBALS['transients'] = []; $GLOBALS['transient_ttls'] = []; $GLOBALS['options'] = []; $GLOBALS['logs'] = [];
$repo = new DispatchRepo(); $GLOBALS['repo'] = $repo; $GLOBALS['woo'] = []; $api = new DispatchApi(); $context = new DispatchContext();
$GLOBALS['api'] = $api;
for ($i=1; $i<=($in['count'] ?? 1); ++$i) { $id = 'KA-' . $i; $repo->rows[$id] = (object)array_merge(['id'=>$i,'wp_wc_order_stat_order_id'=>$i,'order_id'=>$id,'service'=>'gosend','service_name'=>'sameday','vehicle'=>'motor','status'=>'new','shipping_cost'=>15000,'shipping_info'=>'{"_shipping_city":"Saved City","custom":"keep"}'], $in['row'] ?? []); }
if (!empty($in['ineligible_last'])) { end($repo->rows)->ineligible = true; }
if (!empty($in['other_origin'])) { end($repo->rows)->origin = 'Other complete warehouse address'; }
$service = new \KiriminAjaOfficial\Services\InstantDispatchService($repo,$api,$context);
$initial_rows = array_map(static fn($row)=>get_object_vars($row), array_values($repo->rows));
$result = ['error'=>'','quote'=>null,'dispatch'=>null,'retry_error'=>''];
try {
    $ids = $in['ids'] ?? array_keys($repo->rows);
    $quote = $service->quote($ids); $result['quote'] = $quote;
    $result['quoted_at'] = time();
    if (!empty($in['repeat_quote'])) { $result['repeat_quote'] = $service->quote($ids); }
    if (!empty($in['user_change'])) { $GLOBALS['user'] = 2; }
    if (!empty($in['stale'])) { $repo->rows['KA-1']->mutation = 'changed'; }
    if (!empty($in['expire'])) { $GLOBALS['transients']['kiriof_instant_quote_' . $quote['token']]['expires'] = time()-1; }
    if (!empty($in['lock'])) { $GLOBALS['options']['kiriof_instant_dispatch_' . hash('sha256','KA-1')] = 'locked'; }
    if (isset($in['lease'])) { $GLOBALS['options']['kiriof_instant_dispatch_' . hash('sha256','KA-1')] = ['owner'=>'old-owner','expires'=>time()+('expired' === $in['lease'] ? -1 : 300)]; }
    if (!empty($in['crash_pending'])) { $repo->rows['KA-1']->status = 'pending'; }
    if (array_key_exists('quote_price', $in)) { foreach ($GLOBALS['transients']['kiriof_instant_quote_' . $quote['token']]['contexts'] as &$quoted_context) { $quoted_context['price'] = $in['quote_price']; } unset($quoted_context); }
    if (!empty($in['validate_credit'])) {
        $result['before_validation_rows'] = array_map(static fn($row)=>get_object_vars($row), array_values($repo->rows));
        try { $result['validation'] = $service->validateCredit($in['dispatch_ids'] ?? $ids, $in['validate_token'] ?? $quote['token'], $in['pin'] ?? ''); }
        catch (Throwable $error) { $result['validation_error'] = $error->getMessage(); $result['validation_exception'] = get_class($error); }
        $result['after_validation'] = ['rows'=>array_map(static fn($row)=>get_object_vars($row), array_values($repo->rows)), 'claims'=>$repo->claims, 'writes'=>$repo->writes, 'books'=>$api->books, 'options'=>$GLOBALS['options'], 'transients'=>$GLOBALS['transients']];
        if (!empty($in['retry_validation'])) { $GLOBALS['input']['credit_fail'] = false; $GLOBALS['input']['credit_throw'] = false; $result['validation_retry'] = $service->validateCredit($ids, $quote['token'], '654321'); }
        if (!empty($in['credit_fail_after_validation'])) { $GLOBALS['input']['credit_fail'] = true; }
    }
    if (empty($in['quote_only'])) {
        $dispatch_ids = $in['dispatch_ids'] ?? $ids;
        $result['dispatch'] = $service->dispatch($quote['token'], $dispatch_ids, $in['method'] ?? 'qris', $in['pin'] ?? '');
        if (!empty($in['retry'])) { try { $service->dispatch($quote['token'],$dispatch_ids,$in['method'] ?? 'qris'); } catch (Throwable $error) { $result['retry_error'] = $error->getMessage(); } }
        if (!empty($in['new_retry'])) { try { $fresh = $service->quote($dispatch_ids); $service->dispatch($fresh['token'], $dispatch_ids, $in['method'] ?? 'qris'); } catch (Throwable $error) { $result['new_retry_error'] = $error->getMessage(); } }
        if (!empty($in['recover'])) {
            $state = new \KiriminAjaOfficial\Services\InstantShipmentState($repo);
            $package = ['order_id'=>'KA-1','service'=>'gosend','service_type'=>'sameday','awb'=>'RECOVERED-AWB'];
            $state->mergeTrackingMetadata('KA-1', $package);
            $result['metadata_recovery_can_print'] = \KiriminAjaOfficial\Services\InstantLabelService::canPrint($repo->rows['KA-1']);
            $result['metadata_recovery_row'] = get_object_vars($repo->rows['KA-1']);
            $state->apply('KA-1', $package + ['status'=>100]);
        }
        if (!empty($in['refresh'])) { $result['refresh'] = $service->refreshPayment($dispatch_ids,$in['refresh_pid'] ?? 'PAY-1'); }
    }
} catch (Throwable $error) { $result['error'] = $error->getMessage(); }
if (!empty($in['retry_restored'])) {
    $GLOBALS['input']['definite_rejection'] = false; $GLOBALS['input']['not_submitted'] = false; $GLOBALS['input']['validation_rejection'] = false;
    try { $fresh = $service->quote(array_keys($repo->rows)); $result['restored_retry'] = $service->dispatch($fresh['token'], array_keys($repo->rows), $in['method'] ?? 'qris'); } catch (Throwable $error) { $result['restored_retry_error'] = $error->getMessage(); }
}
$result['initial_rows'] = $initial_rows;
$result += ['claims'=>$repo->claims,'releases'=>$repo->releases,'writes'=>$repo->writes,'gets'=>$repo->gets,'woo'=>$GLOBALS['woo'],'books'=>$api->books,'prepared_at_book'=>$api->preparedAtBook,'prices'=>$api->prices,'profiles'=>$api->profiles,'credits'=>$api->credits,'payments_called'=>$api->payments,'rows'=>array_values($repo->rows),'transients'=>$GLOBALS['transients'],'transient_ttls'=>$GLOBALS['transient_ttls'],'options'=>$GLOBALS['options']];
if (!empty($in['can_select'])) { $result['can_select'] = array_map([\KiriminAjaOfficial\Services\InstantShipmentContext::class, 'canProcess'], array_values($repo->rows)); }
$result['can_print'] = array_map([\KiriminAjaOfficial\Services\InstantLabelService::class, 'canPrint'], array_values($repo->rows));
$result['logs'] = $GLOBALS['logs'];
echo json_encode($result, JSON_THROW_ON_ERROR);
