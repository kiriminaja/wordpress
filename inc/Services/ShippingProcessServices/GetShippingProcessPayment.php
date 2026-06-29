<?php
namespace KiriminAjaOfficial\Services\ShippingProcessServices;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use DateTime;
use DateTimeZone;
use KiriminAjaOfficial\Base\BaseService;
class GetShippingProcessPayment extends BaseService{
    
    public $payment_id = 0;
    private $transactionsSummary;
    private $timeZone = '';
    
    public function __construct(){
        $this->timeZone = wp_timezone_string();
    }
    
    public function payment_id($payment_id){
        $this->payment_id = $payment_id;
        return $this;
    }
    
    public function call(){
        $getKiriofPayment = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->getPayment([
            'payment_id'=>$this->payment_id
        ]);
        if (!$getKiriofPayment['status']){ return  self::error([],@$getKiriofPayment['data'] ?? 'Terjadi Kesalahan');}
        
        $paymentRepo = new \KiriminAjaOfficial\Repositories\PaymentRepository();
        $getPayment = $paymentRepo->getPaymentByPaymentId($this->payment_id);
        $remotePayment = @$getKiriofPayment['data']->data;
        $remoteStatusCode = (int) ($remotePayment->status_code ?? 0);
        $remotePayTime = (string) ($remotePayment->pay_time ?? '');
        $hasAwbForPickup = $this->hasAwbForPickup($this->payment_id);

        $remoteIsPaid = $remoteStatusCode >= 100 || $remotePayTime !== '' || $hasAwbForPickup;
        $localMethod = strtolower((string) ($getPayment->method ?? ''));
        $localStatusBefore = (string) ($getPayment->status ?? '');

        if ($getPayment && $remoteIsPaid && ($getPayment->status ?? '') !== 'paid') {
            $paymentRepo->updatePaymentByCallback([
                'changes' => [
                    'status' => 'paid',
                ],
                'condition' => [
                    'pickup_number' => $this->payment_id,
                ],
            ]);
            $getPayment = $paymentRepo->getPaymentByPaymentId($this->payment_id);
        }

        if ($getPayment && $localMethod === 'qris' && !$remoteIsPaid && ! $hasAwbForPickup && ($getPayment->status ?? '') === 'paid') {
            $paymentRepo->updatePaymentByCallback([
                'changes' => [
                    'status' => 'unpaid',
                ],
                'condition' => [
                    'pickup_number' => $this->payment_id,
                ],
            ]);
            $getPayment = $paymentRepo->getPaymentByPaymentId($this->payment_id);
        }

        kiriof_log('info', 'QRIS payment form status resolved.', [
            'pickup_number' => $this->payment_id,
            'local_method' => $localMethod,
            'local_status_before' => $localStatusBefore,
            'local_status_after' => (string) ($getPayment->status ?? ''),
            'remote_status_code' => $remoteStatusCode,
            'remote_pay_time_present' => $remotePayTime !== '',
            'remote_is_paid' => $remoteIsPaid,
            'has_awb_for_pickup' => $hasAwbForPickup,
            'has_qr_content' => !empty($remotePayment->qr_content ?? ''),
        ], 'kiriminaja_request_pickup');

        self::transactionsSummaryProccess();
        return self::success([
            'payment_data'          =>  $remotePayment,
            'payment_in_wc_data'    =>  @$getPayment,
            'count_cod'             =>  @$this->transactionsSummary['count_cod'],
            'sum_fee_cod'           =>  @$this->transactionsSummary['sum_fee_cod'],
            'sum_fee_non_cod'       =>  @$this->transactionsSummary['sum_fee_non_cod'],
            'created_at'            =>  gmdate('Y-m-d H:i:s',strtotime(self::convertTimeToSettingTimezone(@$getKiriofPayment['data']->data->pay_time))),
            'expired_at'            =>  gmdate('Y-m-d H:i:s',strtotime(self::convertTimeToSettingTimezone(@$getKiriofPayment['data']->data->pay_time).'+5minutes')),
        ],'');
    }
    
    private function transactionsSummaryProccess(){
        $transactionRepo = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getTransactionByPickupNumber($this->payment_id);
        $count_cod = 0;
        $count_non_cod = 0;
        $sum_fee_cod = 0;
        $sum_fee_non_cod = 0;
        foreach ($transactionRepo as $transaction){
            if ($this->isCodTransaction($transaction)){
                $count_cod+=1;
            }else{
                $count_non_cod+=1;
                $sum_fee_non_cod+=($transaction->shipping_cost - $transaction->discount_amount) + $transaction->insurance_cost;
            }
        }
        
        $this->transactionsSummary['count_cod']=$count_cod;
        $this->transactionsSummary['count_non_cod']=$count_non_cod;
        $this->transactionsSummary['sum_fee_cod']=$sum_fee_cod;
        $this->transactionsSummary['sum_fee_non_cod']=$sum_fee_non_cod;
    }

    private function isCodTransaction($transaction){
        if ((float) ($transaction->cod_fee ?? 0) > 0) {
            return true;
        }

        if (empty($transaction->wp_wc_order_stat_order_id) || !function_exists('wc_get_order')) {
            return false;
        }

        $order = wc_get_order((int) $transaction->wp_wc_order_stat_order_id);
        if (!$order) {
            return false;
        }

        return 'cod' === strtolower((string) $order->get_payment_method());
    }

    private function hasAwbForPickup($pickupNumber): bool
    {
        $transactions = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getTransactionByPickupNumber($pickupNumber);
        foreach ((array) $transactions as $transaction) {
            if (trim((string) ($transaction->awb ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }
    
    private function convertTimeToSettingTimezone($dateTime){
        if (empty($dateTime)) {
            return gmdate('Y-m-d H:i:s');
        }
        $dt = new DateTime("now", new DateTimeZone($this->timeZone));
        $dt->setTimestamp(strtotime($dateTime));
        $date = $dt->format('Y-m-d H:i:s');
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$tz',[$this->timeZone]);
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$dt',[$dt->format('Y-m-d H:i:s')]);
        
        return $date;
    }
}
