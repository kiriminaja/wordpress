<?php
namespace KiriminAjaOfficial\Services\UtilServices;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;
use KiriminAjaOfficial\Utils\Volumetric;
class GetWCCartAttributeService extends BaseService{
    private array $wc_cart_contents                 = [];
    private array $cartsProductAttributes           = [];
    private array $cartsProductAttributeCollection  = [];
    private array $cartsProcessedAttribute          = [];
    private array $cartsConvertedAttribute          = [];
    
    public function __construct($payload){
        $this->wc_cart_contents = $payload['wc_cart_contents'];
        return $this;
    }
    
    public function call(){
        $this->cartsProductAttributes           = self::getCartProductAttribute();
        $this->cartsProductAttributeCollection  = self::getCartsProductAttributeCollection();
        $this->cartsProcessedAttribute          = self::getCartsProcessedAttribute();
        $this->cartsConvertedAttribute          = self::getCartsConvertedAttribute();
        
        return self::success($this->cartsConvertedAttribute,'success');
    }
    
    private function getCartProductAttribute(){
        $ids = [];
        foreach ($this->wc_cart_contents as $cart){
            $ids[] = self::getCartItemVolumetricProductId($cart);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (empty($ids)) {
            return [];
        }

        $wpPostMetaRepo = (new \KiriminAjaOfficial\Repositories\WpPostMetaRepository())->getRequiredRowsByPostIdsAndMetaKeys($ids,[
            '_weight',
            '_length',
            '_width',
            '_height',
        ]);
        
        $cartProducts = [];
        /** Make Product ARR*/
        foreach ($ids as $id){
            $cartProducts[$id]=[
                'id' => $id,
                'weight' => 0,
                'length' => 0,
                'width' => 0,
                'height' => 0,
                'cart_quantity' => 0,
                'cart_total' => 0,
            ];
        }
        
        /** FIll Product Data*/
        foreach ($wpPostMetaRepo as $product){
            if ($product->meta_key === '_weight'){
                $cartProducts[$product->post_id]['weight']  = @$product->meta_value ?? 0;
            }else if ($product->meta_key === '_length'){
                $cartProducts[$product->post_id]['length']  = @$product->meta_value ?? 0;
            }else if ($product->meta_key === '_width'){
                $cartProducts[$product->post_id]['width']   = @$product->meta_value ?? 0;
            }else if ($product->meta_key === '_height'){
                $cartProducts[$product->post_id]['height']  = @$product->meta_value ?? 0;
            }
        }
        
        /** Fill Cart Data*/
        foreach ($this->wc_cart_contents as $cart){
            $product_id = self::getCartItemVolumetricProductId($cart);

            if (empty($product_id) || !isset($cartProducts[$product_id])) {
                continue;
            }

            $cartProducts[$product_id]['cart_quantity'] += intval($cart['quantity'] ?? 0);
            $cartProducts[$product_id]['cart_total'] += (float) ($cart['line_total'] ?? 0);
        }
        
        return $cartProducts;
    }

    private function getCartItemVolumetricProductId($cart){
        $variation_id = intval($cart['variation_id'] ?? 0);
        if ($variation_id > 0) {
            return $variation_id;
        }

        return intval($cart['product_id'] ?? 0);
    }
    
    private function getCartsProductAttributeCollection(){
        $volumetricItems    = [];
        $weightSum          = 0;
        $transactionValue   = 0;
        foreach ($this->cartsProductAttributes as $p_attr){
            $quantity = intval($p_attr['cart_quantity']);
            if ($quantity < 1) {
                $quantity = 1;
            }

            $volumetricItems[] = [
                'qty' => $quantity,
                'length' => (float) $p_attr['length'],
                'width' => (float) $p_attr['width'],
                'height' => (float) $p_attr['height'],
            ];

            $weightSum += (float) $p_attr['weight'] * $quantity;
            $transactionValue   += $p_attr['cart_total'];
        }

        $discountedTotal = $this->getCartTotalAfterDiscount($transactionValue);
        $discountAmount = max(0, (float) $transactionValue - (float) $discountedTotal);
        $pricingItemValue = (float) $discountedTotal;
        if ($pricingItemValue <= 0) {
            // Pricing API may reject zero-value item_value for fully discounted carts.
            $pricingItemValue = 1000;
        }

        return [
            'volumetric_items'  => $volumetricItems,
            'weight_sum'        => $weightSum,
            'transaction_value_before_discount' => (float) $transactionValue,
            'transaction_value_after_discount' => (float) $discountedTotal,
            'cart_discount_amount' => (float) $discountAmount,
            'pricing_item_value' => (float) $pricingItemValue,
            'cart_discount_description' => $this->getCouponDescription(),
        ];
    }
    
    private function getCartsProcessedAttribute(){
        $cartsProductAttributeCollection = $this->cartsProductAttributeCollection;
        $volumetricDimensions = Volumetric::calculateSmallestBox($cartsProductAttributeCollection['volumetric_items'] ?? []);

        return [
            'weight'      => $cartsProductAttributeCollection['weight_sum'],
            'height'      => $volumetricDimensions['height'],
            'length'      => $volumetricDimensions['length'],
            'width'       => $volumetricDimensions['width'],
            'item_value'  => $cartsProductAttributeCollection['pricing_item_value'],
            'cart_total_before_discount' => $cartsProductAttributeCollection['transaction_value_before_discount'],
            'cart_total_after_discount' => $cartsProductAttributeCollection['transaction_value_after_discount'],
            'cart_discount_amount' => $cartsProductAttributeCollection['cart_discount_amount'],
            'cart_discount_description' => $cartsProductAttributeCollection['cart_discount_description'],
        ];
    }
    
    private function getCartsConvertedAttribute(){
        $weightMultiplier = 1;
        $dimensionMultiplier = 1;
        $weight_unit = get_option('woocommerce_weight_unit') ?? '';
        $dimension_unit = get_option('woocommerce_dimension_unit') ?? '';
        /** convert weight to gr */
        switch ($weight_unit) {
            case "kg":
                $weightMultiplier = 1000;
                break;
            case "g":
                $weightMultiplier = 1;
                break;
            case "lbs":
                $weightMultiplier = 453.592;
                break;
            case "oz":
                $weightMultiplier = 28.3495;
                break;
            default:
                $weightMultiplier = 1;
        }
        /** convert dimension to cm*/
        switch ($dimension_unit) {
            case "m":
                $dimensionMultiplier = 100;
                break;
            case "cm":
                $dimensionMultiplier = 1;
                break;
            case "mm":
                $dimensionMultiplier = 0.1;
                break;
            case "in":
                $dimensionMultiplier = 2.54;
                break;
            case "yd":
                $dimensionMultiplier = 91.44;
                break;
            default:
                $dimensionMultiplier = 1;
        }
        return [
            'weight'      => $this->cartsProcessedAttribute['weight'] * $weightMultiplier,
            'height'      => $this->cartsProcessedAttribute['height'] * $dimensionMultiplier,
            'length'      => $this->cartsProcessedAttribute['length'] * $dimensionMultiplier,
            'width'       => $this->cartsProcessedAttribute['width'] * $dimensionMultiplier,
            'item_value'  => $this->cartsProcessedAttribute['item_value'],
            'cart_total_before_discount' => (float) ($this->cartsProcessedAttribute['cart_total_before_discount'] ?? 0),
            'cart_total_after_discount' => (float) ($this->cartsProcessedAttribute['cart_total_after_discount'] ?? 0),
            'cart_discount_amount' => (float) ($this->cartsProcessedAttribute['cart_discount_amount'] ?? 0),
            'cart_discount_description' => (string) ($this->cartsProcessedAttribute['cart_discount_description'] ?? ''),
        ];
    }

    private function getCartTotalAfterDiscount($fallbackTotal){
        $fallbackTotal = (float) $fallbackTotal;
        if (!function_exists('WC')) {
            return $fallbackTotal;
        }

        $wc = WC();
        if (!$wc || !isset($wc->cart) || !$wc->cart) {
            return $fallbackTotal;
        }

        $rawTotal = $wc->cart->get_total('edit');
        if ($rawTotal === '' || $rawTotal === null) {
            return $fallbackTotal;
        }

        return (float) $rawTotal;
    }

    private function getCouponDescription(){
        if (!function_exists('WC')) {
            return '';
        }

        $wc = WC();
        if (!$wc || !isset($wc->cart) || !$wc->cart) {
            return '';
        }

        $coupons = $wc->cart->get_coupons();
        $codes = is_array($coupons) ? array_keys($coupons) : [];
        if (empty($codes)) {
            return '';
        }

        $codes = array_filter(array_map('sanitize_text_field', $codes));
        return implode(', ', $codes);
    }
}
