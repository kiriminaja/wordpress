<?php
error_reporting(E_ALL & ~E_DEPRECATED);
// Isolated production routing with a recording state boundary (no SDK or WordPress writes).
define('ABSPATH', dirname(__DIR__, 2) . '/');
$root = dirname(__DIR__, 2);
foreach (['Utils/ServiceResponse', 'Base/BaseService', 'Services/TransactionDeliveryType', 'Services/InstantShipmentState', 'Services/InstantWebhookService', 'Services/CallbackHandlerService'] as $file) {
    require_once $root . '/inc/' . $file . '.php';
}
function kiriof_log($level, $message, $context = []) { $GLOBALS['logs'][] = $context; }
$input = json_decode($argv[1] ?? '{}', true);
$repo = new class($input['rows'] ?? [['order_id'=>'A', 'service'=>'gosend'], ['order_id'=>'B', 'service'=>'grab_express']]) {
    public $rows;
    public $reads = 0;
    public $writes = [];
    public function __construct($rows) { $this->rows = array_map(fn($row)=>(object)array_merge(['status'=>'request_pickup', 'service_name'=>'instant'], $row), $rows); }
    public function getTransactionByOrderIds($ids) { ++$this->reads; return array_values(array_filter($this->rows, fn($row)=>in_array($row->order_id, $ids, true))); }
    public function updateTransactionByCallbackVerified($data) { $this->writes[] = $data; return true; }
};
$payment = new class {
    public $calls = 0;
    public function getPaymentByPaymentId($id) { ++$this->calls; throw new RuntimeException('Express payments forbidden'); }
    public function updatePaymentByCallbackVerified($data) { ++$this->calls; throw new RuntimeException('Express payments forbidden'); }
};
$state = new class($input['fail'] ?? '') {
    public $calls = [];
    private $fail;
    public function __construct($fail) { $this->fail = $fail; }
    public function apply($id, array $package, array $payment = [], ?string $method = null): array {
        $this->calls[] = compact('id', 'package', 'payment', 'method');
        return ['status'=>$id === $this->fail ? false : 'shipped', 'changed'=>true, 'order_id'=>$id, 'tracking_url'=>''];
    }
};
$default = ['method'=>'shipped_packages', 'data'=>[['order_id'=>'A', 'shipped_at'=>'2026-07-30 10:00:00'], ['order_id'=>'B']], 'packages'=>[['order_id'=>'B', 'status'=>106, 'awb'=>'B-AWB'], ['order_id'=>'A', 'status'=>'106', 'awb'=>'A-AWB']]];
$body = json_decode(json_encode($input['body'] ?? $default));
$handler = new KiriminAjaOfficial\Services\CallbackHandlerService($repo, $payment, 'secret', new KiriminAjaOfficial\Services\InstantWebhookService($state));
$handler->header(['Authorization'=>'Bearer ' . ($input['token'] ?? 'secret')])->body($body);
$response = $handler->call();
$responses = [$response->status];
foreach ($input['repeat'] ?? [] as $next) {
    $responses[] = $handler->body(json_decode(json_encode($next)))->call()->status;
}
echo json_encode(['status'=>$response->status, 'message'=>$response->message, 'responses'=>$responses, 'reads'=>$repo->reads, 'writes'=>$repo->writes, 'calls'=>$state->calls, 'payment_calls'=>$payment->calls, 'logs'=>$GLOBALS['logs'] ?? []]);
