<?php

namespace Inc\Services\UtilServices;

use Inc\Base\BaseService;

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
            $ids[] = $cart['product_id'];
        }
        $wpPostMetaRepo = (new \Inc\Repositories\WpPostMetaRepository())->getRequiredRowsByPostIdsAndMetaKeys($ids,[
            '_weight',
            '_length',
            '_width',
            '_height',
        ]);
        
        $cartProducts = [];
        /** Make Product ARR*/
        foreach ($ids as $id){
            $cartProducts[$id]=[
                'id' => $id
            ];
        }
        
        /** FIll Product Data*/
        foreach ($wpPostMetaRepo as $product){
            if ($product->meta_key === '_weight'){
                $cartProducts[$product->post_id]['weight']  = @$product->meta_value ?? 0;
            }
            if ($product->meta_key === '_length'){
                $cartProducts[$product->post_id]['length']  = @$product->meta_value ?? 0;
            }else{
                $cartProducts[$product->post_id]['length']  = 0;
            }
            if ($product->meta_key === '_width'){
                $cartProducts[$product->post_id]['width']   = @$product->meta_value ?? 0;
            }else {
                $cartProducts[$product->post_id]['width']   = 0;
            }
            if ($product->meta_key === '_height'){
                $cartProducts[$product->post_id]['height']  = @$product->meta_value ?? 0;
            }else {
                $cartProducts[$product->post_id]['height']  = 0;
            }
        }
        
        /** Fill Cart Data*/
        foreach ($this->wc_cart_contents as $cart){
            $cartProducts[$cart['product_id']]['cart_quantity']  = $cart['quantity'];
            $cartProducts[$cart['product_id']]['cart_total']     = $cart['line_total'];
        }
        
        return $cartProducts;
    }
    
    private function getCartsProductAttributeCollection(){
        $heightArr          = [];
        $lengthArr          = [];
        $weightArr          = [];
        $widthArr           = [];
        $transactionValue   = 0;
        foreach ($this->cartsProductAttributes as $p_attr){
            for ($i=1;$i<=intval($p_attr['cart_quantity']);$i++){
                $heightArr[]    = intval($p_attr['height']);
                $lengthArr[]    = intval($p_attr['length']);
                $weightArr[]    = intval($p_attr['weight']);
                $widthArr[]     = intval($p_attr['width']);
            }
            $transactionValue   += $p_attr['cart_total'];
        }
        return [
            'height_highest'    => max($heightArr),
            'height_sum'        => array_sum($heightArr),
            'height_collection' => $heightArr,

            'length_highest'    => max($lengthArr),
            'length_sum'        => array_sum($lengthArr),
            'length_collection' => $lengthArr,

            'width_highest'    => max($widthArr),
            'width_sum'        => array_sum($widthArr),
            'width_collection' => $widthArr,
            
            'weight_sum'        => array_sum($weightArr),
            'weight_collection' => $weightArr,  
            
            'transaction_value' => $transactionValue,
        ];
    }
    
    private function getCartsProcessedAttribute(){
        $cartsProductAttributeCollection = $this->cartsProductAttributeCollection;
        
        /** Check By Height*/
        if (
            $cartsProductAttributeCollection['height_sum'] <= $cartsProductAttributeCollection['length_sum']
            &&
            $cartsProductAttributeCollection['height_sum'] <= $cartsProductAttributeCollection['width_sum']
        ){
            return [
                'weight'        => $cartsProductAttributeCollection['weight_sum'],  
                'height'        => $cartsProductAttributeCollection['height_sum'],  
                'length'        => $cartsProductAttributeCollection['length_highest'],  
                'width'         => $cartsProductAttributeCollection['width_highest'],
                'item_value'    => $cartsProductAttributeCollection['transaction_value'],  
            ];
        }
        
        /** Check By Length*/
        if (
            $cartsProductAttributeCollection['length_sum'] <= $cartsProductAttributeCollection['height_sum']
            &&
            $cartsProductAttributeCollection['length_sum'] <= $cartsProductAttributeCollection['width_sum']
        ){
            return [
                'weight'        => $cartsProductAttributeCollection['weight_sum'],  
                'height'        => $cartsProductAttributeCollection['height_highest'],  
                'length'        => $cartsProductAttributeCollection['length_sum'],  
                'width'         => $cartsProductAttributeCollection['width_highest'],
                'item_value'    => $cartsProductAttributeCollection['transaction_value'],

            ];
        }
        
        /** Check By Width*/
        if (
            $cartsProductAttributeCollection['width_sum'] <= $cartsProductAttributeCollection['height_sum']
            &&
            $cartsProductAttributeCollection['width_sum'] <= $cartsProductAttributeCollection['length_sum']
        ){
            return [
              'weight'      => $cartsProductAttributeCollection['weight_sum'],  
              'height'      => $cartsProductAttributeCollection['height_highest'],  
              'length'      => $cartsProductAttributeCollection['length_highest'],  
              'width'       => $cartsProductAttributeCollection['width_sum'],  
              'item_value'  => $cartsProductAttributeCollection['transaction_value'],  
            ];
        }
        throw new \ErrorException('data is wrong','400',);
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
        ];
    }
}