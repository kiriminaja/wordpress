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
    public array $packages;
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
            return self::error([],'Authorization failed');
        }
        if (isset($this->body->data) && !empty($this->body->data)) {
            $this->packages = $this->body->data;
        }
        $orderIds = array_column($this->packages, 'order_id') ?? [];
        if (empty($orderIds)) {
            return self::error([],'No Order ID Found');
        }
        /** check if transaction exists */
        $this->transactions = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getTransactionByOrderIds($orderIds);
        if(count($this->transactions)<1){
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
        }
        if (!$this->processing['status']){
            return self::error([],$this->processing['message']);
        }
        
        return self::success([],'Success');
    }
    
    private function headerValidation(){
        $authorization = @$this->header['Authorization'] ?? '';
        $authorizationExploded = explode(' ',$authorization);
        $authorizationToken = @$authorizationExploded[1] ?? '$authorizationToken';
        $token = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('api_key')->value ?? 'noToken';
        return $authorizationToken === $token;
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
            return ['status'=>true, 'message'=>'',];
        }catch (\Throwable $th){
            return ['status'=>false, 'message'=>$th->getMessage(),];
        }
    }
    
    public function processedPackages(){
        try {
            
            // save log
            update_option( 'kiriof_processed_packages', $this->packages );
            /** Update AWB*/
            foreach ( $this->packages as $package ) {
                // save log item packages
                update_option( 'kiriof_item_processed_packages', $package );
                
                $payload = [];
                $payload['changes']=[
                    'awb'   =>  $package->awb
                ];
                $payload['condition']=[
                    'order_id'  =>  $package->order_id
                ];
                (new \KiriminAjaOfficial\Repositories\TransactionRepository())->updateTransactionByCallback($payload);
            }
            /** Update Payment Status*/
            (new \KiriminAjaOfficial\Repositories\PaymentRepository())->updatePaymentByCallback([
                'changes'=>[
                    'status'=>'paid'
                ],
                'condition'=>[
                    'pickup_number'=>$this->transactionPickupNumber
                ],
            ]);
            
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
                (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$theTransaction',[$theTransaction]);
                
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
                (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$theTransaction',[$theTransaction]);
                
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
                    /** Update in wc order table*/
                    $order = wc_get_order( $theTransaction->wp_wc_order_stat_order_id );
                    $order->update_status( 'cancelled' );
                }
                
                
            }
            return ['status'=>true, 'message'=>'',];
        }catch (\Throwable $th){
            return ['status'=>false, 'message'=>$th->getMessage(),];
        }
    }
}