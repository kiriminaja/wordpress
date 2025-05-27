<?php

namespace Inc\Utils;

class WeightConverter
{
    /**
     * Convert weight to grams based on WooCommerce weight unit setting.
     *
     * @param float|int $weight The weight value to convert.
     * @param string|null $unit The unit of the weight (kg, g, lbs, oz). If null, will use WooCommerce setting.
     * @return float Converted weight in grams.
     */
    public static function toGram($weight, $unit = null)
    {
        if ($unit === null) {
            $unit = get_option('woocommerce_weight_unit') ?? 'g';
        }
        switch (strtolower($unit)) {
            case 'kg':
                return $weight * 1000;
            case 'g':
                return $weight;
            case 'lbs':
                return $weight * 453.592;
            case 'oz':
                return $weight * 28.3495;
            default:
                return $weight;
        }
    }
}
