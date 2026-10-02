<?php
namespace KiriminAjaOfficial\Services\CheckoutServices;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;
use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Repositories\TransactionRepository;
use KiriminAjaOfficial\Repositories\WpPostMetaRepository;
use KiriminAjaOfficial\Services\CheckoutServiceFactory;
use KiriminAjaOfficial\Services\KiriminAja\GenerateOrderId;
use KiriminAjaOfficial\Services\ShipmentLocationService;
use WC_Order_Item_Fee;
class CreateTransactionService extends BaseService{
    
    private $payload;
    private $checkoutCalcCache;
    private TransactionRepository $transaction_repository;
    private SettingRepository $setting_repository;
    private WpPostMetaRepository $post_meta_repository;
    private CodDeficitService $cod_deficit_service;
    private ShipmentLocationService $shipment_location_service;
    private GenerateOrderId $order_id_generator;
    private CheckoutServiceFactory $checkout_service_factory;
    private $expressLease;
    private $expressLock;
    
    
    /**
    $payload array
    keys :
    order_id
    checkout_post_data
    kiriof_destination_area
    kiriof_destination_area_name
    kiriof_expedition
    insurance
    payment_method
    wc_cart_contents
     */
    public function __construct(
        $payload,
        TransactionRepository $transaction_repository,
        SettingRepository $setting_repository,
        WpPostMetaRepository $post_meta_repository,
        CodDeficitService $cod_deficit_service,
        ShipmentLocationService $shipment_location_service,
        GenerateOrderId $order_id_generator,
        CheckoutServiceFactory $checkout_service_factory
    ){
        $this->payload = $payload;
        $this->payload['wc_cart_contents'] = $this->normalizeCartContents( $payload['wc_cart_contents'] ?? array() );
        $this->transaction_repository    = $transaction_repository;
        $this->setting_repository        = $setting_repository;
        $this->post_meta_repository      = $post_meta_repository;
        $this->cod_deficit_service       = $cod_deficit_service;
        $this->shipment_location_service = $shipment_location_service;
        $this->order_id_generator        = $order_id_generator;
        $this->checkout_service_factory  = $checkout_service_factory;
        return $this;
    }
    
    
    public function call(){
        if ( ! empty( $this->payload['blocks_validated'] ) ) {
            return $this->callVerifiedExpress();
        }
        return $this->callTransaction();
    }

    private function callTransaction(){
        try {
            
            $checkoutCalc = $this->getCheckoutCalculation();
            if (!$checkoutCalc['status']){ 
                return self::error([],$checkoutCalc['message']);
            }
            
            $requiredPostMeta = $this->getRequiredPostMeta();
            if (!$requiredPostMeta['status']){ 
                return self::error([],$requiredPostMeta['message']);
            }
            if ( ! empty( $this->payload['destination_zipcode'] ) ) {
                $requiredPostMeta['data']['_kiriof_checkout_postcode'] = sanitize_text_field( (string) $this->payload['destination_zipcode'] );
            }
            /** Generating Payload*/
            $calcResult = $checkoutCalc['data']['calculation_result'];
            $cartsAttr = $checkoutCalc['data']['carts_attribute'];
            $forceInsurance = @$calcResult['selected_expedition']->force_insurance;
            
            $is_insurance = ! empty( $this->payload['blocks_validated'] )
                ? ! empty( $this->payload['is_insurance'] ) || ! empty( $forceInsurance )
                : $this->isInsuranceRequested( $forceInsurance );

            $insurance_cost = $is_insurance
                ? $calcResult['insurance_amt'] 
                : 0;
            $transactionValue = (float) ($calcResult['cart_total_after_discount'] ?? $calcResult['cart_total_amt'] ?? 0);
            $shippingCostRaw = (float) ($calcResult['ongkir_fee_raw'] ?? 0);
            $codFee = (float) ($calcResult['cod_amt'] ?? 0);
            $isCod = !empty($this->payload['is_cod']);

            // Determine deficit status via CodDeficitService.
            $expeditionParts = $this->payload['kiriof_expedition'] ? explode('_', $this->payload['kiriof_expedition'], 2) : ['', ''];
            $deficitResult = $this->cod_deficit_service->detect([
                'is_cod'               => $isCod,
                'total_cod'            => $transactionValue,
                'shipping_cost'        => $shippingCostRaw,
                'insurance_fee'        => (float) $insurance_cost,
                'cod_fee'              => $codFee,
                'admin_fee'            => 0,
                'item_price'           => $transactionValue,
                'courier_code'         => $expeditionParts[0],
                'courier_service_code' => $expeditionParts[1] ?? '',
                'discount_amount'      => (float) ($calcResult['discount_amt'] ?? 0),
            ]);
            $isDeficit  = $deficitResult['isDeficit'] ? 1 : 0;
            $codMinimum = $deficitResult['codMinimum'];
            $wooDiscountAmount = (float) ($calcResult['woo_discount_amount'] ?? 0);
            if ($wooDiscountAmount <= 0 && !empty($this->payload['woo_discount_amount'])) {
                $wooDiscountAmount = (float) $this->payload['woo_discount_amount'];
            }
            $wooDiscountDescription = (string) ($calcResult['woo_discount_description'] ?? '');
            if ($wooDiscountDescription === '' && !empty($this->payload['woo_discount_description'])) {
                $wooDiscountDescription = (string) $this->payload['woo_discount_description'];
            }
            // $expeditionParts already computed above for deficit detection.
            $shipmentLocationService = $this->shipment_location_service;
            $checkoutOriginLocation  = $shipmentLocationService->getDefaultLocation();
            $checkoutOriginSnapshot  = $shipmentLocationService->locationToOrigin( $checkoutOriginLocation );
            $payload = [
                'order_id'                      => $this->payload['express_invoice'] ?? $this->order_id_generator->call(),
                'shipping_info'                 => wp_json_encode($requiredPostMeta['data']),
                'destination_sub_district_id'   => $this->payload['kiriof_destination_area'],
                'destination_sub_district'      => $this->payload['kiriof_destination_area_name'],
                'status'                        => 'new',
                'service'                       => $expeditionParts[0],
                'service_name'                  => $expeditionParts[1] ?? '',
                'weight'                        => $cartsAttr['weight'],
                "length"                        => $cartsAttr['length'],
                "width"                         => $cartsAttr['width'],
                "height"                        => $cartsAttr['height'],
                'shipping_cost'                 => $shippingCostRaw,
                'insurance_cost'                => $insurance_cost,
                'cod_fee'                       => $codFee,
                'transaction_value'             => $transactionValue,
                'created_at'                    => gmdate('Y-m-d H:i:s'),
                'wp_wc_order_stat_order_id'     => $this->payload['order_id'],
                'discount_amount'               => $calcResult['discount_amt'] ?? null,
                'discount_percentage'           => $calcResult['discount_percentage'] ?? null,
                'woocommerce_discount_amount'   => $wooDiscountAmount,
                'woocommerce_discount_description' => $wooDiscountDescription,
                'is_deficit'                    => $isDeficit,
                'cod_minimum'                   => $isCod ? $codMinimum : null,
                'shipment_location_id'          => (int) ( $checkoutOriginSnapshot['location_id'] ?? 0 ),
                'shipment_location_snapshot'    => ! empty( $checkoutOriginSnapshot ) ? wp_json_encode( $checkoutOriginSnapshot ) : null,
            ];
            
            if ( ! empty( $this->payload['blocks_validated'] ) ) {
                $order = wc_get_order( $this->payload['order_id'] );
                // Repository DECIMAL columns normalize omitted discounts to zero.
                $payload['discount_amount'] = (float) ( $payload['discount_amount'] ?? 0 );
                $payload['discount_percentage'] = (float) ( $payload['discount_percentage'] ?? 0 );
                $saved_payload = $order->get_meta( '_kiriof_express_transaction_payload', true );
                if ( is_array( $saved_payload ) && ! empty( $saved_payload ) ) {
                    if ( (string) ( $saved_payload['order_id'] ?? '' ) !== (string) $this->payload['express_invoice']
                        || (string) ( $saved_payload['service'] ?? '' ) !== (string) $payload['service']
                        || (string) ( $saved_payload['service_name'] ?? '' ) !== (string) $payload['service_name']
                        || (int) ( $saved_payload['destination_sub_district_id'] ?? 0 ) !== (int) $payload['destination_sub_district_id'] ) {
                        return self::error( array(), 'Express transaction could not be verified.' );
                    }
                    foreach ( array( 'weight', 'length', 'width', 'height', 'shipping_cost', 'insurance_cost', 'cod_fee', 'transaction_value', 'discount_amount', 'woocommerce_discount_amount' ) as $field ) {
                        if ( ! $this->equalExpressAmount( $saved_payload[$field] ?? null, $payload[$field] ?? null ) ) {
                            return self::error( array(), 'Express transaction could not be verified.' );
                        }
                    }
                    $payload = $saved_payload;
                } else {
                    $order->update_meta_data( '_kiriof_express_transaction_payload', $payload );
                    $order->save_meta_data();
                }
                $existing = $this->transaction_repository->getTransactionByWCOrderId( $this->payload['order_id'] );
                // A repository error is not evidence that a transaction is absent.
                if ( false === $existing ) {
                    return self::error( array(), 'Express transaction could not be verified.' );
                }
                if ( $existing ) {
                    foreach ( array( 'order_id', 'wp_wc_order_stat_order_id', 'service', 'service_name', 'destination_sub_district_id', 'weight', 'length', 'width', 'height', 'shipping_cost', 'insurance_cost', 'cod_fee', 'transaction_value', 'discount_amount', 'discount_percentage', 'woocommerce_discount_amount', 'shipment_location_id' ) as $field ) {
                        if ( ! property_exists( $existing, $field ) || ( is_numeric( $payload[$field] ?? null )
                            ? ! $this->equalExpressAmount( $existing->$field, $payload[$field] )
                            : (string) $existing->$field !== (string) ( $payload[$field] ?? '' ) ) ) {
                            return self::error( array(), 'Express transaction could not be verified.' );
                        }
                    }
                    return self::success( array(), 'success' );
                }
                if ( ! $this->ownsExpressLease() ) {
                    return self::error( array(), 'Express transaction could not be verified.' );
                }
            } else {
                /** Classic checkout retains its existing fee calculation behavior. */
                $this->updateWcTotalOrder($checkoutCalc);
            }
            
            $createTransactionRepo = $this->transaction_repository->createTransaction($payload);
            
            /** Save in Log Transaction*/
            if ( empty( $this->payload['blocks_validated'] ) ) {
                update_post_meta( $this->payload['order_id'], 'log_after_checkout_order', compact('payload','createTransactionRepo') );
            }
            
            if (!$createTransactionRepo){
                return self::error([],'fail creating transaction');
            }

            // Add WC order note and meta when deficit is detected.
            if ( $isDeficit ) {
                $wcOrder = wc_get_order( $this->payload['order_id'] );
                if ( $wcOrder ) {
                    $wcOrder->add_order_note(
                        __( 'COD order flagged as deficit — total COD below minimum threshold.', 'kiriminaja-official' )
                    );
                    $wcOrder->update_meta_data( 'cod-deficit', '1' );
                    if ( ! empty( $this->payload['blocks_validated'] ) ) {
                        $wcOrder->save_meta_data();
                    } else {
                        $wcOrder->save();
                    }
                }
            }

            return self::success([],'success');
        }catch (\Throwable $th){
            if ( empty( $this->payload['blocks_validated'] ) ) {
                (new \KiriminAjaOfficial\Base\BaseInit())->logThis('err',[$th->getMessage()]);
            }
            return self::error([],'fail creating transaction');
        }
    }
    /** Store API consumes the saved quote, never reprices or changes payable amounts. */
    private function callVerifiedExpress() {
        $order = null;
        $acquired = false;
        try {
            $this->expressLock = '_kiriof_express_order_lock_' . absint( $this->payload['order_id'] );
            $this->expressLease = array( 'owner' => bin2hex( random_bytes( 16 ) ), 'expires' => time() + 180 );
            $acquired = add_option( $this->expressLock, $this->expressLease, '', false );
            if ( ! $acquired ) {
                $previous = get_option( $this->expressLock );
                if ( is_array( $previous ) && isset( $previous['owner'], $previous['expires'] ) && $previous['expires'] < time() && $this->deleteExpressLease( $previous ) ) {
                    $acquired = add_option( $this->expressLock, $this->expressLease, '', false );
                }
            }
            if ( ! $acquired ) {
                return $this->expressFailure();
            }
            // Read order metadata after the lock: a waiting retry must not use stale data.
            $order = wc_get_order( $this->payload['order_id'] );
            if ( ! $order ) { return $this->expressFailure(); }
            $order->read_meta_data( true );
            $context = $order->get_meta( '_kiriof_express_validated', true );
            $calculation = $context['validated_calculation'] ?? null;
            if ( ! is_array( $context ) || ! is_array( $calculation ) || empty( $calculation['calculation_result'] ) || empty( $calculation['carts_attribute'] ) ) {
                return $this->expressFailure( $order );
            }
            $calc = $calculation['calculation_result'];
            if ( ! isset( $calc['calc_total_amt'] ) || ! is_array( $calc['selected_expedition'] ?? null ) ) { return $this->expressFailure( $order ); }
            $this->payload['kiriof_expedition'] = $context['expedition'];
            $this->payload['kiriof_destination_area'] = $context['destination_id'];
            $this->payload['kiriof_destination_area_name'] = (string) $order->get_meta( '_kiriof_checkout_destination_area_name', true );
            $this->payload['is_cod'] = 'cod' === $context['payment_method'];
            $this->payload['is_insurance'] = ! empty( $context['is_insurance'] );
            $logistics_fees = 0.0;
            foreach ( $order->get_items( 'fee' ) as $fee ) {
                if ( in_array( $fee->get_meta( '_kiriof_fee_type', true ), array( 'insurance', 'cod_fee' ), true ) || in_array( $fee->get_name(), array( 'Insurance', 'COD Fee', __( 'Insurance', 'kiriminaja-official' ), __( 'COD Fee', 'kiriminaja-official' ) ), true ) ) {
                    $logistics_fees += (float) $fee->get_total();
                }
            }
            $expected = (float) $calc['calc_total_amt'] + (float) $order->get_total_tax() + (float) $order->get_total_fees() - $logistics_fees;
            if ( ! $this->equalExpressAmount( $order->get_total(), $expected ) || (string) $order->get_payment_method() !== (string) $context['payment_method'] ) {
                return $this->expressFailure( $order );
            }
            $calculation['calculation_result']['selected_expedition'] = (object) $calc['selected_expedition'];
            $this->checkoutCalcCache = array( 'status' => true, 'data' => $calculation );
            $invoice = (string) $order->get_meta( '_kiriof_express_invoice', true );
            if ( '' === $invoice ) {
                $invoice = (string) $this->order_id_generator->call();
                if ( '' === $invoice ) { return $this->expressFailure( $order ); }
                $order->update_meta_data( '_kiriof_express_invoice', $invoice );
                $order->save_meta_data();
            }
            $this->payload['express_invoice'] = $invoice;
            $result = $this->callTransaction();
            if ( 200 !== $result->status ) { return $this->expressFailure( $order ); }
            $order->delete_meta_data( '_kiriof_express_transaction_error' );
            $order->save_meta_data();
            return $result;
        } catch ( \Throwable $error ) {
            return $this->expressFailure( $order );
        } finally {
            if ( $acquired ) { $this->deleteExpressLease( $this->expressLease ); }
        }
    }

    private function equalExpressAmount( $left, $right ): bool {
        return is_numeric( $left ) && is_numeric( $right ) && is_finite( (float) $left ) && is_finite( (float) $right ) && abs( (float) $left - (float) $right ) < 0.005;
    }

    private function ownsExpressLease(): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Ownership must bypass stale object caches.
        $value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $this->expressLock ) );
        return $this->expressLease['expires'] > time() && maybe_serialize( $this->expressLease ) === $value;
    }

    /** Compare-and-delete prevents an expired worker from deleting a newer lease. */
    private function deleteExpressLease( array $lease ): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic conditional lease release.
        $deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND BINARY option_value = %s", $this->expressLock, maybe_serialize( $lease ) ) );
        if ( 1 === $deleted ) {
            wp_cache_delete( $this->expressLock, 'options' );
            wp_cache_delete( 'notoptions', 'options' );
            return true;
        }
        return false;
    }

    private function expressFailure( $order = null ) {
        $message = __( 'Express shipment could not be saved. Please retry checkout or contact the store.', 'kiriminaja-official' );
        if ( $order ) {
            try {
                if ( 'transaction_save_failed' !== $order->get_meta( '_kiriof_express_transaction_error', true ) ) {
                    $order->update_meta_data( '_kiriof_express_transaction_error', 'transaction_save_failed' );
                    $order->save_meta_data();
                    $order->add_order_note( $message );
                }
            } catch ( \Throwable $error ) { /* Keep the fixed checkout failure visible even when metadata storage fails. */ }
        }
        return self::error( array(), $message, 503, 'kiriof_express_transaction_failed' );
    }

    private function normalizeCartContents( $cart_contents ){
        if ( is_array( $cart_contents ) && ! empty( $cart_contents ) ) {
            return $cart_contents;
        }

        return $this->getOrderCartContentsFallback();
    }

    private function getOrderCartContentsFallback(){
        if ( empty( $this->payload['order_id'] ) || ! function_exists( 'wc_get_order' ) ) {
            return array();
        }

        $order = wc_get_order($this->payload['order_id']);
        if ( ! $order ) {
            return array();
        }

        $cart_contents = array();
        foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
            $product_id = $item->get_variation_id() ?: $item->get_product_id();
            if ( empty( $product_id ) ) {
                continue;
            }

            $cart_contents[ $item_id ] = array(
                'product_id' => $product_id,
                'quantity'   => $item->get_quantity(),
                'line_total' => $item->get_total(),
            );
        }

        return $cart_contents;
    }

    private function updateWcTotalOrder($checkoutCalc){
        $order = wc_get_order($this->payload['order_id']);
        if (!$order) {
            return;
        }
        $calcResult = $checkoutCalc['data']['calculation_result'];
        $forceInsurance = @$calcResult['selected_expedition']->force_insurance ?? 0;
        $is_insurance = $this->isInsuranceRequested( $forceInsurance );
        $is_cod = $this->payload['is_cod'] ?? 0;
        
        if ($is_cod && ! $this->orderHasFeeItem($order, 'COD Fee')) {
            $cod_amt = $calcResult['cod_amt'];
            $cod_fee = new WC_Order_Item_Fee();
            $cod_fee->set_name('COD Fee');
            $cod_fee->set_amount($cod_amt);
            $cod_fee->set_total($cod_amt);
            $cod_fee->add_meta_data( '_kiriof_fee_type', 'cod_fee', true );
            $order->add_item($cod_fee); 
        }
        if ($is_insurance && ! $this->orderHasFeeItem($order, 'Insurance')) {
            $insurance_amt = $calcResult['insurance_amt'];
            $insurance_fee = new WC_Order_Item_Fee();
            $insurance_fee->set_name('Insurance');
            $insurance_fee->set_amount($insurance_amt);
            $insurance_fee->set_total($insurance_amt);
            $insurance_fee->add_meta_data( '_kiriof_fee_type', 'insurance', true );
            $order->add_item($insurance_fee);
        }
        
        $order->calculate_totals();
        $order->save();
    }

    private function orderHasFeeItem($order, $feeName){
        $expected_meta = ( 'Insurance' === $feeName ) ? 'insurance' : 'cod_fee';

        foreach ($order->get_items('fee') as $feeItem) {
            if ( $feeItem->get_meta( '_kiriof_fee_type' ) === $expected_meta ) {
                return true;
            }

            $itemName = trim( (string) $feeItem->get_name() );
            if ( false !== stripos( $itemName, $feeName ) ) {
                return true;
            }
        }

        return false;
    }

    private function isInsuranceRequested($forceInsurance = 0){
        $insurance_setting = $this->setting_repository->getSettingByKey('enable_insurance');
        $global_insurance  = ( $insurance_setting && 'yes' === $insurance_setting->value );

        return ! empty( $this->payload['checkout_post_data']['kiriof_insurance'] )
            || ! empty( $this->payload['is_insurance'] )
            || ! empty( $forceInsurance )
            || $global_insurance;
    }
    
    private function getRequiredPostMeta(){
        try {
            if ( ! empty( $this->payload['blocks_validated'] ) ) {
                $order = wc_get_order( $this->payload['order_id'] );
                $data = array();
                // Use WooCommerce getters for HPOS; never serialize all order metadata.
                foreach ( array( 'billing', 'shipping' ) as $kind ) {
                    foreach ( array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone', 'email' ) as $field ) {
                        $getter = 'get_' . $kind . '_' . $field;
                        if ( is_callable( array( $order, $getter ) ) ) { $data[ '_' . $kind . '_' . $field ] = $order->$getter(); }
                    }
                }
                $data['_kiriof_checkout_postcode'] = $order->get_shipping_postcode();
                return array( 'status' => true, 'data' => $data );
            }
            $postMetaRepo = $this->post_meta_repository->getRequiredRowsByPostId($this->payload['order_id']);
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$postMetaRepo',[$postMetaRepo]);
            
            // Use array_column for more efficient mapping
            $returnArr = [];
            if (is_array($postMetaRepo) && !empty($postMetaRepo)) {
                foreach ($postMetaRepo as $postMeta) {
                    $returnArr[$postMeta->meta_key] = $postMeta->meta_value;
                }
            }
            
            return [
                'status'    => true,
                'msg'       => 'success',
                'data'      => $returnArr
            ];            
        }catch (\Throwable $th){
            return [
                'status'    => false,
                'msg'       => $th->getMessage(),
                'data'      => []
            ];
        }
    }
    
    private function getCheckoutCalculation(){
        // Return cached result if already calculated
        if ($this->checkoutCalcCache !== null) {
            return $this->checkoutCalcCache;
        }
        
        $this->payload['is_insurance'] = $this->isInsuranceRequested() ? 1 : 0;
        
        $service = $this->checkout_service_factory->calculation([
            'destination_area_id'   => $this->payload['kiriof_destination_area'],
            'expedition'            => $this->payload['kiriof_expedition'],
            'is_insurance'          => $this->payload['is_insurance'],
            'is_cod'                => $this->payload['is_cod'] ?? 0,
            'wc_cart_contents'      => $this->payload['wc_cart_contents'],
        ])->call();
        
        if ($service->status !== 200){
            $result = [
                'status'    => false,
                'msg'       => $service->message ?? 'Something is wrong',
                'data'      => []
            ];
        } else {
            $result = [
                'status'    => true,
                'msg'       => 'success',
                'data'      => $service->data
            ];
        }
        
        // Cache the result
        $this->checkoutCalcCache = $result;
        return $result;
    }
}
