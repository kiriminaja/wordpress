<?php
namespace KiriminAjaOfficial\Services\ShippingProcessServices;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use DateTime;
use DateTimeZone;
use KiriminAjaOfficial\Base\BaseService;
use KiriminAjaOfficial\Repositories\KiriminajaApiRepository;
use KiriminAjaOfficial\Repositories\PaymentRepository;
use KiriminAjaOfficial\Repositories\TransactionRepository;
class GetShippingProcessPayment extends BaseService{
    
    public $payment_id = 0;
    private $transactionsSummary;
    private $timeZone = '';
    private KiriminajaApiRepository $apiRepository;
    private PaymentRepository $paymentRepository;
    private TransactionRepository $transactionRepository;
    
    public function __construct(
        KiriminajaApiRepository $apiRepository,
        PaymentRepository $paymentRepository,
        TransactionRepository $transactionRepository
    ){
        $this->timeZone = wp_timezone_string();
        $this->apiRepository         = $apiRepository;
        $this->paymentRepository     = $paymentRepository;
        $this->transactionRepository = $transactionRepository;
    }
    
    public function payment_id($payment_id){
        $this->payment_id = $payment_id;
        return $this;
    }
    
    public function call(){
        $getKiriofPayment = $this->apiRepository->getPayment([
            'payment_id'=>$this->payment_id
        ]);
        if (!$getKiriofPayment['status']){ return  self::error([],@$getKiriofPayment['data'] ?? 'Terjadi Kesalahan');}
        
        $paymentRepo = $this->paymentRepository;
        $getPayment = $paymentRepo->getPaymentByPaymentId($this->payment_id);
        $remotePayment = @$getKiriofPayment['data']->data;
        $remoteStatusCode = trim((string) ($remotePayment->status_code ?? ''));
        $remotePayTime = (string) ($remotePayment->pay_time ?? '');
        $remotePaidAt = (string) ($remotePayment->paid_at ?? '');
        $hasAwbForPickup = $this->hasAwbForPickup($this->payment_id);
        $localMethod = strtolower((string) ($getPayment->method ?? ''));
        $remotePaymentStatus = strtolower((string) ($remotePayment->payment_status ?? $remotePayment->status ?? ''));
        $remoteHasPaidTimestamp = $remotePaidAt !== '';
        $remoteHasPaidStatus = in_array($remotePaymentStatus, ['paid', 'settlement', 'settled', 'success'], true);
        $remoteIsPaid = $localMethod === 'qris'
            ? ($remoteHasPaidTimestamp || $remoteHasPaidStatus)
            : ($remoteStatusCode === '0' || $remotePayTime !== '' || $remoteHasPaidTimestamp || $remoteHasPaidStatus || $hasAwbForPickup);

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

        if ($getPayment && $localMethod === 'qris' && !$remoteIsPaid && ($getPayment->status ?? '') === 'paid') {
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
        $transactionRepo = $this->transactionRepository->getTransactionByPickupNumber($this->payment_id);
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
        $transactions = $this->transactionRepository->getTransactionByPickupNumber($pickupNumber);
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
        
        return $date;
    }
}
