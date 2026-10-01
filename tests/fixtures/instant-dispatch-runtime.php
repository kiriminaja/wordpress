<?php
// Isolated runtime: use real types, injecting subclasses without constructing WP/SDK infrastructure.
define( 'ABSPATH', __DIR__ );
function __( $text, $domain = '' ) { return $text; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function get_current_user_id() { return $GLOBALS['user']; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][$key] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['transients'][$key] ?? false; }
function delete_transient( $key ) { if (!isset($GLOBALS['transients'][$key])) { return false; } unset($GLOBALS['transients'][$key]); return true; }
function add_option( $key, $value, $deprecated = '', $autoload = false ) { if (isset($GLOBALS['options'][$key])) { return false; } $GLOBALS['options'][$key] = $value; return true; }
function delete_option( $key ) { unset($GLOBALS['options'][$key]); return true; }
$root = dirname(__DIR__, 2);
foreach (['Contracts/TransactionPrintRepositoryInterface','Services/TransactionDeliveryType','Repositories/TransactionRepository','Base/KiriminAjaApi','Repositories/InstantDeliveryApiRepository','Services/InstantShipmentContext','Services/InstantDispatchService'] as $file) { require $root . '/inc/' . $file . '.php'; }
class DispatchRepo extends \KiriminAjaOfficial\Repositories\TransactionRepository {
    public array $rows = [];
    public array $claims = [];
    public array $releases = [];
    public array $writes = [];
    public function __construct() {}
    public function getTransactionByOrderIds($ids) { return array_values(array_intersect_key($this->rows, array_flip($ids))); }
    public function claimInstantDispatch(string $id): bool {
        if ('KA-' . ($GLOBALS['input']['claim_fail'] ?? 0) === $id) { return false; }
        foreach ($this->rows as $row) { if ($row->order_id === $id && $row->status === 'new') { $row->status = 'pending'; $this->claims[] = $id; return true; } }
        return false;
    }
    public function releaseInstantDispatch(string $id): bool {
        $this->releases[] = $id;
        foreach ($this->rows as $row) { if ($row->order_id === $id && $row->status === 'pending') { $row->status = 'new'; } }
        return true;
    }
    public function updateTransactionByCallbackVerified($payloads) {
        $this->writes[] = $payloads;
        if (!empty($GLOBALS['input']['write_fail'])) { return false; }
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
        $ctx['fingerprint'] = hash('sha256', json_encode([$ctx, $row->mutation ?? '']));
        return $ctx;
    }
}
class DispatchApi extends \KiriminAjaOfficial\Repositories\InstantDeliveryApiRepository {
    public array $books = [];
    public int $prices = 0;
    public int $profiles = 0;
    public array $credits = [];
    public int $payments = 0;
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
    public function validateCredit(string $pin, int|float $amount): array { $this->credits[] = ['amount'=>$amount,'valid_pin'=>strlen($pin) === 6]; return ['status'=>empty($GLOBALS['input']['credit_fail'])]; }
    public function book(array $payload): array {
        $stored = $payload; $stored['valid_credit_pin'] = isset($payload['pin']) && 1 === preg_match('/\A[0-9]{6}\z/', $payload['pin']); unset($stored['pin']); $this->books[] = $stored;
        $in = $GLOBALS['input'];
        if (!empty($in['timeout'])) { throw new RuntimeException('Secret upstream error 123456'); }
        if (!empty($in['false'])) { return ['status'=>false]; }
        $packages = array_reverse(array_map(static fn($p)=>['order_id'=>$p['order_id'],'status'=>$in['remote_status'] ?? 100,'awb'=>'AWB-' . $p['order_id'],'tracking_url'=>'https://example.com/tracking'], $payload['packages']));
        if (!empty($in['no_awb'])) { foreach ($packages as &$package) { unset($package['awb']); } unset($package); }
        if (!empty($in['missing'])) { array_pop($packages); }
        if (!empty($in['duplicate'])) { $packages[] = $packages[0]; }
        if (!empty($in['position_only'])) { foreach ($packages as &$package) { unset($package['order_id']); } unset($package); }
        $data = ['packages'=>$packages];
        if (empty($in['no_payment'])) { $data['payment'] = ['payment_id'=>'PAY-' . count($this->books),'status_code'=>$in['payment_status'] ?? 9,'amount'=>count($payload['packages']) * 18000,'qr_content'=>'000201-QR']; }
        if (array_key_exists('payment_legacy_status', $in) && isset($data['payment'])) { $data['payment']['status'] = $in['payment_legacy_status']; }
        if (!empty($in['echo_pin'])) { $data['pin'] = $payload['pin'] ?? ''; }
        if (!empty($in['nested_booking'])) { $data = ['result'=>$data]; }
        return ['status'=>$in['response_status'] ?? true,'data'=>(object)['results'=>$data]];
    }
    public function payment(string $id): array {
        ++$this->payments;
        $in = $GLOBALS['input'];
        $data = ['payment_id'=>$id,'status_code'=>$in['refresh_status'] ?? 0,'amount'=>18000,'qr_content'=>'QR'];
        if (array_key_exists('refresh_legacy_status', $in)) { $data['status'] = $in['refresh_legacy_status']; }
        if (!empty($in['nested_payment'])) { $data = ['result'=>$data]; }
        return ['status'=>$in['refresh_response_status'] ?? true,'data'=>(object)['result'=>$data]];
    }
}
$GLOBALS['input'] = json_decode($argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
$in = $GLOBALS['input']; $GLOBALS['user'] = 1; $GLOBALS['transients'] = []; $GLOBALS['options'] = [];
$repo = new DispatchRepo(); $api = new DispatchApi(); $context = new DispatchContext();
for ($i=1; $i<=($in['count'] ?? 1); ++$i) { $id = 'KA-' . $i; $repo->rows[$id] = (object)array_merge(['id'=>$i,'order_id'=>$id,'service'=>'gosend','service_name'=>'sameday','vehicle'=>'motor','status'=>'new','shipping_cost'=>15000,'shipping_info'=>'{"_shipping_city":"Saved City","custom":"keep"}'], $in['row'] ?? []); }
if (!empty($in['ineligible_last'])) { end($repo->rows)->ineligible = true; }
if (!empty($in['other_origin'])) { end($repo->rows)->origin = 'Other complete warehouse address'; }
$service = new \KiriminAjaOfficial\Services\InstantDispatchService($repo,$api,$context);
$result = ['error'=>'','quote'=>null,'dispatch'=>null,'retry_error'=>''];
try {
    $ids = $in['ids'] ?? array_keys($repo->rows);
    $quote = $service->quote($ids); $result['quote'] = $quote;
    if (!empty($in['user_change'])) { $GLOBALS['user'] = 2; }
    if (!empty($in['stale'])) { $repo->rows['KA-1']->mutation = 'changed'; }
    if (!empty($in['expire'])) { $GLOBALS['transients']['kiriof_instant_quote_' . $quote['token']]['expires'] = time()-1; }
    if (!empty($in['lock'])) { $GLOBALS['options']['kiriof_instant_dispatch_' . hash('sha256','KA-1')] = 'locked'; }
    if (empty($in['quote_only'])) {
        $dispatch_ids = $in['dispatch_ids'] ?? $ids;
        $result['dispatch'] = $service->dispatch($quote['token'], $dispatch_ids, $in['method'] ?? 'qris', $in['pin'] ?? '');
        if (!empty($in['retry'])) { try { $service->dispatch($quote['token'],$dispatch_ids,$in['method'] ?? 'qris'); } catch (Throwable $error) { $result['retry_error'] = $error->getMessage(); } }
        if (!empty($in['refresh'])) { $result['refresh'] = $service->refreshPayment($dispatch_ids,$in['refresh_pid'] ?? 'PAY-1'); }
    }
} catch (Throwable $error) { $result['error'] = $error->getMessage(); }
$result += ['claims'=>$repo->claims,'releases'=>$repo->releases,'writes'=>$repo->writes,'books'=>$api->books,'prices'=>$api->prices,'profiles'=>$api->profiles,'credits'=>$api->credits,'payments_called'=>$api->payments,'rows'=>array_values($repo->rows),'transients'=>$GLOBALS['transients'],'options'=>$GLOBALS['options']];
echo json_encode($result, JSON_THROW_ON_ERROR);
