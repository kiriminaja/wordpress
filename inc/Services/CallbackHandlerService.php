<?php

namespace Inc\Services;

use Inc\Base\BaseService;

class CallbackHandlerService extends BaseService{
    
    public $header;
    public $body;
    public array $packages;
    public $processing;
    public $transactionPickupNumber;
    
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

        $this->packages = @$this->body->data ?? [];
 
        $orderIds = array_column($this->packages, 'order_id') ?? [];
        /** check if transaction exists */
        $transactions = (new \Inc\Repositories\TransactionRepository())->getTransactionByOrderIds($orderIds);
        if(count($transactions)<1){
            return self::error([],'No Transaction Found');
        }
        /** Set Pickup Number*/
        $this->transactionPickupNumber = $transactions[0]->pickup_number;

        switch (@$this->body->method) {
            case 'return_finished_packages':
                $this->processing = $this->returnFinishedPackages();
                break;
            case 'processed_packages':
                $this->processing = $this->processedPackages();
                break;
            case 'shipped_packages':
                $this->processing = $this->shippedPackages();
                break;
            case 'finished_packages':
                $this->processing = $this->finishedPackages();
                break;
            case 'returned_packages':
                $this->processing = $this->returnedPackages();
                break;
            case 'validated_packages':
                $this->processing = $this->validatedPackages();
                break;
            case 'rejected_packages':
                $this->processing = $this->rejectedPackages();
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
        $token = (new \Inc\Repositories\SettingRepository())->getSettingByKey('api_key')->value ?? 'noToken';
        return $authorizationToken === $token;
    }
    
    public function returnFinishedPackages(){
        try {
            foreach ($this->packages as $package){
                $payload = [];
                $payload['changes']=[
                    'return_finished_at'=>$package->date
                ];
                $payload['condition']=[
                    'order_id'=>$package->order_id
                ];
                (new \Inc\Repositories\TransactionRepository())->updateTransactionByCallback($payload);
            }
            return ['status'=>true, 'message'=>'',];
        }catch (\Throwable $th){
            return ['status'=>false, 'message'=>$th->getMessage(),];
        }

    }
    
    public function processedPackages(){
        try {
            
            /** Update AWB*/
            foreach ($this->packages as $package){
                $payload = [];
                $payload['changes']=[
                    'awb'=>$package->awb
                ];
                $payload['condition']=[
                    'order_id'=>$package->order_id
                ];
                (new \Inc\Repositories\TransactionRepository())->updateTransactionByCallback($payload);
            }

            /** Update Payment Status*/
            (new \Inc\Repositories\PaymentRepository())->updatePaymentByCallback([
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
                    'shipped_at'=>$package->shipped_at
                ];
                $payload['condition']=[
                    'order_id'=>$package->order_id
                ];
                (new \Inc\Repositories\TransactionRepository())->updateTransactionByCallback($payload);
            }
            return ['status'=>true, 'message'=>'',];
        }catch (\Throwable $th){
            return ['status'=>false, 'message'=>$th->getMessage(),];
        }
    }
    
    public function finishedPackages(){
        try {
            foreach ($this->packages as $package){
                $payload = [];
                $payload['changes']=[
                    'finished_at'=>$package->finished_at
                ];
                $payload['condition']=[
                    'order_id'=>$package->order_id
                ];
                (new \Inc\Repositories\TransactionRepository())->updateTransactionByCallback($payload);
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
                    'returned_at'=>$package->returned_at
                ];
                $payload['condition']=[
                    'order_id'=>$package->order_id
                ];
                (new \Inc\Repositories\TransactionRepository())->updateTransactionByCallback($payload);
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
                (new \Inc\Repositories\TransactionRepository())->updateTransactionByCallback($payload);
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
                    'rejected_at'=>$package->rejected_at,
                    'rejected_reason'=>$package->reason,
                ];
                $payload['condition']=[
                    'order_id'=>$package->order_id
                ];
                (new \Inc\Repositories\TransactionRepository())->updateTransactionByCallback($payload);
            }
            return ['status'=>true, 'message'=>'',];
        }catch (\Throwable $th){
            return ['status'=>false, 'message'=>$th->getMessage(),];
        }
    }
    
    
    
}