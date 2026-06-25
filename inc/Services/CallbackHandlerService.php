<?php
namespace KiriminAjaOfficial\Services;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;
class CallbackHandlerService extends BaseService{
    
    public $header;
    public $body;
    public array $packages = [];
    public $processing;
    public $transactionPickupNumber;
    private $transactions;
    
    public function header($header){
        $this->header = $header;
        return $this;
    }
    
    public function body($body){
        $this->body = $body;
        return $this;
    }
    
    
    public function call(){
        
        if (!$this->headerValidation()){
            $this->logWebhookEvent( 'warning', 'KiriminAja webhook authorization failed because the bearer token did not match the saved API key.' );
            return self::error([],'Authorization failed');
        }
        if (isset($this->body->data) && !empty($this->body->data)) {
            $this->packages = (array) $this->body->data;
        } elseif (isset($this->body->packages) && !empty($this->body->packages)) {
            $this->packages = (array) $this->body->packages;
        }
        $orderIds = $this->getPackageOrderIds();
        if (empty($orderIds)) {
            $this->logWebhookEvent( 'warning', 'KiriminAja webhook request was ignored because no order identifiers were present in the payload.' );
            return self::error([],'No Order ID Found');
        }
        /** check if transaction exists */
        $this->transactions = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getTransactionByOrderIds($orderIds);
        if(count($this->transactions)<1){
            $this->logWebhookEvent(
                'warning',
                'KiriminAja webhook request was ignored because no matching plugin transactions were found.',
                array(
                    'order_ids' => $orderIds,
                )
            );
            return self::error([],'No Transaction Found');
        }
        /** Set Pickup Number*/
        $this->transactionPickupNumber = $this->transactions[0]->pickup_number;
        switch (@$this->body->method) {
            case "return_finished_packages":
                $this->processing = $this->returnFinishedPackages();
                break;
            case "processed_packages":
                $this->processing = $this->processedPackages();
                break;
            case "shipped_packages":
                $this->processing = $this->shippedPackages();
                break;
            case "finished_packages":
                $this->processing = $this->finishedPackages();
                break;
            case "returned_packages":
                $this->processing = $this->returnedPackages();
                break;
            case "validated_packages":
                $this->processing = $this->validatedPackages();
                break;
            case "rejected_packages":
                $this->processing = $this->rejectedPackages();
                break;
            case "canceled_packages":
                $this->processing = $this->canceledPackages();
                break;
            default:
                $this->logWebhookEvent(
                    'warning',
                    'KiriminAja webhook request used an unsupported callback method.',
                    array(
                        'order_ids' => $orderIds,
                    )
                );
                return self::error([], 'Unsupported callback method');
        }
        if (!$this->processing['status']){
            $this->logWebhookEvent(
                'error',
                'KiriminAja webhook processing failed while updating local transaction state.',
                array(
                    'order_ids' => $orderIds,
                    'message'   => $this->processing['message'],
                )
            );
            return self::error([],$this->processing['message']);
        }
        
        return self::success([],'Success');
    }
    
    private function headerValidation(){
        $authorization = '';
        foreach ((array) $this->header as $key => $value) {
            if (strtolower((string) $key) === 'authorization') {
                $authorization = trim((string) $value);
                break;
            }
        }

        if ($authorization === '') {
            return false;
        }

        if (stripos($authorization, 'Bearer ') === 0) {
            $authorization = trim(substr($authorization, 7));
        }

        $token = trim((string) ((new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('api_key')->value ?? ''));

        if ($authorization === '' || $token === '') {
            return false;
        }

        return hash_equals($token, $authorization);
    }
    
    public function returnFinishedPackages(){
        try {
            foreach ($this->packages as $package){
                /** Check if wc transaction exist and get wc order id*/
                $transactionArrKey = array_search($package->order_id, array_column($this->transactions, 'order_id'));
                $theTransaction = @$this->transactions[$transactionArrKey];
                
                if ($theTransaction){
                    /** Update KJ Table*/
                    $payload = [];
                    $payload['changes']=[
                        'return_finished_at'    => kiriof_helper()->dateConvertGMT($package->date),
                        'status'                => 'returned',
                    ];
                    $payload['condition']=[
                        'order_id'  =>  $package->order_id
                    ];
                    (new \KiriminAjaOfficial\Repositories\TransactionRepository())->updateTransactionByCallback($payload);
                    /** Update in wc order table*/        
                    $order = wc_get_order( $theTransaction->wp_wc_order_stat_order_id );
                    $order->update_status( 'wc-cancelled' );
                }
        
                
            }
            $this->logWebhookEvent(
                'notice',
                'KiriminAja webhook marked packages as return finished.',
                array(
                    'order_ids' => $this->getPackageOrderIds(),
                )
            );
            return ['status'=>true, 'message'=>'',];
        }catch (\Throwable $th){
            return ['status'=>false, 'message'=>$th->getMessage(),];
        }
    }
    
    public function processedPackages(){
        try {
            /** Update AWB*/
            foreach ( $this->packages as $package ) {
                $payload = [];
                $payload['changes']=[
                    'awb'   =>  $package->awb
                ];
                $payload['condition']=[
                    'order_id'  =>  $package->order_id
                ];
                (new \KiriminAjaOfficial\Repositories\TransactionRepository())->updateTransactionByCallback($payload);
            }
            $paymentRepository = new \KiriminAjaOfficial\Repositories\PaymentRepository();
            $paymentRecord = $paymentRepository->getPaymentByPaymentId($this->transactionPickupNumber);
            $paymentMethod = strtolower((string) ($paymentRecord->method ?? ''));
            $paymentPayload = is_object($this->body) && isset($this->body->payment) ? $this->body->payment : null;
            $paymentStatusCode = (int) ($paymentPayload->status_code ?? 0);
            $paymentPayTime = (string) ($paymentPayload->pay_time ?? '');
            $resolvedPaymentStatus = 'paid';

            if ($paymentMethod === 'qris') {
                $resolvedPaymentStatus = ($paymentStatusCode >= 100 || $paymentPayTime !== '') ? 'paid' : 'unpaid';
            }
            /** Update Payment Status*/
            $paymentRepository->updatePaymentByCallback([
                'changes'=>[
                    'status'=>$resolvedPaymentStatus
                ],
                'condition'=>[
                    'pickup_number'=>$this->transactionPickupNumber
                ],
            ]);
            $this->logWebhookEvent(
                'notice',
                'KiriminAja webhook stored AWB numbers and synchronized payment status for processed packages.',
                array(
                    'order_ids'      => $this->getPackageOrderIds(),
                    'pickup_number'  => $this->transactionPickupNumber,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $resolvedPaymentStatus,
                )
            );
            
            return ['status'=>true, 'message'=>'',];
        }catch (\Throwable $th){
            return ['status'=>false, 'message'=>$th->getMessage(),];
        }
    }
    
    public function shippedPackages(){
        try {
            foreach ($this->packages as $package){
                $payload = [];
                $payload['changes']=[
                    'shipped_at'    =>  kiriof_helper()->dateConvertGMT($package->shipped_at),
                    'status'        =>  'shipped'
                ];
                $payload['condition']=[
                    'order_id'=>$package->order_id
                ];
                (new \KiriminAjaOfficial\Repositories\TransactionRepository())->updateTransactionByCallback($payload);
            }
            $this->logWebhookEvent(
                'notice',
                'KiriminAja webhook marked packages as shipped.',
                array(
                    'order_ids' => $this->getPackageOrderIds(),
                )
            );
            return ['status'=>true, 'message'=>'',];
        }catch (\Throwable $th){
            return ['status'=>false, 'message'=>$th->getMessage(),];
        }
    }
    
    public function finishedPackages(){
        try {
            foreach ($this->packages as $package){
                /** Check if wc transaction exist and get wc order id*/
                $transactionArrKey = array_search($package->order_id, array_column($this->transactions, 'order_id'));
                $theTransaction = @$this->transactions[$transactionArrKey];
                
                if ($theTransaction){
                    /** Update KJ Table*/
                    $payload = [];
                    $payload['changes']=[
                        'finished_at'   =>  kiriof_helper()->dateConvertGMT($package->finished_at),
                        'status'        =>  'finished'
                    ];
                    $payload['condition']=[
                        'order_id'=>$package->order_id
                    ];
                    (new \KiriminAjaOfficial\Repositories\TransactionRepository())->updateTransactionByCallback($payload);
                    /** Update in wc order table*/
                    $order = wc_get_order( $theTransaction->wp_wc_order_stat_order_id );
                    $order->update_status( 'completed' );
                }
                
                
            }
            $this->logWebhookEvent(
                'notice',
                'KiriminAja webhook marked packages as finished and completed matching WooCommerce orders.',
                array(
                    'order_ids' => $this->getPackageOrderIds(),
                )
            );
            return ['status'=>true, 'message'=>'',];
        }catch (\Throwable $th){
            return ['status'=>false, 'message'=>$th->getMessage(),];
        }
    }
    
    public function returnedPackages(){
        try {
            foreach ($this->packages as $package){
                $payload = [];
                $payload['changes']=[
                    'returned_at'   =>  kiriof_helper()->dateConvertGMT($package->returned_at),
                    'status'        =>  'return'
                    
                ];
                $payload['condition']=[
                    'order_id'=>$package->order_id
                ];
                (new \KiriminAjaOfficial\Repositories\TransactionRepository())->updateTransactionByCallback($payload);
            }
            $this->logWebhookEvent(
                'notice',
                'KiriminAja webhook marked packages as returned.',
                array(
                    'order_ids' => $this->getPackageOrderIds(),
                )
            );
            return ['status'=>true, 'message'=>'',];
        }catch (\Throwable $th){
            return ['status'=>false, 'message'=>$th->getMessage(),];
        }
    }
    
    public function validatedPackages(){
        try {
            foreach ($this->packages as $package){
                $payload = [];
                $payload['changes']=[
                    'shipping_cost'=>$package->shipping_cost
                ];
                $payload['condition']=[
                    'order_id'=>$package->order_id
                ];
                (new \KiriminAjaOfficial\Repositories\TransactionRepository())->updateTransactionByCallback($payload);
            }
            $this->logWebhookEvent(
                'notice',
                'KiriminAja webhook updated validated package shipping costs.',
                array(
                    'order_ids' => $this->getPackageOrderIds(),
                )
            );
            return ['status'=>true, 'message'=>'',];
        }catch (\Throwable $th){
            return ['status'=>false, 'message'=>$th->getMessage(),];
        }
    }
    
    public function rejectedPackages(){
        try {
            foreach ($this->packages as $package){
                $payload = [];
                $payload['changes']=[
                    'rejected_at'       =>  kiriof_helper()->dateConvertGMT($package->rejected_at),
                    'rejected_reason'   =>  $package->reason,
                    'status'            =>  'rejected'
                    
                ];
                $payload['condition']=[
                    'order_id'=>$package->order_id
                ];
                (new \KiriminAjaOfficial\Repositories\TransactionRepository())->updateTransactionByCallback($payload);
            }
            $this->logWebhookEvent(
                'notice',
                'KiriminAja webhook marked packages as rejected.',
                array(
                    'order_ids' => $this->getPackageOrderIds(),
                )
            );
            return ['status'=>true, 'message'=>'',];
        }catch (\Throwable $th){
            return ['status'=>false, 'message'=>$th->getMessage(),];
        }
    }
    /** Cancel Packages Callback */
    public function canceledPackages(){
        try {
            foreach ($this->packages as $package){
                /** Check if wc transaction exist and get wc order id*/
                $transactionArrKey = array_search($package->order_id, array_column($this->transactions, 'order_id'));
                $theTransaction = @$this->transactions[$transactionArrKey];
                
                if ($theTransaction){
                    /** Update KJ Table*/
                    $payload = [];
                    
                    $canceledAt = $package->canceled_at ?? gmdate('Y-m-d H:i:s');
                    $payload['changes']=[
                        'canceled_at'   =>  kiriof_helper()->dateConvertGMT( $canceledAt ),
                        'status'        =>  'canceled'
                    ];
                    $payload['condition']=[
                        'order_id'=>$package->order_id
                    ];
                    (new \KiriminAjaOfficial\Repositories\TransactionRepository())->updateTransactionByCallback($payload);
                    /** Update in wc order table — unhook our listener to prevent a loop back to the Mitra API */
                    $order = wc_get_order( $theTransaction->wp_wc_order_stat_order_id );
                    if ( $order && $order->get_status() !== 'cancelled' ) {
                        remove_action( 'woocommerce_order_status_cancelled', [ 'KiriminAjaOfficial\\Controllers\\TransactionProcessController', 'handleWcOrderCancelled' ] );
                        $order->update_status( 'cancelled' );
                        add_action( 'woocommerce_order_status_cancelled', [ 'KiriminAjaOfficial\\Controllers\\TransactionProcessController', 'handleWcOrderCancelled' ] );
                    }
                }
                
                
            }
            $this->logWebhookEvent(
                'notice',
                'KiriminAja webhook marked packages as canceled and synced matching WooCommerce orders.',
                array(
                    'order_ids' => $this->getPackageOrderIds(),
                )
            );
            return ['status'=>true, 'message'=>'',];
        }catch (\Throwable $th){
            return ['status'=>false, 'message'=>$th->getMessage(),];
        }
    }

    private function getPackageOrderIds(): array {
        $order_ids = array();

        foreach ( (array) $this->packages as $package ) {
            $order_id = is_object( $package ) ? ( $package->order_id ?? '' ) : ( is_array( $package ) ? ( $package['order_id'] ?? '' ) : '' );
            if ( '' !== (string) $order_id ) {
                $order_ids[] = (string) $order_id;
            }
        }

        return $order_ids;
    }

    private function logWebhookEvent( string $level, string $message, array $context = array() ): void {
        kiriof_log(
            $level,
            $message,
            array_merge(
                array(
                    'source'        => 'kiriminaja_webhook',
                    'callback_method' => is_object( $this->body ) ? (string) ( $this->body->method ?? '' ) : '',
                    'package_count' => count( (array) $this->packages ),
                ),
                $context
            )
        );
    }
}
